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
add_action( 'wp_ajax_hm_prefit_cancel',               [ 'HearMed_Orders', 'ajax_prefit_cancel' ] );
add_action( 'wp_ajax_hm_get_patient_credit_balance',  [ 'HearMed_Orders', 'ajax_get_patient_credit_balance' ] );
add_action( 'wp_ajax_hm_record_order_deposit',        [ 'HearMed_Orders', 'ajax_record_order_deposit' ] );
add_action( 'wp_ajax_hm_get_order_stock',             [ 'HearMed_Orders', 'ajax_get_order_stock' ] );

// ---------------------------------------------------------------------------
// Main class
// ---------------------------------------------------------------------------
class HearMed_Orders {

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
             JOIN hearmed_core.patients p ON p.id = o.patient_id
             JOIN hearmed_reference.clinics c ON c.id = o.clinic_id
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
             JOIN hearmed_core.patients p ON p.id = o.patient_id
             JOIN hearmed_reference.clinics c ON c.id = o.clinic_id
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
            <div style="background:var(--hm-teal,#0BB4C4);color:#fff;display:flex;align-items:center;justify-content:space-between;padding:0 24px;height:50px;border-radius:10px 10px 0 0">
                <a href="<?php echo esc_url( $base ); ?>" style="background:none;border:1px solid rgba(255,255,255,.2);color:#fff;font-size:13px;font-weight:600;padding:6px 14px;border-radius:6px;font-family:var(--hm-font-btn);display:flex;align-items:center;gap:6px;text-decoration:none;transition:all .15s">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 8H1M8 15L1 8l7-7"/></svg> Orders
                </a>
                <div style="text-align:center">
                    <div style="font-family:var(--hm-font-title,'Cormorant Garamond',serif);font-size:20px;font-weight:700;letter-spacing:-.3px">New Order</div>
                    <div style="font-size:11px;opacity:.7;margin-top:1px"><?php echo esc_html( $patient_name ); ?></div>
                </div>
                <div style="min-width:90px"></div>
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
        </style>

        <script>
        (function(){
            var $=jQuery;
            var ajaxUrl='<?php echo esc_js(admin_url("admin-ajax.php")); ?>';
            var nonce='<?php echo esc_js(wp_create_nonce("hm_nonce")); ?>';
            var pid=<?php echo (int)$pid; ?>;
            var ordersBase=<?php echo json_encode($base); ?>;

            var orderItems=[];
            var allProducts=[], allSvcs=[];
            var discountMode='pct';
            var paymentRows=[];
            var stockItems=[], stockLoaded=false;

            function esc(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML;}
            function fmt(n){return '€'+(parseFloat(n)||0).toFixed(2);}

            /* ── Load products ── */
            $.post(ajaxUrl,{action:'hm_get_order_products',nonce:nonce},function(r){
                if(!r||!r.success){$('#hm-oc-loading').text('Failed to load products.');return;}
                allProducts=r.data.products||[];
                allSvcs=r.data.services||[];
                buildCategoryDropdown();
                $('#hm-oc-loading').hide();
                $('#hm-oc-selectors').show();
            });

            function buildCategoryDropdown(){
                var seen={};
                var labels={product:'Hearing Aid',service:'Service',accessory:'Accessory',consumable:'Consumable',bundled:'Bundled Item'};
                allProducts.forEach(function(p){seen[p.item_type||'product']=labels[p.item_type||'product']||p.item_type;});
                if(allSvcs.length) seen['service']='Service';
                var $c=$('#hm-oc-cat').empty().append('<option value="">— Select Category —</option>');
                Object.keys(seen).forEach(function(k){$c.append('<option value="'+k+'">'+esc(seen[k])+'</option>');});
            }

            /* ── Faceted filter system ── */
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
                    $p.append('<option value="'+p.id+'"'+(String(p.id)===curProd?' selected':'')+
                        ' data-price="'+price+'" data-vat="'+vat+'" data-name="'+esc(p.product_name)+'">'+
                        esc(p.product_name)+' — €'+price.toFixed(2)+'</option>');
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

            $('#hm-oc-cat').on('change',function(){
                var cat=$(this).val();
                $('#hm-oc-filters,#hm-oc-svc-wrap').hide();
                $('#hm-oc-ear-wrap,#hm-oc-add-wrap').hide();
                $('#hm-oc-mfr,#hm-oc-style,#hm-oc-range,#hm-oc-prod,#hm-oc-ear,#hm-oc-svc').val('');
                if(!cat) return;
                if(cat==='service'){
                    var $sv=$('#hm-oc-svc').empty().append('<option value="">— Select Service —</option>');
                    allSvcs.forEach(function(s){
                        $sv.append('<option value="'+s.id+'" data-price="'+parseFloat(s.default_price||0)+'">'+esc(s.service_name)+' — €'+parseFloat(s.default_price||0).toFixed(2)+'</option>');
                    });
                    $('#hm-oc-svc-wrap').show();
                } else {
                    $('#hm-oc-filters').show();
                    refreshAllFilters();
                }
            });

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
                        msg+='<div style="margin-top:10px;display:flex;gap:16px">';
                        msg+='<a href="'+esc(ordersBase)+'" style="font-size:13px;font-weight:600;color:#0BB4C4;text-decoration:none">← Back to Orders</a>';
                        msg+='<a href="'+esc(ordersBase)+'?hm_action=view&order_id='+d.order_id+'" style="font-size:13px;font-weight:600;color:#0BB4C4;text-decoration:none">View Order →</a>';
                        msg+='</div></div>';
                        $('#hm-oc-success').html(msg).show();
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

        // ── Check for hearing-aid products that still have NO serial numbers ──
        $missing_serials = $db->get_results(
            "SELECT oi.id AS order_item_id, oi.item_id AS product_id, oi.ear_side,
                    CONCAT(m.name,' ',p.product_name,' ',p.style) AS item_name
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
        $has_missing_serials = !empty($missing_serials);

        $amount_due = $order->invoice_total ?? $order->grand_total;

        ob_start(); ?>
        <div class="hm-content hm-complete-form">
            <div class="hm-page-header">
                <a href="<?php echo esc_url($base.'?hm_action=view&order_id='.$order_id); ?>" class="hm-back">← Order</a>
                <h1 class="hm-page-title">Record Fitting + Payment</h1>
            </div>

            <?php if ($has_missing_serials) : ?>
            <!-- ── SERIAL NUMBERS REQUIRED ──────────────────────────────── -->
            <div class="hm-card hm-card--action" style="max-width:600px;border-left:4px solid #e53e3e;">
                <h3 class="hm-card-title" style="color:#e53e3e;">⚠ Serial Numbers Required</h3>
                <p class="hm-form__hint">
                    The following hearing aids have <strong>not been received in branch</strong> or
                    serial numbers have not been entered. Enter them now before finalising.
                </p>

                <?php foreach ($missing_serials as $ms) :
                    $need_left  = in_array($ms->ear_side, ['Left','Binaural']);
                    $need_right = in_array($ms->ear_side, ['Right','Binaural']);
                    if (!$need_left && !$need_right) { $need_left = true; $need_right = true; } // unknown → ask both
                ?>
                <div class="hm-card hm-card--inset" style="margin-bottom:1rem;">
                    <strong><?php echo esc_html($ms->item_name); ?></strong>
                    <span class="hm-muted">(<?php echo esc_html($ms->ear_side ?? 'Unknown'); ?>)</span>
                    <input type="hidden" class="hm-serial-product" value="<?php echo $ms->product_id; ?>">
                    <input type="hidden" class="hm-serial-oiid"    value="<?php echo $ms->order_item_id; ?>">

                    <?php if ($need_left) : ?>
                    <div class="hm-form-group" style="margin-top:0.75rem;">
                        <label class="hm-label">Left Ear Serial Number <span style="color:#e53e3e;">*</span></label>
                        <input type="text" class="hm-input hm-input--mono hm-serial-left"
                               placeholder="Serial number..." required>
                    </div>
                    <?php endif; ?>
                    <?php if ($need_right) : ?>
                    <div class="hm-form-group" style="margin-top:0.5rem;">
                        <label class="hm-label">Right Ear Serial Number <span style="color:#e53e3e;">*</span></label>
                        <input type="text" class="hm-input hm-input--mono hm-serial-right"
                               placeholder="Serial number..." required>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

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

                <!-- Patient credit application -->
                <div id="hm-credit-available-row" class="hm-form-group" style="display:none;">
                    <div style="padding:12px 16px;background:#f0fdfe;border:1px solid #a5f3fc;border-radius:8px;margin-bottom:4px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <div>
                                <span style="font-size:13px;font-weight:600;color:#0e7490;">Patient has available credit</span>
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
        (function(){
            // ── Check patient credit balance on form load ──
            var hmPatientId  = <?php echo (int) $order->patient_id; ?>;
            var hmGrandTotal = <?php echo (float) $amount_due; ?>;
            var hmDeposit    = <?php echo (float) ($order->deposit_amount ?? 0); ?>;
            var hmBalanceDue = hmGrandTotal;

            if (hmBalanceDue > 0) {
                var fd = new URLSearchParams({action:'hm_get_patient_credit_balance', nonce:'<?php echo $nonce; ?>', patient_id:hmPatientId});
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:fd})
                .then(function(r){return r.json();})
                .then(function(res){
                    if (!res.success) return;
                    var creditBalance = parseFloat(res.data.balance);
                    if (creditBalance <= 0) return;
                    document.getElementById('hm-credit-available-row').style.display = '';
                    document.getElementById('hm-credit-balance-display').textContent = creditBalance.toFixed(2);

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
                    });
                });
            }
        })();

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

            var params = {
                action:'hm_complete_order',
                order_id: btn.dataset.orderId,
                nonce: btn.dataset.nonce,
                fit_date: document.getElementById('hm-fit-date').value,
                amount: document.getElementById('hm-fit-amount').value,
                notes: document.getElementById('hm-fit-notes').value,
                apply_credit: document.getElementById('hm-apply-credit-cb').checked ? '1' : '',
                credit_apply_amount: document.getElementById('hm-credit-apply-value').value
            };
            if (serials.length) params.inline_serials = JSON.stringify(serials);

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body: new URLSearchParams(params)
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

        $patient_id      = intval($_POST['patient_id'] ?? 0);
        $payment_method  = sanitize_text_field($_POST['payment_method'] ?? '');
        $notes           = sanitize_textarea_field($_POST['notes'] ?? '');
        $prsi_left       = !empty($_POST['prsi_left']);
        $prsi_right      = !empty($_POST['prsi_right']);
        $items           = json_decode(sanitize_text_field($_POST['items_json'] ?? '[]'), true);
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

        $prsi_applicable = $prsi_left || $prsi_right;
        $prsi_amount     = ($prsi_left ? 500 : 0) + ($prsi_right ? 500 : 0);
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
                    'notes'            => 'Deposit at order creation — held in patient credits',
                    'reference'        => $order_num,
                    'transaction_date' => $deposit_paid_at ?: date('Y-m-d'),
                ] );
            }
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
                'notes'            => 'Deposit at order creation — balance collected at fitting',
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
                $db->insert('hearmed_core.patient_devices', [
                    'patient_id'          => $order->patient_id,
                    'product_id'          => $product_id,
                    'order_id'            => $order_id,
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
        check_ajax_referer('hm_nonce','nonce');

        $order_id = intval($_POST['order_id'] ?? 0);
        $fit_date = sanitize_text_field($_POST['fit_date'] ?? date('Y-m-d'));
        $amount   = floatval($_POST['amount'] ?? 0);
        $notes    = sanitize_textarea_field($_POST['notes'] ?? '');
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

        // ── Save inline serials submitted from the complete form ──
        $inline_serials = json_decode(stripslashes($_POST['inline_serials'] ?? ''), true);
        if (!empty($inline_serials) && is_array($inline_serials)) {
            foreach ($inline_serials as $s) {
                $pid   = intval($s['product_id'] ?? 0);
                $left  = sanitize_text_field($s['left']  ?? '');
                $right = sanitize_text_field($s['right'] ?? '');
                if ($pid && ($left || $right)) {
                    $db->insert('hearmed_core.patient_devices', [
                        'patient_id'          => $order->patient_id,
                        'product_id'          => $pid,
                        'order_id'            => $order_id,
                        'serial_number_left'  => $left  ?: null,
                        'serial_number_right' => $right ?: null,
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
        $deposit_paid = floatval( $order->deposit_amount ?? 0 );
        $balance_paid = $amount;  // amount entered by dispenser at fitting

        // 5. Create proper invoice with VAT breakdown (captures invoice_id)
        $fitting_invoice_id = null;
        $payment_data = [
            'amount'         => $balance_paid,
            'payment_date'   => $fit_date,
            'payment_method' => $order->payment_method ?? 'Card',
            'received_by'    => $user->id ?? null,
        ];
        if ( class_exists( 'HearMed_Invoice' ) ) {
            $fitting_invoice_id = HearMed_Invoice::create_from_order( $order_id, $payment_data );
        }

        // 6. If create_from_order didn't already mark invoice Paid, do it here
        if ( $order->invoice_id && ! $fitting_invoice_id ) {
            $db->update( 'hearmed_core.invoices', [
                'payment_status'    => 'Paid',
                'balance_remaining' => 0,
            ], [ 'id' => $order->invoice_id ] );
        }
        $effective_invoice_id = $fitting_invoice_id ?: ( $order->invoice_id ?: null );

        // 7. Create payment record for fitting balance (deposit was already recorded at order creation)
        $db->insert( 'hearmed_core.payments', [
            'invoice_id'     => $effective_invoice_id,
            'patient_id'     => $order->patient_id,
            'amount'         => $balance_paid,
            'payment_date'   => $fit_date,
            'payment_method' => $order->payment_method ?? 'Card',
            'received_by'    => $user->id ?? null,
            'clinic_id'      => $order->clinic_id,
            'created_by'     => $user->id ?? null,
            'is_refund'      => false,
        ] );

        // 8. Record fitting payment in financial_transactions
        if ( class_exists( 'HearMed_Finance' ) && $balance_paid > 0 ) {
            HearMed_Finance::record( 'payment', $balance_paid, [
                'patient_id'       => (int) $order->patient_id,
                'order_id'         => $order_id,
                'invoice_id'       => $effective_invoice_id ?: null,
                'payment_method'   => $order->payment_method ?? 'Card',
                'staff_id'         => $user->id ?? null,
                'clinic_id'        => $order->clinic_id ? (int) $order->clinic_id : null,
                'notes'            => 'Fitting payment — balance collected',
                'reference'        => $order->order_number ?? '',
                'transaction_date' => date('Y-m-d'),
            ] );
        }

        // 8b. Record deposit portion applied at fitting
        if ( $deposit_paid > 0 && class_exists( 'HearMed_Finance' ) ) {
            HearMed_Finance::record( 'payment', $deposit_paid, [
                'patient_id'       => (int) $order->patient_id,
                'order_id'         => $order_id,
                'invoice_id'       => $effective_invoice_id ?: null,
                'payment_method'   => $order->deposit_method ?? $order->payment_method ?? 'Card',
                'staff_id'         => $user->id ?? null,
                'clinic_id'        => $order->clinic_id ? (int) $order->clinic_id : null,
                'notes'            => 'Deposit portion — applied at fitting',
                'reference'        => $order->order_number ?? '',
                'transaction_date' => $order->deposit_paid_at ?? date('Y-m-d'),
            ] );
        }

        // 9. Log in patient timeline
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

        // 10. Status history log
        self::log_status_change( $order_id, 'Awaiting Fitting', 'Complete', $user->id ?? null, 'Fitted and paid' );

        // ── 11. Apply patient credit if requested ─────────────────────────
        $credit_actually_applied = 0;
        if ( $apply_credit && $credit_apply_amount > 0 && $effective_invoice_id && class_exists( 'HearMed_Finance' ) ) {
            $credit_balance = HearMed_Finance::get_patient_credit_balance( (int) $order->patient_id );
            $max_apply = min( $credit_apply_amount, $credit_balance );

            if ( $max_apply > 0 ) {
                $credit_actually_applied = HearMed_Finance::apply_credit(
                    (int) $order->patient_id,
                    (int) $effective_invoice_id,
                    $max_apply,
                    $user->id ?? 0
                );

                if ( $credit_actually_applied > 0 ) {
                    // Update invoice to reflect credit applied
                    $db->query(
                        "UPDATE hearmed_core.invoices
                         SET credit_applied = COALESCE(credit_applied, 0) + \$1,
                             balance_remaining = GREATEST(0, COALESCE(balance_remaining, 0) - \$1),
                             updated_at = NOW()
                         WHERE id = \$2",
                        [ $credit_actually_applied, $effective_invoice_id ]
                    );

                    // Record as financial transaction
                    HearMed_Finance::record( 'credit_applied', $credit_actually_applied, [
                        'patient_id'       => (int) $order->patient_id,
                        'order_id'         => $order_id,
                        'invoice_id'       => (int) $effective_invoice_id,
                        'staff_id'         => $user->id ?? null,
                        'clinic_id'        => $order->clinic_id ? (int) $order->clinic_id : null,
                        'notes'            => 'Patient credit applied to order ' . $order->order_number,
                        'reference'        => $order->order_number ?? '',
                        'transaction_date' => date('Y-m-d'),
                    ] );

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

        wp_send_json_success([
            'message'         => 'Fitting complete. Invoice created and queued for QBO review.'
                . ( $credit_actually_applied > 0 ? ' Credit of €' . number_format( $credit_actually_applied, 2 ) . ' applied.' : '' ),
            'order_id'        => $order_id,
            'credit_applied'  => $credit_actually_applied,
            'redirect'        => HearMed_Utils::page_url('orders').'?hm_action=view&order_id='.$order_id,
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
                    o.prsi_applicable, o.prsi_amount, o.created_at,
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
            ];
        }

        wp_send_json_success( $data );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AJAX: Pre-fit cancel (cancel order from Awaiting Fitting)
    // ═══════════════════════════════════════════════════════════════════════
    public static function ajax_prefit_cancel() {
        check_ajax_referer( 'hm_nonce', 'nonce' );

        $order_id = intval( $_POST['order_id'] ?? 0 );
        $reason   = sanitize_textarea_field( $_POST['reason'] ?? '' );
        $user     = HearMed_Auth::current_user();
        $db       = HearMed_DB::instance();

        if ( ! $order_id ) wp_send_json_error( [ 'msg' => 'Missing order ID.' ] );
        if ( ! $reason )   wp_send_json_error( [ 'msg' => 'A cancellation reason is required.' ] );

        $order = $db->get_row(
            "SELECT current_status FROM hearmed_core.orders WHERE id = \$1",
            [ $order_id ]
        );
        if ( ! $order ) wp_send_json_error( [ 'msg' => 'Order not found.' ] );

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

        self::log_status_change( $order_id, $order->current_status, 'Cancelled', $user->id ?? null, 'Pre-fit cancel: ' . $reason );

        wp_send_json_success( 'Order cancelled.' );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CREDIT BALANCE CHECK (used by the complete-order form)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * AJAX: Get patient credit balance.
     */
    public static function ajax_get_patient_credit_balance() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        $patient_id = intval( $_POST['patient_id'] ?? 0 );
        if ( ! $patient_id ) wp_send_json_error( 'No patient.' );

        $balance = class_exists( 'HearMed_Finance' )
            ? HearMed_Finance::get_patient_credit_balance( $patient_id )
            : 0;

        wp_send_json_success( [ 'balance' => number_format( $balance, 2, '.', '' ) ] );
    }

    public static function ajax_get_order_products() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        $db = HearMed_DB::instance();
        $products = $db->get_results(
            "SELECT p.id, p.product_name, p.item_type,
                    p.manufacturer_id, m.name AS manufacturer_name,
                    p.style, p.tech_level,
                    p.retail_price, p.cost_price,
                    p.vat_category, p.is_active
             FROM hearmed_reference.products p
             LEFT JOIN hearmed_reference.manufacturers m ON m.id = p.manufacturer_id
             WHERE p.is_active = true
             ORDER BY m.name, p.product_name"
        );
        $services = $db->get_results(
            "SELECT id, service_name, default_price, service_code
             FROM hearmed_reference.services
             WHERE is_active = true
             ORDER BY service_name"
        );
        wp_send_json_success([
            'products' => $products ?: [],
            'services' => $services ?: [],
            'ranges'   => [],
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