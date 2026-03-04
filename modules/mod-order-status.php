<?php
/**
 * HearMed Portal — Order Status
 *
 * Shortcode: [hearmed_order_status]
 *
 * Two tables:
 *  1) Awaiting Approval: Orders not yet approved by C-Level
 *  2) Approved Orders (History): All orders that have been approved — tracks through to completion
 *
 * Aging colour coding based on product Class:
 *   Ready-Fit: Yellow ≥ 5 days, Red ≥ 7 days
 *   Custom:    Yellow ≥ 10 days, Red ≥ 14 days
 *
 * @package HearMed_Portal
 * @since   4.1.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'hearmed_order_status', 'hm_render_order_status_page' );

// ═══════════════════════════════════════════════════════════════
// RENDER
// ═══════════════════════════════════════════════════════════════
function hm_render_order_status_page() {
    if ( ! PortalAuth::is_logged_in() ) return '<p>Please log in.</p>';

    $db    = HearMed_DB::instance();
    $nonce = wp_create_nonce('hm_nonce');

    // ── Summary counts ──
    $awaiting_count = (int) $db->get_var("SELECT COUNT(*) FROM hearmed_core.orders WHERE current_status = 'Awaiting Approval'");
    $approved_count = (int) $db->get_var("SELECT COUNT(*) FROM hearmed_core.orders WHERE LOWER(TRIM(current_status)) IN ('approved','awaiting order')");
    $ordered_count  = (int) $db->get_var("SELECT COUNT(*) FROM hearmed_core.orders WHERE current_status = 'Ordered'");

    // Alert counts across all pipeline orders
    $pipeline_orders = $db->get_results(
        "SELECT o.id, o.created_at, o.order_date, o.approved_date, o.current_status,
                COALESCE(pr.hearing_aid_class, '') AS hearing_aid_class,
                osh.changed_at AS ordered_at
         FROM hearmed_core.orders o
         LEFT JOIN hearmed_core.order_items oi ON oi.order_id = o.id AND oi.item_type = 'product'
         LEFT JOIN hearmed_reference.products pr ON pr.id = oi.item_id
         LEFT JOIN LATERAL (
             SELECT changed_at FROM hearmed_core.order_status_history
             WHERE order_id = o.id AND to_status = 'Ordered'
             ORDER BY changed_at DESC LIMIT 1
         ) osh ON true
         WHERE o.current_status IN ('Awaiting Approval','Approved','Ordered')
         GROUP BY o.id, o.created_at, o.order_date, o.approved_date, o.current_status, pr.hearing_aid_class, osh.changed_at"
    );

    $amber_count = 0;
    $red_count   = 0;
    $now_ts      = time();
    $seen_ids    = [];
    if ($pipeline_orders) {
        foreach ($pipeline_orders as $po) {
            if (isset($seen_ids[$po->id])) continue;
            $seen_ids[$po->id] = true;
            $st = $po->current_status;
            if ($st === 'Awaiting Approval') $ref = $po->created_at;
            elseif ($st === 'Ordered')       $ref = $po->ordered_at ?? $po->order_date ?? $po->created_at;
            else                             $ref = $po->approved_date ?? $po->created_at;
            $days = $ref ? floor(($now_ts - strtotime($ref)) / 86400) : 0;
            $cls  = strtolower(trim($po->hearing_aid_class ?? ''));
            $at   = ($cls === 'ready-fit') ? 5 : 10;
            $rt   = ($cls === 'ready-fit') ? 7 : 14;
            if ($days >= $rt)      $red_count++;
            elseif ($days >= $at)  $amber_count++;
        }
    }

    ob_start(); ?>
    <style>
    /* ── Order Status page — scoped styles ── */
    .hmos-section { margin-bottom: 32px; }
    .hmos-bubble {
        background: #fff;
        border-radius: 14px;
        border: 1px solid #f1f5f9;
        box-shadow: 0 2px 10px rgba(15,23,42,.045);
        overflow: hidden;
    }
    .hmos-bubble-hd {
        padding: 16px 22px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid #f1f5f9;
    }
    .hmos-bubble-title {
        font-size: 15px;
        font-weight: 700;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
    }
    .hmos-count-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 24px;
        height: 24px;
        padding: 0 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
        background: #e2e8f0;
        color: #475569;
    }
    .hmos-count-pill.teal  { background: rgba(11,180,196,.12); color: #0a8a96; }
    .hmos-count-pill.amber { background: rgba(245,158,11,.12); color: #92400e; }
    .hmos-count-pill.red   { background: rgba(239,68,68,.1);   color: #dc2626; }

    .hmos-bubble-body { padding: 0; }
    .hmos-bubble-body .hm-table { margin: 0; }

    /* Days column */
    .hmos-days { font-weight: 700; font-size: 12px; white-space: nowrap; }
    .hmos-days.green { color: #059669; }
    .hmos-days.amber { color: #d97706; }
    .hmos-days.red   { color: #dc2626; }

    /* Class badge */
    .hmos-class-badge {
        display: inline-flex;
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .3px;
    }
    .hmos-class-custom { background: #ede9fe; color: #6d28d9; }
    .hmos-class-ready  { background: #dbeafe; color: #1e40af; }

    /* Alert badges */
    .hmos-alert-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .3px;
    }
    .hmos-alert-amber { background: #fef3cd; color: #92400e; }
    .hmos-alert-red   { background: #fee2e2; color: #991b1b; }

    /* Status badge */
    .hmos-status-badge {
        display: inline-flex;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        white-space: nowrap;
    }
    .hmos-status-approved  { background: rgba(16,185,129,.08); color: #059669; border: 1px solid rgba(16,185,129,.25); }
    .hmos-status-ordered   { background: rgba(11,180,196,.08); color: #0a8a96; border: 1px solid rgba(11,180,196,.25); }
    .hmos-status-received  { background: rgba(59,130,246,.08); color: #1e40af; border: 1px solid rgba(59,130,246,.25); }
    .hmos-status-fitting   { background: rgba(139,92,246,.08); color: #6d28d9; border: 1px solid rgba(139,92,246,.25); }
    .hmos-status-complete  { background: rgba(16,185,129,.08); color: #047857; border: 1px solid rgba(16,185,129,.3); }

    /* Action buttons */
    .hmos-btn {
        display: inline-flex;
        align-items: center;
        padding: 5px 12px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .3px;
        cursor: pointer;
        border: none;
        transition: all .15s;
        text-decoration: none;
        white-space: nowrap;
    }
    .hmos-btn-pdf {
        background: #fff;
        color: #475569;
        border: 1px solid #e2e8f0;
    }
    .hmos-btn-pdf:hover {
        background: #f8fafc;
        border-color: var(--hm-teal, #0bb4c4);
        color: var(--hm-teal, #0bb4c4);
    }
    .hmos-btn-action {
        background: var(--hm-teal, #0bb4c4);
        color: #fff;
    }
    .hmos-btn-action:hover { background: #0a9aa8; }
    .hmos-btn-action:disabled { opacity: .6; cursor: not-allowed; }

    /* Row highlights for alerts */
    tr.hmos-row-amber td { background: rgba(245,158,11,.04) !important; }
    tr.hmos-row-red td   { background: rgba(239,68,68,.04) !important; }

    /* Empty state inside bubble */
    .hmos-empty {
        padding: 32px 20px;
        text-align: center;
        color: #94a3b8;
        font-size: 13px;
    }
    .hmos-empty-icon { font-size: 28px; margin-bottom: 6px; }
    </style>

    <div class="hm-calendar" data-module="admin" data-view="order-status">
        <div class="hm-page">
            <div class="hm-page-header">
                <h1 class="hm-page-title">Order Status</h1>
                <div style="color:var(--hm-text-muted);font-size:12px;margin-top:4px;">Track orders from approval through to delivery.</div>
            </div>

            <!-- Summary Stats -->
            <div class="hm-stats">
                <div class="hm-stat">
                    <div class="hm-stat-label">Awaiting Approval</div>
                    <div class="hm-stat-val teal" id="hmos-stat-awaiting"><?php echo $awaiting_count; ?></div>
                </div>
                <div class="hm-stat">
                    <div class="hm-stat-label">Ready to Order</div>
                    <div class="hm-stat-val blue" id="hmos-stat-approved"><?php echo $approved_count; ?></div>
                </div>
                <div class="hm-stat">
                    <div class="hm-stat-label">Out for Delivery</div>
                    <div class="hm-stat-val blue" id="hmos-stat-ordered"><?php echo $ordered_count; ?></div>
                </div>
                <div class="hm-stat">
                    <div class="hm-stat-label">Amber Alerts</div>
                    <div class="hm-stat-val amber" id="hmos-stat-amber"><?php echo $amber_count; ?></div>
                </div>
                <div class="hm-stat">
                    <div class="hm-stat-label">Red Alerts</div>
                    <div class="hm-stat-val red" id="hmos-stat-red"><?php echo $red_count; ?></div>
                </div>
            </div>

            <!-- Table 1: Awaiting Approval -->
            <div class="hmos-section">
                <div class="hmos-bubble">
                    <div class="hmos-bubble-hd">
                        <h2 class="hmos-bubble-title">
                            Awaiting Approval
                            <span class="hmos-count-pill teal" id="hmos-awaiting-badge"><?php echo $awaiting_count; ?></span>
                        </h2>
                    </div>
                    <div class="hmos-bubble-body" id="hmos-awaiting-content">
                        <div class="hm-loading"><div class="hm-loading-dots"><div class="hm-loading-dot"></div><div class="hm-loading-dot"></div><div class="hm-loading-dot"></div></div><div class="hm-loading-text">Loading&hellip;</div></div>
                    </div>
                </div>
            </div>

            <!-- Table 2: Approved Orders (History) -->
            <div class="hmos-section">
                <div class="hmos-bubble">
                    <div class="hmos-bubble-hd">
                        <h2 class="hmos-bubble-title">
                            Approved Orders
                            <span class="hmos-count-pill" id="hmos-history-badge">…</span>
                        </h2>
                    </div>
                    <div class="hmos-bubble-body" id="hmos-history-content">
                        <div class="hm-loading"><div class="hm-loading-dots"><div class="hm-loading-dot"></div><div class="hm-loading-dot"></div><div class="hm-loading-dot"></div></div><div class="hm-loading-text">Loading&hellip;</div></div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
    var hmOsCtx = {
        ajax_url: (window.HM && HM.ajax_url) || (window.HMP && HMP.ajax) || '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
        nonce:    (window.HM && HM.nonce)    || (window.HMP && HMP.nonce) || '<?php echo esc_js($nonce); ?>'
    };

    var hmOrderStatus = {

        init: function() {
            this.bindActions();
            this.loadAwaiting();
            this.loadHistory();
        },

        bindActions: function() {
            var self = this;

            jQuery(document)
                .off('click.hmOsOrdered')
                .on('click.hmOsOrdered', '.hmos-btn-ordered', function() {
                    var id = parseInt(this.getAttribute('data-order-id') || '0', 10);
                    if (id) self.markStatus(id, 'Ordered', 'Mark as placed with manufacturer?');
                });

            jQuery(document)
                .off('click.hmOsReceived')
                .on('click.hmOsReceived', '.hmos-btn-received', function() {
                    var id = parseInt(this.getAttribute('data-order-id') || '0', 10);
                    if (id) self.markStatus(id, 'Received', 'Confirm this order has been received?');
                });

            jQuery(document)
                .off('click.hmOsPdf')
                .on('click.hmOsPdf', '.hmos-btn-pdf', function(e) {
                    e.preventDefault();
                    var id = parseInt(this.getAttribute('data-order-id') || '0', 10);
                    if (id) self.downloadPDF(id);
                });
        },

        // ─── Table 1: Awaiting Approval ───
        loadAwaiting: function() {
            jQuery.post(hmOsCtx.ajax_url, {
                action: 'hm_order_status_load',
                nonce: hmOsCtx.nonce,
                section: 'awaiting_approval'
            }, function(r) {
                if (r.success) {
                    hmOrderStatus.renderAwaiting(r.data.orders);
                    var badge = document.getElementById('hmos-awaiting-badge');
                    if (badge) badge.textContent = r.data.orders.length;
                } else {
                    document.getElementById('hmos-awaiting-content').innerHTML = '<div class="hmos-empty">Error loading data.</div>';
                }
            });
        },

        renderAwaiting: function(orders) {
            var el = document.getElementById('hmos-awaiting-content');
            if (!orders || orders.length === 0) {
                el.innerHTML = '<div class="hmos-empty"><div class="hmos-empty-icon">✓</div>No orders awaiting approval.</div>';
                return;
            }

            var html = '<table class="hm-table"><thead><tr>';
            html += '<th>Order #</th><th>H Number</th><th>Patient</th><th>Dispenser</th><th>Clinic</th>';
            html += '<th>Manufacturer</th><th>Product</th><th>Class</th>';
            html += '<th>Date Created</th><th>Days Waiting</th>';
            html += '</tr></thead><tbody>';

            orders.forEach(function(o) {
                var clsBadge = hmOsClassBadge(o.hearing_aid_class);
                var daysInfo = hmOsDaysCell(o.days_waiting, o.alert_level);
                var rowCls   = o.alert_level === 'red' ? ' class="hmos-row-red"' : (o.alert_level === 'amber' ? ' class="hmos-row-amber"' : '');

                html += '<tr' + rowCls + '>';
                html += '<td><strong>' + hmOsE(o.order_number) + '</strong></td>';
                html += '<td><code style="font-size:11px;color:#64748b;">' + hmOsE(o.patient_number) + '</code></td>';
                html += '<td>' + hmOsE(o.patient_name) + '</td>';
                html += '<td>' + hmOsE(o.dispenser_name) + '</td>';
                html += '<td>' + hmOsE(o.clinic_name) + '</td>';
                html += '<td>' + hmOsE(o.manufacturer_name) + '</td>';
                html += '<td style="max-width:180px;">' + hmOsE(o.product_name) + '</td>';
                html += '<td>' + clsBadge + '</td>';
                html += '<td>' + hmOsE(o.created_date) + '</td>';
                html += '<td>' + daysInfo + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
            el.innerHTML = html;
        },

        // ─── Table 2: Approved Orders (History) ───
        loadHistory: function() {
            jQuery.post(hmOsCtx.ajax_url, {
                action: 'hm_order_status_load',
                nonce: hmOsCtx.nonce,
                section: 'approved_history'
            }, function(r) {
                if (r.success) {
                    hmOrderStatus.renderHistory(r.data.orders);
                    var badge = document.getElementById('hmos-history-badge');
                    if (badge) badge.textContent = r.data.orders.length;
                } else {
                    document.getElementById('hmos-history-content').innerHTML = '<div class="hmos-empty">Error loading data.</div>';
                }
            });
        },

        renderHistory: function(orders) {
            var el = document.getElementById('hmos-history-content');
            if (!orders || orders.length === 0) {
                el.innerHTML = '<div class="hmos-empty"><div class="hmos-empty-icon">📋</div>No approved orders yet.</div>';
                return;
            }

            var html = '<table class="hm-table"><thead><tr>';
            html += '<th>Order #</th><th>H Number</th><th>Patient</th><th>Dispenser</th><th>Clinic</th>';
            html += '<th>Product</th><th>Class</th><th>Status</th>';
            html += '<th>Days Since Order</th><th style="text-align:right;">Actions</th>';
            html += '</tr></thead><tbody>';

            orders.forEach(function(o) {
                var clsBadge  = hmOsClassBadge(o.hearing_aid_class);
                var stBadge   = hmOsStatusBadge(o.status);
                var daysInfo  = hmOsDaysCell(o.days_since, o.alert_level);
                var rowCls    = o.alert_level === 'red' ? ' class="hmos-row-red"' : (o.alert_level === 'amber' ? ' class="hmos-row-amber"' : '');

                var actions = '<div style="display:flex;gap:6px;justify-content:flex-end;white-space:nowrap;">';
                actions += '<button class="hmos-btn hmos-btn-pdf" data-order-id="' + o.id + '">PDF</button>';
                if (o.status === 'Approved') {
                    actions += '<button class="hmos-btn hmos-btn-action hmos-btn-ordered" data-order-id="' + o.id + '">Ordered →</button>';
                } else if (o.status === 'Ordered') {
                    actions += '<button class="hmos-btn hmos-btn-action hmos-btn-received" data-order-id="' + o.id + '">Received ✓</button>';
                }
                actions += '</div>';

                html += '<tr' + rowCls + '>';
                html += '<td><strong>' + hmOsE(o.order_number) + '</strong></td>';
                html += '<td><code style="font-size:11px;color:#64748b;">' + hmOsE(o.patient_number) + '</code></td>';
                html += '<td>' + hmOsE(o.patient_name) + '</td>';
                html += '<td>' + hmOsE(o.dispenser_name) + '</td>';
                html += '<td>' + hmOsE(o.clinic_name) + '</td>';
                html += '<td style="max-width:180px;">' + hmOsE(o.product_name) + '</td>';
                html += '<td>' + clsBadge + '</td>';
                html += '<td>' + stBadge + '</td>';
                html += '<td>' + daysInfo + '</td>';
                html += '<td>' + actions + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
            el.innerHTML = html;
        },

        // ─── Actions ───
        markStatus: function(id, newStatus, msg) {
            if (!confirm(msg)) return;
            var btn = jQuery('[data-order-id="' + id + '"].hmos-btn-action');
            btn.prop('disabled', true).text('Saving…');
            jQuery.post(hmOsCtx.ajax_url, {
                action: 'hm_update_order_status',
                nonce: hmOsCtx.nonce,
                order_id: id,
                new_status: newStatus
            }, function(r) {
                if (r.success) { location.reload(); }
                else { alert(r.data && r.data.msg ? r.data.msg : 'Error updating order'); btn.prop('disabled', false); }
            }).fail(function() { alert('Network error'); btn.prop('disabled', false); });
        },

        downloadPDF: function(id) {
            window.open(hmOsCtx.ajax_url + '?action=hm_order_pdf&nonce=' + encodeURIComponent(hmOsCtx.nonce) + '&order_id=' + id, '_blank');
        }
    };

    /* ── Helpers ── */
    function hmOsE(s) { if (!s) return ''; var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    function hmOsClassBadge(cls) {
        if (!cls) return '\u2014';
        var lc = cls.toLowerCase();
        var cc = lc === 'custom' ? 'hmos-class-custom' : 'hmos-class-ready';
        return '<span class="hmos-class-badge ' + cc + '">' + hmOsE(cls) + '</span>';
    }

    function hmOsStatusBadge(st) {
        var map = {
            'Approved':         'hmos-status-approved',
            'Ordered':          'hmos-status-ordered',
            'Received':         'hmos-status-received',
            'Awaiting Fitting': 'hmos-status-fitting',
            'Complete':         'hmos-status-complete'
        };
        var cc = map[st] || 'hmos-status-approved';
        return '<span class="hmos-status-badge ' + cc + '">' + hmOsE(st) + '</span>';
    }

    function hmOsDaysCell(days, level) {
        var cls = level === 'red' ? 'red' : (level === 'amber' ? 'amber' : 'green');
        var badge = '';
        if (level === 'red')   badge = ' <span class="hmos-alert-badge hmos-alert-red">OVERDUE</span>';
        if (level === 'amber') badge = ' <span class="hmos-alert-badge hmos-alert-amber">LATE</span>';
        return '<span class="hmos-days ' + cls + '">' + days + ' day' + (days !== 1 ? 's' : '') + '</span>' + badge;
    }

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
    $section = sanitize_key($_POST['section'] ?? 'awaiting_approval');
    $now_ts  = time();

    // ── Helper: calculate alert level ──
    $calc_alert = function($days, $hearing_aid_class) {
        $cls = strtolower(trim($hearing_aid_class));
        $amber = ($cls === 'ready-fit') ? 5 : 10;
        $red   = ($cls === 'ready-fit') ? 7 : 14;
        if ($days >= $red)   return 'red';
        if ($days >= $amber) return 'amber';
        return '';
    };

    // ────────────────────────────────────────────────
    // SECTION 1: Awaiting Approval
    // ────────────────────────────────────────────────
    if ($section === 'awaiting_approval') {
        $orders = $db->get_results(
            "SELECT o.id, o.order_number, o.created_at,
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
             WHERE o.current_status = 'Awaiting Approval'
             ORDER BY o.created_at ASC"
        );

        $result = [];
        $seen   = [];
        foreach (($orders ?: []) as $o) {
            if (isset($seen[$o->id])) continue;
            $seen[$o->id] = true;

            $days  = $o->created_at ? (int) floor(($now_ts - strtotime($o->created_at)) / 86400) : 0;
            $cls   = $o->hearing_aid_class ?? '';
            $alert = $calc_alert($days, $cls);

            $result[] = [
                'id'                => (int)$o->id,
                'order_number'      => $o->order_number ?? '',
                'patient_number'    => $o->patient_number ?? '',
                'patient_name'      => trim(($o->p_first ?? '') . ' ' . ($o->p_last ?? '')),
                'dispenser_name'    => $o->dispenser_name ?? '',
                'clinic_name'       => $o->clinic_name ?? '',
                'manufacturer_name' => $o->manufacturer_name ?? '',
                'product_name'      => $o->display_name ?: ($o->product_name ?? ''),
                'hearing_aid_class' => $cls,
                'created_date'      => $o->created_at ? date('d M Y', strtotime($o->created_at)) : '',
                'days_waiting'      => $days,
                'alert_level'       => $alert,
            ];
        }

        wp_send_json_success(['orders' => $result]);

    // ────────────────────────────────────────────────
    // SECTION 2: Approved History (all post-approval)
    // ────────────────────────────────────────────────
    } elseif ($section === 'approved_history') {
        $orders = $db->get_results(
            "SELECT o.id, o.order_number, o.order_date, o.approved_date, o.created_at,
                    o.current_status,
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
             WHERE o.current_status IN ('Approved','Ordered','Received','Awaiting Fitting','Complete')
             ORDER BY
                 CASE o.current_status
                     WHEN 'Approved' THEN 1
                     WHEN 'Ordered'  THEN 2
                     WHEN 'Received' THEN 3
                     WHEN 'Awaiting Fitting' THEN 4
                     WHEN 'Complete' THEN 5
                 END,
                 o.created_at ASC"
        );

        $result    = [];
        $seen      = [];
        $notifiers = [];

        foreach (($orders ?: []) as $o) {
            if (isset($seen[$o->id])) continue;
            $seen[$o->id] = true;

            $st = $o->current_status;

            // Reference date depends on status
            if ($st === 'Ordered') {
                $ref_date = $o->ordered_at ?? $o->order_date ?? $o->created_at;
            } elseif ($st === 'Approved') {
                $ref_date = $o->approved_date ?? $o->created_at;
            } else {
                $ref_date = $o->created_at;
            }

            $days  = $ref_date ? (int) floor(($now_ts - strtotime($ref_date)) / 86400) : 0;
            $cls   = $o->hearing_aid_class ?? '';

            // Only show alerts for orders still in the pipeline (Approved / Ordered)
            $alert = in_array($st, ['Approved', 'Ordered']) ? $calc_alert($days, $cls) : '';

            $row = [
                'id'                => (int)$o->id,
                'order_number'      => $o->order_number ?? '',
                'patient_number'    => $o->patient_number ?? '',
                'patient_name'      => trim(($o->p_first ?? '') . ' ' . ($o->p_last ?? '')),
                'dispenser_name'    => $o->dispenser_name ?? '',
                'clinic_name'       => $o->clinic_name ?? '',
                'manufacturer_name' => $o->manufacturer_name ?? '',
                'product_name'      => $o->display_name ?: ($o->product_name ?? ''),
                'hearing_aid_class' => $cls,
                'status'            => $st,
                'days_since'        => $days,
                'alert_level'       => $alert,
            ];
            $result[]    = $row;
            $notifiers[] = $row;
        }

        // Trigger finance notifications for amber/red
        hm_order_status_check_notifications($notifiers);

        wp_send_json_success(['orders' => $result]);

    // ── Legacy compat: old sections still work ──
    } elseif ($section === 'approved') {
        $_POST['section'] = 'awaiting_approval';
        hm_ajax_order_status_load();
        return;
    } elseif ($section === 'ordered') {
        $_POST['section'] = 'approved_history';
        hm_ajax_order_status_load();
        return;
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
    $staff_id = PortalAuth::staff_id();
    $now      = current_time('Y-m-d H:i:s');

    $order = $db->get_row("SELECT * FROM hearmed_core.orders WHERE id = \$1", [$order_id]);
    if (!$order) {
        wp_send_json_error(['msg' => 'Order not found']); return;
    }
    $curr = strtolower(trim((string)($order->current_status ?? '')));
    if (!in_array($curr, ['approved', 'awaiting order'], true)) {
        wp_send_json_error(['msg' => 'Order is not in Awaiting Order state']); return;
    }

    $db->query(
        "UPDATE hearmed_core.orders SET current_status = 'Ordered', updated_at = \$1 WHERE id = \$2",
        [$now, $order_id]
    );

    $db->query(
        "INSERT INTO hearmed_core.order_status_history (order_id, from_status, to_status, changed_by, changed_at) VALUES (\$1, \$2, \$3, \$4, \$5)",
        [$order_id, $order->current_status, 'Ordered', $staff_id, $now]
    );

    if (function_exists('hm_audit_log')) {
        hm_audit_log($staff_id, 'order_placed', 'order', $order_id, ['status' => $order->current_status], ['status' => 'Ordered']);
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
    if (!wp_verify_nonce($_REQUEST['nonce'] ?? '', 'hm_nonce')) {
        wp_die('Invalid nonce — please refresh the page and try again.');
    }

    $db       = HearMed_DB::instance();
    $order_id = intval($_GET['order_id'] ?? 0);

    $order = $db->get_row(
        "SELECT o.*,
                p.first_name AS p_first, p.last_name AS p_last, p.patient_number,
                c.clinic_name, CONCAT_WS(', ', c.address_line1, c.city, c.county, COALESCE(c.postcode, '')) AS clinic_address, c.phone AS clinic_phone,
                CONCAT(s.first_name, ' ', s.last_name) AS dispenser_name
         FROM hearmed_core.orders o
         JOIN hearmed_core.patients p          ON p.id = o.patient_id
         LEFT JOIN hearmed_reference.clinics c ON c.id = o.clinic_id
         LEFT JOIN hearmed_reference.staff s   ON s.id = o.staff_id
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

    // Build data object for template engine
    $tpl_data = clone $order;
    $tpl_data->items = $items;

    // Flatten first item's product info for display
    if (!empty($items[0])) {
        $first = $items[0];
        foreach (['style', 'tech_level', 'product_code', 'manufacturer_name'] as $f) {
            if (!isset($tpl_data->$f) && isset($first->$f)) {
                $tpl_data->$f = $first->$f;
            }
        }
    }

    // Approval info
    if (!empty($order->approved_by)) {
        $approver = $db->get_row(
            "SELECT CONCAT(first_name, ' ', last_name) AS name FROM hearmed_reference.staff WHERE id = \$1",
            [$order->approved_by]
        );
        $tpl_data->approved_by_name = $approver ? $approver->name : '';
        $tpl_data->approved_at      = $order->approved_at ?? $order->approved_date ?? '';
    }

    // Ear mould + notes
    $tpl_data->ear_mould_type       = $order->ear_mould_type ?? '';
    $tpl_data->ear_mould_vent       = $order->ear_mould_vent ?? '';
    $tpl_data->ear_mould_material   = $order->ear_mould_material ?? '';
    $tpl_data->special_instructions = $order->notes ?? '';

    header('Content-Type: text/html; charset=utf-8');
    echo HearMed_Print_Templates::render('order', $tpl_data);
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
