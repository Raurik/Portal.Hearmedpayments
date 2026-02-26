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
            return '<div class="hm-notice hm-notice--error"><div class="hm-notice-body"><span class="hm-notice-icon">✕</span> Access restricted to administrators.</div></div>';
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
        <div id="hm-app" data-view="hearmed_chat_logs">
            <div class="hm-page-header">
                <h1 class="hm-page-title">Chat Logs</h1>
                <p class="hm-page-subtitle">Full audit trail of all team chat messages. Access to these logs is restricted to administrators and logged for GDPR compliance.</p>
            </div>

            <!-- FILTERS -->
            <form method="GET" action="<?php echo esc_attr( $current_url ); ?>" class="hm-chat-log-filters">
                <input type="hidden" name="log_page" value="1">

                <div class="hm-filter-row">
                    <div class="hm-filter-group">
                        <label class="hm-label">Channel</label>
                        <select name="channel_id" class="hm-select">
                            <option value="">All channels</option>
                            <?php foreach ( $channels as $ch ) : ?>
                                <option value="<?php echo esc_attr( $ch->id ); ?>"
                                    <?php selected( $channel_filter, $ch->id ); ?>>
                                    <?php
                                    $label = $ch->channel_type === 'company'
                                        ? '# ' . ( $ch->channel_name ?: 'Company' )
                                        : '👤 DM ' . $ch->id;
                                    echo esc_html( $label );
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="hm-filter-group">
                        <label class="hm-label">Search message text</label>
                        <input type="text"
                               name="search"
                               class="hm-input"
                               placeholder="Keyword…"
                               value="<?php echo esc_attr( $search_query ); ?>">
                    </div>

                    <div class="hm-filter-group">
                        <label class="hm-label">From date</label>
                        <input type="date" name="date_from" class="hm-input"
                               value="<?php echo esc_attr( $date_from ); ?>">
                    </div>

                    <div class="hm-filter-group">
                        <label class="hm-label">To date</label>
                        <input type="date" name="date_to" class="hm-input"
                               value="<?php echo esc_attr( $date_to ); ?>">
                    </div>

                    <div class="hm-filter-group hm-filter-actions">
                        <button type="submit" class="hm-btn hm-btn-primary">Filter</button>
                        <a href="<?php echo esc_url( $current_url ); ?>" class="hm-btn hm-btn--secondary">Clear</a>
                    </div>
                </div>
            </form>

            <!-- RESULTS SUMMARY -->
            <div class="hm-chat-log-summary">
                <span><?php echo number_format( $total ); ?> message<?php echo $total !== 1 ? 's' : ''; ?> found</span>
                <?php if ( $total > 0 ) : ?>
                    <span>· Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                <?php endif; ?>
            </div>

            <!-- MESSAGE TABLE -->
            <?php if ( empty( $messages ) ) : ?>
                <div class="hm-empty-state">
                    <p>No messages found matching your filters.</p>
                </div>
            <?php else : ?>
                <div class="hm-table-wrap">
                    <table class="hm-table" data-no-enhance>
                        <thead>
                            <tr>
                                <th>Date &amp; Time</th>
                                <th>Channel</th>
                                <th>Sender</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $messages as $msg ) :
                                $sender  = get_user_by( 'id', $msg->sender_id );
                                $sender_name = $sender ? $sender->display_name : 'User #' . $msg->sender_id;

                                $ch_label = $msg->channel_type === 'company'
                                    ? '# ' . ( $msg->channel_name ?: 'Company' )
                                    : '👤 DM';
                            ?>
                                <tr>
                                    <td class="hm-chat-log-time">
                                        <?php echo esc_html( date( 'd M Y H:i', strtotime( $msg->created_at ) ) ); ?>
                                    </td>
                                    <td>
                                        <span class="hm-badge hm-badge-navy"><?php echo esc_html( $ch_label ); ?></span>
                                    </td>
                                    <td class="hm-chat-log-sender">
                                        <?php echo esc_html( $sender_name ); ?>
                                    </td>
                                    <td class="hm-chat-log-message">
                                        <?php echo esc_html( $msg->message ); ?>
                                        <?php if ( $msg->is_edited ) : ?>
                                            <em class="hm-chat-log-edited">(edited)</em>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINATION -->
                <?php if ( $total_pages > 1 ) : ?>
                    <div class="hm-pagination">
                        <?php for ( $p = 1; $p <= $total_pages; $p++ ) :
                            $url = add_query_arg( array_merge( $_GET, [ 'log_page' => $p ] ), $current_url );
                        ?>
                            <a href="<?php echo esc_url( $url ); ?>"
                               class="hm-page-btn <?php echo $p === $page ? 'active' : ''; ?>">
                                <?php echo $p; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="hm-notice hm-notice--info">
                <strong>GDPR Notice:</strong> Access to employee communications is restricted to authorised administrators
                and should only be reviewed when operationally necessary. All admin access to these logs is recorded in the
                audit trail under <strong>Chat Log Access</strong>.
            </div>
        </div>

        <style>
        .hm-chat-log-filters { margin-bottom: 20px; }
        .hm-filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
        .hm-filter-group { display: flex; flex-direction: column; gap: 4px; }
        .hm-filter-actions { flex-direction: row; gap: 8px; }
        .hm-chat-log-summary { color: #64748b; font-size: 14px; margin-bottom: 14px; }
        .hm-chat-log-time { white-space: nowrap; color: #64748b; font-size: 13px; }
        .hm-chat-log-sender { font-weight: 600; color: #151B33; }
        .hm-chat-log-message { max-width: 480px; word-break: break-word; }
        .hm-chat-log-edited { color: #94a3b8; font-size: 12px; margin-left: 6px; }
        </style>
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

        $rows = HearMed_DB::get_results(
            "SELECT wp_user_id, first_name, last_name, role
             FROM hearmed_reference.staff
             WHERE is_active = true
               AND wp_user_id IS NOT NULL
               AND wp_user_id != $1
               AND (
                   first_name ILIKE $2
                   OR last_name ILIKE $2
                   OR (first_name || ' ' || last_name) ILIKE $2
               )
             ORDER BY first_name, last_name
             LIMIT 15",
            [ $current, '%' . $query . '%' ]
        );

        $result = [];
        foreach ( $rows as $row ) {
            $result[] = [
                'id'   => (int) $row->wp_user_id,
                'name' => trim( $row->first_name . ' ' . $row->last_name ),
                'role' => self::format_role( $row->role ?? '' ),
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