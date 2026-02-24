<?php
/**
 * Quick test - add to a page temporarily
 * Shows if database query works at all
 */

// Load WordPress
require_once('wp-load.php');

// Try to get staff directly
$staff = HearMed_DB::get_results(
    "SELECT id, first_name, last_name, email FROM hearmed_reference.staff LIMIT 5"
);

echo "<h2>Direct Staff Query Test</h2>";
echo "<pre>";
echo "Query Result: " . print_r($staff, true);
echo "</pre>";
echo "<p>If you see staff records above, the database is working. If empty, query is failing.</p>";
?>
