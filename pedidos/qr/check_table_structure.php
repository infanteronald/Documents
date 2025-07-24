<?php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once dirname(__DIR__) . '/config_secure.php';

echo "🔍 ESTRUCTURA TABLA QR_CODES\n";
echo "============================\n\n";

$result = $conn->query("DESCRIBE qr_codes");
while ($row = $result->fetch_assoc()) {
    echo "{$row['Field']}: {$row['Type']} | Null: {$row['Null']} | Key: {$row['Key']} | Default: " . ($row['Default'] ?: 'NULL') . "\n";
}

echo "\n🔍 CONSTRAINTS\n";
echo "==============\n";

$constraints = $conn->query("
    SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_NAME = 'qr_codes' 
    AND TABLE_SCHEMA = DATABASE()
    AND REFERENCED_TABLE_NAME IS NOT NULL
");

if ($constraints->num_rows > 0) {
    while ($constraint = $constraints->fetch_assoc()) {
        echo "Constraint: {$constraint['CONSTRAINT_NAME']} | Column: {$constraint['COLUMN_NAME']} -> {$constraint['REFERENCED_TABLE_NAME']}.{$constraint['REFERENCED_COLUMN_NAME']}\n";
    }
} else {
    echo "No hay foreign key constraints definidos\n";
}
?>