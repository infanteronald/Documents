/**
 * Fix collation issue in usuarios view
 */

-- Eliminar vista actual
DROP VIEW IF EXISTS acc_vista_permisos_usuario;

-- Recrear vista con collation correcta usando COLLATE
CREATE VIEW acc_vista_permisos_usuario AS
SELECT DISTINCT
    u.id as usuario_id,
    u.nombre as usuario_nombre,
    u.email as usuario_email,
    r.id as rol_id,
    r.nombre as rol_nombre,
    CASE 
        WHEN m.nombre IN ('usuarios', 'roles', 'modulos', 'permisos') THEN CONCAT('acc_', m.nombre)
        ELSE m.nombre
    END COLLATE utf8mb4_unicode_ci as modulo,
    p.tipo_permiso,
    p.descripcion as permiso_descripcion
FROM acc_usuarios u
INNER JOIN acc_usuario_roles ur ON u.id = ur.usuario_id AND ur.activo = 1
INNER JOIN acc_roles r ON ur.rol_id = r.id AND r.activo = 1
INNER JOIN acc_rol_permisos rp ON r.id = rp.rol_id AND rp.activo = 1
INNER JOIN acc_permisos p ON rp.permiso_id = p.id AND p.activo = 1
INNER JOIN acc_modulos m ON p.modulo_id = m.id AND m.activo = 1
WHERE u.activo = 1

UNION

SELECT DISTINCT
    u.id as usuario_id,
    u.nombre as usuario_nombre,
    u.email as usuario_email,
    r.id as rol_id,
    r.nombre as rol_nombre,
    'usuarios' COLLATE utf8mb4_unicode_ci as modulo,
    p.tipo_permiso,
    p.descripcion as permiso_descripcion
FROM acc_usuarios u
INNER JOIN acc_usuario_roles ur ON u.id = ur.usuario_id AND ur.activo = 1
INNER JOIN acc_roles r ON ur.rol_id = r.id AND r.activo = 1
INNER JOIN acc_rol_permisos rp ON r.id = rp.rol_id AND rp.activo = 1
INNER JOIN acc_permisos p ON rp.permiso_id = p.id AND p.activo = 1
INNER JOIN acc_modulos m ON p.modulo_id = m.id AND m.activo = 1
WHERE u.activo = 1 AND m.nombre = 'usuarios';

-- Verificar que se creó correctamente
SELECT 'View created successfully' as status;