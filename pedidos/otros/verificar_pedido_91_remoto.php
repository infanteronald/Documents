<?php
// Script para verificar el pedido #91 en el servidor remoto
echo "=== VERIFICACIÓN PEDIDO 91 - SERVIDOR REMOTO ===\n\n";

// Configuración de conexión a la base de datos (ajustar según el servidor)
$servidor = "localhost";
$usuario = "motodota_ronald";  // Ajustar según configuración real
$password = ""; // Se debe proporcionar
$base_datos = "motodota_sequoia";

// Intentar conexión
$conn = new mysqli($servidor, $usuario, $password, $base_datos);

if ($conn->connect_error) {
    echo "ERROR: No se pudo conectar a la base de datos: " . $conn->connect_error . "\n";
    echo "Verifique las credenciales de conexión.\n";
    exit(1);
}

echo "✅ Conexión a la base de datos exitosa\n\n";

// Verificar pedido en pedidos_detal
echo "--- 1. Verificar datos en pedidos_detal ---\n";
$sql = "SELECT * FROM pedidos_detal WHERE id = 91";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "✅ Pedido encontrado:\n";
        echo "   ID: " . $row['id'] . "\n";
        echo "   Cliente: " . $row['nombre'] . "\n";
        echo "   Teléfono: " . $row['telefono'] . "\n";
        echo "   Email: " . $row['email'] . "\n";
        echo "   Monto: $" . number_format($row['monto'], 0) . "\n";
        echo "   Estado: " . $row['estado'] . "\n";
        echo "   Fecha: " . $row['fecha'] . "\n";
        echo "   Método pago: " . $row['metodo_pago'] . "\n";
        if (isset($row['order_id'])) {
            echo "   Order ID: " . $row['order_id'] . "\n";
        }
        echo "\n";
    }
} else {
    echo "❌ No se encontró pedido con ID 91 en pedidos_detal\n\n";
}

// Verificar productos del pedido
echo "--- 2. Verificar productos en pedido_detalle ---\n";
$sql = "SELECT * FROM pedido_detalle WHERE pedido_id = 91";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $total_productos = 0;
    $valor_total = 0;
    
    echo "✅ Productos del pedido:\n";
    while ($row = $result->fetch_assoc()) {
        echo "   - " . $row['nombre'] . "\n";
        echo "     Precio: $" . number_format($row['precio'], 0) . "\n";
        echo "     Cantidad: " . $row['cantidad'] . "\n";
        echo "     Talla: " . $row['talla'] . "\n";
        echo "     Subtotal: $" . number_format($row['precio'] * $row['cantidad'], 0) . "\n";
        echo "\n";
        
        $total_productos += $row['cantidad'];
        $valor_total += ($row['precio'] * $row['cantidad']);
    }
    
    echo "📊 Resumen productos:\n";
    echo "   Total productos: " . $total_productos . "\n";
    echo "   Valor total calculado: $" . number_format($valor_total, 0) . "\n\n";
} else {
    echo "❌ No se encontraron productos para el pedido 91\n\n";
}

// Verificar logs de transacciones Bold
echo "--- 3. Verificar logs de transacciones Bold ---\n";
$sql = "SELECT * FROM bold_transactions WHERE order_id LIKE '%91%' OR reference LIKE '%91%' ORDER BY created_at DESC LIMIT 10";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo "✅ Transacciones Bold relacionadas:\n";
    while ($row = $result->fetch_assoc()) {
        echo "   - ID: " . $row['id'] . "\n";
        echo "     Order ID: " . $row['order_id'] . "\n";
        echo "     Estado: " . $row['status'] . "\n";
        echo "     Monto: $" . number_format($row['amount'], 0) . "\n";
        echo "     Fecha: " . $row['created_at'] . "\n";
        echo "\n";
    }
} else {
    echo "ℹ️ No se encontraron transacciones Bold para el pedido 91\n\n";
}

// Verificar configuración Bold actual
echo "--- 4. Verificar configuración Bold ---\n";
$config_file = 'dual_mode_config.php';
if (file_exists($config_file)) {
    include $config_file;
    echo "✅ Configuración Bold cargada:\n";
    if (defined('ENHANCED_WEBHOOK_PERCENTAGE')) {
        echo "   Porcentaje webhook mejorado: " . ENHANCED_WEBHOOK_PERCENTAGE . "%\n";
    }
    if (defined('BOLD_PRODUCTION_MODE')) {
        echo "   Modo producción: " . (BOLD_PRODUCTION_MODE ? 'SÍ' : 'NO') . "\n";
    }
    echo "\n";
} else {
    echo "⚠️ No se encontró archivo de configuración Bold\n\n";
}

// Verificar archivos críticos del sistema
echo "--- 5. Verificar archivos del sistema Bold ---\n";
$archivos_criticos = [
    'index.php' => 'Formulario principal',
    'bold_payment.php' => 'Ventana de pago Bold',
    'bold_webhook_enhanced.php' => 'Webhook mejorado',
    'bold_hash.php' => 'Generador de hash',
    'dual_mode_config.php' => 'Configuración del sistema'
];

foreach ($archivos_criticos as $archivo => $descripcion) {
    if (file_exists($archivo)) {
        $size = filesize($archivo);
        echo "   ✅ $archivo ($descripcion) - " . number_format($size) . " bytes\n";
    } else {
        echo "   ❌ $archivo ($descripcion) - NO ENCONTRADO\n";
    }
}

echo "\n--- 6. Test de URL del pedido 91 ---\n";
$url_pedido = "https://sequoiaspeed.com.co/pedidos/index.php?pedido=91";
echo "URL a probar: $url_pedido\n";

// Simular acceso (básico)
if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url_pedido);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        echo "✅ URL responde correctamente (HTTP $http_code)\n";
        if (strpos($response, 'PSE Bold') !== false) {
            echo "✅ Opción 'PSE Bold' encontrada en la página\n";
        } else {
            echo "⚠️ No se encontró la opción 'PSE Bold' en la página\n";
        }
    } else {
        echo "❌ Error al acceder a la URL (HTTP $http_code)\n";
    }
} else {
    echo "ℹ️ cURL no disponible para test de URL\n";
}

$conn->close();
echo "\n=== FIN VERIFICACIÓN PEDIDO 91 ===\n";
?>
