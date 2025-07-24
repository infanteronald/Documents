<?php
/**
 * Instalar Sistema QR - Versión Corregida
 * Compatible con tablas acc_ del sistema de accesos migrando
 */

defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once dirname(__DIR__) . '/config_secure.php';

echo "🚀 INSTALANDO SISTEMA QR (VERSIÓN CORREGIDA)\n";
echo "=============================================\n\n";

$conn->autocommit(false);

try {
    // 1. Verificar si ya existe el módulo QR
    echo "🔍 1. Verificando módulo QR existente...\n";
    $check_module = $conn->query("SELECT id FROM acc_modulos WHERE nombre = 'qr'");
    if ($check_module->num_rows > 0) {
        echo "   ✅ Módulo QR ya existe, continuando con tablas...\n\n";
    } else {
        echo "   ⚠️ Módulo QR no existe, debe ejecutar crear_modulo_qr_corregido.php primero\n";
        echo "   🔄 Ejecutando creación de módulo automáticamente...\n";
        
        // Ejecutar creación de módulo
        include dirname(__DIR__) . '/accesos/crear_modulo_qr_corregido.php';
        echo "\n";
    }
    
    // 2. Crear tablas QR principales
    echo "📦 2. Creando tablas del sistema QR...\n";
    
    // Tabla principal de códigos QR
    $qr_codes_sql = "
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
        INDEX idx_scan_analytics (scan_count, last_scanned_at),
        INDEX idx_linked_entities (linked_product_id, linked_almacen_id),
        INDEX idx_active (active),
        
        FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE CASCADE,
        FOREIGN KEY (linked_product_id) REFERENCES productos(id) ON DELETE SET NULL,
        FOREIGN KEY (linked_almacen_id) REFERENCES almacenes(id) ON DELETE SET NULL
    )";
    
    if ($conn->query($qr_codes_sql)) {
        echo "   ✅ Tabla qr_codes creada\n";
    } else {
        echo "   ⚠️ Error creando qr_codes: " . $conn->error . "\n";
    }
    
    // Tabla de transacciones de escaneo
    $qr_transactions_sql = "
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
        warning_message TEXT NULL,
        
        scan_duration_ms INT,
        processing_duration_ms INT,
        
        generated_movement_id INT NULL,
        generated_alert_id INT NULL,
        
        offline_processed BOOLEAN DEFAULT FALSE,
        synced_at TIMESTAMP NULL,
        
        workflow_type VARCHAR(50) NULL,
        workflow_step INT NULL,
        workflow_session_id VARCHAR(36) NULL,
        
        scanned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX idx_scan_time (scanned_at),
        INDEX idx_user_scans (user_id, scanned_at),
        INDEX idx_qr_analytics (qr_code_id, action_performed, scanned_at),
        INDEX idx_sync_status (offline_processed, synced_at),
        INDEX idx_processing_status (processing_status),
        INDEX idx_workflow (workflow_type, workflow_session_id),
        
        FOREIGN KEY (qr_code_id) REFERENCES qr_codes(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($qr_transactions_sql)) {
        echo "   ✅ Tabla qr_scan_transactions creada\n";
    } else {
        echo "   ⚠️ Error creando qr_scan_transactions: " . $conn->error . "\n";
    }
    
    // Tabla de configuración del sistema QR
    $qr_config_sql = "
    CREATE TABLE IF NOT EXISTS qr_system_config (
        id INT PRIMARY KEY AUTO_INCREMENT,
        config_key VARCHAR(100) NOT NULL UNIQUE,
        config_value JSON NOT NULL,
        config_description TEXT,
        
        active BOOLEAN DEFAULT TRUE,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_config_key (config_key),
        INDEX idx_active (active),
        
        FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($qr_config_sql)) {
        echo "   ✅ Tabla qr_system_config creada\n";
    } else {
        echo "   ⚠️ Error creando qr_system_config: " . $conn->error . "\n";
    }
    
    // 3. Verificar/crear tabla de movimientos_inventario si no existe
    echo "\n📈 3. Verificando tabla movimientos_inventario...\n";
    $check_movements = $conn->query("SHOW TABLES LIKE 'movimientos_inventario'");
    if ($check_movements->num_rows == 0) {
        echo "   ⚠️ Tabla movimientos_inventario no existe, creándola...\n";
        $movements_sql = "
        CREATE TABLE IF NOT EXISTS movimientos_inventario (
            id INT PRIMARY KEY AUTO_INCREMENT,
            producto_id INT NOT NULL,
            almacen_id INT NOT NULL,
            tipo_movimiento ENUM('entrada', 'salida', 'ajuste', 'transferencia') NOT NULL,
            cantidad INT NOT NULL,
            cantidad_anterior INT DEFAULT 0,
            cantidad_nueva INT DEFAULT 0,
            motivo VARCHAR(255),
            documento_referencia VARCHAR(100),
            usuario_responsable INT NOT NULL,
            observaciones TEXT,
            fecha_movimiento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            INDEX idx_producto (producto_id),
            INDEX idx_almacen (almacen_id),
            INDEX idx_tipo (tipo_movimiento),
            INDEX idx_fecha (fecha_movimiento),
            
            FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
            FOREIGN KEY (almacen_id) REFERENCES almacenes(id) ON DELETE CASCADE,
            FOREIGN KEY (usuario_responsable) REFERENCES usuarios(id) ON DELETE CASCADE
        )";
        
        if ($conn->query($movements_sql)) {
            echo "   ✅ Tabla movimientos_inventario creada\n";
        } else {
            echo "   ⚠️ Error creando movimientos_inventario: " . $conn->error . "\n";
        }
    } else {
        echo "   ✅ Tabla movimientos_inventario ya existe\n";
    }
    
    // 4. Verificar/crear tabla inventario_almacen si no existe
    echo "\n📦 4. Verificando tabla inventario_almacen...\n";
    $check_inventory = $conn->query("SHOW TABLES LIKE 'inventario_almacen'");
    if ($check_inventory->num_rows == 0) {
        echo "   ⚠️ Tabla inventario_almacen no existe, creándola...\n";
        $inventory_sql = "
        CREATE TABLE IF NOT EXISTS inventario_almacen (
            id INT PRIMARY KEY AUTO_INCREMENT,
            producto_id INT NOT NULL,
            almacen_id INT NOT NULL,
            stock_actual INT DEFAULT 0,
            stock_minimo INT DEFAULT 0,
            stock_maximo INT DEFAULT 0,
            ubicacion_fisica VARCHAR(100),
            fecha_ultima_entrada TIMESTAMP NULL,
            fecha_ultima_salida TIMESTAMP NULL,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            UNIQUE KEY unique_producto_almacen (producto_id, almacen_id),
            INDEX idx_producto (producto_id),
            INDEX idx_almacen (almacen_id),
            INDEX idx_stock (stock_actual),
            
            FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
            FOREIGN KEY (almacen_id) REFERENCES almacenes(id) ON DELETE CASCADE
        )";
        
        if ($conn->query($inventory_sql)) {
            echo "   ✅ Tabla inventario_almacen creada\n";
        } else {
            echo "   ⚠️ Error creando inventario_almacen: " . $conn->error . "\n";
        }
    } else {
        echo "   ✅ Tabla inventario_almacen ya existe\n";
    }
    
    // 5. Insertar configuraciones por defecto
    echo "\n⚙️ 5. Insertando configuraciones por defecto...\n";
    $default_configs = [
        ['qr_generation_format', '{"prefix": "SEQ", "include_year": true, "include_checksum": true, "separator": "-"}', 'Formato para generación de códigos QR'],
        ['qr_default_size', '{"pixels": 400, "margin": 20, "error_correction": "H"}', 'Configuración por defecto para QR generados'],
        ['scan_validation_rules', '{"max_scan_frequency": 1000, "duplicate_scan_window": 5000, "require_location": false}', 'Reglas de validación para escaneos']
    ];
    
    foreach ($default_configs as $config) {
        $check_config = $conn->prepare("SELECT id FROM qr_system_config WHERE config_key = ?");
        $check_config->bind_param('s', $config[0]);
        $check_config->execute();
        
        if ($check_config->get_result()->num_rows == 0) {
            $insert_config = $conn->prepare("INSERT INTO qr_system_config (config_key, config_value, config_description, created_by) VALUES (?, ?, ?, 1)");
            $insert_config->bind_param('sss', $config[0], $config[1], $config[2]);
            if ($insert_config->execute()) {
                echo "   ✅ Configuración '{$config[0]}' insertada\n";
            } else {
                echo "   ⚠️ Error insertando configuración '{$config[0]}': " . $conn->error . "\n";
            }
        } else {
            echo "   ℹ️ Configuración '{$config[0]}' ya existe\n";
        }
    }
    
    // 6. Commit de la transacción
    $conn->commit();
    echo "\n🎉 ¡SISTEMA QR INSTALADO EXITOSAMENTE!\n\n";
    
    // 7. Verificación final
    echo "🔍 Verificación final:\n";
    $tables = ['qr_codes', 'qr_scan_transactions', 'qr_system_config', 'movimientos_inventario', 'inventario_almacen'];
    
    foreach ($tables as $table) {
        $check = $conn->query("SHOW TABLES LIKE '$table'");
        if ($check->num_rows > 0) {
            $count = $conn->query("SELECT COUNT(*) as total FROM $table")->fetch_assoc();
            echo "   ✅ $table: {$count['total']} registros\n";
        } else {
            echo "   ❌ $table: NO EXISTE\n";
        }
    }
    
    echo "\n✅ El sistema QR está listo para usar.\n";
    echo "🌐 Accede a: https://sequoiaspeed.com.co/pedidos/qr/\n";
    
} catch (Exception $e) {
    $conn->rollback();
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📋 Error SQL: " . $conn->error . "\n";
} finally {
    $conn->autocommit(true);
    $conn->close();
}
?>