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

// Función para limpiar texto para Excel
function cleanForExcel($text) {
    // Remover caracteres de control y normalizar saltos de línea
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
    $text = str_replace(["\r\n", "\r", "\n"], " | ", $text);
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

// Configurar headers para descarga de Excel
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $titulo . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM para UTF-8
echo "\xEF\xBB\xBF";

// Generar HTML para Excel (Excel puede interpretar HTML básico)
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background-color: #4CAF50;
            color: white;
            font-weight: bold;
        }
        .number {
            text-align: right;
            mso-number-format: "\#\,\#\#0";
        }
        .date {
            mso-number-format: "dd\/mm\/yyyy";
        }
        .text {
            mso-number-format: "\@";
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .header-info {
            margin-bottom: 20px;
            font-weight: bold;
        }
        .total-row {
            font-weight: bold;
            background-color: #e0e0e0;
        }
    </style>
</head>
<body>
    <div class="header-info">
        <h2>Reporte de Pedidos - Sequoia Speed</h2>
        <p>Fecha de generación: <?php echo date('d/m/Y H:i'); ?></p>
        <p>Total de pedidos: <?php echo number_format($total_pedidos); ?></p>
        <p>Monto total: $<?php echo number_format($monto_total_real, 0, ',', '.'); ?></p>
        <?php if ($filtro && $filtro != 'todos'): ?>
            <p>Filtro aplicado: <?php echo ucfirst(str_replace('_', ' ', $filtro)); ?></p>
        <?php endif; ?>
        <?php if ($buscar): ?>
            <p>Búsqueda: <?php echo htmlspecialchars($buscar); ?></p>
        <?php endif; ?>
        <?php if ($fecha_desde || $fecha_hasta): ?>
            <p>Período: 
                <?php echo $fecha_desde ? date('d/m/Y', strtotime($fecha_desde)) : 'Inicio'; ?> - 
                <?php echo $fecha_hasta ? date('d/m/Y', strtotime($fecha_hasta)) : 'Hoy'; ?>
            </p>
        <?php endif; ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha Pedido</th>
                <th>Cliente</th>
                <th>Email</th>
                <th>Teléfono</th>
                <th>Ciudad</th>
                <th>Dirección</th>
                <th>Productos</th>
                <th>Total</th>
                <th>Método de Pago</th>
                <th>Estado</th>
                <th>Pagado</th>
                <th>Enviado</th>
                <th>Guía</th>
                <th>Notas</th>
                <th>Origen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pedidos as $pedido): ?>
            <tr>
                <td class="number"><?php echo $pedido['id']; ?></td>
                <td class="date"><?php echo date('d/m/Y', strtotime($pedido['fecha'])); ?></td>
                <td class="text"><?php echo htmlspecialchars(cleanForExcel($pedido['nombre'])); ?></td>
                <td class="text"><?php echo htmlspecialchars(cleanForExcel($pedido['correo'])); ?></td>
                <td class="text"><?php echo htmlspecialchars(cleanForExcel($pedido['telefono'])); ?></td>
                <td class="text"><?php echo htmlspecialchars(cleanForExcel($pedido['ciudad'])); ?></td>
                <td class="text"><?php echo htmlspecialchars(cleanForExcel($pedido['direccion'])); ?></td>
                <td class="text"><?php echo htmlspecialchars(cleanForExcel($pedido['productos'])); ?></td>
                <td class="number"><?php echo number_format($pedido['monto'], 0, ',', '.'); ?></td>
                <td class="text"><?php echo htmlspecialchars($pedido['metodo_pago']); ?></td>
                <td class="text"><?php echo getEstadoTexto($pedido); ?></td>
                <td class="text"><?php echo $pedido['pagado'] ? 'Sí' : 'No'; ?></td>
                <td class="text"><?php echo $pedido['enviado'] ? 'Sí' : 'No'; ?></td>
                <td class="text"><?php echo htmlspecialchars(cleanForExcel($pedido['guia'] ?? '')); ?></td>
                <td class="text"><?php echo htmlspecialchars(cleanForExcel($pedido['nota_interna'] ?? '')); ?></td>
                <td class="text">Web</td>
            </tr>
            <?php endforeach; ?>
            
            <!-- Fila de totales -->
            <tr class="total-row">
                <td colspan="8" style="text-align: right;">TOTALES:</td>
                <td class="number"><?php echo number_format($monto_total_real, 0, ',', '.'); ?></td>
                <td colspan="7">Total de pedidos: <?php echo number_format($total_pedidos); ?></td>
            </tr>
        </tbody>
    </table>
</body>
</html>