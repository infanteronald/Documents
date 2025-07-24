<?php
/**
 * Verificar estructura de tablas de módulos y permisos
 */

defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once dirname(__DIR__) . '/config_secure.php';

echo "🔍 VERIFICANDO ESTRUCTURA DE TABLAS\n";
echo "===================================\n\n";

// 1. Estructura de acc_modulos
echo "📦 ESTRUCTURA DE acc_modulos:\n";
echo "============================\n";
$modulos_structure = $conn->query("DESCRIBE acc_modulos");
while ($row = $modulos_structure->fetch_assoc()) {
    echo "- {$row['Field']}: {$row['Type']} " . ($row['Null'] == 'NO' ? 'NOT NULL' : 'NULL');
    if ($row['Default']) echo " DEFAULT '{$row['Default']}'";
    echo "\n";
}

// 2. Estructura de acc_permisos
echo "\n🔐 ESTRUCTURA DE acc_permisos:\n";
echo "=============================\n";
$permisos_structure = $conn->query("DESCRIBE acc_permisos");
while ($row = $permisos_structure->fetch_assoc()) {
    echo "- {$row['Field']}: {$row['Type']} " . ($row['Null'] == 'NO' ? 'NOT NULL' : 'NULL');
    if ($row['Default']) echo " DEFAULT '{$row['Default']}'";
    echo "\n";
}

// 3. Estructura de acc_rol_permisos
echo "\n🔗 ESTRUCTURA DE acc_rol_permisos:\n";
echo "=================================\n";
$rol_permisos_structure = $conn->query("DESCRIBE acc_rol_permisos");
while ($row = $rol_permisos_structure->fetch_assoc()) {
    echo "- {$row['Field']}: {$row['Type']} " . ($row['Null'] == 'NO' ? 'NOT NULL' : 'NULL');
    if ($row['Default']) echo " DEFAULT '{$row['Default']}'";
    echo "\n";
}

$conn->close();
?>