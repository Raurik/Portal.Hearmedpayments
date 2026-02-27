<?php
/**
 * HearMed Orders Module — v3.0 (real DB schema)
 *
 * Shortcode: [hearmed_orders]
 *
 * ═══════════════════════════════════════════════════════════
 * TABLE MAP (all fully qualified)
 * ─────────────────────────────────────────────────────────
 *  hearmed_core.orders              main order record
 *  hearmed_core.order_items         line items
 *  hearmed_core.order_status_history audit trail of status changes
 *  hearmed_core.invoices            financial document (separate!)
 *  hearmed_core.invoice_items       invoice line items
 *  hearmed_core.payments            payment records
 *  hearmed_core.fitting_queue       fitting queue (already exists)
 *  hearmed_core.patient_devices     serial numbers (already exists)
 *  hearmed_core.patient_timeline    event log
 *  hearmed_core.patients            patient data
 *  hearmed_reference.staff          staff data
 *  hearmed_reference.clinics        clinic data
 *  hearmed_reference.products       product catalogue
 *  hearmed_reference.services       service catalogue
 *
 * ═══════════════════════════════════════════════════════════
 * ORDER STATUS FLOW (current_status column, has CHECK constraint)
 * ─────────────────────────────────────────────────────────
 *  'Awaiting Approval'  Dispenser creates order → C-Level notified
 *  'Approved'           C-Level approves → Admin notified
 *  'Ordered'            Admin places with supplier
 *  'Received'           Aid arrives in clinic → Dispenser notified
 *                       → Serial numbers entered → patient_devices row
 *  'Awaiting Fitting'   Serials done → appears in fitting_queue
 *  'Complete'           Patient fitted + paid → invoice marked Paid
 *                       → payment row created → QBO sync fires
 *  'Cancelled'          Rejected at any stage
 *
 * @package HearMed_Portal
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ---------------------------------------------------------------------------
// Router
// ---------------------------------------------------------------------------
function hm_orders_render() {
    if ( ! HearMed_Auth::is_logged_in() ) {
        return '<div class="hm-notice hm-notice--error">Please log in to access Orders.</div>';
    }
    $action   = sanitize_key( $_GET['hm_action'] ?? 'list' );
    $order_id = intval( $_GET['order_id'] ?? 0 );

    switch ( $action ) {
        case 'create':   echo HearMed_Orders::render_create();              break;
        case 'view':     echo HearMed_Orders::render_view( $order_id );     break;
        case 'serials':  echo HearMed_Orders::render_serials( $order_id );  break;
        case 'complete': echo HearMed_Orders::render_complete( $order_id ); break;
        case 'print':    echo HearMed_Orders::render_order_sheet( $order_id ); break;
        default:         echo HearMed_Orders::render_list();
    }
}

// ---------------------------------------------------------------------------
// Main class
// ---------------------------------------------------------------------------
class HearMed_Orders {

    // ═══════════════════════════════════════════════════════════════════════
    // LIST VIEW
    // ═══════════════════════════════════════════════════════════════════════
    public static function render_list() {
        $db     = HearMed_DB::instance();
        $role   = HearMed_Auth::current_role();
        $clinic = HearMed_Auth::current_clinic();

        $status_filter = sanitize_text_field( $_GET['status'] ?? 'all' );
        $search        = sanitize_text_field( $_GET['search'] ?? '' );

        $where_parts = [];
        $params      = [];
        $i           = 1;

        if ( $clinic ) {
            $where_parts[] = "o.clinic_id = \${$i}"; $params[] = $clinic; $i++;
        }
        if ( $status_filter && $status_filter !== 'all' ) {
            $where_parts[] = "o.current_status = \${$i}"; $params[] = $status_filter; $i++;
        }
        if ( $search ) {
            $where_parts[] = "(p.first_name ILIKE \${$i} OR p.last_name ILIKE \${$i} OR o.order_number ILIKE \${$i})";
            $params[] = '%' . $search . '%'; $i++;
        }

        $where = $where_parts ? 'WHERE ' . implode( ' AND ', $where_parts ) : '';

        $orders = $db->get_results(
            "SELECT o.id, o.order_number, o.current_status, o.created_at,
                    o.grand_total, o.prsi_amount, o.prsi_applicable,
                    p.first_name, p.last_name, p.id AS patient_id,
                    c.clinic_name,
                    CONCAT(s.first_name,' ',s.last_name) AS created_by_name
             FROM hearmed_core.orders o
             JOIN hearmed_core.patients p       ON p.id = o.patient_id
             JOIN hearmed_reference.clinics c   ON c.id = o.clinic_id
             LEFT JOIN hearmed_reference.staff s ON s.id = o.staff_id
             {$where}
             ORDER BY o.created_at DESC
             LIMIT 150",
            $params
        );

        // Tab counts
        $cp  = $clinic ? [$clinic] : [];
        $cw  = $clinic ? 'WHERE clinic_id = $1' : '';
        $raw = $db->get_results(
            "SELECT current_status, COUNT(*) AS cnt FROM hearmed_core.orders {$cw} GROUP BY current_status", $cp
        );
        $counts = ['all' => 0];
        foreach ( $raw as $r ) {
            $counts[$r->current_status] = (int)$r->cnt;
            $counts['all'] += (int)$r->cnt;
        }

        $base = HearMed_Utils::page_url('orders');

        ob_start(); ?>
        <div class="hm-content hm-orders-list">

            <div class="hm-page-header">
                <h1 class="hm-page-title">Orders</h1>
                <?php if ( HearMed_Auth::can('create_orders') ) : ?>
                <a href="<?php echo esc_url($base.'?hm_action=create'); ?>" class="hm-btn hm-btn--primary">
                    + New Order
                </a>
                <?php endif; ?>
            </div>

            <div class="hm-tab-bar">
                <?php
                $tabs = [
                    'all'               => 'All',
                    'Awaiting Approval' => 'Awaiting Approval',
                    'Approved'          => 'Approved',
                    'Ordered'           => 'Ordered',
                    'Received'          => 'Received',
                    'Awaiting Fitting'  => 'Awaiting Fitting',
                    'Complete'          => 'Complete',
                    'Cancelled'         => 'Cancelled',
                ];
                foreach ( $tabs as $key => $label ) :
                    $active = $status_filter === $key ? 'hm-tab--active' : '';
                    $cnt    = $counts[$key] ?? 0;
                    $urgent = '';
                    if ($key==='Awaiting Approval' && $role==='c_level'                   && $cnt>0) $urgent='hm-tab--urgent';
                    if ($key==='Approved'           && in_array($role,['admin','finance']) && $cnt>0) $urgent='hm-tab--urgent';
                    if ($key==='Received'           && $cnt>0)                                       $urgent='hm-tab--urgent';
                    ?>
                    <a href="<?php echo esc_url($base.'?status='.urlencode($key)); ?>"
                       class="hm-tab <?php echo $active.' '.$urgent; ?>">
                        <?php echo esc_html($label); ?>
                        <?php if ($cnt) : ?><span class="hm-tab__badge"><?php echo $cnt; ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <form class="hm-search-bar" method="get">
                <input type="hidden" name="page"   value="orders">
                <input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>">
                <input type="text"   name="search" class="hm-input hm-input--search"
                       placeholder="Search patient name or order number..."
                       value="<?php echo esc_attr($search); ?>">
                <button type="submit" class="hm-btn hm-btn--secondary">Search</button>
            </form>

            <div class="hm-table-wrap">
                <table class="hm-table">
                    <thead>
                        <tr>
                            <th>Order #</th><th>Patient</th><th>Clinic</th>
                            <th>Dispenser</th><th>Total</th><th>PRSI</th>
                            <th>Status</th><th>Date</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ( empty($orders) ) : ?>
                        <tr><td colspan="9" class="hm-empty hm-empty-text">No orders found.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $orders as $o ) : ?>
                        <tr>
                            <td class="hm-mono"><?php echo esc_html($o->order_number); ?></td>
                            <td>
                                <a href="<?php echo esc_url(HearMed_Utils::page_url('patients').'?patient_id='.$o->patient_id); ?>">
                                    <?php echo esc_html($o->first_name.' '.$o->last_name); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($o->clinic_name); ?></td>
                            <td class="hm-muted"><?php echo esc_html($o->created_by_name ?: '—'); ?></td>
                            <td class="hm-money">€<?php echo number_format($o->grand_total,2); ?></td>
                            <td class="hm-money <?php echo $o->prsi_applicable ? 'hm-text--green' : 'hm-muted'; ?>">
                                <?php echo $o->prsi_applicable ? '€'.number_format($o->prsi_amount,2) : '—'; ?>
                            </td>
                            <td><?php echo self::status_badge($o->current_status); ?></td>
                            <td class="hm-muted"><?php echo date('d M Y',strtotime($o->created_at)); ?></td>
                            <td>
                                <a href="<?php echo esc_url($base.'?hm_action=view&order_id='.$o->id); ?>"
                                   class="hm-btn hm-btn--sm hm-btn--ghost">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CREATE ORDER — Status: 'Awaiting Approval'
    // ═══════════════════════════════════════════════════════════════════════
    public static function render_create() {
        if ( ! HearMed_Auth::can('create_orders') ) {
            return '<div class="hm-notice hm-notice--error">Access denied.</div>';
        }

        $db = HearMed_DB::instance();

        $products = $db->get_results(
            "SELECT p.id, m.name AS manufacturer_name, p.product_name, p.style,
                    p.tech_level, p.retail_price
             FROM hearmed_reference.products p
             LEFT JOIN hearmed_reference.manufacturers m ON m.id = p.manufacturer_id
             WHERE p.is_active = true
             ORDER BY m.name, p.product_name", []
        );

        $services = $db->get_results(
            "SELECT id, service_name, default_price
             FROM hearmed_reference.services
             WHERE is_active = true ORDER BY service_name", []
        );

        $base  = HearMed_Utils::page_url('orders');
        $nonce = wp_create_nonce('hearmed_nonce');

        ob_start(); ?>
        <div class="hm-content hm-orders-create">

            <div class="hm-page-header">
                <a href="<?php echo esc_url($base); ?>" class="hm-back">← Orders</a>
                <h1 class="hm-page-title">New Order</h1>
                <span class="hm-workflow-hint">Will be sent to C-Level for approval before supplier is contacted.</span>
            </div>

            <form id="hm-order-form" class="hm-form hm-card">
                <input type="hidden" name="nonce"  value="<?php echo esc_attr($nonce); ?>">
                <input type="hidden" name="action" value="hm_create_order">

                <!-- PATIENT -->
                <div class="hm-form__section">
                    <h2 class="hm-form__section-title">Patient</h2>
                    <div class="hm-form-group hm-form-group--wide">
                        <label class="hm-label">Search Patient <span class="hm-required">*</span></label>
                        <input type="text" id="hm-patient-search" class="hm-input"
                               placeholder="Type patient name..." autocomplete="off">
                        <div id="hm-patient-results" class="hm-autocomplete" style="display:none;"></div>
                        <input type="hidden" name="patient_id" id="hm-patient-id">
                        <div id="hm-patient-selected" class="hm-patient-chip" style="display:none;"></div>
                    </div>
                </div>

                <!-- LINE ITEMS -->
                <div class="hm-form__section">
                    <h2 class="hm-form__section-title">Items</h2>
                    <div id="hm-items-empty" class="hm-empty hm-empty-text">No items added yet.</div>
                    <table class="hm-table hm-order-items__table" id="hm-items-table" style="display:none;">
                        <thead>
                            <tr>
                                <th>Item</th><th>Ear</th><th>Qty</th>
                                <th>Speaker Size</th><th>Charger?</th>
                                <th>Unit Price</th><th>VAT</th><th>Total</th><th></th>
                            </tr>
                        </thead>
                        <tbody id="hm-items-body"></tbody>
                    </table>

                    <div class="hm-order-items__actions">
                        <div class="hm-item-adder">
                            <select id="hm-product-select" class="hm-input hm-input--sm">
                                <option value="">— Add a Hearing Aid / Product —</option>
                                <?php foreach ($products as $p) : ?>
                                <option value="<?php echo esc_attr($p->id); ?>"
                                        data-name="<?php echo esc_attr($p->manufacturer_name.' '.$p->product_name.' '.$p->style); ?>"
                                        data-manufacturer="<?php echo esc_attr($p->manufacturer_name); ?>"
                                        data-product-name="<?php echo esc_attr($p->product_name); ?>"
                                        data-style="<?php echo esc_attr($p->style); ?>"
                                        data-tech="<?php echo esc_attr($p->tech_level); ?>"
                                        data-price="<?php echo esc_attr($p->retail_price ?? 0); ?>"
                                        data-vat="23">
                                    <?php echo esc_html($p->manufacturer_name.' — '.$p->product_name.' '.$p->style.' ('.$p->tech_level.')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <select id="hm-ear-select" class="hm-input hm-input--sm">
                                <option value="">— Ear —</option>
                                <option value="Left">Left</option>
                                <option value="Right">Right</option>
                                <option value="Binaural">Binaural (both)</option>
                            </select>
                            <button type="button" id="hm-add-product" class="hm-btn hm-btn--secondary hm-btn--sm">
                                + Add Product
                            </button>
                        </div>
                        <div class="hm-item-adder">
                            <select id="hm-service-select" class="hm-input hm-input--sm">
                                <option value="">— Add a Service —</option>
                                <?php foreach ($services as $s) : ?>
                                <option value="<?php echo esc_attr($s->id); ?>"
                                        data-name="<?php echo esc_attr($s->service_name); ?>"
                                        data-price="<?php echo esc_attr($s->default_price ?? 0); ?>"
                                        data-vat="23">
                                    <?php echo esc_html($s->service_name.' — €'.number_format($s->default_price,2)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" id="hm-add-service" class="hm-btn hm-btn--secondary hm-btn--sm">
                                + Add Service
                            </button>
                        </div>
                    </div>
                </div>

                <!-- PRSI -->
                <div class="hm-form__section">
                    <h2 class="hm-form__section-title">PRSI Grant</h2>
                    <p class="hm-form__hint">Deducted from patient invoice. HearMed claims from DSP.</p>
                    <div class="hm-prsi-row">
                        <label class="hm-checkbox-label">
                            <input type="checkbox" name="prsi_left"  id="prsi_left"  value="1"> Left ear — €500
                        </label>
                        <label class="hm-checkbox-label">
                            <input type="checkbox" name="prsi_right" id="prsi_right" value="1"> Right ear — €500
                        </label>
                        <div class="hm-prsi-total">PRSI deduction: <strong id="hm-prsi-display">€0.00</strong></div>
                    </div>
                </div>

                <!-- PAYMENT METHOD (expected — collected at fitting) -->
                <div class="hm-form__section">
                    <h2 class="hm-form__section-title">Expected Payment Method</h2>
                    <p class="hm-form__hint">Collected at fitting. Recorded now for planning purposes.</p>
                    <select name="payment_method" class="hm-input" required>
                        <option value="">— Select —</option>
                        <option value="Card">Card</option>
                        <option value="Cash">Cash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Cheque">Cheque</option>
                        <option value="PRSI">PRSI Grant only</option>
                    </select>
                </div>

                <!-- DEPOSIT (optional — paid at order stage) -->
                <div class="hm-form__section">
                    <h2 class="hm-form__section-title">Deposit <span class="hm-form__section-optional">— Optional</span></h2>
                    <p class="hm-form__hint">If the patient pays a deposit today, record it here. Balance will be collected at fitting.</p>
                    <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
                        <div class="hm-form-group" style="flex:0 0 160px;">
                            <label class="hm-label">Deposit Amount (€)</label>
                            <input type="number" name="deposit_amount" id="hm-deposit-amount"
                                   class="hm-input" step="0.01" min="0" value="0"
                                   placeholder="0.00" oninput="hmUpdateDepositBalance()">
                        </div>
                        <div class="hm-form-group" style="flex:0 0 180px;">
                            <label class="hm-label">Deposit Method</label>
                            <select name="deposit_method" id="hm-deposit-method" class="hm-input">
                                <option value="">— None —</option>
                                <option value="Card">Card</option>
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cheque">Cheque</option>
                            </select>
                        </div>
                        <div class="hm-form-group" style="flex:0 0 160px;">
                            <label class="hm-label">Date Paid</label>
                            <input type="date" name="deposit_paid_at" id="hm-deposit-date"
                                   class="hm-input" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div id="hm-deposit-balance-row" style="display:none;margin-top:10px;padding:10px 14px;background:#f0fdfe;border:1px solid #a5f3fc;border-radius:8px;font-size:13px;color:#0e7490;">
                        Balance due at fitting: <strong id="hm-deposit-balance">€0.00</strong>
                    </div>
                </div>

                <!-- NOTES -->
                <div class="hm-form__section">
                    <h2 class="hm-form__section-title">Clinical Notes for Order</h2>
                    <textarea name="notes" class="hm-input hm-input--textarea" rows="3"
                              placeholder="Dome size, speaker requirements, any notes for the admin ordering..."></textarea>
                </div>

                <!-- TOTALS -->
                <div class="hm-order-totals hm-card hm-card--inset">
                    <div class="hm-order-totals__row"><span>Subtotal</span><span id="hm-subtotal">€0.00</span></div>
                    <div class="hm-order-totals__row"><span>VAT</span><span id="hm-vat-total">€0.00</span></div>
                    <div class="hm-order-totals__row hm-text--green"><span>PRSI Grant Deduction</span><span id="hm-prsi-deduction">−€0.00</span></div>
                    <div class="hm-order-totals__row hm-order-totals__row--total"><span>Patient Pays</span><span id="hm-grand-total">€0.00</span></div>
                    <div class="hm-order-totals__row hm-text--teal" id="hm-deposit-row" style="display:none;"><span>Deposit Paid Today</span><span id="hm-deposit-display">−€0.00</span></div>
                    <div class="hm-order-totals__row hm-order-totals__row--total" id="hm-balance-row" style="display:none;"><span>Balance at Fitting</span><span id="hm-balance-display">€0.00</span></div>
                </div>

                <script>
                function hmUpdateDepositBalance() {
                    var dep = parseFloat(document.getElementById('hm-deposit-amount').value) || 0;
                    var grand = parseFloat(document.getElementById('hm-grand-total').textContent.replace(/[^0-9.]/g,'')) || 0;
                    var bal = Math.max(0, grand - dep);
                    document.getElementById('hm-deposit-row').style.display  = dep > 0 ? '' : 'none';
                    document.getElementById('hm-balance-row').style.display  = dep > 0 ? '' : 'none';
                    document.getElementById('hm-deposit-balance-row').style.display = dep > 0 ? '' : 'none';
                    document.getElementById('hm-deposit-display').textContent  = '−€' + dep.toFixed(2);
                    document.getElementById('hm-deposit-balance').textContent  = '€' + bal.toFixed(2);
                    document.getElementById('hm-balance-display').textContent  = '€' + bal.toFixed(2);
                }
                </script>

                <input type="hidden" name="items_json" id="hm-items-json" value="[]">

                <div class="hm-form__actions">
                    <a href="<?php echo esc_url($base); ?>" class="hm-btn hm-btn--ghost">Cancel</a>
                    <button type="submit" id="hm-submit-order" class="hm-btn hm-btn--primary" disabled>
                        Submit for Approval →
                    </button>
                </div>
                <div id="hm-order-msg" class="hm-notice" style="display:none;"></div>
            </form>
        </div>
        <?php echo self::create_form_js(); ?>
        <?php echo self::maybe_prefill_patient_js(); ?>
        <?php return ob_get_clean();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // VIEW ORDER
    // ═══════════════════════════════════════════════════════════════════════
    public static function render_view( $order_id ) {
        if ( ! $order_id ) return '<div class="hm-notice hm-notice--error">No order specified.</div>';

        $db    = HearMed_DB::instance();
        $role  = HearMed_Auth::current_role();
        $nonce = wp_create_nonce('hearmed_nonce');

        $order = $db->get_row(
            "SELECT o.*,
                    p.first_name, p.last_name, p.email, p.phone,
                    p.date_of_birth, p.id AS patient_id,
                    p.address_line1, p.address_line2, p.city, p.county, p.eircode,
                    c.clinic_name,
                    CONCAT(s.first_name,' ',s.last_name)  AS created_by_name,
                    CONCAT(ap.first_name,' ',ap.last_name) AS approved_by_name,
                    inv.invoice_number, inv.payment_status,
                    inv.grand_total AS invoice_total, inv.quickbooks_id,
                    inv.qbo_sync_status, inv.qbo_synced_at
             FROM hearmed_core.orders o
             JOIN hearmed_core.patients p          ON p.id = o.patient_id
             JOIN hearmed_reference.clinics c      ON c.id = o.clinic_id
             LEFT JOIN hearmed_reference.staff s   ON s.id = o.staff_id
             LEFT JOIN hearmed_reference.staff ap  ON ap.id = o.approved_by
             LEFT JOIN hearmed_core.invoices inv   ON inv.id = o.invoice_id
             WHERE o.id = \$1", [$order_id]
        );
        if (!$order) return '<div class="hm-notice hm-notice--error">Order not found.</div>';

        $items = $db->get_results(
            "SELECT oi.*,
                    CASE
                        WHEN oi.item_type = 'product'
                            THEN CONCAT(m.name,' ',p.product_name,' ',p.style)
                        ELSE s.service_name
                    END AS item_name,
                    p.tech_level
             FROM hearmed_core.order_items oi
             LEFT JOIN hearmed_reference.products p      ON p.id = oi.item_id AND oi.item_type = 'product'
             LEFT JOIN hearmed_reference.manufacturers m ON m.id = p.manufacturer_id
             LEFT JOIN hearmed_reference.services s      ON s.id = oi.item_id AND oi.item_type = 'service'
             WHERE oi.order_id = \$1 ORDER BY oi.line_number", [$order_id]
        );

        // Serial numbers from patient_devices
        $serials = $db->get_results(
            "SELECT pd.*, p.product_name
             FROM hearmed_core.patient_devices pd
             LEFT JOIN hearmed_reference.products p ON p.id = pd.product_id
             WHERE pd.fitting_date IS NULL
               AND pd.patient_id = \$1
               AND EXISTS (
                   SELECT 1 FROM hearmed_core.order_items oi
                   WHERE oi.order_id = \$2 AND oi.item_id = pd.product_id AND oi.item_type = 'product'
               )",
            [$order->patient_id, $order_id]
        );

        $base = HearMed_Utils::page_url('orders');

        $can_approve  = $role === 'c_level'                      && $order->current_status === 'Awaiting Approval';
        $can_order    = in_array($role,['admin','finance'])       && $order->current_status === 'Approved';
        $can_receive  = in_array($role,['admin','finance'])       && $order->current_status === 'Ordered';
        $can_serials  = $order->current_status === 'Received';
        $can_complete = in_array($role,['dispenser','c_level'])   && $order->current_status === 'Awaiting Fitting';
        $can_print    = !in_array($order->current_status, ['Awaiting Approval','Cancelled']);

        ob_start(); ?>
        <div class="hm-content hm-order-view">

            <div class="hm-page-header">
                <a href="<?php echo esc_url($base); ?>" class="hm-back">← Orders</a>
                <h1 class="hm-page-title"><?php echo esc_html($order->order_number); ?></h1>
                <?php echo self::status_badge($order->current_status); ?>
            </div>

            <div class="hm-order-view__grid">

                <!-- Invoice panel -->
                <div class="hm-card hm-order-invoice">
                    <div class="hm-invoice__header">
                        <div class="hm-invoice__brand">HearMed</div>
                        <div class="hm-invoice__meta">
                            <div><strong>Order:</strong> <?php echo esc_html($order->order_number); ?></div>
                            <?php if ($order->invoice_number) : ?>
                            <div><strong>Invoice:</strong> <?php echo esc_html($order->invoice_number); ?></div>
                            <?php endif; ?>
                            <div><strong>Date:</strong> <?php echo date('d M Y',strtotime($order->created_at)); ?></div>
                            <div><strong>Clinic:</strong> <?php echo esc_html($order->clinic_name); ?></div>
                            <div><strong>Dispenser:</strong> <?php echo esc_html($order->created_by_name ?: '—'); ?></div>
                        </div>
                    </div>

                    <div class="hm-invoice__patient">
                        <strong><?php echo esc_html($order->first_name.' '.$order->last_name); ?></strong><br>
                        <?php if ($order->phone) echo esc_html($order->phone).'<br>'; ?>
                        <?php if ($order->email) echo esc_html($order->email); ?>
                    </div>

                    <table class="hm-table hm-invoice__items">
                        <thead>
                            <tr>
                                <th>Item</th><th>Ear</th><th>Qty</th>
                                <th>Speaker</th><th>Charger</th>
                                <th>Unit Price</th><th>VAT</th><th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $item) : ?>
                        <tr>
                            <td><?php echo esc_html($item->item_name); ?></td>
                            <td><?php echo esc_html($item->ear_side ?: '—'); ?></td>
                            <td><?php echo esc_html($item->quantity); ?></td>
                            <td><?php echo esc_html($item->speaker_size ?: '—'); ?></td>
                            <td><?php echo !empty($item->needs_charger) ? 'Yes' : '—'; ?></td>
                            <td class="hm-money">€<?php echo number_format($item->unit_retail_price,2); ?></td>
                            <td class="hm-money">€<?php echo number_format($item->vat_amount ?? 0,2); ?></td>
                            <td class="hm-money">€<?php echo number_format($item->line_total,2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr><td colspan="7" class="hm-text-right">Subtotal</td>
                                <td class="hm-money">€<?php echo number_format($order->subtotal,2); ?></td></tr>
                            <tr><td colspan="7" class="hm-text-right">Discount</td>
                                <td class="hm-money">−€<?php echo number_format($order->discount_total,2); ?></td></tr>
                            <tr><td colspan="7" class="hm-text-right">VAT</td>
                                <td class="hm-money">€<?php echo number_format($order->vat_total,2); ?></td></tr>
                            <?php if ($order->prsi_applicable) : ?>
                            <tr class="hm-text--green">
                                <td colspan="7" class="hm-text-right">PRSI Grant</td>
                                <td class="hm-money">−€<?php echo number_format($order->prsi_amount,2); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr class="hm-invoice__total-row">
                                <td colspan="7" class="hm-text-right"><strong>Patient Pays</strong></td>
                                <td class="hm-money"><strong>€<?php echo number_format($order->grand_total,2); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>

                    <?php if ($order->notes) : ?>
                    <div class="hm-invoice__notes"><strong>Notes:</strong> <?php echo esc_html($order->notes); ?></div>
                    <?php endif; ?>

                    <?php if (!empty($serials)) : ?>
                    <div class="hm-serial-summary">
                        <strong>Serials:</strong>
                        <?php foreach ($serials as $sd) : ?>
                        <span class="hm-mono" style="margin-right:1rem;">
                            <?php echo esc_html($sd->product_name); ?>:
                            <?php if ($sd->serial_number_left)  echo 'L: '.esc_html($sd->serial_number_left); ?>
                            <?php if ($sd->serial_number_right) echo ' R: '.esc_html($sd->serial_number_right); ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($order->current_status === 'Complete') : ?>
                    <div class="hm-muted" style="font-size:0.8rem;margin-top:1rem;">
                        QuickBooks:
                        <?php if ($order->quickbooks_id) : ?>
                            <span class="hm-badge hm-badge--green">Synced</span>
                            Ref: <?php echo esc_html($order->quickbooks_id); ?>
                        <?php else : ?>
                            <span class="hm-badge hm-badge--grey"><?php echo esc_html($order->qbo_sync_status ?? 'Pending'); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar: Actions + Timeline -->
                <div class="hm-order-view__sidebar">

                    <!-- STAGE 1→2: C-Level Approval -->
                    <?php if ($can_approve) : ?>
                    <div class="hm-card hm-card--action">
                        <h3 class="hm-card-title">Awaiting Your Approval</h3>
                        <p class="hm-form__hint">Review the order then approve or reject.</p>
                        <textarea id="hm-approval-note" class="hm-input hm-input--textarea" rows="2"
                                  placeholder="Optional note..."></textarea>
                        <div class="hm-btn-group" style="margin-top:0.75rem;">
                            <button class="hm-btn hm-btn--primary hm-order-action"
                                    data-ajax="hm_approve_order" data-order-id="<?php echo $order_id; ?>"
                                    data-nonce="<?php echo esc_attr($nonce); ?>"
                                    data-confirm="Approve this order and notify admin?">
                                ✓ Approve
                            </button>
                            <button class="hm-btn hm-btn--danger hm-order-action"
                                    data-ajax="hm_reject_order" data-order-id="<?php echo $order_id; ?>"
                                    data-nonce="<?php echo esc_attr($nonce); ?>"
                                    data-confirm="Reject this order? The dispenser will be notified.">
                                × Reject
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- STAGE 2→3: Admin places order -->
                    <?php if ($can_order) : ?>
                    <div class="hm-card hm-card--action">
                        <h3 class="hm-card-title">Place Order with Supplier</h3>
                        <a href="<?php echo esc_url($base.'?hm_action=print&order_id='.$order_id); ?>"
                           target="_blank" class="hm-btn hm-btn--secondary hm-btn--block" style="margin-bottom:0.75rem;">
                            Print Order Sheet
                        </a>
                        <p class="hm-form__hint">Once placed with the supplier, click below.</p>
                        <button class="hm-btn hm-btn--primary hm-btn--block hm-order-action"
                                data-ajax="hm_mark_ordered" data-order-id="<?php echo $order_id; ?>"
                                data-nonce="<?php echo esc_attr($nonce); ?>"
                                data-confirm="Confirm you have placed this order with the supplier?">
                            ✓ Order Placed with Supplier
                        </button>
                    </div>
                    <?php endif; ?>

                    <!-- STAGE 3→4: Received in clinic -->
                    <?php if ($can_receive) : ?>
                    <div class="hm-card hm-card--action">
                        <h3 class="hm-card-title">Aids Arrived?</h3>
                        <p class="hm-form__hint">Mark received. Dispenser will be notified to enter serial numbers.</p>
                        <button class="hm-btn hm-btn--primary hm-btn--block hm-order-action"
                                data-ajax="hm_mark_received" data-order-id="<?php echo $order_id; ?>"
                                data-nonce="<?php echo esc_attr($nonce); ?>"
                                data-confirm="Mark this order as received in clinic?">
                            Received in Clinic
                        </button>
                    </div>
                    <?php endif; ?>

                    <!-- STAGE 4→5: Serial numbers -->
                    <?php if ($can_serials) : ?>
                    <div class="hm-card hm-card--action">
                        <h3 class="hm-card-title">Enter Serial Numbers</h3>
                        <p class="hm-form__hint">Record serials before the patient is fitted.</p>
                        <a href="<?php echo esc_url($base.'?hm_action=serials&order_id='.$order_id); ?>"
                           class="hm-btn hm-btn--primary hm-btn--block">
                            Enter Serial Numbers →
                        </a>
                    </div>
                    <?php endif; ?>

                    <!-- STAGE 5→6: Fitting + Payment -->
                    <?php if ($can_complete) : ?>
                    <div class="hm-card hm-card--action">
                        <h3 class="hm-card-title">Patient is Here — Fit + Pay</h3>
                        <p class="hm-form__hint">
                            Finalises invoice as Paid, logs in patient file, fires to QuickBooks.
                        </p>
                        <a href="<?php echo esc_url($base.'?hm_action=complete&order_id='.$order_id); ?>"
                           class="hm-btn hm-btn--primary hm-btn--block">
                            Record Fitting + Payment →
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php if ($can_print && !$can_order) : ?>
                    <div class="hm-card">
                        <a href="<?php echo esc_url($base.'?hm_action=print&order_id='.$order_id); ?>"
                           target="_blank" class="hm-btn hm-btn--secondary hm-btn--block">
                            Print Order Sheet
                        </a>
                    </div>
                    <?php endif; ?>

                    <!-- Timeline -->
                    <div class="hm-card">
                        <h3 class="hm-card-title">Timeline</h3>
                        <div class="hm-timeline">
                            <?php
                            $stages = [
                                ['Order Created',        $order->created_at,  ['Awaiting Approval','Approved','Ordered','Received','Awaiting Fitting','Complete']],
                                ['Approved by C-Level',  $order->approved_at, ['Approved','Ordered','Received','Awaiting Fitting','Complete']],
                                ['Order Placed',         $order->ordered_at,  ['Ordered','Received','Awaiting Fitting','Complete']],
                                ['Arrived in Clinic',    $order->arrived_at,  ['Received','Awaiting Fitting','Complete']],
                                ['Serials Recorded',     $order->serials_at,  ['Awaiting Fitting','Complete']],
                                ['Fitted & Paid',        $order->fitted_at,   ['Complete']],
                            ];
                            foreach ($stages as [$label, $date, $statuses]) :
                                $done = in_array($order->current_status, $statuses);
                            ?>
                            <div class="hm-timeline__item <?php echo $done ? 'hm-timeline__item--done' : ''; ?>">
                                <span class="hm-timeline__dot"></span>
                                <div>
                                    <span class="hm-timeline__label"><?php echo esc_html($label); ?></span>
                                    <?php if ($date && $done) : ?>
                                    <span class="hm-timeline__date"><?php echo date('d M Y',strtotime($date)); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <script>
        document.querySelectorAll('.hm-order-action').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (this.dataset.confirm && !confirm(this.dataset.confirm)) return;
                const me = this;
                me.disabled = true; me.textContent = 'Saving...';
                const noteEl = document.getElementById('hm-approval-note');
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {'Content-Type':'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: me.dataset.ajax,
                        order_id: me.dataset.orderId,
                        nonce: me.dataset.nonce,
                        note: noteEl ? noteEl.value : ''
                    })
                }).then(r=>r.json()).then(d=>{
                    if (d.success) location.reload();
                    else { alert('Error: ' + (d.data?.message || d.data)); me.disabled=false; }
                });
            });
        });
        </script>
        <?php return ob_get_clean();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SERIAL NUMBERS — inserts into patient_devices
    // ═══════════════════════════════════════════════════════════════════════
    public static function render_serials( $order_id ) {
        $db    = HearMed_DB::instance();
        $nonce = wp_create_nonce('hearmed_nonce');
        $base  = HearMed_Utils::page_url('orders');

        $order = $db->get_row(
            "SELECT o.id, o.current_status, o.patient_id, p.first_name, p.last_name
             FROM hearmed_core.orders o
             JOIN hearmed_core.patients p ON p.id = o.patient_id
             WHERE o.id = \$1", [$order_id]
        );
        if (!$order || $order->current_status !== 'Received') {
            return '<div class="hm-notice hm-notice--error">Order not available for serial entry.</div>';
        }

        // Get product items only — services have no serials
        $ha_items = $db->get_results(
            "SELECT oi.id, oi.item_id AS product_id, oi.ear_side,
                    CONCAT(m.name,' ',p.product_name,' ',p.style) AS item_name
             FROM hearmed_core.order_items oi
             JOIN hearmed_reference.products p      ON p.id = oi.item_id
             JOIN hearmed_reference.manufacturers m ON m.id = p.manufacturer_id
             WHERE oi.order_id = \$1 AND oi.item_type = 'product'
             ORDER BY oi.line_number", [$order_id]
        );

        ob_start(); ?>
        <div class="hm-content hm-serials-form">
            <div class="hm-page-header">
                <a href="<?php echo esc_url($base.'?hm_action=view&order_id='.$order_id); ?>" class="hm-back">← Order</a>
                <h1 class="hm-page-title">Serial Numbers</h1>
                <span class="hm-workflow-hint"><?php echo esc_html($order->first_name.' '.$order->last_name); ?></span>
            </div>

            <div class="hm-card" style="max-width:600px;">

                <?php if (empty($ha_items)) : ?>
                <div class="hm-notice hm-notice--info">No hearing aid products — no serials needed.</div>
                <button class="hm-btn hm-btn--primary hm-skip-serials" style="margin-top:1rem;"
                        data-order-id="<?php echo $order_id; ?>"
                        data-nonce="<?php echo esc_attr($nonce); ?>">
                    Continue → Move to Awaiting Fitting
                </button>
                <?php else : ?>
                <p class="hm-form__hint">Left and right are recorded separately. Services are skipped automatically.</p>

                <form id="hm-serials-form">
                    <input type="hidden" name="nonce"    value="<?php echo esc_attr($nonce); ?>">
                    <input type="hidden" name="action"   value="hm_save_serials">
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">

                    <?php foreach ($ha_items as $item) :
                        $need_left  = in_array($item->ear_side, ['Left','Binaural']);
                        $need_right = in_array($item->ear_side, ['Right','Binaural']);
                    ?>
                    <div class="hm-card hm-card--inset" style="margin-bottom:1rem;">
                        <strong><?php echo esc_html($item->item_name); ?></strong>
                        <span class="hm-muted">(<?php echo esc_html($item->ear_side ?? 'Unknown'); ?>)</span>
                        <input type="hidden" name="items[<?php echo $item->id; ?>][product_id]"
                               value="<?php echo $item->product_id; ?>">

                        <?php if ($need_left) : ?>
                        <div class="hm-form-group" style="margin-top:0.75rem;">
                            <label class="hm-label">Left Ear Serial Number</label>
                            <input type="text" name="items[<?php echo $item->id; ?>][left]"
                                   class="hm-input hm-input--mono" placeholder="Serial number...">
                        </div>
                        <?php endif; ?>
                        <?php if ($need_right) : ?>
                        <div class="hm-form-group" style="margin-top:0.5rem;">
                            <label class="hm-label">Right Ear Serial Number</label>
                            <input type="text" name="items[<?php echo $item->id; ?>][right]"
                                   class="hm-input hm-input--mono" placeholder="Serial number...">
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>

                    <div class="hm-form__actions">
                        <button type="submit" class="hm-btn hm-btn--primary">
                            Save Serials → Move to Awaiting Fitting
                        </button>
                    </div>
                    <div id="hm-serials-msg" class="hm-notice" style="display:none;margin-top:1rem;"></div>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <script>
        (function() {
            const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
            const base    = '<?php echo esc_url($base); ?>';
            const skipBtn = document.querySelector('.hm-skip-serials');
            if (skipBtn) {
                skipBtn.addEventListener('click', function() {
                    this.disabled = true;
                    fetch(ajaxUrl, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({action:'hm_save_serials', order_id:this.dataset.orderId, nonce:this.dataset.nonce})
                    }).then(r=>r.json()).then(d=>{ if(d.success) location.href=base; });
                });
            }
            const form = document.getElementById('hm-serials-form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const btn = form.querySelector('[type=submit]');
                    btn.disabled=true; btn.textContent='Saving...';
                    fetch(ajaxUrl, {method:'POST', body:new URLSearchParams(new FormData(form))})
                    .then(r=>r.json()).then(d=>{
                        const msg=document.getElementById('hm-serials-msg');
                        msg.style.display='block';
                        if (d.success) {
                            msg.className='hm-notice hm-notice--success';
                            msg.innerHTML='<div class="hm-notice-body"><span class="hm-notice-icon">✓</span> Serials saved!</div>';
                            setTimeout(()=>location.href=base, 1200);
                        } else {
                            msg.className='hm-notice hm-notice--error';
                            msg.innerHTML='<div class="hm-notice-body"><span class="hm-notice-icon">×</span> '+d.data+'</div>';
                            btn.disabled=false;
                            btn.textContent='Save Serials → Move to Awaiting Fitting';
                        }
                    });
                });
            }
        })();
        </script>
        <?php return ob_get_clean();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // FITTING + PAYMENT — QBO fires here, nowhere else
    // ═══════════════════════════════════════════════════════════════════════
    public static function render_complete( $order_id ) {
        $db    = HearMed_DB::instance();
        $nonce = wp_create_nonce('hearmed_nonce');
        $base  = HearMed_Utils::page_url('orders');

        $order = $db->get_row(
            "SELECT o.*, p.first_name, p.last_name,
                    inv.invoice_number, inv.grand_total AS invoice_total
             FROM hearmed_core.orders o
             JOIN hearmed_core.patients p ON p.id = o.patient_id
             LEFT JOIN hearmed_core.invoices inv ON inv.id = o.invoice_id
             WHERE o.id = \$1", [$order_id]
        );
        if (!$order || $order->current_status !== 'Awaiting Fitting') {
            return '<div class="hm-notice hm-notice--error">Order not ready for fitting yet.</div>';
        }

        $amount_due = $order->invoice_total ?? $order->grand_total;

        ob_start(); ?>
        <div class="hm-content hm-complete-form">
            <div class="hm-page-header">
                <a href="<?php echo esc_url($base.'?hm_action=view&order_id='.$order_id); ?>" class="hm-back">← Order</a>
                <h1 class="hm-page-title">Record Fitting + Payment</h1>
            </div>

            <div class="hm-card" style="max-width:520px;">
                <div class="hm-complete-summary">
                    <p><strong>Patient:</strong> <?php echo esc_html($order->first_name.' '.$order->last_name); ?></p>
                    <p><strong>Payment Method:</strong> <?php echo esc_html($order->payment_method ?? '—'); ?></p>
                    <?php if ($order->prsi_applicable) : ?>
                    <p class="hm-muted" style="font-size:0.875rem;">
                        PRSI grant of €<?php echo number_format($order->prsi_amount,2); ?> already deducted.
                    </p>
                    <?php endif; ?>
                    <p style="font-size:1.25rem;margin-top:0.5rem;">
                        <strong>Collect: <span class="hm-text--teal">€<?php echo number_format($amount_due,2); ?></span></strong>
                    </p>
                </div>

                <hr class="hm-divider">

                <div class="hm-form-group">
                    <label class="hm-label">Fitting Date</label>
                    <input type="date" id="hm-fit-date" class="hm-input" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="hm-form-group" style="margin-top:1rem;">
                    <label class="hm-label">Amount Received (€)</label>
                    <input type="number" id="hm-fit-amount" class="hm-input" step="0.01"
                           value="<?php echo number_format($amount_due,2,'.',''); ?>">
                </div>
                <div class="hm-form-group" style="margin-top:1rem;">
                    <label class="hm-label">Fitting Notes (optional)</label>
                    <textarea id="hm-fit-notes" class="hm-input hm-input--textarea" rows="2"
                              placeholder="Clinical notes, adjustments made..."></textarea>
                </div>

                <div class="hm-notice hm-notice--info" style="margin-top:1.25rem;font-size:0.875rem;">
                    ℹ️ This will: mark the invoice as Paid, create a payment record,
                    log the fitting in the patient file, and sync to QuickBooks.
                </div>

                <div class="hm-form__actions" style="margin-top:1.25rem;">
                    <button class="hm-btn hm-btn--primary hm-btn--block" id="hm-confirm-complete"
                            data-order-id="<?php echo $order_id; ?>"
                            data-nonce="<?php echo esc_attr($nonce); ?>">
                        ✓ Confirm Fitted + Paid — Finalise
                    </button>
                </div>
                <div id="hm-complete-msg" class="hm-notice" style="display:none;margin-top:1rem;"></div>
            </div>
        </div>

        <script>
        document.getElementById('hm-confirm-complete').addEventListener('click', function() {
            if (!confirm('Confirm patient fitted and payment received? This will finalise the invoice.')) return;
            const btn = this;
            btn.disabled=true; btn.textContent='Finalising...';
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action:'hm_complete_order',
                    order_id: btn.dataset.orderId,
                    nonce: btn.dataset.nonce,
                    fit_date: document.getElementById('hm-fit-date').value,
                    amount: document.getElementById('hm-fit-amount').value,
                    notes: document.getElementById('hm-fit-notes').value
                })
            }).then(r=>r.json()).then(d=>{
                const msg=document.getElementById('hm-complete-msg');
                msg.style.display='block';
                if (d.success) {
                    msg.className='hm-notice hm-notice--success';
                    msg.innerHTML='<div class="hm-notice-body"><span class="hm-notice-icon">✓</span> '+d.data.message+'</div>';
                    setTimeout(()=>window.location=d.data.redirect, 1500);
                } else {
                    msg.className='hm-notice hm-notice--error';
                    msg.innerHTML='<div class="hm-notice-body"><span class="hm-notice-icon">×</span> '+d.data+'</div>';
                    btn.disabled=false; btn.textContent='✓ Confirm Fitted + Paid — Finalise';
                }
            });
        });
        </script>
        <?php return ob_get_clean();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PRINTABLE ORDER SHEET
    // ═══════════════════════════════════════════════════════════════════════
    public static function render_order_sheet( $order_id ) {
        $db = HearMed_DB::instance();
        $order = $db->get_row(
            "SELECT o.*, p.first_name, p.last_name, p.date_of_birth,
                    c.clinic_name,
                    CONCAT(s.first_name,' ',s.last_name) AS created_by_name
             FROM hearmed_core.orders o
             JOIN hearmed_core.patients p        ON p.id = o.patient_id
             JOIN hearmed_reference.clinics c    ON c.id = o.clinic_id
             LEFT JOIN hearmed_reference.staff s ON s.id = o.staff_id
             WHERE o.id = \$1", [$order_id]
        );
        if (!$order) return '<p>Order not found.</p>';

        $items = $db->get_results(
            "SELECT oi.ear_side, oi.speaker_size, oi.needs_charger, oi.quantity,
                    m.name AS manufacturer, p.product_name, p.style, p.tech_level
             FROM hearmed_core.order_items oi
             JOIN hearmed_reference.products p      ON p.id = oi.item_id
             JOIN hearmed_reference.manufacturers m ON m.id = p.manufacturer_id
             WHERE oi.order_id = \$1 AND oi.item_type = 'product'
             ORDER BY oi.line_number", [$order_id]
        );

        ob_start(); ?>
        <!DOCTYPE html><html><head>
        <meta charset="UTF-8">
        <title>Order Sheet — <?php echo esc_html($order->order_number); ?></title>
        <style>
            *{box-sizing:border-box}
            body{font-family:Arial,sans-serif;max-width:820px;margin:2rem auto;color:#151B33;font-size:13px}
            h1{color:#151B33;margin-bottom:0.25rem} .teal{color:var(--hm-teal)}
            .sub{color:#64748b;font-size:12px;margin-bottom:2rem}
            .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:0.75rem 1.5rem;margin-bottom:1.5rem}
            .grid div strong{display:block;font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px}
            table{width:100%;border-collapse:collapse;margin-top:1rem}
            th{background:#151B33;color:#fff;padding:7px 10px;text-align:left;font-size:11px;text-transform:uppercase}
            td{padding:8px 10px;border-bottom:1px solid #e2e8f0}
            .badge{background:var(--hm-teal);color:#fff;padding:1px 7px;border-radius:3px;font-size:11px;font-weight:bold}
            .notes{margin-top:1.5rem;padding:0.75rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:4px}
            .sign{margin-top:2.5rem;border-top:2px solid #151B33;padding-top:1.25rem}
            .sign-row{display:flex;gap:3rem;margin-top:0.75rem}
            .sign-field{flex:1}
            .sign-field span{display:block;font-size:10px;color:#94a3b8;text-transform:uppercase;margin-bottom:0.5rem}
            .sign-line{border-bottom:1px solid #94a3b8;min-height:28px}
            .footer{margin-top:2rem;font-size:10px;color:#94a3b8}
            @media print{body{margin:1cm}}
        </style>
        </head><body>

        <h1>HearMed <span class="teal">Order Sheet</span></h1>
        <p class="sub">Print and use to place order with supplier. File a copy.</p>

        <div class="grid">
            <div><strong>Order Ref</strong><?php echo esc_html($order->order_number); ?></div>
            <div><strong>Date</strong><?php echo date('d M Y',strtotime($order->created_at)); ?></div>
            <div><strong>Clinic</strong><?php echo esc_html($order->clinic_name); ?></div>
            <div><strong>Dispenser</strong><?php echo esc_html($order->created_by_name ?: '—'); ?></div>
            <div><strong>Patient</strong><?php echo esc_html($order->first_name.' '.$order->last_name); ?></div>
            <div><strong>DOB</strong><?php echo $order->date_of_birth ? date('d/m/Y',strtotime($order->date_of_birth)) : '—'; ?></div>
        </div>

        <?php if ($order->notes) : ?>
        <div class="notes"><strong>Clinical Notes:</strong> <?php echo esc_html($order->notes); ?></div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>Manufacturer</th><th>Model</th><th>Style</th>
                    <th>Tech Level</th><th>Ear</th>
                    <th>Speaker Size</th><th>Charger?</th><th>Qty</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item) : ?>
            <tr>
                <td><?php echo esc_html($item->manufacturer); ?></td>
                <td><?php echo esc_html($item->product_name); ?></td>
                <td><?php echo esc_html($item->style ?: '—'); ?></td>
                <td><?php echo esc_html($item->tech_level ?: '—'); ?></td>
                <td><?php echo esc_html($item->ear_side ?: '—'); ?></td>
                <td><?php echo esc_html($item->speaker_size ?: '—'); ?></td>
                <td><?php echo !empty($item->needs_charger) ? '<span class="badge">YES</span>' : 'No'; ?></td>
                <td><?php echo esc_html($item->quantity); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="sign">
            <div class="sign-row">
                <div class="sign-field"><span>Ordered by</span><div class="sign-line"></div></div>
                <div class="sign-field"><span>Date placed with supplier</span><div class="sign-line"></div></div>
                <div class="sign-field"><span>Supplier order reference</span><div class="sign-line"></div></div>
            </div>
            <div class="sign-row" style="margin-top:1.25rem;">
                <div class="sign-field"><span>Expected delivery date</span><div class="sign-line"></div></div>
                <div class="sign-field"><span>Received in clinic — date</span><div class="sign-line"></div></div>
                <div class="sign-field"><span>Received by</span><div class="sign-line"></div></div>
            </div>
        </div>

        <p class="footer">HearMed Acoustic Health Care Ltd — Confidential — <?php echo esc_html($order->clinic_name); ?></p>
        <script>window.print();</script>
        </body></html>
        <?php return ob_get_clean();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AJAX HANDLERS
    // ═══════════════════════════════════════════════════════════════════════

    public static function ajax_create_order() {
        check_ajax_referer('hearmed_nonce','nonce');
        if (!HearMed_Auth::can('create_orders')) wp_send_json_error('Access denied.');

        $payment_method  = sanitize_text_field($_POST['payment_method'] ?? '');
        $notes           = sanitize_textarea_field($_POST['notes'] ?? '');
        $prsi_left       = !empty($_POST['prsi_left']);
        $prsi_right      = !empty($_POST['prsi_right']);
        $items           = json_decode(sanitize_text_field($_POST['items_json'] ?? '[]'), true);
        $deposit_amount  = floatval($_POST['deposit_amount'] ?? 0);
        $deposit_method  = sanitize_text_field($_POST['deposit_method'] ?? '');
        $deposit_paid_at = sanitize_text_field($_POST['deposit_paid_at'] ?? '');

        if (!$patient_id)     wp_send_json_error('Please select a patient.');
        if (!$payment_method) wp_send_json_error('Please select a payment method.');
        if (empty($items))    wp_send_json_error('Please add at least one item.');
        if ($deposit_amount < 0) wp_send_json_error('Deposit cannot be negative.');

        $db     = HearMed_DB::instance();
        $clinic = HearMed_Auth::current_clinic();
        $user   = HearMed_Auth::current_user();

        $subtotal = $vat_total = $discount_total = 0;
        foreach ($items as $item) {
            $subtotal  += floatval($item['unit_price']) * intval($item['qty']);
            $vat_total += floatval($item['vat_amount']);
        }

        $prsi_applicable = $prsi_left || $prsi_right;
        $prsi_amount     = ($prsi_left ? 500 : 0) + ($prsi_right ? 500 : 0);
        $grand_total     = max(0, $subtotal + $vat_total - $prsi_amount);

        // Generate order number: ORD-YYYYMMDD-XXXX
        $order_num = 'ORD-'.date('Ymd').'-'.str_pad(rand(1,9999),4,'0',STR_PAD_LEFT);

        $order_id = $db->insert('hearmed_core.orders', [
            'order_number'    => $order_num,
            'patient_id'      => $patient_id,
            'staff_id'        => $user->id ?? null,
            'clinic_id'       => $clinic,
            'order_date'      => date('Y-m-d'),
            'current_status'  => 'Awaiting Approval',
            'subtotal'        => $subtotal,
            'discount_total'  => $discount_total,
            'vat_total'       => $vat_total,
            'grand_total'     => $grand_total,
            'prsi_applicable' => $prsi_applicable,
            'prsi_amount'     => $prsi_amount,
            'prsi_left'       => $prsi_left,
            'prsi_right'      => $prsi_right,
            'payment_method'  => $payment_method,
            'deposit_amount'  => $deposit_amount > 0 ? $deposit_amount : 0,
            'deposit_method'  => $deposit_amount > 0 ? $deposit_method : null,
            'deposit_paid_at' => $deposit_amount > 0 && $deposit_paid_at ? $deposit_paid_at : null,
            'notes'           => $notes,
            'created_by'      => $user->id ?? null,
        ]);;

        if (!$order_id) wp_send_json_error('Failed to save order. Please try again.');

        // Insert line items
        $line = 1;
        foreach ($items as $item) {
            $db->insert('hearmed_core.order_items', [
                'order_id'          => $order_id,
                'line_number'       => $line++,
                'item_type'         => sanitize_key($item['type']),
                'item_id'           => intval($item['id']),
                'item_description'  => sanitize_text_field($item['name']),
                'ear_side'          => sanitize_text_field($item['ear'] ?? ''),
                'speaker_size'      => sanitize_text_field($item['speaker'] ?? ''),
                'needs_charger'     => !empty($item['charger']),
                'quantity'          => intval($item['qty']),
                'unit_retail_price' => floatval($item['unit_price']),
                'vat_rate'          => floatval($item['vat_rate']),
                'vat_amount'        => floatval($item['vat_amount']),
                'line_total'        => floatval($item['line_total']),
            ]);
        }

        // Status history log
        self::log_status_change($order_id, null, 'Awaiting Approval', $user->id ?? null, 'Order created');

        // Notify C-Level
        self::notify('c_level', 'order_awaiting_approval', $order_id, ['order_number' => $order_num]);

        wp_send_json_success([
            'message'  => 'Order '.$order_num.' submitted for C-Level approval.',
            'order_id' => $order_id,
            'redirect' => HearMed_Utils::page_url('orders').'?hm_action=view&order_id='.$order_id,
        ]);
    }

    public static function ajax_approve_order() {
        check_ajax_referer('hearmed_nonce','nonce');
        if (HearMed_Auth::current_role() !== 'c_level') wp_send_json_error('Access denied.');

        $order_id = intval($_POST['order_id'] ?? 0);
        $note     = sanitize_textarea_field($_POST['note'] ?? '');
        $user     = HearMed_Auth::current_user();
        $db       = HearMed_DB::instance();

        $db->update('hearmed_core.orders', [
            'current_status' => 'Approved',
            'approved_by'    => $user->id ?? null,
            'approved_at'    => date('Y-m-d H:i:s'),
            'approval_note'  => $note,
        ], ['id' => $order_id]);

        self::log_status_change($order_id, 'Awaiting Approval', 'Approved', $user->id ?? null, $note);
        self::notify('admin', 'order_approved', $order_id, []);

        wp_send_json_success('Order approved. Admin notified to place with supplier.');
    }

    public static function ajax_reject_order() {
        check_ajax_referer('hearmed_nonce','nonce');
        if (HearMed_Auth::current_role() !== 'c_level') wp_send_json_error('Access denied.');

        $order_id = intval($_POST['order_id'] ?? 0);
        $note     = sanitize_textarea_field($_POST['note'] ?? '');
        $user     = HearMed_Auth::current_user();
        $db       = HearMed_DB::instance();

        $order = $db->get_row("SELECT staff_id FROM hearmed_core.orders WHERE id = \$1", [$order_id]);
        $db->update('hearmed_core.orders', [
            'current_status' => 'Cancelled',
            'approval_note'  => $note,
        ], ['id' => $order_id]);

        self::log_status_change($order_id, 'Awaiting Approval', 'Cancelled', $user->id ?? null, 'Rejected: '.$note);
        self::notify_user($order->staff_id ?? null, 'order_rejected', $order_id, ['note' => $note]);

        wp_send_json_success('Order rejected. Dispenser notified.');
    }

    public static function ajax_mark_ordered() {
        check_ajax_referer('hearmed_nonce','nonce');
        if (!HearMed_Auth::can('manage_orders')) wp_send_json_error('Access denied.');

        $order_id = intval($_POST['order_id'] ?? 0);
        $user     = HearMed_Auth::current_user();
        $db       = HearMed_DB::instance();

        $db->update('hearmed_core.orders', [
            'current_status' => 'Ordered',
            'ordered_at'     => date('Y-m-d H:i:s'),
        ], ['id' => $order_id]);

        self::log_status_change($order_id, 'Approved', 'Ordered', $user->id ?? null, 'Order placed with supplier');

        wp_send_json_success('Marked as ordered. Waiting for delivery.');
    }

    public static function ajax_mark_received() {
        check_ajax_referer('hearmed_nonce','nonce');
        if (!HearMed_Auth::can('manage_orders')) wp_send_json_error('Access denied.');

        $order_id = intval($_POST['order_id'] ?? 0);
        $user     = HearMed_Auth::current_user();
        $db       = HearMed_DB::instance();

        $order = $db->get_row("SELECT staff_id, order_number FROM hearmed_core.orders WHERE id = \$1", [$order_id]);

        $db->update('hearmed_core.orders', [
            'current_status' => 'Received',
            'arrived_at'     => date('Y-m-d H:i:s'),
        ], ['id' => $order_id]);

        self::log_status_change($order_id, 'Ordered', 'Received', $user->id ?? null, 'Aids arrived in clinic');
        self::notify_user($order->staff_id ?? null, 'order_arrived', $order_id, [
            'order_number' => $order->order_number,
        ]);

        wp_send_json_success('Marked as received. Dispenser notified to enter serials.');
    }

    public static function ajax_save_serials() {
        check_ajax_referer('hearmed_nonce','nonce');

        $order_id = intval($_POST['order_id'] ?? 0);
        $items    = $_POST['items'] ?? [];
        $user     = HearMed_Auth::current_user();
        $db       = HearMed_DB::instance();

        $order = $db->get_row(
            "SELECT patient_id FROM hearmed_core.orders WHERE id = \$1", [$order_id]
        );

        // Save serials into patient_devices (not order_items)
        foreach ($items as $item_id => $data) {
            $product_id  = intval($data['product_id'] ?? 0);
            $serial_left  = sanitize_text_field($data['left']  ?? '');
            $serial_right = sanitize_text_field($data['right'] ?? '');

            if ($product_id && ($serial_left || $serial_right)) {
                $db->insert('hearmed_core.patient_devices', [
                    'patient_id'          => $order->patient_id,
                    'product_id'          => $product_id,
                    'serial_number_left'  => $serial_left  ?: null,
                    'serial_number_right' => $serial_right ?: null,
                    'device_status'       => 'Active',
                    'created_by'          => $user->id ?? null,
                ]);
            }
        }

        // Update order status + add to fitting_queue
        $db->update('hearmed_core.orders', [
            'current_status' => 'Awaiting Fitting',
            'serials_at'     => date('Y-m-d H:i:s'),
        ], ['id' => $order_id]);

        // Add to fitting_queue if not already there
        $existing = $db->get_row(
            "SELECT id FROM hearmed_core.fitting_queue WHERE order_id = \$1", [$order_id]
        );
        if (!$existing) {
            $db->insert('hearmed_core.fitting_queue', [
                'patient_id'   => $order->patient_id,
                'order_id'     => $order_id,
                'queue_status' => 'Awaiting',
                'created_by'   => $user->id ?? null,
            ]);
        }

        self::log_status_change($order_id, 'Received', 'Awaiting Fitting', $user->id ?? null, 'Serials recorded');

        wp_send_json_success('Serials saved. Order is now on the Awaiting Fitting queue.');
    }

    public static function ajax_complete_order() {
        check_ajax_referer('hearmed_nonce','nonce');
        if (!HearMed_Auth::can('create_orders')) wp_send_json_error('Access denied.');

        $order_id = intval($_POST['order_id'] ?? 0);
        $fit_date = sanitize_text_field($_POST['fit_date'] ?? date('Y-m-d'));
        $amount   = floatval($_POST['amount'] ?? 0);
        $notes    = sanitize_textarea_field($_POST['notes'] ?? '');

        if (!$order_id) wp_send_json_error('Invalid order.');
        if (!$amount)   wp_send_json_error('Please enter the amount received.');

        $db   = HearMed_DB::instance();
        $user = HearMed_Auth::current_user();

        $order = $db->get_row(
            "SELECT o.*, p.first_name, p.last_name
             FROM hearmed_core.orders o
             JOIN hearmed_core.patients p ON p.id = o.patient_id
             WHERE o.id = \$1", [$order_id]
        );
        if (!$order) wp_send_json_error('Order not found.');

        // 1. Update order to Complete
        $db->update('hearmed_core.orders', [
            'current_status' => 'Complete',
            'fitted_at'      => $fit_date.' 00:00:00',
            'fitted_by'      => $user->id ?? null,
            'fitting_notes'  => $notes,
        ], ['id' => $order_id]);

        // 2. Update fitting_queue to Fitted
        $db->update('hearmed_core.fitting_queue', [
            'queue_status' => 'Fitted',
            'fitting_date' => $fit_date,
        ], ['order_id' => $order_id]);

        // 3. Update patient_devices with fitting_date (finalises serial records)
        $db->query(
            "UPDATE hearmed_core.patient_devices
             SET fitting_date = \$1
             WHERE patient_id = \$2 AND fitting_date IS NULL",
            [$fit_date, $order->patient_id]
        );

        // 4. Update invoice to Paid
        if ($order->invoice_id) {
            $db->update('hearmed_core.invoices', [
                'payment_status'  => 'Paid',
                'balance_remaining' => 0,
            ], ['id' => $order->invoice_id]);
        }

        // 5. Create payment record
        $db->insert('hearmed_core.payments', [
            'invoice_id'     => $order->invoice_id,
            'patient_id'     => $order->patient_id,
            'amount'         => $amount,
            'payment_date'   => $fit_date,
            'payment_method' => $order->payment_method ?? 'Card',
            'received_by'    => $user->id ?? null,
            'clinic_id'      => $order->clinic_id,
            'created_by'     => $user->id ?? null,
        ]);

        // 6. Log in patient timeline
        $db->insert('hearmed_core.patient_timeline', [
            'patient_id'  => $order->patient_id,
            'event_type'  => 'fitting_complete',
            'event_date'  => $fit_date,
            'staff_id'    => $user->id ?? null,
            'description' => 'Hearing aids fitted and paid. Order '.$order->order_number.
                             '. Amount received: €'.number_format($amount,2).
                             ($notes ? '. Notes: '.$notes : ''),
            'order_id'    => $order_id,
        ]);

        // 7. Status history log
        self::log_status_change($order_id, 'Awaiting Fitting', 'Complete', $user->id ?? null, 'Fitted and paid');

// Create proper invoice with VAT breakdown
        $payment_data = [
            'amount'         => $amount,
            'payment_date'   => $fit_date,
            'payment_method' => $order->payment_method ?? 'Card',
            'received_by'    => $user->id ?? null,
        ];
        if (class_exists('HearMed_Invoice')) {
            HearMed_Invoice::create_from_order($order_id, $payment_data);
        }

        // Queue invoice for end-of-week QBO batch sync
        $updated_order = $db->get_row("SELECT invoice_id FROM hearmed_core.orders WHERE id = \$1", [$order_id]);
        if ($updated_order && $updated_order->invoice_id) {
            $db->insert('hearmed_admin.qbo_batch_queue', [
                'entity_type' => 'invoice',
                'entity_id'   => $updated_order->invoice_id,
                'status'      => 'pending',
                'queued_at'   => date('Y-m-d H:i:s'),
                'created_by'  => $user->id ?? null,
            ]);
        }

        wp_send_json_success([
            'message'  => 'Fitting complete. Invoice queued for end-of-week QuickBooks sync.',
            'order_id' => $order_id,
            'redirect' => HearMed_Utils::page_url('orders').'?hm_action=view&order_id='.$order_id,
        ]);
    }

    public static function ajax_patient_search() {
        check_ajax_referer('hearmed_nonce','nonce');
        $q      = sanitize_text_field($_POST['q'] ?? '');
        $clinic = HearMed_Auth::current_clinic();
        if (strlen($q) < 2) wp_send_json_success([]);

        $db     = HearMed_DB::instance();
        $params = ['%'.$q.'%'];
        $cfilter = '';
        if ($clinic) {
            $cfilter = 'AND p.assigned_clinic_id = $2';
            $params[] = $clinic;
        }

        $patients = $db->get_results(
            "SELECT p.id, p.first_name, p.last_name, p.date_of_birth, p.phone
             FROM hearmed_core.patients p
             WHERE (p.first_name ILIKE \$1 OR p.last_name ILIKE \$1
                    OR CONCAT(p.first_name,' ',p.last_name) ILIKE \$1)
               AND p.is_active = true {$cfilter}
             LIMIT 8",
            $params
        );

        $results = [];
        foreach ($patients as $p) {
            $results[] = [
                'id'    => $p->id,
                'label' => $p->first_name.' '.$p->last_name.' · '.
                           ($p->date_of_birth ? date('d/m/Y',strtotime($p->date_of_birth)) : ''),
                'phone' => $p->phone,
            ];
        }
        wp_send_json_success($results);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════════

    private static function log_status_change( $order_id, $from, $to, $changed_by, $notes = '' ) {
        HearMed_DB::instance()->insert('hearmed_core.order_status_history', [
            'order_id'   => $order_id,
            'from_status'=> $from,
            'to_status'  => $to,
            'changed_by' => $changed_by,
            'notes'      => $notes,
        ]);
    }

    private static function status_badge( $status ) {
        $map = [
            'Awaiting Approval' => ['hm-badge--grey',   'Awaiting Approval'],
            'Approved'          => ['hm-badge--blue',   'Approved'],
            'Ordered'           => ['hm-badge--purple', 'Ordered'],
            'Received'          => ['hm-badge--yellow', 'Received'],
            'Awaiting Fitting'  => ['hm-badge--orange', 'Awaiting Fitting'],
            'Complete'          => ['hm-badge--green',  'Complete'],
            'Cancelled'         => ['hm-badge--red',    'Cancelled'],
        ];
        [$class, $label] = $map[$status] ?? ['hm-badge--grey', $status];
        return '<span class="hm-badge '.$class.'">'.esc_html($label).'</span>';
    }

    private static function notify( $role, $event_type, $order_id, $data ) {
        if ( class_exists('HearMed_Notifications') ) {
            HearMed_Notifications::create_for_role($role, $event_type, array_merge($data, ['order_id'=>$order_id]));
        }
    }

    private static function notify_user( $staff_id, $event_type, $order_id, $data ) {
        if ( $staff_id && class_exists('HearMed_Notifications') ) {
            HearMed_Notifications::create($staff_id, $event_type, array_merge($data, ['order_id'=>$order_id]));
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CREATE FORM JAVASCRIPT
    // ═══════════════════════════════════════════════════════════════════════
    private static function create_form_js() {
        ob_start(); ?>
        <script>
        (function() {
            const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
            const nonce   = document.querySelector('[name="nonce"]').value;
            let items = [];
            let searchTimeout;

            // Patient autocomplete
            const patientInput   = document.getElementById('hm-patient-search');
            const patientResults = document.getElementById('hm-patient-results');
            const patientIdInput = document.getElementById('hm-patient-id');
            const patientChip    = document.getElementById('hm-patient-selected');

            patientInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                if (this.value.length < 2) { patientResults.style.display='none'; return; }
                searchTimeout = setTimeout(() => {
                    fetch(ajaxUrl, {
                        method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({action:'hm_patient_search', nonce, q:patientInput.value})
                    }).then(r=>r.json()).then(d=>{
                        patientResults.innerHTML = '';
                        if (!d.success||!d.data.length) { patientResults.style.display='none'; return; }
                        d.data.forEach(p=>{
                            const div = document.createElement('div');
                            div.className = 'hm-autocomplete__item';
                            div.textContent = p.label;
                            div.addEventListener('click', ()=>selectPatient(p));
                            patientResults.appendChild(div);
                        });
                        patientResults.style.display = 'block';
                    });
                }, 300);
            });

            function selectPatient(p) {
                patientIdInput.value = p.id;
                patientInput.style.display = 'none';
                patientResults.style.display = 'none';
                patientChip.style.display = 'block';
                patientChip.innerHTML = p.label + ' <button type="button" class="hm-chip__remove" id="hm-clear-patient">×</button>';
                document.getElementById('hm-clear-patient').addEventListener('click', ()=>{
                    patientIdInput.value = '';
                    patientChip.style.display = 'none';
                    patientInput.style.display = '';
                    patientInput.value = '';
                    validateForm();
                });
                validateForm();
            }

            // Add product
            document.getElementById('hm-add-product').addEventListener('click', function() {
                const sel = document.getElementById('hm-product-select');
                const ear = document.getElementById('hm-ear-select');
                if (!sel.value) { alert('Please select a product.'); return; }
                if (!ear.value) { alert('Please select which ear.'); return; }
                const opt = sel.options[sel.selectedIndex];
                addItem({
                    id: sel.value, type:'product',
                    name: opt.dataset.name,
                    ear: ear.value,
                    speaker: '',
                    charger: false,
                    unit_price: parseFloat(opt.dataset.price) || 0,
                    vat_rate: parseFloat(opt.dataset.vat) || 23,
                    qty: 1
                });
                sel.value = ''; ear.value = '';
            });

            // Add service
            document.getElementById('hm-add-service').addEventListener('click', function() {
                const sel = document.getElementById('hm-service-select');
                if (!sel.value) { alert('Please select a service.'); return; }
                const opt = sel.options[sel.selectedIndex];
                addItem({
                    id: sel.value, type:'service',
                    name: opt.dataset.name, ear: '',
                    speaker: '', charger: false,
                    unit_price: parseFloat(opt.dataset.price) || 0,
                    vat_rate: parseFloat(opt.dataset.vat) || 23,
                    qty: 1
                });
                sel.value = '';
            });

            function addItem(item) {
                item.vat_amount = parseFloat(((item.unit_price * item.qty) * (item.vat_rate/100)).toFixed(2));
                item.line_total = parseFloat(((item.unit_price * item.qty) + item.vat_amount).toFixed(2));
                item._uid = Date.now() + Math.random();
                items.push(item); renderItems(); updateTotals(); validateForm();
            }

            function renderItems() {
                const body  = document.getElementById('hm-items-body');
                const table = document.getElementById('hm-items-table');
                const empty = document.getElementById('hm-items-empty');
                body.innerHTML = '';
                if (!items.length) { table.style.display='none'; empty.style.display=''; return; }
                table.style.display=''; empty.style.display='none';

                items.forEach((item,idx)=>{
                    const earLabel  = item.ear || '—';
                    const speakerCell = item.type==='product'
                        ? `<input type="text" class="hm-input hm-input--sm hm-input--mono hm-speaker"
                                  value="${item.speaker||''}" placeholder="e.g. 85dB" data-idx="${idx}" style="width:80px;">`
                        : '—';
                    const chargerCell = item.type==='product'
                        ? `<input type="checkbox" class="hm-charger" data-idx="${idx}" ${item.charger?'checked':''}> Yes`
                        : '—';
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${item.name}</td>
                        <td>${earLabel}</td>
                        <td><input type="number" class="hm-input hm-qty" min="1" value="${item.qty}" data-idx="${idx}" style="width:55px;"></td>
                        <td>${speakerCell}</td>
                        <td>${chargerCell}</td>
                        <td class="hm-money">€${item.unit_price.toFixed(2)}</td>
                        <td class="hm-money">€${item.vat_amount.toFixed(2)}</td>
                        <td class="hm-money">€${item.line_total.toFixed(2)}</td>
                        <td><button type="button" class="hm-btn hm-btn--sm hm-btn--danger hm-remove" data-idx="${idx}">×</button></td>`;
                    body.appendChild(tr);
                });

                body.querySelectorAll('.hm-remove').forEach(btn=>{
                    btn.addEventListener('click',function(){
                        items.splice(parseInt(this.dataset.idx),1);
                        renderItems(); updateTotals(); validateForm();
                    });
                });
                body.querySelectorAll('.hm-qty').forEach(inp=>{
                    inp.addEventListener('change',function(){
                        const i = parseInt(this.dataset.idx);
                        items[i].qty = parseInt(this.value)||1;
                        items[i].vat_amount = parseFloat(((items[i].unit_price*items[i].qty)*(items[i].vat_rate/100)).toFixed(2));
                        items[i].line_total = parseFloat(((items[i].unit_price*items[i].qty)+items[i].vat_amount).toFixed(2));
                        renderItems(); updateTotals();
                    });
                });
                body.querySelectorAll('.hm-speaker').forEach(inp=>{
                    inp.addEventListener('change',function(){items[parseInt(this.dataset.idx)].speaker=this.value;});
                });
                body.querySelectorAll('.hm-charger').forEach(chk=>{
                    chk.addEventListener('change',function(){items[parseInt(this.dataset.idx)].charger=this.checked;});
                });
            }

            ['prsi_left','prsi_right'].forEach(id=>{
                document.getElementById(id).addEventListener('change', updateTotals);
            });

            function updateTotals() {
                let sub=0, vat=0;
                items.forEach(item=>{ sub+=item.unit_price*item.qty; vat+=item.vat_amount; });
                const prsi = (document.getElementById('prsi_left').checked?500:0)
                           + (document.getElementById('prsi_right').checked?500:0);
                const total = Math.max(0, sub+vat-prsi);
                document.getElementById('hm-subtotal').textContent       = '€'+sub.toFixed(2);
                document.getElementById('hm-vat-total').textContent      = '€'+vat.toFixed(2);
                document.getElementById('hm-prsi-display').textContent   = '€'+prsi.toFixed(2);
                document.getElementById('hm-prsi-deduction').textContent = '−€'+prsi.toFixed(2);
                document.getElementById('hm-grand-total').textContent    = '€'+total.toFixed(2);
                document.getElementById('hm-items-json').value           = JSON.stringify(items);
            }

            function validateForm() {
                document.getElementById('hm-submit-order').disabled =
                    !(patientIdInput.value && items.length > 0);
            }

            document.getElementById('hm-order-form').addEventListener('submit', function(e) {
                e.preventDefault();
                document.getElementById('hm-items-json').value = JSON.stringify(items);
                const btn = document.getElementById('hm-submit-order');
                btn.disabled=true; btn.textContent='Submitting...';
                fetch(ajaxUrl, {method:'POST', body:new URLSearchParams(new FormData(this))})
                .then(r=>r.json()).then(d=>{
                    const msg = document.getElementById('hm-order-msg');
                    msg.style.display = 'block';
                    if (d.success) {
                        msg.className = 'hm-notice hm-notice--success';
                        msg.innerHTML = '<div class="hm-notice-body"><span class="hm-notice-icon">✓</span> '+d.data.message+'</div>';
                        setTimeout(()=>window.location=d.data.redirect, 1200);
                    } else {
                        msg.className = 'hm-notice hm-notice--error';
                        msg.innerHTML = '<div class="hm-notice-body"><span class="hm-notice-icon">×</span> '+d.data+'</div>';
                        btn.disabled=false; btn.textContent='Submit for Approval →';
                    }
                });
            });
        })();
        </script>
        <?php return ob_get_clean();
    }

    /**
     * Auto-fill patient on the create form when patient_id is in URL
     */
    public static function maybe_prefill_patient_js() {
        $pid = intval( $_GET['patient_id'] ?? 0 );
        if ( ! $pid ) return '';

        $db = HearMed_DB::instance();
        $p  = $db->get_row(
            "SELECT id, first_name, last_name, patient_number
             FROM hearmed_core.patients WHERE id = \$1",
            [ $pid ]
        );
        if ( ! $p ) return '';

        $data = json_encode([
            'id'    => (int) $p->id,
            'label' => trim( $p->first_name . ' ' . $p->last_name )
                       . ( $p->patient_number ? ' (' . $p->patient_number . ')' : '' ),
        ]);

        return '<script>
        (function(){
            var pp = ' . $data . ';
            var pInput = document.getElementById("hm-patient-id");
            var pSearch = document.getElementById("hm-patient-search");
            var pChip = document.getElementById("hm-patient-selected");
            if (pInput && pp.id) {
                pInput.value = pp.id;
                if (pSearch) pSearch.style.display = "none";
                if (pChip) {
                    pChip.style.display = "block";
                    pChip.innerHTML = pp.label + \' <button type="button" class="hm-chip__remove" id="hm-clear-patient">×</button>\';
                    document.getElementById("hm-clear-patient").addEventListener("click", function(){
                        pInput.value = ""; pChip.style.display = "none";
                        if (pSearch) { pSearch.style.display = ""; pSearch.value = ""; }
                    });
                }
            }
        })();
        </script>';
    }
}