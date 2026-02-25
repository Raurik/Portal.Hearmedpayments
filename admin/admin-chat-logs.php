<?php
/**
 * HearMed Admin — Chat Logs
 * 
 * Shortcode: [hearmed_chat_logs]
 * 
 * Full audit view of all chat messages across all channels.
 * Accessible to administrators and C-Level only.
 * GDPR note: access to these logs should be documented in your DPIA.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class HearMed_Admin_ChatLogs {

    public static function init() {
        add_shortcode( 'hearmed_chat_logs', [ __CLASS__, 'render' ] );
        add_action( 'wp_ajax_hm_chat_search_users', [ __CLASS__, 'ajax_search_users' ] );
    }

    public static function render(): string {
        if ( ! is_user_logged_in() ) return '';

        $auth = new HearMed_Auth();
        if ( ! $auth->is_admin() ) {
            return '<div class="hm-alert hm-alert-error">Access restricted to administrators.</div>';
        }

        // ── Filters ──
        $channel_filter  = (int) ( $_GET['channel_id'] ?? 0 );
        $user_filter     = (int) ( $_GET['user_id'] ?? 0 );
        $search_query    = sanitize_text_field( $_GET['search'] ?? '' );
        $date_from       = sanitize_text_field( $_GET['date_from'] ?? '' );
        $date_to         = sanitize_text_field( $_GET['date_to'] ?? '' );
        $page            = max( 1, (int) ( $_GET['log_page'] ?? 1 ) );
        $per_page        = 50;
        $offset          = ( $page - 1 ) * $per_page;

        // Build query conditions
        $conditions = [ 'm.is_deleted = false' ];
        $params     = [];
        $param_idx  = 1;

        if ( $channel_filter ) {
            $conditions[] = "m.channel_id = \${$param_idx}";
            $params[]     = $channel_filter;
            $param_idx++;
        }

        if ( $user_filter ) {
            $conditions[] = "m.sender_id = \${$param_idx}";
            $params[]     = $user_filter;
            $param_idx++;
        }

        if ( $search_query ) {
            $conditions[] = "m.message ILIKE \${$param_idx}";
            $params[]     = '%' . $search_query . '%';
            $param_idx++;
        }

        if ( $date_from ) {
            $conditions[] = "m.created_at >= \${$param_idx}";
            $params[]     = $date_from . ' 00:00:00';
            $param_idx++;
        }

        if ( $date_to ) {
            $conditions[] = "m.created_at <= \${$param_idx}";
            $params[]     = $date_to . ' 23:59:59';
            $param_idx++;
        }

        $where = 'WHERE ' . implode( ' AND ', $conditions );

        // Count total
        $total_row = HearMed_DB::get_row(
            "SELECT COUNT(*) AS cnt FROM hearmed_communication.chat_messages m {$where}",
            $params
        );
        $total = $total_row ? (int) $total_row->cnt : 0;
        $total_pages = (int) ceil( $total / $per_page );

        // Fetch messages
        $messages = HearMed_DB::get_results(
            "SELECT
                m.id,
                m.channel_id,
                m.sender_id,
                m.message,
                m.is_edited,
                m.created_at,
                c.channel_name,
                c.channel_type
             FROM hearmed_communication.chat_messages m
             JOIN hearmed_communication.chat_channels c ON c.id = m.channel_id
             {$where}
             ORDER BY m.created_at DESC
             LIMIT {$per_page} OFFSET {$offset}",
            $params
        );

        // Fetch all channels for filter dropdown
        $channels = HearMed_DB::get_results(
            "SELECT id, channel_name, channel_type FROM hearmed_communication.chat_channels WHERE is_active = true ORDER BY channel_type, channel_name"
        );

        $current_url = strtok( $_SERVER['REQUEST_URI'], '?' );

        ob_start();
        ?>
        <div class="hm-admin" id="hm-chat-logs-admin">
            <div style="margin-bottom:16px;"><a href="<?php echo esc_url(home_url("/admin-console/")); ?>" class="hm-btn">&larr; Back</a></div>
            <div class="hm-admin-hd">
                <h2>Chat Logs</h2>
            </div>

            <p style="color:var(--hm-text-light);font-size:13px;margin-bottom:20px;">
                Full audit trail of all team chat messages. Access is restricted to administrators and logged for GDPR compliance.
            </p>

            <!-- FILTERS -->
            <div class="hm-settings-panel" style="margin-bottom:20px;">
                <form method="GET" action="<?php echo esc_attr( $current_url ); ?>">
                    <input type="hidden" name="log_page" value="1">
                    <div class="hm-filter-row">
                        <div class="hm-form-group" style="margin-bottom:0;">
                            <label>Channel</label>
                            <select name="channel_id" style="max-width:200px;">
                                <option value="">All channels</option>
                                <?php foreach ( $channels as $ch ) : ?>
                                    <option value="<?php echo esc_attr( $ch->id ); ?>"
                                        <?php selected( $channel_filter, $ch->id ); ?>>
                                        <?php
                                        $label = $ch->channel_type === 'company'
                                            ? '# ' . ( $ch->channel_name ?: 'Company' )
                                            : 'DM ' . $ch->id;
                                        echo esc_html( $label );
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="hm-form-group" style="margin-bottom:0;">
                            <label>Search message text</label>
                            <input type="text" name="search" placeholder="Keyword…" value="<?php echo esc_attr( $search_query ); ?>" style="max-width:200px;">
                        </div>

                        <div class="hm-form-group" style="margin-bottom:0;">
                            <label>From date</label>
                            <input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" style="max-width:160px;">
                        </div>

                        <div class="hm-form-group" style="margin-bottom:0;">
                            <label>To date</label>
                            <input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" style="max-width:160px;">
                        </div>

                        <div style="display:flex;gap:8px;align-items:flex-end;padding-bottom:1px;">
                            <button type="submit" class="hm-btn hm-btn-teal">Filter</button>
                            <a href="<?php echo esc_url( $current_url ); ?>" class="hm-btn hm-btn-sm">Clear</a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- RESULTS SUMMARY -->
            <div class="hm-table-count">
                <?php echo number_format( $total ); ?> message<?php echo $total !== 1 ? 's' : ''; ?> found
                <?php if ( $total > 0 ) : ?>
                    &middot; Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                <?php endif; ?>
            </div>

            <!-- MESSAGE TABLE -->
            <?php if ( empty( $messages ) ) : ?>
                <div class="hm-empty-state"><p>No messages found matching your filters.</p></div>
            <?php else : ?>
                <table class="hm-table">
                    <thead>
                        <tr>
                            <th style="width:150px">Date &amp; Time</th>
                            <th style="width:130px">Channel</th>
                            <th style="width:140px">Sender</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $messages as $msg ) :
                            $sender  = get_user_by( 'id', $msg->sender_id );
                            $sender_name = $sender ? $sender->display_name : 'User #' . $msg->sender_id;

                            $ch_label = $msg->channel_type === 'company'
                                ? '# ' . ( $msg->channel_name ?: 'Company' )
                                : 'DM';
                        ?>
                            <tr>
                                <td style="white-space:nowrap;color:#64748b;font-size:12px;">
                                    <?php echo esc_html( date( 'd M Y H:i', strtotime( $msg->created_at ) ) ); ?>
                                </td>
                                <td>
                                    <span class="hm-badge hm-badge-blue"><?php echo esc_html( $ch_label ); ?></span>
                                </td>
                                <td><strong><?php echo esc_html( $sender_name ); ?></strong></td>
                                <td style="max-width:480px;word-break:break-word;">
                                    <?php echo esc_html( $msg->message ); ?>
                                    <?php if ( $msg->is_edited ) : ?>
                                        <em style="color:#94a3b8;font-size:11px;margin-left:4px;">(edited)</em>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- PAGINATION -->
                <?php if ( $total_pages > 1 ) : ?>
                    <div style="display:flex;gap:4px;margin-top:16px;flex-wrap:wrap;">
                        <?php for ( $p = 1; $p <= $total_pages; $p++ ) :
                            $url = add_query_arg( array_merge( $_GET, [ 'log_page' => $p ] ), $current_url );
                            $is_active = ( $p === $page );
                        ?>
                            <a href="<?php echo esc_url( $url ); ?>"
                               style="padding:6px 12px;font-size:12px;font-weight:<?php echo $is_active ? '700' : '500'; ?>;color:<?php echo $is_active ? '#fff' : '#64748b'; ?>;background:<?php echo $is_active ? '#0BB4C4' : '#f8fafc'; ?>;border:1px solid <?php echo $is_active ? '#0BB4C4' : '#e2e8f0'; ?>;border-radius:6px;text-decoration:none;">
                                <?php echo $p; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="hm-alert hm-alert-warning" style="margin-top:24px;">
                <strong>GDPR Notice:</strong> Access to employee communications is restricted to authorised administrators
                and should only be reviewed when operationally necessary. All admin access to these logs is recorded in the
                audit trail under <strong>Chat Log Access</strong>.
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX: Search WP users by name for DM modal.
     */
    public static function ajax_search_users(): void {
        check_ajax_referer( 'hm_chat_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( 'Not logged in', 401 );

        $query   = sanitize_text_field( $_GET['q'] ?? '' );
        $current = get_current_user_id();

        $users = get_users( [
            'search'         => '*' . $query . '*',
            'search_columns' => [ 'display_name', 'user_login', 'user_email' ],
            'number'         => 15,
            'exclude'        => [ $current ],
        ] );

        $result = [];
        foreach ( $users as $u ) {
            $roles = (array) $u->roles;
            $result[] = [
                'id'   => $u->ID,
                'name' => $u->display_name,
                'role' => self::format_role( $roles[0] ?? '' ),
            ];
        }

        wp_send_json_success( $result );
    }

    private static function format_role( string $role ): string {
        $map = [
            'hm_clevel'    => 'C-Level',
            'hm_admin'     => 'Administrator',
            'hm_finance'   => 'Finance',
            'hm_dispenser' => 'Audiologist',
            'hm_reception' => 'Reception',
            'hm_ca'        => 'Clinical Assistant',
            'hm_scheme'    => 'Scheme',
            'administrator' => 'Super Admin',
        ];
        return $map[ $role ] ?? ucfirst( str_replace( 'hm_', '', $role ) );
    }
}

HearMed_Admin_ChatLogs::init();