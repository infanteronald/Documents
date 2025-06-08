<?php
/**
 * Monitor de Pedidos en Tiempo Real
 * Para verificar que los pedidos se guarden correctamente durante las pruebas
 */

require_once "conexion.php";

echo "<h2>📊 Monitor de Pedidos - Tiempo Real</h2>\n";
echo "<meta http-equiv='refresh' content='5'>\n"; // Auto-refresh cada 5 segundos

// Obtener últimos 10 pedidos
$result = $conn->query("
    SELECT 
        pd.id,
        pd.pedido,
        pd.nombre,
        pd.metodo_pago,
        pd.monto,
        pd.estado,
        pd.fecha,
        pd.bold_order_id,
        pd.bold_transaction_id,
        pd.estado_pago,
        pd.fecha_pago,
        COUNT(pdt.id) as items
    FROM pedidos_detal pd
    LEFT JOIN pedido_detalle pdt ON pd.pedido = pdt.pedido
    GROUP BY pd.id
    ORDER BY pd.fecha DESC
    LIMIT 10
");

echo "<table border='1' style='border-collapse: collapse; width: 100%; font-family: monospace;'>\n";
echo "<tr style='background: #f0f0f0;'>\n";
echo "<th>ID</th><th>Pedido</th><th>Cliente</th><th>Método</th><th>Monto</th><th>Estado</th><th>Items</th><th>Bold ID</th><th>Estado Pago</th><th>Fecha</th>\n";
echo "</tr>\n";

while ($row = $result->fetch_assoc()) {
    $estado_color = '';
    switch($row['estado']) {
        case 'enviado': $estado_color = 'background: #d4edda;'; break;
        case 'sin_enviar': $estado_color = 'background: #fff3cd;'; break;
        case 'archivado': $estado_color = 'background: #f8d7da;'; break;
    }
    
    $pago_color = '';
    switch($row['estado_pago']) {
        case 'pagado': $pago_color = 'background: #d1ecf1; color: #0c5460;'; break;
        case 'pendiente': $pago_color = 'background: #ffeaa7; color: #856404;'; break;
        case 'fallido': $pago_color = 'background: #f5c6cb; color: #721c24;'; break;
    }
    
    echo "<tr style='$estado_color'>\n";
    echo "<td>{$row['id']}</td>\n";
    echo "<td><strong>{$row['pedido']}</strong></td>\n";
    echo "<td>" . substr($row['nombre'], 0, 20) . "</td>\n";
    echo "<td>" . substr($row['metodo_pago'], 0, 10) . "</td>\n";
    echo "<td>$" . number_format($row['monto']) . "</td>\n";
    echo "<td>{$row['estado']}</td>\n";
    echo "<td>{$row['items']}</td>\n";
    echo "<td style='font-size: 10px;'>" . substr($row['bold_order_id'], 0, 15) . "</td>\n";
    echo "<td style='$pago_color'>{$row['estado_pago']}</td>\n";
    echo "<td>" . date('H:i:s', strtotime($row['fecha'])) . "</td>\n";
    echo "</tr>\n";
}

echo "</table>\n";

// Mostrar últimos productos con tallas
echo "<h3>🏷️ Últimos productos con tallas</h3>\n";
$result = $conn->query("
    SELECT 
        pdt.pedido,
        pdt.producto,
        pdt.precio,
        pdt.talla,
        pdt.fecha
    FROM pedido_detalle pdt
    ORDER BY pdt.fecha DESC
    LIMIT 15
");

echo "<table border='1' style='border-collapse: collapse; width: 100%; font-family: monospace;'>\n";
echo "<tr style='background: #f0f0f0;'>\n";
echo "<th>Pedido</th><th>Producto</th><th>Precio</th><th>Talla</th><th>Fecha</th>\n";
echo "</tr>\n";

while ($row = $result->fetch_assoc()) {
    $talla_color = $row['talla'] !== 'N/A' ? 'background: #e8f5e8;' : '';
    
    echo "<tr>\n";
    echo "<td>{$row['pedido']}</td>\n";
    echo "<td>" . substr($row['producto'], 0, 30) . "</td>\n";
    echo "<td>$" . number_format($row['precio']) . "</td>\n";
    echo "<td style='$talla_color'><strong>{$row['talla']}</strong></td>\n";
    echo "<td>" . date('H:i:s', strtotime($row['fecha'])) . "</td>\n";
    echo "</tr>\n";
}

echo "</table>\n";

echo "<br><a href='?' style='background: #28a745; color: white; padding: 8px 12px; text-decoration: none; border-radius: 4px;'>🔄 Actualizar</a>\n";
echo "<a href='orden_pedido.php' style='background: #007cba; color: white; padding: 8px 12px; text-decoration: none; border-radius: 4px; margin-left: 10px;'>🛒 Nuevo Pedido</a>\n";
echo "<a href='listar_pedidos.php' style='background: #6c757d; color: white; padding: 8px 12px; text-decoration: none; border-radius: 4px; margin-left: 10px;'>📋 Ver Todos</a>\n";

echo "<p><em>📡 Página se actualiza automáticamente cada 5 segundos</em></p>\n";

$conn->close();
?>
