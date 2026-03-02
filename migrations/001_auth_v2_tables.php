<?php
/**
 * Migration 001: Auth V2 Tables
 * 
 * Extends/creates tables for the custom auth subsystem:
 *   - hearmed_reference.staff_auth (extend with lockout + recovery fields)
 *   - hearmed_reference.staff_devices (new)
 *   - hearmed_reference.staff_sessions (new)
 *   - hearmed_reference.staff_invites  (new)
 *   - hearmed_admin.auth_audit_log     (new — auth-specific audit log)
 *   - hearmed_admin.impersonation_sessions (new)
 *   - hearmed_admin.report_permissions (new)
 *
 * Run via: php migrations/001_auth_v2_tables.php
 * Or call HearMed_Migration_AuthV2::run() from WP context.
 *
 * SAFE TO RE-RUN: uses IF NOT EXISTS / ADD COLUMN IF NOT EXISTS.
 */

if ( ! defined( 'ABSPATH' ) ) {
    // Allow running from CLI with WP bootstrap
    $wp_load = dirname(__DIR__, 4) . '/wp-load.php';
    if ( file_exists( $wp_load ) ) {
        require_once $wp_load;
    } else {
        die("Cannot locate wp-load.php. Run from the WordPress root or within WP context.\n");
    }
}

class HearMed_Migration_AuthV2 {

    public static function run() {
        $pdo = self::get_pdo();
        $statements = self::get_statements();
        $results = [];

        foreach ( $statements as $label => $sql ) {
            try {
                $pdo->exec( $sql );
                $results[] = "✅ $label";
            } catch ( PDOException $e ) {
                $results[] = "❌ $label: " . $e->getMessage();
            }
        }

        return $results;
    }

    private static function get_pdo() {
        // Re-use the HearMed PG connection if available
        if ( class_exists( 'HearMed_DB' ) ) {
            $conn = HearMed_DB::instance()->get_connection();
            if ( $conn ) {
                // pg_connect resource → build DSN from it
                $host = pg_host( $conn );
                $port = pg_port( $conn );
                $db   = pg_dbname( $conn );
                $user = pg_parameter_status( $conn, 'user' ) ?: '';
                $dsn  = "pgsql:host=$host;port=$port;dbname=$db";
                return new PDO( $dsn, $user, '', [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ] );
            }
        }

        // Fallback: read from wp-config HM constants
        $host = defined('HM_PG_HOST') ? HM_PG_HOST : 'localhost';
        $port = defined('HM_PG_PORT') ? HM_PG_PORT : '5432';
        $db   = defined('HM_PG_DB')   ? HM_PG_DB   : 'hearmed';
        $user = defined('HM_PG_USER') ? HM_PG_USER : 'hearmed';
        $pass = defined('HM_PG_PASS') ? HM_PG_PASS : '';
        $dsn  = "pgsql:host=$host;port=$port;dbname=$db";
        return new PDO( $dsn, $user, $pass, [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ] );
    }

    private static function get_statements() {
        return [

            // ─── 1. Extend staff_auth with lockout & security fields ────────
            'staff_auth: add failed_login_count' => "
                ALTER TABLE hearmed_reference.staff_auth
                ADD COLUMN IF NOT EXISTS failed_login_count integer NOT NULL DEFAULT 0;
            ",
            'staff_auth: add last_failed_login_at' => "
                ALTER TABLE hearmed_reference.staff_auth
                ADD COLUMN IF NOT EXISTS last_failed_login_at timestamp;
            ",
            'staff_auth: add locked_until' => "
                ALTER TABLE hearmed_reference.staff_auth
                ADD COLUMN IF NOT EXISTS locked_until timestamp;
            ",
            'staff_auth: add password_changed_at' => "
                ALTER TABLE hearmed_reference.staff_auth
                ADD COLUMN IF NOT EXISTS password_changed_at timestamp;
            ",
            'staff_auth: add recovery_codes_hash' => "
                ALTER TABLE hearmed_reference.staff_auth
                ADD COLUMN IF NOT EXISTS recovery_codes_hash text;
            ",
            'staff_auth: add totp_secret_enc' => "
                ALTER TABLE hearmed_reference.staff_auth
                ADD COLUMN IF NOT EXISTS totp_secret_enc text;
            ",

            // ─── 2. Ensure staff.status column exists ────────────────────────
            'staff: add status column' => "
                ALTER TABLE hearmed_reference.staff
                ADD COLUMN IF NOT EXISTS status varchar(20) NOT NULL DEFAULT 'active';
            ",

            // ─── 3. staff_devices ────────────────────────────────────────────
            'Create staff_devices' => "
                CREATE TABLE IF NOT EXISTS hearmed_reference.staff_devices (
                    id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                    staff_id        bigint NOT NULL REFERENCES hearmed_reference.staff(id) ON DELETE CASCADE,
                    device_token    varchar(128) NOT NULL,
                    created_at      timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    last_seen_at    timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    last_seen_ip    inet,
                    user_agent      text,
                    last_2fa_at     timestamp,
                    trusted_until   timestamp,
                    revoked_at      timestamp,
                    UNIQUE (staff_id, device_token)
                );
            ",
            'staff_devices: index on staff_id' => "
                CREATE INDEX IF NOT EXISTS idx_staff_devices_staff
                ON hearmed_reference.staff_devices (staff_id);
            ",

            // ─── 4. staff_sessions ──────────────────────────────────────────
            'Create staff_sessions' => "
                CREATE TABLE IF NOT EXISTS hearmed_reference.staff_sessions (
                    id                  bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                    staff_id            bigint NOT NULL REFERENCES hearmed_reference.staff(id) ON DELETE CASCADE,
                    device_token        varchar(128),
                    session_token_hash  varchar(128) NOT NULL,
                    created_at          timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    expires_at          timestamp NOT NULL,
                    last_seen_at        timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    revoked_at          timestamp,
                    ip                  inet,
                    user_agent          text,
                    is_impersonation    boolean NOT NULL DEFAULT false,
                    actor_staff_id      bigint,
                    target_staff_id     bigint
                );
            ",
            'staff_sessions: index on token hash' => "
                CREATE INDEX IF NOT EXISTS idx_staff_sessions_token
                ON hearmed_reference.staff_sessions (session_token_hash);
            ",
            'staff_sessions: index on staff_id' => "
                CREATE INDEX IF NOT EXISTS idx_staff_sessions_staff
                ON hearmed_reference.staff_sessions (staff_id);
            ",

            // ─── 5. staff_invites ───────────────────────────────────────────
            'Create staff_invites' => "
                CREATE TABLE IF NOT EXISTS hearmed_reference.staff_invites (
                    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                    staff_id    bigint NOT NULL REFERENCES hearmed_reference.staff(id) ON DELETE CASCADE,
                    token_hash  varchar(128) NOT NULL,
                    expires_at  timestamp NOT NULL,
                    used_at     timestamp,
                    created_at  timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
                );
            ",
            'staff_invites: index on token_hash' => "
                CREATE INDEX IF NOT EXISTS idx_staff_invites_token
                ON hearmed_reference.staff_invites (token_hash);
            ",

            // ─── 6. Auth audit log (separate from generic audit_log) ─────────
            'Create auth_audit_log' => "
                CREATE TABLE IF NOT EXISTS hearmed_admin.auth_audit_log (
                    id                  bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                    actor_staff_id      bigint,
                    action              varchar(60) NOT NULL,
                    target_staff_id     bigint,
                    meta                jsonb DEFAULT '{}',
                    ip                  inet,
                    user_agent          text,
                    created_at          timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
                );
            ",
            'auth_audit_log: index on actor' => "
                CREATE INDEX IF NOT EXISTS idx_auth_audit_actor
                ON hearmed_admin.auth_audit_log (actor_staff_id);
            ",
            'auth_audit_log: index on action' => "
                CREATE INDEX IF NOT EXISTS idx_auth_audit_action
                ON hearmed_admin.auth_audit_log (action);
            ",
            'auth_audit_log: index on created_at' => "
                CREATE INDEX IF NOT EXISTS idx_auth_audit_created
                ON hearmed_admin.auth_audit_log (created_at);
            ",

            // ─── 7. Impersonation sessions ──────────────────────────────────
            'Create impersonation_sessions' => "
                CREATE TABLE IF NOT EXISTS hearmed_admin.impersonation_sessions (
                    id                  bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                    actor_staff_id      bigint NOT NULL,
                    target_staff_id     bigint NOT NULL,
                    session_id          bigint,
                    reason              text NOT NULL,
                    created_at          timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    expires_at          timestamp NOT NULL,
                    ended_at            timestamp
                );
            ",
            'impersonation_sessions: index on actor' => "
                CREATE INDEX IF NOT EXISTS idx_impersonation_actor
                ON hearmed_admin.impersonation_sessions (actor_staff_id);
            ",

            // ─── 8. Report permissions ──────────────────────────────────────
            'Create report_permissions' => "
                CREATE TABLE IF NOT EXISTS hearmed_admin.report_permissions (
                    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                    report_key  varchar(100) NOT NULL,
                    role        varchar(50) NOT NULL,
                    allowed     boolean NOT NULL DEFAULT true,
                    created_at  timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE (report_key, role)
                );
            ",

            // ─── 9. Seed default report permissions ─────────────────────────
            'Seed report_permissions' => "
                INSERT INTO hearmed_admin.report_permissions (report_key, role, allowed) VALUES
                    ('revenue', 'c_level', true),
                    ('revenue', 'finance', true),
                    ('revenue', 'dispenser', false),
                    ('revenue', 'reception', false),
                    ('gp', 'c_level', true),
                    ('gp', 'finance', true),
                    ('gp', 'dispenser', true),
                    ('gp', 'reception', false),
                    ('my-stats', 'c_level', true),
                    ('my-stats', 'finance', true),
                    ('my-stats', 'dispenser', true),
                    ('my-stats', 'reception', true)
                ON CONFLICT (report_key, role) DO NOTHING;
            ",
        ];
    }
}

// Auto-run if called directly
if ( php_sapi_name() === 'cli' || ( isset($_GET['run_migration']) && current_user_can('manage_options') ) ) {
    $results = HearMed_Migration_AuthV2::run();
    foreach ( $results as $r ) {
        echo $r . "\n";
    }
}
