<?php
require_once __DIR__ . '/../php82_helpers.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago PSE Bold - Confirmación</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <style>
        /* Usar los mismos estilos de Apple dark theme */
        :root {
            --vscode-bg: #1e1e1e;
            --vscode-sidebar: #252526;
            --vscode-border: #3e3e42;
            --vscode-text: #cccccc;
            --vscode-text-light: #ffffff;
            --apple-blue: #007aff;
            --apple-green: #30d158;
            --space-md: 16px;
            --space-lg: 24px;
            --space-xl: 32px;
            --radius-md: 12px;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'SF Pro Display', 'Helvetica Neue', Arial, sans-serif;
            background: var(--vscode-bg);
            color: var(--vscode-text);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            max-width: 500px;
            margin: 0 auto;
            padding: var(--space-xl);
            background: var(--vscode-sidebar);
            border-radius: var(--radius-md);
            border: 1px solid var(--vscode-border);
            text-align: center;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
        }

        .logo {
            height: 60px;
            width: auto;
            margin-bottom: var(--space-lg);
            object-fit: contain;
        }

        .status-icon {
            font-size: 4rem;
            margin-bottom: var(--space-md);
        }

        .success { color: var(--apple-green); }
        .pending { color: var(--apple-blue); }
        .error { color: #ff6b6b; }

        h1 {
            color: var(--vscode-text-light);
            margin-bottom: var(--space-md);
            font-size: 1.5rem;
            font-weight: 600;
        }

        .order-info {
            background: var(--vscode-bg);
            border-radius: var(--radius-md);
            padding: var(--space-md);
            margin: var(--space-lg) 0;
            border: 1px solid var(--vscode-border);
        }

        .order-info p {
            margin: 8px 0;
            font-size: 0.9rem;
        }

        .order-info strong {
            color: var(--vscode-text-light);
        }

        .actions {
            margin-top: var(--space-xl);
            display: flex;
            gap: var(--space-md);
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--apple-blue);
            color: white;
        }

        .btn-primary:hover {
            background: #0056d3;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--vscode-bg);
            color: var(--vscode-text);
            border: 1px solid var(--vscode-border);
        }

        .btn-secondary:hover {
            background: var(--vscode-border);
        }

        .whatsapp-link {
            margin-top: var(--space-md);
            color: var(--apple-blue);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .whatsapp-link:hover {
            text-decoration: underline;
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid var(--apple-blue);
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 480px) {
            .container {
                margin: var(--space-md);
                padding: var(--space-lg);
            }

            .actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="logo.png" alt="Sequoia Speed" class="logo">
          <?php
        $orden_id = $_GET['orden'] ?? '';
        $bold_order_id = $_GET['bold_order_id'] ?? '';
        $status = 'pending'; // Por defecto

        // Determinar el estado según los parámetros
        if (isset($_GET['bold_success'])) {
            $status = 'success';
        } elseif (isset($_GET['bold_error'])) {
            $status = 'error';
        } elseif (isset($_GET['bold_pending'])) {
            $status = 'pending';
        }

        // Si tenemos bold_order_id, buscar en la base de datos el estado real
        if ($bold_order_id) {
            require_once "../config_secure.php";
            $stmt = $conn->prepare("SELECT pedido, estado_pago FROM pedidos_detal WHERE bold_order_id = ?");
            $stmt->bind_param("s", $bold_order_id);
            $stmt->execute();

            // Usar bind_result para compatibilidad
            $stmt->bind_result($pedido_detalle, $estado_pago);

            if ($stmt->fetch()) {
                $stmt->close();
                $orden_id = $pedido_detalle;

                // Actualizar status basado en estado_pago de la BD
                switch ($pedido_data['estado_pago']) {
                    case 'pagado':
                        $status = 'success';
                        break;
                    case 'fallido':
                        $status = 'error';
                        break;
                    default:
                        $status = 'pending';
                        break;
                }
            } else {
                // Si no encontramos el pedido en BD, puede estar procesándose
                error_log("Bold Order ID $bold_order_id no encontrado en BD, puede estar procesándose");
            }
        }

        // Mostrar contenido según el estado
        switch ($status) {
            case 'success':
                echo '<div class="status-icon success">✅</div>';
                echo '<h1>¡Pago Exitoso!</h1>';
                echo '<p>Su pago ha sido procesado correctamente a través de PSE Bold.</p>';
                break;

            case 'error':
                echo '<div class="status-icon error">❌</div>';
                echo '<h1>Error en el Pago</h1>';
                echo '<p>Hubo un problema al procesar su pago. Puede intentar nuevamente.</p>';
                break;

            case 'pending':
            default:
                echo '<div class="status-icon pending">⏳</div>';
                echo '<h1>Pago en Proceso</h1>';
                echo '<p>Su pago está siendo verificado. Le notificaremos cuando se confirme.</p>';
                break;
        }
        ?>

        <div class="order-info">
            <?php if ($orden_id): ?>
                <p><strong>Número de Orden:</strong> #<?php echo h($orden_id); ?></p>
            <?php endif; ?>

            <?php if ($bold_order_id): ?>
                <p><strong>ID Transacción Bold:</strong> <?php echo h($bold_order_id); ?></p>
            <?php endif; ?>

            <p><strong>Método de Pago:</strong> PSE Bold</p>
            <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i'); ?></p>

            <?php if ($status === 'success'): ?>
                <p style="color: var(--apple-green);"><strong>Estado:</strong> Pagado ✅</p>
            <?php elseif ($status === 'error'): ?>
                <p style="color: #ff6b6b;"><strong>Estado:</strong> Error ❌</p>
            <?php else: ?>
                <p style="color: var(--apple-blue);"><strong>Estado:</strong> Pendiente ⏳</p>
            <?php endif; ?>
        </div>

        <?php if ($status === 'success'): ?>
            <p>Recibirá un correo electrónico con la confirmación de su pedido. Nuestro equipo se pondrá en contacto con usted para coordinar la entrega.</p>
        <?php elseif ($status === 'error'): ?>
            <p>Si el problema persiste, puede contactarnos directamente o intentar con otro método de pago.</p>
        <?php else: ?>
            <p>Los pagos PSE pueden tomar unos minutos en confirmarse. Le enviaremos un correo cuando el pago esté confirmado.</p>
        <?php endif; ?>

        <div class="actions">
            <?php if ($orden_id): ?>
                <a href="comprobante.php?orden=<?php echo urlencode($orden_id); ?>" class="btn btn-primary">
                    Ver Comprobante
                </a>
            <?php endif; ?>

            <a href="pedido.php" class="btn btn-secondary">
                Nueva Orden
            </a>

            <?php if ($status === 'error'): ?>
                <a href="pedido.php?retry=1&orden=<?php echo urlencode($orden_id); ?>" class="btn btn-primary">
                    Intentar Nuevamente
                </a>
            <?php endif; ?>
        </div>

        <a href="https://wa.me/573142162979" target="_blank" class="whatsapp-link">
            📱 ¿Necesita ayuda? Contáctenos por WhatsApp
        </a>

        <?php if ($status === 'pending'): ?>
            <script>
                // Auto-actualizar la página cada 30 segundos para verificar el estado
                setTimeout(() => {
                    window.location.reload();
                }, 30000);
            </script>
        <?php endif; ?>
    </div>

    <?php
    // Usar conexión segura
    require_once '../config_secure.php';

    // Verificar conexión
    if ($conn->connect_error) {
        die("Conexión fallida: " . $conn->connect_error);
    }

    // Obtener el order_id desde la URL
    $order_id = $_GET['orden'] ?? '';

    // Cambiar consulta para usar pedidos_detal
    $stmt = $conn->prepare("SELECT id, estado_pago, monto, descuento, nombre, correo, pedido FROM pedidos_detal WHERE bold_order_id = ? LIMIT 1");
    $stmt->bind_param("s", $order_id);
    $stmt->execute();

    // Usar bind_result para compatibilidad
    $stmt->bind_result($pedido_id, $estado_pago, $monto, $descuento, $nombre, $correo, $pedido_detalle);

    if ($stmt->fetch()) {
        $stmt->close();

        // Mostrar información del pedido desde pedidos_detal
        echo "<h2>Confirmación de Pago</h2>";
        echo "<p><strong>Número de Pedido:</strong> #" . $pedido_id . "</p>";
        echo "<p><strong>Estado del Pago:</strong> " . ucfirst($estado_pago) . "</p>";
        // Mostrar desglose de monto con descuento
        if ($descuento > 0) {
            $subtotal = $monto + $descuento;
            echo "<p><strong>Subtotal:</strong> $" . number_format($subtotal, 0, ',', '.') . "</p>";
            echo "<p style='color: var(--apple-green);'><strong>Descuento:</strong> -$" . number_format($descuento, 0, ',', '.') . "</p>";
            echo "<p><strong>Total Final:</strong> $" . number_format($monto, 0, ',', '.') . "</p>";
        } else {
            echo "<p><strong>Monto:</strong> $" . number_format($monto, 0, ',', '.') . "</p>";
        }
        echo "<p><strong>Nombre:</strong> " . h($nombre) . "</p>";
        echo "<p><strong>Método de Pago:</strong> " . h($pedido['metodo_pago']) . "</p>";

        if ($pedido['estado_pago'] === 'pagado') {
            echo "<div style='color: green; font-weight: bold; margin: 20px 0;'>";
            echo "✅ Su pago ha sido procesado exitosamente";
            echo "</div>";
        } else {
            echo "<div style='color: orange; font-weight: bold; margin: 20px 0;'>";
            echo "⏳ Su pago está siendo procesado";
            echo "</div>";
        }

    } else {
        echo "<h2>Pedido no encontrado</h2>";
        echo "<p>No se pudo encontrar el pedido con ID: " . h($order_id) . "</p>";
    }

    $stmt->close();
    $conn->close();
    ?>
</body>
</html>
