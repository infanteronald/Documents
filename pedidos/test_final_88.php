<?php
require_once "conexion.php";

echo "<h2>Verificación final del pedido 88</h2>";

$pedido_id = 88;

echo "<strong>1. Verificando pedido principal en pedidos_detal:</strong><br>";
$res = $conn->query("SELECT * FROM pedidos_detal WHERE id = $pedido_id");
if ($res && $res->num_rows > 0) {
    $pedido = $res->fetch_assoc();
    echo "✅ Pedido encontrado - ID: " . $pedido['id'] . ", Monto: $" . number_format($pedido['monto'], 0, ',', '.') . "<br>";
} else {
    echo "❌ Pedido no encontrado<br>";
}

echo "<br><strong>2. Verificando detalles en pedido_detalle (tabla correcta):</strong><br>";
$res = $conn->query("SELECT * FROM pedido_detalle WHERE pedido_id = $pedido_id");
if ($res && $res->num_rows > 0) {
    echo "✅ Detalles encontrados (" . $res->num_rows . " productos):<br>";
    $total_calculado = 0;
    while ($row = $res->fetch_assoc()) {
        $subtotal = $row['precio'] * $row['cantidad'];
        $total_calculado += $subtotal;
        echo "- " . $row['nombre'] . " (Talla: " . ($row['talla'] ?? 'N/A') . ") - $" . number_format($row['precio'], 0, ',', '.') . " x " . $row['cantidad'] . " = $" . number_format($subtotal, 0, ',', '.') . "<br>";
    }
    echo "<strong>Total calculado: $" . number_format($total_calculado, 0, ',', '.') . "</strong><br>";
} else {
    echo "❌ No se encontraron detalles<br>";
}

echo "<br><strong>3. URL de prueba:</strong><br>";
echo '<a href="index.php?pedido=' . $pedido_id . '" target="_blank">http://localhost/pedidos/index.php?pedido=' . $pedido_id . '</a><br>';

$conn->close();
?>
