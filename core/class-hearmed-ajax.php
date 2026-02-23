<?php
/**
 * HearMed AJAX Handler
 * 
 * Central dispatcher for all AJAX requests.
 * Module-specific AJAX handlers register with this class.
 * 
 * @package HearMed_Portal
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HearMed_Ajax {
    
    /**
     * Constructor - Register AJAX actions
     */
    public function __construct() {
        // Load legacy module AJAX files for backward compatibility
        $this->load_legacy_ajax();

        // Core AJAX handlers
        add_action( 'wp_ajax_hm_acknowledge_privacy_notice', [ $this, 'acknowledge_privacy_notice' ] );
    }
    
    /**
     * Load legacy AJAX handlers from module files
     * 
     * For backward compatibility, we load the old mod-*.php files
     * which contain their own add_action('wp_ajax_*') registrations
     */
    private function load_legacy_ajax() {
        // Load legacy module files (they self-register AJAX handlers)
        $module_files = [
            'mod-calendar.php',
            'mod-patients.php',
            'mod-orders.php',
            'mod-accounting.php',
            'mod-reports.php',
            'mod-repairs.php',
            'mod-notifications.php',
            'mod-approvals.php',
            'mod-kpi.php',
            'mod-cash.php',
            'mod-commissions.php',
            'mod-team-chat.php',
        ];
        
        foreach ( $module_files as $file ) {
            $file_path = HEARMED_PATH . 'modules/' . $file;
            if ( file_exists( $file_path ) ) {
                require_once $file_path;
            }
        }
    }
    
    /**
     * Register an AJAX action
     * 
     * @param string $action Action name (without hm_ prefix)
     * @param callable $callback Callback function
     * @param bool $nopriv Allow non-logged-in users (default: false)
     */
    public static function register( $action, $callback, $nopriv = false ) {
        add_action( 'wp_ajax_hm_' . $action, $callback );
        
        if ( $nopriv ) {
            add_action( 'wp_ajax_nopriv_hm_' . $action, $callback );
        }
    }
    
    /**
     * Verify nonce for AJAX request
     * 
     * @param string $nonce_field Nonce field name (default: nonce)
     * @return bool True if valid
     */
    public static function verify_nonce( $nonce_field = 'nonce' ) {
        if ( ! isset( $_POST[ $nonce_field ] ) ) {
            wp_send_json_error( 'Missing nonce' );
            exit;
        }
        
        if ( ! wp_verify_nonce( $_POST[ $nonce_field ], 'hm_nonce' ) ) {
            wp_send_json_error( 'Invalid nonce' );
            exit;
        }
        
        return true;
    }
    
    /**
     * Check user capability for AJAX request
     * 
     * @param string $capability Required capability
     * @return bool True if authorized
     */
    public static function check_capability( $capability ) {
        $auth = new HearMed_Auth();
        
        if ( ! $auth->can( $capability ) ) {
            wp_send_json_error( 'Unauthorized' );
            exit;
        }
        
        return true;
    }
    
    /**
     * Get POST data with sanitization
     * 
     * @param string $key Data key
     * @param mixed $default Default value
     * @param string $sanitize Sanitization method (text|email|int|float|html)
     * @return mixed Sanitized value
     */
    public static function get_post( $key, $default = '', $sanitize = 'text' ) {
        if ( ! isset( $_POST[ $key ] ) ) {
            return $default;
        }
        
        $value = $_POST[ $key ];
        
        switch ( $sanitize ) {
            case 'email':
                return sanitize_email( $value );
            case 'int':
                return intval( $value );
            case 'float':
                return floatval( $value );
            case 'html':
                return wp_kses_post( $value );
            case 'text':
            default:
                return sanitize_text_field( $value );
        }
    }
    
    /**
     * Send JSON success response
     * 
     * @param mixed $data Response data
     * @param int $status_code HTTP status code
     */
    public static function success( $data = null, $status_code = 200 ) {
        wp_send_json_success( $data, $status_code );
        exit;
    }
    
    /**
     * Send JSON error response
     * 
     * @param string $message Error message
     * @param mixed $data Additional error data
     * @param int $status_code HTTP status code
     */
    public static function error( $message, $data = null, $status_code = 400 ) {
        wp_send_json_error([
            'message' => $message,
            'data' => $data,
        ], $status_code );
        exit;
    }

    /**
     * AJAX handler: acknowledge privacy notice
     *
     * Records that the current user has read and accepted the Staff Data
     * Processing Notice so they can proceed into the portal.
     */
    public function acknowledge_privacy_notice() {
        check_ajax_referer( 'hm_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'Unauthorized' );
            return;
        }

        $user_id = get_current_user_id();
        update_user_meta( $user_id, 'hm_privacy_notice_accepted', current_time( 'mysql' ) );

        wp_send_json_success();
    }
}
