<?php
/**
 * Ejecutar Migración Específica de Categorías
 * Sistema de Inventario - Sequoia Speed
 */

// Configuración directa de base de datos
$host = '127.0.0.1';
$username = 'motodota_facturas';
$password = 'f4ctur45_m0t0d0t4_2024';
$database = 'motodota_facturas';
$port = 3306;

echo "🚀 Iniciando migración específica de categorías...\n\n";

try {
    $conn = new mysqli($host, $username, $password, $database, $port);
    
    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }
    
    echo "✅ Conectado exitosamente a la base de datos\n\n";
    
    // Verificar si existe el archivo de migración específica
    $archivo_sql = __DIR__ . '/migracion_categorias_especificas.sql';
    
    if (!file_exists($archivo_sql)) {
        echo "⚠️ No se encontró el archivo de migración específica.\n";
        echo "📋 Ejecutando lectura de categorías existentes...\n\n";
        
        // Ejecutar el script de lectura primero
        include __DIR__ . '/leer_categorias_existentes.php';
        
        if (!file_exists($archivo_sql)) {
            throw new Exception("No se pudo generar el archivo de migración específica");
        }
    }
    
    echo "📄 Leyendo archivo de migración: migracion_categorias_especificas.sql\n";
    
    $sql_content = file_get_contents($archivo_sql);
    if (!$sql_content) {
        throw new Exception("No se pudo leer el archivo SQL");
    }
    
    // Dividir en sentencias individuales
    $statements = array_filter(
        array_map('trim', 
            preg_split('/;(?=(?:[^\'"]|\'[^\']*\'|"[^"]*")*$)/', $sql_content)
        )
    );
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        echo "📋 Ejecutando: " . substr(str_replace(["\n", "\r"], ' ', $statement), 0, 80) . "...\n";
        
        try {
            $result = $conn->query($statement);
            if ($result) {
                echo "   ✅ Ejecutado correctamente\n";
                $success_count++;
                
                // Mostrar información adicional para ciertas operaciones
                if (strpos($statement, 'INSERT') === 0) {
                    echo "   📊 Filas afectadas: " . $conn->affected_rows . "\n";
                } elseif (strpos($statement, 'UPDATE') === 0) {
                    echo "   📊 Productos actualizados: " . $conn->affected_rows . "\n";
                }
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
    echo "=" . str_repeat("=", 40) . "\n";
    echo "   ✅ Exitosas: $success_count\n";
    echo "   ❌ Errores: $error_count\n";
    echo "=" . str_repeat("=", 40) . "\n\n";
    
    if ($error_count === 0) {
        echo "🎉 ¡Migración completada exitosamente!\n\n";
        
        // Verificar resultados
        echo "🔍 Verificando resultados de la migración...\n";
        
        // Total de categorías
        $result = $conn->query("SELECT COUNT(*) as total FROM categorias_productos");
        if ($result) {
            $total_categorias = $result->fetch_assoc()['total'];
            echo "📂 Total de categorías creadas: $total_categorias\n";
        }
        
        // Productos migrados
        $result = $conn->query("SELECT COUNT(*) as total FROM productos WHERE categoria_id IS NOT NULL");
        if ($result) {
            $productos_migrados = $result->fetch_assoc()['total'];
            echo "📦 Productos con categoría asignada: $productos_migrados\n";
        }
        
        // Productos sin migrar
        $result = $conn->query("SELECT COUNT(*) as total FROM productos WHERE categoria IS NOT NULL AND categoria != '' AND categoria_id IS NULL");
        if ($result) {
            $productos_sin_migrar = $result->fetch_assoc()['total'];
            echo "⚠️ Productos sin migrar: $productos_sin_migrar\n";
            
            if ($productos_sin_migrar > 0) {
                echo "\n📋 Productos sin migrar (categorías no encontradas):\n";
                $result2 = $conn->query("SELECT DISTINCT categoria FROM productos WHERE categoria IS NOT NULL AND categoria != '' AND categoria_id IS NULL LIMIT 10");
                while ($row = $result2->fetch_assoc()) {
                    echo "   - " . $row['categoria'] . "\n";
                }
            }
        }
        
        echo "\n🔗 Acceso al sistema:\n";
        echo "   📂 Gestión de categorías: /inventario/categorias/\n";
        echo "   📦 Productos: /inventario/productos.php\n";
        echo "   🛒 Crear pedido: /orden_pedido.php\n";
        
    } else {
        echo "⚠️ Migración completada con errores.\n";
        echo "📋 Revisa los errores anteriores y corrige antes de continuar.\n";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error crítico: " . $e->getMessage() . "\n";
    exit(1);
}
?>