<?php
/**
 * Crear Tablas QR de forma Simple
 */

defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once dirname(__DIR__) . '/config_secure.php';

echo "🚀 Creando tablas QR...\n";

$tables = [
    'qr_codes' => "
        CREATE TABLE IF NOT EXISTS qr_codes (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            qr_uuid VARCHAR(36) UNIQUE NOT NULL,
            qr_content VARCHAR(255) UNIQUE NOT NULL,
            entity_type ENUM('producto', 'ubicacion', 'lote', 'pedido', 'almacen') NOT NULL,
            entity_id VARCHAR(100) NOT NULL,
            error_correction_level ENUM('L', 'M', 'Q', 'H') DEFAULT 'H',
            size_pixels INT DEFAULT 400,
            base_data JSON NOT NULL,
            context_rules JSON NULL,
            scan_count INT DEFAULT 0,
            first_scanned_at TIMESTAMP NULL,
            last_scanned_at TIMESTAMP NULL,
            linked_inventory_id INT NULL,
            linked_product_id INT NULL,
            linked_almacen_id INT NULL,
            active BOOLEAN DEFAULT TRUE,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_qr_content (qr_content),
            INDEX idx_qr_uuid (qr_uuid),
            INDEX idx_entity (entity_type, entity_id),
            INDEX idx_active (active)
        )
    ",
    
    'qr_scan_transactions' => "
        CREATE TABLE IF NOT EXISTS qr_scan_transactions (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            transaction_uuid VARCHAR(36) UNIQUE NOT NULL,
            qr_code_id BIGINT NOT NULL,
            user_id INT NOT NULL,
            scan_method ENUM('camera_web', 'camera_mobile', 'scanner_device', 'manual_input') DEFAULT 'camera_mobile',
            device_info JSON,
            scan_location VARCHAR(100),
            action_performed ENUM('consulta', 'entrada', 'salida', 'conteo', 'movimiento', 'ajuste', 'transferencia') NOT NULL,
            quantity_affected INT DEFAULT 1,
            notes TEXT,
            processing_status ENUM('success', 'failed', 'pending', 'warning') DEFAULT 'success',
            error_message TEXT NULL,
            scan_duration_ms INT,
            processing_duration_ms INT,
            generated_movement_id INT NULL,
            offline_processed BOOLEAN DEFAULT FALSE,
            synced_at TIMESTAMP NULL,
            workflow_type VARCHAR(50) NULL,
            scanned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_scan_time (scanned_at),
            INDEX idx_user_scans (user_id, scanned_at),
            INDEX idx_processing_status (processing_status)
        )
    ",
    
    'qr_workflow_config' => "
        CREATE TABLE IF NOT EXISTS qr_workflow_config (
            id INT PRIMARY KEY AUTO_INCREMENT,
            workflow_name VARCHAR(100) NOT NULL,
            workflow_type ENUM('entrada', 'salida', 'conteo', 'movimiento', 'auditoria', 'transferencia', 'ajuste') NOT NULL,
            config_data JSON NOT NULL,
            validation_rules JSON NULL,
            ui_config JSON NULL,
            required_permissions JSON NULL,
            active BOOLEAN DEFAULT TRUE,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_workflow_type (workflow_type, active)
        )
    ",
    
    'qr_system_config' => "
        CREATE TABLE IF NOT EXISTS qr_system_config (
            id INT PRIMARY KEY AUTO_INCREMENT,
            config_key VARCHAR(100) NOT NULL UNIQUE,
            config_value JSON NOT NULL,
            config_description TEXT,
            active BOOLEAN DEFAULT TRUE,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_config_key (config_key)
        )
    ",
    
    'qr_physical_locations' => "
        CREATE TABLE IF NOT EXISTS qr_physical_locations (
            id INT PRIMARY KEY AUTO_INCREMENT,
            qr_code_id BIGINT NOT NULL,
            almacen_id INT NOT NULL,
            zona VARCHAR(50),
            pasillo VARCHAR(50),
            estante VARCHAR(50),
            nivel VARCHAR(50),
            posicion VARCHAR(50),
            coordinate_x DECIMAL(10,3),
            coordinate_y DECIMAL(10,3),
            coordinate_z DECIMAL(10,3),
            capacity_max INT DEFAULT 0,
            current_occupancy INT DEFAULT 0,
            location_type ENUM('shelf', 'floor', 'hanging', 'bulk', 'special', 'transit') DEFAULT 'shelf',
            active BOOLEAN DEFAULT TRUE,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_verified_at TIMESTAMP NULL,
            INDEX idx_almacen_zone (almacen_id, zona, pasillo),
            INDEX idx_active (active)
        )
    ",
    
    'qr_work_sessions' => "
        CREATE TABLE IF NOT EXISTS qr_work_sessions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            session_uuid VARCHAR(36) UNIQUE NOT NULL,
            session_type ENUM('bulk_scan', 'inventory_count', 'receiving', 'picking', 'audit') NOT NULL,
            user_id INT NOT NULL,
            almacen_id INT NOT NULL,
            status ENUM('active', 'paused', 'completed', 'cancelled', 'error') DEFAULT 'active',
            session_data JSON NOT NULL,
            progress_data JSON NULL,
            total_items_expected INT DEFAULT 0,
            total_items_processed INT DEFAULT 0,
            total_errors INT DEFAULT 0,
            started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_activity_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            INDEX idx_session_uuid (session_uuid),
            INDEX idx_user_sessions (user_id, started_at)
        )
    "
];

$created_count = 0;
foreach ($tables as $table_name => $sql) {
    try {
        if ($conn->query($sql)) {
            echo "✅ Tabla $table_name creada correctamente\n";
            $created_count++;
        } else {
            echo "❌ Error creando tabla $table_name: " . $conn->error . "\n";
        }
    } catch (Exception $e) {
        echo "❌ Excepción creando tabla $table_name: " . $e->getMessage() . "\n";
    }
}

echo "\n📊 Tablas creadas: $created_count de " . count($tables) . "\n";

// Insertar configuraciones iniciales
echo "\n🔧 Insertando configuraciones iniciales...\n";

$configs = [
    [
        'config_key' => 'qr_generation_format',
        'config_value' => '{"prefix": "SEQ", "include_year": true, "include_checksum": true, "separator": "-"}',
        'config_description' => 'Formato para generación de códigos QR'
    ],
    [
        'config_key' => 'qr_default_size', 
        'config_value' => '{"pixels": 400, "margin": 20, "error_correction": "H"}',
        'config_description' => 'Configuración por defecto para QR generados'
    ],
    [
        'config_key' => 'scan_validation_rules',
        'config_value' => '{"max_scan_frequency": 1000, "duplicate_scan_window": 5000, "require_location": false}',
        'config_description' => 'Reglas de validación para escaneos'
    ]
];

$config_count = 0;
foreach ($configs as $config) {
    $stmt = $conn->prepare("INSERT IGNORE INTO qr_system_config (config_key, config_value, config_description, created_by) VALUES (?, ?, ?, 1)");
    if ($stmt && $stmt->bind_param('sss', $config['config_key'], $config['config_value'], $config['config_description']) && $stmt->execute()) {
        echo "✅ Configuración {$config['config_key']} insertada\n";
        $config_count++;
    } else {
        echo "❌ Error insertando configuración {$config['config_key']}\n";
    }
}

// Insertar workflows iniciales
echo "\n⚙️ Insertando workflows iniciales...\n";

$workflows = [
    [
        'workflow_name' => 'Recepción Estándar',
        'workflow_type' => 'entrada',
        'config_data' => '{"steps": [{"name": "scan_product", "required": true}, {"name": "verify_quantity", "required": true}], "auto_create_movement": true}',
        'validation_rules' => '{"require_product_validation": true, "allow_new_products": false, "require_quantity": true}'
    ],
    [
        'workflow_name' => 'Salida para Pedido', 
        'workflow_type' => 'salida',
        'config_data' => '{"steps": [{"name": "scan_product", "required": true}, {"name": "verify_stock", "required": true}], "auto_create_movement": true}',
        'validation_rules' => '{"require_stock_validation": true, "prevent_negative_stock": true, "require_quantity": true}'
    ],
    [
        'workflow_name' => 'Conteo de Inventario',
        'workflow_type' => 'conteo', 
        'config_data' => '{"steps": [{"name": "scan_products", "required": true}, {"name": "compare_system", "required": true}], "auto_create_adjustment": true}',
        'validation_rules' => '{"allow_discrepancies": true, "create_alerts_on_discrepancy": true}'
    ]
];

$workflow_count = 0;
foreach ($workflows as $workflow) {
    $stmt = $conn->prepare("INSERT IGNORE INTO qr_workflow_config (workflow_name, workflow_type, config_data, validation_rules, created_by) VALUES (?, ?, ?, ?, 1)");
    if ($stmt && $stmt->bind_param('ssss', $workflow['workflow_name'], $workflow['workflow_type'], $workflow['config_data'], $workflow['validation_rules']) && $stmt->execute()) {
        echo "✅ Workflow {$workflow['workflow_name']} insertado\n";
        $workflow_count++;
    } else {
        echo "❌ Error insertando workflow {$workflow['workflow_name']}\n";
    }
}

echo "\n🎉 Instalación completada:\n";
echo "- Tablas creadas: $created_count\n"; 
echo "- Configuraciones: $config_count\n";
echo "- Workflows: $workflow_count\n";
echo "✅ Sistema QR listo para usar!\n";
?>