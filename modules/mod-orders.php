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
 *  hearmed_reference.services         service catalogue
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
// AJAX registrations
// ---------------------------------------------------------------------------
add_action( 'wp_ajax_hm_print_order_sheet',           [ 'HearMed_Orders', 'ajax_print_order_sheet' ] );
add_action( 'wp_ajax_hm_create_order',                [ 'HearMed_Orders', 'ajax_create_order' ] );
add_action( 'wp_ajax_hm_approve_order',               [ 'HearMed_Orders', 'ajax_approve_order' ] );
add_action( 'wp_ajax_hm_reject_order',                [ 'HearMed_Orders', 'ajax_reject_order' ] );
add_action( 'wp_ajax_hm_mark_ordered',                [ 'HearMed_Orders', 'ajax_mark_ordered' ] );
add_action( 'wp_ajax_hm_mark_received',               [ 'HearMed_Orders', 'ajax_mark_received' ] );
add_action( 'wp_ajax_hm_save_serials',                [ 'HearMed_Orders', 'ajax_save_serials' ] );
add_action( 'wp_ajax_hm_complete_order',              [ 'HearMed_Orders', 'ajax_complete_order' ] );
add_action( 'wp_ajax_hm_patient_search',              [ 'HearMed_Orders', 'ajax_patient_search' ] );
add_action( 'wp_ajax_hm_get_orders',                  [ 'HearMed_Orders', 'ajax_get_orders' ] );
add_action( 'wp_ajax_hm_get_order_detail',            [ 'HearMed_Orders', 'ajax_get_order_detail' ] );
add_action( 'wp_ajax_hm_get_order_products',          [ 'HearMed_Orders', 'ajax_get_order_products' ] );
add_action( 'wp_ajax_hm_update_order_status',         [ 'HearMed_Orders', 'ajax_update_order_status' ] );
add_action( 'wp_ajax_hm_get_pending_orders',          [ 'HearMed_Orders', 'ajax_get_pending_orders' ] );
add_action( 'wp_ajax_hm_get_awaiting_fitting',        [ 'HearMed_Orders', 'ajax_get_awaiting_fitting' ] );
add_action( 'wp_ajax_hm_update_awaiting_fitting_order', [ 'HearMed_Orders', 'ajax_update_awaiting_fitting_order' ] );
add_action( 'wp_ajax_hm_prefit_cancel',               [ 'HearMed_Orders', 'ajax_prefit_cancel' ] );
add_action( 'wp_ajax_hm_get_patient_credit_balance',  [ 'HearMed_Orders', 'ajax_get_patient_credit_balance' ] );
add_action( 'wp_ajax_hm_record_order_deposit',        [ 'HearMed_Orders', 'ajax_record_order_deposit' ] );
add_action( 'wp_ajax_hm_get_order_stock',             [ 'HearMed_Orders', 'ajax_get_order_stock' ] );

// S6-FIX: INVOICE TRIGGER
add_action( 'wp_ajax_hm_get_patient_open_orders', function() {
    check_ajax_referer( 'hm_nonce', 'nonce' );

    $patient_id = intval( $_POST['patient_id'] ?? 0 );
    if ( ! $patient_id ) {
        wp_send_json_error( 'No patient ID' );
    }

    $db = HearMed_DB::instance();
    $orders = $db->get_results(
        "SELECT id, order_number, order_date, grand_total, current_status,
                (SELECT string_agg(p.product_name, ', ')
                 FROM hearmed_core.order_items oi
             JOIN hearmed_reference.products p ON p.id = oi.item_id
             WHERE oi.order_id = o.id AND oi.item_type = 'product') AS products
         FROM hearmed_core.orders o
         WHERE o.patient_id = $1
         AND o.current_status IN ('Approved', 'Ordered', 'Received', 'Awaiting Fitting')
         ORDER BY o.order_date DESC",
        [ $patient_id ]
    );

    wp_send_json_success( $orders ?: [] );
} );

// ---------------------------------------------------------------------------
// Main class
// ---------------------------------------------------------------------------
class HearMed_Orders {

    private static $tracking_schema_ensured = false;

    // ═══════════════════════════════════════════════════════════════════════
    // LIST VIEW — Two white-bubble tables: Awaiting Order + Ordered
    // ═══════════════════════════════════════════════════════════════════════
    public static function render_list() {
        $db     = HearMed_DB::instance();
        $clinic = HearMed_Auth::current_clinic();
        $now_ts = time();
        $nonce  = wp_create_nonce('hm_nonce');
        $ajax   = admin_url('admin-ajax.php');

        $clinic_where = $clinic ? 'AND o.clinic_id = $1' : '';
        $clinic_param = $clinic ? [$clinic] : [];

        // ── Alert-level helper ──
        $calc_alert = function($days, $hearing_aid_class) {
            $cls = strtolower(trim($hearing_aid_class));
            $amber = ($cls === 'ready-fit') ? 5 : 10;
            $red   = ($cls === 'ready-fit') ? 7 : 14;
            if ($days >= $red)   return 'red';
            if ($days >= $amber) return 'amber';
            return '';
        };

        // ── 1) Awaiting Order — approved, ready to place with manufacturer ──
        $awaiting = $db->get_results(
            "SELECT o.id, o.order_number, o.created_at, o.approved_date,
                    p.first_name, p.last_name, p.patient_number, p.id AS patient_id,
                    c.clinic_name,
                    CONCAT(s.first_name,' ',s.last_name) AS dispenser_name,
                    m.name AS manufacturer_name,
                    COALESCE(pr.display_name, pr.product_name, oi.item_description) AS product_name,
                    COALESCE(pr.hearing_aid_class,'') AS hearing_aid_class
             FROM hearmed_core.orders o
             LEFT JOIN hearmed_core.patients p ON p.id = o.patient_id
             LEFT JOIN hearmed_reference.clinics c ON c.id = o.clinic_id
             LEFT JOIN hearmed_reference.staff s ON s.id = o.staff_id
             LEFT JOIN hearmed_core.order_items oi ON oi.order_id = o.id AND oi.item_type = 'product'
             LEFT JOIN hearmed_reference.products pr ON pr.id = oi.item_id
             LEFT JOIN hearmed_reference.manufacturers m ON m.id = pr.manufacturer_id
             WHERE o.current_status = 'Approved' {$clinic_where}
             ORDER BY o.approved_date ASC, o.created_at ASC",
            $clinic_param
        ) ?: [];

        $seen = [];
        $awaiting_rows = [];
        foreach ($awaiting as $o) {
            if (isset($seen[$o->id])) continue;
            $seen[$o->id] = true;
            $ref  = $o->approved_date ?? $o->created_at;
            $days = $ref ? (int)floor(($now_ts - strtotime($ref)) / 86400) : 0;
            $o->_days  = $days;
            $o->_alert = $calc_alert($days, $o->hearing_aid_class);
            $awaiting_rows[] = $o;
        }

        // ── 2) Ordered — placed with manufacturer, awaiting delivery ──
        $ordered = $db->get_results(
            "SELECT o.id, o.order_number, o.created_at, o.order_date,
                    p.first_name, p.last_name, p.patient_number, p.id AS patient_id,
                    c.clinic_name,
                    CONCAT(s.first_name,' ',s.last_name) AS dispenser_name,
                    m.name AS manufacturer_name,
                    COALESCE(pr.display_name, pr.product_name, oi.item_description) AS product_name,
                    COALESCE(pr.hearing_aid_class,'') AS hearing_aid_class,
                    osh.changed_at AS ordered_at
             FROM hearmed_core.orders o
             LEFT JOIN hearmed_core.patients p ON p.id = o.patient_id
             LEFT JOIN hearmed_reference.clinics c ON c.id = o.clinic_id
             LEFT JOIN hearmed_reference.staff s ON s.id = o.staff_id
             LEFT JOIN hearmed_core.order_items oi ON oi.order_id = o.id AND oi.item_type = 'product'
             LEFT JOIN hearmed_reference.products pr ON pr.id = oi.item_id
             LEFT JOIN hearmed_reference.manufacturers m ON m.id = pr.manufacturer_id
             LEFT JOIN LATERAL (
                 SELECT changed_at FROM hearmed_core.order_status_history
                 WHERE order_id = o.id AND to_status = 'Ordered'
                 ORDER BY changed_at DESC LIMIT 1
             ) osh ON true
             WHERE o.current_status IN ('Ordered','Received','Awaiting Fitting') {$clinic_where}
             ORDER BY
                 CASE o.current_status
                     WHEN 'Ordered'          THEN 1
                     WHEN 'Received'         THEN 2
                     WHEN 'Awaiting Fitting' THEN 3
                 END,
                 o.order_date ASC, o.created_at ASC",
            $clinic_param
        ) ?: [];

        $seen = [];
        $ordered_rows = [];
        foreach ($ordered as $o) {
            if (isset($seen[$o->id])) continue;
            $seen[$o->id] = true;
            $ref  = $o->ordered_at ?? $o->order_date ?? $o->created_at;
            $days = $ref ? (int)floor(($now_ts - strtotime($ref)) / 86400) : 0;
            $o->_days  = $days;
            $o->_alert = $calc_alert($days, $o->hearing_aid_class);
            $ordered_rows[] = $o;
        }

        $base = HearMed_Utils::page_url('orders');

        ob_start(); ?>
        <style>
        .hmo-bubble{background:#fff;border-radius:14px;border:1px solid #f1f5f9;box-shadow:0 2px 10px rgba(15,23,42,.045);overflow:hidden;margin-bottom:28px;}
        .hmo-bubble-hd{padding:16px 22px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #f1f5f9;}
        .hmo-bubble-title{font-size:15px;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:10px;margin:0;}
        .hmo-pill{display:inline-flex;align-items:center;justify-content:center;min-width:24px;height:24px;padding:0 8px;border-radius:12px;font-size:11px;font-weight:700;background:#e2e8f0;color:#475569;}
        .hmo-pill-teal{background:rgba(11,180,196,.12);color:#0a8a96;}
        .hmo-pill-blue{background:rgba(59,130,246,.12);color:#1e40af;}
        .hmo-days{font-weight:700;font-size:12px;white-space:nowrap;}
        .hmo-days-green{color:#059669;}.hmo-days-amber{color:#d97706;}.hmo-days-red{color:#dc2626;}
        .hmo-alert{display:inline-flex;padding:3px 8px;border-radius:6px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;margin-left:6px;}
        .hmo-alert-amber{background:#fef3cd;color:#92400e;}.hmo-alert-red{background:#fee2e2;color:#991b1b;}
        .hmo-cls{display:inline-flex;padding:3px 8px;border-radius:6px;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.3px;}
        .hmo-cls-custom{background:#ede9fe;color:#6d28d9;}.hmo-cls-ready{background:#dbeafe;color:#1e40af;}
        tr.hmo-row-amber td{background:rgba(245,158,11,.04)!important;}
        tr.hmo-row-red td{background:rgba(239,68,68,.04)!important;}
        .hmo-empty{padding:32px 20px;text-align:center;color:#94a3b8;font-size:13px;}
        .hmo-empty-icon{font-size:28px;margin-bottom:6px;}
        .hmo-acts{display:flex;gap:6px;justify-content:flex-end;white-space:nowrap;}
        .hmo-btn{display:inline-flex;align-items:center;padding:5px 12px;border-radius:6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;cursor:pointer;border:none;transition:all .15s;text-decoration:none;white-space:nowrap;}
        .hmo-btn-pdf{background:#fff;color:#475569;border:1px solid #e2e8f0;}
        .hmo-btn-pdf:hover{background:#f8fafc;border-color:var(--hm-teal,#0bb4c4);color:var(--hm-teal,#0bb4c4);}
        .hmo-btn-action{background:var(--hm-teal,#0bb4c4);color:#fff;}
        .hmo-btn-action:hover{background:#0a9aa8;}
        .hmo-btn-action:disabled{opacity:.6;cursor:not-allowed;}
        </style>

        <div class="hm-content hm-orders-list">

            <div class="hm-page-header">
                <h1 class="hm-page-title">Orders</h1>
                <a href="<?php echo esc_url($base.'?hm_action=create'); ?>" class="hm-btn hm-btn--primary">
                    + New Order
                </a>
            </div>

            <!-- ═══ BUBBLE 1: Awaiting Order ═══ -->
            <div class="hmo-bubble">
                <div class="hmo-bubble-hd">
                    <h2 class="hmo-bubble-title">
                        Awaiting Order
                        <span class="hmo-pill hmo-pill-teal"><?php echo count($awaiting_rows); ?></span>
                    </h2>
                </div>
                <?php if (empty($awaiting_rows)) : ?>
                    <div class="hmo-empty"><div class="hmo-empty-icon">✓</div>No orders awaiting placement.</div>
                <?php else : ?>
                <div style="overflow-x:auto;">
                    <table class="hm-table">
                        <thead>
                            <tr>
                                <th>Order #</th><th>Patient</th><th>Clinic</th>
                                <th>Dispenser</th><th>Manufacturer</th><th>Product</th><th>Class</th>
                                <th>Date Approved</th><th>Days Waiting</th><th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($awaiting_rows as $o) :
                            $row_cls = $o->_alert === 'red' ? ' class="hmo-row-red"' : ($o->_alert === 'amber' ? ' class="hmo-row-amber"' : '');
                            $days_cls = $o->_alert === 'red' ? 'hmo-days-red' : ($o->_alert === 'amber' ? 'hmo-days-amber' : 'hmo-days-green');
                            $cls_lc = strtolower(trim($o->hearing_aid_class));
                            $cls_badge = $o->hearing_aid_class
                                ? '<span class="hmo-cls '.($cls_lc==='custom'?'hmo-cls-custom':'hmo-cls-ready').'">'.esc_html($o->hearing_aid_class).'</span>'
                                : '—';
                            $alert_badge = '';
                            if ($o->_alert === 'amber') $alert_badge = '<span class="hmo-alert hmo-alert-amber">LATE</span>';
                            if ($o->_alert === 'red')   $alert_badge = '<span class="hmo-alert hmo-alert-red">OVERDUE</span>';
                            $pdf_url = $ajax . '?action=hm_print_order_sheet&nonce=' . $nonce . '&order_id=' . $o->id;
                        ?>
                        <tr<?php echo $row_cls; ?>>
                            <td><strong><?php echo esc_html($o->order_number); ?></strong></td>
                            <td>
                                <a href="<?php echo esc_url(HearMed_Utils::page_url('patients').'?patient_id='.$o->patient_id); ?>" style="color:var(--hm-teal);text-decoration:none;">
                                    <?php echo esc_html($o->first_name.' '.$o->last_name); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($o->clinic_name); ?></td>
                            <td class="hm-muted"><?php echo esc_html($o->dispenser_name ?: '—'); ?></td>
                            <td><?php echo esc_html($o->manufacturer_name ?: '—'); ?></td>
                            <td style="max-width:180px;"><?php echo esc_html($o->product_name ?: '—'); ?></td>
                            <td><?php echo $cls_badge; ?></td>
                            <td class="hm-muted"><?php echo $o->approved_date ? date('d M Y', strtotime($o->approved_date)) : date('d M Y', strtotime($o->created_at)); ?></td>
                            <td>
                                <span class="hmo-days <?php echo $days_cls; ?>"><?php echo $o->_days; ?> day<?php echo $o->_days !== 1 ? 's' : ''; ?></span>
                                <?php echo $alert_badge; ?>
                            </td>
                            <td>
                                <div class="hmo-acts">
                                    <a href="<?php echo esc_url($pdf_url); ?>" target="_blank" class="hmo-btn hmo-btn-pdf">PDF</a>
                                    <button class="hmo-btn hmo-btn-action hmo-mark-btn" data-id="<?php echo $o->id; ?>" data-num="<?php echo esc_attr($o->order_number); ?>" data-status="Ordered">Ordered →</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- ═══ BUBBLE 2: Ordered — Awaiting Delivery / Fitting ═══ -->
            <div class="hmo-bubble">
                <div class="hmo-bubble-hd">
                    <h2 class="hmo-bubble-title">
                        Ordered &amp; Awaiting Fitting
                        <span class="hmo-pill hmo-pill-blue"><?php echo count($ordered_rows); ?></span>
                    </h2>
                </div>
                <?php if (empty($ordered_rows)) : ?>
                    <div class="hmo-empty"><div class="hmo-empty-icon">📦</div>No orders in the pipeline.</div>
                <?php else : ?>
                <div style="overflow-x:auto;">
                    <table class="hm-table">
                        <thead>
                            <tr>
                                <th>Order #</th><th>Patient</th><th>Clinic</th>
                                <th>Dispenser</th><th>Manufacturer</th><th>Product</th><th>Class</th>
                                <th>Status</th><th>Days Since Order</th><th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $st_colors = [
                            'Ordered'          => ['bg'=>'rgba(11,180,196,.08)','color'=>'#0a8a96','border'=>'rgba(11,180,196,.25)'],
                            'Received'         => ['bg'=>'rgba(59,130,246,.08)','color'=>'#1e40af','border'=>'rgba(59,130,246,.25)'],
                            'Awaiting Fitting' => ['bg'=>'rgba(139,92,246,.08)','color'=>'#6d28d9','border'=>'rgba(139,92,246,.25)'],
                        ];
                        foreach ($ordered_rows as $o) :
                            $row_cls = $o->_alert === 'red' ? ' class="hmo-row-red"' : ($o->_alert === 'amber' ? ' class="hmo-row-amber"' : '');
                            $days_cls = $o->_alert === 'red' ? 'hmo-days-red' : ($o->_alert === 'amber' ? 'hmo-days-amber' : 'hmo-days-green');
                            $cls_lc = strtolower(trim($o->hearing_aid_class));
                            $cls_badge = $o->hearing_aid_class
                                ? '<span class="hmo-cls '.($cls_lc==='custom'?'hmo-cls-custom':'hmo-cls-ready').'">'.esc_html($o->hearing_aid_class).'</span>'
                                : '—';
                            $alert_badge = '';
                            if ($o->_alert === 'amber') $alert_badge = '<span class="hmo-alert hmo-alert-amber">LATE</span>';
                            if ($o->_alert === 'red')   $alert_badge = '<span class="hmo-alert hmo-alert-red">OVERDUE</span>';
                            $ref_date = $o->ordered_at ?? $o->order_date ?? $o->created_at;
                            $pdf_url = $ajax . '?action=hm_print_order_sheet&nonce=' . $nonce . '&order_id=' . $o->id;
                            $st = $o->current_status;
                            $sc = $st_colors[$st] ?? ['bg'=>'#f8fafc','color'=>'#64748b','border'=>'#e2e8f0'];
                        ?>
                        <tr<?php echo $row_cls; ?>>
                            <td><strong><?php echo esc_html($o->order_number); ?></strong></td>
                            <td>
                                <a href="<?php echo esc_url(HearMed_Utils::page_url('patients').'?patient_id='.$o->patient_id); ?>" style="color:var(--hm-teal);text-decoration:none;">
                                    <?php echo esc_html($o->first_name.' '.$o->last_name); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($o->clinic_name); ?></td>
                            <td class="hm-muted"><?php echo esc_html($o->dispenser_name ?: '—'); ?></td>
                            <td><?php echo esc_html($o->manufacturer_name ?: '—'); ?></td>
                            <td style="max-width:180px;"><?php echo esc_html($o->product_name ?: '—'); ?></td>
                            <td><?php echo $cls_badge; ?></td>
                            <td><span style="display:inline-flex;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;background:<?php echo $sc['bg']; ?>;color:<?php echo $sc['color']; ?>;border:1px solid <?php echo $sc['border']; ?>;"><?php echo esc_html($st); ?></span></td>
                            <td>
                                <span class="hmo-days <?php echo $days_cls; ?>"><?php echo $o->_days; ?> day<?php echo $o->_days !== 1 ? 's' : ''; ?></span>
                                <?php echo $alert_badge; ?>
                            </td>
                            <td>
                                <div class="hmo-acts">
                                    <a href="<?php echo esc_url($pdf_url); ?>" target="_blank" class="hmo-btn hmo-btn-pdf">PDF</a>
                                    <?php if ($st === 'Ordered') : ?>
                                        <button class="hmo-btn hmo-btn-action hmo-mark-btn" data-id="<?php echo $o->id; ?>" data-num="<?php echo esc_attr($o->order_number); ?>" data-status="Received">Received ✓</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

        </div>
        <script>
        (function(){
            var ajaxUrl = '<?php echo esc_js($ajax); ?>';
            var nonce   = '<?php echo esc_js($nonce); ?>';
            document.querySelectorAll('.hmo-mark-btn').forEach(function(btn){
                btn.addEventListener('click', function(){
                    var id     = btn.getAttribute('data-id');
                    var num    = btn.getAttribute('data-num');
                    var status = btn.getAttribute('data-status');
                    if(!confirm('Mark ' + num + ' as ' + status + '?')) return;
                    btn.disabled = true; btn.textContent = 'Saving…';
                    var fd = new FormData();
                    fd.append('action','hm_update_order_status');
                    fd.append('nonce', nonce);
                    fd.append('order_id', id);
                    fd.append('new_status', status);
                    fetch(ajaxUrl,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(r){
                        if(r.success){ location.reload(); } else { alert(r.data && r.data.msg ? r.data.msg : 'Error'); btn.disabled=false; }
                    }).catch(function(){ alert('Network error'); btn.disabled=false; });
                });
            });
        })();
        </script>
        <?php return ob_get_clean();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CREATE ORDER — Reuses the calendar-style order page (HM_openOrderPage)
    // ═══════════════════════════════════════════════════════════════════════
    public static function render_create() {
        $pid  = intval( $_GET['patient_id'] ?? 0 );
        $base = HearMed_Utils::page_url( 'orders' );

        // Resolve patient name if patient_id was supplied
        $patient_name = '';
        if ( $pid ) {
            $db = HearMed_DB::instance();
            $p  = $db->get_row(
                "SELECT first_name, last_name, patient_number
                 FROM hearmed_core.patients WHERE id = \$1",
                [ $pid ]
            );
            if ( $p ) {
                $patient_name = trim( $p->first_name . ' ' . $p->last_name );
                if ( $p->patient_number ) {
                    $patient_name .= ' (' . $p->patient_number . ')';
                }
            }
        }

        ob_start();
        ?>
        <?php if ( ! $pid ) : ?>
        <!-- Patient search step — shown when no patient_id in URL -->
        <div class="hm-content hm-orders-create" id="hm-orders-patient-search"
             style="max-width:520px;margin:40px auto;font-family:var(--hm-font,'Source Sans 3',sans-serif)">
            <a href="<?php echo esc_url( $base ); ?>" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:var(--hm-teal,#0BB4C4);text-decoration:none;margin-bottom:20px">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 8H1M8 15L1 8l7-7"/></svg> Orders
            </a>
            <div style="background:#fff;border-radius:12px;border:1.5px solid var(--hm-border,#e2e8f0);padding:32px;text-align:center">
                <div style="font-family:var(--hm-font-title,'Cormorant Garamond',serif);font-size:22px;font-weight:700;color:var(--hm-navy,#151B33);margin-bottom:4px">New Order</div>
                <div style="font-size:13px;color:var(--hm-text-light,#64748b);margin-bottom:24px">Search for a patient to begin</div>
                <div style="position:relative">
                    <input type="text" id="hm-op-patient-q" autofocus
                           placeholder="Type patient name…"
                           style="width:100%;font-size:14px;padding:12px 16px;border-radius:8px;border:1.5px solid var(--hm-border,#e2e8f0);box-sizing:border-box;font-family:var(--hm-font);transition:border-color .15s">
                    <div id="hm-op-patient-results" style="display:none;position:absolute;left:0;right:0;top:100%;margin-top:4px;background:#fff;border:1.5px solid var(--hm-border,#e2e8f0);border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.1);max-height:260px;overflow-y:auto;z-index:10"></div>
                </div>
            </div>
        </div>
        <script>
        (function(){
            var timer,q=document.getElementById('hm-op-patient-q'),box=document.getElementById('hm-op-patient-results');
            q.addEventListener('input',function(){
                clearTimeout(timer);
                if(q.value.length<2){box.style.display='none';return;}
                timer=setTimeout(function(){
                    var fd=new FormData();fd.append('action','hm_patient_search');fd.append('nonce',HM.nonce);fd.append('q',q.value);
                    fetch(HM.ajax_url,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
                        if(!d.success||!d.data.length){box.style.display='none';return;}
                        box.innerHTML='';
                        d.data.forEach(function(p){
                            var d2=document.createElement('div');
                            d2.textContent=p.label;
                            d2.style.cssText='padding:10px 14px;cursor:pointer;font-size:13px;border-bottom:1px solid #f1f5f9;transition:background .1s';
                            d2.addEventListener('mouseenter',function(){d2.style.background='#f0fdfa';});
                            d2.addEventListener('mouseleave',function(){d2.style.background='';});
                            d2.addEventListener('click',function(){
                                window.location='<?php echo esc_url( $base ); ?>?hm_action=create&patient_id='+encodeURIComponent(p.id);
                            });
                            box.appendChild(d2);
                        });
                        box.style.display='block';
                    });
                },300);
            });
            document.addEventListener('click',function(e){if(!q.contains(e.target)&&!box.contains(e.target))box.style.display='none';});
        })();
        </script>
        <?php else : ?>
        <!-- ═══════════════════════════════════════════════════════════════
             STANDALONE ORDER CREATION FORM
             Posts to hm_create_order (ajax_create_order).
             Same form for Orders page AND calendar outcome redirect.
             ═══════════════════════════════════════════════════════════════ -->
        <div class="hm-content hm-orders-create" id="hm-oc"
             style="font-family:var(--hm-font,'Source Sans 3',sans-serif);color:var(--hm-text,#334155);-webkit-font-smoothing:antialiased">

            <!-- Top bar -->
            <div style="background:var(--hm-teal,#0BB4C4);color:#fff;display:flex;align-items:center;justify-content:center;padding:0 24px;height:50px;border-radius:10px 10px 0 0">
                <div style="text-align:center">
                    <div style="font-family:var(--hm-font-title,'Cormorant Garamond',serif);font-size:20px;font-weight:700;letter-spacing:-.3px">New Order</div>
                    <div style="font-size:11px;opacity:.7;margin-top:1px"><?php echo esc_html( $patient_name ); ?></div>
                </div>
            </div>

            <!-- Two-panel split -->
            <div style="display:flex;min-height:600px;border:1px solid var(--hm-border,#e2e8f0);border-top:none;border-radius:0 0 10px 10px;overflow:hidden">

                <!-- ═════ LEFT PANEL — Product picker ═════ -->
                <div id="hm-oc-left" style="flex:0 0 40%;max-width:40%;overflow-y:auto;padding:24px 28px;background:#fff;border-right:1px solid var(--hm-border,#e2e8f0)">
                    <input type="hidden" id="hm-oc-pid" value="<?php echo (int) $pid; ?>">

                    <div style="font-size:12px;font-weight:700;color:var(--hm-text-light,#64748b);text-transform:uppercase;letter-spacing:.5px;margin-bottom:14px;display:flex;align-items:center;gap:8px">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg> Add Products
                    </div>

                    <div id="hm-oc-loading" style="text-align:center;padding:40px 0;color:var(--hm-text-muted,#94a3b8);font-size:13px">Loading products…</div>

                    <!-- Dropdowns populated by JS -->
                    <div id="hm-oc-selectors" style="display:none">
                        <div style="margin-bottom:14px">
                            <label class="hm-oc-lbl">Product Type</label>
                            <select id="hm-oc-cat" class="hm-oc-inp"><option value="">— Select Category —</option></select>
                        </div>

                        <!-- ═══ HEARING AID FLOW ═══ -->
                        <div id="hm-oc-ha-flow" style="display:none">
                            <!-- Step: Search method -->
                            <div style="margin-bottom:14px">
                                <label class="hm-oc-lbl">Search By</label>
                                <div class="hm-toggle-group">
                                    <label class="hm-toggle-card" id="hm-oc-search-range-label">
                                        <input type="radio" name="hm-oc-search-method" value="range"> HearMed Range
                                    </label>
                                    <label class="hm-toggle-card" id="hm-oc-search-mfr-label">
                                        <input type="radio" name="hm-oc-search-method" value="manufacturer"> Manufacturer
                                    </label>
                                </div>
                            </div>

                            <!-- Path A: HearMed Range -->
                            <div id="hm-oc-path-range" style="display:none">
                                <div style="margin-bottom:14px">
                                    <label class="hm-oc-lbl">HearMed Range</label>
                                    <select id="hm-oc-hmrange" class="hm-oc-inp"><option value="">— Select Range —</option></select>
                                </div>
                                <div id="hm-oc-pr-mfr-wrap" style="display:none;margin-bottom:14px">
                                    <label class="hm-oc-lbl">Manufacturer</label>
                                    <select id="hm-oc-pr-mfr" class="hm-oc-inp"><option value="">— All Manufacturers —</option></select>
                                </div>
                                <div id="hm-oc-pr-style-wrap" style="display:none;margin-bottom:14px">
                                    <label class="hm-oc-lbl">Style</label>
                                    <select id="hm-oc-pr-style" class="hm-oc-inp"><option value="">— All Styles —</option></select>
                                </div>
                                <div id="hm-oc-pr-tech-wrap" style="display:none;margin-bottom:14px">
                                    <label class="hm-oc-lbl">Tech Level</label>
                                    <select id="hm-oc-pr-tech" class="hm-oc-inp"><option value="">— All Tech Levels —</option></select>
                                </div>
                                <div id="hm-oc-pr-prod-wrap" style="display:none;margin-bottom:14px">
                                    <label class="hm-oc-lbl">Model</label>
                                    <select id="hm-oc-pr-prod" class="hm-oc-inp"><option value="">— Select Model —</option></select>
                                </div>
                            </div>

                            <!-- Path B: Manufacturer -->
                            <div id="hm-oc-path-mfr" style="display:none">
                                <div style="margin-bottom:14px">
                                    <label class="hm-oc-lbl">Manufacturer</label>
                                    <select id="hm-oc-pm-mfr" class="hm-oc-inp"><option value="">— Select Manufacturer —</option></select>
                                </div>
                                <div id="hm-oc-pm-row" style="display:none;margin-bottom:14px">
                                    <div style="display:flex;gap:10px">
                                        <div style="flex:1">
                                            <label class="hm-oc-lbl">Style</label>
                                            <select id="hm-oc-pm-style" class="hm-oc-inp"><option value="">— All Styles —</option></select>
                                        </div>
                                        <div style="flex:1">
                                            <label class="hm-oc-lbl">Model</label>
                                            <select id="hm-oc-pm-model" class="hm-oc-inp"><option value="">— All Models —</option></select>
                                        </div>
                                    </div>
                                </div>
                                <div id="hm-oc-pm-tech-wrap" style="display:none;margin-bottom:14px">
                                    <label class="hm-oc-lbl">Tech Level</label>
                                    <select id="hm-oc-pm-tech" class="hm-oc-inp"><option value="">— All Tech Levels —</option></select>
                                </div>
                                <div id="hm-oc-pm-prod-wrap" style="display:none;margin-bottom:14px">
                                    <label class="hm-oc-lbl">Product</label>
                                    <select id="hm-oc-pm-prod" class="hm-oc-inp"><option value="">— Select Product —</option></select>
                                </div>
                            </div>

                            <!-- Ear selection (shared) -->
                            <div id="hm-oc-ha-ear-wrap" style="display:none;margin-bottom:14px">
                                <label class="hm-oc-lbl">Ear</label>
                                <select id="hm-oc-ha-ear" class="hm-oc-inp">
                                    <option value="">— Select —</option>
                                    <option value="Left">Left</option>
                                    <option value="Right">Right</option>
                                    <option value="Binaural">Binaural (both)</option>
                                </select>
                            </div>

                            <!-- Bundled Accessories (includes charger) -->
                            <div id="hm-oc-bundled-wrap" style="display:none;margin-bottom:14px;padding:12px 14px;background:var(--hm-bg-alt,#f8fafc);border-radius:8px;border:1px solid var(--hm-border,#e2e8f0)">
                                <label class="hm-oc-lbl" style="margin-bottom:10px">Bundled Accessories <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--hm-text-muted,#94a3b8)">— free on first sale</span></label>
                                <div id="hm-oc-charger-row" style="display:none;margin-bottom:10px">
                                    <label style="font-size:11px;font-weight:600;color:var(--hm-text,#334155);display:block;margin-bottom:4px">Charger</label>
                                    <div class="hm-toggle-group" style="margin-bottom:6px">
                                        <label class="hm-toggle-card hm-toggle-sm" id="hm-oc-charger-yes-label">
                                            <input type="radio" name="hm-oc-charger" value="yes"> Yes
                                        </label>
                                        <label class="hm-toggle-card hm-toggle-sm" id="hm-oc-charger-no-label">
                                            <input type="radio" name="hm-oc-charger" value="no"> No
                                        </label>
                                    </div>
                                    <div id="hm-oc-charger-select-wrap" style="display:none">
                                        <select id="hm-oc-charger-sel" class="hm-oc-inp"><option value="">— Select Charger —</option></select>
                                        <div style="font-size:11px;color:#059669;margin-top:4px;font-weight:600">Bundled free with hearing aids — €0.00</div>
                                    </div>
                                </div>
                                <div class="hm-bundled-row" id="hm-oc-dome-row" style="display:none">
                                    <div class="hm-bundled-sel">
                                        <label style="font-size:11px;font-weight:600;color:var(--hm-text,#334155);display:block;margin-bottom:4px">Domes</label>
                                        <select id="hm-oc-dome" class="hm-oc-inp"><option value="">— None —</option></select>
                                    </div>
                                    <div class="hm-bundled-qty">
                                        <label style="font-size:11px;font-weight:600;color:var(--hm-text,#334155);display:block;margin-bottom:4px">Qty</label>
                                        <input type="number" id="hm-oc-dome-qty" value="1" min="1" max="2" step="1">
                                    </div>
                                </div>
                                <div class="hm-bundled-row" id="hm-oc-speaker-row" style="display:none">
                                    <div class="hm-bundled-sel">
                                        <label style="font-size:11px;font-weight:600;color:var(--hm-text,#334155);display:block;margin-bottom:4px">Speakers</label>
                                        <select id="hm-oc-speaker" class="hm-oc-inp"><option value="">— None —</option></select>
                                    </div>
                                    <div class="hm-bundled-qty">
                                        <label style="font-size:11px;font-weight:600;color:var(--hm-text,#334155);display:block;margin-bottom:4px">Qty</label>
                                        <input type="number" id="hm-oc-speaker-qty" value="1" min="1" max="2" step="1">
                                    </div>
                                </div>
                                <div class="hm-bundled-row" id="hm-oc-filter-row" style="display:none">
                                    <div class="hm-bundled-sel">
                                        <label style="font-size:11px;font-weight:600;color:var(--hm-text,#334155);display:block;margin-bottom:4px">Filters</label>
                                        <select id="hm-oc-filter" class="hm-oc-inp"><option value="">— None —</option></select>
                                    </div>
                                    <div class="hm-bundled-qty">
                                        <label style="font-size:11px;font-weight:600;color:var(--hm-text,#334155);display:block;margin-bottom:4px">Qty</label>
                                        <input type="number" id="hm-oc-filter-qty" value="1" min="1" max="2" step="1">
                                    </div>
                                </div>
                            </div>

                            <!-- Add HA button -->
                            <div id="hm-oc-ha-add-wrap" style="display:none;margin-bottom:14px;text-align:right">
                                <button type="button" id="hm-oc-ha-add" style="font-size:13px;font-weight:600;padding:8px 20px;border-radius:8px;border:none;background:var(--hm-teal,#0BB4C4);color:#fff;cursor:pointer">+ Add to Order</button>
                            </div>
                        </div>

                        <!-- ═══ NON-HA PRODUCT FLOW (original) ═══ -->
                        <div id="hm-oc-filters" style="display:none">
                            <div style="margin-bottom:14px">
                                <label class="hm-oc-lbl">Manufacturer</label>
                                <select id="hm-oc-mfr" class="hm-oc-inp"><option value="">— All Manufacturers —</option></select>
                            </div>
                            <div style="margin-bottom:14px">
                                <label class="hm-oc-lbl">Style</label>
                                <select id="hm-oc-style" class="hm-oc-inp"><option value="">— All Styles —</option></select>
                            </div>
                            <div style="margin-bottom:14px">
                                <label class="hm-oc-lbl">Range / Tech Level</label>
                                <select id="hm-oc-range" class="hm-oc-inp"><option value="">— All Ranges —</option></select>
                            </div>
                            <div style="margin-bottom:14px">
                                <label class="hm-oc-lbl">Product</label>
                                <select id="hm-oc-prod" class="hm-oc-inp"><option value="">— Select Product —</option></select>
                            </div>
                            <div id="hm-oc-ear-wrap" style="display:none;margin-bottom:14px">
                                <label class="hm-oc-lbl">Ear</label>
                                <select id="hm-oc-ear" class="hm-oc-inp">
                                    <option value="">— Select —</option>
                                    <option value="Left">Left</option>
                                    <option value="Right">Right</option>
                                    <option value="Binaural">Binaural (both)</option>
                                </select>
                            </div>
                            <div id="hm-oc-add-wrap" style="display:none;margin-bottom:14px;text-align:right">
                                <button type="button" id="hm-oc-add-item" style="font-size:13px;font-weight:600;padding:8px 20px;border-radius:8px;border:none;background:var(--hm-teal,#0BB4C4);color:#fff;cursor:pointer">+ Add to Order</button>
                            </div>
                        </div>

                        <div id="hm-oc-svc-wrap" style="display:none;margin-bottom:14px">
                            <label class="hm-oc-lbl">Service</label>
                            <select id="hm-oc-svc" class="hm-oc-inp"><option value="">— Select Service —</option></select>
                            <div style="margin-top:10px;text-align:right">
                                <button type="button" id="hm-oc-add-svc" style="font-size:13px;font-weight:600;padding:8px 20px;border-radius:8px;border:none;background:var(--hm-teal,#0BB4C4);color:#fff;cursor:pointer">+ Add to Order</button>
                            </div>
                        </div>
                    </div>

                    <!-- ─── Pick from Stock ─── -->
                    <div style="border-top:1px dashed var(--hm-border-light,#e2e8f0);padding-top:14px;margin-top:4px">
                        <button type="button" id="hm-oc-stock-toggle" style="width:100%;padding:8px 14px;font-size:13px;font-weight:600;border-radius:8px;border:1.5px solid var(--hm-navy,#151B33);background:#fff;color:var(--hm-navy,#151B33);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;font-family:var(--hm-font-btn)">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="12" height="9" rx="1"/><path d="M8 3V2a2 2 0 00-4 0v1"/></svg>
                            Pick from Stock
                        </button>
                        <div id="hm-oc-stock-panel" style="display:none;margin-top:12px">
                            <input type="search" id="hm-oc-stock-search" placeholder="Search model, serial, manufacturer…" class="hm-oc-inp" style="margin-bottom:10px">
                            <div id="hm-oc-stock-loading" style="text-align:center;padding:20px 0;color:var(--hm-text-muted,#94a3b8);font-size:12px">Loading stock…</div>
                            <div id="hm-oc-stock-list" style="max-height:260px;overflow-y:auto;display:none"></div>
                        </div>
                    </div>
                </div>

                <!-- ═════ RIGHT PANEL — Invoice preview ═════ -->
                <div id="hm-oc-right" style="flex:0 0 60%;max-width:60%;display:flex;flex-direction:column;padding:28px 32px;background:#fff">

                    <!-- Invoice header -->
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px">
                        <div>
                            <div style="font-size:22px;font-weight:700;font-family:var(--hm-font-title,'Cormorant Garamond',serif);color:var(--hm-navy,#151B33);letter-spacing:1px">INVOICE</div>
                            <div style="font-size:11px;color:var(--hm-text-muted,#94a3b8);margin-top:2px">Draft &bull; <?php echo date('j M Y'); ?></div>
                        </div>
                        <div style="text-align:right">
                            <div style="font-size:12px;font-weight:600;color:var(--hm-navy,#151B33)"><?php echo esc_html( $patient_name ); ?></div>
                        </div>
                    </div>

                    <!-- Line items table -->
                    <div style="flex:1;overflow-y:auto;margin-bottom:16px">
                        <table style="width:100%;border-collapse:collapse">
                            <thead><tr style="border-bottom:2px solid var(--hm-navy,#151B33)">
                                <th style="text-align:left;padding:6px 0;font-size:10px;font-weight:700;color:var(--hm-text-light,#64748b);text-transform:uppercase;letter-spacing:.8px">Item</th>
                                <th style="text-align:center;padding:6px 8px;font-size:10px;font-weight:700;color:var(--hm-text-light,#64748b);text-transform:uppercase;letter-spacing:.8px;width:40px">Qty</th>
                                <th style="text-align:right;padding:6px 8px;font-size:10px;font-weight:700;color:var(--hm-text-light,#64748b);text-transform:uppercase;letter-spacing:.8px;width:80px">Price</th>
                                <th style="text-align:right;padding:6px 0;font-size:10px;font-weight:700;color:var(--hm-text-light,#64748b);text-transform:uppercase;letter-spacing:.8px;width:80px">Total</th>
                                <th style="width:28px"></th>
                            </tr></thead>
                            <tbody id="hm-oc-items"><tr><td colspan="5" style="text-align:center;padding:24px 0;color:var(--hm-text-muted,#94a3b8);font-size:13px;font-style:italic">No items added yet</td></tr></tbody>
                        </table>
                    </div>

                    <!-- Totals block -->
                    <div style="border-top:1px solid var(--hm-border,#e2e8f0);padding-top:12px">
                        <div class="hm-oc-totrow" style="color:var(--hm-text-light,#64748b)"><span>Subtotal (excl. VAT)</span><span id="hm-oc-sub">€0.00</span></div>
                        <div class="hm-oc-totrow" style="color:var(--hm-text-light,#64748b)"><span>VAT</span><span id="hm-oc-vat">€0.00</span></div>

                        <!-- Discount -->
                        <div class="hm-oc-totrow" style="margin-top:6px;padding-top:8px;border-top:1px dashed var(--hm-border-light,#e2e8f0)">
                            <div style="display:flex;align-items:center;gap:6px">
                                <span style="font-size:13px">Discount</span>
                                <div style="display:inline-flex;border:1px solid var(--hm-border,#e2e8f0);border-radius:4px;overflow:hidden">
                                    <button type="button" class="hm-oc-disc-mode" data-mode="pct" style="padding:2px 8px;font-size:10px;font-weight:700;cursor:pointer;border:none;background:var(--hm-navy,#151B33);color:#fff">%</button>
                                    <button type="button" class="hm-oc-disc-mode" data-mode="eur" style="padding:2px 8px;font-size:10px;font-weight:700;cursor:pointer;border:none;background:#fff;color:var(--hm-text,#334155)">€</button>
                                </div>
                                <input type="number" id="hm-oc-disc" value="0" min="0" max="100" step="1" style="width:60px;font-size:12px;padding:3px 6px;border-radius:4px;border:1px solid var(--hm-border,#e2e8f0);text-align:right;font-family:var(--hm-font)">
                                <span id="hm-oc-disc-unit" style="font-size:11px;font-weight:600;color:var(--hm-text-light,#64748b)">%</span>
                            </div>
                            <span id="hm-oc-disc-amt" style="font-size:13px;color:#dc2626">−€0.00</span>
                        </div>

                        <!-- PRSI -->
                        <div style="margin-top:6px;padding-top:8px;border-top:1px dashed var(--hm-border-light,#e2e8f0)">
                            <div class="hm-oc-totrow"><label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px">
                                <input type="checkbox" id="hm-oc-prsi-l" style="accent-color:#0e7490;width:14px;height:14px"> PRSI Grant — Left ear</label>
                                <span style="font-size:13px;color:#059669">−€500.00</span></div>
                            <div class="hm-oc-totrow"><label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px">
                                <input type="checkbox" id="hm-oc-prsi-r" style="accent-color:#0e7490;width:14px;height:14px"> PRSI Grant — Right ear</label>
                                <span style="font-size:13px;color:#059669">−€500.00</span></div>
                        </div>

                        <!-- Grand total -->
                        <div style="display:flex;justify-content:space-between;align-items:baseline;margin-top:10px;padding-top:10px;border-top:2px solid var(--hm-navy,#151B33)">
                            <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--hm-navy,#151B33)">Total Due</span>
                            <span id="hm-oc-total" style="font-size:22px;font-weight:700;color:var(--hm-navy,#151B33)">€0.00</span>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div style="margin-top:14px"><textarea id="hm-oc-notes" rows="2" placeholder="Order notes..." class="hm-oc-inp" style="resize:vertical;font-size:12px;background:var(--hm-bg-alt,#f8fafc)"></textarea></div>

                    <!-- ═══ DEPOSIT / SPLIT PAYMENT section ═══ -->
                    <div style="border-top:1px dashed var(--hm-border-light,#e2e8f0);padding-top:14px;margin-top:14px">
                        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--hm-text-light,#64748b);margin-bottom:10px;display:flex;align-items:center;justify-content:space-between">
                            <span style="display:flex;align-items:center;gap:6px">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                                Deposit / Payments
                                <span style="font-weight:400;color:var(--hm-text-muted,#94a3b8);text-transform:none;letter-spacing:0;font-size:11px">— optional, balance at fitting</span>
                            </span>
                            <button type="button" id="hm-oc-add-payment" style="font-size:11px;font-weight:700;padding:4px 10px;border-radius:6px;border:1px solid var(--hm-teal,#0BB4C4);background:#fff;color:var(--hm-teal,#0BB4C4);cursor:pointer">+ Add Payment</button>
                        </div>
                        <div id="hm-oc-payments-list"></div>
                        <div id="hm-oc-dep-balance" style="display:none;margin-top:10px;padding:10px 14px;background:#f0fdfe;border:1px solid #a5f3fc;border-radius:8px;font-size:13px;color:#0e7490">
                            Balance due at fitting: <strong id="hm-oc-dep-bal-val">€0.00</strong>
                        </div>
                    </div>

                    <!-- Error -->
                    <div id="hm-oc-err" style="color:#ef4444;font-size:12px;margin-top:6px"></div>

                    <!-- Submit -->
                    <div id="hm-oc-actions" style="margin-top:14px">
                        <button type="button" id="hm-oc-submit" style="width:100%;padding:14px;font-size:14px;font-weight:700;border-radius:8px;border:none;background:var(--hm-navy,#151B33);color:#fff;cursor:pointer;font-family:var(--hm-font-btn);transition:all .15s">Submit Order for Approval</button>
                    </div>

                    <!-- Success banner (hidden) -->
                    <div id="hm-oc-success" style="display:none"></div>
                </div>
            </div>
        </div>

        <style>
        .hm-oc-lbl{font-size:11px;font-weight:700;color:var(--hm-text,#334155);text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:5px}
        .hm-oc-inp{font-size:13px;padding:9px 12px;border-radius:8px;border:1.5px solid var(--hm-border,#e2e8f0);width:100%;background:#fff;box-sizing:border-box;font-family:var(--hm-font);transition:border-color .15s}
        .hm-oc-inp:focus{border-color:var(--hm-teal,#0BB4C4);outline:none}
        .hm-oc-totrow{display:flex;justify-content:space-between;align-items:center;font-size:13px;padding:3px 0}
        /* Card-style radio/toggle buttons — hides native dot */
        .hm-toggle-group{display:flex;gap:8px}
        .hm-toggle-card{flex:1;position:relative;display:flex;align-items:center;justify-content:center;gap:6px;padding:10px 14px;border:2px solid var(--hm-border,#e2e8f0);border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;background:#fff;transition:all .15s;text-align:center;color:var(--hm-text,#334155);user-select:none}
        .hm-toggle-card input[type="radio"]{position:absolute;opacity:0;width:0;height:0;pointer-events:none}
        .hm-toggle-card.active{border-color:var(--hm-teal,#0BB4C4);background:rgba(11,180,196,0.06);color:var(--hm-teal,#0BB4C4)}
        .hm-toggle-card:hover:not(.active){border-color:#cbd5e1;background:#f8fafc}
        .hm-toggle-sm{padding:8px 12px;font-size:12px;border-width:1.5px;border-radius:6px}
        /* Bundled accessory row with qty */
        .hm-bundled-row{display:flex;gap:8px;align-items:flex-end;margin-bottom:10px}
        .hm-bundled-row .hm-bundled-sel{flex:1;min-width:0}
        .hm-bundled-row .hm-bundled-qty{width:58px;flex:0 0 58px}
        .hm-bundled-row .hm-bundled-qty input{width:100%;font-size:12px;padding:9px 6px;border-radius:8px;border:1.5px solid var(--hm-border,#e2e8f0);text-align:center;font-family:var(--hm-font);box-sizing:border-box}
        .hm-bundled-row .hm-bundled-qty input:focus{border-color:var(--hm-teal,#0BB4C4);outline:none}
        </style>

        <script>
        (function(){
            var $=jQuery;
            var ajaxUrl='<?php echo esc_js(admin_url("admin-ajax.php")); ?>';
            var nonce='<?php echo esc_js(wp_create_nonce("hm_nonce")); ?>';
            var pid=<?php echo (int)$pid; ?>;
            var ordersBase=<?php echo json_encode($base); ?>;

            var orderItems=[];
            var allProducts=[], allSvcs=[], allRanges=[];
            var discountMode='pct';
            var paymentRows=[];
            var stockItems=[], stockLoaded=false;

            function esc(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML;}
            function fmt(n){return '€'+(parseFloat(n)||0).toFixed(2);}

            /* helpers */
            function haProducts(){ return allProducts.filter(function(p){return p.item_type==='product';}); }
            function chargerProducts(){ return allProducts.filter(function(p){return p.item_type==='charger';}); }
            function bundledProducts(){ return allProducts.filter(function(p){return p.item_type==='bundled';}); }
            function unique(arr,key){var m={};arr.forEach(function(p){if(p[key])m[p[key]]=p[key];});return m;}
            function uniqueBy(arr,idKey,nameKey){var m={};arr.forEach(function(p){if(p[idKey])m[p[idKey]]=p[nameKey]||p[idKey];});return m;}
            function populateSel(sel,map,cur,allLabel){
                var $el=$(sel).empty().append('<option value="">'+allLabel+'</option>');
                Object.keys(map).sort(function(a,b){return String(map[a]).localeCompare(String(map[b]));}).forEach(function(k){
                    $el.append('<option value="'+k+'"'+(String(k)===String(cur)?' selected':'')+'>'+esc(map[k])+'</option>');
                });
            }

            /* ── Load products ── */
            $.post(ajaxUrl,{action:'hm_get_order_products',nonce:nonce},function(r){
                if(!r||!r.success){$('#hm-oc-loading').text('Failed to load products.');return;}
                allProducts=r.data.products||[];
                allSvcs=r.data.services||[];
                allRanges=r.data.ranges||[];
                buildCategoryDropdown();
                $('#hm-oc-loading').hide();
                $('#hm-oc-selectors').show();
            });

            function buildCategoryDropdown(){
                var $c=$('#hm-oc-cat').empty().append('<option value="">— Select Category —</option>');
                /* Hearing Aid first */
                if(haProducts().length) $c.append('<option value="product">Hearing Aids</option>');
                if(allSvcs.length)      $c.append('<option value="service">Service</option>');
                var otherTypes={};
                allProducts.forEach(function(p){
                    if(p.item_type==='product'||p.item_type==='charger'||p.item_type==='bundled') return;
                    var labels={accessory:'Accessory',consumable:'Consumable'};
                    otherTypes[p.item_type]=labels[p.item_type]||p.item_type;
                });
                Object.keys(otherTypes).forEach(function(k){$c.append('<option value="'+k+'">'+esc(otherTypes[k])+'</option>');});
            }

            /* ══════════════════════════════════════════
               CATEGORY CHANGE — route to correct flow
               ══════════════════════════════════════════ */
            $('#hm-oc-cat').on('change',function(){
                var cat=$(this).val();
                $('#hm-oc-ha-flow,#hm-oc-filters,#hm-oc-svc-wrap').hide();
                $('#hm-oc-ear-wrap,#hm-oc-add-wrap').hide();
                resetHAFlow();
                if(!cat) return;
                if(cat==='product'){
                    /* Hearing aid path */
                    $('input[name="hm-oc-search-method"]').prop('checked',false);
                    $('#hm-oc-path-range,#hm-oc-path-mfr').hide();
                    $('#hm-oc-search-range-label,#hm-oc-search-mfr-label').removeClass('active');
                    $('#hm-oc-ha-flow').show();
                } else if(cat==='service'){
                    var $sv=$('#hm-oc-svc').empty().append('<option value="">— Select Service —</option>');
                    allSvcs.forEach(function(s){
                        $sv.append('<option value="'+s.id+'" data-price="'+parseFloat(s.default_price||0)+'">'+esc(s.service_name)+' — €'+parseFloat(s.default_price||0).toFixed(2)+'</option>');
                    });
                    $('#hm-oc-svc-wrap').show();
                } else {
                    /* Other product types — use existing faceted flow */
                    $('#hm-oc-filters').show();
                    $('#hm-oc-mfr,#hm-oc-style,#hm-oc-range,#hm-oc-prod,#hm-oc-ear').val('');
                    refreshAllFilters(cat);
                }
            });

            /* ══════════════════════════════════════════
               HEARING AID FLOW (HA)
               ══════════════════════════════════════════ */
            var _haSelectedProd=null;
            var _haSelectedMfr=null;

            function resetHAFlow(){
                _haSelectedProd=null;_haSelectedMfr=null;
                $('#hm-oc-path-range,#hm-oc-path-mfr').hide();
                $('#hm-oc-ha-ear-wrap,#hm-oc-bundled-wrap,#hm-oc-ha-add-wrap').hide();
                $('input[name="hm-oc-charger"]').prop('checked',false);
                $('#hm-oc-charger-select-wrap,#hm-oc-charger-row,#hm-oc-dome-row,#hm-oc-speaker-row,#hm-oc-filter-row').hide();
                $('#hm-oc-charger-yes-label,#hm-oc-charger-no-label').removeClass('active');
            }

            /* Search method toggle */
            $('input[name="hm-oc-search-method"]').on('change',function(){
                var method=$(this).val();
                resetHAFlow();
                $('#hm-oc-search-range-label,#hm-oc-search-mfr-label').removeClass('active');
                $(this).closest('label').addClass('active');
                if(method==='range'){
                    /* populate ranges */
                    var rMap={};
                    allRanges.forEach(function(r){rMap[r.id]=r.range_name;});
                    populateSel('#hm-oc-hmrange',rMap,'','— Select Range —');
                    $('#hm-oc-path-range').show();
                    $('#hm-oc-path-mfr').hide();
                } else {
                    /* populate all HA manufacturers */
                    var mMap=uniqueBy(haProducts(),'manufacturer_id','manufacturer_name');
                    populateSel('#hm-oc-pm-mfr',mMap,'','— Select Manufacturer —');
                    $('#hm-oc-path-mfr').show();
                    $('#hm-oc-path-range').hide();
                }
            });

            /* ── PATH A: HearMed Range ── */
            $('#hm-oc-hmrange').on('change',function(){
                var rid=$(this).val();
                $('#hm-oc-pr-mfr-wrap,#hm-oc-pr-style-wrap,#hm-oc-pr-tech-wrap,#hm-oc-pr-prod-wrap').hide();
                hideHAExtras();
                if(!rid) return;
                var allHA=haProducts();
                var inRange=allHA.filter(function(p){return p.hearmed_range_id && String(p.hearmed_range_id)===String(rid);});
                console.log('[HM Range Debug] Selected range_id:', rid, '| HA products total:', allHA.length, '| Matched:', inRange.length);
                if(!inRange.length){console.warn('[HM Range Debug] No products matched. Sample hearmed_range_id values:', allHA.slice(0,5).map(function(p){return p.hearmed_range_id;}));}
                var mMap=uniqueBy(inRange,'manufacturer_id','manufacturer_name');
                populateSel('#hm-oc-pr-mfr',mMap,'','— All Manufacturers —');
                $('#hm-oc-pr-mfr-wrap').show();
                refreshPathA();
            });

            $('#hm-oc-pr-mfr').on('change',function(){ refreshPathA(); });
            $('#hm-oc-pr-style').on('change',function(){ refreshPathA(); });
            $('#hm-oc-pr-tech').on('change',function(){ refreshPathA(); });

            function hearingAidLabel(p){
                if(!p) return '';
                var name=String(p.product_name||'').trim();
                var tech=String(p.tech_level||'').trim();
                if(!name) return tech;
                if(!tech) return name;
                return name.toLowerCase().indexOf(tech.toLowerCase())!==-1?name:(name+' '+tech);
            }

            function refreshPathA(){
                var rid=$('#hm-oc-hmrange').val();
                var mfr=$('#hm-oc-pr-mfr').val();
                var style=$('#hm-oc-pr-style').val();
                var tech=$('#hm-oc-pr-tech').val();
                if(!rid) return;
                var pool=haProducts().filter(function(p){return p.hearmed_range_id && String(p.hearmed_range_id)===String(rid);});
                var poolForStyle=mfr?pool.filter(function(p){return String(p.manufacturer_id)===String(mfr);}):pool;
                var poolForTech=style?poolForStyle.filter(function(p){return p.style===style;}):poolForStyle;
                var poolFinal=tech?poolForTech.filter(function(p){return p.tech_level===tech;}):poolForTech;

                /* styles */
                var sMap=unique(poolForStyle,'style');
                populateSel('#hm-oc-pr-style',sMap,style,'— All Styles —');
                if(Object.keys(sMap).length) $('#hm-oc-pr-style-wrap').show();

                /* tech levels */
                var tMap=unique(poolForTech,'tech_level');
                populateSel('#hm-oc-pr-tech',tMap,tech,'— All Tech Levels —');
                if(Object.keys(tMap).length) $('#hm-oc-pr-tech-wrap').show();

                /* products */
                var $p=$('#hm-oc-pr-prod').empty().append('<option value="">— Select Model —</option>');
                poolFinal.forEach(function(p){
                    var price=parseFloat(p.retail_price||0);
                    var label=hearingAidLabel(p);
                    $p.append('<option value="'+p.id+'" data-price="'+price+'" data-vat="'+(p.vat_category==='standard'?23:0)+'" data-name="'+esc(label)+'" data-mfr="'+p.manufacturer_id+'">'+esc(label)+' — €'+price.toFixed(2)+'</option>');
                });
                if(poolFinal.length) $('#hm-oc-pr-prod-wrap').show();
                handleHAProdChange('#hm-oc-pr-prod');
            }

            $('#hm-oc-pr-prod').on('change',function(){ handleHAProdChange('#hm-oc-pr-prod'); });

            /* ── PATH B: Manufacturer ── */
            $('#hm-oc-pm-mfr').on('change',function(){
                var mfr=$(this).val();
                $('#hm-oc-pm-row,#hm-oc-pm-tech-wrap,#hm-oc-pm-prod-wrap').hide();
                hideHAExtras();
                if(!mfr) return;
                $('#hm-oc-pm-row').show();
                refreshPathB();
            });

            $('#hm-oc-pm-style').on('change',function(){ refreshPathB(); });
            $('#hm-oc-pm-model').on('change',function(){ refreshPathB(); });
            $('#hm-oc-pm-tech').on('change',function(){ refreshPathB(); });

            function refreshPathB(){
                var mfr=$('#hm-oc-pm-mfr').val();
                var style=$('#hm-oc-pm-style').val();
                var model=$('#hm-oc-pm-model').val();
                var tech=$('#hm-oc-pm-tech').val();
                if(!mfr) return;
                var pool=haProducts().filter(function(p){return String(p.manufacturer_id)===String(mfr);});

                /* styles */
                var sPool=model?pool.filter(function(p){return String(p.id)===String(model);}):pool;
                var sMap=unique(pool,'style');
                populateSel('#hm-oc-pm-style',sMap,style,'— All Styles —');

                /* models (filtered by style) */
                var mPool=style?pool.filter(function(p){return p.style===style;}):pool;
                var mMap={};mPool.forEach(function(p){mMap[p.id]=p.product_name;});
                populateSel('#hm-oc-pm-model',mMap,model,'— All Models —');

                /* tech level — only show once style or model narrows it */
                var tPool=pool;
                if(style) tPool=tPool.filter(function(p){return p.style===style;});
                if(model) tPool=tPool.filter(function(p){return String(p.id)===String(model);});
                var tMap=unique(tPool,'tech_level');
                populateSel('#hm-oc-pm-tech',tMap,tech,'— All Tech Levels —');
                if(Object.keys(tMap).length && (style||model)) $('#hm-oc-pm-tech-wrap').show();
                else $('#hm-oc-pm-tech-wrap').hide();

                /* final product select — if model already chosen from dropdown */
                if(model){
                    handleHAProdChangeVal(model);
                } else {
                    /* build product selector from pool */
                    var fPool=tPool;
                    if(tech) fPool=fPool.filter(function(p){return p.tech_level===tech;});
                    if(fPool.length<=10 && (style||tech)){
                        var $pp=$('#hm-oc-pm-prod').empty().append('<option value="">— Select Product —</option>');
                        fPool.forEach(function(p){
                            var pr=parseFloat(p.retail_price||0);
                            var label=hearingAidLabel(p);
                            $pp.append('<option value="'+p.id+'" data-price="'+pr+'" data-vat="'+(p.vat_category==='standard'?23:0)+'" data-name="'+esc(label)+'" data-mfr="'+p.manufacturer_id+'">'+esc(label)+' — €'+pr.toFixed(2)+'</option>');
                        });
                        $('#hm-oc-pm-prod-wrap').show();
                    } else {
                        $('#hm-oc-pm-prod-wrap').hide();
                    }
                    hideHAExtras();
                }
            }

            $('#hm-oc-pm-prod').on('change',function(){
                var v=$(this).val();
                if(v) handleHAProdChangeVal(v);
                else hideHAExtras();
            });

            /* ── Shared: when a hearing aid product is selected ── */
            function handleHAProdChange(sel){
                var v=$(sel).val();
                if(v) handleHAProdChangeVal(v);
                else hideHAExtras();
            }
            function handleHAProdChangeVal(prodId){
                var p=allProducts.find(function(x){return String(x.id)===String(prodId);});
                if(!p){hideHAExtras();return;}
                _haSelectedProd=p;
                _haSelectedMfr=p.manufacturer_id;
                /* show ear */
                $('#hm-oc-ha-ear-wrap').show();
                /* populate charger inside bundled */
                var chargers=chargerProducts().filter(function(c){return String(c.manufacturer_id)===String(p.manufacturer_id);});
                if(chargers.length){
                    var $cs=$('#hm-oc-charger-sel').empty().append('<option value="">— Select Charger —</option>');
                    chargers.forEach(function(c){$cs.append('<option value="'+c.id+'" data-name="'+esc(c.product_name)+'">'+esc(c.product_name)+'</option>');});
                    $('input[name="hm-oc-charger"]').prop('checked',false);
                    $('#hm-oc-charger-yes-label,#hm-oc-charger-no-label').removeClass('active');
                    $('#hm-oc-charger-select-wrap').hide();
                    $('#hm-oc-charger-row').show();
                } else { $('#hm-oc-charger-row').hide(); }
                /* populate bundled (domes, speakers, filters) */
                var bun=bundledProducts().filter(function(b){return String(b.manufacturer_id)===String(p.manufacturer_id);});
                var domes=bun.filter(function(b){return(b.bundled_category||'').toLowerCase()==='dome';});
                var speakers=bun.filter(function(b){return(b.bundled_category||'').toLowerCase()==='speaker';});
                var filters=bun.filter(function(b){var c=(b.bundled_category||'').toLowerCase();return c==='filter'||c==='other';});
                fillBundledSel('#hm-oc-dome',domes);
                fillBundledSel('#hm-oc-speaker',speakers);
                fillBundledSel('#hm-oc-filter',filters);
                $('#hm-oc-dome-row').toggle(domes.length>0);
                $('#hm-oc-speaker-row').toggle(speakers.length>0);
                $('#hm-oc-filter-row').toggle(filters.length>0);
                /* Reset qty defaults */
                $('#hm-oc-dome-qty,#hm-oc-speaker-qty,#hm-oc-filter-qty').val(1);
                /* Show bundled section if chargers or accessories exist */
                if(chargers.length||domes.length||speakers.length||filters.length) $('#hm-oc-bundled-wrap').show();
                else $('#hm-oc-bundled-wrap').hide();
                /* show add button */
                $('#hm-oc-ha-add-wrap').show();
            }

            function fillBundledSel(sel,items){
                var $el=$(sel).empty().append('<option value="">— None —</option>');
                items.forEach(function(b){
                    var label=b.product_name;
                    if(b.dome_type) label+=' ('+b.dome_type+(b.dome_size?' '+b.dome_size:'')+')';
                    if(b.speaker_power) label+=' ('+b.speaker_power+(b.speaker_length?' / '+b.speaker_length:'')+')';
                    $el.append('<option value="'+b.id+'" data-name="'+esc(b.product_name)+'">'+esc(label)+'</option>');
                });
            }

            function hideHAExtras(){
                _haSelectedProd=null;
                $('#hm-oc-ha-ear-wrap,#hm-oc-bundled-wrap,#hm-oc-ha-add-wrap').hide();
            }

            /* Charger radio */
            $(document).on('change','input[name="hm-oc-charger"]',function(){
                var v=$(this).val();
                $('#hm-oc-charger-yes-label,#hm-oc-charger-no-label').removeClass('active');
                $(this).closest('label').addClass('active');
                if(v==='yes') $('#hm-oc-charger-select-wrap').show();
                else $('#hm-oc-charger-select-wrap').hide();
            });

            /* ── ADD HEARING AID + BUNDLED ── */
            $('#hm-oc-ha-add').on('click',function(){
                if(!_haSelectedProd){$('#hm-oc-err').text('Please select a hearing aid.');return;}
                var ear=$('#hm-oc-ha-ear').val();
                if(!ear){$('#hm-oc-err').text('Please select an ear.');return;}
                var p=_haSelectedProd;
                var price=parseFloat(p.retail_price||0);
                var vatRate=p.vat_category==='standard'?23:0;
                var qty=ear==='Binaural'?2:1;
                var gross=price*qty;
                var vatAmt=vatRate>0?Math.round((gross-gross/(1+vatRate/100))*100)/100:0;
                /* Hearing aid */
                orderItems.push({id:parseInt(p.id),type:'product',name:hearingAidLabel(p),unit_price:price,qty:qty,ear:ear,vat_rate:vatRate,vat_amount:vatAmt,line_total:gross});
                /* Charger (free) */
                if($('input[name="hm-oc-charger"]:checked').val()==='yes'){
                    var cid=$('#hm-oc-charger-sel').val();
                    if(cid){
                        var cname=$('#hm-oc-charger-sel option:selected').data('name')||'Charger';
                        orderItems.push({id:parseInt(cid),type:'charger',name:cname+' (bundled)',unit_price:0,qty:1,ear:'',vat_rate:0,vat_amount:0,line_total:0,bundled:true});
                    }
                }
                /* Bundled accessories (free) — use qty spinners */
                var bundledSels=[
                    {sel:'#hm-oc-dome',  qty:'#hm-oc-dome-qty'},
                    {sel:'#hm-oc-speaker',qty:'#hm-oc-speaker-qty'},
                    {sel:'#hm-oc-filter', qty:'#hm-oc-filter-qty'}
                ];
                bundledSels.forEach(function(b){
                    var bid=$(b.sel).val();
                    if(bid){
                        var bname=$(b.sel+' option:selected').data('name')||'Accessory';
                        var bqty=Math.min(Math.max(parseInt($(b.qty).val())||1,1),2);
                        orderItems.push({id:parseInt(bid),type:'bundled',name:bname+' (bundled)',unit_price:0,qty:bqty,ear:'',vat_rate:0,vat_amount:0,line_total:0,bundled:true});
                    }
                });
                renderItems();updateTotals();$('#hm-oc-err').text('');
                /* Reset HA flow to add another */
                resetHAFlow();
                $('input[name="hm-oc-search-method"]').prop('checked',false);
                $('#hm-oc-search-range-label,#hm-oc-search-mfr-label').removeClass('active');
                $('#hm-oc-path-range,#hm-oc-path-mfr').hide();
            });

            /* ══════════════════════════════════════════
               NON-HA FACETED FILTERS (accessories etc)
               ══════════════════════════════════════════ */
            function getFilters(){
                return {
                    cat:   $('#hm-oc-cat').val()||'',
                    mfr:   $('#hm-oc-mfr').val()||'',
                    style: $('#hm-oc-style').val()||'',
                    range: $('#hm-oc-range').val()||''
                };
            }

            function applyFilters(prods,f,exclude){
                return prods.filter(function(p){
                    if(exclude!=='cat'   && f.cat   && p.item_type!==f.cat)                       return false;
                    if(exclude!=='mfr'   && f.mfr   && String(p.manufacturer_id)!==String(f.mfr)) return false;
                    if(exclude!=='style' && f.style  && p.style!==f.style)                         return false;
                    if(exclude!=='range' && f.range  && p.tech_level!==f.range)                    return false;
                    return true;
                });
            }

            function refreshAllFilters(){
                var f=getFilters();
                if(!f.cat||f.cat==='service') return;

                var mfrs={};
                applyFilters(allProducts,f,'mfr').forEach(function(p){if(p.manufacturer_id&&p.manufacturer_name)mfrs[p.manufacturer_id]=p.manufacturer_name;});
                repopulate('#hm-oc-mfr',mfrs,f.mfr,'— All Manufacturers —');

                var styles={};
                applyFilters(allProducts,f,'style').forEach(function(p){if(p.style)styles[p.style]=p.style;});
                repopulate('#hm-oc-style',styles,f.style,'— All Styles —');

                var ranges={};
                applyFilters(allProducts,f,'range').forEach(function(p){if(p.tech_level)ranges[p.tech_level]=p.tech_level;});
                repopulate('#hm-oc-range',ranges,f.range,'— All Ranges —');

                var $p=$('#hm-oc-prod');
                var curProd=$p.val();
                $p.empty().append('<option value="">— Select Product —</option>');
                applyFilters(allProducts,f,'').forEach(function(p){
                    var price=parseFloat(p.retail_price||0);
                    var vat=p.vat_category==='standard'?23:0;
                    var label=hearingAidLabel(p);
                    $p.append('<option value="'+p.id+'"'+(String(p.id)===curProd?' selected':'')+
                        ' data-price="'+price+'" data-vat="'+vat+'" data-name="'+esc(label)+'">'+
                        esc(label)+' — €'+price.toFixed(2)+'</option>');
                });

                if($('#hm-oc-prod').val()){$('#hm-oc-ear-wrap,#hm-oc-add-wrap').show();}
                else{$('#hm-oc-ear-wrap,#hm-oc-add-wrap').hide();$('#hm-oc-ear').val('');}
            }

            function repopulate(sel,map,curVal,allLabel){
                var $el=$(sel);
                $el.empty().append('<option value="">'+allLabel+'</option>');
                Object.keys(map).sort(function(a,b){return map[a].localeCompare(map[b]);}).forEach(function(k){
                    $el.append('<option value="'+k+'"'+(k===curVal?' selected':'')+'>'+esc(map[k])+'</option>');
                });
            }

            /* old cat handler removed — handled above */

            $('#hm-oc-mfr,#hm-oc-style,#hm-oc-range').on('change',function(){refreshAllFilters();});
            $('#hm-oc-prod').on('change',function(){
                if($(this).val()){$('#hm-oc-ear-wrap,#hm-oc-add-wrap').show();}
                else{$('#hm-oc-ear-wrap,#hm-oc-add-wrap').hide();$('#hm-oc-ear').val('');}
            });

            /* ── Add items ── */
            $('#hm-oc-add-item').on('click',function(){
                var $opt=$('#hm-oc-prod option:selected');
                if(!$opt.val()){$('#hm-oc-err').text('Please select a product.');return;}
                var ear=$('#hm-oc-ear').val();
                if(!ear){$('#hm-oc-err').text('Please select an ear.');return;}
                var price=parseFloat($opt.data('price'))||0;
                var vatRate=parseFloat($opt.data('vat'))||0;
                var qty=ear==='Binaural'?2:1;
                var gross=price*qty;
                var vatAmt=vatRate>0?Math.round((gross-gross/(1+vatRate/100))*100)/100:0;
                orderItems.push({id:parseInt($opt.val()),type:'product',name:$opt.data('name'),unit_price:price,qty:qty,ear:ear,vat_rate:vatRate,vat_amount:vatAmt,line_total:gross});
                renderItems();updateTotals();$('#hm-oc-err').text('');
                $('#hm-oc-cat').val('').trigger('change');
            });

            $('#hm-oc-add-svc').on('click',function(){
                var $opt=$('#hm-oc-svc option:selected');
                if(!$opt.val()){$('#hm-oc-err').text('Please select a service.');return;}
                var price=parseFloat($opt.data('price'))||0;
                orderItems.push({id:parseInt($opt.val()),type:'service',name:$opt.text().split(' — ')[0],unit_price:price,qty:1,ear:'',vat_rate:0,vat_amount:0,line_total:price});
                renderItems();updateTotals();$('#hm-oc-err').text('');
                $('#hm-oc-cat').val('').trigger('change');
            });

            /* ── Render items ── */
            function renderItems(){
                var $tb=$('#hm-oc-items');
                if(!orderItems.length){$tb.html('<tr><td colspan="5" style="text-align:center;padding:24px 0;color:#94a3b8;font-size:13px;font-style:italic">No items added yet</td></tr>');return;}
                var h='';
                orderItems.forEach(function(it,i){
                    h+='<tr style="border-bottom:1px solid #f1f5f9">';
                    h+='<td style="padding:10px 0;font-size:13px">'+esc(it.name)+(it.ear?' <span style="font-size:11px;color:#64748b">('+esc(it.ear)+')</span>':'')+'</td>';
                    h+='<td style="text-align:center;font-size:13px">'+it.qty+'</td>';
                    h+='<td style="text-align:right;font-size:13px">'+fmt(it.unit_price)+'</td>';
                    h+='<td style="text-align:right;font-size:13px;font-weight:600">'+fmt(it.unit_price*it.qty)+'</td>';
                    h+='<td><button class="hm-oc-rem" data-idx="'+i+'" style="border:none;background:none;color:#b91c1c;font-size:15px;cursor:pointer;padding:2px 6px">×</button></td>';
                    h+='</tr>';
                });
                $tb.html(h);
            }

            $(document).on('click','.hm-oc-rem',function(){
                orderItems.splice(parseInt($(this).data('idx')),1);
                renderItems();updateTotals();
            });

            /* ── Totals ── */
            function updateTotals(){
                var sub=0,vat=0;
                orderItems.forEach(function(it){vat+=it.vat_amount;sub+=it.unit_price*it.qty-it.vat_amount;});
                var discVal=parseFloat($('#hm-oc-disc').val())||0;
                var discAmt=0;
                if(discountMode==='pct'&&discVal>0) discAmt=Math.round(sub*(discVal/100)*100)/100;
                else if(discountMode==='eur'&&discVal>0) discAmt=Math.min(discVal,sub+vat);
                var prsiL=$('#hm-oc-prsi-l').is(':checked')?500:0;
                var prsiR=$('#hm-oc-prsi-r').is(':checked')?500:0;
                var total=Math.max(0,sub+vat-discAmt-prsiL-prsiR);
                $('#hm-oc-sub').text(fmt(sub));
                $('#hm-oc-vat').text(fmt(vat));
                $('#hm-oc-disc-amt').text('−'+fmt(discAmt));
                $('#hm-oc-total').text(fmt(total));
                updateDepBalance();
            }

            $(document).on('click','.hm-oc-disc-mode',function(){
                discountMode=$(this).data('mode');
                $('.hm-oc-disc-mode').css({background:'#fff',color:'#334155'});
                $(this).css({background:'#151B33',color:'#fff'});
                $('#hm-oc-disc-unit').text(discountMode==='pct'?'%':'€');
                $('#hm-oc-disc').attr(discountMode==='pct'?{max:100,step:1}:{max:99999,step:10}).val(0);
                updateTotals();
            });
            $('#hm-oc-disc').on('input',function(){updateTotals();});
            $('#hm-oc-prsi-l,#hm-oc-prsi-r').on('change',function(){updateTotals();});

            /* ── Deposit / split payments ── */
            function updateDepBalance(){
                var total=parseFloat($('#hm-oc-total').text().replace(/[^0-9.]/g,''))||0;
                var paid=0;
                paymentRows.forEach(function(p){paid+=parseFloat(p.amount)||0;});
                if(paid>0){$('#hm-oc-dep-bal-val').text(fmt(Math.max(0,total-paid)));$('#hm-oc-dep-balance').show();}
                else{$('#hm-oc-dep-balance').hide();}
            }

            function renderPaymentRows(){
                var h='';
                paymentRows.forEach(function(p,i){
                    h+='<div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">';
                    h+='<input type="number" class="hm-oc-inp hm-pay-amt" data-idx="'+i+'" step="0.01" min="0" value="'+p.amount+'" placeholder="€0.00" style="flex:1;min-width:80px">';
                    h+='<select class="hm-oc-inp hm-pay-method" data-idx="'+i+'" style="flex:1;min-width:110px">';
                    ['Card','Cash','Cheque','Bank Transfer'].forEach(function(m){h+='<option value="'+m+'"'+(p.method===m?' selected':'')+'>'+m+'</option>';});
                    h+='</select>';
                    h+='<input type="date" class="hm-oc-inp hm-pay-date" data-idx="'+i+'" value="'+p.date+'" style="flex:1;min-width:120px">';
                    h+='<button type="button" class="hm-pay-del" data-idx="'+i+'" style="border:none;background:none;color:#dc2626;font-size:18px;cursor:pointer;padding:0 6px">×</button>';
                    h+='</div>';
                });
                $('#hm-oc-payments-list').html(h);
                updateDepBalance();
            }

            $('#hm-oc-add-payment').on('click',function(){
                paymentRows.push({amount:'',method:'Card',date:new Date().toISOString().split('T')[0]});
                renderPaymentRows();
            });
            $(document).on('input change','.hm-pay-amt,.hm-pay-method,.hm-pay-date',function(){
                var i=parseInt($(this).data('idx'));
                if($(this).hasClass('hm-pay-amt'))    paymentRows[i].amount=$(this).val();
                if($(this).hasClass('hm-pay-method')) paymentRows[i].method=$(this).val();
                if($(this).hasClass('hm-pay-date'))   paymentRows[i].date=$(this).val();
                updateDepBalance();
            });
            $(document).on('click','.hm-pay-del',function(){
                paymentRows.splice(parseInt($(this).data('idx')),1);
                renderPaymentRows();
            });

            /* ── Pick from stock ── */
            $('#hm-oc-stock-toggle').on('click',function(){
                var $panel=$('#hm-oc-stock-panel');
                if($panel.is(':visible')){$panel.hide();return;}
                $panel.show();
                if(stockLoaded){renderStockList();return;}
                $.post(ajaxUrl,{action:'hm_get_order_stock',nonce:nonce},function(r){
                    $('#hm-oc-stock-loading').hide();
                    if(!r||!r.success){$('#hm-oc-stock-list').html('<p style="font-size:12px;color:#dc2626">Failed to load stock.</p>').show();return;}
                    stockItems=r.data.items||[];stockLoaded=true;renderStockList();
                });
            });
            $('#hm-oc-stock-search').on('input',function(){renderStockList();});
            function renderStockList(){
                var q=$('#hm-oc-stock-search').val().toLowerCase();
                var filtered=stockItems.filter(function(s){return !q||(s.manufacturer_name+' '+s.model_name+' '+(s.serial_number||'')).toLowerCase().indexOf(q)>=0;});
                if(!filtered.length){$('#hm-oc-stock-list').html('<p style="font-size:12px;color:#94a3b8;text-align:center;padding:8px 0">No stock found.</p>').show();return;}
                var h='<table style="width:100%;border-collapse:collapse;font-size:12px"><thead><tr style="border-bottom:1.5px solid #151B33">';
                ['Model','Serial','Price','Ear',''].forEach(function(t){h+='<th style="text-align:left;padding:4px;font-size:10px;font-weight:700;text-transform:uppercase;color:#64748b">'+t+'</th>';});
                h+='</tr></thead><tbody>';
                filtered.forEach(function(s){
                    h+='<tr style="border-bottom:1px solid #f1f5f9">';
                    h+='<td style="padding:6px 4px">'+esc((s.manufacturer_name?s.manufacturer_name+' ':'')+s.model_name)+'</td>';
                    h+='<td style="padding:6px 4px;color:#94a3b8">'+esc(s.serial_number||'—')+'</td>';
                    h+='<td style="padding:6px 4px;text-align:right">'+fmt(s.retail_price)+'</td>';
                    h+='<td style="padding:6px 4px"><select class="hm-stock-ear" style="font-size:11px;padding:3px 6px;border:1px solid #e2e8f0;border-radius:5px"><option value="Left">Left</option><option value="Right">Right</option><option value="Binaural">Binaural</option></select></td>';
                    h+='<td style="padding:6px 4px;text-align:right"><button type="button" class="hm-stock-add" data-sid="'+s.id+'" style="font-size:11px;font-weight:700;padding:4px 10px;border-radius:6px;border:none;background:#0BB4C4;color:#fff;cursor:pointer">Add</button></td>';
                    h+='</tr>';
                });
                h+='</tbody></table>';
                $('#hm-oc-stock-list').html(h).show();
            }
            $(document).on('click','.hm-stock-add',function(){
                var sid=$(this).data('sid');
                var s=stockItems.find(function(x){return x.id==sid;});
                if(!s) return;
                var ear=$(this).closest('tr').find('.hm-stock-ear').val();
                var price=parseFloat(s.retail_price||0);
                var qty=ear==='Binaural'?2:1;
                var gross=price*qty;
                var vatAmt=s.vat_rate>0?Math.round((gross-gross/(1+s.vat_rate/100))*100)/100:0;
                orderItems.push({id:s.product_id||0,type:'product',name:(s.manufacturer_name?s.manufacturer_name+' ':'')+s.model_name+(s.serial_number?' (S/N: '+s.serial_number+')':''),unit_price:price,qty:qty,ear:ear,vat_rate:s.vat_rate||0,vat_amount:vatAmt,line_total:gross,from_stock:true,stock_id:sid});
                stockItems=stockItems.filter(function(x){return x.id!=sid;});
                renderItems();updateTotals();renderStockList();$('#hm-oc-err').text('');
            });

            /* ── Submit ── */
            $('#hm-oc-submit').off('click').on('click',function(){
                console.log('SUBMIT FIRED — orderItems count:',orderItems.length,JSON.stringify(orderItems));
                if(!orderItems.length){$('#hm-oc-err').text('Please add at least one item.');return;}
                var total=parseFloat($('#hm-oc-total').text().replace(/[^0-9.]/g,''))||0;
                var dep=0;
                paymentRows.forEach(function(p){dep+=parseFloat(p.amount)||0;});
                if(dep>total+0.01){$('#hm-oc-err').text('Payments exceed order total.');return;}
                var firstMethod=paymentRows.length?paymentRows[0].method:'';
                var firstDate=paymentRows.length?paymentRows[0].date:'';
                if(dep>0&&!firstMethod){$('#hm-oc-err').text('Please select a payment method.');return;}
                var $btn=$(this);
                $btn.prop('disabled',true).text('Submitting…');
                $('#hm-oc-err').text('');
                var discVal=parseFloat($('#hm-oc-disc').val())||0;
                $.post(ajaxUrl,{
                    action:'hm_create_order',nonce:nonce,patient_id:pid,
                    items_json:JSON.stringify(orderItems),
                    notes:$('#hm-oc-notes').val()||'',
                    prsi_left:$('#hm-oc-prsi-l').is(':checked')?1:0,
                    prsi_right:$('#hm-oc-prsi-r').is(':checked')?1:0,
                    discount_pct:discountMode==='pct'?discVal:0,
                    discount_euro:discountMode==='eur'?discVal:0,
                    deposit_amount:dep,deposit_method:firstMethod,deposit_paid_at:firstDate,
                    payments_json:JSON.stringify(paymentRows),
                    payment_method:firstMethod
                },function(r){
                    if(r&&r.success){
                        var d=r.data;
                        $('#hm-oc-actions').hide();
                        var msg='<div style="background:#e8f8f0;border:1px solid #27AE60;border-left:4px solid #27AE60;padding:16px 20px;border-radius:6px;color:#1a4731">';
                        msg+='<div style="font-size:14px;font-weight:700;margin-bottom:4px">✓ Order '+esc(d.order_number)+' submitted for approval.</div>';
                        if(d.deposit_amount>0){msg+='<div style="font-size:13px;margin-bottom:6px">'+fmt(d.deposit_amount)+' deposit recorded. Balance '+fmt(d.balance_due)+' due at fitting.</div>';}
                        msg+='<div style="font-size:13px;margin-top:8px">Redirecting to calendar...</div></div>';
                        $('#hm-oc-success').html(msg).show();
                        setTimeout(function(){ window.location = '<?php echo esc_url( HearMed_Utils::page_url('calendar') ); ?>'; }, 900);
                    } else {
                        $btn.prop('disabled',false).text('Submit Order for Approval');
                        $('#hm-oc-err').text(r&&r.data&&r.data.message?r.data.message:(typeof r.data==='string'?r.data:'Failed to create order.'));
                    }
                }).fail(function(){
                    $btn.prop('disabled',false).text('Submit Order for Approval');
                    $('#hm-oc-err').text('Network error — please try again.');
                });
            });

        })();
        </script>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // VIEW ORDER
    // ═══════════════════════════════════════════════════════════════════════
    public static function render_view( $order_id ) {
        if ( ! $order_id ) return '<div class="hm-notice hm-notice--error">No order specified.</div>';

        $db    = HearMed_DB::instance();
        $role  = HearMed_Auth::current_role();
        $nonce = wp_create_nonce('hm_nonce');

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
             JOIN hearmed_core.patients p           ON p.id = o.patient_id
             LEFT JOIN hearmed_reference.clinics c  ON c.id = o.clinic_id
             LEFT JOIN hearmed_reference.staff s    ON s.id = o.staff_id
             LEFT JOIN hearmed_reference.staff ap   ON ap.id = o.approved_by
             LEFT JOIN hearmed_core.invoices inv    ON inv.id = o.invoice_id
             WHERE o.id = \$1", [$order_id]
        );
        if (!$order) return '<div class="hm-notice hm-notice--error">Order not found.</div>';

        $items = $db->get_results(
            "SELECT oi.*,
                    CASE
                        WHEN oi.item_type = 'product'
                            THEN p.product_name
                        ELSE s.service_name
                    END AS item_name,
                    p.tech_level
             FROM hearmed_core.order_items oi
             LEFT JOIN hearmed_reference.products p      ON p.id = oi.item_id AND oi.item_type = 'product'
             LEFT JOIN hearmed_reference.manufacturers m ON m.id = p.manufacturer_id
             LEFT JOIN hearmed_reference.services s      ON s.id = oi.item_id AND oi.item_type = 'service'
             WHERE oi.order_id = \$1 ORDER BY oi.line_number", [$order_id]
        );

        foreach ( $items as $item ) {
            if ( ( $item->item_type ?? '' ) === 'product' ) {
                $item->item_name = HearMed_Utils::format_hearing_aid_label( $item->item_name ?? '', $item->tech_level ?? '' );
            }
        }

        // Serial numbers from patient_devices
        $serials = $db->get_results(
            "SELECT pd.*, p.product_name, COALESCE(p.tech_level, '') AS tech_level
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

        // RBAC: role restrictions removed — status-only checks for now
        $can_approve  = $order->current_status === 'Awaiting Approval';
        $can_order    = $order->current_status === 'Approved';
        $can_receive  = $order->current_status === 'Ordered';
        $can_serials  = $order->current_status === 'Received';
        $can_complete = $order->current_status === 'Awaiting Fitting';
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
                            <?php if (($order->deposit_amount ?? 0) > 0) : ?>
                            <tr class="hm-text--green">
                                <td colspan="7" class="hm-text-right">Deposit Paid</td>
                                <td class="hm-money" style="color:#059669;">−€<?php echo number_format($order->deposit_amount,2); ?></td>
                            </tr>
                            <tr class="hm-invoice__total-row">
                                <td colspan="7" class="hm-text-right"><strong>Balance Due at Fitting</strong></td>
                                <td class="hm-money"><strong>€<?php echo number_format(max(0,$order->grand_total - $order->deposit_amount),2); ?></strong></td>
                            </tr>
                            <?php endif; ?>
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
                            <?php echo esc_html( HearMed_Utils::format_hearing_aid_label( $sd->product_name ?? '', $sd->tech_level ?? '' ) ); ?>:
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
                        <a href="<?php echo esc_url(admin_url('admin-ajax.php').'?action=hm_download_order_pdf&nonce='.wp_create_nonce('hm_nonce').'&order_id='.$order_id); ?>"
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

                    <!-- STAGE 3→4: Receive in Branch → then enter serials -->
                    <?php if ($can_receive) : ?>
                    <div class="hm-card hm-card--action">
                        <h3 class="hm-card-title">Aids Arrived?</h3>
                        <p class="hm-form__hint">Mark received and enter serial numbers.</p>
                        <button class="hm-btn hm-btn--primary hm-btn--block" id="hm-receive-branch-btn"
                                data-order-id="<?php echo $order_id; ?>"
                                data-nonce="<?php echo esc_attr($nonce); ?>">
                            Receive in Branch + Enter Serials
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

                    <!-- DEPOSIT — record or view deposit on any active order -->
                    <?php
                    $deposit_recorded = floatval( $order->deposit_amount ?? 0 ) > 0;
                    $is_active_order  = ! in_array( $order->current_status, [ 'Complete', 'Cancelled' ] );
                    ?>
                    <?php if ( $is_active_order ) : ?>
                    <div class="hm-card hm-card--action" id="hm-deposit-card">
                        <h3 class="hm-card-title">Deposit</h3>
                        <?php if ( $deposit_recorded ) : ?>
                        <div style="padding:10px 14px;background:#f0fdfe;border:1px solid #a5f3fc;border-radius:8px;font-size:13px;color:#0e7490;margin-bottom:8px">
                            <div><strong>Amount:</strong> €<?php echo number_format($order->deposit_amount,2); ?></div>
                            <div><strong>Method:</strong> <?php echo esc_html($order->deposit_method ?? '—'); ?></div>
                            <div><strong>Paid:</strong> <?php echo $order->deposit_paid_at ? date('j M Y', strtotime($order->deposit_paid_at)) : '—'; ?></div>
                        </div>
                        <?php else : ?>
                        <p class="hm-form__hint">Record a deposit for this order.</p>
                        <div style="margin-bottom:8px">
                            <label style="font-size:11px;font-weight:700;color:var(--hm-text,#334155);text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:4px">Amount (€)</label>
                            <input type="number" id="hm-dep-amount" step="0.01" min="0" placeholder="0.00"
                                   style="font-size:13px;padding:8px 12px;border-radius:8px;border:1.5px solid var(--hm-border,#e2e8f0);width:100%;box-sizing:border-box;font-family:var(--hm-font)">
                        </div>
                        <div style="margin-bottom:8px">
                            <label style="font-size:11px;font-weight:700;color:var(--hm-text,#334155);text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:4px">Payment Method</label>
                            <select id="hm-dep-method" style="font-size:13px;padding:8px 12px;border-radius:8px;border:1.5px solid var(--hm-border,#e2e8f0);width:100%;box-sizing:border-box;font-family:var(--hm-font)">
                                <option value="Card">Card</option>
                                <option value="Cash">Cash</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                            </select>
                        </div>
                        <div style="margin-bottom:10px">
                            <label style="font-size:11px;font-weight:700;color:var(--hm-text,#334155);text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:4px">Date Paid</label>
                            <input type="date" id="hm-dep-date" value="<?php echo date('Y-m-d'); ?>"
                                   style="font-size:13px;padding:8px 12px;border-radius:8px;border:1.5px solid var(--hm-border,#e2e8f0);width:100%;box-sizing:border-box;font-family:var(--hm-font)">
                        </div>
                        <button class="hm-btn hm-btn--primary hm-btn--block" id="hm-dep-submit"
                                data-order-id="<?php echo $order_id; ?>"
                                data-nonce="<?php echo esc_attr($nonce); ?>">
                            Record Deposit
                        </button>
                        <div id="hm-dep-msg" style="display:none;margin-top:8px;padding:8px 12px;border-radius:6px;font-size:12px"></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($can_print && !$can_order) : ?>
                    <div class="hm-card">
                        <a href="<?php echo esc_url(admin_url('admin-ajax.php').'?action=hm_download_order_pdf&nonce='.wp_create_nonce('hm_nonce').'&order_id='.$order_id); ?>"
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

        // Receive in Branch — marks as received then redirects to serial entry page
        var receiveBtn = document.getElementById('hm-receive-branch-btn');
        if (receiveBtn) {
            receiveBtn.addEventListener('click', function() {
                if (!confirm('Confirm aids have arrived in branch? You will be asked to enter serial numbers next.')) return;
                var me = this;
                me.disabled = true; me.textContent = 'Marking received...';
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {'Content-Type':'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'hm_mark_received',
                        order_id: me.dataset.orderId,
                        nonce: me.dataset.nonce
                    })
                }).then(function(r){return r.json();}).then(function(d){
                    if (d.success) {
                        window.location.href = '<?php echo esc_url($base); ?>?hm_action=serials&order_id=<?php echo $order_id; ?>';
                    } else {
                        alert('Error: ' + (d.data && d.data.message ? d.data.message : d.data));
                        me.disabled = false; me.textContent = 'Receive in Branch + Enter Serials';
                    }
                });
            });
        }

        // Record Deposit — inline deposit form
        var depBtn = document.getElementById('hm-dep-submit');
        if (depBtn) {
            depBtn.addEventListener('click', function() {
                var me = this;
                var amt = parseFloat(document.getElementById('hm-dep-amount').value) || 0;
                var method = document.getElementById('hm-dep-method').value;
                var dt = document.getElementById('hm-dep-date').value;
                var msgEl = document.getElementById('hm-dep-msg');
                if (amt <= 0) { msgEl.textContent = 'Enter a deposit amount.'; msgEl.style.display = 'block'; msgEl.style.background = '#fef2f2'; msgEl.style.color = '#991b1b'; return; }
                me.disabled = true; me.textContent = 'Recording…';
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {'Content-Type':'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'hm_record_order_deposit',
                        order_id: me.dataset.orderId,
                        nonce: me.dataset.nonce,
                        deposit_amount: amt,
                        deposit_method: method,
                        deposit_paid_at: dt
                    })
                }).then(function(r){return r.json();}).then(function(d){
                    if (d.success) { location.reload(); }
                    else { msgEl.textContent = d.data && d.data.message ? d.data.message : (typeof d.data === 'string' ? d.data : 'Failed'); msgEl.style.display = 'block'; msgEl.style.background = '#fef2f2'; msgEl.style.color = '#991b1b'; me.disabled = false; me.textContent = 'Record Deposit'; }
                });
            });
        }
        </script>
        <?php return ob_get_clean();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SERIAL NUMBERS — inserts into patient_devices
    // ═══════════════════════════════════════════════════════════════════════
    public static function render_serials( $order_id ) {
        $db    = HearMed_DB::instance();
        $nonce = wp_create_nonce('hm_nonce');
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
                    p.product_name AS item_name,
                    COALESCE(p.tech_level, '') AS tech_level
             FROM hearmed_core.order_items oi
             JOIN hearmed_reference.products p      ON p.id = oi.item_id
             JOIN hearmed_reference.manufacturers m ON m.id = p.manufacturer_id
             WHERE oi.order_id = \$1 AND oi.item_type = 'product'
             ORDER BY oi.line_number", [$order_id]
        );

        foreach ( $ha_items as $item ) {
            $item->item_name = HearMed_Utils::format_hearing_aid_label( $item->item_name ?? '', $item->tech_level ?? '' );
        }

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
        $nonce = wp_create_nonce('hm_nonce');
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

        // Pre-create invoice so we have a proper invoice number and can preview
        $invoice_id_for_preview = null;
        if ( empty( $order->invoice_number ) && class_exists( 'HearMed_Invoice' ) ) {
            $uid = HearMed_Auth::current_user();
            $invoice_id_for_preview = HearMed_Invoice::ensure_invoice_for_order( $order_id, $uid->id ?? null );
            if ( $invoice_id_for_preview ) {
                // Re-fetch order to get updated invoice data
                $order = $db->get_row(
                    "SELECT o.*, p.first_name, p.last_name,
                            inv.invoice_number, inv.grand_total AS invoice_total
                     FROM hearmed_core.orders o
                     JOIN hearmed_core.patients p ON p.id = o.patient_id
                     LEFT JOIN hearmed_core.invoices inv ON inv.id = o.invoice_id
                     WHERE o.id = \$1", [$order_id]
                );
            }
        }
        $invoice_id_for_preview = $invoice_id_for_preview ?: ( $order->invoice_id ?? null );

        // ── Check for hearing-aid products that still have NO serial numbers ──
        $missing_serials = $db->get_results(
            "SELECT oi.id AS order_item_id, oi.item_id AS product_id, oi.ear_side,
                    p.product_name AS item_name,
                    COALESCE(p.tech_level, '') AS tech_level
             FROM hearmed_core.order_items oi
             JOIN hearmed_reference.products p      ON p.id = oi.item_id
             JOIN hearmed_reference.manufacturers m ON m.id = p.manufacturer_id
             WHERE oi.order_id = \$1 AND oi.item_type = 'product'
               AND NOT EXISTS (
                   SELECT 1 FROM hearmed_core.patient_devices pd
                   WHERE pd.patient_id = \$2
                     AND pd.product_id = oi.item_id
                     AND (pd.serial_number_left IS NOT NULL OR pd.serial_number_right IS NOT NULL)
               )
             ORDER BY oi.line_number",
            [$order_id, $order->patient_id]
        );
        foreach ( $missing_serials as $item ) {
            $item->item_name = HearMed_Utils::format_hearing_aid_label( $item->item_name ?? '', $item->tech_level ?? '' );
        }
        $has_missing_serials = !empty($missing_serials);

        $deposit_already_paid = floatval( $order->deposit_amount ?? 0 );
        $amount_due = max( 0, ( $order->invoice_total ?? $order->grand_total ) - $deposit_already_paid );

        $patient_name = trim( $order->first_name . ' ' . $order->last_name );
        $invoice_number_display = $order->invoice_number ?: $order->order_number;

        ob_start(); ?>
        <!-- S6-FIX: INVOICE TRIGGER — Two-panel completion form matching order creation layout -->
        <div class="hm-content hm-complete-form"
             style="font-family:var(--hm-font,'Source Sans 3',sans-serif);color:var(--hm-text,#334155);-webkit-font-smoothing:antialiased">

            <!-- ═══ Teal top bar ═══ -->
            <div style="background:var(--hm-teal,#0BB4C4);color:#fff;display:flex;align-items:center;justify-content:center;padding:0 24px;height:50px;border-radius:10px 10px 0 0">
                <div style="text-align:center">
                    <div style="font-family:var(--hm-font-title,'Cormorant Garamond',serif);font-size:20px;font-weight:700;letter-spacing:-.3px">Record Fitting + Payment</div>
                    <div style="font-size:11px;opacity:.7;margin-top:1px"><?php echo esc_html($patient_name); ?> — <?php echo esc_html($invoice_number_display); ?></div>
                </div>
            </div>

            <!-- ═══ Two-panel split ═══ -->
            <div style="display:flex;min-height:600px;border:1px solid var(--hm-border,#e2e8f0);border-top:none;border-radius:0 0 10px 10px;overflow:hidden">

                <!-- ═════ LEFT PANEL — Payment form ═════ -->
                <div style="flex:0 0 40%;max-width:40%;overflow-y:auto;padding:24px 28px;background:#fff;border-right:1px solid var(--hm-border,#e2e8f0)">

                    <div style="font-size:12px;font-weight:700;color:var(--hm-text-light,#64748b);text-transform:uppercase;letter-spacing:.5px;margin-bottom:14px;display:flex;align-items:center;gap:8px">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                        Payment Details
                    </div>

                    <!-- Summary -->
                    <div style="padding:14px 16px;background:var(--hm-bg-alt,#f8fafc);border:1.5px solid var(--hm-border,#e2e8f0);border-radius:10px;margin-bottom:18px">
                        <div style="font-size:13px;color:var(--hm-text,#334155)"><strong>Patient:</strong> <?php echo esc_html($patient_name); ?></div>
                        <div style="font-size:13px;color:var(--hm-text,#334155);margin-top:4px"><strong>Payment Method:</strong> <?php echo esc_html($order->payment_method ?? '—'); ?></div>
                        <?php if ($order->prsi_applicable) : ?>
                        <div style="font-size:12px;color:var(--hm-text-muted,#94a3b8);margin-top:4px">PRSI grant of €<?php echo number_format($order->prsi_amount,2); ?> already deducted.</div>
                        <?php endif; ?>
                        <div style="font-size:18px;font-weight:700;color:var(--hm-teal,#0BB4C4);margin-top:8px">
                            Collect: €<?php echo number_format($amount_due,2); ?>
                        </div>
                    </div>

                    <!-- Patient cash credit application -->
                    <div id="hm-credit-available-row" style="display:none;margin-bottom:14px">
                        <div style="padding:12px 16px;background:#f0fdfe;border:1px solid #a5f3fc;border-radius:8px;margin-bottom:4px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <div>
                                    <span style="font-size:13px;font-weight:600;color:#0e7490;">Patient cash credit available</span>
                                    <span style="font-size:16px;font-weight:700;color:#0e7490;margin-left:8px;">
                                        €<span id="hm-credit-balance-display">0.00</span>
                                    </span>
                                </div>
                                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                    <input type="checkbox" id="hm-apply-credit-cb" name="apply_credit" value="1"
                                           style="width:18px;height:18px;accent-color:var(--hm-teal);">
                                    <span style="font-size:13px;font-weight:600;color:var(--hm-navy);">Apply credit</span>
                                </label>
                            </div>
                            <div id="hm-credit-apply-detail" style="display:none;margin-top:10px;padding-top:10px;border-top:1px solid #a5f3fc;">
                                <div style="display:flex;justify-content:space-between;font-size:13px;color:#0e7490;">
                                    <span>Credit applied:</span>
                                    <strong>€<span id="hm-credit-apply-amount">0.00</span></strong>
                                </div>
                                <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--hm-navy);margin-top:4px;">
                                    <span>Remaining to collect:</span>
                                    <strong>€<span id="hm-credit-remaining-collect">0.00</span></strong>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" id="hm-credit-apply-value" name="credit_apply_amount" value="0">
                    </div>

                    <div id="hm-prsi-balance-row" style="display:none;margin-bottom:14px">
                        <div style="padding:12px 16px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;font-size:12px;color:#1e3a8a;">
                            <div>PRSI entitlement available: <strong>€<span id="hm-prsi-balance-display">0.00</span></strong> (L: €<span id="hm-prsi-left-balance-display">0.00</span>, R: €<span id="hm-prsi-right-balance-display">0.00</span>).</div>
                            <div id="hm-prsi-next-eligible" style="margin-top:4px;opacity:.9;display:none;"></div>
                            <div style="margin-top:4px;">PRSI is tracked separately from cash credit and is not auto-applied as patient cash.</div>
                        </div>
                    </div>

                    <?php if ($has_missing_serials) : ?>
                    <!-- ── SERIAL NUMBERS REQUIRED ── -->
                    <div style="border:1.5px solid #fca5a5;border-radius:10px;padding:14px 16px;margin-bottom:14px;background:#fef2f2">
                        <div style="font-size:12px;font-weight:700;color:#991b1b;text-transform:uppercase;letter-spacing:.3px;margin-bottom:8px;display:flex;align-items:center;gap:6px">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><path d="M12 9v4M12 17h.01"/></svg>
                            Serial Numbers Required
                        </div>
                        <div style="font-size:12px;color:#7f1d1d;margin-bottom:10px">Enter serial numbers before finalising.</div>
                        <?php foreach ($missing_serials as $ms) :
                            $need_left  = in_array($ms->ear_side, ['Left','Binaural']);
                            $need_right = in_array($ms->ear_side, ['Right','Binaural']);
                            if (!$need_left && !$need_right) { $need_left = true; $need_right = true; }
                        ?>
                        <div style="background:#fff;border:1px solid #fecaca;border-radius:8px;padding:10px 12px;margin-bottom:8px">
                            <div style="font-size:12px;font-weight:700;color:var(--hm-navy,#151B33)"><?php echo esc_html($ms->item_name); ?></div>
                            <div style="font-size:11px;color:var(--hm-text-muted,#94a3b8);margin-bottom:6px"><?php echo esc_html($ms->ear_side ?? 'Unknown'); ?></div>
                            <input type="hidden" class="hm-serial-product" value="<?php echo $ms->product_id; ?>">
                            <input type="hidden" class="hm-serial-oiid"    value="<?php echo $ms->order_item_id; ?>">
                            <?php if ($need_left) : ?>
                            <div style="margin-bottom:6px">
                                <label style="font-size:11px;font-weight:700;color:var(--hm-text,#334155);text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:4px">Left Ear Serial <span style="color:#e53e3e">*</span></label>
                                <input type="text" class="hm-serial-left" placeholder="Serial number..."
                                       style="font-size:13px;padding:9px 12px;border-radius:8px;border:1.5px solid var(--hm-border,#e2e8f0);width:100%;box-sizing:border-box;font-family:var(--hm-font);transition:border-color .15s" required>
                            </div>
                            <?php endif; ?>
                            <?php if ($need_right) : ?>
                            <div>
                                <label style="font-size:11px;font-weight:700;color:var(--hm-text,#334155);text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:4px">Right Ear Serial <span style="color:#e53e3e">*</span></label>
                                <input type="text" class="hm-serial-right" placeholder="Serial number..."
                                       style="font-size:13px;padding:9px 12px;border-radius:8px;border:1.5px solid var(--hm-border,#e2e8f0);width:100%;box-sizing:border-box;font-family:var(--hm-font);transition:border-color .15s" required>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Form fields -->
                    <div style="margin-bottom:14px">
                        <label style="font-size:11px;font-weight:700;color:var(--hm-text,#334155);text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:5px">Fitting Date</label>
                        <input type="date" id="hm-fit-date" value="<?php echo date('Y-m-d'); ?>"
                               style="font-size:13px;padding:9px 12px;border-radius:8px;border:1.5px solid var(--hm-border,#e2e8f0);width:100%;box-sizing:border-box;font-family:var(--hm-font);transition:border-color .15s">
                    </div>
                    <div style="margin-bottom:14px">
                        <label style="font-size:11px;font-weight:700;color:var(--hm-text,#334155);text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:5px">Payment Method</label>
                        <select id="hm-fit-method"
                                style="font-size:13px;padding:9px 12px;border-radius:8px;border:1.5px solid var(--hm-border,#e2e8f0);width:100%;box-sizing:border-box;font-family:var(--hm-font);transition:border-color .15s;background:#fff">
                            <option value="Card"<?php echo ($order->payment_method ?? '') === 'Card' ? ' selected' : ''; ?>>Card</option>
                            <option value="Cash"<?php echo ($order->payment_method ?? '') === 'Cash' ? ' selected' : ''; ?>>Cash</option>
                            <option value="Bank Transfer"<?php echo ($order->payment_method ?? '') === 'Bank Transfer' ? ' selected' : ''; ?>>Bank Transfer</option>
                            <option value="Cheque"<?php echo ($order->payment_method ?? '') === 'Cheque' ? ' selected' : ''; ?>>Cheque</option>
                        </select>
                    </div>
                    <div style="margin-bottom:14px">
                        <label style="font-size:11px;font-weight:700;color:var(--hm-text,#334155);text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:5px">Amount Received (€)</label>
                        <input type="number" id="hm-fit-amount" step="0.01" value="<?php echo number_format($amount_due,2,'.',''); ?>"
                               style="font-size:13px;padding:9px 12px;border-radius:8px;border:1.5px solid var(--hm-border,#e2e8f0);width:100%;box-sizing:border-box;font-family:var(--hm-font);transition:border-color .15s">
                    </div>
                    <!-- Split payment toggle -->
                    <div style="margin-bottom:14px">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:600;color:var(--hm-text,#334155)">
                            <input type="checkbox" id="hm-fit-split" style="width:18px;height:18px;accent-color:var(--hm-teal)">
                            Split payment methods
                        </label>
                    </div>
                    <div id="hm-fit-split-row" style="display:none;margin-bottom:14px;padding:14px 16px;background:var(--hm-bg-alt,#f8fafc);border:1.5px solid var(--hm-border,#e2e8f0);border-radius:10px">
                        <div style="margin-bottom:10px">
                            <label style="font-size:11px;font-weight:700;color:var(--hm-text,#334155);text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:5px">Second Method</label>
                            <select id="hm-fit-method-2"
                                    style="font-size:13px;padding:9px 12px;border-radius:8px;border:1.5px solid var(--hm-border,#e2e8f0);width:100%;box-sizing:border-box;font-family:var(--hm-font);background:#fff">
                                <option value="">Select method</option>
                                <option value="Card">Card</option>
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cheque">Cheque</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size:11px;font-weight:700;color:var(--hm-text,#334155);text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:5px">Second Amount (€)</label>
                            <input type="number" id="hm-fit-amount-2" step="0.01" min="0" placeholder="0.00" readonly
                                style="font-size:13px;padding:9px 12px;border-radius:8px;border:1.5px solid var(--hm-border,#e2e8f0);width:100%;box-sizing:border-box;font-family:var(--hm-font);background:#f8fafc;color:#64748b;cursor:not-allowed">
                        </div>
                    </div>
                    <div style="margin-bottom:14px">
                        <label style="font-size:11px;font-weight:700;color:var(--hm-text,#334155);text-transform:uppercase;letter-spacing:.3px;display:block;margin-bottom:5px">Fitting Notes (optional)</label>
                        <textarea id="hm-fit-notes" rows="2" placeholder="Clinical notes, adjustments made..."
                                  style="font-size:13px;padding:9px 12px;border-radius:8px;border:1.5px solid var(--hm-border,#e2e8f0);width:100%;box-sizing:border-box;font-family:var(--hm-font);resize:vertical;transition:border-color .15s"></textarea>
                    </div>

                    <div style="padding:10px 14px;background:#f0fdfe;border:1px solid #a5f3fc;border-radius:8px;font-size:12px;color:#0e7490;margin-bottom:14px;display:flex;align-items:flex-start;gap:8px">
                        <span style="font-size:14px">ℹ️</span> This will: mark the invoice as Paid, create a payment record, log the fitting in the patient file, and sync to QuickBooks.
                    </div>

                    <button id="hm-confirm-complete"
                            data-order-id="<?php echo $order_id; ?>"
                            data-nonce="<?php echo esc_attr($nonce); ?>"
                            style="width:100%;padding:14px;font-size:14px;font-weight:700;border-radius:8px;border:none;background:var(--hm-navy,#151B33);color:#fff;cursor:pointer;font-family:var(--hm-font-btn);transition:all .15s">
                        ✓ Confirm Fitted + Paid — Finalise
                    </button>
                    <div id="hm-complete-msg" style="display:none;margin-top:12px;padding:10px 14px;border-radius:8px;font-size:13px"></div>
                </div>

                <!-- ═════ RIGHT PANEL — Invoice preview (Form Builder template) ═════ -->
                <div style="flex:0 0 60%;max-width:60%;display:flex;flex-direction:column;background:#fff;overflow:hidden">

                    <!-- Invoice toolbar -->
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 24px;border-bottom:1px solid var(--hm-border,#e2e8f0);background:var(--hm-bg-alt,#f8fafc)">
                        <div>
                            <span style="font-size:14px;font-weight:700;color:var(--hm-navy,#151B33)"><?php echo esc_html($order->invoice_number ?: 'Invoice'); ?></span>
                            <span style="font-size:12px;color:var(--hm-text-muted,#94a3b8);margin-left:8px"><?php echo esc_html($order->order_number ?? ''); ?></span>
                        </div>
                        <div style="display:flex;gap:8px">
                            <?php if ($invoice_id_for_preview) : ?>
                            <a href="<?php echo esc_url(admin_url('admin-ajax.php').'?action=hm_download_invoice&nonce='.$nonce.'&_ID='.(int)$invoice_id_for_preview); ?>" target="_blank"
                               style="font-size:12px;font-weight:600;padding:5px 12px;border-radius:6px;border:1px solid var(--hm-border,#e2e8f0);background:#fff;color:var(--hm-navy,#151B33);text-decoration:none;display:flex;align-items:center;gap:4px;cursor:pointer">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6M7 10l5 5 5-5M12 15V3"/></svg>
                                Open Full Invoice
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Invoice preview iframe (uses Form Builder template) -->
                    <?php if ($invoice_id_for_preview) : ?>
                    <iframe src="<?php echo esc_url(admin_url('admin-ajax.php').'?action=hm_download_invoice&nonce='.$nonce.'&_ID='.(int)$invoice_id_for_preview); ?>"
                            style="flex:1;width:100%;border:none;min-height:580px"
                            title="Invoice Preview"></iframe>
                    <?php else : ?>
                    <div style="flex:1;display:flex;align-items:center;justify-content:center;color:var(--hm-text-muted,#94a3b8);font-size:14px;font-style:italic">
                        Invoice preview unavailable — invoice will be created on finalise.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
        var hmPatientId  = <?php echo (int) $order->patient_id; ?>;
        var hmBalanceDue = <?php echo (float) $amount_due; ?>;

        (function(){
            // ── Check patient credit balance on form load ──
            if (hmBalanceDue > 0) {
                var fd = new URLSearchParams({action:'hm_get_patient_credit_balance', nonce:'<?php echo $nonce; ?>', patient_id:hmPatientId});
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:fd})
                .then(function(r){return r.json();})
                .then(function(res){
                    if (!res.success) return;
                    var creditBalance = parseFloat(res.data.cash_balance || res.data.balance || 0);
                    var prsiBalance = parseFloat(res.data.prsi_balance || 0);
                    if (creditBalance > 0) {
                        document.getElementById('hm-credit-available-row').style.display = '';
                        document.getElementById('hm-credit-balance-display').textContent = creditBalance.toFixed(2);
                    }

                    if (prsiBalance > 0) {
                        document.getElementById('hm-prsi-balance-row').style.display = '';
                        document.getElementById('hm-prsi-balance-display').textContent = prsiBalance.toFixed(2);
                        document.getElementById('hm-prsi-left-balance-display').textContent = parseFloat(res.data.prsi_left_balance || 0).toFixed(2);
                        document.getElementById('hm-prsi-right-balance-display').textContent = parseFloat(res.data.prsi_right_balance || 0).toFixed(2);
                    }

                    var leftNext = res.data.prsi_next_left_eligible || '';
                    var rightNext = res.data.prsi_next_right_eligible || '';
                    if (leftNext || rightNext) {
                        var nextBox = document.getElementById('hm-prsi-next-eligible');
                        nextBox.style.display = '';
                        nextBox.textContent = 'Next PRSI claim eligibility - Left: ' + (leftNext || 'Now') + ', Right: ' + (rightNext || 'Now');
                    }

                    if (creditBalance <= 0) return;

                    document.getElementById('hm-apply-credit-cb').addEventListener('change', function(){
                        var detail = document.getElementById('hm-credit-apply-detail');
                        var amtEl  = document.getElementById('hm-fit-amount');
                        if (this.checked) {
                            var creditToApply = Math.min(creditBalance, hmBalanceDue);
                            var remaining     = hmBalanceDue - creditToApply;
                            detail.style.display = '';
                            document.getElementById('hm-credit-apply-amount').textContent = creditToApply.toFixed(2);
                            document.getElementById('hm-credit-remaining-collect').textContent = remaining.toFixed(2);
                            document.getElementById('hm-credit-apply-value').value = creditToApply.toFixed(2);
                            amtEl.value = remaining.toFixed(2);
                        } else {
                            detail.style.display = 'none';
                            document.getElementById('hm-credit-apply-value').value = '0';
                            amtEl.value = hmBalanceDue.toFixed(2);
                        }
                        if (document.getElementById('hm-fit-split').checked) {
                            hmRebalanceSplitAmounts('first');
                        }
                    });
                });
            }
        })();

        function hmCurrentCollectTotal() {
            var creditApplied = parseFloat(document.getElementById('hm-credit-apply-value').value || 0);
            return Math.max(0, hmBalanceDue - creditApplied);
        }

        function hmSetAmountFieldState(el, isAlert) {
            if (!el) return;
            el.style.borderColor = isAlert ? '#dc2626' : '';
            el.style.color = isAlert ? '#b91c1c' : '';
        }

        function hmValidatePaymentInputs() {
            var amount1El = document.getElementById('hm-fit-amount');
            var amount2El = document.getElementById('hm-fit-amount-2');
            var splitEl   = document.getElementById('hm-fit-split');
            var msg       = document.getElementById('hm-complete-msg');

            var due = hmCurrentCollectTotal();
            var a1  = parseFloat((amount1El && amount1El.value) || 0);
            var a2  = parseFloat((amount2El && amount2El.value) || 0);
            if (!isFinite(a1) || a1 < 0) a1 = 0;
            if (!isFinite(a2) || a2 < 0) a2 = 0;

            var splitOn = !!(splitEl && splitEl.checked);
            var entered = splitOn ? (a1 + a2) : a1;
            var isOver = entered > due + 0.009;
            var delta = Math.abs(due - entered);
            var isMismatch = !isOver && delta >= 0.01;

            hmSetAmountFieldState(amount1El, isOver);
            hmSetAmountFieldState(amount2El, splitOn && isOver);

            if (msg) {
                if (isOver) {
                    msg.style.display = 'block';
                    msg.className = '';
                    msg.style.background = '#fef2f2';
                    msg.style.border = '1px solid #fecaca';
                    msg.style.color = '#991b1b';
                    msg.innerHTML = '<strong>Flag:</strong> Entered amount (€' + entered.toFixed(2) + ') is above outstanding (€' + due.toFixed(2) + ').';
                    msg.dataset.mode = 'payment-flag';
                } else if (isMismatch) {
                    msg.style.display = 'block';
                    msg.className = '';
                    msg.style.background = '#fffbeb';
                    msg.style.border = '1px solid #fde68a';
                    msg.style.color = '#92400e';
                    msg.innerHTML = '<strong>Flag:</strong> Amounts do not exactly match outstanding (difference €' + delta.toFixed(2) + ').';
                    msg.dataset.mode = 'payment-flag';
                } else if (msg.dataset.mode === 'payment-flag') {
                    msg.style.display = 'none';
                    msg.innerHTML = '';
                    msg.dataset.mode = '';
                    msg.style.background = '';
                    msg.style.border = '';
                    msg.style.color = '';
                }
            }

            return { totalDue: due, totalEntered: entered, isOver: isOver, isMismatch: isMismatch };
        }

        function hmRebalanceSplitAmounts(changed) {
            var amount1El = document.getElementById('hm-fit-amount');
            var amount2El = document.getElementById('hm-fit-amount-2');
            var totalDue  = hmCurrentCollectTotal();

            var a1 = parseFloat(amount1El.value || 0);
            if (!isFinite(a1) || a1 < 0) a1 = 0;

            // Amount Received is the master value. Second amount is always derived.
            var a2 = Math.max(0, totalDue - a1);

            amount1El.value = a1.toFixed(2);
            amount2El.value = a2.toFixed(2);
            hmValidatePaymentInputs();
        }

        // ── Split payment toggle ──
        document.getElementById('hm-fit-split').addEventListener('change', function() {
            document.getElementById('hm-fit-split-row').style.display = this.checked ? '' : 'none';
            if (this.checked) {
                hmRebalanceSplitAmounts('init');
            } else {
                document.getElementById('hm-fit-method-2').value = '';
                document.getElementById('hm-fit-amount-2').value = '';
                hmValidatePaymentInputs();
            }
        });

        document.getElementById('hm-fit-amount').addEventListener('input', function() {
            if (!document.getElementById('hm-fit-split').checked) return;
            hmRebalanceSplitAmounts('first');
            hmValidatePaymentInputs();
        });

        document.getElementById('hm-fit-method').addEventListener('change', hmValidatePaymentInputs);
        document.getElementById('hm-fit-method-2').addEventListener('change', hmValidatePaymentInputs);
        document.getElementById('hm-fit-amount').addEventListener('input', hmValidatePaymentInputs);

        document.getElementById('hm-confirm-complete').addEventListener('click', function() {

            // ── Collect inline serials if any are on-screen ──
            var serialCards = document.querySelectorAll('.hm-serial-product');
            var serials = [];
            var serialsMissing = false;
            serialCards.forEach(function(el) {
                var card = el.closest('.hm-card--inset');
                var pid  = el.value;
                var oiid = card.querySelector('.hm-serial-oiid').value;
                var leftEl  = card.querySelector('.hm-serial-left');
                var rightEl = card.querySelector('.hm-serial-right');
                var left  = leftEl  ? leftEl.value.trim()  : '';
                var right = rightEl ? rightEl.value.trim() : '';
                if ((leftEl && !left) || (rightEl && !right)) {
                    serialsMissing = true;
                    if (leftEl  && !left)  leftEl.style.borderColor  = '#e53e3e';
                    if (rightEl && !right) rightEl.style.borderColor = '#e53e3e';
                } else {
                    if (leftEl)  leftEl.style.borderColor  = '';
                    if (rightEl) rightEl.style.borderColor = '';
                }
                serials.push({product_id: pid, order_item_id: oiid, left: left, right: right});
            });
            if (serialsMissing) {
                alert('Please enter ALL serial numbers before finalising.');
                return;
            }

            if (!confirm('Confirm patient fitted and payment received? This will finalise the invoice.')) return;
            const btn = this;
            btn.disabled=true; btn.textContent='Finalising...';

            // Inline flagging for amount checks; do not hard-jam the screen.
            var check = hmValidatePaymentInputs();
            if (check.isOver) {
                btn.disabled=false; btn.textContent='✓ Confirm Fitted + Paid — Finalise';
                return;
            }

            var params = {
                action:'hm_complete_order',
                order_id: btn.dataset.orderId,
                nonce: btn.dataset.nonce,
                fit_date: document.getElementById('hm-fit-date').value,
                amount: document.getElementById('hm-fit-amount').value,
                payment_method: document.getElementById('hm-fit-method').value,
                notes: document.getElementById('hm-fit-notes').value,
                apply_credit: document.getElementById('hm-apply-credit-cb').checked ? '1' : '',
                credit_apply_amount: document.getElementById('hm-credit-apply-value').value
            };
            // Build split payments array
            var splitCb = document.getElementById('hm-fit-split');
            if (splitCb && splitCb.checked) {
                var m2 = document.getElementById('hm-fit-method-2').value;
                var a2 = parseFloat(document.getElementById('hm-fit-amount-2').value || 0);
                if (m2 && a2 > 0) {
                    params.split_payments_json = JSON.stringify([
                        {method: params.payment_method, amount: parseFloat(params.amount)},
                        {method: m2, amount: a2}
                    ]);
                }
            }
            if (serials.length) params.inline_serials = JSON.stringify(serials);

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body: new URLSearchParams(params)
            }).then(r=>r.json()).then(d=>{
                const msg=document.getElementById('hm-complete-msg');
                msg.style.display='block';
                if (d.success) {
                    msg.className='hm-notice hm-notice--success';
                    msg.dataset.mode='';
                    msg.innerHTML='<div class="hm-notice-body"><span class="hm-notice-icon">✓</span> '+d.data.message+'</div>';
                    var printUrl = '';
                    if (d.data && typeof d.data.print_url === 'string') {
                        printUrl = d.data.print_url;
                    }

                    // Guard against malformed URLs like "&_ID=undefined" from stale client/server payloads.
                    if (printUrl && /(?:\?|&)_ID=undefined(?:&|$)/i.test(printUrl)) {
                        printUrl = '';
                    }

                    if (!printUrl && d.data) {
                        var invoiceId = parseInt(d.data.invoice_id, 10);
                        if (Number.isFinite(invoiceId) && invoiceId > 0) {
                            printUrl = '<?php echo esc_js( admin_url('admin-ajax.php') ); ?>'
                                + '?action=hm_download_invoice'
                                + '&nonce=' + encodeURIComponent(btn.dataset.nonce || '')
                                + '&_ID=' + invoiceId
                                + '&auto_print=1';
                        }
                    }

                    if (printUrl) {
                        var iframe = document.createElement('iframe');
                        iframe.style.position = 'fixed';
                        iframe.style.right = '0';
                        iframe.style.bottom = '0';
                        iframe.style.width = '0';
                        iframe.style.height = '0';
                        iframe.style.border = '0';
                        iframe.style.opacity = '0';
                        iframe.setAttribute('aria-hidden', 'true');
                        iframe.src = printUrl;
                        document.body.appendChild(iframe);
                    }
                    setTimeout(()=>window.location=(d.data.next_redirect || d.data.redirect), 1200);
                } else {
                    msg.className='hm-notice hm-notice--error';
                    msg.dataset.mode='';
                    var errText = (d && d.data && d.data.message) ? d.data.message : (typeof d.data === 'string' ? d.data : 'Finalise failed.');
                    msg.innerHTML='<div class="hm-notice-body"><span class="hm-notice-icon">×</span> '+errText+'</div>';
                    btn.disabled=false; btn.textContent='✓ Confirm Fitted + Paid — Finalise';
                }
            }).catch(function(){
                const msg=document.getElementById('hm-complete-msg');
                msg.style.display='block';
                msg.className='hm-notice hm-notice--error';
                msg.dataset.mode='';
                msg.innerHTML='<div class="hm-notice-body"><span class="hm-notice-icon">×</span> Network error while finalising. Please try again.</div>';
                btn.disabled=false; btn.textContent='✓ Confirm Fitted + Paid — Finalise';
            });
        });

        hmValidatePaymentInputs();
        </script>
        <?php return ob_get_clean();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PRINTABLE ORDER SHEET
    // ═══════════════════════════════════════════════════════════════════════
    public static function render_order_sheet( $order_id ) {
        $db = HearMed_DB::instance();
        $order = $db->get_row(
            "SELECT o.*,
                    p.first_name AS p_first, p.last_name AS p_last, p.patient_number,
                    p.date_of_birth, p.phone, p.mobile, p.prsi_number AS pps_number,
                    c.clinic_name,
                    CONCAT_WS(', ', c.address_line1, c.address_line2, c.city, c.county, COALESCE(c.postcode, '')) AS clinic_address,
                    c.phone AS clinic_phone,
                    CONCAT(s.first_name,' ',s.last_name) AS dispenser_name
             FROM hearmed_core.orders o
             JOIN hearmed_core.patients p         ON p.id = o.patient_id
             LEFT JOIN hearmed_reference.clinics c ON c.id = o.clinic_id
             LEFT JOIN hearmed_reference.staff s   ON s.id = o.staff_id
             WHERE o.id = \$1", [$order_id]
        );
        if (!$order) return '<p>Order not found.</p>';

        // Full item details including hearing_aid_class, dome, speaker, manufacturer address
        $items = $db->get_results(
            "SELECT oi.*,
                    pr.product_name, pr.style, pr.tech_level, pr.product_code,
                    pr.hearing_aid_class, pr.dome_type, pr.dome_size,
                    m.name   AS manufacturer_name,
                    m.address AS manufacturer_address,
                    m.order_email AS manufacturer_email,
                    m.order_phone AS manufacturer_phone,
                    m.order_contact_name AS manufacturer_contact,
                    m.account_number AS manufacturer_account
             FROM hearmed_core.order_items oi
             LEFT JOIN hearmed_reference.products pr     ON pr.id = oi.item_id AND oi.item_type = 'product'
             LEFT JOIN hearmed_reference.manufacturers m ON m.id = pr.manufacturer_id
             WHERE oi.order_id = \$1
             ORDER BY oi.line_number", [$order_id]
        );

        foreach ( $items as $item ) {
            if ( ( $item->item_type ?? '' ) === 'product' ) {
                $item->display_name = HearMed_Utils::format_hearing_aid_label( $item->product_name ?? '', $item->tech_level ?? '' );
            }
        }

        $tpl_data = clone $order;
        $tpl_data->items = $items ?: [];
        $tpl_data->order_status = $order->current_status ?? '';

        if (!empty($order->approved_by)) {
            $approver = $db->get_row(
                "SELECT CONCAT(first_name, ' ', last_name) AS name FROM hearmed_reference.staff WHERE id = \$1",
                [$order->approved_by]
            );
            $tpl_data->approved_by_name = $approver ? $approver->name : '';
            $tpl_data->approved_at      = $order->approved_at ?? $order->approved_date ?? '';
        }

        $tpl_data->ear_mould_type       = $order->ear_mould_type ?? '';
        $tpl_data->ear_mould_vent       = $order->ear_mould_vent ?? '';
        $tpl_data->ear_mould_material   = $order->ear_mould_material ?? '';
        $tpl_data->special_instructions = $order->notes ?? '';

        if ( class_exists( 'HearMed_Print_Templates' ) ) {
            return HearMed_Print_Templates::render( 'order', $tpl_data );
        }

        return '<p>Order template engine not available.</p>';
    }

    /**
     * AJAX handler: print order sheet in a standalone browser tab.
     * Outputs a complete HTML document and exits — avoids nesting inside WP template.
     */
    public static function ajax_print_order_sheet() {
        if ( ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', 'hm_nonce' ) ) {
            wp_die( 'Security check failed — please refresh the page and try again.' );
        }
        if ( ! PortalAuth::is_logged_in() ) wp_die( 'Access denied.' );

        $order_id = intval( $_GET['order_id'] ?? $_POST['order_id'] ?? 0 );
        if ( ! $order_id ) wp_die( 'Missing order ID.' );

        $html = self::render_order_sheet( $order_id );
        header( 'Content-Type: text/html; charset=utf-8' );
        echo $html;
        exit;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AJAX HANDLERS
    // ═══════════════════════════════════════════════════════════════════════

    public static function ajax_create_order() {
        check_ajax_referer('hm_nonce','nonce');
        self::ensure_tracking_schema();

        $patient_id      = intval($_POST['patient_id'] ?? 0);
        $payment_method  = sanitize_text_field($_POST['payment_method'] ?? '');
        $notes           = sanitize_textarea_field($_POST['notes'] ?? '');
        $prsi_left       = !empty($_POST['prsi_left']);
        $prsi_right      = !empty($_POST['prsi_right']);
        $items           = json_decode(wp_unslash($_POST['items_json'] ?? '[]'), true);
        $deposit_amount  = floatval($_POST['deposit_amount'] ?? 0);
        $deposit_method  = sanitize_text_field($_POST['deposit_method'] ?? '');
        $deposit_paid_at = sanitize_text_field($_POST['deposit_paid_at'] ?? '');

        if (!$patient_id)     wp_send_json_error('Please select a patient.');
        // payment_method is optional — may be blank when no deposit is taken
        if (empty($items))    wp_send_json_error('Please add at least one item.');
        if ($deposit_amount < 0) wp_send_json_error('Deposit cannot be negative.');

        $db     = HearMed_DB::instance();
        $clinic = HearMed_Auth::current_clinic();
        $user   = HearMed_Auth::current_user();

        $subtotal = $vat_total = $discount_total = 0;
        foreach ($items as $item) {
            $gross      = floatval($item['unit_price']) * intval($item['qty']);
            $vat_total += floatval($item['vat_amount']);
            $subtotal  += $gross - floatval($item['vat_amount']); // net (ex-VAT)
        }

        $prsi_sources = self::resolve_prsi_sources_for_order( $patient_id, $prsi_left, $prsi_right );
        $prsi_per_ear = self::prsi_per_ear_amount();
        $prsi_applicable = $prsi_left || $prsi_right;
        $prsi_amount     = ( $prsi_left ? $prsi_per_ear : 0 ) + ( $prsi_right ? $prsi_per_ear : 0 );
        // Prices are VAT-inclusive, so grand = net + vat - PRSI = gross - PRSI
        $grand_total     = max(0, $subtotal + $vat_total - $prsi_amount);

        // Deposit validation: may be zero (no deposit) or partial — cannot exceed grand total
        if ($deposit_amount > $grand_total + 0.01) {
            wp_send_json_error('Deposit cannot exceed the order total (€' . number_format($grand_total, 2) . ').');
        }

        // ── Quickpay detection: service-only orders bypass approval ──
        $is_quickpay = ! empty($items);
        foreach ($items as $item) {
            if (($item['type'] ?? '') !== 'service') {
                $is_quickpay = false;
                break;
            }
        }
        $initial_status = $is_quickpay ? 'Complete' : 'Awaiting Approval';

        $order_num = HearMed_Utils::generate_order_number();

        $order_id = $db->insert('hearmed_core.orders', [
            'order_number'    => $order_num,
            'patient_id'      => $patient_id,
            'staff_id'        => $user->id ?? null,
            'clinic_id'       => $clinic,
            'order_date'      => date('Y-m-d'),
            'current_status'  => $initial_status,
            'subtotal'        => $subtotal,
            'discount_total'  => $discount_total,
            'vat_total'       => $vat_total,
            'grand_total'     => $grand_total,
            'prsi_applicable' => $prsi_applicable,
            'prsi_amount'     => $prsi_amount,
            'prsi_left'       => $prsi_left,
            'prsi_right'      => $prsi_right,
            'prsi_left_source'=> $prsi_sources['left'],
            'prsi_right_source'=> $prsi_sources['right'],
            'payment_method'  => $payment_method,
            'deposit_amount'  => $deposit_amount > 0 ? $deposit_amount : 0,
            'deposit_method'  => $deposit_amount > 0 ? $deposit_method : null,
            'deposit_paid_at' => $deposit_amount > 0 && $deposit_paid_at ? $deposit_paid_at : null,
            'notes'           => $notes,
            'created_by'      => $user->id ?? null,
            'fitted_date'     => $is_quickpay ? date('Y-m-d H:i:s') : null,
        ]);;

        if (!$order_id) wp_send_json_error('Failed to save order. Please try again.');

        // Product costing map, including manufacturer-specific charger costs.
        $product_cost_map = [];
        $product_ids = [];
        foreach ($items as $item) {
            if (($item['type'] ?? '') === 'product') {
                $pid = intval($item['id'] ?? 0);
                if ($pid > 0) $product_ids[] = $pid;
            }
        }
        $product_ids = array_values(array_unique($product_ids));
        if (!empty($product_ids)) {
            $params = [];
            $ph = [];
            foreach ($product_ids as $idx => $pid) {
                $params[] = $pid;
                $ph[] = '$' . ($idx + 1);
            }
            $rows = $db->get_results(
                "SELECT p.id,
                        COALESCE(p.cost_price, 0) AS base_cost,
                        COALESCE((
                            SELECT c.cost_price
                            FROM hearmed_reference.products c
                            WHERE c.is_active = true
                              AND c.item_type = 'charger'
                              AND c.manufacturer_id = p.manufacturer_id
                            ORDER BY c.updated_at DESC NULLS LAST, c.id DESC
                            LIMIT 1
                        ), 0) AS charger_cost
                 FROM hearmed_reference.products p
                 WHERE p.id IN (" . implode(',', $ph) . ")",
                $params
            ) ?: [];

            foreach ($rows as $r) {
                $product_cost_map[(int)$r->id] = [
                    'base_cost'    => (float)($r->base_cost ?? 0),
                    'charger_cost' => (float)($r->charger_cost ?? 0),
                ];
            }
        }

        // Insert line items
        $line = 1;
        foreach ($items as $item) {
            $item_type = sanitize_key($item['type'] ?? '');
            $item_id = intval($item['id'] ?? 0);
            $needs_charger = !empty($item['charger']);

            $unit_cost_price = 0.0;
            if ($item_type === 'product' && $item_id > 0) {
                $cost_row = $product_cost_map[$item_id] ?? null;
                if ($cost_row) {
                    $unit_cost_price = (float)$cost_row['base_cost'];
                    if ($needs_charger) {
                        $unit_cost_price += (float)$cost_row['charger_cost'];
                    }
                }
            }

            $db->insert('hearmed_core.order_items', [
                'order_id'          => $order_id,
                'line_number'       => $line++,
                'item_type'         => $item_type,
                'item_id'           => $item_id,
                'item_description'  => sanitize_text_field($item['name']),
                'ear_side'          => sanitize_text_field($item['ear'] ?? ''),
                'speaker_size'      => sanitize_text_field($item['speaker'] ?? ''),
                'needs_charger'     => $needs_charger,
                'quantity'          => intval($item['qty']),
                'unit_cost_price'   => $unit_cost_price,
                // Store net (ex-VAT) price: gross - (extracted VAT / qty)
                'unit_retail_price' => round( floatval($item['unit_price']) - ( floatval($item['vat_amount']) / max( intval($item['qty']), 1 ) ), 2 ),
                'vat_rate'          => floatval($item['vat_rate']),
                'vat_amount'        => floatval($item['vat_amount']),
                'line_total'        => floatval($item['line_total']),
            ]);
        }

        // Status history log
        self::log_status_change($order_id, null, $initial_status, $user->id ?? null,
            $is_quickpay ? 'Service quickpay — approval not required' : 'Order created');

        // ── Record deposit payment in payments table + financial_transactions ──
        if ( $deposit_amount > 0 ) {
            $db->insert( 'hearmed_core.payments', [
                'invoice_id'     => null,               // no invoice yet at order creation
                'patient_id'     => $patient_id,
                'amount'         => $deposit_amount,
                'payment_date'   => $deposit_paid_at ?: date( 'Y-m-d' ),
                'payment_method' => $deposit_method ?: $payment_method,
                'received_by'    => $user->id ?? null,
                'clinic_id'      => $clinic,
                'created_by'     => $user->id ?? null,
                'is_refund'      => false,
            ] );

            if ( class_exists( 'HearMed_Finance' ) ) {
                HearMed_Finance::record( 'deposit', $deposit_amount, [
                    'patient_id'       => $patient_id,
                    'order_id'         => $order_id,
                    'payment_method'   => $deposit_method ?: $payment_method,
                    'staff_id'         => $user->id ?? null,
                    'clinic_id'        => $clinic,
                    'notes'            => 'Deposit at order creation — held against this order',
                    'reference'        => $order_num,
                    'transaction_date' => $deposit_paid_at ?: date('Y-m-d'),
                ] );
            }

            // IMPORTANT: deposits are not patient credits.
            // They remain tied to this order and are carried into its invoice at fitting.
        }

        // ── Quickpay: auto-create invoice for service-only orders ──
        $invoice_id = null;
        if ( $is_quickpay && class_exists( 'HearMed_Invoice' ) ) {
            try {
                $invoice_id = HearMed_Invoice::ensure_invoice_for_order( $order_id, $user->id ?? null );
            } catch ( Throwable $e ) {
                error_log( '[HearMed] Quickpay auto-invoice failed: ' . $e->getMessage() );
            }
        }

        // ── Duplicate detection: same patient + same product within 90 days ──
        $duplicate_flag = false;
        $dup_reason     = '';
        $product_ids_for_dup = [];
        foreach ($items as $item) {
            if (($item['type'] ?? '') === 'product' && intval($item['id'] ?? 0) > 0) {
                $product_ids_for_dup[] = intval($item['id']);
            }
        }
        $product_ids_for_dup = array_values(array_unique($product_ids_for_dup));

        if (!empty($product_ids_for_dup)) {
            $dup_params = [$patient_id, $order_id];
            $ph = [];
            foreach ($product_ids_for_dup as $idx => $pid) {
                $dup_params[] = $pid;
                $ph[] = '$' . ($idx + 3);
            }
            $dup_order = $db->get_row(
                "SELECT DISTINCT o2.id, o2.order_number
                 FROM hearmed_core.orders o2
                 JOIN hearmed_core.order_items oi2 ON oi2.order_id = o2.id
                 WHERE o2.patient_id = \$1
                   AND o2.id <> \$2
                   AND o2.current_status <> 'Cancelled'
                   AND o2.created_at > NOW() - INTERVAL '90 days'
                   AND oi2.item_type = 'product'
                   AND oi2.item_id IN (" . implode(',', $ph) . ")
                 ORDER BY o2.created_at DESC
                 LIMIT 1",
                $dup_params
            );
            if ($dup_order) {
                $duplicate_flag = true;
                $dup_reason = 'Same product(s) ordered for this patient within 90 days (see ' . $dup_order->order_number . ')';
                $db->update('hearmed_core.orders', [
                    'is_flagged'  => true,
                    'flag_reason' => $dup_reason,
                ], ['id' => $order_id]);
            }
        }

        // Notify C-Level (only for orders needing approval)
        if ( ! $is_quickpay ) {
            self::notify('c_level', 'order_awaiting_approval', $order_id, ['order_number' => $order_num]);
        }

        // Fetch patient name for the success response
        $patient_row = $db->get_row(
            "SELECT CONCAT(first_name, ' ', last_name) AS name FROM hearmed_core.patients WHERE id = \$1",
            [$patient_id]
        );
        $balance_due = max(0, $grand_total - $deposit_amount);

        wp_send_json_success([
            'message'        => $is_quickpay
                ? 'Service invoice '.$order_num.' created.'
                : 'Order '.$order_num.' submitted for C-Level approval.',
            'order_id'       => $order_id,
            'order_number'   => $order_num,
            'debug_items_decoded' => count($items),
            'debug_item_sample'   => !empty($items[0]) ? array_keys($items[0]) : [],
            'debug_order_insert'  => $order_id ? 'ok' : 'FAILED',

            'is_quickpay'    => $is_quickpay,
            'invoice_id'     => $invoice_id,
            'duplicate_flag' => $duplicate_flag,
            'duplicate_flag_reason' => $dup_reason,
            'patient_name'   => $patient_row ? $patient_row->name : '',
            'grand_total'    => $grand_total,
            'deposit_amount' => $deposit_amount,
            'balance_due'    => $balance_due,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RECORD DEPOSIT — called after order created from the orders page
    // Accepts partial payment (deposit).  Balance collected at fitting.
    // ═══════════════════════════════════════════════════════════════════════
    public static function ajax_record_order_deposit() {
        check_ajax_referer( 'hm_nonce', 'nonce' );

        $order_id       = intval( $_POST['order_id'] ?? 0 );
        $deposit_amount = floatval( $_POST['deposit_amount'] ?? 0 );
        $deposit_method = sanitize_text_field( $_POST['deposit_method'] ?? 'Card' );
        $deposit_date   = sanitize_text_field( $_POST['deposit_paid_at'] ?? date( 'Y-m-d' ) );

        if ( ! $order_id )          wp_send_json_error( 'Missing order.' );
        if ( $deposit_amount <= 0 ) wp_send_json_error( 'No deposit amount.' );
        if ( $deposit_amount < 0 )  wp_send_json_error( 'Deposit cannot be negative.' );

        $db   = HearMed_DB::instance();
        $user = HearMed_Auth::current_user();

        $order = $db->get_row(
            "SELECT id, patient_id, grand_total, clinic_id, order_number
             FROM hearmed_core.orders WHERE id = \$1",
            [ $order_id ]
        );
        if ( ! $order ) wp_send_json_error( 'Order not found.' );

        if ( $deposit_amount > floatval( $order->grand_total ) + 0.01 ) {
            wp_send_json_error( 'Deposit cannot exceed order total (€' . number_format( $order->grand_total, 2 ) . ').' );
        }

        // Update order with deposit info
        $db->update( 'hearmed_core.orders', [
            'deposit_amount'  => $deposit_amount,
            'deposit_method'  => $deposit_method,
            'deposit_paid_at' => $deposit_date,
        ], [ 'id' => $order_id ] );

        // Record in payments table
        $db->insert( 'hearmed_core.payments', [
            'invoice_id'     => null,
            'patient_id'     => $order->patient_id,
            'amount'         => $deposit_amount,
            'payment_date'   => $deposit_date,
            'payment_method' => $deposit_method,
            'received_by'    => $user->id ?? null,
            'clinic_id'      => $order->clinic_id,
            'created_by'     => $user->id ?? null,
            'is_refund'      => false,
        ] );

        // Financial transaction
        if ( class_exists( 'HearMed_Finance' ) ) {
            HearMed_Finance::record( 'deposit', $deposit_amount, [
                'patient_id'       => $order->patient_id,
                'order_id'         => $order_id,
                'payment_method'   => $deposit_method,
                'staff_id'         => $user->id ?? null,
                'clinic_id'        => $order->clinic_id,
                'notes'            => 'Deposit recorded against this order',
                'reference'        => $order->order_number,
                'transaction_date' => $deposit_date,
            ] );
        }

        wp_send_json_success( 'Deposit of €' . number_format( $deposit_amount, 2 ) . ' recorded.' );
    }

    public static function ajax_approve_order() {
        check_ajax_referer('hm_nonce','nonce');

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
        check_ajax_referer('hm_nonce','nonce');

        $order_id = intval($_POST['order_id'] ?? 0);
        $note     = sanitize_textarea_field($_POST['note'] ?? '');
        $user     = HearMed_Auth::current_user();
        $db       = HearMed_DB::instance();

        $order = $db->get_row("SELECT staff_id FROM hearmed_core.orders WHERE id = \$1", [$order_id]);
        $db->update('hearmed_core.orders', [
            'current_status' => 'Cancelled',
            'approval_note'  => $note,
        ], ['id' => $order_id]);

        self::return_cancelled_order_to_stock( $order_id, 'Rejected before fitting: ' . $note, $user->id ?? null );

        self::log_status_change($order_id, 'Awaiting Approval', 'Cancelled', $user->id ?? null, 'Rejected: '.$note);
        self::notify_user($order->staff_id ?? null, 'order_rejected', $order_id, ['note' => $note]);

        wp_send_json_success('Order rejected. Dispenser notified.');
    }

    public static function ajax_mark_ordered() {
        check_ajax_referer('hm_nonce','nonce');

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
        check_ajax_referer('hm_nonce','nonce');

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
        check_ajax_referer('hm_nonce','nonce');
        self::ensure_tracking_schema();

        $order_id = intval($_POST['order_id'] ?? 0);
        $items    = $_POST['items'] ?? [];
        $user     = HearMed_Auth::current_user();
        $db       = HearMed_DB::instance();

        $order = $db->get_row(
            "SELECT patient_id FROM hearmed_core.orders WHERE id = \$1", [$order_id]
        );

        // Validate that every HA product item has at least one serial
        $ha_items = $db->get_results(
            "SELECT oi.id, oi.item_id AS product_id, oi.ear_side
             FROM hearmed_core.order_items oi
             WHERE oi.order_id = \$1 AND oi.item_type = 'product'
             ORDER BY oi.line_number", [$order_id]
        );

        if (!empty($ha_items)) {
            foreach ($ha_items as $ha) {
                $item_key = null;
                foreach ($items as $k => $d) {
                    if (intval($d['product_id'] ?? 0) === intval($ha->product_id)) { $item_key = $k; break; }
                }
                $left  = sanitize_text_field($items[$item_key]['left']  ?? '');
                $right = sanitize_text_field($items[$item_key]['right'] ?? '');
                $need_left  = in_array($ha->ear_side, ['Left','Binaural']);
                $need_right = in_array($ha->ear_side, ['Right','Binaural']);
                if (!$need_left && !$need_right) { $need_left = true; $need_right = true; }
                if (($need_left && !$left) || ($need_right && !$right)) {
                    wp_send_json_error('Serial numbers are required for all hearing aid products. Please fill in every field.');
                }
            }
        }

        // Save serials into patient_devices (not order_items)
        foreach ($items as $item_id => $data) {
            $product_id  = intval($data['product_id'] ?? 0);
            $serial_left  = sanitize_text_field($data['left']  ?? '');
            $serial_right = sanitize_text_field($data['right'] ?? '');

            if ($product_id && ($serial_left || $serial_right)) {
                $coverage = self::build_device_coverage_dates( $product_id, date( 'Y-m-d' ) );
                $db->insert('hearmed_core.patient_devices', [
                    'patient_id'          => $order->patient_id,
                    'product_id'          => $product_id,
                    'order_id'            => $order_id,
                    'serial_number_left'  => $serial_left  ?: null,
                    'serial_number_right' => $serial_right ?: null,
                    'warranty_start_date' => $coverage['warranty_start_date'],
                    'warranty_expiry'     => $coverage['warranty_expiry'],
                    'return_guarantee_until' => $coverage['return_guarantee_until'],
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
        check_ajax_referer('hm_nonce','nonce');
        self::ensure_tracking_schema();

        $order_id = intval($_POST['order_id'] ?? 0);
        $fit_date = sanitize_text_field($_POST['fit_date'] ?? date('Y-m-d'));
        $amount   = floatval($_POST['amount'] ?? 0);
        $notes    = sanitize_textarea_field($_POST['notes'] ?? '');
        $payment_method      = sanitize_text_field($_POST['payment_method'] ?? '');
        $split_payments_json = stripslashes($_POST['split_payments_json'] ?? '');
        $apply_credit        = ! empty( $_POST['apply_credit'] );
        $credit_apply_amount = floatval( $_POST['credit_apply_amount'] ?? 0 );

        if (!$order_id) wp_send_json_error('Invalid order.');
        if ( ! $amount && ! ( $apply_credit && $credit_apply_amount > 0 ) )
            wp_send_json_error('Please enter the amount received.');

        $db   = HearMed_DB::instance();
        $user = HearMed_Auth::current_user();

        $order = $db->get_row(
            "SELECT o.*, p.first_name, p.last_name
             FROM hearmed_core.orders o
             JOIN hearmed_core.patients p ON p.id = o.patient_id
             WHERE o.id = \$1", [$order_id]
        );
        if (!$order) wp_send_json_error('Order not found.');

        // Guardrail: never allow collecting more cash/card than outstanding.
        $payment_total = $amount;
        $split_payments = json_decode( $split_payments_json, true );
        $has_split = ! empty( $split_payments ) && is_array( $split_payments ) && count( $split_payments ) > 1;
        if ( $has_split ) {
            $payment_total = 0;
            foreach ( $split_payments as $sp ) {
                $payment_total += max( 0, floatval( $sp['amount'] ?? 0 ) );
            }
        }

        $deposit_paid = floatval( $order->deposit_amount ?? 0 );
        $base_due = max( 0, floatval( $order->grand_total ?? 0 ) - $deposit_paid );
        $expected_credit = 0;
        if ( $apply_credit && $credit_apply_amount > 0 && class_exists( 'HearMed_Finance' ) ) {
            $credit_balance = HearMed_Finance::get_patient_credit_balance( (int) $order->patient_id );
            $expected_credit = min( $credit_apply_amount, $credit_balance, $base_due );
        }
        $max_cash_due = max( 0, $base_due - $expected_credit );
        if ( $payment_total > $max_cash_due + 0.009 ) {
            wp_send_json_error(
                'Amount exceeds outstanding balance. Outstanding: €' . number_format( $max_cash_due, 2 )
                . ', entered: €' . number_format( $payment_total, 2 ) . '.'
            );
        }

        // ── Save inline serials submitted from the complete form ──
        $inline_serials = json_decode(stripslashes($_POST['inline_serials'] ?? ''), true);
        if (!empty($inline_serials) && is_array($inline_serials)) {
            foreach ($inline_serials as $s) {
                $pid   = intval($s['product_id'] ?? 0);
                $left  = sanitize_text_field($s['left']  ?? '');
                $right = sanitize_text_field($s['right'] ?? '');
                if ($pid && ($left || $right)) {
                    $coverage = self::build_device_coverage_dates( $pid, $fit_date );
                    $db->insert('hearmed_core.patient_devices', [
                        'patient_id'          => $order->patient_id,
                        'product_id'          => $pid,
                        'order_id'            => $order_id,
                        'serial_number_left'  => $left  ?: null,
                        'serial_number_right' => $right ?: null,
                        'warranty_start_date' => $coverage['warranty_start_date'],
                        'warranty_expiry'     => $coverage['warranty_expiry'],
                        'return_guarantee_until' => $coverage['return_guarantee_until'],
                        'device_status'       => 'Active',
                        'created_by'          => $user->id ?? null,
                    ]);
                }
            }
        }

        // ── Server-side gate: every HA product must have a serial by now ──
        $still_missing = $db->get_results(
            "SELECT oi.id
             FROM hearmed_core.order_items oi
             WHERE oi.order_id = \$1 AND oi.item_type = 'product'
               AND NOT EXISTS (
                   SELECT 1 FROM hearmed_core.patient_devices pd
                   WHERE pd.patient_id = \$2
                     AND pd.product_id = oi.item_id
                     AND (pd.serial_number_left IS NOT NULL OR pd.serial_number_right IS NOT NULL)
               )",
            [$order_id, $order->patient_id]
        );
        if (!empty($still_missing)) {
            wp_send_json_error('Serial numbers are missing for one or more hearing aids. Please enter them before finalising.');
        }

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

        // 4. Calculate deposit already paid vs balance due at fitting
        $balance_paid = $amount;  // amount entered by dispenser at fitting

        // Resolve payment method (use form selection, fallback to order default)
        $allowed_methods = ['Card','Cash','Bank Transfer','Cheque','PRSI'];
        if ( ! $payment_method || ! in_array( $payment_method, $allowed_methods ) ) {
            $payment_method = $order->payment_method ?? 'Card';
        }

        // Parse split payments if provided

        // 5. Create proper invoice with VAT breakdown (captures invoice_id)
        $fitting_invoice_id = null;
        if ( $has_split ) {
            // For split payments, record first payment via create_from_order
            $first = $split_payments[0];
            $payment_data = [
                'amount'         => floatval( $first['amount'] ?? 0 ),
                'payment_date'   => $fit_date,
                'payment_method' => in_array( $first['method'] ?? '', $allowed_methods ) ? $first['method'] : $payment_method,
                'received_by'    => $user->id ?? null,
            ];
            if ( class_exists( 'HearMed_Invoice' ) ) {
                $fitting_invoice_id = HearMed_Invoice::create_from_order( $order_id, $payment_data );
            }
            // Record additional split payments
            if ( $fitting_invoice_id ) {
                for ( $i = 1; $i < count( $split_payments ); $i++ ) {
                    $sp = $split_payments[$i];
                    $sp_amount = floatval( $sp['amount'] ?? 0 );
                    $sp_method = in_array( $sp['method'] ?? '', $allowed_methods ) ? $sp['method'] : 'Card';
                    if ( $sp_amount > 0 ) {
                        $db->insert( 'payments', [
                            'invoice_id'     => $fitting_invoice_id,
                            'patient_id'     => $order->patient_id,
                            'amount'         => $sp_amount,
                            'payment_date'   => $fit_date,
                            'payment_method' => $sp_method,
                            'received_by'    => $user->id ?? null,
                            'clinic_id'      => $order->clinic_id,
                            'created_by'     => $user->id ?? null,
                        ] );
                    }
                }
                // Recalculate invoice balance after all split payments
                $inv_row = $db->get_row(
                    "SELECT grand_total FROM hearmed_core.invoices WHERE id = \$1",
                    [ $fitting_invoice_id ]
                );
                $total_paid_now = (float) $db->get_var(
                    "SELECT COALESCE(SUM(amount), 0) FROM hearmed_core.payments WHERE invoice_id = \$1 AND is_refund = false",
                    [ $fitting_invoice_id ]
                );
                $credit_on_inv = (float) $db->get_var(
                    "SELECT COALESCE(credit_applied, 0) FROM hearmed_core.invoices WHERE id = \$1",
                    [ $fitting_invoice_id ]
                );
                $new_bal = max( 0, (float) $inv_row->grand_total - $total_paid_now - $credit_on_inv );
                $db->update( 'invoices', [
                    'payment_status'    => $new_bal <= 0.009 ? 'Paid' : 'Partial',
                    'balance_remaining' => round( $new_bal, 2 ),
                    'updated_at'        => date( 'Y-m-d H:i:s' ),
                ], [ 'id' => $fitting_invoice_id ] );
            }
        } else {
            // Single payment method
            $payment_data = [
                'amount'         => $balance_paid,
                'payment_date'   => $fit_date,
                'payment_method' => $payment_method,
                'received_by'    => $user->id ?? null,
            ];
            if ( class_exists( 'HearMed_Invoice' ) ) {
                $fitting_invoice_id = HearMed_Invoice::create_from_order( $order_id, $payment_data );
            }
        }

        $effective_invoice_id = $fitting_invoice_id ?: ( $order->invoice_id ?: null );

        // Record per-ear PRSI entitlement consumption for auditable 4-year tracking.
        self::record_prsi_entitlement_usage( $order, $effective_invoice_id, $user->id ?? null, $fit_date );

        // Ensure deposit is attached to this invoice once (deposit is order-specific, not reusable credit).
        if ( $deposit_paid > 0 && $effective_invoice_id ) {
            $deposit_date = ! empty( $order->deposit_paid_at ) ? substr( (string) $order->deposit_paid_at, 0, 10 ) : $fit_date;
            $existing_deposit_payment = (int) $db->get_var(
                "SELECT id
                   FROM hearmed_core.payments
                  WHERE invoice_id = \$1
                    AND patient_id = \$2
                    AND is_refund = false
                    AND amount = \$3
                    AND payment_date::date = \$4::date
                  ORDER BY id DESC
                  LIMIT 1",
                [ $effective_invoice_id, $order->patient_id, $deposit_paid, $deposit_date ]
            );

            if ( ! $existing_deposit_payment ) {
                $db->insert( 'hearmed_core.payments', [
                    'invoice_id'     => $effective_invoice_id,
                    'patient_id'     => $order->patient_id,
                    'amount'         => $deposit_paid,
                    'payment_date'   => $deposit_date,
                    'payment_method' => $order->deposit_method ?? $order->payment_method ?? 'Card',
                    'received_by'    => $user->id ?? null,
                    'clinic_id'      => $order->clinic_id,
                    'created_by'     => $user->id ?? null,
                    'is_refund'      => false,
                ] );
            }
        }

        // 6. Record fitting payment in financial_transactions
        if ( class_exists( 'HearMed_Finance' ) ) {
            if ( $has_split ) {
                foreach ( $split_payments as $sp ) {
                    $sp_amt = floatval( $sp['amount'] ?? 0 );
                    $sp_meth = in_array( $sp['method'] ?? '', $allowed_methods ) ? $sp['method'] : $payment_method;
                    if ( $sp_amt > 0 ) {
                        HearMed_Finance::record( 'payment', $sp_amt, [
                            'patient_id'       => (int) $order->patient_id,
                            'order_id'         => $order_id,
                            'invoice_id'       => $effective_invoice_id ?: null,
                            'payment_method'   => $sp_meth,
                            'staff_id'         => $user->id ?? null,
                            'clinic_id'        => $order->clinic_id ? (int) $order->clinic_id : null,
                            'notes'            => 'Fitting payment — ' . $sp_meth,
                            'reference'        => $order->order_number ?? '',
                            'transaction_date' => date('Y-m-d'),
                        ] );
                    }
                }
            } elseif ( $balance_paid > 0 ) {
                HearMed_Finance::record( 'payment', $balance_paid, [
                    'patient_id'       => (int) $order->patient_id,
                    'order_id'         => $order_id,
                    'invoice_id'       => $effective_invoice_id ?: null,
                    'payment_method'   => $payment_method,
                    'staff_id'         => $user->id ?? null,
                    'clinic_id'        => $order->clinic_id ? (int) $order->clinic_id : null,
                    'notes'            => 'Fitting payment — balance collected',
                    'reference'        => $order->order_number ?? '',
                    'transaction_date' => date('Y-m-d'),
                ] );
            }
        }

        // 7. Do NOT post deposit again in financial_transactions at fitting.
        // Deposit was already recorded at order creation and is now linked to invoice payments.

        // 8. Log in patient timeline
        $db->insert( 'hearmed_core.patient_timeline', [
            'patient_id'  => $order->patient_id,
            'event_type'  => 'fitting_complete',
            'event_date'  => $fit_date,
            'staff_id'    => $user->id ?? null,
            'description' => 'Hearing aids fitted and paid. Order ' . $order->order_number
                             . '. Amount received: €' . number_format( $balance_paid, 2 )
                             . ( $deposit_paid > 0 ? ' (deposit €' . number_format( $deposit_paid, 2 ) . ' paid earlier)' : '' )
                             . ( $notes ? '. Notes: ' . $notes : '' ),
            'order_id'    => $order_id,
        ] );

        // 9. Status history log
        self::log_status_change( $order_id, 'Awaiting Fitting', 'Complete', $user->id ?? null, 'Fitted and paid' );

        // ── 10. Apply patient credit if requested ─────────────────────────
        $credit_actually_applied = 0;
        if ( $apply_credit && $credit_apply_amount > 0 && $effective_invoice_id && class_exists( 'HearMed_Finance' ) ) {
            $credit_balance = HearMed_Finance::get_patient_credit_balance( (int) $order->patient_id );
            $max_apply = min( $credit_apply_amount, $credit_balance );

            if ( $max_apply > 0 ) {
                $credit_actually_applied = HearMed_Finance::apply_credit(
                    (int) $order->patient_id,
                    $max_apply,
                    (int) $effective_invoice_id,
                    $order_id,
                    $user->id ?? null,
                    $fit_date
                );

                if ( $credit_actually_applied > 0 ) {
                    // Recalculate invoice status/balance from actual totals.
                    $inv_row = $db->get_row(
                        "SELECT grand_total, COALESCE(credit_applied, 0) AS credit_applied
                         FROM hearmed_core.invoices WHERE id = \$1",
                        [ $effective_invoice_id ]
                    );
                    $paid_total = (float) $db->get_var(
                        "SELECT COALESCE(SUM(amount), 0)
                         FROM hearmed_core.payments
                         WHERE invoice_id = \$1 AND is_refund = false",
                        [ $effective_invoice_id ]
                    );
                    $new_balance = max( 0, (float) ($inv_row->grand_total ?? 0) - $paid_total - (float) ($inv_row->credit_applied ?? 0) );
                    $db->update( 'invoices', [
                        'payment_status'    => $new_balance <= 0.009 ? 'Paid' : 'Partial',
                        'balance_remaining' => round( $new_balance, 2 ),
                        'updated_at'        => date( 'Y-m-d H:i:s' ),
                    ], [ 'id' => $effective_invoice_id ] );

                    // Timeline entry
                    $db->insert( 'hearmed_core.patient_timeline', [
                        'patient_id'  => $order->patient_id,
                        'event_type'  => 'credit_applied',
                        'event_date'  => $fit_date,
                        'staff_id'    => $user->id ?? null,
                        'description' => 'Credit of €' . number_format( $credit_actually_applied, 2 )
                            . ' applied to order ' . $order->order_number,
                        'order_id'    => $order_id,
                    ] );
                }
            }
        }

        // Queue invoice for QBO review (weekly batch — do NOT auto-sync)
        // Invoice is created with qbo_sync_status = 'pending_review' by default
        // Rauri reviews and sends to QBO via /qbo-review/ page

        // After completion: open invoice print view and return staff to calendar.
        $redirect  = HearMed_Utils::page_url('calendar');
        $print_url = null;
        if ( $effective_invoice_id ) {
            $print_url = admin_url('admin-ajax.php')
                . '?action=hm_download_invoice&nonce=' . wp_create_nonce('hm_nonce')
                . '&_ID=' . (int) $effective_invoice_id
                . '&auto_print=1';
        }

        wp_send_json_success([
            'message'         => 'Fitting complete. Invoice created and queued for QBO review.'
                . ( $credit_actually_applied > 0 ? ' Credit of €' . number_format( $credit_actually_applied, 2 ) . ' applied.' : '' ),
            'order_id'        => $order_id,
            'invoice_id'      => $effective_invoice_id,
            'credit_applied'  => $credit_actually_applied,
            'print_url'       => $print_url,
            'next_redirect'   => $redirect,
            'redirect'        => $redirect,
        ]);
    }

    public static function ajax_patient_search() {
        check_ajax_referer('hm_nonce','nonce');
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

    private static function ensure_tracking_schema() {
        if ( self::$tracking_schema_ensured ) {
            return;
        }
        self::$tracking_schema_ensured = true;

        $db = HearMed_DB::instance();

        $db->query( "ALTER TABLE hearmed_core.orders ADD COLUMN IF NOT EXISTS prsi_left_source VARCHAR(20)" );
        $db->query( "ALTER TABLE hearmed_core.orders ADD COLUMN IF NOT EXISTS prsi_right_source VARCHAR(20)" );

        $db->query( "ALTER TABLE hearmed_core.patient_devices ADD COLUMN IF NOT EXISTS warranty_start_date DATE" );
        $db->query( "ALTER TABLE hearmed_core.patient_devices ADD COLUMN IF NOT EXISTS return_guarantee_until DATE" );

        $db->query( "ALTER TABLE hearmed_reference.products ADD COLUMN IF NOT EXISTS warranty_months INTEGER" );
        $db->query( "ALTER TABLE hearmed_reference.products ADD COLUMN IF NOT EXISTS return_guarantee_days INTEGER" );
        $db->query( "UPDATE hearmed_reference.products SET return_guarantee_days = 60 WHERE return_guarantee_days IS NULL" );

        $db->query(
            "CREATE TABLE IF NOT EXISTS hearmed_core.prsi_entitlement_usage (
                id BIGSERIAL PRIMARY KEY,
                patient_id BIGINT NOT NULL,
                order_id BIGINT,
                invoice_id BIGINT,
                ear_side VARCHAR(10) NOT NULL,
                amount NUMERIC(10,2) NOT NULL DEFAULT 0,
                source_credit_note_id BIGINT,
                status VARCHAR(20) NOT NULL DEFAULT 'applied',
                applied_by BIGINT,
                applied_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                notes TEXT
            )"
        );

        $db->query(
            "CREATE UNIQUE INDEX IF NOT EXISTS idx_prsi_usage_order_ear_applied
             ON hearmed_core.prsi_entitlement_usage(order_id, ear_side)
             WHERE status = 'applied'"
        );
    }

    private static function prsi_per_ear_amount() {
        return (float) HearMed_Settings::get( 'hm_prsi_amount_per_ear', '500' );
    }

    private static function get_prsi_entitlement_by_ear( $patient_id ) {
        $db = HearMed_DB::instance();

        $issued = $db->get_row(
            "SELECT
                COALESCE(SUM(CASE
                    WHEN LOWER(COALESCE(r.side, 'both')) = 'left' THEN COALESCE(cn.prsi_amount, 0)
                    WHEN LOWER(COALESCE(r.side, 'both')) IN ('both', 'binaural') THEN COALESCE(cn.prsi_amount, 0) / 2.0
                    ELSE 0
                END), 0) AS left_issued,
                COALESCE(SUM(CASE
                    WHEN LOWER(COALESCE(r.side, 'both')) = 'right' THEN COALESCE(cn.prsi_amount, 0)
                    WHEN LOWER(COALESCE(r.side, 'both')) IN ('both', 'binaural') THEN COALESCE(cn.prsi_amount, 0) / 2.0
                    ELSE 0
                END), 0) AS right_issued
             FROM hearmed_core.credit_notes cn
             LEFT JOIN hearmed_core.returns r ON r.credit_note_id = cn.id
             WHERE cn.patient_id = $1
               AND COALESCE(cn.refund_type, '') = 'credit'
               AND COALESCE(cn.prsi_amount, 0) > 0",
            [ (int) $patient_id ]
        );

        $used = $db->get_row(
            "SELECT
                COALESCE(SUM(CASE WHEN ear_side = 'left' THEN amount ELSE 0 END), 0) AS left_used,
                COALESCE(SUM(CASE WHEN ear_side = 'right' THEN amount ELSE 0 END), 0) AS right_used
             FROM hearmed_core.prsi_entitlement_usage
             WHERE patient_id = $1 AND status = 'applied'",
            [ (int) $patient_id ]
        );

        return [
            'left'  => max( 0, (float) ( $issued->left_issued ?? 0 ) - (float) ( $used->left_used ?? 0 ) ),
            'right' => max( 0, (float) ( $issued->right_issued ?? 0 ) - (float) ( $used->right_used ?? 0 ) ),
        ];
    }

    private static function get_prsi_last_new_claim_dates( $patient_id ) {
        $row = HearMed_DB::get_row(
            "SELECT
                MAX(CASE WHEN o.prsi_left IS TRUE
                            AND COALESCE(o.prsi_left_source, 'new_claim') = 'new_claim'
                         THEN COALESCE(o.fitted_at::date, o.order_date)
                    END) AS left_last,
                MAX(CASE WHEN o.prsi_right IS TRUE
                            AND COALESCE(o.prsi_right_source, 'new_claim') = 'new_claim'
                         THEN COALESCE(o.fitted_at::date, o.order_date)
                    END) AS right_last
             FROM hearmed_core.orders o
             WHERE o.patient_id = $1
               AND COALESCE(o.current_status, '') <> 'Cancelled'",
            [ (int) $patient_id ]
        );

        return [
            'left'  => $row->left_last ?? null,
            'right' => $row->right_last ?? null,
        ];
    }

    private static function next_prsi_date( $last_date ) {
        if ( ! $last_date ) {
            return null;
        }
        try {
            $d = new DateTime( $last_date );
            $d->modify( '+4 years' );
            return $d->format( 'Y-m-d' );
        } catch ( \Exception $e ) {
            return null;
        }
    }

    private static function resolve_prsi_sources_for_order( $patient_id, $prsi_left, $prsi_right ) {
        $per_ear = self::prsi_per_ear_amount();
        $pool = self::get_prsi_entitlement_by_ear( $patient_id );
        $claims = self::get_prsi_last_new_claim_dates( $patient_id );

        $sources = [ 'left' => null, 'right' => null ];

        foreach ( [ 'left' => $prsi_left, 'right' => $prsi_right ] as $ear => $selected ) {
            if ( ! $selected ) {
                continue;
            }

            $available_entitlement = (float) ( $pool[ $ear ] ?? 0 );
            if ( $available_entitlement + 0.009 >= $per_ear ) {
                $sources[ $ear ] = 'entitlement';
                $pool[ $ear ] = max( 0, $available_entitlement - $per_ear );
                continue;
            }

            $next_date = self::next_prsi_date( $claims[ $ear ] ?? null );
            if ( $next_date ) {
                $today = new DateTime( 'today' );
                $eligible = new DateTime( $next_date );
                if ( $today < $eligible ) {
                    $label = ucfirst( $ear );
                    wp_send_json_error( $label . ' ear is not PRSI-eligible until ' . $next_date . '. Use available entitlement credit for that ear or remove PRSI for this order.' );
                }
            }

            $sources[ $ear ] = 'new_claim';
        }

        return $sources;
    }

    private static function record_prsi_entitlement_usage( $order, $invoice_id, $staff_id, $fit_date ) {
        $db = HearMed_DB::instance();
        $per_ear = self::prsi_per_ear_amount();

        $ear_map = [
            'left'  => [ in_array( ( $order->prsi_left ?? false ), [ true, 1, '1', 't', 'true' ], true ), (string) ( $order->prsi_left_source ?? '' ) ],
            'right' => [ in_array( ( $order->prsi_right ?? false ), [ true, 1, '1', 't', 'true' ], true ), (string) ( $order->prsi_right_source ?? '' ) ],
        ];

        foreach ( $ear_map as $ear => $cfg ) {
            list( $enabled, $source ) = $cfg;
            if ( ! $enabled || $source !== 'entitlement' ) {
                continue;
            }

            $exists = (int) $db->get_var(
                "SELECT id FROM hearmed_core.prsi_entitlement_usage
                 WHERE order_id = $1 AND ear_side = $2 AND status = 'applied'
                 LIMIT 1",
                [ (int) $order->id, $ear ]
            );
            if ( $exists ) {
                continue;
            }

            $db->insert( 'hearmed_core.prsi_entitlement_usage', [
                'patient_id' => (int) $order->patient_id,
                'order_id'   => (int) $order->id,
                'invoice_id' => $invoice_id ? (int) $invoice_id : null,
                'ear_side'   => $ear,
                'amount'     => $per_ear,
                'applied_by' => $staff_id ?: null,
                'applied_at' => ( $fit_date ?: date( 'Y-m-d' ) ) . ' 00:00:00',
                'status'     => 'applied',
                'notes'      => 'Applied to fitting as PRSI entitlement reuse',
            ] );
        }
    }

    private static function get_product_coverage_terms( $product_id ) {
        $db = HearMed_DB::instance();
        $row = $db->get_row(
            "SELECT p.warranty_months, p.return_guarantee_days,
                    COALESCE(m.warranty_terms, '') AS manufacturer_warranty_terms
             FROM hearmed_reference.products p
             LEFT JOIN hearmed_reference.manufacturers m ON m.id = p.manufacturer_id
             WHERE p.id = $1",
            [ (int) $product_id ]
        );

        $warranty_months = (int) ( $row->warranty_months ?? 0 );
        if ( $warranty_months <= 0 ) {
            $terms = (string) ( $row->manufacturer_warranty_terms ?? '' );
            if ( $terms && preg_match( '/(\d+)\s*month/i', $terms, $mm ) ) {
                $warranty_months = (int) $mm[1];
            } elseif ( $terms && preg_match( '/(\d+)\s*year/i', $terms, $my ) ) {
                $warranty_months = (int) $my[1] * 12;
            }
        }

        $return_days = (int) ( $row->return_guarantee_days ?? 60 );
        if ( $return_days <= 0 ) {
            $return_days = 60;
        }

        return [
            'warranty_months' => $warranty_months,
            'return_days'     => $return_days,
        ];
    }

    private static function build_device_coverage_dates( $product_id, $fit_date ) {
        $terms = self::get_product_coverage_terms( $product_id );
        $fit = $fit_date ?: date( 'Y-m-d' );

        $warranty_start = $fit;
        $warranty_expiry = null;
        $return_until = null;

        try {
            $base = new DateTime( $fit );
            $ret = clone $base;
            $ret->modify( '+' . (int) $terms['return_days'] . ' days' );
            $return_until = $ret->format( 'Y-m-d' );

            $months = (int) $terms['warranty_months'];
            if ( $months > 0 ) {
                $w = clone $base;
                $w->modify( '+' . $months . ' months' );
                $warranty_expiry = $w->format( 'Y-m-d' );
            }
        } catch ( \Exception $e ) {
            // Keep null dates if parsing fails.
        }

        return [
            'warranty_start_date'    => $warranty_start,
            'warranty_expiry'        => $warranty_expiry,
            'return_guarantee_until' => $return_until,
        ];
    }

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
                    manufacturer_id: parseInt(opt.dataset.manufacturerId || '0', 10) || 0,
                    ear: ear.value,
                    speaker: '',
                    charger: false,
                    product_cost: parseFloat(opt.dataset.cost) || 0,
                    charger_cost: 0,
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
                // Prices are VAT-inclusive — extract VAT from gross
                var gross = item.unit_price * item.qty;
                item.vat_amount = item.vat_rate > 0 ? parseFloat((gross - (gross / (1 + item.vat_rate/100))).toFixed(2)) : 0;
                item.line_total = parseFloat(gross.toFixed(2));
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
                        var qGross = items[i].unit_price*items[i].qty;
                        items[i].vat_amount = items[i].vat_rate > 0 ? parseFloat((qGross - (qGross / (1 + items[i].vat_rate/100))).toFixed(2)) : 0;
                        items[i].line_total = parseFloat(qGross.toFixed(2));
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
                let net=0, vat=0, grossSum=0;
                items.forEach(item=>{ var g=item.unit_price*item.qty; grossSum+=g; vat+=item.vat_amount; net+=g-item.vat_amount; });
                const prsi = (document.getElementById('prsi_left').checked?500:0)
                           + (document.getElementById('prsi_right').checked?500:0);
                const total = Math.max(0, grossSum-prsi);
                document.getElementById('hm-subtotal').textContent       = '€'+net.toFixed(2);
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
        $from_appt  = intval( $_GET['from_appointment'] ?? 0 );
        $is_quickpay = ( $_GET['quickpay'] ?? '' ) === '1';

        $out = '';

        // ── Quickpay / from-appointment banner ──
        if ( $is_quickpay || $from_appt ) {
            $banner_text = $is_quickpay
                ? 'Service Invoice — add services and submit. Approval is automatic for service-only orders.'
                : 'Order linked to appointment #' . $from_appt . '.';
            $banner_bg   = $is_quickpay ? '#dbeafe' : '#f0fdfe';
            $banner_fg   = $is_quickpay ? '#1e40af' : '#0e7490';
            $out .= '<div id="hm-prefill-banner" style="padding:10px 16px;background:' . $banner_bg . ';border:1px solid ' . $banner_fg . '30;border-radius:8px;margin-bottom:16px;font-size:13px;font-weight:600;color:' . $banner_fg . ';">' . esc_html( $banner_text ) . '</div>';
        }

        if ( ! $pid ) return $out;

        $db = HearMed_DB::instance();
        $p  = $db->get_row(
            "SELECT id, first_name, last_name, patient_number
             FROM hearmed_core.patients WHERE id = \$1",
            [ $pid ]
        );
        if ( ! $p ) return $out;

        $data = json_encode([
            'id'    => (int) $p->id,
            'label' => trim( $p->first_name . ' ' . $p->last_name )
                       . ( $p->patient_number ? ' (' . $p->patient_number . ')' : '' ),
        ]);

        $out .= '<script>
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
                // Re-validate form since patient is now set
                var submitBtn = document.getElementById("hm-submit-order");
                if (submitBtn) submitBtn.disabled = false;
            }
        })();
        </script>';

        return $out;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AJAX: Load orders list (for hearmed-orders.js Order Status page)
    // ═══════════════════════════════════════════════════════════════════════
    public static function ajax_get_orders() {
        check_ajax_referer( 'hm_nonce', 'nonce' );

        $db     = HearMed_DB::instance();
        $clinic = HearMed_Auth::current_clinic();
        $status = sanitize_text_field( $_POST['status'] ?? '' );
        $search = sanitize_text_field( $_POST['search'] ?? '' );
        $paged  = max( 1, intval( $_POST['paged'] ?? 1 ) );
        $per    = 25;
        $offset = ( $paged - 1 ) * $per;

        $where = [];
        $params = [];
        $i = 1;

        if ( $clinic ) {
            $where[] = "o.clinic_id = \${$i}"; $params[] = $clinic; $i++;
        }
        if ( $status && $status !== 'all' ) {
            $where[] = "o.current_status = \${$i}"; $params[] = $status; $i++;
        }
        if ( $search ) {
            $where[] = "(p.first_name ILIKE \${$i} OR p.last_name ILIKE \${$i} OR o.order_number ILIKE \${$i} OR p.patient_number ILIKE \${$i})";
            $params[] = '%' . $search . '%'; $i++;
        }

        $wc = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

        // Count
        $count_row = $db->get_row(
            "SELECT COUNT(*) AS cnt FROM hearmed_core.orders o
             JOIN hearmed_core.patients p ON p.id = o.patient_id
             {$wc}", $params
        );
        $total = (int) ( $count_row->cnt ?? 0 );

        // Fetch page
        $params_page = $params;
        $params_page[] = $per;    $li = $i++;
        $params_page[] = $offset; $oi = $i++;

        $rows = $db->get_results(
            "SELECT o.id, o.order_number, o.current_status AS status,
                    o.grand_total, o.prsi_applicable, o.prsi_amount,
                    o.created_at, o.is_flagged AS duplicate_flag,
                    p.first_name, p.last_name, p.patient_number,
                    CONCAT(s.first_name,' ',s.last_name) AS dispenser_name
             FROM hearmed_core.orders o
             JOIN hearmed_core.patients p ON p.id = o.patient_id
             LEFT JOIN hearmed_reference.staff s ON s.id = o.staff_id
             {$wc}
             ORDER BY o.created_at DESC
             LIMIT \${$li} OFFSET \${$oi}",
            $params_page
        ) ?: [];

        $order_ids = array_map( function( $r ) { return (int) $r->id; }, $rows );

        // Product summaries in one query
        $summaries = [];
        if ( $order_ids ) {
            $ph = [];
            $sp = [];
            foreach ( $order_ids as $idx => $oid ) {
                $sp[] = $oid;
                $ph[] = '$' . ( $idx + 1 );
            }
            $items = $db->get_results(
                "SELECT order_id, item_description FROM hearmed_core.order_items
                 WHERE order_id IN (" . implode( ',', $ph ) . ")
                 ORDER BY order_id, line_number",
                $sp
            ) ?: [];
            foreach ( $items as $it ) {
                $summaries[ (int) $it->order_id ][] = $it->item_description;
            }
        }

        $orders = [];
        foreach ( $rows as $r ) {
            $descs = $summaries[ (int) $r->id ] ?? [];
            $orders[] = [
                'id'              => (int) $r->id,
                'order_number'    => $r->order_number,
                'patient_name'    => trim( $r->first_name . ' ' . $r->last_name ),
                'patient_number'  => $r->patient_number ?? '',
                'product_summary' => implode( ', ', $descs ),
                'grand_total'     => (float) $r->grand_total,
                'prsi_applicable' => (bool) $r->prsi_applicable,
                'prsi_amount'     => (float) ( $r->prsi_amount ?? 0 ),
                'status'          => $r->status,
                'created_at'      => $r->created_at,
                'duplicate_flag'  => (bool) $r->duplicate_flag,
            ];
        }

        wp_send_json_success( [ 'orders' => $orders, 'total' => $total, 'per_page' => $per ] );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AJAX: Get single order detail (for order detail modal)
    // ═══════════════════════════════════════════════════════════════════════
    public static function ajax_get_order_detail() {
        check_ajax_referer( 'hm_nonce', 'nonce' );

        $order_id = intval( $_POST['order_id'] ?? 0 );
        if ( ! $order_id ) wp_send_json_error( [ 'msg' => 'Missing order ID.' ] );

        $db = HearMed_DB::instance();

        $o = $db->get_row(
            "SELECT o.*,
                    p.first_name AS p_first, p.last_name AS p_last, p.patient_number,
                    c.clinic_name,
                    CONCAT(s.first_name,' ',s.last_name) AS dispenser_name
             FROM hearmed_core.orders o
             JOIN hearmed_core.patients p ON p.id = o.patient_id
             JOIN hearmed_reference.clinics c ON c.id = o.clinic_id
             LEFT JOIN hearmed_reference.staff s ON s.id = o.staff_id
             WHERE o.id = \$1",
            [ $order_id ]
        );
        if ( ! $o ) wp_send_json_error( [ 'msg' => 'Order not found.' ] );

        $items = $db->get_results(
            "SELECT oi.item_description, oi.ear_side, oi.quantity,
                    oi.unit_retail_price, oi.vat_amount, oi.line_total,
                    COALESCE(oi.unit_cost_price, 0) AS cost_price,
                    oi.unit_retail_price - oi.line_total AS discount_amount
             FROM hearmed_core.order_items oi
             WHERE oi.order_id = \$1
             ORDER BY oi.line_number",
            [ $order_id ]
        ) ?: [];

        $line_items  = [];
        $cost_total  = 0;
        $retail_total = 0;
        foreach ( $items as $it ) {
            $qty          = (int) ( $it->quantity ?? 1 );
            $unit_price   = (float) $it->unit_retail_price;
            $line_total   = (float) $it->line_total;
            $discount     = max( 0, ( $unit_price * $qty ) - $line_total + (float) $it->vat_amount );
            $cost_total  += (float) $it->cost_price * $qty;
            $retail_total += $unit_price * $qty;

            $line_items[] = [
                'product_name' => $it->item_description,
                'ear'          => $it->ear_side ?? '',
                'qty'          => $qty,
                'unit_price'   => $unit_price,
                'discount'     => round( $discount, 2 ),
                'line_total'   => $line_total,
            ];
        }

        $margin = $retail_total > 0
            ? round( ( ( $retail_total - $cost_total ) / $retail_total ) * 100, 1 )
            : null;

        wp_send_json_success( [
            'id'                    => (int) $o->id,
            'order_number'          => $o->order_number,
            'status'                => $o->current_status,
            'patient_name'          => trim( $o->p_first . ' ' . $o->p_last ),
            'patient_number'        => $o->patient_number ?? '',
            'clinic_name'           => $o->clinic_name ?? '',
            'dispenser_name'        => $o->dispenser_name ?? '',
            'created_at'            => $o->created_at,
            'line_items'            => $line_items,
            'subtotal'              => (float) ( $o->subtotal ?? 0 ),
            'discount'              => (float) ( $o->discount_total ?? 0 ),
            'vat_total'             => (float) ( $o->vat_total ?? 0 ),
            'prsi_applicable'       => (bool) $o->prsi_applicable,
            'prsi_amount'           => (float) ( $o->prsi_amount ?? 0 ),
            'grand_total'           => (float) ( $o->grand_total ?? 0 ),
            'gross_margin_percent'  => $margin,
            'notes'                 => $o->notes ?? '',
            'duplicate_flag'        => (bool) ( $o->is_flagged ?? false ),
            'duplicate_flag_reason' => $o->flag_reason ?? '',
        ] );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AJAX: Update order status (dispatcher for Mark Ordered / Received)
    // ═══════════════════════════════════════════════════════════════════════
    public static function ajax_update_order_status() {
        check_ajax_referer( 'hm_nonce', 'nonce' );

        $order_id   = intval( $_POST['order_id'] ?? 0 );
        $new_status = sanitize_text_field( $_POST['new_status'] ?? '' );
        $user       = HearMed_Auth::current_user();
        $db         = HearMed_DB::instance();

        if ( ! $order_id || ! in_array( $new_status, [ 'Ordered', 'Received' ], true ) ) {
            wp_send_json_error( [ 'msg' => 'Invalid request.' ] );
        }

        $order = $db->get_row(
            "SELECT o.current_status, o.staff_id, o.order_number
             FROM hearmed_core.orders o WHERE o.id = \$1",
            [ $order_id ]
        );
        if ( ! $order ) wp_send_json_error( [ 'msg' => 'Order not found.' ] );

        if ( $new_status === 'Ordered' ) {
            $db->update( 'hearmed_core.orders', [
                'current_status' => 'Ordered',
                'ordered_at'     => date( 'Y-m-d H:i:s' ),
            ], [ 'id' => $order_id ] );
            self::log_status_change( $order_id, $order->current_status, 'Ordered', $user->id ?? null, 'Order placed with supplier' );
            wp_send_json_success( 'Marked as ordered.' );

        } elseif ( $new_status === 'Received' ) {
            $db->update( 'hearmed_core.orders', [
                'current_status' => 'Received',
                'arrived_at'     => date( 'Y-m-d H:i:s' ),
            ], [ 'id' => $order_id ] );
            self::log_status_change( $order_id, $order->current_status, 'Received', $user->id ?? null, 'Aids arrived in clinic' );
            self::notify_user( $order->staff_id ?? null, 'order_arrived', $order_id, [
                'order_number' => $order->order_number,
            ] );
            wp_send_json_success( 'Marked as received.' );
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AJAX: Get pending orders for approval queue
    // ═══════════════════════════════════════════════════════════════════════
    public static function ajax_get_pending_orders() {
        check_ajax_referer( 'hm_nonce', 'nonce' );

        $db = HearMed_DB::instance();

        $orders = $db->get_results(
            "SELECT o.*,
                    p.first_name AS p_first, p.last_name AS p_last,
                    p.patient_number,
                    c.clinic_name,
                    CONCAT(s.first_name,' ',s.last_name) AS dispenser_name
             FROM hearmed_core.orders o
             JOIN hearmed_core.patients p ON p.id = o.patient_id
             JOIN hearmed_reference.clinics c ON c.id = o.clinic_id
             LEFT JOIN hearmed_reference.staff s ON s.id = o.staff_id
             WHERE o.current_status = 'Awaiting Approval'
             ORDER BY o.created_at ASC"
        ) ?: [];

        $result = [];
        foreach ( $orders as $o ) {
            $items = $db->get_results(
                "SELECT oi.*,
                        pr.product_name, pr.style, pr.tech_level,
                        COALESCE(pr.cost_price, 0) AS product_cost,
                        m.name AS manufacturer_name
                 FROM hearmed_core.order_items oi
                 LEFT JOIN hearmed_reference.products pr ON pr.id = oi.item_id AND oi.item_type = 'product'
                 LEFT JOIN hearmed_reference.manufacturers m ON m.id = pr.manufacturer_id
                 WHERE oi.order_id = \$1
                 ORDER BY oi.line_number",
                [ $o->id ]
            ) ?: [];

            $line_items   = [];
            $cost_total   = 0;
            $retail_total = 0;
            foreach ( $items as $it ) {
                $qty          = (int) ( $it->quantity ?? 1 );
                $unit_price   = (float) ( $it->unit_retail_price ?? 0 );
                $unit_cost    = (float) ( $it->unit_cost_price ?? $it->product_cost ?? 0 );
                $line_total   = (float) ( $it->line_total ?? 0 );
                $discount     = max( 0, ( $unit_price * $qty ) - $line_total + (float) ( $it->vat_amount ?? 0 ) );
                $cost_total  += $unit_cost * $qty;
                $retail_total += $unit_price * $qty;

                $line_items[] = [
                    'product_name' => $it->item_description ?: ( $it->product_name ?? '' ),
                    'manufacturer' => $it->manufacturer_name ?? '',
                    'style'        => $it->style ?? '',
                    'range'        => $it->tech_level ?? '',
                    'ear'          => $it->ear_side ?? '',
                    'qty'          => $qty,
                    'unit_price'   => $unit_price,
                    'cost_price'   => $unit_cost,
                    'discount'     => round( $discount, 2 ),
                    'vat_rate'     => (float) ( $it->vat_rate ?? 0 ),
                    'line_total'   => $line_total,
                ];
            }

            $margin = $retail_total > 0
                ? ( ( $retail_total - $cost_total ) / $retail_total ) * 100
                : 0;

            // Flags
            $flags = [];
            if ( $margin < 15 ) {
                $flags[] = [ 'level' => 'red', 'msg' => 'Low margin: ' . number_format( $margin, 1 ) . '%' ];
            } elseif ( $margin < 25 ) {
                $flags[] = [ 'level' => 'amber', 'msg' => 'Margin: ' . number_format( $margin, 1 ) . '%' ];
            }

            // Duplicate check
            $product_ids = [];
            foreach ( $items as $it ) {
                if ( $it->item_type === 'product' && ! empty( $it->item_id ) ) {
                    $product_ids[] = (int) $it->item_id;
                }
            }
            $product_ids = array_values( array_unique( $product_ids ) );
            if ( ! empty( $product_ids ) ) {
                $dp = [ (int) $o->patient_id, (int) $o->id ];
                $ph = [];
                foreach ( $product_ids as $idx => $pid ) { $dp[] = $pid; $ph[] = '$' . ( $idx + 3 ); }
                $dup = $db->get_row(
                    "SELECT o2.order_number FROM hearmed_core.orders o2
                     JOIN hearmed_core.order_items oi2 ON oi2.order_id = o2.id
                     WHERE o2.patient_id = \$1 AND o2.id <> \$2
                       AND o2.current_status <> 'Cancelled'
                       AND o2.created_at > NOW() - INTERVAL '90 days'
                       AND oi2.item_type = 'product'
                       AND oi2.item_id IN (" . implode( ',', $ph ) . ")
                     LIMIT 1", $dp
                );
                if ( $dup ) {
                    $flags[] = [ 'level' => 'red', 'msg' => 'Possible duplicate — see ' . $dup->order_number ];
                }
            }

            $result[] = [
                'id'                   => (int) $o->id,
                'order_number'         => $o->order_number,
                'patient_name'         => trim( $o->p_first . ' ' . $o->p_last ),
                'patient_number'       => $o->patient_number ?? '',
                'dispenser_name'       => $o->dispenser_name ?? '',
                'clinic_name'          => $o->clinic_name ?? '',
                'created_at'           => $o->created_at,
                'prsi_applicable'      => (bool) $o->prsi_applicable,
                'prsi_amount'          => (float) ( $o->prsi_amount ?? 0 ),
                'subtotal'             => (float) ( $o->subtotal ?? 0 ),
                'discount'             => (float) ( $o->discount_total ?? 0 ),
                'vat_total'            => (float) ( $o->vat_total ?? 0 ),
                'grand_total'          => (float) ( $o->grand_total ?? 0 ),
                'gross_margin_percent' => round( $margin, 1 ),
                'notes'                => $o->notes ?? '',
                'line_items'           => $line_items,
                'flags'                => $flags,
            ];
        }

        wp_send_json_success( $result );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AJAX: Get awaiting fitting queue
    // ═══════════════════════════════════════════════════════════════════════
    private static function can_manage_awaiting_fitting_actions() : bool {
        $role = HearMed_Auth::current_role();
        return in_array( $role, [ 'finance', 'c_level' ], true );
    }

    private static function has_prefit_cancellation_queue_table() : bool {
        $db = HearMed_DB::instance();
        return (bool) $db->get_var(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = 'hearmed_core' AND table_name = 'prefit_cancellation_queue'"
        );
    }

    private static function queue_prefit_cancellation( int $order_id, int $patient_id, ?int $invoice_id, ?int $cancelled_by, string $reason, float $paid_amount, float $refund_due ) : void {
        $db = HearMed_DB::instance();
        $now = date( 'Y-m-d H:i:s' );
        $credit_required = $refund_due > 0 ? 'true' : 'false';
        $refund_status = $refund_due > 0 ? 'pending_review' : 'not_required';

        $db->query(
            "INSERT INTO hearmed_core.prefit_cancellation_queue
                (order_id, patient_id, invoice_id, cancelled_by, cancellation_reason, cancelled_at, paid_amount, refund_due, credit_note_required, refund_status, created_at, updated_at)
             VALUES
                (\$1, \$2, \$3, \$4, \$5, \$6, \$7, \$8, {$credit_required}, \$9, \$6, \$6)
             ON CONFLICT (order_id)
             DO UPDATE SET
                cancellation_reason = EXCLUDED.cancellation_reason,
                cancelled_by        = EXCLUDED.cancelled_by,
                cancelled_at        = EXCLUDED.cancelled_at,
                paid_amount         = EXCLUDED.paid_amount,
                refund_due          = EXCLUDED.refund_due,
                credit_note_required= EXCLUDED.credit_note_required,
                refund_status       = EXCLUDED.refund_status,
                updated_at          = EXCLUDED.updated_at",
            [
                $order_id,
                $patient_id,
                $invoice_id,
                $cancelled_by,
                $reason,
                $now,
                $paid_amount,
                $refund_due,
                $refund_status,
            ]
        );
    }

    public static function ajax_get_awaiting_fitting() {
        check_ajax_referer( 'hm_nonce', 'nonce' );

        $db        = HearMed_DB::instance();
        $clinic_id = intval( $_POST['clinic_id'] ?? 0 );
        $date_from = sanitize_text_field( $_POST['date_from'] ?? '' );
        $date_to   = sanitize_text_field( $_POST['date_to'] ?? '' );

        $where  = [ "o.current_status = 'Awaiting Fitting'" ];
        $params = [];
        $i      = 1;

        if ( $clinic_id ) {
            $where[] = "o.clinic_id = \${$i}"; $params[] = $clinic_id; $i++;
        }
        if ( $date_from ) {
            $where[] = "o.created_at >= \${$i}"; $params[] = $date_from; $i++;
        }
        if ( $date_to ) {
            $where[] = "o.created_at <= \${$i}::date + interval '1 day'"; $params[] = $date_to; $i++;
        }

        $wc = 'WHERE ' . implode( ' AND ', $where );

        $rows = $db->get_results(
            "SELECT o.id AS order_id, o.order_number, o.grand_total AS total_price,
                    o.prsi_applicable, o.prsi_amount, o.created_at, o.notes,
                    p.first_name || ' ' || p.last_name AS patient_name,
                    p.patient_number,
                    c.clinic_name,
                    CONCAT(s.first_name,' ',s.last_name) AS dispenser_name,
                    fq.fitting_date,
                    (SELECT string_agg(oi.item_description, ', ' ORDER BY oi.line_number)
                     FROM hearmed_core.order_items oi WHERE oi.order_id = o.id) AS product_description
             FROM hearmed_core.orders o
             JOIN hearmed_core.patients p ON p.id = o.patient_id
             JOIN hearmed_reference.clinics c ON c.id = o.clinic_id
             LEFT JOIN hearmed_reference.staff s ON s.id = o.staff_id
             LEFT JOIN hearmed_core.fitting_queue fq ON fq.order_id = o.id
             {$wc}
             ORDER BY fq.fitting_date ASC NULLS LAST, o.created_at DESC",
            $params
        ) ?: [];

        $can_manage = self::can_manage_awaiting_fitting_actions();
        $data = [];
        foreach ( $rows as $r ) {
            $data[] = [
                'order_id'            => (int) $r->order_id,
                'order_number'        => $r->order_number ?? '',
                'patient_name'        => $r->patient_name ?? '',
                'patient_number'      => $r->patient_number ?? '',
                'clinic_name'         => $r->clinic_name ?? '',
                'dispenser_name'      => $r->dispenser_name ?? '',
                'product_description' => $r->product_description ?? '',
                'total_price'         => (float) ( $r->total_price ?? 0 ),
                'prsi_applicable'     => (bool) $r->prsi_applicable,
                'prsi_amount'         => (float) ( $r->prsi_amount ?? 0 ),
                'fitting_date'        => $r->fitting_date,
                'order_notes'         => $r->notes ?? '',
                'can_manage'          => $can_manage,
            ];
        }

        wp_send_json_success( $data );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AJAX: Edit order from Awaiting Fitting queue (Finance/C-Level only)
    // ═══════════════════════════════════════════════════════════════════════
    public static function ajax_update_awaiting_fitting_order() {
        check_ajax_referer( 'hm_nonce', 'nonce' );

        if ( ! self::can_manage_awaiting_fitting_actions() ) {
            wp_send_json_error( [ 'msg' => 'Only Finance and C-Level can edit Awaiting Fitting orders.' ] );
        }

        $order_id = intval( $_POST['order_id'] ?? 0 );
        $fitting_date = sanitize_text_field( $_POST['fitting_date'] ?? '' );
        $notes = sanitize_textarea_field( $_POST['notes'] ?? '' );
        $db = HearMed_DB::instance();

        if ( ! $order_id ) {
            wp_send_json_error( [ 'msg' => 'Missing order ID.' ] );
        }
        if ( $fitting_date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $fitting_date ) ) {
            wp_send_json_error( [ 'msg' => 'Fitting date must be YYYY-MM-DD.' ] );
        }

        $order = $db->get_row( "SELECT id, current_status FROM hearmed_core.orders WHERE id = \$1", [ $order_id ] );
        if ( ! $order ) {
            wp_send_json_error( [ 'msg' => 'Order not found.' ] );
        }
        if ( $order->current_status !== 'Awaiting Fitting' ) {
            wp_send_json_error( [ 'msg' => 'Only Awaiting Fitting orders can be edited here.' ] );
        }

        $db->update( 'hearmed_core.orders', [
            'notes'      => $notes,
            'updated_at' => date( 'Y-m-d H:i:s' ),
        ], [ 'id' => $order_id ] );

        if ( $fitting_date ) {
            $updated = $db->query(
                "UPDATE hearmed_core.fitting_queue
                 SET fitting_date = \$1
                 WHERE order_id = \$2",
                [ $fitting_date, $order_id ]
            );
            if ( ! $updated ) {
                $db->insert( 'hearmed_core.fitting_queue', [
                    'order_id'     => $order_id,
                    'fitting_date' => $fitting_date,
                    'queue_status' => 'Awaiting Fitting',
                ] );
            }
        }

        wp_send_json_success( [ 'msg' => 'Awaiting Fitting order updated.' ] );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AJAX: Pre-fit cancel (cancel order from Awaiting Fitting)
    // ═══════════════════════════════════════════════════════════════════════
    public static function ajax_prefit_cancel() {
        check_ajax_referer( 'hm_nonce', 'nonce' );

        if ( ! self::can_manage_awaiting_fitting_actions() ) {
            wp_send_json_error( [ 'msg' => 'Only Finance and C-Level can pre-fit cancel.' ] );
        }

        $order_id = intval( $_POST['order_id'] ?? 0 );
        $reason   = sanitize_textarea_field( $_POST['reason'] ?? '' );
        $user     = HearMed_Auth::current_user();
        $db       = HearMed_DB::instance();

        if ( ! $order_id ) wp_send_json_error( [ 'msg' => 'Missing order ID.' ] );
        if ( ! $reason )   wp_send_json_error( [ 'msg' => 'A cancellation reason is required.' ] );

        $order = $db->get_row(
            "SELECT current_status, patient_id, invoice_id, grand_total, COALESCE(deposit_amount, 0) AS deposit_amount
             FROM hearmed_core.orders WHERE id = \$1",
            [ $order_id ]
        );
        if ( ! $order ) wp_send_json_error( [ 'msg' => 'Order not found.' ] );
        if ( $order->current_status !== 'Awaiting Fitting' ) {
            wp_send_json_error( [ 'msg' => 'Order is no longer in Awaiting Fitting.' ] );
        }

        $paid_amount = 0.0;
        if ( ! empty( $order->invoice_id ) ) {
            $paid_amount = (float) $db->get_var(
                "SELECT COALESCE(SUM(amount), 0)
                 FROM hearmed_core.payments
                 WHERE invoice_id = \$1
                   AND COALESCE(is_refund, false) = false",
                [ (int) $order->invoice_id ]
            );
        }
        if ( $paid_amount <= 0 && (float) $order->deposit_amount > 0 ) {
            $paid_amount = (float) $order->deposit_amount;
        }
        $order_total = (float) ( $order->grand_total ?? 0 );
        $refund_due  = max( 0.0, min( $paid_amount, $order_total > 0 ? $order_total : $paid_amount ) );

        // Update order to Cancelled
        $db->update( 'hearmed_core.orders', [
            'current_status'      => 'Cancelled',
            'cancellation_type'   => 'pre_fit_cancel',
            'cancellation_reason' => $reason,
            'cancellation_date'   => date( 'Y-m-d H:i:s' ),
        ], [ 'id' => $order_id ] );

        // Remove from fitting queue
        $db->query(
            "UPDATE hearmed_core.fitting_queue SET queue_status = 'Cancelled' WHERE order_id = \$1",
            [ $order_id ]
        );

        self::return_cancelled_order_to_stock( $order_id, 'Pre-fit cancellation: ' . $reason, $user->id ?? null );

        self::log_status_change( $order_id, $order->current_status, 'Cancelled', $user->id ?? null, 'Pre-fit cancel: ' . $reason );

        $queued = false;
        if ( self::has_prefit_cancellation_queue_table() ) {
            self::queue_prefit_cancellation(
                $order_id,
                (int) ( $order->patient_id ?? 0 ),
                ! empty( $order->invoice_id ) ? (int) $order->invoice_id : null,
                isset( $user->id ) ? (int) $user->id : null,
                $reason,
                round( $paid_amount, 2 ),
                round( $refund_due, 2 )
            );
            $queued = true;
        }

        $msg = 'Order cancelled and stock returned.';
        if ( $refund_due > 0 ) {
            $msg .= ' Refund due: €' . number_format( $refund_due, 2 ) . '.';
            if ( $queued ) {
                $msg .= ' Credit note/refund has been queued for Finance review.';
            } else {
                $msg .= ' Create the pre-fit queue table to track credit note/refund workflow.';
            }
        }

        wp_send_json_success( [
            'msg'                  => $msg,
            'refund_due'           => round( $refund_due, 2 ),
            'credit_note_required' => $refund_due > 0,
            'queue_recorded'       => $queued,
        ] );
    }

    /**
     * On cancellation, return serialled hearing aids (and charger) into inventory stock.
     */
    private static function return_cancelled_order_to_stock( int $order_id, string $reason, ?int $staff_id = null ) : void {
        if ( ! function_exists( 'hm_ensure_stock_tables_for_return' ) || ! function_exists( 'hm_return_device_to_stock' ) ) {
            return;
        }

        $db = HearMed_DB::instance();
        hm_ensure_stock_tables_for_return();

        $devices = $db->get_results(
            "SELECT * FROM hearmed_core.patient_devices
             WHERE order_id = \$1
               AND COALESCE(device_status,'Active') <> 'Returned'",
            [ $order_id ]
        ) ?: [];

        $returned_any = false;
        foreach ( $devices as $dev ) {
            $count = hm_return_device_to_stock( $dev, 'both', 'Cancelled', $staff_id, null, $order_id );
            if ( $count > 0 ) {
                $returned_any = true;
                $db->update( 'hearmed_core.patient_devices', [
                    'device_status'   => 'Returned',
                    'inactive_reason' => 'Order Cancelled',
                    'inactive_date'   => date( 'Y-m-d' ),
                    'serial_number_left'  => null,
                    'serial_number_right' => null,
                ], [ 'id' => (int) $dev->id ] );
            }
        }

        if ( function_exists( 'hm_return_order_charger_to_stock' ) ) {
            hm_return_order_charger_to_stock( $order_id, 0, (int) ( $staff_id ?: 0 ) );
        }

        if ( ! $returned_any && class_exists( 'HearMed_Stock' ) ) {
            $staff_name = 'System';
            if ( $staff_id ) {
                $s = $db->get_row( "SELECT first_name, last_name FROM hearmed_reference.staff WHERE id = \$1", [ $staff_id ] );
                if ( $s ) {
                    $staff_name = trim( ($s->first_name ?? '') . ' ' . ($s->last_name ?? '') ) ?: 'System';
                }
            }
            HearMed_Stock::return_stock_from_order( $order_id, $reason, $staff_name );
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CREDIT BALANCE CHECK (used by the complete-order form)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * AJAX: Get patient credit balance.
     */
    public static function ajax_get_patient_credit_balance() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        self::ensure_tracking_schema();
        $patient_id = intval( $_POST['patient_id'] ?? 0 );
        if ( ! $patient_id ) wp_send_json_error( 'No patient.' );

        $cash_balance = class_exists( 'HearMed_Finance' )
            ? HearMed_Finance::get_patient_credit_balance( $patient_id )
            : 0;

        $prsi_pool = self::get_prsi_entitlement_by_ear( $patient_id );
        $prsi_balance = (float) ( $prsi_pool['left'] + $prsi_pool['right'] );
        $claims = self::get_prsi_last_new_claim_dates( $patient_id );
        $left_next = self::next_prsi_date( $claims['left'] ?? null );
        $right_next = self::next_prsi_date( $claims['right'] ?? null );

        wp_send_json_success( [
            'balance'      => number_format( $cash_balance, 2, '.', '' ),
            'cash_balance' => number_format( $cash_balance, 2, '.', '' ),
            'prsi_balance' => number_format( $prsi_balance, 2, '.', '' ),
            'prsi_left_balance'  => number_format( (float) $prsi_pool['left'], 2, '.', '' ),
            'prsi_right_balance' => number_format( (float) $prsi_pool['right'], 2, '.', '' ),
            'prsi_next_left_eligible'  => $left_next,
            'prsi_next_right_eligible' => $right_next,
        ] );
    }

    public static function ajax_get_order_products() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        $db = HearMed_DB::instance();
        $products = $db->get_results(
            "SELECT p.id, p.product_name, p.item_type,
                    p.manufacturer_id, m.name AS manufacturer_name,
                    p.style, p.tech_level,
                    COALESCE(p.retail_price, hr.price_total::numeric, 0) AS retail_price,
                    p.cost_price, p.vat_category, p.is_active,
                    p.hearmed_range_id, p.hearing_aid_class, p.power_type,
                    p.bundled_category, p.speaker_length, p.speaker_power,
                    p.dome_type, p.dome_size
             FROM hearmed_reference.products p
             LEFT JOIN hearmed_reference.manufacturers m ON m.id = p.manufacturer_id
             LEFT JOIN hearmed_reference.hearmed_range hr ON hr.id = p.hearmed_range_id
             WHERE p.is_active = true
             ORDER BY m.name, p.product_name"
        );
        $services = $db->get_results(
            "SELECT id, service_name, default_price, service_code
             FROM hearmed_reference.services
             WHERE is_active = true
             ORDER BY service_name"
        );
        $ranges = $db->get_results(
            "SELECT id, range_name, price_total::numeric AS price_total,
                    price_ex_prsi::numeric AS price_ex_prsi
             FROM hearmed_reference.hearmed_range
             WHERE COALESCE(is_active::text,'true') NOT IN ('false','f','0')
             ORDER BY range_name"
        ) ?: [];
        wp_send_json_success([
            'products' => $products ?: [],
            'services' => $services ?: [],
            'ranges'   => $ranges,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AJAX: Get available stock items for order creation
    // ═══════════════════════════════════════════════════════════════════════
    public static function ajax_get_order_stock() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        $db      = HearMed_DB::instance();
        $clinic  = HearMed_Auth::current_clinic();

        $params = [ 'Available' ];
        $clinic_filter = $clinic ? " AND s.clinic_id = \$2" : '';
        if ( $clinic ) $params[] = $clinic;

        $rows = $db->get_results(
            "SELECT s.id,
                    s.model_name,
                    s.style,
                    s.technology_level,
                    s.serial_number,
                    s.status,
                    COALESCE(m.name, '') AS manufacturer_name,
                    s.manufacturer_id,
                    -- best-effort price match from products catalogue
                    COALESCE(
                        (SELECT p.retail_price
                         FROM hearmed_reference.products p
                         WHERE p.manufacturer_id = s.manufacturer_id
                           AND LOWER(p.product_name) = LOWER(s.model_name)
                           AND p.is_active = true
                         LIMIT 1),
                        0
                    ) AS retail_price,
                    COALESCE(
                        (SELECT p.id
                         FROM hearmed_reference.products p
                         WHERE p.manufacturer_id = s.manufacturer_id
                           AND LOWER(p.product_name) = LOWER(s.model_name)
                           AND p.is_active = true
                         LIMIT 1),
                        0
                    ) AS product_id,
                    COALESCE(
                        (SELECT p.vat_category
                         FROM hearmed_reference.products p
                         WHERE p.manufacturer_id = s.manufacturer_id
                           AND LOWER(p.product_name) = LOWER(s.model_name)
                           AND p.is_active = true
                         LIMIT 1),
                        'exempt'
                    ) AS vat_category
             FROM hearmed_reference.inventory_stock s
             LEFT JOIN hearmed_reference.manufacturers m ON m.id = s.manufacturer_id
             WHERE s.status = \$1
               AND s.item_category = 'hearing_aid'{$clinic_filter}
             ORDER BY m.name, s.model_name, s.serial_number",
            $params
        );

        $items = [];
        foreach ( $rows ?: [] as $r ) {
            $items[] = [
                'id'               => (int) $r->id,
                'manufacturer_name'=> $r->manufacturer_name ?? '',
                'manufacturer_id'  => (int) ( $r->manufacturer_id ?? 0 ),
                'model_name'       => $r->model_name ?? '',
                'style'            => $r->style ?? '',
                'technology_level' => $r->technology_level ?? '',
                'serial_number'    => $r->serial_number ?? '',
                'retail_price'     => (float) ( $r->retail_price ?? 0 ),
                'product_id'       => (int) ( $r->product_id ?? 0 ),
                'vat_rate'         => ( $r->vat_category === 'standard' ) ? 23 : 0,
            ];
        }

        wp_send_json_success( [ 'items' => $items ] );
    }
}