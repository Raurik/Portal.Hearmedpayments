<?php
/**
 * HearMed Team Chat Module
 * 
 * Real-time internal messaging powered by Pusher.
 * 
 * Features:
 *  - Company-wide channel (all staff)
 *  - Direct messages (1-to-1)
 *  - Messages stored in PostgreSQL (GDPR compliant — data stays on your server)
 *  - Soft-delete only — messages are never permanently erased
 *  - Admin can view all message logs via [hearmed_chat_logs] shortcode
 *  - Unread badge counts
 * 
 * Shortcode: [hearmed_team_chat]
 * 
 * Requires:
 *  - Pusher PHP SDK (via Composer or manual include)
 *  - Pusher app credentials in HearMed settings (Admin > Settings > Pusher)
 *  - Pusher JS SDK (loaded automatically)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class HearMed_TeamChat {

    // ── AJAX actions registered ───────────────────────────────────────────────
    // hm_chat_get_channels      GET  list of channels for current user
    // hm_chat_get_messages      GET  message history for a channel
    // hm_chat_send_message      POST send a message
    // hm_chat_delete_message    POST soft-delete a message (own messages only; admin any)
    // hm_chat_create_dm         POST create or retrieve a DM channel
    // hm_chat_mark_read         POST mark channel as read (clear unread badge)
    // hm_chat_pusher_auth       POST authenticate Pusher private/presence channels

    public static function init() {
        add_shortcode( 'hearmed_team_chat', [ __CLASS__, 'render' ] );

        // AJAX — logged in users only (no _nopriv versions — chat is internal)
        $actions = [
            'hm_chat_get_channels',
            'hm_chat_get_messages',
            'hm_chat_send_message',
            'hm_chat_delete_message',
            'hm_chat_create_dm',
            'hm_chat_mark_read',
            'hm_chat_pusher_auth',
            'hm_chat_search_users',
        ];
        foreach ( $actions as $action ) {
            add_action( "wp_ajax_{$action}", [ __CLASS__, str_replace( 'hm_chat_', 'ajax_', $action ) ] );
        }
    }

    // =========================================================================
    // SHORTCODE RENDER
    // =========================================================================

    public static function render( $atts = [] ): string {
        if ( ! is_user_logged_in() ) {
            return '<p class="hm-auth-error">Please log in to access Team Chat.</p>';
        }

        $user    = wp_get_current_user();
        $user_id = $user->ID;

        // Get display name from PostgreSQL staff table
        $staff = HearMed_DB::get_row(
            "SELECT first_name, last_name FROM hearmed_reference.staff
              WHERE wp_user_id = $1 AND is_active = true LIMIT 1",
            [ $user_id ]
        );
        $display_name = $staff
            ? trim( $staff->first_name . ' ' . $staff->last_name )
            : $user->display_name; // fallback to WP if no staff record

        $pusher_key     = get_option( 'hm_pusher_app_key', '' );
        $pusher_cluster = get_option( 'hm_pusher_cluster', 'eu' );

        // Ensure the company-wide channel exists
        self::ensure_company_channel();

        ob_start();
        ?>
        <div id="hm-chat-app"
             data-user-id="<?php echo esc_attr( $user_id ); ?>"
             data-user-name="<?php echo esc_attr( $display_name ); ?>"
             data-pusher-key="<?php echo esc_attr( $pusher_key ); ?>"
             data-pusher-cluster="<?php echo esc_attr( $pusher_cluster ); ?>"
             data-ajax-url="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>"
             data-nonce="<?php echo esc_attr( wp_create_nonce( 'hm_chat_nonce' ) ); ?>">

            <!-- SIDEBAR -->
            <div class="hm-chat-sidebar">
                <div class="hm-chat-sidebar-header">
                    <span class="hm-chat-logo">💬 Team Chat</span>
                    <button class="hm-chat-new-dm-btn" title="New direct message">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    </button>
                </div>

                <div class="hm-chat-section-label">Channels</div>
                <div id="hm-chat-channels-list" class="hm-chat-channel-list">
                    <div class="hm-chat-loading">Loading…</div>
                </div>

                <div class="hm-chat-section-label">Direct Messages</div>
                <div id="hm-chat-dms-list" class="hm-chat-channel-list">
                    <!-- Populated by JS -->
                </div>
            </div>

            <!-- MAIN PANEL -->
            <div class="hm-chat-main">
                <div id="hm-chat-header" class="hm-chat-header">
                    <div class="hm-chat-header-info">
                        <span id="hm-chat-channel-name" class="hm-chat-channel-title">Select a channel</span>
                        <span id="hm-chat-channel-meta" class="hm-chat-channel-meta"></span>
                    </div>
                    <div class="hm-chat-header-actions">
                        <span id="hm-chat-online-count" class="hm-chat-online-badge" title="Online now"></span>
                    </div>
                </div>

                <div id="hm-chat-messages" class="hm-chat-messages">
                    <div class="hm-chat-welcome">
                        <div class="hm-chat-welcome-icon">💬</div>
                        <p>Select a channel or colleague to start chatting.</p>
                    </div>
                </div>

                <div id="hm-chat-input-area" class="hm-chat-input-area" style="display:none;">
                    <div class="hm-chat-typing-indicator" id="hm-chat-typing"></div>
                    <div class="hm-chat-input-row">
                        <textarea id="hm-chat-input"
                                  class="hm-chat-input"
                                  placeholder="Type a message… (Enter to send, Shift+Enter for new line)"
                                  rows="1"
                                  maxlength="4000"></textarea>
                        <button id="hm-chat-send-btn" class="hm-chat-send-btn" title="Send message">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M2 21l21-9L2 3v7l15 2-15 2v7z"/></svg>
                        </button>
                    </div>
                    <div class="hm-chat-input-hint">Enter to send · Shift+Enter for new line</div>
                </div>
            </div>

            <!-- NEW DM MODAL -->
            <div id="hm-chat-dm-modal" class="hm-chat-modal" style="display:none;">
                <div class="hm-chat-modal-box">
                    <div class="hm-chat-modal-header">
                        <h3>New Direct Message</h3>
                        <button class="hm-chat-modal-close">✕</button>
                    </div>
                    <input type="text"
                           id="hm-chat-dm-search"
                           class="hm-chat-dm-search"
                           placeholder="Search staff name…" />
                    <div id="hm-chat-dm-results" class="hm-chat-dm-results"></div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Ensure the single company-wide channel exists in the DB.
     * Called on page load — safe to call multiple times.
     */
    private static function ensure_company_channel(): void {
        $existing = HearMed_DB::get_row(
            "SELECT id FROM hearmed_communication.chat_channels WHERE channel_type = 'company' LIMIT 1"
        );
        if ( $existing ) return;

        // Create the company channel
        $channel_id = HearMed_DB::insert( 'chat_channels', [
            'channel_type' => 'company',
            'channel_name' => 'HearMed Team',
            'created_by'   => get_current_user_id(),
        ] );

        if ( ! $channel_id ) return;

        // Add all active staff (who have a linked WP user account) as members
        $staff = HearMed_DB::get_results(
            "SELECT wp_user_id FROM hearmed_reference.staff
              WHERE is_active = true AND wp_user_id IS NOT NULL"
        );

        foreach ( $staff as $s ) {
            HearMed_DB::insert( 'chat_channel_members', [
                'channel_id' => $channel_id,
                'wp_user_id' => (int) $s->wp_user_id,
            ] );
        }
    }

    /**
     * Verify nonce and login — dies on failure.
     */
    private static function verify(): int {
        check_ajax_referer( 'hm_chat_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Not logged in' ], 401 );
        }
        return get_current_user_id();
    }

    /**
     * Check user is member of channel.
     */
    private static function is_member( int $channel_id, int $user_id ): bool {
        $row = HearMed_DB::get_row(
            "SELECT 1 FROM hearmed_communication.chat_channel_members
              WHERE channel_id = $1 AND wp_user_id = $2",
            [ $channel_id, $user_id ]
        );
        return (bool) $row;
    }

    /**
     * Get Pusher credentials from WP options.
     */
    private static function get_pusher_creds(): array {
        return [
            'app_id'  => get_option( 'hm_pusher_app_id', '' ),
            'key'     => get_option( 'hm_pusher_app_key', '' ),
            'secret'  => get_option( 'hm_pusher_app_secret', '' ),
            'cluster' => get_option( 'hm_pusher_cluster', 'eu' ),
        ];
    }

    /**
     * Get Pusher channel name for a given DB channel.
     */
    private static function pusher_channel_name( object $channel ): string {
        if ( $channel->channel_type === 'company' ) {
            return 'presence-hm-company';
        }
        return 'private-hm-ch-' . $channel->id;
    }

    // =========================================================================
    // AJAX HANDLERS
    // =========================================================================

    /**
     * GET list of channels the current user belongs to.
     */
    public static function ajax_get_channels(): void {
        $user_id = self::verify();

        // Auto-add user to company channel if not already a member
        $company = HearMed_DB::get_row(
            "SELECT id FROM hearmed_communication.chat_channels WHERE channel_type = 'company' LIMIT 1"
        );
        if ( $company && ! self::is_member( (int) $company->id, $user_id ) ) {
            HearMed_DB::insert( 'chat_channel_members', [
                'channel_id' => $company->id,
                'wp_user_id' => $user_id,
            ] );
        }

        $rows = HearMed_DB::get_results(
            "SELECT
                c.id,
                c.channel_type,
                c.channel_name,
                cm.last_read_at,
                (
                    SELECT COUNT(*)
                    FROM hearmed_communication.chat_messages m
                    WHERE m.channel_id = c.id
                      AND m.is_deleted = false
                      AND m.created_at > COALESCE(cm.last_read_at, '1970-01-01')
                ) AS unread_count,
                (
                    SELECT m2.message
                    FROM hearmed_communication.chat_messages m2
                    WHERE m2.channel_id = c.id AND m2.is_deleted = false
                    ORDER BY m2.created_at DESC LIMIT 1
                ) AS last_message,
                (
                    SELECT m3.created_at
                    FROM hearmed_communication.chat_messages m3
                    WHERE m3.channel_id = c.id AND m3.is_deleted = false
                    ORDER BY m3.created_at DESC LIMIT 1
                ) AS last_message_at
             FROM hearmed_communication.chat_channels c
             JOIN hearmed_communication.chat_channel_members cm
               ON cm.channel_id = c.id AND cm.wp_user_id = $1
             WHERE c.is_active = true
             ORDER BY c.channel_type ASC, last_message_at DESC NULLS LAST",
            [ $user_id ]
        );

        // For DM channels, resolve the other person's name
        $channels = [];
        foreach ( $rows as $row ) {
            $item = [
                'id'              => (int) $row->id,
                'type'            => $row->channel_type,
                'name'            => $row->channel_name,
                'unread'          => (int) $row->unread_count,
                'last_message'    => $row->last_message ? mb_substr( $row->last_message, 0, 80 ) : null,
                'last_message_at' => $row->last_message_at,
                'pusher_channel'  => self::pusher_channel_name( $row ),
            ];

            if ( $row->channel_type === 'dm' ) {
                // Find the other member
                $other = HearMed_DB::get_row(
                    "SELECT wp_user_id FROM hearmed_communication.chat_channel_members
                      WHERE channel_id = $1 AND wp_user_id != $2 LIMIT 1",
                    [ $row->id, $user_id ]
                );
                if ( $other ) {
                    $staff = HearMed_DB::get_row(
                        "SELECT first_name, last_name FROM hearmed_reference.staff
                          WHERE wp_user_id = $1 LIMIT 1",
                        [ $other->wp_user_id ]
                    );
                    $item['name'] = $staff
                        ? trim( $staff->first_name . ' ' . $staff->last_name )
                        : 'Unknown';
                    $item['other_user_id'] = (int) $other->wp_user_id;
                }
            }

            $channels[] = $item;
        }

        wp_send_json_success( $channels );
    }

    /**
     * GET message history for a channel.
     */
    public static function ajax_get_messages(): void {
        $user_id    = self::verify();
        $channel_id = (int) ( $_GET['channel_id'] ?? 0 );
        $before_id  = (int) ( $_GET['before_id'] ?? 0 ); // for pagination
        $limit      = 50;

        if ( ! $channel_id ) wp_send_json_error( 'Missing channel_id' );
        if ( ! self::is_member( $channel_id, $user_id ) ) wp_send_json_error( 'Access denied', 403 );

        $where_before = $before_id > 0 ? "AND m.id < $before_id" : '';

        $rows = HearMed_DB::get_results(
            "SELECT
                m.id,
                m.sender_id,
                m.message,
                m.is_edited,
                m.edited_at,
                m.is_deleted,
                m.created_at
             FROM hearmed_communication.chat_messages m
             WHERE m.channel_id = $1
               AND m.is_deleted = false
               {$where_before}
             ORDER BY m.created_at DESC
             LIMIT {$limit}",
            [ $channel_id ]
        );

        // Attach sender display names (batch)
        $sender_ids = array_unique( array_column( $rows, 'sender_id' ) );
        $user_map   = [];

        if ( ! empty( $sender_ids ) ) {
            $placeholders = implode( ',', array_map( fn( $i ) => '$' . ( $i + 1 ), array_keys( $sender_ids ) ) );
            $staff_rows   = HearMed_DB::get_results(
                "SELECT wp_user_id, first_name, last_name
                   FROM hearmed_reference.staff
                  WHERE wp_user_id IN ({$placeholders})",
                array_values( $sender_ids )
            );
            foreach ( $staff_rows as $s ) {
                $user_map[ (int) $s->wp_user_id ] = trim( $s->first_name . ' ' . $s->last_name );
            }
        }

        $messages = [];
        foreach ( array_reverse( $rows ) as $row ) {
            $messages[] = [
                'id'          => (int) $row->id,
                'sender_id'   => (int) $row->sender_id,
                'sender_name' => $user_map[ $row->sender_id ] ?? 'Unknown',
                'message'     => $row->message,
                'is_edited'   => (bool) $row->is_edited,
                'created_at'  => $row->created_at,
                'is_mine'     => ( (int) $row->sender_id === $user_id ),
            ];
        }

        wp_send_json_success( [
            'messages'   => $messages,
            'has_more'   => count( $rows ) === $limit,
            'channel_id' => $channel_id,
        ] );
    }

    /**
     * POST send a message.
     */
    public static function ajax_send_message(): void {
        $user_id    = self::verify();
        $channel_id = (int) ( $_POST['channel_id'] ?? 0 );
        $message    = trim( sanitize_textarea_field( $_POST['message'] ?? '' ) );

        if ( ! $channel_id || ! $message ) wp_send_json_error( 'Missing fields' );
        if ( mb_strlen( $message ) > 4000 )  wp_send_json_error( 'Message too long' );
        if ( ! self::is_member( $channel_id, $user_id ) ) wp_send_json_error( 'Access denied', 403 );

        // Insert into PostgreSQL
        $msg_id = HearMed_DB::insert( 'chat_messages', [
            'channel_id' => $channel_id,
            'sender_id'  => $user_id,
            'message'    => $message,
        ] );

        if ( ! $msg_id ) {
            wp_send_json_error( 'DB error — message not saved' );
        }

        // Update channel updated_at
        HearMed_DB::update( 'chat_channels', [ 'updated_at' => 'now()' ], [ 'id' => $channel_id ] );

        $user = wp_get_current_user();
        $payload = [
            'id'          => $msg_id,
            'channel_id'  => $channel_id,
            'sender_id'   => $user_id,
            'sender_name' => $user->display_name,
            'message'     => $message,
            'created_at'  => current_time( 'c' ),
            'is_mine'     => true,
        ];

        // Trigger Pusher event
        $creds = self::get_pusher_creds();
        if ( $creds['key'] && $creds['secret'] ) {
            self::pusher_trigger( $channel_id, 'new-message', $payload, $creds );
        }

        wp_send_json_success( $payload );
    }

    /**
     * POST soft-delete a message.
     */
    public static function ajax_delete_message(): void {
        $user_id = self::verify();
        $msg_id  = (int) ( $_POST['message_id'] ?? 0 );

        if ( ! $msg_id ) wp_send_json_error( 'Missing message_id' );

        $msg = HearMed_DB::get_row(
            "SELECT id, channel_id, sender_id FROM hearmed_communication.chat_messages WHERE id = $1",
            [ $msg_id ]
        );

        if ( ! $msg ) wp_send_json_error( 'Message not found' );

        // Only sender or admin can delete
        $auth = new HearMed_Auth();
        if ( (int) $msg->sender_id !== $user_id && ! $auth->is_admin() ) {
            wp_send_json_error( 'Access denied', 403 );
        }

        HearMed_DB::update( 'chat_messages',
            [ 'is_deleted' => true, 'deleted_at' => 'now()' ],
            [ 'id' => $msg_id ]
        );

        $creds = self::get_pusher_creds();
        if ( $creds['key'] ) {
            self::pusher_trigger( (int) $msg->channel_id, 'message-deleted',
                [ 'message_id' => $msg_id ], $creds );
        }

        wp_send_json_success( [ 'message_id' => $msg_id ] );
    }

    /**
     * POST create or get a DM channel between current user and another.
     */
    public static function ajax_create_dm(): void {
        $user_id       = self::verify();
        $other_user_id = (int) ( $_POST['other_user_id'] ?? 0 );

        if ( ! $other_user_id || $other_user_id === $user_id ) {
            wp_send_json_error( 'Invalid user' );
        }

        // Validate against PostgreSQL staff table
        $staff = HearMed_DB::get_row(
            "SELECT id FROM hearmed_reference.staff
              WHERE wp_user_id = $1 AND is_active = true LIMIT 1",
            [ $other_user_id ]
        );
        if ( ! $staff ) {
            wp_send_json_error( 'Staff member not found' );
        }

        // Check if DM already exists
        $existing = HearMed_DB::get_row(
            "SELECT c.id FROM hearmed_communication.chat_channels c
             JOIN hearmed_communication.chat_channel_members m1 ON m1.channel_id = c.id AND m1.wp_user_id = $1
             JOIN hearmed_communication.chat_channel_members m2 ON m2.channel_id = c.id AND m2.wp_user_id = $2
             WHERE c.channel_type = 'dm'
             LIMIT 1",
            [ $user_id, $other_user_id ]
        );

        if ( $existing ) {
            wp_send_json_success( [ 'channel_id' => (int) $existing->id, 'existed' => true ] );
            return;
        }

        // Create new DM channel
        $channel_id = HearMed_DB::insert( 'chat_channels', [
            'channel_type' => 'dm',
            'channel_name' => null,
            'created_by'   => $user_id,
        ] );

        HearMed_DB::insert( 'chat_channel_members', [ 'channel_id' => $channel_id, 'wp_user_id' => $user_id ] );
        HearMed_DB::insert( 'chat_channel_members', [ 'channel_id' => $channel_id, 'wp_user_id' => $other_user_id ] );

        wp_send_json_success( [ 'channel_id' => $channel_id, 'existed' => false ] );
    }

    /**
     * POST mark a channel as read.
     */
    public static function ajax_mark_read(): void {
        $user_id    = self::verify();
        $channel_id = (int) ( $_POST['channel_id'] ?? 0 );

        if ( ! $channel_id ) wp_send_json_error( 'Missing channel_id' );

        HearMed_DB::query(
            "UPDATE hearmed_communication.chat_channel_members
                SET last_read_at = NOW()
              WHERE channel_id = $1 AND wp_user_id = $2",
            [ $channel_id, $user_id ]
        );

        wp_send_json_success();
    }

    /**
     * POST Pusher channel authentication.
     * Required for private- and presence- channels.
     */
    public static function ajax_pusher_auth(): void {
        $user_id = self::verify();

        $socket_id   = sanitize_text_field( $_POST['socket_id'] ?? '' );
        $channel     = sanitize_text_field( $_POST['channel_name'] ?? '' );

        if ( ! $socket_id || ! $channel ) {
            wp_send_json_error( 'Missing socket_id or channel_name' );
        }

        // Validate the user has access to this channel
        // private-hm-ch-{id}  or  presence-hm-company
        if ( strpos( $channel, 'private-hm-ch-' ) === 0 ) {
            $ch_id = (int) str_replace( 'private-hm-ch-', '', $channel );
            if ( ! self::is_member( $ch_id, $user_id ) ) {
                wp_send_json_error( 'Access denied', 403 );
            }
        } elseif ( $channel !== 'presence-hm-company' ) {
            wp_send_json_error( 'Unknown channel', 403 );
        }

        $creds = self::get_pusher_creds();
        $user  = wp_get_current_user();

        // Generate Pusher auth signature
        $string_to_sign = $socket_id . ':' . $channel;

        if ( strpos( $channel, 'presence-' ) === 0 ) {
            $user_data      = json_encode( [
                'user_id' => (string) $user_id,
                'user_info' => [
                    'name'    => $user->display_name,
                    'user_id' => $user_id,
                ],
            ] );
            $string_to_sign .= ':' . $user_data;
            $signature       = hash_hmac( 'sha256', $string_to_sign, $creds['secret'] );

            wp_send_json( [
                'auth'         => $creds['key'] . ':' . $signature,
                'channel_data' => $user_data,
            ] );
        } else {
            $signature = hash_hmac( 'sha256', $string_to_sign, $creds['secret'] );
            wp_send_json( [
                'auth' => $creds['key'] . ':' . $signature,
            ] );
        }
    }

    // =========================================================================
    // PUSHER HTTP API HELPER (no SDK required)
    // =========================================================================

    /**
     * AJAX: Search staff by name for DM modal.
     * Available to ALL logged-in staff (not just admins).
     */
    public static function ajax_search_users(): void {
        check_ajax_referer( 'hm_chat_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( 'Not logged in', 401 );

        $query   = sanitize_text_field( $_GET['q'] ?? '' );
        $current = get_current_user_id();

        if ( ! $query ) {
            wp_send_json_success( [] );
            return;
        }

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

    /**
     * Trigger a Pusher event via the HTTP API.
     * Does not require the Pusher PHP SDK — uses wp_remote_post instead.
     */
    private static function pusher_trigger( int $channel_id, string $event, array $data, array $creds ): void {
        // Determine Pusher channel name
        $ch_row = HearMed_DB::get_row(
            "SELECT channel_type FROM hearmed_communication.chat_channels WHERE id = $1",
            [ $channel_id ]
        );
        $pusher_channel = ( $ch_row && $ch_row->channel_type === 'company' )
            ? 'presence-hm-company'
            : 'private-hm-ch-' . $channel_id;

        $body = json_encode( [
            'name'     => $event,
            'data'     => json_encode( $data ),
            'channels' => [ $pusher_channel ],
        ] );

        $timestamp = time();
        $md5_body  = md5( $body );
        $path      = "/apps/{$creds['app_id']}/events";
        $params    = "auth_key={$creds['key']}&auth_timestamp={$timestamp}&auth_version=1.0&body_md5={$md5_body}";
        $sign_str  = "POST\n{$path}\n{$params}";
        $signature = hash_hmac( 'sha256', $sign_str, $creds['secret'] );

        $url = "https://api-{$creds['cluster']}.pusher.com{$path}?{$params}&auth_signature={$signature}";

        wp_remote_post( $url, [
            'body'    => $body,
            'headers' => [ 'Content-Type' => 'application/json' ],
            'timeout' => 5,
            'blocking' => false, // Fire and forget — don't slow down the response
        ] );
    }
}

HearMed_TeamChat::init();