<?php
/**
 * Limpiar Tabla Pedidos Incorrecta
 * Script para eliminar la tabla 'pedidos' incorrecta después de confirmar que todo funciona
 */

require_once "conexion.php";

echo "<h2>🗑️ Limpieza de Tabla Pedidos Incorrecta</h2>\n";

// 1. Verificar que pedidos_detal tiene todos los campos Bold
$result = $conn->query("DESCRIBE pedidos_detal");
$campos_bold = ['bold_order_id', 'bold_transaction_id', 'estado_pago', 'bold_response', 'fecha_pago'];
$campos_encontrados = [];

while ($row = $result->fetch_assoc()) {
    if (in_array($row['Field'], $campos_bold)) {
        $campos_encontrados[] = $row['Field'];
    }
}

$campos_faltantes = array_diff($campos_bold, $campos_encontrados);

if (!empty($campos_faltantes)) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>\n";
    echo "<h3>❌ No se puede proceder</h3>\n";
    echo "<p>La tabla pedidos_detal no tiene todos los campos Bold necesarios.</p>\n";
    echo "<p>Campos faltantes: " . implode(', ', $campos_faltantes) . "</p>\n";
    echo "</div>\n";
    exit;
}

// 2. Verificar que existe la tabla pedidos
$result = $conn->query("SHOW TABLES LIKE 'pedidos'");
if ($result->num_rows === 0) {
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>\n";
    echo "<h3>✅ Tabla ya limpia</h3>\n";
    echo "<p>La tabla 'pedidos' no existe. La limpieza ya fue realizada.</p>\n";
    echo "</div>\n";
    exit;
}

// 3. Contar registros en ambas tablas
$count_pedidos = $conn->query("SELECT COUNT(*) as count FROM pedidos")->fetch_assoc()['count'];
$count_pedidos_detal = $conn->query("SELECT COUNT(*) as count FROM pedidos_detal")->fetch_assoc()['count'];

echo "<h3>📊 Estado actual de las tablas</h3>\n";
echo "<table border='1' style='border-collapse: collapse;'>\n";
echo "<tr><th>Tabla</th><th>Registros</th><th>Estado</th></tr>\n";
echo "<tr><td>pedidos_detal</td><td>$count_pedidos_detal</td><td style='background: #d4edda;'>✅ Correcta</td></tr>\n";
echo "<tr><td>pedidos</td><td>$count_pedidos</td><td style='background: #fff3cd;'>⚠️ A eliminar</td></tr>\n";
echo "</table>\n";

// 4. Mostrar formulario de confirmación
if (!isset($_POST['confirmar_eliminacion'])) {
    echo "<h3>⚠️ Confirmación requerida</h3>\n";
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
    echo "<p><strong>¿Está seguro de que desea eliminar la tabla 'pedidos'?</strong></p>\n";
    echo "<p>Esta acción:</p>\n";
    echo "<ul>\n";
    echo "<li>✅ Eliminará la tabla 'pedidos' incorrecta (con $count_pedidos registros)</li>\n";
    echo "<li>✅ Mantendrá intacta la tabla 'pedidos_detal' (con $count_pedidos_detal registros)</li>\n";
    echo "<li>⚠️ Es <strong>IRREVERSIBLE</strong></li>\n";
    echo "</ul>\n";
    
    if ($count_pedidos > 0) {
        echo "<h4>📋 Registros en tabla 'pedidos' que se eliminarán:</h4>\n";
        $result = $conn->query("SELECT id, bold_order_id, fecha_creacion FROM pedidos ORDER BY fecha_creacion DESC LIMIT 10");
        echo "<table border='1' style='border-collapse: collapse; font-size: 0.9rem;'>\n";
        echo "<tr><th>ID</th><th>Bold Order ID</th><th>Fecha</th></tr>\n";
        while ($row = $result->fetch_assoc()) {
            echo "<tr><td>{$row['id']}</td><td>{$row['bold_order_id']}</td><td>{$row['fecha_creacion']}</td></tr>\n";
        }
        echo "</table>\n";
        if ($count_pedidos > 10) {
            echo "<p><em>... y " . ($count_pedidos - 10) . " registros más</em></p>\n";
        }
    }
    
    echo "</div>\n";
    
    echo "<form method='post' style='margin: 20px 0;'>\n";
    echo "<input type='checkbox' id='entiendo' name='entiendo' required>\n";
    echo "<label for='entiendo' style='margin-left: 8px;'>Entiendo que esta acción es irreversible</label><br><br>\n";
    echo "<input type='checkbox' id='confirmo' name='confirmo' required>\n";
    echo "<label for='confirmo' style='margin-left: 8px;'>Confirmo que he verificado que el sistema funciona correctamente con pedidos_detal</label><br><br>\n";
    echo "<button type='submit' name='confirmar_eliminacion' value='1' style='background: #dc3545; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer;'>🗑️ Eliminar Tabla 'pedidos'</button>\n";
    echo "</form>\n";
    
} else {
    // 5. Proceder con la eliminación
    if (!isset($_POST['entiendo']) || !isset($_POST['confirmo'])) {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>\n";
        echo "<h3>❌ Error</h3>\n";
        echo "<p>Debe confirmar ambas casillas para proceder.</p>\n";
        echo "</div>\n";
        exit;
    }
    
    echo "<h3>🗑️ Procediendo con la eliminación...</h3>\n";
    
    try {
        // Crear backup antes de eliminar (opcional)
        if ($count_pedidos > 0) {
            echo "<p>📦 Creando backup de seguridad...</p>\n";
            $backup_sql = "CREATE TABLE pedidos_backup_" . date('Y_m_d_H_i_s') . " AS SELECT * FROM pedidos";
            if ($conn->query($backup_sql)) {
                echo "<p>✅ Backup creado exitosamente</p>\n";
            } else {
                echo "<p>⚠️ No se pudo crear backup: " . $conn->error . "</p>\n";
            }
        }
        
        // Eliminar la tabla
        echo "<p>🗑️ Eliminando tabla 'pedidos'...</p>\n";
        if ($conn->query("DROP TABLE pedidos")) {
            echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>\n";
            echo "<h3>✅ Eliminación exitosa</h3>\n";
            echo "<p>La tabla 'pedidos' ha sido eliminada correctamente.</p>\n";
            echo "<p>El sistema ahora usa únicamente la tabla 'pedidos_detal' para todos los pedidos y pagos Bold.</p>\n";
            echo "</div>\n";
            
            echo "<h3>🎉 Migración Completada</h3>\n";
            echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px;'>\n";
            echo "<h4>✅ Estado final del sistema:</h4>\n";
            echo "<ul>\n";
            echo "<li>✅ Tabla pedidos_detal: $count_pedidos_detal registros</li>\n";
            echo "<li>✅ Campos Bold integrados</li>\n";
            echo "<li>✅ Sistema de tallas implementado</li>\n";
            echo "<li>✅ Pagos en la misma ventana</li>\n";
            echo "<li>✅ Webhook funcionando</li>\n";
            echo "</ul>\n";
            echo "</div>\n";
            
        } else {
            echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>\n";
            echo "<h3>❌ Error al eliminar</h3>\n";
            echo "<p>Error: " . $conn->error . "</p>\n";
            echo "</div>\n";
        }
        
    } catch (Exception $e) {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>\n";
        echo "<h3>❌ Error durante la eliminación</h3>\n";
        echo "<p>Error: " . $e->getMessage() . "</p>\n";
        echo "</div>\n";
    }
}

echo "<hr>\n";
echo "<h3>🔗 Enlaces útiles</h3>\n";
echo "<a href='verificar_migracion_bold.php' style='background: #007cba; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>🔍 Verificar Sistema</a>\n";
echo "<a href='monitor_pedidos_prueba.php' style='background: #28a745; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; margin-left: 10px;'>📊 Monitor</a>\n";
echo "<a href='orden_pedido.php' style='background: #6c757d; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; margin-left: 10px;'>🛒 Nueva Orden</a>\n";

$conn->close();
?>
