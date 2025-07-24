-- ================================================
-- PARCHES DE SEGURIDAD - SISTEMA QR
-- Sequoia Speed - Correcciones Críticas
-- ================================================

-- Verificar que las tablas principales existan antes de crear foreign keys
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

-- 1. CORREGIR FOREIGN KEYS EN qr_codes
-- Eliminar foreign keys existentes si existen
ALTER TABLE qr_codes DROP FOREIGN KEY IF EXISTS qr_codes_ibfk_1;
ALTER TABLE qr_codes DROP FOREIGN KEY IF EXISTS qr_codes_ibfk_2;
ALTER TABLE qr_codes DROP FOREIGN KEY IF EXISTS qr_codes_ibfk_3;
ALTER TABLE qr_codes DROP FOREIGN KEY IF EXISTS qr_codes_ibfk_4;

-- Verificar existencia de tablas antes de crear foreign keys
SELECT COUNT(*) INTO @usuarios_exists FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios';

SELECT COUNT(*) INTO @productos_exists FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'productos';

SELECT COUNT(*) INTO @almacenes_exists FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'almacenes';

SELECT COUNT(*) INTO @inventario_exists FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventario_almacen';

-- Crear foreign keys solo si las tablas existen
SET @sql = IF(@usuarios_exists > 0, 
    'ALTER TABLE qr_codes ADD CONSTRAINT fk_qr_created_by FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE CASCADE',
    'SELECT "Tabla usuarios no existe - foreign key omitida" as warning');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(@productos_exists > 0, 
    'ALTER TABLE qr_codes ADD CONSTRAINT fk_qr_product FOREIGN KEY (linked_product_id) REFERENCES productos(id) ON DELETE SET NULL',
    'SELECT "Tabla productos no existe - foreign key omitida" as warning');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(@almacenes_exists > 0, 
    'ALTER TABLE qr_codes ADD CONSTRAINT fk_qr_almacen FOREIGN KEY (linked_almacen_id) REFERENCES almacenes(id) ON DELETE SET NULL',
    'SELECT "Tabla almacenes no existe - foreign key omitida" as warning');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(@inventario_exists > 0, 
    'ALTER TABLE qr_codes ADD CONSTRAINT fk_qr_inventory FOREIGN KEY (linked_inventory_id) REFERENCES inventario_almacen(id) ON DELETE SET NULL',
    'SELECT "Tabla inventario_almacen no existe - foreign key omitida" as warning');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. CORREGIR FOREIGN KEYS EN qr_scan_transactions
ALTER TABLE qr_scan_transactions DROP FOREIGN KEY IF EXISTS qr_scan_transactions_ibfk_1;
ALTER TABLE qr_scan_transactions DROP FOREIGN KEY IF EXISTS qr_scan_transactions_ibfk_2;
ALTER TABLE qr_scan_transactions DROP FOREIGN KEY IF EXISTS qr_scan_transactions_ibfk_3;

-- Recrear con manejo de errores
SET @sql = IF(@usuarios_exists > 0, 
    'ALTER TABLE qr_scan_transactions ADD CONSTRAINT fk_scan_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE',
    'SELECT "Tabla usuarios no existe - foreign key omitida" as warning');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Foreign key para qr_codes (debe existir)
ALTER TABLE qr_scan_transactions ADD CONSTRAINT fk_scan_qr_code 
FOREIGN KEY (qr_code_id) REFERENCES qr_codes(id) ON DELETE CASCADE;

-- 3. VERIFICAR Y CORREGIR INDICES DUPLICADOS
-- Eliminar indices duplicados si existen
ALTER TABLE qr_codes DROP INDEX IF EXISTS idx_qr_content_duplicate;
ALTER TABLE qr_codes DROP INDEX IF EXISTS idx_qr_uuid_duplicate;

-- Verificar que los indices principales existan
SHOW INDEX FROM qr_codes WHERE Key_name = 'idx_qr_content';
SHOW INDEX FROM qr_codes WHERE Key_name = 'idx_qr_uuid';

-- 4. AGREGAR CAMPOS DE AUDITORIA FALTANTES
-- Verificar si existen campos de auditoria, si no, agregarlos
SET @sql = (SELECT CASE 
    WHEN COUNT(*) = 0 THEN 
        'ALTER TABLE qr_scan_transactions ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
    ELSE 'SELECT "Campo created_at ya existe" as info'
END
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'qr_scan_transactions' 
AND COLUMN_NAME = 'created_at');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. CORREGIR CONFIGURACIONES BÁSICAS CON VALIDACIÓN
-- Insertar configuraciones solo si no existen
INSERT IGNORE INTO qr_system_config (config_key, config_value, config_description, created_by, created_at) 
VALUES 
('qr_generation_format', 
 '{"prefix": "SEQ", "include_year": true, "include_checksum": true, "separator": "-", "validation": true}', 
 'Formato seguro para generación de códigos QR con validación', 
 1, NOW()),

('qr_security_config', 
 '{"max_scans_per_minute": 60, "require_csrf": true, "log_all_actions": true, "validate_permissions": true}', 
 'Configuración de seguridad para sistema QR', 
 1, NOW()),

('qr_validation_rules', 
 '{"min_content_length": 5, "max_content_length": 255, "allowed_characters": "alphanumeric_dash", "require_checksum": true}', 
 'Reglas de validación para códigos QR', 
 1, NOW());

-- 6. CREAR TABLA DE LOGS DE SEGURIDAD SI NO EXISTE
CREATE TABLE IF NOT EXISTS qr_security_logs (
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
);

-- 7. VERIFICAR PERMISOS RBAC PARA QR
-- Insertar módulo QR si no existe
INSERT IGNORE INTO modulos (nombre, descripcion, activo, created_at) 
VALUES ('qr', 'Sistema de códigos QR para inventario', 1, NOW());

-- Obtener ID del módulo QR
SET @qr_module_id = (SELECT id FROM modulos WHERE nombre = 'qr' LIMIT 1);

-- Insertar permisos básicos si no existen
INSERT IGNORE INTO permisos (modulo_id, tipo_permiso, descripcion, created_at) VALUES
(@qr_module_id, 'leer', 'Ver códigos QR y estadísticas', NOW()),
(@qr_module_id, 'crear', 'Generar nuevos códigos QR', NOW()),
(@qr_module_id, 'actualizar', 'Modificar códigos QR existentes', NOW()),
(@qr_module_id, 'eliminar', 'Eliminar códigos QR', NOW()),
(@qr_module_id, 'escanear', 'Escanear códigos QR', NOW()),
(@qr_module_id, 'reportes', 'Ver reportes y analytics de QR', NOW());

-- 8. LIMPIAR DATOS INCONSISTENTES
-- Eliminar registros huérfanos en qr_scan_transactions
DELETE qst FROM qr_scan_transactions qst 
LEFT JOIN qr_codes qc ON qst.qr_code_id = qc.id 
WHERE qc.id IS NULL;

-- Eliminar códigos QR con referencias inválidas a productos
UPDATE qr_codes SET linked_product_id = NULL 
WHERE linked_product_id IS NOT NULL 
AND linked_product_id NOT IN (SELECT id FROM productos);

-- Eliminar códigos QR con referencias inválidas a almacenes  
UPDATE qr_codes SET linked_almacen_id = NULL 
WHERE linked_almacen_id IS NOT NULL 
AND linked_almacen_id NOT IN (SELECT id FROM almacenes);

-- 9. CREAR VISTA PARA MONITOREO DE SEGURIDAD
CREATE OR REPLACE VIEW vista_qr_security_monitor AS
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
    COUNT(CASE WHEN processing_status = 'failed' THEN 1 END) as inactive_records
FROM qr_scan_transactions;

-- 10. TRIGGER DE SEGURIDAD PARA DETECTAR ACTIVIDAD SOSPECHOSA
DELIMITER //

CREATE TRIGGER IF NOT EXISTS qr_security_trigger
    AFTER INSERT ON qr_scan_transactions
    FOR EACH ROW
BEGIN
    -- Detectar múltiples escaneos del mismo usuario en poco tiempo
    DECLARE scan_count INT DEFAULT 0;
    
    SELECT COUNT(*) INTO scan_count
    FROM qr_scan_transactions 
    WHERE user_id = NEW.user_id 
    AND scanned_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE);
    
    -- Si hay más de 20 escaneos en 1 minuto, loguear como sospechoso
    IF scan_count > 20 THEN
        INSERT INTO qr_security_logs (
            event_type, user_id, ip_address, 
            request_data, severity, created_at
        ) VALUES (
            'rate_limit_exceeded', NEW.user_id, 'system',
            JSON_OBJECT('scan_count', scan_count, 'qr_code_id', NEW.qr_code_id),
            'high', NOW()
        );
    END IF;
END//

DELIMITER ;

-- Restaurar configuración de foreign keys
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;

-- MENSAJE FINAL
SELECT 'Parches de seguridad aplicados correctamente' as status, NOW() as applied_at;

-- VERIFICACIÓN FINAL
SELECT 
    'VERIFICACIÓN FINAL' as phase,
    (SELECT COUNT(*) FROM qr_codes) as qr_codes_count,
    (SELECT COUNT(*) FROM qr_scan_transactions) as transactions_count,
    (SELECT COUNT(*) FROM qr_system_config WHERE active = 1) as active_configs,
    (SELECT COUNT(*) FROM modulos WHERE nombre = 'qr') as qr_module_exists;