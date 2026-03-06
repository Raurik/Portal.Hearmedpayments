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
        
        // Cash / Tender management
        'hearmed_cash' => [ 'module' => 'cash', 'view' => 'dashboard', 'cap' => 'cash' ],
        'hearmed_till' => [ 'module' => 'cash', 'view' => 'admin', 'cap' => 'cash_admin' ],

        // Other modules
        'hearmed_repairs' => [ 'module' => 'repairs', 'view' => 'list', 'cap' => 'repairs' ],
        'hearmed_refunds' => [ 'module' => 'refunds', 'view' => 'list', 'cap' => 'finance_area' ],
        'hearmed_stock' => [ 'module' => 'stock', 'view' => 'list', 'cap' => 'stock' ],
        'hearmed_notifications' => [ 'module' => 'notifications', 'view' => 'list', 'cap' => null ],
        'hearmed_kpi' => [ 'module' => 'kpi', 'view' => 'dashboard', 'cap' => 'kpi_view_self' ],
    ];
    
    /**
     * Register all shortcodes and hooks.
     *
     * These hooks are essential regardless of the PORTAL_AUTH_V2 flag:
     * - auth_redirect: protects portal pages
     * - login_template: serves standalone login page
     * - disable_page_cache: prevents SiteGround from caching per-user pages
     * - bridge_wp_login: keeps WP user in sync for wp_ajax_* dispatch
     */
    public function register_shortcodes() {
        foreach ( $this->shortcode_map as $shortcode => $config ) {
            add_shortcode( $shortcode, [ $this, 'render_shortcode' ] );
        }

        // [hearmed_username] — returns the logged-in staff member's display name.
        // Used inside Elementor HTML widgets (e.g. "Welcome, [hearmed_username]").
        add_shortcode( 'hearmed_username', [ $this, 'shortcode_username' ] );

        /* ═══════════════════════════════════════════════════════════
         * ██  REDIRECT-LOOP PROTECTION — DO NOT MODIFY  ██████████
         * ═══════════════════════════════════════════════════════════
         * These four hooks work together to prevent infinite redirect
         * loops between /login/ and /calendar/.  They were added to
         * fix a production outage (ERR_TOO_MANY_REDIRECTS) caused by:
         *   1. Missing WP pages (calendar deleted during rollback)
         *   2. Stale WP cookies with no HMSESS portal session
         *   3. WP redirect_canonical 301-ing portal slugs to root
         *
         * If you change priorities, remove hooks, or rename methods
         * below, users WILL be locked out of the portal.
         *
         * See also:
         *   - auth_redirect()         → stale cookie cleanup + missing page recovery
         *   - block_portal_canonical() → stops WP 301s on portal slugs
         *   - ensure_page_exists()    → auto-recreates deleted portal pages
         *   - admin-staff-login.php   → login shortcode stale-cookie guard
         *
         * Last verified working: 2026-03-04
         * ═══════════════════════════════════════════════════════════ */

        // Priority 1: run BEFORE WordPress redirect_canonical (priority 10)
        // so we handle auth redirects before WP can 301 missing pages to root.
        // DO NOT change this priority or the redirect loop returns.
        add_action( 'template_redirect', [ $this, 'auth_redirect' ], 1 );
        add_filter( 'template_include',  [ $this, 'login_template' ], 999 );

        // Tell SiteGround (and any reverse-proxy cache) NOT to cache portal
        // pages. These pages render per-user content (identity, nonce, data)
        // so a cached response leaks one user's session into another's view.
        add_action( 'template_redirect', [ $this, 'disable_page_cache' ], 0 );

        // Block WP canonical redirects for ALL portal slugs.
        // Without this, WP 301s /calendar/ → / when the page is missing.
        // DO NOT remove or rename. DO NOT change to only cover /login/.
        add_filter( 'redirect_canonical', [ $this, 'block_portal_canonical' ], 10, 2 );

        // Remove WordPress built-in /login → wp-login.php redirect.
        remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );

        /* ══════════ END REDIRECT-LOOP PROTECTION ══════════════════ */

        // Bridge: if portal-authenticated but NOT WP-authenticated, set the
        // WP login cookie so SiteGround's Nginx cache is bypassed and
        // admin-ajax.php dispatches wp_ajax_* hooks correctly.
        add_action( 'init', [ $this, 'bridge_wp_login' ], 1 );

        // User bar: "Logged in as ___" + logout icon in the topbar
        add_action( 'wp_footer', [ $this, 'render_user_bar' ], 98 );
    }

    /* ═══════════════════════════════════════════════════════════════
     * ██  PORTAL SLUG REGISTRY — DO NOT MODIFY  █████████████████
     * ═══════════════════════════════════════════════════════════════
     * Master list of every portal page slug.  Used by:
     *   - block_portal_canonical()  → prevent WP 301 redirects
     *   - auth_redirect()           → auto-recreate deleted pages
     *   - ensure_portal_pages()     → initial page creation
     *
     * If you add a new portal page, add its slug here too.
     * DO NOT remove existing slugs or the redirect loop returns.
     * ═══════════════════════════════════════════════════════════════ */
    private static $portal_slugs = [
        'login', 'calendar', 'patients', 'orders', 'order-status',
        'accounting', 'repairs', 'refunds', 'stock', 'reporting',
        'notifications', 'kpi', 'clinical-review',
    ];

    /**
     * Block WordPress canonical redirects for portal pages.
     *
     * ██ DO NOT MODIFY — redirect-loop protection ██
     *
     * Without this, WP treats missing portal pages (trashed, deleted)
     * as unknown URLs and 301s them to root or wp-login.php, which
     * creates an infinite redirect loop with auth_redirect().
     */
    public function block_portal_canonical( $redirect_url, $requested_url ) {
        $path = trim( parse_url( $requested_url, PHP_URL_PATH ), '/' );
        $slug = basename( $path );
        if ( in_array( $slug, self::$portal_slugs, true ) ) {
            return false;
        }
        return $redirect_url;
    }

    /**
     * Prevent SiteGround / Nginx / any reverse-proxy from caching portal pages.
     *
     * Portal pages contain per-user content (nonce, identity, data). If cached,
     * one user's session is served to another user — causing wrong identity,
     * stale nonces, and data leaks.
     *
     * Runs at template_redirect:0 (before auth_redirect) so headers are sent
     * regardless of whether the user is authenticated.
     */
    public function disable_page_cache() {
        if ( defined( 'DOING_AJAX' ) || defined( 'DOING_CRON' ) || defined( 'WP_CLI' ) ) return;

        // Only for front-end portal pages, not wp-admin
        $uri = trim( strtok( $_SERVER['REQUEST_URI'] ?? '', '?' ), '/' );
        if ( strpos( $uri, 'wp-admin' ) === 0 || strpos( $uri, 'wp-json' ) === 0 ) return;

        // Tell SiteGround not to cache
        if ( ! defined( 'DONOTCACHEPAGE' ) ) {
            define( 'DONOTCACHEPAGE', true );
        }

        // Send HTTP headers that prevent caching
        nocache_headers();
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
            $is_login = ( basename( $uri ) === 'login' );
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
        // MUST run during AJAX too: admin-ajax.php dispatches wp_ajax_*
        // hooks ONLY when is_user_logged_in() is true.  Without the
        // bridge all 197+ wp_ajax_hm_* handlers silently fail when WP
        // users are deleted or stale.
        if ( defined( 'DOING_CRON' ) || defined( 'WP_CLI' ) ) return;

        // ── Determine the portal-authenticated staff ─────────────────
        // Always read the HMSESS cookie — it is the single source of truth.
        $staff = null;
        $hmsess = $_COOKIE[ PortalAuth::COOKIE_SESSION ] ?? '';
        if ( $hmsess && strlen( $hmsess ) >= 32 ) {
            $staff = PortalAuth::resolve();
        }

        if ( ! $staff ) return;

        // Ensure a WP user exists for this staff member.
        // ensure_wp_user_for_staff() handles deleted users: detects
        // stale wp_user_id, creates a fresh WP user, updates PG.
        $wp_user_id = HearMed_Staff_Auth::ensure_wp_user_for_staff(
            $staff->id,
            $staff->email,
            strtolower( str_replace( ' ', '.', trim( $staff->first_name . '.' . $staff->last_name ) ) ),
            $staff->role
        );

        if ( ! $wp_user_id ) {
            error_log( '[HM Router] bridge_wp_login FAILED: ensure_wp_user returned 0 for staff ' . $staff->id . ' (' . $staff->email . ')' );
            return;
        }

        // Already WP-logged-in as the correct user → nothing to do
        if ( is_user_logged_in() && get_current_user_id() === $wp_user_id ) return;

        wp_clear_auth_cookie();
        wp_set_current_user( $wp_user_id );
        wp_set_auth_cookie( $wp_user_id, true );
        error_log( '[HM Router] bridge_wp_login: set WP user to wp_user_id=' . $wp_user_id . ' (staff_id=' . $staff->id . ')' );
    }

    /**
     * Redirect unauthenticated users to /login/ on any portal page.
     * Skips the login page itself and REST/AJAX/Cron requests.
     *
     * ██ DO NOT MODIFY without testing the full login→calendar flow. ██
     * ██ This method contains THREE critical redirect-loop fixes:    ██
     * ██   1. Stale WP cookie cleanup (lines below)                 ██
     * ██   2. ensure_page_exists() for missing calendar page         ██
     * ██   3. Auto-recreate any 404'd portal slug                    ██
     * ██ Removing any of these causes ERR_TOO_MANY_REDIRECTS.        ██
     */
    public function auth_redirect() {
        if ( defined( 'DOING_AJAX' ) || defined( 'DOING_CRON' ) || defined( 'REST_REQUEST' ) || defined( 'WP_CLI' ) ) return;

        // Elementor editor/preview/library render requests often hit "/" with
        // query args (elementor_preview / elementor_library / render_mode).
        // Never force portal auth redirects for those requests.
        if (
            isset( $_GET['elementor-preview'] ) ||
            isset( $_GET['elementor_library'] ) ||
            isset( $_GET['render_mode'] ) ||
            ( isset( $_GET['action'] ) && sanitize_text_field( $_GET['action'] ) === 'elementor' )
        ) {
            return;
        }

        // Don't redirect the login page itself (WP check + exact URI match).
        // home_url is /login/ so every page starts with "login/" — we must
        // match ONLY the login page paths, not /login/calendar/ etc.
        $uri = trim( strtok( $_SERVER['REQUEST_URI'] ?? '', '?' ), '/' );
        if ( is_page( 'login' ) || $uri === 'login' || $uri === 'login/login' ) return;

        // Don't redirect WordPress admin or REST API requests
        if ( strpos( $uri, 'wp-admin' ) === 0 || strpos( $uri, 'wp-json' ) === 0 ) return;

        // Check for a real portal session (HMSESS cookie exists and is plausible).
        // If the user only has stale WP cookies (no HMSESS), clear them and
        // redirect to login — without this, resolve_legacy() bridges WP cookies
        // into a "logged in" state that the login page also sees, creating a
        // redirect loop: /login/ → /calendar/ → /login/ → ...
        $hmsess_cookie = $_COOKIE[ PortalAuth::COOKIE_SESSION ] ?? '';
        $has_hmsess    = ( $hmsess_cookie && strlen( $hmsess_cookie ) >= 32 );

        if ( ! $has_hmsess && function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
            // Stale WP cookies with no portal session — evict them.
            wp_clear_auth_cookie();
            wp_set_current_user( 0 );
        }

        // If not authenticated via portal, redirect to login
        if ( ! PortalAuth::is_logged_in() ) {
            nocache_headers();
            header( 'X-Accel-Expires: 0' );
            $clean_uri = $_SERVER['REQUEST_URI'] ?? '/';
            // Only strip redirect_to if it points at the exact login page, not
            // at other pages that happen to live under the /login/ home path.
            $clean_path = trim( strtok( $clean_uri, '?' ), '/' );
            if ( $clean_path === 'login' || $clean_path === 'login/login' || $clean_path === '' ) {
                $clean_uri = home_url( '/calendar/' );
            }
            wp_redirect( home_url( '/login/?redirect_to=' . urlencode( $clean_uri ) ) );
            exit;
        }

        // Logged-in user hitting plain root URL → send to calendar
        // (but never for query-driven editor/preview requests; handled above).
        if ( $uri === '' ) {
            $this->ensure_page_exists( 'calendar', 'Calendar', '[hearmed_calendar]' );
            wp_redirect( home_url( '/calendar/' ) );
            exit;
        }

        // Logged-in user hitting a known portal slug that is a 404 →
        // the page was probably deleted. Recreate it on the fly and
        // reload so WP resolves it correctly.
        if ( is_404() && in_array( $uri, self::$portal_slugs, true ) ) {
            $this->ensure_page_exists( $uri, ucfirst( str_replace( '-', ' ', $uri ) ), '' );
            // Reload the same URL so WP picks up the new page.
            wp_redirect( home_url( '/' . $uri . '/' ) );
            exit;
        }
    }

    /**
     * Ensure a WordPress page with the given slug exists.
     * Creates it if missing. Idempotent.
     *
     * ██ DO NOT REMOVE — this is the safety net that auto-recreates ██
     * ██ portal pages if they get deleted. Without it, deleted pages ██
     * ██ cause redirect loops that lock all users out.               ██
     */
    private function ensure_page_exists( $slug, $title, $shortcode ) {
        $existing = get_posts( [
            'name'        => $slug,
            'post_type'   => 'page',
            'post_status' => [ 'publish', 'draft', 'trash', 'private' ],
            'numberposts' => 1,
        ] );
        if ( $existing ) {
            // Republish if trashed/drafted
            if ( $existing[0]->post_status !== 'publish' ) {
                wp_update_post( [ 'ID' => $existing[0]->ID, 'post_status' => 'publish', 'post_name' => $slug ] );
            }
            return;
        }
        // Page doesn't exist at all — create it
        if ( ! $shortcode ) {
            // Look up the correct shortcode from ensure_portal_pages mapping
            $map = [
                'calendar' => '[hearmed_calendar]', 'patients' => '[hearmed_patients]',
                'orders' => '[hearmed_orders]', 'order-status' => '[hearmed_orders]',
                'accounting' => '[hearmed_accounting]', 'repairs' => '[hearmed_repairs]',
                'refunds' => '[hearmed_refunds]', 'stock' => '[hearmed_stock]',
                'reporting' => '[hearmed_reporting]', 'notifications' => '[hearmed_notifications]',
                'kpi' => '[hearmed_kpi]', 'clinical-review' => '[hearmed_clinical_review]',
            ];
            $shortcode = $map[ $slug ] ?? '';
        }
        wp_insert_post( [
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_content' => $shortcode,
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ] );
        error_log( '[HM Router] Created missing portal page: ' . $slug );
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

        // URL-first safety net: if /stock/ page content has an incorrect
        // shortcode (e.g. [hearmed_repairs]), force stock module rendering.
        $uri_path = trim( strtok( $_SERVER['REQUEST_URI'] ?? '', '?' ), '/' );
        if ( basename( $uri_path ) === 'stock' && $tag !== 'hearmed_stock' && isset( $this->shortcode_map['hearmed_stock'] ) ) {
            $config = $this->shortcode_map['hearmed_stock'];
        }

        $module = $config['module'];
        $view = $config['view'];

        // In Elementor editor/preview, always short-circuit to a placeholder
        // before auth/redirect checks so the editor iframe can load reliably.
        if ( HearMed_Utils::is_elementor_editor() && ! HearMed_Utils::allow_elementor_preview_boot() ) {
            return '<div id="hm-app" class="hm-elementor-placeholder"><p style="text-align:center;padding:32px;color:#94a3b8;">[ HearMed ' . esc_html( ucfirst( $module ) ) . ' — editing mode ]</p><p style="text-align:center;padding:0 32px 24px;color:#64748b;font-size:13px;">Enable preview runtime with <code>hm_allow_elementor_preview_boot</code> filter for staging validation.</p></div>';
        }
        
        // Check authentication — PortalAuth is source of truth
        $logged_in = PortalAuth::is_logged_in();
        if ( ! $logged_in ) {
            $return_to = $_SERVER['REQUEST_URI'] ?? home_url( '/calendar/' );
            wp_redirect( home_url( '/login/?redirect_to=' . urlencode( $return_to ) ) );
            exit;
        }

        // RBAC capability check
        $required_cap = $config['cap'] ?? null;
        if ( $required_cap && ! PortalAuth::can( $required_cap ) ) {
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
        // PortalAuth is the source of truth — check PG staff record
        $staff = PortalAuth::current_user();
        if ( ! $staff ) return false;

        $accepted = HearMed_DB::get_var(
            "SELECT privacy_notice_accepted FROM hearmed_reference.staff WHERE id = $1",
            [ $staff->id ]
        );

        // If column doesn't exist or is true/t, skip notice
        if ( $accepted === null || $accepted === 't' || $accepted === true || $accepted === '1' ) {
            return false;
        }

        // Also check WP user meta as fallback
        $wp_user_id = PortalAuth::wp_user_id();
        if ( $wp_user_id && get_user_meta( $wp_user_id, 'hm_privacy_notice_accepted', true ) ) {
            return false;
        }

        // No privacy notice template exists yet — just skip it for now
        // rather than blocking access entirely with an undismissable message.
        return false;
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

    /* ================================================================
       [hearmed_username] SHORTCODE
       Renders the full welcome widget with hover → Psalm animation.
       ================================================================ */

    public function shortcode_username( $atts = [] ) {
        if ( ! PortalAuth::is_logged_in() ) {
            return '';
        }
        $staff = PortalAuth::current_user();
        if ( ! $staff ) {
            return '';
        }
        $name = esc_html( $staff->display_name );

        return '
<style>
.welcome-speed{
  position:relative;
  display:inline-block;
  color:#fff;
  font-size:14.5px;
  font-weight:400;
  overflow:visible;
  cursor:default;
}
.welcome-speed span{
  display:inline-block;
  white-space:nowrap;
  transition:transform 0.45s cubic-bezier(.25,.8,.25,1),
              opacity 0.35s ease;
}
.welcome-text{
  transform:translateX(0);
  opacity:1;
}
.hover-text{
  position:absolute;
  left:0;
  top:0;
  transform:translateX(-40px);
  opacity:0;
}
.welcome-speed:hover .welcome-text{
  transform:translateX(120%);
  opacity:0;
}
.welcome-speed:hover .hover-text{
  transform:translateX(0);
  opacity:1;
}
@media (prefers-reduced-motion:reduce){
  .welcome-speed span{transition:none;}
}
</style>
<div class="welcome-speed">
  <span class="welcome-text">Welcome, <strong>' . $name . '</strong></span>
  <span class="hover-text">Have a good day &mdash; <em>Psalms 90:17</em></span>
</div>';
    }

    /* ================================================================
       LOGOUT BUTTON — door icon in sidebar bottom-right
       Injected via wp_footer, positioned into sidebar by JS.
       ================================================================ */

    public function render_user_bar() {
        if ( HearMed_Utils::is_elementor_editor() && ! HearMed_Utils::allow_elementor_preview_boot() ) {
            return;
        }

        if ( ! PortalAuth::is_logged_in() ) {
            return;
        }

        $staff = PortalAuth::current_user();
        if ( ! $staff ) {
            return;
        }

        $name = esc_html( $staff->display_name );
        ?>
        <!-- HearMed Logout Button -->
        <style>
        #hm-logout-btn {
            display: none; /* shown by JS once placed */
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: none;
            background: transparent;
            color: rgba(255,255,255,0.7);
            cursor: pointer;
            transition: color 0.2s ease, background 0.2s ease;
            text-decoration: none;
            padding: 0;
            position: absolute;
            bottom: 6px;
            right: 8px;
            z-index: 10;
        }
        #hm-logout-btn:hover {
            color: #fff;
            background: rgba(255,255,255,0.12);
        }
        #hm-logout-btn svg {
            width: 20px;
            height: 20px;
        }
        </style>

        <a href="#" title="Log out" id="hm-logout-btn">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
        </a>

        <script>
        (function(){
            function hmIsElementorContext(){
                if(window.__HM_ELEMENTOR_EDITOR__){ return true; }
                var href = String(window.location.href || '');
                var ref = String(document.referrer || '');
                var search = String(window.location.search || '');
                var topHref = '';
                try {
                    if (window.top && window.top !== window && window.top.location) {
                        topHref = String(window.top.location.href || '');
                    }
                } catch (e) {
                    topHref = '';
                }
                var hay = (href + ' ' + ref + ' ' + search + ' ' + topHref).toLowerCase();
                return hay.indexOf('elementor') !== -1 || hay.indexOf('action=elementor') !== -1 || hay.indexOf('elementor-preview') !== -1;
            }

            if (hmIsElementorContext()) {
                return;
            }

            /* ── Place logout button in sidebar bottom-right ── */
            function placeLogout(){
                var btn = document.getElementById('hm-logout-btn');
                if(!btn) return;

                var target = document.getElementById('hm-bottomsidebar');
                if(!target) target = document.querySelector('.hm-sidebar');
                if(!target) return;

                target.style.position = 'relative';
                btn.style.display = 'inline-flex';
                target.appendChild(btn);

                console.log('[HearMed] Logout button placed in', target.id || target.className);
            }

            /* ── Logout handler ── */
            function setupLogout(){
                var btn = document.getElementById('hm-logout-btn');
                if(!btn) return;
                btn.addEventListener('click', function(e){
                    e.preventDefault();
                    var url = (typeof HM !== 'undefined' && HM.logout_url)
                        ? HM.logout_url
                        : '/login/?logout=1';
                    var xhr = new XMLHttpRequest();
                    xhr.open('GET', url, true);
                    xhr.withCredentials = true;
                    xhr.onreadystatechange = function(){
                        if(xhr.readyState === 4){
                            window.location.href = '/login/';
                        }
                    };
                    xhr.send();
                });
            }

            /* ── Populate Elementor #hm-name / .hm-name with staff name ── */
            function syncNameWidget(){
                var name = <?php echo wp_json_encode( $name ); ?>;
                var el = document.getElementById('hm-name');
                if(el) el.textContent = name;
                var els = document.querySelectorAll('.hm-name');
                for(var i=0;i<els.length;i++) els[i].textContent = name;
            }

            if(document.readyState === 'loading'){
                document.addEventListener('DOMContentLoaded', function(){
                    placeLogout();
                    setupLogout();
                    syncNameWidget();
                });
            } else {
                placeLogout();
                setupLogout();
                syncNameWidget();
            }
        })();
        </script>
        <?php
    }
}
