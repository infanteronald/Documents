<?php
/**
 * Verificar estructura de tablas existentes
 */

defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once dirname(__DIR__) . '/config_secure.php';

if (!isset($conn) || $conn->connect_error) {
    die("❌ Error: No se pudo conectar a la base de datos\n");
}

echo "🔍 VERIFICANDO ESTRUCTURA DE TABLAS EXISTENTES\n";
echo "==============================================\n\n";

$tables_to_check = [
    'acc_usuarios', 'acc_roles', 'acc_modulos', 'acc_permisos', 
    'acc_usuario_roles', 'acc_rol_permisos', 'acc_auditoria_accesos', 
    'acc_sesiones', 'acc_remember_tokens'
];

foreach ($tables_to_check as $table) {
    echo "📋 Tabla: $table\n";
    
    // Verificar si la tabla existe
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "  ✅ Existe\n";
        
        // Mostrar estructura
        $columns_result = $conn->query("DESCRIBE $table");
        if ($columns_result) {
            echo "  📊 Columnas:\n";
            while ($column = $columns_result->fetch_assoc()) {
                echo "    - " . $column['Field'] . " (" . $column['Type'] . ")\n";
            }
        }
        
        // Contar registros
        $count_result = $conn->query("SELECT COUNT(*) as count FROM $table");
        if ($count_result) {
            $count = $count_result->fetch_assoc()['count'];
            echo "  📊 Registros: $count\n";
        }
    } else {
        echo "  ❌ No existe\n";
    }
    
    echo "\n";
}

// Verificar vista
echo "📋 Vista: vista_permisos_usuario\n";
$view_result = $conn->query("SHOW TABLES LIKE 'acc_vista_permisos_usuario'");
if ($view_result && $view_result->num_rows > 0) {
    echo "  ✅ Existe\n";
    $count_result = $conn->query("SELECT COUNT(*) as count FROM acc_vista_permisos_usuario");
    if ($count_result) {
        $count = $count_result->fetch_assoc()['count'];
        echo "  📊 Registros: $count\n";
    }
} else {
    echo "  ❌ No existe\n";
}

$conn->close();
?>