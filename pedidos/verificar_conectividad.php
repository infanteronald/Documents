<?php
/**
 * Script de verificación de conectividad con la nueva configuración de base de datos
 * Verifica que la configuración motodota_factura_electronica funcione correctamente
 */

echo "🔍 VERIFICACIÓN DE CONECTIVIDAD - SEQUOIA SPEED\n";
echo "==============================================\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

echo "📋 CONFIGURACIÓN ESPERADA:\n";
echo "- Base de datos: motodota_factura_electronica\n";
echo "- Usuario: motodota_facturacion\n";
echo "- Servidor: 68.66.226.124\n\n";

// Test 1: Verificar archivo de conexión
echo "🔧 Test 1: Verificando archivo de conexión...\n";
if (file_exists('conexion.php')) {
    echo "✅ Archivo conexion.php encontrado\n";
    
    // Leer y analizar el contenido
    $contenido = file_get_contents('conexion.php');
    if (strpos($contenido, 'motodota_factura_electronica') !== false) {
        echo "✅ Base de datos correcta configurada\n";
    } else {
        echo "❌ Base de datos incorrecta en conexion.php\n";
    }
    
    if (strpos($contenido, 'motodota_facturacion') !== false) {
        echo "✅ Usuario correcto configurado\n";
    } else {
        echo "❌ Usuario incorrecto en conexion.php\n";
    }
} else {
    echo "❌ Archivo conexion.php no encontrado\n";
    exit(1);
}

echo "\n🔌 Test 2: Probando conectividad...\n";
try {
    require_once 'conexion.php';
    
    if ($conn && $conn->ping()) {
        echo "✅ Conexión establecida exitosamente\n";
        echo "ℹ️  Información del servidor:\n";
        echo "   - Host: " . $conn->host_info . "\n";
        echo "   - Versión MySQL: " . $conn->server_info . "\n";
        echo "   - Charset: " . $conn->character_set_name() . "\n";
        
        // Verificar base de datos actual
        $result = $conn->query("SELECT DATABASE() as current_db");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "   - Base de datos actual: " . $row['current_db'] . "\n";
            
            if ($row['current_db'] === 'motodota_factura_electronica') {
                echo "✅ Base de datos correcta conectada\n";
            } else {
                echo "❌ Base de datos incorrecta: " . $row['current_db'] . "\n";
            }
        }
    } else {
        echo "❌ Error: No se pudo establecer conexión\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🗄️  Test 3: Verificando estructura de tablas...\n";
$tablas_requeridas = [
    'pedidos_detal',
    'pedido_detalle', 
    'productos',
    'usuarios',
    'clientes',
    'bold_logs',
    'bold_retry_queue',
    'bold_webhook_logs',
    'notification_logs'
];

$tablas_encontradas = 0;
foreach ($tablas_requeridas as $tabla) {
    $result = $conn->query("SHOW TABLES LIKE '$tabla'");
    if ($result && $result->num_rows > 0) {
        echo "✅ Tabla '$tabla' existe\n";
        $tablas_encontradas++;
        
        // Contar registros
        $count_result = $conn->query("SELECT COUNT(*) as total FROM $tabla");
        if ($count_result) {
            $count = $count_result->fetch_assoc()['total'];
            echo "   📊 Registros: $count\n";
        }
    } else {
        echo "⚠️  Tabla '$tabla' no encontrada\n";
    }
}

echo "\n📊 RESUMEN:\n";
echo "- Tablas encontradas: $tablas_encontradas/" . count($tablas_requeridas) . "\n";

$porcentaje = round(($tablas_encontradas / count($tablas_requeridas)) * 100, 1);
echo "- Completitud: $porcentaje%\n";

if ($tablas_encontradas >= 5) {
    echo "✅ Sistema operativo - tablas principales presentes\n";
} else {
    echo "⚠️  Sistema incompleto - faltan tablas importantes\n";
}

echo "\n🔍 Test 4: Verificando datos de prueba...\n";
try {
    // Verificar último pedido
    $result = $conn->query("SELECT * FROM pedidos_detal ORDER BY fecha DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $ultimo = $result->fetch_assoc();
        echo "✅ Último pedido encontrado:\n";
        echo "   - ID: " . $ultimo['id'] . "\n";
        echo "   - Nombre: " . $ultimo['nombre'] . "\n";
        echo "   - Fecha: " . $ultimo['fecha'] . "\n";
        echo "   - Estado: " . ($ultimo['estado_pago'] ?? 'N/A') . "\n";
    } else {
        echo "ℹ️  No hay pedidos en la base de datos\n";
    }
    
    // Verificar productos
    $result = $conn->query("SELECT COUNT(*) as total FROM productos WHERE activo = 1");
    if ($result) {
        $count = $result->fetch_assoc()['total'];
        echo "✅ Productos activos: $count\n";
    }
    
} catch (Exception $e) {
    echo "⚠️  Error verificando datos: " . $e->getMessage() . "\n";
}

echo "\n🎯 VERIFICACIÓN COMPLETADA\n";
echo "=========================\n";

if (isset($conn)) {
    $conn->close();
    echo "✅ Conexión cerrada correctamente\n";
}

echo "📝 Estado: Sistema Sequoia Speed verificado con configuración actualizada\n";
echo "🕒 Hora: " . date('Y-m-d H:i:s') . "\n";
?>
