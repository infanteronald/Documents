<?php
// Test rápido de conexión y última consulta
require_once "conexion.php";

echo "Conexión OK\n";

// Último pedido
$result = $conn->query("SELECT * FROM pedidos_detal ORDER BY fecha DESC LIMIT 1");
if ($result) {
    $ultimo = $result->fetch_assoc();
    echo "Último pedido: " . $ultimo['pedido'] . " - " . $ultimo['nombre'] . " - " . $ultimo['fecha'] . "\n";
    echo "Bold Order ID: " . ($ultimo['bold_order_id'] ?? 'NULL') . "\n";
} else {
    echo "Error: " . $conn->error . "\n";
}

// Buscar específicamente
$bold_id = "SEQ-1748345751941-saz5n4gi4";
$stmt = $conn->prepare("SELECT COUNT(*) as encontrados FROM pedidos_detal WHERE bold_order_id = ?");
$stmt->bind_param("s", $bold_id);
$stmt->execute();
$result = $stmt->get_result();
$count = $result->fetch_assoc();
echo "Pedidos con bold_order_id '$bold_id': " . $count['encontrados'] . "\n";

$conn->close();
?>
