<?php
require_once "conexion.php";

$orden_id = 77; // El pedido que acabamos de crear

echo "<h2>Debug del Comprobante - Pedido #$orden_id</h2>";

// 1. Verificar datos principales del pedido
echo "<h3>1. Datos principales (pedidos_detal)</h3>";
$sql = "SELECT id, pedido, monto, nombre FROM pedidos_detal WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $orden_id);
$stmt->execute();
$stmt->bind_result($id, $pedido, $monto, $nombre);

if ($stmt->fetch()) {
    echo "<p><strong>ID:</strong> $id</p>";
    echo "<p><strong>Nombre:</strong> $nombre</p>";
    echo "<p><strong>Monto:</strong> $monto</p>";
    echo "<p><strong>Pedido texto:</strong> " . htmlspecialchars($pedido) . "</p>";
} else {
    echo "<p style='color: red;'>❌ No se encontró el pedido principal</p>";
}
$stmt->close();

// 2. Verificar si hay productos detallados
echo "<h3>2. Productos detallados (pedidos_detalle)</h3>";
$sql_det = "SELECT nombre, precio, cantidad, talla FROM pedidos_detalle WHERE pedido_id = ?";
$stmt_det = $conn->prepare($sql_det);

if ($stmt_det) {
    $stmt_det->bind_param("i", $orden_id);
    if ($stmt_det->execute()) {
        $stmt_det->bind_result($det_nombre, $det_precio, $det_cantidad, $det_talla);
        $productos_encontrados = 0;
        
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Nombre</th><th>Precio</th><th>Cantidad</th><th>Talla</th></tr>";
        
        while ($stmt_det->fetch()) {
            $productos_encontrados++;
            echo "<tr>";
            echo "<td>" . htmlspecialchars($det_nombre) . "</td>";
            echo "<td>$" . number_format($det_precio, 0) . "</td>";
            echo "<td>$det_cantidad</td>";
            echo "<td>" . htmlspecialchars($det_talla) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        if ($productos_encontrados == 0) {
            echo "<p style='color: orange;'>⚠️ No se encontraron productos detallados para este pedido</p>";
            echo "<p>Esto es normal para pedidos creados desde el formulario simple (index.php)</p>";
        } else {
            echo "<p style='color: green;'>✅ Se encontraron $productos_encontrados productos</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Error en consulta: " . $stmt_det->error . "</p>";
    }
    $stmt_det->close();
} else {
    echo "<p style='color: red;'>❌ Error preparando consulta: " . $conn->error . "</p>";
}

// 3. Verificar estructura de la tabla pedidos_detalle
echo "<h3>3. Estructura de tabla pedidos_detalle</h3>";
$describe = $conn->query("DESCRIBE pedidos_detalle");
if ($describe) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $describe->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ Error obteniendo estructura: " . $conn->error . "</p>";
}

// 4. Contar registros en pedidos_detalle
echo "<h3>4. Total de registros en pedidos_detalle</h3>";
$count_result = $conn->query("SELECT COUNT(*) as total FROM pedidos_detalle");
if ($count_result) {
    $count = $count_result->fetch_assoc()['total'];
    echo "<p>Total de registros en pedidos_detalle: <strong>$count</strong></p>";
} else {
    echo "<p style='color: red;'>❌ Error contando registros: " . $conn->error . "</p>";
}

?>
