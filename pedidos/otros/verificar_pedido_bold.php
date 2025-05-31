<?php
/**
 * Verificar Pedido Bold Específico
 * Verifica el pedido con ID: SEQ-1748345751941-saz5n4gi4
 */

require_once "conexion.php";

$bold_order_id = "SEQ-1748345751941-saz5n4gi4";

echo "<h2>🔍 Verificación del Pedido Bold</h2>\n";
echo "<h3>Order ID: <code>$bold_order_id</code></h3>\n";
echo "<p><strong>Estado reportado por Bold:</strong> ✅ APPROVED</p>\n";

// 1. Buscar en pedidos_detal por bold_order_id
echo "<h3>1. 📊 Buscando en pedidos_detal</h3>\n";
echo "<p>Ejecutando consulta con Order ID: <code>$bold_order_id</code></p>\n";

try {
    $stmt = $conn->prepare("SELECT * FROM pedidos_detal WHERE bold_order_id = ?");
    if (!$stmt) {
        throw new Exception("Error preparando consulta: " . $conn->error);
    }
    
    $stmt->bind_param("s", $bold_order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo "<p>✅ Consulta ejecutada correctamente. Filas encontradas: " . $result->num_rows . "</p>\n";

if ($result->num_rows > 0) {
    $pedido = $result->fetch_assoc();
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; border: 1px solid #c3e6cb;'>\n";
    echo "<h4>✅ Pedido encontrado en pedidos_detal</h4>\n";
    echo "<table border='1' style='border-collapse: collapse;'>\n";
    foreach ($pedido as $campo => $valor) {
        if ($campo === 'bold_response' && strlen($valor) > 100) {
            $valor = substr($valor, 0, 100) . "... (truncado)";
        }
        echo "<tr><td><strong>$campo</strong></td><td>$valor</td></tr>\n";
    }
    echo "</table>\n";
    echo "</div>\n";
    
    $pedido_numero = $pedido['pedido'];
} else {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; border: 1px solid #f5c6cb;'>\n";
    echo "<h4>❌ Pedido NO encontrado en pedidos_detal</h4>\n";
    echo "<p>Esto indica que el webhook de Bold no ha procesado el pago aún, o hay un problema con la integración.</p>\n";
    echo "</div>\n";
    
    // Buscar el último pedido para referencia
    $result_ultimo = $conn->query("SELECT * FROM pedidos_detal ORDER BY fecha DESC LIMIT 1");
    if ($result_ultimo->num_rows > 0) {
        $ultimo = $result_ultimo->fetch_assoc();
        echo "<h4>📋 Último pedido registrado:</h4>\n";
        echo "<p>ID: {$ultimo['id']}, Pedido: {$ultimo['pedido']}, Fecha: {$ultimo['fecha']}</p>\n";
        $pedido_numero = $ultimo['pedido'];
    }
}

// 2. Verificar productos del pedido (si existe el número de pedido)
if (isset($pedido_numero)) {
    echo "<h3>2. 🛍️ Productos del pedido: $pedido_numero</h3>\n";
    $stmt_productos = $conn->prepare("SELECT * FROM pedido_detalle WHERE pedido = ?");
    $stmt_productos->bind_param("s", $pedido_numero);
    $stmt_productos->execute();
    $result_productos = $stmt_productos->get_result();
    
    if ($result_productos->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr style='background: #f0f0f0;'><th>Producto</th><th>Precio</th><th>Talla</th><th>Fecha</th></tr>\n";
        
        while ($producto = $result_productos->fetch_assoc()) {
            $talla_style = $producto['talla'] !== 'N/A' ? 'background: #d1ecf1; font-weight: bold;' : '';
            echo "<tr>\n";
            echo "<td>{$producto['producto']}</td>\n";
            echo "<td>$" . number_format($producto['precio']) . "</td>\n";
            echo "<td style='$talla_style'>{$producto['talla']}</td>\n";
            echo "<td>{$producto['fecha']}</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "<p>❌ No se encontraron productos para este pedido.</p>\n";
    }
}

// 3. Verificar webhook logs (si existen)
echo "<h3>3. 📝 Estado del Webhook</h3>\n";
if (file_exists("webhook_logs.txt")) {
    $logs = file_get_contents("webhook_logs.txt");
    if (strpos($logs, $bold_order_id) !== false) {
        echo "<div style='background: #d1ecf1; padding: 10px; border-radius: 5px;'>\n";
        echo "<p>✅ Webhook procesó este pedido</p>\n";
        // Mostrar las últimas líneas del log que contengan este order_id
        $lineas = explode("\n", $logs);
        $lineas_relevantes = array_filter($lineas, function($linea) use ($bold_order_id) {
            return strpos($linea, $bold_order_id) !== false;
        });
        foreach (array_slice($lineas_relevantes, -5) as $linea) {
            echo "<code>$linea</code><br>\n";
        }
        echo "</div>\n";
    } else {
        echo "<div style='background: #fff3cd; padding: 10px; border-radius: 5px;'>\n";
        echo "<p>⚠️ No se encontró este order_id en los logs del webhook</p>\n";
        echo "</div>\n";
    }
} else {
    echo "<p>ℹ️ No hay archivo de logs del webhook</p>\n";
}

// 4. Simular procesamiento manual si es necesario
echo "<h3>4. 🔧 Acciones disponibles</h3>\n";
if ($result->num_rows === 0) {
    echo "<div style='background: #ffeaa7; padding: 15px; border-radius: 5px;'>\n";
    echo "<h4>🚨 El pedido no se procesó automáticamente</h4>\n";
    echo "<p>Esto puede suceder si:</p>\n";
    echo "<ul>\n";
    echo "<li>El webhook de Bold no se ejecutó correctamente</li>\n";
    echo "<li>Hay un problema de configuración en bold_webhook.php</li>\n";
    echo "<li>El pedido se procesó pero no se guardó el bold_order_id</li>\n";
    echo "</ul>\n";
    echo "<p><strong>Solución:</strong> Podemos procesar el pago manualmente.</p>\n";
    echo "</div>\n";
    
    echo "<a href='procesar_pago_manual.php?order_id=$bold_order_id&status=approved' style='background: #28a745; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>✅ Procesar Pago Manualmente</a><br><br>\n";
}

echo "<a href='monitor_pedidos_prueba.php' style='background: #007cba; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>📊 Ver Monitor</a>\n";
echo "<a href='listar_pedidos.php' style='background: #6c757d; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; margin-left: 10px;'>📋 Ver Todos los Pedidos</a>\n";

$conn->close();
?>
