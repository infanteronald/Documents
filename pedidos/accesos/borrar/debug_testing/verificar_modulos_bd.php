<?php
/**
 * Verificar módulos en BD y permisos del usuario super admin
 */

defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once dirname(__DIR__) . '/config_secure.php';

echo "🔍 VERIFICANDO MÓDULOS Y PERMISOS EN BD\n";
echo "======================================\n\n";

$email_admin = 'infanteronald2@gmail.com';

// 1. Obtener ID del usuario
$user_query = "SELECT id, nombre FROM acc_usuarios WHERE email = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param('s', $email_admin);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$user_id = $user_data['id'];

echo "👤 Usuario: {$user_data['nombre']} (ID: $user_id)\n\n";

// 2. Verificar todos los módulos existentes
echo "📦 MÓDULOS EXISTENTES EN BD:\n";
echo "============================\n";
$modulos_query = "SELECT id, nombre, descripcion, activo FROM acc_modulos ORDER BY nombre";
$modulos_result = $conn->query($modulos_query);

$modulos_existentes = [];
while ($modulo = $modulos_result->fetch_assoc()) {
    $status = $modulo['activo'] ? 'Activo' : 'Inactivo';
    echo "  📦 {$modulo['nombre']} - {$modulo['descripcion']} [$status]\n";
    $modulos_existentes[] = $modulo['nombre'];
}

// 3. Verificar permisos del usuario por módulo
echo "\n🔐 PERMISOS DEL USUARIO POR MÓDULO:\n";
echo "==================================\n";

$permisos_query = "
    SELECT m.nombre as modulo, p.tipo_permiso, COUNT(*) as tiene_permiso
    FROM acc_vista_permisos_usuario vpu
    INNER JOIN acc_modulos m ON vpu.modulo = m.nombre
    INNER JOIN acc_permisos p ON vpu.modulo = m.nombre AND vpu.tipo_permiso = p.tipo_permiso
    WHERE vpu.usuario_id = ?
    GROUP BY m.nombre, p.tipo_permiso
    ORDER BY m.nombre, p.tipo_permiso";

$stmt = $conn->prepare($permisos_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$permisos_result = $stmt->get_result();

$permisos_por_modulo = [];
while ($permiso = $permisos_result->fetch_assoc()) {
    $permisos_por_modulo[$permiso['modulo']][] = $permiso['tipo_permiso'];
}

foreach ($modulos_existentes as $modulo) {
    echo "📦 $modulo:\n";
    if (isset($permisos_por_modulo[$modulo])) {
        foreach (['crear', 'leer', 'actualizar', 'eliminar'] as $tipo) {
            $tiene = in_array($tipo, $permisos_por_modulo[$modulo]);
            $status = $tiene ? '✅' : '❌';
            echo "    $status $tipo\n";
        }
    } else {
        echo "    ❌ Sin permisos\n";
    }
}

// 4. Verificar qué busca index.php vs qué hay en BD
echo "\n🔍 COMPARACIÓN: Index.php vs BD\n";
echo "===============================\n";

$modulos_index = ['ventas', 'inventario', 'reportes', 'usuarios', 'configuracion'];

foreach ($modulos_index as $modulo_index) {
    echo "🎯 Index.php busca: '$modulo_index'\n";
    
    // Verificar si existe en BD
    $existe_bd = in_array($modulo_index, $modulos_existentes);
    echo "   En BD: " . ($existe_bd ? "✅ Existe" : "❌ No existe") . "\n";
    
    // Verificar si tiene permisos
    $tiene_permisos = isset($permisos_por_modulo[$modulo_index]) && in_array('leer', $permisos_por_modulo[$modulo_index]);
    echo "   Permiso 'leer': " . ($tiene_permisos ? "✅ SÍ" : "❌ NO") . "\n";
    
    if (!$existe_bd && !$tiene_permisos) {
        echo "   🚨 PROBLEMA: Index.php busca '$modulo_index' pero no existe en BD\n";
    }
    echo "\n";
}

// 5. Sugerir soluciones
echo "💡 SOLUCIONES SUGERIDAS:\n";
echo "========================\n";

$modulos_faltantes = [];
foreach ($modulos_index as $modulo_index) {
    if (!in_array($modulo_index, $modulos_existentes)) {
        $modulos_faltantes[] = $modulo_index;
    }
}

if (!empty($modulos_faltantes)) {
    echo "1. CREAR MÓDULOS FALTANTES:\n";
    foreach ($modulos_faltantes as $modulo_faltante) {
        echo "   📦 Crear módulo '$modulo_faltante'\n";
    }
    echo "\n";
}

echo "2. VERIFICAR NOMBRES:\n";
echo "   - Index.php busca nombres sin prefijo (ventas, inventario, etc.)\n";
echo "   - Vista debe tener nombres sin prefijo también\n";
echo "   - Solo el sistema de accesos usa prefijo acc_\n";

$conn->close();
?>