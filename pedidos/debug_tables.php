<?php
require_once "conexion.php";

echo "<h2>Verificando estructura de tablas</h2>";

// Mostrar todas las tablas relacionadas con pedidos
$result = $conn->query("SHOW TABLES LIKE 'pedido%'");
echo "<h3>Tablas que contienen 'pedido':</h3>";
while ($row = $result->fetch_array()) {
    echo "- " . $row[0] . "<br>";
}

// Verificar estructura de pedidos_detal
echo "<h3>Estructura de pedidos_detal:</h3>";
$result = $conn->query("DESCRIBE pedidos_detal");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "<br>";
    }
} else {
    echo "Tabla pedidos_detal no existe<br>";
}

// Verificar estructura de pedido_detalle
echo "<h3>Estructura de pedido_detalle:</h3>";
$result = $conn->query("DESCRIBE pedido_detalle");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "<br>";
    }
} else {
    echo "Tabla pedido_detalle no existe<br>";
}

// Verificar datos del pedido 88
echo "<h3>Datos del pedido ID 88:</h3>";

// Buscar en pedidos_detal
$result = $conn->query("SELECT * FROM pedidos_detal WHERE id = 88");
if ($result && $result->num_rows > 0) {
    echo "<strong>Encontrado en pedidos_detal:</strong><br>";
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . ", Monto: " . ($row['monto'] ?? 'N/A') . "<br>";
    }
} else {
    echo "No encontrado en pedidos_detal<br>";
}

// Buscar detalles en pedido_detalle
$result = $conn->query("SELECT * FROM pedido_detalle WHERE pedido_id = 88");
if ($result && $result->num_rows > 0) {
    echo "<strong>Detalles en pedido_detalle:</strong><br>";
    while ($row = $result->fetch_assoc()) {
        echo "Producto: " . $row['nombre'] . ", Precio: " . $row['precio'] . ", Cantidad: " . $row['cantidad'] . "<br>";
    }
} else {
    echo "No encontrados detalles en pedido_detalle<br>";
}

// Buscar detalles en pedidos_detalle (por si acaso)
$result = $conn->query("SELECT * FROM pedidos_detalle WHERE pedido_id = 88");
if ($result && $result->num_rows > 0) {
    echo "<strong>Detalles en pedidos_detalle:</strong><br>";
    while ($row = $result->fetch_assoc()) {
        echo "Producto: " . $row['nombre'] . ", Precio: " . $row['precio'] . ", Cantidad: " . $row['cantidad'] . "<br>";
    }
} else {
    echo "No encontrados detalles en pedidos_detalle<br>";
}

$conn->close();
?>
