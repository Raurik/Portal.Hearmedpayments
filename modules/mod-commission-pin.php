<?php
/**
 * HearMed Module — Commission PIN
 *
 * Allows each dispenser to set / verify a 4-digit PIN used to
 * authorise commission-related actions (e.g. applying a discount,
 * overriding a price, confirming a commission payout).
 *
 * AJAX endpoints:
 *   hm_set_commission_pin    – set or update PIN  (requires current PIN if one exists)
 *   hm_verify_commission_pin – verify PIN          (returns true / false)
 *
 * @package HearMed_Portal
 * @since   5.3.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class HearMed_Commission_Pin {

    private $auth_table = 'hearmed_reference.staff_auth';

    public function __construct() {
        add_action( 'wp_ajax_hm_set_commission_pin',    [ $this, 'ajax_set_pin' ] );
        add_action( 'wp_ajax_hm_verify_commission_pin', [ $this, 'ajax_verify_pin' ] );
    }

    /* ──────────────────────────────────────────────
       SET / UPDATE PIN
       Body: { nonce, staff_id, new_pin, current_pin? }
       ────────────────────────────────────────────── */
    public function ajax_set_pin() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Permission denied' );
            return;
        }

        $staff_id = intval( $_POST['staff_id'] ?? 0 );
        $new_pin  = sanitize_text_field( $_POST['new_pin'] ?? '' );

        if ( ! $staff_id )                    { wp_send_json_error( 'Invalid staff ID' );  return; }
        if ( ! preg_match( '/^\d{4}$/', $new_pin ) ) { wp_send_json_error( 'PIN must be exactly 4 digits' ); return; }

        // If staff already has a PIN, require current PIN to change it
        $existing = $this->get_pin_hash( $staff_id );
        if ( $existing ) {
            $current_pin = sanitize_text_field( $_POST['current_pin'] ?? '' );
            if ( ! $current_pin || ! password_verify( $current_pin, $existing ) ) {
                wp_send_json_error( 'Current PIN is incorrect' );
                return;
            }
        }

        $hash   = password_hash( $new_pin, PASSWORD_DEFAULT );
        $result = HearMed_DB::update(
            $this->auth_table,
            [ 'commission_pin' => $hash ],
            [ 'staff_id' => $staff_id ]
        );

        if ( $result === false ) {
            wp_send_json_error( HearMed_DB::last_error() ?: 'Database error' );
        } else {
            wp_send_json_success( [ 'message' => 'Commission PIN updated' ] );
        }
    }

    /* ──────────────────────────────────────────────
       VERIFY PIN
       Body: { nonce, staff_id, pin }
       ────────────────────────────────────────────── */
    public function ajax_verify_pin() {
        check_ajax_referer( 'hm_nonce', 'nonce' );

        $staff_id = intval( $_POST['staff_id'] ?? 0 );
        $pin      = sanitize_text_field( $_POST['pin'] ?? '' );

        if ( ! $staff_id || ! $pin ) {
            wp_send_json_error( 'Missing staff ID or PIN' );
            return;
        }

        $hash = $this->get_pin_hash( $staff_id );
        if ( ! $hash ) {
            wp_send_json_error( 'No commission PIN set for this staff member' );
            return;
        }

        if ( password_verify( $pin, $hash ) ) {
            wp_send_json_success( [ 'verified' => true ] );
        } else {
            wp_send_json_error( 'Incorrect PIN' );
        }
    }

    /* ──────────────────────────────────────────────
       HELPER: check if staff member has a PIN set
       ────────────────────────────────────────────── */
    public function has_pin( $staff_id ) {
        return (bool) $this->get_pin_hash( $staff_id );
    }

    /* ──────────────────────────────────────────────
       INTERNAL: fetch hashed PIN from DB
       ────────────────────────────────────────────── */
    private function get_pin_hash( $staff_id ) {
        $staff_id = intval( $staff_id );
        return HearMed_DB::get_var(
            "SELECT commission_pin FROM {$this->auth_table} WHERE staff_id = $staff_id"
        );
    }
}

new HearMed_Commission_Pin();
