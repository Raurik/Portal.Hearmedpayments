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

// Force HTTPS on all WordPress URLs to prevent mixed content errors
if ( ! is_admin() && isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ) {
    // Force HTTPS for home and site URL
    add_filter( 'home_url', 'hearmed_force_https_url', 10, 4 );
    add_filter( 'site_url', 'hearmed_force_https_url', 10, 4 );
    add_filter( 'wp_get_attachment_url', 'hearmed_force_https_url', 10, 2 );
    add_filter( 'upload_dir', 'hearmed_force_https_upload_dir' );
    add_filter( 'script_loader_src', 'hearmed_force_https_url', 10, 2 );
    add_filter( 'style_loader_src', 'hearmed_force_https_url', 10, 2 );
    
    // Force Elementor to use HTTPS
    add_filter( 'elementor/frontend/builder_content_data', 'hearmed_force_https_elementor_content', 10, 2 );
}

function hearmed_force_https_url( $url ) {
    if ( is_string( $url ) && strpos( $url, 'http://' ) === 0 ) {
        $url = str_replace( 'http://', 'https://', $url );
    }
    return $url;
}

function hearmed_force_https_upload_dir( $uploads ) {
    if ( isset( $uploads['url'] ) ) {
        $uploads['url'] = hearmed_force_https_url( $uploads['url'] );
    }
    if ( isset( $uploads['baseurl'] ) ) {
        $uploads['baseurl'] = hearmed_force_https_url( $uploads['baseurl'] );
    }
    return $uploads;
}

function hearmed_force_https_elementor_content( $data, $post_id ) {
    if ( is_string( $data ) ) {
        $data = str_replace( 'http://', 'https://', $data );
    } elseif ( is_array( $data ) ) {
        array_walk_recursive( $data, function( &$item ) {
            if ( is_string( $item ) && strpos( $item, 'http://' ) === 0 ) {
                $item = str_replace( 'http://', 'https://', $item );
            }
        });
    }
    return $data;
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

// REMOVE THEME FOOTER
remove_action( 'wp_footer', 'wp_footer' );

// INITIALIZE PLUGIN
function hearmed_initialize_plugin() {
    require_once HEARMED_PATH . 'core/class-hearmed-core.php';
    HearMed_Core::instance();
}
add_action( 'plugins_loaded', 'hearmed_initialize_plugin' );
