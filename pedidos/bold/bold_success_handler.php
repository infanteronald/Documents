<?php
/**
 * Handler para pagos exitosos de Bold
 * Este archivo maneja las redirecciones de éxito de Bold
 */

header('Content-Type: text/html; charset=UTF-8');

$order_id = $_GET['order_id'] ?? '';
$transaction_id = $_GET['transaction_id'] ?? '';
$amount = $_GET['amount'] ?? '';
$status = $_GET['status'] ?? 'success';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago Exitoso - Bold</title>
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
        .success-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 20px;
        }
        h1 {
            color: #28a745;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">✅</div>
        <h1>¡Pago Completado!</h1>

        <p>Su pago ha sido procesado exitosamente a través de Bold.</p>

        <div class="details">
            <div class="detail-row">
                <span><strong>Orden:</strong></span>
                <span><?= htmlspecialchars($order_id) ?></span>
            </div>
            <?php if ($transaction_id): ?>
            <div class="detail-row">
                <span><strong>Transacción:</strong></span>
                <span><?= htmlspecialchars($transaction_id) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($amount): ?>
            <div class="detail-row">
                <span><strong>Monto:</strong></span>
                <span>$<?= number_format($amount) ?> COP</span>
            </div>
            <?php endif; ?>
            <div class="detail-row">
                <span><strong>Fecha:</strong></span>
                <span><?= date('d/m/Y H:i:s') ?></span>
            </div>
        </div>

        <p style="color: #999; margin: 20px 0;">
            Esta ventana se cerrará automáticamente...
        </p>

        <button class="btn" onclick="closeWindow()">Cerrar Ventana</button>
    </div>

    <script>
        console.log('🎉 Bold Success Handler - Pago exitoso');

        // Datos del pago
        const paymentData = {
            orderId: '<?= htmlspecialchars($order_id) ?>',
            transactionId: '<?= htmlspecialchars($transaction_id) ?>',
            amount: <?= intval($amount) ?>,
            status: 'success'
        };

        // Notificar a la ventana padre
        function notifyParent() {
            if (window.opener && !window.opener.closed) {
                const message = {
                    type: 'bold_payment_result',
                    status: 'payment_success',
                    orderId: paymentData.orderId,
                    timestamp: new Date().toISOString(),
                    amount: paymentData.amount,
                    method: 'bold_real',
                    transaction_id: paymentData.transactionId,
                    message: 'Pago procesado exitosamente con Bold'
                };

                try {
                    window.opener.postMessage(message, '*');
                    console.log('✅ Mensaje de éxito enviado a ventana padre');
                } catch (error) {
                    console.error('❌ Error enviando mensaje:', error);
                }
            }
        }

        // Cerrar ventana
        function closeWindow() {
            notifyParent();
            window.close();
        }

        // Auto-enviar notificación y cerrar
        window.addEventListener('load', function() {
            notifyParent();

            // Auto-cerrar después de 5 segundos
            setTimeout(() => {
                window.close();
            }, 5000);
        });
    </script>
</body>
</html>
