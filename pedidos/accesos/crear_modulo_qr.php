<?php
/**
 * Crear módulo QR y asignar permisos al super admin
 */

defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once dirname(__DIR__) . '/config_secure.php';

echo "🔧 CREANDO MÓDULO QR\n";
echo "===================\n\n";

$conn->autocommit(false);

try {
    // 1. Crear módulo QR
    echo "📦 1. Creando módulo QR...\n";
    $modulo_query = "INSERT INTO acc_modulos (nombre, descripcion, activo, creado_por) VALUES (?, ?, 1, ?)";
    $stmt = $conn->prepare($modulo_query);
    $nombre_modulo = 'qr';
    $descripcion_modulo = 'Módulo de generación y gestión de códigos QR';
    $creado_por = 6; // ID del super admin
    $stmt->bind_param('ssi', $nombre_modulo, $descripcion_modulo, $creado_por);
    
    if (!$stmt->execute()) {
        throw new Exception("Error creando módulo: " . $stmt->error);
    }
    
    $modulo_id = $conn->insert_id;
    echo "   ✅ Módulo QR creado con ID: $modulo_id\n\n";
    
    // 2. Crear permisos para el módulo QR
    echo "🔐 2. Creando permisos para módulo QR...\n";
    $permisos = [
        'leer' => 'Visualizar códigos QR y acceder al módulo',
        'crear' => 'Generar nuevos códigos QR',
        'actualizar' => 'Modificar códigos QR existentes',
        'eliminar' => 'Eliminar códigos QR'
    ];
    
    $permiso_query = "INSERT INTO acc_permisos (modulo_id, tipo_permiso, descripcion, activo, creado_por) VALUES (?, ?, ?, 1, ?)";
    $stmt = $conn->prepare($permiso_query);
    
    $permisos_ids = [];
    
    foreach ($permisos as $tipo => $descripcion) {
        $stmt->bind_param('issi', $modulo_id, $tipo, $descripcion, $creado_por);
        if (!$stmt->execute()) {
            throw new Exception("Error creando permiso '$tipo': " . $stmt->error);
        }
        $permisos_ids[$tipo] = $conn->insert_id;
        echo "   ✅ Permiso '$tipo' creado con ID: {$permisos_ids[$tipo]}\n";
    }
    
    echo "\n";
    
    // 3. Obtener ID del rol super_admin
    echo "🎭 3. Obteniendo rol super_admin...\n";
    $rol_query = "SELECT id FROM acc_roles WHERE nombre = 'super_admin' AND activo = 1";
    $result = $conn->query($rol_query);
    
    if ($result->num_rows == 0) {
        throw new Exception("No se encontró el rol super_admin");
    }
    
    $super_admin_rol = $result->fetch_assoc();
    $super_admin_id = $super_admin_rol['id'];
    echo "   ✅ Rol super_admin encontrado con ID: $super_admin_id\n\n";
    
    // 4. Asignar todos los permisos QR al rol super_admin
    echo "🔗 4. Asignando permisos QR al rol super_admin...\n";
    $usuario_admin_id = 6; // ID del usuario super admin para asignado_por
    
    $asignar_query = "INSERT INTO acc_rol_permisos (rol_id, permiso_id, fecha_asignacion, asignado_por, activo) VALUES (?, ?, NOW(), ?, 1)";
    $stmt = $conn->prepare($asignar_query);
    
    foreach ($permisos_ids as $tipo => $permiso_id) {
        $stmt->bind_param('iii', $super_admin_id, $permiso_id, $usuario_admin_id);
        if (!$stmt->execute()) {
            throw new Exception("Error asignando permiso '$tipo' al super_admin: " . $stmt->error);
        }
        echo "   ✅ Permiso '$tipo' asignado al super_admin\n";
    }
    
    // 5. Confirmar transacción
    $conn->commit();
    echo "\n🎉 ¡MÓDULO QR CREADO EXITOSAMENTE!\n\n";
    
    // 6. Verificar resultado
    echo "🔍 Verificación final:\n";
    $verificacion = $conn->query("
        SELECT 
            m.nombre as modulo,
            p.tipo_permiso,
            p.descripcion
        FROM acc_modulos m
        INNER JOIN acc_permisos p ON m.id = p.modulo_id
        WHERE m.nombre = 'qr' AND m.activo = 1 AND p.activo = 1
        ORDER BY p.tipo_permiso
    ");
    
    while ($row = $verificacion->fetch_assoc()) {
        echo "   📦 {$row['modulo']} -> {$row['tipo_permiso']}: {$row['descripcion']}\n";
    }
    
    echo "\n✅ El usuario infanteronald2@gmail.com ahora puede acceder al módulo QR\n";
    
} catch (Exception $e) {
    $conn->rollback();
    echo "❌ Error: " . $e->getMessage() . "\n";
} finally {
    $conn->autocommit(true);
    $conn->close();
}
?>