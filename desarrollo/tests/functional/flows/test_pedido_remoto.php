<?php
// Script de prueba para simular el envío de un pedido
echo "<h2>🧪 Prueba de Envío de Pedido</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #1e1e1e; color: #cccccc; }
    .success { color: #00ff00; font-weight: bold; }
    .error { color: #ff6b6b; font-weight: bold; }
    .info { color: #007aff; }
    pre { background: #000; color: #0f0; padding: 10px; border-radius: 5px; overflow-x: auto; }
</style>";

// Datos de prueba que simularían lo que envía el frontend
$test_data = [
    'carrito' => [
        [
            'id' => 0,
            'nombre' => 'Camiseta Prueba Personalizada',
            'precio' => 45000,
            'cantidad' => 1,
            'talla' => 'M',
            'personalizado' => true
        ]
    ],
    'monto' => 45000
];

echo "<div class='info'>📦 Datos de prueba que se enviarán:</div>";
echo "<pre>" . json_encode($test_data, JSON_PRETTY_PRINT) . "</pre>";

// Simular la llamada AJAX
$json_data = json_encode($test_data);

echo "<div class='info'>📡 Enviando datos a guardar_pedido.php...</div>";

// Usar cURL para simular la petición AJAX
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/guardar_pedido.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    'Content-Length: ' . strlen($json_data)
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "<div class='info'>📊 Respuesta del servidor:</div>";
echo "<div class='info'>Código HTTP: $http_code</div>";

if ($curl_error) {
    echo "<div class='error'>❌ Error cURL: $curl_error</div>";
} else {
    echo "<div class='info'>Respuesta cruda:</div>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    
    // Intentar decodificar la respuesta JSON
    $response_data = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<div class='info'>Respuesta JSON decodificada:</div>";
        echo "<pre>" . json_encode($response_data, JSON_PRETTY_PRINT) . "</pre>";
        
        if (isset($response_data['success']) && $response_data['success']) {
            echo "<div class='success'>✅ Pedido guardado exitosamente!</div>";
            if (isset($response_data['pedido_id'])) {
                echo "<div class='success'>ID del pedido: " . $response_data['pedido_id'] . "</div>";
            }
        } else {
            echo "<div class='error'>❌ Error al guardar pedido:</div>";
            if (isset($response_data['error'])) {
                echo "<div class='error'>" . htmlspecialchars($response_data['error']) . "</div>";
            }
            if (isset($response_data['debug_info'])) {
                echo "<div class='error'>Info de debug:</div>";
                echo "<pre>" . json_encode($response_data['debug_info'], JSON_PRETTY_PRINT) . "</pre>";
            }
        }
    } else {
        echo "<div class='error'>❌ Error al decodificar respuesta JSON: " . json_last_error_msg() . "</div>";
    }
}

echo "<div class='info'>📄 Para más detalles, revisa el archivo debug.log</div>";
?>
