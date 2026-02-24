<?php
/**
 * Database Migration Helper
 * 
 * Add this file to your WordPress root and visit:
 * https://portal.hearmedpayments.net/db-migrate.php?key=YOUR_SECRET_KEY
 * 
 * Replace YOUR_SECRET_KEY with a random string for security.
 * 
 * This script will apply missing columns to the live PostgreSQL database.
 */

// Security: require a key parameter
$required_key = 'HearMed_Fix_2026';
$provided_key = isset($_GET['key']) ? $_GET['key'] : '';

if ($provided_key !== $required_key) {
    die('Unauthorized');
}

// Load WordPress
require_once('wp-load.php');

global $wpdb;

echo "<h2>HearMed Portal Database Migration</h2>";
echo "<pre style='background:#f5f5f5;padding:20px;overflow:auto;'>";

// List of migrations
$migrations = [
    'Add employee_number to staff' => [
        'table' => 'hearmed_reference.staff',
        'column' => 'employee_number',
        'definition' => 'character varying(50)',
        'query' => "ALTER TABLE hearmed_reference.staff ADD COLUMN IF NOT EXISTS employee_number character varying(50);"
    ],
    'Add qualifications to staff' => [
        'table' => 'hearmed_reference.staff',
        'column' => 'qualifications',
        'definition' => 'jsonb',
        'query' => "ALTER TABLE hearmed_reference.staff ADD COLUMN IF NOT EXISTS qualifications jsonb;"
    ],
    'Add photo_url to staff' => [
        'table' => 'hearmed_reference.staff',
        'column' => 'photo_url',
        'definition' => 'character varying(500)',
        'query' => "ALTER TABLE hearmed_reference.staff ADD COLUMN IF NOT EXISTS photo_url character varying(500);"
    ],
    'Add last_login to staff_auth' => [
        'table' => 'hearmed_reference.staff_auth',
        'column' => 'last_login',
        'definition' => 'timestamp without time zone',
        'query' => "ALTER TABLE hearmed_reference.staff_auth ADD COLUMN IF NOT EXISTS last_login timestamp without time zone;"
    ],
    'Allow NULL password_hash in staff_auth' => [
        'table' => 'hearmed_reference.staff_auth',
        'column' => 'password_hash',
        'definition' => 'text',
        'query' => "ALTER TABLE hearmed_reference.staff_auth ALTER COLUMN password_hash DROP NOT NULL;"
    ]
];

$results = [];

foreach ($migrations as $label => $migration) {
    echo "\n[*] " . $label . "\n";
    echo "    Table: " . $migration['table'] . "\n";
    echo "    Column: " . $migration['column'] . "\n";
    
    // Run the migration
    $result = $wpdb->query($migration['query']);
    
    if ($result === false) {
        echo "    ❌ ERROR: " . $wpdb->last_error . "\n";
        $results[$label] = false;
    } else {
        echo "    ✅ Applied successfully\n";
        $results[$label] = true;
    }
}

echo "\n\n=== VERIFICATION ===\n";

// Verify all columns exist
$verify_queries = [
    'staff.employee_number' => "SELECT column_name FROM information_schema.columns WHERE table_schema='hearmed_reference' AND table_name='staff' AND column_name='employee_number';",
    'staff.qualifications' => "SELECT column_name FROM information_schema.columns WHERE table_schema='hearmed_reference' AND table_name='staff' AND column_name='qualifications';",
    'staff.photo_url' => "SELECT column_name FROM information_schema.columns WHERE table_schema='hearmed_reference' AND table_name='staff' AND column_name='photo_url';",
    'staff_auth.last_login' => "SELECT column_name FROM information_schema.columns WHERE table_schema='hearmed_reference' AND table_name='staff_auth' AND column_name='last_login';",
    'staff_auth.password_hash NULL' => "SELECT is_nullable FROM information_schema.columns WHERE table_schema='hearmed_reference' AND table_name='staff_auth' AND column_name='password_hash';"
];

foreach ($verify_queries as $label => $query) {
    $result = $wpdb->get_var($query);
    if ($result) {
        echo "[✅] " . $label . " → EXISTS\n";
    } else {
        echo "[❌] " . $label . " → MISSING\n";
    }
}

echo "\n\n=== RESULT ===\n";
$all_success = !in_array(false, $results);
if ($all_success) {
    echo "✅ All migrations applied successfully!\n";
    echo "You can now use the staff form without errors.\n";
} else {
    echo "❌ Some migrations failed. Check errors above.\n";
}

echo "</pre>";
echo "<hr>";
echo "<p><strong>SECURITY NOTE:</strong> Delete this file (db-migrate.php) after running migrations.</p>";
echo "<p><a href='https://portal.hearmedpayments.net/wp-admin/'>Return to WordPress Admin</a></p>";
?>
