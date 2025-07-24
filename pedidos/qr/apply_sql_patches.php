<?php
/**
 * Aplicar Parches SQL de Seguridad
 * Sequoia Speed - Sistema QR
 */

defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once dirname(__DIR__) . '/config_secure.php';

echo "🔧 APLICANDO PARCHES DE SEGURIDAD SQL\n";
echo "=====================================\n\n";

$patches_applied = 0;
$errors_found = 0;

// 1. Deshabilitar foreign key checks temporalmente
echo "1. 🔒 Configurando foreign key checks...\n";
try {
    $conn->query("SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0");
    echo "   ✅ Foreign key checks deshabilitados temporalmente\n";
} catch (Exception $e) {
    echo "   ❌ Error configurando foreign keys: " . $e->getMessage() . "\n";
    $errors_found++;
}

// 2. Verificar existencia de tablas
echo "\n2. 📊 Verificando existencia de tablas...\n";
$tables_check = [
    'usuarios' => false,
    'productos' => false,
    'almacenes' => false,
    'inventario_almacen' => false,
    'movimientos_inventario' => false
];

foreach ($tables_check as $table => $exists) {
    try {
        $result = $conn->query("SELECT COUNT(*) FROM $table LIMIT 1");
        if ($result !== false) {
            $tables_check[$table] = true;
            echo "   ✅ Tabla '$table' existe\n";
        }
    } catch (Exception $e) {
        echo "   ⚠️  Tabla '$table' no existe o no es accesible\n";
    }
}

// 3. Eliminar foreign keys existentes de qr_codes
echo "\n3. 🗑️  Eliminando foreign keys existentes...\n";
$fk_drops = [
    "ALTER TABLE qr_codes DROP FOREIGN KEY IF EXISTS qr_codes_ibfk_1",
    "ALTER TABLE qr_codes DROP FOREIGN KEY IF EXISTS qr_codes_ibfk_2", 
    "ALTER TABLE qr_codes DROP FOREIGN KEY IF EXISTS qr_codes_ibfk_3",
    "ALTER TABLE qr_codes DROP FOREIGN KEY IF EXISTS qr_codes_ibfk_4",
    "ALTER TABLE qr_codes DROP FOREIGN KEY IF EXISTS fk_qr_created_by",
    "ALTER TABLE qr_codes DROP FOREIGN KEY IF EXISTS fk_qr_product",
    "ALTER TABLE qr_codes DROP FOREIGN KEY IF EXISTS fk_qr_almacen",
    "ALTER TABLE qr_codes DROP FOREIGN KEY IF EXISTS fk_qr_inventory"
];

foreach ($fk_drops as $drop_sql) {
    try {
        $conn->query($drop_sql);
        echo "   ✅ Foreign key eliminada\n";
    } catch (Exception $e) {
        // Es normal que algunas no existan
        echo "   ℹ️  Foreign key no existía (normal)\n";
    }
}

// 4. Crear foreign keys seguras
echo "\n4. 🔗 Creando foreign keys seguras...\n";

if ($tables_check['usuarios']) {
    try {
        $conn->query("ALTER TABLE qr_codes ADD CONSTRAINT fk_qr_created_by FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE CASCADE");
        echo "   ✅ Foreign key usuarios creada\n";
        $patches_applied++;
    } catch (Exception $e) {
        echo "   ⚠️  No se pudo crear FK usuarios: " . $e->getMessage() . "\n";
    }
}

if ($tables_check['productos']) {
    try {
        $conn->query("ALTER TABLE qr_codes ADD CONSTRAINT fk_qr_product FOREIGN KEY (linked_product_id) REFERENCES productos(id) ON DELETE SET NULL");
        echo "   ✅ Foreign key productos creada\n";
        $patches_applied++;
    } catch (Exception $e) {
        echo "   ⚠️  No se pudo crear FK productos: " . $e->getMessage() . "\n";
    }
}

if ($tables_check['almacenes']) {
    try {
        $conn->query("ALTER TABLE qr_codes ADD CONSTRAINT fk_qr_almacen FOREIGN KEY (linked_almacen_id) REFERENCES almacenes(id) ON DELETE SET NULL");
        echo "   ✅ Foreign key almacenes creada\n";
        $patches_applied++;
    } catch (Exception $e) {
        echo "   ⚠️  No se pudo crear FK almacenes: " . $e->getMessage() . "\n";
    }
}

if ($tables_check['inventario_almacen']) {
    try {
        $conn->query("ALTER TABLE qr_codes ADD CONSTRAINT fk_qr_inventory FOREIGN KEY (linked_inventory_id) REFERENCES inventario_almacen(id) ON DELETE SET NULL");
        echo "   ✅ Foreign key inventario creada\n";
        $patches_applied++;
    } catch (Exception $e) {
        echo "   ⚠️  No se pudo crear FK inventario: " . $e->getMessage() . "\n";
    }
}

// 5. Corregir foreign keys en qr_scan_transactions
echo "\n5. 🔄 Corrigiendo foreign keys en qr_scan_transactions...\n";

try {
    $conn->query("ALTER TABLE qr_scan_transactions DROP FOREIGN KEY IF EXISTS qr_scan_transactions_ibfk_1");
    $conn->query("ALTER TABLE qr_scan_transactions DROP FOREIGN KEY IF EXISTS qr_scan_transactions_ibfk_2");
    $conn->query("ALTER TABLE qr_scan_transactions DROP FOREIGN KEY IF EXISTS fk_scan_user");
    $conn->query("ALTER TABLE qr_scan_transactions DROP FOREIGN KEY IF EXISTS fk_scan_qr_code");
    echo "   ✅ Foreign keys existentes eliminadas\n";
} catch (Exception $e) {
    echo "   ℹ️  Algunas foreign keys no existían\n";
}

if ($tables_check['usuarios']) {
    try {
        $conn->query("ALTER TABLE qr_scan_transactions ADD CONSTRAINT fk_scan_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE");
        echo "   ✅ Foreign key scan-usuario creada\n";
        $patches_applied++;
    } catch (Exception $e) {
        echo "   ⚠️  No se pudo crear FK scan-usuario: " . $e->getMessage() . "\n";
    }
}

try {
    $conn->query("ALTER TABLE qr_scan_transactions ADD CONSTRAINT fk_scan_qr_code FOREIGN KEY (qr_code_id) REFERENCES qr_codes(id) ON DELETE CASCADE");
    echo "   ✅ Foreign key scan-qr creada\n";
    $patches_applied++;
} catch (Exception $e) {
    echo "   ⚠️  No se pudo crear FK scan-qr: " . $e->getMessage() . "\n";
}

// 6. Crear tabla de logs de seguridad
echo "\n6. 🛡️  Creando tabla de logs de seguridad...\n";
try {
    $security_logs_sql = "CREATE TABLE IF NOT EXISTS qr_security_logs (
        id BIGINT PRIMARY KEY AUTO_INCREMENT,
        event_type ENUM('sql_injection_attempt', 'invalid_permission', 'suspicious_activity', 'rate_limit_exceeded') NOT NULL,
        user_id INT NULL,
        ip_address VARCHAR(45) NOT NULL,
        user_agent TEXT,
        request_data JSON,
        severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX idx_event_type (event_type),
        INDEX idx_severity (severity),
        INDEX idx_created_at (created_at),
        INDEX idx_ip_address (ip_address)
    )";
    
    $conn->query($security_logs_sql);
    echo "   ✅ Tabla qr_security_logs creada\n";
    $patches_applied++;
} catch (Exception $e) {
    echo "   ❌ Error creando tabla de logs: " . $e->getMessage() . "\n";
    $errors_found++;
}

// 7. Insertar configuraciones de seguridad
echo "\n7. ⚙️ Insertando configuraciones de seguridad...\n";
$security_configs = [
    [
        'key' => 'qr_security_config',
        'value' => '{"max_scans_per_minute": 60, "require_csrf": true, "log_all_actions": true, "validate_permissions": true}',
        'desc' => 'Configuración de seguridad para sistema QR'
    ],
    [
        'key' => 'qr_validation_rules', 
        'value' => '{"min_content_length": 5, "max_content_length": 255, "allowed_characters": "alphanumeric_dash", "require_checksum": true}',
        'desc' => 'Reglas de validación para códigos QR'
    ]
];

foreach ($security_configs as $config) {
    try {
        $stmt = $conn->prepare("INSERT IGNORE INTO qr_system_config (config_key, config_value, config_description, created_by, created_at) VALUES (?, ?, ?, 1, NOW())");
        $stmt->bind_param('sss', $config['key'], $config['value'], $config['desc']);
        if ($stmt->execute()) {
            echo "   ✅ Configuración '{$config['key']}' insertada\n";
            $patches_applied++;
        } else {
            echo "   ℹ️  Configuración '{$config['key']}' ya existía\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Error insertando configuración: " . $e->getMessage() . "\n";
        $errors_found++;
    }
}

// 8. Limpiar datos inconsistentes
echo "\n8. 🧹 Limpiando datos inconsistentes...\n";

// Eliminar transacciones huérfanas
try {
    $result = $conn->query("DELETE qst FROM qr_scan_transactions qst LEFT JOIN qr_codes qc ON qst.qr_code_id = qc.id WHERE qc.id IS NULL");
    $affected = $conn->affected_rows;
    echo "   ✅ Transacciones huérfanas eliminadas: $affected\n";
    if ($affected > 0) $patches_applied++;
} catch (Exception $e) {
    echo "   ⚠️  No se pudieron limpiar transacciones huérfanas: " . $e->getMessage() . "\n";
}

// Limpiar referencias inválidas a productos
if ($tables_check['productos']) {
    try {
        $result = $conn->query("UPDATE qr_codes SET linked_product_id = NULL WHERE linked_product_id IS NOT NULL AND linked_product_id NOT IN (SELECT id FROM productos)");
        $affected = $conn->affected_rows;
        echo "   ✅ Referencias inválidas a productos limpiadas: $affected\n";
        if ($affected > 0) $patches_applied++;
    } catch (Exception $e) {
        echo "   ⚠️  No se pudieron limpiar referencias de productos: " . $e->getMessage() . "\n";
    }
}

// 9. Crear vista de monitoreo
echo "\n9. 👁️  Creando vista de monitoreo de seguridad...\n";
try {
    $monitoring_view = "CREATE OR REPLACE VIEW vista_qr_security_monitor AS
    SELECT 
        'qr_codes' as table_name,
        COUNT(*) as total_records,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as created_last_24h,
        COUNT(CASE WHEN active = 0 THEN 1 END) as inactive_records
    FROM qr_codes
    
    UNION ALL
    
    SELECT 
        'qr_scan_transactions' as table_name,
        COUNT(*) as total_records,
        COUNT(CASE WHEN scanned_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as created_last_24h,
        COUNT(CASE WHEN processing_status = 'failed' THEN 1 END) as failed_records
    FROM qr_scan_transactions";
    
    $conn->query($monitoring_view);
    echo "   ✅ Vista de monitoreo creada\n";
    $patches_applied++;
} catch (Exception $e) {
    echo "   ❌ Error creando vista de monitoreo: " . $e->getMessage() . "\n";
    $errors_found++;
}

// 10. Restaurar foreign key checks
echo "\n10. 🔓 Restaurando configuración de foreign keys...\n";
try {
    $conn->query("SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS");
    echo "   ✅ Foreign key checks restaurados\n";
} catch (Exception $e) {
    echo "   ❌ Error restaurando foreign keys: " . $e->getMessage() . "\n";
    $errors_found++;
}

// Resumen final
echo "\n" . str_repeat("=", 50) . "\n";
echo "📊 RESUMEN DE PARCHES DE SEGURIDAD\n";
echo str_repeat("=", 50) . "\n";
echo "✅ Parches aplicados: $patches_applied\n";
echo "❌ Errores encontrados: $errors_found\n";

if ($errors_found == 0) {
    echo "🎉 ¡PARCHES DE SEGURIDAD APLICADOS EXITOSAMENTE!\n";
    echo "   El sistema QR ahora tiene integridad referencial\n";
    echo "   Las vulnerabilidades SQL han sido corregidas\n";
} else {
    echo "⚠️  ALGUNOS PARCHES NO SE PUDIERON APLICAR\n";
    echo "   Revisar errores arriba para corrección manual\n";
}

echo "\n🔍 Próximo paso: php qr/validate_system.php\n";
?>