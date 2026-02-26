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
        'hearmed-admin',
        HEARMED_URL . 'assets/css/hearmed-admin.css',
        [],
        HEARMED_VERSION
    );

    wp_enqueue_style(
        'hearmed-debug',
        HEARMED_URL . 'assets/css/hearmed-debug.css',
        [ 'hearmed-admin' ],
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
    <div class="hm-admin" id="hm-debug-wrap">
        <a href="<?php echo esc_url(home_url("/admin-console/")); ?>" class="hm-back">← Back</a>
        <div class="hm-admin-hd">
            <h2>Debug / Health Check</h2>
        </div>
        <p style="color:#64748b;font-size:13px;margin-bottom:20px;">
            Plugin version: <strong><?php echo esc_html( HEARMED_VERSION ); ?></strong> &nbsp;|&nbsp;
            Generated: <strong><?php echo esc_html( current_time( 'mysql' ) ); ?></strong>
        </p>

        <!-- ── A. Environment ─────────────────────────────────── -->
        <h3 class="hm-section-heading">A. Environment</h3>
        <table class="hm-table" data-no-enhance>
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

        <!-- ── B. Module / shortcode detection ───────────────── -->
        <h3 class="hm-section-heading">B. Module / Shortcode Detection</h3>
        <p style="font-size:13px;color:#64748b;margin-bottom:14px;">
            HearMed loads module assets <em>conditionally</em>: a module's CSS and JS are
            only enqueued when a page's content contains the corresponding shortcode.
            Use this section to check which HearMed shortcodes appear on a given page.
        </p>

        <?php
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
                echo '<div class="hm-alert hm-alert-info"><strong>Shortcodes on page #' . esc_html( $page_id ) . ' — ' . esc_html( $post->post_title ) . ':</strong><ul style="margin:8px 0 0 18px;">';
                $found_any = false;
                foreach ( $shortcode_map as $shortcode => $module ) {
                    if ( has_shortcode( $post->post_content, $shortcode ) ) {
                        $found_any = true;
                        echo '<li><code>[' . esc_html( $shortcode ) . ']</code> → <strong>' . esc_html( $module ) . '</strong></li>';
                    }
                }
                if ( ! $found_any ) {
                    echo '<li>No HearMed shortcodes found on this page.</li>';
                }
                echo '</ul></div>';
            } else {
                echo '<div class="hm-alert hm-alert-warning">Page ID ' . esc_html( $page_id ) . ' not found or is not a page.</div>';
            }
        }
        ?>

        <form method="get" action="" style="display:flex;gap:8px;align-items:center;margin-bottom:16px;">
            <input type="hidden" name="page" value="hearmed-debug">
            <?php wp_nonce_field( 'hm_debug_sc', 'hm_debug_sc_nonce' ); ?>
            <label for="hm_debug_page_id"><strong>Check a page:</strong></label>
            <input type="number" id="hm_debug_page_id" name="hm_debug_page_id"
                   class="hm-filter-select"
                   value="<?php echo esc_attr( $page_id ?: '' ); ?>"
                   placeholder="Page ID" style="width:120px;">
            <button type="submit" class="hm-btn hm-btn--primary">Check Page</button>
        </form>

        <details style="margin-bottom:24px;">
            <summary style="cursor:pointer;font-size:13px;color:#64748b;">Show all registered shortcodes</summary>
            <table class="hm-table" data-no-enhance style="margin-top:10px;">
                <thead><tr><th>Shortcode</th><th>Module</th></tr></thead>
                <tbody>
                <?php foreach ( $shortcode_map as $shortcode => $module ) : ?>
                <tr>
                    <td><code>[<?php echo esc_html( $shortcode ); ?>]</code></td>
                    <td><?php echo esc_html( $module ); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </details>

        <!-- ── C. AJAX Health Checks ─────────────────────────── -->
        <h3 class="hm-section-heading">C. AJAX Health Checks</h3>

        <div class="hm-nonce-info">
            <strong>Nonce status:</strong>
            A fresh <code>hm_nonce</code> has been generated for this session and passed
            to the buttons below. Each button sends a live request to
            <code>admin-ajax.php</code> using the same nonce that the portal scripts use.
        </div>

        <p style="margin-bottom:14px;">
            <button id="hm-debug-run-all" class="hm-btn hm-btn--primary">▶ Run All Checks</button>
        </p>

        <?php foreach ( $ajax_checks as $check ) : ?>
        <div class="hm-ajax-row" data-action="<?php echo esc_attr( $check['action'] ); ?>">
            <div class="hm-ajax-row-header">
                <button class="hm-btn hm-btn--sm">Run</button>
                <code><?php echo esc_html( $check['action'] ); ?></code>
                <span class="hm-ajax-status"></span>
                <?php if ( ! $check['registered'] ) : ?>
                <span class="hm-badge hm-badge--amber">Action not registered</span>
                <?php endif; ?>
            </div>
            <div class="hm-ajax-result"></div>
        </div>
        <?php endforeach; ?>

        <!-- ── D. Database / Tables ───────────────────────────── -->
        <h3 class="hm-section-heading">D. Database / Tables</h3>
        <table class="hm-table" data-no-enhance>
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
                            <span class="hm-badge hm-badge--green">Exists</span>
                            <?php else : ?>
                            <span class="hm-badge hm-badge--red">Missing</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $t['exists'] ? esc_html( number_format( (int) $t['count'] ) ) : '—'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p style="font-size:12px;color:#94a3b8;margin-top:8px;">
            Tables are resolved via <code>HearMed_DB::table()</code> to their PostgreSQL schema-qualified names.
            "Missing" means the table does not yet exist in the Railway PostgreSQL database.
        </p>

    </div><!-- /.hm-admin -->
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
    // PostgreSQL version
    $pg_version = '(unavailable)';
    try {
        $row = HearMed_DB::get_var( "SELECT version()" );
        if ( $row ) {
            $pg_version = $row;
        }
    } catch ( \Exception $e ) {
        $pg_version = '(connection error)';
    }

    return [
        'Plugin Version (HEARMED_VERSION)' => defined( 'HEARMED_VERSION' ) ? HEARMED_VERSION : '(undefined)',
        'WordPress Version'                 => get_bloginfo( 'version' ),
        'PHP Version'                       => PHP_VERSION,
        'PostgreSQL Version'                => $pg_version,
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
        'hearmed_order_status'      => 'order-status',
        'hearmed_approvals'         => 'approvals',
        'hearmed_awaiting_fitting'  => 'fitting',
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
    $slugs = [
        'calendar_settings',
        'appointments',
        'patients',
        'clinics',
        'staff',
        'services',
        'appointment_types',
        'orders',
        'invoices',
        'audit_log',
        'products',
        'manufacturers',
        'dispenser_schedules',
        'sms_templates',
        'kpi_targets',
    ];

    $result = [];
    foreach ( $slugs as $slug ) {
        $full = HearMed_DB::table( $slug );
        if ( ! $full ) {
            $result[] = [
                'slug'      => $slug,
                'full_name' => '(not registered)',
                'exists'    => false,
                'count'     => 0,
            ];
            continue;
        }
        $found  = HearMed_DB::get_var( HearMed_DB::prepare( "SELECT to_regclass(%s)", $full ) );
        $exists = ( $found !== null );
        $count  = 0;
        if ( $exists ) {
            $count = (int) HearMed_DB::get_var( "SELECT COUNT(*) FROM {$full}" );
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
