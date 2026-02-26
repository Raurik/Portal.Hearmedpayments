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
 * HearMed System Status Page
 * 
 * Admin page to verify system health and configuration
 * 
 * @package HearMed_Portal
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Add admin menu
add_action( 'admin_menu', function() {
    add_submenu_page(
        'tools.php',
        'HearMed System Status',
        'HearMed Status',
        'manage_options',
        'hearmed-system-status',
        'hearmed_render_system_status_page'
    );
});

// Enqueue admin CSS on this page
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( 'tools_page_hearmed-system-status' !== $hook ) {
        return;
    }
    wp_enqueue_style(
        'hearmed-admin',
        HEARMED_URL . 'assets/css/hearmed-admin.css',
        [],
        HEARMED_VERSION
    );
});

/**
 * Render system status page
 */
function hearmed_render_system_status_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized' );
    }
    
    // Run system checks
    $checks = hearmed_run_system_checks();
    
    ?>
    <div class="hm-admin">
        <a href="<?php echo esc_url(home_url("/admin-console/")); ?>" class="hm-back">← Back</a>
        <div class="hm-admin-hd">
            <h2>System Status</h2>
        </div>
        <p style="color:#64748b;font-size:13px;margin-bottom:20px;">
            Version: <strong><?php echo HEARMED_VERSION; ?></strong>
        </p>
        
        <h3 class="hm-section-heading">Core System</h3>
        <table class="hm-table" data-no-enhance>
            <thead>
                <tr>
                    <th>Component</th>
                    <th>Status</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $checks['core'] as $check ): ?>
                <tr>
                    <td><?php echo esc_html( $check['name'] ); ?></td>
                    <td><?php echo $check['status'] ? '<span class="hm-badge hm-badge--green">OK</span>' : '<span class="hm-badge hm-badge--red">Failed</span>'; ?></td>
                    <td><?php echo esc_html( $check['message'] ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <h3 class="hm-section-heading">Database Tables</h3>
        <table class="hm-table" data-no-enhance>
            <thead>
                <tr>
                    <th>Table</th>
                    <th>Status</th>
                    <th>Row Count</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $checks['tables'] as $table ): ?>
                <tr>
                    <td><code><?php echo esc_html( $table['name'] ); ?></code></td>
                    <td><?php echo $table['exists'] ? '<span class="hm-badge hm-badge--green">Exists</span>' : '<span class="hm-badge hm-badge--red">Missing</span>'; ?></td>
                    <td><?php echo $table['exists'] ? number_format( $table['count'] ) . ' rows' : '—'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <h3 class="hm-section-heading">Assets</h3>
        <table class="hm-table" data-no-enhance>
            <thead>
                <tr>
                    <th>File</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Size</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $checks['assets'] as $asset ): ?>
                <tr>
                    <td><code><?php echo esc_html( $asset['name'] ); ?></code></td>
                    <td><?php echo esc_html( $asset['type'] ); ?></td>
                    <td><?php echo $asset['exists'] ? '<span class="hm-badge hm-badge--green">Found</span>' : '<span class="hm-badge hm-badge--red">Missing</span>'; ?></td>
                    <td><?php echo $asset['exists'] ? size_format( $asset['size'] ) : '—'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <h3 class="hm-section-heading">Loaded Modules</h3>
        <table class="hm-table" data-no-enhance>
            <thead>
                <tr>
                    <th>Module</th>
                    <th>File</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $checks['modules'] as $module ): ?>
                <tr>
                    <td><?php echo esc_html( $module['name'] ); ?></td>
                    <td><code><?php echo esc_html( $module['file'] ); ?></code></td>
                    <td><?php echo $module['loaded'] ? '<span class="hm-badge hm-badge--green">Loaded</span>' : '<span class="hm-badge hm-badge--amber">Not loaded</span>'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <h3 class="hm-section-heading">Quick Actions</h3>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?php echo admin_url( 'admin.php?page=hearmed-system-status&action=clear_cache' ); ?>" class="hm-btn">Clear Plugin Cache</a>
            <a href="<?php echo home_url( '/calendar/' ); ?>" class="hm-btn hm-btn--primary" target="_blank">Test Calendar Page</a>
            <a href="<?php echo home_url( '/patients/' ); ?>" class="hm-btn hm-btn--primary" target="_blank">Test Patients Page</a>
        </div>
        
        <?php if ( isset( $_GET['action'] ) && $_GET['action'] === 'clear_cache' ): ?>
            <?php
            delete_transient( 'hearmed_system_status' );
            echo '<div class="hm-notice hm-notice--success" style="margin-top:16px;"><div class="hm-notice-body"><span class="hm-notice-icon">✓</span> Cache cleared successfully.</div></div>';
            ?>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Run system health checks
 */
function hearmed_run_system_checks() {
        // PostgreSQL only - no $wpdb needed
    $checks = [
        'core' => [],
        'tables' => [],
        'assets' => [],
        'modules' => [],
    ];
    
    // Core classes check
    $core_classes = [
        'HearMed_Core',
        'HearMed_Enqueue',
        'HearMed_Router',
        'HearMed_Auth',
        'HearMed_DB',
        'HearMed_Utils',
        'HearMed_Ajax',
    ];
    
    foreach ( $core_classes as $class ) {
        $checks['core'][] = [
            'name' => $class,
            'status' => class_exists( $class ),
            'message' => class_exists( $class ) ? 'Loaded' : 'Missing',
        ];
    }
    
    // Database tables check
    $tables = [
        'appointments',
        'orders',
        'invoices',
        'patients',
        'notifications',
        'audit_log',
        'repairs',
    ];
    
    foreach ( $tables as $table ) {
        $full_table = HearMed_Core::table( $table );
        $exists = HearMed_DB::get_var( HearMed_DB::prepare( "SELECT to_regclass(%s)", $full_table ) ) !== null;
        $count = $exists ? HearMed_DB::get_var( "SELECT COUNT(*) FROM {$full_table}" ) : 0;
        
        $checks['tables'][] = [
            'name' => $table,
            'exists' => $exists,
            'count' => $count,
        ];
    }
    
    // Assets check
    $assets = [
        [ 'name' => 'hearmed-foundation.css', 'type' => 'CSS', 'path' => 'assets/css/hearmed-foundation.css' ],
        [ 'name' => 'hearmed-design.css', 'type' => 'CSS', 'path' => 'assets/css/hearmed-design.css' ],
        [ 'name' => 'hearmed-core.js', 'type' => 'JS', 'path' => 'assets/js/hearmed-core.js' ],
        [ 'name' => 'hearmed-calendar.js', 'type' => 'JS', 'path' => 'assets/js/hearmed-calendar.js' ],
        [ 'name' => 'hearmed-patients.js', 'type' => 'JS', 'path' => 'assets/js/hearmed-patients.js' ],
    ];
    
    foreach ( $assets as $asset ) {
        $file_path = HEARMED_PATH . $asset['path'];
        $exists = file_exists( $file_path );
        
        $checks['assets'][] = [
            'name' => $asset['name'],
            'type' => $asset['type'],
            'exists' => $exists,
            'size' => $exists ? filesize( $file_path ) : 0,
        ];
    }
    
    // Modules check
    $modules = [
        'calendar' => 'modules/mod-calendar.php',
        'patients' => 'modules/mod-patients.php',
        'orders' => 'modules/mod-orders.php',
        'accounting' => 'modules/mod-accounting.php',
        'reports' => 'modules/mod-reports.php',
        'repairs' => 'modules/mod-repairs.php',
    ];
    
    foreach ( $modules as $name => $file ) {
        $checks['modules'][] = [
            'name' => ucfirst( $name ),
            'file' => $file,
            'loaded' => file_exists( HEARMED_PATH . $file ),
        ];
    }
    
    return $checks;
}
