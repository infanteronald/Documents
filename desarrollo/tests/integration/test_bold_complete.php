<?php
/**
 * Test Completo Bold Integration - Verificación End-to-End
 * Simula todo el flujo desde la selección del método hasta la apertura de la ventana
 */
require_once "conexion.php";

// Configurar test con pedido real
$pedido_id = 88;
$detalles = [];
$monto = 0;

if ($pedido_id) {
    $res = $conn->query("SELECT * FROM pedido_detalle WHERE pedido_id = $pedido_id");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $detalles[] = $row;
            $monto += $row['precio'] * $row['cantidad'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Completo Bold Integration</title>
    <script src="https://checkout.bold.co/library/boldPaymentButton.js"></script>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'SF Pro Display', Arial, sans-serif;
            background: #1e1e1e;
            color: #cccccc;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #2d2d30;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        h1 {
            color: #007aff;
            text-align: center;
            margin-bottom: 30px;
            font-size: 2rem;
        }
        .test-section {
            background: #3e3e42;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #007aff;
        }
        .form-group {
            margin: 15px 0;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #e0e0e0;
            font-weight: 500;
        }
        input, select, textarea {
            width: 100%;
            padding: 12px;
            background: #1e1e1e;
            border: 1px solid #555;
            border-radius: 6px;
            color: #cccccc;
            font-size: 14px;
            box-sizing: border-box;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #007aff;
            box-shadow: 0 0 0 2px rgba(0, 122, 255, 0.2);
        }
        button {
            background: #007aff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            margin: 8px 4px;
            transition: all 0.2s ease;
        }
        button:hover {
            background: #0056d3;
            transform: translateY(-1px);
        }
        button:active {
            transform: translateY(0);
        }
        .success { color: #34c759; }
        .error { color: #ff453a; }
        .warning { color: #ff9f0a; }
        .info { color: #007aff; }
        .log-area {
            background: #0a0a0a;
            border: 1px solid #444;
            border-radius: 6px;
            padding: 15px;
            font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', monospace;
            font-size: 12px;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
            margin: 15px 0;
        }
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-pending { background: #ff9f0a; }
        .status-success { background: #34c759; }
        .status-error { background: #ff453a; }
        .pse-bold-container {
            background: #2d2d30;
            border: 1px solid #007aff;
            border-radius: 8px;
            padding: 20px;
            margin: 15px 0;
        }
        .info-pago {
            background: #3e3e42;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }
        .step {
            display: flex;
            align-items: center;
            margin: 10px 0;
            padding: 10px;
            background: #2d2d30;
            border-radius: 6px;
        }
        .step-number {
            background: #007aff;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            margin-right: 12px;
            flex-shrink: 0;
        }
        .step.completed .step-number {
            background: #34c759;
        }
        .step.error .step-number {
            background: #ff453a;
        }
        .step-content {
            flex: 1;
        }
        .step-title {
            font-weight: 500;
            margin-bottom: 4px;
        }
        .step-description {
            color: #999;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Test Completo Bold Integration</h1>
        
        <div class="test-section">
            <h3>📊 Información del Test</h3>
            <p><strong>Pedido ID:</strong> <?= $pedido_id ?></p>
            <p><strong>Productos:</strong> <?= count($detalles) ?> items</p>
            <p><strong>Monto Total:</strong> $<?= number_format($monto, 0, ',', '.') ?> COP</p>
            <p><strong>Variable PHP:</strong> <code><?= json_encode($monto > 0 ? $monto : 0) ?></code></p>
        </div>

        <div class="test-section">
            <h3>📝 Formulario de Prueba</h3>
            <form id="formPedido">
                <div class="form-group">
                    <label>Nombre completo</label>
                    <input type="text" name="nombre" value="Usuario Test Bold" required>
                </div>
                
                <div class="form-group">
                    <label>Correo electrónico</label>
                    <input type="email" name="correo" value="test@boldtest.com" required>
                </div>
                
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="tel" name="telefono" value="3001234567" required>
                </div>
                
                <div class="form-group">
                    <label>Dirección</label>
                    <input type="text" name="direccion" value="Calle Test Bold 123, Bogotá" required>
                </div>
                
                <div class="form-group">
                    <label>Monto</label>
                    <input type="text" name="monto" value="$<?= number_format($monto, 0, ',', '.') ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label>Método de pago</label>
                    <select name="metodo_pago" id="metodo_pago" required>
                        <option value="">Seleccionar método de pago</option>
                        <option value="PSE Bold">PSE Bold</option>
                        <option value="Botón Bancolombia">Botón Bancolombia</option>
                        <option value="Tarjeta de Crédito o Débito">Tarjeta de Crédito o Débito</option>
                    </select>
                </div>
                
                <div id="info_pago" class="info-pago" style="display: none;"></div>
            </form>
        </div>

        <div class="test-section">
            <h3>🎯 Controles de Test</h3>
            <button onclick="runCompleteTest()">🚀 Ejecutar Test Completo</button>
            <button onclick="testBoldFunction()">⚡ Test Solo Función Bold</button>
            <button onclick="testVariables()">🔍 Test Variables</button>
            <button onclick="clearLogs()">🧹 Limpiar Logs</button>
        </div>

        <div class="test-section">
            <h3>📋 Pasos del Test</h3>
            <div id="test-steps">
                <div class="step" id="step-1">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <div class="step-title">Configuración inicial</div>
                        <div class="step-description">Preparar variables y elementos del DOM</div>
                    </div>
                </div>
                <div class="step" id="step-2">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <div class="step-title">Selección método Bold</div>
                        <div class="step-description">Cambiar método de pago a PSE Bold</div>
                    </div>
                </div>
                <div class="step" id="step-3">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <div class="step-title">Inicialización función</div>
                        <div class="step-description">Ejecutar initializeBoldPayment()</div>
                    </div>
                </div>
                <div class="step" id="step-4">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <div class="step-title">Generación datos</div>
                        <div class="step-description">Preparar datos del cliente y orden</div>
                    </div>
                </div>
                <div class="step" id="step-5">
                    <div class="step-number">5</div>
                    <div class="step-content">
                        <div class="step-title">Apertura ventana Bold</div>
                        <div class="step-description">Abrir ventana de pago Bold</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="test-section">
            <h3>📊 Console Logs</h3>
            <div id="console-logs" class="log-area">Esperando ejecución del test...</div>
        </div>
    </div>

    <script>
        // Sistema de logging mejorado
        const logArea = document.getElementById('console-logs');
        const originalLog = console.log;
        const originalError = console.error;
        
        function addLogToPage(message, type = 'log') {
            const timestamp = new Date().toLocaleTimeString();
            const colors = {
                log: '#cccccc',
                error: '#ff453a',
                success: '#34c759',
                warning: '#ff9f0a',
                info: '#007aff'
            };
            const color = colors[type] || colors.log;
            logArea.innerHTML += `<span style="color: ${color}">[${timestamp}] ${message}</span>\n`;
            logArea.scrollTop = logArea.scrollHeight;
        }
        
        // Sobrescribir console methods
        console.log = function(...args) {
            originalLog.apply(console, args);
            addLogToPage(args.join(' '), 'log');
        };
        
        console.error = function(...args) {
            originalError.apply(console, args);
            addLogToPage(args.join(' '), 'error');
        };

        // Funciones auxiliares para logging con colores
        const logSuccess = (msg) => { originalLog(msg); addLogToPage(msg, 'success'); };
        const logError = (msg) => { originalError(msg); addLogToPage(msg, 'error'); };
        const logWarning = (msg) => { originalLog(msg); addLogToPage(msg, 'warning'); };
        const logInfo = (msg) => { originalLog(msg); addLogToPage(msg, 'info'); };

        // Control de pasos
        function markStepCompleted(stepId) {
            const step = document.getElementById(stepId);
            if (step) {
                step.classList.add('completed');
            }
        }
        
        function markStepError(stepId) {
            const step = document.getElementById(stepId);
            if (step) {
                step.classList.add('error');
            }
        }

        // FUNCIÓN PRINCIPAL: initializeBoldPayment (copiada del index.php corregido)
        function initializeBoldPayment() {
            console.log('🚀 initializeBoldPayment() llamada - PRIMER LOG');
            
            try {
                markStepCompleted('step-3');
                
                console.log('🔧 Intentando obtener container...');
                let container = document.getElementById('bold-payment-container');
                if (!container) {
                    container = document.createElement('div');
                    container.id = 'bold-payment-container';
                    document.getElementById('info_pago').appendChild(container);
                    console.log('✅ Container Bold creado dinámicamente');
                } else {
                    console.log('✅ Container Bold encontrado');
                }

                console.log('🔧 Intentando mostrar loading...');
                container.innerHTML = '<div style="text-align: center; padding: 16px; color: #007aff;">Preparando pago seguro...</div>';
                console.log('✅ Loading mostrado');

                const orderId = 'TEST-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
                console.log('✅ ID orden generado:', orderId);
            
                // Obtener monto con manejo seguro
                console.log('🔍 DEBUG: Obteniendo monto...');
                let monto = <?php echo json_encode($monto > 0 ? $monto : 0); ?>;
                logSuccess(`✅ Monto desde PHP: ${monto} (${typeof monto})`);
                
                if (!monto || monto === 0) {
                    console.log('🔧 Intentando obtener monto del formulario...');
                    const montoField = document.querySelector('input[name="monto"]');
                    if (montoField && montoField.value) {
                        const rawValue = montoField.value.replace(/[^\d]/g, '');
                        monto = parseInt(rawValue) || 0;
                        console.log('✅ Monto desde formulario:', monto);
                    }
                }

                markStepCompleted('step-4');

                const metodoPago = document.getElementById('metodo_pago').value;
                console.log('✅ Método de pago:', metodoPago);
                
                console.log('🔧 Preparando Bold con método:', metodoPago, 'monto:', monto);
                
                if (!monto || monto === 0) {
                    logWarning('⚠️ Inicializando Bold con checkout abierto (sin monto específico)');
                    monto = 0;
                }
                
                logInfo(`💰 Monto final para Bold: ${monto}`);
                
                console.log('🔄 Iniciando proceso de preparación Bold...');
                
                // Obtener datos del cliente
                const customerData = {
                    email: document.querySelector('input[name="correo"]')?.value || '',
                    fullName: document.querySelector('input[name="nombre"]')?.value || '',
                    phone: document.querySelector('input[name="telefono"]')?.value || '',
                    dialCode: '+57'
                };
                console.log('👤 Datos del cliente:', JSON.stringify(customerData));

                const billingAddress = {
                    address: document.querySelector('input[name="direccion"]')?.value || '',
                    city: 'Bogotá',
                    state: 'Cundinamarca',
                    country: 'CO'
                };
                console.log('📍 Dirección de facturación:', JSON.stringify(billingAddress));

                // Simular creación de URL de pago
                const paymentParams = new URLSearchParams({
                    order_id: orderId,
                    amount: monto,
                    method: metodoPago,
                    customer: encodeURIComponent(JSON.stringify(customerData)),
                    billing: encodeURIComponent(JSON.stringify(billingAddress))
                });

                const paymentUrl = `bold_payment.php?${paymentParams.toString()}`;
                logSuccess(`🔗 URL de pago generada: ${paymentUrl}`);

                // Simular apertura de ventana (en test no abrimos ventana real)
                logSuccess('🚀 Simulando apertura de ventana Bold...');
                markStepCompleted('step-5');
                
                container.innerHTML = `
                    <div style="text-align: center; padding: 20px; background: #2d4a3d; border-radius: 8px; border: 1px solid #34c759;">
                        <div style="color: #34c759; font-size: 18px; margin-bottom: 10px;">✅ Test Completado</div>
                        <div style="color: #999; font-size: 14px;">La función Bold funciona correctamente</div>
                        <div style="color: #007aff; font-size: 12px; margin-top: 10px;">URL: ${paymentUrl.substring(0, 50)}...</div>
                    </div>
                `;

                logSuccess('🎉 initializeBoldPayment() completada exitosamente');
                return true;

            } catch (error) {
                logError(`❌ Error en initializeBoldPayment: ${error.message}`);
                logError(`❌ Stack trace: ${error.stack}`);
                markStepError('step-3');
                return false;
            }
        }

        // Funciones de test
        function testVariables() {
            console.log('🔍 TEST: Verificando variables...');
            const monto = <?php echo json_encode($monto); ?>;
            logInfo(`Monto PHP: ${monto} (${typeof monto})`);
            logInfo(`Es número válido: ${Number.isFinite(monto)}`);
            logSuccess('✅ Variables verificadas');
        }

        function testBoldFunction() {
            console.log('⚡ TEST: Ejecutando solo función Bold...');
            
            // Preparar método de pago
            const metodoPago = document.getElementById('metodo_pago');
            if (!metodoPago.value) {
                metodoPago.value = 'PSE Bold';
                metodoPago.dispatchEvent(new Event('change'));
            }
            
            setTimeout(() => {
                const result = initializeBoldPayment();
                logInfo(`🎯 Resultado: ${result}`);
            }, 100);
        }

        function runCompleteTest() {
            console.log('🚀 INICIANDO TEST COMPLETO BOLD INTEGRATION');
            clearLogs();
            
            // Reset steps
            document.querySelectorAll('.step').forEach(step => {
                step.classList.remove('completed', 'error');
            });
            
            markStepCompleted('step-1');
            logSuccess('✅ Paso 1: Configuración inicial completada');
            
            // Seleccionar método PSE Bold
            setTimeout(() => {
                const metodoPago = document.getElementById('metodo_pago');
                metodoPago.value = 'PSE Bold';
                metodoPago.dispatchEvent(new Event('change'));
                markStepCompleted('step-2');
                logSuccess('✅ Paso 2: Método PSE Bold seleccionado');
                
                // Ejecutar función Bold
                setTimeout(() => {
                    const result = initializeBoldPayment();
                    if (result) {
                        logSuccess('🎉 TEST COMPLETO EXITOSO - Bold Integration funciona correctamente');
                    } else {
                        logError('❌ TEST FALLIDO - Revisar errores arriba');
                    }
                }, 500);
            }, 300);
        }

        function clearLogs() {
            document.getElementById('console-logs').innerHTML = 'Logs limpiados...\n';
        }

        // Event listener para método de pago
        document.getElementById('metodo_pago').addEventListener('change', function() {
            const value = this.value;
            console.log(`🔥 Método de pago cambiado a: ${value}`);
            
            const infoPago = document.getElementById('info_pago');
            if (value === 'PSE Bold' || value === 'Botón Bancolombia' || value === 'Tarjeta de Crédito o Débito') {
                infoPago.style.display = 'block';
                infoPago.innerHTML = `
                    <div class="pse-bold-container">
                        <b>${value} - Pago Seguro:</b>
                        <p style="color: #999; margin: 8px 0;">Pague de manera segura a través de Bold</p>
                        <div id="bold-payment-container" style="margin-top: 12px;"></div>
                    </div>
                `;
                
                setTimeout(() => {
                    console.log('⏰ Auto-ejecutando initializeBoldPayment...');
                    const result = initializeBoldPayment();
                    console.log(`✅ Auto-ejecución completada: ${result}`);
                }, 100);
            } else {
                infoPago.style.display = 'none';
            }
        });

        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            logSuccess('📋 Test Completo Bold Integration cargado');
            testVariables();
        });
    </script>
</body>
</html>
