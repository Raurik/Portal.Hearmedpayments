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

        // ── Core / Auth ───────────────────────────────────────────────────────
        add_action( 'wp_ajax_hm_acknowledge_privacy_notice', [ $this, 'acknowledge_privacy_notice' ] );

        // ── Orders — full 6-stage workflow ────────────────────────────────────
        add_action( 'wp_ajax_hm_create_order',   [ 'HearMed_Orders', 'ajax_create_order' ] );   // Stage 1
        add_action( 'wp_ajax_hm_approve_order',  [ 'HearMed_Orders', 'ajax_approve_order' ] );  // Stage 2a
        add_action( 'wp_ajax_hm_reject_order',   [ 'HearMed_Orders', 'ajax_reject_order' ] );   // Stage 2b
        add_action( 'wp_ajax_hm_mark_ordered',   [ 'HearMed_Orders', 'ajax_mark_ordered' ] );   // Stage 3
        add_action( 'wp_ajax_hm_mark_received',  [ 'HearMed_Orders', 'ajax_mark_received' ] );  // Stage 4
        add_action( 'wp_ajax_hm_save_serials',   [ 'HearMed_Orders', 'ajax_save_serials' ] );   // Stage 4→5
        add_action( 'wp_ajax_hm_complete_order', [ 'HearMed_Orders', 'ajax_complete_order' ] ); // Stage 6 (fires QBO)
        add_action( 'wp_ajax_hm_patient_search', [ 'HearMed_Orders', 'ajax_patient_search' ] ); // Autocomplete

        // Accounting — ADD THESE 5 NEW ONES
add_action('wp_ajax_hm_save_supplier_invoice', ['HearMed_Accounting', 'ajax_save_supplier_invoice']);
add_action('wp_ajax_hm_retry_qbo_sync',        ['HearMed_Accounting', 'ajax_retry_qbo_sync']);
add_action('wp_ajax_hm_assign_bank_txn',        ['HearMed_Accounting', 'ajax_assign_bank_txn']);
add_action('wp_ajax_hm_qbo_sync_accounts',      ['HearMed_Accounting', 'ajax_qbo_sync_accounts']);
add_action('wp_ajax_hm_qbo_disconnect',         ['HearMed_Accounting', 'ajax_qbo_disconnect']);

        // ── Accounting / QuickBooks ───────────────────────────────────────────
        add_action( 'wp_ajax_hm_retry_qbo_sync', [ 'HearMed_Accounting', 'ajax_retry_sync' ] );

        // ── Calendar ─────────────────────────────────────────────────────────
        // Calendar registers its own handlers inside mod-calendar.php
        // (legacy pattern kept until calendar is refactored)

        // ── Patients ─────────────────────────────────────────────────────────
        // Patients registers its own handlers inside mod-patients.php
        // (legacy pattern kept until patients is refactored)

        // ── Approvals ────────────────────────────────────────────────────────
        // Approvals registers its own handlers inside mod-approvals.php

        // ── Notifications (registered when module is built) ───────────────────
        // add_action( 'wp_ajax_hm_mark_notification_read', [ 'HearMed_Notifications', 'ajax_mark_read' ] );
        // add_action( 'wp_ajax_hm_dismiss_notification',   [ 'HearMed_Notifications', 'ajax_dismiss' ] );

        // ── Repairs (registered when module is built) ─────────────────────────
        // add_action( 'wp_ajax_hm_create_repair',   [ 'HearMed_Repairs', 'ajax_create' ] );
        // add_action( 'wp_ajax_hm_update_repair',   [ 'HearMed_Repairs', 'ajax_update' ] );

        // ── Team Chat (registered when module is built) ───────────────────────
        // add_action( 'wp_ajax_hm_send_message',    [ 'HearMed_Chat', 'ajax_send' ] );
        // add_action( 'wp_ajax_hm_get_messages',    [ 'HearMed_Chat', 'ajax_get' ] );

        // ── Reports / Commissions / KPI / Cash (registered when built) ────────
        // add_action( 'wp_ajax_hm_get_report',      [ 'HearMed_Reports',     'ajax_get' ] );
        // add_action( 'wp_ajax_hm_get_commissions', [ 'HearMed_Commissions', 'ajax_get' ] );
        // add_action( 'wp_ajax_hm_get_kpi',         [ 'HearMed_KPI',         'ajax_get' ] );
    }

    // -------------------------------------------------------------------------
    // Helper: register a single action (used by modules that self-register)
    // -------------------------------------------------------------------------
    public static function register( $action, $callback, $nopriv = false ) {
        add_action( 'wp_ajax_hm_' . $action, $callback );
        if ( $nopriv ) {
            add_action( 'wp_ajax_nopriv_hm_' . $action, $callback );
        }
    }

    // -------------------------------------------------------------------------
    // Helper: verify nonce
    // -------------------------------------------------------------------------
    public static function verify_nonce( $nonce_field = 'nonce' ) {
        if ( empty( $_POST[ $nonce_field ] ) ) {
            wp_send_json_error( 'Missing nonce' ); exit;
        }
        if ( ! wp_verify_nonce( $_POST[ $nonce_field ], 'hearmed_nonce' ) ) {
            wp_send_json_error( 'Invalid nonce' ); exit;
        }
        return true;
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

    // -------------------------------------------------------------------------
    // Privacy notice acknowledgement
    // -------------------------------------------------------------------------
    public function acknowledge_privacy_notice() {
        check_ajax_referer( 'hearmed_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'Unauthorized' ); return;
        }

        update_user_meta( get_current_user_id(), 'hm_privacy_notice_accepted', current_time('mysql') );
        wp_send_json_success();
    }
}