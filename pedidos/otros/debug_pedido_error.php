<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Diagnóstico del Error de Pedidos</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #1e1e1e; color: #cccccc; }
    .success { color: #00ff00; font-weight: bold; }
    .error { color: #ff6b6b; font-weight: bold; }
    .warning { color: #ffa500; font-weight: bold; }
    .info { color: #007aff; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #444; padding: 8px; text-align: left; }
    th { background-color: #333; }
    .test-section { border: 1px solid #444; padding: 15px; margin: 10px 0; border-radius: 5px; background: #252526; }
</style>";

require_once "conexion.php";

echo "<div class='test-section'>";
echo "<h3>📊 1. Verificar estructura de tabla productos</h3>";

try {
    $result = $conn->query("DESCRIBE productos");
    if ($result) {
        echo "<table>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Clave</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<div class='success'>✅ Tabla productos accesible</div>";
    } else {
        echo "<div class='error'>❌ Error al acceder tabla productos: " . $conn->error . "</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Excepción al verificar tabla productos: " . $e->getMessage() . "</div>";
}
echo "</div>";

echo "<div class='test-section'>";
echo "<h3>📊 2. Verificar estructura de tabla pedido_detalle</h3>";

try {
    $result = $conn->query("DESCRIBE pedido_detalle");
    if ($result) {
        echo "<table>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Clave</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<div class='success'>✅ Tabla pedido_detalle accesible</div>";
    } else {
        echo "<div class='error'>❌ Error al acceder tabla pedido_detalle: " . $conn->error . "</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Excepción al verificar tabla pedido_detalle: " . $e->getMessage() . "</div>";
}
echo "</div>";

echo "<div class='test-section'>";
echo "<h3>🧪 3. Probar inserción de producto personalizado</h3>";

try {
    // Intentar insertar un producto de prueba
    $test_nombre = "Producto Test " . date('H:i:s');
    $test_precio = 25000;
    
    echo "<div class='info'>Intentando insertar: $test_nombre - Precio: $test_precio</div>";
    
    $stmt = $conn->prepare("INSERT INTO productos (nombre, precio, activo, categoria) VALUES (?, ?, 1, 'Personalizado')");
    if ($stmt === false) {
        echo "<div class='error'>❌ Error al preparar consulta: " . $conn->error . "</div>";
    } else {
        $stmt->bind_param("sd", $test_nombre, $test_precio);
        $result = $stmt->execute();
        
        if ($result === false) {
            echo "<div class='error'>❌ Error al ejecutar inserción: " . $stmt->error . "</div>";
            echo "<div class='error'>Error MySQL: " . $conn->error . "</div>";
        } else {
            $new_id = $conn->insert_id;
            echo "<div class='success'>✅ Producto insertado correctamente con ID: $new_id</div>";
            
            // Limpiar el producto de prueba
            $delete_stmt = $conn->prepare("DELETE FROM productos WHERE id = ?");
            $delete_stmt->bind_param("i", $new_id);
            $delete_stmt->execute();
            echo "<div class='info'>🗑️ Producto de prueba eliminado</div>";
            $delete_stmt->close();
        }
        $stmt->close();
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Excepción al probar inserción: " . $e->getMessage() . "</div>";
}
echo "</div>";

echo "<div class='test-section'>";
echo "<h3>📄 4. Revisar últimas entradas del log de errores</h3>";

$log_file = __DIR__ . '/debug.log';
if (file_exists($log_file)) {
    echo "<div class='info'>📂 Archivo de log encontrado: $log_file</div>";
    $log_content = file_get_contents($log_file);
    $lines = explode("\n", $log_content);
    $recent_lines = array_slice($lines, -20); // Últimas 20 líneas
    
    echo "<pre style='background: #000; color: #0f0; padding: 10px; border-radius: 5px; overflow-x: auto;'>";
    foreach ($recent_lines as $line) {
        if (trim($line)) {
            echo htmlspecialchars($line) . "\n";
        }
    }
    echo "</pre>";
} else {
    echo "<div class='warning'>⚠️ Archivo de log no encontrado</div>";
}
echo "</div>";

echo "<div class='test-section'>";
echo "<h3>🔧 5. Simulación de carrito completo</h3>";

try {
    echo "<div class='info'>Simulando guardado de pedido con producto personalizado...</div>";
    
    // Simular datos del carrito
    $test_carrito = [
        [
            'id' => 0,
            'nombre' => 'Camiseta Personalizada Test',
            'precio' => 35000,
            'cantidad' => 2,
            'talla' => 'L',
            'personalizado' => true
        ]
    ];
    
    $test_monto = 70000;
    
    echo "<div class='info'>Carrito de prueba:</div>";
    echo "<pre style='background: #222; color: #ccc; padding: 10px; border-radius: 5px;'>";
    echo json_encode($test_carrito, JSON_PRETTY_PRINT);
    echo "</pre>";
    
    // 1. Insertar pedido principal
    $stmt_main = $conn->prepare("INSERT INTO pedidos_detal (pedido, monto, estado) VALUES (?, ?, 'sin_enviar')");
    if ($stmt_main === false) {
        echo "<div class='error'>❌ Error preparando pedido principal: " . $conn->error . "</div>";
    } else {
        $pedido_str = "Camiseta Personalizada Test";
        $stmt_main->bind_param("sd", $pedido_str, $test_monto);
        
        if ($stmt_main->execute() === false) {
            echo "<div class='error'>❌ Error ejecutando pedido principal: " . $stmt_main->error . "</div>";
        } else {
            $test_pedido_id = $conn->insert_id;
            echo "<div class='success'>✅ Pedido principal creado con ID: $test_pedido_id</div>";
            
            // 2. Insertar producto personalizado
            $item = $test_carrito[0];
            $check_stmt = $conn->prepare("SELECT id FROM productos WHERE nombre = ? LIMIT 1");
            $check_stmt->bind_param("s", $item['nombre']);
            $check_stmt->execute();
            $check_stmt->store_result();
            
            $producto_id = 0;
            if ($check_stmt->num_rows == 0) {
                // Producto no existe, insertarlo
                $insert_stmt = $conn->prepare("INSERT INTO productos (nombre, precio, activo, categoria) VALUES (?, ?, 1, 'Personalizado')");
                $insert_stmt->bind_param("sd", $item['nombre'], $item['precio']);
                
                if ($insert_stmt->execute() === false) {
                    echo "<div class='error'>❌ ERROR AQUÍ - Al insertar producto: " . $insert_stmt->error . "</div>";
                } else {
                    $producto_id = $conn->insert_id;
                    echo "<div class='success'>✅ Producto personalizado creado con ID: $producto_id</div>";
                }
                $insert_stmt->close();
            } else {
                echo "<div class='info'>ℹ️ Producto ya existe</div>";
            }
            $check_stmt->close();
            
            // 3. Insertar detalle del pedido
            if ($producto_id > 0) {
                $detail_stmt = $conn->prepare("INSERT INTO pedido_detalle (pedido_id, producto_id, nombre, precio, cantidad, talla) VALUES (?, ?, ?, ?, ?, ?)");
                $detail_stmt->bind_param("iisdis", $test_pedido_id, $producto_id, $item['nombre'], $item['precio'], $item['cantidad'], $item['talla']);
                
                if ($detail_stmt->execute() === false) {
                    echo "<div class='error'>❌ Error insertando detalle: " . $detail_stmt->error . "</div>";
                } else {
                    echo "<div class='success'>✅ Detalle del pedido insertado correctamente</div>";
                }
                $detail_stmt->close();
            }
            
            // Limpiar datos de prueba
            $conn->query("DELETE FROM pedido_detalle WHERE pedido_id = $test_pedido_id");
            $conn->query("DELETE FROM pedidos_detal WHERE id = $test_pedido_id");
            if ($producto_id > 0) {
                $conn->query("DELETE FROM productos WHERE id = $producto_id");
            }
            echo "<div class='info'>🧹 Datos de prueba limpiados</div>";
        }
        $stmt_main->close();
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Excepción en simulación: " . $e->getMessage() . "</div>";
}
echo "</div>";

echo "<div class='test-section'>";
echo "<h3>💡 6. Posibles soluciones</h3>";
echo "<div class='info'>";
echo "Si el error persiste, posibles causas:<br>";
echo "• Permisos insuficientes en la base de datos<br>";
echo "• Campos obligatorios faltantes en tabla productos<br>";
echo "• Restricciones de foreign key<br>";
echo "• Longitud de datos excede límites de columnas<br>";
echo "• Caracteres especiales en nombres de productos<br>";
echo "</div>";
echo "</div>";

$conn->close();
?>
