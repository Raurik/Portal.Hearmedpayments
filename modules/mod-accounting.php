<?php
/**
 * HearMed Accounting Module
 * 
 * Shortcode: [hearmed_accounting]
 * Shows recent QBO sync history for Finance/C-Level users.
 * Automatically fires QBO webhook when orders module creates an invoice.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class HearMed_Accounting {

    // -------------------------------------------------------------------------
    // Hook in — called from mod-orders.php after invoice is created
    // This is the ONE line you add to mod-orders.php (see bottom of this file)
    // -------------------------------------------------------------------------
    public static function on_invoice_created( $order_id ) {
        HearMed_QBO::send_invoice( $order_id );
    }

    // -------------------------------------------------------------------------
    // Shortcode renderer — [hearmed_accounting]
    // -------------------------------------------------------------------------
    public static function render( $atts ) {

        if ( ! HearMed_Auth::can( 'view_accounting' ) ) {
            return '<div class="hm-notice hm-notice--error">Access denied.</div>';
        }

        $db      = HearMed_DB::instance();
        $user    = HearMed_Auth::current_user();
        $clinic  = HearMed_Auth::current_clinic();

        // Fetch last 50 orders with their QBO sync status
        $where  = $clinic ? 'WHERE o.clinic_id = $1' : '';
        $params = $clinic ? [ $clinic ] : [];

        $orders = $db->get_results(
            "SELECT o.id, o.invoice_number, o.created_at, o.total_amount,
                    o.qbo_sync_status, o.qbo_synced_at, o.qbo_invoice_id,
                    p.first_name, p.last_name,
                    c.name AS clinic_name
             FROM orders o
             JOIN patients p ON p.id = o.patient_id
             JOIN clinics  c ON c.id = o.clinic_id
             {$where}
             ORDER BY o.created_at DESC
             LIMIT 50",
            $params
        );

        ob_start();
        ?>
        <div class="hm-accounting" id="hm-accounting">

            <div class="hm-accounting__header">
                <h2 class="hm-accounting__title">QuickBooks Sync</h2>
                <p class="hm-accounting__subtitle">Invoices are synced automatically when created. Use this page to monitor sync status or manually retry failed syncs.</p>
            </div>

            <div class="hm-accounting__summary">
                <?php
                $synced  = array_filter( $orders, fn($o) => $o->qbo_sync_status === 'synced' );
                $failed  = array_filter( $orders, fn($o) => $o->qbo_sync_status === 'failed' );
                $pending = array_filter( $orders, fn($o) => ! $o->qbo_sync_status || $o->qbo_sync_status === 'pending' );
                ?>
                <div class="hm-stat hm-stat--green">
                    <span class="hm-stat__num"><?php echo count($synced); ?></span>
                    <span class="hm-stat__label">Synced</span>
                </div>
                <div class="hm-stat hm-stat--red">
                    <span class="hm-stat__num"><?php echo count($failed); ?></span>
                    <span class="hm-stat__label">Failed</span>
                </div>
                <div class="hm-stat hm-stat--grey">
                    <span class="hm-stat__num"><?php echo count($pending); ?></span>
                    <span class="hm-stat__label">Pending</span>
                </div>
            </div>

            <div class="hm-accounting__table-wrap">
                <table class="hm-table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Patient</th>
                            <th>Clinic</th>
                            <th>Amount</th>
                            <th>Created</th>
                            <th>QBO Status</th>
                            <th>QBO Invoice ID</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ( empty($orders) ) : ?>
                        <tr><td colspan="8" class="hm-table__empty">No invoices found.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $orders as $order ) : ?>
                        <tr class="hm-accounting__row" data-order-id="<?php echo esc_attr($order->id); ?>">
                            <td><?php echo esc_html( $order->invoice_number ?: 'HM-' . $order->id ); ?></td>
                            <td><?php echo esc_html( $order->first_name . ' ' . $order->last_name ); ?></td>
                            <td><?php echo esc_html( $order->clinic_name ); ?></td>
                            <td class="hm-money">€<?php echo number_format( $order->total_amount, 2 ); ?></td>
                            <td><?php echo esc_html( date( 'd M Y', strtotime($order->created_at) ) ); ?></td>
                            <td>
                                <?php
                                $status = $order->qbo_sync_status ?: 'pending';
                                $badge_class = match($status) {
                                    'synced'  => 'hm-badge hm-badge--green',
                                    'failed'  => 'hm-badge hm-badge--red',
                                    default   => 'hm-badge hm-badge--grey',
                                };
                                ?>
                                <span class="<?php echo $badge_class; ?>"><?php echo ucfirst($status); ?></span>
                                <?php if ( $order->qbo_synced_at ) : ?>
                                    <span class="hm-muted"><?php echo date('d M H:i', strtotime($order->qbo_synced_at)); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="hm-muted"><?php echo esc_html( $order->qbo_invoice_id ?: '—' ); ?></td>
                            <td>
                                <?php if ( $status !== 'synced' ) : ?>
                                <button class="hm-btn hm-btn--sm hm-btn--secondary hm-qbo-retry"
                                        data-order-id="<?php echo esc_attr($order->id); ?>">
                                    Retry Sync
                                </button>
                                <?php else : ?>
                                <span class="hm-muted">✓</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // AJAX: manual retry
    // -------------------------------------------------------------------------
    public static function ajax_retry_sync() {
        check_ajax_referer( 'hearmed_nonce', 'nonce' );

        if ( ! HearMed_Auth::can( 'view_accounting' ) ) {
            wp_send_json_error( 'Access denied.' );
        }

        $order_id = intval( $_POST['order_id'] ?? 0 );
        if ( ! $order_id ) {
            wp_send_json_error( 'Invalid order ID.' );
        }

        $result = HearMed_QBO::send_invoice( $order_id );

        if ( $result ) {
            wp_send_json_success( 'Invoice re-synced successfully.' );
        } else {
            wp_send_json_error( 'Sync failed. Check the error log in Admin → Debug.' );
        }
    }
}

/*
 * ─────────────────────────────────────────────────────────────────────────────
 * HOW TO CONNECT THIS TO mod-orders.php
 * 
 * Find the place in mod-orders.php where an invoice is saved to PostgreSQL.
 * After the INSERT succeeds, add this one line:
 * 
 *     HearMed_Accounting::on_invoice_created( $new_order_id );
 * 
 * That's it. The rest is automatic.
 * ─────────────────────────────────────────────────────────────────────────────
 */