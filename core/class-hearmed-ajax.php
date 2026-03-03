<?php
/**
 * HearMed AJAX Handler
 *
 * Central dispatcher for all AJAX requests.
 * All module AJAX actions are registered here — one place, no surprises.
 *
 * @package HearMed_Portal
 * @since   5.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class HearMed_Ajax {

    public function __construct() {
        $this->load_modules();
        $this->register_all();
    }

    // -------------------------------------------------------------------------
    // Load all module files so their classes are available
    // -------------------------------------------------------------------------
    private function load_modules() {
        $modules = [
            'mod-calendar.php',
            'mod-patients.php',
            'mod-orders.php',
            'mod-accounting.php',
            'mod-approvals.php',
            'mod-notifications.php',
            'mod-repairs.php',
            'mod-reports.php',
            'mod-commissions.php',
            'mod-kpi.php',
            'mod-cash.php',
            'mod-team-chat.php',
            'mod-stock.php',
            'mod-refunds.php',
            'mod-qbo-review.php',
        ];

        foreach ( $modules as $file ) {
            $path = HEARMED_PATH . 'modules/' . $file;
            if ( file_exists( $path ) ) {
                require_once $path;
            }
        }

        // QBO class lives in core
        $qbo = HEARMED_PATH . 'core/class-hearmed-qbo.php';
        if ( file_exists( $qbo ) ) {
            require_once $qbo;
        }
    }

    // -------------------------------------------------------------------------
    // Register every AJAX action in one place
    // -------------------------------------------------------------------------
    private function register_all() {
        // All AJAX handlers are registered by their own modules.
        // Do NOT add registrations here — it creates duplicates.
        // See: mod-orders.php, mod-accounting.php, mod-stock.php, etc.
    }

    // -------------------------------------------------------------------------
    // Helper: register a single action (used by modules that self-register)
    // -------------------------------------------------------------------------
    public static function register( $action, $callback, $nopriv = true ) {
        add_action( 'wp_ajax_hm_' . $action, $callback );
        // Always register nopriv — portal auth (HMSESS) may be valid even
        // when WP auth is not (e.g. WP users deleted).  Each handler
        // checks PortalAuth internally.
        add_action( 'wp_ajax_nopriv_hm_' . $action, $callback );
    }

    // -------------------------------------------------------------------------
    // Helper: verify nonce
    // -------------------------------------------------------------------------
    public static function verify_nonce( $nonce_field = 'nonce' ) {
        if ( empty( $_POST[ $nonce_field ] ) ) {
            wp_send_json_error( 'Missing nonce' ); exit;
        }
        // All nonces now standardised to 'hm_nonce'
        if ( wp_verify_nonce( $_POST[ $nonce_field ], 'hm_nonce' ) ) {
            return true;
        }
        wp_send_json_error( 'Invalid nonce' ); exit;
    }

    // -------------------------------------------------------------------------
    // Helper: check capability
    // -------------------------------------------------------------------------
    public static function check_capability( $capability ) {
        if ( ! HearMed_Auth::can( $capability ) ) {
            wp_send_json_error( 'Unauthorized' ); exit;
        }
        return true;
    }

    // -------------------------------------------------------------------------
    // Helper: get sanitised POST value
    // -------------------------------------------------------------------------
    public static function get_post( $key, $default = '', $sanitize = 'text' ) {
        if ( ! isset( $_POST[ $key ] ) ) return $default;
        $value = $_POST[ $key ];
        switch ( $sanitize ) {
            case 'email': return sanitize_email( $value );
            case 'int':   return intval( $value );
            case 'float': return floatval( $value );
            case 'html':  return wp_kses_post( $value );
            default:      return sanitize_text_field( $value );
        }
    }

    // -------------------------------------------------------------------------
    // Helper: success response
    // -------------------------------------------------------------------------
    public static function success( $data = null, $status_code = 200 ) {
        wp_send_json_success( $data, $status_code ); exit;
    }

    // -------------------------------------------------------------------------
    // Helper: error response
    // -------------------------------------------------------------------------
    public static function error( $message, $data = null, $status_code = 400 ) {
        wp_send_json_error( [ 'message' => $message, 'data' => $data ], $status_code ); exit;
    }
}