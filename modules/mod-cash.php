<?php
/**
 * HearMed Cash / Tender Management Module
 *
 * Shortcodes: [hearmed_cash] (My Tender), [hearmed_till] (Staff Tenders admin)
 *
 * @package HearMed_Portal
 * @since   5.3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─────────────────────────────────────────────────────────────────────────────
// Shortcode entry points (used by class-hearmed-router.php shortcode_map)
// ─────────────────────────────────────────────────────────────────────────────

function hm_cash_render() {
    if ( ! PortalAuth::is_logged_in() ) return;
    echo HearMed_Cash::render( 'dashboard' );
}

function hm_till_render() {
    if ( ! PortalAuth::is_logged_in() ) return;
    echo HearMed_Cash::render( 'admin' );
}

// ─────────────────────────────────────────────────────────────────────────────
// AJAX registrations
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_hm_activate_tender',    [ 'HearMed_Cash', 'ajax_activate_tender' ] );
add_action( 'wp_ajax_hm_lodge_money',        [ 'HearMed_Cash', 'ajax_lodge_money' ] );
add_action( 'wp_ajax_hm_petty_cash',         [ 'HearMed_Cash', 'ajax_petty_cash' ] );
add_action( 'wp_ajax_hm_confirm_lodgment',   [ 'HearMed_Cash', 'ajax_confirm_lodgment' ] );
add_action( 'wp_ajax_hm_upload_receipt',     [ 'HearMed_Cash', 'ajax_upload_receipt' ] );

// ─────────────────────────────────────────────────────────────────────────────
// Class
// ─────────────────────────────────────────────────────────────────────────────

class HearMed_Cash {

    // ═════════════════════════════════════════════════════════════════════════
    // ROUTER
    // ═════════════════════════════════════════════════════════════════════════

    public static function render( $view = 'dashboard' ) {
        if ( ! PortalAuth::is_logged_in() ) return '';

        $action = sanitize_key( $_GET['hm_action'] ?? '' );

        ob_start();

        if ( $view === 'admin' ) {
            if ( $action === 'lodgments' ) {
                self::render_lodgments();
            } else {
                self::render_admin();
            }
        } else {
            // Dashboard view
            $staff_id = PortalAuth::staff_id();
            $tender   = self::get_tender( $staff_id );

            if ( ! $tender ) {
                self::render_setup();
            } else {
                switch ( $action ) {
                    case 'lodge':   self::render_lodge( $tender ); break;
                    case 'petty':   self::render_petty( $tender ); break;
                    case 'history': self::render_history( $tender ); break;
                    default:        self::render_dashboard( $tender ); break;
                }
            }
        }

        return ob_get_clean();
    }

    // ═════════════════════════════════════════════════════════════════════════
    // HELPERS
    // ═════════════════════════════════════════════════════════════════════════

    private static function get_tender( $staff_id ) {
        $db = HearMed_DB::instance();
        return $db->get_row(
            "SELECT t.*, s.first_name, s.last_name, c.clinic_name
             FROM hearmed_core.staff_tenders t
             JOIN hearmed_reference.staff s ON s.id = t.staff_id
             LEFT JOIN hearmed_reference.clinics c ON c.id = t.clinic_id
             WHERE t.staff_id = $1",
            [ $staff_id ]
        );
    }

    private static function entry_type_label( $type ) {
        $labels = [
            'payment_in'      => 'Payment In',
            'opening_float'   => 'Opening Float',
            'float_topup'     => 'Float Top-Up',
            'lodgment_cash'   => 'Cash Lodgment',
            'lodgment_cheque' => 'Cheque Lodgment',
            'petty_cash'      => 'Petty Cash',
            'till_float'      => 'Till Float',
            'adjustment'      => 'Adjustment',
        ];
        return $labels[ $type ] ?? ucwords( str_replace( '_', ' ', $type ) );
    }

    private static function entry_badge_class( $type ) {
        $map = [
            'payment_in'      => 'hm-badge--green',
            'opening_float'   => 'hm-badge--grey',
            'float_topup'     => 'hm-badge--grey',
            'lodgment_cash'   => 'hm-badge--amber',
            'lodgment_cheque' => 'hm-badge--blue',
            'petty_cash'      => 'hm-badge--red',
            'till_float'      => 'hm-badge--grey',
            'adjustment'      => 'hm-badge--amber',
        ];
        return $map[ $type ] ?? 'hm-badge--grey';
    }

    private static function money( $val ) {
        return '€' . number_format( floatval( $val ), 2 );
    }

    private static function dashboard_url() {
        return HearMed_Utils::page_url( 'cash' ) ?: home_url( '/my-tender/' );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // RENDER: DASHBOARD
    // ═════════════════════════════════════════════════════════════════════════

    private static function render_dashboard( $tender ) {
        $db       = HearMed_DB::instance();
        $base_url = self::dashboard_url();

        $entries = $db->get_results(
            "SELECT te.*, inv.invoice_number,
                    CONCAT(p.first_name, ' ', p.last_name) AS patient_name
             FROM hearmed_core.tender_entries te
             LEFT JOIN hearmed_core.invoices inv ON inv.id = te.invoice_id
             LEFT JOIN hearmed_core.payments pay ON pay.id = te.payment_id
             LEFT JOIN hearmed_core.patients p ON p.id = pay.patient_id
             WHERE te.tender_id = $1
             ORDER BY te.created_at DESC
             LIMIT 15",
            [ $tender->id ]
        );

        $pending = (int) $db->get_var(
            "SELECT count(*) FROM hearmed_core.tender_lodgments WHERE tender_id = $1 AND status = 'pending'",
            [ $tender->id ]
        );

        $staff_name  = trim( ( $tender->first_name ?? '' ) . ' ' . ( $tender->last_name ?? '' ) );
        $clinic_name = $tender->clinic_name ?? 'Unassigned';

        ?>
        <div class="hm-content">
            <a href="<?php echo esc_url( home_url( '/calendar/' ) ); ?>" class="hm-back">Back to Portal</a>

            <div class="hm-page-header">
                <div>
                    <h1 class="hm-page-title">My Tender</h1>
                    <p style="margin:0;font-size:13px;color:var(--hm-grey,#64748b);"><?php echo esc_html( $staff_name ); ?> · <?php echo esc_html( $clinic_name ); ?></p>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="hm-cash-kpi">
                <div class="hm-card" style="border-top:3px solid var(--hm-green,#16a34a);">
                    <div style="padding:16px;text-align:center;">
                        <div style="font-size:24px;font-weight:700;color:<?php echo floatval( $tender->cash_balance ) > 0 ? 'var(--hm-green,#16a34a)' : 'var(--hm-navy,#151B33)'; ?>;">
                            <?php echo self::money( $tender->cash_balance ); ?>
                        </div>
                        <div style="font-size:12px;color:var(--hm-grey,#64748b);text-transform:uppercase;letter-spacing:.5px;margin-top:4px;">Cash Balance</div>
                    </div>
                </div>
                <div class="hm-card" style="border-top:3px solid var(--hm-teal,#0BB4C4);">
                    <div style="padding:16px;text-align:center;">
                        <div style="font-size:24px;font-weight:700;color:var(--hm-teal,#0BB4C4);">
                            <?php echo self::money( $tender->cheque_balance ); ?>
                        </div>
                        <div style="font-size:12px;color:var(--hm-grey,#64748b);text-transform:uppercase;letter-spacing:.5px;margin-top:4px;">Cheque Balance</div>
                    </div>
                </div>
                <div class="hm-card" style="border-top:3px solid var(--hm-border,#e2e8f0);">
                    <div style="padding:16px;text-align:center;">
                        <div style="font-size:24px;font-weight:700;color:var(--hm-grey,#64748b);">
                            <?php echo self::money( $tender->float_amount ); ?>
                        </div>
                        <div style="font-size:12px;color:var(--hm-grey,#64748b);text-transform:uppercase;letter-spacing:.5px;margin-top:4px;">Float Amount</div>
                    </div>
                </div>
                <div class="hm-card" style="border-top:3px solid <?php echo $pending > 0 ? 'var(--hm-amber,#f59e0b)' : 'var(--hm-border,#e2e8f0)'; ?>;">
                    <div style="padding:16px;text-align:center;">
                        <div style="font-size:24px;font-weight:700;color:<?php echo $pending > 0 ? 'var(--hm-amber,#f59e0b)' : 'var(--hm-navy,#151B33)'; ?>;">
                            <?php echo intval( $pending ); ?>
                        </div>
                        <div style="font-size:12px;color:var(--hm-grey,#64748b);text-transform:uppercase;letter-spacing:.5px;margin-top:4px;">Pending Lodgments</div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="hm-cash-actions">
                <a href="<?php echo esc_url( $base_url . '?hm_action=lodge' ); ?>" class="hm-btn hm-btn--primary">Lodge Money</a>
                <a href="<?php echo esc_url( $base_url . '?hm_action=petty' ); ?>" class="hm-btn hm-btn--secondary">Log Expense</a>
                <a href="<?php echo esc_url( $base_url . '?hm_action=history' ); ?>" class="hm-btn hm-btn--ghost">History</a>
            </div>

            <!-- Recent Activity -->
            <div class="hm-card">
                <div style="padding:16px 20px 8px;"><h3 style="margin:0;font-size:15px;font-weight:700;color:var(--hm-navy,#151B33);">Recent Activity</h3></div>
                <?php if ( empty( $entries ) ) : ?>
                    <div class="hm-empty"><div class="hm-empty-text">No entries yet</div></div>
                <?php else : ?>
                    <table class="hm-table">
                        <thead><tr>
                            <th>Date/Time</th><th>Type</th><th>Description</th><th style="text-align:right">Amount</th>
                            <th style="text-align:right">Cash</th><th style="text-align:right">Cheques</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ( $entries as $e ) :
                            $is_in   = ( $e->direction ?? '' ) === 'in';
                            $amt_cls = $is_in ? 'hm-amount-in' : 'hm-amount-out';
                            $prefix  = $is_in ? '+' : '-';
                            $desc    = '';
                            if ( ! empty( $e->invoice_id ) && ! empty( $e->patient_name ) ) {
                                $desc = trim( $e->patient_name );
                                if ( ! empty( $e->invoice_number ) ) $desc .= ' — ' . $e->invoice_number;
                            }
                            if ( ! $desc && ! empty( $e->notes ) ) $desc = $e->notes;
                            if ( ! $desc ) $desc = self::entry_type_label( $e->entry_type );
                        ?>
                            <tr>
                                <td style="white-space:nowrap;font-size:12px;"><?php echo esc_html( date( 'd/m/Y H:i', strtotime( $e->created_at ) ) ); ?></td>
                                <td><span class="hm-badge hm-badge--sm <?php echo self::entry_badge_class( $e->entry_type ); ?>"><?php echo esc_html( self::entry_type_label( $e->entry_type ) ); ?></span></td>
                                <td><?php echo esc_html( $desc ); ?></td>
                                <td style="text-align:right" class="<?php echo $amt_cls; ?>"><?php echo $prefix . self::money( $e->amount ); ?></td>
                                <td style="text-align:right"><?php echo self::money( $e->running_cash ); ?></td>
                                <td style="text-align:right"><?php echo self::money( $e->running_cheque ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    // ═════════════════════════════════════════════════════════════════════════
    // RENDER: SETUP (first-time activation)
    // ═════════════════════════════════════════════════════════════════════════

    private static function render_setup() {
        $db      = HearMed_DB::instance();
        $clinics = $db->get_results( "SELECT id, clinic_name FROM hearmed_reference.clinics WHERE is_active = true ORDER BY clinic_name" ) ?: [];

        ?>
        <div class="hm-content">
            <div class="hm-card" style="max-width:520px;margin:40px auto;">
                <div style="padding:24px;">
                    <h2 style="margin:0 0 4px;font-size:22px;color:var(--hm-navy,#151B33);">Activate Your Tender</h2>
                    <p style="margin:0 0 20px;font-size:13px;color:var(--hm-grey,#64748b);">
                        A tender tracks your cash and cheque holdings. You'll record lodgments and petty cash expenses against it.
                    </p>

                    <div class="hm-form-group">
                        <label class="hm-label">Clinic *</label>
                        <select class="hm-input" id="hm-cash-setup-clinic">
                            <option value="">— Select clinic —</option>
                            <?php foreach ( $clinics as $c ) : ?>
                                <option value="<?php echo intval( $c->id ); ?>"><?php echo esc_html( $c->clinic_name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="hm-form-group">
                        <label class="hm-label">Opening Cash Float (€)</label>
                        <input type="number" class="hm-input" id="hm-cash-setup-float" value="100" min="0" step="0.01">
                    </div>

                    <div class="hm-form-group">
                        <label class="hm-label">Opening Cheques (€)</label>
                        <input type="number" class="hm-input" id="hm-cash-setup-cheques" value="0" min="0" step="0.01">
                    </div>

                    <button class="hm-btn hm-btn--primary" id="hm-cash-activate" style="width:100%;margin-top:8px;">Activate My Tender</button>

                    <div style="margin-top:16px;padding:12px;background:var(--hm-bg,#f8fafc);border-radius:8px;font-size:12px;color:var(--hm-grey,#64748b);">
                        <strong>What is a tender?</strong><br>
                        Your tender is a running record of all cash and cheques you hold. When a patient pays in cash or by cheque, the amount is automatically added. You lodge money to the bank and log petty cash expenses from here.
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    // ═════════════════════════════════════════════════════════════════════════
    // RENDER: LODGE MONEY
    // ═════════════════════════════════════════════════════════════════════════

    private static function render_lodge( $tender ) {
        $base_url = self::dashboard_url();
        ?>
        <div class="hm-content">
            <a href="<?php echo esc_url( $base_url ); ?>" class="hm-back">Back to My Tender</a>

            <div class="hm-page-header">
                <h1 class="hm-page-title">Lodge Money</h1>
            </div>

            <!-- Current Balances -->
            <div class="hm-cash-balance-hero">
                <div class="hm-cash-bal">Cash: <strong><?php echo self::money( $tender->cash_balance ); ?></strong></div>
                <div class="hm-cash-bal">Cheques: <strong><?php echo self::money( $tender->cheque_balance ); ?></strong></div>
            </div>

            <div class="hm-card" style="max-width:600px;">
                <div style="padding:20px;">
                    <input type="hidden" id="hm-lodge-tender-id" value="<?php echo intval( $tender->id ); ?>">
                    <input type="hidden" id="hm-lodge-type" value="cash">
                    <input type="hidden" id="hm-lodge-max-cash" value="<?php echo esc_attr( $tender->cash_balance ); ?>">
                    <input type="hidden" id="hm-lodge-max-cheque" value="<?php echo esc_attr( $tender->cheque_balance ); ?>">

                    <!-- Lodge Type Toggle -->
                    <label class="hm-label" style="margin-bottom:8px;">What are you lodging?</label>
                    <div class="hm-lodge-type-group">
                        <button type="button" class="hm-lodge-type-btn active hm-btn--primary" data-type="cash">Cash</button>
                        <button type="button" class="hm-lodge-type-btn" data-type="cheque">Cheques</button>
                        <button type="button" class="hm-lodge-type-btn" data-type="both">Both</button>
                    </div>

                    <div id="hm-lodge-cash-row" class="hm-form-group">
                        <label class="hm-label">Cash Amount (€) *</label>
                        <input type="number" class="hm-input" id="hm-lodge-cash-amount" min="0" step="0.01" max="<?php echo esc_attr( $tender->cash_balance ); ?>">
                    </div>

                    <div id="hm-lodge-cheque-row" class="hm-form-group" style="display:none;">
                        <label class="hm-label">Cheque Amount (€) *</label>
                        <input type="number" class="hm-input" id="hm-lodge-cheque-amount" min="0" step="0.01" max="<?php echo esc_attr( $tender->cheque_balance ); ?>">
                    </div>

                    <div id="hm-lodge-cheque-count-row" class="hm-form-group" style="display:none;">
                        <label class="hm-label">Number of Cheques</label>
                        <input type="number" class="hm-input" id="hm-lodge-cheque-count" min="0" step="1">
                    </div>

                    <div class="hm-form-group">
                        <label class="hm-label">Bank Reference</label>
                        <input type="text" class="hm-input" id="hm-lodge-reference" placeholder="e.g. lodgment slip number">
                    </div>

                    <!-- Lodge Slip Photo -->
                    <div class="hm-form-group">
                        <label class="hm-label">Lodge Slip Photo</label>
                        <div class="hm-cash-upload-zone" id="hm-lodge-slip-zone">
                            <span class="hm-cash-upload-icon">📷</span>
                            <div>Tap to take photo or select file</div>
                            <input type="file" id="hm-lodge-slip-file" accept="image/*" capture="environment">
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="hm-cash-summary" id="hm-lodge-summary" style="display:none;">
                        <span id="hm-lodge-summary-text"></span>
                    </div>

                    <div class="hm-form-group">
                        <label class="hm-label">Notes</label>
                        <textarea class="hm-input" id="hm-lodge-notes" rows="2" placeholder="Optional notes"></textarea>
                    </div>

                    <button class="hm-btn hm-btn--primary" id="hm-lodge-submit" style="width:100%;">Lodge Money</button>
                </div>
            </div>
        </div>
        <?php
    }

    // ═════════════════════════════════════════════════════════════════════════
    // RENDER: PETTY CASH EXPENSE
    // ═════════════════════════════════════════════════════════════════════════

    private static function render_petty( $tender ) {
        $base_url = self::dashboard_url();
        ?>
        <div class="hm-content">
            <a href="<?php echo esc_url( $base_url ); ?>" class="hm-back">Back to My Tender</a>

            <div class="hm-page-header">
                <h1 class="hm-page-title">Log Petty Cash Expense</h1>
            </div>

            <div class="hm-cash-balance-hero">
                <div class="hm-cash-bal">Available Cash: <strong><?php echo self::money( $tender->cash_balance ); ?></strong></div>
            </div>

            <div class="hm-card" style="max-width:600px;">
                <div style="padding:20px;">
                    <input type="hidden" id="hm-petty-tender-id" value="<?php echo intval( $tender->id ); ?>">

                    <div class="hm-form-group">
                        <label class="hm-label">Amount (€) *</label>
                        <input type="number" class="hm-input" id="hm-petty-amount" min="0.01" step="0.01" max="<?php echo esc_attr( $tender->cash_balance ); ?>">
                    </div>

                    <div class="hm-form-group">
                        <label class="hm-label">Category *</label>
                        <select class="hm-input" id="hm-petty-category">
                            <option value="supplies">Supplies</option>
                            <option value="postage">Postage</option>
                            <option value="cleaning">Cleaning</option>
                            <option value="refreshments">Refreshments</option>
                            <option value="parking">Parking</option>
                            <option value="travel">Travel</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="hm-form-group">
                        <label class="hm-label">Description *</label>
                        <textarea class="hm-input" id="hm-petty-description" rows="2" placeholder="What was purchased?"></textarea>
                    </div>

                    <div class="hm-form-group">
                        <label class="hm-label">Vendor</label>
                        <input type="text" class="hm-input" id="hm-petty-vendor" placeholder="e.g. Tesco, An Post">
                    </div>

                    <!-- Receipt Photo (REQUIRED) -->
                    <div class="hm-form-group">
                        <label class="hm-label">Receipt Photo * <span style="font-size:11px;color:var(--hm-red,#dc2626);">(required)</span></label>
                        <div class="hm-cash-upload-zone hm-cash-upload-zone--required" id="hm-petty-receipt-zone">
                            <span class="hm-cash-upload-icon">🧾</span>
                            <div>Tap to take photo or select file</div>
                            <input type="file" id="hm-petty-receipt-file" accept="image/*" capture="environment">
                        </div>
                    </div>

                    <div class="hm-form-group">
                        <label class="hm-label">Notes</label>
                        <textarea class="hm-input" id="hm-petty-notes" rows="2" placeholder="Optional notes"></textarea>
                    </div>

                    <button class="hm-btn hm-btn--primary" id="hm-petty-submit" style="width:100%;">Log Expense</button>
                </div>
            </div>
        </div>
        <?php
    }

    // ═════════════════════════════════════════════════════════════════════════
    // RENDER: HISTORY (full audit trail)
    // ═════════════════════════════════════════════════════════════════════════

    private static function render_history( $tender ) {
        $db       = HearMed_DB::instance();
        $base_url = self::dashboard_url();

        $entries = $db->get_results(
            "SELECT te.*, inv.invoice_number,
                    CONCAT(p.first_name, ' ', p.last_name) AS patient_name
             FROM hearmed_core.tender_entries te
             LEFT JOIN hearmed_core.invoices inv ON inv.id = te.invoice_id
             LEFT JOIN hearmed_core.payments pay ON pay.id = te.payment_id
             LEFT JOIN hearmed_core.patients p ON p.id = pay.patient_id
             WHERE te.tender_id = $1
             ORDER BY te.created_at ASC",
            [ $tender->id ]
        );

        ?>
        <div class="hm-content">
            <a href="<?php echo esc_url( $base_url ); ?>" class="hm-back">Back to My Tender</a>

            <div class="hm-page-header">
                <h1 class="hm-page-title">Tender History</h1>
            </div>

            <div class="hm-card">
                <?php if ( empty( $entries ) ) : ?>
                    <div class="hm-empty"><div class="hm-empty-text">No entries yet</div></div>
                <?php else : ?>
                    <table class="hm-table">
                        <thead><tr>
                            <th>Date</th><th>Type</th><th>Tender Type</th><th>Direction</th>
                            <th style="text-align:right">Amount</th>
                            <th style="text-align:right">Cash</th><th style="text-align:right">Cheques</th><th>Notes</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ( $entries as $e ) :
                            $is_in   = ( $e->direction ?? '' ) === 'in';
                            $dir_cls = $is_in ? 'hm-amount-in' : 'hm-amount-out';
                            $dir_lbl = $is_in ? '↑ In' : '↓ Out';
                        ?>
                            <tr>
                                <td style="white-space:nowrap;font-size:12px;"><?php echo esc_html( date( 'd/m/Y H:i', strtotime( $e->created_at ) ) ); ?></td>
                                <td><span class="hm-badge hm-badge--sm <?php echo self::entry_badge_class( $e->entry_type ); ?>"><?php echo esc_html( self::entry_type_label( $e->entry_type ) ); ?></span></td>
                                <td><span class="hm-badge hm-badge--sm <?php echo ( $e->tender_type ?? '' ) === 'cheque' ? 'hm-badge--blue' : 'hm-badge--green'; ?>"><?php echo esc_html( ucfirst( $e->tender_type ?? '' ) ); ?></span></td>
                                <td class="<?php echo $dir_cls; ?>"><?php echo $dir_lbl; ?></td>
                                <td style="text-align:right" class="<?php echo $dir_cls; ?>"><?php echo ( $is_in ? '+' : '-' ) . self::money( $e->amount ); ?></td>
                                <td style="text-align:right"><?php echo self::money( $e->running_cash ); ?></td>
                                <td style="text-align:right"><?php echo self::money( $e->running_cheque ); ?></td>
                                <td style="font-size:12px;"><?php echo esc_html( $e->notes ?? '' ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    // ═════════════════════════════════════════════════════════════════════════
    // RENDER: ADMIN — all staff tenders
    // ═════════════════════════════════════════════════════════════════════════

    private static function render_admin() {
        if ( ! HearMed_Auth::can( 'view_accounting' ) ) {
            echo '<div class="hm-content"><div class="hm-card"><div class="hm-empty"><div class="hm-empty-text">You do not have permission to view this page.</div></div></div></div>';
            return;
        }

        $db = HearMed_DB::instance();

        $tenders = $db->get_results(
            "SELECT t.*, s.first_name, s.last_name, c.clinic_name
             FROM hearmed_core.staff_tenders t
             JOIN hearmed_reference.staff s ON s.id = t.staff_id
             LEFT JOIN hearmed_reference.clinics c ON c.id = t.clinic_id
             ORDER BY s.first_name, s.last_name"
        );

        $totals = $db->get_row(
            "SELECT COALESCE(SUM(cash_balance), 0) AS total_cash,
                    COALESCE(SUM(cheque_balance), 0) AS total_cheque,
                    COALESCE(SUM(float_amount), 0) AS total_float
             FROM hearmed_core.staff_tenders"
        );

        $pending_all = (int) $db->get_var(
            "SELECT count(*) FROM hearmed_core.tender_lodgments WHERE status = 'pending'"
        );

        $admin_url = HearMed_Utils::page_url( 'till' ) ?: home_url( '/staff-tenders/' );

        ?>
        <div class="hm-content">
            <div class="hm-page-header">
                <h1 class="hm-page-title">Staff Tenders</h1>
                <div>
                    <a href="<?php echo esc_url( $admin_url . '?hm_action=lodgments' ); ?>" class="hm-btn hm-btn--secondary">Lodgment History</a>
                </div>
            </div>

            <div class="hm-cash-kpi">
                <div class="hm-card" style="border-top:3px solid var(--hm-green,#16a34a);">
                    <div style="padding:16px;text-align:center;">
                        <div style="font-size:24px;font-weight:700;color:var(--hm-green,#16a34a);"><?php echo self::money( $totals->total_cash ?? 0 ); ?></div>
                        <div style="font-size:12px;color:var(--hm-grey,#64748b);text-transform:uppercase;letter-spacing:.5px;margin-top:4px;">Total Cash</div>
                    </div>
                </div>
                <div class="hm-card" style="border-top:3px solid var(--hm-teal,#0BB4C4);">
                    <div style="padding:16px;text-align:center;">
                        <div style="font-size:24px;font-weight:700;color:var(--hm-teal,#0BB4C4);"><?php echo self::money( $totals->total_cheque ?? 0 ); ?></div>
                        <div style="font-size:12px;color:var(--hm-grey,#64748b);text-transform:uppercase;letter-spacing:.5px;margin-top:4px;">Total Cheques</div>
                    </div>
                </div>
                <div class="hm-card" style="border-top:3px solid var(--hm-border,#e2e8f0);">
                    <div style="padding:16px;text-align:center;">
                        <div style="font-size:24px;font-weight:700;color:var(--hm-grey,#64748b);"><?php echo self::money( $totals->total_float ?? 0 ); ?></div>
                        <div style="font-size:12px;color:var(--hm-grey,#64748b);text-transform:uppercase;letter-spacing:.5px;margin-top:4px;">Total Float</div>
                    </div>
                </div>
                <div class="hm-card" style="border-top:3px solid <?php echo $pending_all > 0 ? 'var(--hm-amber,#f59e0b)' : 'var(--hm-border,#e2e8f0)'; ?>;">
                    <div style="padding:16px;text-align:center;">
                        <div style="font-size:24px;font-weight:700;color:<?php echo $pending_all > 0 ? 'var(--hm-amber,#f59e0b)' : 'var(--hm-navy,#151B33)'; ?>;"><?php echo $pending_all; ?></div>
                        <div style="font-size:12px;color:var(--hm-grey,#64748b);text-transform:uppercase;letter-spacing:.5px;margin-top:4px;">Pending Lodgments</div>
                    </div>
                </div>
            </div>

            <div class="hm-card">
                <?php if ( empty( $tenders ) ) : ?>
                    <div class="hm-empty"><div class="hm-empty-text">No staff tenders created yet</div></div>
                <?php else : ?>
                    <table class="hm-table">
                        <thead><tr>
                            <th>Staff</th><th>Clinic</th>
                            <th style="text-align:right">Cash</th><th style="text-align:right">Cheques</th>
                            <th style="text-align:right">Float</th><th>Last Reconciled</th><th>Status</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ( ( $tenders ?: [] ) as $t ) :
                            $st_cls = ( $t->status ?? '' ) === 'active' ? 'hm-badge--green' : ( ( $t->status ?? '' ) === 'suspended' ? 'hm-badge--amber' : 'hm-badge--red' );
                        ?>
                            <tr>
                                <td style="font-weight:500;"><?php echo esc_html( trim( ( $t->first_name ?? '' ) . ' ' . ( $t->last_name ?? '' ) ) ); ?></td>
                                <td><?php echo esc_html( $t->clinic_name ?? '—' ); ?></td>
                                <td style="text-align:right"><?php echo self::money( $t->cash_balance ); ?></td>
                                <td style="text-align:right"><?php echo self::money( $t->cheque_balance ); ?></td>
                                <td style="text-align:right"><?php echo self::money( $t->float_amount ); ?></td>
                                <td style="font-size:12px;"><?php echo $t->last_reconciled ? date( 'd/m/Y H:i', strtotime( $t->last_reconciled ) ) : '—'; ?></td>
                                <td><span class="hm-badge hm-badge--sm <?php echo $st_cls; ?>"><?php echo esc_html( ucfirst( $t->status ?? 'active' ) ); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    // ═════════════════════════════════════════════════════════════════════════
    // RENDER: LODGMENTS (manager approval)
    // ═════════════════════════════════════════════════════════════════════════

    private static function render_lodgments() {
        if ( ! HearMed_Auth::can( 'view_accounting' ) ) {
            echo '<div class="hm-content"><div class="hm-card"><div class="hm-empty"><div class="hm-empty-text">You do not have permission to view this page.</div></div></div></div>';
            return;
        }

        $db        = HearMed_DB::instance();
        $admin_url = HearMed_Utils::page_url( 'till' ) ?: home_url( '/staff-tenders/' );

        $lodgments = $db->get_results(
            "SELECT l.*, s.first_name, s.last_name,
                    cs.first_name AS conf_first, cs.last_name AS conf_last
             FROM hearmed_core.tender_lodgments l
             JOIN hearmed_core.staff_tenders t ON t.id = l.tender_id
             JOIN hearmed_reference.staff s ON s.id = t.staff_id
             LEFT JOIN hearmed_reference.staff cs ON cs.id = l.confirmed_by
             ORDER BY l.created_at DESC"
        );

        ?>
        <div class="hm-content">
            <a href="<?php echo esc_url( $admin_url ); ?>" class="hm-back">Back to Staff Tenders</a>

            <div class="hm-page-header">
                <h1 class="hm-page-title">Lodgment History</h1>
            </div>

            <div class="hm-card">
                <?php if ( empty( $lodgments ) ) : ?>
                    <div class="hm-empty"><div class="hm-empty-text">No lodgments recorded yet</div></div>
                <?php else : ?>
                    <table class="hm-table">
                        <thead><tr>
                            <th>Date</th><th>Staff</th><th>Type</th>
                            <th style="text-align:right">Cash</th><th style="text-align:right">Cheques</th>
                            <th>Bank Ref</th><th>Status</th><th></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ( ( $lodgments ?: [] ) as $l ) :
                            $st_cls = 'hm-badge--grey';
                            if ( ( $l->status ?? '' ) === 'pending' )   $st_cls = 'hm-badge--amber';
                            if ( ( $l->status ?? '' ) === 'confirmed' ) $st_cls = 'hm-badge--green';
                            if ( ( $l->status ?? '' ) === 'queried' )   $st_cls = 'hm-badge--red';
                            if ( ( $l->status ?? '' ) === 'rejected' )  $st_cls = 'hm-badge--red';

                            $confirmed_name = '';
                            if ( $l->confirmed_by && isset( $l->conf_first ) ) {
                                $confirmed_name = trim( ( $l->conf_first ?? '' ) . ' ' . ( $l->conf_last ?? '' ) );
                            }
                        ?>
                            <tr>
                                <td style="white-space:nowrap;font-size:12px;"><?php echo esc_html( date( 'd/m/Y', strtotime( $l->created_at ) ) ); ?></td>
                                <td style="font-weight:500;"><?php echo esc_html( trim( ( $l->first_name ?? '' ) . ' ' . ( $l->last_name ?? '' ) ) ); ?></td>
                                <td><span class="hm-badge hm-badge--sm hm-badge--blue"><?php echo esc_html( ucfirst( $l->lodge_type ?? '' ) ); ?></span></td>
                                <td style="text-align:right"><?php echo self::money( $l->cash_amount ); ?></td>
                                <td style="text-align:right"><?php echo self::money( $l->cheque_amount ); ?></td>
                                <td style="font-size:12px;"><?php echo esc_html( $l->bank_reference ?? '—' ); ?></td>
                                <td>
                                    <span class="hm-badge hm-badge--sm <?php echo $st_cls; ?>"><?php echo esc_html( ucfirst( $l->status ?? '' ) ); ?></span>
                                    <?php if ( $confirmed_name ) : ?>
                                        <br><span style="font-size:11px;color:var(--hm-grey,#64748b);">by <?php echo esc_html( $confirmed_name ); ?></span>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $l->qbo_deposit_id ) ) : ?>
                                        <br><span style="font-size:11px;color:#1e40af;">QBO #<?php echo esc_html( $l->qbo_deposit_id ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="display:flex;gap:4px;">
                                    <?php if ( ( $l->status ?? '' ) === 'pending' ) : ?>
                                        <button class="hm-btn hm-btn--primary hm-btn--sm hm-lodge-confirm-btn" data-id="<?php echo intval( $l->id ); ?>">Confirm</button>
                                        <button class="hm-btn hm-btn--secondary hm-btn--sm hm-lodge-query-btn" data-id="<?php echo intval( $l->id ); ?>">Query</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    // ═════════════════════════════════════════════════════════════════════════
    // STATIC HELPER: Record payment to tender (called from mod-calendar.php)
    // ═════════════════════════════════════════════════════════════════════════

    public static function record_payment_tender( $payment_id, $staff_id, $payment_method, $amount, $invoice_id = null, $clinic_id = null ) {
        $method = strtolower( trim( $payment_method ) );
        if ( ! in_array( $method, [ 'cash', 'cheque' ], true ) ) return;

        $db = HearMed_DB::instance();

        // Get or auto-create tender
        $tender = $db->get_row(
            "SELECT * FROM hearmed_core.staff_tenders WHERE staff_id = $1",
            [ $staff_id ]
        );

        if ( ! $tender ) {
            $tender_id = $db->insert( 'hearmed_core.staff_tenders', [
                'staff_id'  => $staff_id,
                'clinic_id' => $clinic_id,
            ] );
            if ( ! $tender_id ) return;
            $tender = $db->get_row(
                "SELECT * FROM hearmed_core.staff_tenders WHERE id = $1",
                [ $tender_id ]
            );
            if ( ! $tender ) return;
        }

        // Determine column
        $column = ( $method === 'cheque' ) ? 'cheque_balance' : 'cash_balance';

        // Update balance
        $db->query(
            "UPDATE hearmed_core.staff_tenders SET {$column} = {$column} + $1, updated_at = NOW() WHERE id = $2",
            [ floatval( $amount ), $tender->id ]
        );

        // Re-fetch for running totals
        $updated = $db->get_row(
            "SELECT cash_balance, cheque_balance FROM hearmed_core.staff_tenders WHERE id = $1",
            [ $tender->id ]
        );

        // Insert tender entry
        $db->insert( 'hearmed_core.tender_entries', [
            'tender_id'      => $tender->id,
            'entry_type'     => 'payment_in',
            'tender_type'    => $method,
            'direction'      => 'in',
            'amount'         => floatval( $amount ),
            'running_cash'   => floatval( $updated->cash_balance ?? 0 ),
            'running_cheque' => floatval( $updated->cheque_balance ?? 0 ),
            'payment_id'     => $payment_id,
            'invoice_id'     => $invoice_id,
            'created_by'     => $staff_id,
        ] );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // AJAX: Activate Tender
    // ═════════════════════════════════════════════════════════════════════════

    public static function ajax_activate_tender() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        if ( ! PortalAuth::is_logged_in() ) { wp_send_json_error( [ 'msg' => 'Not logged in' ] ); return; }

        $staff_id   = PortalAuth::staff_id();
        $clinic_id  = intval( $_POST['clinic_id'] ?? 0 );
        $cash_float = floatval( $_POST['cash_float'] ?? 0 );
        $cheques    = floatval( $_POST['cheques'] ?? 0 );

        if ( ! $clinic_id ) { wp_send_json_error( [ 'msg' => 'Clinic is required' ] ); return; }

        $db = HearMed_DB::instance();

        // Check if already exists
        $existing = $db->get_var( "SELECT id FROM hearmed_core.staff_tenders WHERE staff_id = $1", [ $staff_id ] );
        if ( $existing ) { wp_send_json_error( [ 'msg' => 'You already have an active tender' ] ); return; }

        $tender_id = $db->insert( 'hearmed_core.staff_tenders', [
            'staff_id'       => $staff_id,
            'clinic_id'      => $clinic_id,
            'cash_balance'   => $cash_float,
            'cheque_balance' => $cheques,
            'float_amount'   => $cash_float,
            'status'         => 'active',
        ] );

        if ( ! $tender_id ) { wp_send_json_error( [ 'msg' => 'Failed to create tender' ] ); return; }

        // Opening float entry — cash
        if ( $cash_float > 0 ) {
            $db->insert( 'hearmed_core.tender_entries', [
                'tender_id'      => $tender_id,
                'entry_type'     => 'opening_float',
                'tender_type'    => 'cash',
                'direction'      => 'in',
                'amount'         => $cash_float,
                'running_cash'   => $cash_float,
                'running_cheque' => $cheques,
                'created_by'     => $staff_id,
            ] );
        }

        // Opening float entry — cheques
        if ( $cheques > 0 ) {
            $db->insert( 'hearmed_core.tender_entries', [
                'tender_id'      => $tender_id,
                'entry_type'     => 'opening_float',
                'tender_type'    => 'cheque',
                'direction'      => 'in',
                'amount'         => $cheques,
                'running_cash'   => $cash_float,
                'running_cheque' => $cheques,
                'created_by'     => $staff_id,
            ] );
        }

        wp_send_json_success( [ 'msg' => 'Tender activated', 'tender_id' => $tender_id ] );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // AJAX: Lodge Money
    // ═════════════════════════════════════════════════════════════════════════

    public static function ajax_lodge_money() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        if ( ! PortalAuth::is_logged_in() ) { wp_send_json_error( [ 'msg' => 'Not logged in' ] ); return; }

        $staff_id      = PortalAuth::staff_id();
        $tender_id     = intval( $_POST['tender_id'] ?? 0 );
        $lodge_type    = sanitize_key( $_POST['lodge_type'] ?? 'cash' );
        $cash_amount   = floatval( $_POST['cash_amount'] ?? 0 );
        $cheque_amount = floatval( $_POST['cheque_amount'] ?? 0 );
        $cheque_count  = intval( $_POST['cheque_count'] ?? 0 );
        $reference     = sanitize_text_field( $_POST['reference'] ?? '' );
        $notes         = sanitize_text_field( $_POST['notes'] ?? '' );

        if ( ! in_array( $lodge_type, [ 'cash', 'cheque', 'both' ], true ) ) {
            wp_send_json_error( [ 'msg' => 'Invalid lodge type' ] ); return;
        }

        $db     = HearMed_DB::instance();
        $tender = $db->get_row( "SELECT * FROM hearmed_core.staff_tenders WHERE id = $1", [ $tender_id ] );
        if ( ! $tender ) { wp_send_json_error( [ 'msg' => 'Tender not found' ] ); return; }

        // Validate amounts
        if ( ( $lodge_type === 'cash' || $lodge_type === 'both' ) && $cash_amount > 0 ) {
            if ( $cash_amount > floatval( $tender->cash_balance ) ) {
                wp_send_json_error( [ 'msg' => 'Cash amount exceeds balance (' . self::money( $tender->cash_balance ) . ')' ] ); return;
            }
        }
        if ( ( $lodge_type === 'cheque' || $lodge_type === 'both' ) && $cheque_amount > 0 ) {
            if ( $cheque_amount > floatval( $tender->cheque_balance ) ) {
                wp_send_json_error( [ 'msg' => 'Cheque amount exceeds balance (' . self::money( $tender->cheque_balance ) . ')' ] ); return;
            }
        }
        if ( $cash_amount <= 0 && $cheque_amount <= 0 ) {
            wp_send_json_error( [ 'msg' => 'Enter at least one amount' ] ); return;
        }

        // Create lodgment
        $lodgment_id = $db->insert( 'hearmed_core.tender_lodgments', [
            'tender_id'      => $tender_id,
            'lodge_type'     => $lodge_type,
            'cash_amount'    => $cash_amount,
            'cheque_amount'  => $cheque_amount,
            'cheque_count'   => $cheque_count,
            'bank_reference' => $reference ?: null,
            'notes'          => $notes ?: null,
            'status'         => 'pending',
            'lodgment_date'  => date( 'Y-m-d' ),
            'created_by'     => $staff_id,
        ] );

        if ( ! $lodgment_id ) { wp_send_json_error( [ 'msg' => 'Failed to create lodgment' ] ); return; }

        // Deduct from tender
        if ( $cash_amount > 0 ) {
            $db->query(
                "UPDATE hearmed_core.staff_tenders SET cash_balance = cash_balance - $1, updated_at = NOW() WHERE id = $2",
                [ $cash_amount, $tender_id ]
            );
        }
        if ( $cheque_amount > 0 ) {
            $db->query(
                "UPDATE hearmed_core.staff_tenders SET cheque_balance = cheque_balance - $1, updated_at = NOW() WHERE id = $2",
                [ $cheque_amount, $tender_id ]
            );
        }

        // Re-fetch
        $updated = $db->get_row( "SELECT cash_balance, cheque_balance FROM hearmed_core.staff_tenders WHERE id = $1", [ $tender_id ] );

        // Tender entries
        if ( $cash_amount > 0 ) {
            $db->insert( 'hearmed_core.tender_entries', [
                'tender_id'      => $tender_id,
                'entry_type'     => 'lodgment_cash',
                'tender_type'    => 'cash',
                'direction'      => 'out',
                'amount'         => $cash_amount,
                'running_cash'   => floatval( $updated->cash_balance ?? 0 ),
                'running_cheque' => floatval( $updated->cheque_balance ?? 0 ),
                'lodgment_id'    => $lodgment_id,
                'notes'          => 'Lodged ' . self::money( $cash_amount ) . ' cash' . ( $reference ? ' — Ref: ' . $reference : '' ),
                'created_by'     => $staff_id,
            ] );
        }
        if ( $cheque_amount > 0 ) {
            $db->insert( 'hearmed_core.tender_entries', [
                'tender_id'      => $tender_id,
                'entry_type'     => 'lodgment_cheque',
                'tender_type'    => 'cheque',
                'direction'      => 'out',
                'amount'         => $cheque_amount,
                'running_cash'   => floatval( $updated->cash_balance ?? 0 ),
                'running_cheque' => floatval( $updated->cheque_balance ?? 0 ),
                'lodgment_id'    => $lodgment_id,
                'notes'          => 'Lodged ' . self::money( $cheque_amount ) . ' in cheques (' . $cheque_count . ' cheques)' . ( $reference ? ' — Ref: ' . $reference : '' ),
                'created_by'     => $staff_id,
            ] );
        }

        wp_send_json_success( [
            'msg'            => 'Lodgment recorded',
            'lodgment_id'    => $lodgment_id,
            'cash_balance'   => floatval( $updated->cash_balance ?? 0 ),
            'cheque_balance' => floatval( $updated->cheque_balance ?? 0 ),
        ] );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // AJAX: Petty Cash Expense
    // ═════════════════════════════════════════════════════════════════════════

    public static function ajax_petty_cash() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        if ( ! PortalAuth::is_logged_in() ) { wp_send_json_error( [ 'msg' => 'Not logged in' ] ); return; }

        $staff_id    = PortalAuth::staff_id();
        $tender_id   = intval( $_POST['tender_id'] ?? 0 );
        $amount      = floatval( $_POST['amount'] ?? 0 );
        $category    = sanitize_key( $_POST['category'] ?? 'other' );
        $description = sanitize_text_field( $_POST['description'] ?? '' );
        $vendor      = sanitize_text_field( $_POST['vendor'] ?? '' );
        $notes       = sanitize_text_field( $_POST['notes'] ?? '' );

        if ( $amount <= 0 ) { wp_send_json_error( [ 'msg' => 'Amount must be greater than zero' ] ); return; }
        if ( empty( $description ) ) { wp_send_json_error( [ 'msg' => 'Description is required' ] ); return; }

        $db     = HearMed_DB::instance();
        $tender = $db->get_row( "SELECT * FROM hearmed_core.staff_tenders WHERE id = $1", [ $tender_id ] );
        if ( ! $tender ) { wp_send_json_error( [ 'msg' => 'Tender not found' ] ); return; }
        if ( $amount > floatval( $tender->cash_balance ) ) {
            wp_send_json_error( [ 'msg' => 'Amount exceeds cash balance (' . self::money( $tender->cash_balance ) . ')' ] ); return;
        }

        // Create expense
        $expense_id = $db->insert( 'hearmed_core.petty_cash_expenses', [
            'tender_id'    => $tender_id,
            'amount'       => $amount,
            'category'     => $category,
            'description'  => $description,
            'vendor'       => $vendor ?: null,
            'notes'        => $notes ?: null,
            'status'       => 'pending',
            'expense_date' => date( 'Y-m-d' ),
            'created_by'   => $staff_id,
        ] );

        if ( ! $expense_id ) { wp_send_json_error( [ 'msg' => 'Failed to create expense' ] ); return; }

        // Deduct from cash
        $db->query(
            "UPDATE hearmed_core.staff_tenders SET cash_balance = cash_balance - $1, updated_at = NOW() WHERE id = $2",
            [ $amount, $tender_id ]
        );

        $updated = $db->get_row( "SELECT cash_balance, cheque_balance FROM hearmed_core.staff_tenders WHERE id = $1", [ $tender_id ] );

        $db->insert( 'hearmed_core.tender_entries', [
            'tender_id'      => $tender_id,
            'entry_type'     => 'petty_cash',
            'tender_type'    => 'cash',
            'direction'      => 'out',
            'amount'         => $amount,
            'running_cash'   => floatval( $updated->cash_balance ?? 0 ),
            'running_cheque' => floatval( $updated->cheque_balance ?? 0 ),
            'expense_id'     => $expense_id,
            'notes'          => ucfirst( $category ) . ': ' . $description . ( $vendor ? ' (' . $vendor . ')' : '' ),
            'created_by'     => $staff_id,
        ] );

        // QBO sync (fire-and-forget)
        if ( class_exists( 'HearMed_QBO' ) && method_exists( 'HearMed_QBO', 'push_petty_expense' ) ) {
            try { HearMed_QBO::push_petty_expense( $expense_id ); } catch ( \Exception $e ) { /* non-critical */ }
        }

        wp_send_json_success( [
            'msg'          => 'Expense logged',
            'expense_id'   => $expense_id,
            'cash_balance' => floatval( $updated->cash_balance ?? 0 ),
        ] );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // AJAX: Confirm / Query Lodgment (Manager only)
    // ═════════════════════════════════════════════════════════════════════════

    public static function ajax_confirm_lodgment() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        if ( ! PortalAuth::is_logged_in() ) { wp_send_json_error( [ 'msg' => 'Not logged in' ] ); return; }
        if ( ! HearMed_Auth::can( 'view_accounting' ) ) { wp_send_json_error( [ 'msg' => 'Unauthorized' ] ); return; }

        $staff_id    = PortalAuth::staff_id();
        $lodgment_id = intval( $_POST['lodgment_id'] ?? 0 );
        $action_type = sanitize_key( $_POST['action_type'] ?? '' );
        $reason      = sanitize_text_field( $_POST['reason'] ?? '' );

        if ( ! $lodgment_id || ! in_array( $action_type, [ 'confirm', 'query' ], true ) ) {
            wp_send_json_error( [ 'msg' => 'Invalid request' ] ); return;
        }

        $db = HearMed_DB::instance();

        if ( $action_type === 'confirm' ) {
            $db->query(
                "UPDATE hearmed_core.tender_lodgments SET status = 'confirmed', confirmed_by = $1, confirmed_at = NOW() WHERE id = $2",
                [ $staff_id, $lodgment_id ]
            );

            // QBO sync
            if ( class_exists( 'HearMed_QBO' ) && method_exists( 'HearMed_QBO', 'push_bank_deposit' ) ) {
                try { HearMed_QBO::push_bank_deposit( $lodgment_id ); } catch ( \Exception $e ) { /* non-critical */ }
            }

            wp_send_json_success( [ 'msg' => 'Lodgment confirmed' ] );
        } else {
            $db->query(
                "UPDATE hearmed_core.tender_lodgments SET status = 'queried', notes = COALESCE(notes, '') || ' [QUERY: ' || $1 || ']' WHERE id = $2",
                [ $reason, $lodgment_id ]
            );
            wp_send_json_success( [ 'msg' => 'Lodgment queried' ] );
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // AJAX: Upload Receipt / Lodge Slip
    // ═════════════════════════════════════════════════════════════════════════

    public static function ajax_upload_receipt() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        if ( ! PortalAuth::is_logged_in() ) { wp_send_json_error( [ 'msg' => 'Not logged in' ] ); return; }

        if ( empty( $_FILES['photo'] ) ) { wp_send_json_error( [ 'msg' => 'No file uploaded' ] ); return; }

        $ref_type = sanitize_key( $_POST['ref_type'] ?? '' );
        $ref_id   = intval( $_POST['ref_id'] ?? 0 );

        if ( ! in_array( $ref_type, [ 'lodge', 'expense' ], true ) || ! $ref_id ) {
            wp_send_json_error( [ 'msg' => 'Invalid reference' ] ); return;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $upload = wp_handle_upload( $_FILES['photo'], [ 'test_form' => false ] );
        if ( isset( $upload['error'] ) ) {
            wp_send_json_error( [ 'msg' => $upload['error'] ] ); return;
        }

        $db  = HearMed_DB::instance();
        $url = $upload['url'];

        if ( $ref_type === 'lodge' ) {
            $db->query(
                "UPDATE hearmed_core.tender_lodgments SET lodge_slip_url = $1 WHERE id = $2",
                [ $url, $ref_id ]
            );
        } else {
            $db->query(
                "UPDATE hearmed_core.petty_cash_expenses SET receipt_url = $1 WHERE id = $2",
                [ $url, $ref_id ]
            );
        }

        wp_send_json_success( [ 'msg' => 'Uploaded', 'url' => $url ] );
    }
}
