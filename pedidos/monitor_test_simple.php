<?php
/**
 * MONITOR SIMPLE - Test de conectividad y datos
 * Solo para verificar que la consulta funciona correctamente
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'conexion.php';

echo "<h1>🧪 MONITOR SIMPLE - TEST</h1>";
echo "<p><strong>Fecha/Hora:</strong> " . date('Y-m-d H:i:s') . "</p>";

// Test de conexión
if (!$conn || $conn->connect_error) {
    die("❌ Error de conexión: " . ($conn->connect_error ?? 'No se pudo conectar'));
}

echo "<p>✅ <strong>Conexión exitosa</strong></p>";

// Consulta simple EXACTAMENTE como en listar_pedidos.php
$where = "enviado = '0' AND anulado = '0'";

$sql = "SELECT
            p.id, p.nombre, p.correo, p.telefono, p.ciudad, p.fecha,
            p.pagado, p.enviado, p.tiene_comprobante, p.tiene_guia,
            p.archivado, p.anulado,
            COALESCE(SUM(pd.cantidad * pd.precio), 0) as monto
        FROM pedidos_detal p
        LEFT JOIN pedido_detalle pd ON p.id = pd.pedido_id
        WHERE $where
        GROUP BY p.id
        ORDER BY p.fecha DESC
        LIMIT 10";

echo "<h2>📋 Consulta SQL:</h2>";
echo "<pre style='background: #f0f0f0; padding: 10px; border-radius: 5px;'>$sql</pre>";

$result = $conn->query($sql);

if (!$result) {
    echo "<p>❌ <strong>Error en la consulta:</strong> " . $conn->error . "</p>";
    exit;
}

echo "<p>✅ <strong>Consulta ejecutada exitosamente</strong></p>";
echo "<p>📊 <strong>Filas encontradas:</strong> " . $result->num_rows . "</p>";

if ($result->num_rows > 0) {
    echo "<h2>📦 Pedidos Sin Enviar:</h2>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%; font-size: 14px;'>";
    echo "<tr style='background: #4299e1; color: white;'>";
    echo "<th>ID</th><th>Cliente</th><th>Email</th><th>Teléfono</th><th>Ciudad</th><th>Fecha</th><th>Monto</th><th>Pagado</th><th>Comprobante</th>";
    echo "</tr>";

    $total_monto = 0;
    $total_pagados = 0;
    $total_pendientes = 0;

    while ($row = $result->fetch_assoc()) {
        $total_monto += $row['monto'];
        if ($row['pagado'] == '1') {
            $total_pagados++;
        } else {
            $total_pendientes++;
        }

        $pagado_text = ($row['pagado'] == '1') ? '✅ Sí' : '⏳ No';
        $comprobante_text = ($row['tiene_comprobante'] == '1') ? '📄 Sí' : '❌ No';
        $monto_formateado = '$' . number_format($row['monto'], 0, ',', '.');
        $fecha_formateada = date('d/m/Y H:i', strtotime($row['fecha']));

        echo "<tr>";
        echo "<td><strong>#{$row['id']}</strong></td>";
        echo "<td>" . htmlspecialchars($row['nombre']) . "</td>";
        echo "<td>" . htmlspecialchars($row['correo']) . "</td>";
        echo "<td>" . htmlspecialchars($row['telefono']) . "</td>";
        echo "<td>" . htmlspecialchars($row['ciudad']) . "</td>";
        echo "<td>$fecha_formateada</td>";
        echo "<td><strong>$monto_formateado</strong></td>";
        echo "<td>$pagado_text</td>";
        echo "<td>$comprobante_text</td>";
        echo "</tr>";
    }

    echo "</table>";

    // Estadísticas
    echo "<h2>📊 Resumen:</h2>";
    echo "<div style='background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
    echo "<p><strong>📦 Total pedidos sin enviar:</strong> " . $result->num_rows . "</p>";
    echo "<p><strong>💰 Monto total:</strong> $" . number_format($total_monto, 0, ',', '.') . "</p>";
    echo "<p><strong>✅ Pagados:</strong> $total_pagados</p>";
    echo "<p><strong>⏳ Pendientes:</strong> $total_pendientes</p>";
    echo "</div>";

} else {
    echo "<p>⚠️ <strong>No hay pedidos sin enviar en este momento</strong></p>";
}

// Test adicional: verificar si hay datos en las tablas
echo "<hr>";
echo "<h2>🔍 Verificación Adicional de Datos:</h2>";

$sql_total_pedidos = "SELECT COUNT(*) as total FROM pedidos_detal";
$result_total = $conn->query($sql_total_pedidos);
if ($result_total) {
    $row_total = $result_total->fetch_assoc();
    echo "<p>📋 <strong>Total pedidos en sistema:</strong> " . $row_total['total'] . "</p>";
}

$sql_sin_enviar = "SELECT COUNT(*) as total FROM pedidos_detal WHERE enviado = '0'";
$result_sin_enviar = $conn->query($sql_sin_enviar);
if ($result_sin_enviar) {
    $row_sin_enviar = $result_sin_enviar->fetch_assoc();
    echo "<p>📦 <strong>Total sin enviar (incluyendo anulados):</strong> " . $row_sin_enviar['total'] . "</p>";
}

$sql_activos = "SELECT COUNT(*) as total FROM pedidos_detal WHERE enviado = '0' AND anulado = '0'";
$result_activos = $conn->query($sql_activos);
if ($result_activos) {
    $row_activos = $result_activos->fetch_assoc();
    echo "<p>✅ <strong>Pedidos activos sin enviar:</strong> " . $row_activos['total'] . "</p>";
}

echo "<hr>";
echo "<p><strong>🔄 Auto-refresh:</strong> <a href='?refresh=1'>Actualizar datos</a></p>";
echo "<p><strong>📱 Ir al monitor:</strong> <a href='monitor_final.php'>Ver Monitor Final</a></p>";

?>

<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    background: #f5f5f5;
    color: #333;
}

h1 {
    color: #2563eb;
    border-bottom: 2px solid #2563eb;
    padding-bottom: 10px;
}

h2 {
    color: #1f2937;
    margin-top: 30px;
    background: #e5e7eb;
    padding: 10px;
    border-radius: 5px;
}

table {
    margin: 15px 0;
    font-size: 14px;
}

th {
    padding: 8px 12px;
    font-weight: bold;
}

td {
    padding: 6px 12px;
}

a {
    color: #2563eb;
    text-decoration: none;
    font-weight: bold;
}

a:hover {
    text-decoration: underline;
}

pre {
    overflow-x: auto;
    font-size: 12px;
}
</style>
