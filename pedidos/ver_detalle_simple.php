<?php
/**
 * Ver Detalle de Pedido - Versión Simplificada que Funciona
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '256M');

include 'conexion.php';
$id = 117;

// Obtener datos del pedido
$r = $conn->query("SELECT * FROM pedidos_detal WHERE id=$id LIMIT 1");
if($r->num_rows==0) {
    echo "<div style='padding: 20px; text-align: center; color: #ff6b6b;'><b>❌ Pedido no encontrado.</b></div>";
    exit;
}
$p = $r->fetch_assoc();

function h($txt) { return htmlspecialchars($txt ?? ''); }

// Obtener productos del pedido
$productos = [];
$detalle_query = "SELECT nombre, precio, cantidad, talla FROM pedido_detalle WHERE pedido_id = ?";
$stmt_detalle = $conn->prepare($detalle_query);
$stmt_detalle->bind_param("i", $id);
$stmt_detalle->execute();
$result_detalle = $stmt_detalle->get_result();
while ($item = $result_detalle->fetch_assoc()) {
    $productos[] = $item;
}
$stmt_detalle->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido #<?php echo h($p['id']); ?> - Sequoia Speed</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Helvetica Neue", Arial, sans-serif;
            background: #0d1117;
            color: #e6edf3;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #161b22;
            border-radius: 12px;
            padding: 24px;
            border: 1px solid #30363d;
        }

        .header {
            background: linear-gradient(135deg, #1f6feb, #0969da);
            padding: 24px;
            text-align: center;
            border-radius: 8px;
            margin-bottom: 24px;
        }

        .header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 24px;
        }

        .logo {
            width: 100px;
            height: auto;
            margin-bottom: 12px;
            border-radius: 8px;
        }

        .info-card {
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
        }

        .info-card h3 {
            color: #1f6feb;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .info-row {
            display: flex;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }

        .info-label {
            color: #8b949e;
            font-weight: 500;
            min-width: 140px;
            font-size: 14px;
        }

        .info-value {
            color: #f0f6fc;
            font-weight: 600;
            font-size: 14px;
            flex: 1;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
            border: 1px solid #ffc107;
        }

        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: #1f6feb;
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-size: 12px;
            margin-left: 8px;
        }

        .section h2 {
            color: #f0f6fc;
            font-size: 18px;
            margin-bottom: 16px;
            border-bottom: 2px solid #1f6feb;
            padding-bottom: 8px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            background: #0d1117;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #30363d;
            margin-top: 16px;
        }

        .table th {
            background: #1f6feb;
            color: #ffffff;
            padding: 12px;
            text-align: left;
            font-size: 14px;
        }

        .table td {
            padding: 12px;
            border-bottom: 1px solid #30363d;
            color: #e6edf3;
            font-size: 14px;
        }

        .table .price {
            color: #1f6feb;
            font-weight: 600;
            text-align: right;
        }

        @media (max-width: 600px) {
            .info-row { flex-direction: column; }
            .info-label { min-width: auto; margin-bottom: 4px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="logo.png" class="logo" alt="Sequoia Speed">
            <h1>Detalle del Pedido #<?php echo h($p['id']); ?></h1>
            <p style="color: rgba(255,255,255,0.8); margin: 8px 0 0 0;">Sistema de Pedidos Sequoia Speed</p>
        </div>

        <div class="info-card">
            <h3>▸ Información del Pedido</h3>
            <div class="info-row">
                <span class="info-label">Número de Pedido:</span>
                <span class="info-value">#<?php echo h($p['id']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Fecha:</span>
                <span class="info-value"><?php echo date('d/m/Y H:i:s', strtotime($p['fecha'])); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Estado:</span>
                <span class="info-value">
                    <span class="status-badge"><?php echo ucfirst(str_replace('_', ' ', $p['estado'])); ?></span>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Método de Pago:</span>
                <span class="info-value"><?php echo h($p['metodo_pago']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Monto Total:</span>
                <span class="info-value">$<?php echo number_format($p['monto'], 0, ',', '.'); ?> COP</span>
            </div>
        </div>

        <div class="info-card">
            <h3>▸ Información del Cliente</h3>
            <div class="info-row">
                <span class="info-label">Nombre:</span>
                <span class="info-value"><?php echo h($p['nombre']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Email:</span>
                <span class="info-value"><?php echo h($p['correo']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Teléfono:</span>
                <span class="info-value">
                    <?php echo h($p['telefono']); ?>
                    <a href="https://wa.me/57<?php echo preg_replace('/\D/', '', $p['telefono']); ?>?text=Hola%20<?php echo urlencode($p['nombre']); ?>,%20tu%20pedido%20#<?php echo $p['id']; ?>"
                       target="_blank" class="btn">💬 WhatsApp</a>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Dirección:</span>
                <span class="info-value"><?php echo h($p['direccion']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Persona que recibe:</span>
                <span class="info-value"><?php echo h($p['persona_recibe']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Horarios:</span>
                <span class="info-value"><?php echo h($p['horarios']); ?></span>
            </div>
        </div>

        <?php if (!empty($productos)): ?>
        <div class="info-card">
            <h3>▸ Productos del Pedido</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Talla</th>
                        <th>Precio Unit.</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos as $item): ?>
                    <tr>
                        <td><?php echo h($item['nombre']); ?></td>
                        <td><?php echo intval($item['cantidad']); ?></td>
                        <td><?php echo h($item['talla'] ?? 'N/A'); ?></td>
                        <td class="price">$<?php echo number_format($item['precio'], 0, ',', '.'); ?></td>
                        <td class="price">$<?php echo number_format($item['precio'] * $item['cantidad'], 0, ',', '.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="info-card">
            <h3>▸ Descripción del Pedido</h3>
            <p style="color: #f0f6fc; font-size: 15px; line-height: 1.6;"><?php echo nl2br(h($p['pedido'] ?? 'No especificado')); ?></p>
        </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 24px;">
            <a href="index.php" class="btn">← Nuevo Pedido</a>
            <a href="listar_pedidos.php" class="btn">📋 Lista de Pedidos</a>
        </div>

        <div style="text-align: center; margin-top: 24px; padding-top: 16px; border-top: 1px solid #30363d;">
            <p style="color: #8b949e; font-size: 14px;">© <?php echo date('Y'); ?> Sequoia Speed - Sistema de Pedidos</p>
        </div>
    </div>
</body>
</html>
