<?php
/**
 * Temporary auth debug — DELETE AFTER DEBUGGING
 * Visit: https://portal.hearmedpayments.net/wp-admin/admin-ajax.php?action=hm_auth_debug
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_ajax_hm_auth_debug', 'hm_auth_debug_output' );
add_action( 'wp_ajax_nopriv_hm_auth_debug', 'hm_auth_debug_output' );

function hm_auth_debug_output() {
    header( 'Content-Type: text/plain; charset=utf-8' );
    header( 'Cache-Control: no-cache, no-store, must-revalidate' );

echo "=== HearMed Auth Debug ===\n\n";

// 1. Feature flag
echo "1. PORTAL_AUTH_V2 defined: " . ( defined('PORTAL_AUTH_V2') ? 'YES' : 'NO' ) . "\n";
echo "   PORTAL_AUTH_V2 value: " . ( defined('PORTAL_AUTH_V2') ? var_export(PORTAL_AUTH_V2, true) : 'N/A' ) . "\n";
echo "   PortalAuth class exists: " . ( class_exists('PortalAuth') ? 'YES' : 'NO' ) . "\n";
echo "   PortalAuth::is_v2(): " . ( class_exists('PortalAuth') ? var_export(PortalAuth::is_v2(), true) : 'N/A' ) . "\n\n";

// 2. Cookie
echo "2. HMSESS cookie present: " . ( isset($_COOKIE['HMSESS']) ? 'YES' : 'NO' ) . "\n";
if ( isset($_COOKIE['HMSESS']) ) {
    $token = $_COOKIE['HMSESS'];
    echo "   Token length: " . strlen($token) . "\n";
    echo "   Token prefix: " . substr($token, 0, 12) . "...\n";
    $hash = hash('sha256', $token);
    echo "   SHA-256 hash prefix: " . substr($hash, 0, 12) . "...\n";
} else {
    echo "   All cookies: " . implode(', ', array_keys($_COOKIE)) . "\n";
}
echo "\n";

// 3. PG connection
echo "3. PG constants defined:\n";
echo "   HEARMED_PG_HOST: " . ( defined('HEARMED_PG_HOST') ? HEARMED_PG_HOST : 'NOT DEFINED' ) . "\n";
echo "   HEARMED_PG_PORT: " . ( defined('HEARMED_PG_PORT') ? HEARMED_PG_PORT : 'NOT DEFINED' ) . "\n";
echo "   HEARMED_PG_DB:   " . ( defined('HEARMED_PG_DB') ? HEARMED_PG_DB : 'NOT DEFINED' ) . "\n";
echo "   HEARMED_PG_USER: " . ( defined('HEARMED_PG_USER') ? 'SET' : 'NOT DEFINED' ) . "\n";
echo "   HEARMED_PG_PASS: " . ( defined('HEARMED_PG_PASS') ? 'SET' : 'NOT DEFINED' ) . "\n\n";

// 4. Try PG connection
echo "4. PG connection test:\n";
try {
    $conn_str = sprintf(
        "host=%s port=%s dbname=%s user=%s password=%s sslmode=require",
        defined('HEARMED_PG_HOST') ? HEARMED_PG_HOST : '',
        defined('HEARMED_PG_PORT') ? HEARMED_PG_PORT : '',
        defined('HEARMED_PG_DB')   ? HEARMED_PG_DB   : '',
        defined('HEARMED_PG_USER') ? HEARMED_PG_USER : '',
        defined('HEARMED_PG_PASS') ? HEARMED_PG_PASS : ''
    );
    $conn = @pg_connect($conn_str);
    echo "   Connected: " . ($conn ? 'YES' : 'NO — ' . pg_last_error()) . "\n";
    
    if ($conn && isset($_COOKIE['HMSESS'])) {
        $hash = hash('sha256', $_COOKIE['HMSESS']);
        $result = pg_query_params($conn,
            "SELECT ss.staff_id, ss.expires_at, ss.revoked_at, s.first_name, s.last_name, s.email, s.role, s.is_active
             FROM hearmed_reference.staff_sessions ss
             JOIN hearmed_reference.staff s ON s.id = ss.staff_id
             WHERE ss.session_token_hash = $1",
            [$hash]
        );
        $row = $result ? pg_fetch_assoc($result) : null;
        echo "\n5. Session lookup (without expiry/revoke filter):\n";
        if ($row) {
            foreach ($row as $k => $v) {
                echo "   $k: $v\n";
            }
        } else {
            echo "   NO ROW FOUND for this token hash\n";
        }
        
        // Also check total sessions
        $count = pg_fetch_assoc(pg_query($conn, "SELECT count(*) FROM hearmed_reference.staff_sessions"));
        echo "\n   Total sessions in DB: " . $count['count'] . "\n";
        
        // Check active sessions
        $active = pg_fetch_assoc(pg_query($conn, "SELECT count(*) FROM hearmed_reference.staff_sessions WHERE revoked_at IS NULL AND expires_at > NOW()"));
        echo "   Active sessions: " . $active['count'] . "\n";
    }
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

echo "\n6. PortalAuth::is_logged_in(): ";
if (class_exists('PortalAuth')) {
    // Reset resolved state so we get a fresh check
    $ref = new ReflectionClass('PortalAuth');
    $prop = $ref->getProperty('resolved');
    $prop->setAccessible(true);
    $prop->setValue(null, false);
    $prop2 = $ref->getProperty('current_staff');
    $prop2->setAccessible(true);
    $prop2->setValue(null, null);
    
    echo var_export(PortalAuth::is_logged_in(), true) . "\n";
    $user = PortalAuth::current_user();
    if ($user) {
        echo "   Staff: " . $user->first_name . ' ' . $user->last_name . " (ID: " . $user->id . ", role: " . $user->role . ")\n";
    }
} else {
    echo "PortalAuth class not loaded\n";
}

echo "\n7. WP auth: is_user_logged_in() = " . var_export(is_user_logged_in(), true) . "\n";
if (is_user_logged_in()) {
    $wp_user = wp_get_current_user();
    echo "   WP User: " . $wp_user->user_login . " (ID: " . $wp_user->ID . ")\n";
}

echo "\n=== END DEBUG ===\n";
    die();
}