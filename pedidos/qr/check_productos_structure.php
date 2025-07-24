<?php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once dirname(__DIR__) . '/config_secure.php';

echo "🔍 ESTRUCTURA TABLA PRODUCTOS\n";
echo "=============================\n\n";

$result = $conn->query("DESCRIBE productos");
echo "Columnas disponibles:\n";
while ($row = $result->fetch_assoc()) {
    echo "- {$row['Field']}: {$row['Type']} | Null: {$row['Null']} | Key: {$row['Key']} | Default: " . ($row['Default'] ?: 'NULL') . "\n";
}

echo "\n🔍 MUESTRA DE DATOS\n";
echo "==================\n";
$sample = $conn->query("SELECT * FROM productos WHERE activo = 1 LIMIT 3");
while ($row = $sample->fetch_assoc()) {
    echo "\nProducto ID: {$row['id']}\n";
    foreach ($row as $field => $value) {
        echo "  $field: " . ($value ?: 'NULL') . "\n";
    }
}
?>