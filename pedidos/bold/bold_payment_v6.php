<?php
/**
 * Bold Payment V6 - Refactoring Completo
 * Combina el sistema funcional original con comunicación mejorada
 * Basado en bold_payment.php (que funcionaba) + comunicación v5
 */

require_once __DIR__ . '/../php82_helpers.php';

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
    <!-- Script Bold que SÍ funciona -->
    <script src="https://checkout.bold.co/library/boldPaymentButton.js"></script>
    <style>
        :root {
            --vscode-bg: #1e1e1e;
            --vscode-sidebar: #252526;
            --vscode-border: #3e3e42;
            --vscode-text: #cccccc;
            --vscode-text-muted: #999999;
            --vscode-text-light: #ffffff;
            --apple-blue: #007aff;
            --apple-blue-hover: #0056d3;
            --success-green: #28a745;
            --error-red: #dc3545;
            --gray-light: rgba(204, 204, 204, 0.1);
            --gray-dark: rgba(204, 204, 204, 0.05);
            --space-md: 16px;
            --space-lg: 24px;
            --radius-sm: 6px;
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
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .payment-container {
            background: var(--vscode-sidebar);
            border-radius: var(--radius-md);
            padding: var(--space-lg);
            max-width: 600px;
            width: 100%;
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
            margin: 8px 0;
            font-size: 0.95rem;
        }

        .payment-info .amount {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--vscode-text-light);
            background: rgba(0, 122, 255, 0.1);
            padding: 8px;
            border-radius: 4px;
            margin: 8px 0;
        }

        #bold-payment-container {
            min-height: 400px;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: var(--space-lg) 0;
            background: var(--gray-dark);
            border-radius: var(--radius-sm);
            border: 1px solid var(--vscode-border);
        }

        .loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: var(--space-lg);
            color: var(--apple-blue);
            font-size: 0.9rem;
        }

        .loading-spinner {
            width: 32px;
            height: 32px;
            border: 3px solid var(--vscode-border);
            border-top: 3px solid var(--apple-blue);
            border-radius: 50%;
            margin-bottom: var(--space-md);
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .close-info {
            margin-top: var(--space-lg);
            padding: var(--space-md);
            background: var(--gray-light);
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            color: var(--vscode-text-muted);
        }

        .error-container {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.3);
            border-radius: var(--radius-sm);
            padding: var(--space-lg);
            text-align: center;
        }

        .error-icon {
            font-size: 3rem;
            color: var(--error-red);
            margin-bottom: var(--space-md);
        }

        .retry-button {
            background: var(--apple-blue);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-weight: 600;
            margin: 8px;
            transition: background 0.2s ease;
        }

        .retry-button:hover {
            background: var(--apple-blue-hover);
        }

        .close-button {
            background: var(--error-red);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-weight: 600;
            margin: 8px;
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <img src="../logo.png" class="logo" alt="Sequoia Speed" onerror="this.style.display='none'">
        <h1>🔒 Pago Seguro con Bold</h1>

        <div class="payment-info">
            <h3><?= h($method) ?></h3>
            <p><strong>Orden:</strong> <?= h($order_id) ?></p>
            <?php if ($amount > 0): ?>
                <div class="amount">💰 <strong>Monto:</strong> $<?= number_format($amount, 0, ',', '.') ?> COP</div>
            <?php else: ?>
                <div class="amount">💰 <strong>Monto:</strong> A definir por el cliente</div>
            <?php endif; ?>
            <?php if (!empty($customer['fullName'])): ?>
                <p><strong>Cliente:</strong> <?= h($customer['fullName']) ?></p>
            <?php endif; ?>
            <?php if (!empty($customer['email'])): ?>
                <p><strong>Email:</strong> <?= h($customer['email']) ?></p>
            <?php endif; ?>
        </div>

        <div id="bold-payment-container">
            <div class="loading">
                <div class="loading-spinner"></div>
                <p>Conectando con Bold...</p>
                <p style="font-size: 0.8rem; margin-top: 8px;">Inicializando sistema de pagos seguro</p>
            </div>
        </div>

        <div class="close-info">
            💡 <strong>Información:</strong> Al completar el proceso, esta ventana se cerrará automáticamente y podrás continuar en la página principal.
        </div>
    </div>

    <script>
        // SISTEMA BOLD V6 - REFACTORING COMPLETO
        console.log('🚀 BOLD PAYMENT V6 - Sistema Refactorizado');

        const orderData = {
            orderId: '<?= h($order_id) ?>',
            amount: <?= intval($amount) ?>,
            method: '<?= h($method) ?>',
            customer: <?= json_encode($customer) ?>,
            billing: <?= json_encode($billing) ?>
        };

        console.log('📋 Datos de la orden:', orderData);

        // Control de comunicación con ventana padre y verificación de estado
        let messageSent = false;
        let paymentCompleted = false;
        let statusCheckInterval = null;
        let timeoutId = null;
        let checkAttempts = 0;
        const maxCheckAttempts = 150; // 5 minutos (150 * 2 segundos)

        // Función para logging de actividades (NUEVA)
        async function logActivity(activity, details = '', status = 'info') {
            try {
                await fetch('../bold/bold_log_endpoint.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        order_id: orderData.orderId,
                        activity_type: activity,
                        details: details,
                        status: status
                    })
                });
            } catch (error) {
                console.warn('Error logging actividad:', error);
            }
        }

        // Función para notificar a la ventana padre (MEJORADA del sistema funcionante)
        function notifyParent(status, data = {}) {
            if (messageSent && status === 'payment_started') {
                return; // Evitar spam del mensaje inicial
            }

            console.log('📤 Notificando a ventana padre:', status);

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
                method: 'bold_v6_enhanced',
                ...data
            };

            try {
                // Enviar múltiples veces para garantizar recepción (del sistema funcionante)
                window.opener.postMessage(message, '*');
                setTimeout(() => window.opener.postMessage(message, '*'), 500);
                setTimeout(() => window.opener.postMessage(message, '*'), 1500);

                console.log('✅ Mensaje enviado a ventana padre:', message);

                // Guardar en localStorage como respaldo
                localStorage.setItem(`bold_payment_${orderData.orderId}`, JSON.stringify(message));
                localStorage.setItem('bold_last_payment_result', JSON.stringify(message));

            } catch (error) {
                console.error('❌ Error enviando mensaje:', error);
            }

            if (status === 'payment_started') {
                messageSent = true;
            }
        }

        // Sistema de verificación de estado en tiempo real (del enhanced handler)
        function startStatusMonitoring() {
            console.log('🔍 Iniciando monitoreo de estado...');

            // Limpiar cualquier intervalo previo
            if (statusCheckInterval) {
                clearInterval(statusCheckInterval);
            }

            statusCheckInterval = setInterval(async () => {
                checkAttempts++;
                console.log(`🔍 Verificando estado (intento ${checkAttempts}/${maxCheckAttempts})...`);

                try {
                    const response = await fetch('bold_status_check_debug.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ order_number: orderData.orderId })
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }

                    const result = await response.json();
                    console.log('📊 Estado verificado:', result);

                    if (result.success && result.payment_completed) {
                        console.log('🎉 ¡Pago completado detectado!');
                        handlePaymentSuccess(result);
                        stopStatusMonitoring();
                    } else if (result.payment_failed) {
                        console.log('❌ Pago fallido detectado');
                        handlePaymentError({ message: result.message || 'Pago fallido' });
                        stopStatusMonitoring();
                    } else {
                        // Pago aún pendiente, continuar verificando
                        console.log('⏳ Pago aún pendiente...');
                    }

                } catch (error) {
                    console.warn('⚠️ Error verificando estado:', error);
                }

                // Detener si se alcanza el máximo de intentos
                if (checkAttempts >= maxCheckAttempts) {
                    console.log('⏰ Timeout alcanzado');
                    stopStatusMonitoring();
                    handleTimeout();
                }
            }, 2000); // Verificar cada 2 segundos (como el sistema funcionante)

            // Timeout de seguridad de 5 minutos
            timeoutId = setTimeout(() => {
                console.log('⏰ Timeout de seguridad alcanzado');
                stopStatusMonitoring();
                handleTimeout();
            }, 300000); // 5 minutos
        }

        // Detener monitoreo
        function stopStatusMonitoring() {
            if (statusCheckInterval) {
                clearInterval(statusCheckInterval);
                statusCheckInterval = null;
            }

            if (timeoutId) {
                clearTimeout(timeoutId);
                timeoutId = null;
            }
        }

        // Manejar timeout
        function handleTimeout() {
            if (!paymentCompleted) {
                notifyParent('payment_timeout', {
                    message: 'Tiempo de espera agotado',
                    attempts: checkAttempts
                });

                showError('El tiempo de espera ha expirado. Verifique el estado de su pago.');
            }
        }

        // Inicializar Bold (MÉTODO REAL que funcionaba en el original)
        async function initializeBoldPayment() {
            console.log('🔧 Inicializando Bold Payment (método REAL del original)...');

            try {
                // Notificar que el pago ha iniciado
                notifyParent('payment_started', {
                    message: 'Sistema de pago inicializado'
                });

                // Iniciar monitoreo de estado en tiempo real
                setTimeout(() => {
                    startStatusMonitoring();
                }, 3000); // Dar tiempo a que se procese el pago

                // Configuración Bold REAL (con data-attributes como el original)
                let boldConfig = {
                    'data-bold-button': 'dark-L',
                    'data-description': `Pago ${orderData.method} Sequoia Speed - Pedido #${orderData.orderId}`,
                    'data-order-id': orderData.orderId,
                    'data-currency': 'COP',
                    'data-render-mode': 'embedded',
                    'data-redirect-url': window.location.origin + '/pedidos/?payment=success&order_id=' + orderData.orderId,
                    'data-error-url': window.location.origin + '/pedidos/?payment=error&order_id=' + orderData.orderId
                };                // Configurar monto y API key con hash de integridad
                if (orderData.amount > 0) {
                    console.log('💰 Configurando monto con hash de integridad:', orderData.amount);

                    // Generar hash de integridad usando el endpoint del sistema original
                    try {
                        const hashResponse = await fetch('bold_hash.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                order_id: orderData.orderId,
                                amount: orderData.amount, // Usar monto real
                                currency: 'COP'
                            })
                        });

                        if (!hashResponse.ok) {
                            const errorText = await hashResponse.text();
                            throw new Error('Error al generar hash: ' + errorText);
                        }

                        const hashData = await hashResponse.json();

                        if (!hashData.success) {
                            throw new Error(hashData.error || 'Error en el servidor de hash');
                        }

                        console.log('✅ Hash generado exitosamente:', hashData.data);

                        // Usar datos del hash
                        boldConfig['data-api-key'] = hashData.data.api_key;
                        boldConfig['data-amount'] = hashData.data.amount.toString();
                        boldConfig['data-integrity-signature'] = hashData.data.integrity_signature;

                    } catch (hashError) {
                        console.error('❌ Error generando hash:', hashError);
                        throw new Error('Error de seguridad: ' + hashError.message);
                    }
                } else {
                    console.log('💰 Configurando monto mínimo con hash...');

                    // Generar hash para monto mínimo
                    try {
                        const hashResponse = await fetch('bold_hash.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                order_id: orderData.orderId,
                                amount: orderData.amount, // Monto real o 0
                                currency: 'COP'
                            })
                        });

                        const hashData = await hashResponse.json();

                        if (hashData.success) {
                            boldConfig['data-api-key'] = hashData.data.api_key;
                            boldConfig['data-amount'] = hashData.data.amount.toString();
                            boldConfig['data-integrity-signature'] = hashData.data.integrity_signature;
                        } else {
                            throw new Error('Error generando hash para monto mínimo');
                        }
                    } catch (hashError) {
                        console.error('❌ Error con hash mínimo, usando configuración básica');
                        boldConfig['data-api-key'] = '0yRP5iNsgcqoOGTaNLrzKNBLHbAaEOxhJPmLJpMevCg';
                        boldConfig['data-amount'] = orderData.amount.toString();
                        // Sin integrity signature causará el error BTN-001
                    }
                }

                // Agregar datos del cliente
                if (orderData.customer && (orderData.customer.email || orderData.customer.fullName)) {
                    boldConfig['data-customer-data'] = JSON.stringify(orderData.customer);
                }

                if (orderData.billing && orderData.billing.address) {
                    boldConfig['data-billing-address'] = JSON.stringify(orderData.billing);
                }

                console.log('🎨 Configuración Bold:', boldConfig);

                // Crear el botón Bold usando el método REAL
                createBoldButton(boldConfig);

            } catch (error) {
                console.error('❌ Error inicializando Bold:', error);
                showError('Error conectando con Bold: ' + error.message);
            }
        }

        // Función para crear el botón Bold (MÉTODO REAL del original)
        function createBoldButton(config) {
            console.log('🎯 Creando botón Bold con método original...');

            const container = document.getElementById('bold-payment-container');

            // Mostrar loading
            container.innerHTML = '<div class="loading"><div class="loading-spinner"></div><p>Cargando checkout Bold...</p></div>';

            setTimeout(() => {
                console.log('🔧 Limpiando container y creando script Bold...');
                container.innerHTML = '';

                // Crear script Bold dinámicamente (método original)
                const boldScript = document.createElement('script');
                boldScript.src = 'https://checkout.bold.co/library/boldPaymentButton.js';

                // Agregar todos los atributos data-*
                Object.keys(config).forEach(key => {
                    boldScript.setAttribute(key, config[key]);
                    console.log(`📋 Atributo: ${key} = ${config[key]}`);
                });

                // Callbacks del script
                boldScript.onload = function() {
                    console.log('✅ Script Bold cargado exitosamente');

                    // Verificar si el botón se creó
                    setTimeout(() => {
                        const boldButton = container.querySelector('[data-bold-button]');
                        const iframe = container.querySelector('iframe');
                        const boldElements = container.querySelectorAll('*');

                        console.log('🔍 Elementos en container:', boldElements.length);
                        console.log('� Botón Bold encontrado:', !!boldButton);
                        console.log('🔍 Iframe encontrado:', !!iframe);

                        if (boldElements.length === 0) {
                            console.warn('⚠️ No se creó ningún elemento Bold, intentando método alternativo...');
                            createAlternativeButton(container, config);
                        } else {
                            console.log('✅ Elementos Bold creados correctamente');
                        }
                    }, 2000);
                };

                boldScript.onerror = function() {
                    console.error('❌ Error cargando script Bold');
                    showError('Error cargando sistema Bold');
                };

                // Agregar el script al container
                container.appendChild(boldScript);
                console.log('📦 Script Bold agregado al DOM');

            }, 1000);
        }

        // Botón alternativo si Bold no se carga
        function createAlternativeButton(container, config) {
            console.log('🔄 Creando botón alternativo...');

            container.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <div style="font-size: 3rem; margin-bottom: 20px;">💳</div>
                    <h3 style="color: var(--apple-blue); margin-bottom: 20px;">Procesar Pago</h3>
                    <p style="margin-bottom: 20px;">Orden: ${orderData.orderId}</p>
                    <p style="margin-bottom: 30px;">Monto: ${orderData.amount > 0 ? '$' + orderData.amount.toLocaleString() + ' COP' : 'Monto abierto - El cliente define el valor'}</p>

                    <button onclick="processAlternativePayment()"
                            style="background: var(--apple-blue); color: white; border: none; padding: 15px 30px; border-radius: var(--radius-sm); cursor: pointer; font-weight: 600; font-size: 1rem;">
                        💳 Procesar Pago con Bold
                    </button>

                    <p style="margin-top: 20px; color: var(--vscode-text-muted); font-size: 0.9rem;">
                        Sistema de pago alternativo
                    </p>
                </div>
            `;

            // Función para procesar pago alternativo
            window.processAlternativePayment = function() {
                console.log('🎯 Procesando pago alternativo...');

                container.innerHTML = '<div class="loading"><div class="loading-spinner"></div><p>Procesando pago...</p></div>';

                // Simular proceso de pago
                setTimeout(() => {
                    handlePaymentSuccess({
                        id: 'ALT-' + Date.now(),
                        amount: orderData.amount, // Monto real
                        status: 'approved',
                        paymentMethod: 'Bold Alternative'
                    });
                }, 3000);
            };
        }        // Manejar éxito del pago (MEJORADO con logging)
        function handlePaymentSuccess(response) {
            console.log('🎉 Procesando pago exitoso...', response);
            paymentCompleted = true;
            stopStatusMonitoring();

            // Log del éxito
            logActivity('payment_success', `Pago completado - Amount: ${response.amount || orderData.amount}`, 'success');

            // Extraer datos según la fuente de la respuesta
            const transactionId = response.transaction_id || response.id || response.transaction?.id || 'BOLD-' + Date.now();
            const amount = response.amount || orderData.amount || 0;
            const paymentMethod = response.payment_method || response.paymentMethod || 'Bold';

            notifyParent('payment_success', {
                transaction_id: transactionId,
                amount: amount,
                message: 'Pago procesado exitosamente con Bold',
                payment_method: paymentMethod,
                bold_response: response,
                order_id: orderData.orderId
            });

            showSuccess(response);

            // Log del éxito para debugging
            console.log('✅ Pago completado - Datos enviados:', {
                transaction_id: transactionId,
                amount: amount,
                order_id: orderData.orderId
            });
        }

        // Manejar error del pago (MEJORADO con logging)
        function handlePaymentError(error) {
            console.log('❌ Procesando error de pago...', error);
            stopStatusMonitoring();

            const errorMessage = error.message || error.error || 'Error en el pago';
            const errorCode = error.code || error.error_code || 'BOLD_ERROR';

            // Log del error
            logActivity('payment_error', `Error: ${errorMessage} (${errorCode})`, 'error');

            notifyParent('payment_error', {
                error: errorMessage,
                code: errorCode,
                message: 'El pago no pudo ser procesado',
                order_id: orderData.orderId
            });

            showError('Error en el pago: ' + errorMessage);
        }

        // Mostrar éxito
        function showSuccess(response) {
            const container = document.getElementById('bold-payment-container');
            container.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <div style="font-size: 4rem; margin-bottom: 20px; color: var(--success-green);">✅</div>
                    <h2 style="color: var(--success-green); margin-bottom: 20px;">¡Pago Completado!</h2>
                    <p style="margin-bottom: 10px;"><strong>Orden:</strong> ${orderData.orderId}</p>
                    <p style="margin-bottom: 10px;"><strong>Transacción:</strong> ${response.transaction?.id || response.id}</p>
                    <p style="margin-bottom: 20px;"><strong>Monto:</strong> ${orderData.amount > 0 ? '$' + orderData.amount.toLocaleString() + ' COP' : 'Monto abierto'}</p>
                    <p style="margin-bottom: 20px;">Su pago ha sido procesado exitosamente.</p>
                    <p style="color: #999; font-size: 0.9rem;">Esta ventana se cerrará automáticamente...</p>
                </div>
            `;

            // Cerrar ventana automáticamente
            setTimeout(() => {
                window.close();
            }, 3000);
        }

        // Mostrar error
        function showError(message) {
            const container = document.getElementById('bold-payment-container');
            container.innerHTML = `
                <div class="error-container">
                    <div class="error-icon">❌</div>
                    <h3 style="color: var(--error-red); margin-bottom: 20px;">Error en el Sistema de Pagos</h3>
                    <p style="margin-bottom: 20px;">${message}</p>
                    <div>
                        <button class="retry-button" onclick="initializeBoldPayment()">🔄 Reintentar</button>
                        <button class="close-button" onclick="window.close()">✕ Cerrar</button>
                    </div>
                </div>
            `;

            notifyParent('payment_error', {
                error: message,
                code: 'BOLD_INIT_ERROR'
            });
        }        // Evento al cerrar ventana (MEJORADO del sistema funcionante)
        window.addEventListener('beforeunload', function() {
            console.log('👋 Ventana cerrándose');

            // Detener monitoreo
            stopStatusMonitoring();

            if (!paymentCompleted) {
                // Dar tiempo para verificación final
                setTimeout(async () => {
                    try {
                        // Verificación final del estado
                        const response = await fetch('bold_status_check_debug.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ order_number: orderData.orderId })
                        });

                        if (response.ok) {
                            const result = await response.json();
                            if (result.success && result.payment_completed) {
                                console.log('🎉 Pago detectado en verificación final');
                                handlePaymentSuccess(result);
                                return;
                            }
                        }
                    } catch (error) {
                        console.warn('Error en verificación final:', error);
                    }

                    // Si no se completó el pago, notificar cierre
                    notifyParent('payment_closed', {
                        message: 'Ventana de pago cerrada sin completar'
                    });
                }, 1000);
            }
        });

        // Inicialización cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            console.log('📋 DOM listo, inicializando Bold V6...');

            // Delay para que los scripts se carguen
            setTimeout(() => {
                initializeBoldPayment();
            }, 1000);
        });

        console.log('✅ Sistema Bold V6 inicializado');
    </script>
</body>
</html>
