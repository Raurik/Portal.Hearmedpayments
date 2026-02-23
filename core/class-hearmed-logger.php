<?php
/**
 * HearMed Logger
 * Lightweight structured logging for portal debugging.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HearMed_Logger {

    /**
     * Get log file path in uploads/hearmed-logs/portal.log
     *
     * @return string|null
     */
    private static function get_log_file() {
        if ( ! function_exists( 'wp_upload_dir' ) ) {
            return null;
        }

        $uploads = wp_upload_dir();
        if ( ! empty( $uploads['error'] ) ) {
            return null;
        }

        $dir = trailingslashit( $uploads['basedir'] ) . 'hearmed-logs/';

        if ( ! is_dir( $dir ) ) {
            // Silently fail if we cannot create the directory.
            if ( ! @wp_mkdir_p( $dir ) ) {
                return null;
            }
        }

        return $dir . 'portal.log';
    }

    /**
     * Write a log entry.
     *
     * @param string $level
     * @param string $message
     * @param array  $context
     */
    public static function log( $level, $message, $context = [] ) {
        $file = self::get_log_file();
        if ( ! $file ) {
            // Fallback to PHP error_log if uploads not writable
            error_log( sprintf( '[HearMed %s] %s %s', strtoupper( $level ), $message, $context ? json_encode( $context ) : '' ) );
            return;
        }

        $entry = [
            'time'    => gmdate( 'c' ),
            'level'   => strtoupper( $level ),
            'message' => $message,
            'context' => $context,
        ];

        $line = wp_json_encode( $entry ) . PHP_EOL;
        @file_put_contents( $file, $line, FILE_APPEND | LOCK_EX );
    }

    public static function info( $message, $context = [] ) {
        self::log( 'info', $message, $context );
    }

    public static function warning( $message, $context = [] ) {
        self::log( 'warning', $message, $context );
    }

    public static function error( $message, $context = [] ) {
        self::log( 'error', $message, $context );
    }
}
