<?php
/**
 * Procesador Manual de Pago Bold
 * Para procesar manualmente pagos cuando el webhook no funciona
 */

require_once "conexion.php";

if (!isset($_GET['order_id']) || !isset($_GET['status'])) {
    die("❌ Faltan parámetros: order_id y status son requeridos");
}

$order_id = $_GET['order_id'];
$status = $_GET['status'];

echo "<h2>🔧 Procesamiento Manual de Pago Bold</h2>\n";
echo "<h3>Order ID: <code>$order_id</code></h3>\n";
echo "<h3>Status: <code>$status</code></h3>\n";

// 1. Buscar si ya existe el pedido
$stmt = $conn->prepare("SELECT id, pedido, estado_pago, bold_order_id, nombre, correo, monto, descuento FROM pedidos_detal WHERE bold_order_id = ?");
$stmt->bind_param("s", $order_id);
$stmt->execute();

// Usar bind_result para compatibilidad
$stmt->bind_result($pedido_id, $pedido_detalle, $estado_pago, $bold_order_id, $nombre, $correo, $monto, $descuento);

if ($stmt->fetch()) {
    $stmt->close();
    echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px;'>\n";
    echo "<h4>ℹ️ El pedido ya existe en la base de datos</h4>\n";
    echo "<p>Pedido: {$pedido_detalle}, Estado actual: {$estado_pago}</p>\n";
    echo "</div>\n";

    // Crear array para mantener compatibilidad
    $pedido = [
        'id' => $pedido_id,
        'pedido' => $pedido_detalle,
        'estado_pago' => $estado_pago,
        'bold_order_id' => $bold_order_id,
        'nombre' => $nombre,
        'correo' => $correo
    ];
} else {
    $stmt->close();
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>\n";
    echo "<h4>⚠️ El pedido no existe en la base de datos</h4>\n";
    echo "<p>Esto sugiere que el proceso de checkout no se completó correctamente antes del pago.</p>\n";
    echo "</div>\n";

    // Buscar el último pedido sin bold_order_id
    $result_pendiente = $conn->query("
        SELECT * FROM pedidos_detal
        WHERE bold_order_id IS NULL
        AND metodo_pago LIKE '%Bold%'
        ORDER BY fecha DESC
        LIMIT 3
    ");

    if ($result_pendiente->num_rows > 0) {
        echo "<h4>🔍 Pedidos pendientes que podrían corresponder a este pago:</h4>\n";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Pedido</th><th>Cliente</th><th>Monto</th><th>Fecha</th><th>Acción</th></tr>\n";

        while ($pendiente = $result_pendiente->fetch_assoc()) {
            echo "<tr>\n";
            echo "<td>{$pendiente['id']}</td>\n";
            echo "<td>{$pendiente['pedido']}</td>\n";
            echo "<td>{$pendiente['nombre']}</td>\n";
            echo "<td>$" . number_format($pendiente['monto']) . "</td>\n";
            echo "<td>{$pendiente['fecha']}</td>\n";
            echo "<td><a href='?order_id=$order_id&status=$status&assign_to={$pendiente['id']}' style='background: #28a745; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;'>Asignar</a></td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
}

// 2. Procesar asignación si se especifica
if (isset($_GET['assign_to'])) {
    $pedido_id = $_GET['assign_to'];

    echo "<h3>🔄 Asignando pago a pedido existente</h3>\n";

    $estado_pago = ($status === 'approved') ? 'pagado' : 'pendiente';

    $stmt_update = $conn->prepare("
        UPDATE pedidos_detal
        SET bold_order_id = ?,
            estado_pago = ?,
            fecha_pago = NOW(),
            bold_response = ?
        WHERE id = ?
    ");

    $response_data = json_encode([
        'status' => $status,
        'order_id' => $order_id,
        'processed_manually' => true,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

    $stmt_update->bind_param("sssi", $order_id, $estado_pago, $response_data, $pedido_id);

    if ($stmt_update->execute()) {
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>\n";
        echo "<h4>✅ Pago asignado exitosamente</h4>\n";
        echo "<p>El pedido ID $pedido_id ahora tiene asignado el Bold Order ID: $order_id</p>\n";
        echo "<p>Estado de pago actualizado a: $estado_pago</p>\n";
        echo "</div>\n";

        // Mostrar detalles del pedido actualizado
        $stmt_verify = $conn->prepare("SELECT pedido, nombre, monto, descuento, bold_order_id, estado_pago, fecha_pago FROM pedidos_detal WHERE id = ?");
        $stmt_verify->bind_param("i", $pedido_id);
        $stmt_verify->execute();

        // Usar bind_result para compatibilidad
        $stmt_verify->bind_result($pedido_detalle, $nombre, $monto, $descuento_verify, $bold_order_id, $estado_pago_actual, $fecha_pago);

        if ($stmt_verify->fetch()) {
            $stmt_verify->close();

            echo "<h4>📋 Detalles del pedido actualizado:</h4>\n";
            echo "<table border='1' style='border-collapse: collapse;'>\n";
            echo "<tr><td><strong>Pedido</strong></td><td>{$pedido_detalle}</td></tr>\n";
            echo "<tr><td><strong>Cliente</strong></td><td>{$nombre}</td></tr>\n";
            if ($descuento_verify > 0) {
                echo "<tr><td><strong>Subtotal</strong></td><td>$" . number_format($monto + $descuento_verify) . "</td></tr>\n";
                echo "<tr><td><strong>Descuento</strong></td><td style='color: #28a745;'>-$" . number_format($descuento_verify) . "</td></tr>\n";
                echo "<tr><td><strong>Total Final</strong></td><td><strong>$" . number_format($monto) . "</strong></td></tr>\n";
            } else {
                echo "<tr><td><strong>Monto</strong></td><td>$" . number_format($monto) . "</td></tr>\n";
            }
            echo "<tr><td><strong>Bold Order ID</strong></td><td>{$bold_order_id}</td></tr>\n";
            echo "<tr><td><strong>Estado Pago</strong></td><td>{$estado_pago_actual}</td></tr>\n";
            echo "<tr><td><strong>Fecha Pago</strong></td><td>{$fecha_pago}</td></tr>\n";
            echo "</table>\n";
        }

    } else {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>\n";
        echo "<h4>❌ Error al asignar el pago</h4>\n";
        echo "<p>Error: " . $stmt_update->error . "</p>\n";
        echo "</div>\n";
    }
}

echo "<hr>\n";
echo "<h3>🔗 Enlaces útiles</h3>\n";
echo "<a href='verificar_pedido_bold.php' style='background: #007cba; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>🔍 Verificar Pedido</a>\n";
echo "<a href='monitor_pedidos_prueba.php' style='background: #28a745; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; margin-left: 10px;'>📊 Monitor</a>\n";
echo "<a href='listar_pedidos.php' style='background: #6c757d; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; margin-left: 10px;'>📋 Todos los Pedidos</a>\n";

$conn->close();
?>
