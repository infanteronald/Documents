<?php
// Obtener parámetros de la URL
$order_id = $_GET['order_id'] ?? '';
$amount = $_GET['amount'] ?? 0;
$method = $_GET['method'] ?? 'PSE Bold';
$customer_data = $_GET['customer_data'] ?? '{}';
$billing_address = $_GET['billing_address'] ?? '{}';

// Decodificar datos del cliente
$customer = json_decode(urldecode($customer_data), true) ?: [];
$billing = json_decode(urldecode($billing_address), true) ?: [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pago Seguro - Sequoia Speed</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <script src="https://checkout.bold.co/library/boldPaymentButton.js"></script>
    <style>
        :root {
            --vscode-bg: #1e1e1e;
            --vscode-sidebar: #252526;
            --vscode-border: #3e3e42;
            --vscode-text: #cccccc;
            --vscode-text-light: #ffffff;
            --apple-blue: #007aff;
            --gray-dark: rgba(204, 204, 204, 0.05);
            --space-md: 16px;
            --space-lg: 24px;
            --radius-sm: 6px;
            --radius-md: 12px;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Inter', Arial, sans-serif;
            background: var(--vscode-bg);
            color: var(--vscode-text);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .payment-container {
            background: var(--vscode-sidebar);
            border-radius: var(--radius-md);
            padding: var(--space-lg);
            max-width: 500px;
            width: 90%;
            text-align: center;
            border: 1px solid var(--vscode-border);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .logo {
            height: 40px;
            width: auto;
            margin-bottom: var(--space-md);
        }

        h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: var(--space-md);
            color: var(--vscode-text-light);
        }

        .payment-info {
            background: var(--gray-dark);
            padding: var(--space-md);
            border-radius: var(--radius-sm);
            margin-bottom: var(--space-lg);
            text-align: left;
        }

        .payment-info h3 {
            margin: 0 0 var(--space-md) 0;
            color: var(--apple-blue);
            font-size: 1.1rem;
        }

        .payment-info p {
            margin: 4px 0;
            font-size: 0.9rem;
        }

        .payment-info .amount {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--vscode-text-light);
        }

        #bold-payment-container {
            min-height: 80px;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: var(--space-lg) 0;
        }

        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-md);
            color: var(--apple-blue);
            font-size: 0.9rem;
        }

        .loading::before {
            content: '';
            width: 16px;
            height: 16px;
            border: 2px solid var(--apple-blue);
            border-top: 2px solid transparent;
            border-radius: 50%;
            margin-right: 8px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .close-info {
            margin-top: var(--space-lg);
            padding: var(--space-md);
            background: rgba(0, 122, 255, 0.1);
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
        }
    </style>
</head>

<body>
    <div class="payment-container">
        <img src="../logo.png" class="logo" alt="Sequoia Speed">
        <h1>Pago Seguro</h1>

        <div class="payment-info">
            <h3><?= htmlspecialchars($method) ?></h3>
            <p><strong>Orden:</strong> <?= htmlspecialchars($order_id) ?></p>
            <?php if ($amount > 0): ?>
                <p class="amount"><strong>Monto:</strong> $<?= number_format($amount, 0, ',', '.') ?> COP</p>
            <?php else: ?>
                <p class="amount"><strong>Monto:</strong> A definir por el cliente</p>
            <?php endif; ?>
            <?php if (!empty($customer['fullName'])): ?>
                <p><strong>Cliente:</strong> <?= htmlspecialchars($customer['fullName']) ?></p>
            <?php endif; ?>
            <?php if (!empty($customer['email'])): ?>
                <p><strong>Email:</strong> <?= htmlspecialchars($customer['email']) ?></p>
            <?php endif; ?>
        </div>

        <div id="bold-payment-container">
            <div class="loading">Inicializando pago seguro...</div>
        </div>

        <div class="close-info">
            💡 <strong>Información:</strong> Al completar el pago, esta ventana se cerrará automáticamente y podrás continuar en la página principal.
        </div>
    </div>

    <script>
        // SISTEMA MEJORADO DE COMUNICACIÓN - V5.0 (SIN LOOPS)
        console.log('🚀 BOLD PAYMENT V5.0 - SISTEMA SIN LOOPS');

        const orderData = {
            orderId: '<?= htmlspecialchars($order_id) ?>',
            amount: <?= intval($amount) ?>,
            method: '<?= htmlspecialchars($method) ?>',
            customer: <?= json_encode($customer) ?>,
            billing: <?= json_encode($billing) ?>
        };

        console.log('📋 Datos de la orden:', orderData);

        // Control de envío de mensajes para evitar spam
        let messagesSent = {
            payment_started: false,
            payment_success: false,
            payment_error: false,
            payment_closed: false
        };

        // FUNCIÓN PRINCIPAL: Notificar una sola vez por estado
        function notifyParentOnce(status, data = {}) {
            if (messagesSent[status]) {
                console.log('� Mensaje ya enviado para estado:', status);
                return;
            }

            messagesSent[status] = true;
            console.log('📤 [ÚNICO] Enviando mensaje:', status);

            if (!window.opener || window.opener.closed) {
                console.warn('⚠️ Ventana padre no disponible');
                return;
            }

            const message = {
                type: 'bold_payment_result',
                status: status,
                orderId: orderData.orderId,
                timestamp: new Date().toISOString(),
                amount: orderData.amount,
                method: 'direct_window_v5',
                ...data
            };

            try {
                // Envío único con 3 intentos espaciados
                window.opener.postMessage(message, '*');
                setTimeout(() => window.opener.postMessage(message, '*'), 500);
                setTimeout(() => window.opener.postMessage(message, '*'), 1500);

                console.log('✅ [ÚNICO] Mensaje enviado:', message);

                // Guardar en localStorage como respaldo
                localStorage.setItem(`bold_payment_${orderData.orderId}`, JSON.stringify(message));
                localStorage.setItem('bold_last_payment_result', JSON.stringify(message));

            } catch (error) {
                console.error('❌ [ÚNICO] Error:', error);
            }
        }

        // INICIALIZACIÓN DEL PAGO BOLD REAL
        async function initializeBoldPayment() {
            console.log('🔧 Inicializando pago Bold real...');

            try {
                // Notificar que el pago ha iniciado (solo una vez)
                notifyParentOnce('payment_started', {
                    message: 'Ventana de pago inicializada'
                });

                // Configuración Bold
                const boldConfig = {
                    apiKey: 'cbc16e3ff42d9be85b3ea5dd2bde0b80',
                    sandbox: true,
                    currency: 'COP',
                    amount: orderData.amount,
                    orderReference: orderData.orderId,
                    customer: {
                        name: orderData.customer.fullName || 'Cliente',
                        email: orderData.customer.email,
                        phone: orderData.customer.phone
                    },
                    paymentMethods: ['PSE', 'CARD'],
                    onSuccess: function(response) {
                        console.log('✅ PAGO EXITOSO:', response);
                        handlePaymentSuccess(response);
                    },
                    onError: function(error) {
                        console.log('❌ ERROR DE PAGO:', error);
                        handlePaymentError(error);
                    },
                    onClose: function() {
                        console.log('� Widget cerrado');
                        // No notificar aquí porque puede ser confuso
                    }
                };

                // Verificar si Bold está disponible
                if (typeof BoldCheckout !== 'undefined') {
                    console.log('🎯 Creando widget Bold...');

                    const container = document.getElementById('bold-payment-container');
                    container.innerHTML = '<div class="loading">Conectando con Bold...</div>';

                    // Crear widget Bold
                    const boldWidget = new BoldCheckout(boldConfig);

                    // Montar el widget
                    boldWidget.mount('#bold-payment-container');

                    console.log('✅ Widget Bold montado correctamente');

                } else {
                    console.warn('⚠️ Bold no disponible, usando simulación');
                    await simulatePaymentFlow();
                }

            } catch (error) {
                console.error('❌ Error inicializando Bold:', error);
                showErrorUI('Error inicializando sistema de pago');
            }
        }

        // SIMULACIÓN DE FLUJO DE PAGO (para pruebas)
        async function simulatePaymentFlow() {
            console.log('🎭 Iniciando simulación de pago...');

            const container = document.getElementById('bold-payment-container');

            // UI de simulación
            container.innerHTML = `
                <div style="text-align: center; padding: 20px;">
                    <h3 style="color: var(--apple-blue); margin-bottom: 20px;">Simulación de Pago PSE</h3>
                    <p style="margin-bottom: 20px;">Orden: ${orderData.orderId}</p>
                    <p style="margin-bottom: 20px;">Monto: $${orderData.amount.toLocaleString()} COP</p>

                    <div style="margin: 20px 0;">
                        <button onclick="simulateSuccess()" style="background: #28a745; color: white; border: none; padding: 12px 24px; border-radius: 8px; margin: 10px; cursor: pointer;">
                            ✅ Simular Pago Exitoso
                        </button>
                        <button onclick="simulateError()" style="background: #dc3545; color: white; border: none; padding: 12px 24px; border-radius: 8px; margin: 10px; cursor: pointer;">
                            ❌ Simular Error de Pago
                        </button>
                    </div>

                    <p style="font-size: 0.8rem; color: var(--vscode-text-muted);">
                        Esta es una simulación para pruebas.<br>
                        En producción se usará el widget real de Bold.
                    </p>
                </div>
            `;

            // Funciones globales para los botones
            window.simulateSuccess = function() {
                console.log('🎉 Usuario seleccionó simular éxito');
                handlePaymentSuccess({
                    transactionId: 'SIM-' + Date.now(),
                    amount: orderData.amount,
                    status: 'APPROVED'
                });
            };

            window.simulateError = function() {
                console.log('❌ Usuario seleccionó simular error');
                handlePaymentError({
                    error: 'Pago rechazado por el banco',
                    code: 'DECLINED'
                });
            };
        }

        // MANEJADORES DE RESULTADO
        function handlePaymentSuccess(response) {
            console.log('🎉 PROCESANDO ÉXITO DE PAGO:', response);

            notifyParentOnce('payment_success', {
                transaction_id: response.transactionId || response.id || 'N/A',
                amount: response.amount || orderData.amount,
                message: 'Pago completado exitosamente',
                response: response
            });

            showSuccessUI(response);
        }

        function handlePaymentError(error) {
            console.log('❌ PROCESANDO ERROR DE PAGO:', error);

            notifyParentOnce('payment_error', {
                error: error.message || error.error || 'Error desconocido',
                code: error.code || 'UNKNOWN',
                details: error
            });

            showErrorUI(error.message || error.error || 'Error en el pago');
        }

        function showSuccessUI(response) {
            document.body.innerHTML = `
                <div style="background: #1e1e1e; color: #cccccc; padding: 40px; text-align: center; font-family: Arial; min-height: 100vh; display: flex; flex-direction: column; justify-content: center;">
                    <div style="background: #252526; padding: 40px; border-radius: 12px; max-width: 500px; margin: 0 auto;">
                        <div style="font-size: 4rem; color: #28a745; margin-bottom: 20px;">✅</div>
                        <h1 style="color: #28a745; margin-bottom: 20px;">¡Pago Completado!</h1>
                        <p style="margin-bottom: 10px;"><strong>Orden:</strong> ${orderData.orderId}</p>
                        <p style="margin-bottom: 20px;"><strong>Monto:</strong> $${orderData.amount.toLocaleString()} COP</p>
                        ${response.transactionId ? `<p style="margin-bottom: 20px;"><strong>ID de transacción:</strong> ${response.transactionId}</p>` : ''}
                        <p style="margin-bottom: 20px;">Su pago ha sido procesado exitosamente.</p>
                        <p style="color: #999; font-size: 0.9rem; margin-bottom: 20px;">Esta ventana se cerrará automáticamente en <span id="countdown">5</span> segundos</p>
                        <button onclick="window.close()" style="background: #28a745; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer;">
                            Cerrar Ventana
                        </button>
                    </div>
                </div>
            `;

            // Countdown automático
            let countdown = 5;
            const countdownInterval = setInterval(() => {
                countdown--;
                const el = document.getElementById('countdown');
                if (el) el.textContent = countdown;

                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                    window.close();
                }
            }, 1000);
        }

        function showErrorUI(message) {
            document.body.innerHTML = `
                <div style="background: #1e1e1e; color: #cccccc; padding: 40px; text-align: center; font-family: Arial; min-height: 100vh; display: flex; flex-direction: column; justify-content: center;">
                    <div style="background: #252526; padding: 40px; border-radius: 12px; max-width: 500px; margin: 0 auto;">
                        <div style="font-size: 4rem; color: #dc3545; margin-bottom: 20px;">❌</div>
                        <h1 style="color: #dc3545; margin-bottom: 20px;">Error en el Pago</h1>
                        <p style="margin-bottom: 10px;"><strong>Orden:</strong> ${orderData.orderId}</p>
                        <p style="margin-bottom: 20px;"><strong>Error:</strong> ${message}</p>
                        <p style="margin-bottom: 20px;">El pago no pudo ser procesado. Puede intentar nuevamente.</p>
                        <p style="color: #999; font-size: 0.9rem; margin-bottom: 20px;">Esta ventana se cerrará automáticamente en <span id="countdown">8</span> segundos</p>
                        <button onclick="window.close()" style="background: #dc3545; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer;">
                            Cerrar Ventana
                        </button>
                    </div>
                </div>
            `;

            // Countdown automático
            let countdown = 8;
            const countdownInterval = setInterval(() => {
                countdown--;
                const el = document.getElementById('countdown');
                if (el) el.textContent = countdown;

                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                    window.close();
                }
            }, 1000);
        }

        // EVENTOS DE VENTANA
        window.addEventListener('beforeunload', function() {
            console.log('� Ventana cerrándose');

            if (!messagesSent.payment_success && !messagesSent.payment_error) {
                notifyParentOnce('payment_closed', {
                    message: 'Ventana cerrada sin completar pago'
                });
            }
        });

        // INICIALIZACIÓN
        document.addEventListener('DOMContentLoaded', function() {
            console.log('� DOM cargado, inicializando pago...');

            // Delay pequeño para evitar problemas de timing
            setTimeout(() => {
                initializeBoldPayment();
            }, 1000);
        });

        console.log('✅ Sistema V5.0 inicializado - SIN LOOPS');
    </script>
</body>
</html>
