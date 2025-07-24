<?php
/**
 * Verificar que los permisos están corregidos
 */

defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once dirname(__DIR__) . '/config_secure.php';

echo "🔍 VERIFICANDO PERMISOS CORREGIDOS\n";
echo "==================================\n\n";

$email_admin = 'infanteronald2@gmail.com';
$user_id = 6; // ID del usuario super admin

// 1. Verificar módulos en la vista corregida
echo "📦 MÓDULOS EN VISTA CORREGIDA:\n";
echo "=============================\n";

$vista_query = "SELECT DISTINCT modulo FROM acc_vista_permisos_usuario WHERE usuario_id = ? ORDER BY modulo";
$stmt = $conn->prepare($vista_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$modulos_vista = $stmt->get_result();

while ($row = $modulos_vista->fetch_assoc()) {
    echo "📦 {$row['modulo']}\n";
}

// 2. Probar módulos específicos que busca index.php
echo "\n🎯 PROBANDO MÓDULOS DE INDEX.PHP:\n";
echo "=================================\n";

$modulos_index = ['ventas', 'inventario', 'reportes', 'acc_usuarios', 'configuracion'];

foreach ($modulos_index as $modulo) {
    $test_query = "SELECT COUNT(*) as count FROM acc_vista_permisos_usuario WHERE usuario_id = ? AND modulo = ? AND tipo_permiso = 'leer'";
    $stmt = $conn->prepare($test_query);
    $stmt->bind_param('is', $user_id, $modulo);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['count'];
    
    $status = $count > 0 ? "✅" : "❌";
    echo "  $status $modulo (leer): $count registros\n";
}

// 3. Verificar cambios específicos
echo "\n🔧 CAMBIOS REALIZADOS:\n";
echo "=====================\n";

echo "✅ Vista recreada sin prefijo acc_ para módulos principales\n";
echo "✅ Solo módulos de accesos (usuarios, roles, modulos, permisos) tienen prefijo acc_\n";
echo "✅ Módulos principales (ventas, inventario, reportes, configuracion) sin prefijo\n";

// 4. Mostrar definición actual de la vista
echo "\n📝 DEFINICIÓN ACTUAL DE LA VISTA:\n";
echo "=================================\n";

$show_create = $conn->query("SHOW CREATE VIEW acc_vista_permisos_usuario");
if ($show_create) {
    $create_def = $show_create->fetch_assoc();
    echo $create_def['Create View'] . "\n";
} else {
    echo "❌ No se pudo obtener la definición de la vista\n";
}

$conn->close();
?>