<?php
/**
 * HearMed Portal — Approvals & Ordering Workflow
 *
 * Shortcode: [hearmed_approvals]
 * Access:    C-Level, Finance, Admin ONLY
 *
 * Two tabs:
 *  1) Pending Approval  — Orders awaiting C-Level sign-off
 *  2) Awaiting Order    — Approved orders ready to be placed with manufacturer
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

    // Counts
    $pending_count  = (int) $db->get_var("SELECT COUNT(*) FROM hearmed_core.orders WHERE current_status = 'Awaiting Approval'");
    $approved_count = (int) $db->get_var("SELECT COUNT(*) FROM hearmed_core.orders WHERE current_status = 'Approved'");

    $tab = sanitize_key($_GET['tab'] ?? 'pending');
    if (!in_array($tab, ['pending','awaiting'])) $tab = 'pending';

    ob_start(); ?>
    <style>
    .hma-tabs{display:flex;gap:4px;margin-bottom:20px;border-bottom:2px solid #e2e8f0;padding-bottom:0;}
    .hma-tab{padding:10px 20px;font-size:13px;font-weight:600;color:#64748b;background:none;border:none;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .15s;}
    .hma-tab:hover{color:#0f172a;}
    .hma-tab.active{color:var(--hm-primary,#0BB4C4);border-bottom-color:var(--hm-primary,#0BB4C4);}
    .hma-tab .hma-badge{display:inline-flex;align-items:center;justify-content:center;min-width:20px;height:20px;padding:0 6px;border-radius:10px;font-size:11px;font-weight:700;margin-left:6px;}
    .hma-badge-amber{background:#fef3cd;color:#92400e;}
    .hma-badge-teal{background:#d1fae5;color:#065f46;}
    .hma-card{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(15,23,42,.04);margin-bottom:12px;overflow:hidden;border:1px solid #f1f5f9;}
    .hma-card-hd{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;cursor:pointer;transition:background .1s;}
    .hma-card-hd:hover{background:#f8fafc;}
    .hma-card-left{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
    .hma-card-right{display:flex;align-items:center;gap:12px;}
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
    .hma-pdf-btn{display:inline-flex;align-items:center;gap:4px;padding:6px 14px;border-radius:8px;border:1px solid #e2e8f0;background:#fff;color:#475569;font-size:12px;font-weight:600;cursor:pointer;transition:all .15s;}
    .hma-pdf-btn:hover{background:#f8fafc;border-color:#0BB4C4;color:#0BB4C4;}
    </style>

    <div id="hm-app" class="hm-calendar" data-module="admin" data-view="approvals">
        <div class="hm-page">
            <div class="hm-page-header" style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <h1 class="hm-page-title" style="font-size:22px;font-weight:700;color:#0f172a;margin:0;">Order Approvals</h1>
                    <div style="color:#94a3b8;font-size:12px;margin-top:4px;">Review, approve and manage orders placed by dispensers.</div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="hma-tabs">
                <button class="hma-tab <?php echo $tab==='pending' ? 'active' : ''; ?>" onclick="hmApprovals.switchTab('pending')">
                    Pending Approval
                    <?php if ($pending_count > 0): ?><span class="hma-badge hma-badge-amber"><?php echo $pending_count; ?></span><?php endif; ?>
                </button>
                <button class="hma-tab <?php echo $tab==='awaiting' ? 'active' : ''; ?>" onclick="hmApprovals.switchTab('awaiting')">
                    Awaiting Order
                    <?php if ($approved_count > 0): ?><span class="hma-badge hma-badge-teal"><?php echo $approved_count; ?></span><?php endif; ?>
                </button>
            </div>

            <!-- Tab content loaded via AJAX -->
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
        currentTab: '<?php echo esc_js($tab); ?>',

        switchTab: function(tab) {
            this.currentTab = tab;
            document.querySelectorAll('.hma-tab').forEach(function(t,i){
                t.classList.toggle('active', (i === 0 && tab === 'pending') || (i === 1 && tab === 'awaiting'));
            });
            this.load();
            var url = new URL(window.location);
            url.searchParams.set('tab', tab);
            history.replaceState(null, '', url);
        },

        load: function() {
            var el = document.getElementById('hma-content');
            el.innerHTML = '<div style="text-align:center;padding:40px;color:#94a3b8;"><div class="hm-spinner"></div></div>';
            var self = this;
            jQuery.post(HM.ajax_url, {
                action: 'hm_approvals_load',
                nonce: HM.nonce,
                tab: this.currentTab
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
                var icon = this.currentTab === 'pending' ? '&#10004;' : '&#128230;';
                var msg  = this.currentTab === 'pending' ? 'No orders awaiting approval — all clear!' : 'No approved orders waiting to be placed.';
                el.innerHTML = '<div class="hma-empty"><div class="hma-empty-icon">' + icon + '</div>' + msg + '</div>';
                return;
            }

            var isPending = this.currentTab === 'pending';
            var html = '';

            data.orders.forEach(function(o) {
                var marginClass = o.margin_percent >= 25 ? 'hma-margin-good' : (o.margin_percent >= 15 ? 'hma-margin-warn' : 'hma-margin-bad');

                html += '<div class="hma-card" data-order-id="' + o.id + '">';
                html += '<div class="hma-card-hd" onclick="hmApprovals.toggle(' + o.id + ')">';
                html += '<div class="hma-card-left">';
                html += '<span class="hma-ord-num">' + hmE(o.order_number) + '</span>';
                html += '<span class="hma-patient">' + hmE(o.patient_name) + ' (' + hmE(o.patient_number) + ')</span>';
                if (o.prsi_applicable) html += '<span class="hma-prsi">PRSI &#10004;</span>';

                if (o.flags && o.flags.length) {
                    o.flags.forEach(function(f) {
                        html += '<span class="hma-flag hma-flag-' + f.level + '">' + hmE(f.msg) + '</span>';
                    });
                }
                html += '</div>';
                html += '<div class="hma-card-right">';

                if (isPending) {
                    html += '<span style="font-size:11px;color:#64748b;">Cost: &euro;' + hmN(o.cost_total) + '</span>';
                    html += '<span class="hma-total">Retail: &euro;' + hmN(o.grand_total) + '</span>';
                    html += '<span class="hma-margin ' + marginClass + '">' + o.margin_percent.toFixed(1) + '%</span>';
                } else {
                    html += '<span style="font-size:11px;color:#64748b;">' + o.items_count + ' item' + (o.items_count !== 1 ? 's' : '') + '</span>';
                }

                html += '<span class="hma-expand" id="hma-arrow-' + o.id + '">&#9660;</span>';
                html += '</div></div>';

                html += '<div class="hma-body" id="hma-body-' + o.id + '">';

                html += '<div class="hma-meta">';
                html += '<div><div class="hma-meta-label">Dispenser</div><div class="hma-meta-val">' + hmE(o.dispenser_name) + '</div></div>';
                html += '<div><div class="hma-meta-label">Clinic</div><div class="hma-meta-val">' + hmE(o.clinic_name) + '</div></div>';
                html += '<div><div class="hma-meta-label">Date</div><div class="hma-meta-val">' + hmE(o.order_date) + '</div></div>';
                html += '<div><div class="hma-meta-label">PRSI</div><div class="hma-meta-val">' + (o.prsi_applicable ? 'Yes &mdash; &euro;' + hmN(o.prsi_amount) : 'No') + '</div></div>';
                if (!isPending && o.approved_by_name) {
                    html += '<div><div class="hma-meta-label">Approved By</div><div class="hma-meta-val">' + hmE(o.approved_by_name) + '</div></div>';
                }
                html += '</div>';

                if (isPending) {
                    html += '<table class="hma-tbl"><thead><tr>';
                    html += '<th>Product</th><th>Manufacturer</th><th>Model / Style</th><th>Tech Level</th><th>Ear</th><th>Qty</th>';
                    html += '<th>Cost</th><th>Retail</th><th>Margin</th>';
                    html += '</tr></thead><tbody>';
                    o.items.forEach(function(it) {
                        var lineMargin = it.unit_retail > 0 ? ((it.unit_retail - it.unit_cost) / it.unit_retail * 100).toFixed(1) : '0.0';
                        html += '<tr>';
                        html += '<td>' + hmE(it.description) + '</td>';
                        html += '<td>' + hmE(it.manufacturer) + '</td>';
                        html += '<td>' + hmE(it.style || '\u2014') + '</td>';
                        html += '<td>' + hmE(it.tech_level || '\u2014') + '</td>';
                        html += '<td>' + hmE(it.ear_side || '\u2014') + '</td>';
                        html += '<td>' + it.quantity + '</td>';
                        html += '<td>&euro;' + hmN(it.unit_cost * it.quantity) + '</td>';
                        html += '<td>&euro;' + hmN(it.unit_retail * it.quantity) + '</td>';
                        html += '<td><span class="hma-margin ' + (parseFloat(lineMargin) >= 25 ? 'hma-margin-good' : (parseFloat(lineMargin) >= 15 ? 'hma-margin-warn' : 'hma-margin-bad')) + '">' + lineMargin + '%</span></td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table>';

                    html += '<div class="hma-totals"><div class="hma-totals-box">';
                    html += '<div class="hma-totals-row"><span>Cost Total</span><span>&euro;' + hmN(o.cost_total) + '</span></div>';
                    html += '<div class="hma-totals-row"><span>Retail Subtotal</span><span>&euro;' + hmN(o.subtotal) + '</span></div>';
                    if (o.discount_total > 0) html += '<div class="hma-totals-row"><span>Discount</span><span style="color:#dc2626;">-&euro;' + hmN(o.discount_total) + '</span></div>';
                    html += '<div class="hma-totals-row"><span>VAT</span><span>&euro;' + hmN(o.vat_total) + '</span></div>';
                    if (o.prsi_applicable) html += '<div class="hma-totals-row"><span>PRSI</span><span style="color:#0BB4C4;">-&euro;' + hmN(o.prsi_amount) + '</span></div>';
                    html += '<div class="hma-totals-row hma-totals-total"><span>Grand Total</span><span>&euro;' + hmN(o.grand_total) + '</span></div>';
                    html += '<div class="hma-totals-row"><span>Overall Margin</span><span class="' + marginClass + '" style="padding:2px 6px;border-radius:4px;">' + o.margin_percent.toFixed(1) + '%</span></div>';
                    html += '</div></div>';
                } else {
                    html += '<table class="hma-tbl"><thead><tr>';
                    html += '<th>Order #</th><th>H Number</th><th>Manufacturer</th><th>Model</th><th>Tech Level</th><th>Style</th>';
                    html += '<th>Ear</th><th>Rechargeable</th><th>Charger</th><th>Bundled Items</th>';
                    html += '</tr></thead><tbody>';
                    o.items.forEach(function(it) {
                        var bundledStr = '';
                        if (it.dome_type) bundledStr += 'Dome: ' + it.dome_type + (it.dome_size ? ' (' + it.dome_size + ')' : '') + '; ';
                        if (it.filter_type) bundledStr += 'Filter: ' + it.filter_type + '; ';
                        if (it.speaker_size) bundledStr += 'Speaker: ' + it.speaker_size + '; ';
                        if (it.bundled_items && it.bundled_items.length) {
                            it.bundled_items.forEach(function(b) {
                                bundledStr += b.type + ': ' + b.description + '; ';
                            });
                        }
                        if (!bundledStr) bundledStr = '\u2014';

                        html += '<tr>';
                        html += '<td class="hma-ord-num">' + hmE(o.order_number) + '</td>';
                        html += '<td>' + hmE(o.patient_number) + '</td>';
                        html += '<td>' + hmE(it.manufacturer) + '</td>';
                        html += '<td>' + hmE(it.description) + '</td>';
                        html += '<td>' + hmE(it.tech_level || '\u2014') + '</td>';
                        html += '<td>' + hmE(it.style || '\u2014') + '</td>';
                        html += '<td>' + hmE(it.ear_side || '\u2014') + '</td>';
                        html += '<td>' + (it.is_rechargeable ? '&#10004; Rechargeable' : 'Battery') + '</td>';
                        html += '<td>' + (it.needs_charger ? '&#10004; Yes' : 'No') + '</td>';
                        html += '<td>' + bundledStr + '</td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table>';

                    html += '<div style="display:flex;justify-content:flex-end;margin-top:10px;">';
                    html += '<button class="hma-pdf-btn" onclick="hmApprovals.downloadPDF(' + o.id + ')">&#128196; Download Order PDF</button>';
                    html += '</div>';
                }

                if (o.notes) {
                    html += '<div class="hma-notes"><strong>Notes:</strong> ' + hmE(o.notes) + '</div>';
                }

                html += '<div class="hma-acts">';
                if (isPending) {
                    html += '<button class="hm-btn" style="background:#dc2626;color:#fff;" onclick="hmApprovals.deny(' + o.id + ')">Deny</button>';
                    html += '<button class="hm-btn hm-btn-teal" onclick="hmApprovals.approve(' + o.id + ')">Approve &#10004;</button>';
                } else {
                    html += '<button class="hm-btn hm-btn-teal" onclick="hmApprovals.markOrdered(' + o.id + ')">Mark as Ordered &rarr;</button>';
                }
                html += '</div>';

                html += '</div>';
                html += '</div>';
            });

            document.getElementById('hma-content').innerHTML = html;
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
        },

        markOrdered: function(id) {
            if (!confirm('Mark this order as placed with the manufacturer?')) return;
            var self = this;
            jQuery.post(HM.ajax_url, {
                action: 'hm_mark_ordered',
                nonce: HM.nonce,
                order_id: id
            }, function(r) {
                if (r.success) { self.load(); } else { alert(r.data && r.data.msg ? r.data.msg : 'Error'); }
            });
        },

        downloadPDF: function(id) {
            window.open(HM.ajax_url + '?action=hm_order_pdf&nonce=' + HM.nonce + '&order_id=' + id, '_blank');
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
// AJAX: Load orders for tab
// ═══════════════════════════════════════════════════════════════
add_action('wp_ajax_hm_approvals_load', 'hm_ajax_approvals_load');

function hm_ajax_approvals_load() {
    check_ajax_referer('hm_nonce', 'nonce');
    if (!hm_user_can_approve()) { wp_send_json_error(['msg' => 'Access denied']); return; }

    $db  = HearMed_DB::instance();
    $tab = sanitize_key($_POST['tab'] ?? 'pending');
    $status = $tab === 'awaiting' ? 'Approved' : 'Awaiting Approval';

    $orders = $db->get_results(
        "SELECT o.*,
                p.first_name AS p_first, p.last_name AS p_last,
                p.patient_number,
                c.clinic_name,
                CONCAT(s.first_name, ' ', s.last_name) AS dispenser_name,
                CONCAT(ap.first_name, ' ', ap.last_name) AS approved_by_name
         FROM hearmed_core.orders o
         JOIN hearmed_core.patients p ON p.id = o.patient_id
         JOIN hearmed_reference.clinics c ON c.id = o.clinic_id
         LEFT JOIN hearmed_reference.staff s ON s.id = o.staff_id
         LEFT JOIN hearmed_reference.staff ap ON ap.id = o.approved_by
         WHERE o.current_status = \$1
         ORDER BY o.created_at ASC",
        [$status]
    );

    $result = [];
    foreach ($orders as $o) {
        $items = $db->get_results(
            "SELECT oi.*,
                    pr.product_name, pr.style, pr.tech_level, pr.cost_price AS product_cost,
                    m.name AS manufacturer_name,
                    ps.battery_type, ps.receiver_type
             FROM hearmed_core.order_items oi
             LEFT JOIN hearmed_reference.products pr ON pr.id = oi.item_id AND oi.item_type = 'product'
             LEFT JOIN hearmed_reference.manufacturers m ON m.id = pr.manufacturer_id
             LEFT JOIN hearmed_reference.product_specifications ps ON ps.product_id = pr.id
             WHERE oi.order_id = \$1
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

            $is_rechargeable = false;
            if (!empty($it->battery_type)) {
                $is_rechargeable = stripos($it->battery_type, 'rechargeable') !== false;
            }

            $bundled = [];
            if (!empty($it->bundled_items)) {
                $bundled = json_decode($it->bundled_items, true) ?: [];
            }

            $line_items[] = [
                'description'    => $it->item_description ?: ($it->product_name ?? ''),
                'manufacturer'   => $it->manufacturer_name ?? '',
                'style'          => $it->style ?? '',
                'tech_level'     => $it->tech_level ?? '',
                'ear_side'       => $it->ear_side ?? '',
                'quantity'       => $qty,
                'unit_cost'      => $unit_cost,
                'unit_retail'    => $unit_retail,
                'dome_type'      => $it->dome_type ?? '',
                'dome_size'      => $it->dome_size ?? '',
                'speaker_size'   => $it->speaker_size ?? '',
                'filter_type'    => $it->filter_type ?? '',
                'is_rechargeable'=> $is_rechargeable || !empty($it->is_rechargeable),
                'needs_charger'  => !empty($it->needs_charger),
                'bundled_items'  => $bundled,
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
            'approved_by_name'=> $o->approved_by_name ?? '',
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

    $staff_id = $db->get_var("SELECT id FROM hearmed_reference.staff WHERE wp_user_id = \$1", [$uid]);

    $order = $db->get_row("SELECT * FROM hearmed_core.orders WHERE id = \$1", [$order_id]);
    if (!$order || $order->current_status !== 'Awaiting Approval') {
        wp_send_json_error(['msg' => 'Order not found or already processed']); return;
    }

    $db->query(
        "UPDATE hearmed_core.orders SET current_status = 'Approved', approved_by = \$1, approved_date = \$2, updated_at = \$2 WHERE id = \$3",
        [$staff_id ?: $uid, $now, $order_id]
    );

    $db->query(
        "INSERT INTO hearmed_core.order_status_history (order_id, from_status, to_status, changed_by, changed_at) VALUES (\$1, \$2, \$3, \$4, \$5)",
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

    $order = $db->get_row("SELECT * FROM hearmed_core.orders WHERE id = \$1", [$order_id]);
    if (!$order || $order->current_status !== 'Awaiting Approval') {
        wp_send_json_error(['msg' => 'Order not found or already processed']); return;
    }

    $db->query(
        "UPDATE hearmed_core.orders SET current_status = 'Cancelled', cancellation_type = 'denied', cancellation_reason = \$1, cancellation_date = \$2, updated_at = \$2 WHERE id = \$3",
        [$reason, $now, $order_id]
    );

    $staff_id = $db->get_var("SELECT id FROM hearmed_reference.staff WHERE wp_user_id = \$1", [$uid]);
    $db->query(
        "INSERT INTO hearmed_core.order_status_history (order_id, from_status, to_status, changed_by, changed_at, notes) VALUES (\$1, \$2, \$3, \$4, \$5, \$6)",
        [$order_id, 'Awaiting Approval', 'Cancelled', $staff_id ?: $uid, $now, 'Denied: ' . $reason]
    );

    if (function_exists('hm_audit_log')) {
        hm_audit_log($uid, 'deny', 'order', $order_id, ['status' => 'Awaiting Approval'], ['status' => 'Cancelled', 'reason' => $reason]);
    }

    wp_send_json_success(['order_id' => $order_id, 'new_status' => 'Cancelled']);
}

// ═══════════════════════════════════════════════════════════════
// AJAX: Mark as Ordered (Approved -> Ordered)
// ═══════════════════════════════════════════════════════════════
add_action('wp_ajax_hm_mark_ordered', 'hm_ajax_mark_ordered');

function hm_ajax_mark_ordered() {
    check_ajax_referer('hm_nonce', 'nonce');
    if (!hm_user_can_approve()) { wp_send_json_error(['msg' => 'Access denied']); return; }

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
add_action('wp_ajax_hm_order_pdf', 'hm_ajax_order_pdf');

function hm_ajax_order_pdf() {
    if (!wp_verify_nonce($_GET['nonce'] ?? '', 'hm_nonce')) { wp_die('Invalid nonce'); }
    if (!hm_user_can_approve()) { wp_die('Access denied'); }

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
                pr.product_name, pr.style, pr.tech_level, pr.product_code,
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
.header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #0BB4C4; }
.header h1 { font-size: 20px; color: #0BB4C4; }
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
.print-btn { position: fixed; top: 10px; right: 10px; padding: 10px 20px; background: #0BB4C4; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600; z-index: 100; }
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
                <th>Product</th>
                <th>Code</th>
                <th>Tech Level</th>
                <th>Style</th>
                <th>Ear</th>
                <th>Qty</th>
                <th>Power</th>
                <th>Charger</th>
                <th>Bundled Items</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($mfr['items'] as $item): ?>
            <tr>
                <td><strong><?php echo esc_html($item['product']); ?></strong></td>
                <td><?php echo esc_html($item['product_code'] ?: "\xE2\x80\x94"); ?></td>
                <td><?php echo esc_html($item['tech_level'] ?: "\xE2\x80\x94"); ?></td>
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
// PERMISSION CHECK
// ═══════════════════════════════════════════════════════════════
if (!function_exists('hm_user_can_approve')) {
    function hm_user_can_approve() {
        if (!is_user_logged_in()) return false;
        $user = wp_get_current_user();
        if (in_array('administrator', (array)$user->roles)) return true;
        if (class_exists('HearMed_Auth')) {
            $role = HearMed_Auth::current_role();
            return in_array($role, ['c_level', 'admin', 'finance', 'manager']);
        }
        return current_user_can('manage_options');
    }
}
