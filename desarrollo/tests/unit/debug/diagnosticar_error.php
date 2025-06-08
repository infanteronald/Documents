<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/diagnostic.log');

echo "<h2>🔍 Diagnóstico Completo del Sistema</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .test-section { border: 1px solid #ccc; padding: 15px; margin: 10px 0; border-radius: 5px; }
</style>";

// 1. Verificar conexión a base de datos
echo "<div class='test-section'>";
echo "<h3>1. 🗄️ Conexión a Base de Datos</h3>";
try {
    require_once "conexion.php";
    if ($conn && $conn->ping()) {
        echo "<span class='success'>✅ Conexión exitosa</span><br>";
        echo "Servidor: " . $conn->host_info . "<br>";
        echo "Versión MySQL: " . $conn->server_info . "<br>";
        echo "Charset: " . $conn->character_set_name() . "<br>";
    } else {
        echo "<span class='error'>❌ Error de conexión</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Error: " . $e->getMessage() . "</span><br>";
}
echo "</div>";

// 2. Verificar estructura de tablas críticas
echo "<div class='test-section'>";
echo "<h3>2. 📋 Estructura de Tablas</h3>";

$tablas = ['pedidos_detal', 'pedido_detalle', 'productos'];
foreach ($tablas as $tabla) {
    echo "<h4>Tabla: $tabla</h4>";
    $result = $conn->query("SHOW TABLE STATUS WHERE Name = '$tabla'");
    if ($result && $result->num_rows > 0) {
        $status = $result->fetch_assoc();
        echo "<span class='success'>✅ Existe</span> - Filas: " . $status['Rows'] . " - Engine: " . $status['Engine'] . "<br>";
        
        // Verificar campos
        $fields = $conn->query("DESCRIBE $tabla");
        if ($fields) {
            echo "<table><tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Clave</th><th>Default</th></tr>";
            while ($field = $fields->fetch_assoc()) {
                echo "<tr><td>{$field['Field']}</td><td>{$field['Type']}</td><td>{$field['Null']}</td><td>{$field['Key']}</td><td>{$field['Default']}</td></tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<span class='error'>❌ No existe</span><br>";
    }
}
echo "</div>";

// 3. Simular inserción de pedido
echo "<div class='test-section'>";
echo "<h3>3. 🧪 Prueba de Inserción de Pedido</h3>";

$test_carrito = [
    [
        'id' => 999,
        'nombre' => 'Producto Test',
        'precio' => 100000,
        'cantidad' => 1,
        'personalizado' => false
    ]
];
$test_monto = 100000;

echo "<h4>Datos de prueba:</h4>";
echo "Carrito: " . json_encode($test_carrito) . "<br>";
echo "Monto: $test_monto<br><br>";

try {
    // Simular el proceso completo
    $conn->autocommit(FALSE); // Iniciar transacción
    
    // 1. Insertar en pedidos_detal
    $nombres = array_map(function($item) { return $item['nombre']; }, $test_carrito);
    $pedido_str = implode(', ', $nombres);
    
    $stmt_main = $conn->prepare("INSERT INTO pedidos_detal (pedido, monto, estado) VALUES (?, ?, 'sin_enviar')");
    if (!$stmt_main) {
        throw new Exception("Error preparando consulta principal: " . $conn->error);
    }
    
    $stmt_main->bind_param("sd", $pedido_str, $test_monto);
    if (!$stmt_main->execute()) {
        throw new Exception("Error ejecutando consulta principal: " . $stmt_main->error);
    }
    
    $pedido_id = $conn->insert_id;
    echo "<span class='success'>✅ Pedido principal insertado (ID: $pedido_id)</span><br>";
    
    // 2. Insertar detalles
    $stmt_detalle = $conn->prepare("INSERT INTO pedido_detalle (pedido_id, producto_id, nombre, precio, cantidad) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt_detalle) {
        throw new Exception("Error preparando consulta detalle: " . $conn->error);
    }
    
    foreach ($test_carrito as $item) {
        $producto_id = isset($item['personalizado']) && $item['personalizado'] ? 0 : $item['id'];
        $stmt_detalle->bind_param("iisdi", $pedido_id, $producto_id, $item['nombre'], $item['precio'], $item['cantidad']);
        
        if (!$stmt_detalle->execute()) {
            throw new Exception("Error insertando detalle: " . $stmt_detalle->error);
        }
    }
    
    echo "<span class='success'>✅ Detalles insertados correctamente</span><br>";
    
    // Confirmar transacción
    $conn->commit();
    echo "<span class='success'>✅ Transacción completada exitosamente</span><br>";
    
    // Limpiar datos de prueba
    $conn->query("DELETE FROM pedido_detalle WHERE pedido_id = $pedido_id");
    $conn->query("DELETE FROM pedidos_detal WHERE id = $pedido_id");
    echo "<span class='info'>🧹 Datos de prueba eliminados</span><br>";
    
} catch (Exception $e) {
    $conn->rollback();
    echo "<span class='error'>❌ Error en prueba: " . $e->getMessage() . "</span><br>";
}

$conn->autocommit(TRUE); // Restaurar autocommit
echo "</div>";

// 4. Verificar configuración PHP
echo "<div class='test-section'>";
echo "<h3>4. ⚙️ Configuración PHP</h3>";

$php_checks = [
    'PHP Version' => phpversion(),
    'MySQL Extension' => extension_loaded('mysqli') ? '✅ Cargada' : '❌ No disponible',
    'JSON Extension' => extension_loaded('json') ? '✅ Cargada' : '❌ No disponible',
    'Memory Limit' => ini_get('memory_limit'),
    'Max Execution Time' => ini_get('max_execution_time') . ' segundos',
    'Post Max Size' => ini_get('post_max_size'),
    'Upload Max Filesize' => ini_get('upload_max_filesize'),
    'Error Reporting' => error_reporting(),
    'Display Errors' => ini_get('display_errors') ? 'Activado' : 'Desactivado',
    'Log Errors' => ini_get('log_errors') ? 'Activado' : 'Desactivado',
];

echo "<table>";
foreach ($php_checks as $setting => $value) {
    echo "<tr><td><strong>$setting</strong></td><td>$value</td></tr>";
}
echo "</table>";
echo "</div>";

// 5. Verificar permisos de archivos
echo "<div class='test-section'>";
echo "<h3>5. 📁 Permisos de Archivos</h3>";

$files_to_check = [
    'guardar_pedido.php',
    'conexion.php',
    'procesar_orden.php',
    'debug.log',
    'diagnostic.log'
];

foreach ($files_to_check as $file) {
    $full_path = __DIR__ . '/' . $file;
    if (file_exists($full_path)) {
        $perms = fileperms($full_path);
        $readable = is_readable($full_path) ? '✅' : '❌';
        $writable = is_writable($full_path) ? '✅' : '❌';
        echo "$file: Lectura $readable Escritura $writable (Permisos: " . decoct($perms & 0777) . ")<br>";
    } else {
        echo "$file: <span class='warning'>⚠️ No existe</span><br>";
    }
}
echo "</div>";

// 6. Simular request AJAX
echo "<div class='test-section'>";
echo "<h3>6. 📡 Simulación de Request AJAX</h3>";

echo "<h4>Simulando llamada a guardar_pedido.php:</h4>";

$test_data = [
    'carrito' => $test_carrito,
    'monto' => $test_monto
];

$json_data = json_encode($test_data);
echo "Datos JSON: " . $json_data . "<br><br>";

// Simular el procesamiento interno
$_POST = []; // Limpiar POST
$original_input = $json_data;

echo "Simulando procesamiento...<br>";

try {
    $data = json_decode($json_data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Error JSON: " . json_last_error_msg());
    }
    
    $carrito = $data['carrito'] ?? [];
    $monto = $data['monto'] ?? 0;
    
    if (empty($carrito)) {
        throw new Exception("Carrito vacío");
    }
    
    echo "<span class='success'>✅ Datos procesados correctamente</span><br>";
    echo "Items en carrito: " . count($carrito) . "<br>";
    echo "Monto total: $monto<br>";
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Error en simulación: " . $e->getMessage() . "</span><br>";
}
echo "</div>";

// 7. Verificar logs recientes
echo "<div class='test-section'>";
echo "<h3>7. 📊 Logs Recientes</h3>";

$log_files = ['debug.log', 'diagnostic.log', 'error.log'];
foreach ($log_files as $log_file) {
    $log_path = __DIR__ . '/' . $log_file;
    if (file_exists($log_path) && is_readable($log_path)) {
        echo "<h4>$log_file:</h4>";
        $content = file_get_contents($log_path);
        $lines = explode("\n", $content);
        $recent_lines = array_slice($lines, -10); // Últimas 10 líneas
        echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 3px; max-height: 200px; overflow-y: auto;'>";
        foreach ($recent_lines as $line) {
            if (trim($line)) echo htmlspecialchars($line) . "\n";
        }
        echo "</pre>";
    } else {
        echo "<h4>$log_file:</h4>";
        echo "<span class='info'>No existe o no es legible</span><br>";
    }
}
echo "</div>";

echo "<div class='test-section'>";
echo "<h3>8. 🎯 Recomendaciones</h3>";
echo "<ul>";
echo "<li><strong>Si todo está verde:</strong> El problema puede estar en el lado del cliente (JavaScript) o en la red.</li>";
echo "<li><strong>Si hay errores de BD:</strong> Verificar la configuración de conexión y permisos.</li>";
echo "<li><strong>Si hay errores PHP:</strong> Revisar configuración del servidor y logs detallados.</li>";
echo "<li><strong>Verificar red:</strong> Probar con herramientas como curl o Postman para aislar el problema.</li>";
echo "</ul>";
echo "</div>";

// Log de la ejecución del diagnóstico
error_log("=== DIAGNÓSTICO EJECUTADO === " . date('Y-m-d H:i:s'));
?>
