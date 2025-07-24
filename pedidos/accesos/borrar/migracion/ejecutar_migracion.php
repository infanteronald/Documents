<?php
/**
 * Ejecutar migración ACC_ directamente desde PHP
 * Sequoia Speed - Sistema de Gestión de Pedidos
 */

// Activar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🚀 EJECUTANDO MIGRACIÓN ACC_ DIRECTAMENTE\n";
echo "=========================================\n\n";

// Definir constante requerida
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

// Cargar configuración de BD
require_once dirname(__DIR__) . '/config_secure.php';

if (!isset($conn) || $conn->connect_error) {
    die("❌ Error: No se pudo conectar a la base de datos\n");
}

echo "✅ Conexión a BD establecida\n";
echo "📊 Servidor: " . $conn->server_info . "\n";
echo "🔗 Host: " . $conn->host_info . "\n\n";

// Leer el archivo SQL de migración
$sql_file = __DIR__ . '/migracion_acc_prefix.sql';

if (!file_exists($sql_file)) {
    die("❌ Error: No se encontró el archivo migracion_acc_prefix.sql\n");
}

echo "📁 Leyendo archivo de migración...\n";
$sql_content = file_get_contents($sql_file);

// Limpiar el contenido SQL - remover comentarios y mensajes informativos
$sql_lines = explode("\n", $sql_content);
$clean_sql = '';

foreach ($sql_lines as $line) {
    $line = trim($line);
    // Saltar comentarios y líneas vacías
    if (empty($line) || 
        strpos($line, '--') === 0 || 
        strpos($line, '#') === 0 ||
        strpos($line, '/*') === 0 ||
        strpos($line, "SELECT '") === 0) {
        continue;
    }
    $clean_sql .= $line . "\n";
}

// Dividir en statements individuales
$statements = array_filter(
    array_map('trim', explode(';', $clean_sql)),
    function($stmt) {
        return !empty($stmt);
    }
);

// Separar statements por tipo para ejecutar en orden correcto
$create_tables = [];
$inserts = [];
$views = [];
$procedures = [];
$triggers = [];
$others = [];

foreach ($statements as $stmt) {
    $stmt_upper = strtoupper(trim($stmt));
    if (strpos($stmt_upper, 'CREATE TABLE') === 0) {
        $create_tables[] = $stmt;
    } elseif (strpos($stmt_upper, 'INSERT') === 0) {
        $inserts[] = $stmt;
    } elseif (strpos($stmt_upper, 'CREATE VIEW') === 0) {
        $views[] = $stmt;
    } elseif (strpos($stmt_upper, 'CREATE PROCEDURE') === 0) {
        $procedures[] = $stmt;
    } elseif (strpos($stmt_upper, 'CREATE TRIGGER') === 0) {
        $triggers[] = $stmt;
    } else {
        $others[] = $stmt;
    }
}

// Reorganizar statements en orden correcto
$statements = array_merge($create_tables, $inserts, $views, $procedures, $triggers, $others);

echo "📋 Se encontraron " . count($statements) . " statements SQL\n\n";

$executed = 0;
$errors = 0;

// Desactivar foreign key checks temporalmente
echo "🔧 Desactivando foreign key checks...\n";
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

foreach ($statements as $index => $statement) {
    $statement = trim($statement);
    
    if (empty($statement)) continue;
    
    // Mostrar progreso cada 10 statements
    if ($index % 10 == 0) {
        echo "📊 Progreso: " . ($index + 1) . "/" . count($statements) . "\n";
    }
    
    // Ejecutar statement
    $result = $conn->query($statement);
    
    if ($result === false) {
        $errors++;
        echo "❌ Error en statement " . ($index + 1) . ":\n";
        echo "   SQL: " . substr($statement, 0, 100) . "...\n";
        echo "   Error: " . $conn->error . "\n\n";
        
        // Si es un error crítico, detener
        if (strpos($conn->error, 'syntax error') !== false) {
            echo "💥 Error crítico de sintaxis. Deteniendo migración.\n";
            break;
        }
    } else {
        $executed++;
        
        // Mostrar información relevante para ciertos tipos de statements
        if (preg_match('/^CREATE TABLE\s+(\w+)/i', $statement, $matches)) {
            echo "✅ Tabla creada: " . $matches[1] . "\n";
        } elseif (preg_match('/^INSERT.*INTO\s+(\w+)/i', $statement, $matches)) {
            $affected = $conn->affected_rows;
            if ($affected > 0) {
                echo "✅ Datos migrados a " . $matches[1] . ": $affected registros\n";
            }
        } elseif (preg_match('/^CREATE VIEW\s+(\w+)/i', $statement, $matches)) {
            echo "✅ Vista creada: " . $matches[1] . "\n";
        }
    }
}

// Reactivar foreign key checks
echo "\n🔧 Reactivando foreign key checks...\n";
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

echo "\n=========================================\n";
echo "📊 RESUMEN DE MIGRACIÓN:\n";
echo "  Statements ejecutados: $executed\n";
echo "  Errores encontrados: $errors\n";

if ($errors > 0) {
    echo "⚠️  Migración completada con errores\n";
} else {
    echo "✅ Migración completada exitosamente\n";
}

// Verificar tablas creadas
echo "\n🔍 Verificando tablas creadas con prefijo acc_:\n";
$result = $conn->query("SHOW TABLES LIKE 'acc_%'");

if ($result) {
    $tables = [];
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
    
    echo "📋 Tablas encontradas: " . count($tables) . "\n";
    foreach ($tables as $table) {
        // Contar registros en cada tabla
        $count_result = $conn->query("SELECT COUNT(*) as count FROM $table");
        $count = $count_result ? $count_result->fetch_assoc()['count'] : 0;
        echo "  ✅ $table: $count registros\n";
    }
} else {
    echo "❌ Error verificando tablas: " . $conn->error . "\n";
}

// Verificar vista
echo "\n🔍 Verificando vista acc_vista_permisos_usuario:\n";
$view_result = $conn->query("SELECT COUNT(*) as count FROM acc_vista_permisos_usuario");
if ($view_result) {
    $view_count = $view_result->fetch_assoc()['count'];
    echo "✅ Vista funcional: $view_count registros de permisos\n";
} else {
    echo "❌ Error en vista: " . $conn->error . "\n";
}

$conn->close();

echo "\n🎉 MIGRACIÓN DE BASE DE DATOS COMPLETADA\n";
echo "\n📋 PRÓXIMO PASO:\n";
echo "Ejecutar: php actualizar_consultas_acc.php\n";
?>