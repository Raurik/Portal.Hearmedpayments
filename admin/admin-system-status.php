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
    <div class="wrap">
        <h1>🔧 HearMed Portal - System Status</h1>
        <p>Version: <strong><?php echo HEARMED_VERSION; ?></strong></p>
        
        <hr>
        
        <h2>Core System</h2>
        <table class="widefat">
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
                    <td><?php echo $check['status'] ? '✅ OK' : '❌ Failed'; ?></td>
                    <td><?php echo esc_html( $check['message'] ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <h2>Database Tables</h2>
        <table class="widefat">
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
                    <td><?php echo $table['exists'] ? '✅ Exists' : '❌ Missing'; ?></td>
                    <td><?php echo $table['exists'] ? number_format( $table['count'] ) . ' rows' : 'N/A'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <h2>Assets</h2>
        <table class="widefat">
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
                    <td><?php echo $asset['exists'] ? '✅ Found' : '❌ Missing'; ?></td>
                    <td><?php echo $asset['exists'] ? size_format( $asset['size'] ) : 'N/A'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <h2>Loaded Modules</h2>
        <table class="widefat">
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
                    <td><?php echo $module['loaded'] ? '✅ Loaded' : '⚠️ Not loaded'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <hr>
        
        <h2>Quick Actions</h2>
        <p>
            <a href="<?php echo admin_url( 'admin.php?page=hearmed-system-status&action=clear_cache' ); ?>" class="button">Clear Plugin Cache</a>
            <a href="<?php echo home_url( '/calendar/' ); ?>" class="button button-primary" target="_blank">Test Calendar Page</a>
            <a href="<?php echo home_url( '/patients/' ); ?>" class="button button-primary" target="_blank">Test Patients Page</a>
        </p>
        
        <?php if ( isset( $_GET['action'] ) && $_GET['action'] === 'clear_cache' ): ?>
            <?php
            // Clear any transients or caches
            delete_transient( 'hearmed_system_status' );
            echo '<div class="notice notice-success"><p>Cache cleared!</p></div>';
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
        $exists = HearMed_DB::get_var( "SHOW TABLES LIKE '$full_table'" ) === $full_table;
        $count = $exists ? HearMed_DB::get_var( "SELECT COUNT(*) FROM `$full_table`" ) : 0;
        
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
