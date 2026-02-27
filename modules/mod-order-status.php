<?php
/**
 * HearMed Portal — Order Status
 *
 * Shortcode: [hearmed_order_status]
 *
 * Two sections:
 *  1) Approved — Awaiting Order: Orders approved by C-Level, ready to place with manufacturer
 *  2) Ordered — Awaiting Receipt: Orders placed, waiting for delivery
 *
 * Summary boxes: Awaiting Order | Awaiting Delivery Total | Amber Alerts | Red Alerts
 * Aging colour coding based on product Class:
 *   Ready-Fit: Yellow > 5 days, Red > 7 days
 *   Custom:    Yellow > 10 days, Red > 14 days
 *
 * @package HearMed_Portal
 * @since   4.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'hearmed_order_status', 'hm_render_order_status_page' );

// ═══════════════════════════════════════════════════════════════
// RENDER
// ═══════════════════════════════════════════════════════════════
function hm_render_order_status_page() {
    if ( ! is_user_logged_in() ) return '<p>Please log in.</p>';

    $db = HearMed_DB::instance();

    // Summary counts
    $approved_count = (int) $db->get_var("SELECT COUNT(*) FROM hearmed_core.orders WHERE current_status = 'Approved'");
    $ordered_count  = (int) $db->get_var("SELECT COUNT(*) FROM hearmed_core.orders WHERE current_status = 'Ordered'");

    // Aging alerts for ordered items
    $ordered_orders = $db->get_results(
        "SELECT o.id, o.order_date,
                COALESCE(pr.hearing_aid_class, '') AS hearing_aid_class
         FROM hearmed_core.orders o
         LEFT JOIN hearmed_core.order_items oi ON oi.order_id = o.id AND oi.item_type = 'product'
         LEFT JOIN hearmed_reference.products pr ON pr.id = oi.item_id
         WHERE o.current_status = 'Ordered'
         GROUP BY o.id, o.order_date, pr.hearing_aid_class"
    );

    $amber_count = 0;
    $red_count   = 0;
    $now_ts      = time();
    $seen_ids    = [];
    if ($ordered_orders) {
        foreach ($ordered_orders as $oo) {
            if (isset($seen_ids[$oo->id])) continue;
            $days = $oo->order_date ? floor(($now_ts - strtotime($oo->order_date)) / 86400) : 0;
            $cls  = strtolower(trim($oo->hearing_aid_class ?? ''));
            $amber_threshold = ($cls === 'ready-fit') ? 5 : 10;
            $red_threshold   = ($cls === 'ready-fit') ? 7 : 14;
            if ($days >= $red_threshold)        { $red_count++;   $seen_ids[$oo->id] = true; }
            elseif ($days >= $amber_threshold)  { $amber_count++; $seen_ids[$oo->id] = true; }
            else                                { $seen_ids[$oo->id] = true; }
        }
    }

    ob_start(); ?>
    <style>
    /* ── Order Status — page-specific ── */
    .hmos-section{margin-bottom:28px;}
    .hmos-section-title{font-size:15px;font-weight:700;color:#0f172a;margin-bottom:12px;display:flex;align-items:center;gap:8px;}
    .hmos-section-badge{display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:22px;padding:0 7px;border-radius:11px;font-size:11px;font-weight:700;background:#e2e8f0;color:#475569;}

    .hm-tbl-wrap{background:#fff;border-radius:12px;border:1px solid #f1f5f9;box-shadow:0 2px 8px rgba(15,23,42,.04);overflow-x:auto;}

    .hmos-days{font-weight:700;font-size:12px;}
    .hmos-days.green{color:#059669;}
    .hmos-days.amber{color:#d97706;}
    .hmos-days.red{color:#dc2626;}

    .hmos-class-badge{display:inline-flex;padding:3px 8px;border-radius:6px;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.3px;}
    .hmos-class-custom{background:#ede9fe;color:#6d28d9;}
    .hmos-class-ready{background:#dbeafe;color:#1e40af;}

    .hm-btn-order{background:var(--hm-teal);color:#fff;}
    .hm-btn-order:hover{background:#0a9aa8;}
    .hm-btn-pdf{background:#fff;color:#475569;border:1px solid #e2e8f0;}
    .hm-btn-pdf:hover{background:#f8fafc;border-color:var(--hm-teal);color:var(--hm-teal);}

    .hmos-alert-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:6px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;}
    .hmos-alert-amber{background:#fef3cd;color:#92400e;}
    .hmos-alert-red{background:#fee2e2;color:#991b1b;}
    </style>

    <div class="hm-calendar" data-module="admin" data-view="order-status">
        <div class="hm-page">
            <div class="hm-page-header">
                <h1 class="hm-page-title">Order Status</h1>
                <div style="color:var(--hm-text-muted);font-size:12px;margin-top:4px;">Track approved orders through to delivery.</div>
            </div>

            <!-- Summary Boxes -->
            <div class="hm-stats">
                <div class="hm-stat">
                    <div class="hm-stat-label">Awaiting Order</div>
                    <div class="hm-stat-val teal"><?php echo $approved_count; ?></div>
                </div>
                <div class="hm-stat">
                    <div class="hm-stat-label">Awaiting Delivery</div>
                    <div class="hm-stat-val blue"><?php echo $ordered_count; ?></div>
                </div>
                <div class="hm-stat">
                    <div class="hm-stat-label">Amber Alerts</div>
                    <div class="hm-stat-val amber"><?php echo $amber_count; ?></div>
                </div>
                <div class="hm-stat">
                    <div class="hm-stat-label">Red Alerts</div>
                    <div class="hm-stat-val red"><?php echo $red_count; ?></div>
                </div>
            </div>

            <!-- Section 1: Approved — Awaiting Order -->
            <div id="hmos-section-approved" class="hmos-section">
                <div class="hmos-section-title">
                    Approved &mdash; Awaiting Order
                    <span class="hmos-section-badge" id="hmos-approved-badge"><?php echo $approved_count; ?></span>
                </div>
                <div id="hmos-approved-content">
                    <div class="hm-loading"><div class="hm-loading-dots"><div class="hm-loading-dot"></div><div class="hm-loading-dot"></div><div class="hm-loading-dot"></div></div><div class="hm-loading-text">Loading&hellip;</div></div>
                </div>
            </div>

            <!-- Section 2: Ordered — Awaiting Receipt -->
            <div id="hmos-section-ordered" class="hmos-section">
                <div class="hmos-section-title">
                    Ordered &mdash; Awaiting Receipt
                    <span class="hmos-section-badge" id="hmos-ordered-badge"><?php echo $ordered_count; ?></span>
                </div>
                <div id="hmos-ordered-content">
                    <div class="hm-loading"><div class="hm-loading-dots"><div class="hm-loading-dot"></div><div class="hm-loading-dot"></div><div class="hm-loading-dot"></div></div><div class="hm-loading-text">Loading&hellip;</div></div>
                </div>
            </div>

        </div>
    </div>

    <script>
    var hmOrderStatus = {

        init: function() {
            this.loadApproved();
            this.loadOrdered();
        },

        // ─── Load Approved — Awaiting Order ───
        loadApproved: function() {
            var el = document.getElementById('hmos-approved-content');
            jQuery.post(HM.ajax_url, {
                action: 'hm_order_status_load',
                nonce: HM.nonce,
                section: 'approved'
            }, function(r) {
                if (r.success) {
                    hmOrderStatus.renderApproved(r.data.orders);
                } else {
                    el.innerHTML = '<div class="hm-empty">Error loading data.</div>';
                }
            });
        },

        renderApproved: function(orders) {
            var el = document.getElementById('hmos-approved-content');
            if (!orders || orders.length === 0) {
                el.innerHTML = '<div class="hm-empty">No orders awaiting placement.</div>';
                return;
            }

            var html = '<div class="hm-tbl-wrap"><table class="hm-table"><thead><tr>';
            html += '<th>Order #</th><th>H Number</th><th>Patient</th><th>Dispenser</th><th>Clinic</th>';
            html += '<th>Manufacturer</th><th>Product</th><th>Class</th><th>Date Approved</th>';
            html += '<th style="text-align:right">Actions</th>';
            html += '</tr></thead><tbody>';

            orders.forEach(function(o) {
                var clsClass = (o.hearing_aid_class || '').toLowerCase() === 'custom' ? 'hmos-class-custom' : 'hmos-class-ready';
                var clsLabel = o.hearing_aid_class || '\u2014';

                html += '<tr>';
                html += '<td><strong>' + hmOsE(o.order_number) + '</strong></td>';
                html += '<td>' + hmOsE(o.patient_number) + '</td>';
                html += '<td>' + hmOsE(o.patient_name) + '</td>';
                html += '<td>' + hmOsE(o.dispenser_name) + '</td>';
                html += '<td>' + hmOsE(o.clinic_name) + '</td>';
                html += '<td>' + hmOsE(o.manufacturer_name) + '</td>';
                html += '<td>' + hmOsE(o.product_name) + '</td>';
                html += '<td>' + (o.hearing_aid_class ? '<span class="hmos-class-badge ' + clsClass + '">' + hmOsE(clsLabel) + '</span>' : '\u2014') + '</td>';
                html += '<td>' + hmOsE(o.approved_date) + '</td>';
                html += '<td style="text-align:right;white-space:nowrap;">';
                html += '<button class="hm-btn hm-btn-pdf" onclick="hmOrderStatus.downloadPDF(' + o.id + ')" title="Download order sheet">PDF</button> ';
                html += '<button class="hm-btn hm-btn-order" onclick="hmOrderStatus.markOrdered(' + o.id + ')">Ordered &rarr;</button>';
                html += '</td>';
                html += '</tr>';
            });

            html += '</tbody></table></div>';
            el.innerHTML = html;
        },

        // ─── Load Ordered — Awaiting Receipt ───
        loadOrdered: function() {
            var el = document.getElementById('hmos-ordered-content');
            jQuery.post(HM.ajax_url, {
                action: 'hm_order_status_load',
                nonce: HM.nonce,
                section: 'ordered'
            }, function(r) {
                if (r.success) {
                    hmOrderStatus.renderOrdered(r.data.orders);
                } else {
                    el.innerHTML = '<div class="hm-empty">Error loading data.</div>';
                }
            });
        },

        renderOrdered: function(orders) {
            var el = document.getElementById('hmos-ordered-content');
            if (!orders || orders.length === 0) {
                el.innerHTML = '<div class="hm-empty">No orders out for delivery.</div>';
                return;
            }

            var html = '<div class="hm-tbl-wrap"><table class="hm-table"><thead><tr>';
            html += '<th>H Number</th><th>Patient #</th><th>Dispenser</th><th>Clinic</th>';
            html += '<th>Manufacturer</th><th>Name of Aids</th><th>Class</th>';
            html += '<th>Date Ordered</th><th>Fitting Appt</th><th>Days Since Order</th>';
            html += '</tr></thead><tbody>';

            orders.forEach(function(o) {
                var rowClass = '';
                var alertBadge = '';
                if (o.alert_level === 'red') {
                    rowClass = ' class="hm-red"';
                    alertBadge = ' <span class="hmos-alert-badge hmos-alert-red">RED</span>';
                } else if (o.alert_level === 'amber') {
                    rowClass = ' class="hm-amber"';
                    alertBadge = ' <span class="hmos-alert-badge hmos-alert-amber">AMBER</span>';
                }

                var daysClass = o.alert_level === 'red' ? 'red' : (o.alert_level === 'amber' ? 'amber' : 'green');
                var clsClass = (o.hearing_aid_class || '').toLowerCase() === 'custom' ? 'hmos-class-custom' : 'hmos-class-ready';
                var clsLabel = o.hearing_aid_class || '\u2014';

                html += '<tr' + rowClass + '>';
                html += '<td><strong>' + hmOsE(o.patient_number) + '</strong></td>';
                html += '<td>' + hmOsE(o.order_number) + '</td>';
                html += '<td>' + hmOsE(o.dispenser_name) + '</td>';
                html += '<td>' + hmOsE(o.clinic_name) + '</td>';
                html += '<td>' + hmOsE(o.manufacturer_name) + '</td>';
                html += '<td>' + hmOsE(o.product_name) + '</td>';
                html += '<td>' + (o.hearing_aid_class ? '<span class="hmos-class-badge ' + clsClass + '">' + hmOsE(clsLabel) + '</span>' : '\u2014') + '</td>';
                html += '<td>' + hmOsE(o.ordered_date) + '</td>';
                html += '<td>' + (o.fitting_date ? hmOsE(o.fitting_date) : '<span style="color:var(--hm-text-muted);font-style:italic;">Not booked</span>') + '</td>';
                html += '<td><span class="hmos-days ' + daysClass + '">' + o.days_since_order + ' days</span>' + alertBadge + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table></div>';
            el.innerHTML = html;
        },

        // ─── Actions ───
        markOrdered: function(id) {
            if (!confirm('Mark this order as placed with the manufacturer?')) return;
            jQuery.post(HM.ajax_url, {
                action: 'hm_mark_ordered',
                nonce: HM.nonce,
                order_id: id
            }, function(r) {
                if (r.success) { location.reload(); }
                else { alert(r.data && r.data.msg ? r.data.msg : 'Error marking order'); }
            });
        },

        downloadPDF: function(id) {
            window.open(HM.ajax_url + '?action=hm_order_pdf&nonce=' + HM.nonce + '&order_id=' + id, '_blank');
        }
    };

    function hmOsE(s) { if (!s) return ''; var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    jQuery(function() { hmOrderStatus.init(); });
    </script>
    <?php
    return ob_get_clean();
}

// ═══════════════════════════════════════════════════════════════
// AJAX: Load Order Status data
// ═══════════════════════════════════════════════════════════════
add_action('wp_ajax_hm_order_status_load', 'hm_ajax_order_status_load');

function hm_ajax_order_status_load() {
    check_ajax_referer('hm_nonce', 'nonce');

    $db      = HearMed_DB::instance();
    $section = sanitize_key($_POST['section'] ?? 'approved');

    if ($section === 'approved') {
        // Approved orders awaiting placement with manufacturer
        $orders = $db->get_results(
            "SELECT o.id, o.order_number, o.order_date, o.approved_date,
                    p.patient_number, p.first_name AS p_first, p.last_name AS p_last,
                    c.clinic_name,
                    CONCAT(s.first_name, ' ', s.last_name) AS dispenser_name,
                    m.name AS manufacturer_name,
                    pr.product_name, pr.display_name, pr.hearing_aid_class
             FROM hearmed_core.orders o
             JOIN hearmed_core.patients p ON p.id = o.patient_id
             JOIN hearmed_reference.clinics c ON c.id = o.clinic_id
             LEFT JOIN hearmed_reference.staff s ON s.id = o.staff_id
             LEFT JOIN hearmed_core.order_items oi ON oi.order_id = o.id AND oi.item_type = 'product'
             LEFT JOIN hearmed_reference.products pr ON pr.id = oi.item_id
             LEFT JOIN hearmed_reference.manufacturers m ON m.id = pr.manufacturer_id
             WHERE o.current_status = 'Approved'
             ORDER BY o.approved_date ASC, o.created_at ASC"
        );

        $result = [];
        $seen   = [];
        foreach ($orders as $o) {
            if (isset($seen[$o->id])) continue;
            $seen[$o->id] = true;

            $result[] = [
                'id'               => (int)$o->id,
                'order_number'     => $o->order_number ?? '',
                'patient_number'   => $o->patient_number ?? '',
                'patient_name'     => trim(($o->p_first ?? '') . ' ' . ($o->p_last ?? '')),
                'dispenser_name'   => $o->dispenser_name ?? '',
                'clinic_name'      => $o->clinic_name ?? '',
                'manufacturer_name'=> $o->manufacturer_name ?? '',
                'product_name'     => $o->display_name ?: ($o->product_name ?? ''),
                'hearing_aid_class'=> $o->hearing_aid_class ?? '',
                'approved_date'    => $o->approved_date ? date('d M Y', strtotime($o->approved_date)) : '',
            ];
        }

        wp_send_json_success(['orders' => $result]);

    } elseif ($section === 'ordered') {
        // Ordered — waiting for delivery
        $orders = $db->get_results(
            "SELECT o.id, o.order_number, o.order_date,
                    p.patient_number, p.first_name AS p_first, p.last_name AS p_last,
                    c.clinic_name,
                    CONCAT(s.first_name, ' ', s.last_name) AS dispenser_name,
                    m.name AS manufacturer_name,
                    pr.product_name, pr.display_name, pr.hearing_aid_class,
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
             WHERE o.current_status = 'Ordered'
             ORDER BY o.order_date ASC"
        );

        // Find fitting appointment dates for these orders' patients
        $patient_fittings = [];

        $result = [];
        $seen   = [];
        $now_ts = time();

        foreach ($orders as $o) {
            if (isset($seen[$o->id])) continue;
            $seen[$o->id] = true;

            // Get fitting appointment if exists
            $fitting_date = null;
            if (!isset($patient_fittings[$o->patient_number])) {
                $fitting = $db->get_var(
                    "SELECT a.appointment_date
                     FROM hearmed_core.appointments a
                     JOIN hearmed_reference.appointment_types at ON at.id = a.appointment_type_id
                     WHERE a.patient_id = (SELECT id FROM hearmed_core.patients WHERE patient_number = \$1 LIMIT 1)
                       AND at.category = 'fitting'
                       AND a.appointment_date >= CURRENT_DATE
                       AND a.appointment_status != 'Cancelled'
                     ORDER BY a.appointment_date ASC
                     LIMIT 1",
                    [$o->patient_number]
                );
                $patient_fittings[$o->patient_number] = $fitting;
            }
            $fitting_date = $patient_fittings[$o->patient_number] ?? null;

            // Calculate days since order
            $order_date_str = $o->ordered_at ?? $o->order_date;
            $days = $order_date_str ? floor(($now_ts - strtotime($order_date_str)) / 86400) : 0;

            // Determine alert level based on class
            $cls = strtolower(trim($o->hearing_aid_class ?? ''));
            $amber_threshold = ($cls === 'ready-fit') ? 5 : 10;
            $red_threshold   = ($cls === 'ready-fit') ? 7 : 14;

            $alert_level = '';
            if ($days >= $red_threshold) $alert_level = 'red';
            elseif ($days >= $amber_threshold) $alert_level = 'amber';

            $result[] = [
                'id'               => (int)$o->id,
                'order_number'     => $o->order_number ?? '',
                'patient_number'   => $o->patient_number ?? '',
                'patient_name'     => trim(($o->p_first ?? '') . ' ' . ($o->p_last ?? '')),
                'dispenser_name'   => $o->dispenser_name ?? '',
                'clinic_name'      => $o->clinic_name ?? '',
                'manufacturer_name'=> $o->manufacturer_name ?? '',
                'product_name'     => $o->display_name ?: ($o->product_name ?? ''),
                'hearing_aid_class'=> $o->hearing_aid_class ?? '',
                'ordered_date'     => $order_date_str ? date('d M Y', strtotime($order_date_str)) : '',
                'fitting_date'     => $fitting_date ? date('d M Y', strtotime($fitting_date)) : '',
                'days_since_order' => $days,
                'alert_level'      => $alert_level,
            ];
        }

        // Trigger finance notifications for amber/red (once per session)
        hm_order_status_check_notifications($result);

        wp_send_json_success(['orders' => $result]);

    } else {
        wp_send_json_error(['msg' => 'Invalid section']);
    }
}

// ═══════════════════════════════════════════════════════════════
// AJAX: Mark as Ordered (Approved -> Ordered)
// ═══════════════════════════════════════════════════════════════
if (!has_action('wp_ajax_hm_mark_ordered')) {
    add_action('wp_ajax_hm_mark_ordered', 'hm_ajax_os_mark_ordered');
}

function hm_ajax_os_mark_ordered() {
    check_ajax_referer('hm_nonce', 'nonce');

    $db       = HearMed_DB::instance();
    $order_id = intval($_POST['order_id'] ?? 0);
    $uid      = get_current_user_id();
    $now      = current_time('Y-m-d H:i:s');

    $order = $db->get_row("SELECT * FROM hearmed_core.orders WHERE id = \$1", [$order_id]);
    if (!$order || $order->current_status !== 'Approved') {
        wp_send_json_error(['msg' => 'Order not found or not in Approved state']); return;
    }

    $db->query(
        "UPDATE hearmed_core.orders SET current_status = 'Ordered', updated_at = \$1 WHERE id = \$2",
        [$now, $order_id]
    );

    $staff_id = $db->get_var("SELECT id FROM hearmed_reference.staff WHERE wp_user_id = \$1", [$uid]);
    $db->query(
        "INSERT INTO hearmed_core.order_status_history (order_id, from_status, to_status, changed_by, changed_at) VALUES (\$1, \$2, \$3, \$4, \$5)",
        [$order_id, 'Approved', 'Ordered', $staff_id ?: $uid, $now]
    );

    if (function_exists('hm_audit_log')) {
        hm_audit_log($uid, 'order_placed', 'order', $order_id, ['status' => 'Approved'], ['status' => 'Ordered']);
    }

    wp_send_json_success(['order_id' => $order_id, 'new_status' => 'Ordered']);
}

// ═══════════════════════════════════════════════════════════════
// AJAX: PDF Download — Manufacturer Order Sheet
// ═══════════════════════════════════════════════════════════════
if (!has_action('wp_ajax_hm_order_pdf')) {
    add_action('wp_ajax_hm_order_pdf', 'hm_ajax_os_order_pdf');
}

function hm_ajax_os_order_pdf() {
    if (!wp_verify_nonce($_GET['nonce'] ?? '', 'hm_nonce')) { wp_die('Invalid nonce'); }

    $db       = HearMed_DB::instance();
    $order_id = intval($_GET['order_id'] ?? 0);

    $order = $db->get_row(
        "SELECT o.*,
                p.first_name AS p_first, p.last_name AS p_last, p.patient_number,
                c.clinic_name, c.address AS clinic_address, c.phone AS clinic_phone,
                CONCAT(s.first_name, ' ', s.last_name) AS dispenser_name
         FROM hearmed_core.orders o
         JOIN hearmed_core.patients p ON p.id = o.patient_id
         JOIN hearmed_reference.clinics c ON c.id = o.clinic_id
         LEFT JOIN hearmed_reference.staff s ON s.id = o.staff_id
         WHERE o.id = \$1",
        [$order_id]
    );
    if (!$order) wp_die('Order not found');

    $items = $db->get_results(
        "SELECT oi.*,
                pr.product_name, pr.style, pr.tech_level, pr.product_code, pr.hearing_aid_class,
                m.name AS manufacturer_name, m.order_email, m.order_phone,
                m.order_contact_name, m.account_number, m.address AS mfr_address,
                m.support_email, m.support_phone,
                ps.battery_type, ps.receiver_type
         FROM hearmed_core.order_items oi
         LEFT JOIN hearmed_reference.products pr ON pr.id = oi.item_id AND oi.item_type = 'product'
         LEFT JOIN hearmed_reference.manufacturers m ON m.id = pr.manufacturer_id
         LEFT JOIN hearmed_reference.product_specifications ps ON ps.product_id = pr.id
         WHERE oi.order_id = \$1
         ORDER BY oi.line_number",
        [$order_id]
    );

    // Group items by manufacturer
    $by_mfr = [];
    foreach ($items as $it) {
        $mfr = $it->manufacturer_name ?: 'Unknown';
        if (!isset($by_mfr[$mfr])) {
            $by_mfr[$mfr] = [
                'name'    => $mfr,
                'email'   => $it->order_email ?: $it->support_email ?: '',
                'phone'   => $it->order_phone ?: $it->support_phone ?: '',
                'contact' => $it->order_contact_name ?: '',
                'account' => $it->account_number ?: '',
                'address' => $it->mfr_address ?: '',
                'items'   => [],
            ];
        }
        $is_rechargeable = !empty($it->battery_type) && stripos($it->battery_type, 'rechargeable') !== false;

        $bundled_str = '';
        if (!empty($it->dome_type)) $bundled_str .= 'Dome: ' . $it->dome_type . ($it->dome_size ? ' (' . $it->dome_size . ')' : '') . '; ';
        if (!empty($it->filter_type)) $bundled_str .= 'Filter: ' . $it->filter_type . '; ';
        if (!empty($it->speaker_size)) $bundled_str .= 'Speaker: ' . $it->speaker_size . '; ';
        if (!empty($it->bundled_items)) {
            $bi = json_decode($it->bundled_items, true) ?: [];
            foreach ($bi as $b) $bundled_str .= ($b['type'] ?? '') . ': ' . ($b['description'] ?? '') . '; ';
        }

        $by_mfr[$mfr]['items'][] = [
            'product'      => $it->product_name ?: $it->item_description,
            'product_code' => $it->product_code ?: '',
            'style'        => $it->style ?: '',
            'tech_level'   => $it->tech_level ?: '',
            'hearing_aid_class' => $it->hearing_aid_class ?: '',
            'ear_side'     => $it->ear_side ?: '',
            'quantity'     => (int)$it->quantity,
            'rechargeable' => $is_rechargeable || !empty($it->is_rechargeable),
            'needs_charger'=> !empty($it->needs_charger),
            'bundled'      => trim($bundled_str, '; '),
        ];
    }

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html>
<head>
<title>Order <?php echo esc_html($order->order_number); ?> - Manufacturer Order Sheet</title>
<style>
@page { size: A4; margin: 15mm; }
@media print { body { -webkit-print-color-adjust: exact; } .no-print { display: none !important; } }
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 11px; color: #1e293b; line-height: 1.5; padding: 20px; }
.header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid var(--hm-teal); }
.header h1 { font-size: 20px; color: var(--hm-teal); }
.header-meta { text-align: right; font-size: 10px; color: #64748b; }
.header-meta strong { color: #1e293b; }
.section { margin-bottom: 16px; }
.section-title { font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 8px; padding-bottom: 4px; border-bottom: 1px solid #e2e8f0; }
.meta-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; margin-bottom: 12px; font-size: 10px; }
.meta-item label { color: #94a3b8; font-size: 9px; text-transform: uppercase; letter-spacing: .3px; font-weight: 600; display: block; }
.meta-item div { font-weight: 500; color: #1e293b; }
table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
th { background: #f8fafc; text-align: left; padding: 6px 8px; font-size: 10px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .3px; border-bottom: 1px solid #e2e8f0; }
td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; font-size: 11px; }
.mfr-block { margin-bottom: 20px; page-break-inside: avoid; }
.mfr-header { background: #f0fdfa; border: 1px solid #99f6e4; border-radius: 6px; padding: 10px 14px; margin-bottom: 10px; }
.mfr-name { font-size: 14px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
.mfr-contact { font-size: 10px; color: #475569; }
.footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #e2e8f0; font-size: 9px; color: #94a3b8; text-align: center; }
.print-btn { position: fixed; top: 10px; right: 10px; padding: 10px 20px; background: var(--hm-teal); color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600; z-index: 100; }
.print-btn:hover { background: #0a9aa8; }
</style>
</head>
<body>
<button class="print-btn no-print" onclick="window.print()">Print / Save PDF</button>

<div class="header">
    <div>
        <h1>HearMed</h1>
        <div style="font-size:10px;color:#64748b;margin-top:2px;">Manufacturer Order Sheet</div>
    </div>
    <div class="header-meta">
        <div><strong>Order:</strong> <?php echo esc_html($order->order_number); ?></div>
        <div><strong>Date:</strong> <?php echo date('d M Y'); ?></div>
        <div><strong>Clinic:</strong> <?php echo esc_html($order->clinic_name); ?></div>
    </div>
</div>

<div class="section">
    <div class="section-title">Order Details</div>
    <div class="meta-grid">
        <div class="meta-item"><label>Order Number</label><div><?php echo esc_html($order->order_number); ?></div></div>
        <div class="meta-item"><label>H Number</label><div><?php echo esc_html($order->patient_number); ?></div></div>
        <div class="meta-item"><label>Patient</label><div><?php echo esc_html($order->p_first . ' ' . $order->p_last); ?></div></div>
        <div class="meta-item"><label>Dispenser</label><div><?php echo esc_html($order->dispenser_name); ?></div></div>
        <div class="meta-item"><label>Clinic</label><div><?php echo esc_html($order->clinic_name); ?></div></div>
        <div class="meta-item"><label>Order Date</label><div><?php echo esc_html(date('d M Y', strtotime($order->order_date))); ?></div></div>
    </div>
</div>

<?php foreach ($by_mfr as $mfr): ?>
<div class="mfr-block">
    <div class="mfr-header">
        <div class="mfr-name"><?php echo esc_html($mfr['name']); ?></div>
        <div class="mfr-contact">
            <?php if ($mfr['contact']): ?>Contact: <?php echo esc_html($mfr['contact']); ?> | <?php endif; ?>
            <?php if ($mfr['email']): ?>Email: <?php echo esc_html($mfr['email']); ?> | <?php endif; ?>
            <?php if ($mfr['phone']): ?>Phone: <?php echo esc_html($mfr['phone']); ?> | <?php endif; ?>
            <?php if ($mfr['account']): ?>Account: <?php echo esc_html($mfr['account']); ?> | <?php endif; ?>
            <?php if ($mfr['address']): ?><br>Address: <?php echo esc_html($mfr['address']); ?><?php endif; ?>
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <th>Product</th><th>Code</th><th>Tech Level</th><th>Class</th>
                <th>Style</th><th>Ear</th><th>Qty</th><th>Power</th><th>Charger</th><th>Bundled Items</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($mfr['items'] as $item): ?>
            <tr>
                <td><strong><?php echo esc_html($item['product']); ?></strong></td>
                <td><?php echo esc_html($item['product_code'] ?: "\xE2\x80\x94"); ?></td>
                <td><?php echo esc_html($item['tech_level'] ?: "\xE2\x80\x94"); ?></td>
                <td><?php echo esc_html($item['hearing_aid_class'] ?: "\xE2\x80\x94"); ?></td>
                <td><?php echo esc_html($item['style'] ?: "\xE2\x80\x94"); ?></td>
                <td><?php echo esc_html($item['ear_side'] ?: "\xE2\x80\x94"); ?></td>
                <td><?php echo $item['quantity']; ?></td>
                <td><?php echo $item['rechargeable'] ? 'Rechargeable' : 'Battery'; ?></td>
                <td><?php echo $item['needs_charger'] ? 'Yes' : 'No'; ?></td>
                <td><?php echo esc_html($item['bundled'] ?: "\xE2\x80\x94"); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endforeach; ?>

<?php if ($order->notes): ?>
<div class="section">
    <div class="section-title">Clinical Notes</div>
    <p style="font-size:11px;color:#475569;"><?php echo nl2br(esc_html($order->notes)); ?></p>
</div>
<?php endif; ?>

<div class="footer">
    Generated by HearMed Portal on <?php echo date('d M Y \a\t H:i'); ?> | Order <?php echo esc_html($order->order_number); ?>
</div>

</body>
</html>
    <?php
    exit;
}

// ═══════════════════════════════════════════════════════════════
// NOTIFICATION TRIGGER: Alert finance on amber/red thresholds
// ═══════════════════════════════════════════════════════════════
function hm_order_status_check_notifications($orders) {
    if (!function_exists('hm_send_notification')) return;

    // Only run once per hour (transient check)
    $transient_key = 'hm_order_alert_check_' . date('YmdH');
    if (get_transient($transient_key)) return;
    set_transient($transient_key, 1, 3600);

    $db = HearMed_DB::instance();

    // Get finance role staff
    $finance_staff = $db->get_results(
        "SELECT s.id, s.wp_user_id, s.first_name, s.last_name, s.email
         FROM hearmed_reference.staff s
         WHERE s.is_active = true
           AND s.role IN ('finance', 'c_level', 'admin')
         ORDER BY s.first_name"
    );
    if (empty($finance_staff)) return;

    $amber_orders = [];
    $red_orders   = [];

    foreach ($orders as $o) {
        if ($o['alert_level'] === 'amber') $amber_orders[] = $o;
        if ($o['alert_level'] === 'red')   $red_orders[]   = $o;
    }

    if (empty($amber_orders) && empty($red_orders)) return;

    // Build notification message
    $msg_parts = [];
    if (!empty($red_orders)) {
        $msg_parts[] = count($red_orders) . ' RED alert' . (count($red_orders) > 1 ? 's' : '') . ' (overdue delivery)';
    }
    if (!empty($amber_orders)) {
        $msg_parts[] = count($amber_orders) . ' AMBER alert' . (count($amber_orders) > 1 ? 's' : '') . ' (approaching overdue)';
    }

    $message = 'Order Status Alert: ' . implode(', ', $msg_parts) . '. Please review the Order Status page.';

    foreach ($finance_staff as $staff) {
        if (!empty($staff->wp_user_id)) {
            hm_send_notification($staff->wp_user_id, 'order_alert', $message, [
                'amber_count' => count($amber_orders),
                'red_count'   => count($red_orders),
            ]);
        }
    }
}
