<?php
require_once "conexion.php";

// Simular el mismo proceso del index.php
$pedido_id = 88;
$detalles = [];
$monto = 0;

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
    <title>Test Variable Monto - Debug</title>
</head>
<body>
    <h1>🔍 Test Variable Monto</h1>
    
    <h2>Información PHP:</h2>
    <p><strong>Pedido ID:</strong> <?php echo $pedido_id; ?></p>
    <p><strong>Monto calculado:</strong> $<?php echo number_format($monto, 0, ',', '.'); ?></p>
    <p><strong>Monto raw:</strong> <?php echo $monto; ?></p>
    <p><strong>Tipo de $monto:</strong> <?php echo gettype($monto); ?></p>
    <p><strong>JSON encode:</strong> <?php echo json_encode($monto); ?></p>
    
    <h2>Test JavaScript:</h2>
    <div id="results"></div>
    
    <script>
        console.log('=== INICIO TEST VARIABLE MONTO ===');
        
        // Test 1: Variable directa
        console.log('🧪 Test 1: Variable directa');
        try {
            let monto1 = <?php echo $monto > 0 ? $monto : 0; ?>;
            console.log('✅ Test 1 exitoso:', monto1, typeof monto1);
            document.getElementById('results').innerHTML += '<p>✅ Test 1: ' + monto1 + ' (tipo: ' + typeof monto1 + ')</p>';
        } catch (error) {
            console.error('❌ Test 1 falló:', error);
            document.getElementById('results').innerHTML += '<p>❌ Test 1 falló: ' + error.message + '</p>';
        }
        
        // Test 2: JSON encode
        console.log('🧪 Test 2: JSON encode');
        try {
            let monto2 = <?php echo json_encode($monto > 0 ? $monto : 0); ?>;
            console.log('✅ Test 2 exitoso:', monto2, typeof monto2);
            document.getElementById('results').innerHTML += '<p>✅ Test 2: ' + monto2 + ' (tipo: ' + typeof monto2 + ')</p>';
        } catch (error) {
            console.error('❌ Test 2 falló:', error);
            document.getElementById('results').innerHTML += '<p>❌ Test 2 falló: ' + error.message + '</p>';
        }
        
        // Test 3: ParseInt JSON
        console.log('🧪 Test 3: ParseInt JSON');
        try {
            let monto3 = parseInt(<?php echo json_encode($monto > 0 ? $monto : 0); ?>) || 0;
            console.log('✅ Test 3 exitoso:', monto3, typeof monto3);
            document.getElementById('results').innerHTML += '<p>✅ Test 3: ' + monto3 + ' (tipo: ' + typeof monto3 + ')</p>';
        } catch (error) {
            console.error('❌ Test 3 falló:', error);
            document.getElementById('results').innerHTML += '<p>❌ Test 3 falló: ' + error.message + '</p>';
        }
        
        // Test 4: Función simulada como en Bold
        console.log('🧪 Test 4: Función simulada Bold');
        function testBoldFunction() {
            console.log('🚀 testBoldFunction() - INICIO');
            
            try {
                let monto = <?php echo json_encode($monto > 0 ? $monto : 0); ?>;
                console.log('💰 Monto en función:', monto);
                console.log('🎯 testBoldFunction() - FIN EXITOSO');
                return true;
            } catch (error) {
                console.error('❌ Error en función Bold:', error);
                return false;
            }
        }
        
        const resultTest4 = testBoldFunction();
        document.getElementById('results').innerHTML += '<p>✅ Test 4 función: ' + resultTest4 + '</p>';
        
        console.log('=== FIN TEST VARIABLE MONTO ===');
    </script>
</body>
</html>
