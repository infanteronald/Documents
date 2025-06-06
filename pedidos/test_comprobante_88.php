<?php
require_once "conexion.php";

echo "=== TEST COMPROBANTE PEDIDO 88 ===\n\n";

$orden_id = 88;

// Verificar datos en pedidos_detal
echo "--- Datos en pedidos_detal ---\n";
$result = $conn->query("SELECT id, monto, nombre FROM pedidos_detal WHERE id = $orden_id");
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "ID: " . $row['id'] . "\n";
    echo "Monto en tabla principal: " . $row['monto'] . "\n";
    echo "Nombre: " . $row['nombre'] . "\n";
} else {
    echo "No se encontró pedido con ID $orden_id\n";
}

echo "\n--- Productos en pedido_detalle ---\n";
$total_calculado = 0;
$result = $conn->query("SELECT nombre, precio, cantidad FROM pedido_detalle WHERE pedido_id = $orden_id");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $subtotal = $row['precio'] * $row['cantidad'];
        $total_calculado += $subtotal;
        echo "Producto: " . $row['nombre'] . "\n";
        echo "Precio: " . $row['precio'] . "\n";
        echo "Cantidad: " . $row['cantidad'] . "\n";
        echo "Subtotal: " . $subtotal . "\n";
        echo "---\n";
    }
    echo "TOTAL CALCULADO: $total_calculado\n";
} else {
    echo "No se encontraron productos para el pedido $orden_id\n";
}

echo "\n=== FIN TEST ===\n";
?>
