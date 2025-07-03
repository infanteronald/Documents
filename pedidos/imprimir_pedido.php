<?php
/**
 * Imprimir un pedido individual
 * Archivo auxiliar para el sistema de listado de pedidos modernizado
 */

$id_pedido = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id_pedido) {
    die('ID de pedido requerido');
}

try {
    require_once 'conexion.php';

    // Obtener información del pedido
    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.cliente_nombre,
            p.cliente_email,
            p.cliente_telefono,
            p.cliente_ciudad,
            p.cliente_direccion,
            p.cliente_barrio,
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
        WHERE p.id = ?
    ");

    $stmt->execute([$id_pedido]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        die('Pedido no encontrado');
    }

    // Obtener productos del pedido
    $productos_stmt = $pdo->prepare("
        SELECT
            pp.cantidad,
            pp.precio_unitario,
            pp.subtotal,
            pr.nombre as producto_nombre,
            pr.codigo as producto_codigo,
            c.nombre as categoria_nombre
        FROM pedidos_productos pp
        LEFT JOIN productos pr ON pp.producto_id = pr.id
        LEFT JOIN categorias c ON pr.categoria_id = c.id
        WHERE pp.pedido_id = ?
        ORDER BY pp.id
    ");

    $productos_stmt->execute([$id_pedido]);
    $productos = $productos_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die('Error de base de datos: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido #<?php echo $pedido['id']; ?> - Sequoia Speed</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: white;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .company-name {
            font-size: 28px;
            font-weight: bold;
            color: #1f6feb;
            margin-bottom: 5px;
        }
        .pedido-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .info-section {
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
        }
        .info-section h3 {
            margin: 0 0 15px 0;
            color: #1f6feb;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .info-row {
            margin-bottom: 8px;
        }
        .label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }
        .productos-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .productos-table th,
        .productos-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        .productos-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .totales {
            text-align: right;
            margin-top: 20px;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        .totales .total-final {
            font-size: 18px;
            font-weight: bold;
            color: #1f6feb;
            margin-top: 10px;
        }
        .estado {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .estado.pendiente { background: #fff3cd; color: #856404; }
        .estado.confirmado { background: #d4edda; color: #155724; }
        .estado.enviado { background: #cce5ff; color: #004085; }
        .estado.entregado { background: #e7e3ff; color: #5a1a7b; }
        .estado.cancelado { background: #f8d7da; color: #721c24; }
        .notas {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-left: 4px solid #1f6feb;
        }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">SEQUOIA SPEED</div>
        <h2>Comprobante de Pedido #<?php echo $pedido['id']; ?></h2>
        <p>Fecha de impresión: <?php echo date('d/m/Y H:i:s'); ?></p>
    </div>

    <div class="pedido-info">
        <div class="info-section">
            <h3>📋 Información del Pedido</h3>
            <div class="info-row">
                <span class="label">Número:</span>
                #<?php echo $pedido['id']; ?>
            </div>
            <div class="info-row">
                <span class="label">Estado:</span>
                <span class="estado <?php echo strtolower($pedido['estado']); ?>">
                    <?php echo ucfirst($pedido['estado']); ?>
                </span>
            </div>
            <div class="info-row">
                <span class="label">Método Pago:</span>
                <?php echo ucfirst($pedido['metodo_pago']); ?>
            </div>
            <div class="info-row">
                <span class="label">Fecha Creación:</span>
                <?php echo date('d/m/Y H:i', strtotime($pedido['fecha_creacion'])); ?>
            </div>
            <?php if ($pedido['comprobante_pago']): ?>
            <div class="info-row">
                <span class="label">Comprobante:</span>
                ✅ Adjunto
            </div>
            <?php endif; ?>
            <?php if ($pedido['guia_envio']): ?>
            <div class="info-row">
                <span class="label">Guía Envío:</span>
                <?php echo $pedido['guia_envio']; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="info-section">
            <h3>👤 Información del Cliente</h3>
            <div class="info-row">
                <span class="label">Nombre:</span>
                <?php echo htmlspecialchars($pedido['cliente_nombre']); ?>
            </div>
            <div class="info-row">
                <span class="label">Email:</span>
                <?php echo htmlspecialchars($pedido['cliente_email']); ?>
            </div>
            <div class="info-row">
                <span class="label">Teléfono:</span>
                <?php echo htmlspecialchars($pedido['cliente_telefono']); ?>
            </div>
            <div class="info-row">
                <span class="label">Ciudad:</span>
                <?php echo htmlspecialchars($pedido['cliente_ciudad']); ?>
            </div>
            <?php if ($pedido['cliente_barrio']): ?>
            <div class="info-row">
                <span class="label">Barrio:</span>
                <?php echo htmlspecialchars($pedido['cliente_barrio']); ?>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <span class="label">Dirección:</span>
                <?php echo htmlspecialchars($pedido['cliente_direccion']); ?>
            </div>
        </div>
    </div>

    <?php if (!empty($productos)): ?>
    <table class="productos-table">
        <thead>
            <tr>
                <th>Código</th>
                <th>Producto</th>
                <th>Categoría</th>
                <th>Cantidad</th>
                <th>Precio Unitario</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($productos as $producto): ?>
            <tr>
                <td><?php echo htmlspecialchars($producto['producto_codigo'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($producto['producto_nombre'] ?? 'Producto eliminado'); ?></td>
                <td><?php echo htmlspecialchars($producto['categoria_nombre'] ?? 'Sin categoría'); ?></td>
                <td><?php echo $producto['cantidad']; ?></td>
                <td>$<?php echo number_format($producto['precio_unitario'], 2); ?></td>
                <td>$<?php echo number_format($producto['subtotal'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <div class="totales">
        <?php if ($pedido['subtotal']): ?>
        <div>Subtotal: $<?php echo number_format($pedido['subtotal'], 2); ?></div>
        <?php endif; ?>

        <?php if ($pedido['descuento']): ?>
        <div>Descuento: -$<?php echo number_format($pedido['descuento'], 2); ?></div>
        <?php endif; ?>

        <?php if ($pedido['impuestos']): ?>
        <div>Impuestos: $<?php echo number_format($pedido['impuestos'], 2); ?></div>
        <?php endif; ?>

        <div class="total-final">
            Total: $<?php echo number_format($pedido['total'], 2); ?>
        </div>
    </div>

    <?php if ($pedido['notas']): ?>
    <div class="notas">
        <h3>📝 Notas del Pedido</h3>
        <p><?php echo nl2br(htmlspecialchars($pedido['notas'])); ?></p>
    </div>
    <?php endif; ?>

    <script>
        // Auto-imprimir al cargar la página
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
