/**
 * Corregir vista de permisos - Solo agregar prefijo acc_ a módulos de accesos
 * Problema: La vista actual agrega acc_ a TODOS los módulos, pero index.php
 * busca nombres sin prefijo para módulos principales del sistema
 */

-- Eliminar vista actual
DROP VIEW IF EXISTS acc_vista_permisos_usuario;

-- Recrear vista SIN agregar prefijo acc_ a módulos principales del sistema
CREATE VIEW acc_vista_permisos_usuario AS
SELECT DISTINCT
    u.id as usuario_id,
    u.nombre as usuario_nombre,
    u.email as usuario_email,
    r.id as rol_id,
    r.nombre as rol_nombre,
    -- Solo agregar prefijo acc_ a módulos del sistema de accesos
    -- Mantener nombres originales para módulos principales
    CASE 
        WHEN m.nombre IN ('usuarios', 'roles', 'modulos', 'permisos') THEN CONCAT('acc_', m.nombre)
        ELSE m.nombre  -- Mantener nombre original para ventas, inventario, reportes, configuracion, etc.
    END as modulo,
    p.tipo_permiso,
    p.descripcion as permiso_descripcion
FROM acc_usuarios u
INNER JOIN acc_usuario_roles ur ON u.id = ur.usuario_id AND ur.activo = 1
INNER JOIN acc_roles r ON ur.rol_id = r.id AND r.activo = 1
INNER JOIN acc_rol_permisos rp ON r.id = rp.rol_id AND rp.activo = 1
INNER JOIN acc_permisos p ON rp.permiso_id = p.id AND p.activo = 1
INNER JOIN acc_modulos m ON p.modulo_id = m.id AND m.activo = 1
WHERE u.activo = 1;

-- Verificar que se creó correctamente
SHOW CREATE VIEW acc_vista_permisos_usuario;