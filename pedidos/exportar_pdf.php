<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config_secure.php';
require_once 'filters.php';
require_once 'php82_helpers.php';

// Funci�n para obtener productos de un pedido
function getProductosPedido($conn, $pedido_id) {
    $query = "SELECT nombre, cantidad, precio, talla FROM pedido_detalle WHERE pedido_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $pedido_id);
    $stmt->execute();
    
    // Usar bind_result para compatibilidad
    $stmt->bind_result($nombre, $cantidad, $precio, $talla);
    
    $productos = [];
    while ($stmt->fetch()) {
        $talla_display = !empty($talla) ? " ({$talla})" : "";
        $productos[] = $nombre . $talla_display . " x" . $cantidad . " ($" . number_format($precio, 0, ',', '.') . ")";
    }
    
    $stmt->close();
    return implode(", ", $productos);
}

// Funci�n para limpiar texto para PDF
function cleanForPDF($text) {
    return str_replace(["\n", "\r"], " ", $text);
}

// Funci�n para obtener estado de texto
function getEstadoTexto($pedido) {
    if ($pedido['anulado'] == '1') return 'Anulado';
    if ($pedido['tienda'] == '1') return 'Entrega en Tienda';
    if ($pedido['enviado'] == '1') return 'Enviado';
    if ($pedido['pagado'] == '1') return 'Pagado';
    return 'Pendiente';
}

// Procesar filtros usando la nueva clase
try {
    $filter = new PedidosFilter($conn);
    $filter_data = $filter->processFilters();
    
    $pedidos = $filter_data['pedidos'];
    $total_pedidos = $filter_data['total_pedidos'];
    $monto_total_real = $filter_data['monto_total_real'];
    
    // Agregar productos a cada pedido
    foreach ($pedidos as &$pedido) {
        $pedido['productos'] = getProductosPedido($conn, $pedido['id']);
    }
    
} catch (Exception $e) {
    die("Error en los filtros: " . $e->getMessage());
}

// Establecer headers para PDF
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reporte de Pedidos - PDF</title>
    <style>
        @media print {
            @page {
                size: A4 landscape;
                margin: 0.5cm;
            }
            body {
                margin: 0;
                padding: 0;
            }
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 8px;
            margin: 0;
            padding: 10px;
            background: white;
            color: black;
        }
        
        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        
        .header h1 {
            font-size: 16px;
            margin: 0;
            font-weight: bold;
        }
        
        .header .info {
            font-size: 10px;
            margin: 5px 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7px;
            margin-bottom: 15px;
        }
        
        th, td {
            border: 1px solid #000;
            padding: 2px;
            text-align: left;
            vertical-align: top;
        }
        
        th {
            background-color: #f0f0f0;
            font-weight: bold;
            font-size: 7px;
        }
        
        .number {
            text-align: right;
        }
        
        .center {
            text-align: center;
        }
        
        .productos {
            font-size: 6px;
            max-width: 150px;
            word-wrap: break-word;
        }
        
        .text {
            max-width: 80px;
            word-wrap: break-word;
        }
        
        .total-row {
            background-color: #e0e0e0;
            font-weight: bold;
        }
        
        .summary {
            margin-top: 15px;
            border-top: 2px solid #000;
            padding-top: 10px;
            font-size: 9px;
        }
        
        .filters-applied {
            margin-top: 10px;
            font-size: 8px;
            font-style: italic;
        }
    </style>
</head>
<body>

<div class="header">
    <h1>REPORTE DE PEDIDOS</h1>
    <div class="info">Generado el: <?php echo date('Y-m-d H:i:s'); ?></div>
    <div class="info">Total de pedidos: <?php echo $total_pedidos; ?> | Monto total: $<?php echo number_format($monto_total_real, 0, ',', '.'); ?></div>
</div>

<table>
    <thead>
        <tr>
            <th style="width: 30px;">ID</th>
            <th style="width: 50px;">Fecha</th>
            <th style="width: 80px;">Cliente</th>
            <th style="width: 80px;">Email</th>
            <th style="width: 60px;">Tel�fono</th>
            <th style="width: 60px;">Ciudad</th>
            <th style="width: 100px;">Direcci�n</th>
            <th style="width: 150px;">Productos</th>
            <th style="width: 40px;">Desc.</th>
            <th style="width: 50px;">Total</th>
            <th style="width: 60px;">M�todo Pago</th>
            <th style="width: 50px;">Estado</th>
            <th style="width: 30px;">Pagado</th>
            <th style="width: 30px;">Enviado</th>
            <th style="width: 60px;">Gu�a</th>
            <th style="width: 80px;">Notas</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $total_descuentos = 0;
        foreach ($pedidos as $pedido): 
            $total_descuentos += ($pedido['descuento'] ?? 0);
        ?>
            <tr>
                <td class="center"><?php echo $pedido['id']; ?></td>
                <td class="center"><?php echo date('d/m/Y', strtotime($pedido['fecha'])); ?></td>
                <td class="text"><?php echo h(cleanForPDF(substr($pedido['nombre'], 0, 50))); ?></td>
                <td class="text"><?php echo h(cleanForPDF(substr($pedido['correo'], 0, 40))); ?></td>
                <td class="text"><?php echo h(cleanForPDF($pedido['telefono'])); ?></td>
                <td class="text"><?php echo h(cleanForPDF($pedido['ciudad'])); ?></td>
                <td class="text"><?php echo h(cleanForPDF(substr($pedido['direccion'], 0, 60))); ?></td>
                <td class="productos"><?php echo h(cleanForPDF(substr($pedido['productos'], 0, 100))); ?></td>
                <td class="number">$<?php echo number_format($pedido['descuento'] ?? 0, 0, ',', '.'); ?></td>
                <td class="number">$<?php echo number_format($pedido['monto'], 0, ',', '.'); ?></td>
                <td class="text"><?php echo h(substr($pedido['metodo_pago'], 0, 15)); ?></td>
                <td class="text"><?php echo getEstadoTexto($pedido); ?></td>
                <td class="center"><?php echo ($pedido['pagado'] == '1' ? 'S�' : 'No'); ?></td>
                <td class="center"><?php echo ($pedido['enviado'] == '1' ? 'S�' : 'No'); ?></td>
                <td class="text"><?php echo h(cleanForPDF(substr($pedido['guia'] ?: 'N/A', 0, 20))); ?></td>
                <td class="text"><?php echo h(cleanForPDF(substr($pedido['nota_interna'] ?: '', 0, 50))); ?></td>
            </tr>
        <?php endforeach; ?>
        
        <!-- Fila de totales -->
        <tr class="total-row">
            <td colspan="8" style="text-align: right;"><strong>TOTALES:</strong></td>
            <td class="number"><strong>$<?php echo number_format($total_descuentos, 0, ',', '.'); ?></strong></td>
            <td class="number"><strong>$<?php echo number_format($monto_total_real, 0, ',', '.'); ?></strong></td>
            <td colspan="6"><strong>Total de pedidos: <?php echo number_format($total_pedidos); ?></strong></td>
        </tr>
    </tbody>
</table>

<div class="summary">
    <strong>RESUMEN DEL REPORTE</strong><br>
    Total de pedidos mostrados: <?php echo $total_pedidos; ?><br>
    Total de descuentos aplicados: $<?php echo number_format($total_descuentos, 0, ',', '.'); ?><br>
    Monto total de pedidos: $<?php echo number_format($monto_total_real, 0, ',', '.'); ?><br>
    Fecha de generaci�n: <?php echo date('d/m/Y H:i:s'); ?>
</div>

<?php
// Mostrar filtros aplicados
$filtros_aplicados = [];
if (!empty($_GET['filtro'])) $filtros_aplicados[] = "Filtro: " . $_GET['filtro'];
if (!empty($_GET['buscar'])) $filtros_aplicados[] = "B�squeda: " . $_GET['buscar'];
if (!empty($_GET['metodo_pago'])) $filtros_aplicados[] = "M�todo de pago: " . $_GET['metodo_pago'];
if (!empty($_GET['ciudad'])) $filtros_aplicados[] = "Ciudad: " . $_GET['ciudad'];
if (!empty($_GET['fecha_desde']) && !empty($_GET['fecha_hasta'])) {
    $filtros_aplicados[] = "Rango de fecha: " . $_GET['fecha_desde'] . " a " . $_GET['fecha_hasta'];
}

if (!empty($filtros_aplicados)): ?>
<div class="filters-applied">
    <strong>Filtros aplicados:</strong> <?php echo implode(', ', $filtros_aplicados); ?>
</div>
<?php endif; ?>

<script>
window.onload = function() {
    window.print();
}
</script>

</body>
</html>