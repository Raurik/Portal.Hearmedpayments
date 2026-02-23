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

// Force HTTPS/correct domain on assets to prevent mixed content errors.
// NOTE: Only applied to scripts/styles/uploads — NOT home_url or site_url
// (those filters corrupt internal navigation links and cause URL doubling).
if ( ! is_admin() ) {
    add_filter( 'wp_get_attachment_url', 'hearmed_force_https_url', 10, 2 );
    add_filter( 'upload_dir', 'hearmed_force_https_upload_dir' );
    add_filter( 'script_loader_src', 'hearmed_force_https_url', 10, 2 );
    add_filter( 'style_loader_src', 'hearmed_force_https_url', 10, 2 );
    add_filter( 'elementor/frontend/builder_content_data', 'hearmed_force_https_elementor_content', 10, 2 );
    add_filter( 'elementor/frontend/the_content', 'hearmed_fix_elementor_output', 999 );
}

function hearmed_force_https_url( $url ) {
    if ( is_string( $url ) ) {
        // First, replace any old domain references
        $url = str_replace( 'hear-med-portal.shop', 'portal.hearmedpayments.net', $url );
        $url = str_replace( 'http://portal.hearmedpayments.net', 'portal.hearmedpayments.net', $url );
        // Then ensure HTTPS
        if ( strpos( $url, 'http://' ) === 0 ) {
            $url = str_replace( 'http://', 'https://', $url );
        }
        // Ensure HTTPS protocol if not present
        if ( strpos( $url, '//' ) === 0 ) {
            $url = 'https:' . $url;
        }
    }
    return $url;
}

function hearmed_fix_elementor_output( $content ) {
    if ( is_string( $content ) ) {
        // Replace old domain
        $content = str_replace( 'hear-med-portal.shop', 'portal.hearmedpayments.net', $content );
        $content = str_replace( 'http://portal.hearmedpayments.net', 'https://portal.hearmedpayments.net', $content );
        // Fix protocol-relative URLs
        $content = str_replace( '//portal.hearmedpayments.net', 'https://portal.hearmedpayments.net', $content );
    }
    return $content;
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
        // Fix old domain
        $data = str_replace( 'hear-med-portal.shop', 'portal.hearmedpayments.net', $data );
        // Fix HTTP
        $data = str_replace( 'http://', 'https://', $data );
    } elseif ( is_array( $data ) ) {
        array_walk_recursive( $data, function( &$item ) {
            if ( is_string( $item ) ) {
                // Fix old domain
                $item = str_replace( 'hear-med-portal.shop', 'portal.hearmedpayments.net', $item );
                // Fix HTTP
                if ( strpos( $item, 'http://' ) === 0 ) {
                    $item = str_replace( 'http://', 'https://', $item );
                }
            }
        });
    }
    return $data;
}

// Add filter to fix font CSS output
add_filter( 'wp_head', function() {
    ?><style type="text/css">
        /* Force correct domain for fonts */
        @font-face {
            font-display: swap;
        }
    </style><?php
}, 1 );

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
