<?php
/**
 * Setup Push Notifications Database Tables
 * Run this script to create the required tables for push notifications
 */

require_once 'conexion.php';

// Read and execute the SQL file
$sql = file_get_contents('create_push_subscriptions_table.sql');

// Split the SQL into individual statements
$statements = array_filter(array_map('trim', explode(';', $sql)));

$success = true;
$results = [];

foreach ($statements as $statement) {
    if (empty($statement) || strpos($statement, '--') === 0) {
        continue;
    }
    
    $result = $conn->query($statement);
    
    if ($result) {
        $results[] = "✅ Executed successfully: " . substr($statement, 0, 50) . "...";
    } else {
        $results[] = "❌ Error executing: " . substr($statement, 0, 50) . "...";
        $results[] = "   Error: " . $conn->error;
        $success = false;
    }
}

echo "Push Notifications Database Setup\n";
echo "================================\n\n";

foreach ($results as $result) {
    echo $result . "\n";
}

if ($success) {
    echo "\n🎉 All tables created successfully!\n";
    echo "Tables created:\n";
    echo "- push_subscriptions\n";
    echo "- push_notification_logs\n";
    echo "- push_notification_settings\n";
} else {
    echo "\n⚠️  Some tables failed to create. Check the errors above.\n";
}

$conn->close();
?>