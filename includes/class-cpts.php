<?php
/**
 * HearMed Asset Enqueue Manager
 * 
 * Handles ALL asset loading with smart conditional loading.
 * NO module should enqueue assets directly.
 * 
 * @package HearMed_Portal
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HearMed_Enqueue {
    
    /**
     * Tracks loaded modules to prevent duplicates
     */
    private static $loaded_modules = [];

    /**
     * Cache for portal-page detection result
     */
    private static $is_portal_page = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_foundation' ], 5 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_modules' ], 10 );
    }
    
    /**
     * Enqueue foundation assets - ALWAYS loaded
     * These files control layout, design system, and core JS
     */
    public function enqueue_foundation() {
        // Design system CSS - tokens, components, utilities (always loaded)
        wp_enqueue_style(
            'hearmed-design',
            HEARMED_URL . 'assets/css/hearmed-design.css',
            [],
            $this->get_file_version( 'assets/css/hearmed-design.css' )
        );
        
        // JetEngine overrides (if needed, always loaded)
        if ( file_exists( HEARMED_PATH . 'assets/css/hearmed-jet-overrides.css' ) ) {
            wp_enqueue_style(
                'hearmed-jet-overrides',
                HEARMED_URL . 'assets/css/hearmed-jet-overrides.css',
                [ 'hearmed-design' ],
                $this->get_file_version( 'assets/css/hearmed-jet-overrides.css' )
            );
        }

        // Layout/foundation CSS - ONLY on portal pages to avoid global scroll interference
        if ( $this->is_portal_page() ) {
            wp_enqueue_style(
                'hearmed-foundation',
                HEARMED_URL . 'assets/css/hearmed-foundation.css',
                [ 'hearmed-design' ],
                $this->get_file_version( 'assets/css/hearmed-foundation.css' )
            );

            wp_enqueue_style(
                'hearmed-layout',
                HEARMED_URL . 'assets/css/hearmed-layout.css',
                [ 'hearmed-foundation' ],
                $this->get_file_version( 'assets/css/hearmed-layout.css' )
            );

            // Mark portal pages with a body class so CSS can scope safely
            add_filter( 'body_class', [ $this, 'add_portal_body_class' ] );
        }
        
        // Core JavaScript - base functionality, AJAX wrapper, utilities
        wp_enqueue_script(
            'hearmed-core',
            HEARMED_URL . 'assets/js/hearmed-core.js',
            [ 'jquery' ],
            $this->get_file_version( 'assets/js/hearmed-core.js' ),
            true
        );
        
        // Localize script with global data
        $user = wp_get_current_user();
        wp_localize_script( 'hearmed-core', 'HM', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'ajax'     => admin_url( 'admin-ajax.php' ), // alias for legacy code paths
            'nonce' => wp_create_nonce( 'hm_nonce' ),
            'user_id' => $user->ID,
            'user_name' => $user->display_name,
            'user_role' => ! empty( $user->roles ) ? $user->roles[0] : '',
            'is_admin' => ! empty( array_intersect( 
                [ 'administrator', 'hm_clevel', 'hm_admin', 'hm_finance' ], 
                (array) $user->roles 
            )),
            'logout_url' => wp_logout_url( home_url() ),
            'plugin_url' => HEARMED_URL,
        ]);
    }

    /**
     * Add portal body class to page-level body tag
     *
     * @param array $classes Existing body classes
     * @return array Modified body classes
     */
    public function add_portal_body_class( $classes ) {
        $classes[] = 'hm-portal-page';
        return $classes;
    }

    /**
     * Detect whether the current page contains any HearMed portal shortcode.
     *
     * Result is cached so detection only runs once per request.
     *
     * @return bool
     */
    private function is_portal_page() {
        if ( null !== self::$is_portal_page ) {
            return self::$is_portal_page;
        }

        global $post;

        if ( ! is_page() || ! $post ) {
            self::$is_portal_page = false;
            return false;
        }

        $portal_shortcodes = [
            'hearmed_calendar', 'hearmed_calendar_settings', 'hearmed_appointment_types',
            'hearmed_blockouts', 'hearmed_holidays',
            'hearmed_patients',
            'hearmed_order_status', 'hearmed_approvals', 'hearmed_awaiting_fitting',
            'hearmed_accounting', 'hearmed_invoices', 'hearmed_payments',
            'hearmed_credit_notes', 'hearmed_prsi',
            'hearmed_reporting', 'hearmed_my_stats', 'hearmed_report_revenue', 'hearmed_report_gp',
            'hearmed_reports',
            'hearmed_repairs',
            'hearmed_notifications', 'hearmed_team_chat',
            'hearmed_kpi', 'hearmed_kpi_tracking',
            'hearmed_admin_console', 'hearmed_users', 'hearmed_clinics', 'hearmed_products',
            'hearmed_manage_users', 'hearmed_manage_clinics', 'hearmed_manage_products',
            'hearmed_kpi_targets', 'hearmed_sms_templates', 'hearmed_audit_log',
            'hearmed_data_export', 'hearmed_audiometers',
        ];

        foreach ( $portal_shortcodes as $shortcode ) {
            if ( has_shortcode( $post->post_content, $shortcode ) ) {
                self::$is_portal_page = true;
                return true;
            }
        }

        self::$is_portal_page = false;
        return false;
    }
    
    /**
     * Enqueue module-specific assets - CONDITIONAL loading
     * Only loads CSS/JS for modules actually on the current page
     */
    public function enqueue_modules() {
        global $post;
        
        // Only run on pages
        if ( ! is_page() || ! $post ) {
            return;
        }

        // Skip module scripts in Elementor editor/preview unless explicitly enabled
        if ( HearMed_Utils::is_elementor_editor() && ! HearMed_Utils::allow_elementor_preview_boot() ) {
            return;
        }
        
        $content = $post->post_content;
        
        // Detect which modules are needed based on shortcodes
        $this->detect_and_load( 'calendar', $content, [
            'hearmed_calendar',
            'hearmed_calendar_settings',
            'hearmed_appointment_types',
            'hearmed_blockouts',
            'hearmed_holidays',
        ]);
        
        $this->detect_and_load( 'patients', $content, [
            'hearmed_patients',
        ]);
        
        $this->detect_and_load( 'orders', $content, [
            'hearmed_order_status',
            'hearmed_approvals',
            'hearmed_awaiting_fitting',
        ]);
        
        $this->detect_and_load( 'accounting', $content, [
            'hearmed_accounting',
            'hearmed_invoices',
            'hearmed_payments',
            'hearmed_credit_notes',
            'hearmed_prsi',
        ]);
        
        $this->detect_and_load( 'reports', $content, [
            'hearmed_reporting',
            'hearmed_my_stats',
            'hearmed_report_revenue',
            'hearmed_report_gp',
            'hearmed_reports',
        ]);
        
        $this->detect_and_load( 'repairs', $content, [
            'hearmed_repairs',
        ]);
        
        $this->detect_and_load( 'notifications', $content, [
            'hearmed_notifications',
            'hearmed_team_chat',
        ]);
        
        $this->detect_and_load( 'kpi', $content, [
            'hearmed_kpi',
            'hearmed_kpi_tracking',
            'hearmed_kpi_targets',
        ]);
        
        $this->detect_and_load( 'admin', $content, [
            'hearmed_admin_console',
            'hearmed_users',
            'hearmed_clinics',
            'hearmed_products',
            'hearmed_manage_users',
            'hearmed_manage_clinics',
            'hearmed_manage_products',
            'hearmed_sms_templates',
            'hearmed_audit_log',
            'hearmed_data_export',
            'hearmed_audiometers',
        ]);
    }
    
    /**
     * Detect shortcode presence and load module assets
     * 
     * @param string $module Module name
     * @param string $content Page content
     * @param array $shortcodes Shortcodes that trigger this module
     */
    private function detect_and_load( $module, $content, $shortcodes ) {
        // Skip if already loaded
        if ( in_array( $module, self::$loaded_modules, true ) ) {
            return;
        }
        
        // Check if any shortcode is present
        foreach ( $shortcodes as $shortcode ) {
            if ( has_shortcode( $content, $shortcode ) ) {
                $this->load_module( $module );
                return;
            }
        }
    }
    
    /**
     * Load a specific module's assets
     * 
     * @param string $module Module name
     */
    private function load_module( $module ) {
        // Mark as loaded
        self::$loaded_modules[] = $module;
        
        // Load module CSS if exists
        $css_path = "modules/{$module}/{$module}.css";
        if ( file_exists( HEARMED_PATH . $css_path ) ) {
            wp_enqueue_style(
                "hearmed-{$module}",
                HEARMED_URL . $css_path,
                [ 'hearmed-design' ], // Always depend on design system
                $this->get_file_version( $css_path )
            );
        }
        
        // Load module JS if exists
        $js_path = "modules/{$module}/{$module}.js";
        if ( file_exists( HEARMED_PATH . $js_path ) ) {
            $deps = [ 'hearmed-core' ]; // Always depend on core JS
            
            // Add jQuery UI dependencies for specific modules
            if ( $module === 'calendar' ) {
                $deps[] = 'jquery-ui-sortable';
            }
            
            wp_enqueue_script(
                "hearmed-{$module}",
                HEARMED_URL . $js_path,
                $deps,
                $this->get_file_version( $js_path ),
                true
            );
        }
        
        // Load legacy JS files for compatibility (temporary)
        $this->load_legacy_js( $module );
    }
    
    /**
     * Load legacy JS files (for backward compatibility during migration)
     * 
     * @param string $module Module name
     */
    private function load_legacy_js( $module ) {
        $legacy_map = [
            'calendar' => 'js/hearmed-calendar.js',
            'patients' => 'js/hearmed-patients.js',
            'orders' => 'js/hearmed-orders.js',
            'reports' => 'js/hearmed-reports.js',
            'admin' => 'js/hearmed-admin.js',
        ];
        
        if ( isset( $legacy_map[ $module ] ) ) {
            $legacy_path = $legacy_map[ $module ];
            if ( file_exists( HEARMED_PATH . $legacy_path ) ) {
                wp_enqueue_script(
                    "hearmed-legacy-{$module}",
                    HEARMED_URL . $legacy_path,
                    [ 'hearmed-core' ],
                    $this->get_file_version( $legacy_path ),
                    true
                );
            }
        }
    }
    
    /**
     * Get file version for cache busting
     * 
     * @param string $file Relative file path
     * @return string File modification time or plugin version
     */
    private function get_file_version( $file ) {
        $full_path = HEARMED_PATH . $file;
        return file_exists( $full_path ) ? filemtime( $full_path ) : HEARMED_VERSION;
    }
    
    /**
     * Get list of loaded modules (for debugging)
     * 
     * @return array Loaded module names
     */
    public static function get_loaded_modules() {
        return self::$loaded_modules;
    }
}
