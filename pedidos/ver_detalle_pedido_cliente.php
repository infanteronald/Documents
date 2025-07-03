<?php
/**
 * Ver Detalle de Pedido - Versión Cliente
 * Sequoia Speed - Vista de solo lectura para clientes con opciones limitadas
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

// Función para obtener campo de array de manera segura
function getField($array, $field, $default = 'No disponible') {
    // Mapeo de campos alternativos comunes
    $fieldMappings = [
        'nombre_cliente' => ['nombre_cliente', 'nombre', 'cliente_nombre', 'client_name'],
        'email_cliente' => ['email_cliente', 'correo', 'email', 'cliente_email', 'client_email'],
        'telefono_cliente' => ['telefono_cliente', 'telefono', 'cliente_telefono', 'phone'],
        'ciudad_cliente' => ['ciudad_cliente', 'ciudad', 'cliente_ciudad', 'city'],
        'barrio_cliente' => ['barrio_cliente', 'barrio', 'cliente_barrio', 'neighborhood'],
        'fecha_pedido' => ['fecha_pedido', 'fecha', 'created_at', 'date_created'],
        'direccion_entrega' => ['direccion_entrega', 'direccion', 'address', 'delivery_address'],
        'metodo_pago' => ['metodo_pago', 'metodo_pago', 'pago', 'payment_method', 'tipo_pago'],
        'nota_interna' => ['nota_interna', 'notas', 'observaciones', 'comments', 'notes']
    ];

    // Intentar con el campo directo primero
    if (isset($array[$field]) && !empty($array[$field])) {
        return $array[$field];
    }

    // Si hay mapeos alternativos, intentarlos
    if (isset($fieldMappings[$field])) {
        foreach ($fieldMappings[$field] as $altField) {
            if (isset($array[$altField]) && !empty($array[$altField])) {
                return $array[$altField];
            }
        }
    }

    return $default;
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

            // Función para determinar el estado basándose en las columnas booleanas
            function determinarEstado($p) {
                if ($p['anulado'] == 1) {
                    return ['texto' => 'Anulado', 'clase' => 'cancelado'];
                }
                if ($p['archivado'] == 1) {
                    return ['texto' => 'Archivado', 'clase' => 'archivado'];
                }

                // Determinar estado de pago
                $estado_pago = '';
                if ($p['pagado'] == 1) {
                    $estado_pago = 'Pago Confirmado';
                    $clase_pago = 'pago-confirmado';
                } else {
                    $estado_pago = 'Pago Pendiente';
                    $clase_pago = 'pago-pendiente';
                }

                if ($p['enviado'] == 1) {
                    return ['texto' => $estado_pago . ' • Enviado', 'clase' => $clase_pago . ' enviado'];
                }

                // Estado de pago como principal
                return ['texto' => $estado_pago, 'clase' => $clase_pago];
            }

            // Obtener el estado dinámico
            $estado_dinamico = determinarEstado($p);

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
    <title><?php echo $pedido_encontrado ? 'Tu Pedido #' . h($p['id']) . ' - Sequoia Speed' : 'Sequoia Speed'; ?></title>
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
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
            text-size-adjust: 100%;
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
            color: white;
            padding: 25px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            object-fit: contain;
        }

        .header-text h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .header-text p {
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .status {
            display: inline-block;
            padding: 8px 16px;
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

        .status.enviado {
            background: linear-gradient(135deg, #238636 0%, #2ea043 100%);
            color: white;
        }

        .status.archivado {
            background: linear-gradient(135deg, #6e7681 0%, #8b949e 100%);
            color: white;
        }

        .status.confirmado {
            background: linear-gradient(135deg, #1f6feb 0%, #0969da 100%);
            color: white;
        }

        .status.cancelado {
            background: linear-gradient(135deg, #da3633 0%, #f85149 100%);
            color: white;
        }

        .status.pago-pendiente {
            background: linear-gradient(135deg, #da3633 0%, #f85149 100%);
            color: white;
            animation: pulse 2s infinite;
        }

        .status.pago-confirmado {
            background: linear-gradient(135deg, #238636 0%, #3fb950 100%);
            color: white;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        .content {
            padding: 30px;
        }

        .error-card {
            background: linear-gradient(135deg, #da3633 0%, #f85149 100%);
            color: white;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
        }

        .section {
            margin-bottom: 30px;
            background: #30363d;
            border-radius: 8px;
            padding: 25px;
            border: 1px solid #3d444d;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #e6edf3;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-item {
            background: #21262d;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #3d444d;
        }

        .info-label {
            font-size: 0.85rem;
            color: #8b949e;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .info-value {
            font-size: 1rem;
            color: #e6edf3;
            font-weight: 500;
        }

        .productos-table {
            width: 100%;
            border-collapse: collapse;
            background: #30363d;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            margin-bottom: 20px;
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
            text-align: right;
            margin-top: 20px;
        }

        .total-card {
            display: inline-block;
            background: linear-gradient(135deg, #1f6feb 0%, #0969da 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(31, 111, 235, 0.3);
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

        .actions-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .action-card {
            background: #30363d;
            border: 1px solid #3d444d;
            border-radius: 8px;
            padding: 25px;
            text-align: center;
        }

        .action-card h3 {
            color: #1f6feb;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }

        .action-card p {
            color: #8b949e;
            margin-bottom: 20px;
            font-size: 0.95rem;
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
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(31, 111, 235, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #238636 0%, #2ea043 100%);
        }

        .btn-success:hover {
            box-shadow: 0 4px 12px rgba(35, 134, 54, 0.4);
        }

        /* Comentarios del cliente */
        .comentarios-cliente {
            background: #21262d;
            border: 1px solid #3d444d;
            border-radius: 6px;
            padding: 15px;
            margin-top: 15px;
        }

        .comentarios-cliente h4 {
            color: #1f6feb;
            margin-bottom: 10px;
            font-size: 1rem;
        }

        .comentarios-lista {
            max-height: 200px;
            overflow-y: auto;
            white-space: pre-wrap;
            font-size: 0.9rem;
            color: #8b949e;
        }

        /* Modales */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .modal-content {
            background: #161b22;
            border: 1px solid #3d444d;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid #3d444d;
        }

        .modal-close {
            background: none;
            border: none;
            color: #8b949e;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: #3d444d;
            color: #e6edf3;
        }

        .modal-body {
            padding: 25px;
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

        .form-group textarea,
        .form-group input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #3d444d;
            border-radius: 6px;
            background: #21262d;
            color: #e6edf3;
            font-size: 1rem;
            font-family: inherit;
        }

        .form-group textarea:focus,
        .form-group input[type="file"]:focus {
            outline: none;
            border-color: #1f6feb;
            box-shadow: 0 0 0 2px rgba(31, 111, 235, 0.3);
        }

        .form-group textarea {
            height: 120px;
            resize: vertical;
        }

        .status-message {
            margin-top: 15px;
            padding: 10px;
            border-radius: 6px;
            text-align: center;
            font-weight: 600;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 8px;
            }

            .header {
                padding: 20px;
                flex-direction: column;
                text-align: center;
            }

            .header-left {
                flex-direction: column;
                text-align: center;
            }

            .content {
                padding: 20px;
            }

            .section {
                padding: 20px;
            }

            .info-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .actions-section {
                grid-template-columns: 1fr;
            }

            .modal-content {
                width: 95%;
                margin: 10px;
            }

            .modal-header,
            .modal-body {
                padding: 15px 20px;
            }

            .productos-table {
                font-size: 0.9rem;
            }

            .productos-table th,
            .productos-table td {
                padding: 10px 8px;
            }
        }

        @media (max-width: 480px) {
            .header-text h1 {
                font-size: 1.5rem;
            }

            .section-title {
                font-size: 1.2rem;
            }

            .total-card {
                padding: 15px 20px;
            }

            .total-card .amount {
                font-size: 1.6rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($pedido_encontrado): ?>
            <div class="header">
                <div class="header-left">
                    <img src="logo.png" alt="Sequoia Speed" class="logo" onerror="this.style.display='none'">
                    <div class="header-text">
                        <h1>Pedido #<?php echo h($p['id']); ?></h1>
                        <p>Cliente: <?php echo h(getField($p, 'nombre_cliente')); ?></p>
                    </div>
                </div>
                <div class="status <?php echo $estado_dinamico['clase']; ?>">
                    <?php echo $estado_dinamico['texto']; ?>
                </div>
            </div>

            <div class="content">
                <!-- Información del Cliente -->
                <div class="section">
                    <h2 class="section-title">
                        <span>👤</span> Información del Cliente
                    </h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Cliente</div>
                            <div class="info-value"><?php echo h(getField($p, 'nombre_cliente')); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?php echo h(getField($p, 'email_cliente')); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Teléfono</div>
                            <div class="info-value"><?php echo h(getField($p, 'telefono_cliente')); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Ciudad</div>
                            <div class="info-value"><?php echo h(getField($p, 'ciudad_cliente')); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Barrio</div>
                            <div class="info-value"><?php echo h(getField($p, 'barrio_cliente')); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Fecha del Pedido</div>
                            <div class="info-value"><?php echo h(getField($p, 'fecha_pedido')); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Información del Pedido -->
                <div class="section">
                    <h2 class="section-title">
                        <span>📦</span> Detalles del Pedido
                    </h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Dirección de Entrega</div>
                            <div class="info-value"><?php echo h(getField($p, 'direccion_entrega')); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Método de Pago</div>
                            <div class="info-value"><?php echo h(getField($p, 'metodo_pago')); ?></div>
                        </div>
                        <?php if (isset($p['pagado']) && $p['pagado'] == 1): ?>
                        <div class="info-item">
                            <div class="info-label">Estado de Pago</div>
                            <div class="info-value" style="color: #238636; font-weight: 600;">✅ Pagado</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Productos -->
                <div class="section">
                    <h2 class="section-title">
                        <span>🛍️</span> Productos
                    </h2>
                    <?php if (!empty($productos)): ?>
                        <div class="table-container">
                            <table class="productos-table">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Talla</th>
                                        <th>Cantidad</th>
                                        <th>Precio</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($productos as $producto): ?>
                                        <tr>
                                            <td><?php echo h($producto['nombre']); ?></td>
                                            <td><?php echo h($producto['talla']); ?></td>
                                            <td><?php echo h($producto['cantidad']); ?></td>
                                            <td class="precio">$<?php echo number_format($producto['precio'], 0, ',', '.'); ?></td>
                                            <td class="precio">$<?php echo number_format($producto['precio'] * $producto['cantidad'], 0, ',', '.'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="total-section">
                            <div class="total-card">
                                <h3>Total del Pedido</h3>
                                <div class="amount">$<?php echo number_format($total_productos, 0, ',', '.'); ?></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; color: #8b949e; padding: 20px;">No se encontraron productos para este pedido.</p>
                    <?php endif; ?>
                </div>

                <!-- Información adicional -->
                <?php if (!empty(getField($p, 'nota_interna', ''))): ?>
                <div class="section">
                    <h2 class="section-title">
                        <span>📝</span> Información Adicional
                    </h2>
                    <div class="info-item">
                        <div class="info-value">
                            <?php
                            $nota_interna = getField($p, 'nota_interna', '');

                            // Dividir las notas por líneas dobles (separador entre comentarios)
                            $lineas = explode("\n\n", $nota_interna);

                            // Filtrar líneas vacías
                            $lineas = array_filter($lineas, function($linea) {
                                return !empty(trim($linea));
                            });

                            // Revertir el array para mostrar los más recientes primero
                            $lineas = array_reverse($lineas);

                            // Mostrar cada línea
                            foreach ($lineas as $index => $linea) {
                                echo h(trim($linea));
                                // Agregar separador entre comentarios, excepto en el último
                                if ($index < count($lineas) - 1) {
                                    echo "<br><br>";
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Acciones del cliente -->
                <div class="actions-section">
                    <div class="action-card">
                        <h3>💬 Agregar Comentario</h3>
                        <p>¿Tienes alguna pregunta o comentario sobre tu pedido?</p>
                        <button class="btn" onclick="abrirModalComentario()">
                            <span>💬</span> Agregar Comentario
                        </button>
                    </div>

                    <?php if (!isset($p['pagado']) || $p['pagado'] != 1): ?>
                    <div class="action-card">
                        <h3>💳 Subir Comprobante</h3>
                        <p>Si ya realizaste el pago, sube tu comprobante aquí</p>
                        <button class="btn btn-success" onclick="abrirModalComprobante()">
                            <span>📤</span> Subir Comprobante
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="action-card">
                        <h3>✅ Pago Confirmado</h3>
                        <p>Tu pago ha sido verificado y confirmado</p>
                        <div style="color: #238636; font-weight: 600; font-size: 1.1rem; margin-top: 10px;">
                            ✅ Comprobante recibido
                        </div>
                        <?php if (!empty($p['comprobante'])): ?>
                        <div style="margin-top: 15px;">
                            <a href="comprobantes/<?php echo h($p['comprobante']); ?>" target="_blank" class="btn" style="background: linear-gradient(135deg, #1f6feb 0%, #0969da 100%);">
                                <span>👁️</span> Ver Comprobante
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>
            <div class="header">
                <div class="header-left">
                    <img src="logo.png" alt="Sequoia Speed" class="logo" onerror="this.style.display='none'">
                    <div class="header-text">
                        <h1>Error</h1>
                        <p>Pedido no encontrado</p>
                    </div>
                </div>
            </div>
            <div class="content">
                <div class="error-card">
                    <h2>❌ Error</h2>
                    <p><?php echo h($error_message); ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Agregar Comentario -->
    <div id="modalComentario" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3>💬 Agregar Comentario</h3>
                <button class="modal-close" onclick="cerrarModalComentario()">×</button>
            </div>
            <div class="modal-body">
                <form id="formComentario">
                    <input type="hidden" name="pedido_id" value="<?php echo h($id); ?>">
                    <div class="form-group">
                        <label for="comentario">Tu comentario:</label>
                        <textarea name="comentario" id="comentario" required placeholder="Escribe tu comentario o pregunta aquí..."></textarea>
                    </div>
                    <button type="submit" class="btn">
                        <span>💬</span> Enviar Comentario
                    </button>
                    <div id="statusComentario" class="status-message"></div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Subir Comprobante -->
    <div id="modalComprobante" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3>📤 Subir Comprobante de Pago</h3>
                <button class="modal-close" onclick="cerrarModalComprobante()">×</button>
            </div>
            <div class="modal-body">
                <form id="formComprobante" enctype="multipart/form-data">
                    <input type="hidden" name="pedido_id" value="<?php echo h($id); ?>">
                    <div class="form-group">
                        <label for="comprobante">Seleccionar archivo:</label>
                        <input type="file" name="comprobante" id="comprobante" accept=".jpg,.jpeg,.png,.pdf" required>
                        <small style="color: #8b949e; font-size: 0.85rem; display: block; margin-top: 5px;">
                            Formatos permitidos: JPG, PNG, PDF (máx. 5MB)
                        </small>
                    </div>
                    <button type="submit" class="btn btn-success">
                        <span>📤</span> Subir Comprobante
                    </button>
                    <div id="statusComprobante" class="status-message"></div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Funciones para los modales
    function abrirModalComentario() {
        document.getElementById('modalComentario').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function cerrarModalComentario() {
        document.getElementById('modalComentario').style.display = 'none';
        document.body.style.overflow = 'auto';
        // Limpiar formulario
        document.getElementById('formComentario').reset();
        document.getElementById('statusComentario').innerHTML = '';
    }

    function abrirModalComprobante() {
        document.getElementById('modalComprobante').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function cerrarModalComprobante() {
        document.getElementById('modalComprobante').style.display = 'none';
        document.body.style.overflow = 'auto';
        // Limpiar formulario
        document.getElementById('formComprobante').reset();
        document.getElementById('statusComprobante').innerHTML = '';
    }

    // Cerrar modales con ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            cerrarModalComentario();
            cerrarModalComprobante();
        }
    });

    // Cerrar modales al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            cerrarModalComentario();
            cerrarModalComprobante();
        }
    });

    // Manejar envío de comentario
    document.getElementById('formComentario').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const statusDiv = document.getElementById('statusComentario');
        const submitBtn = this.querySelector('button[type="submit"]');

        // Estado de carga
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span>⏳</span> Enviando...';
        statusDiv.innerHTML = '<span style="color: #1f6feb;">📤 Enviando comentario...</span>';

        fetch('agregar_comentario_cliente.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                statusDiv.innerHTML = '<span style="color: #238636;">✅ Comentario agregado correctamente</span>';
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                statusDiv.innerHTML = '<span style="color: #da3633;">❌ ' + (data.error || 'Error al agregar comentario') + '</span>';
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<span>💬</span> Enviar Comentario';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            statusDiv.innerHTML = '<span style="color: #da3633;">❌ Error de conexión</span>';
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<span>💬</span> Enviar Comentario';
        });
    });

    // Manejar envío de comprobante
    document.getElementById('formComprobante').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const statusDiv = document.getElementById('statusComprobante');
        const submitBtn = this.querySelector('button[type="submit"]');

        // Estado de carga
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span>⏳</span> Subiendo...';
        statusDiv.innerHTML = '<span style="color: #1f6feb;">📤 Subiendo comprobante...</span>';

        fetch('subir_comprobante.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                statusDiv.innerHTML = '<span style="color: #238636;">✅ Comprobante subido correctamente</span>';
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                statusDiv.innerHTML = '<span style="color: #da3633;">❌ ' + (data.error || 'Error al subir comprobante') + '</span>';
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<span>📤</span> Subir Comprobante';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            statusDiv.innerHTML = '<span style="color: #da3633;">❌ Error de conexión</span>';
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<span>📤</span> Subir Comprobante';
        });
    });
    </script>
</body>
</html>
