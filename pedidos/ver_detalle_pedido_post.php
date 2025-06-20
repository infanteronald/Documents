<?php
/**
 * Ver Detalle de Pedido - Versión con formulario POST
 * Evita problemas con parámetros GET
 */

// Mostrar errores para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'conexion.php';

// Función para escape HTML
function h($txt) { return htmlspecialchars($txt); }

$pedido_encontrado = false;
$id = null;
$p = null;
$productos = [];
$total_productos = 0;

// Procesar formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pedido_id'])) {
    $id = intval($_POST['pedido_id']);

    // Obtener datos del pedido
    $r = $conn->query("SELECT * FROM pedidos_detal WHERE id=$id LIMIT 1");
    if ($r && $r->num_rows > 0) {
        $pedido_encontrado = true;
        $p = $r->fetch_assoc();

        // Obtener productos del pedido
        $detalle_query = "SELECT nombre, precio, cantidad, talla FROM pedido_detalle WHERE pedido_id = ?";
        $stmt_detalle = $conn->prepare($detalle_query);
        $stmt_detalle->bind_param("i", $id);
        $stmt_detalle->execute();
        $result_detalle = $stmt_detalle->get_result();
        while ($item = $result_detalle->fetch_assoc()) {
            $productos[] = $item;
            $total_productos += $item['precio'] * $item['cantidad'];
        }
        $stmt_detalle->close();
    }
}

// También procesar GET si existe (para compatibilidad)
if (!$pedido_encontrado && isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Obtener datos del pedido
    $r = $conn->query("SELECT * FROM pedidos_detal WHERE id=$id LIMIT 1");
    if ($r && $r->num_rows > 0) {
        $pedido_encontrado = true;
        $p = $r->fetch_assoc();

        // Obtener productos del pedido
        $detalle_query = "SELECT nombre, precio, cantidad, talla FROM pedido_detalle WHERE pedido_id = ?";
        $stmt_detalle = $conn->prepare($detalle_query);
        $stmt_detalle->bind_param("i", $id);
        $stmt_detalle->execute();
        $result_detalle = $stmt_detalle->get_result();
        while ($item = $result_detalle->fetch_assoc()) {
            $productos[] = $item;
            $total_productos += $item['precio'] * $item['cantidad'];
        }
        $stmt_detalle->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pedido_encontrado ? 'Pedido #' . $p['id'] : 'Buscar Pedido'; ?> - Sequoia Speed</title>
    <style>
        /* Reset y base - Misma paleta que email_templates.php */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Helvetica Neue", Arial, sans-serif;
            background: #0d1117;
            color: #e6edf3;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }

        /* Container principal */
        .page-container {
            max-width: 600px;
            margin: 0 auto;
            background: #161b22;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.6);
            border: 1px solid #30363d;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #1f6feb, #0969da);
            padding: 32px 24px;
            text-align: center;
            border-bottom: 1px solid #30363d;
        }

        .logo {
            width: 120px;
            height: auto;
            margin-bottom: 16px;
            filter: brightness(1.2);
            border-radius: 8px;
        }

        .header h1 {
            color: #ffffff;
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .header .subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
            margin-top: 8px;
            font-weight: 400;
        }

        /* Content */
        .content {
            padding: 32px 24px;
        }

        .section {
            margin-bottom: 32px;
        }

        .section h2 {
            color: #f0f6fc;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 16px;
            border-bottom: 2px solid #1f6feb;
            padding-bottom: 8px;
        }

        .section p {
            margin-bottom: 12px;
            color: #e6edf3;
            font-size: 15px;
        }

        /* Formulario de búsqueda */
        .search-form {
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
            text-align: center;
        }

        .search-form input {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 6px;
            padding: 12px 16px;
            color: #e6edf3;
            font-size: 16px;
            width: 200px;
            margin-right: 12px;
        }

        .search-form input:focus {
            outline: none;
            border-color: #1f6feb;
        }

        /* Info cards */
        .info-card {
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 16px;
        }

        .info-card h3 {
            color: #1f6feb;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
        }

        .info-card h3::before {
            content: "▸";
            margin-right: 8px;
            color: #1f6feb;
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
            flex: 1;
            font-size: 14px;
        }

        /* Table para productos */
        .products-table {
            width: 100%;
            border-collapse: collapse;
            background: #0d1117;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #30363d;
        }

        .products-table th {
            background: #1f6feb;
            color: #ffffff;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .products-table td {
            padding: 12px;
            border-bottom: 1px solid #30363d;
            color: #e6edf3;
            font-size: 14px;
        }

        .products-table tr:last-child td {
            border-bottom: none;
        }

        .products-table .price {
            color: #1f6feb;
            font-weight: 600;
            text-align: right;
        }

        /* Status badges */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-success {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
            border: 1px solid #28a745;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
            border: 1px solid #ffc107;
        }

        .status-error {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
            border: 1px solid #dc3545;
        }

        .status-shipped {
            background: rgba(32, 201, 151, 0.2);
            color: #20c997;
            border: 1px solid #20c997;
        }

        /* Buttons */
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #1f6feb;
            color: #ffffff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            margin: 8px 4px;
        }

        .btn:hover {
            background: #0969da;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid #30363d;
            color: #e6edf3;
        }

        .btn-secondary:hover {
            background: #30363d;
            color: #ffffff;
        }

        /* Footer */
        .footer {
            background: #0d1117;
            padding: 24px;
            text-align: center;
            border-top: 1px solid #30363d;
        }

        .footer p {
            color: #8b949e;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .footer .company-info {
            color: #f0f6fc;
            font-weight: 600;
            margin-bottom: 16px;
        }

        /* Error y éxito */
        .error-msg {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #dc3545;
            margin-bottom: 16px;
            text-align: center;
        }

        /* Responsive */
        @media (max-width: 600px) {
            body {
                padding: 10px;
            }

            .page-container {
                border-radius: 0;
                margin: 0;
            }

            .header {
                padding: 24px 16px;
            }

            .content {
                padding: 24px 16px;
            }

            .info-row {
                flex-direction: column;
            }

            .info-label {
                min-width: auto;
                margin-bottom: 4px;
            }

            .search-form input {
                width: 100%;
                margin-right: 0;
                margin-bottom: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="header">
            <img src="logo.png" class="logo" alt="Sequoia Speed">
            <h1><?php echo $pedido_encontrado ? 'Detalle del Pedido #' . h($p['id']) : 'Buscar Pedido'; ?></h1>
            <div class="subtitle">Sistema de Pedidos Sequoia Speed</div>
        </div>

        <div class="content">
            <?php if (!$pedido_encontrado): ?>
            <!-- Formulario de búsqueda -->
            <div class="section">
                <h2>🔍 Buscar Pedido</h2>
                <p>Ingresa el número de pedido que deseas consultar:</p>
            </div>

            <form method="POST" class="search-form">
                <input type="number" name="pedido_id" placeholder="Número de pedido" value="<?php echo $id ? $id : ''; ?>" required>
                <button type="submit" class="btn">Buscar Pedido</button>
            </form>

            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pedido_id'])): ?>
            <div class="error-msg">
                ❌ Pedido #<?php echo h($_POST['pedido_id']); ?> no encontrado
            </div>
            <?php endif; ?>

            <?php else: ?>
            <!-- Mostrar detalles del pedido -->
            <div class="section">
                <h2>📦 Información del Pedido</h2>
                <p>Detalles completos de su pedido en el sistema Sequoia Speed.</p>
            </div>

            <div class="info-card">
                <h3>Información del Pedido</h3>
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
                        <?php
                        $estado = $p['estado'];
                        $clase_estado = 'status-pending';
                        if ($estado == 'completado' || $estado == 'entregado') $clase_estado = 'status-success';
                        if ($estado == 'enviado') $clase_estado = 'status-shipped';
                        if ($estado == 'cancelado' || $estado == 'fallido') $clase_estado = 'status-error';
                        ?>
                        <span class="status-badge <?php echo $clase_estado; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $estado)); ?>
                        </span>
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
                <h3>Información del Cliente</h3>
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
                           target="_blank" class="btn btn-secondary" style="margin-left: 12px; padding: 4px 8px; font-size: 12px;">
                            💬 WhatsApp
                        </a>
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

            <!-- Productos/Descripción del Pedido -->
            <?php if (!empty($productos)): ?>
            <div class="section">
                <h2>🛍️ Detalles del Pedido</h2>
                <table class="products-table">
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
                <h3>Descripción del Pedido</h3>
                <p style="color: #f0f6fc; font-size: 15px; line-height: 1.6;"><?php echo nl2br(h($p['pedido'] ?? 'No especificado')); ?></p>
            </div>
            <?php endif; ?>

            <!-- Acciones -->
            <div class="section" style="text-align: center; margin-top: 32px;">
                <form method="POST" style="display: inline;">
                    <button type="submit" class="btn btn-secondary">🔍 Buscar Otro Pedido</button>
                </form>
                <a href="index.php" class="btn btn-secondary">← Nuevo Pedido</a>
                <a href="listar_pedidos.php" class="btn">📋 Lista de Pedidos</a>
            </div>

            <?php endif; ?>
        </div>

        <div class="footer">
            <div class="company-info">Sequoia Speed</div>
            <p>Sistema automatizado de gestión de pedidos</p>
            <p>© <?php echo date('Y'); ?> Sequoia Speed. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
