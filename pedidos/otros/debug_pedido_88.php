<?php
require_once "conexion.php";

echo "<h2>Debug específico para pedido ID 88</h2>";

$pedido_id = 88;
echo "Buscando pedido ID: $pedido_id<br><br>";

// Verificar el pedido principal
$res = $conn->query("SELECT * FROM pedidos_detal WHERE id = $pedido_id");
if ($res && $res->num_rows > 0) {
    $pedido = $res->fetch_assoc();
    echo "<strong>Pedido principal encontrado:</strong><br>";
    echo "ID: " . $pedido['id'] . "<br>";
    echo "Monto: " . ($pedido['monto'] ?? 'N/A') . "<br>";
    echo "Fecha: " . ($pedido['fecha'] ?? 'N/A') . "<br>";
    echo "<br>";
} else {
    echo "❌ No se encontró el pedido principal en pedidos_detal<br><br>";
}

// Verificar los detalles del pedido
echo "<strong>Buscando detalles del pedido...</strong><br>";

// Intentar con pedido_detalle
$res = $conn->query("SELECT * FROM pedido_detalle WHERE pedido_id = $pedido_id");
if ($res && $res->num_rows > 0) {
    echo "✅ Detalles encontrados en pedido_detalle:<br>";
    while ($row = $res->fetch_assoc()) {
        echo "- Producto: " . $row['nombre'] . ", Precio: $" . number_format($row['precio'], 0, ',', '.') . ", Cantidad: " . $row['cantidad'] . ", Talla: " . ($row['talla'] ?? 'N/A') . "<br>";
    }
} else {
    echo "❌ No se encontraron detalles en pedido_detalle<br>";
}

// Intentar con pedidos_detalle (por si acaso)
$res = $conn->query("SELECT * FROM pedidos_detalle WHERE pedido_id = $pedido_id");
if ($res && $res->num_rows > 0) {
    echo "✅ Detalles encontrados en pedidos_detalle:<br>";
    while ($row = $res->fetch_assoc()) {
        echo "- Producto: " . $row['nombre'] . ", Precio: $" . number_format($row['precio'], 0, ',', '.') . ", Cantidad: " . $row['cantidad'] . ", Talla: " . ($row['talla'] ?? 'N/A') . "<br>";
    }
} else {
    echo "❌ No se encontraron detalles en pedidos_detalle<br>";
}

echo "<br><strong>Simulando la consulta de index.php:</strong><br>";
$detalles = [];
$monto = 0;

if ($pedido_id) {
    $res = $conn->query("SELECT * FROM pedidos_detalle WHERE pedido_id = $pedido_id");
    echo "Consulta ejecutada: SELECT * FROM pedidos_detalle WHERE pedido_id = $pedido_id<br>";
    echo "Número de filas encontradas: " . ($res ? $res->num_rows : 0) . "<br>";
    
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $detalles[] = $row;
            $monto += $row['precio'] * $row['cantidad'];
        }
    }
}

echo "Detalles cargados: " . count($detalles) . "<br>";
echo "Monto calculado: $" . number_format($monto, 0, ',', '.') . "<br>";

$conn->close();
?>
