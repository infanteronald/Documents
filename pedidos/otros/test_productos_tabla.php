<?php
require_once "conexion.php";

echo "<h3>Verificando estructura de la tabla productos</h3>";

// Verificar si la tabla productos existe
$result = $conn->query("SHOW TABLES LIKE 'productos'");
if ($result->num_rows > 0) {
    echo "<p>✓ La tabla 'productos' existe</p>";
    
    // Mostrar estructura de la tabla
    echo "<h4>Estructura de la tabla productos:</h4>";
    $result = $conn->query("DESCRIBE productos");
    echo "<table border='1'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Clave</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Mostrar algunos datos de ejemplo
    echo "<h4>Datos de ejemplo:</h4>";
    $result = $conn->query("SELECT * FROM productos LIMIT 5");
    if ($result->num_rows > 0) {
        echo "<table border='1'>";
        $first = true;
        while ($row = $result->fetch_assoc()) {
            if ($first) {
                echo "<tr>";
                foreach (array_keys($row) as $key) {
                    echo "<th>$key</th>";
                }
                echo "</tr>";
                $first = false;
            }
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No hay datos en la tabla productos</p>";
    }
} else {
    echo "<p>❌ La tabla 'productos' NO existe</p>";
    
    // Crear la tabla si no existe
    echo "<h4>Creando tabla productos...</h4>";
    $sql = "CREATE TABLE productos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(255) NOT NULL,
        precio DECIMAL(10,2) NOT NULL,
        categoria VARCHAR(100) DEFAULT NULL,
        activo TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql) === TRUE) {
        echo "<p>✓ Tabla 'productos' creada exitosamente</p>";
    } else {
        echo "<p>❌ Error al crear tabla: " . $conn->error . "</p>";
    }
}

echo "<hr>";
echo "<h3>Verificando estructura de la tabla pedidos_detalle</h3>";

// Verificar si la tabla pedidos_detalle existe
$result = $conn->query("SHOW TABLES LIKE 'pedidos_detalle'");
if ($result->num_rows > 0) {
    echo "<p>✓ La tabla 'pedidos_detalle' existe</p>";
    
    // Mostrar estructura de la tabla
    echo "<h4>Estructura de la tabla pedidos_detalle:</h4>";
    $result = $conn->query("DESCRIBE pedidos_detalle");
    echo "<table border='1'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Clave</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ La tabla 'pedidos_detalle' NO existe</p>";
    
    // Crear la tabla si no existe
    echo "<h4>Creando tabla pedidos_detalle...</h4>";
    $sql = "CREATE TABLE pedidos_detalle (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pedido_id INT NOT NULL,
        producto_id INT NOT NULL,
        nombre VARCHAR(255) NOT NULL,
        precio DECIMAL(10,2) NOT NULL,
        cantidad INT NOT NULL,
        talla VARCHAR(10) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (pedido_id) REFERENCES pedidos_detal(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($sql) === TRUE) {
        echo "<p>✓ Tabla 'pedidos_detalle' creada exitosamente</p>";
    } else {
        echo "<p>❌ Error al crear tabla: " . $conn->error . "</p>";
    }
}

$conn->close();
?>
