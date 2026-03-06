<?php
/**
 * HearMed Runtime Log Viewer
 *
 * WP Admin -> Tools -> HearMed Runtime Log
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_menu', function () {
    add_submenu_page(
        'tools.php',
        'HearMed Runtime Log',
        'HearMed Runtime Log',
        'manage_options',
        'hearmed-runtime-log',
        'hm_runtime_log_render_page'
    );
} );

/**
 * Access check for runtime log page.
 *
 * @return bool
 */
function hm_runtime_log_user_is_allowed() {
    $user = wp_get_current_user();
    $admin_roles = [ 'administrator', 'hm_clevel', 'hm_admin', 'hm_finance' ];
    return ! empty( array_intersect( $admin_roles, (array) $user->roles ) );
}

/**
 * Get runtime log file path.
 *
 * @return string
 */
function hm_runtime_log_path() {
    return trailingslashit( HEARMED_PATH ) . 'var/hearmed-runtime.log';
}

/**
 * Return the last N lines from a file.
 *
 * @param string $path
 * @param int    $max_lines
 * @return array<int,string>
 */
function hm_runtime_log_tail_lines( $path, $max_lines = 800 ) {
    if ( ! file_exists( $path ) ) {
        return [];
    }

    $lines = @file( $path, FILE_IGNORE_NEW_LINES );
    if ( ! is_array( $lines ) ) {
        return [];
    }

    if ( count( $lines ) <= $max_lines ) {
        return $lines;
    }

    return array_slice( $lines, -1 * $max_lines );
}

/**
 * Handle runtime log file download.
 */
function hm_runtime_log_maybe_download() {
    if ( ! is_admin() ) {
        return;
    }

    if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'hearmed-runtime-log' ) {
        return;
    }

    if ( ! isset( $_GET['hm_runtime_download'] ) ) {
        return;
    }

    if ( ! hm_runtime_log_user_is_allowed() ) {
        wp_die( 'Access denied', 403 );
    }

    check_admin_referer( 'hm_runtime_log_actions', 'hm_runtime_nonce' );

    $path = hm_runtime_log_path();
    if ( ! file_exists( $path ) ) {
        wp_die( 'Runtime log not found.' );
    }

    nocache_headers();
    header( 'Content-Type: text/plain; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="hearmed-runtime.log"' );
    readfile( $path );
    exit;
}
add_action( 'admin_init', 'hm_runtime_log_maybe_download' );

/**
 * Render Tools -> HearMed Runtime Log.
 */
function hm_runtime_log_render_page() {
    if ( ! hm_runtime_log_user_is_allowed() ) {
        wp_die( '<p>You do not have permission to access this page.</p>', 403 );
    }

    $path = hm_runtime_log_path();

    if ( isset( $_POST['hm_runtime_clear'] ) ) {
        check_admin_referer( 'hm_runtime_log_actions', 'hm_runtime_nonce' );
        @file_put_contents( $path, '' );
        echo '<div class="notice notice-success"><p>Runtime log cleared.</p></div>';
    }

    $filter = sanitize_text_field( $_GET['hm_type'] ?? '' );
    $lines = hm_runtime_log_tail_lines( $path, 1000 );

    if ( $filter !== '' ) {
        $tag = '[' . strtoupper( $filter ) . ']';
        $lines = array_values( array_filter( $lines, static function( $line ) use ( $tag ) {
            return strpos( (string) $line, $tag ) !== false;
        } ) );
    }

    $size_bytes = file_exists( $path ) ? (int) @filesize( $path ) : 0;
    $size_kb = $size_bytes > 0 ? round( $size_bytes / 1024, 2 ) : 0;
    $types = [
        'PHP_ERROR', 'PHP_FATAL', 'UNCAUGHT_EXCEPTION',
        'WP_DOING_IT_WRONG', 'WP_DEPRECATED_FUNCTION', 'WP_DEPRECATED_ARGUMENT', 'WP_DEPRECATED_HOOK',
    ];

    ?>
    <div class="wrap">
        <h1>HearMed Runtime Log</h1>
        <p><code><?php echo esc_html( $path ); ?></code></p>
        <p>
            Size: <strong><?php echo esc_html( number_format_i18n( $size_kb ) ); ?> KB</strong>
            | Showing: <strong><?php echo esc_html( count( $lines ) ); ?></strong> line(s)
        </p>

        <form method="get" style="margin:12px 0 14px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <input type="hidden" name="page" value="hearmed-runtime-log" />
            <label for="hm_type"><strong>Filter type:</strong></label>
            <select name="hm_type" id="hm_type">
                <option value="">All</option>
                <?php foreach ( $types as $t ) : ?>
                    <option value="<?php echo esc_attr( $t ); ?>" <?php selected( strtoupper( $filter ), $t ); ?>><?php echo esc_html( $t ); ?></option>
                <?php endforeach; ?>
            </select>
            <button class="button button-primary" type="submit">Apply</button>
            <a class="button" href="<?php echo esc_url( admin_url( 'tools.php?page=hearmed-runtime-log' ) ); ?>">Reset</a>
        </form>

        <form method="post" style="display:flex;gap:8px;align-items:center;margin-bottom:14px;">
            <?php wp_nonce_field( 'hm_runtime_log_actions', 'hm_runtime_nonce' ); ?>
            <button class="button" type="submit" name="hm_runtime_clear" value="1" onclick="return confirm('Clear runtime log?');">Clear Log</button>
            <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'tools.php?page=hearmed-runtime-log&hm_runtime_download=1' ), 'hm_runtime_log_actions', 'hm_runtime_nonce' ) ); ?>">Download Log</a>
        </form>

        <textarea readonly style="width:100%;min-height:70vh;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;line-height:1.45;"><?php echo esc_textarea( implode( "\n", $lines ) ); ?></textarea>
    </div>
    <?php
}
