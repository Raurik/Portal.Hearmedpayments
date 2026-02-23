<?php

// ============================================================
// AUTO-CONVERTED TO POSTGRESQL
// ============================================================
// All database operations converted from WordPress to PostgreSQL
// - $wpdb → HearMed_DB
// - wp_posts/wp_postmeta → PostgreSQL tables
// - Column names updated (_ID → id, etc.)
// 
// REVIEW REQUIRED:
// - Check all queries use correct table names
// - Verify all AJAX handlers work
// - Test all CRUD operations
// ============================================================

/**
 * HearMed Debug / Health Check Page
 *
 * Adds an admin-only diagnostics page under WP Admin → Tools → HearMed Debug.
 *
 * Access: administrator, hm_clevel, hm_admin, hm_finance only.
 *
 * @package HearMed_Portal
 * @since   4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ============================================================
// ADMIN MENU REGISTRATION
// ============================================================

add_action( 'admin_menu', function () {
    add_submenu_page(
        'tools.php',
        'HearMed Debug',
        'HearMed Debug',
        'manage_options', // Broad gate; role checked inside render callback.
        'hearmed-debug',
        'hm_render_debug_page'
    );
} );

// ============================================================
// ASSET ENQUEUE — only on this page
// ============================================================

add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( 'tools_page_hearmed-debug' !== $hook ) {
        return;
    }

    wp_enqueue_style(
        'hearmed-debug',
        HEARMED_URL . 'assets/css/hearmed-debug.css',
        [],
        HEARMED_VERSION
    );

    wp_enqueue_script(
        'hearmed-debug',
        HEARMED_URL . 'assets/js/hearmed-debug.js',
        [ 'jquery' ],
        HEARMED_VERSION,
        true
    );

    wp_localize_script( 'hearmed-debug', 'HMDebug', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'hmNonce' => wp_create_nonce( 'hm_nonce' ),
    ] );
} );

// ============================================================
// ACCESS HELPER
// ============================================================

/**
 * Returns true when the current user may access the debug page.
 *
 * @return bool
 */
function hm_debug_user_is_allowed() {
    $user        = wp_get_current_user();
    $admin_roles = [ 'administrator', 'hm_clevel', 'hm_admin', 'hm_finance' ];
    return ! empty( array_intersect( $admin_roles, (array) $user->roles ) );
}

// ============================================================
// PAGE RENDERER
// ============================================================

/**
 * Render the HearMed Debug / Health Check admin page.
 */
function hm_render_debug_page() {
    if ( ! hm_debug_user_is_allowed() ) {
        wp_die(
            '<p>' . esc_html__( 'You do not have permission to access this page.', 'hearmed-portal' ) . '</p>',
            403
        );
    }
// Removed: global $wpdb - now using HearMed_DB
    // ── Section A data ──────────────────────────────────────────
    $env = hm_debug_environment_info();

    // ── Section B data ──────────────────────────────────────────
    $shortcode_map = hm_debug_shortcode_map();

    // ── Section C data ──────────────────────────────────────────
    $ajax_checks = hm_debug_ajax_actions();

    // ── Section D data ──────────────────────────────────────────
    $tables = hm_debug_table_status();

    ?>
    <div class="wrap" id="hm-debug-wrap">
        <h1>🔍 HearMed Debug / Health Check</h1>
        <p>
            Plugin version: <strong><?php echo esc_html( HEARMED_VERSION ); ?></strong> &nbsp;|&nbsp;
            Generated: <strong><?php echo esc_html( current_time( 'mysql' ) ); ?></strong>
        </p>

        <!-- ── A. Environment ─────────────────────────────────── -->
        <div class="hm-debug-section">
            <h2>A. Environment</h2>
            <table class="hm-debug-table widefat striped">
                <thead>
                    <tr><th>Setting</th><th>Value</th></tr>
                </thead>
                <tbody>
                    <?php foreach ( $env as $label => $value ) : ?>
                    <tr>
                        <td><?php echo esc_html( $label ); ?></td>
                        <td><?php echo esc_html( $value ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ── B. Module / shortcode detection ───────────────── -->
        <div class="hm-debug-section">
            <h2>B. Module / Shortcode Detection</h2>
            <p>
                HearMed loads module assets <em>conditionally</em>: a module's CSS and JS are
                only enqueued when a page's content contains the corresponding shortcode.
                Use this section to check which HearMed shortcodes appear on a given page.
            </p>

            <?php
            // Optional: if a page ID was submitted (GET, read-only), show its shortcodes.
            $page_id = 0;
            if (
                isset( $_GET['hm_debug_page_id'], $_GET['hm_debug_sc_nonce'] ) &&
                wp_verify_nonce( sanitize_key( $_GET['hm_debug_sc_nonce'] ), 'hm_debug_sc' )
            ) {
                $page_id = absint( $_GET['hm_debug_page_id'] );
            }
            if ( $page_id ) {
                $post = get_post( $page_id );
                if ( $post && 'page' === $post->post_type ) {
                    echo '<h3>Shortcodes detected on page ID ' . esc_html( $page_id ) . ' — <em>' . esc_html( $post->post_title ) . '</em></h3>';
                    echo '<ul>';
                    $found_any = false;
                    foreach ( $shortcode_map as $shortcode => $module ) {
                        if ( has_shortcode( $post->post_content, $shortcode ) ) {
                            $found_any = true;
                            echo '<li><code>[' . esc_html( $shortcode ) . ']</code> → module: <strong>' . esc_html( $module ) . '</strong></li>';
                        }
                    }
                    if ( ! $found_any ) {
                        echo '<li>No HearMed shortcodes found on this page.</li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<p class="hm-debug-warn">⚠ Page ID ' . esc_html( $page_id ) . ' not found or is not a page.</p>';
                }
            }
            ?>

            <form method="get" action="">
                <input type="hidden" name="page" value="hearmed-debug">
                <?php wp_nonce_field( 'hm_debug_sc', 'hm_debug_sc_nonce' ); ?>
                <label for="hm_debug_page_id"><strong>Check a page:</strong></label>
                <input type="number" id="hm_debug_page_id" name="hm_debug_page_id"
                       value="<?php echo esc_attr( $page_id ?: '' ); ?>"
                       placeholder="Enter page ID" style="width:140px;">
                <button type="submit" class="button">Check Page</button>
            </form>

            <p style="margin-top:12px;">All registered HearMed shortcodes:</p>
            <ul>
                <?php foreach ( $shortcode_map as $shortcode => $module ) : ?>
                <li><code>[<?php echo esc_html( $shortcode ); ?>]</code> → <em><?php echo esc_html( $module ); ?></em></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- ── C. AJAX Health Checks ─────────────────────────── -->
        <div class="hm-debug-section">
            <h2>C. AJAX Health Checks</h2>

            <div class="hm-nonce-info">
                <strong>Nonce status:</strong>
                A fresh <code>hm_nonce</code> has been generated for this session and passed
                to the buttons below. Each button sends a live request to
                <code>admin-ajax.php</code> using the same nonce that the portal scripts use.
            </div>

            <p>
                <button id="hm-debug-run-all" class="button button-primary">▶ Run All Checks</button>
            </p>

            <?php foreach ( $ajax_checks as $check ) : ?>
            <div class="hm-ajax-row" data-action="<?php echo esc_attr( $check['action'] ); ?>">
                <div class="hm-ajax-row-header">
                    <button class="button hm-debug-run-btn">Run</button>
                    <code><?php echo esc_html( $check['action'] ); ?></code>
                    <span class="hm-ajax-status"></span>
                    <?php if ( ! $check['registered'] ) : ?>
                    <span class="hm-debug-warn">(⚠ action not registered — module may not be loaded)</span>
                    <?php endif; ?>
                </div>
                <div class="hm-ajax-result"></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ── D. Database / Tables ───────────────────────────── -->
        <div class="hm-debug-section">
            <h2>D. Database / Tables</h2>
            <table class="hm-debug-table widefat">
                <thead>
                    <tr>
                        <th>Table</th>
                        <th>Status</th>
                        <th>Row count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $tables as $t ) : ?>
                    <tr>
                        <td><code><?php echo esc_html( $t['full_name'] ); ?></code></td>
                        <td>
                            <?php if ( $t['exists'] ) : ?>
                                <span class="hm-debug-ok">✅ Exists</span>
                            <?php else : ?>
                                <span class="hm-debug-err">❌ Missing</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $t['exists'] ? esc_html( number_format( (int) $t['count'] ) ) : 'N/A'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="font-size:12px;color:#666;margin-top:8px;">
                Tables are prefix-aware (current prefix: <code><?php echo esc_html( $wpdb->prefix ); ?></code>).
                "Missing" means the table does not yet exist in this database, which is expected for
                JetEngine CCT types not yet created.
            </p>
        </div>

    </div><!-- /#hm-debug-wrap -->
    <?php
}

// ============================================================
// DATA HELPERS
// ============================================================

/**
 * Collect environment information.
 *
 * @return array<string, string>
 */
function hm_debug_environment_info() {
// Removed: global $wpdb - now using HearMed_DB
    // MySQL version — uses a safe SHOW VARIABLES query (no raw SQL exposed in output).
    $mysql_version = '(unavailable)';
    $row = HearMed_DB::get_row( "SHOW VARIABLES LIKE 'version'" );
    if ( $row ) {
        $mysql_version = esc_html( $row->Value );
    }

    return [
        'Plugin Version (HEARMED_VERSION)' => defined( 'HEARMED_VERSION' ) ? HEARMED_VERSION : '(undefined)',
        'WordPress Version'                 => get_bloginfo( 'version' ),
        'PHP Version'                       => PHP_VERSION,
        'MySQL Version'                     => $mysql_version,
        'Site URL'                          => site_url(),
        'Home URL'                          => home_url(),
        'WP_DEBUG'                          => defined( 'WP_DEBUG' ) && WP_DEBUG ? 'true' : 'false',
        'WP_DEBUG_LOG'                      => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ? 'true' : 'false',
        'Active Theme'                      => wp_get_theme()->get( 'Name' ) . ' ' . wp_get_theme()->get( 'Version' ),
    ];
}

/**
 * Returns the full list of portal shortcodes mapped to their module.
 *
 * @return array<string, string>
 */
function hm_debug_shortcode_map() {
    return [
        'hearmed_calendar'          => 'calendar',
        'hearmed_calendar_settings' => 'calendar',
        'hearmed_appointment_types' => 'calendar',
        'hearmed_blockouts'         => 'calendar',
        'hearmed_holidays'          => 'calendar',
        'hearmed_patients'          => 'patients',
        'hearmed_order_status'      => 'orders',
        'hearmed_approvals'         => 'orders',
        'hearmed_awaiting_fitting'  => 'orders',
        'hearmed_accounting'        => 'accounting',
        'hearmed_invoices'          => 'accounting',
        'hearmed_payments'          => 'accounting',
        'hearmed_credit_notes'      => 'accounting',
        'hearmed_prsi'              => 'accounting',
        'hearmed_reporting'         => 'reports',
        'hearmed_my_stats'          => 'reports',
        'hearmed_report_revenue'    => 'reports',
        'hearmed_report_gp'         => 'reports',
        'hearmed_repairs'           => 'repairs',
        'hearmed_notifications'     => 'notifications',
        'hearmed_kpi'               => 'kpi',
        'hearmed_kpi_tracking'      => 'kpi',
        'hearmed_admin_console'     => 'admin',
        'hearmed_users'             => 'admin',
        'hearmed_clinics'           => 'admin',
        'hearmed_products'          => 'admin',
    ];
}

/**
 * Returns the list of AJAX actions to test with their registration status.
 *
 * @return array[]
 */
function hm_debug_ajax_actions() {
    $actions = [
        'hm_get_clinics',
        'hm_get_dispensers',
        'hm_get_services',
        'hm_get_settings',
        'hm_get_patients',
    ];

    $result = [];
    foreach ( $actions as $action ) {
        $result[] = [
            'action'     => $action,
            'registered' => (bool) has_action( 'wp_ajax_' . $action ),
        ];
    }
    return $result;
}

/**
 * Check existence and row count for the key JetEngine CCT tables.
 *
 * @return array[]
 */
function hm_debug_table_status() {
// Removed: global $wpdb - now using HearMed_DB
    $slugs = [
        'calendar_settings',
        'appointments',
        'patients',
        'clinics',
        'dispensers',
        'services',
        'appointment_types',
        'orders',
        'invoices',
        'audit_log',
    ];

    $result = [];
    foreach ( $slugs as $slug ) {
        $full = $wpdb->prefix . 'jet_cct_' . $slug;
        // $full is built from the WP prefix (trusted) and a hardcoded slug (not user input).
        $found  = HearMed_DB::get_var( HearMed_DB::prepare( "SELECT to_regclass(%s)", $full ) );
        $exists = ( $found !== null );
        $count  = 0;
        if ( $exists ) {
            // Table name is trusted (prefix + hardcoded slug); cannot use %i placeholder
            // in older WP versions, so we escape the identifier explicitly.
            $count = (int) HearMed_DB::get_var( 'SELECT COUNT(*) FROM `' . esc_sql( $full ) . '`' );
        }
        $result[] = [
            'slug'      => $slug,
            'full_name' => $full,
            'exists'    => $exists,
            'count'     => $count,
        ];
    }
    return $result;
}
