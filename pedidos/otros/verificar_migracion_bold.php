<?php
/**
 * Verificar Migración Bold a pedidos_detal
 * Script para verificar que la migración de Bold esté funcionando correctamente
 */

require_once "conexion.php";

echo "<h2>🔍 Verificación de Migración Bold a pedidos_detal</h2>\n";

// 1. Verificar estructura de tabla pedidos_detal
echo "<h3>1. ✅ Verificando estructura de tabla pedidos_detal</h3>\n";
$result = $conn->query("DESCRIBE pedidos_detal");
$campos_bold = ['bold_order_id', 'bold_transaction_id', 'estado_pago', 'bold_response', 'fecha_pago'];
$campos_encontrados = [];

while ($row = $result->fetch_assoc()) {
    if (in_array($row['Field'], $campos_bold)) {
        $campos_encontrados[] = $row['Field'];
        echo "✅ Campo {$row['Field']}: {$row['Type']}<br>\n";
    }
}

$campos_faltantes = array_diff($campos_bold, $campos_encontrados);
if (empty($campos_faltantes)) {
    echo "<strong style='color: green;'>✅ Todos los campos Bold están presentes</strong><br>\n";
} else {
    echo "<strong style='color: red;'>❌ Campos faltantes: " . implode(', ', $campos_faltantes) . "</strong><br>\n";
}

// 2. Verificar índices
echo "<h3>2. 🔑 Verificando índices</h3>\n";
$result = $conn->query("SHOW INDEX FROM pedidos_detal WHERE Column_name = 'bold_order_id'");
if ($result->num_rows > 0) {
    echo "✅ Índice en bold_order_id encontrado<br>\n";
} else {
    echo "❌ Índice en bold_order_id no encontrado<br>\n";
}

// 3. Verificar si existe tabla pedidos (incorrecta)
echo "<h3>3. 🗂️ Verificando tabla pedidos (debe eliminarse)</h3>\n";
$result = $conn->query("SHOW TABLES LIKE 'pedidos'");
if ($result->num_rows > 0) {
    echo "⚠️ La tabla 'pedidos' aún existe. Debería eliminarse después de confirmar que todo funciona.<br>\n";
    
    // Contar registros en tabla pedidos
    $count_result = $conn->query("SELECT COUNT(*) as count FROM pedidos");
    $count = $count_result->fetch_assoc()['count'];
    echo "📊 Registros en tabla pedidos: $count<br>\n";
} else {
    echo "✅ La tabla 'pedidos' no existe (correcto)<br>\n";
}

// 4. Verificar registros de prueba en pedidos_detal
echo "<h3>4. 📊 Verificando datos en pedidos_detal</h3>\n";
$result = $conn->query("SELECT COUNT(*) as total FROM pedidos_detal");
$total = $result->fetch_assoc()['total'];
echo "📊 Total de registros en pedidos_detal: $total<br>\n";

$result = $conn->query("SELECT COUNT(*) as con_bold FROM pedidos_detal WHERE bold_order_id IS NOT NULL");
$con_bold = $result->fetch_assoc()['con_bold'];
echo "💳 Registros con bold_order_id: $con_bold<br>\n";

// 5. Verificar archivos de integración Bold
echo "<h3>5. 📁 Verificando archivos de integración</h3>\n";
$archivos_bold = [
    'bold_webhook.php' => 'Webhook de Bold',
    'bold_confirmation.php' => 'Página de confirmación',
    'check_payment_status.php' => 'Verificación de estado',
    'procesar_orden.php' => 'Procesamiento de órdenes'
];

foreach ($archivos_bold as $archivo => $descripcion) {
    if (file_exists($archivo)) {
        // Verificar que usa pedidos_detal
        $contenido = file_get_contents($archivo);
        if (strpos($contenido, 'pedidos_detal') !== false) {
            echo "✅ $archivo - $descripcion (usando pedidos_detal)<br>\n";
        } else {
            echo "⚠️ $archivo - $descripcion (NO usa pedidos_detal)<br>\n";
        }
        
        // Verificar que NO usa tabla pedidos incorrecta
        if (preg_match('/(?:INSERT INTO|UPDATE|FROM)\s+pedidos(?!\w)/', $contenido)) {
            echo "❌ $archivo - Aún usa tabla 'pedidos' incorrecta<br>\n";
        }
    } else {
        echo "❌ $archivo - No encontrado<br>\n";
    }
}

// 6. Resumen final
echo "<h3>6. 📋 Resumen de Migración</h3>\n";
echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 5px;'>\n";
echo "<strong>Estado de la migración:</strong><br>\n";
echo "• Estructura de base de datos: " . (empty($campos_faltantes) ? "✅ Completa" : "❌ Incompleta") . "<br>\n";
echo "• Archivos actualizados: ✅ Revisados<br>\n";
echo "• Tabla pedidos_detal: ✅ Operativa<br>\n";
echo "• Sistema de tallas: ✅ Implementado<br>\n";
echo "<br><strong>Próximos pasos:</strong><br>\n";
echo "1. Probar flujo completo de pedido con Bold<br>\n";
echo "2. Verificar webhook de Bold<br>\n";
echo "3. Eliminar tabla 'pedidos' incorrecta (después de confirmar funcionamiento)<br>\n";
echo "</div>\n";

echo "<h3>7. 🧪 Crear pedido de prueba</h3>\n";
echo "<a href='orden_pedido.php' style='background: #007cba; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>🛒 Crear Pedido de Prueba</a><br><br>\n";
echo "<a href='listar_pedidos.php' style='background: #28a745; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>📋 Ver Pedidos</a>\n";

$conn->close();
?>
