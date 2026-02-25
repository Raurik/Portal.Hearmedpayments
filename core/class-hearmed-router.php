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
        'hearmed_calendar' => [ 'module' => 'calendar', 'view' => 'calendar' ],
        // 'hearmed_calendar_settings' is provided by admin/admin-calendar-settings.php
        // and registers its own shortcode; exclude it here to avoid overriding.
        'hearmed_appointment_types' => [ 'module' => 'calendar', 'view' => 'appointment-types' ],
        'hearmed_blockouts' => [ 'module' => 'calendar', 'view' => 'blockouts' ],
        'hearmed_holidays' => [ 'module' => 'calendar', 'view' => 'holidays' ],
        'hearmed_exclusions' => [ 'module' => 'calendar', 'view' => 'exclusions' ],
        
        // Patients
        'hearmed_patients' => [ 'module' => 'patients', 'view' => 'list' ],
        
        // Orders & Finance
        'hearmed_orders' => [ 'module' => 'orders', 'view' => 'list' ],
        'hearmed_order_status' => [ 'module' => 'orders', 'view' => 'status' ],
        'hearmed_approvals' => [ 'module' => 'orders', 'view' => 'approvals' ],
        'hearmed_awaiting_fitting' => [ 'module' => 'orders', 'view' => 'awaiting-fitting' ],
        
        // Accounting
        'hearmed_accounting' => [ 'module' => 'accounting', 'view' => 'dashboard' ],
        'hearmed_invoices' => [ 'module' => 'accounting', 'view' => 'invoices' ],
        'hearmed_payments' => [ 'module' => 'accounting', 'view' => 'payments' ],
        'hearmed_credit_notes' => [ 'module' => 'accounting', 'view' => 'credit-notes' ],
        'hearmed_prsi' => [ 'module' => 'accounting', 'view' => 'prsi' ],
        
        // Reports
        'hearmed_reporting' => [ 'module' => 'reports', 'view' => 'dashboard' ],
        'hearmed_my_stats' => [ 'module' => 'reports', 'view' => 'my-stats' ],
        'hearmed_report_revenue' => [ 'module' => 'reports', 'view' => 'revenue' ],
        'hearmed_report_gp' => [ 'module' => 'reports', 'view' => 'gp' ],
        
        // Other modules
        'hearmed_repairs' => [ 'module' => 'repairs', 'view' => 'list' ],
        'hearmed_notifications' => [ 'module' => 'notifications', 'view' => 'list' ],
        'hearmed_kpi' => [ 'module' => 'kpi', 'view' => 'dashboard' ],
        // NOTE: admin console / admin management shortcodes are registered
        // by dedicated files under /admin and should not be routed through
        // this module router (no modules/admin/admin.php exists).
    ];
    
    /**
     * Register all shortcodes
     */
    public function register_shortcodes() {
        foreach ( $this->shortcode_map as $shortcode => $config ) {
            add_shortcode( $shortcode, [ $this, 'render_shortcode' ] );
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
        
        // Check authentication
        if ( ! is_user_logged_in() ) {
            return '<div id="hm-app"><p>Please log in to access this page.</p></div>';
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
}
