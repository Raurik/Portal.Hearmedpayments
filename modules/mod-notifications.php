<?php
/**
 * HearMed Notifications Module — Full Build
 *
 * Shortcode: [hearmed_notifications]
 * Bell widget injected via wp_footer hook
 *
 * Tables used:
 *   hearmed_communication.internal_notifications
 *   hearmed_communication.notification_recipients
 *
 * @package HearMed_Portal
 * @since   5.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ═══════════════════════════════════════════════════════════════════════════
   AJAX HOOKS
   ═══════════════════════════════════════════════════════════════════════════ */
add_action( 'wp_ajax_hm_notifications_get',          'hm_ajax_notifications_get' );
add_action( 'wp_ajax_hm_notifications_poll',         'hm_ajax_notifications_poll' );
add_action( 'wp_ajax_hm_notifications_mark_read',    'hm_ajax_notifications_mark_read' );
add_action( 'wp_ajax_hm_notifications_mark_all_read','hm_ajax_notifications_mark_all_read' );
add_action( 'wp_ajax_hm_notifications_clear',        'hm_ajax_notifications_clear' );
add_action( 'wp_ajax_hm_notifications_clear_section','hm_ajax_notifications_clear_section' );
add_action( 'wp_ajax_hm_notifications_day_stats',    'hm_ajax_notifications_day_stats' );
add_action( 'wp_ajax_hm_notifications_get_log',      'hm_ajax_notifications_get_log' );
add_action( 'wp_ajax_hm_notifications_unread_count', 'hm_ajax_notifications_unread_count' );

/* ═══════════════════════════════════════════════════════════════════════════
   CLASS MAP — notification_type → visual class
   ═══════════════════════════════════════════════════════════════════════════ */

/**
 * Map notification_type strings to one of the 6 visual classes.
 * Any unmapped type falls into 'internal'.
 */
function hm_notification_class_map() {
    return [
        // Urgent
        'fitting_overdue'     => 'urgent',
        'cheque_reminder'     => 'urgent',
        'system_alert'        => 'urgent',
        'payment_overdue'     => 'urgent',
        'urgent'              => 'urgent',

        // Appointment-focused
        'appointment'         => 'appointment',
        'appointment_created' => 'appointment',
        'appointment_moved'   => 'appointment',
        'appointment_cancel'  => 'appointment',
        'schedule_change'     => 'appointment',

        // Patient-focused
        'patient'             => 'patient',
        'annual_review'       => 'patient',
        'patient_update'      => 'patient',
        'ha_not_received'     => 'patient',

        // Reminders
        'reminder'            => 'reminder',
        'phone_call'          => 'reminder',
        'Phone Call'          => 'reminder',
        'follow_up'           => 'reminder',
        'followup'            => 'reminder',

        // Internal
        'approval_needed'     => 'internal',
        'order_status'        => 'internal',
        'invoice_created'     => 'internal',
        'credit_note_created' => 'internal',
        'repair_update'       => 'internal',
        'internal'            => 'internal',

        // Team chat
        'message'             => 'chat',
        'chat'                => 'chat',
        'team_chat'           => 'chat',
    ];
}

/**
 * Visual class definitions — label, colour, background
 */
function hm_notification_classes() {
    return [
        'urgent'      => [
            'label' => 'Urgent / Overdue',
            'color' => '#DC2626',
            'bg'    => '#fef2f2',
        ],
        'appointment' => [
            'label' => 'Appointments',
            'color' => '#2563EB',
            'bg'    => '#eff6ff',
        ],
        'patient'     => [
            'label' => 'Patients',
            'color' => '#EA580C',
            'bg'    => '#fff7ed',
        ],
        'reminder'    => [
            'label' => 'Reminders',
            'color' => '#7C3AED',
            'bg'    => '#f5f3ff',
        ],
        'internal'    => [
            'label' => 'Internal',
            'color' => '#CA8A04',
            'bg'    => '#fefce8',
        ],
        'chat'        => [
            'label' => 'Team Chat',
            'color' => '#16A34A',
            'bg'    => '#f0fdf4',
        ],
    ];
}

/**
 * Resolve notification_type to visual class key
 */
function hm_resolve_notification_class( $type ) {
    $map = hm_notification_class_map();
    return $map[ $type ] ?? 'internal';
}


/* ═══════════════════════════════════════════════════════════════════════════
   HELPER — get current staff ID
   ═══════════════════════════════════════════════════════════════════════════ */

function hm_notif_staff_id() {
    static $sid = null;
    if ( $sid !== null ) return $sid;

    $uid = get_current_user_id();
    if ( ! $uid ) { $sid = 0; return 0; }

    $db  = HearMed_DB::instance();
    $row = $db->get_row(
        "SELECT id FROM hearmed_reference.staff WHERE wp_user_id = \$1 AND is_active = true LIMIT 1",
        [ $uid ]
    );
    $sid = $row ? (int) $row->id : 0;
    return $sid;
}


/* ═══════════════════════════════════════════════════════════════════════════
   MAIN CLASS
   ═══════════════════════════════════════════════════════════════════════════ */

class HearMed_Notifications {

    /* ── Shortcode + hooks ─────────────────────────────────────── */
    public static function init() {
        add_shortcode( 'hearmed_notifications', [ __CLASS__, 'render' ] );
    }

    /* ── Render shortcode ──────────────────────────────────────── */
    public static function render( $atts = [] ): string {
        if ( ! is_user_logged_in() ) return '';

        ob_start();
        hm_notifications_render();
        return ob_get_clean();
    }

    /* ── Create a notification for a specific staff member ────── */
    public static function create( $staff_id, $event_type, $data = [] ) {
        if ( ! $staff_id ) return false;

        $db = HearMed_DB::instance();

        $subject  = $data['subject'] ?? self::event_type_to_subject( $event_type, $data );
        $message  = $data['message'] ?? $subject;
        $priority = $data['priority'] ?? 'Normal';

        $notif_id = $db->insert( 'hearmed_communication.internal_notifications', [
            'notification_type'   => $event_type,
            'subject'             => $subject,
            'message'             => $message,
            'created_by'          => $data['created_by'] ?? ( hm_notif_staff_id() ?: null ),
            'priority'            => $priority,
            'related_entity_type' => $data['entity_type'] ?? $data['reference_type'] ?? null,
            'related_entity_id'   => $data['entity_id'] ?? $data['reference_id'] ?? null,
            'is_active'           => true,
        ]);

        if ( ! $notif_id ) return false;

        $db->insert( 'hearmed_communication.notification_recipients', [
            'notification_id' => $notif_id,
            'recipient_type'  => 'staff',
            'recipient_id'    => (int) $staff_id,
            'is_read'         => false,
        ]);

        return $notif_id;
    }

    /* ── Create a notification targeting all staff with a role ── */
    public static function create_for_role( $role, $event_type, $data = [] ) {
        $db = HearMed_DB::instance();

        // Find all active staff with this role
        $staff_rows = $db->get_results(
            "SELECT id FROM hearmed_reference.staff WHERE role = \$1 AND is_active = true",
            [ $role ]
        );

        if ( empty( $staff_rows ) ) return false;

        $subject  = $data['subject'] ?? self::event_type_to_subject( $event_type, $data );
        $message  = $data['message'] ?? $subject;
        $priority = $data['priority'] ?? 'Normal';

        $notif_id = $db->insert( 'hearmed_communication.internal_notifications', [
            'notification_type'   => $event_type,
            'subject'             => $subject,
            'message'             => $message,
            'created_by'          => $data['created_by'] ?? ( hm_notif_staff_id() ?: null ),
            'priority'            => $priority,
            'related_entity_type' => $data['entity_type'] ?? null,
            'related_entity_id'   => $data['entity_id'] ?? null,
            'is_active'           => true,
        ]);

        if ( ! $notif_id ) return false;

        foreach ( $staff_rows as $s ) {
            $db->insert( 'hearmed_communication.notification_recipients', [
                'notification_id' => $notif_id,
                'recipient_type'  => 'role',
                'recipient_id'    => (int) $s->id,
                'recipient_role'  => $role,
                'is_read'         => false,
            ]);
        }

        return $notif_id;
    }

    /* ── Generate subject line from event type ─────────────────── */
    private static function event_type_to_subject( $type, $data = [] ) {
        $patient = $data['patient_name'] ?? '';
        $map = [
            'invoice_created'      => 'Invoice created' . ( $patient ? " — {$patient}" : '' ),
            'credit_note_created'  => 'Credit note raised' . ( $patient ? " — {$patient}" : '' ),
            'approval_needed'      => 'Order needs approval' . ( $patient ? " — {$patient}" : '' ),
            'order_status'         => 'Order status update' . ( $patient ? " — {$patient}" : '' ),
            'fitting_overdue'      => 'Fitting overdue' . ( $patient ? " — {$patient}" : '' ),
            'cheque_reminder'      => 'Cheque not sent',
            'repair_update'        => 'Repair returned' . ( $patient ? " — {$patient}" : '' ),
            'annual_review'        => 'Annual review overdue' . ( $patient ? " — {$patient}" : '' ),
            'appointment_created'  => 'New appointment' . ( $patient ? " — {$patient}" : '' ),
            'appointment_cancel'   => 'Appointment cancelled' . ( $patient ? " — {$patient}" : '' ),
            'message'              => 'New message',
        ];
        return $map[ $type ] ?? ucfirst( str_replace( '_', ' ', $type ) );
    }
}

HearMed_Notifications::init();


/* ═══════════════════════════════════════════════════════════════════════════
   SHORTCODE RENDER
   ═══════════════════════════════════════════════════════════════════════════ */

function hm_notifications_render() {
    if ( ! is_user_logged_in() ) return;

    $staff_id = hm_notif_staff_id();
    $role     = HearMed_Auth::current_role();
    $classes  = hm_notification_classes();
    ?>
    <div class="hearmed-notifications" style="display:flex;flex-direction:column;background:#fff;padding:24px;min-height:100%;color:#334155;">

        <!-- Page header -->
        <div class="hm-page-header" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid #e2e8f0;">
            <h1 class="hm-page-title" style="font-size:22px;font-weight:700;color:#151B33;margin:0;">Notifications</h1>
            <div class="hm-notif-header-actions">
                <button class="hm-btn hm-btn--sm hm-btn--ghost" id="hm-notif-mark-all-read">
                    Mark All Read
                </button>
                <button class="hm-btn hm-btn--sm hm-btn--ghost" id="hm-notif-toggle-log">
                    View Log
                </button>
            </div>
        </div>

        <!-- ═══════════ YOUR DAY TODAY ═══════════ -->
        <div class="hm-notif-day-panel" style="margin-bottom:24px;">
            <h2 class="hm-notif-day-title" style="font-size:15px;font-weight:700;color:#151B33;margin:0 0 12px 0;text-transform:uppercase;letter-spacing:.5px;">Your Day Today</h2>
            <div class="hm-notif-day-tiles" id="hm-day-tiles" style="display:grid;grid-template-columns:repeat(6,1fr);gap:12px;">
                <div class="hm-day-tile" data-tile="appointments" style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px 12px;text-align:center;">
                    <div class="hm-day-tile__val" id="tile-appointments" style="font-size:28px;font-weight:800;color:#151B33;line-height:1.1;margin-bottom:4px;">—</div>
                    <div class="hm-day-tile__lbl" style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;">Appointments</div>
                </div>
                <div class="hm-day-tile" data-tile="tests" style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px 12px;text-align:center;">
                    <div class="hm-day-tile__val" id="tile-tests" style="font-size:28px;font-weight:800;color:#151B33;line-height:1.1;margin-bottom:4px;">—</div>
                    <div class="hm-day-tile__lbl" style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;">Tests Scheduled</div>
                </div>
                <div class="hm-day-tile" data-tile="calls" style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px 12px;text-align:center;">
                    <div class="hm-day-tile__val" id="tile-calls" style="font-size:28px;font-weight:800;color:#151B33;line-height:1.1;margin-bottom:4px;">—</div>
                    <div class="hm-day-tile__lbl" style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;">Calls to Make</div>
                </div>
                <div class="hm-day-tile" data-tile="repairs" style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px 12px;text-align:center;">
                    <div class="hm-day-tile__val" id="tile-repairs" style="font-size:28px;font-weight:800;color:#151B33;line-height:1.1;margin-bottom:4px;">—</div>
                    <div class="hm-day-tile__lbl" style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;">Repairs Overdue</div>
                </div>
                <div class="hm-day-tile" data-tile="fittings" style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px 12px;text-align:center;">
                    <div class="hm-day-tile__val" id="tile-fittings" style="font-size:28px;font-weight:800;color:#151B33;line-height:1.1;margin-bottom:4px;">—</div>
                    <div class="hm-day-tile__lbl" style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;">Fittings Due</div>
                </div>
                <div class="hm-day-tile" data-tile="ha_not_received" style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px 12px;text-align:center;">
                    <div class="hm-day-tile__val" id="tile-ha-not-received" style="font-size:28px;font-weight:800;color:#151B33;line-height:1.1;margin-bottom:4px;">—</div>
                    <div class="hm-day-tile__lbl" style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;">HAs Not Received</div>
                </div>
            </div>
        </div>

        <!-- ═══════════ NOTIFICATION SECTIONS ═══════════ -->
        <div class="hm-notif-sections" id="hm-notif-sections" style="display:flex;flex-direction:column;gap:8px;">
            <?php foreach ( $classes as $key => $cls ) : ?>
            <div class="hm-notif-section" data-class="<?php echo esc_attr( $key ); ?>" style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
                <div class="hm-notif-section__header" data-toggle="<?php echo esc_attr( $key ); ?>" style="display:flex;align-items:center;gap:8px;padding:6px 12px;height:34px;cursor:pointer;">
                    <span class="hm-notif-section__stripe" style="background:<?php echo esc_attr( $cls['color'] ); ?>;width:4px;height:18px;border-radius:2px;flex-shrink:0;"></span>
                    <span class="hm-notif-section__label" style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#334155;flex:1;"><?php echo esc_html( $cls['label'] ); ?></span>
                    <span class="hm-notif-section__count" id="count-<?php echo esc_attr( $key ); ?>">0</span>
                    <button class="hm-notif-section__clear hm-btn hm-btn--xs hm-btn--ghost"
                            data-clear-section="<?php echo esc_attr( $key ); ?>"
                            title="Clear all to log">✕ Clear</button>
                    <span class="hm-notif-section__chevron">▾</span>
                </div>
                <div class="hm-notif-section__body" id="section-<?php echo esc_attr( $key ); ?>">
                    <div class="hm-notif-empty">No notifications</div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ═══════════ NOTIFICATION LOG (hidden) ═══════════ -->
        <div class="hm-notif-log" id="hm-notif-log" style="display:none;">
            <div class="hm-notif-log__header">
                <h2 class="hm-notif-log__title">Notification Log</h2>
                <button class="hm-btn hm-btn--sm hm-btn--ghost" id="hm-notif-back-to-live">
                    ← Back to Notifications
                </button>
            </div>
            <div class="hm-notif-log__body" id="hm-notif-log-body">
                <div class="hm-notif-empty">No cleared notifications</div>
            </div>
        </div>

    </div><!-- /.hearmed-notifications -->

    <script>
    /* Pass staff/role data to JS */
    window.hmNotifConfig = {
        staffId:  <?php echo (int) $staff_id; ?>,
        role:     <?php echo wp_json_encode( $role ); ?>,
        classes:  <?php echo wp_json_encode( $classes ); ?>,
        classMap: <?php echo wp_json_encode( hm_notification_class_map() ); ?>
    };
    </script>
    <?php
}


/* ═══════════════════════════════════════════════════════════════════════════
   BELL WIDGET (injected via wp_footer)
   ═══════════════════════════════════════════════════════════════════════════ */

add_action( 'wp_footer', 'hm_notifications_bell_widget', 99 );

function hm_notifications_bell_widget() {
    if ( ! is_user_logged_in() ) return;

    // Only render on portal pages
    $page_slug = get_post_field( 'post_name', get_the_ID() );
    if ( ! $page_slug ) return;

    ?>
    <div id="hm-bell-widget" style="display:none;">
        <a href="/notifications/" id="hm-bell-link" title="Notifications">
            <svg id="hm-bell-svg" xmlns="http://www.w3.org/2000/svg" width="22" height="22"
                 viewBox="0 0 24 24" fill="none" stroke="#F5C218" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            <span id="hm-bell-dot" class="hm-bell-dot" style="display:none;"></span>
        </a>
    </div>

    <script>
    (function(){
        /* Position bell centered below "Quick Till Check" link in .hm-topbar */
        function placeBell(){
            var bell = document.getElementById('hm-bell-widget');
            if(!bell) return;

            var placed = false;
            var topbar = document.querySelector('.hm-topbar');
            if(topbar){
                var links = topbar.querySelectorAll('a');
                for(var i = 0; i < links.length; i++){
                    if(links[i].textContent.trim().toLowerCase().indexOf('quick till') !== -1){
                        /* Use the Elementor widget container as the anchor */
                        var container = links[i].closest('.elementor-widget') || links[i].parentElement;
                        container.style.position = 'relative';
                        /* Absolutely position bell below, centered */
                        bell.style.position = 'absolute';
                        bell.style.top      = '100%';
                        bell.style.left     = '50%';
                        bell.style.transform = 'translateX(-50%)';
                        container.appendChild(bell);
                        bell.style.display = '';
                        placed = true;
                        break;
                    }
                }
                /* Fallback: append to topbar */
                if(!placed){
                    topbar.style.position = 'relative';
                    topbar.appendChild(bell);
                    bell.style.display = '';
                    placed = true;
                }
            }

            /* Last resort: attach to header */
            if(!placed){
                var header = document.querySelector('header, .elementor-location-header, [data-elementor-type="header"]');
                if(header){
                    header.style.position = 'relative';
                    header.appendChild(bell);
                    bell.style.display = '';
                }
            }
        }

        if(document.readyState === 'loading'){
            document.addEventListener('DOMContentLoaded', placeBell);
        } else {
            placeBell();
        }

        /* Poll for unread count — lightweight endpoint */
        function pollBell(){
            if(typeof jQuery === 'undefined' || typeof HM === 'undefined') return;
            jQuery.post(HM.ajax_url, {
                action: 'hm_notifications_unread_count',
                nonce:  HM.nonce
            }, function(r){
                if(!r.success) return;
                var dot  = document.getElementById('hm-bell-dot');
                var svg  = document.getElementById('hm-bell-svg');
                if(!dot || !svg) return;

                if(r.data.count > 0){
                    dot.style.display = '';
                    dot.textContent   = r.data.count > 99 ? '99+' : r.data.count;
                    svg.classList.add('hm-bell-ring');
                    svg.setAttribute('stroke', '#F5C218');
                    setTimeout(function(){ svg.classList.remove('hm-bell-ring'); }, 1200);
                } else {
                    dot.style.display = 'none';
                    svg.setAttribute('stroke', '#94a3b8');
                }
            });
        }

        /* Initial poll + interval */
        setTimeout(pollBell, 2000);
        setInterval(pollBell, 30000);
    })();
    </script>
    <?php
}


/* ═══════════════════════════════════════════════════════════════════════════
   AJAX: Get all notifications for current user
   ═══════════════════════════════════════════════════════════════════════════ */

function hm_ajax_notifications_get() {
    check_ajax_referer( 'hm_nonce', 'nonce' );

    $staff_id = hm_notif_staff_id();
    if ( ! $staff_id ) wp_send_json_error( 'No staff record' );

    $db = HearMed_DB::instance();

    $rows = $db->get_results(
        "SELECT n.id, n.notification_type, n.subject, n.message, n.priority,
                n.related_entity_type, n.related_entity_id, n.created_at, n.created_by,
                nr.id AS recipient_row_id, nr.is_read, nr.read_at, nr.dismissed_at,
                s.first_name || ' ' || s.last_name AS created_by_name
         FROM hearmed_communication.internal_notifications n
         INNER JOIN hearmed_communication.notification_recipients nr
             ON nr.notification_id = n.id
         LEFT JOIN hearmed_reference.staff s ON s.id = n.created_by
         WHERE nr.recipient_id = \$1
           AND n.is_active = true
           AND nr.dismissed_at IS NULL
         ORDER BY n.created_at DESC
         LIMIT 200",
        [ $staff_id ]
    );

    $out       = [];
    $class_map = hm_notification_class_map();

    foreach ( $rows as $r ) {
        $visual = $class_map[ $r->notification_type ] ?? 'internal';
        $out[] = [
            'id'              => (int) $r->id,
            'recipient_id'    => (int) $r->recipient_row_id,
            'type'            => $r->notification_type,
            'class'           => $visual,
            'subject'         => $r->subject,
            'message'         => $r->message,
            'priority'        => $r->priority,
            'entity_type'     => $r->related_entity_type,
            'entity_id'       => $r->related_entity_id ? (int) $r->related_entity_id : null,
            'is_read'         => ( $r->is_read === true || $r->is_read === 't' ),
            'created_at'      => $r->created_at,
            'created_by_name' => $r->created_by_name ?: 'System',
        ];
    }

    wp_send_json_success( [ 'notifications' => $out ] );
}


/* ═══════════════════════════════════════════════════════════════════════════
   AJAX: Poll — returns only new/unread since last check
   ═══════════════════════════════════════════════════════════════════════════ */

function hm_ajax_notifications_poll() {
    check_ajax_referer( 'hm_nonce', 'nonce' );

    $staff_id = hm_notif_staff_id();
    if ( ! $staff_id ) wp_send_json_error( 'No staff record' );

    $since = sanitize_text_field( $_POST['since'] ?? '' );
    if ( ! $since ) $since = gmdate( 'Y-m-d H:i:s', time() - 60 );

    $db   = HearMed_DB::instance();
    $rows = $db->get_results(
        "SELECT n.id, n.notification_type, n.subject, n.message, n.priority,
                n.related_entity_type, n.related_entity_id, n.created_at, n.created_by,
                nr.id AS recipient_row_id, nr.is_read,
                s.first_name || ' ' || s.last_name AS created_by_name
         FROM hearmed_communication.internal_notifications n
         INNER JOIN hearmed_communication.notification_recipients nr
             ON nr.notification_id = n.id
         LEFT JOIN hearmed_reference.staff s ON s.id = n.created_by
         WHERE nr.recipient_id = \$1
           AND n.is_active = true
           AND nr.dismissed_at IS NULL
           AND n.created_at > \$2::timestamp
         ORDER BY n.created_at DESC
         LIMIT 50",
        [ $staff_id, $since ]
    );

    $out       = [];
    $class_map = hm_notification_class_map();

    foreach ( $rows as $r ) {
        $visual = $class_map[ $r->notification_type ] ?? 'internal';
        $out[] = [
            'id'              => (int) $r->id,
            'recipient_id'    => (int) $r->recipient_row_id,
            'type'            => $r->notification_type,
            'class'           => $visual,
            'subject'         => $r->subject,
            'message'         => $r->message,
            'priority'        => $r->priority,
            'entity_type'     => $r->related_entity_type,
            'entity_id'       => $r->related_entity_id ? (int) $r->related_entity_id : null,
            'is_read'         => ( $r->is_read === true || $r->is_read === 't' ),
            'created_at'      => $r->created_at,
            'created_by_name' => $r->created_by_name ?: 'System',
        ];
    }

    wp_send_json_success( [ 'notifications' => $out ] );
}


/* ═══════════════════════════════════════════════════════════════════════════
   AJAX: Unread count (lightweight — for bell badge)
   ═══════════════════════════════════════════════════════════════════════════ */

function hm_ajax_notifications_unread_count() {
    check_ajax_referer( 'hm_nonce', 'nonce' );

    $staff_id = hm_notif_staff_id();
    if ( ! $staff_id ) wp_send_json_success( [ 'count' => 0 ] );

    $db    = HearMed_DB::instance();
    $count = $db->get_var(
        "SELECT COUNT(*)
         FROM hearmed_communication.notification_recipients nr
         INNER JOIN hearmed_communication.internal_notifications n
             ON n.id = nr.notification_id
         WHERE nr.recipient_id = \$1
           AND nr.is_read = false
           AND nr.dismissed_at IS NULL
           AND n.is_active = true",
        [ $staff_id ]
    );

    wp_send_json_success( [ 'count' => (int) $count ] );
}


/* ═══════════════════════════════════════════════════════════════════════════
   AJAX: Mark single notification read
   ═══════════════════════════════════════════════════════════════════════════ */

function hm_ajax_notifications_mark_read() {
    check_ajax_referer( 'hm_nonce', 'nonce' );

    $recipient_id = intval( $_POST['recipient_id'] ?? 0 );
    if ( ! $recipient_id ) wp_send_json_error( 'Missing recipient_id' );

    $staff_id = hm_notif_staff_id();
    $db       = HearMed_DB::instance();

    $db->query(
        "UPDATE hearmed_communication.notification_recipients
         SET is_read = true, read_at = NOW()
         WHERE id = \$1 AND recipient_id = \$2",
        [ $recipient_id, $staff_id ]
    );

    wp_send_json_success();
}


/* ═══════════════════════════════════════════════════════════════════════════
   AJAX: Mark ALL read
   ═══════════════════════════════════════════════════════════════════════════ */

function hm_ajax_notifications_mark_all_read() {
    check_ajax_referer( 'hm_nonce', 'nonce' );

    $staff_id = hm_notif_staff_id();
    if ( ! $staff_id ) wp_send_json_error( 'No staff record' );

    $db = HearMed_DB::instance();
    $db->query(
        "UPDATE hearmed_communication.notification_recipients
         SET is_read = true, read_at = NOW()
         WHERE recipient_id = \$1 AND is_read = false AND dismissed_at IS NULL",
        [ $staff_id ]
    );

    wp_send_json_success();
}


/* ═══════════════════════════════════════════════════════════════════════════
   AJAX: Clear single notification (dismiss to log)
   ═══════════════════════════════════════════════════════════════════════════ */

function hm_ajax_notifications_clear() {
    check_ajax_referer( 'hm_nonce', 'nonce' );

    $recipient_id = intval( $_POST['recipient_id'] ?? 0 );
    if ( ! $recipient_id ) wp_send_json_error( 'Missing recipient_id' );

    $staff_id = hm_notif_staff_id();
    $db       = HearMed_DB::instance();

    $db->query(
        "UPDATE hearmed_communication.notification_recipients
         SET dismissed_at = NOW(), is_read = true, read_at = COALESCE(read_at, NOW())
         WHERE id = \$1 AND recipient_id = \$2",
        [ $recipient_id, $staff_id ]
    );

    wp_send_json_success();
}


/* ═══════════════════════════════════════════════════════════════════════════
   AJAX: Clear entire section (by visual class)
   ═══════════════════════════════════════════════════════════════════════════ */

function hm_ajax_notifications_clear_section() {
    check_ajax_referer( 'hm_nonce', 'nonce' );

    $section  = sanitize_text_field( $_POST['section'] ?? '' );
    $staff_id = hm_notif_staff_id();
    if ( ! $section || ! $staff_id ) wp_send_json_error( 'Missing data' );

    // Get all notification_types that map to this visual class
    $map   = hm_notification_class_map();
    $types = [];
    foreach ( $map as $type => $cls ) {
        if ( $cls === $section ) $types[] = $type;
    }
    if ( empty( $types ) ) wp_send_json_error( 'Unknown section' );

    $db = HearMed_DB::instance();

    // Build IN clause
    $placeholders = [];
    $params       = [ $staff_id ];
    $idx          = 2;
    foreach ( $types as $t ) {
        $placeholders[] = '$' . $idx;
        $params[]       = $t;
        $idx++;
    }
    $in = implode( ',', $placeholders );

    $db->query(
        "UPDATE hearmed_communication.notification_recipients nr
         SET dismissed_at = NOW(), is_read = true, read_at = COALESCE(nr.read_at, NOW())
         FROM hearmed_communication.internal_notifications n
         WHERE nr.notification_id = n.id
           AND nr.recipient_id = \$1
           AND nr.dismissed_at IS NULL
           AND n.notification_type IN ({$in})",
        $params
    );

    wp_send_json_success();
}


/* ═══════════════════════════════════════════════════════════════════════════
   AJAX: Get notification log (dismissed items)
   ═══════════════════════════════════════════════════════════════════════════ */

function hm_ajax_notifications_get_log() {
    check_ajax_referer( 'hm_nonce', 'nonce' );

    $staff_id = hm_notif_staff_id();
    if ( ! $staff_id ) wp_send_json_error( 'No staff record' );

    $db   = HearMed_DB::instance();
    $rows = $db->get_results(
        "SELECT n.id, n.notification_type, n.subject, n.message, n.priority,
                n.related_entity_type, n.related_entity_id, n.created_at,
                nr.dismissed_at,
                s.first_name || ' ' || s.last_name AS created_by_name
         FROM hearmed_communication.internal_notifications n
         INNER JOIN hearmed_communication.notification_recipients nr
             ON nr.notification_id = n.id
         LEFT JOIN hearmed_reference.staff s ON s.id = n.created_by
         WHERE nr.recipient_id = \$1
           AND nr.dismissed_at IS NOT NULL
         ORDER BY nr.dismissed_at DESC
         LIMIT 200",
        [ $staff_id ]
    );

    $out       = [];
    $class_map = hm_notification_class_map();

    foreach ( $rows as $r ) {
        $visual = $class_map[ $r->notification_type ] ?? 'internal';
        $out[] = [
            'id'              => (int) $r->id,
            'type'            => $r->notification_type,
            'class'           => $visual,
            'subject'         => $r->subject,
            'message'         => $r->message,
            'priority'        => $r->priority,
            'entity_type'     => $r->related_entity_type,
            'entity_id'       => $r->related_entity_id ? (int) $r->related_entity_id : null,
            'created_at'      => $r->created_at,
            'dismissed_at'    => $r->dismissed_at,
            'created_by_name' => $r->created_by_name ?: 'System',
        ];
    }

    wp_send_json_success( [ 'log' => $out ] );
}


/* ═══════════════════════════════════════════════════════════════════════════
   AJAX: Day stats for "Your Day Today" tiles
   ═══════════════════════════════════════════════════════════════════════════ */

function hm_ajax_notifications_day_stats() {
    check_ajax_referer( 'hm_nonce', 'nonce' );

    $staff_id = hm_notif_staff_id();
    if ( ! $staff_id ) wp_send_json_error( 'No staff record' );

    $db    = HearMed_DB::instance();
    $today = gmdate( 'Y-m-d' );

    // Appointments today for this staff member
    $appointments = (int) $db->get_var(
        "SELECT COUNT(*) FROM hearmed_core.appointments
         WHERE staff_id = \$1
           AND DATE(start_time) = \$2::date
           AND status NOT IN ('Cancelled','No Show')",
        [ $staff_id, $today ]
    );

    // Tests scheduled today
    $tests = (int) $db->get_var(
        "SELECT COUNT(*) FROM hearmed_core.appointments
         WHERE staff_id = \$1
           AND DATE(start_time) = \$2::date
           AND status NOT IN ('Cancelled','No Show')
           AND appointment_type_id IN (
               SELECT id FROM hearmed_reference.appointment_types
               WHERE LOWER(name) LIKE '%test%' OR LOWER(name) LIKE '%audio%'
           )",
        [ $staff_id, $today ]
    );

    // Calls to make (active reminders of type phone_call / Phone Call / follow_up)
    $calls = (int) $db->get_var(
        "SELECT COUNT(*)
         FROM hearmed_communication.notification_recipients nr
         INNER JOIN hearmed_communication.internal_notifications n ON n.id = nr.notification_id
         WHERE nr.recipient_id = \$1
           AND nr.dismissed_at IS NULL
           AND n.is_active = true
           AND n.notification_type IN ('phone_call','Phone Call','follow_up','followup')",
        [ $staff_id ]
    );

    // Repairs overdue
    $repairs = (int) $db->get_var(
        "SELECT COUNT(*) FROM hearmed_core.repairs
         WHERE assigned_to = \$1
           AND status IN ('Sent','Pending')
           AND sent_date IS NOT NULL
           AND sent_date < (NOW() - INTERVAL '28 days')",
        [ $staff_id ]
    );

    // Fittings due
    $fittings = (int) $db->get_var(
        "SELECT COUNT(*) FROM hearmed_core.orders
         WHERE dispenser_id = \$1
           AND status = 'Received'
           AND fitting_date IS NOT NULL
           AND fitting_date <= \$2::date",
        [ $staff_id, $today ]
    );

    // Hearing aids not received (ordered but not yet received)
    $ha_not_received = (int) $db->get_var(
        "SELECT COUNT(*) FROM hearmed_core.orders
         WHERE dispenser_id = \$1
           AND status = 'Ordered'",
        [ $staff_id ]
    );

    wp_send_json_success([
        'appointments'    => $appointments,
        'tests'           => $tests,
        'calls'           => $calls,
        'repairs'         => $repairs,
        'fittings'        => $fittings,
        'ha_not_received' => $ha_not_received,
    ]);
}


/* ═══════════════════════════════════════════════════════════════════════════
   GLOBAL HELPER: hearmed_notify()
   ═══════════════════════════════════════════════════════════════════════════ */

/**
 * Create a notification for a specific staff member.
 * Convenience wrapper around HearMed_Notifications::create().
 *
 * @param string   $event_type  Notification type (maps to visual class via hm_notification_class_map)
 * @param string   $message     Notification message / subject
 * @param int      $staff_id    Target staff member ID
 * @param array    $extra       Optional: entity_type, entity_id, priority, created_by
 * @return int|false  Notification ID on success, false on failure
 */
function hearmed_notify( $event_type, $message, $staff_id, $extra = [] ) {
    if ( ! class_exists( 'HearMed_Notifications' ) ) return false;

    $data = array_merge( $extra, [
        'subject' => $message,
        'message' => $message,
    ]);

    return HearMed_Notifications::create( $staff_id, $event_type, $data );
}
