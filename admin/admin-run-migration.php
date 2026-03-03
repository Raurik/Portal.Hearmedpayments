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

        // V2 status
        $v2_active = defined( 'PORTAL_AUTH_V2' ) && PORTAL_AUTH_V2;
        echo '<p><strong>Auth V2 Status:</strong> ' . ( $v2_active ? '🟢 ACTIVE' : '🔴 Inactive (comment out in wp-config.php)' ) . '</p>';

        // Tab navigation
        $tab = sanitize_text_field( $_GET['tab'] ?? 'migration' );
        echo '<h2 class="nav-tab-wrapper">';
        echo '<a href="?page=hm-run-migration&tab=migration" class="nav-tab ' . ( $tab === 'migration' ? 'nav-tab-active' : '' ) . '">Migration</a>';
        echo '<a href="?page=hm-run-migration&tab=passwords" class="nav-tab ' . ( $tab === 'passwords' ? 'nav-tab-active' : '' ) . '">Staff Passwords</a>';
        echo '<a href="?page=hm-run-migration&tab=activate" class="nav-tab ' . ( $tab === 'activate' ? 'nav-tab-active' : '' ) . '">Activate V2</a>';
        echo '</h2>';

        if ( $tab === 'passwords' ) {
            $this->render_passwords_tab( $conn );
        } elseif ( $tab === 'activate' ) {
            $this->render_activate_tab( $conn, $v2_active );
        } else {
            $this->render_migration_tab( $conn );
        }

        echo '</div>';
    }

    /* ── MIGRATION TAB ─────────────────────────────────────────────── */
    private function render_migration_tab( $conn ) {
        if ( isset( $_POST['run_migration'] ) && check_admin_referer( 'hm_run_migration' ) ) {
            $results = $this->run_migration( $conn );
            echo '<h2>Migration Results</h2>';
            echo '<pre style="background:#1d2327;color:#fff;padding:16px;border-radius:6px;font-size:13px;max-height:500px;overflow:auto;">';
            foreach ( $results as $r ) {
                echo esc_html( $r ) . "\n";
            }
            echo '</pre>';
            echo '<h2>Verification</h2>';
            $this->verify_tables( $conn );
        } else {
            echo '<h2>Current State</h2>';
            $this->verify_tables( $conn );
            echo '<form method="post">';
            wp_nonce_field( 'hm_run_migration' );
            echo '<p>This will create/extend auth V2 tables in your <strong>Railway Postgres</strong>.</p>';
            echo '<p><strong>Safe to re-run</strong> — uses IF NOT EXISTS / ADD COLUMN IF NOT EXISTS.</p>';
            echo '<p><input type="submit" name="run_migration" class="button button-primary button-hero" value="Run Migration Now"></p>';
            echo '</form>';
        }
    }

    /* ── PASSWORDS TAB ─────────────────────────────────────────────── */
    private function render_passwords_tab( $conn ) {
        // Handle password set
        if ( isset( $_POST['set_password'] ) && check_admin_referer( 'hm_set_password' ) ) {
            $staff_id = (int) $_POST['staff_id'];
            $password = $_POST['new_password'] ?? '';
            if ( $staff_id > 0 && strlen( $password ) >= 10 ) {
                $hash = password_hash( $password, PASSWORD_BCRYPT, [ 'cost' => 12 ] );

                // Get email for username fallback
                $staff_r = @pg_query_params( $conn,
                    "SELECT email FROM hearmed_reference.staff WHERE id = $1", [ $staff_id ] );
                $staff_email = $staff_r ? pg_fetch_result( $staff_r, 0, 0 ) : 'staff' . $staff_id;

                // Upsert: insert if no staff_auth row, update if exists
                $ok = @pg_query_params( $conn,
                    "INSERT INTO hearmed_reference.staff_auth (staff_id, username, password_hash, temp_password, password_changed_at, created_at, updated_at)
                     VALUES ($1, $3, $2, true, NOW(), NOW(), NOW())
                     ON CONFLICT (staff_id) DO UPDATE
                     SET password_hash = $2, temp_password = true, password_changed_at = NOW(), updated_at = NOW()",
                    [ $staff_id, $hash, $staff_email ]
                );
                if ( $ok ) {
                    echo '<div class="notice notice-success"><p>✅ Password set for staff ID ' . $staff_id . ' (' . esc_html( $staff_email ) . '). They will be prompted to set up 2FA on first login.</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>❌ Failed: ' . esc_html( pg_last_error( $conn ) ) . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error"><p>❌ Password must be at least 10 characters.</p></div>';
            }
        }

        // List all staff with auth status
        $result = @pg_query( $conn,
            "SELECT s.id, s.first_name, s.last_name, s.email, s.role, s.is_active,
                    sa.username, sa.password_hash, sa.two_factor_enabled, sa.last_login
             FROM hearmed_reference.staff s
             LEFT JOIN hearmed_reference.staff_auth sa ON sa.staff_id = s.id
             ORDER BY s.first_name, s.last_name"
        );

        echo '<h2>Staff Auth Status</h2>';
        echo '<p>Set temporary passwords for staff before activating V2. They will set up 2FA on first login.</p>';
        echo '<table class="widefat striped" style="max-width:1000px;">';
        echo '<thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Username</th><th>Role</th><th>Has Password</th><th>2FA</th><th>Last Login</th><th>Set Password</th></tr></thead><tbody>';

        while ( $row = pg_fetch_object( $result ) ) {
            $has_pw = ! empty( $row->password_hash ) ? '✅' : '❌';
            $has_2fa = $row->two_factor_enabled === 't' ? '✅' : '❌';
            $last = $row->last_login ? date( 'd M Y', strtotime( $row->last_login ) ) : 'Never';
            $active = $row->is_active === 't' ? '' : ' <span style="color:red;">(Inactive)</span>';

            echo '<tr>';
            echo '<td>' . (int) $row->id . '</td>';
            echo '<td>' . esc_html( $row->first_name . ' ' . $row->last_name ) . $active . '</td>';
            echo '<td>' . esc_html( $row->email ) . '</td>';
            echo '<td>' . esc_html( $row->username ?? '—' ) . '</td>';
            echo '<td>' . esc_html( $row->role ) . '</td>';
            echo '<td style="text-align:center;">' . $has_pw . '</td>';
            echo '<td style="text-align:center;">' . $has_2fa . '</td>';
            echo '<td>' . esc_html( $last ) . '</td>';
            echo '<td>';
            echo '<form method="post" style="display:flex;gap:4px;align-items:center;">';
            wp_nonce_field( 'hm_set_password' );
            echo '<input type="hidden" name="staff_id" value="' . (int) $row->id . '">';
            echo '<input type="text" name="new_password" placeholder="Min 10 chars" style="width:140px;" minlength="10" required>';
            echo '<input type="submit" name="set_password" class="button button-small" value="Set">';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    /* ── ACTIVATE TAB ──────────────────────────────────────────────── */
    private function render_activate_tab( $conn, $v2_active ) {
        // Check readiness
        $tables_ok = true;
        $tables_to_check = [
            'hearmed_reference.staff_devices',
            'hearmed_reference.staff_sessions',
            'hearmed_reference.staff_invites',
            'hearmed_admin.auth_audit_log',
        ];
        foreach ( $tables_to_check as $table ) {
            $parts = explode( '.', $table );
            $check = @pg_query_params( $conn,
                "SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = $1 AND table_name = $2)",
                [ $parts[0], $parts[1] ]
            );
            if ( ! $check || pg_fetch_result( $check, 0, 0 ) !== 't' ) {
                $tables_ok = false;
                break;
            }
        }

        // Count staff with passwords
        $pw_check = @pg_query( $conn,
            "SELECT COUNT(*) AS total,
                    COUNT(CASE WHEN sa.password_hash IS NOT NULL AND sa.password_hash != '' THEN 1 END) AS has_pw
             FROM hearmed_reference.staff s
             LEFT JOIN hearmed_reference.staff_auth sa ON sa.staff_id = s.id
             WHERE s.is_active = true"
        );
        $pw_row = $pw_check ? pg_fetch_object( $pw_check ) : null;
        $total_staff = $pw_row ? (int) $pw_row->total : 0;
        $staff_with_pw = $pw_row ? (int) $pw_row->has_pw : 0;

        $totp_key_set = defined( 'HEARMED_TOTP_KEY' ) && strlen( HEARMED_TOTP_KEY ) === 64;

        echo '<h2>Activation Checklist</h2>';
        echo '<table class="widefat" style="max-width:600px;">';
        echo '<tr><td>Migration tables created</td><td>' . ( $tables_ok ? '✅' : '❌ <a href="?page=hm-run-migration&tab=migration">Run migration</a>' ) . '</td></tr>';
        echo '<tr><td>HEARMED_TOTP_KEY defined (64-char hex)</td><td>' . ( $totp_key_set ? '✅' : '❌ Add to wp-config.php' ) . '</td></tr>';
        echo '<tr><td>Staff with passwords</td><td>' . $staff_with_pw . ' / ' . $total_staff . ( $staff_with_pw === 0 ? ' ❌ <a href="?page=hm-run-migration&tab=passwords">Set passwords</a>' : ( $staff_with_pw < $total_staff ? ' ⚠️' : ' ✅' ) ) . '</td></tr>';
        echo '<tr><td>PORTAL_AUTH_V2 in wp-config.php</td><td>' . ( $v2_active ? '✅ Active' : '❌ Not set' ) . '</td></tr>';
        echo '<tr><td>Login page exists at /login/</td><td>Check: <a href="' . esc_url( home_url( '/login/' ) ) . '" target="_blank">/login/</a></td></tr>';
        echo '</table>';

        if ( $tables_ok && $totp_key_set && $staff_with_pw > 0 && ! $v2_active ) {
            echo '<div class="notice notice-info" style="margin-top:16px;"><p><strong>Ready to activate!</strong> Add this to wp-config.php above the "stop editing" line:</p>';
            echo '<pre style="background:#1d2327;color:#0f0;padding:12px;border-radius:4px;">define( \'PORTAL_AUTH_V2\', true );</pre>';
            echo '<p>After saving, all portal auth will use the new system. WP admin remains accessible for super-admins.</p></div>';
        } elseif ( $v2_active ) {
            echo '<div class="notice notice-success" style="margin-top:16px;"><p><strong>Auth V2 is live!</strong> Staff should log in at <a href="' . esc_url( home_url( '/login/' ) ) . '">/login/</a>.</p></div>';
        }
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
