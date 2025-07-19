<?php
/**
 * Ejecutar Migración de Categorías
 * Sistema de Inventario - Sequoia Speed
 */

// Definir constante
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once '../../config_secure.php';

// Leer archivo SQL
$sql_file = __DIR__ . '/setup_categorias.sql';
if (!file_exists($sql_file)) {
    die("❌ Error: No se encontró el archivo setup_categorias.sql\n");
}

$sql_content = file_get_contents($sql_file);
if (!$sql_content) {
    die("❌ Error: No se pudo leer el archivo SQL\n");
}

// Dividir en sentencias individuales
$statements = array_filter(
    array_map('trim', 
        preg_split('/;(?=(?:[^\'"]|\'[^\']*\'|"[^"]*")*$)/', $sql_content)
    )
);

echo "🚀 Iniciando migración de categorías...\n\n";

$success_count = 0;
$error_count = 0;

foreach ($statements as $statement) {
    if (empty($statement) || strpos($statement, '--') === 0) {
        continue;
    }
    
    echo "📋 Ejecutando: " . substr($statement, 0, 50) . "...\n";
    
    try {
        $result = $conn->query($statement);
        if ($result) {
            echo "   ✅ Ejecutado correctamente\n";
            $success_count++;
        } else {
            echo "   ❌ Error: " . $conn->error . "\n";
            $error_count++;
        }
    } catch (Exception $e) {
        echo "   ❌ Excepción: " . $e->getMessage() . "\n";
        $error_count++;
    }
    
    echo "\n";
}

echo "📊 Resumen de migración:\n";
echo "   ✅ Exitosas: $success_count\n";
echo "   ❌ Errores: $error_count\n";

if ($error_count === 0) {
    echo "\n🎉 ¡Migración completada exitosamente!\n";
} else {
    echo "\n⚠️ Migración completada con algunos errores.\n";
}

// Verificar que se crearon las categorías
$result = $conn->query("SELECT COUNT(*) as total FROM categorias_productos");
if ($result) {
    $total = $result->fetch_assoc()['total'];
    echo "\n📊 Total de categorías en el sistema: $total\n";
} else {
    echo "\n❌ Error verificando categorías: " . $conn->error . "\n";
}

echo "\n🔗 Puedes acceder al sistema en: http://localhost/pedidos/inventario/categorias/\n";
?>