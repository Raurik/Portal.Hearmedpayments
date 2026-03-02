<?php
/**
 * HearMed — Disable WP Login Routes
 *
 * Must-use plugin. Blocks direct access to wp-login.php and wp-admin
 * for non-super-admin users. All authentication is handled by the
 * HearMed portal at /login/.
 *
 * Deploy: Copy this file to wp-content/mu-plugins/disable-wp-login.php
 *
 * Feature flag: Only active when PORTAL_AUTH_V2 is true.
 *
 * @package HearMed_Portal
 * @since   5.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Gate: only enforce when V2 auth is on.
 */
if ( ! defined( 'PORTAL_AUTH_V2' ) || ! PORTAL_AUTH_V2 ) return;

/**
 * Block wp-login.php and xmlrpc.php direct access.
 */
add_action( 'login_init', function() {
    // Allow logout action through so WP session is properly torn down
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'logout' ) return;

    // Allow AJAX/Cron/CLI
    if ( defined( 'DOING_AJAX' ) || defined( 'DOING_CRON' ) || defined( 'WP_CLI' ) ) return;

    // Redirect to portal login
    wp_redirect( home_url( '/login/' ) );
    exit;
});

/**
 * Block XML-RPC completely.
 */
add_filter( 'xmlrpc_enabled', '__return_false' );

/**
 * Redirect wp-admin for non-logged-in users to portal login.
 */
add_action( 'admin_init', function() {
    // Allow AJAX calls
    if ( defined( 'DOING_AJAX' ) || defined( 'DOING_CRON' ) || defined( 'WP_CLI' ) ) return;

    // Allow WP super admins to still access wp-admin
    if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) return;

    // Non-admin or not logged in — redirect to portal
    wp_redirect( home_url( '/login/' ) );
    exit;
});

/**
 * Remove the admin bar for portal users (non-super-admins).
 */
add_action( 'after_setup_theme', function() {
    if ( ! is_user_logged_in() ) return;
    if ( ! current_user_can( 'manage_options' ) ) {
        show_admin_bar( false );
    }
});

/**
 * Hide WP login errors — don't leak user existence.
 */
add_filter( 'login_errors', function() {
    return 'Invalid login details.';
});

/**
 * Block user enumeration via REST API.
 */
add_filter( 'rest_endpoints', function( $endpoints ) {
    if ( isset( $endpoints['/wp/v2/users'] ) )     unset( $endpoints['/wp/v2/users'] );
    if ( isset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] ) ) unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
    return $endpoints;
});

/**
 * Security headers for all responses.
 */
add_action( 'send_headers', function() {
    if ( headers_sent() ) return;
    header( 'X-Frame-Options: SAMEORIGIN' );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'Referrer-Policy: strict-origin-when-cross-origin' );
    header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()' );
});
