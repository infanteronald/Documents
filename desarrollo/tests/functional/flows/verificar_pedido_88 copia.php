<?php
require_once "conexion.php";

echo "=== VERIFICACIÓN PEDIDO 88 ===\n\n";

// Verificar en pedidos_detal (tabla principal)
echo "--- Datos en pedidos_detal ---\n";
$result = $conn->query("SELECT * FROM pedidos_detal WHERE id = 88");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . "\n";
        echo "Monto: " . $row['monto'] . "\n";
        echo "Nombre: " . $row['nombre'] . "\n";
        echo "Fecha: " . $row['fecha'] . "\n";
        echo "---\n";
    }
} else {
    echo "No se encontró pedido con ID 88 en pedidos_detal\n";
}

// Verificar en pedido_detalle (productos del pedido)
echo "\n--- Productos en pedido_detalle ---\n";
$result = $conn->query("SELECT * FROM pedido_detalle WHERE pedido_id = 88");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Producto: " . $row['nombre'] . "\n";
        echo "Precio: " . $row['precio'] . "\n";
        echo "Cantidad: " . $row['cantidad'] . "\n";
        echo "Talla: " . $row['talla'] . "\n";
        echo "---\n";
    }
} else {
    echo "No se encontraron productos para el pedido 88 en pedido_detalle\n";
}

echo "\n=== FIN VERIFICACIÓN ===\n";
?>
