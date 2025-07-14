<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'conexion.php';
require_once 'filters.php';


// Función para obtener productos de un pedido (compatible con versiones anteriores de PHP)
function getProductosPedido($conn, $pedido_id) {
    $productos_query = "SELECT p.nombre, pd.cantidad, pd.precio 
                       FROM pedido_detalle pd 
                       INNER JOIN productos p ON pd.producto_id = p.id 
                       WHERE pd.pedido_id = " . intval($pedido_id);
    
    $result = $conn->query($productos_query);
    if (!$result) {
        return 'Error en consulta de productos';
    }
    
    $productos = array();
    while ($row = $result->fetch_assoc()) {
        $productos[] = $row['cantidad'] . 'x ' . $row['nombre'] . ' ($' . number_format($row['precio'], 0, ',', '.') . ')';
    }
    
    return count($productos) > 0 ? implode(' | ', $productos) : 'Sin productos';
}

// Inicializar filtros
try {
    $filter = new PedidosFilter($conn);
    // Remover el límite de paginación para exportar todos los resultados filtrados
    $_GET['limite'] = 99999;
    $filter_data = $filter->processFilters();
    
    $pedidos = $filter_data['pedidos'];
    $total_pedidos = $filter_data['total_pedidos'];
    $monto_total_real = $filter_data['monto_total_real'];
    
    // Obtener productos para cada pedido
    foreach ($pedidos as &$pedido) {
        $pedido['productos'] = getProductosPedido($conn, $pedido['id']);
    }
    
    // Parámetros de filtro para el título
    $params = $filter_data['params'];
    $filtro = $params['filtro'];
    $buscar = $params['buscar'];
    $metodo_pago = $params['metodo_pago'];
    $ciudad = $params['ciudad'];
    $fecha_desde = $params['fecha_desde'];
    $fecha_hasta = $params['fecha_hasta'];
    
} catch (Exception $e) {
    die("Error en los filtros: " . $e->getMessage());
}

// Función para obtener el estado como texto
function getEstadoTexto($pedido) {
    if ($pedido['anulado'] == 1) {
        return 'Anulado';
    } elseif ($pedido['tienda'] == 1) {
        return 'Entrega en Tienda';
    } elseif ($pedido['enviado'] == 1 && $pedido['archivado'] == 1) {
        return 'Completado';
    } elseif ($pedido['enviado'] == 1) {
        return 'Enviado';
    } elseif ($pedido['pagado'] == 1) {
        return 'Pagado';
    } else {
        return 'Pendiente';
    }
}

// Función para limpiar texto para PDF
function cleanForPDF($text) {
    // Remover caracteres de control y normalizar saltos de línea
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
    $text = str_replace(["\r\n", "\r", "\n"], " ", $text);
    return $text;
}

// Generar título del archivo
$titulo = "Pedidos";
if ($filtro && $filtro != 'todos') {
    $titulo .= "_" . str_replace(' ', '_', $filtro);
}
if ($buscar) {
    $titulo .= "_busqueda_" . substr(preg_replace('/[^a-zA-Z0-9]/', '', $buscar), 0, 20);
}
$titulo .= "_" . date('Y-m-d');

// Generate HTML content optimized for browser PDF printing
// Don't set PDF headers - let the browser handle the PDF conversion
?>
<script>
// Auto-open print dialog for PDF generation
window.onload = function() {
    document.title = '<?php echo $titulo; ?>';
    setTimeout(function() {
        window.print();
    }, 1000);
};
</script>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reporte de Pedidos - Sequoia Speed</title>
    <style>
        @media print {
            @page {
                size: A4 landscape;
                margin: 0.5cm;
            }
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
            margin: 0;
            padding: 10px;
            background: white;
        }
        
        .header-info {
            margin-bottom: 15px;
            border-bottom: 2px solid #333;
            padding-bottom: 8px;
            page-break-inside: avoid;
        }
        
        .header-info h2 {
            margin: 0 0 8px 0;
            color: #333;
            font-size: 18px;
            font-weight: bold;
        }
        
        .header-info p {
            margin: 1px 0;
            font-size: 11px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
            margin: 0;
        }
        
        th, td {
            border: 1px solid #333;
            padding: 2px 4px;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word;
        }
        
        th {
            background-color: #4CAF50 !important;
            color: white !important;
            font-weight: bold;
            font-size: 9px;
            text-align: center;
        }
        
        .number {
            text-align: right;
            white-space: nowrap;
        }
        
        .date {
            text-align: center;
            white-space: nowrap;
        }
        
        .total-row {
            font-weight: bold;
            background-color: #e0e0e0 !important;
            font-size: 10px;
        }
        
        .text {
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        
        .productos {
            font-size: 7px;
            max-width: 180px;
        }
        
        /* Ocultar elementos no necesarios para impresión */
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="header-info">
        <h2>Reporte de Pedidos - Sequoia Speed</h2>
        <p><strong>Fecha de generación:</strong> <?php echo date('d/m/Y H:i'); ?></p>
        <p><strong>Total de pedidos:</strong> <?php echo number_format($total_pedidos); ?></p>
        <p><strong>Monto total:</strong> $<?php echo number_format($monto_total_real, 0, ',', '.'); ?></p>
        <?php if ($filtro && $filtro != 'todos'): ?>
            <p><strong>Filtro aplicado:</strong> <?php echo ucfirst(str_replace('_', ' ', $filtro)); ?></p>
        <?php endif; ?>
        <?php if ($buscar): ?>
            <p><strong>Búsqueda:</strong> <?php echo htmlspecialchars($buscar); ?></p>
        <?php endif; ?>
        <?php if ($fecha_desde || $fecha_hasta): ?>
            <p><strong>Período:</strong> 
                <?php echo $fecha_desde ? date('d/m/Y', strtotime($fecha_desde)) : 'Inicio'; ?> - 
                <?php echo $fecha_hasta ? date('d/m/Y', strtotime($fecha_hasta)) : 'Hoy'; ?>
            </p>
        <?php endif; ?>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 30px;">ID</th>
                <th style="width: 60px;">Fecha</th>
                <th style="width: 80px;">Cliente</th>
                <th style="width: 100px;">Email</th>
                <th style="width: 60px;">Teléfono</th>
                <th style="width: 60px;">Ciudad</th>
                <th style="width: 100px;">Dirección</th>
                <th style="width: 150px;">Productos</th>
                <th style="width: 40px;">Desc.</th>
                <th style="width: 50px;">Total</th>
                <th style="width: 60px;">Método Pago</th>
                <th style="width: 50px;">Estado</th>
                <th style="width: 30px;">Pagado</th>
                <th style="width: 30px;">Enviado</th>
                <th style="width: 60px;">Guía</th>
                <th style="width: 80px;">Notas</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pedidos as $pedido): ?>
            <tr>
                <td class="number"><?php echo $pedido['id']; ?></td>
                <td class="date"><?php echo date('d/m/Y', strtotime($pedido['fecha'])); ?></td>
                <td class="text"><?php echo htmlspecialchars(cleanForPDF(substr($pedido['nombre'], 0, 50))); ?></td>
                <td class="text"><?php echo htmlspecialchars(cleanForPDF(substr($pedido['correo'], 0, 40))); ?></td>
                <td class="text"><?php echo htmlspecialchars(cleanForPDF($pedido['telefono'])); ?></td>
                <td class="text"><?php echo htmlspecialchars(cleanForPDF($pedido['ciudad'])); ?></td>
                <td class="text"><?php echo htmlspecialchars(cleanForPDF(substr($pedido['direccion'], 0, 60))); ?></td>
                <td class="productos"><?php echo htmlspecialchars(cleanForPDF(substr($pedido['productos'], 0, 100))); ?></td>
                <td class="number">$<?php echo number_format($pedido['descuento'] ?? 0, 0, ',', '.'); ?></td>
                <td class="number">$<?php echo number_format($pedido['monto'], 0, ',', '.'); ?></td>
                <td class="text"><?php echo htmlspecialchars(substr($pedido['metodo_pago'], 0, 15)); ?></td>
                <td class="text"><?php echo getEstadoTexto($pedido); ?></td>
                <td class="text"><?php echo $pedido['pagado'] ? 'Sí' : 'No'; ?></td>
                <td class="text"><?php echo $pedido['enviado'] ? 'Sí' : 'No'; ?></td>
                <td class="text"><?php echo htmlspecialchars(cleanForPDF(substr($pedido['guia'] ?? '', 0, 20))); ?></td>
                <td class="text"><?php echo htmlspecialchars(cleanForPDF(substr($pedido['nota_interna'] ?? '', 0, 50))); ?></td>
            </tr>
            <?php endforeach; ?>
            
            <!-- Fila de totales -->
            <tr class="total-row">
                <td colspan="8" style="text-align: right;"><strong>TOTALES:</strong></td>
                <td class="number"><strong>$<?php 
                    $total_descuentos = 0;
                    foreach($pedidos as $p) { $total_descuentos += ($p['descuento'] ?? 0); }
                    echo number_format($total_descuentos, 0, ',', '.'); 
                ?></strong></td>
                <td class="number"><strong>$<?php echo number_format($monto_total_real, 0, ',', '.'); ?></strong></td>
                <td colspan="5"><strong>Total de pedidos: <?php echo number_format($total_pedidos); ?></strong></td>
            </tr>
        </tbody>
    </table>

</body>
</html>