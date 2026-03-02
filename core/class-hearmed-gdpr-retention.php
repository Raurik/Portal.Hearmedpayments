<?php
/**
 * HearMed — GDPR Auth Data Retention
 *
 * Scheduled task to purge old authentication data:
 *   - Auth audit logs older than 2 years
 *   - Expired/revoked sessions older than 90 days
 *   - Expired invites older than 30 days
 *   - Revoked device records older than 90 days
 *
 * Runs daily via WP-Cron.
 *
 * @package HearMed_Portal
 * @since   5.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class HearMed_GDPR_Retention {

    const CRON_HOOK = 'hm_gdpr_auth_retention';

    /**
     * Retention periods (days).
     */
    const AUDIT_LOG_DAYS        = 730;  // 2 years
    const SESSION_DAYS          = 90;
    const INVITE_DAYS           = 30;
    const DEVICE_REVOKED_DAYS   = 90;
    const IMPERSONATION_DAYS    = 365;  // 1 year

    public function __construct() {
        add_action( self::CRON_HOOK, [ $this, 'run' ] );
        add_action( 'init', [ $this, 'schedule' ] );
    }

    /**
     * Ensure the cron event is scheduled.
     */
    public function schedule() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            // Schedule for 3 AM local time daily
            wp_schedule_event( strtotime( 'tomorrow 3:00am' ), 'daily', self::CRON_HOOK );
        }
    }

    /**
     * Run all retention tasks.
     */
    public function run() {
        $conn = HearMed_DB::get_connection();
        if ( ! $conn ) {
            error_log( '[HearMed GDPR] Cannot connect to database for retention run.' );
            return;
        }

        $counts = [];

        // 1. Purge old auth audit logs
        $result = pg_query_params( $conn,
            "DELETE FROM hearmed_admin.auth_audit_log WHERE created_at < NOW() - interval '$1 days'",
            [ self::AUDIT_LOG_DAYS ]
        );
        $counts['audit_log'] = $result ? pg_affected_rows( $result ) : 0;

        // 2. Purge expired/revoked sessions
        $result = pg_query_params( $conn,
            "DELETE FROM hearmed_reference.staff_sessions
             WHERE (revoked_at IS NOT NULL AND revoked_at < NOW() - interval '$1 days')
                OR (expires_at < NOW() - interval '$1 days')",
            [ self::SESSION_DAYS ]
        );
        $counts['sessions'] = $result ? pg_affected_rows( $result ) : 0;

        // 3. Purge expired/used invites
        $result = pg_query_params( $conn,
            "DELETE FROM hearmed_reference.staff_invites
             WHERE (used_at IS NOT NULL AND used_at < NOW() - interval '$1 days')
                OR (expires_at < NOW() - interval '$1 days')",
            [ self::INVITE_DAYS ]
        );
        $counts['invites'] = $result ? pg_affected_rows( $result ) : 0;

        // 4. Purge revoked device records
        $result = pg_query_params( $conn,
            "DELETE FROM hearmed_reference.staff_devices
             WHERE revoked_at IS NOT NULL AND revoked_at < NOW() - interval '$1 days'",
            [ self::DEVICE_REVOKED_DAYS ]
        );
        $counts['devices'] = $result ? pg_affected_rows( $result ) : 0;

        // 5. Purge old impersonation session records
        $result = pg_query_params( $conn,
            "DELETE FROM hearmed_admin.impersonation_sessions
             WHERE created_at < NOW() - interval '$1 days'",
            [ self::IMPERSONATION_DAYS ]
        );
        $counts['impersonation'] = $result ? pg_affected_rows( $result ) : 0;

        // Log summary
        $total = array_sum( $counts );
        if ( $total > 0 ) {
            error_log( sprintf(
                '[HearMed GDPR Retention] Purged %d records: audit=%d, sessions=%d, invites=%d, devices=%d, impersonation=%d',
                $total,
                $counts['audit_log'],
                $counts['sessions'],
                $counts['invites'],
                $counts['devices'],
                $counts['impersonation']
            ) );
        }

        // Audit the retention run itself
        if ( class_exists( 'PortalAuth' ) ) {
            PortalAuth::audit( 'GDPR_RETENTION_RUN', null, $counts );
        }
    }

    /**
     * Unschedule the cron event (for deactivation).
     */
    public static function unschedule() {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
    }
}

new HearMed_GDPR_Retention();
