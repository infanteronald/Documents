<?php
require_once "conexion.php";

echo "<h2>Verificación de Base de Datos</h2>";

// Verificar estructura de tablas
echo "<h3>Estructura de tabla pedidos_detal:</h3>";
$result = $conn->query("DESCRIBE pedidos_detal");
if ($result) {
    echo "<table border='1'><tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Clave</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "Error: " . $conn->error;
}

echo "<h3>Estructura de tabla pedido_detalle:</h3>";
$result = $conn->query("DESCRIBE pedido_detalle");
if ($result) {
    echo "<table border='1'><tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Clave</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "Error: " . $conn->error;
}

echo "<h3>Estructura de tabla productos:</h3>";
$result = $conn->query("DESCRIBE productos");
if ($result) {
    echo "<table border='1'><tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Clave</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "Error: " . $conn->error;
}

// Verificar si existe la tabla pedido_detalle
echo "<h3>¿Existe la tabla pedido_detalle?</h3>";
$result = $conn->query("SHOW TABLES LIKE 'pedido_detalle'");
if ($result->num_rows > 0) {
    echo "✅ La tabla pedido_detalle existe";
} else {
    echo "❌ La tabla pedido_detalle NO existe";
    
    // Crear la tabla si no existe
    echo "<br><strong>Creando tabla pedido_detalle...</strong><br>";
    $createTable = "CREATE TABLE pedido_detalle (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pedido_id INT NOT NULL,
        producto_id INT DEFAULT 0,
        nombre VARCHAR(255) NOT NULL,
        precio DECIMAL(10,2) NOT NULL,
        cantidad INT NOT NULL,
        fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(pedido_id)
    )";
    
    if ($conn->query($createTable)) {
        echo "✅ Tabla pedido_detalle creada exitosamente";
    } else {
        echo "❌ Error creando tabla: " . $conn->error;
    }
}

$conn->close();
?>
