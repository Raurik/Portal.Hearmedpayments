<?php
/**
 * Database Diagnostic - Check staff table contents
 * 
 * Visit: https://portal.hearmedpayments.net/db-check-staff.php?key=HearMed_Diag_2026
 * 
 * Shows current staff records and staff_auth records
 */

$required_key = 'HearMed_Diag_2026';
$provided_key = isset($_GET['key']) ? $_GET['key'] : '';

if ($provided_key !== $required_key) {
    die('Unauthorized');
}

require_once('wp-load.php');
global $wpdb;

echo "<h2>HearMed Staff Database Diagnostic</h2>";
echo "<pre style='background:#f5f5f5;padding:20px;overflow:auto;font-family:monospace;'>";

echo "\n=== STAFF TABLE RECORDS ===\n";
$staff = $wpdb->get_results("SELECT * FROM hearmed_reference.staff ORDER BY id DESC LIMIT 10;");
if ($staff) {
    echo "Found " . count($staff) . " staff records:\n";
    foreach ($staff as $s) {
        echo "\n[ID: {$s->id}] {$s->first_name} {$s->last_name}\n";
        echo "  Email: {$s->email}\n";
        echo "  Phone: {$s->phone}\n";
        echo "  Role: {$s->role}\n";
        echo "  Active: " . ($s->is_active ? 'YES' : 'NO') . "\n";
        echo "  Created: {$s->created_at}\n";
    }
} else {
    echo "❌ NO STAFF RECORDS FOUND\n";
    echo "Error: " . $wpdb->last_error . "\n";
}

echo "\n\n=== STAFF_AUTH TABLE RECORDS ===\n";
$auth = $wpdb->get_results("SELECT staff_id, username, temp_password, two_factor_enabled FROM hearmed_reference.staff_auth ORDER BY staff_id DESC LIMIT 10;");
if ($auth) {
    echo "Found " . count($auth) . " auth records:\n";
    foreach ($auth as $a) {
        echo "\n[Staff ID: {$a->staff_id}] Username: {$a->username}\n";
        echo "  Temp Password: " . ($a->temp_password ? 'YES' : 'NO') . "\n";
        echo "  2FA Enabled: " . ($a->two_factor_enabled ? 'YES' : 'NO') . "\n";
    }
} else {
    echo "❌ NO AUTH RECORDS FOUND\n";
    echo "Error: " . $wpdb->last_error . "\n";
}

echo "\n\n=== TABLE COLUMN VERIFICATION ===\n";
$cols = $wpdb->get_results("
    SELECT column_name, data_type, is_nullable 
    FROM information_schema.columns 
    WHERE table_schema='hearmed_reference' AND table_name='staff'
    ORDER BY ordinal_position
");
if ($cols) {
    echo "Staff table columns:\n";
    foreach ($cols as $col) {
        echo "  - {$col->column_name} ({$col->data_type}, nullable: {$col->is_nullable})\n";
    }
} else {
    echo "❌ Could not retrieve column info\n";
}

echo "\n\n=== QUERY TEST ===\n";
$test = $wpdb->get_results("
    SELECT s.id, s.first_name, s.last_name, s.email, 
           a.username, a.two_factor_enabled
    FROM hearmed_reference.staff s
    LEFT JOIN hearmed_reference.staff_auth a ON s.id = a.staff_id
    ORDER BY s.last_name, s.first_name
");
if ($test) {
    echo "Join query returned " . count($test) . " records\n";
    foreach ($test as $row) {
        echo "  [{$row->id}] {$row->first_name} {$row->last_name} ({$row->email})\n";
    }
} else {
    echo "❌ Join query failed\n";
    echo "Error: " . $wpdb->last_error . "\n";
}

echo "\n</pre>";
echo "<hr><p><strong>Delete this file (db-check-staff.php) after diagnosis.</strong></p>";
?>
