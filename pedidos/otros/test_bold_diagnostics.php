<?php
/**
 * Diagnóstico Bold PSE - Verificar configuración
 */

header('Content-Type: text/html; charset=utf-8');

// Incluir configuración
require_once 'bold_hash.php';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico Bold PSE</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .info { color: #17a2b8; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; }
        .section { margin: 20px 0; padding: 15px; border-left: 4px solid #007bff; background: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnóstico Bold PSE - Sequoia Speed</h1>
        
        <div class="section">
            <h3>1. Verificación de Credenciales Bold</h3>
            <?php
            // Verificar constantes de Bold
            if (defined('BOLD_API_KEY') && defined('BOLD_SECRET_KEY')) {
                echo '<p class="success">✅ Credenciales Bold encontradas</p>';
                echo '<p><strong>API Key:</strong> ' . substr(BOLD_API_KEY, 0, 10) . '...</p>';
                echo '<p><strong>Secret Key:</strong> ' . substr(BOLD_SECRET_KEY, 0, 5) . '...</p>';
            } else {
                echo '<p class="error">❌ Credenciales Bold no encontradas</p>';
            }
            ?>
        </div>

        <div class="section">
            <h3>2. Test de Generación de Hash</h3>
            <?php
            try {
                $test_order = 'TEST-' . time();
                $test_amount = 50000;
                $test_currency = 'COP';
                
                $hash_string = $test_order . $test_amount . $test_currency . BOLD_SECRET_KEY;
                $integrity_hash = hash('sha256', $hash_string);
                
                echo '<p class="success">✅ Hash generado correctamente</p>';
                echo '<div class="code">';
                echo '<strong>Orden:</strong> ' . $test_order . '<br>';
                echo '<strong>Monto:</strong> ' . $test_amount . '<br>';
                echo '<strong>Hash:</strong> ' . substr($integrity_hash, 0, 20) . '...';
                echo '</div>';
            } catch (Exception $e) {
                echo '<p class="error">❌ Error generando hash: ' . $e->getMessage() . '</p>';
            }
            ?>
        </div>

        <div class="section">
            <h3>3. Test de Conexión a Bold API</h3>
            <div id="bold-test-container"></div>
            <button onclick="testBoldConnection()" style="background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">
                Probar Conexión Bold
            </button>
        </div>

        <div class="section">
            <h3>4. Configuración Recomendada</h3>
            <p class="info">💡 Para resolver el error BTN-001, verifica:</p>
            <ul>
                <li><strong>URLs Permitidas:</strong> En tu panel Bold, agrega tu dominio</li>
                <li><strong>Webhook URL:</strong> https://tudominio.com/bold_webhook.php</li>
                <li><strong>Ambiente:</strong> Asegúrate de usar credenciales de producción</li>
                <li><strong>SSL:</strong> Tu sitio debe tener certificado SSL válido</li>
            </ul>
        </div>

        <div class="section">
            <h3>5. Test Simplificado Bold</h3>
            <p>Botón Bold básico para testing:</p>
            <div id="test-bold-basic"></div>
        </div>
    </div>

    <script src="https://checkout.bold.co/library/boldPaymentButton.js"></script>
    <script>
        function testBoldConnection() {
            const container = document.getElementById('bold-test-container');
            container.innerHTML = '<p class="info">🔄 Probando conexión...</p>';
            
            // Test básico de carga del script
            try {
                const testScript = document.createElement('script');
                testScript.src = 'https://checkout.bold.co/library/boldPaymentButton.js';
                testScript.onload = function() {
                    container.innerHTML = '<p class="success">✅ Script Bold cargado correctamente</p>';
                    testBasicBold();
                };
                testScript.onerror = function() {
                    container.innerHTML = '<p class="error">❌ Error cargando script Bold</p>';
                };
            } catch (error) {
                container.innerHTML = '<p class="error">❌ Error: ' + error.message + '</p>';
            }
        }

        function testBasicBold() {
            const testContainer = document.getElementById('test-bold-basic');
            
            // Crear botón Bold básico con monto mínimo
            const testOrder = 'TEST-' + Date.now();
            
            const testScript = document.createElement('script');
            testScript.src = 'https://checkout.bold.co/library/boldPaymentButton.js';
            testScript.setAttribute('data-bold-button', 'dark-L');
            testScript.setAttribute('data-api-key', '<?php echo BOLD_API_KEY; ?>');
            testScript.setAttribute('data-description', 'Test Sequoia Speed - ' + testOrder);
            testScript.setAttribute('data-order-id', testOrder);
            testScript.setAttribute('data-currency', 'COP');
            testScript.setAttribute('data-render-mode', 'embedded');
            
            testContainer.appendChild(testScript);
            
            console.log('Test Bold iniciado con orden:', testOrder);
        }

        // Auto-ejecutar test de conexión
        setTimeout(testBoldConnection, 1000);
    </script>
</body>
</html>
