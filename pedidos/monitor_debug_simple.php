<?php
/**
 * MONITOR SIMPLE DE PRUEBA
 * Para verificar que la conexión y datos funcionan
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'conexion.php';

echo "<h1>MONITOR SIMPLE - PRUEBA</h1>";

if (!$conn || $conn->connect_error) {
    die("❌ Error de conexión: " . ($conn->connect_error ?? 'No se pudo conectar'));
}

echo "<p>✅ Conexión exitosa</p>";

// Consulta MUY simple primero
$sql = "SELECT COUNT(*) as total FROM pedidos_detal";
$result = $conn->query($sql);
if ($result) {
    $row = $result->fetch_assoc();
    echo "<p>📊 Total pedidos en sistema: " . $row['total'] . "</p>";
}

// Consulta de pedidos sin enviar
$sql = "SELECT COUNT(*) as total FROM pedidos_detal WHERE enviado = '0' AND anulado = '0'";
$result = $conn->query($sql);
if ($result) {
    $row = $result->fetch_assoc();
    echo "<p>📦 Pedidos sin enviar: " . $row['total'] . "</p>";
}

// Si hay pedidos, mostrar algunos
if ($row['total'] > 0) {
    echo "<h2>Últimos 5 pedidos sin enviar:</h2>";

    $sql = "SELECT id, nombre, fecha, pagado, enviado, tiene_comprobante
            FROM pedidos_detal
            WHERE enviado = '0' AND anulado = '0'
            ORDER BY fecha DESC
            LIMIT 5";

    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Cliente</th><th>Fecha</th><th>Pagado</th><th>Comprobante</th></tr>";

        while ($pedido = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>#" . $pedido['id'] . "</td>";
            echo "<td>" . htmlspecialchars($pedido['nombre']) . "</td>";
            echo "<td>" . $pedido['fecha'] . "</td>";
            echo "<td>" . ($pedido['pagado'] == '1' ? 'SÍ' : 'NO') . "</td>";
            echo "<td>" . ($pedido['tiene_comprobante'] == '1' ? 'SÍ' : 'NO') . "</td>";
            echo "</tr>";
        }

        echo "</table>";
    } else {
        echo "<p>❌ Error obteniendo pedidos: " . $conn->error . "</p>";
    }
} else {
    echo "<p>ℹ️ No hay pedidos sin enviar en este momento</p>";
}

echo "<hr>";
echo "<p><a href='monitor.php'>← Volver al Monitor Principal</a></p>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    background: #f5f5f5;
}

table {
    background: white;
    margin: 10px 0;
}

th, td {
    padding: 8px 12px;
    text-align: left;
}

th {
    background: #4CAF50;
    color: white;
}

a {
    color: #2196F3;
    text-decoration: none;
    font-weight: bold;
}
</style>
