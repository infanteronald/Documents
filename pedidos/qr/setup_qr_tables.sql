-- ================================================
-- SISTEMA QR - SEQUOIA SPEED
-- Script de Creación de Tablas
-- ================================================

-- Tabla principal de códigos QR
CREATE TABLE IF NOT EXISTS qr_codes (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    qr_uuid VARCHAR(36) UNIQUE NOT NULL,
    qr_content VARCHAR(255) UNIQUE NOT NULL, -- El string del QR
    
    -- Tipo y referencia
    entity_type ENUM('producto', 'ubicacion', 'lote', 'pedido', 'almacen') NOT NULL,
    entity_id VARCHAR(100) NOT NULL,
    
    -- Metadatos del QR
    error_correction_level ENUM('L', 'M', 'Q', 'H') DEFAULT 'H',
    size_pixels INT DEFAULT 400,
    
    -- Datos contextuales (JSON compacto)
    base_data JSON NOT NULL,
    context_rules JSON NULL,
    
    -- Analytics básicos
    scan_count INT DEFAULT 0,
    first_scanned_at TIMESTAMP NULL,
    last_scanned_at TIMESTAMP NULL,
    
    -- Integración con sistema Sequoia
    linked_inventory_id INT NULL, -- Link a inventario_almacen
    linked_product_id INT NULL,   -- Link a productos  
    linked_almacen_id INT NULL,   -- Link a almacenes
    
    -- Control
    active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_qr_content (qr_content),
    INDEX idx_qr_uuid (qr_uuid),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_scan_analytics (scan_count, last_scanned_at),
    INDEX idx_linked_entities (linked_product_id, linked_almacen_id),
    INDEX idx_active (active),
    
    -- Foreign Keys
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (linked_inventory_id) REFERENCES inventario_almacen(id) ON DELETE SET NULL,
    FOREIGN KEY (linked_product_id) REFERENCES productos(id) ON DELETE SET NULL,
    FOREIGN KEY (linked_almacen_id) REFERENCES almacenes(id) ON DELETE SET NULL
);

-- Tabla de transacciones de escaneo
CREATE TABLE IF NOT EXISTS qr_scan_transactions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    transaction_uuid VARCHAR(36) UNIQUE NOT NULL,
    qr_code_id BIGINT NOT NULL,
    
    -- Contexto del escaneo
    user_id INT NOT NULL,
    scan_method ENUM('camera_web', 'camera_mobile', 'scanner_device', 'manual_input') DEFAULT 'camera_mobile',
    device_info JSON,
    scan_location VARCHAR(100),
    location_coordinates POINT NULL,
    
    -- Acción realizada
    action_performed ENUM('consulta', 'entrada', 'salida', 'conteo', 'movimiento', 'ajuste', 'transferencia') NOT NULL,
    quantity_affected INT DEFAULT 1,
    notes TEXT,
    
    -- Estados y resultados
    processing_status ENUM('success', 'failed', 'pending', 'warning') DEFAULT 'success',
    error_message TEXT NULL,
    warning_message TEXT NULL,
    
    -- Performance metrics
    scan_duration_ms INT,
    processing_duration_ms INT,
    
    -- Integración con sistema existente
    generated_movement_id INT NULL, -- Link a movimientos_inventario si se creó
    generated_alert_id INT NULL,    -- Link a alertas_inventario si se creó
    
    -- Sincronización offline
    offline_processed BOOLEAN DEFAULT FALSE,
    synced_at TIMESTAMP NULL,
    
    -- Workflow context
    workflow_type VARCHAR(50) NULL,
    workflow_step INT NULL,
    workflow_session_id VARCHAR(36) NULL,
    
    scanned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_scan_time (scanned_at),
    INDEX idx_user_scans (user_id, scanned_at),
    INDEX idx_qr_analytics (qr_code_id, action_performed, scanned_at),
    INDEX idx_sync_status (offline_processed, synced_at),
    INDEX idx_processing_status (processing_status),
    INDEX idx_workflow (workflow_type, workflow_session_id),
    
    -- Foreign Keys
    FOREIGN KEY (qr_code_id) REFERENCES qr_codes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (generated_movement_id) REFERENCES movimientos_inventario(id) ON DELETE SET NULL,
    FOREIGN KEY (generated_alert_id) REFERENCES alertas_inventario(id) ON DELETE SET NULL
);

-- Tabla de configuración de workflows QR
CREATE TABLE IF NOT EXISTS qr_workflow_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    workflow_name VARCHAR(100) NOT NULL,
    workflow_type ENUM('entrada', 'salida', 'conteo', 'movimiento', 'auditoria', 'transferencia', 'ajuste') NOT NULL,
    
    -- Configuración del workflow
    config_data JSON NOT NULL,
    
    -- Reglas de validación
    validation_rules JSON NULL,
    
    -- UI personalizada
    ui_config JSON NULL,
    
    -- Permisos requeridos
    required_permissions JSON NULL,
    
    -- Control
    active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_workflow_type (workflow_type, active),
    INDEX idx_workflow_name (workflow_name),
    
    -- Foreign Keys
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabla de configuración general del sistema QR
CREATE TABLE IF NOT EXISTS qr_system_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value JSON NOT NULL,
    config_description TEXT,
    
    -- Control
    active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_config_key (config_key),
    INDEX idx_active (active),
    
    -- Foreign Keys
    FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabla de ubicaciones físicas con QR
CREATE TABLE IF NOT EXISTS qr_physical_locations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    qr_code_id BIGINT NOT NULL,
    
    -- Ubicación física
    almacen_id INT NOT NULL,
    zona VARCHAR(50),
    pasillo VARCHAR(50),
    estante VARCHAR(50),
    nivel VARCHAR(50),
    posicion VARCHAR(50),
    
    -- Coordinates dentro del almacén
    coordinate_x DECIMAL(10,3),
    coordinate_y DECIMAL(10,3),
    coordinate_z DECIMAL(10,3),
    
    -- Metadatos de ubicación
    capacity_max INT DEFAULT 0,
    current_occupancy INT DEFAULT 0,
    location_type ENUM('shelf', 'floor', 'hanging', 'bulk', 'special', 'transit') DEFAULT 'shelf',
    
    -- Condiciones ambientales
    temperature_controlled BOOLEAN DEFAULT FALSE,
    humidity_controlled BOOLEAN DEFAULT FALSE,
    security_level ENUM('public', 'restricted', 'secured', 'classified') DEFAULT 'public',
    
    -- Control
    active BOOLEAN DEFAULT TRUE,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_verified_at TIMESTAMP NULL,
    
    -- Índices
    INDEX idx_almacen_zone (almacen_id, zona, pasillo),
    INDEX idx_coordinates (coordinate_x, coordinate_y, coordinate_z),
    INDEX idx_location_type (location_type),
    INDEX idx_active (active),
    
    -- Foreign Keys
    FOREIGN KEY (qr_code_id) REFERENCES qr_codes(id) ON DELETE CASCADE,
    FOREIGN KEY (almacen_id) REFERENCES almacenes(id) ON DELETE CASCADE,
    
    -- Constraint única
    UNIQUE KEY unique_qr_location (qr_code_id)
);

-- Tabla de sesiones de trabajo QR (para workflows largos)
CREATE TABLE IF NOT EXISTS qr_work_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_uuid VARCHAR(36) UNIQUE NOT NULL,
    
    -- Información de la sesión
    session_type ENUM('bulk_scan', 'inventory_count', 'receiving', 'picking', 'audit') NOT NULL,
    user_id INT NOT NULL,
    almacen_id INT NOT NULL,
    
    -- Estado de la sesión
    status ENUM('active', 'paused', 'completed', 'cancelled', 'error') DEFAULT 'active',
    
    -- Datos de la sesión
    session_data JSON NOT NULL,
    progress_data JSON NULL,
    
    -- Métricas de rendimiento
    total_items_expected INT DEFAULT 0,
    total_items_processed INT DEFAULT 0,
    total_errors INT DEFAULT 0,
    
    -- Timestamps
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    
    -- Índices
    INDEX idx_session_uuid (session_uuid),
    INDEX idx_user_sessions (user_id, started_at),
    INDEX idx_session_type (session_type, status),
    INDEX idx_almacen_sessions (almacen_id, session_type),
    
    -- Foreign Keys
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (almacen_id) REFERENCES almacenes(id) ON DELETE CASCADE
);

-- ================================================
-- VISTAS PARA ANALYTICS Y REPORTES
-- ================================================

-- Vista para analytics de QR
CREATE OR REPLACE VIEW vista_qr_analytics AS
SELECT 
    qc.id,
    qc.qr_content,
    qc.entity_type,
    qc.entity_id,
    qc.scan_count,
    qc.last_scanned_at,
    qc.created_at,
    
    -- Datos del producto/entidad vinculada
    CASE 
        WHEN qc.entity_type = 'producto' THEN p.nombre
        WHEN qc.entity_type = 'ubicacion' THEN CONCAT(a.codigo, '-', JSON_EXTRACT(qc.base_data, '$.zona'))
        WHEN qc.entity_type = 'almacen' THEN a.nombre
        ELSE qc.entity_id
    END as entity_name,
    
    -- Analytics calculados
    COUNT(qst.id) as total_transactions,
    COUNT(CASE WHEN qst.action_performed = 'entrada' THEN 1 END) as entradas_count,
    COUNT(CASE WHEN qst.action_performed = 'salida' THEN 1 END) as salidas_count,
    COUNT(CASE WHEN qst.action_performed = 'conteo' THEN 1 END) as conteos_count,
    COUNT(CASE WHEN qst.processing_status = 'failed' THEN 1 END) as failed_scans,
    
    AVG(qst.processing_duration_ms) as avg_processing_time,
    MAX(qst.scanned_at) as last_transaction_at,
    
    -- Información del creador
    u.nombre as created_by_name

FROM qr_codes qc
LEFT JOIN productos p ON qc.linked_product_id = p.id
LEFT JOIN almacenes a ON qc.linked_almacen_id = a.id
LEFT JOIN usuarios u ON qc.created_by = u.id
LEFT JOIN qr_scan_transactions qst ON qc.id = qst.qr_code_id
WHERE qc.active = 1
GROUP BY qc.id;

-- Vista para actividad en tiempo real
CREATE OR REPLACE VIEW vista_qr_realtime_activity AS
SELECT 
    qst.transaction_uuid,
    qc.qr_content,
    qc.entity_type,
    qst.action_performed,
    qst.quantity_affected,
    qst.scanned_at,
    qst.scan_location,
    qst.processing_status,
    qst.processing_duration_ms,
    
    u.nombre as user_name,
    u.usuario as username,
    
    CASE 
        WHEN qc.entity_type = 'producto' THEN p.nombre
        WHEN qc.entity_type = 'ubicacion' THEN JSON_EXTRACT(qc.base_data, '$.zona')
        WHEN qc.entity_type = 'almacen' THEN a.nombre
        ELSE qc.entity_id
    END as entity_name,
    
    -- Información del almacén
    a.nombre as almacen_name,
    a.codigo as almacen_codigo

FROM qr_scan_transactions qst
JOIN qr_codes qc ON qst.qr_code_id = qc.id
JOIN usuarios u ON qst.user_id = u.id
LEFT JOIN productos p ON qc.linked_product_id = p.id
LEFT JOIN almacenes a ON qc.linked_almacen_id = a.id
WHERE qst.scanned_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY qst.scanned_at DESC;

-- ================================================
-- CONFIGURACIÓN INICIAL DEL SISTEMA
-- ================================================

-- Configuraciones por defecto
INSERT IGNORE INTO qr_system_config (config_key, config_value, config_description, created_by) VALUES
('qr_generation_format', '{"prefix": "SEQ", "include_year": true, "include_checksum": true, "separator": "-"}', 'Formato para generación de códigos QR', 1),
('qr_default_size', '{"pixels": 400, "margin": 20, "error_correction": "H"}', 'Configuración por defecto para QR generados', 1),
('scan_validation_rules', '{"max_scan_frequency": 1000, "duplicate_scan_window": 5000, "require_location": false}', 'Reglas de validación para escaneos', 1),
('offline_sync_config', '{"max_offline_transactions": 1000, "sync_batch_size": 50, "max_retry_attempts": 3}', 'Configuración para sincronización offline', 1),
('analytics_retention', '{"transaction_retention_days": 365, "session_retention_days": 90, "cleanup_frequency": "weekly"}', 'Configuración de retención de datos analytics', 1);

-- Workflows por defecto
INSERT IGNORE INTO qr_workflow_config (workflow_name, workflow_type, config_data, validation_rules, created_by) VALUES
('Recepción Estándar', 'entrada', 
 '{"steps": [{"name": "scan_product", "required": true}, {"name": "verify_quantity", "required": true}, {"name": "assign_location", "required": false}], "auto_create_movement": true}', 
 '{"require_product_validation": true, "allow_new_products": false, "require_quantity": true}', 
 1),
('Salida para Pedido', 'salida', 
 '{"steps": [{"name": "scan_product", "required": true}, {"name": "verify_stock", "required": true}, {"name": "confirm_quantity", "required": true}], "auto_create_movement": true}', 
 '{"require_stock_validation": true, "prevent_negative_stock": true, "require_quantity": true}', 
 1),
('Conteo de Inventario', 'conteo', 
 '{"steps": [{"name": "scan_location", "required": false}, {"name": "scan_products", "required": true}, {"name": "compare_system", "required": true}], "auto_create_adjustment": true}', 
 '{"allow_discrepancies": true, "require_supervisor_approval": false, "create_alerts_on_discrepancy": true}', 
 1);

-- ================================================
-- TRIGGERS PARA AUDITORÍA Y SINCRONIZACIÓN
-- ================================================

DELIMITER //

-- Trigger para actualizar contador de escaneos en qr_codes
CREATE TRIGGER IF NOT EXISTS update_qr_scan_count
    AFTER INSERT ON qr_scan_transactions
    FOR EACH ROW
BEGIN
    UPDATE qr_codes 
    SET scan_count = scan_count + 1,
        last_scanned_at = NEW.scanned_at,
        first_scanned_at = COALESCE(first_scanned_at, NEW.scanned_at)
    WHERE id = NEW.qr_code_id;
END//

-- Trigger para log de cambios en configuración
CREATE TRIGGER IF NOT EXISTS log_qr_config_changes
    AFTER UPDATE ON qr_system_config
    FOR EACH ROW
BEGIN
    INSERT INTO qr_scan_transactions (
        transaction_uuid, qr_code_id, user_id, action_performed, 
        notes, processing_status, scanned_at
    ) VALUES (
        UUID(), 0, NEW.created_by, 'configuracion',
        CONCAT('Configuración actualizada: ', NEW.config_key), 'success', NOW()
    );
END//

DELIMITER ;

-- ================================================
-- ÍNDICES ADICIONALES PARA PERFORMANCE
-- ================================================

-- Índices compuestos para consultas frecuentes
CREATE INDEX IF NOT EXISTS idx_qr_entity_active ON qr_codes(entity_type, entity_id, active);
CREATE INDEX IF NOT EXISTS idx_scan_user_date ON qr_scan_transactions(user_id, DATE(scanned_at));
CREATE INDEX IF NOT EXISTS idx_scan_action_status ON qr_scan_transactions(action_performed, processing_status);
CREATE INDEX IF NOT EXISTS idx_workflow_active_type ON qr_workflow_config(active, workflow_type);

-- ================================================
-- PERMISOS PARA EL MÓDULO QR
-- ================================================

-- Insertar nuevo módulo QR si no existe
INSERT IGNORE INTO modulos (nombre, descripcion, activo) 
VALUES ('qr', 'Sistema de códigos QR para inventario', 1);

-- Insertar permisos específicos para QR
INSERT IGNORE INTO permisos (modulo_id, tipo_permiso, descripcion) 
SELECT m.id, 'leer', 'Ver códigos QR y estadísticas' 
FROM modulos m WHERE m.nombre = 'qr';

INSERT IGNORE INTO permisos (modulo_id, tipo_permiso, descripcion) 
SELECT m.id, 'crear', 'Generar nuevos códigos QR' 
FROM modulos m WHERE m.nombre = 'qr';

INSERT IGNORE INTO permisos (modulo_id, tipo_permiso, descripcion) 
SELECT m.id, 'actualizar', 'Modificar códigos QR existentes' 
FROM modulos m WHERE m.nombre = 'qr';

INSERT IGNORE INTO permisos (modulo_id, tipo_permiso, descripcion) 
SELECT m.id, 'eliminar', 'Eliminar códigos QR' 
FROM modulos m WHERE m.nombre = 'qr';

INSERT IGNORE INTO permisos (modulo_id, tipo_permiso, descripcion) 
SELECT m.id, 'escanear', 'Escanear códigos QR' 
FROM modulos m WHERE m.nombre = 'qr';

INSERT IGNORE INTO permisos (modulo_id, tipo_permiso, descripcion) 
SELECT m.id, 'reportes', 'Ver reportes y analytics de QR' 
FROM modulos m WHERE m.nombre = 'qr';

-- Asignar permisos básicos a roles existentes
INSERT IGNORE INTO rol_permisos (rol_id, permiso_id)
SELECT r.id, p.id 
FROM roles r 
CROSS JOIN permisos p 
JOIN modulos m ON p.modulo_id = m.id 
WHERE m.nombre = 'qr' 
AND r.nombre IN ('super_admin', 'admin', 'gerente');

-- ================================================
-- FINAL DEL SCRIPT
-- ================================================

-- Mensaje de confirmación
SELECT 'Sistema QR instalado correctamente' as status;