<?php
/**
 * HearMed Orders Module
 *
 * Shortcode: [hearmed_orders]
 *
 * ═══════════════════════════════════════════════════════════
 * FULL ORDER WORKFLOW — 6 STAGES
 * ═══════════════════════════════════════════════════════════
 *
 *  STAGE 1 — DRAFT
 *    Dispenser creates order (products + services, PRSI, payment method)
 *    → Saves to PostgreSQL as status = 'draft'
 *    → Notifies C-Level for approval
 *
 *  STAGE 2 — APPROVED
 *    C-Level reviews and approves (or rejects)
 *    → Status = 'approved'
 *    → Notifies admin/office to place order
 *    → Printable order sheet available (manufacturer, model, style,
 *       tech level, speaker size, domes, charger Y/N)
 *
 *  STAGE 3 — ORDERED
 *    Admin confirms order placed with supplier
 *    → Status = 'ordered'
 *    → Portal logs date ordered
 *
 *  STAGE 4 — IN_CLINIC
 *    Admin clicks "Received in Clinic" when aids arrive
 *    → Status = 'in_clinic'
 *    → Dispenser who created the order is notified
 *    → Serial number entry prompted
 *
 *  STAGE 5 — AWAITING_FITTING
 *    Serial numbers entered (left/right separately, services skipped)
 *    → Status = 'awaiting_fitting'
 *    → Order appears on fitting queue
 *
 *  STAGE 6 — COMPLETE
 *    Dispenser records fitting + payment in one action (fitting = payment)
 *    → Invoice finalised as PAID
 *    → Fitting logged in patient file (patient timeline)
 *    → QBO webhook fires ← ONLY HERE, never before
 *    → Order removed from fitting queue
 *    → Logged for reporting
 *
 * ═══════════════════════════════════════════════════════════
 *
 * @package HearMed_Portal
 * @since   5.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ---------------------------------------------------------------------------
// Router — called by class-hearmed-router.php
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

        $status_filter = sanitize_key( $_GET['status'] ?? 'all' );
        $search        = sanitize_text_field( $_GET['search'] ?? '' );

        $where_parts = [];
        $params      = [];
        $i           = 1;

        if ( $clinic ) {
            $where_parts[] = "o.clinic_id = \${$i}"; $params[] = $clinic; $i++;
        }
        if ( $status_filter && $status_filter !== 'all' ) {
            $where_parts[] = "o.status = \${$i}"; $params[] = $status_filter; $i++;
        }
        if ( $search ) {
            $where_parts[] = "(p.first_name ILIKE \${$i} OR p.last_name ILIKE \${$i} OR o.invoice_number ILIKE \${$i})";
            $params[] = '%' . $search . '%'; $i++;
        }

        $where  = $where_parts ? 'WHERE ' . implode( ' AND ', $where_parts ) : '';

        $orders = $db->get_results(
            "SELECT o.id, o.invoice_number, o.status, o.created_at,
                    o.total_amount, o.prsi_grant, o.payment_method,
                    p.first_name, p.last_name, p.id AS patient_id,
                    c.name AS clinic_name,
                    CONCAT(cr.first_name,' ',cr.last_name) AS created_by_name
             FROM orders o
             JOIN patients p  ON p.id = o.patient_id
             JOIN clinics  c  ON c.id = o.clinic_id
             LEFT JOIN staff cr ON cr.id = o.created_by
             {$where}
             ORDER BY o.created_at DESC
             LIMIT 150",
            $params
        );

        $cp    = $clinic ? [$clinic] : [];
        $cw    = $clinic ? 'WHERE clinic_id = $1' : '';
        $raw   = $db->get_results("SELECT status, COUNT(*) AS cnt FROM orders {$cw} GROUP BY status", $cp);
        $counts = ['all' => 0];
        foreach ($raw as $r) { $counts[$r->status] = (int)$r->cnt; $counts['all'] += (int)$r->cnt; }

        $base = HearMed_Utils::page_url('orders');

        ob_start(); ?>
        <div class="hm-main hm-orders-list">

            <div class="hm-page-header">
                <h1 class="hm-page-title">Orders</h1>
                <?php if ( HearMed_Auth::can('create_orders') ) : ?>
                <a href="<?php echo esc_url($base.'?hm_action=create'); ?>" class="hm-btn hm-btn--primary">
                    + New Order
                </a>
                <?php endif; ?>
            </div>

            <div class="hm-tabs">
                <?php
                $tabs = [
                    'all'              => 'All',
                    'draft'            => 'Draft',
                    'approved'         => 'Approved',
                    'ordered'          => 'Ordered',
                    'in_clinic'        => 'In Clinic',
                    'awaiting_fitting' => 'Awaiting Fitting',
                    'complete'         => 'Complete',
                    'rejected'         => 'Rejected',
                ];
                foreach ( $tabs as $key => $label ) :
                    $active = $status_filter === $key ? 'hm-tab--active' : '';
                    $cnt    = $counts[$key] ?? 0;
                    $urgent = '';
                    if ($key==='draft'     && $role==='c_level'                        && $cnt>0) $urgent='hm-tab--urgent';
                    if ($key==='approved'  && in_array($role,['admin','finance'])      && $cnt>0) $urgent='hm-tab--urgent';
                    if ($key==='in_clinic' && $cnt>0)                                            $urgent='hm-tab--urgent';
                    ?>
                    <a href="<?php echo esc_url($base.'?status='.$key); ?>"
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
                       placeholder="Search patient name or invoice number..."
                       value="<?php echo esc_attr($search); ?>">
                <button type="submit" class="hm-btn hm-btn--secondary">Search</button>
            </form>

            <div class="hm-table-wrap">
                <table class="hm-table">
                    <thead>
                        <tr>
                            <th>Invoice #</th><th>Patient</th><th>Clinic</th>
                            <th>Dispenser</th><th>Total</th><th>PRSI</th>
                            <th>Status</th><th>Created</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ( empty($orders) ) : ?>
                        <tr><td colspan="9" class="hm-table__empty">No orders found.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $orders as $o ) : ?>
                        <tr>
                            <td class="hm-mono"><?php echo esc_html($o->invoice_number ?: '—'); ?></td>
                            <td>
                                <a href="<?php echo esc_url(HearMed_Utils::page_url('patients').'?patient_id='.$o->patient_id); ?>">
                                    <?php echo esc_html($o->first_name.' '.$o->last_name); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($o->clinic_name); ?></td>
                            <td class="hm-muted"><?php echo esc_html($o->created_by_name ?: '—'); ?></td>
                            <td class="hm-money">€<?php echo number_format($o->total_amount,2); ?></td>
                            <td class="hm-money <?php echo $o->prsi_grant>0?'hm-text--green':'hm-muted'; ?>">
                                <?php echo $o->prsi_grant>0 ? '€'.number_format($o->prsi_grant,2) : '—'; ?>
                            </td>
                            <td><?php echo self::status_badge($o->status); ?></td>
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
    // STAGE 1 — CREATE ORDER FORM
    // ═══════════════════════════════════════════════════════════════════════
    public static function render_create() {

        if ( ! HearMed_Auth::can('create_orders') ) {
            return '<div class="hm-notice hm-notice--error">Access denied.</div>';
        }

        $db = HearMed_DB::instance();

        $products = $db->get_results(
            "SELECT id, manufacturer, model, style, tech_level, sell_price, vat_rate,
                    has_charger, default_speaker_size
             FROM products WHERE active = true ORDER BY manufacturer, model", []
        );
        $services = $db->get_results(
            "SELECT id, name, price, vat_rate FROM services WHERE active = true ORDER BY name", []
        );

        $base  = HearMed_Utils::page_url('orders');
        $nonce = wp_create_nonce('hearmed_nonce');

        ob_start(); ?>
        <div class="hm-main hm-orders-create">

            <div class="hm-page-header">
                <a href="<?php echo esc_url($base); ?>" class="hm-back-btn">← Orders</a>
                <h1 class="hm-page-title">New Order</h1>
                <span class="hm-workflow-hint">Will be sent to C-Level for approval before ordering from supplier.</span>
            </div>

            <form id="hm-order-form" class="hm-form hm-card">
                <input type="hidden" name="nonce"  value="<?php echo esc_attr($nonce); ?>">
                <input type="hidden" name="action" value="hm_create_order">

                <!-- PATIENT -->
                <div class="hm-form__section">
                    <h2 class="hm-form__section-title">Patient</h2>
                    <div class="hm-form__row">
                        <div class="hm-form__field hm-form__field--wide">
                            <label class="hm-label">Search Patient <span class="hm-required">*</span></label>
                            <input type="text" id="hm-patient-search" class="hm-input"
                                   placeholder="Type patient name..." autocomplete="off">
                            <div id="hm-patient-results" class="hm-autocomplete" style="display:none;"></div>
                            <input type="hidden" name="patient_id" id="hm-patient-id">
                            <div id="hm-patient-selected" class="hm-patient-chip" style="display:none;"></div>
                        </div>
                    </div>
                </div>

                <!-- LINE ITEMS -->
                <div class="hm-form__section">
                    <h2 class="hm-form__section-title">Items</h2>
                    <div id="hm-items-empty" class="hm-order-items__empty">No items added yet.</div>
                    <table class="hm-table hm-order-items__table" id="hm-items-table" style="display:none;">
                        <thead>
                            <tr>
                                <th>Item</th><th>Ear</th><th>Qty</th>
                                <th>Speaker Size</th><th>Charger?</th>
                                <th>Unit Price</th><th>VAT</th><th>Line Total</th><th></th>
                            </tr>
                        </thead>
                        <tbody id="hm-items-body"></tbody>
                    </table>

                    <div class="hm-order-items__actions">
                        <div class="hm-item-adder">
                            <select id="hm-product-select" class="hm-input hm-input--sm">
                                <option value="">— Add a Product —</option>
                                <?php foreach ($products as $p) : ?>
                                <option value="<?php echo esc_attr($p->id); ?>"
                                        data-name="<?php echo esc_attr($p->manufacturer.' '.$p->model.' '.$p->style); ?>"
                                        data-price="<?php echo esc_attr($p->sell_price); ?>"
                                        data-vat="<?php echo esc_attr($p->vat_rate); ?>"
                                        data-tech="<?php echo esc_attr($p->tech_level); ?>"
                                        data-manufacturer="<?php echo esc_attr($p->manufacturer); ?>"
                                        data-style="<?php echo esc_attr($p->style); ?>"
                                        data-charger="<?php echo esc_attr($p->has_charger ? '1':'0'); ?>"
                                        data-speaker="<?php echo esc_attr($p->default_speaker_size ?? ''); ?>">
                                    <?php echo esc_html($p->manufacturer.' — '.$p->model.' '.$p->style.' ('.$p->tech_level.')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <select id="hm-ear-select" class="hm-input hm-input--sm">
                                <option value="">— Ear —</option>
                                <option value="left">Left</option>
                                <option value="right">Right</option>
                                <option value="binaural">Binaural (both)</option>
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
                                        data-name="<?php echo esc_attr($s->name); ?>"
                                        data-price="<?php echo esc_attr($s->price); ?>"
                                        data-vat="<?php echo esc_attr($s->vat_rate); ?>">
                                    <?php echo esc_html($s->name.' — €'.number_format($s->price,2)); ?>
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
                    <p class="hm-form__hint">Deducted from invoice. HearMed claims from DSP on patient's behalf.</p>
                    <div class="hm-prsi-row">
                        <label class="hm-checkbox-label">
                            <input type="checkbox" name="prsi_left"  id="prsi_left"  value="1"> Left ear — €500
                        </label>
                        <label class="hm-checkbox-label">
                            <input type="checkbox" name="prsi_right" id="prsi_right" value="1"> Right ear — €500
                        </label>
                        <div class="hm-prsi-total">
                            PRSI deduction: <strong id="hm-prsi-display">€0.00</strong>
                        </div>
                    </div>
                </div>

                <!-- PAYMENT METHOD -->
                <div class="hm-form__section">
                    <h2 class="hm-form__section-title">Expected Payment Method</h2>
                    <p class="hm-form__hint">Payment is collected at fitting. Recorded now for planning.</p>
                    <select name="payment_method" class="hm-input" required>
                        <option value="">— Select —</option>
                        <option value="cash">Cash</option>
                        <option value="card">Card (in-clinic)</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="humm">Humm</option>
                        <option value="cheque">Cheque</option>
                        <option value="prsi_only">PRSI Grant only (no patient payment)</option>
                    </select>
                </div>

                <!-- CLINICAL NOTES -->
                <div class="hm-form__section">
                    <h2 class="hm-form__section-title">Clinical Notes for Order</h2>
                    <textarea name="notes" class="hm-input hm-input--textarea"
                              placeholder="Dome size, special requirements, any notes for the admin ordering this..." rows="3"></textarea>
                </div>

                <!-- TOTALS -->
                <div class="hm-order-totals hm-card hm-card--inset">
                    <div class="hm-order-totals__row"><span>Subtotal</span><span id="hm-subtotal">€0.00</span></div>
                    <div class="hm-order-totals__row"><span>VAT</span><span id="hm-vat-total">€0.00</span></div>
                    <div class="hm-order-totals__row hm-text--green"><span>PRSI Grant Deduction</span><span id="hm-prsi-deduction">−€0.00</span></div>
                    <div class="hm-order-totals__row hm-order-totals__row--total"><span>Patient Pays</span><span id="hm-grand-total">€0.00</span></div>
                </div>

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
        <?php return ob_get_clean();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // VIEW ORDER — stage-aware action panel
    // ═══════════════════════════════════════════════════════════════════════
    public static function render_view( $order_id ) {

        if ( ! $order_id ) return '<div class="hm-notice hm-notice--error">No order specified.</div>';

        $db    = HearMed_DB::instance();
        $role  = HearMed_Auth::current_role();
        $nonce = wp_create_nonce('hearmed_nonce');

        $order = $db->get_row(
            "SELECT o.*,
                    p.first_name, p.last_name, p.email, p.phone, p.dob, p.id AS patient_id,
                    p.address_line1, p.address_line2, p.city, p.county, p.eircode,
                    c.name AS clinic_name,
                    CONCAT(cr.first_name,' ',cr.last_name) AS created_by_name
             FROM orders o
             JOIN patients p  ON p.id = o.patient_id
             JOIN clinics  c  ON c.id = o.clinic_id
             LEFT JOIN staff cr ON cr.id = o.created_by
             WHERE o.id = \$1", [$order_id]
        );
        if (!$order) return '<div class="hm-notice hm-notice--error">Order not found.</div>';

        $items = $db->get_results(
            "SELECT oi.*,
                    COALESCE(pr.manufacturer||' '||pr.model||' '||pr.style, sv.name) AS item_name,
                    pr.tech_level
             FROM order_items oi
             LEFT JOIN products pr ON pr.id = oi.product_id
             LEFT JOIN services sv ON sv.id = oi.service_id
             WHERE oi.order_id = \$1 ORDER BY oi.id", [$order_id]
        );

        $base = HearMed_Utils::page_url('orders');

        // Role + status action permissions
        $can_approve  = $role==='c_level'                       && $order->status==='draft';
        $can_order    = in_array($role,['admin','finance'])      && $order->status==='approved';
        $can_receive  = in_array($role,['admin','finance'])      && $order->status==='ordered';
        $can_serials  = $order->status==='in_clinic';
        $can_complete = in_array($role,['dispenser','c_level'])  && $order->status==='awaiting_fitting';
        $can_print    = !in_array($order->status,['draft','rejected']);

        ob_start(); ?>
        <div class="hm-main hm-order-view">

            <div class="hm-page-header">
                <a href="<?php echo esc_url($base); ?>" class="hm-back-btn">← Orders</a>
                <h1 class="hm-page-title">
                    <?php echo esc_html($order->invoice_number ?: 'Order #'.$order_id); ?>
                </h1>
                <?php echo self::status_badge($order->status); ?>
            </div>

            <div class="hm-order-view__grid">

                <!-- Invoice panel -->
                <div class="hm-card hm-order-invoice">

                    <div class="hm-invoice__header">
                        <div class="hm-invoice__brand">HearMed</div>
                        <div class="hm-invoice__meta">
                            <div><strong>Ref:</strong> <?php echo esc_html($order->invoice_number ?: 'Pending'); ?></div>
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
                                <td><?php echo esc_html(ucfirst($item->ear_side ?? '—')); ?></td>
                                <td><?php echo esc_html($item->quantity); ?></td>
                                <td><?php echo esc_html($item->speaker_size ?: '—'); ?></td>
                                <td><?php echo !empty($item->needs_charger) ? 'Yes' : '—'; ?></td>
                                <td class="hm-money">€<?php echo number_format($item->unit_price,2); ?></td>
                                <td class="hm-money">€<?php echo number_format($item->vat_amount,2); ?></td>
                                <td class="hm-money">€<?php echo number_format($item->line_total,2); ?></td>
                            </tr>
                            <?php if ($item->serial_left || $item->serial_right) : ?>
                            <tr class="hm-serial-row">
                                <td colspan="8" class="hm-muted" style="font-size:0.8rem;padding-top:0;">
                                    <?php if ($item->serial_left)  echo '↳ Left: '.esc_html($item->serial_left).'&nbsp;&nbsp;'; ?>
                                    <?php if ($item->serial_right) echo '↳ Right: '.esc_html($item->serial_right); ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr><td colspan="7" class="hm-text-right">Subtotal</td>
                                <td class="hm-money">€<?php echo number_format($order->subtotal,2); ?></td></tr>
                            <tr><td colspan="7" class="hm-text-right">VAT</td>
                                <td class="hm-money">€<?php echo number_format($order->vat_total,2); ?></td></tr>
                            <?php if ($order->prsi_grant > 0) : ?>
                            <tr class="hm-text--green">
                                <td colspan="7" class="hm-text-right">PRSI Grant Deduction</td>
                                <td class="hm-money">−€<?php echo number_format($order->prsi_grant,2); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr class="hm-invoice__total-row">
                                <td colspan="7" class="hm-text-right"><strong>Patient Pays</strong></td>
                                <td class="hm-money"><strong>€<?php echo number_format($order->total_amount,2); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>

                    <?php if ($order->notes) : ?>
                    <div class="hm-invoice__notes"><strong>Notes:</strong> <?php echo esc_html($order->notes); ?></div>
                    <?php endif; ?>

                    <?php if ($order->status === 'complete') : ?>
                    <div class="hm-invoice__qbo hm-muted" style="font-size:0.8rem;margin-top:1rem;">
                        QuickBooks: <?php echo esc_html(ucfirst($order->qbo_sync_status ?? 'pending')); ?>
                        <?php if ($order->qbo_invoice_id) echo ' · Ref: '.esc_html($order->qbo_invoice_id); ?>
                    </div>
                    <?php endif; ?>

                </div>

                <!-- Sidebar: Actions + Timeline -->
                <div class="hm-order-view__sidebar">

                    <!-- STAGE 2: Approve/Reject -->
                    <?php if ($can_approve) : ?>
                    <div class="hm-card hm-card--action">
                        <h3 class="hm-card__title">⏳ Awaiting Your Approval</h3>
                        <p class="hm-form__hint">Review the order then approve or reject.</p>
                        <textarea id="hm-approval-note" class="hm-input hm-input--textarea" rows="2"
                                  placeholder="Optional note (visible to admin/dispenser)..."></textarea>
                        <div class="hm-btn-group" style="margin-top:0.75rem;">
                            <button class="hm-btn hm-btn--primary hm-order-action"
                                    data-ajax="hm_approve_order" data-order-id="<?php echo $order_id; ?>"
                                    data-nonce="<?php echo esc_attr($nonce); ?>"
                                    data-confirm="Approve this order and notify admin to proceed?">
                                ✓ Approve Order
                            </button>
                            <button class="hm-btn hm-btn--danger hm-order-action"
                                    data-ajax="hm_reject_order" data-order-id="<?php echo $order_id; ?>"
                                    data-nonce="<?php echo esc_attr($nonce); ?>"
                                    data-confirm="Reject this order? The dispenser will be notified.">
                                ✕ Reject
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- STAGE 3: Place order with supplier -->
                    <?php if ($can_order) : ?>
                    <div class="hm-card hm-card--action">
                        <h3 class="hm-card__title">📋 Place Order with Supplier</h3>
                        <a href="<?php echo esc_url($base.'?hm_action=print&order_id='.$order_id); ?>"
                           target="_blank" class="hm-btn hm-btn--secondary hm-btn--block" style="margin-bottom:0.75rem;">
                            🖨 Print Order Sheet
                        </a>
                        <p class="hm-form__hint">Once placed with the supplier, click below to record this.</p>
                        <button class="hm-btn hm-btn--primary hm-btn--block hm-order-action"
                                data-ajax="hm_mark_ordered" data-order-id="<?php echo $order_id; ?>"
                                data-nonce="<?php echo esc_attr($nonce); ?>"
                                data-confirm="Confirm you have placed this order with the supplier?">
                            ✓ Order Placed with Supplier
                        </button>
                    </div>
                    <?php endif; ?>

                    <!-- STAGE 4: Received in clinic -->
                    <?php if ($can_receive) : ?>
                    <div class="hm-card hm-card--action">
                        <h3 class="hm-card__title">📦 Hearing Aids Arrived?</h3>
                        <p class="hm-form__hint">Mark as received. The dispenser will be notified and serial numbers will be recorded.</p>
                        <button class="hm-btn hm-btn--primary hm-btn--block hm-order-action"
                                data-ajax="hm_mark_received" data-order-id="<?php echo $order_id; ?>"
                                data-nonce="<?php echo esc_attr($nonce); ?>"
                                data-confirm="Mark this order as received in clinic?">
                            📦 Received in Clinic
                        </button>
                    </div>
                    <?php endif; ?>

                    <!-- STAGE 4→5: Serial numbers -->
                    <?php if ($can_serials) : ?>
                    <div class="hm-card hm-card--action">
                        <h3 class="hm-card__title">🔢 Enter Serial Numbers</h3>
                        <p class="hm-form__hint">Record serial numbers before the patient is fitted.</p>
                        <a href="<?php echo esc_url($base.'?hm_action=serials&order_id='.$order_id); ?>"
                           class="hm-btn hm-btn--primary hm-btn--block">
                            Enter Serial Numbers →
                        </a>
                    </div>
                    <?php endif; ?>

                    <!-- STAGE 6: Fitting + Payment -->
                    <?php if ($can_complete) : ?>
                    <div class="hm-card hm-card--action">
                        <h3 class="hm-card__title">🎉 Patient is Here — Fit + Pay</h3>
                        <p class="hm-form__hint">
                            This finalises the invoice, logs the fitting in the patient file,
                            and sends the <strong>paid</strong> invoice to QuickBooks.
                        </p>
                        <a href="<?php echo esc_url($base.'?hm_action=complete&order_id='.$order_id); ?>"
                           class="hm-btn hm-btn--primary hm-btn--block">
                            Record Fitting + Payment →
                        </a>
                    </div>
                    <?php endif; ?>

                    <!-- Print sheet (non-draft orders) -->
                    <?php if ($can_print && !$can_order) : ?>
                    <div class="hm-card">
                        <a href="<?php echo esc_url($base.'?hm_action=print&order_id='.$order_id); ?>"
                           target="_blank" class="hm-btn hm-btn--secondary hm-btn--block">
                            🖨 Print Order Sheet
                        </a>
                    </div>
                    <?php endif; ?>

                    <!-- Timeline -->
                    <div class="hm-card">
                        <h3 class="hm-card__title">Timeline</h3>
                        <div class="hm-timeline">
                            <?php
                            $stages = [
                                ['Order Created',       $order->created_at,  ['draft','approved','ordered','in_clinic','awaiting_fitting','complete']],
                                ['Approved by C-Level', $order->approved_at, ['approved','ordered','in_clinic','awaiting_fitting','complete']],
                                ['Order Placed',        $order->ordered_at,  ['ordered','in_clinic','awaiting_fitting','complete']],
                                ['Arrived in Clinic',   $order->arrived_at,  ['in_clinic','awaiting_fitting','complete']],
                                ['Serials Recorded',    $order->serials_at ?? null, ['awaiting_fitting','complete']],
                                ['Fitted & Paid',       $order->fitted_at,   ['complete']],
                            ];
                            foreach ($stages as [$label, $date, $statuses]) :
                                $done = in_array($order->status, $statuses);
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
                    method:'POST',
                    headers:{'Content-Type':'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: me.dataset.ajax,
                        order_id: me.dataset.orderId,
                        nonce: me.dataset.nonce,
                        note: noteEl ? noteEl.value : ''
                    })
                }).then(r=>r.json()).then(d=>{
                    if (d.success) location.reload();
                    else { alert('Error: ' + d.data); me.disabled=false; }
                });
            });
        });
        </script>
        <?php return ob_get_clean();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SERIAL NUMBERS — STAGE 4 → 5
    // ═══════════════════════════════════════════════════════════════════════
    public static function render_serials( $order_id ) {

        $db    = HearMed_DB::instance();
        $nonce = wp_create_nonce('hearmed_nonce');
        $base  = HearMed_Utils::page_url('orders');

        $order = $db->get_row(
            "SELECT o.id, o.status, p.first_name, p.last_name
             FROM orders o JOIN patients p ON p.id=o.patient_id WHERE o.id=\$1", [$order_id]
        );
        if (!$order || $order->status !== 'in_clinic') {
            return '<div class="hm-notice hm-notice--error">Order not available for serial entry.</div>';
        }

        // Products only — services have no serials
        $ha_items = $db->get_results(
            "SELECT oi.id, oi.ear_side, oi.serial_left, oi.serial_right,
                    pr.manufacturer, pr.model, pr.style
             FROM order_items oi
             JOIN products pr ON pr.id = oi.product_id
             WHERE oi.order_id = \$1 AND oi.item_type = 'product'
             ORDER BY oi.id", [$order_id]
        );

        ob_start(); ?>
        <div class="hm-main hm-serials-form">
            <div class="hm-page-header">
                <a href="<?php echo esc_url($base.'?hm_action=view&order_id='.$order_id); ?>" class="hm-back-btn">← Order</a>
                <h1 class="hm-page-title">Serial Numbers</h1>
                <span class="hm-workflow-hint"><?php echo esc_html($order->first_name.' '.$order->last_name); ?></span>
            </div>

            <div class="hm-card" style="max-width:600px;">

                <?php if (empty($ha_items)) : ?>
                <div class="hm-notice hm-notice--info">No hearing aid products on this order — no serials needed.</div>
                <div style="margin-top:1rem;">
                    <button class="hm-btn hm-btn--primary hm-skip-serials"
                            data-order-id="<?php echo $order_id; ?>"
                            data-nonce="<?php echo esc_attr($nonce); ?>">
                        Continue → Move to Awaiting Fitting
                    </button>
                </div>
                <?php else : ?>

                <p class="hm-form__hint">Record serial numbers from the packaging. Left and right are recorded separately.</p>

                <form id="hm-serials-form">
                    <input type="hidden" name="nonce"    value="<?php echo esc_attr($nonce); ?>">
                    <input type="hidden" name="action"   value="hm_save_serials">
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">

                    <?php foreach ($ha_items as $item) :
                        $name       = $item->manufacturer.' '.$item->model.' '.$item->style;
                        $ear        = ucfirst($item->ear_side ?? 'unknown');
                        $need_left  = in_array($item->ear_side, ['left','binaural']);
                        $need_right = in_array($item->ear_side, ['right','binaural']);
                    ?>
                    <div class="hm-card hm-card--inset" style="margin-bottom:1rem;">
                        <strong><?php echo esc_html($name); ?></strong>
                        <span class="hm-muted">(<?php echo esc_html($ear); ?>)</span>

                        <?php if ($need_left) : ?>
                        <div class="hm-form__field" style="margin-top:0.75rem;">
                            <label class="hm-label">Left Ear Serial</label>
                            <input type="text" name="serial[<?php echo $item->id; ?>][left]"
                                   class="hm-input hm-input--mono" placeholder="Serial number..."
                                   value="<?php echo esc_attr($item->serial_left ?? ''); ?>">
                        </div>
                        <?php endif; ?>

                        <?php if ($need_right) : ?>
                        <div class="hm-form__field" style="margin-top:0.5rem;">
                            <label class="hm-label">Right Ear Serial</label>
                            <input type="text" name="serial[<?php echo $item->id; ?>][right]"
                                   class="hm-input hm-input--mono" placeholder="Serial number..."
                                   value="<?php echo esc_attr($item->serial_right ?? ''); ?>">
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
                    fetch(ajaxUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
                        body:new URLSearchParams({action:'hm_save_serials',order_id:this.dataset.orderId,nonce:this.dataset.nonce})
                    }).then(r=>r.json()).then(d=>{ if(d.success) location.href=base; });
                });
            }

            const form = document.getElementById('hm-serials-form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const btn = form.querySelector('[type=submit]');
                    btn.disabled=true; btn.textContent='Saving...';
                    fetch(ajaxUrl,{method:'POST',body:new URLSearchParams(new FormData(form))})
                    .then(r=>r.json()).then(d=>{
                        const msg=document.getElementById('hm-serials-msg');
                        msg.style.display='block';
                        if (d.success) {
                            msg.className='hm-notice hm-notice--success';
                            msg.textContent='Serials saved! Moving to Awaiting Fitting...';
                            setTimeout(()=>location.href=base, 1200);
                        } else {
                            msg.className='hm-notice hm-notice--error';
                            msg.textContent=d.data;
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
    // FITTING + PAYMENT — STAGE 6  (QBO fires here, never before)
    // ═══════════════════════════════════════════════════════════════════════
    public static function render_complete( $order_id ) {

        $db    = HearMed_DB::instance();
        $nonce = wp_create_nonce('hearmed_nonce');
        $base  = HearMed_Utils::page_url('orders');

        $order = $db->get_row(
            "SELECT o.*, p.first_name, p.last_name
             FROM orders o JOIN patients p ON p.id=o.patient_id WHERE o.id=\$1", [$order_id]
        );
        if (!$order || $order->status !== 'awaiting_fitting') {
            return '<div class="hm-notice hm-notice--error">Order not ready for fitting yet.</div>';
        }

        ob_start(); ?>
        <div class="hm-main hm-complete-form">
            <div class="hm-page-header">
                <a href="<?php echo esc_url($base.'?hm_action=view&order_id='.$order_id); ?>" class="hm-back-btn">← Order</a>
                <h1 class="hm-page-title">Record Fitting + Payment</h1>
            </div>

            <div class="hm-card" style="max-width:520px;">

                <div class="hm-complete-summary">
                    <p><strong>Patient:</strong> <?php echo esc_html($order->first_name.' '.$order->last_name); ?></p>
                    <p><strong>Payment Method:</strong>
                        <?php echo esc_html(ucfirst(str_replace('_',' ',$order->payment_method ?? ''))); ?>
                    </p>
                    <?php if ($order->prsi_grant > 0) : ?>
                    <p class="hm-muted" style="font-size:0.875rem;">
                        PRSI grant of €<?php echo number_format($order->prsi_grant,2); ?> already deducted below.
                        Claim to DSP outstanding.
                    </p>
                    <?php endif; ?>
                    <p class="hm-complete-summary__total" style="font-size:1.25rem;margin-top:0.5rem;">
                        <strong>Collect from patient:
                            <span class="hm-text--teal">€<?php echo number_format($order->total_amount,2); ?></span>
                        </strong>
                    </p>
                </div>

                <hr class="hm-divider">

                <div class="hm-form__field">
                    <label class="hm-label">Fitting Date</label>
                    <input type="date" id="hm-fit-date" class="hm-input" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="hm-form__field" style="margin-top:1rem;">
                    <label class="hm-label">Amount Received (€)</label>
                    <input type="number" id="hm-fit-amount" class="hm-input" step="0.01"
                           value="<?php echo number_format($order->total_amount,2,'.',''); ?>">
                </div>
                <div class="hm-form__field" style="margin-top:1rem;">
                    <label class="hm-label">Fitting Notes (optional)</label>
                    <textarea id="hm-fit-notes" class="hm-input hm-input--textarea" rows="2"
                              placeholder="Clinical fitting notes, adjustments made etc."></textarea>
                </div>

                <div class="hm-notice hm-notice--info" style="margin-top:1.25rem;font-size:0.875rem;">
                    ℹ️ This will: mark the order complete, log the fitting in the patient file,
                    and send the <strong>paid</strong> invoice to QuickBooks.
                </div>

                <div class="hm-form__actions" style="margin-top:1.25rem;">
                    <button class="hm-btn hm-btn--primary hm-btn--block" id="hm-confirm-complete"
                            data-order-id="<?php echo $order_id; ?>"
                            data-nonce="<?php echo esc_attr($nonce); ?>">
                        ✓ Confirm Fitted + Paid — Finalise Invoice
                    </button>
                </div>
                <div id="hm-complete-msg" class="hm-notice" style="display:none;margin-top:1rem;"></div>
            </div>
        </div>

        <script>
        document.getElementById('hm-confirm-complete').addEventListener('click', function() {
            if (!confirm('Confirm patient has been fitted and payment received? This cannot be undone.')) return;
            const btn = this;
            btn.disabled=true; btn.textContent='Finalising...';
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'hm_complete_order',
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
                    msg.textContent=d.data.message;
                    setTimeout(()=>window.location=d.data.redirect, 1500);
                } else {
                    msg.className='hm-notice hm-notice--error';
                    msg.textContent=d.data;
                    btn.disabled=false;
                    btn.textContent='✓ Confirm Fitted + Paid — Finalise Invoice';
                }
            });
        });
        </script>
        <?php return ob_get_clean();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PRINTABLE ORDER SHEET — admin gives this to / uses to order from supplier
    // ═══════════════════════════════════════════════════════════════════════
    public static function render_order_sheet( $order_id ) {

        $db    = HearMed_DB::instance();
        $order = $db->get_row(
            "SELECT o.*, p.first_name, p.last_name, p.dob, c.name AS clinic_name,
                    CONCAT(cr.first_name,' ',cr.last_name) AS created_by_name
             FROM orders o
             JOIN patients p  ON p.id = o.patient_id
             JOIN clinics  c  ON c.id = o.clinic_id
             LEFT JOIN staff cr ON cr.id = o.created_by
             WHERE o.id = \$1", [$order_id]
        );
        if (!$order) return '<p>Order not found.</p>';

        $items = $db->get_results(
            "SELECT oi.*, pr.manufacturer, pr.model, pr.style, pr.tech_level
             FROM order_items oi
             JOIN products pr ON pr.id = oi.product_id
             WHERE oi.order_id = \$1 AND oi.item_type = 'product'
             ORDER BY oi.id", [$order_id]
        );

        ob_start(); ?>
        <!DOCTYPE html><html><head>
        <meta charset="UTF-8">
        <title>Order Sheet — <?php echo esc_html($order->invoice_number ?: 'Order #'.$order_id); ?></title>
        <style>
            * { box-sizing:border-box; }
            body { font-family:Arial,sans-serif; max-width:820px; margin:2rem auto; color:#151B33; font-size:13px; }
            h1 { color:#151B33; margin-bottom:0.25rem; }
            .teal { color:#0BB4C4; }
            .sub { color:#64748b; font-size:12px; margin-bottom:2rem; }
            .meta-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:0.75rem 1.5rem; margin-bottom:1.5rem; }
            .meta-grid div strong { display:block; font-size:10px; color:#94a3b8; text-transform:uppercase; letter-spacing:.05em; margin-bottom:2px; }
            .meta-grid div span { font-size:13px; font-weight:600; }
            table { width:100%; border-collapse:collapse; margin-top:1rem; }
            th { background:#151B33; color:#fff; padding:7px 10px; text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:.05em; }
            td { padding:8px 10px; border-bottom:1px solid #e2e8f0; font-size:13px; }
            tr:last-child td { border-bottom:none; }
            .badge { background:#0BB4C4; color:#fff; padding:1px 7px; border-radius:3px; font-size:11px; font-weight:bold; }
            .notes-box { margin-top:1.5rem; padding:0.75rem 1rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px; }
            .sign-area { margin-top:2.5rem; border-top:2px solid #151B33; padding-top:1.25rem; }
            .sign-row { display:flex; gap:3rem; margin-top:0.75rem; }
            .sign-field { flex:1; }
            .sign-field span { display:block; font-size:10px; color:#94a3b8; text-transform:uppercase; letter-spacing:.05em; margin-bottom:0.5rem; }
            .sign-line { border-bottom:1px solid #94a3b8; min-height:28px; }
            .footer { margin-top:2rem; font-size:10px; color:#94a3b8; }
            @media print {
                body { margin:1cm; }
                .no-print { display:none; }
            }
        </style>
        </head><body>

        <h1>HearMed <span class="teal">Order Sheet</span></h1>
        <p class="sub">Print and use to place order with supplier. Keep a copy on file.</p>

        <div class="meta-grid">
            <div><strong>Order Ref</strong><span><?php echo esc_html($order->invoice_number ?: '#'.$order_id); ?></span></div>
            <div><strong>Date</strong><span><?php echo date('d M Y',strtotime($order->created_at)); ?></span></div>
            <div><strong>Clinic</strong><span><?php echo esc_html($order->clinic_name); ?></span></div>
            <div><strong>Dispenser / Ordered By</strong><span><?php echo esc_html($order->created_by_name ?: '—'); ?></span></div>
            <div><strong>Patient</strong><span><?php echo esc_html($order->first_name.' '.$order->last_name); ?></span></div>
            <div><strong>Patient DOB</strong><span><?php echo $order->dob ? date('d/m/Y',strtotime($order->dob)) : '—'; ?></span></div>
        </div>

        <?php if ($order->notes) : ?>
        <div class="notes-box"><strong>Clinical Notes:</strong> <?php echo esc_html($order->notes); ?></div>
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
                <td><?php echo esc_html($item->model); ?></td>
                <td><?php echo esc_html($item->style); ?></td>
                <td><?php echo esc_html($item->tech_level ?: '—'); ?></td>
                <td><?php echo esc_html(ucfirst($item->ear_side ?? '—')); ?></td>
                <td><?php echo esc_html($item->speaker_size ?: '—'); ?></td>
                <td><?php echo !empty($item->needs_charger) ? '<span class="badge">YES</span>' : 'No'; ?></td>
                <td><?php echo esc_html($item->quantity); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="sign-area">
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

    /** STAGE 1: Create order → status = draft → notify C-Level */
    public static function ajax_create_order() {
        check_ajax_referer('hearmed_nonce','nonce');
        if (!HearMed_Auth::can('create_orders')) wp_send_json_error('Access denied.');

        $patient_id     = intval($_POST['patient_id'] ?? 0);
        $payment_method = sanitize_key($_POST['payment_method'] ?? '');
        $notes          = sanitize_textarea_field($_POST['notes'] ?? '');
        $prsi_left      = intval($_POST['prsi_left'] ?? 0);
        $prsi_right     = intval($_POST['prsi_right'] ?? 0);
        $items          = json_decode(sanitize_text_field($_POST['items_json'] ?? '[]'), true);

        if (!$patient_id)     wp_send_json_error('Please select a patient.');
        if (!$payment_method) wp_send_json_error('Please select a payment method.');
        if (empty($items))    wp_send_json_error('Please add at least one item.');

        $db     = HearMed_DB::instance();
        $clinic = HearMed_Auth::current_clinic();
        $user   = HearMed_Auth::current_user();

        $subtotal = $vat_total = 0;
        foreach ($items as $item) {
            $subtotal  += floatval($item['line_total']) - floatval($item['vat_amount']);
            $vat_total += floatval($item['vat_amount']);
        }
        $prsi_grant = ($prsi_left?500:0) + ($prsi_right?500:0);
        $total_due  = max(0, $subtotal + $vat_total - $prsi_grant);
        $inv_num    = 'HM-'.date('Ymd').'-'.str_pad(rand(1,9999),4,'0',STR_PAD_LEFT);

        $order_id = $db->insert('orders', [
            'patient_id'     => $patient_id,
            'clinic_id'      => $clinic,
            'created_by'     => $user->id ?? null,
            'invoice_number' => $inv_num,
            'status'         => 'draft',
            'subtotal'       => $subtotal,
            'vat_total'      => $vat_total,
            'prsi_grant'     => $prsi_grant,
            'prsi_left'      => $prsi_left  ? true : false,
            'prsi_right'     => $prsi_right ? true : false,
            'total_amount'   => $total_due,
            'payment_method' => $payment_method,
            'notes'          => $notes,
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        if (!$order_id) wp_send_json_error('Failed to save order. Please try again.');

        foreach ($items as $item) {
            $db->insert('order_items', [
                'order_id'     => $order_id,
                'item_type'    => sanitize_key($item['type']),
                'product_id'   => $item['type']==='product' ? intval($item['id']) : null,
                'service_id'   => $item['type']==='service' ? intval($item['id']) : null,
                'item_name'    => sanitize_text_field($item['name']),
                'ear_side'     => sanitize_key($item['ear'] ?? 'na'),
                'speaker_size' => sanitize_text_field($item['speaker'] ?? ''),
                'needs_charger'=> !empty($item['charger']),
                'quantity'     => intval($item['qty']),
                'unit_price'   => floatval($item['unit_price']),
                'vat_rate'     => floatval($item['vat_rate']),
                'vat_amount'   => floatval($item['vat_amount']),
                'line_total'   => floatval($item['line_total']),
            ]);
        }

        self::notify('c_level', 'order_awaiting_approval', $order_id, ['invoice_number'=>$inv_num]);

        wp_send_json_success([
            'message'  => 'Order '.$inv_num.' submitted for C-Level approval.',
            'order_id' => $order_id,
            'redirect' => HearMed_Utils::page_url('orders').'?hm_action=view&order_id='.$order_id,
        ]);
    }

    /** STAGE 2a: C-Level approves → status = approved → notify admin */
    public static function ajax_approve_order() {
        check_ajax_referer('hearmed_nonce','nonce');
        if (HearMed_Auth::current_role() !== 'c_level') wp_send_json_error('Access denied.');

        $order_id = intval($_POST['order_id'] ?? 0);
        $note     = sanitize_textarea_field($_POST['note'] ?? '');
        $user     = HearMed_Auth::current_user();
        $db       = HearMed_DB::instance();

        $db->update('orders', [
            'status'        => 'approved',
            'approved_by'   => $user->id ?? null,
            'approved_at'   => date('Y-m-d H:i:s'),
            'approval_note' => $note,
        ], ['id'=>$order_id]);

        self::notify('admin', 'order_approved_place_order', $order_id, []);

        wp_send_json_success('Order approved. Admin has been notified to place the order with the supplier.');
    }

    /** STAGE 2b: C-Level rejects → notify dispenser */
    public static function ajax_reject_order() {
        check_ajax_referer('hearmed_nonce','nonce');
        if (HearMed_Auth::current_role() !== 'c_level') wp_send_json_error('Access denied.');

        $order_id = intval($_POST['order_id'] ?? 0);
        $note     = sanitize_textarea_field($_POST['note'] ?? '');
        $db       = HearMed_DB::instance();

        $order = $db->get_row("SELECT created_by FROM orders WHERE id=\$1", [$order_id]);
        $db->update('orders', ['status'=>'rejected','approval_note'=>$note], ['id'=>$order_id]);
        self::notify_user($order->created_by ?? null, 'order_rejected', $order_id, ['note'=>$note]);

        wp_send_json_success('Order rejected. Dispenser has been notified.');
    }

    /** STAGE 3: Admin confirms order placed → status = ordered */
    public static function ajax_mark_ordered() {
        check_ajax_referer('hearmed_nonce','nonce');
        if (!HearMed_Auth::can('manage_orders')) wp_send_json_error('Access denied.');

        $order_id = intval($_POST['order_id'] ?? 0);
        HearMed_DB::instance()->update('orders',
            ['status'=>'ordered','ordered_at'=>date('Y-m-d H:i:s')], ['id'=>$order_id]
        );
        wp_send_json_success('Marked as ordered. Waiting for delivery.');
    }

    /** STAGE 4: Admin marks received → status = in_clinic → notify dispenser */
    public static function ajax_mark_received() {
        check_ajax_referer('hearmed_nonce','nonce');
        if (!HearMed_Auth::can('manage_orders')) wp_send_json_error('Access denied.');

        $order_id = intval($_POST['order_id'] ?? 0);
        $db       = HearMed_DB::instance();
        $order    = $db->get_row("SELECT created_by, invoice_number FROM orders WHERE id=\$1", [$order_id]);

        $db->update('orders', ['status'=>'in_clinic','arrived_at'=>date('Y-m-d H:i:s')], ['id'=>$order_id]);
        self::notify_user($order->created_by ?? null, 'order_arrived_in_clinic', $order_id, [
            'invoice_number' => $order->invoice_number,
        ]);

        wp_send_json_success('Marked as received in clinic. Dispenser notified.');
    }

    /** STAGE 4→5: Save serials → status = awaiting_fitting */
    public static function ajax_save_serials() {
        check_ajax_referer('hearmed_nonce','nonce');

        $order_id = intval($_POST['order_id'] ?? 0);
        $serials  = $_POST['serial'] ?? [];
        $db       = HearMed_DB::instance();

        foreach ($serials as $item_id => $sides) {
            $upd = [];
            if (!empty($sides['left']))  $upd['serial_left']  = sanitize_text_field($sides['left']);
            if (!empty($sides['right'])) $upd['serial_right'] = sanitize_text_field($sides['right']);
            if ($upd) $db->update('order_items', $upd, ['id'=>intval($item_id)]);
        }

        $db->update('orders', [
            'status'     => 'awaiting_fitting',
            'serials_at' => date('Y-m-d H:i:s'),
        ], ['id'=>$order_id]);

        wp_send_json_success('Serial numbers saved. Order is now on the Awaiting Fitting queue.');
    }

    /**
     * STAGE 6: Fitting + Payment complete
     *
     * - Status → complete
     * - Logs fitting in patient timeline
     * - QBO webhook fires here — ONLY here — with paid invoice
     */
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
            "SELECT o.*, p.first_name, p.last_name FROM orders o
             JOIN patients p ON p.id=o.patient_id WHERE o.id=\$1", [$order_id]
        );
        if (!$order) wp_send_json_error('Order not found.');

        // Mark complete
        $db->update('orders', [
            'status'           => 'complete',
            'fitted_at'        => $fit_date.' 00:00:00',
            'fitted_by'        => $user->id ?? null,
            'payment_received' => $fit_date,
            'payment_amount'   => $amount,
            'fitting_notes'    => $notes,
        ], ['id'=>$order_id]);

        // Log in patient timeline
        $db->insert('patient_timeline', [
            'patient_id'  => $order->patient_id,
            'event_type'  => 'fitting_complete',
            'event_date'  => $fit_date,
            'staff_id'    => $user->id ?? null,
            'description' => 'Hearing aids fitted and paid. Invoice '.$order->invoice_number.
                             '. Amount received: €'.number_format($amount,2).
                             ($notes ? '. Notes: '.$notes : ''),
            'order_id'    => $order_id,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        // ✅ QBO fires here — paid invoice only — never before this point
        HearMed_Accounting::on_invoice_created( $order_id );

        wp_send_json_success([
            'message'  => 'Fitting complete. Invoice finalised and sent to QuickBooks.',
            'order_id' => $order_id,
            'redirect' => HearMed_Utils::page_url('orders').'?hm_action=view&order_id='.$order_id,
        ]);
    }

    /** Patient autocomplete */
    public static function ajax_patient_search() {
        check_ajax_referer('hearmed_nonce','nonce');
        $q      = sanitize_text_field($_POST['q'] ?? '');
        $clinic = HearMed_Auth::current_clinic();
        if (strlen($q) < 2) wp_send_json_success([]);

        $db     = HearMed_DB::instance();
        $where  = $clinic ? 'AND pc.clinic_id = $2' : '';
        $params = $clinic ? ['%'.$q.'%', $clinic] : ['%'.$q.'%'];

        $patients = $db->get_results(
            "SELECT DISTINCT p.id, p.first_name, p.last_name, p.dob, p.phone
             FROM patients p
             LEFT JOIN patient_clinics pc ON pc.patient_id=p.id
             WHERE (p.first_name ILIKE \$1 OR p.last_name ILIKE \$1
                    OR CONCAT(p.first_name,' ',p.last_name) ILIKE \$1) {$where}
             LIMIT 8",
            $params
        );

        $results = [];
        foreach ($patients as $p) {
            $results[] = [
                'id'    => $p->id,
                'label' => $p->first_name.' '.$p->last_name.' · '.date('d/m/Y',strtotime($p->dob)),
                'phone' => $p->phone,
            ];
        }
        wp_send_json_success($results);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════════

    private static function status_badge( $status ) {
        $map = [
            'draft'            => ['hm-badge--grey',   'Draft'],
            'approved'         => ['hm-badge--blue',   'Approved'],
            'ordered'          => ['hm-badge--purple', 'Ordered'],
            'in_clinic'        => ['hm-badge--yellow', 'In Clinic'],
            'awaiting_fitting' => ['hm-badge--orange', 'Awaiting Fitting'],
            'complete'         => ['hm-badge--green',  'Complete'],
            'rejected'         => ['hm-badge--red',    'Rejected'],
        ];
        [$class, $label] = $map[$status] ?? ['hm-badge--grey', ucfirst($status)];
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

            // ── Patient autocomplete ──────────────────────────────────────
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
                        patientResults.innerHTML='';
                        if (!d.success||!d.data.length) { patientResults.style.display='none'; return; }
                        d.data.forEach(p=>{
                            const div=document.createElement('div');
                            div.className='hm-autocomplete__item';
                            div.textContent=p.label;
                            div.addEventListener('click',()=>selectPatient(p));
                            patientResults.appendChild(div);
                        });
                        patientResults.style.display='block';
                    });
                }, 300);
            });

            function selectPatient(p) {
                patientIdInput.value=p.id;
                patientInput.style.display='none';
                patientResults.style.display='none';
                patientChip.style.display='block';
                patientChip.innerHTML=p.label+' <button type="button" class="hm-chip__remove" id="hm-clear-patient">×</button>';
                document.getElementById('hm-clear-patient').addEventListener('click',()=>{
                    patientIdInput.value=''; patientChip.style.display='none';
                    patientInput.style.display=''; patientInput.value='';
                    validateForm();
                });
                validateForm();
            }

            // ── Add product ───────────────────────────────────────────────
            document.getElementById('hm-add-product').addEventListener('click', function() {
                const sel=document.getElementById('hm-product-select');
                const ear=document.getElementById('hm-ear-select');
                if (!sel.value) { alert('Please select a product.'); return; }
                if (!ear.value) { alert('Please select which ear.'); return; }
                const opt=sel.options[sel.selectedIndex];
                addItem({
                    id:sel.value, type:'product',
                    name:opt.dataset.name, ear:ear.value,
                    speaker: opt.dataset.speaker||'',
                    charger: opt.dataset.charger==='1',
                    tech: opt.dataset.tech,
                    unit_price:parseFloat(opt.dataset.price),
                    vat_rate:parseFloat(opt.dataset.vat), qty:1
                });
                sel.value=''; ear.value='';
            });

            // ── Add service ───────────────────────────────────────────────
            document.getElementById('hm-add-service').addEventListener('click', function() {
                const sel=document.getElementById('hm-service-select');
                if (!sel.value) { alert('Please select a service.'); return; }
                const opt=sel.options[sel.selectedIndex];
                addItem({
                    id:sel.value, type:'service', name:opt.dataset.name, ear:'na',
                    speaker:'', charger:false,
                    unit_price:parseFloat(opt.dataset.price),
                    vat_rate:parseFloat(opt.dataset.vat), qty:1
                });
                sel.value='';
            });

            function addItem(item) {
                item.vat_amount=parseFloat(((item.unit_price*item.qty)*(item.vat_rate/100)).toFixed(2));
                item.line_total=parseFloat(((item.unit_price*item.qty)+item.vat_amount).toFixed(2));
                item._uid=Date.now()+Math.random();
                items.push(item); renderItems(); updateTotals(); validateForm();
            }

            function renderItems() {
                const body  = document.getElementById('hm-items-body');
                const table = document.getElementById('hm-items-table');
                const empty = document.getElementById('hm-items-empty');
                body.innerHTML='';
                if (!items.length) { table.style.display='none'; empty.style.display=''; return; }
                table.style.display=''; empty.style.display='none';

                items.forEach((item,idx)=>{
                    const earLabel=item.ear!=='na' ? item.ear.charAt(0).toUpperCase()+item.ear.slice(1) : '—';
                    const speakerCell = item.type==='product'
                        ? `<input type="text" class="hm-input hm-input--sm hm-input--mono hm-speaker-input"
                                  value="${item.speaker||''}" placeholder="e.g. 85dB"
                                  data-idx="${idx}" style="width:80px;">`
                        : '—';
                    const chargerCell = item.type==='product'
                        ? `<input type="checkbox" class="hm-charger-check" data-idx="${idx}" ${item.charger?'checked':''}> Yes`
                        : '—';
                    const tr=document.createElement('tr');
                    tr.innerHTML=`
                        <td>${item.name}</td>
                        <td>${earLabel}</td>
                        <td><input type="number" class="hm-input hm-input--qty" min="1"
                                   value="${item.qty}" data-idx="${idx}" style="width:55px;"></td>
                        <td>${speakerCell}</td>
                        <td>${chargerCell}</td>
                        <td class="hm-money">€${item.unit_price.toFixed(2)}</td>
                        <td class="hm-money">€${item.vat_amount.toFixed(2)}</td>
                        <td class="hm-money">€${item.line_total.toFixed(2)}</td>
                        <td><button type="button" class="hm-btn hm-btn--sm hm-btn--danger hm-remove-item"
                                    data-idx="${idx}">✕</button></td>`;
                    body.appendChild(tr);
                });

                body.querySelectorAll('.hm-remove-item').forEach(btn=>{
                    btn.addEventListener('click',function(){
                        items.splice(parseInt(this.dataset.idx),1);
                        renderItems(); updateTotals(); validateForm();
                    });
                });
                body.querySelectorAll('.hm-input--qty').forEach(inp=>{
                    inp.addEventListener('change',function(){
                        const i=parseInt(this.dataset.idx);
                        items[i].qty=parseInt(this.value)||1;
                        items[i].vat_amount=parseFloat(((items[i].unit_price*items[i].qty)*(items[i].vat_rate/100)).toFixed(2));
                        items[i].line_total=parseFloat(((items[i].unit_price*items[i].qty)+items[i].vat_amount).toFixed(2));
                        renderItems(); updateTotals();
                    });
                });
                body.querySelectorAll('.hm-speaker-input').forEach(inp=>{
                    inp.addEventListener('change',function(){items[parseInt(this.dataset.idx)].speaker=this.value;});
                });
                body.querySelectorAll('.hm-charger-check').forEach(chk=>{
                    chk.addEventListener('change',function(){items[parseInt(this.dataset.idx)].charger=this.checked;});
                });
            }

            // ── PRSI toggles ──────────────────────────────────────────────
            ['prsi_left','prsi_right'].forEach(id=>{
                document.getElementById(id).addEventListener('change', updateTotals);
            });

            function updateTotals() {
                let sub=0, vat=0;
                items.forEach(item=>{sub+=item.unit_price*item.qty; vat+=item.vat_amount;});
                const prsi=(document.getElementById('prsi_left').checked?500:0)+
                           (document.getElementById('prsi_right').checked?500:0);
                const total=Math.max(0,sub+vat-prsi);
                document.getElementById('hm-subtotal').textContent        = '€'+sub.toFixed(2);
                document.getElementById('hm-vat-total').textContent       = '€'+vat.toFixed(2);
                document.getElementById('hm-prsi-display').textContent    = '€'+prsi.toFixed(2);
                document.getElementById('hm-prsi-deduction').textContent  = '−€'+prsi.toFixed(2);
                document.getElementById('hm-grand-total').textContent     = '€'+total.toFixed(2);
                document.getElementById('hm-items-json').value            = JSON.stringify(items);
            }

            function validateForm() {
                document.getElementById('hm-submit-order').disabled =
                    !(patientIdInput.value && items.length > 0);
            }

            // ── Form submit ───────────────────────────────────────────────
            document.getElementById('hm-order-form').addEventListener('submit', function(e) {
                e.preventDefault();
                document.getElementById('hm-items-json').value = JSON.stringify(items);
                const btn=document.getElementById('hm-submit-order');
                btn.disabled=true; btn.textContent='Submitting...';
                fetch(ajaxUrl, {method:'POST', body:new URLSearchParams(new FormData(this))})
                .then(r=>r.json()).then(d=>{
                    const msg=document.getElementById('hm-order-msg');
                    msg.style.display='block';
                    if (d.success) {
                        msg.className='hm-notice hm-notice--success';
                        msg.textContent=d.data.message;
                        setTimeout(()=>window.location=d.data.redirect, 1200);
                    } else {
                        msg.className='hm-notice hm-notice--error';
                        msg.textContent=d.data;
                        btn.disabled=false; btn.textContent='Submit for Approval →';
                    }
                });
            });
        })();
        </script>
        <?php return ob_get_clean();
    }
}