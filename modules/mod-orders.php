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
 * HearMed Portal — Module: Orders
 * Handles: Order creation modal, order status page, AJAX handlers
 * Blueprint 01 — Section 1 (Order Creation Modal) + Section 2 (Order Status Page)
 *
 * @package HearMed_Calendar
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ---------------------------------------------------------------------------
// Shortcode: [hearmed_order_status]
// ---------------------------------------------------------------------------
add_shortcode( 'hearmed_order_status', 'hm_render_order_status_page' );

function hm_render_order_status_page() {
    if ( ! is_user_logged_in() ) {
        return '<div id="hm-app"><p class="hm-text-muted">Please log in to view orders.</p></div>';
    }
    ob_start();
    ?>
    <div id="hm-app" class="hm-admin">
        <?php hm_render_order_modal(); ?>

        <div class="hm-page-header">
            <div>
                <h1 class="hm-page-title">Order Status</h1>
                <p class="hm-page-subtitle">All orders across your clinics</p>
            </div>
            <?php if ( current_user_can('edit_posts') ) : ?>
            <button class="hm-btn hm-btn-teal" id="hm-order-new-btn">+ Create Order</button>
            <?php endif; ?>
        </div>

        <div class="hm-filter-bar" style="flex-wrap:wrap;gap:10px;">
            <div class="hm-search-bar">
                <input type="text" class="hm-search-input" id="hm-orders-search"
                    placeholder="Search order #…" autocomplete="off">
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;" id="hm-orders-status-pills">
                <button class="hm-btn hm-btn-sm hm-btn-teal hm-status-pill active" data-status="">All</button>
                <button class="hm-btn hm-btn-sm hm-btn-outline hm-status-pill" data-status="Awaiting Approval">Awaiting Approval</button>
                <button class="hm-btn hm-btn-sm hm-btn-outline hm-status-pill" data-status="Approved">Approved</button>
                <button class="hm-btn hm-btn-sm hm-btn-outline hm-status-pill" data-status="Ordered">Ordered</button>
                <button class="hm-btn hm-btn-sm hm-btn-outline hm-status-pill" data-status="Received">Received</button>
                <button class="hm-btn hm-btn-sm hm-btn-outline hm-status-pill" data-status="Fitted">Fitted</button>
                <button class="hm-btn hm-btn-sm hm-btn-outline hm-status-pill" data-status="Cancelled">Cancelled</button>
            </div>
            <?php if ( hm_user_can_finance() ) : ?>
            <select class="hm-dd" id="hm-orders-clinic" style="min-width:160px;">
                <option value="">All Clinics</option>
                <?php foreach ( HearMed_DB::get_results("SELECT id, clinic_name as post_title, id as ID FROM hearmed_reference.clinics WHERE is_active = true ORDER BY clinic_name") as $cl ) :
                    <option value="<?php echo esc_attr($cl->ID); ?>"><?php echo esc_html($cl->post_title); ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <input type="date" class="hm-inp" id="hm-orders-date-from" style="width:145px;">
            <input type="date" class="hm-inp" id="hm-orders-date-to"   style="width:145px;">
        </div>

        <div class="hm-card" style="margin-top:16px;">
            <div id="hm-orders-loading" class="hm-loading" style="display:none;"><div class="hm-spinner"></div></div>
            <div id="hm-orders-empty" class="hm-empty" style="display:none;">
                <div class="hm-empty-icon">📋</div>
                <div class="hm-empty-text">No orders found.</div>
            </div>
            <div id="hm-orders-table-wrap" style="overflow-x:auto;">
                <table class="hm-table" id="hm-orders-table">
                    <thead><tr>
                        <th>Patient</th><th>C-Number</th><th>Order #</th>
                        <th>Product</th><th>Total</th><th>PRSI</th>
                        <th>Status</th><th>Date</th><th>Actions</th>
                    </tr></thead>
                    <tbody id="hm-orders-tbody">
                        <tr><td colspan="9" style="text-align:center;padding:2rem;color:#94a3b8;">Loading…</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="hm-pagination" id="hm-orders-pagination" style="display:none;"></div>
        </div>

        <!-- Receive modal -->
        <div id="hm-receive-modal-bg" class="hm-modal-bg" style="display:none;">
            <div class="hm-modal" style="max-width:440px;">
                <div class="hm-modal-hd">
                    <h2 style="margin:0;font-size:1rem;font-weight:600;">Confirm Receipt</h2>
                    <button class="hm-modal-x" id="hm-receive-modal-close">&times;</button>
                </div>
                <div class="hm-modal-body">
                    <p style="color:#475569;">Confirm stock for order <strong id="hm-receive-order-num"></strong> has arrived in branch.</p>
                    <input type="hidden" id="hm-receive-order-id">
                </div>
                <div class="hm-modal-ft">
                    <button class="hm-btn hm-btn-outline" id="hm-receive-cancel">Cancel</button>
                    <button class="hm-btn hm-btn-teal"    id="hm-receive-confirm">Confirm Receipt</button>
                </div>
            </div>
        </div>

        <!-- Mark Ordered modal -->
        <div id="hm-mark-ordered-modal-bg" class="hm-modal-bg" style="display:none;">
            <div class="hm-modal" style="max-width:440px;">
                <div class="hm-modal-hd">
                    <h2 style="margin:0;font-size:1rem;font-weight:600;">Mark as Ordered</h2>
                    <button class="hm-modal-x" id="hm-mark-ordered-close">&times;</button>
                </div>
                <div class="hm-modal-body">
                    <p style="color:#475569;">Confirm order <strong id="hm-mark-ordered-num"></strong> has been placed with the supplier.</p>
                    <input type="hidden" id="hm-mark-ordered-id">
                </div>
                <div class="hm-modal-ft">
                    <button class="hm-btn hm-btn-outline" id="hm-mark-ordered-cancel">Cancel</button>
                    <button class="hm-btn hm-btn-teal"    id="hm-mark-ordered-confirm">Mark as Ordered</button>
                </div>
            </div>
        </div>

        <!-- Order detail modal -->
        <div id="hm-order-detail-modal-bg" class="hm-modal-bg" style="display:none;">
            <div class="hm-modal" style="max-width:680px;width:96%;">
                <div class="hm-modal-hd">
                    <h2 style="margin:0;font-size:1rem;font-weight:600;" id="hm-order-detail-title">Order Detail</h2>
                    <button class="hm-modal-x" id="hm-order-detail-close">&times;</button>
                </div>
                <div class="hm-modal-body" id="hm-order-detail-body">
                    <div class="hm-loading"><div class="hm-spinner"></div></div>
                </div>
            </div>
        </div>

    </div>
    <?php
    return ob_get_clean();
}

// ---------------------------------------------------------------------------
// Shortcode: [hearmed_awaiting_fitting]
// ---------------------------------------------------------------------------
add_shortcode( 'hearmed_awaiting_fitting', 'hm_render_awaiting_fitting_page' );

function hm_render_awaiting_fitting_page() {
    if ( ! is_user_logged_in() ) return '<p>Please log in.</p>';
    ob_start();
    ?>
    <div id="hm-app" class="hm-admin">
        <div class="hm-page-header">
            <div>
                <h1 class="hm-page-title">Awaiting Fitting</h1>
                <p class="hm-page-subtitle">Patients with received hearing aids not yet fitted</p>
            </div>
        </div>
        <div class="hm-filter-bar" style="flex-wrap:wrap;gap:10px;">
            <?php if ( hm_user_can_finance() ) : ?>
            <select class="hm-dd" id="hm-af-clinic" style="min-width:160px;">
                <option value="">All Clinics</option>
                <?php foreach ( HearMed_DB::get_results("SELECT id, clinic_name as post_title, id as ID FROM hearmed_reference.clinics WHERE is_active = true ORDER BY clinic_name") as $cl ) :
                    <option value="<?php echo esc_attr($cl->ID); ?>"><?php echo esc_html($cl->post_title); ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <input type="date" class="hm-inp" id="hm-af-date-from" style="width:145px;">
            <input type="date" class="hm-inp" id="hm-af-date-to"   style="width:145px;">
            <button class="hm-btn hm-btn-outline hm-btn-sm" id="hm-af-refresh">↻ Refresh</button>
        </div>
        <div class="hm-card" style="margin-top:16px;">
            <div id="hm-af-loading" class="hm-loading" style="display:none;"><div class="hm-spinner"></div></div>
            <div id="hm-af-empty" class="hm-empty" style="display:none;">
                <div class="hm-empty-icon">✅</div>
                <div class="hm-empty-text">No patients awaiting fitting.</div>
            </div>
            <div style="overflow-x:auto;">
                <table class="hm-table">
                    <thead><tr>
                        <th>Patient</th><th>C-Number</th><th>Clinic</th><th>Dispenser</th>
                        <th>Product</th><th>Total</th><th>PRSI</th><th>Fitting Date</th><th>Actions</th>
                    </tr></thead>
                    <tbody id="hm-af-tbody">
                        <tr><td colspan="9" style="text-align:center;padding:2rem;color:#94a3b8;">Loading…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pre-Fit Cancel modal -->
        <div id="hm-prefit-cancel-modal-bg" class="hm-modal-bg" style="display:none;">
            <div class="hm-modal" style="max-width:460px;">
                <div class="hm-modal-hd">
                    <h2 style="margin:0;font-size:1rem;font-weight:600;">Pre-Fit Cancellation</h2>
                    <button class="hm-modal-x" id="hm-prefit-cancel-close">&times;</button>
                </div>
                <div class="hm-modal-body">
                    <p style="color:#dc2626;font-size:.875rem;margin:0 0 14px;">⚠️ This will cancel the order and remove it from Awaiting Fitting.</p>
                    <input type="hidden" id="hm-prefit-order-id">
                    <div class="hm-form-group">
                        <label class="hm-label" for="hm-prefit-reason">Reason <span style="color:#dc2626;">*</span></label>
                        <textarea class="hm-textarea" id="hm-prefit-reason" rows="3" placeholder="Required…"></textarea>
                    </div>
                </div>
                <div class="hm-modal-ft">
                    <button class="hm-btn hm-btn-outline" id="hm-prefit-cancel-dismiss">Back</button>
                    <button class="hm-btn hm-btn-danger"  id="hm-prefit-cancel-confirm">Confirm Cancellation</button>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ---------------------------------------------------------------------------
// ORDER CREATION MODAL — HTML
// Called from patient profile Orders tab and from order status shortcode.
// ---------------------------------------------------------------------------
function hm_render_order_modal() {
    $user          = wp_get_current_user();
    $show_margin   = hm_user_can_finance(); // gross margin visible only to Finance/C-Level/Admin
    $nonce         = wp_create_nonce( 'hm_nonce' );

    // Pre-load clinics for the dispenser
    $clinics = HearMed_DB::get_results("SELECT id, clinic_name as post_title, id as ID FROM hearmed_reference.clinics WHERE is_active = true ORDER BY clinic_name");

    // VAT rate options (Ireland)
    $vat_rates = [
        '0'    => '0% (Exempt)',
        '13.5' => '13.5%',
        '23'   => '23% (Standard)',
    ];
    ?>
    <!-- ================================================================
         ORDER CREATION MODAL
         Opened by: HM.openOrderModal(patientId, patientName)
         ================================================================ -->
    <div id="hm-order-modal-bg" class="hm-modal-bg" style="display:none;" aria-modal="true" role="dialog" aria-labelledby="hm-order-modal-title">

        <div class="hm-modal" style="max-width:760px;width:96%;">

            <!-- Header -->
            <div class="hm-modal-hd">
                <h2 id="hm-order-modal-title" style="margin:0;font-size:1.05rem;font-weight:600;">
                    Create Order — <span id="hm-order-patient-name">Patient</span>
                </h2>
                <button class="hm-btn hm-btn-outline hm-btn-sm" id="hm-order-modal-close" aria-label="Close">&times;</button>
            </div>

            <!-- Body -->
            <div class="hm-modal-body">
                <input type="hidden" id="hm-order-patient-id" value="">
                <input type="hidden" id="hm-order-nonce" value="<?php echo esc_attr( $nonce ); ?>">

                <!-- ── Clinic selector (hidden from dispenser who has one clinic) ── -->
                <div class="hm-form-group" id="hm-order-clinic-wrap">
                    <label class="hm-label" for="hm-order-clinic">Clinic <span style="color:#e53e3e;">*</span></label>
                    <select class="hm-dd" id="hm-order-clinic" required>
                        <option value="">— Select clinic —</option>
                        <?php foreach ( $clinics as $clinic ) : ?>
                            <option value="<?php echo esc_attr( $clinic->ID ); ?>">
                                <?php echo esc_html( $clinic->post_title ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- ── PRSI eligibility ── -->
                <div class="hm-form-group" style="display:flex;align-items:center;gap:10px;">
                    <label class="hm-label" style="margin:0;" for="hm-order-prsi">
                        PRSI / HSE Grant Applicable
                    </label>
                    <label class="hm-tog" title="Toggle PRSI grant">
                        <input type="checkbox" id="hm-order-prsi" class="hm-tog-input">
                        <span class="hm-tog-track"></span>
                    </label>
                    <span id="hm-order-prsi-note" style="font-size:.8rem;color:#0BB4C4;display:none;">
                        €500 per hearing aid ear will be deducted from total.
                    </span>
                </div>

                <hr style="border:none;border-top:1px solid #e2e8f0;margin:12px 0 18px;">

                <!-- ── Line items ── -->
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                    <h3 style="font-size:.9rem;font-weight:600;margin:0;color:#1e293b;">Line Items</h3>
                    <button type="button" class="hm-btn hm-btn-outline hm-btn-sm" id="hm-order-add-line">
                        + Add Product
                    </button>
                </div>

                <div id="hm-order-lines">
                    <!-- Line items injected here by JS -->
                </div>

                <!-- ── Totals summary ── -->
                <div class="hm-card" style="margin-top:18px;background:#f8fafc;">
                    <div class="hm-card-body" style="padding:14px 18px;">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 24px;font-size:.875rem;">
                            <span style="color:#64748b;">Subtotal</span>
                            <span id="hm-total-subtotal" style="text-align:right;font-weight:500;">€0.00</span>

                            <span style="color:#64748b;">Discount</span>
                            <span id="hm-total-discount" style="text-align:right;font-weight:500;">−€0.00</span>

                            <span style="color:#64748b;">VAT</span>
                            <span id="hm-total-vat" style="text-align:right;font-weight:500;">€0.00</span>

                            <span style="color:#64748b;">PRSI Deduction</span>
                            <span id="hm-total-prsi" style="text-align:right;font-weight:500;color:#0BB4C4;">−€0.00</span>

                            <span style="color:#1e293b;font-weight:700;font-size:.95rem;border-top:1px solid #e2e8f0;padding-top:6px;">Grand Total</span>
                            <span id="hm-total-grand" style="text-align:right;font-weight:700;font-size:.95rem;color:#151B33;border-top:1px solid #e2e8f0;padding-top:6px;">€0.00</span>

                            <?php if ( $show_margin ) : ?>
                            <span style="color:#64748b;">Gross Margin</span>
                            <span id="hm-total-margin" style="text-align:right;font-weight:600;">—</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ── Order notes ── -->
                <div class="hm-form-group" style="margin-top:16px;">
                    <label class="hm-label" for="hm-order-notes">Order Notes (optional)</label>
                    <textarea class="hm-textarea" id="hm-order-notes" rows="3"
                        placeholder="Any special instructions, fitting preferences, urgency…"></textarea>
                </div>

                <!-- ── Duplicate warning (shown if flagged) ── -->
                <div id="hm-order-dup-warning" style="display:none;padding:10px 14px;background:#fff7ed;border:1px solid #f59e0b;border-radius:8px;font-size:.85rem;color:#92400e;margin-top:10px;">
                    ⚠️ <strong>Possible duplicate:</strong> A similar product was ordered for this patient within the last 90 days. The order will still be submitted and flagged for review.
                </div>

            </div><!-- /.hm-modal-body -->

            <!-- Footer -->
            <div class="hm-modal-ft">
                <button type="button" class="hm-btn hm-btn-outline" id="hm-order-cancel">Cancel</button>
                <button type="button" class="hm-btn hm-btn-teal" id="hm-order-submit">
                    <span id="hm-order-submit-label">Submit for Approval</span>
                    <span id="hm-order-submit-spinner" style="display:none;">Submitting…</span>
                </button>
            </div>

        </div><!-- /.hm-modal -->
    </div><!-- /.hm-modal-bg -->

    <!-- ── Line item TEMPLATE (cloned by JS, never displayed directly) ── -->
    <template id="hm-order-line-tpl">
        <div class="hm-order-line" data-line-index="" style="border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;margin-bottom:12px;background:#fff;position:relative;">

            <!-- Remove button -->
            <button type="button" class="hm-btn hm-btn-danger hm-btn-sm hm-line-remove"
                style="position:absolute;top:10px;right:12px;" title="Remove line">✕</button>

            <!-- Row 1: Product search -->
            <div class="hm-form-group" style="margin-bottom:10px;">
                <label class="hm-label">Product <span style="color:#e53e3e;">*</span></label>
                <div style="position:relative;">
                    <input type="text" class="hm-inp hm-line-product-search"
                        placeholder="Search by product name or manufacturer…"
                        autocomplete="off">
                    <div class="hm-line-product-results hm-product-dropdown"
                        style="display:none;position:absolute;z-index:9999;top:100%;left:0;right:0;
                               background:#fff;border:1px solid #e2e8f0;border-radius:8px;
                               max-height:220px;overflow-y:auto;box-shadow:0 8px 24px rgba(0,0,0,.12);">
                    </div>
                    <input type="hidden" class="hm-line-product-id" value="">
                    <input type="hidden" class="hm-line-product-name" value="">
                    <input type="hidden" class="hm-line-cost-price" value="0">
                </div>
                <!-- Selected product badge -->
                <div class="hm-line-product-selected" style="display:none;margin-top:6px;">
                    <span class="hm-badge hm-badge-teal hm-line-product-badge"></span>
                    <button type="button" class="hm-btn hm-btn-outline hm-btn-sm hm-line-product-clear"
                        style="margin-left:6px;">Change</button>
                </div>
            </div>

            <!-- Row 2: Ear / Qty / Price / Discount / VAT -->
            <div style="display:grid;grid-template-columns:1fr 80px 120px 120px 110px;gap:10px;align-items:end;">

                <div class="hm-form-group" style="margin:0;">
                    <label class="hm-label">Ear <span style="color:#e53e3e;">*</span></label>
                    <select class="hm-dd hm-line-ear" required>
                        <option value="">—</option>
                        <option value="Right">Right</option>
                        <option value="Left">Left</option>
                        <option value="Binaural">Binaural</option>
                    </select>
                </div>

                <div class="hm-form-group" style="margin:0;">
                    <label class="hm-label">Qty</label>
                    <input type="number" class="hm-inp hm-line-qty" value="1" min="1" step="1">
                </div>

                <div class="hm-form-group" style="margin:0;">
                    <label class="hm-label">Unit Price (€)</label>
                    <input type="number" class="hm-inp hm-line-price" value="" min="0" step="0.01" placeholder="0.00">
                </div>

                <div class="hm-form-group" style="margin:0;">
                    <label class="hm-label">Discount</label>
                    <div style="display:flex;gap:4px;">
                        <input type="number" class="hm-inp hm-line-discount" value="0" min="0" step="0.01"
                            placeholder="0" style="flex:1;min-width:0;">
                        <select class="hm-dd hm-line-discount-type" style="width:52px;padding:0 4px;">
                            <option value="eur">€</option>
                            <option value="pct">%</option>
                        </select>
                    </div>
                </div>

                <div class="hm-form-group" style="margin:0;">
                    <label class="hm-label">VAT Rate</label>
                    <select class="hm-dd hm-line-vat">
                        <?php foreach ( $vat_rates as $rate => $label ) : ?>
                            <option value="<?php echo esc_attr( $rate ); ?>"
                                <?php echo $rate === '0' ? 'selected' : ''; ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Row 3: Line total + margin (margin role-gated server-side via PHP class) -->
            <div style="display:flex;justify-content:flex-end;align-items:center;gap:18px;margin-top:10px;font-size:.8rem;">
                <?php if ( $show_margin ) : ?>
                <span style="color:#64748b;">
                    Margin: <strong class="hm-line-margin-pct">—</strong>
                </span>
                <?php endif; ?>
                <span style="color:#64748b;">
                    Line Total: <strong class="hm-line-total" style="color:#151B33;">€0.00</strong>
                </span>
            </div>

        </div>
    </template>
    <?php
}

// ---------------------------------------------------------------------------
// ROLE HELPER FUNCTIONS (define only if not already declared)
// ---------------------------------------------------------------------------
if ( ! function_exists( 'hm_user_can_finance' ) ) {
    function hm_user_can_finance() {
        return current_user_can('administrator')
            || current_user_can('hm_clevel')
            || current_user_can('hm_finance')
            || current_user_can('hm_admin');
    }
}
if ( ! function_exists( 'hm_user_can_approve' ) ) {
    function hm_user_can_approve() {
        return current_user_can('administrator')
            || current_user_can('hm_clevel')
            || current_user_can('hm_finance');
    }
}
if ( ! function_exists( 'hm_get_user_role' ) ) {
    function hm_get_user_role() {
        $user  = wp_get_current_user();
        $roles = ['administrator','hm_clevel','hm_finance','hm_admin',
                  'hm_dispenser','hm_reception','hm_ca','hm_scheme'];
        foreach ( $roles as $r ) {
            if ( in_array( $r, $user->roles, true ) ) return $r;
        }
        return 'subscriber';
    }
}

// ---------------------------------------------------------------------------
// AUDIT LOG HELPER (define only if not already declared)
// ---------------------------------------------------------------------------
if ( ! function_exists( 'hm_audit_log' ) ) {
    /**
     * Write to audit_log.
     * Matches actual live schema: user_id, action, entity_type, entity_id, details, ip_address, created_at
     * old/new values are merged into the details JSON blob.
     */
    function hm_audit_log( $user_id, $action, $entity_type, $entity_id, $old = null, $new = null ) {
        // PostgreSQL only - no $wpdb needed
        $t = HearMed_DB::table('audit_log');

        $details = [];
        if ( $old ) $details['old'] = $old;
        if ( $new ) $details['new'] = $new;
        $details['user_role'] = hm_get_user_role();

        HearMed_DB::insert( $t, [
            'cct_status'  => 'publish',
            'cct_author_id' => intval( $user_id ),
            'created_at' => current_time( 'mysql' ),
            'user_id'     => intval( $user_id ),
            'action'      => sanitize_key( $action ),
            'entity_type' => sanitize_key( $entity_type ),
            'entity_id'   => intval( $entity_id ),
            'details'     => wp_json_encode( $details ),
            'ip_address'  => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
        ] );
    }
}

// ---------------------------------------------------------------------------
// AJAX: Product search (autocomplete in order modal)
// Action: hm_search_products
// ---------------------------------------------------------------------------
add_action( 'wp_ajax_hm_search_products', 'hm_ajax_search_products' );

function hm_ajax_search_products() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'msg' => 'Login required' ] );
    }

    $q = sanitize_text_field( $_POST['q'] ?? '' );
    if ( strlen( $q ) < 2 ) {
        wp_send_json_success( [] );
    }

    // Search ha-product CPT by title + meta (manufacturer, range)
    $args = [
        'post_type'      => 'ha-product',
        'post_status'    => 'publish',
    }

    $product_id = intval( $_POST['product_id'] ?? 0 );
    $post       = get_post( $product_id );
    if ( ! $post || $post->post_type !== 'ha-product' ) {
        wp_send_json_error( [ 'msg' => 'Product not found' ] );
    }

    $retail_price = (float) /* USE PostgreSQL: Get from table columns */ /* get_post_meta( $product_id, 'retail_price', true );
    $cost_price   = (float) /* USE PostgreSQL: Get from table columns */ /* get_post_meta( $product_id, 'cost_price', true );
    $vat_rate     = (string) /* USE PostgreSQL: Get from table columns */ /* get_post_meta( $product_id, 'vat_rate', true );

    wp_send_json_success( [
        'id'           => $post->ID,
        'name'         => $post->post_title,
        'manufacturer' => /* USE PostgreSQL: Get from table columns */ /* get_post_meta( $product_id, 'manufacturer', true ),
        'style'        => /* USE PostgreSQL: Get from table columns */ /* get_post_meta( $product_id, 'style', true ),
        'range'        => /* USE PostgreSQL: Get from table columns */ /* get_post_meta( $product_id, 'hearmed_range', true ),
        'range_id'     => /* USE PostgreSQL: Get from table columns */ /* get_post_meta( $product_id, 'hearmed_range_id', true ),
        'retail_price' => $retail_price,
        'cost_price'   => hm_user_can_finance() ? $cost_price : null,
        'vat_rate'     => $vat_rate ?: '0',
    ] );
}

// ---------------------------------------------------------------------------
// AJAX: Create Order
// Action: hm_create_order
// ---------------------------------------------------------------------------
add_action( 'wp_ajax_hm_create_order', 'hm_ajax_create_order' );

function hm_ajax_create_order() {
    check_ajax_referer( 'hm_nonce', 'nonce' );

    // Role check — dispensers, finance, admins can create orders
    if ( ! current_user_can('edit_posts') ) {
        wp_send_json_error( [ 'msg' => 'Permission denied' ] );
        return;
    }
        // PostgreSQL only - no $wpdb needed
    $t_orders   = HearMed_DB::table('orders');
    $t_invoices = HearMed_DB::table('invoices');
    $t_audit    = HearMed_DB::table('audit_log');

    // ── Sanitise & validate inputs ──────────────────────────────────────────
    $patient_id      = intval( $_POST['patient_id'] ?? 0 );
    $clinic_id       = intval( $_POST['clinic_id'] ?? 0 );
    $prsi_applicable = intval( $_POST['prsi_applicable'] ?? 0 );
    $notes           = sanitize_textarea_field( $_POST['notes'] ?? '' );
    $raw_lines       = $_POST['line_items'] ?? [];

    if ( ! $patient_id ) {
        wp_send_json_error( [ 'msg' => 'Patient ID is required' ] );
        return;
    }
    if ( ! $clinic_id ) {
        wp_send_json_error( [ 'msg' => 'Please select a clinic' ] );
        return;
    }
    if ( empty( $raw_lines ) || ! is_array( $raw_lines ) ) {
        wp_send_json_error( [ 'msg' => 'Please add at least one product line' ] );
        return;
    }

    // ── Validate patient exists ─────────────────────────────────────────────
    $patient = get_post( $patient_id );
    if ( ! $patient || $patient->post_type !== 'patient' ) {
        wp_send_json_error( [ 'msg' => 'Invalid patient' ] );
        return;
    }

    // ── Process line items ──────────────────────────────────────────────────
    $line_items       = [];
    $subtotal         = 0.0;
    $total_discount   = 0.0;
    $total_vat        = 0.0;
    $total_cost       = 0.0;
    $duplicate_flag   = 0;
    $duplicate_reason = '';

    foreach ( $raw_lines as $raw ) {
        $product_id    = intval( $raw['product_id'] ?? 0 );
        $product_name  = sanitize_text_field( $raw['product_name'] ?? '' );
        $ear           = sanitize_text_field( $raw['ear'] ?? '' );
        $qty           = max( 1, intval( $raw['qty'] ?? 1 ) );
        $unit_price    = round( floatval( $raw['unit_price'] ?? 0 ), 2 );
        $discount_val  = round( floatval( $raw['discount'] ?? 0 ), 2 );
        $discount_type = in_array( $raw['discount_type'] ?? 'eur', ['eur','pct'], true )
                         ? $raw['discount_type'] : 'eur';
        $vat_rate      = round( floatval( $raw['vat_rate'] ?? 0 ), 1 );
        $cost_price    = round( floatval( $raw['cost_price'] ?? 0 ), 2 );

        if ( ! $product_id || ! in_array( $ear, ['Left','Right','Binaural'], true ) ) {
            wp_send_json_error( [ 'msg' => 'Each line item must have a product and ear selection' ] );
            return;
        }

        // Calculate discount in €
        if ( $discount_type === 'pct' ) {
            $discount_eur = round( ( $discount_val / 100 ) * $unit_price * $qty, 2 );
        } else {
            $discount_eur = $discount_val;
        }

        $line_net   = round( ( $unit_price * $qty ) - $discount_eur, 2 );
        $line_vat   = round( $line_net * ( $vat_rate / 100 ), 2 );
        $line_total = round( $line_net + $line_vat, 2 );

        $subtotal       += $unit_price * $qty;
        $total_discount += $discount_eur;
        $total_vat      += $line_vat;
        $total_cost     += $cost_price * $qty;

        $line_items[] = [
            'product_id'    => $product_id,
            'product_name'  => $product_name,
            'ear'           => $ear,
            'qty'           => $qty,
            'unit_price'    => $unit_price,
            'discount'      => $discount_eur,
            'discount_type' => $discount_type,
            'vat_rate'      => $vat_rate,
            'line_total'    => $line_total,
            'cost_price'    => $cost_price,
        ];

        // ── Duplicate detection: same patient + same/similar product within 90 days ──
        if ( ! $duplicate_flag ) {
            $existing = HearMed_DB::get_var( /* TODO: Convert to params array */ "SELECT COUNT(*) FROM {$t_invoices}
                 WHERE patient_id = %d
                   AND created_at > DATE_SUB(NOW(), INTERVAL 90 DAY)
                   AND JSON_SEARCH(line_items, 'one', %s) IS NOT NULL",
                $patient_id,
                $product_name
            ) );
            if ( $existing > 0 ) {
                $duplicate_flag   = 1;
                $duplicate_reason = 'Similar product (' . $product_name . ') ordered within the last 90 days';
            }
        }
    }

    // ── PRSI deduction ─────────────────────────────────────────────────────
    $prsi_amount = 0.0;
    if ( $prsi_applicable ) {
        // Count distinct ears across all line items
        $ears = array_unique( array_column( $line_items, 'ear' ) );
        $ear_count = 0;
        foreach ( $ears as $e ) {
            $ear_count += ( $e === 'Binaural' ) ? 2 : 1;
        }
        $prsi_amount = min( 500.0 * $ear_count, 1000.0 ); // max €1,000 (2 ears)
    }

    // ── Gross margin ───────────────────────────────────────────────────────
    $grand_total_pre_prsi = round( $subtotal - $total_discount + $total_vat, 2 );
    $grand_total          = max( 0, round( $grand_total_pre_prsi - $prsi_amount, 2 ) );
    $gross_margin_pct     = 0.0;
    if ( $grand_total > 0 && $total_cost > 0 ) {
        $gross_margin_pct = round( ( ( $grand_total - $total_cost ) / $grand_total ) * 100, 2 );
    }

    // ── Generate order number ──────────────────────────────────────────────
    $clinic_code  = strtoupper( substr( /* USE PostgreSQL: Get from table columns */ /* get_post_meta( $clinic_id, 'clinic_code', true ) ?: 'HM', 0, 3 ) );
    $ym           = date( 'Ym' );
    $count        = (int) HearMed_DB::get_var( "SELECT COUNT(*) FROM {$t_orders} WHERE order_number LIKE 'ORD-{$clinic_code}-{$ym}%'" ) + 1;
    $order_number = sprintf( 'ORD-%s-%s-%04d', $clinic_code, $ym, $count );

    // ── Insert order ───────────────────────────────────────────────────────
    $current_user_id = get_current_user_id();
    $now             = current_time( 'mysql' );

    $inserted = HearMed_DB::insert( $t_orders, [
        'cct_status'           => 'publish',
        'cct_author_id'        => $current_user_id,
        'created_at'          => $now,
        'updated_at'         => $now,
        'order_number'         => $order_number,
        'patient_id'           => $patient_id,
        'dispenser_id'         => $current_user_id,
        'clinic_id'            => $clinic_id,
        'order_date'           => current_time( 'Y-m-d' ),
        'status'               => 'Awaiting Approval',
        'line_items'           => wp_json_encode( $line_items ),
        'subtotal'             => $subtotal,
        'discount_total'       => $total_discount,
        'vat_total'            => $total_vat,
        'grand_total'          => $grand_total,
        'prsi_applicable'      => $prsi_applicable,
        'prsi_amount'          => $prsi_amount,
        'gross_margin_percent' => $gross_margin_pct,
        'notes'                => $notes,
    ] );

    if ( ! $inserted ) {
        wp_send_json_error( [ 'msg' => 'Database error — order could not be saved. Please try again.' ] );
        return;
    }

    $order_id = $last_insert_id /* TODO: Get this from HearMed_DB::insert() return value */;

    // ── Audit log ──────────────────────────────────────────────────────────
    hm_audit_log(
        $current_user_id,
        'create',
        'order',
        $order_id,
        null,
        [
            'order_number' => $order_number,
            'patient_id'   => $patient_id,
            'clinic_id'    => $clinic_id,
            'status'       => 'Awaiting Approval',
            'grand_total'  => $grand_total,
            'line_count'   => count( $line_items ),
        ]
    );

    // ── Duplicate flag — update order record + notify ─────────────────────
    if ( $duplicate_flag ) {
        HearMed_DB::update( $t_orders,
            [ 'flagged' => 1, 'flag_reason' => $duplicate_reason ],
            [ 'id' => $order_id ]
        );
        hm_audit_log( $current_user_id, 'flag', 'order', $order_id, null,
            [ 'reason' => $duplicate_reason ] );

        // Notify C-Level + Finance (uses whatever notification system exists)
        do_action( 'hm_notify_duplicate_order', $order_id, $duplicate_reason );
    }

    // ── Notify approvers (Rauri + Rose + Finance) ─────────────────────────
    do_action( 'hm_notify_order_created', $order_id, $patient_id, $current_user_id );

    wp_send_json_success( [
        'order_id'       => $order_id,
        'order_number'   => $order_number,
        'duplicate_flag' => $duplicate_flag,
        'grand_total'    => $grand_total,
    ] );
}

// ---------------------------------------------------------------------------
// AJAX: Get orders list
// ---------------------------------------------------------------------------
add_action( 'wp_ajax_hm_get_orders', 'hm_ajax_get_orders' );

function hm_ajax_get_orders() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $user     = wp_get_current_user();
    $allowed  = ['administrator','hm_clevel','hm_admin','hm_finance','hm_dispenser','hm_reception','hm_ca'];
    if ( ! array_intersect( $allowed, (array)$user->roles ) ) {
        wp_send_json_error(['msg' => 'Access denied']); return;
    }
        // PostgreSQL only - no $wpdb needed
    $t = HearMed_DB::table('orders');

    $is_admin    = hm_user_can_finance();
    $status      = sanitize_text_field( $_POST['status']    ?? '' );
    $clinic_id   = intval( $_POST['clinic_id']  ?? 0 );
    $search      = sanitize_text_field( $_POST['search']    ?? '' );
    $date_from   = sanitize_text_field( $_POST['date_from'] ?? '' );
    $date_to     = sanitize_text_field( $_POST['date_to']   ?? '' );
    $page        = max( 1, intval( $_POST['paged'] ?? 1 ) );
    $per_page    = 25;

    $where  = ['1=1'];
    $params = [];

    // Dispensers see only their clinic
    if ( ! $is_admin && in_array('hm_dispenser', (array)$user->roles, true) ) {
        $disp = HearMed_DB::get_results("SELECT s.id, s.first_name || ' ' || s.last_name as post_title, s.id as ID FROM hearmed_reference.staff s WHERE s.is_active = true ORDER BY s.last_name, s.first_name"); // Converted from /* USE PostgreSQL: HearMed_DB::get_results() */ /* get_posts() to PostgreSQL
            'meta_query'=>[['key'=>'user_account','value'=>$user->ID]]]);
        if ( $disp ) {
            $cid = intval(/* USE PostgreSQL: Get from table columns */ /* get_post_meta($disp[0]->ID, 'clinic_id', true));
            if ( $cid ) { $where[] = 'o.clinic_id = %d'; $params[] = $cid; }
        }
    }

    if ( $status )    { $where[] = 'o.status = %s';           $params[] = $status; }
    if ( $clinic_id ) { $where[] = 'o.clinic_id = %d';        $params[] = $clinic_id; }
    if ( $date_from ) { $where[] = 'DATE(o.created_at) >= %s'; $params[] = $date_from; }
    if ( $date_to )   { $where[] = 'DATE(o.created_at) <= %s'; $params[] = $date_to; }
    if ( $search )    { $where[] = 'o.order_number LIKE %s';   $params[] = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%'; }

    $w      = implode( ' AND ', $where );
    $offset = ($page - 1) * $per_page;

    // Use JOIN on postmeta for patient name/number — correct architecture per DB note
    $base_sql = "FROM `{$t}` o
        LEFT JOIN /* TODO: Convert to PostgreSQL table */ wp_postmeta pm1 ON pm1.post_id = o.patient_id AND pm1.meta_key = 'first_name'
        LEFT JOIN /* TODO: Convert to PostgreSQL table */ wp_postmeta pm2 ON pm2.post_id = o.patient_id AND pm2.meta_key = 'last_name'
        LEFT JOIN /* TODO: Convert to PostgreSQL table */ wp_postmeta pm3 ON pm3.post_id = o.patient_id AND pm3.meta_key = 'patient_number'
        WHERE {$w}";

    $count_sql  = "SELECT COUNT(*) {$base_sql}";
    $select_sql = "SELECT o.*, pm1.meta_value as patient_first, pm2.meta_value as patient_last, pm3.meta_value as patient_number {$base_sql} ORDER BY o.created_at DESC LIMIT %d OFFSET %d";

    $count_params  = $params;
    $select_params = array_merge($params, [$per_page, $offset]);

    $total = $count_params
        ? (int)HearMed_DB::get_var( $count_sql, $count_params)
        : (int)HearMed_DB::get_var($count_sql);

    $rows = $select_params
        ? HearMed_DB::get_results( $select_sql, $select_params)
        : HearMed_DB::get_results($select_sql);

    $orders = [];
    foreach ( $rows as $r ) {
        $patient_name = trim( ($r->patient_first ?? '') . ' ' . ($r->patient_last ?? '') );
        $line_items   = json_decode( $r->line_items ?: '[]', true );
        $prod_parts   = [];
        foreach ( $line_items as $li ) {
            $prod_parts[] = ($li['product_name'] ?? '') . ' (' . ($li['ear'] ?? '') . ')';
        }
        $orders[] = [
            'id'             => (int)$r->id,
            'order_number'   => $r->order_number,
            'patient_id'     => (int)$r->patient_id,
            'patient_name'   => $patient_name,
            'patient_number' => $r->patient_number ?? '',
            'clinic_name'    => get_the_title($r->clinic_id),
            'product_summary'=> implode(', ', $prod_parts),
            'grand_total'    => (float)$r->grand_total,
            'prsi_applicable'=> (int)$r->prsi_applicable,
            'prsi_amount'    => (float)$r->prsi_amount,
            'status'         => $r->status,
            'flagged'        => (int)($r->flagged ?? 0),
            'created_at'     => $r->created_at,
        ];
    }

    wp_send_json_success(['orders' => $orders, 'total' => $total, 'per_page' => $per_page, 'page' => $page]);
}

// ---------------------------------------------------------------------------
// AJAX: Update order status (Ordered / Received)
// ---------------------------------------------------------------------------
add_action( 'wp_ajax_hm_update_order_status', 'hm_ajax_update_order_status' );

function hm_ajax_update_order_status() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! current_user_can('edit_posts') ) { wp_send_json_error(['msg'=>'Permission denied']); return; }
        // PostgreSQL only - no $wpdb needed
    $t       = HearMed_DB::table('orders');
    $id      = intval( $_POST['order_id'] ?? 0 );
    $new_st  = sanitize_text_field( $_POST['new_status'] ?? '' );
    $uid     = get_current_user_id();
    $now     = current_time('mysql');

    if ( ! in_array($new_st, ['Ordered','Received'], true) ) {
        wp_send_json_error(['msg'=>'Invalid status']); return;
    }
    if ( $new_st === 'Ordered' && ! hm_user_can_finance() ) {
        wp_send_json_error(['msg'=>'You do not have permission to mark orders as Ordered']); return;
    }

    $old = HearMed_DB::get_row( /* TODO: Convert to params array */ "SELECT * FROM `{$t}` WHERE id = %d", $id);
    if ( ! $old ) { wp_send_json_error(['msg'=>'Order not found']); return; }

    // Use real column names: ordered_date / received_date / updated_at
    $data = ['status' => $new_st, 'updated_at' => $now];
    if ( $new_st === 'Ordered'  ) { $data['ordered_by']   = $uid; $data['ordered_date']  = $now; }
    if ( $new_st === 'Received' ) { $data['received_by']  = $uid; $data['received_date'] = $now; }

    HearMed_DB::update($t, $data, ['id' => $id]);
    hm_audit_log($uid, 'status_change', 'order', $id, ['status'=>$old->status], ['status'=>$new_st]);

    // On Received → insert into awaiting_fitting
    if ( $new_st === 'Received' ) {
        $t_af  = HearMed_DB::table('awaiting_fitting');
        $exists = HearMed_DB::get_var( /* TODO: Convert to params array */ "SELECT COUNT(*) FROM `{$t_af}` WHERE order_id = %d", $param);
        if ( ! $exists ) {
            $lines = json_decode($old->line_items ?: '[]', true);
            $desc  = implode(', ', array_map(fn($l) => ($l['product_name']??'').' ('.($l['ear']??')'), $param);
            HearMed_DB::insert($t_af, [
                'cct_status'          => 'publish',
                'cct_author_id'       => $uid,
                'created_at'         => $now,
                'patient_id'          => $old->patient_id,
                'order_id'            => $id,
                'clinic_id'           => $old->clinic_id,
                'dispenser_id'        => $old->dispenser_id,
                'product_description' => $desc,
                'total_price'         => $old->grand_total,
                'prsi_applicable'     => $old->prsi_applicable,
                'status'              => 'Awaiting',
            ]);
        }
    }

    do_action('hm_notify_order_status_change', $id, $old->status, $new_st, $uid);
    wp_send_json_success(['order_id'=>$id, 'new_status'=>$new_st]);
}

// ---------------------------------------------------------------------------
// AJAX: Get single order detail
// ---------------------------------------------------------------------------
add_action( 'wp_ajax_hm_get_order_detail', 'hm_ajax_get_order_detail' );

function hm_ajax_get_order_detail() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) { wp_send_json_error(['msg'=>'Login required']); return; }
        // PostgreSQL only - no $wpdb needed
    $t  = HearMed_DB::table('orders');
    $id = intval( $_POST['order_id'] ?? 0 );

    $r = HearMed_DB::get_row( /* TODO: Convert to params array */ "SELECT o.*,
         pm1.meta_value as patient_first, pm2.meta_value as patient_last,
         pm3.meta_value as patient_number
         FROM `{$t}` o
         LEFT JOIN /* TODO: Convert to PostgreSQL table */ wp_postmeta pm1 ON pm1.post_id = o.patient_id AND pm1.meta_key = 'first_name'
         LEFT JOIN /* TODO: Convert to PostgreSQL table */ wp_postmeta pm2 ON pm2.post_id = o.patient_id AND pm2.meta_key = 'last_name'
         LEFT JOIN /* TODO: Convert to PostgreSQL table */ wp_postmeta pm3 ON pm3.post_id = o.patient_id AND pm3.meta_key = 'patient_number'
         WHERE o.id = %d", $id
    );
    if ( ! $r ) { wp_send_json_error(['msg'=>'Order not found']); return; }

    wp_send_json_success([
        'id'                   => (int)$r->id,
        'order_number'         => $r->order_number,
        'patient_name'         => trim(($r->patient_first??'').' '.($r->patient_last??'')),
        'patient_number'       => $r->patient_number ?? '',
        'clinic_name'          => get_the_title($r->clinic_id),
        'dispenser_name'       => get_the_title($r->dispenser_id),
        'status'               => $r->status,
        'line_items'           => json_decode($r->line_items ?: '[]', true),
        'subtotal'             => (float)$r->subtotal,
        'discount'             => (float)$r->discount_total,
        'vat_total'            => (float)$r->vat_total,
        'grand_total'          => (float)$r->grand_total,
        'prsi_applicable'      => (int)$r->prsi_applicable,
        'prsi_amount'          => (float)$r->prsi_amount,
        'gross_margin_percent' => hm_user_can_finance() ? (float)$r->gross_margin_percent : null,
        'notes'                => $r->notes,
        'created_at'           => $r->created_at,
        'approved_date'        => $r->approved_date,
        'ordered_date'         => $r->ordered_date,
        'received_date'        => $r->received_date,
        'flagged'              => (int)($r->flagged ?? 0),
        'flag_reason'          => $r->flag_reason ?? '',
    ]);
}

// ---------------------------------------------------------------------------
// AJAX: Get awaiting fitting list
// ---------------------------------------------------------------------------
add_action( 'wp_ajax_hm_get_awaiting_fitting', 'hm_ajax_get_awaiting_fitting' );

function hm_ajax_get_awaiting_fitting() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) { wp_send_json_error(['msg'=>'Login required']); return; }
        // PostgreSQL only - no $wpdb needed
    $t_af    = HearMed_DB::table('awaiting_fitting');
    $clinic_id = intval( $_POST['clinic_id'] ?? 0 );

    $where  = ["af.status != 'Cancelled'"];
    $params = [];
    if ( $clinic_id ) { $where[] = 'af.clinic_id = %d'; $params[] = $clinic_id; }
    $w = implode(' AND ', $where);

    $sql = "SELECT af.*,
        pm1.meta_value as patient_first, pm2.meta_value as patient_last,
        pm3.meta_value as patient_number
        FROM `{$t_af}` af
        LEFT JOIN /* TODO: Convert to PostgreSQL table */ wp_postmeta pm1 ON pm1.post_id = af.patient_id AND pm1.meta_key = 'first_name'
        LEFT JOIN /* TODO: Convert to PostgreSQL table */ wp_postmeta pm2 ON pm2.post_id = af.patient_id AND pm2.meta_key = 'last_name'
        LEFT JOIN /* TODO: Convert to PostgreSQL table */ wp_postmeta pm3 ON pm3.post_id = af.patient_id AND pm3.meta_key = 'patient_number'
        WHERE {$w}
        ORDER BY af.id DESC";

    $rows = $params ? HearMed_DB::get_results( /* TODO: Convert to params array */ $sql, $params) : HearMed_DB::get_results($sql);

    $result = [];
    foreach ( $rows as $r ) {
        $result[] = [
            'id'                  => (int)$r->id,
            'order_id'            => (int)$r->order_id,
            'patient_id'          => (int)$r->patient_id,
            'patient_name'        => trim(($r->patient_first??'').' '.($r->patient_last??'')),
            'patient_number'      => $r->patient_number ?? '',
            'clinic_name'         => get_the_title($r->clinic_id),
            'dispenser_name'      => get_the_title($r->dispenser_id),
            'product_description' => $r->product_description,
            'total_price'         => (float)$r->total_price,
            'prsi_applicable'     => (int)$r->prsi_applicable,
            'fitting_date'        => $r->fitting_date,
            'status'              => $r->status,
        ];
    }
    wp_send_json_success($result);
}

// ---------------------------------------------------------------------------
// AJAX: Pre-fit cancel
// ---------------------------------------------------------------------------
add_action( 'wp_ajax_hm_prefit_cancel', 'hm_ajax_prefit_cancel' );

function hm_ajax_prefit_cancel() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! current_user_can('edit_posts') ) { wp_send_json_error(['msg'=>'Permission denied']); return; }
        // PostgreSQL only - no $wpdb needed
    $t_orders = HearMed_DB::table('orders');
    $t_af     = HearMed_DB::table('awaiting_fitting');
    $order_id = intval( $_POST['order_id'] ?? 0 );
    $reason   = sanitize_textarea_field( $_POST['reason'] ?? '' );
    $uid      = get_current_user_id();
    $now      = current_time('mysql');
    $today    = current_time('Y-m-d');

    if ( ! $order_id || ! $reason ) { wp_send_json_error(['msg'=>'Order and reason required']); return; }

    // Use real column names: cancellation_type, cancellation_reason, cancellation_date, updated_at
    HearMed_DB::update($t_orders, [
        'status'               => 'Cancelled',
        'cancellation_type'    => 'pre_fit',
        'cancellation_reason'  => $reason,
        'cancellation_date'    => $now,
        'updated_at'         => $now,
    ], ['id' => $order_id]);

    // awaiting_fitting uses pre_fit_cancel_reason / pre_fit_cancel_date
    HearMed_DB::update($t_af, [
        'status'                => 'Cancelled',
        'pre_fit_cancel_reason' => $reason,
        'pre_fit_cancel_date'   => $today,
        'updated_at'          => $now,
    ], ['order_id' => $order_id]);

    hm_audit_log($uid, 'prefit_cancel', 'order', $order_id, null, ['reason'=>$reason]);
    do_action('hm_notify_prefit_cancel', $order_id, $reason, $uid);

    wp_send_json_success(['order_id' => $order_id]);
}
