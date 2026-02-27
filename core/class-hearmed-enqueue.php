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
         * Enqueue calendar settings assets if shortcode is present
         */
        public function maybe_enqueue_calendar_settings() {
            if ( is_singular() && has_shortcode( get_post()->post_content, 'hearmed_calendar_settings' ) ) {
                wp_enqueue_style(
                    'calendar-settings',
                    HEARMED_URL . 'assets/css/calendar-settings.css',
                    [ 'hearmed-core' ],
                    $this->get_file_version( 'assets/css/calendar-settings.css' )
                );
                wp_enqueue_script(
                    'hearmed-calendar-settings',
                    HEARMED_URL . 'assets/js/hearmed-calendar-settings.js',
                    [ 'hearmed-core' ],
                    $this->get_file_version( 'assets/js/hearmed-calendar-settings.js' ),
                    true
                );
            }
        }
    
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
        // Priority 15 = AFTER Elementor (priority 11) so dependencies exist
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_foundation' ], 15 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_modules' ], 16 );
        add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_calendar_settings' ], 17 );
    }
    
    /**
     * Enqueue foundation assets - ALWAYS loaded
     * These files control layout, design system, and core JS
     */
    public function enqueue_foundation() {
        // Google Fonts — Cormorant Garamond (titles), Bricolage Grotesque (subtitles),
        // Source Sans 3 (body), Plus Jakarta Sans (buttons)
        wp_enqueue_style(
            'hearmed-google-fonts',
            'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,500;12..96,600;12..96,700;12..96,800&family=Source+Sans+3:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap',
            [],
            null
        );

        // Core CSS - viewport, layout, and component styles
        // Depend on Elementor frontend so our CSS loads AFTER Elementor
        $core_deps = [ 'hearmed-google-fonts' ];
        if ( wp_style_is( 'elementor-frontend', 'registered' ) ) {
            $core_deps[] = 'elementor-frontend';
        }
        
        wp_enqueue_style(
            'hearmed-core',
            HEARMED_URL . 'assets/css/hearmed-core.css',
            $core_deps,
            $this->get_file_version( 'assets/css/hearmed-core.css' )
        );

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
                [ 'hearmed-core' ],
                $this->get_file_version( 'assets/css/hearmed-jet-overrides.css' )
            );
        }

        // Layout/foundation CSS - ONLY on portal pages to avoid global scroll interference
        if ( $this->is_portal_page() ) {
            $foundation_deps = [ 'hearmed-core' ];
            if ( wp_style_is( 'elementor-frontend', 'registered' ) ) {
                $foundation_deps[] = 'elementor-frontend';
            }
            
            wp_enqueue_style(
                'hearmed-foundation',
                HEARMED_URL . 'assets/css/hearmed-foundation.css',
                $foundation_deps,
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

        if ( $this->is_portal_page() ) {
            wp_enqueue_script(
                'hearmed-back-btn',
                HEARMED_URL . 'assets/js/hearmed-back-btn.js',
                [ 'hearmed-core' ],
                $this->get_file_version( 'assets/js/hearmed-back-btn.js' ),
                true
            );
        }
        
        // Load calendar settings from PostgreSQL
        $settings = [];
        try {
            $settings_row = HearMed_DB::get_row( "SELECT * FROM hearmed_core.calendar_settings LIMIT 1" );
            if ( $settings_row ) {
                $settings = (array) $settings_row;
                // Decode jsonb columns — PostgreSQL returns them with JSON encoding
                foreach ( ['working_days', 'enabled_days', 'calendar_order', 'appointment_statuses'] as $jf ) {
                    if ( isset( $settings[ $jf ] ) && is_string( $settings[ $jf ] ) ) {
                        $decoded = json_decode( $settings[ $jf ], true );
                        if ( $decoded !== null ) {
                            $settings[ $jf ] = is_array( $decoded ) ? implode( ',', $decoded ) : $decoded;
                        }
                    }
                }
                // Decode status_badge_colours as associative array (keep as object for JS)
                if ( isset( $settings['status_badge_colours'] ) && is_string( $settings['status_badge_colours'] ) ) {
                    $decoded = json_decode( $settings['status_badge_colours'], true );
                    if ( is_array( $decoded ) ) {
                        $settings['status_badge_colours'] = $decoded;
                    }
                }
            }
        } catch ( Throwable $e ) {
            error_log( '[HearMed] Could not load calendar settings: ' . $e->getMessage() );
        }
        
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
            'settings'   => $settings,
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
            'hearmed_calendar', 'hearmed_calendar_settings',
            'hearmed_blockouts', 'hearmed_holidays',
            'hearmed_appointment_types',
            'hearmed_appointment_type_detail',
            'hearmed_patients',
            'hearmed_order_status', 'hearmed_approvals', 'hearmed_fitting', 'hearmed_awaiting_fitting', 'hearmed_orders',
            'hearmed_accounting', 'hearmed_invoices', 'hearmed_payments',
            'hearmed_credit_notes', 'hearmed_prsi',
            'hearmed_reporting', 'hearmed_my_stats', 'hearmed_report_revenue', 'hearmed_report_gp',
            'hearmed_reports',
            'hearmed_repairs', 'hearmed_stock', 'hearmed_refunds',
            'hearmed_notifications', 'hearmed_team_chat',
            'hearmed_kpi', 'hearmed_kpi_tracking',
            'hearmed_commissions',
            'hearmed_cash', 'hearmed_till',
            'hearmed_admin_console', 'hearmed_users', 'hearmed_clinics', 'hearmed_products',
            'hearmed_manage_users', 'hearmed_manage_clinics', 'hearmed_manage_products',
            'hearmed_kpi_targets', 'hearmed_sms_templates', 'hearmed_audit_log',
            'hearmed_data_export', 'hearmed_audiometers',
            'hearmed_brands', 'hearmed_range_settings', 'hearmed_lead_types',
            'hearmed_admin_groups', 'hearmed_admin_resources',
            'hearmed_dispenser_schedules', 'hearmed_staff_login',
            // Calendar admin sub-pages
            'hearmed_admin_blockouts', 'hearmed_admin_availability', 'hearmed_admin_holidays', 'hearmed_admin_exclusions',
            // Settings sub-pages
            'hearmed_finance_settings', 'hearmed_comms_settings', 'hearmed_document_types',
            'hearmed_form_settings', 'hearmed_cash_settings', 'hearmed_ai_settings',
            'hearmed_pusher_settings', 'hearmed_gdpr_settings', 'hearmed_admin_alerts',
            'hearmed_admin_report_layout', 'hearmed_admin_patient_overview',
            // Chat admin
            'hearmed_chat_logs',
            // Document template editor
            'hearmed_document_template_editor',
            // Form templates
            'hearmed_form_templates',
            'hearmed_fitting',
            'hearmed_stock',
            'hearmed_refunds',
            'hearmed_admin_availability',
        ];

        foreach ( $portal_shortcodes as $shortcode ) {
            if ( has_shortcode( $post->post_content, $shortcode ) ) {
                self::$is_portal_page = true;
                return true;
            }
        }

        // Fallback: check Elementor data for shortcodes placed via widgets/templates
        $elementor_data = get_post_meta( $post->ID, '_elementor_data', true );
        if ( ! empty( $elementor_data ) && is_string( $elementor_data ) ) {
            foreach ( $portal_shortcodes as $shortcode ) {
                if ( strpos( $elementor_data, $shortcode ) !== false ) {
                    self::$is_portal_page = true;
                    return true;
                }
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
            // 'hearmed_appointment_types' now has its own admin page
            'hearmed_blockouts',
            'hearmed_holidays',
        ]);
        
        $this->detect_and_load( 'patients', $content, [
            'hearmed_patients',
        ]);
        
        $this->detect_and_load( 'orders', $content, [
            'hearmed_order_status',
            'hearmed_approvals',
            'hearmed_fitting', 'hearmed_awaiting_fitting',
            'hearmed_orders',
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
            'hearmed_repairs', 'hearmed_stock', 'hearmed_refunds',
        ]);
        
        $this->detect_and_load( 'notifications', $content, [
            'hearmed_notifications',
        ]);

        $this->detect_and_load( 'chat', $content, [
            'hearmed_team_chat',
        ]);
        
        $this->detect_and_load( 'kpi', $content, [
            'hearmed_kpi',
            'hearmed_kpi_tracking',
            'hearmed_kpi_targets',
        ]);
        
        $this->detect_and_load( 'commissions', $content, [
            'hearmed_commissions',
        ]);
        
        $this->detect_and_load( 'cash', $content, [
            'hearmed_cash',
            'hearmed_till',
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
            'hearmed_brands',
            'hearmed_range_settings',
            'hearmed_lead_types',
            'hearmed_admin_groups',
            'hearmed_admin_resources',
            'hearmed_dispenser_schedules',
            'hearmed_staff_login',
            'hearmed_kpi_targets',
            // Calendar-related admin pages that use hm-admin layout
            'hearmed_calendar_settings',
            'hearmed_appointment_types',
            'hearmed_admin_blockouts', 'hearmed_admin_availability',
            'hearmed_admin_holidays',
            'hearmed_admin_exclusions',
            // Settings sub-pages (admin-settings.php)
            'hearmed_finance_settings',
            'hearmed_comms_settings',
            'hearmed_document_types',
            'hearmed_form_settings',
            'hearmed_cash_settings',
            'hearmed_ai_settings',
            'hearmed_pusher_settings',
            'hearmed_gdpr_settings',
            'hearmed_admin_alerts',
            'hearmed_admin_report_layout',
            'hearmed_admin_patient_overview',
            // Chat admin
            'hearmed_chat_logs',
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
        
        // Check if any shortcode is present in post_content
        foreach ( $shortcodes as $shortcode ) {
            if ( has_shortcode( $content, $shortcode ) ) {
                $this->load_module( $module );
                return;
            }
        }

        // Fallback: check Elementor data for shortcodes in widgets/templates
        global $post;
        if ( $post ) {
            $elementor_data = get_post_meta( $post->ID, '_elementor_data', true );
            if ( ! empty( $elementor_data ) && is_string( $elementor_data ) ) {
                foreach ( $shortcodes as $shortcode ) {
                    if ( strpos( $elementor_data, $shortcode ) !== false ) {
                        $this->load_module( $module );
                        return;
                    }
                }
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
        
        // Load module CSS from assets/css/ directory
        $css_path = "assets/css/hearmed-{$module}.css";
        if ( file_exists( HEARMED_PATH . $css_path ) ) {
            wp_enqueue_style(
                "hearmed-{$module}",
                HEARMED_URL . $css_path,
                [ 'hearmed-core' ], // Core has all variables + foundation classes
                $this->get_file_version( $css_path )
            );
        }
        
        // Load module JS from assets/js/ directory
        $js_path = "assets/js/hearmed-{$module}.js";
        if ( file_exists( HEARMED_PATH . $js_path ) ) {
            $deps = [ 'hearmed-core' ]; // Always depend on core JS
            
            // Add jQuery UI dependencies for specific modules
            if ( $module === 'calendar' ) {
                $deps[] = 'jquery-ui-sortable';
                // Calendar also needs orders.js which contains the main calendar logic
                wp_enqueue_script(
                    'hearmed-orders',
                    HEARMED_URL . 'assets/js/hearmed-orders.js',
                    [ 'hearmed-core' ],
                    $this->get_file_version( 'assets/js/hearmed-orders.js' ),
                    true
                );
            }
            
            wp_enqueue_script(
                "hearmed-{$module}",
                HEARMED_URL . $js_path,
                $deps,
                $this->get_file_version( $js_path ),
                true
            );
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
