<?php
/**
 * Página de Confirmación de Pago Bold
 * Se ejecuta después de un pago exitoso para comunicar resultado a ventana padre
 */

// Obtener parámetros del callback
$order_id = $_GET['order_id'] ?? $_GET['orden'] ?? '';
$status = $_GET['status'] ?? 'success';
$transaction_id = $_GET['transaction_id'] ?? '';
$amount = $_GET['amount'] ?? 0;

// Si no hay order_id, intentar extraerlo de otros parámetros
if (empty($order_id) && !empty($_GET['orden'])) {
    $order_id = $_GET['orden'];
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pago Completado - Sequoia Speed</title>
    <style>
        :root {
            --vscode-bg: #1e1e1e;
            --vscode-sidebar: #252526;
            --vscode-text: #cccccc;
            --apple-blue: #007aff;
            --space-md: 16px;
            --space-lg: 24px;
            --radius-md: 12px;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Inter', Arial, sans-serif;
            background: var(--vscode-bg);
            color: var(--vscode-text);
            margin: 0;
            padding: var(--space-lg);
            text-align: center;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .confirmation-container {
            background: var(--vscode-sidebar);
            padding: var(--space-lg);
            border-radius: var(--radius-md);
            max-width: 400px;
            width: 100%;
        }

        .success-icon {
            font-size: 4rem;
            color: var(--apple-blue);
            margin-bottom: var(--space-md);
        }

        h1 {
            color: var(--apple-blue);
            margin-bottom: var(--space-md);
        }

        .order-info {
            background: rgba(0, 122, 255, 0.1);
            padding: var(--space-md);
            border-radius: var(--radius-md);
            margin: var(--space-md) 0;
        }

        .auto-close {
            color: #999;
            font-size: 0.9rem;
            margin-top: var(--space-md);
        }

        .close-button {
            background: var(--apple-blue);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: var(--radius-md);
            cursor: pointer;
            margin-top: var(--space-md);
            font-weight: 600;
        }

        .close-button:hover {
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <div class="success-icon">✅</div>
        <h1>¡Pago Completado!</h1>

        <?php if (!empty($order_id)): ?>
        <div class="order-info">
            <p><strong>Orden:</strong> <?= htmlspecialchars($order_id) ?></p>
            <?php if ($amount > 0): ?>
            <p><strong>Monto:</strong> $<?= number_format($amount, 0, ',', '.') ?> COP</p>
            <?php endif; ?>
            <?php if (!empty($transaction_id)): ?>
            <p><strong>Transacción:</strong> <?= htmlspecialchars($transaction_id) ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <p>Su pago ha sido procesado exitosamente.</p>
        <p>Puede cerrar esta ventana y continuar con su pedido.</p>

        <button class="close-button" onclick="closeWindow()">Cerrar Ventana</button>

        <div class="auto-close">
            Esta ventana se cerrará automáticamente en <span id="countdown">5</span> segundos
        </div>
    </div>

    <script>
        console.log('🎉 Página de confirmación de pago cargada');

        // Datos del pago
        const paymentData = {
            orderId: '<?= htmlspecialchars($order_id) ?>',
            status: '<?= htmlspecialchars($status) ?>',
            transactionId: '<?= htmlspecialchars($transaction_id) ?>',
            amount: <?= intval($amount) ?>
        };

        console.log('💳 Datos del pago:', paymentData);

        // Función para notificar a la ventana padre
        function notifyParentWindow() {
            try {
                if (window.opener && !window.opener.closed) {
                    const message = {
                        type: 'bold_payment_result',
                        status: 'payment_success',
                        orderId: paymentData.orderId,
                        transaction_id: paymentData.transactionId,
                        amount: paymentData.amount,
                        message: 'Pago completado exitosamente',
                        timestamp: new Date().toISOString()
                    };

                    console.log('📤 Enviando mensaje a ventana padre:', message);
                    window.opener.postMessage(message, '*');

                    // Enviar múltiples veces para asegurar recepción
                    setTimeout(() => window.opener.postMessage(message, '*'), 500);
                    setTimeout(() => window.opener.postMessage(message, '*'), 1000);

                    return true;
                } else {
                    console.warn('⚠️ Ventana padre no disponible');
                    return false;
                }
            } catch (error) {
                console.error('❌ Error notificando a ventana padre:', error);
                return false;
            }
        }

        // Función para cerrar ventana
        function closeWindow() {
            console.log('🔒 Cerrando ventana manualmente');
            notifyParentWindow();

            setTimeout(() => {
                window.close();

                // Fallback si window.close() no funciona
                setTimeout(() => {
                    if (window.opener && !window.opener.closed) {
                        window.opener.focus();
                    }
                }, 500);
            }, 1000);
        }

        // Countdown y cierre automático
        let countdown = 5;
        const countdownElement = document.getElementById('countdown');

        const countdownInterval = setInterval(() => {
            countdown--;
            if (countdownElement) {
                countdownElement.textContent = countdown;
            }

            if (countdown <= 0) {
                clearInterval(countdownInterval);
                closeWindow();
            }
        }, 1000);

        // Notificar inmediatamente al cargar
        document.addEventListener('DOMContentLoaded', function() {
            console.log('📋 DOM cargado, notificando éxito del pago');
            notifyParentWindow();
        });

        // Notificar también cuando la página esté completamente cargada
        window.addEventListener('load', function() {
            console.log('🎯 Página completamente cargada, enviando notificación final');
            notifyParentWindow();
        });

        // Manejar cierre de ventana
        window.addEventListener('beforeunload', function() {
            console.log('👋 Ventana cerrándose, enviando notificación final');
            notifyParentWindow();
        });
    </script>
</body>
</html>
