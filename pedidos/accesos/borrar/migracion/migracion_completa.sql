-- ============================================
-- MIGRACIÓN COMPLETA SISTEMA DE ACCESOS
-- Sequoia Speed - Incluye usuarios y pedidos
-- ============================================

-- Desactivar verificación de claves foráneas temporalmente
SET foreign_key_checks = 0;

-- ============================================
-- PARTE 1: MIGRACIÓN DE USUARIOS
-- ============================================

-- Agregar columnas faltantes a la tabla usuarios existente
ALTER TABLE usuarios 
ADD COLUMN IF NOT EXISTS ultimo_acceso DATETIME NULL AFTER activo,
ADD COLUMN IF NOT EXISTS fecha_modificacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER fecha_registro,
ADD COLUMN IF NOT EXISTS creado_por INT NULL AFTER fecha_modificacion,
ADD COLUMN IF NOT EXISTS modificado_por INT NULL AFTER creado_por;

-- Renombrar columna fecha_registro a fecha_creacion para consistencia
ALTER TABLE usuarios 
CHANGE COLUMN fecha_registro fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- Agregar índices faltantes
ALTER TABLE usuarios 
ADD INDEX IF NOT EXISTS idx_fecha_creacion (fecha_creacion),
ADD INDEX IF NOT EXISTS idx_ultimo_acceso (ultimo_acceso);

-- ============================================
-- PARTE 2: CREAR TABLAS DEL SISTEMA DE ACCESOS
-- ============================================

-- Tabla de roles
CREATE TABLE IF NOT EXISTS roles (
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
);

-- Tabla de módulos del sistema
CREATE TABLE IF NOT EXISTS modulos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT,
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_nombre (nombre),
    INDEX idx_activo (activo)
);

-- Tabla de permisos
CREATE TABLE IF NOT EXISTS permisos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    modulo_id INT NOT NULL,
    tipo_permiso ENUM('crear', 'leer', 'actualizar', 'eliminar') NOT NULL,
    descripcion TEXT,
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_modulo_tipo (modulo_id, tipo_permiso),
    INDEX idx_activo (activo),
    FOREIGN KEY (modulo_id) REFERENCES modulos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_modulo_permiso (modulo_id, tipo_permiso)
);

-- Tabla de asignación de roles a usuarios
CREATE TABLE IF NOT EXISTS usuario_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    rol_id INT NOT NULL,
    fecha_asignacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    asignado_por INT NULL,
    
    INDEX idx_usuario (usuario_id),
    INDEX idx_rol (rol_id),
    INDEX idx_fecha_asignacion (fecha_asignacion),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_usuario_rol (usuario_id, rol_id)
);

-- Tabla de permisos de roles
CREATE TABLE IF NOT EXISTS rol_permisos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rol_id INT NOT NULL,
    permiso_id INT NOT NULL,
    fecha_asignacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    asignado_por INT NULL,
    
    INDEX idx_rol (rol_id),
    INDEX idx_permiso (permiso_id),
    INDEX idx_fecha_asignacion (fecha_asignacion),
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_rol_permiso (rol_id, permiso_id)
);

-- Tabla de auditoría de accesos
CREATE TABLE IF NOT EXISTS auditoria_accesos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    accion VARCHAR(100) NOT NULL,
    modulo VARCHAR(50) NOT NULL,
    descripcion TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    fecha_accion DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_usuario (usuario_id),
    INDEX idx_accion (accion),
    INDEX idx_modulo (modulo),
    INDEX idx_fecha_accion (fecha_accion),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabla de sesiones
CREATE TABLE IF NOT EXISTS sesiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    fecha_inicio DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_expiracion DATETIME NOT NULL,
    activa TINYINT(1) DEFAULT 1,
    
    INDEX idx_usuario (usuario_id),
    INDEX idx_token (token),
    INDEX idx_activa (activa),
    INDEX idx_fecha_expiracion (fecha_expiracion),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- ============================================
-- PARTE 3: AGREGAR USUARIO_ID A PEDIDOS
-- ============================================

-- Agregar columna usuario_id a pedidos_detal
ALTER TABLE pedidos_detal 
ADD COLUMN IF NOT EXISTS usuario_id INT NULL AFTER metodo_pago,
ADD COLUMN IF NOT EXISTS fecha_creacion_sistema DATETIME DEFAULT CURRENT_TIMESTAMP AFTER usuario_id,
ADD COLUMN IF NOT EXISTS fecha_modificacion_sistema DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER fecha_creacion_sistema;

-- Agregar índices para mejorar rendimiento
ALTER TABLE pedidos_detal 
ADD INDEX IF NOT EXISTS idx_usuario_id (usuario_id),
ADD INDEX IF NOT EXISTS idx_fecha_creacion_sistema (fecha_creacion_sistema);

-- ============================================
-- PARTE 4: AGREGAR LLAVES FORÁNEAS
-- ============================================

-- Agregar llaves foráneas para usuarios
ALTER TABLE usuarios 
ADD CONSTRAINT IF NOT EXISTS fk_usuarios_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
ADD CONSTRAINT IF NOT EXISTS fk_usuarios_modificado_por FOREIGN KEY (modificado_por) REFERENCES usuarios(id) ON DELETE SET NULL;

-- Agregar llaves foráneas para roles
ALTER TABLE roles 
ADD CONSTRAINT IF NOT EXISTS fk_roles_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
ADD CONSTRAINT IF NOT EXISTS fk_roles_modificado_por FOREIGN KEY (modificado_por) REFERENCES usuarios(id) ON DELETE SET NULL;

-- Agregar llave foránea para pedidos_detal
ALTER TABLE pedidos_detal 
ADD CONSTRAINT IF NOT EXISTS fk_pedidos_usuario 
FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL;

-- ============================================
-- PARTE 5: DATOS INICIALES
-- ============================================

-- Insertar módulos del sistema
INSERT INTO modulos (nombre, descripcion) VALUES 
('ventas', 'Módulo de gestión de ventas y pedidos'),
('inventario', 'Módulo de gestión de inventario y productos'),
('usuarios', 'Módulo de gestión de usuarios y accesos'),
('reportes', 'Módulo de generación de reportes'),
('configuracion', 'Módulo de configuración del sistema')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

-- Insertar permisos por módulo
INSERT INTO permisos (modulo_id, tipo_permiso, descripcion) 
SELECT m.id, 'crear', CONCAT('Crear en módulo ', m.nombre) FROM modulos m
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

INSERT INTO permisos (modulo_id, tipo_permiso, descripcion) 
SELECT m.id, 'leer', CONCAT('Leer en módulo ', m.nombre) FROM modulos m
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

INSERT INTO permisos (modulo_id, tipo_permiso, descripcion) 
SELECT m.id, 'actualizar', CONCAT('Actualizar en módulo ', m.nombre) FROM modulos m
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

INSERT INTO permisos (modulo_id, tipo_permiso, descripcion) 
SELECT m.id, 'eliminar', CONCAT('Eliminar en módulo ', m.nombre) FROM modulos m
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

-- Insertar roles jerárquicos
INSERT INTO roles (nombre, descripcion) VALUES 
('super_admin', 'Super Administrador - Acceso total al sistema'),
('admin', 'Administrador - Acceso completo excepto configuración crítica'),
('gerente', 'Gerente - Acceso a ventas, inventario y reportes'),
('supervisor', 'Supervisor - Acceso a operaciones diarias y reportes básicos'),
('vendedor', 'Vendedor - Acceso a ventas y consulta de inventario'),
('consultor', 'Consultor - Solo consulta de información básica')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

-- Asignar permisos a roles
-- Super Admin: todos los permisos
INSERT INTO rol_permisos (rol_id, permiso_id) 
SELECT r.id, p.id 
FROM roles r 
CROSS JOIN permisos p 
WHERE r.nombre = 'super_admin'
ON DUPLICATE KEY UPDATE fecha_asignacion = VALUES(fecha_asignacion);

-- Admin: todos los permisos excepto configuración crítica
INSERT INTO rol_permisos (rol_id, permiso_id) 
SELECT r.id, p.id 
FROM roles r 
CROSS JOIN permisos p 
INNER JOIN modulos m ON p.modulo_id = m.id
WHERE r.nombre = 'admin' AND m.nombre != 'configuracion'
ON DUPLICATE KEY UPDATE fecha_asignacion = VALUES(fecha_asignacion);

-- Admin: solo lectura en configuración
INSERT INTO rol_permisos (rol_id, permiso_id) 
SELECT r.id, p.id 
FROM roles r 
CROSS JOIN permisos p 
INNER JOIN modulos m ON p.modulo_id = m.id
WHERE r.nombre = 'admin' AND m.nombre = 'configuracion' AND p.tipo_permiso = 'leer'
ON DUPLICATE KEY UPDATE fecha_asignacion = VALUES(fecha_asignacion);

-- Gerente: CRUD en ventas, inventario y reportes
INSERT INTO rol_permisos (rol_id, permiso_id) 
SELECT r.id, p.id 
FROM roles r 
CROSS JOIN permisos p 
INNER JOIN modulos m ON p.modulo_id = m.id
WHERE r.nombre = 'gerente' AND m.nombre IN ('ventas', 'inventario', 'reportes')
ON DUPLICATE KEY UPDATE fecha_asignacion = VALUES(fecha_asignacion);

-- Supervisor: CRUD en ventas, lectura en inventario, lectura en reportes
INSERT INTO rol_permisos (rol_id, permiso_id) 
SELECT r.id, p.id 
FROM roles r 
CROSS JOIN permisos p 
INNER JOIN modulos m ON p.modulo_id = m.id
WHERE r.nombre = 'supervisor' AND (
    (m.nombre = 'ventas') OR 
    (m.nombre = 'inventario' AND p.tipo_permiso IN ('leer', 'actualizar')) OR
    (m.nombre = 'reportes' AND p.tipo_permiso = 'leer')
)
ON DUPLICATE KEY UPDATE fecha_asignacion = VALUES(fecha_asignacion);

-- Vendedor: CRUD en ventas, lectura en inventario
INSERT INTO rol_permisos (rol_id, permiso_id) 
SELECT r.id, p.id 
FROM roles r 
CROSS JOIN permisos p 
INNER JOIN modulos m ON p.modulo_id = m.id
WHERE r.nombre = 'vendedor' AND (
    (m.nombre = 'ventas') OR 
    (m.nombre = 'inventario' AND p.tipo_permiso = 'leer')
)
ON DUPLICATE KEY UPDATE fecha_asignacion = VALUES(fecha_asignacion);

-- Consultor: solo lectura en ventas e inventario
INSERT INTO rol_permisos (rol_id, permiso_id) 
SELECT r.id, p.id 
FROM roles r 
CROSS JOIN permisos p 
INNER JOIN modulos m ON p.modulo_id = m.id
WHERE r.nombre = 'consultor' AND m.nombre IN ('ventas', 'inventario') AND p.tipo_permiso = 'leer'
ON DUPLICATE KEY UPDATE fecha_asignacion = VALUES(fecha_asignacion);

-- Crear usuario super admin inicial
INSERT INTO usuarios (nombre, email, password, activo) VALUES 
('Administrador', 'admin@sequoiaspeed.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1)
ON DUPLICATE KEY UPDATE 
    nombre = VALUES(nombre),
    password = VALUES(password);

-- Asignar rol super_admin al usuario inicial
INSERT INTO usuario_roles (usuario_id, rol_id) 
SELECT u.id, r.id 
FROM usuarios u 
CROSS JOIN roles r 
WHERE u.email = 'admin@sequoiaspeed.com' AND r.nombre = 'super_admin'
ON DUPLICATE KEY UPDATE fecha_asignacion = CURRENT_TIMESTAMP;

-- Si hay usuarios existentes, asignarles un rol por defecto
INSERT INTO usuario_roles (usuario_id, rol_id)
SELECT u.id, r.id
FROM usuarios u
CROSS JOIN roles r
WHERE r.nombre = 'vendedor' 
AND u.id NOT IN (SELECT usuario_id FROM usuario_roles)
AND u.email != 'admin@sequoiaspeed.com';

-- ============================================
-- PARTE 6: CREAR VISTAS ÚTILES
-- ============================================

-- Vista de usuarios con sus roles (compatible con estructura existente)
DROP VIEW IF EXISTS vista_usuarios_roles;
CREATE VIEW vista_usuarios_roles AS
SELECT 
    u.id as usuario_id,
    u.usuario as usuario_login,
    u.nombre as usuario_nombre,
    u.email as usuario_email,
    u.activo as usuario_activo,
    u.ultimo_acceso,
    u.fecha_creacion,
    r.id as rol_id,
    r.nombre as rol_nombre,
    r.descripcion as rol_descripcion,
    ur.fecha_asignacion
FROM usuarios u
LEFT JOIN usuario_roles ur ON u.id = ur.usuario_id
LEFT JOIN roles r ON ur.rol_id = r.id
WHERE u.activo = 1 AND (r.activo = 1 OR r.id IS NULL);

-- Vista de permisos por usuario
DROP VIEW IF EXISTS vista_permisos_usuario;
CREATE VIEW vista_permisos_usuario AS
SELECT 
    u.id as usuario_id,
    u.nombre as usuario_nombre,
    u.email as usuario_email,
    m.nombre as modulo,
    p.tipo_permiso,
    p.descripcion as permiso_descripcion
FROM usuarios u
INNER JOIN usuario_roles ur ON u.id = ur.usuario_id
INNER JOIN roles r ON ur.rol_id = r.id
INNER JOIN rol_permisos rp ON r.id = rp.rol_id
INNER JOIN permisos p ON rp.permiso_id = p.id
INNER JOIN modulos m ON p.modulo_id = m.id
WHERE u.activo = 1 AND r.activo = 1 AND p.activo = 1 AND m.activo = 1;

-- Vista de auditoría resumida
DROP VIEW IF EXISTS vista_auditoria_resumen;
CREATE VIEW vista_auditoria_resumen AS
SELECT 
    u.nombre as usuario_nombre,
    u.email as usuario_email,
    aa.accion,
    aa.modulo,
    aa.descripcion,
    aa.fecha_accion,
    aa.ip_address
FROM auditoria_accesos aa
INNER JOIN usuarios u ON aa.usuario_id = u.id
ORDER BY aa.fecha_accion DESC;

-- ============================================
-- PARTE 7: PROCEDIMIENTOS ALMACENADOS
-- ============================================

DELIMITER //

-- Procedimiento para verificar permisos
DROP PROCEDURE IF EXISTS verificar_permiso//
CREATE PROCEDURE verificar_permiso(
    IN p_usuario_id INT,
    IN p_modulo VARCHAR(50),
    IN p_tipo_permiso VARCHAR(20),
    OUT p_tiene_permiso BOOLEAN
)
BEGIN
    DECLARE permiso_count INT DEFAULT 0;
    
    SELECT COUNT(*) INTO permiso_count
    FROM vista_permisos_usuario
    WHERE usuario_id = p_usuario_id 
    AND modulo = p_modulo 
    AND tipo_permiso = p_tipo_permiso;
    
    SET p_tiene_permiso = (permiso_count > 0);
END//

-- Procedimiento para registrar auditoría
DROP PROCEDURE IF EXISTS registrar_auditoria//
CREATE PROCEDURE registrar_auditoria(
    IN p_usuario_id INT,
    IN p_accion VARCHAR(100),
    IN p_modulo VARCHAR(50),
    IN p_descripcion TEXT,
    IN p_ip_address VARCHAR(45),
    IN p_user_agent TEXT
)
BEGIN
    INSERT INTO auditoria_accesos (
        usuario_id, accion, modulo, descripcion, 
        ip_address, user_agent
    ) VALUES (
        p_usuario_id, p_accion, p_modulo, p_descripcion, 
        p_ip_address, p_user_agent
    );
END//

-- Procedimiento para limpiar sesiones expiradas
DROP PROCEDURE IF EXISTS limpiar_sesiones_expiradas//
CREATE PROCEDURE limpiar_sesiones_expiradas()
BEGIN
    UPDATE sesiones 
    SET activa = 0 
    WHERE fecha_expiracion < NOW() AND activa = 1;
    
    DELETE FROM sesiones 
    WHERE fecha_expiracion < DATE_SUB(NOW(), INTERVAL 7 DAY);
END//

DELIMITER ;

-- ============================================
-- PARTE 8: EVENTOS PROGRAMADOS
-- ============================================

-- Evento para limpiar sesiones expiradas cada hora
DROP EVENT IF EXISTS limpiar_sesiones_event;
CREATE EVENT limpiar_sesiones_event
ON SCHEDULE EVERY 1 HOUR
DO
CALL limpiar_sesiones_expiradas();

-- Evento para limpiar auditoría antigua (más de 6 meses)
DROP EVENT IF EXISTS limpiar_auditoria_event;
CREATE EVENT limpiar_auditoria_event
ON SCHEDULE EVERY 1 DAY
DO
DELETE FROM auditoria_accesos 
WHERE fecha_accion < DATE_SUB(NOW(), INTERVAL 6 MONTH);

-- Reactivar verificación de claves foráneas
SET foreign_key_checks = 1;

-- ============================================
-- FINALIZACIÓN Y VERIFICACIÓN
-- ============================================

COMMIT;

-- Mostrar resumen de la migración
SELECT 'MIGRACIÓN COMPLETA EXITOSA' as status;
SELECT COUNT(*) as usuarios_totales FROM usuarios;
SELECT COUNT(*) as roles_totales FROM roles;
SELECT COUNT(*) as modulos_totales FROM modulos;
SELECT COUNT(*) as permisos_totales FROM permisos;
SELECT COUNT(*) as usuarios_con_roles FROM usuario_roles;

-- Mostrar información del usuario admin
SELECT 
    u.id,
    u.nombre,
    u.email,
    u.activo,
    GROUP_CONCAT(r.nombre) as roles
FROM usuarios u
LEFT JOIN usuario_roles ur ON u.id = ur.usuario_id
LEFT JOIN roles r ON ur.rol_id = r.id
WHERE u.email = 'admin@sequoiaspeed.com'
GROUP BY u.id;

SELECT 'SISTEMA LISTO PARA USAR' as mensaje;
SELECT 'Usuario admin: admin@sequoiaspeed.com' as login_info;
SELECT 'Contraseña: password' as password_info;
SELECT 'Ejecutar desde: /accesos/login.php' as url_login;