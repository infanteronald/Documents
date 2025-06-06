<?php
// Debug Bold Integration - Test con pedido existente
require_once "conexion.php";

echo "<h1>🔍 Debug Bold Integration</h1>";

// Probar con un pedido_id específico
$pedido_id = 88; // Un pedido que sabemos que existe
$detalles = [];
$monto = 0;

// Simular el proceso del index.php
if ($pedido_id) {
    $res = $conn->query("SELECT * FROM pedido_detalle WHERE pedido_id = $pedido_id");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $detalles[] = $row;
            $monto += $row['precio'] * $row['cantidad'];
        }
    }
}

echo "<h2>📊 Información del Pedido</h2>";
echo "<p><strong>Pedido ID:</strong> $pedido_id</p>";
echo "<p><strong>Detalles encontrados:</strong> " . count($detalles) . "</p>";
echo "<p><strong>Monto calculado:</strong> $" . number_format($monto, 0, ',', '.') . "</p>";

echo "<h2>🧪 Test JavaScript Variable</h2>";
?>
<script>
// Test de la variable que estaba causando problemas
console.log('🔧 Iniciando test de variable $monto...');

let monto = <?php echo $monto > 0 ? $monto : 0; ?>;
console.log('✅ Monto desde PHP:', monto);
console.log('✅ Tipo de variable:', typeof monto);
console.log('✅ Es un número válido:', Number.isFinite(monto));
console.log('✅ Es mayor que 0:', monto > 0);

// Test de la función initializeBoldPayment simplificada
function testBoldFunction() {
    console.log('🚀 Test initializeBoldPayment() - PRIMER LOG');
    
    try {
        console.log('💰 Monto disponible:', monto);
        
        if (monto > 0) {
            console.log('✅ Monto válido, continúa con Bold...');
            return true;
        } else {
            console.log('⚠️ Monto es 0, usando checkout abierto...');
            return true;
        }
    } catch (error) {
        console.error('❌ Error en test:', error);
        return false;
    }
}

// Ejecutar test
document.addEventListener('DOMContentLoaded', function() {
    console.log('📋 Página cargada, ejecutando test...');
    const result = testBoldFunction();
    console.log('🎯 Resultado del test:', result);
    
    // Mostrar resultados en la página
    document.body.innerHTML += '<div style="background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 5px;">';
    document.body.innerHTML += '<h3>Resultados del Test:</h3>';
    document.body.innerHTML += '<p>✅ Monto JavaScript: ' + monto + '</p>';
    document.body.innerHTML += '<p>✅ Tipo: ' + typeof monto + '</p>';
    document.body.innerHTML += '<p>✅ Es válido: ' + Number.isFinite(monto) + '</p>';
    document.body.innerHTML += '<p>✅ Test exitoso: ' + result + '</p>';
    document.body.innerHTML += '</div>';
});
</script>

<p><strong>🔗 URLs de prueba:</strong></p>
<ul>
    <li><a href="index.php?pedido=88" target="_blank">Test con pedido_id=88</a></li>
    <li><a href="index.php" target="_blank">Test sin pedido_id</a></li>
    <li><a href="test_monto.php" target="_blank">Test simple de variable</a></li>
</ul>
