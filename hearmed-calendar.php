<?php
/**
 * Plugin Name: HearMed Portal
 * Plugin URI:  https://portal.hearmedpayments.net
 * Description: HearMed patient and staff portal - PostgreSQL Migration v5.0
 * Version:     5.0.0
 * Author:      HearMed
 * Text Domain: hearmed-portal
 * 
 * ============================================================
 * POSTGRESQL MIGRATION v5.0
 * ============================================================
 * - WordPress = UI + Auth ONLY
 * - PostgreSQL = ALL Business Data
 * - NO Custom Post Types for business data
 * - All modules converted to HearMed_DB
 * ============================================================
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants — version is read from the plugin header to avoid duplication
if ( ! defined( 'HEARMED_VERSION' ) ) {
    $hm_data = get_file_data( __FILE__, [ 'Version' => 'Version' ] );
    define( 'HEARMED_VERSION', $hm_data['Version'] );
    unset( $hm_data );
}

if ( ! defined( 'HEARMED_PATH' ) ) {
    define( 'HEARMED_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'HEARMED_URL' ) ) {
    define( 'HEARMED_URL', plugin_dir_url( __FILE__ ) );
}

// Fix references to old domain (hear-med-portal.shop) that may be cached in
// Elementor or the database. Does NOT touch protocol or URL structure at all.
add_filter( 'script_loader_src', 'hearmed_fix_old_domain', 10, 1 );
add_filter( 'style_loader_src',  'hearmed_fix_old_domain', 10, 1 );

function hearmed_fix_old_domain( $url ) {
    if ( ! is_string( $url ) ) {
        return $url;
    }

    // Swap any leftover references to the old domain
    $url = str_replace( 'hear-med-portal.shop', 'portal.hearmedpayments.net', $url );

    // Specifically fix Elementor local Google Fonts that are still hard-coded as http://
    $fonts_prefix = 'http://portal.hearmedpayments.net/wp-content/uploads/elementor/google-fonts/';
    if ( strpos( $url, $fonts_prefix ) === 0 ) {
        $url = 'https://' . substr( $url, strlen( 'http://' ) );
    }

    return $url;
}

// Load core helpers and PostgreSQL connection classes
require_once HEARMED_PATH . 'core/class-hearmed-logger.php';
require_once HEARMED_PATH . 'core/class-hearmed-pg.php';
require_once HEARMED_PATH . 'core/class-hearmed-db.php';  // Main database abstraction

// Add AJAX handlers
require_once HEARMED_PATH . 'includes/ajax-handlers.php';

// Load admin-only pages (menus, enqueues, AJAX handlers for admin tools)
if ( is_admin() ) {
    require_once HEARMED_PATH . 'admin/admin-debug.php';
}

// Log any fatal PHP errors on shutdown to help diagnose white screens.
add_action( 'shutdown', function() {
    if ( ! class_exists( 'HearMed_Logger' ) ) {
        return;
    }

    $error = error_get_last();
    if ( ! $error ) {
        return;
    }

    $fatal_types = [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ];
    if ( in_array( $error['type'], $fatal_types, true ) ) {
        HearMed_Logger::error( 'Fatal PHP error on shutdown', [
            'type'    => $error['type'],
            'message' => $error['message'],
            'file'    => $error['file'],
            'line'    => $error['line'],
            'uri'     => $_SERVER['REQUEST_URI'] ?? '',
        ] );
    }
} );

// INITIALIZE PLUGIN
function hearmed_initialize_plugin() {
    require_once HEARMED_PATH . 'core/class-hearmed-core.php';
    HearMed_Core::instance();
}
add_action( 'plugins_loaded', 'hearmed_initialize_plugin' );
