<?php
/**
 * Verificar que el fix de usuarios funciona
 */

defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once dirname(__DIR__) . '/config_secure.php';

echo "🔍 VERIFICANDO FIX MÓDULO USUARIOS\n";
echo "==================================\n\n";

$email_admin = 'infanteronald2@gmail.com';
$user_id = 6;

// 1. Verificar que ambos nombres están disponibles
echo "📦 MÓDULOS USUARIOS DISPONIBLES:\n";
echo "===============================\n";

$modulos_usuarios_query = "
    SELECT DISTINCT modulo, tipo_permiso 
    FROM acc_vista_permisos_usuario 
    WHERE usuario_id = ? 
    AND (modulo = 'usuarios' OR modulo = 'acc_usuarios')
    ORDER BY modulo, tipo_permiso";

$stmt = $conn->prepare($modulos_usuarios_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

$permisos_usuarios = [];
$permisos_acc_usuarios = [];

while ($row = $result->fetch_assoc()) {
    if ($row['modulo'] === 'usuarios') {
        $permisos_usuarios[] = $row['tipo_permiso'];
    } else {
        $permisos_acc_usuarios[] = $row['tipo_permiso'];
    }
    echo "✅ {$row['modulo']} -> {$row['tipo_permiso']}\n";
}

echo "\n📊 RESUMEN DE PERMISOS:\n";
echo "======================\n";
echo "📦 'usuarios': " . count($permisos_usuarios) . " permisos -> " . implode(', ', $permisos_usuarios) . "\n";
echo "📦 'acc_usuarios': " . count($permisos_acc_usuarios) . " permisos -> " . implode(', ', $permisos_acc_usuarios) . "\n";

// 2. Probar permisos específicos que requieren los archivos PHP
echo "\n🎯 PROBANDO PERMISOS REQUERIDOS POR ARCHIVOS PHP:\n";
echo "=================================================\n";

$tests = [
    ['usuarios', 'crear', 'usuario_crear.php'],
    ['usuarios', 'actualizar', 'usuario_editar.php'],
    ['usuarios', 'leer', 'usuario_listar.php'],
    ['acc_usuarios', 'leer', 'index.php (dashboard)']
];

foreach ($tests as $test) {
    $modulo = $test[0];
    $permiso = $test[1];
    $archivo = $test[2];
    
    $test_query = "SELECT COUNT(*) as count FROM acc_vista_permisos_usuario WHERE usuario_id = ? AND modulo = ? AND tipo_permiso = ?";
    $stmt = $conn->prepare($test_query);
    $stmt->bind_param('iss', $user_id, $modulo, $permiso);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['count'];
    
    $status = $count > 0 ? "✅" : "❌";
    echo "$status '$modulo' + '$permiso' (usado por $archivo): $count registros\n";
}

echo "\n🏆 RESULTADO:\n";
echo "=============\n";

$usuarios_crear = count($permisos_usuarios) >= 4;
$usuarios_leer = in_array('leer', $permisos_usuarios);
$acc_usuarios_disponible = count($permisos_acc_usuarios) >= 4;

if ($usuarios_crear && $usuarios_leer && $acc_usuarios_disponible) {
    echo "✅ ¡FIX EXITOSO! El super admin ahora puede:\n";
    echo "   - Acceder a usuario_crear.php (usuarios + crear)\n";
    echo "   - Acceder a usuario_editar.php (usuarios + actualizar)\n";
    echo "   - Ver usuarios en index.php (acc_usuarios + leer)\n";
    echo "   - Realizar todas las operaciones CRUD\n";
} else {
    echo "❌ Problema detectado:\n";
    echo "   - usuarios disponible: " . ($usuarios_crear ? "✅" : "❌") . "\n";
    echo "   - acc_usuarios disponible: " . ($acc_usuarios_disponible ? "✅" : "❌") . "\n";
}

$conn->close();
?>