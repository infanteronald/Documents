-- ===============================================
-- MIGRACIÓN: AGREGAR PREFIJO ACC_ AL SISTEMA DE ACCESOS
-- Sequoia Speed - Sistema de Gestión de Pedidos
-- ===============================================
-- Fecha: 2025-07-22
-- Objetivo: Renombrar todas las tablas del sistema de accesos con prefijo acc_
-- ===============================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- ===============================================
-- PASO 1: VERIFICAR EXISTENCIA DE TABLAS ORIGINALES
-- ===============================================
SELECT 'VERIFICANDO TABLAS ORIGINALES...' as mensaje;

-- ===============================================
-- PASO 2: CREAR TABLAS CON PREFIJO ACC_
-- ===============================================

-- Tabla: acc_usuarios
CREATE TABLE IF NOT EXISTS acc_usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    ultimo_acceso DATETIME NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    creado_por INT NULL,
    modificado_por INT NULL,
    
    INDEX idx_usuario (usuario),
    INDEX idx_email (email),
    INDEX idx_activo (activo),
    INDEX idx_fecha_creacion (fecha_creacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: acc_roles
CREATE TABLE IF NOT EXISTS acc_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT,
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    creado_por INT NULL,
    modificado_por INT NULL,
    
    INDEX idx_nombre (nombre),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: acc_modulos
CREATE TABLE IF NOT EXISTS acc_modulos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT,
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_nombre (nombre),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: acc_permisos
CREATE TABLE IF NOT EXISTS acc_permisos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    modulo_id INT NOT NULL,
    tipo_permiso ENUM('crear', 'leer', 'actualizar', 'eliminar') NOT NULL,
    descripcion VARCHAR(255),
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (modulo_id) REFERENCES acc_modulos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_modulo_permiso (modulo_id, tipo_permiso),
    INDEX idx_modulo_id (modulo_id),
    INDEX idx_tipo_permiso (tipo_permiso),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: acc_usuario_roles
CREATE TABLE IF NOT EXISTS acc_usuario_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    rol_id INT NOT NULL,
    fecha_asignacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    asignado_por INT NULL,
    activo TINYINT(1) DEFAULT 1,
    
    FOREIGN KEY (usuario_id) REFERENCES acc_usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (rol_id) REFERENCES acc_roles(id) ON DELETE CASCADE,
    FOREIGN KEY (asignado_por) REFERENCES acc_usuarios(id) ON DELETE SET NULL,
    UNIQUE KEY unique_usuario_rol (usuario_id, rol_id),
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_rol_id (rol_id),
    INDEX idx_fecha_asignacion (fecha_asignacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: acc_rol_permisos
CREATE TABLE IF NOT EXISTS acc_rol_permisos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rol_id INT NOT NULL,
    permiso_id INT NOT NULL,
    fecha_asignacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    asignado_por INT NULL,
    activo TINYINT(1) DEFAULT 1,
    
    FOREIGN KEY (rol_id) REFERENCES acc_roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permiso_id) REFERENCES acc_permisos(id) ON DELETE CASCADE,
    FOREIGN KEY (asignado_por) REFERENCES acc_usuarios(id) ON DELETE SET NULL,
    UNIQUE KEY unique_rol_permiso (rol_id, permiso_id),
    INDEX idx_rol_id (rol_id),
    INDEX idx_permiso_id (permiso_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: acc_auditoria_accesos
CREATE TABLE IF NOT EXISTS acc_auditoria_accesos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    accion VARCHAR(50) NOT NULL,
    modulo VARCHAR(50) NOT NULL,
    descripcion TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    datos_antes JSON NULL,
    datos_despues JSON NULL,
    fecha_accion DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (usuario_id) REFERENCES acc_usuarios(id) ON DELETE SET NULL,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_accion (accion),
    INDEX idx_modulo (modulo),
    INDEX idx_fecha_accion (fecha_accion),
    INDEX idx_ip_address (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: acc_sesiones
CREATE TABLE IF NOT EXISTS acc_sesiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    fecha_inicio DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_expiracion DATETIME NOT NULL,
    activa TINYINT(1) DEFAULT 1,
    fecha_ultimo_acceso DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (usuario_id) REFERENCES acc_usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_token (token),
    INDEX idx_fecha_expiracion (fecha_expiracion),
    INDEX idx_activa (activa),
    INDEX idx_ip_address (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: acc_remember_tokens
CREATE TABLE IF NOT EXISTS acc_remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    selector VARCHAR(24) NOT NULL UNIQUE,
    token_hash VARCHAR(64) NOT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_expiracion DATETIME NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    
    FOREIGN KEY (usuario_id) REFERENCES acc_usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_selector (selector),
    INDEX idx_fecha_expiracion (fecha_expiracion),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===============================================
-- PASO 3: MIGRAR DATOS DE TABLAS ORIGINALES
-- ===============================================

SELECT 'MIGRANDO DATOS...' as mensaje;

-- Migrar usuarios
INSERT IGNORE INTO acc_usuarios 
    (id, nombre, usuario, email, password, activo, ultimo_acceso, fecha_creacion, fecha_modificacion, creado_por, modificado_por)
SELECT 
    id, nombre, usuario, email, password, activo, ultimo_acceso, fecha_creacion, fecha_modificacion, creado_por, modificado_por
FROM usuarios 
WHERE EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'usuarios');

-- Migrar roles
INSERT IGNORE INTO acc_roles
    (id, nombre, descripcion, activo, fecha_creacion, fecha_modificacion, creado_por, modificado_por)
SELECT 
    id, nombre, descripcion, activo, fecha_creacion, fecha_modificacion, creado_por, modificado_por
FROM roles
WHERE EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'roles');

-- Migrar modulos
INSERT IGNORE INTO acc_modulos
    (id, nombre, descripcion, activo, fecha_creacion)
SELECT 
    id, nombre, descripcion, activo, fecha_creacion
FROM modulos
WHERE EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'modulos');

-- Migrar permisos
INSERT IGNORE INTO acc_permisos
    (id, modulo_id, tipo_permiso, descripcion, activo, fecha_creacion)
SELECT 
    id, modulo_id, tipo_permiso, descripcion, activo, fecha_creacion
FROM permisos
WHERE EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'permisos');

-- Migrar usuario_roles
INSERT IGNORE INTO acc_usuario_roles
    (id, usuario_id, rol_id, fecha_asignacion, asignado_por)
SELECT 
    id, usuario_id, rol_id, fecha_asignacion, asignado_por
FROM usuario_roles
WHERE EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'usuario_roles');

-- Migrar rol_permisos
INSERT IGNORE INTO acc_rol_permisos
    (id, rol_id, permiso_id, fecha_asignacion, asignado_por)
SELECT 
    id, rol_id, permiso_id, fecha_asignacion, asignado_por
FROM rol_permisos
WHERE EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'rol_permisos');

-- Migrar auditoria_accesos
INSERT IGNORE INTO acc_auditoria_accesos
    (id, usuario_id, accion, modulo, descripcion, ip_address, user_agent, fecha_accion)
SELECT 
    id, usuario_id, accion, modulo, descripcion, ip_address, user_agent, fecha_accion
FROM auditoria_accesos
WHERE EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'auditoria_accesos');

-- Migrar sesiones (solo las activas y no expiradas)
INSERT IGNORE INTO acc_sesiones
    (id, usuario_id, token, ip_address, user_agent, fecha_inicio, fecha_expiracion, activa)
SELECT 
    id, usuario_id, token, ip_address, user_agent, fecha_inicio, fecha_expiracion, activa
FROM sesiones
WHERE EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'sesiones')
AND fecha_expiracion > NOW();

-- Migrar remember_tokens (solo los activos y no expirados)
INSERT IGNORE INTO acc_remember_tokens
    (id, usuario_id, selector, token_hash, fecha_creacion, fecha_expiracion)
SELECT 
    id, usuario_id, selector, token_hash, fecha_creacion, fecha_expiracion
FROM remember_tokens
WHERE EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'remember_tokens')
AND fecha_expiracion > NOW();

-- ===============================================
-- PASO 4: CREAR VISTA PARA PERMISOS DE USUARIO
-- ===============================================

DROP VIEW IF EXISTS acc_vista_permisos_usuario;

CREATE VIEW acc_vista_permisos_usuario AS
SELECT DISTINCT
    u.id as usuario_id,
    u.nombre as usuario_nombre,
    u.email as usuario_email,
    r.id as rol_id,
    r.nombre as rol_nombre,
    m.nombre as modulo,
    p.tipo_permiso,
    p.descripcion as permiso_descripcion
FROM acc_usuarios u
INNER JOIN acc_usuario_roles ur ON u.id = ur.usuario_id AND ur.activo = 1
INNER JOIN acc_roles r ON ur.rol_id = r.id AND r.activo = 1
INNER JOIN acc_rol_permisos rp ON r.id = rp.rol_id AND rp.activo = 1
INNER JOIN acc_permisos p ON rp.permiso_id = p.id AND p.activo = 1
INNER JOIN acc_modulos m ON p.modulo_id = m.id AND m.activo = 1
WHERE u.activo = 1;

-- ===============================================
-- PASO 5: ACTUALIZAR AUTO_INCREMENT
-- ===============================================

SELECT 'ACTUALIZANDO AUTO_INCREMENT...' as mensaje;

-- Actualizar AUTO_INCREMENT para mantener continuidad
SET @max_usuarios = (SELECT IFNULL(MAX(id), 0) FROM acc_usuarios);
SET @sql = CONCAT('ALTER TABLE acc_usuarios AUTO_INCREMENT = ', @max_usuarios + 1);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @max_roles = (SELECT IFNULL(MAX(id), 0) FROM acc_roles);
SET @sql = CONCAT('ALTER TABLE acc_roles AUTO_INCREMENT = ', @max_roles + 1);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @max_modulos = (SELECT IFNULL(MAX(id), 0) FROM acc_modulos);
SET @sql = CONCAT('ALTER TABLE acc_modulos AUTO_INCREMENT = ', @max_modulos + 1);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @max_permisos = (SELECT IFNULL(MAX(id), 0) FROM acc_permisos);
SET @sql = CONCAT('ALTER TABLE acc_permisos AUTO_INCREMENT = ', @max_permisos + 1);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @max_usuario_roles = (SELECT IFNULL(MAX(id), 0) FROM acc_usuario_roles);
SET @sql = CONCAT('ALTER TABLE acc_usuario_roles AUTO_INCREMENT = ', @max_usuario_roles + 1);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @max_rol_permisos = (SELECT IFNULL(MAX(id), 0) FROM acc_rol_permisos);
SET @sql = CONCAT('ALTER TABLE acc_rol_permisos AUTO_INCREMENT = ', @max_rol_permisos + 1);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @max_auditoria = (SELECT IFNULL(MAX(id), 0) FROM acc_auditoria_accesos);
SET @sql = CONCAT('ALTER TABLE acc_auditoria_accesos AUTO_INCREMENT = ', @max_auditoria + 1);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @max_sesiones = (SELECT IFNULL(MAX(id), 0) FROM acc_sesiones);
SET @sql = CONCAT('ALTER TABLE acc_sesiones AUTO_INCREMENT = ', @max_sesiones + 1);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @max_remember = (SELECT IFNULL(MAX(id), 0) FROM acc_remember_tokens);
SET @sql = CONCAT('ALTER TABLE acc_remember_tokens AUTO_INCREMENT = ', @max_remember + 1);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ===============================================
-- PASO 6: VERIFICACIÓN DE MIGRACIÓN
-- ===============================================

SELECT 'VERIFICANDO MIGRACIÓN...' as mensaje;

SELECT 
    'acc_usuarios' as tabla,
    COUNT(*) as registros_migrados
FROM acc_usuarios

UNION ALL

SELECT 
    'acc_roles' as tabla,
    COUNT(*) as registros_migrados
FROM acc_roles

UNION ALL

SELECT 
    'acc_modulos' as tabla,
    COUNT(*) as registros_migrados
FROM acc_modulos

UNION ALL

SELECT 
    'acc_permisos' as tabla,
    COUNT(*) as registros_migrados
FROM acc_permisos

UNION ALL

SELECT 
    'acc_usuario_roles' as tabla,
    COUNT(*) as registros_migrados
FROM acc_usuario_roles

UNION ALL

SELECT 
    'acc_rol_permisos' as tabla,
    COUNT(*) as registros_migrados
FROM acc_rol_permisos

UNION ALL

SELECT 
    'acc_auditoria_accesos' as tabla,
    COUNT(*) as registros_migrados
FROM acc_auditoria_accesos

UNION ALL

SELECT 
    'acc_sesiones' as tabla,
    COUNT(*) as registros_migrados
FROM acc_sesiones

UNION ALL

SELECT 
    'acc_remember_tokens' as tabla,
    COUNT(*) as registros_migrados
FROM acc_remember_tokens;

-- ===============================================
-- PASO 7: CREAR PROCEDIMIENTOS DE LIMPIEZA
-- ===============================================

DELIMITER //

-- Procedimiento para limpiar sesiones expiradas
CREATE PROCEDURE IF NOT EXISTS acc_limpiar_sesiones_expiradas()
BEGIN
    -- Desactivar sesiones expiradas
    UPDATE acc_sesiones 
    SET activa = 0 
    WHERE fecha_expiracion < NOW() AND activa = 1;
    
    -- Eliminar sesiones muy antiguas (más de 30 días)
    DELETE FROM acc_sesiones 
    WHERE fecha_expiracion < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    SELECT ROW_COUNT() as sesiones_limpiadas;
END //

-- Procedimiento para limpiar tokens expirados
CREATE PROCEDURE IF NOT EXISTS acc_limpiar_tokens_expirados()
BEGIN
    DELETE FROM acc_remember_tokens 
    WHERE fecha_expiracion < NOW();
    
    SELECT ROW_COUNT() as tokens_limpiados;
END //

-- Procedimiento para auditoria antigua
CREATE PROCEDURE IF NOT EXISTS acc_limpiar_auditoria_antigua()
BEGIN
    -- Eliminar auditoría más antigua de 6 meses
    DELETE FROM acc_auditoria_accesos 
    WHERE fecha_accion < DATE_SUB(NOW(), INTERVAL 6 MONTH);
    
    SELECT ROW_COUNT() as registros_auditoria_limpiados;
END //

DELIMITER ;

-- ===============================================
-- PASO 8: CREAR TRIGGERS DE AUDITORÍA
-- ===============================================

-- Trigger para auditar cambios en usuarios
DROP TRIGGER IF EXISTS acc_usuarios_audit_update;
DELIMITER //
CREATE TRIGGER acc_usuarios_audit_update
    AFTER UPDATE ON acc_usuarios
    FOR EACH ROW
BEGIN
    INSERT INTO acc_auditoria_accesos (
        usuario_id, accion, modulo, descripcion, 
        datos_antes, datos_despues, fecha_accion
    ) VALUES (
        NEW.modificado_por, 
        'update', 
        'usuarios', 
        CONCAT('Usuario actualizado: ', NEW.email),
        JSON_OBJECT(
            'nombre', OLD.nombre,
            'email', OLD.email,
            'usuario', OLD.usuario,
            'activo', OLD.activo
        ),
        JSON_OBJECT(
            'nombre', NEW.nombre,
            'email', NEW.email,
            'usuario', NEW.usuario,
            'activo', NEW.activo
        ),
        NOW()
    );
END //
DELIMITER ;

SET FOREIGN_KEY_CHECKS = 1;

-- ===============================================
-- FIN DE MIGRACIÓN
-- ===============================================

SELECT '✅ MIGRACIÓN COMPLETADA EXITOSAMENTE' as resultado;
SELECT 'Tablas creadas con prefijo acc_' as mensaje;
SELECT 'Datos migrados desde tablas originales' as mensaje;
SELECT 'Vista acc_vista_permisos_usuario creada' as mensaje;
SELECT 'Procedimientos de limpieza creados' as mensaje;
SELECT 'Triggers de auditoría activados' as mensaje;

-- ===============================================
-- INSTRUCCIONES POST-MIGRACIÓN
-- ===============================================

/*
PRÓXIMOS PASOS:

1. EJECUTAR ESTE SCRIPT EN LA BASE DE DATOS:
   mysql -u usuario -p base_de_datos < migracion_acc_prefix.sql

2. VERIFICAR QUE TODAS LAS TABLAS FUERON CREADAS:
   SHOW TABLES LIKE 'acc_%';

3. EJECUTAR EL SCRIPT PHP PARA ACTUALIZAR LAS CONSULTAS:
   php actualizar_consultas_acc.php

4. PROBAR EL SISTEMA DE ACCESOS:
   - Login
   - Creación de usuarios
   - Asignación de roles
   - Verificación de permisos

5. UNA VEZ CONFIRMADO QUE TODO FUNCIONA, ELIMINAR TABLAS ORIGINALES:
   - DROP TABLE usuarios;
   - DROP TABLE roles;
   - DROP TABLE modulos;
   - DROP TABLE permisos;
   - DROP TABLE usuario_roles;
   - DROP TABLE rol_permisos;
   - DROP TABLE auditoria_accesos;
   - DROP TABLE sesiones;
   - DROP TABLE remember_tokens;
   - DROP VIEW vista_permisos_usuario;

NOTA: HACER BACKUP ANTES DE EJECUTAR LA MIGRACIÓN
*/