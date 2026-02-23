<?php
/**
 * HearMed Portal Theme Functions
 * 
 * Minimal theme setup for HearMed Portal WordPress integration.
 * This theme is designed to work with:
 * - Elementor (page builder)
 * - HearMed Portal Plugin (business logic)
 * 
 * @package HearMed_Portal
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Set up theme features
 */
function hearmed_theme_setup() {
    // Support Elementor
    add_theme_support( 'post-formats' );
    add_theme_support( 'custom-logo' );
    add_theme_support( 'title-tag' );
    
    // Register menus (even though Elementor handles nav)
    register_nav_menus( [
        'primary' => 'Primary Menu',
        'footer'  => 'Footer Menu',
    ] );
}
add_action( 'after_setup_theme', 'hearmed_theme_setup' );

/**
 * Enqueue theme styles
 */
function hearmed_theme_enqueue_styles() {
    wp_enqueue_style( 
        'hearmed-theme-style',
        get_stylesheet_uri(),
        [],
        wp_get_theme()->get( 'Version' )
    );
}
add_action( 'wp_enqueue_scripts', 'hearmed_theme_enqueue_styles' );

/**
 * Disable theme auto-updates if you prefer manual control
 */
// remove_action( 'load-themes.php', 'wp_auto_update_themes' );
