<?php
/**
 * Ver Detalle de Pedido - Versión Ultra Compatible
 * Sequoia Speed - Máxima compatibilidad con servidores antiguos
 */

// Configuración robusta de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '256M');

// Incluir conexión a base de datos
include 'conexion.php';

// Función para escape HTML seguro
function h($txt) {
    return htmlspecialchars($txt ?? '', ENT_QUOTES, 'UTF-8');
}

// Variables principales
$pedido_encontrado = false;
$id = null;
$p = null;
$productos = [];
$total_productos = 0;
$error_message = '';

// Manejo dual: POST y GET para máxima compatibilidad
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pedido_id'])) {
    $id = intval($_POST['pedido_id']);
} elseif (isset($_GET['id'])) {
    $id = intval($_GET['id']);
}

if ($id && $id > 0) {
    try {
        // Obtener datos del pedido usando consulta directa para compatibilidad
        $id_safe = mysqli_real_escape_string($conn, $id);
        $query = "SELECT * FROM pedidos_detal WHERE id = $id_safe LIMIT 1";
        $result = mysqli_query($conn, $query);

        if ($result && mysqli_num_rows($result) > 0) {
            $pedido_encontrado = true;
            $p = mysqli_fetch_assoc($result);

            // Obtener productos del pedido
            $detalle_query = "SELECT nombre, precio, cantidad, talla FROM pedido_detalle WHERE pedido_id = $id_safe";
            $result_detalle = mysqli_query($conn, $detalle_query);

            if ($result_detalle) {
                while ($item = mysqli_fetch_assoc($result_detalle)) {
                    $productos[] = $item;
                    $total_productos += floatval($item['precio']) * intval($item['cantidad']);
                }
            }
        } else {
            $error_message = "Pedido #$id no encontrado en el sistema.";
        }
    } catch (Exception $e) {
        $error_message = "Error al consultar el pedido: " . $e->getMessage();
    }
} else {
    $error_message = "ID de pedido no válido o no proporcionado.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pedido_encontrado ? 'Pedido #' . h($p['id']) : 'Error'; ?> - Sequoia Speed</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #0d1117 0%, #161b22 100%);
            color: #e6edf3;
            line-height: 1.6;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #21262d;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #1f6feb 0%, #0969da 100%);
            padding: 30px;
            text-align: center;
            position: relative;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }

        .logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 15px;
            display: block;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            border: 3px solid rgba(255, 255, 255, 0.2);
            position: relative;
            z-index: 1;
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }

        .header .subtitle {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            position: relative;
            z-index: 1;
        }

        .content {
            padding: 40px;
        }

        .error {
            background: linear-gradient(135deg, #da3633 0%, #f85149 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
            font-weight: 600;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-card {
            background: #30363d;
            border: 1px solid #3d444d;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .info-card:hover {
            border-color: #1f6feb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(31, 111, 235, 0.15);
        }

        .info-card h3 {
            color: #1f6feb;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .info-card p {
            font-size: 1.1rem;
            font-weight: 500;
            margin: 4px 0;
        }

        .productos-section {
            margin-top: 30px;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #e6edf3;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title::before {
            content: '📦';
            font-size: 1.2rem;
        }

        .productos-table {
            width: 100%;
            border-collapse: collapse;
            background: #30363d;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .productos-table th {
            background: linear-gradient(135deg, #1f6feb 0%, #0969da 100%);
            color: white;
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .productos-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #3d444d;
            vertical-align: middle;
        }

        .productos-table tbody tr:hover {
            background: #3d444d;
        }

        .productos-table tbody tr:last-child td {
            border-bottom: none;
        }

        .precio {
            font-weight: 600;
            color: #238636;
        }

        .total-section {
            margin-top: 30px;
            text-align: right;
        }

        .total-card {
            display: inline-block;
            background: linear-gradient(135deg, #238636 0%, #2ea043 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(35, 134, 54, 0.3);
        }

        .total-card h3 {
            font-size: 1.1rem;
            margin-bottom: 5px;
            opacity: 0.9;
        }

        .total-card .amount {
            font-size: 2rem;
            font-weight: 700;
        }

        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status.pendiente {
            background: linear-gradient(135deg, #fb8500 0%, #ffb700 100%);
            color: white;
        }

        .status.confirmado {
            background: linear-gradient(135deg, #238636 0%, #2ea043 100%);
            color: white;
        }

        .status.cancelado {
            background: linear-gradient(135deg, #da3633 0%, #f85149 100%);
            color: white;
        }

        .form-section {
            background: #30363d;
            border: 1px solid #3d444d;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            margin: 20px 0;
        }

        .form-section h2 {
            color: #1f6feb;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #e6edf3;
        }

        .form-group input {
            width: 200px;
            padding: 12px;
            border: 1px solid #3d444d;
            border-radius: 6px;
            background: #21262d;
            color: #e6edf3;
            font-size: 1rem;
            text-align: center;
        }

        .form-group input:focus {
            outline: none;
            border-color: #1f6feb;
            box-shadow: 0 0 0 2px rgba(31, 111, 235, 0.3);
        }

        .btn {
            background: linear-gradient(135deg, #1f6feb 0%, #0969da 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(31, 111, 235, 0.4);
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 8px;
            }

            .content {
                padding: 20px;
            }

            .info-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .header h1 {
                font-size: 2rem;
            }

            .productos-table th,
            .productos-table td {
                padding: 10px 8px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="logo.png" alt="Sequoia Speed" class="logo" onerror="this.style.display='none'">
            <h1>Sequoia Speed</h1>
            <p class="subtitle">Detalles del Pedido</p>
        </div>

        <div class="content">
            <?php if (!$pedido_encontrado): ?>
                <?php if (empty($id)): ?>
                    <div class="form-section">
                        <h2>Consultar Pedido</h2>
                        <p style="margin-bottom: 20px; opacity: 0.8;">Ingresa el número de pedido para ver los detalles:</p>

                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="pedido_id">Número de Pedido:</label>
                                <input type="number" id="pedido_id" name="pedido_id" min="1" placeholder="Ej: 117" required>
                            </div>
                            <button type="submit" class="btn">Ver Pedido</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="error">
                        <strong>❌ Error:</strong> <?php echo h($error_message); ?>
                    </div>

                    <div class="form-section">
                        <h2>Intentar con otro pedido</h2>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="pedido_id">Número de Pedido:</label>
                                <input type="number" id="pedido_id" name="pedido_id" min="1" placeholder="Ej: 117" required>
                            </div>
                            <button type="submit" class="btn">Ver Pedido</button>
                        </form>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="info-grid">
                    <div class="info-card">
                        <h3>Número de Pedido</h3>
                        <p><strong>#<?php echo h($p['id']); ?></strong></p>
                    </div>

                    <div class="info-card">
                        <h3>Cliente</h3>
                        <p><?php echo h($p['nombre_cliente']); ?></p>
                        <p style="font-size: 0.9rem; opacity: 0.8;"><?php echo h($p['email_cliente']); ?></p>
                    </div>

                    <div class="info-card">
                        <h3>Teléfono</h3>
                        <p><?php echo h($p['telefono_cliente']); ?></p>
                    </div>

                    <div class="info-card">
                        <h3>Estado</h3>
                        <p><span class="status <?php echo strtolower(h($p['estado'])); ?>"><?php echo h($p['estado']); ?></span></p>
                    </div>

                    <div class="info-card">
                        <h3>Fecha</h3>
                        <p><?php echo date('d/m/Y H:i', strtotime($p['fecha_pedido'])); ?></p>
                    </div>

                    <div class="info-card">
                        <h3>Método de Pago</h3>
                        <p><?php echo h($p['metodo_pago'] ?? 'No especificado'); ?></p>
                    </div>
                </div>

                <?php if (!empty($p['direccion_entrega'])): ?>
                <div class="info-card" style="margin-bottom: 30px;">
                    <h3>🚚 Dirección de Entrega</h3>
                    <p><?php echo h($p['direccion_entrega']); ?></p>
                </div>
                <?php endif; ?>

                <?php if (!empty($productos)): ?>
                <div class="productos-section">
                    <h2 class="section-title">Productos Solicitados</h2>

                    <table class="productos-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Talla</th>
                                <th>Cantidad</th>
                                <th>Precio Unit.</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos as $producto): ?>
                            <tr>
                                <td><strong><?php echo h($producto['nombre']); ?></strong></td>
                                <td><?php echo h($producto['talla'] ?? 'N/A'); ?></td>
                                <td><?php echo intval($producto['cantidad']); ?></td>
                                <td class="precio">$<?php echo number_format(floatval($producto['precio']), 0, ',', '.'); ?></td>
                                <td class="precio"><strong>$<?php echo number_format(floatval($producto['precio']) * intval($producto['cantidad']), 0, ',', '.'); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="total-section">
                        <div class="total-card">
                            <h3>Total del Pedido</h3>
                            <div class="amount">$<?php echo number_format($total_productos, 0, ',', '.'); ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($p['notas'])): ?>
                <div class="info-card" style="margin-top: 30px;">
                    <h3>📝 Notas Adicionales</h3>
                    <p><?php echo nl2br(h($p['notas'])); ?></p>
                </div>
                <?php endif; ?>

                <div class="form-section" style="margin-top: 40px;">
                    <h2>Consultar Otro Pedido</h2>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="pedido_id">Número de Pedido:</label>
                            <input type="number" id="pedido_id" name="pedido_id" min="1" placeholder="Ej: 118" required>
                        </div>
                        <button type="submit" class="btn">Ver Pedido</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
