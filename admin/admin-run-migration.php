<?php
/**
 * HearMed — One-Click Auth V2 Migration
 *
 * Adds a hidden WP admin page at:
 *   /wp-admin/admin.php?page=hm-run-migration
 *
 * Only available to WP users with manage_options capability.
 * Connects to Railway Postgres via HearMed_DB and creates auth V2 tables.
 *
 * DELETE THIS FILE after successful migration.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class HearMed_Run_Migration {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
    }

    public function register_page() {
        add_management_page(
            'HearMed Auth Migration',
            'HM Auth Migration',
            'manage_options',
            'hm-run-migration',
            [ $this, 'render' ]
        );
    }

    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        echo '<div class="wrap">';
        echo '<h1>HearMed Auth V2 — Database Migration</h1>';

        // Test connection first
        $conn = HearMed_DB::get_connection();
        if ( ! $conn ) {
            echo '<div class="notice notice-error"><p><strong>Cannot connect to Railway Postgres.</strong> Check HEARMED_PG_* constants in wp-config.php.</p></div>';
            echo '</div>';
            return;
        }

        echo '<div class="notice notice-success"><p>✅ Connected to Railway Postgres (' . esc_html( HEARMED_PG_HOST ) . ':' . esc_html( HEARMED_PG_PORT ) . '/' . esc_html( HEARMED_PG_DB ) . ')</p></div>';

        // Run migration if requested
        if ( isset( $_POST['run_migration'] ) && check_admin_referer( 'hm_run_migration' ) ) {
            $results = $this->run_migration( $conn );
            echo '<h2>Migration Results</h2>';
            echo '<pre style="background:#1d2327;color:#fff;padding:16px;border-radius:6px;font-size:13px;max-height:500px;overflow:auto;">';
            foreach ( $results as $r ) {
                echo esc_html( $r ) . "\n";
            }
            echo '</pre>';

            // Verify tables
            echo '<h2>Verification</h2>';
            $this->verify_tables( $conn );
        } else {
            // Show current state
            echo '<h2>Current State</h2>';
            $this->verify_tables( $conn );

            echo '<form method="post">';
            wp_nonce_field( 'hm_run_migration' );
            echo '<p>This will create/extend the following in your <strong>Railway Postgres</strong>:</p>';
            echo '<ul style="list-style:disc;margin-left:20px;">';
            echo '<li><code>hearmed_reference.staff_auth</code> — add lockout + security columns</li>';
            echo '<li><code>hearmed_reference.staff_devices</code> — new table</li>';
            echo '<li><code>hearmed_reference.staff_sessions</code> — new table</li>';
            echo '<li><code>hearmed_reference.staff_invites</code> — new table</li>';
            echo '<li><code>hearmed_admin.auth_audit_log</code> — new table</li>';
            echo '<li><code>hearmed_admin.impersonation_sessions</code> — new table</li>';
            echo '<li><code>hearmed_admin.report_permissions</code> — new table + seed data</li>';
            echo '</ul>';
            echo '<p><strong>Safe to re-run</strong> — uses IF NOT EXISTS / ADD COLUMN IF NOT EXISTS.</p>';
            echo '<p><input type="submit" name="run_migration" class="button button-primary button-hero" value="Run Migration Now"></p>';
            echo '</form>';
        }

        echo '</div>';
    }

    private function run_migration( $conn ) {
        $statements = $this->get_statements();
        $results = [];

        foreach ( $statements as $label => $sql ) {
            $ok = @pg_query( $conn, $sql );
            if ( $ok ) {
                $results[] = "✅ $label";
            } else {
                $err = pg_last_error( $conn );
                $results[] = "❌ $label: $err";
            }
        }

        return $results;
    }

    private function verify_tables( $conn ) {
        $tables_to_check = [
            'hearmed_reference.staff_auth',
            'hearmed_reference.staff_devices',
            'hearmed_reference.staff_sessions',
            'hearmed_reference.staff_invites',
            'hearmed_admin.auth_audit_log',
            'hearmed_admin.impersonation_sessions',
            'hearmed_admin.report_permissions',
        ];

        echo '<table class="widefat" style="max-width:600px;">';
        echo '<thead><tr><th>Table</th><th>Status</th><th>Rows</th></tr></thead><tbody>';

        foreach ( $tables_to_check as $table ) {
            $parts = explode( '.', $table );
            $check = @pg_query_params( $conn,
                "SELECT EXISTS (
                    SELECT 1 FROM information_schema.tables 
                    WHERE table_schema = $1 AND table_name = $2
                )",
                [ $parts[0], $parts[1] ]
            );
            $exists = $check ? pg_fetch_result( $check, 0, 0 ) === 't' : false;

            $count = '-';
            if ( $exists ) {
                $cnt = @pg_query( $conn, "SELECT COUNT(*) FROM $table" );
                $count = $cnt ? pg_fetch_result( $cnt, 0, 0 ) : '?';
            }

            $status = $exists ? '✅ Exists' : '❌ Missing';
            echo "<tr><td><code>$table</code></td><td>$status</td><td>$count</td></tr>";
        }

        echo '</tbody></table>';

        // Check new columns on staff_auth
        $col_check = @pg_query( $conn,
            "SELECT column_name FROM information_schema.columns 
             WHERE table_schema = 'hearmed_reference' AND table_name = 'staff_auth'
             ORDER BY ordinal_position"
        );
        if ( $col_check ) {
            $cols = [];
            while ( $row = pg_fetch_assoc( $col_check ) ) {
                $cols[] = $row['column_name'];
            }
            echo '<h3 style="margin-top:16px;">staff_auth columns</h3>';
            echo '<p><code>' . esc_html( implode( ', ', $cols ) ) . '</code></p>';
        }
    }

    private function get_statements() {
        return [
            'staff_auth: add failed_login_count' =>
                "ALTER TABLE hearmed_reference.staff_auth ADD COLUMN IF NOT EXISTS failed_login_count integer NOT NULL DEFAULT 0;",

            'staff_auth: add last_failed_login_at' =>
                "ALTER TABLE hearmed_reference.staff_auth ADD COLUMN IF NOT EXISTS last_failed_login_at timestamp;",

            'staff_auth: add locked_until' =>
                "ALTER TABLE hearmed_reference.staff_auth ADD COLUMN IF NOT EXISTS locked_until timestamp;",

            'staff_auth: add password_changed_at' =>
                "ALTER TABLE hearmed_reference.staff_auth ADD COLUMN IF NOT EXISTS password_changed_at timestamp;",

            'staff_auth: add recovery_codes_hash' =>
                "ALTER TABLE hearmed_reference.staff_auth ADD COLUMN IF NOT EXISTS recovery_codes_hash text;",

            'staff_auth: add totp_secret_enc' =>
                "ALTER TABLE hearmed_reference.staff_auth ADD COLUMN IF NOT EXISTS totp_secret_enc text;",

            'staff: add status column' =>
                "ALTER TABLE hearmed_reference.staff ADD COLUMN IF NOT EXISTS status varchar(20) NOT NULL DEFAULT 'active';",

            'Create staff_devices' =>
                "CREATE TABLE IF NOT EXISTS hearmed_reference.staff_devices (
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
                );",

            'staff_devices: index on staff_id' =>
                "CREATE INDEX IF NOT EXISTS idx_staff_devices_staff ON hearmed_reference.staff_devices (staff_id);",

            'Create staff_sessions' =>
                "CREATE TABLE IF NOT EXISTS hearmed_reference.staff_sessions (
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
                );",

            'staff_sessions: index on token hash' =>
                "CREATE INDEX IF NOT EXISTS idx_staff_sessions_token ON hearmed_reference.staff_sessions (session_token_hash);",

            'staff_sessions: index on staff_id' =>
                "CREATE INDEX IF NOT EXISTS idx_staff_sessions_staff ON hearmed_reference.staff_sessions (staff_id);",

            'Create staff_invites' =>
                "CREATE TABLE IF NOT EXISTS hearmed_reference.staff_invites (
                    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                    staff_id    bigint NOT NULL REFERENCES hearmed_reference.staff(id) ON DELETE CASCADE,
                    token_hash  varchar(128) NOT NULL,
                    expires_at  timestamp NOT NULL,
                    used_at     timestamp,
                    created_at  timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
                );",

            'staff_invites: index on token_hash' =>
                "CREATE INDEX IF NOT EXISTS idx_staff_invites_token ON hearmed_reference.staff_invites (token_hash);",

            'Create auth_audit_log' =>
                "CREATE TABLE IF NOT EXISTS hearmed_admin.auth_audit_log (
                    id                  bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                    actor_staff_id      bigint,
                    action              varchar(60) NOT NULL,
                    target_staff_id     bigint,
                    meta                jsonb DEFAULT '{}',
                    ip                  inet,
                    user_agent          text,
                    created_at          timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
                );",

            'auth_audit_log: index on actor' =>
                "CREATE INDEX IF NOT EXISTS idx_auth_audit_actor ON hearmed_admin.auth_audit_log (actor_staff_id);",

            'auth_audit_log: index on action' =>
                "CREATE INDEX IF NOT EXISTS idx_auth_audit_action ON hearmed_admin.auth_audit_log (action);",

            'auth_audit_log: index on created_at' =>
                "CREATE INDEX IF NOT EXISTS idx_auth_audit_created ON hearmed_admin.auth_audit_log (created_at);",

            'Create impersonation_sessions' =>
                "CREATE TABLE IF NOT EXISTS hearmed_admin.impersonation_sessions (
                    id                  bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                    actor_staff_id      bigint NOT NULL,
                    target_staff_id     bigint NOT NULL,
                    session_id          bigint,
                    reason              text NOT NULL,
                    created_at          timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    expires_at          timestamp NOT NULL,
                    ended_at            timestamp
                );",

            'impersonation_sessions: index on actor' =>
                "CREATE INDEX IF NOT EXISTS idx_impersonation_actor ON hearmed_admin.impersonation_sessions (actor_staff_id);",

            'Create report_permissions' =>
                "CREATE TABLE IF NOT EXISTS hearmed_admin.report_permissions (
                    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                    report_key  varchar(100) NOT NULL,
                    role        varchar(50) NOT NULL,
                    allowed     boolean NOT NULL DEFAULT true,
                    created_at  timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE (report_key, role)
                );",

            'Seed report_permissions' =>
                "INSERT INTO hearmed_admin.report_permissions (report_key, role, allowed) VALUES
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
                ON CONFLICT (report_key, role) DO NOTHING;",
        ];
    }
}

new HearMed_Run_Migration();
