<?php
// Test simple para verificar variable $monto
$monto = 150000; // Simular un pedido de 150,000 COP
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Monto</title>
</head>
<body>
    <h1>Test de Variable $monto</h1>
    <p>Monto PHP: $<?php echo number_format($monto, 0, ',', '.'); ?></p>
    
    <script>
        // Esta es la línea que estaba fallando
        let monto = <?php echo $monto > 0 ? $monto : 0; ?>;
        console.log('✅ Monto desde PHP:', monto);
        console.log('✅ Tipo de monto:', typeof monto);
        console.log('✅ Monto es válido:', monto > 0);
        
        // Mostrar en la página
        document.body.innerHTML += '<p>Monto JavaScript: ' + monto + '</p>';
        document.body.innerHTML += '<p>Monto formateado: $' + monto.toLocaleString() + '</p>';
    </script>
</body>
</html>
