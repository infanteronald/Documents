<?php
require_once "conexion.php";
$pedido_id = isset($_GET['pedido']) ? intval($_GET['pedido']) : 0;
$detalles = [];
$monto = 0;

// Procesamiento de pedido existente por ID
if ($pedido_id) {
    $res = $conn->query("SELECT * FROM pedido_detalle WHERE pedido_id = $pedido_id");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $detalles[] = $row;
            $monto += $row['precio'] * $row['cantidad'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Test Bold - Función Simplificada</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #1e1e1e; color: #fff; }
        .container { max-width: 600px; margin: 0 auto; }
        .bold-container { background: #333; padding: 20px; border-radius: 8px; margin: 20px 0; }
        button { background: #007aff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; }
        button:hover { background: #0056d3; }
        #logs { background: #000; color: #0f0; padding: 10px; border-radius: 5px; font-family: monospace; height: 300px; overflow-y: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Test Bold - Función Simplificada</h1>
        
        <p><strong>Pedido ID:</strong> <?php echo $pedido_id; ?></p>
        <p><strong>Monto:</strong> $<?php echo number_format($monto, 0, ',', '.'); ?></p>
        
        <div class="bold-container">
            <h3>PSE Bold - Pago Seguro</h3>
            <p>Prueba de integración Bold simplificada</p>
            <div id="bold-payment-container">
                <button onclick="testBoldSimplified()">🚀 Probar Bold Simplificado</button>
            </div>
        </div>
        
        <h3>📋 Logs:</h3>
        <div id="logs"></div>
    </div>

    <script>
        // Variables globales embebidas desde PHP
        window.MONTO_PHP = <?php echo json_encode($monto); ?>;
        window.PEDIDO_ID = <?php echo json_encode($pedido_id); ?>;
        
        function log(message) {
            console.log(message);
            document.getElementById('logs').innerHTML += message + '\n';
            document.getElementById('logs').scrollTop = document.getElementById('logs').scrollHeight;
        }
        
        // Función Bold simplificada
        function testBoldSimplified() {
            log('🚀 === INICIO TEST BOLD SIMPLIFICADO ===');
            
            try {
                log('🔍 Verificando variables globales...');
                log('💰 MONTO_PHP: ' + window.MONTO_PHP + ' (tipo: ' + typeof window.MONTO_PHP + ')');
                log('🆔 PEDIDO_ID: ' + window.PEDIDO_ID + ' (tipo: ' + typeof window.PEDIDO_ID + ')');
                
                // Container
                const container = document.getElementById('bold-payment-container');
                if (!container) {
                    log('❌ Container no encontrado');
                    return;
                }
                log('✅ Container encontrado');
                
                // Monto
                let monto = parseInt(window.MONTO_PHP) || 0;
                log('💰 Monto procesado: ' + monto);
                
                // Generar orden
                const orderId = 'TEST-' + Date.now();
                log('🆔 Orden generada: ' + orderId);
                
                // Simular preparación Bold
                container.innerHTML = '<div style="padding: 20px; text-align: center; background: #007aff; color: white; border-radius: 5px;">✅ Bold inicializado correctamente!<br>Orden: ' + orderId + '<br>Monto: $' + monto.toLocaleString() + '</div>';
                
                log('🎉 Bold simulado exitosamente');
                log('🚀 === FIN TEST BOLD SIMPLIFICADO ===');
                
                return true;
                
            } catch (error) {
                log('❌ ERROR: ' + error.message);
                log('❌ Stack: ' + error.stack);
                return false;
            }
        }
        
        // Auto-ejecutar al cargar
        document.addEventListener('DOMContentLoaded', function() {
            log('📄 Página cargada');
            log('🔍 Monto disponible: ' + window.MONTO_PHP);
            log('✅ Listo para probar Bold');
        });
    </script>
</body>
</html>
