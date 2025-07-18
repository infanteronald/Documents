<?php
/**
 * Script de Limpieza de Base de Datos
 * Conecta a la BD remota vía túnel SSH para limpiar tablas duplicadas/temporales
 */

// Configuración de conexión (usando túnel SSH localhost:3306)
$host = 'localhost';
$username = 'motodota_facturacion';
$password = 'Blink.182...';
$database = 'motodota_factura_electronica';
$port = 3306;

echo "🔍 Conectando a la base de datos remota...\n";

try {
    // Conectar a la base de datos
    $conn = new mysqli($host, $username, $password, $database, $port);
    
    if ($conn->connect_error) {
        die("❌ Error de conexión: " . $conn->connect_error . "\n");
    }
    
    echo "✅ Conexión exitosa a la base de datos\n\n";
    
    // Configurar charset
    $conn->set_charset('utf8mb4');
    
    // Listar todas las tablas
    echo "📋 LISTANDO TODAS LAS TABLAS:\n";
    echo str_repeat("-", 60) . "\n";
    
    $result = $conn->query("SHOW TABLES");
    $all_tables = [];
    $suspect_tables = [];
    
    while ($row = $result->fetch_array()) {
        $table_name = $row[0];
        $all_tables[] = $table_name;
        
        // Identificar tablas sospechosas (duplicadas/temporales)
        if (preg_match('/(backup_|_new|_old|temp_|tmp_|consolidado|migra)/i', $table_name)) {
            $suspect_tables[] = $table_name;
        }
        
        echo "📦 $table_name\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "📊 RESUMEN:\n";
    echo "- Total de tablas: " . count($all_tables) . "\n";
    echo "- Tablas sospechosas: " . count($suspect_tables) . "\n\n";
    
    if (!empty($suspect_tables)) {
        echo "⚠️  TABLAS SOSPECHOSAS (posibles duplicados/temporales):\n";
        echo str_repeat("-", 60) . "\n";
        
        foreach ($suspect_tables as $table) {
            // Obtener información de la tabla
            $count_result = $conn->query("SELECT COUNT(*) as count FROM `$table`");
            $count = $count_result ? $count_result->fetch_assoc()['count'] : 'Error';
            
            echo "🔸 $table - Registros: $count\n";
        }
        
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "🧹 ANÁLISIS DE LIMPIEZA RECOMENDADA:\n\n";
        
        // Analizar cada tabla sospechosa
        foreach ($suspect_tables as $table) {
            echo "📋 Analizando: $table\n";
            
            // Verificar estructura de la tabla
            $structure_result = $conn->query("DESCRIBE `$table`");
            if ($structure_result) {
                echo "   Estructura: ";
                $columns = [];
                while ($col = $structure_result->fetch_assoc()) {
                    $columns[] = $col['Field'];
                }
                echo implode(', ', array_slice($columns, 0, 5));
                if (count($columns) > 5) echo ", ...";
                echo "\n";
            }
            
            // Determinar acción recomendada
            if (strpos($table, 'backup_') === 0) {
                echo "   🎯 Acción: CONSERVAR (tabla de backup)\n";
            } elseif (strpos($table, '_new') !== false) {
                echo "   🎯 Acción: REVISAR (posible migración incompleta)\n";
            } elseif (strpos($table, '_old') !== false) {
                echo "   🎯 Acción: ELIMINAR (tabla obsoleta)\n";
            } elseif (strpos($table, 'consolidado') !== false) {
                echo "   🎯 Acción: REVISAR (parte del sistema nuevo)\n";
            } else {
                echo "   🎯 Acción: REVISAR MANUALMENTE\n";
            }
            echo "\n";
        }
        
        // Verificar el estado del sistema de almacenes
        echo str_repeat("=", 60) . "\n";
        echo "🏪 VERIFICANDO SISTEMA DE ALMACENES:\n\n";
        
        $tables_to_check = [
            'productos' => 'Tabla principal de productos',
            'almacenes' => 'Tabla principal de almacenes',
            'inventario_almacen' => 'Tabla de inventario por almacén',
            'movimientos_inventario' => 'Tabla de movimientos',
            'almacenes_consolidado' => 'Tabla consolidada (temporal)',
            'inventario_almacen_new' => 'Tabla nueva de inventario (temporal)',
            'movimientos_inventario_new' => 'Tabla nueva de movimientos (temporal)'
        ];
        
        foreach ($tables_to_check as $table => $description) {
            if (in_array($table, $all_tables)) {
                $count_result = $conn->query("SELECT COUNT(*) as count FROM `$table`");
                $count = $count_result ? $count_result->fetch_assoc()['count'] : 'Error';
                echo "✅ $table ($description) - $count registros\n";
            } else {
                echo "❌ $table ($description) - NO EXISTE\n";
            }
        }
        
        // Verificar si productos tiene campo almacen
        echo "\n🔍 VERIFICANDO ESTRUCTURA DE PRODUCTOS:\n";
        $productos_structure = $conn->query("DESCRIBE productos");
        $has_almacen_field = false;
        
        if ($productos_structure) {
            while ($col = $productos_structure->fetch_assoc()) {
                if ($col['Field'] === 'almacen') {
                    $has_almacen_field = true;
                    break;
                }
            }
        }
        
        if ($has_almacen_field) {
            echo "⚠️  Campo 'almacen' AÚN EXISTE en tabla productos\n";
            echo "   → La migración NO está completa\n";
        } else {
            echo "✅ Campo 'almacen' eliminado correctamente de productos\n";
            echo "   → Migración completada\n";
        }
        
    } else {
        echo "✅ No se encontraron tablas sospechosas. La base de datos está limpia.\n";
    }
    
    $conn->close();
    echo "\n🔒 Conexión cerrada.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>