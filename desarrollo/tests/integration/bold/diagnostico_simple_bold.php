<?php
/**
 * Diagnóstico Simple de Base de Datos
 * Para identificar problemas de conexión o consulta
 */

echo "<h2>🔧 Diagnóstico Simple - Bold Order: SEQ-1748345751941-saz5n4gi4</h2>\n";

// Test 1: Conexión básica
echo "<h3>1. ✅ Test de Conexión</h3>\n";
try {
    require_once "conexion.php";
    echo "✅ Conexión establecida correctamente<br>\n";
    echo "ℹ️ Charset: " . $conn->character_set_name() . "<br>\n";
} catch (Exception $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "<br>\n";
    die();
}

// Test 2: Verificar que existe la tabla
echo "<h3>2. 🗂️ Verificar Tabla pedidos_detal</h3>\n";
try {
    $result = $conn->query("SHOW TABLES LIKE 'pedidos_detal'");
    if ($result->num_rows > 0) {
        echo "✅ Tabla pedidos_detal existe<br>\n";
    } else {
        echo "❌ Tabla pedidos_detal NO existe<br>\n";
    }
} catch (Exception $e) {
    echo "❌ Error verificando tabla: " . $e->getMessage() . "<br>\n";
}

// Test 3: Contar registros totales
echo "<h3>3. 📊 Contar Registros</h3>\n";
try {
    $result = $conn->query("SELECT COUNT(*) as total FROM pedidos_detal");
    $row = $result->fetch_assoc();
    echo "📊 Total de registros en pedidos_detal: " . $row['total'] . "<br>\n";
} catch (Exception $e) {
    echo "❌ Error contando registros: " . $e->getMessage() . "<br>\n";
}

// Test 4: Verificar campo bold_order_id
echo "<h3>4. 🔑 Verificar Campo bold_order_id</h3>\n";
try {
    $result = $conn->query("DESCRIBE pedidos_detal");
    $campos = [];
    while ($row = $result->fetch_assoc()) {
        $campos[] = $row['Field'];
    }
    
    if (in_array('bold_order_id', $campos)) {
        echo "✅ Campo bold_order_id existe<br>\n";
        
        // Contar cuántos tienen bold_order_id
        $result2 = $conn->query("SELECT COUNT(*) as con_bold FROM pedidos_detal WHERE bold_order_id IS NOT NULL");
        $row2 = $result2->fetch_assoc();
        echo "📊 Registros con bold_order_id: " . $row2['con_bold'] . "<br>\n";
        
    } else {
        echo "❌ Campo bold_order_id NO existe<br>\n";
        echo "Campos disponibles: " . implode(', ', $campos) . "<br>\n";
    }
} catch (Exception $e) {
    echo "❌ Error verificando campos: " . $e->getMessage() . "<br>\n";
}

// Test 5: Buscar el bold_order_id específico
echo "<h3>5. 🔍 Buscar Order ID Específico</h3>\n";
$bold_order_id = "SEQ-1748345751941-saz5n4gi4";
echo "Buscando: <code>$bold_order_id</code><br>\n";

try {
    $stmt = $conn->prepare("SELECT id, pedido, nombre, monto, bold_order_id, estado_pago FROM pedidos_detal WHERE bold_order_id = ?");
    if (!$stmt) {
        throw new Exception("Error preparando consulta: " . $conn->error);
    }
    
    $stmt->bind_param("s", $bold_order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px;'>\n";
        echo "✅ <strong>ENCONTRADO!</strong> El pedido existe en la base de datos.<br>\n";
        while ($row = $result->fetch_assoc()) {
            echo "ID: {$row['id']}, Pedido: {$row['pedido']}, Cliente: {$row['nombre']}<br>\n";
            echo "Monto: $" . number_format($row['monto']) . ", Estado: {$row['estado_pago']}<br>\n";
        }
        echo "</div>\n";
    } else {
        echo "<div style='background: #fff3cd; padding: 10px; border-radius: 5px;'>\n";
        echo "⚠️ <strong>NO ENCONTRADO</strong> - El pedido no está en la base de datos con este bold_order_id.<br>\n";
        echo "</div>\n";
        
        // Buscar pedidos recientes sin bold_order_id
        echo "<h4>📋 Últimos pedidos sin bold_order_id:</h4>\n";
        $result_recientes = $conn->query("SELECT id, pedido, nombre, monto, metodo_pago, fecha FROM pedidos_detal WHERE bold_order_id IS NULL ORDER BY fecha DESC LIMIT 5");
        
        if ($result_recientes->num_rows > 0) {
            echo "<table border='1' style='border-collapse: collapse;'>\n";
            echo "<tr><th>ID</th><th>Pedido</th><th>Cliente</th><th>Monto</th><th>Método</th><th>Fecha</th></tr>\n";
            while ($reciente = $result_recientes->fetch_assoc()) {
                echo "<tr>\n";
                echo "<td>{$reciente['id']}</td>\n";
                echo "<td>{$reciente['pedido']}</td>\n";
                echo "<td>" . substr($reciente['nombre'], 0, 20) . "</td>\n";
                echo "<td>$" . number_format($reciente['monto']) . "</td>\n";
                echo "<td>{$reciente['metodo_pago']}</td>\n";
                echo "<td>{$reciente['fecha']}</td>\n";
                echo "</tr>\n";
            }
            echo "</table>\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error en búsqueda específica: " . $e->getMessage() . "<br>\n";
}

// Test 6: Verificar webhook
echo "<h3>6. 🌐 Estado del Webhook</h3>\n";
if (file_exists("bold_webhook.php")) {
    echo "✅ Archivo bold_webhook.php existe<br>\n";
    
    // Verificar si hay logs de error
    if (function_exists('error_get_last')) {
        $last_error = error_get_last();
        if ($last_error) {
            echo "⚠️ Último error PHP: " . $last_error['message'] . "<br>\n";
        }
    }
} else {
    echo "❌ Archivo bold_webhook.php NO existe<br>\n";
}

echo "<hr>\n";
echo "<h3>🎯 Próximos Pasos</h3>\n";
echo "<p>Si el pedido no se encontró automáticamente, podemos:</p>\n";
echo "<ol>\n";
echo "<li>Identificar cuál es el último pedido creado</li>\n";
echo "<li>Asignar manualmente el bold_order_id</li>\n";
echo "<li>Actualizar el estado de pago</li>\n";
echo "</ol>\n";

echo "<a href='procesar_pago_manual.php?order_id=$bold_order_id&status=approved' style='background: #28a745; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>🔧 Procesar Manualmente</a><br><br>\n";

$conn->close();
?>
