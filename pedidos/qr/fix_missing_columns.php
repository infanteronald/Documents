<?php
/**
 * Corregir columnas faltantes en tablas QR
 */

defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once dirname(__DIR__) . '/config_secure.php';

echo "🔧 CORRIGIENDO COLUMNAS FALTANTES EN TABLAS QR\n";
echo "===============================================\n\n";

try {
    // 1. Corregir tabla qr_physical_locations
    echo "1. Corrigiendo tabla qr_physical_locations...\n";
    
    $queries = [
        "ALTER TABLE qr_physical_locations ADD COLUMN IF NOT EXISTS location_name VARCHAR(255) NOT NULL AFTER id",
        "ALTER TABLE qr_physical_locations ADD COLUMN IF NOT EXISTS location_code VARCHAR(50) AFTER location_name", 
        "ALTER TABLE qr_physical_locations ADD COLUMN IF NOT EXISTS coordinates JSON AFTER almacen_id",
        "ALTER TABLE qr_physical_locations ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER active"
    ];
    
    foreach ($queries as $query) {
        if ($conn->query($query)) {
            echo "   ✅ Query ejecutado: " . substr($query, 0, 50) . "...\n";
        } else {
            echo "   ❌ Error: " . $conn->error . "\n";
        }
    }
    
    // 2. Corregir tabla qr_work_sessions
    echo "\n2. Corrigiendo tabla qr_work_sessions...\n";
    
    $queries = [
        "ALTER TABLE qr_work_sessions ADD COLUMN IF NOT EXISTS ended_at TIMESTAMP NULL AFTER started_at",
        "ALTER TABLE qr_work_sessions ADD COLUMN IF NOT EXISTS total_scans INT DEFAULT 0 AFTER session_type"
    ];
    
    foreach ($queries as $query) {
        if ($conn->query($query)) {
            echo "   ✅ Query ejecutado: " . substr($query, 0, 50) . "...\n";
        } else {
            echo "   ❌ Error: " . $conn->error . "\n";
        }
    }
    
    echo "\n✅ Corrección de columnas completada!\n\n";
    
    // Verificar estructura resultante
    echo "📋 VERIFICANDO ESTRUCTURA RESULTANTE:\n";
    echo "====================================\n";
    
    $tables = ['qr_physical_locations', 'qr_work_sessions'];
    
    foreach ($tables as $table) {
        echo "\n🗄️  Tabla: $table\n";
        echo str_repeat("-", 30) . "\n";
        
        $result = $conn->query("DESCRIBE $table");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                echo sprintf("   %-20s %-15s %s\n", 
                    $row['Field'], 
                    $row['Type'], 
                    $row['Null'] == 'NO' ? 'NOT NULL' : 'NULL'
                );
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n✨ Proceso completado.\n";
?>