<?php
// Test final de la función Bold corregida
require_once "conexion.php";

echo "<h1>🔧 Test Final Bold Integration</h1>";

// Simular pedido con monto
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

echo "<p><strong>Pedido ID:</strong> $pedido_id</p>";
echo "<p><strong>Monto calculado:</strong> $" . number_format($monto, 0, ',', '.') . "</p>";
echo "<p><strong>Variable para JavaScript:</strong> " . json_encode($monto > 0 ? $monto : 0) . "</p>";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Test Final Bold</title>
    <script src="https://checkout.bold.co/library/boldPaymentButton.js"></script>
    <style>
        body { font-family: Arial, sans-serif; background: #1e1e1e; color: #fff; padding: 20px; }
        .test-container { max-width: 600px; margin: 0 auto; }
        button { background: #007aff; color: white; border: none; padding: 12px 20px; border-radius: 6px; cursor: pointer; margin: 10px; }
        .info-pago { background: #2d2d30; padding: 15px; border-radius: 8px; margin: 15px 0; }
        .pse-bold-container { background: #333; border: 1px solid #007aff; border-radius: 8px; padding: 15px; }
        select, input { background: #2d2d30; color: #fff; border: 1px solid #444; padding: 8px; border-radius: 4px; margin: 5px; width: 200px; }
        .log-area { background: #0e0e0e; padding: 15px; border-radius: 6px; font-family: monospace; font-size: 12px; max-height: 400px; overflow-y: auto; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="test-container">
        <h2>📋 Formulario de Test</h2>
        
        <!-- Campos del formulario simulados -->
        <input type="text" name="correo" value="test@example.com" placeholder="Email">
        <input type="text" name="nombre" value="Usuario Test" placeholder="Nombre">
        <input type="text" name="telefono" value="3001234567" placeholder="Teléfono">
        <input type="text" name="direccion" value="Calle Test 123" placeholder="Dirección">
        <input type="text" name="monto" value="$<?= number_format($monto, 0, ',', '.') ?>" readonly>
        
        <br><br>
        
        <select id="metodo_pago">
            <option value="">Seleccionar método</option>
            <option value="PSE Bold">PSE Bold</option>
            <option value="Botón Bancolombia">Botón Bancolombia</option>
            <option value="Tarjeta de Crédito o Débito">Tarjeta de Crédito o Débito</option>
        </select>
        
        <button onclick="testFunction()">🧪 Test initializeBoldPayment()</button>
        <button onclick="clearLogs()">🧹 Limpiar Logs</button>
        
        <div id="info_pago" class="info-pago"></div>
        
        <h3>📊 Console Logs:</h3>
        <div id="console-logs" class="log-area">Los logs aparecerán aquí...</div>
    </div>

    <script>
        // Capturar console.log para mostrar en la página
        const originalLog = console.log;
        const originalError = console.error;
        const logArea = document.getElementById('console-logs');
        
        function addLogToPage(message, type = 'log') {
            const timestamp = new Date().toLocaleTimeString();
            const color = type === 'error' ? '#ff6b6b' : '#ffffff';
            logArea.innerHTML += `<span style="color: ${color}">[${timestamp}] ${message}</span>\n`;
            logArea.scrollTop = logArea.scrollHeight;
        }
        
        console.log = function(...args) {
            originalLog.apply(console, args);
            addLogToPage(args.join(' '), 'log');
        };
        
        console.error = function(...args) {
            originalError.apply(console, args);
            addLogToPage(args.join(' '), 'error');
        };

        // Función initializeBoldPayment copiada del index.php corregido
        function initializeBoldPayment() {
            console.log('🚀 initializeBoldPayment() llamada - PRIMER LOG');
            
            // Capturar errores globales durante la ejecución
            window.addEventListener('error', function(e) {
                console.error('❌ ERROR GLOBAL durante initializeBoldPayment:', e.message, 'en', e.filename, 'línea', e.lineno);
            });
            
            try {
                console.log('🔧 Intentando obtener container...');
                let container = document.getElementById('bold-payment-container');
                if (!container) {
                    // Crear el container si no existe
                    container = document.createElement('div');
                    container.id = 'bold-payment-container';
                    document.getElementById('info_pago').appendChild(container);
                    console.log('✅ Container Bold creado dinámicamente');
                } else {
                    console.log('✅ Container Bold encontrado:', container);
                }

                // Mostrar información del pago
                console.log('🔧 Intentando mostrar loading...');
                container.innerHTML = '<div style="text-align: center; padding: 16px; color: #007aff;">Preparando pago seguro...</div>';
                console.log('✅ Loading mostrado');

                // Generar ID único para la orden
                const orderId = 'SEQ-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
                console.log('✅ ID orden generado:', orderId);
            
                // Obtener el monto del pedido con manejo seguro
                console.log('🔍 DEBUG: Obteniendo monto...');
                let monto = <?php echo json_encode($monto > 0 ? $monto : 0); ?>;
                console.log('✅ Monto desde PHP:', monto, 'Tipo:', typeof monto);
                
                // Si no hay monto del PHP, intentar obtenerlo del campo del formulario
                if (!monto || monto === 0) {
                    console.log('🔧 Intentando obtener monto del formulario...');
                    const montoField = document.querySelector('input[name="monto"]');
                    if (montoField && montoField.value) {
                        // Remover formato de moneda y convertir a número
                        const rawValue = montoField.value.replace(/[^\d]/g, '');
                        monto = parseInt(rawValue) || 0;
                        console.log('✅ Monto desde formulario:', monto);
                    }
                }

                // Obtener el método de pago seleccionado
                const metodoPago = document.getElementById('metodo_pago').value;
                console.log('✅ Método de pago:', metodoPago);
                
                console.log('🔧 Preparando Bold con método:', metodoPago, 'monto:', monto);
                
                // Validar que hay un monto válido (permitir monto 0 para checkout abierto)
                if (!monto || monto === 0) {
                    console.log('⚠️ Inicializando Bold con checkout abierto (sin monto específico)');
                    monto = 0; // Monto abierto para que el cliente defina el valor
                }
                
                console.log('💰 Monto final para Bold:', monto);
                
                console.log('🔄 Iniciando proceso de preparación Bold...');
                // Obtener datos del cliente del formulario
                const customerData = {
                    email: document.querySelector('input[name="correo"]')?.value || '',
                    fullName: document.querySelector('input[name="nombre"]')?.value || '',
                    phone: document.querySelector('input[name="telefono"]')?.value || '',
                    dialCode: '+57'
                };
                console.log('👤 Datos del cliente:', customerData);

                // Datos de dirección de facturación
                const billingAddress = {
                    address: document.querySelector('input[name="direccion"]')?.value || '',
                    city: 'Bogotá',
                    state: 'Cundinamarca',
                    country: 'CO'
                };
                console.log('📍 Dirección de facturación:', billingAddress);

                console.log('🎉 initializeBoldPayment() completada exitosamente');
                
                // Simular el resultado visual
                setTimeout(() => {
                    container.innerHTML = '<div style="text-align: center; padding: 16px; color: #00ff00;">✅ Función ejecutada correctamente - Bold estaría listo</div>';
                }, 1000);
                
                return true; // Retornar éxito

            } catch (error) {
                console.error('❌ Error general en initializeBoldPayment:', error);
                console.error('❌ Stack trace:', error.stack);
                const container = document.getElementById('bold-payment-container');
                if (container) {
                    container.innerHTML = '<div style="color: #ff6b6b; text-align: center; padding: 16px;">Error al inicializar el pago. Intente nuevamente.</div>';
                }
                return false; // Retornar error
            }
        }

        // Función de test
        function testFunction() {
            console.log('🎯 Iniciando test manual...');
            
            // Configurar método de pago
            const metodoPago = document.getElementById('metodo_pago');
            if (!metodoPago.value) {
                metodoPago.value = 'PSE Bold';
                console.log('🔧 Método de pago establecido a PSE Bold');
            }
            
            // Configurar info_pago
            const infoPago = document.getElementById('info_pago');
            infoPago.innerHTML = `
                <div class="pse-bold-container">
                    <b>PSE Bold - Pago Seguro:</b>
                    <p style="color: #999; margin: 8px 0;">Pague de manera segura sin salir de esta página</p>
                    <div id="bold-payment-container" style="margin-top: 12px;"></div>
                </div>
            `;
            
            // Ejecutar función
            console.log('🚀 Ejecutando initializeBoldPayment...');
            const result = initializeBoldPayment();
            console.log('🎯 Resultado final:', result);
        }

        function clearLogs() {
            document.getElementById('console-logs').innerHTML = 'Logs limpiados...\n';
        }

        // Event listener para método de pago
        document.getElementById('metodo_pago').addEventListener('change', function() {
            const value = this.value;
            console.log('🔥 Método de pago cambiado a:', value);
            
            if (value === 'PSE Bold' || value === 'Botón Bancolombia' || value === 'Tarjeta de Crédito o Débito') {
                const infoPago = document.getElementById('info_pago');
                infoPago.innerHTML = `
                    <div class="pse-bold-container">
                        <b>${value} - Pago Seguro:</b>
                        <p style="color: #999; margin: 8px 0;">Pague de manera segura sin salir de esta página</p>
                        <div id="bold-payment-container" style="margin-top: 12px;"></div>
                    </div>
                `;
                
                setTimeout(() => {
                    console.log('⏰ Auto-ejecutando initializeBoldPayment...');
                    const result = initializeBoldPayment();
                    console.log('✅ Auto-ejecución completada, resultado:', result);
                }, 100);
            }
        });

        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            console.log('📋 Test final cargado - Bold Integration');
            console.log('💰 Monto disponible:', <?php echo json_encode($monto); ?>);
        });
    </script>
</body>
</html>
