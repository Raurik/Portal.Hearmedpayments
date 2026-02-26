<?php
/**
 * HearMed Portal — Order Approvals (C-Level Only)
 *
 * Shortcode: [hearmed_approvals]
 * Access:    C-Level ONLY
 *
 * Single-view: Pending orders awaiting C-Level sign-off.
 * Shows cost, retail, margins per line item so approver can make informed decisions.
 *
 * @package HearMed_Portal
 * @since   4.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'hearmed_approvals', 'hm_render_approvals_page' );

// ═══════════════════════════════════════════════════════════════
// RENDER
// ═══════════════════════════════════════════════════════════════
function hm_render_approvals_page() {
    if ( ! is_user_logged_in() ) return '<p>Please log in.</p>';
    if ( ! hm_user_can_approve() ) {
        return '<div id="hm-app" class="hm-admin"><p style="padding:2rem;color:#94a3b8;">You do not have permission to view this page.</p></div>';
    }

    $db = HearMed_DB::instance();
    $pending_count = (int) $db->get_var("SELECT COUNT(*) FROM hearmed_core.orders WHERE current_status = 'Awaiting Approval'");

    ob_start(); ?>
    <style>
    .hm-card{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(15,23,42,.04);margin-bottom:12px;overflow:hidden;border:1px solid #f1f5f9;}
    .hm-card-hd{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;cursor:pointer;transition:background .1s;}
    .hm-card-hd:hover{background:#f8fafc;}
    .hm-card-left{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
    .hm-card-right{display:flex;align-items:center;gap:12px;}
    .hma-ord-num{font-weight:700;font-size:13px;color:#0f172a;}
    .hma-patient{font-size:12px;color:#475569;}
    .hma-total{font-weight:700;font-size:14px;color:#0f172a;}
    .hma-margin{font-size:11px;font-weight:600;padding:3px 8px;border-radius:6px;}
    .hma-margin-good{background:#d1fae5;color:#065f46;}
    .hma-margin-warn{background:#fef3cd;color:#92400e;}
    .hma-margin-bad{background:#fee2e2;color:#991b1b;}
    .hma-prsi{font-size:11px;color:#0BB4C4;font-weight:600;}
    .hma-expand{color:#94a3b8;font-size:12px;transition:transform .2s;user-select:none;}
    .hma-expand.open{transform:rotate(180deg);}
    .hma-body{display:none;padding:0 18px 18px;border-top:1px solid #f1f5f9;}
    .hma-body.open{display:block;}
    .hma-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin:14px 0;font-size:12px;}
    .hma-meta-label{color:#94a3b8;font-size:10px;text-transform:uppercase;letter-spacing:.5px;font-weight:600;}
    .hma-meta-val{color:#1e293b;font-weight:500;margin-top:2px;}
    .hma-tbl{width:100%;border-collapse:collapse;font-size:12px;margin:12px 0;}
    .hma-tbl th{text-align:left;padding:8px 10px;background:#f8fafc;color:#64748b;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.3px;border-bottom:1px solid #e2e8f0;}
    .hma-tbl td{padding:8px 10px;border-bottom:1px solid #f1f5f9;color:#334155;}
    .hma-tbl tr:last-child td{border-bottom:none;}
    .hma-acts{display:flex;justify-content:flex-end;gap:8px;margin-top:14px;padding-top:14px;border-top:1px solid #f1f5f9;}
    .hma-notes{margin-top:10px;padding:8px 12px;background:#f8fafc;border-radius:8px;font-size:12px;color:#475569;}
    .hma-totals{display:flex;justify-content:flex-end;margin:8px 0;}
    .hma-totals-box{min-width:220px;font-size:12px;}
    .hma-totals-row{display:flex;justify-content:space-between;padding:3px 0;border-bottom:1px solid #f1f5f9;}
    .hma-totals-row span:first-child{color:#64748b;}
    .hma-totals-total{font-weight:700;font-size:14px;border-bottom:none;padding-top:6px;}
    .hma-empty{text-align:center;padding:40px 20px;color:#94a3b8;font-size:13px;}
    .hma-empty-icon{font-size:32px;margin-bottom:8px;}
    .hma-flag{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:6px;font-size:11px;font-weight:600;}
    .hma-flag-red{background:#fee2e2;color:#991b1b;}
    .hma-flag-amber{background:#fef3cd;color:#92400e;}
    </style>

    <div id="hm-app" class="hm-calendar" data-module="admin" data-view="approvals">
        <div class="hm-page">
            <div class="hm-page-header" style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <h1 class="hm-page-title" style="font-size:22px;font-weight:700;color:#0f172a;margin:0;">Order Approvals</h1>
                    <div style="color:#94a3b8;font-size:12px;margin-top:4px;">Review, approve or deny orders &mdash; C-Level access only.</div>
                </div>
                <?php if ($pending_count > 0): ?>
                <div style="display:flex;align-items:center;gap:8px;background:#fef3cd;padding:8px 16px;border-radius:8px;">
                    <span style="font-size:18px;">&#9888;</span>
                    <span style="font-size:13px;font-weight:600;color:#92400e;"><?php echo $pending_count; ?> order<?php echo $pending_count !== 1 ? 's' : ''; ?> awaiting approval</span>
                </div>
                <?php endif; ?>
            </div>

            <div id="hma-content">
                <div style="text-align:center;padding:40px;color:#94a3b8;"><div class="hm-spinner"></div></div>
            </div>
        </div>
    </div>

    <!-- Deny modal -->
    <div id="hma-deny-modal" style="display:none;position:fixed;inset:0;align-items:center;justify-content:center;padding:24px;background:radial-gradient(circle at top left,rgba(148,163,184,.45),rgba(15,23,42,.75));backdrop-filter:blur(8px);z-index:9000;">
        <div class="hm-modal" style="max-width:480px;">
            <div class="hm-modal-hd">
                <h3 style="margin:0;font-size:14px;font-weight:600;">Deny Order</h3>
                <button class="hm-modal-x" onclick="hmApprovals.closeDeny()">&times;</button>
            </div>
            <div class="hm-modal-body">
                <p style="color:#475569;font-size:12px;margin:0 0 12px;">The dispenser will be notified with your reason.</p>
                <input type="hidden" id="hma-deny-id">
                <div class="hm-form-group">
                    <label>Reason <span style="color:#dc2626;">*</span></label>
                    <textarea id="hma-deny-reason" rows="3" placeholder="Required..." style="width:100%;"></textarea>
                </div>
            </div>
            <div class="hm-modal-ft">
                <button class="hm-btn" onclick="hmApprovals.closeDeny()">Cancel</button>
                <button class="hm-btn" style="background:#dc2626;color:#fff;" onclick="hmApprovals.confirmDeny()">Deny Order</button>
            </div>
        </div>
    </div>

    <script>
    var hmApprovals = {

        load: function() {
            var el = document.getElementById('hma-content');
            el.innerHTML = '<div style="text-align:center;padding:40px;color:#94a3b8;"><div class="hm-spinner"></div></div>';
            var self = this;
            jQuery.post(HM.ajax_url, {
                action: 'hm_approvals_load',
                nonce: HM.nonce,
                tab: 'pending'
            }, function(r) {
                if (r.success) {
                    self.render(r.data);
                } else {
                    el.innerHTML = '<div class="hma-empty"><div class="hma-empty-icon">&#9888;</div>Error loading data.</div>';
                }
            });
        },

        render: function(data) {
            var el = document.getElementById('hma-content');
            if (!data.orders || data.orders.length === 0) {
                el.innerHTML = '<div class="hma-empty"><div class="hma-empty-icon">&#10004;</div>No orders awaiting approval &mdash; all clear!</div>';
                return;
            }

            var html = '';
            data.orders.forEach(function(o) {
                var marginClass = o.margin_percent >= 25 ? 'hma-margin-good' : (o.margin_percent >= 15 ? 'hma-margin-warn' : 'hma-margin-bad');

                html += '<div class="hm-card" data-order-id="' + o.id + '">';
                html += '<div class="hm-card-hd" onclick="hmApprovals.toggle(' + o.id + ')">';
                html += '<div class="hm-card-left">';
                html += '<span class="hma-ord-num">' + hmE(o.order_number) + '</span>';
                html += '<span class="hma-patient">' + hmE(o.patient_name) + ' (' + hmE(o.patient_number) + ')</span>';
                if (o.prsi_applicable) html += '<span class="hma-prsi">PRSI &#10004;</span>';

                if (o.flags && o.flags.length) {
                    o.flags.forEach(function(f) {
                        html += '<span class="hma-flag hma-flag-' + f.level + '">' + hmE(f.msg) + '</span>';
                    });
                }
                html += '</div>';
                html += '<div class="hm-card-right">';
                html += '<span style="font-size:11px;color:#64748b;">Cost: &euro;' + hmN(o.cost_total) + '</span>';
                html += '<span class="hma-total">Retail: &euro;' + hmN(o.grand_total) + '</span>';
                html += '<span class="hma-margin ' + marginClass + '">' + o.margin_percent.toFixed(1) + '%</span>';
                html += '<span class="hma-expand" id="hma-arrow-' + o.id + '">&#9660;</span>';
                html += '</div></div>';

                html += '<div class="hma-body" id="hma-body-' + o.id + '">';

                html += '<div class="hma-meta">';
                html += '<div><div class="hma-meta-label">Dispenser</div><div class="hma-meta-val">' + hmE(o.dispenser_name) + '</div></div>';
                html += '<div><div class="hma-meta-label">Clinic</div><div class="hma-meta-val">' + hmE(o.clinic_name) + '</div></div>';
                html += '<div><div class="hma-meta-label">Date</div><div class="hma-meta-val">' + hmE(o.order_date) + '</div></div>';
                html += '<div><div class="hma-meta-label">PRSI</div><div class="hma-meta-val">' + (o.prsi_applicable ? 'Yes &mdash; &euro;' + hmN(o.prsi_amount) : 'No') + '</div></div>';
                html += '</div>';

                // Line items table with margins
                html += '<table class="hma-tbl"><thead><tr>';
                html += '<th>Product</th><th>Manufacturer</th><th>Model / Style</th><th>Tech Level</th><th>Class</th><th>Ear</th><th>Qty</th>';
                html += '<th>Cost</th><th>Retail</th><th>Margin</th>';
                html += '</tr></thead><tbody>';
                o.items.forEach(function(it) {
                    var lineMargin = it.unit_retail > 0 ? ((it.unit_retail - it.unit_cost) / it.unit_retail * 100).toFixed(1) : '0.0';
                    html += '<tr>';
                    html += '<td>' + hmE(it.description) + '</td>';
                    html += '<td>' + hmE(it.manufacturer) + '</td>';
                    html += '<td>' + hmE(it.style || '\u2014') + '</td>';
                    html += '<td>' + hmE(it.tech_level || '\u2014') + '</td>';
                    html += '<td>' + hmE(it.hearing_aid_class || '\u2014') + '</td>';
                    html += '<td>' + hmE(it.ear_side || '\u2014') + '</td>';
                    html += '<td>' + it.quantity + '</td>';
                    html += '<td>&euro;' + hmN(it.unit_cost * it.quantity) + '</td>';
                    html += '<td>&euro;' + hmN(it.unit_retail * it.quantity) + '</td>';
                    html += '<td><span class="hma-margin ' + (parseFloat(lineMargin) >= 25 ? 'hma-margin-good' : (parseFloat(lineMargin) >= 15 ? 'hma-margin-warn' : 'hma-margin-bad')) + '">' + lineMargin + '%</span></td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';

                // Totals box
                html += '<div class="hma-totals"><div class="hma-totals-box">';
                html += '<div class="hma-totals-row"><span>Cost Total</span><span>&euro;' + hmN(o.cost_total) + '</span></div>';
                html += '<div class="hma-totals-row"><span>Retail Subtotal</span><span>&euro;' + hmN(o.subtotal) + '</span></div>';
                if (o.discount_total > 0) html += '<div class="hma-totals-row"><span>Discount</span><span style="color:#dc2626;">-&euro;' + hmN(o.discount_total) + '</span></div>';
                html += '<div class="hma-totals-row"><span>VAT</span><span>&euro;' + hmN(o.vat_total) + '</span></div>';
                if (o.prsi_applicable) html += '<div class="hma-totals-row"><span>PRSI</span><span style="color:#0BB4C4;">-&euro;' + hmN(o.prsi_amount) + '</span></div>';
                html += '<div class="hma-totals-row hma-totals-total"><span>Grand Total</span><span>&euro;' + hmN(o.grand_total) + '</span></div>';
                html += '<div class="hma-totals-row"><span>Overall Margin</span><span class="' + marginClass + '" style="padding:2px 6px;border-radius:4px;">' + o.margin_percent.toFixed(1) + '%</span></div>';
                html += '</div></div>';

                if (o.notes) {
                    html += '<div class="hma-notes"><strong>Notes:</strong> ' + hmE(o.notes) + '</div>';
                }

                html += '<div class="hma-acts">';
                html += '<button class="hm-btn" style="background:#dc2626;color:#fff;" onclick="hmApprovals.deny(' + o.id + ')">Deny</button>';
                html += '<button class="hm-btn hm-btn--primary" onclick="hmApprovals.approve(' + o.id + ')">Approve &#10004;</button>';
                html += '</div>';

                html += '</div>';
                html += '</div>';
            });

            el.innerHTML = html;
        },

        toggle: function(id) {
            var body = document.getElementById('hma-body-' + id);
            var arrow = document.getElementById('hma-arrow-' + id);
            if (body) {
                body.classList.toggle('open');
                if (arrow) arrow.classList.toggle('open');
            }
        },

        approve: function(id) {
            if (!confirm('Approve this order? It will move to Awaiting Order.')) return;
            var self = this;
            jQuery.post(HM.ajax_url, {
                action: 'hm_approve_order',
                nonce: HM.nonce,
                order_id: id
            }, function(r) {
                if (r.success) { self.load(); } else { alert(r.data && r.data.msg ? r.data.msg : 'Error approving order'); }
            });
        },

        deny: function(id) {
            document.getElementById('hma-deny-id').value = id;
            document.getElementById('hma-deny-reason').value = '';
            document.getElementById('hma-deny-modal').style.display = 'flex';
        },

        closeDeny: function() {
            document.getElementById('hma-deny-modal').style.display = 'none';
        },

        confirmDeny: function() {
            var id = document.getElementById('hma-deny-id').value;
            var reason = document.getElementById('hma-deny-reason').value.trim();
            if (!reason) { alert('A reason is required.'); return; }
            var self = this;
            jQuery.post(HM.ajax_url, {
                action: 'hm_deny_order',
                nonce: HM.nonce,
                order_id: id,
                reason: reason
            }, function(r) {
                if (r.success) { self.closeDeny(); self.load(); } else { alert(r.data && r.data.msg ? r.data.msg : 'Error'); }
            });
        }
    };

    function hmE(s) { if (!s) return ''; var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    function hmN(n) { return parseFloat(n || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }

    jQuery(function() { hmApprovals.load(); });
    </script>
    <?php
    return ob_get_clean();
}

// ═══════════════════════════════════════════════════════════════
// AJAX: Load pending orders
// ═══════════════════════════════════════════════════════════════
add_action('wp_ajax_hm_approvals_load', 'hm_ajax_approvals_load');

function hm_ajax_approvals_load() {
    check_ajax_referer('hm_nonce', 'nonce');
    if (!hm_user_can_approve()) { wp_send_json_error(['msg' => 'Access denied']); return; }

    $db = HearMed_DB::instance();

    $orders = $db->get_results(
        "SELECT o.*,
                p.first_name AS p_first, p.last_name AS p_last,
                p.patient_number,
                c.clinic_name,
                CONCAT(s.first_name, ' ', s.last_name) AS dispenser_name
         FROM hearmed_core.orders o
         JOIN hearmed_core.patients p ON p.id = o.patient_id
         JOIN hearmed_reference.clinics c ON c.id = o.clinic_id
         LEFT JOIN hearmed_reference.staff s ON s.id = o.staff_id
         WHERE o.current_status = 'Awaiting Approval'
         ORDER BY o.created_at ASC"
    );

    $result = [];
    foreach ($orders as $o) {
        $items = $db->get_results(
            "SELECT oi.*,
                    pr.product_name, pr.style, pr.tech_level, pr.hearing_aid_class,
                    pr.cost_price AS product_cost,
                    m.name AS manufacturer_name
             FROM hearmed_core.order_items oi
             LEFT JOIN hearmed_reference.products pr ON pr.id = oi.item_id AND oi.item_type = 'product'
             LEFT JOIN hearmed_reference.manufacturers m ON m.id = pr.manufacturer_id
             WHERE oi.order_id = $1
             ORDER BY oi.line_number",
            [$o->id]
        );

        $line_items = [];
        $cost_total = 0;
        foreach ($items as $it) {
            $unit_cost   = (float)($it->unit_cost_price ?? $it->product_cost ?? 0);
            $unit_retail = (float)($it->unit_retail_price ?? 0);
            $qty         = (int)($it->quantity ?? 1);
            $cost_total += $unit_cost * $qty;

            $line_items[] = [
                'description'       => $it->item_description ?: ($it->product_name ?? ''),
                'manufacturer'      => $it->manufacturer_name ?? '',
                'style'             => $it->style ?? '',
                'tech_level'        => $it->tech_level ?? '',
                'hearing_aid_class' => $it->hearing_aid_class ?? '',
                'ear_side'          => $it->ear_side ?? '',
                'quantity'          => $qty,
                'unit_cost'         => $unit_cost,
                'unit_retail'       => $unit_retail,
            ];
        }

        $margin_pct = (float)($o->gross_margin_percent ?? 0);
        $flags = [];
        if ($margin_pct < 15) {
            $flags[] = ['level' => 'red', 'msg' => 'Low margin: ' . number_format($margin_pct, 1) . '%'];
        } elseif ($margin_pct < 25) {
            $flags[] = ['level' => 'amber', 'msg' => 'Margin: ' . number_format($margin_pct, 1) . '%'];
        }
        if (!empty($o->is_flagged)) {
            $flags[] = ['level' => 'red', 'msg' => 'Flagged: ' . ($o->flag_reason ?: 'possible duplicate')];
        }

        $result[] = [
            'id'              => (int)$o->id,
            'order_number'    => $o->order_number,
            'patient_name'    => trim($o->p_first . ' ' . $o->p_last),
            'patient_number'  => $o->patient_number ?? '',
            'dispenser_name'  => $o->dispenser_name ?? '',
            'clinic_name'     => $o->clinic_name ?? '',
            'order_date'      => $o->order_date ? date('d M Y', strtotime($o->order_date)) : '',
            'prsi_applicable' => (bool)$o->prsi_applicable,
            'prsi_amount'     => (float)($o->prsi_amount ?? 0),
            'subtotal'        => (float)($o->subtotal ?? 0),
            'discount_total'  => (float)($o->discount_total ?? 0),
            'vat_total'       => (float)($o->vat_total ?? 0),
            'grand_total'     => (float)($o->grand_total ?? 0),
            'cost_total'      => $cost_total,
            'margin_percent'  => $margin_pct,
            'notes'           => $o->notes ?? '',
            'items'           => $line_items,
            'items_count'     => count($line_items),
            'flags'           => $flags,
        ];
    }

    wp_send_json_success(['orders' => $result]);
}

// ═══════════════════════════════════════════════════════════════
// AJAX: Approve order (Awaiting Approval -> Approved)
// ═══════════════════════════════════════════════════════════════
add_action('wp_ajax_hm_approve_order', 'hm_ajax_approve_order');

function hm_ajax_approve_order() {
    check_ajax_referer('hm_nonce', 'nonce');
    if (!hm_user_can_approve()) { wp_send_json_error(['msg' => 'Access denied']); return; }

    $db       = HearMed_DB::instance();
    $order_id = intval($_POST['order_id'] ?? 0);
    $uid      = get_current_user_id();
    $now      = current_time('Y-m-d H:i:s');

    $staff_id = $db->get_var("SELECT id FROM hearmed_reference.staff WHERE wp_user_id = $1", [$uid]);

    $order = $db->get_row("SELECT * FROM hearmed_core.orders WHERE id = $1", [$order_id]);
    if (!$order || $order->current_status !== 'Awaiting Approval') {
        wp_send_json_error(['msg' => 'Order not found or already processed']); return;
    }

    $db->query(
        "UPDATE hearmed_core.orders SET current_status = 'Approved', approved_by = $1, approved_date = $2, updated_at = $2 WHERE id = $3",
        [$staff_id ?: $uid, $now, $order_id]
    );

    $db->query(
        "INSERT INTO hearmed_core.order_status_history (order_id, from_status, to_status, changed_by, changed_at) VALUES ($1, $2, $3, $4, $5)",
        [$order_id, 'Awaiting Approval', 'Approved', $staff_id ?: $uid, $now]
    );

    if (function_exists('hm_audit_log')) {
        hm_audit_log($uid, 'approve', 'order', $order_id, ['status' => 'Awaiting Approval'], ['status' => 'Approved']);
    }

    wp_send_json_success(['order_id' => $order_id, 'new_status' => 'Approved']);
}

// ═══════════════════════════════════════════════════════════════
// AJAX: Deny order (Awaiting Approval -> Cancelled)
// ═══════════════════════════════════════════════════════════════
add_action('wp_ajax_hm_deny_order', 'hm_ajax_deny_order');

function hm_ajax_deny_order() {
    check_ajax_referer('hm_nonce', 'nonce');
    if (!hm_user_can_approve()) { wp_send_json_error(['msg' => 'Access denied']); return; }

    $db       = HearMed_DB::instance();
    $order_id = intval($_POST['order_id'] ?? 0);
    $reason   = sanitize_textarea_field($_POST['reason'] ?? '');
    $uid      = get_current_user_id();
    $now      = current_time('Y-m-d H:i:s');

    if (!$reason) { wp_send_json_error(['msg' => 'A denial reason is required']); return; }

    $order = $db->get_row("SELECT * FROM hearmed_core.orders WHERE id = $1", [$order_id]);
    if (!$order || $order->current_status !== 'Awaiting Approval') {
        wp_send_json_error(['msg' => 'Order not found or already processed']); return;
    }

    $db->query(
        "UPDATE hearmed_core.orders SET current_status = 'Cancelled', cancellation_type = 'denied', cancellation_reason = $1, cancellation_date = $2, updated_at = $2 WHERE id = $3",
        [$reason, $now, $order_id]
    );

    $staff_id = $db->get_var("SELECT id FROM hearmed_reference.staff WHERE wp_user_id = $1", [$uid]);
    $db->query(
        "INSERT INTO hearmed_core.order_status_history (order_id, from_status, to_status, changed_by, changed_at, notes) VALUES ($1, $2, $3, $4, $5, $6)",
        [$order_id, 'Awaiting Approval', 'Cancelled', $staff_id ?: $uid, $now, 'Denied: ' . $reason]
    );

    if (function_exists('hm_audit_log')) {
        hm_audit_log($uid, 'deny', 'order', $order_id, ['status' => 'Awaiting Approval'], ['status' => 'Cancelled', 'reason' => $reason]);
    }

    wp_send_json_success(['order_id' => $order_id, 'new_status' => 'Cancelled']);
}

// ═══════════════════════════════════════════════════════════════
// PERMISSION CHECK — C-Level only
// ═══════════════════════════════════════════════════════════════
if (!function_exists('hm_user_can_approve')) {
    function hm_user_can_approve() {
        if (!is_user_logged_in()) return false;
        $user = wp_get_current_user();
        if (in_array('administrator', (array)$user->roles)) return true;
        if (class_exists('HearMed_Auth')) {
            $role = HearMed_Auth::current_role();
            return $role === 'c_level';
        }
        return current_user_can('manage_options');
    }
}
