<?php
// Test de URL compartible generada desde orden_pedido.php

// Simular datos de carrito como los generaría orden_pedido.php
$carrito_test = [
    [
        'key' => '1_M',
        'id' => 1,
        'nombre' => 'Camiseta Nike Deportiva',
        'precio' => 45000,
        'cantidad' => 2,
        'talla' => 'M',
        'isCustom' => false
    ],
    [
        'key' => 'custom_1703123456789_L',
        'id' => 'custom_1703123456789',
        'nombre' => 'Chaqueta Personalizada',
        'precio' => 85000,
        'cantidad' => 1,
        'talla' => 'L',
        'isCustom' => true
    ]
];

$productos_personalizados_test = [
    [
        'id' => 'custom_1703123456789',
        'nombre' => 'Chaqueta Personalizada',
        'precio' => 85000,
        'categoria' => 'Personalizado'
    ]
];

// Crear texto del pedido
$texto_pedido = "PEDIDO CON TALLAS:\n\n";
$total = 0;

foreach ($carrito_test as $item) {
    $subtotal = $item['precio'] * $item['cantidad'];
    $total += $subtotal;
    $texto_pedido .= "• " . $item['nombre'];
    if ($item['isCustom']) {
        $texto_pedido .= " (PERSONALIZADO)";
    }
    $texto_pedido .= "\n";
    $texto_pedido .= "  Talla: " . $item['talla'] . "\n";
    $texto_pedido .= "  Cantidad: " . $item['cantidad'] . "\n";
    $texto_pedido .= "  Precio: $" . number_format($item['precio'], 0) . "\n";
    $texto_pedido .= "  Subtotal: $" . number_format($subtotal, 0) . "\n\n";
}

$texto_pedido .= "TOTAL: $" . number_format($total, 0);

// Generar URL compartible
$carrito_data = urlencode(json_encode($carrito_test));
$productos_personalizados_data = urlencode(json_encode($productos_personalizados_test));
$shareable_url = "http://localhost/index.php?" .
    "pedido_text=" . urlencode($texto_pedido) . "&" .
    "monto=" . $total . "&" .
    "carrito=" . $carrito_data . "&" .
    "productos_personalizados=" . $productos_personalizados_data;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Test URL Compartible</title>
    <link rel="stylesheet" href="apple-ui.css">
    <style>
        body {
            background: #1e1e1e;
            color: #e0e0e0;
            font-family: -apple-system, BlinkMacSystemFont, 'Inter', sans-serif;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #232323;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        .test-section {
            background: #2a2a2a;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #444;
        }
        .url-box {
            background: #1a1a1a;
            border: 1px solid #007aff;
            border-radius: 6px;
            padding: 12px;
            font-family: monospace;
            word-break: break-all;
            font-size: 13px;
            margin: 10px 0;
        }
        .btn {
            background: #007aff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            margin: 10px 10px 10px 0;
            cursor: pointer;
            font-weight: 500;
        }
        .btn:hover {
            background: #0056d3;
        }
        pre {
            background: #1a1a1a;
            border: 1px solid #444;
            border-radius: 6px;
            padding: 15px;
            overflow-x: auto;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 style="color: #007aff; text-align: center;">🧪 Test URL Compartible</h1>
        
        <div class="test-section">
            <h3>📊 Datos del Carrito de Prueba</h3>
            <pre><?= htmlspecialchars(print_r($carrito_test, true)) ?></pre>
        </div>
        
        <div class="test-section">
            <h3>📝 Texto del Pedido Generado</h3>
            <pre><?= htmlspecialchars($texto_pedido) ?></pre>
        </div>
        
        <div class="test-section">
            <h3>🔗 URL Compartible Generada</h3>
            <div class="url-box"><?= htmlspecialchars($shareable_url) ?></div>
            
            <a href="<?= $shareable_url ?>" class="btn" target="_blank">🚀 Probar URL en index.php</a>
            <button class="btn" onclick="copiarURL()">📋 Copiar URL</button>
        </div>
        
        <div class="test-section">
            <h3>💰 Resumen</h3>
            <p><strong>Total del pedido:</strong> $<?= number_format($total, 0) ?></p>
            <p><strong>Productos regulares:</strong> 1</p>
            <p><strong>Productos personalizados:</strong> 1</p>
        </div>
    </div>
    
    <script>
        function copiarURL() {
            const url = <?= json_encode($shareable_url) ?>;
            navigator.clipboard.writeText(url).then(() => {
                alert('URL copiada al portapapeles');
            }).catch(() => {
                // Fallback para navegadores más antiguos
                const textarea = document.createElement('textarea');
                textarea.value = url;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                alert('URL copiada al portapapeles');
            });
        }
    </script>
</body>
</html>
