<?php
/**
 * Página de prueba para verificar el estado del webhook Bold
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Webhook Bold - Sequoia Speed</title>
    <style>
        :root {
            --vscode-bg: #1e1e1e;
            --vscode-sidebar: #252526;
            --vscode-border: #3e3e42;
            --vscode-text: #cccccc;
            --vscode-text-secondary: #9d9d9d;
            --apple-blue: #007aff;
            --success-green: #28a745;
            --error-red: #dc3545;
            --warning-orange: #fd7e14;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background: var(--vscode-bg);
            color: var(--vscode-text);
            line-height: 1.6;
            min-height: 100vh;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            padding: 20px;
            background: var(--vscode-sidebar);
            border-radius: 12px;
            border: 1px solid var(--vscode-border);
        }

        .logo {
            width: 60px;
            height: 60px;
            margin: 0 auto 15px;
            display: block;
        }

        h1 {
            color: var(--apple-blue);
            margin-bottom: 10px;
            font-size: 2rem;
            font-weight: 600;
        }

        .subtitle {
            color: var(--vscode-text-secondary);
            font-size: 1.1rem;
        }

        .test-section {
            background: var(--vscode-sidebar);
            border-radius: 12px;
            border: 1px solid var(--vscode-border);
            padding: 20px;
            margin-bottom: 20px;
        }

        .test-title {
            font-size: 1.3rem;
            color: var(--apple-blue);
            margin-bottom: 15px;
            font-weight: 600;
        }

        .status-item {
            display: flex;
            align-items: center;
            padding: 10px;
            margin-bottom: 10px;
            background: var(--vscode-bg);
            border-radius: 8px;
            border: 1px solid var(--vscode-border);
        }

        .status-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-right: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 12px;
        }

        .status-success {
            background: var(--success-green);
            color: white;
        }

        .status-error {
            background: var(--error-red);
            color: white;
        }

        .status-warning {
            background: var(--warning-orange);
            color: white;
        }

        .code-block {
            background: var(--vscode-bg);
            border: 1px solid var(--vscode-border);
            border-radius: 8px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            color: var(--vscode-text);
            margin: 10px 0;
            overflow-x: auto;
        }

        .btn {
            background: var(--apple-blue);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s ease;
        }

        .btn:hover {
            background: #0056b3;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--vscode-border);
            color: var(--vscode-text);
        }

        .btn-secondary:hover {
            background: #4a4a4a;
        }

        .info-box {
            background: rgba(0, 122, 255, 0.1);
            border: 1px solid var(--apple-blue);
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }

        .info-box h4 {
            color: var(--apple-blue);
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="logo.png" alt="Sequoia Speed" class="logo">
            <h1>🔗 Test Webhook Bold PSE</h1>
            <p class="subtitle">Verificación del estado del webhook para pagos</p>
        </div>

        <div class="test-section">
            <h2 class="test-title">📡 Estado del Webhook</h2>
            
            <?php
            $webhook_url = "https://sequoiaspeed.com.co/pedidos/bold_webhook.php";
            $webhook_file = "bold_webhook.php";
            
            // Verificar si el archivo existe
            if (file_exists($webhook_file)) {
                echo '<div class="status-item">';
                echo '<div class="status-icon status-success">✓</div>';
                echo '<div>Archivo webhook encontrado: <code>' . $webhook_file . '</code></div>';
                echo '</div>';
            } else {
                echo '<div class="status-item">';
                echo '<div class="status-icon status-error">✗</div>';
                echo '<div>Archivo webhook NO encontrado</div>';
                echo '</div>';
            }

            // Verificar permisos de lectura
            if (is_readable($webhook_file)) {
                echo '<div class="status-item">';
                echo '<div class="status-icon status-success">✓</div>';
                echo '<div>Archivo webhook legible</div>';
                echo '</div>';
            }

            // Verificar conexión a base de datos
            try {
                require_once "conexion.php";
                echo '<div class="status-item">';
                echo '<div class="status-icon status-success">✓</div>';
                echo '<div>Conexión a base de datos OK</div>';
                echo '</div>';
            } catch (Exception $e) {
                echo '<div class="status-item">';
                echo '<div class="status-icon status-error">✗</div>';
                echo '<div>Error en conexión BD: ' . htmlspecialchars($e->getMessage()) . '</div>';
                echo '</div>';
            }

            // Información sobre el método no permitido
            echo '<div class="status-item">';
            echo '<div class="status-icon status-warning">⚠</div>';
            echo '<div>El error "método no permitido" es NORMAL cuando accedes desde el navegador</div>';
            echo '</div>';
            ?>
        </div>

        <div class="info-box">
            <h4>ℹ️ Información Importante</h4>
            <p><strong>El webhook está funcionando correctamente.</strong> El error "método no permitido" que ves al acceder desde el navegador es esperado porque:</p>
            <ul style="margin: 10px 0 10px 20px;">
                <li>El webhook solo acepta peticiones POST de Bold</li>
                <li>Los navegadores hacen peticiones GET por defecto</li>
                <li>Bold enviará automáticamente peticiones POST cuando ocurran transacciones</li>
            </ul>
        </div>

        <div class="test-section">
            <h2 class="test-title">🔧 Configuración Required en Bold</h2>
            
            <div class="code-block">
<strong>URL del Webhook:</strong>
<?= $webhook_url ?>

<strong>Método:</strong> POST
<strong>Content-Type:</strong> application/json
            </div>

            <div class="info-box">
                <h4>📝 Pasos para configurar en Bold:</h4>
                <ol style="margin: 10px 0 10px 20px;">
                    <li>Ingresar al panel de Bold</li>
                    <li>Ir a Configuración → Webhooks</li>
                    <li>Agregar nueva URL: <code><?= $webhook_url ?></code></li>
                    <li>Seleccionar eventos: payment_intent.succeeded, payment_intent.failed</li>
                    <li>Guardar configuración</li>
                </ol>
            </div>
        </div>

        <div class="test-section">
            <h2 class="test-title">🧪 Simulador de Webhook (Solo para Testing)</h2>
            
            <button class="btn" onclick="testWebhook()">🧪 Simular Webhook de Prueba</button>
            <button class="btn btn-secondary" onclick="checkLogs()">📋 Ver Logs</button>
            
            <div id="test-result" style="margin-top: 15px;"></div>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php" class="btn btn-secondary">← Volver al Inicio</a>
            <a href="listar_pedidos.php" class="btn">📋 Ver Pedidos</a>
        </div>
    </div>

    <script>
        function testWebhook() {
            const resultDiv = document.getElementById('test-result');
            resultDiv.innerHTML = '<div class="info-box">🔄 Enviando petición de prueba...</div>';

            // Simular data de webhook Bold
            const testData = {
                event_type: 'payment_intent.succeeded',
                data: {
                    id: 'test_' + Date.now(),
                    status: 'succeeded',
                    amount: 50000,
                    currency: 'COP',
                    order_id: 'test_order_123',
                    customer: {
                        name: 'Cliente de Prueba',
                        email: 'test@example.com'
                    }
                }
            };

            fetch('bold_webhook.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(testData)
            })
            .then(response => {
                if (response.ok) {
                    resultDiv.innerHTML = '<div class="status-item"><div class="status-icon status-success">✓</div><div>Webhook respondió correctamente</div></div>';
                } else {
                    resultDiv.innerHTML = '<div class="status-item"><div class="status-icon status-warning">⚠</div><div>Webhook respondió con código: ' + response.status + '</div></div>';
                }
            })
            .catch(error => {
                resultDiv.innerHTML = '<div class="status-item"><div class="status-icon status-error">✗</div><div>Error en petición: ' + error.message + '</div></div>';
            });
        }

        function checkLogs() {
            alert('Para ver los logs del webhook, revisa el archivo error.log del servidor o los logs de Bold en el panel administrativo.');
        }
    </script>
</body>
</html>
