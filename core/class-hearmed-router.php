<?php
/**
 * HearMed Router
 * 
 * Handles shortcode registration and routing to modules.
 * Each shortcode maps to a specific module.
 * 
 * @package HearMed_Portal
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HearMed_Router {
    
    /**
     * Shortcode to module mapping
     */
    private $shortcode_map = [
        // Calendar
        'hearmed_calendar' => [ 'module' => 'calendar', 'view' => 'calendar', 'cap' => 'calendar' ],
        'hearmed_blockouts' => [ 'module' => 'calendar', 'view' => 'blockouts', 'cap' => 'calendar' ],
        'hearmed_holidays' => [ 'module' => 'calendar', 'view' => 'holidays', 'cap' => 'calendar' ],
        'hearmed_exclusions' => [ 'module' => 'calendar', 'view' => 'exclusions', 'cap' => 'calendar' ],
        
        // Patients
        'hearmed_patients' => [ 'module' => 'patients', 'view' => 'list', 'cap' => 'patients' ],
        
        // Orders & Finance
        'hearmed_orders' => [ 'module' => 'orders', 'view' => 'list', 'cap' => 'patients' ],
        
        // Accounting
        'hearmed_accounting' => [ 'module' => 'accounting', 'view' => 'dashboard', 'cap' => 'finance_area' ],
        'hearmed_invoices' => [ 'module' => 'accounting', 'view' => 'invoices', 'cap' => 'finance_area' ],
        'hearmed_payments' => [ 'module' => 'accounting', 'view' => 'payments', 'cap' => 'finance_area' ],
        'hearmed_credit_notes' => [ 'module' => 'accounting', 'view' => 'credit-notes', 'cap' => 'finance_area' ],
        'hearmed_prsi' => [ 'module' => 'accounting', 'view' => 'prsi', 'cap' => 'finance_area' ],
        
        // Reports
        'hearmed_reporting' => [ 'module' => 'reports', 'view' => 'dashboard', 'cap' => 'reports' ],
        'hearmed_my_stats' => [ 'module' => 'reports', 'view' => 'my-stats', 'cap' => 'kpi_view_self' ],
        'hearmed_report_revenue' => [ 'module' => 'reports', 'view' => 'revenue', 'cap' => 'reports' ],
        'hearmed_report_gp' => [ 'module' => 'reports', 'view' => 'gp', 'cap' => 'reports' ],
        
        // Other modules
        'hearmed_repairs' => [ 'module' => 'repairs', 'view' => 'list', 'cap' => 'repairs' ],
        'hearmed_refunds' => [ 'module' => 'refunds', 'view' => 'list', 'cap' => 'finance_area' ],
        'hearmed_stock' => [ 'module' => 'stock', 'view' => 'list', 'cap' => 'stock' ],
        'hearmed_notifications' => [ 'module' => 'notifications', 'view' => 'list', 'cap' => null ],
        'hearmed_kpi' => [ 'module' => 'kpi', 'view' => 'dashboard', 'cap' => 'kpi_view_self' ],
    ];
    
    /**
     * Register all shortcodes and hooks
     */
    public function register_shortcodes() {
        foreach ( $this->shortcode_map as $shortcode => $config ) {
            add_shortcode( $shortcode, [ $this, 'render_shortcode' ] );
        }

        // When V2 auth is active, redirect unauthenticated visitors to /login/
        if ( PortalAuth::is_v2() ) {
            add_action( 'template_redirect', [ $this, 'auth_redirect' ] );
            add_filter( 'template_include',  [ $this, 'login_template' ], 999 );

            // Bridge: if portal-authenticated but NOT WP-authenticated, set the
            // WP login cookie so SiteGround's Nginx cache is bypassed.
            add_action( 'init', [ $this, 'bridge_wp_login' ], 1 );
        }
    }

    /**
     * Serve a standalone login template — bypasses Elementor / theme entirely.
     * Uses both is_page() and URI fallback so login works even if the WP page
     * was trashed or doesn't exist.
     */
    public function login_template( $template ) {
        $is_login = is_page( 'login' );
        if ( ! $is_login ) {
            $uri = trim( strtok( $_SERVER['REQUEST_URI'] ?? '', '?' ), '/' );
            $is_login = ( $uri === 'login' );
        }
        if ( $is_login ) {
            $custom = HEARMED_PATH . 'templates/login-page.php';
            if ( file_exists( $custom ) ) {
                return $custom;
            }
        }
        return $template;
    }

    /**
     * Bridge portal auth → WP login cookie.
     *
     * SiteGround's Nginx cache skips caching when a wordpress_logged_in_*
     * cookie is present.  Without this bridge the custom HMSESS cookie is
     * invisible to the web-server cache layer, so authenticated users get
     * served stale cached pages.
     *
     * Runs on 'init' priority 1 (before anything else).
     */
    public function bridge_wp_login() {
        if ( defined( 'DOING_AJAX' ) || defined( 'DOING_CRON' ) || defined( 'REST_REQUEST' ) || defined( 'WP_CLI' ) ) return;

        // Already WP-logged-in → nothing to do
        if ( is_user_logged_in() ) return;

        // Check portal session
        if ( ! PortalAuth::is_logged_in() ) return;

        $staff = PortalAuth::current_user();
        if ( ! $staff ) return;

        // Look up the linked WP user via PG
        $wp_user_id = HearMed_DB::get_var(
            "SELECT wp_user_id FROM hearmed_reference.staff WHERE id = $1 AND wp_user_id IS NOT NULL",
            [ $staff->id ]
        );
        if ( ! $wp_user_id ) return;

        $wp_user_id = (int) $wp_user_id;
        $wp_user    = get_user_by( 'ID', $wp_user_id );
        if ( ! $wp_user ) return;

        // Set WP auth cookie (cache-busting only — PortalAuth is source of truth)
        wp_set_auth_cookie( $wp_user_id, true );
        wp_set_current_user( $wp_user_id );
    }

    /**
     * Redirect unauthenticated users to /login/ on any portal page.
     * Skips the login page itself and REST/AJAX/Cron requests.
     */
    public function auth_redirect() {
        if ( defined( 'DOING_AJAX' ) || defined( 'DOING_CRON' ) || defined( 'REST_REQUEST' ) || defined( 'WP_CLI' ) ) return;

        // Don't redirect the login page itself (WP check + URI fallback to prevent loops)
        $uri = trim( strtok( $_SERVER['REQUEST_URI'] ?? '', '?' ), '/' );
        if ( is_page( 'login' ) || $uri === 'login' ) return;

        // If not authenticated via portal, redirect to login
        if ( ! PortalAuth::is_logged_in() ) {
            // Use the clean URI (without query string nesting) as redirect target
            $clean_uri = $_SERVER['REQUEST_URI'] ?? '/';
            // Prevent redirect_to from pointing back at login (loop guard)
            if ( strpos( $clean_uri, '/login' ) === 0 ) {
                $clean_uri = '/';
            }
            wp_redirect( home_url( '/login/?redirect_to=' . urlencode( $clean_uri ) ) );
            exit;
        }
    }
    
    /**
     * Render a shortcode
     * 
     * @param array $atts Shortcode attributes
     * @param string $content Shortcode content
     * @param string $tag Shortcode tag
     * @return string Rendered output
     */
    public function render_shortcode( $atts, $content = '', $tag = '' ) {
        // Check if shortcode exists in map
        if ( ! isset( $this->shortcode_map[ $tag ] ) ) {
            return '<div id="hm-app"><p>Unknown module.</p></div>';
        }
        
        $config = $this->shortcode_map[ $tag ];
        $module = $config['module'];
        $view = $config['view'];
        
        // Check authentication — PortalAuth is source of truth
        $logged_in = PortalAuth::is_v2() ? PortalAuth::is_logged_in() : is_user_logged_in();
        if ( ! $logged_in ) {
            if ( PortalAuth::is_v2() ) {
                wp_redirect( home_url( '/login/?redirect_to=' . urlencode( $_SERVER['REQUEST_URI'] ?? '' ) ) );
                exit;
            }
            return '<div id="hm-app"><p>Please log in to access this page.</p></div>';
        }

        // RBAC capability check (V2 only)
        $required_cap = $config['cap'] ?? null;
        if ( PortalAuth::is_v2() && $required_cap && ! PortalAuth::can( $required_cap ) ) {
            PortalAuth::audit( 'ACCESS_DENIED', PortalAuth::current_id(), [
                'module' => $module, 'view' => $view, 'cap' => $required_cap
            ] );
            return '<div id="hm-app" class="hm-access-denied"><p style="text-align:center;padding:48px;color:#64748b;">You do not have permission to access this section.</p></div>';
        }
        
        // Check privacy notice
        $privacy_notice = $this->check_privacy_notice();
        if ( $privacy_notice ) {
            return $privacy_notice;
        }

        // Show placeholder in Elementor editor/preview unless explicitly enabled for staging QA
        if ( HearMed_Utils::is_elementor_editor() && ! HearMed_Utils::allow_elementor_preview_boot() ) {
            return '<div id="hm-app" class="hm-elementor-placeholder"><p style="text-align:center;padding:32px;color:#94a3b8;">[ HearMed ' . esc_html( ucfirst( $module ) ) . ' — editing mode ]</p><p style="text-align:center;padding:0 32px 24px;color:#64748b;font-size:13px;">Enable preview runtime with <code>hm_allow_elementor_preview_boot</code> filter for staging validation.</p></div>';
        }
        
        // Load the module file
        // Ensure CSS/JS for this module are enqueued even if enqueue_modules()
        // missed the shortcode (e.g. shortcode placed via template, widget, or
        // dynamic content rather than raw post_content).
        $this->ensure_module_assets( $module );
        
        return $this->load_module( $module, $view, $atts );
    }
    
    /**
     * Load a module
     * 
     * @param string $module Module name
     * @param string $view View name
     * @param array $atts Shortcode attributes
     * @return string Rendered output
     */
    private function load_module( $module, $view, $atts = [] ) {
        // Legacy module file path (for backward compatibility)
        $legacy_file = HEARMED_PATH . "modules/mod-{$module}.php";
        
        // New module file path
        $module_file = HEARMED_PATH . "modules/{$module}/{$module}.php";
        
        // Determine which file to load
        $file_to_load = file_exists( $module_file ) ? $module_file : $legacy_file;
        
        if ( ! file_exists( $file_to_load ) ) {
            return '<div id="hm-app"><p>Module not found.</p></div>';
        }
        
        // Start output buffering
        ob_start();
        
        // Set module context
        $GLOBALS['hm_current_module'] = $module;
        $GLOBALS['hm_current_view'] = $view;
        $GLOBALS['hm_shortcode_atts'] = $atts;
        
        // Render module wrapper
        echo '<div id="hm-app" class="hm-' . esc_attr( $module ) . '" data-module="' . esc_attr( $module ) . '" data-view="' . esc_attr( $view ) . '">';
        
        // Include the module file (may be a no-op if already loaded for AJAX)
        require_once $file_to_load;

        // Call the module's standalone render function if one exists.
        // This is needed because require_once is a no-op when the file was
        // already loaded for AJAX handler registration (e.g. mod-patients.php).
        $render_fn = 'hm_' . $module . '_render';
        if ( function_exists( $render_fn ) ) {
            call_user_func( $render_fn );
        }
        
        echo '</div>';
        
        return ob_get_clean();
    }
    
    /**
     * Check if privacy notice has been accepted
     * 
     * @return string|false Privacy notice HTML or false if accepted
     */
    private function check_privacy_notice() {
        $user_id = get_current_user_id();
        
        if ( ! $user_id ) {
            return false;
        }
        
        $accepted = get_user_meta( $user_id, 'hm_privacy_notice_accepted', true );
        
        if ( $accepted ) {
            return false;
        }
        
        // Load privacy notice template
        ob_start();
        $template_file = HEARMED_PATH . 'templates/privacy-notice.php';
        if ( file_exists( $template_file ) ) {
            require_once $template_file;
        } else {
            echo '<div id="hm-app"><p>Please accept the privacy notice to continue.</p></div>';
        }
        return ob_get_clean();
    }
    
    /**
     * Get current module name
     * 
     * @return string|null Module name or null
     */
    public static function get_current_module() {
        return $GLOBALS['hm_current_module'] ?? null;
    }
    
    /**
     * Get current view name
     * 
     * @return string|null View name or null
     */
    public static function get_current_view() {
        return $GLOBALS['hm_current_view'] ?? null;
    }

    /**
     * Ensure a module's CSS and JS are enqueued.
     *
     * Called at shortcode render time as a safety net — if enqueue_modules()
     * already loaded the assets this is a no-op (wp_enqueue_* deduplicates).
     *
     * @param string $module Module slug (e.g. 'calendar', 'patients').
     */
    private function ensure_module_assets( $module ) {
        $css_path = "assets/css/hearmed-{$module}.css";
        if ( file_exists( HEARMED_PATH . $css_path ) && ! wp_style_is( "hearmed-{$module}", 'enqueued' ) ) {
            wp_enqueue_style(
                "hearmed-{$module}",
                HEARMED_URL . $css_path,
                [ 'hearmed-core' ],
                filemtime( HEARMED_PATH . $css_path )
            );
        }

        $js_path = "assets/js/hearmed-{$module}.js";
        if ( file_exists( HEARMED_PATH . $js_path ) && ! wp_script_is( "hearmed-{$module}", 'enqueued' ) ) {
            $deps = [ 'hearmed-core' ];
            if ( $module === 'calendar' ) {
                $deps[] = 'jquery-ui-sortable';
            }
            wp_enqueue_script(
                "hearmed-{$module}",
                HEARMED_URL . $js_path,
                $deps,
                filemtime( HEARMED_PATH . $js_path ),
                true
            );
        }
    }
}
