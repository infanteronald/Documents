<?php
/**
 * Manejador de Redirecciones Bold
 * Intercepta redirecciones de Bold y las convierte en comunicación con ventana padre
 */

// Obtener todos los parámetros de la URL
$all_params = $_GET;
$order_id = '';
$status = 'unknown';
$transaction_id = '';
$amount = 0;

// Log de debugging
error_log("Bold redirect handler - Params: " . print_r($all_params, true));

// Intentar extraer order_id de diferentes fuentes
if (isset($all_params['order_id'])) {
    $order_id = $all_params['order_id'];
} elseif (isset($all_params['orderId'])) {
    $order_id = $all_params['orderId'];
} elseif (isset($all_params['reference'])) {
    $order_id = $all_params['reference'];
} elseif (isset($all_params['id'])) {
    $order_id = $all_params['id'];
}

// Determinar estado del pago
if (isset($all_params['status'])) {
    $status = strtolower($all_params['status']);
} elseif (isset($all_params['state'])) {
    $status = strtolower($all_params['state']);
}

// Mapear estados Bold a nuestros estados
$success_states = ['success', 'approved', 'completed', 'paid', 'pagado'];
$error_states = ['error', 'failed', 'declined', 'rejected', 'cancelled'];

if (in_array($status, $success_states)) {
    $status = 'success';
} elseif (in_array($status, $error_states)) {
    $status = 'error';
} else {
    // Si no hay estado claro, verificar en base de datos
    if (!empty($order_id)) {
        require_once '../conexion.php';

        try {
            $stmt = $conn->prepare("SELECT estado_pago FROM pedidos_detal WHERE bold_order_id = ? ORDER BY fecha DESC LIMIT 1");
            $stmt->bind_param("s", $order_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if ($row['estado_pago'] === 'pagado' || $row['estado_pago'] === 'Completado') {
                    $status = 'success';
                } elseif ($row['estado_pago'] === 'fallido' || $row['estado_pago'] === 'Cancelado') {
                    $status = 'error';
                }
            }
        } catch (Exception $e) {
            error_log("Error verificando estado en BD: " . $e->getMessage());
        }
    }
}

// Extraer otros datos útiles
if (isset($all_params['transaction_id'])) {
    $transaction_id = $all_params['transaction_id'];
} elseif (isset($all_params['txn_id'])) {
    $transaction_id = $all_params['txn_id'];
}

if (isset($all_params['amount'])) {
    $amount = intval($all_params['amount']);
} elseif (isset($all_params['value'])) {
    $amount = intval($all_params['value']);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Procesando Pago - Sequoia Speed</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, Arial, sans-serif;
            background: #1e1e1e;
            color: #cccccc;
            margin: 0;
            padding: 40px;
            text-align: center;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .processing-container {
            background: #252526;
            padding: 40px;
            border-radius: 12px;
            max-width: 400px;
            width: 100%;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #333;
            border-top: 4px solid #007aff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .status-success { color: #007aff; }
        .status-error { color: #ff6b6b; }
        .status-unknown { color: #ffa500; }
    </style>
</head>
<body>
    <div class="processing-container">
        <div class="spinner"></div>
        <h2>Procesando resultado del pago...</h2>
        <p>Por favor espere mientras procesamos la información.</p>

        <?php if (!empty($order_id)): ?>
        <p><strong>Orden:</strong> <?= htmlspecialchars($order_id) ?></p>
        <?php endif; ?>

        <?php if ($status !== 'unknown'): ?>
        <p class="status-<?= $status ?>">
            <strong>Estado:</strong>
            <?php
            switch($status) {
                case 'success': echo 'Completado exitosamente'; break;
                case 'error': echo 'Error en el proceso'; break;
                default: echo 'Procesando...'; break;
            }
            ?>
        </p>
        <?php endif; ?>

        <p><small>Esta ventana se cerrará automáticamente.</small></p>
    </div>

    <script>
        console.log('🔄 Bold redirect handler cargado');

        // Datos extraídos del servidor
        const paymentData = {
            orderId: '<?= htmlspecialchars($order_id) ?>',
            status: '<?= htmlspecialchars($status) ?>',
            transactionId: '<?= htmlspecialchars($transaction_id) ?>',
            amount: <?= intval($amount) ?>,
            allParams: <?= json_encode($all_params) ?>
        };

        console.log('💳 Datos del pago extraídos:', paymentData);

        function notifyParentAndClose() {
            try {
                if (window.opener && !window.opener.closed) {
                    let messageStatus = 'payment_unknown';

                    if (paymentData.status === 'success') {
                        messageStatus = 'payment_success';
                    } else if (paymentData.status === 'error') {
                        messageStatus = 'payment_error';
                    }

                    const message = {
                        type: 'bold_payment_result',
                        status: messageStatus,
                        orderId: paymentData.orderId,
                        transaction_id: paymentData.transactionId,
                        amount: paymentData.amount,
                        detected_via: 'redirect_handler',
                        raw_params: paymentData.allParams,
                        timestamp: new Date().toISOString()
                    };

                    console.log('📤 Enviando mensaje a ventana padre:', message);

                    // Enviar mensaje múltiples veces para garantizar recepción
                    window.opener.postMessage(message, '*');
                    setTimeout(() => window.opener.postMessage(message, '*'), 500);
                    setTimeout(() => window.opener.postMessage(message, '*'), 1500);
                    setTimeout(() => window.opener.postMessage(message, '*'), 3000);

                    // Cerrar ventana después de enviar mensajes
                    setTimeout(() => {
                        console.log('🔒 Cerrando ventana de redirección');
                        window.close();

                        // Si no se puede cerrar, enfocar la ventana padre
                        setTimeout(() => {
                            if (window.opener && !window.opener.closed) {
                                window.opener.focus();
                            }
                        }, 1000);
                    }, 4000);

                } else {
                    console.warn('⚠️ Ventana padre no disponible');

                    // Si no hay ventana padre, redirigir a página principal
                    setTimeout(() => {
                        window.location.href = '../pedido.php';
                    }, 3000);
                }

            } catch (error) {
                console.error('❌ Error notificando a ventana padre:', error);

                // Fallback: redirigir a página principal
                setTimeout(() => {
                    window.location.href = '../pedido.php';
                }, 3000);
            }
        }

        // Ejecutar inmediatamente al cargar
        document.addEventListener('DOMContentLoaded', function() {
            console.log('📋 DOM cargado, iniciando notificación');
            notifyParentAndClose();
        });

        // También ejecutar cuando la página esté completamente cargada
        window.addEventListener('load', function() {
            console.log('🎯 Página completamente cargada');
            // Dar tiempo adicional para procesar
            setTimeout(notifyParentAndClose, 1000);
        });
    </script>
</body>
</html>
