<?php
/**
 * HearMed Runtime Debug Logger
 *
 * Writes runtime diagnostics to a plugin-local log so production issues can be
 * inspected from the repository workspace without accessing wp-content/debug.log.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HearMed_Runtime_Debug {
    /** @var bool */
    private static $booted = false;

    /** @var string|null */
    private static $log_file = null;

    /** @var array<int,string> */
    private static $levels = [
        E_ERROR             => 'E_ERROR',
        E_WARNING           => 'E_WARNING',
        E_PARSE             => 'E_PARSE',
        E_NOTICE            => 'E_NOTICE',
        E_CORE_ERROR        => 'E_CORE_ERROR',
        E_CORE_WARNING      => 'E_CORE_WARNING',
        E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
        E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
        E_USER_ERROR        => 'E_USER_ERROR',
        E_USER_WARNING      => 'E_USER_WARNING',
        E_USER_NOTICE       => 'E_USER_NOTICE',
        E_STRICT            => 'E_STRICT',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_DEPRECATED        => 'E_DEPRECATED',
        E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
    ];

    /**
     * Bootstrap runtime logging once per request.
     */
    public static function bootstrap() {
        if ( self::$booted ) {
            return;
        }
        self::$booted = true;

        if ( ! self::is_enabled() ) {
            return;
        }

        self::$log_file = self::resolve_log_path();
        if ( ! self::$log_file ) {
            return;
        }

        self::rotate_if_oversize();

        // Direct PHP runtime logging to plugin-local file for this request.
        @ini_set( 'log_errors', '1' );
        @ini_set( 'error_log', self::$log_file );

        set_error_handler( [ __CLASS__, 'handle_php_error' ] );
        register_shutdown_function( [ __CLASS__, 'handle_shutdown' ] );

        set_exception_handler( static function( $e ) {
            if ( $e instanceof Throwable ) {
                self::write( 'UNCAUGHT_EXCEPTION', [
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                    'trace'   => $e->getTraceAsString(),
                    'uri'     => $_SERVER['REQUEST_URI'] ?? '',
                ] );
            }
        } );

        // WordPress diagnostics hooks.
        add_action( 'doing_it_wrong_run', [ __CLASS__, 'handle_doing_it_wrong' ], 10, 3 );
        add_action( 'deprecated_function_run', [ __CLASS__, 'handle_deprecated_function' ], 10, 3 );
        add_action( 'deprecated_argument_run', [ __CLASS__, 'handle_deprecated_argument' ], 10, 3 );
        add_action( 'deprecated_hook_run', [ __CLASS__, 'handle_deprecated_hook' ], 10, 4 );

        self::write( 'RUNTIME_DEBUG_BOOT', [
            'uri'   => $_SERVER['REQUEST_URI'] ?? '',
            'sapi'  => php_sapi_name(),
            'wp'    => function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : '',
            'php'   => PHP_VERSION,
        ] );
    }

    /**
     * Toggle logger via filter. Defaults to enabled.
     *
     * @return bool
     */
    private static function is_enabled() {
        return (bool) apply_filters( 'hm_runtime_debug_enabled', true );
    }

    /**
     * Build/writeable log path.
     *
     * @return string|null
     */
    private static function resolve_log_path() {
        $dir = trailingslashit( HEARMED_PATH ) . 'var';
        if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
            return null;
        }

        $log_path = $dir . '/hearmed-runtime.log';
        if ( ! file_exists( $log_path ) ) {
            @file_put_contents( $log_path, '' );
        }

        if ( ! is_writable( $log_path ) ) {
            return null;
        }

        return $log_path;
    }

    /**
     * Rotate if log exceeds size limit.
     */
    private static function rotate_if_oversize() {
        if ( ! self::$log_file || ! file_exists( self::$log_file ) ) {
            return;
        }

        $max_bytes = 8 * 1024 * 1024; // 8MB
        $size = @filesize( self::$log_file );
        if ( ! is_int( $size ) || $size < $max_bytes ) {
            return;
        }

        $archive = self::$log_file . '.1';
        @unlink( $archive );
        @rename( self::$log_file, $archive );
        @file_put_contents( self::$log_file, '' );
    }

    /**
     * PHP error handler callback.
     */
    public static function handle_php_error( $errno, $errstr, $errfile, $errline ) {
        // Respect current error_reporting settings.
        if ( ! ( error_reporting() & $errno ) ) {
            return false;
        }

        self::write( 'PHP_ERROR', [
            'level'   => self::$levels[ $errno ] ?? (string) $errno,
            'message' => (string) $errstr,
            'file'    => (string) $errfile,
            'line'    => (int) $errline,
            'uri'     => $_SERVER['REQUEST_URI'] ?? '',
        ] );

        // Return false so default PHP/WordPress handlers still run too.
        return false;
    }

    /**
     * Shutdown callback for fatal errors.
     */
    public static function handle_shutdown() {
        $error = error_get_last();
        if ( ! $error ) {
            return;
        }

        $fatal_types = [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ];
        if ( ! in_array( (int) $error['type'], $fatal_types, true ) ) {
            return;
        }

        self::write( 'PHP_FATAL', [
            'level'   => self::$levels[ (int) $error['type'] ] ?? (string) $error['type'],
            'message' => (string) $error['message'],
            'file'    => (string) $error['file'],
            'line'    => (int) $error['line'],
            'uri'     => $_SERVER['REQUEST_URI'] ?? '',
        ] );
    }

    /**
     * WP doing_it_wrong hook callback.
     */
    public static function handle_doing_it_wrong( $function_name, $message, $version ) {
        self::write( 'WP_DOING_IT_WRONG', [
            'function' => (string) $function_name,
            'message'  => (string) $message,
            'version'  => (string) $version,
            'uri'      => $_SERVER['REQUEST_URI'] ?? '',
        ] );
    }

    /**
     * WP deprecated function callback.
     */
    public static function handle_deprecated_function( $function_name, $replacement, $version ) {
        self::write( 'WP_DEPRECATED_FUNCTION', [
            'function'    => (string) $function_name,
            'replacement' => (string) $replacement,
            'version'     => (string) $version,
            'uri'         => $_SERVER['REQUEST_URI'] ?? '',
        ] );
    }

    /**
     * WP deprecated argument callback.
     */
    public static function handle_deprecated_argument( $function_name, $message, $version ) {
        self::write( 'WP_DEPRECATED_ARGUMENT', [
            'function' => (string) $function_name,
            'message'  => (string) $message,
            'version'  => (string) $version,
            'uri'      => $_SERVER['REQUEST_URI'] ?? '',
        ] );
    }

    /**
     * WP deprecated hook callback.
     */
    public static function handle_deprecated_hook( $hook_name, $replacement, $version, $message ) {
        self::write( 'WP_DEPRECATED_HOOK', [
            'hook'        => (string) $hook_name,
            'replacement' => (string) $replacement,
            'version'     => (string) $version,
            'message'     => (string) $message,
            'uri'         => $_SERVER['REQUEST_URI'] ?? '',
        ] );
    }

    /**
     * Structured line write.
     *
     * @param string $type
     * @param array  $context
     */
    private static function write( $type, $context ) {
        if ( ! self::$log_file ) {
            return;
        }

        $line = sprintf(
            "[%s] [%s] %s\n",
            gmdate( 'Y-m-d H:i:s' ),
            $type,
            wp_json_encode( $context, JSON_UNESCAPED_SLASHES )
        );

        @file_put_contents( self::$log_file, $line, FILE_APPEND | LOCK_EX );
    }
}
