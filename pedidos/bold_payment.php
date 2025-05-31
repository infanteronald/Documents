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
            --vscode-text-muted: #999999;
            --vscode-text-light: #ffffff;
            --apple-blue: #007aff;
            --apple-blue-hover: #0056d3;
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
            color: var(--vscode-text-muted);
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
            background: var(--gray-light);
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            color: var(--vscode-text-muted);
        }

        .success-message {
            color: var(--apple-blue);
            background: var(--gray-dark);
            padding: var(--space-md);
            border-radius: var(--radius-sm);
            margin: var(--space-md) 0;
        }

        .error-message {
            color: #ff6b6b;
            background: var(--gray-dark);
            padding: var(--space-md);
            border-radius: var(--radius-sm);
            margin: var(--space-md) 0;
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <img src="logo.png" class="logo" alt="Sequoia Speed">
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
        // Variables globales
        const orderData = {
            orderId: '<?= htmlspecialchars($order_id) ?>',
            amount: <?= intval($amount) ?>,
            method: '<?= htmlspecialchars($method) ?>',
            customer: <?= json_encode($customer) ?>,
            billing: <?= json_encode($billing) ?>
        };

        // Función para comunicar con la ventana padre
        function notifyParentWindow(status, data = {}) {
            try {
                if (window.opener && !window.opener.closed) {
                    window.opener.postMessage({
                        type: 'bold_payment_update',
                        status: status,
                        orderId: orderData.orderId,
                        data: data
                    }, '*');
                    console.log('Mensaje enviado a ventana padre:', status, data);
                }
            } catch (error) {
                console.error('Error al comunicar con ventana padre:', error);
            }
        }

        // Función para mostrar mensajes en la UI
        function showMessage(message, type = 'info') {
            const container = document.getElementById('bold-payment-container');
            let className = type === 'error' ? 'error-message' : 
                           type === 'success' ? 'success-message' : 'loading';
            
            container.innerHTML = `<div class="${className}">${message}</div>`;
        }

        // Función para cerrar la ventana después de un delay
        function closeWindowWithDelay(delay = 3000) {
            setTimeout(() => {
                console.log('Cerrando ventana de pago...');
                window.close();
            }, delay);
        }

        // Inicializar pago Bold al cargar la página
        async function initializeBoldPayment() {
            try {
                console.log('Inicializando Bold con datos:', orderData);
                
                let boldConfig = {
                    'data-bold-button': 'dark-L',
                    'data-description': `Pago ${orderData.method} Sequoia Speed - Pedido #${orderData.orderId}`,
                    'data-order-id': orderData.orderId,
                    'data-currency': 'COP',
                    'data-render-mode': 'embedded'
                };

                // Si hay monto definido, generar hash de integridad
                if (orderData.amount > 0) {
                    showMessage('Generando hash de seguridad...');
                    
                    const hashResponse = await fetch('bold_hash.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            order_id: orderData.orderId,
                            amount: orderData.amount,
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

                    boldConfig['data-api-key'] = hashData.data.api_key;
                    boldConfig['data-amount'] = hashData.data.amount.toString();
                    boldConfig['data-integrity-signature'] = hashData.data.integrity_signature;
                } else {
                    boldConfig['data-api-key'] = '0yRP5iNsgcqoOGTaNLrzKNBLHbAaEOxhJPmLJpMevCg';
                }

                // Agregar datos del cliente si están disponibles
                if (orderData.customer && (orderData.customer.email || orderData.customer.fullName)) {
                    boldConfig['data-customer-data'] = JSON.stringify(orderData.customer);
                }

                if (orderData.billing && orderData.billing.address) {
                    boldConfig['data-billing-address'] = JSON.stringify(orderData.billing);
                }

                // Notificar a la ventana padre que el pago está iniciando
                notifyParentWindow('payment_started', { orderId: orderData.orderId });

                // Crear el botón Bold
                createBoldButton(boldConfig);

            } catch (error) {
                console.error('Error al inicializar Bold:', error);
                showMessage('Error al cargar el checkout: ' + error.message, 'error');
                notifyParentWindow('payment_error', { error: error.message });
                closeWindowWithDelay(5000);
            }
        }

        // Función para crear el botón Bold
        function createBoldButton(config) {
            const container = document.getElementById('bold-payment-container');
            
            showMessage('Cargando checkout seguro...');
            
            setTimeout(() => {
                container.innerHTML = '';
                
                const boldScript = document.createElement('script');
                boldScript.src = 'https://checkout.bold.co/library/boldPaymentButton.js';
                
                Object.keys(config).forEach(key => {
                    boldScript.setAttribute(key, config[key]);
                });

                boldScript.onload = function() {
                    console.log('✅ Script Bold cargado exitosamente');
                    
                    // Verificar si el botón se creó correctamente
                    setTimeout(() => {
                        const boldButton = container.querySelector('[data-bold-button]');
                        if (!boldButton) {
                            console.warn('Botón Bold no se creó, creando alternativo...');
                            createAlternativeButton(container, config);
                        } else {
                            console.log('Botón Bold creado exitosamente');
                        }
                    }, 1000);
                };
                
                boldScript.onerror = function() {
                    console.error('❌ Error al cargar script Bold');
                    showMessage('Error al cargar el script de Bold', 'error');
                    notifyParentWindow('payment_error', { error: 'Error al cargar script Bold' });
                    closeWindowWithDelay(5000);
                };

                container.appendChild(boldScript);
                
            }, 500);
        }

        // Método alternativo para crear el botón
        function createAlternativeButton(container, config) {
            container.innerHTML = '';
            
            const buttonDiv = document.createElement('div');
            buttonDiv.style.textAlign = 'center';
            buttonDiv.style.padding = '16px';
            
            const script = document.createElement('script');
            script.src = 'https://checkout.bold.co/library/boldPaymentButton.js';
            
            Object.entries(config).forEach(([key, value]) => {
                script.setAttribute(key, value);
            });
            
            buttonDiv.appendChild(script);
            container.appendChild(buttonDiv);
        }

        // Interceptar eventos de pago
        window.addEventListener('message', function(event) {
            // Escuchar eventos de Bold (si los hay)
            if (event.data && event.data.type === 'bold_payment') {
                console.log('Evento Bold recibido:', event.data);
                
                if (event.data.status === 'success') {
                    showMessage('¡Pago completado exitosamente! Cerrando ventana...', 'success');
                    notifyParentWindow('payment_success', event.data);
                    closeWindowWithDelay(2000);
                } else if (event.data.status === 'error') {
                    showMessage('Error en el pago: ' + (event.data.message || 'Error desconocido'), 'error');
                    notifyParentWindow('payment_error', event.data);
                    closeWindowWithDelay(5000);
                }
            }
        });

        // Detectar cuando la ventana está a punto de cerrarse
        window.addEventListener('beforeunload', function() {
            notifyParentWindow('payment_closed', { orderId: orderData.orderId });
        });

        // Detectar cambios en el DOM para monitorear el estado del pago
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) {
                        // Buscar elementos que indiquen éxito o error en el pago
                        const successElements = node.querySelectorAll ? 
                            node.querySelectorAll('[class*="success"], [class*="completed"], [id*="success"]') : [];
                        const errorElements = node.querySelectorAll ? 
                            node.querySelectorAll('[class*="error"], [class*="failed"], [id*="error"]') : [];
                        
                        if (successElements.length > 0) {
                            console.log('Elementos de éxito detectados en DOM');
                            showMessage('¡Pago completado! Cerrando ventana...', 'success');
                            notifyParentWindow('payment_success', { orderId: orderData.orderId });
                            closeWindowWithDelay(2000);
                        } else if (errorElements.length > 0) {
                            console.log('Elementos de error detectados en DOM');
                        }
                    }
                });
            });
        });

        // Observar cambios en el DOM
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        // Inicializar el pago cuando la página esté lista
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Página de pago cargada, inicializando Bold...');
            initializeBoldPayment();
        });
    </script>
</body>
</html>
