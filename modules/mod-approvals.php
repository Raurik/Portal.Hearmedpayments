<?php

// ============================================================
// AUTO-CONVERTED TO POSTGRESQL
// ============================================================
// All database operations converted from WordPress to PostgreSQL
// - $wpdb → HearMed_DB
// - wp_posts/wp_postmeta → PostgreSQL tables
// - Column names updated (_ID → id, etc.)
// 
// REVIEW REQUIRED:
// - Check all queries use correct table names
// - Verify all AJAX handlers work
// - Test all CRUD operations
// ============================================================

/**
 * HearMed Portal — Approval Queue
 * Shortcode: [hearmed_approvals]
 * Access: C-Level, Finance, Admin ONLY
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'hearmed_approvals', 'hm_render_approvals_page' );

function hm_render_approvals_page() {
    if ( ! is_user_logged_in() ) return '<p>Please log in.</p>';
    if ( ! hm_user_can_approve() ) {
        return '<div id="hm-app" class="hm-admin"><p style="padding:2rem;color:#94a3b8;">You do not have permission to view this page.</p></div>';
    }
        // PostgreSQL only - no $wpdb needed
    $t = HearMed_DB::table('orders');
    $pending = 0;
    if ( HearMed_DB::get_var("SHOW TABLES LIKE '{$t}'") === $t ) {
        $pending = (int)HearMed_DB::get_var("SELECT COUNT(*) FROM `{$t}` WHERE status = 'Awaiting Approval'");
    }
    ob_start();
    ?>
    <div id="hm-app" class="hm-admin">

        <div class="hm-page-header">
            <div style="display:flex;align-items:center;gap:12px;">
                <h1 class="hm-page-title">Order Approvals</h1>
                <?php if ($pending > 0) : ?>
                <span class="hm-badge hm-badge-amber" style="font-size:.9rem;padding:4px 10px;"><?php echo $pending; ?> pending</span>
                <?php endif; ?>
            </div>
            <button class="hm-btn hm-btn-outline hm-btn-sm" id="hm-approvals-refresh">↻ Refresh</button>
        </div>

        <div id="hm-approvals-loading" class="hm-loading" style="display:none;"><div class="hm-spinner"></div></div>
        <div id="hm-approvals-empty" class="hm-empty" style="display:none;">
            <div class="hm-empty-icon">✅</div>
            <div class="hm-empty-text">No orders awaiting approval — all clear!</div>
        </div>
        <div id="hm-approvals-list" style="display:flex;flex-direction:column;gap:16px;margin-top:8px;"></div>

        <!-- Deny modal -->
        <div id="hm-deny-modal-bg" class="hm-modal-bg" style="display:none;">
            <div class="hm-modal" style="max-width:480px;">
                <div class="hm-modal-hd">
                    <h2 style="margin:0;font-size:1rem;font-weight:600;">Deny Order</h2>
                    <button class="hm-modal-x" id="hm-deny-modal-close">&times;</button>
                </div>
                <div class="hm-modal-body">
                    <p style="color:#475569;font-size:.875rem;margin:0 0 14px;">The dispenser will be notified with your reason.</p>
                    <input type="hidden" id="hm-deny-order-id">
                    <div class="hm-form-group">
                        <label class="hm-label" for="hm-deny-reason">Reason <span style="color:#dc2626;">*</span></label>
                        <textarea class="hm-textarea" id="hm-deny-reason" rows="4" placeholder="Required…"></textarea>
                    </div>
                </div>
                <div class="hm-modal-ft">
                    <button class="hm-btn hm-btn-outline" id="hm-deny-cancel">Cancel</button>
                    <button class="hm-btn hm-btn-danger"  id="hm-deny-confirm">Deny Order</button>
                </div>
            </div>
        </div>

        <!-- Card template -->
        <template id="hm-approval-card-tpl">
            <div class="hm-card hm-approval-card" data-order-id="">
                <div class="hm-card-hd" style="cursor:pointer;" data-toggle="expand">
                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                        <span class="hm-badge hm-badge-amber">Awaiting Approval</span>
                        <strong class="hm-apc-order-num" style="font-size:.95rem;color:#151B33;"></strong>
                        <span class="hm-apc-patient" style="color:#475569;font-size:.875rem;"></span>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span class="hm-apc-total" style="font-weight:700;font-size:1rem;"></span>
                        <span class="hm-apc-flags"></span>
                        <span class="hm-expand-icon" style="color:#94a3b8;">▼</span>
                    </div>
                </div>
                <div class="hm-apc-body" style="display:none;">
                    <div class="hm-card-body" style="padding-top:0;">
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:18px;font-size:.875rem;">
                            <div><div class="hm-label">Dispenser</div><div class="hm-apc-dispenser" style="font-weight:500;"></div></div>
                            <div><div class="hm-label">Clinic</div><div class="hm-apc-clinic" style="font-weight:500;"></div></div>
                            <div><div class="hm-label">Submitted</div><div class="hm-apc-date" style="font-weight:500;"></div></div>
                            <div><div class="hm-label">PRSI</div><div class="hm-apc-prsi" style="font-weight:500;"></div></div>
                        </div>
                        <table class="hm-table" style="margin-bottom:14px;">
                            <thead><tr><th>Product</th><th>Manufacturer</th><th>Style / Range</th><th>Ear</th><th>Qty</th><th>Unit Price</th><th>Discount</th><th>VAT</th><th>Line Total</th><th>Margin</th></tr></thead>
                            <tbody class="hm-apc-lines-tbody"></tbody>
                        </table>
                        <div style="display:flex;justify-content:flex-end;">
                            <div style="min-width:220px;font-size:.875rem;">
                                <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #e2e8f0;"><span style="color:#64748b;">Subtotal</span><span class="hm-apc-subtotal"></span></div>
                                <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #e2e8f0;"><span style="color:#64748b;">Discount</span><span class="hm-apc-discount"></span></div>
                                <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #e2e8f0;"><span style="color:#64748b;">VAT</span><span class="hm-apc-vat"></span></div>
                                <div class="hm-apc-prsi-row" style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #e2e8f0;"><span style="color:#64748b;">PRSI</span><span class="hm-apc-prsi-val" style="color:#0BB4C4;"></span></div>
                                <div style="display:flex;justify-content:space-between;padding:6px 0;font-weight:700;font-size:1rem;"><span>Grand Total</span><span class="hm-apc-grand"></span></div>
                                <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:.8rem;"><span style="color:#64748b;">Gross Margin</span><span class="hm-apc-margin" style="font-weight:600;"></span></div>
                            </div>
                        </div>
                        <div class="hm-apc-notes-wrap" style="display:none;margin-top:12px;padding:10px 14px;background:#f8fafc;border-radius:8px;font-size:.875rem;color:#475569;">
                            <strong>Notes: </strong><span class="hm-apc-notes"></span>
                        </div>
                        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:18px;padding-top:14px;border-top:1px solid #e2e8f0;">
                            <button class="hm-btn hm-btn-danger hm-apc-deny-btn">Deny</button>
                            <button class="hm-btn hm-btn-teal hm-apc-approve-btn">Approve</button>
                        </div>
                    </div>
                </div>
            </div>
        </template>

    </div>
    <?php
    return ob_get_clean();
}

// ---------------------------------------------------------------------------
// AJAX: Get pending orders
// ---------------------------------------------------------------------------
add_action( 'wp_ajax_hm_get_pending_orders', 'hm_ajax_get_pending_orders' );

function hm_ajax_get_pending_orders() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! hm_user_can_approve() ) { wp_send_json_error(['msg'=>'Access denied']); return; }
        // PostgreSQL only - no $wpdb needed
    $t = HearMed_DB::table('orders');

    // JOIN postmeta for patient name — correct architecture
    $rows = HearMed_DB::get_results(
        "SELECT o.*,
         pm1.meta_value as patient_first, pm2.meta_value as patient_last,
         pm3.meta_value as patient_number
         FROM `{$t}` o
         LEFT JOIN /* TODO: Convert to PostgreSQL table */ wp_postmeta pm1 ON pm1.post_id = o.patient_id AND pm1.meta_key = 'first_name'
         LEFT JOIN /* TODO: Convert to PostgreSQL table */ wp_postmeta pm2 ON pm2.post_id = o.patient_id AND pm2.meta_key = 'last_name'
         LEFT JOIN /* TODO: Convert to PostgreSQL table */ wp_postmeta pm3 ON pm3.post_id = o.patient_id AND pm3.meta_key = 'patient_number'
         WHERE o.status = 'Awaiting Approval'
         ORDER BY o.created_at ASC"
    );

    $orders = [];
    foreach ( $rows as $r ) {
        $line_items = json_decode($r->line_items ?: '[]', true);

        // Warning flags
        $flags = [];
        if ( (float)$r->gross_margin_percent < 15 ) {
            $flags[] = ['level'=>'red',   'msg'=>'Low margin: '.number_format($r->gross_margin_percent,1).'%'];
        } elseif ( (float)$r->gross_margin_percent < 25 ) {
            $flags[] = ['level'=>'amber', 'msg'=>'Margin: '.number_format($r->gross_margin_percent,1).'%'];
        }
        $qty_total = array_sum(array_column($line_items, 'qty'));
        if ( $qty_total > 2 ) {
            $flags[] = ['level'=>'amber', 'msg'=>'Unusual qty: '.$qty_total.' units'];
        }
        if ( !empty($r->flagged) ) {
            $flags[] = ['level'=>'red', 'msg'=>'Possible duplicate — '.($r->flag_reason ?: 'similar product within 90 days')];
        }

        // Enrich lines with product meta
        $lines = [];
        foreach ($line_items as $li) {
            $pid = intval($li['product_id'] ?? 0);
            $lines[] = array_merge($li, [
                // TODO: USE PostgreSQL: Get from table columns
                'manufacturer' => get_post_meta($pid, 'manufacturer', true) ?: '',
                'style'        => get_post_meta($pid, 'style', true)        ?: '',
                'range'        => get_post_meta($pid, 'hearmed_range', true) ?: '',
                'cost_price'   => (float)get_post_meta($pid, 'cost_price', true),
            ]);
        }

        $orders[] = [
            'id'                   => (int)$r->id,
            'order_number'         => $r->order_number,
            'patient_name'         => trim(($r->patient_first??'').' '.($r->patient_last??'')),
            'patient_number'       => $r->patient_number ?? '',
            'dispenser_name'       => get_the_title($r->dispenser_id),
            'clinic_name'          => get_the_title($r->clinic_id),
            'line_items'           => $lines,
            'subtotal'             => (float)$r->subtotal,
            'discount'             => (float)$r->discount_total,  // real column: discount_total
            'vat_total'            => (float)$r->vat_total,
            'grand_total'          => (float)$r->grand_total,
            'prsi_applicable'      => (int)$r->prsi_applicable,
            'prsi_amount'          => (float)$r->prsi_amount,
            'gross_margin_percent' => (float)$r->gross_margin_percent,
            'notes'                => $r->notes,
            'created_at'           => $r->created_at,   // real column: created_at
            'flags'                => $flags,
        ];
    }

    wp_send_json_success($orders);
}

// ---------------------------------------------------------------------------
// AJAX: Approve order
// ---------------------------------------------------------------------------
add_action( 'wp_ajax_hm_approve_order', 'hm_ajax_approve_order' );

function hm_ajax_approve_order() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! hm_user_can_approve() ) { wp_send_json_error(['msg'=>'Access denied']); return; }
        // PostgreSQL only - no $wpdb needed
    $t        = HearMed_DB::table('orders');
    $order_id = intval( $_POST['order_id'] ?? 0 );
    $uid      = get_current_user_id();
    $now      = current_time('mysql');

    $order = HearMed_DB::get_row( /* TODO: Convert to params array */ "SELECT * FROM `{$t}` WHERE id = %d", $order_id);
    if ( ! $order || $order->status !== 'Awaiting Approval' ) {
        wp_send_json_error(['msg'=>'Order not found or already processed']); return;
    }

    // Real columns: approved_by, approved_date, updated_at
    HearMed_DB::update($t, [
        'status'        => 'Approved',
        'approved_by'   => $uid,
        'approved_date' => $now,
        'updated_at'  => $now,
    ], ['id' => $order_id]);

    hm_audit_log($uid, 'approve', 'order', $order_id, ['status'=>'Awaiting Approval'], ['status'=>'Approved']);
    do_action('hm_notify_order_approved', $order_id, $uid);
    do_action('hm_notify_dispenser_order_approved', $order_id, $order->dispenser_id, $order->patient_id);

    wp_send_json_success(['order_id'=>$order_id, 'new_status'=>'Approved']);
}

// ---------------------------------------------------------------------------
// AJAX: Deny order
// ---------------------------------------------------------------------------
add_action( 'wp_ajax_hm_deny_order', 'hm_ajax_deny_order' );

function hm_ajax_deny_order() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! hm_user_can_approve() ) { wp_send_json_error(['msg'=>'Access denied']); return; }
        // PostgreSQL only - no $wpdb needed
    $t        = HearMed_DB::table('orders');
    $order_id = intval( $_POST['order_id'] ?? 0 );
    $reason   = sanitize_textarea_field( $_POST['reason'] ?? '' );
    $uid      = get_current_user_id();
    $now      = current_time('mysql');

    if ( ! $reason ) { wp_send_json_error(['msg'=>'A denial reason is required']); return; }

    $order = HearMed_DB::get_row( /* TODO: Convert to params array */ "SELECT * FROM `{$t}` WHERE id = %d", $order_id);
    if ( ! $order || $order->status !== 'Awaiting Approval' ) {
        wp_send_json_error(['msg'=>'Order not found or already processed']); return;
    }

    // Use cancellation_type/reason/date — that's what the real schema has for denials
    HearMed_DB::update($t, [
        'status'               => 'Cancelled',
        'cancellation_type'    => 'denied',
        'cancellation_reason'  => $reason,
        'cancellation_date'    => $now,
        'updated_at'         => $now,
    ], ['id' => $order_id]);

    hm_audit_log($uid, 'deny', 'order', $order_id,
        ['status'=>'Awaiting Approval'],
        ['status'=>'Cancelled','reason'=>$reason]
    );
    do_action('hm_notify_dispenser_order_denied', $order_id, $order->dispenser_id, $order->patient_id, $reason);

    wp_send_json_success(['order_id'=>$order_id, 'new_status'=>'Cancelled']);
}
