<?php

require_once "conexion.php";

echo "<h2>🔧 Agregar Campos Bold a pedidos_detal</h2>";
echo "<style>body{font-family:Arial;padding:20px;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>";

// Campos a agregar para Bold
$campos_bold = [
    "bold_order_id VARCHAR(100) NULL",
    "bold_transaction_id VARCHAR(100) NULL", 
    "estado_pago ENUM('pendiente','pagado','fallido','cancelado') DEFAULT 'pendiente'",
    "bold_response LONGTEXT NULL",
    "fecha_pago TIMESTAMP NULL"
];

foreach ($campos_bold as $campo) {
    $campo_nombre = explode(' ', $campo)[0];
    
    // Verificar si el campo ya existe
    $check = $conn->query("SHOW COLUMNS FROM pedidos_detal LIKE '$campo_nombre'");
    if ($check->num_rows == 0) {
        // El campo no existe, agregarlo
        $sql = "ALTER TABLE pedidos_detal ADD COLUMN $campo";
        if ($conn->query($sql)) {
            echo "<span class='success'>✅ Campo '$campo_nombre' agregado exitosamente</span><br>";
        } else {
            echo "<span class='error'>❌ Error agregando campo '$campo_nombre': " . $conn->error . "</span><br>";
        }
    } else {
        echo "<span class='info'>ℹ️ Campo '$campo_nombre' ya existe</span><br>";
    }
}

// Agregar índice único para bold_order_id si no existe
$check_index = $conn->query("SHOW INDEX FROM pedidos_detal WHERE Key_name = 'bold_order_id'");
if ($check_index->num_rows == 0) {
    $sql = "ALTER TABLE pedidos_detal ADD UNIQUE KEY bold_order_id (bold_order_id)";
    if ($conn->query($sql)) {
        echo "<span class='success'>✅ Índice único para bold_order_id agregado</span><br>";
    } else {
        echo "<span class='error'>❌ Error agregando índice: " . $conn->error . "</span><br>";
    }
} else {
    echo "<span class='info'>ℹ️ Índice único para bold_order_id ya existe</span><br>";
}

echo "<br><h3 class='success'>✅ Proceso completado</h3>";
echo "<p>Los campos Bold han sido agregados a la tabla pedidos_detal. Ahora puedes proceder a actualizar los archivos PHP.</p>";

// Mostrar estructura actualizada
echo "<h4>Estructura actualizada de pedidos_detal:</h4>";
$result = $conn->query("DESCRIBE pedidos_detal");
echo "<table border='1' style='border-collapse:collapse;'><tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Clave</th><th>Default</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td><td>{$row['Default']}</td></tr>";
}
echo "</table>";
?>