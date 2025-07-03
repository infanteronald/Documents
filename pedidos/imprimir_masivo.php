<?php
/**
 * Generar impresión masiva de pedidos
 * Archivo auxiliar para el sistema de listado de pedidos modernizado
 */

// Verificar que se proporcionen los IDs de pedidos
if (!isset($_POST['pedidos']) || empty($_POST['pedidos'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Lista de pedidos requerida'
    ]);
    exit;
}

$pedidos_ids = $_POST['pedidos'];
$formato = $_POST['formato'] ?? 'pdf'; // pdf, html, excel

// Validar que sea un array
if (!is_array($pedidos_ids)) {
    $pedidos_ids = explode(',', $pedidos_ids);
}

// Limpiar y validar IDs
$pedidos_ids = array_map('intval', array_filter($pedidos_ids, 'is_numeric'));

if (empty($pedidos_ids)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'No se proporcionaron IDs válidos de pedidos'
    ]);
    exit;
}

try {
    // Incluir la configuración de la base de datos
    require_once 'conexion.php';

    // Obtener información de los pedidos
    $placeholders = str_repeat('?,', count($pedidos_ids) - 1) . '?';

    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.cliente_nombre,
            p.cliente_email,
            p.cliente_telefono,
            p.cliente_ciudad,
            p.cliente_direccion,
            p.total,
            p.subtotal,
            p.impuestos,
            p.descuento,
            p.estado,
            p.metodo_pago,
            p.fecha_creacion,
            p.fecha_actualizacion,
            p.comprobante_pago,
            p.guia_envio,
            p.notas
        FROM pedidos p
        WHERE p.id IN ($placeholders)
        ORDER BY p.fecha_creacion DESC
    ");

    $stmt->execute($pedidos_ids);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($pedidos)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'No se encontraron pedidos con los IDs proporcionados'
        ]);
        exit;
    }

    // Obtener productos de todos los pedidos
    $productos_stmt = $pdo->prepare("
        SELECT
            pp.pedido_id,
            pp.cantidad,
            pp.precio_unitario,
            pp.subtotal,
            pr.nombre as producto_nombre,
            pr.codigo as producto_codigo,
            c.nombre as categoria_nombre
        FROM pedidos_productos pp
        LEFT JOIN productos pr ON pp.producto_id = pr.id
        LEFT JOIN categorias c ON pr.categoria_id = c.id
        WHERE pp.pedido_id IN ($placeholders)
        ORDER BY pp.pedido_id, pp.id
    ");

    $productos_stmt->execute($pedidos_ids);
    $productos = $productos_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Agrupar productos por pedido
    $productos_por_pedido = [];
    foreach ($productos as $producto) {
        $productos_por_pedido[$producto['pedido_id']][] = $producto;
    }

    // Generar contenido según formato
    switch ($formato) {
        case 'excel':
            generarExcel($pedidos, $productos_por_pedido);
            break;

        case 'pdf':
            generarPDF($pedidos, $productos_por_pedido);
            break;

        case 'html':
        default:
            generarHTML($pedidos, $productos_por_pedido);
            break;
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno: ' . $e->getMessage()
    ]);
}

function generarHTML($pedidos, $productos_por_pedido) {
    header('Content-Type: text/html; charset=utf-8');

    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Impresión Masiva de Pedidos - Sequoia Speed</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
            .pedido { margin-bottom: 40px; page-break-after: always; border: 1px solid #ddd; padding: 20px; }
            .pedido:last-child { page-break-after: auto; }
            .pedido-header { background: #f8f9fa; padding: 15px; margin: -20px -20px 20px -20px; }
            .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
            .info-section h4 { margin: 0 0 10px 0; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
            .productos-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
            .productos-table th, .productos-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            .productos-table th { background: #f8f9fa; font-weight: bold; }
            .totales { text-align: right; margin-top: 15px; font-weight: bold; }
            .estado { padding: 4px 8px; border-radius: 4px; color: white; font-size: 12px; }
            .estado.pendiente { background: #ffc107; color: #000; }
            .estado.confirmado { background: #28a745; }
            .estado.enviado { background: #17a2b8; }
            .estado.entregado { background: #6f42c1; }
            .estado.cancelado { background: #dc3545; }
            @media print {
                body { margin: 0; }
                .pedido { page-break-after: always; margin-bottom: 0; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Sequoia Speed - Impresión Masiva de Pedidos</h1>
            <p>Generado el: ' . date('d/m/Y H:i:s') . ' | Total de pedidos: ' . count($pedidos) . '</p>
        </div>';

    foreach ($pedidos as $pedido) {
        echo '<div class="pedido">
            <div class="pedido-header">
                <h2>Pedido #' . $pedido['id'] . ' <span class="estado ' . strtolower($pedido['estado']) . '">' . ucfirst($pedido['estado']) . '</span></h2>
                <p><strong>Fecha:</strong> ' . date('d/m/Y H:i', strtotime($pedido['fecha_creacion'])) . ' | <strong>Método de pago:</strong> ' . ucfirst($pedido['metodo_pago']) . '</p>
            </div>

            <div class="info-grid">
                <div class="info-section">
                    <h4>Información del Cliente</h4>
                    <p><strong>Nombre:</strong> ' . htmlspecialchars($pedido['cliente_nombre']) . '</p>
                    <p><strong>Email:</strong> ' . htmlspecialchars($pedido['cliente_email']) . '</p>
                    <p><strong>Teléfono:</strong> ' . htmlspecialchars($pedido['cliente_telefono']) . '</p>
                    <p><strong>Ciudad:</strong> ' . htmlspecialchars($pedido['cliente_ciudad']) . '</p>
                    <p><strong>Dirección:</strong> ' . htmlspecialchars($pedido['cliente_direccion']) . '</p>
                </div>

                <div class="info-section">
                    <h4>Información del Pedido</h4>
                    <p><strong>Estado:</strong> ' . ucfirst($pedido['estado']) . '</p>
                    <p><strong>Comprobante:</strong> ' . ($pedido['comprobante_pago'] ? 'Sí' : 'No') . '</p>
                    <p><strong>Guía de envío:</strong> ' . ($pedido['guia_envio'] ? $pedido['guia_envio'] : 'No asignada') . '</p>
                    ' . ($pedido['notas'] ? '<p><strong>Notas:</strong> ' . htmlspecialchars($pedido['notas']) . '</p>' : '') . '
                </div>
            </div>';

        if (isset($productos_por_pedido[$pedido['id']])) {
            echo '<table class="productos-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>Cantidad</th>
                        <th>Precio Unit.</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($productos_por_pedido[$pedido['id']] as $producto) {
                echo '<tr>
                    <td>' . htmlspecialchars($producto['producto_codigo'] ?? 'N/A') . '</td>
                    <td>' . htmlspecialchars($producto['producto_nombre'] ?? 'Producto eliminado') . '</td>
                    <td>' . htmlspecialchars($producto['categoria_nombre'] ?? 'Sin categoría') . '</td>
                    <td>' . $producto['cantidad'] . '</td>
                    <td>$' . number_format($producto['precio_unitario'], 2) . '</td>
                    <td>$' . number_format($producto['subtotal'], 2) . '</td>
                </tr>';
            }

            echo '</tbody></table>';
        }

        echo '<div class="totales">
            ' . ($pedido['subtotal'] ? '<p>Subtotal: $' . number_format($pedido['subtotal'], 2) . '</p>' : '') . '
            ' . ($pedido['descuento'] ? '<p>Descuento: -$' . number_format($pedido['descuento'], 2) . '</p>' : '') . '
            ' . ($pedido['impuestos'] ? '<p>Impuestos: $' . number_format($pedido['impuestos'], 2) . '</p>' : '') . '
            <p style="font-size: 18px; color: #333;">Total: $' . number_format($pedido['total'], 2) . '</p>
        </div>

        </div>';
    }

    echo '</body></html>';
}

function generarPDF($pedidos, $productos_por_pedido) {
    // Nota: Para generar PDF se necesitaría una librería como TCPDF o mPDF
    // Por ahora generar HTML que se puede convertir a PDF
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="pedidos_' . date('Y-m-d_H-i-s') . '.html"');

    generarHTML($pedidos, $productos_por_pedido);
}

function generarExcel($pedidos, $productos_por_pedido) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="pedidos_' . date('Y-m-d_H-i-s') . '.xls"');

    echo '<table border="1">
        <tr>
            <th>ID Pedido</th>
            <th>Cliente</th>
            <th>Email</th>
            <th>Teléfono</th>
            <th>Ciudad</th>
            <th>Estado</th>
            <th>Método Pago</th>
            <th>Total</th>
            <th>Fecha</th>
            <th>Productos</th>
        </tr>';

    foreach ($pedidos as $pedido) {
        $productos_texto = '';
        if (isset($productos_por_pedido[$pedido['id']])) {
            $productos_lista = [];
            foreach ($productos_por_pedido[$pedido['id']] as $producto) {
                $productos_lista[] = $producto['producto_nombre'] . ' (x' . $producto['cantidad'] . ')';
            }
            $productos_texto = implode('; ', $productos_lista);
        }

        echo '<tr>
            <td>' . $pedido['id'] . '</td>
            <td>' . htmlspecialchars($pedido['cliente_nombre']) . '</td>
            <td>' . htmlspecialchars($pedido['cliente_email']) . '</td>
            <td>' . htmlspecialchars($pedido['cliente_telefono']) . '</td>
            <td>' . htmlspecialchars($pedido['cliente_ciudad']) . '</td>
            <td>' . ucfirst($pedido['estado']) . '</td>
            <td>' . ucfirst($pedido['metodo_pago']) . '</td>
            <td>$' . number_format($pedido['total'], 2) . '</td>
            <td>' . date('d/m/Y H:i', strtotime($pedido['fecha_creacion'])) . '</td>
            <td>' . htmlspecialchars($productos_texto) . '</td>
        </tr>';
    }

    echo '</table>';
}
?>
