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
            HEARMED_PATH . 'admin/admin-blockouts.php',
            HEARMED_PATH . 'admin/admin-holidays.php',
            HEARMED_PATH . 'admin/admin-exclusions.php',
            HEARMED_PATH . 'admin/admin-chat-logs.php',
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
            HEARMED_PATH . 'modules/mod-notifications.php',
            HEARMED_PATH . 'modules/mod-repairs.php',
            HEARMED_PATH . 'modules/mod-team-chat.php',
            HEARMED_PATH . 'modules/mod-accounting.php',
            HEARMED_PATH . 'modules/mod-reports.php',
            HEARMED_PATH . 'modules/mod-commissions.php',
            HEARMED_PATH . 'modules/mod-kpi.php',
            HEARMED_PATH . 'modules/mod-cash.php',
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
        
        // System ready hook
        add_action( 'init', [ $this, 'system_ready' ], 10 );
    }
    
    /**
     * System ready callback
     */
    public function system_ready() {
        // Hook for when system is fully initialized
        do_action( 'hearmed_system_ready' );
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
                    'user_id' => get_current_user_id(),
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
                get_current_user_id(),
                wp_json_encode( $meta )
            )
        );
    }
}

// Backward-compatibility alias so legacy module code that references
// HearMed_Portal::table(), HearMed_Portal::setting(), and
// HearMed_Portal::log() continues to work without any changes to those files.
if ( ! class_exists( 'HearMed_Portal' ) ) {
    class_alias( 'HearMed_Core', 'HearMed_Portal' );
}