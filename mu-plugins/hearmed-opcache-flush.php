<?php
/**
 * HearMed — OPcache Auto-Flush on Deploy
 *
 * Must-use plugin. After a deploy, the GitHub Actions workflow touches
 * a flag file (.opcache-flush). On the next web request WordPress
 * processes, this mu-plugin detects the flag, runs opcache_reset()
 * inside the web SAPI (where it actually matters), and deletes the flag.
 *
 * This solves the SiteGround 403 block on direct plugin PHP access
 * that was preventing the old opcache-flush.php from working.
 *
 * @package HearMed_Portal
 * @since   5.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'muplugins_loaded', function() {
    $flag = WP_CONTENT_DIR . '/plugins/hearmed-calendar/.opcache-flush';

    if ( ! file_exists( $flag ) ) return;

    // Remove the flag first to prevent repeated flushes
    @unlink( $flag );

    if ( function_exists( 'opcache_reset' ) ) {
        opcache_reset();
        error_log( '[HearMed] OPcache flushed via deploy flag.' );
    }
} );
