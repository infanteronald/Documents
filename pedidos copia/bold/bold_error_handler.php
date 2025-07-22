<?php
require_once __DIR__ . '/../php82_helpers.php';
/**
 * Handler para errores de pago de Bold
 * Este archivo maneja las redirecciones de error de Bold
 */

header('Content-Type: text/html; charset=UTF-8');

$order_id = $_GET['order_id'] ?? '';
$error_code = $_GET['error_code'] ?? '';
$error_message = $_GET['error_message'] ?? 'Error en el pago';
$status = $_GET['status'] ?? 'error';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error en Pago - Bold</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #1e1e1e;
            color: #cccccc;
            margin: 0;
            padding: 40px 20px;
            text-align: center;
        }
        .container {
            max-width: 500px;
            margin: 0 auto;
            background: #252526;
            padding: 40px;
            border-radius: 8px;
            border: 1px solid #3e3e42;
        }
        .error-icon {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 20px;
        }
        h1 {
            color: #dc3545;
            margin-bottom: 20px;
        }
        .details {
            background: #1e1e1e;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
            text-align: left;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 5px 0;
            border-bottom: 1px solid #3e3e42;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .btn {
            background: #007aff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            margin: 10px;
        }
        .btn-error {
            background: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">❌</div>
        <h1>Error en el Pago</h1>

        <p>El pago no pudo ser procesado.</p>

        <div class="details">
            <div class="detail-row">
                <span><strong>Orden:</strong></span>
                <span><?= h($order_id) ?></span>
            </div>
            <?php if ($error_code): ?>
            <div class="detail-row">
                <span><strong>Código de Error:</strong></span>
                <span><?= h($error_code) ?></span>
            </div>
            <?php endif; ?>
            <div class="detail-row">
                <span><strong>Descripción:</strong></span>
                <span><?= h($error_message) ?></span>
            </div>
            <div class="detail-row">
                <span><strong>Fecha:</strong></span>
                <span><?= date('d/m/Y H:i:s') ?></span>
            </div>
        </div>

        <p style="color: #999; margin: 20px 0;">
            Puede intentar nuevamente o contactar al soporte técnico.
        </p>

        <button class="btn" onclick="retryPayment()">🔄 Intentar Nuevamente</button>
        <button class="btn btn-error" onclick="closeWindow()">✕ Cerrar</button>
    </div>

    <script>
        console.log('❌ Bold Error Handler - Error en pago');

        // Datos del error
        const errorData = {
            orderId: '<?= h($order_id) ?>',
            errorCode: '<?= h($error_code) ?>',
            errorMessage: '<?= h($error_message) ?>',
            status: 'error'
        };

        // Notificar a la ventana padre
        function notifyParent() {
            if (window.opener && !window.opener.closed) {
                const message = {
                    type: 'bold_payment_result',
                    status: 'payment_error',
                    orderId: errorData.orderId,
                    timestamp: new Date().toISOString(),
                    error: errorData.errorMessage,
                    code: errorData.errorCode,
                    method: 'bold_real',
                    message: 'Error en el pago con Bold'
                };

                try {
                    window.opener.postMessage(message, '*');
                    console.log('✅ Mensaje de error enviado a ventana padre');
                } catch (error) {
                    console.error('❌ Error enviando mensaje:', error);
                }
            }
        }

        // Reintentar pago
        function retryPayment() {
            if (window.opener && !window.opener.closed) {
                // Notificar que se va a reintentar
                try {
                    window.opener.postMessage({
                        type: 'bold_payment_result',
                        status: 'payment_retry',
                        orderId: errorData.orderId
                    }, '*');
                } catch (error) {
                    console.error('❌ Error enviando mensaje de retry:', error);
                }
            }
            window.close();
        }

        // Cerrar ventana
        function closeWindow() {
            notifyParent();
            window.close();
        }

        // Auto-enviar notificación
        window.addEventListener('load', function() {
            notifyParent();
        });
    </script>
</body>
</html>
