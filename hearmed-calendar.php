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

// Load PostgreSQL connection classes
require_once HEARMED_PATH . 'core/class-hearmed-pg.php';
require_once HEARMED_PATH . 'core/class-hearmed-db.php';  // Main database abstraction

// Add AJAX handlers
require_once HEARMED_PATH . 'includes/ajax-handlers.php';

// Load admin-only pages (menus, enqueues, AJAX handlers for admin tools)
if ( is_admin() ) {
    require_once HEARMED_PATH . 'admin/admin-debug.php';
}

// AUTO-ACTIVATE HEARMED THEME
function hearmed_auto_activate_theme() {
    $theme_name = 'hearmed-theme';
    $current_theme = wp_get_theme();
    
    // If hearmed-theme exists but not active, activate it
    if ( $current_theme->get( 'Name' ) !== 'HearMed Portal' && file_exists( get_theme_root() . '/' . $theme_name ) ) {
        switch_theme( $theme_name );
    }
}
add_action( 'admin_init', 'hearmed_auto_activate_theme' );

// INITIALIZE PLUGIN
function hearmed_initialize_plugin() {
    require_once HEARMED_PATH . 'core/class-hearmed-core.php';
    HearMed_Core::instance();
}
add_action( 'plugins_loaded', 'hearmed_initialize_plugin' );
