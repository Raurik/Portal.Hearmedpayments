<?php
/**
 * HearMed Core System
 * 
 * Central system controller. Manages all initialization, routing, and core services.
 * This is the ONLY entry point for the entire system.
 * 
 * @package HearMed_Portal
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HearMed_Core {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Core components
     */
    public $enqueue;
    public $router;
    public $auth;
    public $db;
    public $ajax;
    
    /**
     * Get singleton instance
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - Initialize core components
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_components();
        $this->init_hooks();
    }
    
    /**
     * Load required core files
     */
    private function load_dependencies() {
        // Core utilities
        require_once HEARMED_PATH . 'core/class-hearmed-utils.php';
        require_once HEARMED_PATH . 'core/class-hearmed-db.php';
        require_once HEARMED_PATH . 'core/class-hearmed-auth.php';
        require_once HEARMED_PATH . 'core/class-hearmed-staff-auth.php';
        require_once HEARMED_PATH . 'core/class-portal-auth.php';
        require_once HEARMED_PATH . 'core/class-hearmed-gdpr-retention.php';
        require_once HEARMED_PATH . 'core/class-hearmed-enqueue.php';
        require_once HEARMED_PATH . 'core/class-hearmed-router.php';
        require_once HEARMED_PATH . 'core/class-hearmed-ajax.php';
        require_once HEARMED_PATH . 'core/class-hearmed-qbo.php';
        
        // Load selected legacy includes for compatibility (avoid duplicate class definitions)
        $safe_legacy = [
            HEARMED_PATH . 'includes/class-hearmed-roles.php',
            HEARMED_PATH . 'includes/class-cpts.php',
        ];
        foreach ( $safe_legacy as $file ) {
            if ( file_exists( $file ) ) {
                require_once $file;
            }
        }

        // Load admin shortcode providers used by portal pages.
        // These files self-register shortcodes in their constructors and are
        // required on frontend portal routes where the shortcodes are rendered.
        $admin_shortcode_files = [
            HEARMED_PATH . 'admin/admin-console.php',
            HEARMED_PATH . 'admin/admin-calendar-settings.php',
            HEARMED_PATH . 'admin/admin-manage-users.php',
            HEARMED_PATH . 'admin/admin-clinics.php',
            HEARMED_PATH . 'admin/admin-products.php',
            HEARMED_PATH . 'admin/admin-kpi-targets.php',
            HEARMED_PATH . 'admin/admin-sms-templates.php',
            HEARMED_PATH . 'admin/admin-audit-export.php',
            HEARMED_PATH . 'admin/admin-audiometers.php',
            HEARMED_PATH . 'admin/admin-settings.php',
            HEARMED_PATH . 'admin/admin-taxonomies.php',
            HEARMED_PATH . 'admin/admin-groups.php',
            HEARMED_PATH . 'admin/admin-resources.php',
            HEARMED_PATH . 'admin/admin-dispenser-schedules.php',
            HEARMED_PATH . 'admin/admin-staff-login.php',
            HEARMED_PATH . 'admin/admin-appointment-types.php',
            HEARMED_PATH . 'admin/admin-appointment-type-detail.php',
            HEARMED_PATH . 'admin/admin-blockouts.php',
            HEARMED_PATH . 'admin/admin-holidays.php',
            HEARMED_PATH . 'admin/admin-exclusions.php',
            HEARMED_PATH . 'admin/admin-chat-logs.php',
            HEARMED_PATH . 'admin/admin-roles.php',
            HEARMED_PATH . 'admin/admin-availability.php',
            HEARMED_PATH . 'admin/admin-document-templates.php',
            HEARMED_PATH . 'admin/admin-finance-form-builder.php',
            HEARMED_PATH . 'admin/admin-global-settings.php',
            HEARMED_PATH . 'admin/admin-clinical-review.php',
            HEARMED_PATH . 'admin/admin-run-migration.php',
            // auth-debug.php REMOVED — exposed unauthenticated debug endpoints
        ];
        foreach ( $admin_shortcode_files as $file ) {
            if ( file_exists( $file ) ) {
                require_once $file;
            }
        }

        // Load modules (business logic providers)
        // These modules provide shortcodes and AJAX handlers for portal pages
        $module_files = [
            HEARMED_PATH . 'modules/mod-calendar.php',
            HEARMED_PATH . 'modules/mod-patients.php',
            HEARMED_PATH . 'modules/mod-orders.php',
            HEARMED_PATH . 'modules/mod-approvals.php',
            HEARMED_PATH . 'modules/mod-fitting.php',
            HEARMED_PATH . 'modules/mod-order-status.php',
            HEARMED_PATH . 'modules/mod-notifications.php',
            HEARMED_PATH . 'modules/mod-repairs.php',
            HEARMED_PATH . 'modules/mod-refunds.php',
            HEARMED_PATH . 'modules/mod-stock.php',
            HEARMED_PATH . 'modules/mod-team-chat.php',
            HEARMED_PATH . 'modules/mod-accounting.php',
            HEARMED_PATH . 'modules/mod-reports.php',
            HEARMED_PATH . 'modules/mod-commissions.php',
            HEARMED_PATH . 'modules/mod-kpi.php',
            HEARMED_PATH . 'modules/mod-cash.php',
            HEARMED_PATH . 'core/class-hearmed-print-templates.php',
            HEARMED_PATH . 'core/class-hearmed-invoice.php',
            HEARMED_PATH . 'core/class-hearmed-finance.php',
            HEARMED_PATH . 'modules/mod-forms.php', 
            HEARMED_PATH . 'modules/mod-clinical-pdf.php',
            HEARMED_PATH . 'modules/mod-commission-pin.php',
            HEARMED_PATH . 'admin/admin-form-templates.php',
            HEARMED_PATH . 'modules/mod-qbo-review.php',
        ];
        foreach ( $module_files as $file ) {
            if ( file_exists( $file ) ) {
                require_once $file;
            }
        }
    }
    
    /**
     * Initialize core components
     */
    private function init_components() {
        $this->db      = new HearMed_DB();
        $this->auth    = new HearMed_Auth();
        $this->enqueue = new HearMed_Enqueue();
        $this->router  = new HearMed_Router();
        $this->ajax    = new HearMed_Ajax();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Register shortcodes early
        add_action( 'init', [ $this->router, 'register_shortcodes' ], 5 );
        
        // Ensure WP roles exist (idempotent, runs once per deploy)
        add_action( 'init', [ 'HearMed_Roles', 'register_wp_roles' ], 5 );

        // System ready hook
        add_action( 'init', [ $this, 'system_ready' ], 10 );

        // ── Mirror all wp_ajax_hm_* actions to wp_ajax_nopriv_hm_* ───
        // All 197+ AJAX handlers are registered on wp_ajax_ only. If the
        // WP user bridge fails (e.g. because WP users were deleted),
        // WordPress treats the request as unauthenticated and only fires
        // wp_ajax_nopriv_ hooks.  This late hook ensures every hm_*
        // action has a nopriv counterpart so AJAX never silently fails.
        // Each handler already checks PortalAuth internally.
        add_action( 'init', [ $this, 'mirror_ajax_nopriv' ], 99 );

        // One-time repair: fix corrupted Elementor page settings (serialized string instead of array)
        add_action( 'admin_init', [ $this, 'repair_elementor_page_settings' ], 1 );
    }

    /**
     * Auto-mirror every wp_ajax_hm_* hook to wp_ajax_nopriv_hm_*.
     *
     * WordPress only fires wp_ajax_* when is_user_logged_in() is true.
     * If the WP user bridge fails (deleted users, email conflicts, etc),
     * all AJAX calls silently return 0/-1.  By mirroring every hm_*
     * action to its nopriv equivalent, the handler still runs and
     * checks PortalAuth (not WP auth) for authorization.
     */
    public function mirror_ajax_nopriv() {
        global $wp_filter;
        foreach ( $wp_filter as $tag => $hook ) {
            if ( strpos( $tag, 'wp_ajax_hm_' ) !== 0 ) continue;
            $nopriv_tag = 'wp_ajax_nopriv_' . substr( $tag, strlen( 'wp_ajax_' ) );
            if ( isset( $wp_filter[ $nopriv_tag ] ) ) continue; // already registered
            foreach ( $hook->callbacks as $priority => $callbacks ) {
                foreach ( $callbacks as $cb ) {
                    add_action( $nopriv_tag, $cb['function'], $priority, $cb['accepted_args'] );
                }
            }
        }
    }
    
    /**
     * System ready callback
     */
    public function system_ready() {
        // Ensure required portal pages exist
        $this->ensure_portal_pages();

        $this->ensure_privacy_column();

        // Self-test: verify redirect-loop protections are intact.
        // Runs at most once per hour to avoid overhead.
        $this->verify_redirect_loop_guards();

        // Hook for when system is fully initialized
        do_action( 'hearmed_system_ready' );
    }

    /* ═══════════════════════════════════════════════════════════════════
     * ██  REDIRECT-LOOP SELF-TEST — DO NOT REMOVE  ██████████████████
     * ═══════════════════════════════════════════════════════════════════
     * Automated guard that runs once per hour and verifies the three
     * protections that prevent ERR_TOO_MANY_REDIRECTS are still intact.
     * If any check fails, it error_logs a CRITICAL warning so it shows
     * up in server monitoring.
     *
     * The three protections:
     *   1. Portal pages exist in WP (especially 'calendar')
     *   2. redirect_canonical filter is hooked to block_portal_canonical
     *   3. template_redirect fires auth_redirect at priority 1
     *
     * Last verified working: 2026-03-04
     * ═══════════════════════════════════════════════════════════════════ */
    public function verify_redirect_loop_guards() {
        // Throttle: once per hour
        $last_check = get_transient( 'hm_redirect_guard_check' );
        if ( $last_check ) return;
        set_transient( 'hm_redirect_guard_check', time(), 3600 );

        $errors = [];

        // Check 1: Critical portal pages must be published
        $critical_pages = [ 'login', 'calendar', 'patients' ];
        foreach ( $critical_pages as $slug ) {
            $found = get_posts( [
                'name'        => $slug,
                'post_type'   => 'page',
                'post_status' => 'publish',
                'numberposts' => 1,
                'fields'      => 'ids',
            ] );
            if ( empty( $found ) ) {
                $errors[] = 'Portal page "' . $slug . '" is missing or not published';
            }
        }

        // Check 2: redirect_canonical filter must be hooked
        if ( ! has_filter( 'redirect_canonical', [ $this->router, 'block_portal_canonical' ] ) ) {
            $errors[] = 'block_portal_canonical filter is not attached to redirect_canonical';
        }

        // Check 3: auth_redirect must be on template_redirect at priority 1
        $auth_priority = has_action( 'template_redirect', [ $this->router, 'auth_redirect' ] );
        if ( $auth_priority === false ) {
            $errors[] = 'auth_redirect is not attached to template_redirect';
        } elseif ( $auth_priority > 5 ) {
            $errors[] = 'auth_redirect priority is ' . $auth_priority . ' (must be <=5, should be 1)';
        }

        if ( ! empty( $errors ) ) {
            error_log( '[HM CRITICAL] Redirect-loop guards BROKEN — users may be locked out: ' . implode( ' | ', $errors ) );
        }
    }

    /**
     * Auto-create required WordPress pages if they don't exist.
     * Each page maps a slug → shortcode for portal routing.
     * Only runs the check once (flag stored in settings).
     */
    public function ensure_portal_pages() {
        $version = 'v6'; // bumped: re-create any pages lost during rollbacks

        // Always verify critical pages exist even if version flag is set.
        // Pages can vanish (trash, deletion, rollback) while the flag persists.
        if ( HearMed_Settings::get( 'hm_pages_ensured_' . $version, '' ) ) {
            // Quick check: if any required slug is missing, clear the flag and re-run
            $critical_slugs = [ 'login', 'calendar', 'patients' ];
            $missing = false;
            foreach ( $critical_slugs as $slug ) {
                $found = get_posts( [
                    'name'        => $slug,
                    'post_type'   => 'page',
                    'post_status' => 'publish',
                    'numberposts' => 1,
                    'fields'      => 'ids',
                ] );
                if ( empty( $found ) ) {
                    $missing = true;
                    break;
                }
            }
            if ( ! $missing ) return;
            // A critical page is missing — fall through and recreate
        }

        $pages = [
            'login' => [
                'title'     => 'Login',
                'shortcode' => '[hearmed_staff_login]',
            ],
            'calendar' => [
                'title'     => 'Calendar',
                'shortcode' => '[hearmed_calendar]',
            ],
            'patients' => [
                'title'     => 'Patients',
                'shortcode' => '[hearmed_patients]',
            ],
            'orders' => [
                'title'     => 'Orders',
                'shortcode' => '[hearmed_orders]',
            ],
            'order-status' => [
                'title'     => 'Order Status',
                'shortcode' => '[hearmed_orders]',
            ],
            'accounting' => [
                'title'     => 'Accounting',
                'shortcode' => '[hearmed_accounting]',
            ],
            'repairs' => [
                'title'     => 'Repairs',
                'shortcode' => '[hearmed_repairs]',
            ],
            'refunds' => [
                'title'     => 'Refunds',
                'shortcode' => '[hearmed_refunds]',
            ],
            'stock' => [
                'title'     => 'Stock',
                'shortcode' => '[hearmed_stock]',
            ],
            'reporting' => [
                'title'     => 'Reporting',
                'shortcode' => '[hearmed_reporting]',
            ],
            'notifications' => [
                'title'     => 'Notifications',
                'shortcode' => '[hearmed_notifications]',
            ],
            'kpi' => [
                'title'     => 'KPI',
                'shortcode' => '[hearmed_kpi]',
            ],
            'clinical-review' => [
                'title'     => 'Clinical Review',
                'shortcode' => '[hearmed_clinical_review]',
            ],
        ];

        foreach ( $pages as $slug => $info ) {
            // Check for existing page (any status — including trash/draft)
            $existing = get_posts( [
                'name'        => $slug,
                'post_type'   => 'page',
                'post_status' => [ 'publish', 'draft', 'trash', 'private', 'pending' ],
                'numberposts' => 1,
            ] );

            if ( $existing ) {
                $page = $existing[0];
                $needs_update = false;
                $update_data  = [ 'ID' => $page->ID ];

                // If not published, restore it
                if ( $page->post_status !== 'publish' ) {
                    $update_data['post_status'] = 'publish';
                    $update_data['post_name']   = $slug;
                    $needs_update = true;
                }

                // Ensure the shortcode is in post_content.
                // Elementor stores its own content in _elementor_data and may
                // leave post_content empty — but WP shortcode processing runs
                // on post_content. We need it there as a fallback.
                if ( strpos( $page->post_content, $info['shortcode'] ) === false ) {
                    $update_data['post_content'] = $info['shortcode'];
                    $needs_update = true;
                }

                if ( $needs_update ) {
                    wp_update_post( $update_data );
                }
                continue;
            }

            wp_insert_post( [
                'post_title'   => $info['title'],
                'post_name'    => $slug,
                'post_content' => $info['shortcode'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'meta_input'   => [
                    '_wp_page_template'       => 'elementor_canvas',
                    '_elementor_page_settings' => [ 'template' => 'elementor_canvas' ],
                ],
            ] );
        }

        HearMed_Settings::set( 'hm_pages_ensured_' . $version, '1' );
    }

    /**
     * One-time repair for corrupted _elementor_page_settings meta.
     *
     * Elementor 3.35 expects an array but some portal pages ended up with a
     * serialised string in post-meta, causing a fatal TypeError in
     * Controls_Stack::sanitize_settings().
     *
     * Runs once then sets an option flag so it never runs again.
     */
    public function repair_elementor_page_settings() {
        if ( HearMed_Settings::get( 'hm_elementor_meta_repaired_v1', '' ) ) {
            return;
        }

        // Pages that may have corrupted meta
        $page_ids = [ 765 ];

        foreach ( $page_ids as $pid ) {
            $raw = get_post_meta( $pid, '_elementor_page_settings', true );

            if ( is_string( $raw ) && '' !== $raw ) {
                $unserialized = maybe_unserialize( $raw );

                if ( is_array( $unserialized ) ) {
                    // It was double-serialised — store the real array
                    delete_post_meta( $pid, '_elementor_page_settings' );
                    update_post_meta( $pid, '_elementor_page_settings', $unserialized );
                } else {
                    // Completely garbled — reset to safe default
                    delete_post_meta( $pid, '_elementor_page_settings' );
                    update_post_meta( $pid, '_elementor_page_settings', [
                        'template' => 'elementor_canvas',
                    ] );
                }
            }
        }

        HearMed_Settings::set( 'hm_elementor_meta_repaired_v1', '1' );
    }
    
    /**
     * Get the full database table name for a given slug.
     *
     * Returns PostgreSQL schema.table format.
     * Uses mapping from HearMed_DB::table()
     *
     * @param string $slug Table slug (e.g. 'appointments', 'calendar_settings')
     * @return string Full table name with schema
     */
    public static function table( $slug ) {
        return HearMed_DB::table( $slug );
    }

    /**
     * Read a single setting value from the calendar_settings table.
     *
     * @param string $key     Column name in the calendar_settings table
     * @param mixed  $default Value to return when the table or column has no data
     * @return mixed Setting value or $default
     */
    public static function setting( $key, $default = '' ) {
        global $wpdb;
        $t = self::table( 'calendar_settings' );
        
        // For PostgreSQL, check table existence differently
        $table_exists = $wpdb->get_var( 
            $wpdb->prepare( 
                "SELECT to_regclass(%s)", 
                $t 
            ) 
        ) !== null;
        
        if ( ! $table_exists ) {
            return $default;
        }
        
        // PostgreSQL uses double quotes for identifiers, not backticks
        $val = $wpdb->get_var( 
            $wpdb->prepare(
                "SELECT \"{$key}\" FROM {$t} LIMIT 1"
            )
        );
        
        return ( $val !== null ) ? $val : $default;
    }

    /**
     * Static logger method (called during activation/deactivation)
     * 
     * @param string $action Action being logged
     * @param string $entity Entity type
     * @param int $entity_id Entity ID
     * @param array $meta Additional metadata
     */
    public static function log( $action, $entity, $entity_id = 0, $meta = [] ) {
        global $wpdb;
        
        // Only log if debug is enabled OR during activation/deactivation
        $should_log = ( defined('WP_DEBUG') && WP_DEBUG ) || 
                      in_array( $action, ['plugin_activated', 'plugin_deactivated'] );
        
        if ( ! $should_log ) {
            return;
        }
        
        // Try to log to database if audit table exists
        $audit_table = HearMed_DB::table('audit_log');
        
        // For PostgreSQL, check table existence
        $table_exists = $wpdb->get_var( 
            $wpdb->prepare( 
                "SELECT to_regclass(%s)", 
                $audit_table 
            ) 
        ) !== null;
        
        if ( $table_exists ) {
            $wpdb->insert(
                $audit_table,
                [
                    'action' => $action,
                    'entity_type' => $entity,
                    'entity_id' => intval( $entity_id ),
                    'user_id' => PortalAuth::staff_id(),
                    'details' => wp_json_encode( $meta ),
                    'created_at' => current_time( 'mysql' ),
                ],
                ['%s', '%s', '%d', '%d', '%s', '%s']
            );
        }
        
        // Always log to error_log for debugging
        error_log(
            sprintf(
                '[HearMed] %s | %s #%d | User: %d | Meta: %s',
                $action,
                $entity,
                intval( $entity_id ),
                PortalAuth::staff_id(),
                wp_json_encode( $meta )
            )
        );
    }

    /**
     * Ensure the privacy_notice_accepted column exists on staff table.
     * Runs once, gated by a settings flag.
     */
    private function ensure_privacy_column() {
        if ( HearMed_Settings::get( 'hm_privacy_col_added_v1', '' ) ) {
            return;
        }

        $db = HearMed_DB::instance();
        if ( ! $db ) return;

        $exists = $db->get_var(
            "SELECT column_name FROM information_schema.columns
             WHERE table_schema = 'hearmed_reference'
               AND table_name = 'staff'
               AND column_name = 'privacy_notice_accepted'"
        );

        if ( ! $exists ) {
            $db->query(
                "ALTER TABLE hearmed_reference.staff
                 ADD COLUMN privacy_notice_accepted BOOLEAN DEFAULT FALSE"
            );
        }

        HearMed_Settings::set( 'hm_privacy_col_added_v1', '1' );
    }
}

// Backward-compatibility alias so legacy module code that references
// HearMed_Portal::table(), HearMed_Portal::setting(), and
// HearMed_Portal::log() continues to work without any changes to those files.
if ( ! class_exists( 'HearMed_Portal' ) ) {
    class_alias( 'HearMed_Core', 'HearMed_Portal' );
}