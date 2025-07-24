-- ===============================================
-- SOLUCIÓN AL ERROR: acc_vista_permisos_usuario doesn't exist
-- Fecha: 2025-07-23
-- Descripción: Crear vista faltante para el sistema de permisos
-- ===============================================

-- Eliminar vista existente si existe
DROP VIEW IF EXISTS acc_vista_permisos_usuario;

-- Crear la vista acc_vista_permisos_usuario
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

-- Verificar que la vista funciona
SELECT 'Vista creada exitosamente' as resultado;
SELECT COUNT(*) as total_permisos FROM acc_vista_permisos_usuario;

-- Mostrar algunos ejemplos
SELECT 
    usuario_nombre, 
    rol_nombre, 
    modulo, 
    tipo_permiso 
FROM acc_vista_permisos_usuario 
LIMIT 5;