<?php
/**
 * Crear módulo QR y asignar permisos al super admin (versión corregida)
 */

defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once dirname(__DIR__) . '/config_secure.php';

echo "🔧 CREANDO MÓDULO QR (CORREGIDO)\n";
echo "===============================\n\n";

$conn->autocommit(false);

try {
    // 1. Verificar si el módulo QR ya existe
    echo "🔍 1. Verificando si el módulo QR ya existe...\n";
    $check_query = "SELECT id FROM acc_modulos WHERE nombre = 'qr'";
    $check_result = $conn->query($check_query);
    
    if ($check_result->num_rows > 0) {
        $existing_module = $check_result->fetch_assoc();
        echo "   ⚠️  El módulo QR ya existe con ID: {$existing_module['id']}\n";
        echo "   ℹ️  Saltando creación del módulo...\n\n";
        $modulo_id = $existing_module['id'];
    } else {
        // Crear módulo QR
        echo "   ✅ Módulo QR no existe, procediendo a crearlo...\n";
        $modulo_query = "INSERT INTO acc_modulos (nombre, descripcion, activo) VALUES (?, ?, 1)";
        $stmt = $conn->prepare($modulo_query);
        $nombre_modulo = 'qr';
        $descripcion_modulo = 'Módulo de generación y gestión de códigos QR';
        $stmt->bind_param('ss', $nombre_modulo, $descripcion_modulo);
        
        if (!$stmt->execute()) {
            throw new Exception("Error creando módulo: " . $stmt->error);
        }
        
        $modulo_id = $conn->insert_id;
        echo "   ✅ Módulo QR creado con ID: $modulo_id\n\n";
    }
    
    // 2. Verificar permisos existentes
    echo "🔐 2. Verificando permisos existentes para módulo QR...\n";
    $permisos_existentes_query = "SELECT tipo_permiso FROM acc_permisos WHERE modulo_id = ? AND activo = 1";
    $stmt = $conn->prepare($permisos_existentes_query);
    $stmt->bind_param('i', $modulo_id);
    $stmt->execute();
    $permisos_existentes_result = $stmt->get_result();
    
    $permisos_existentes = [];
    while ($row = $permisos_existentes_result->fetch_assoc()) {
        $permisos_existentes[] = $row['tipo_permiso'];
    }
    
    echo "   📋 Permisos existentes: " . (empty($permisos_existentes) ? "Ninguno" : implode(', ', $permisos_existentes)) . "\n\n";
    
    // 3. Crear permisos faltantes para el módulo QR
    echo "🔐 3. Creando permisos faltantes para módulo QR...\n";
    $permisos_necesarios = [
        'leer' => 'Visualizar códigos QR y acceder al módulo',
        'crear' => 'Generar nuevos códigos QR',
        'actualizar' => 'Modificar códigos QR existentes',
        'eliminar' => 'Eliminar códigos QR'
    ];
    
    $permiso_query = "INSERT INTO acc_permisos (modulo_id, tipo_permiso, descripcion, activo) VALUES (?, ?, ?, 1)";
    $stmt = $conn->prepare($permiso_query);
    
    $permisos_ids = [];
    
    foreach ($permisos_necesarios as $tipo => $descripcion) {
        if (!in_array($tipo, $permisos_existentes)) {
            $stmt->bind_param('iss', $modulo_id, $tipo, $descripcion);
            if (!$stmt->execute()) {
                throw new Exception("Error creando permiso '$tipo': " . $stmt->error);
            }
            $permisos_ids[$tipo] = $conn->insert_id;
            echo "   ✅ Permiso '$tipo' creado con ID: {$permisos_ids[$tipo]}\n";
        } else {
            // Obtener ID del permiso existente
            $get_id_query = "SELECT id FROM acc_permisos WHERE modulo_id = ? AND tipo_permiso = ? AND activo = 1";
            $get_stmt = $conn->prepare($get_id_query);
            $get_stmt->bind_param('is', $modulo_id, $tipo);
            $get_stmt->execute();
            $id_result = $get_stmt->get_result()->fetch_assoc();
            $permisos_ids[$tipo] = $id_result['id'];
            echo "   ℹ️  Permiso '$tipo' ya existe con ID: {$permisos_ids[$tipo]}\n";
        }
    }
    
    echo "\n";
    
    // 4. Obtener ID del rol super_admin
    echo "🎭 4. Obteniendo rol super_admin...\n";
    $rol_query = "SELECT id FROM acc_roles WHERE nombre = 'super_admin' AND activo = 1";
    $result = $conn->query($rol_query);
    
    if ($result->num_rows == 0) {
        throw new Exception("No se encontró el rol super_admin");
    }
    
    $super_admin_rol = $result->fetch_assoc();
    $super_admin_id = $super_admin_rol['id'];
    echo "   ✅ Rol super_admin encontrado con ID: $super_admin_id\n\n";
    
    // 5. Verificar y asignar permisos QR al rol super_admin
    echo "🔗 5. Verificando y asignando permisos QR al rol super_admin...\n";
    $usuario_admin_id = 6; // ID del usuario super admin para asignado_por
    
    foreach ($permisos_ids as $tipo => $permiso_id) {
        // Verificar si ya tiene el permiso asignado
        $check_asignacion = "SELECT id FROM acc_rol_permisos WHERE rol_id = ? AND permiso_id = ? AND activo = 1";
        $check_stmt = $conn->prepare($check_asignacion);
        $check_stmt->bind_param('ii', $super_admin_id, $permiso_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows == 0) {
            // Asignar permiso
            $asignar_query = "INSERT INTO acc_rol_permisos (rol_id, permiso_id, fecha_asignacion, asignado_por, activo) VALUES (?, ?, NOW(), ?, 1)";
            $asignar_stmt = $conn->prepare($asignar_query);
            $asignar_stmt->bind_param('iii', $super_admin_id, $permiso_id, $usuario_admin_id);
            if (!$asignar_stmt->execute()) {
                throw new Exception("Error asignando permiso '$tipo' al super_admin: " . $asignar_stmt->error);
            }
            echo "   ✅ Permiso '$tipo' asignado al super_admin\n";
        } else {
            echo "   ℹ️  Permiso '$tipo' ya estaba asignado al super_admin\n";
        }
    }
    
    // 6. Confirmar transacción
    $conn->commit();
    echo "\n🎉 ¡MÓDULO QR CONFIGURADO EXITOSAMENTE!\n\n";
    
    // 7. Verificar resultado final
    echo "🔍 Verificación final:\n";
    $verificacion = $conn->query("
        SELECT DISTINCT
            m.nombre as modulo,
            p.tipo_permiso,
            p.descripcion
        FROM acc_modulos m
        INNER JOIN acc_permisos p ON m.id = p.modulo_id
        INNER JOIN acc_rol_permisos rp ON p.id = rp.permiso_id
        INNER JOIN acc_roles r ON rp.rol_id = r.id
        WHERE m.nombre = 'qr' 
            AND r.nombre = 'super_admin'
            AND m.activo = 1 
            AND p.activo = 1 
            AND rp.activo = 1
            AND r.activo = 1
        ORDER BY p.tipo_permiso
    ");
    
    while ($row = $verificacion->fetch_assoc()) {
        echo "   📦 {$row['modulo']} -> {$row['tipo_permiso']}: {$row['descripcion']}\n";
    }
    
    echo "\n✅ El usuario infanteronald2@gmail.com (super_admin) ahora puede acceder al módulo QR\n";
    echo "🌐 Prueba accediendo a: https://sequoiaspeed.com.co/pedidos/qr/\n";
    
} catch (Exception $e) {
    $conn->rollback();
    echo "❌ Error: " . $e->getMessage() . "\n";
} finally {
    $conn->autocommit(true);
    $conn->close();
}
?>