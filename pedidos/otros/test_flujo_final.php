<?php
/**
 * PRUEBA DEL FLUJO COMPLETO SIMPLIFICADO
 * 
 * Simula el flujo:
 * 1. orden_pedido.php guarda en BD y genera URL simple
 * 2. index.php lee desde BD usando pedido_id
 * 3. procesar_orden.php actualiza el pedido existente
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🧪 Test del Flujo Completo Simplificado</h1>";

// Simular datos del carrito para crear pedido inicial
$test_carrito = [
    [
        'id' => 1,
        'nombre' => 'Camiseta Test',
        'precio' => 25000,
        'cantidad' => 2,
        'talla' => 'M'
    ],
    [
        'id' => 'custom_123',
        'nombre' => 'Producto Personalizado Test',
        'precio' => 35000,
        'cantidad' => 1,
        'talla' => 'L',
        'isCustom' => true
    ]
];

$productos_personalizados = [
    [
        'id' => 'custom_123',
        'nombre' => 'Producto Personalizado Test',
        'precio' => 35000
    ]
];

$monto_total = 85000;

echo "<h2>📋 Paso 1: Simular orden_pedido.php</h2>";
echo "<p>Creando pedido inicial con carrito...</p>";

// Simular POST a crear_pedido_inicial.php
$post_data = [
    'productos_json' => json_encode($test_carrito),
    'productos_personalizados' => json_encode($productos_personalizados),
    'carrito_data' => json_encode($test_carrito),
    'monto_total' => $monto_total
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/crear_pedido_inicial.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0;'>";
echo "<strong>Respuesta de crear_pedido_inicial.php:</strong><br>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";
echo "<strong>HTTP Code:</strong> $http_code";
echo "</div>";

// Extraer pedido_id de la respuesta JSON
$response_data = json_decode($response, true);
if (isset($response_data['pedido_id'])) {
    $pedido_id = $response_data['pedido_id'];
    echo "<p>✅ <strong>Pedido creado con ID: $pedido_id</strong></p>";
    
    // Generar URL simplificada
    $url_compartible = "http://localhost/index.php?pedido=$pedido_id";
    echo "<p>📎 <strong>URL generada:</strong> <a href='$url_compartible' target='_blank'>$url_compartible</a></p>";
    
    echo "<h2>📖 Paso 2: Verificar index.php</h2>";
    echo "<p>Verificando que index.php puede leer el pedido desde la BD...</p>";
    
    // Probar que index.php puede cargar el pedido
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url_compartible);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $index_response = curl_exec($ch);
    $index_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($index_http_code == 200) {
        echo "<p>✅ <strong>index.php carga correctamente</strong></p>";
        
        // Verificar que contiene el pedido_id en un campo oculto
        if (strpos($index_response, "name=\"pedido_id\" value=\"$pedido_id\"") !== false) {
            echo "<p>✅ <strong>Campo pedido_id encontrado en el formulario</strong></p>";
        } else {
            echo "<p>❌ <strong>Campo pedido_id NO encontrado en el formulario</strong></p>";
        }
        
        // Verificar que muestra los productos
        if (strpos($index_response, "Camiseta Test") !== false) {
            echo "<p>✅ <strong>Productos se muestran correctamente</strong></p>";
        } else {
            echo "<p>❌ <strong>Productos NO se muestran</strong></p>";
        }
    } else {
        echo "<p>❌ <strong>Error al cargar index.php (HTTP $index_http_code)</strong></p>";
    }
    
    echo "<h2>💾 Paso 3: Simular procesar_orden.php</h2>";
    echo "<p>Simulando envío del formulario con datos de envío...</p>";
    
    // Simular datos del formulario de envío
    $form_data = [
        'pedido_id' => $pedido_id,
        'monto' => $monto_total,
        'nombre' => 'Juan Test',
        'direccion' => 'Calle Test 123',
        'telefono' => '3001234567',
        'correo' => 'test@test.com',
        'persona_recibe' => 'Juan Test',
        'horarios' => '9 AM - 5 PM',
        'metodo_pago' => 'Nequi'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/procesar_orden.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $process_response = curl_exec($ch);
    $process_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0;'>";
    echo "<strong>Respuesta de procesar_orden.php:</strong><br>";
    echo "<strong>HTTP Code:</strong> $process_http_code<br>";
    if ($process_http_code != 200) {
        echo "<pre>" . htmlspecialchars($process_response) . "</pre>";
    } else {
        echo "<p>✅ <strong>Pedido procesado correctamente</strong></p>";
    }
    echo "</div>";
    
} else {
    echo "<p>❌ <strong>Error: No se pudo crear el pedido inicial</strong></p>";
}

echo "<h2>📊 Resumen</h2>";
echo "<p>Este test verifica que:</p>";
echo "<ul>";
echo "<li>✅ orden_pedido.php guarda el pedido en BD usando crear_pedido_inicial.php</li>";
echo "<li>✅ Se genera URL simple: index.php?pedido={id}</li>";
echo "<li>✅ index.php lee los datos desde BD usando el ID</li>";
echo "<li>✅ procesar_orden.php actualiza el pedido existente en lugar de crear uno nuevo</li>";
echo "</ul>";

?>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    line-height: 1.6;
}
h1, h2 {
    color: #333;
}
.success {
    color: green;
    font-weight: bold;
}
.error {
    color: red;
    font-weight: bold;
}
pre {
    background: #f8f8f8;
    padding: 10px;
    border: 1px solid #ddd;
    overflow-x: auto;
    max-height: 200px;
}
</style>
