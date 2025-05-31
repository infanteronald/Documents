<?php
require_once "conexion.php";

// Probar con el pedido #77 que sabemos que existe
$orden_id = 77;

echo "<h2>Debug Comprobante - Pedido #$orden_id</h2>";

// 1. Verificar si existe el pedido principal
$sql = "SELECT id, pedido, monto, nombre FROM pedidos_detal WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $orden_id);
$stmt->execute();
$stmt->bind_result($id, $pedido, $monto, $nombre);

if ($stmt->fetch()) {
    echo "<h3>✅ Pedido Principal Encontrado:</h3>";
    echo "<p><strong>ID:</strong> $id</p>";
    echo "<p><strong>Nombre:</strong> $nombre</p>";
    echo "<p><strong>Monto:</strong> $monto</p>";
    echo "<p><strong>Pedido (texto):</strong> " . htmlspecialchars($pedido) . "</p>";
} else {
    echo "<h3>❌ Pedido Principal NO encontrado</h3>";
}
$stmt->close();

// 2. Verificar si existen productos detallados
$sql_det = "SELECT nombre, precio, cantidad, talla FROM pedidos_detalle WHERE pedido_id = ?";
$stmt_det = $conn->prepare($sql_det);

echo "<h3>Productos Detallados:</h3>";
if ($stmt_det) {
    $stmt_det->bind_param("i", $orden_id);
    if ($stmt_det->execute()) {
        $stmt_det->bind_result($det_nombre, $det_precio, $det_cantidad, $det_talla);
        $contador = 0;
        while ($stmt_det->fetch()) {
            $contador++;
            echo "<p><strong>Producto $contador:</strong></p>";
            echo "<ul>";
            echo "<li>Nombre: " . htmlspecialchars($det_nombre) . "</li>";
            echo "<li>Precio: $" . number_format($det_precio, 0) . "</li>";
            echo "<li>Cantidad: $det_cantidad</li>";
            echo "<li>Talla: " . htmlspecialchars($det_talla) . "</li>";
            echo "</ul>";
        }
        
        if ($contador == 0) {
            echo "<p>⚠️ No hay productos detallados para este pedido</p>";
            echo "<p>Esto significa que se usó el formulario simple (index.php) en lugar del formulario de productos (orden_pedido.php)</p>";
        } else {
            echo "<p>✅ Se encontraron $contador productos detallados</p>";
        }
    } else {
        echo "<p>❌ Error ejecutando consulta: " . $stmt_det->error . "</p>";
    }
    $stmt_det->close();
} else {
    echo "<p>❌ Error preparando consulta: " . $conn->error . "</p>";
}

// 3. Verificar estructura de la tabla
echo "<h3>Estructura de la tabla pedidos_detalle:</h3>";
$result = $conn->query("DESCRIBE pedidos_detalle");
if ($result) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
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
}

// 4. Mostrar todos los pedidos recientes para verificar IDs
echo "<h3>Últimos 5 pedidos:</h3>";
$result = $conn->query("SELECT id, nombre, monto, fecha FROM pedidos_detal ORDER BY id DESC LIMIT 5");
if ($result) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Nombre</th><th>Monto</th><th>Fecha</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td><a href='comprobante.php?orden=" . $row['id'] . "'>" . $row['id'] . "</a></td>";
        echo "<td>" . htmlspecialchars($row['nombre']) . "</td>";
        echo "<td>$" . number_format($row['monto'], 0) . "</td>";
        echo "<td>" . $row['fecha'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
?>
