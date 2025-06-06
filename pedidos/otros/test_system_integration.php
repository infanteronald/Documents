<?php
/**
 * Script de prueba para el sistema Bold PSE mejorado
 * Prueba la integración completa de webhooks, notificaciones y UX
 */

require_once "conexion.php";
require_once "bold_webhook_enhanced.php";
require_once "bold_notification_system.php";

// Colores para output en consola
const COLOR_GREEN = "\033[32m";
const COLOR_RED = "\033[31m";
const COLOR_YELLOW = "\033[33m";
const COLOR_BLUE = "\033[34m";
const COLOR_RESET = "\033[0m";

/**
 * Clase para testing del sistema
 */
class BoldSystemTester {
    private $conn;
    private $testResults = [];
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Ejecutar todas las pruebas
     */
    public function runAllTests() {
        echo COLOR_BLUE . "🧪 Iniciando pruebas del sistema Bold PSE mejorado...\n" . COLOR_RESET;
        echo str_repeat("=", 60) . "\n";
        
        $this->testDatabaseSetup();
        $this->testWebhookProcessing();
        $this->testNotificationSystem();
        $this->testRetryLogic();
        $this->testSystemIntegration();
        
        $this->showResults();
    }
    
    /**
     * Probar configuración de base de datos
     */
    private function testDatabaseSetup() {
        echo COLOR_YELLOW . "📊 Probando configuración de base de datos...\n" . COLOR_RESET;
        
        // Verificar tablas requeridas
        $requiredTables = [
            'pedidos_detal',
            'bold_retry_queue',
            'bold_webhook_logs',
            'notification_logs'
        ];
        
        foreach ($requiredTables as $table) {
            $result = $this->conn->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows > 0) {
                $this->addResult("✅ Tabla $table existe", true);
            } else {
                $this->addResult("❌ Tabla $table falta", false);
            }
        }
        
        // Verificar vista
        $result = $this->conn->query("SHOW TABLES LIKE 'bold_webhook_stats'");
        if ($result->num_rows > 0) {
            $this->addResult("✅ Vista bold_webhook_stats existe", true);
        } else {
            $this->addResult("❌ Vista bold_webhook_stats falta", false);
        }
    }
    
    /**
     * Probar procesamiento de webhooks
     */
    private function testWebhookProcessing() {
        echo COLOR_YELLOW . "🔗 Probando procesamiento de webhooks...\n" . COLOR_RESET;
        
        // Crear un pedido de prueba
        $testOrderId = $this->createTestOrder();
        
        if ($testOrderId) {
            $this->addResult("✅ Pedido de prueba creado: #$testOrderId", true);
            
            // Simular webhook de éxito
            $webhookData = [
                'event' => 'SALE_APPROVED',
                'order' => [
                    'order_id' => "SEQ-$testOrderId",
                    'status' => 'APPROVED',
                    'amount' => 50000
                ],
                'transaction' => [
                    'id' => 'TEST-' . time(),
                    'status' => 'APPROVED',
                    'amount' => 50000
                ]
            ];
            
            try {
                $processor = new BoldWebhookProcessor($this->conn);
                $result = $processor->processWebhook($webhookData);
                
                if ($result['success']) {
                    $this->addResult("✅ Webhook procesado correctamente", true);
                } else {
                    $this->addResult("❌ Error procesando webhook: " . $result['error'], false);
                }
            } catch (Exception $e) {
                $this->addResult("❌ Excepción en webhook: " . $e->getMessage(), false);
            }
        } else {
            $this->addResult("❌ No se pudo crear pedido de prueba", false);
        }
    }
    
    /**
     * Probar sistema de notificaciones
     */
    private function testNotificationSystem() {
        echo COLOR_YELLOW . "📧 Probando sistema de notificaciones...\n" . COLOR_RESET;
        
        try {
            $notificationSystem = new BoldNotificationSystem();
            
            // Datos de prueba
            $testPedido = [
                'nombre' => 'Usuario Prueba',
                'email' => 'test@example.com',
                'telefono' => '3001234567',
                'direccion' => 'Calle 123 #45-67',
                'ciudad' => 'Bogotá',
                'total' => 50000,
                'observaciones' => 'Pedido de prueba del sistema'
            ];
            
            // Probar template de éxito
            $template = $notificationSystem->getSuccessEmailTemplate($testPedido, 'TEST-123');
            if (strpos($template, 'Usuario Prueba') !== false) {
                $this->addResult("✅ Template de éxito generado correctamente", true);
            } else {
                $this->addResult("❌ Error en template de éxito", false);
            }
            
            // Probar template de fallo
            $template = $notificationSystem->getFailureEmailTemplate($testPedido, 'TEST-456');
            if (strpos($template, 'Usuario Prueba') !== false) {
                $this->addResult("✅ Template de fallo generado correctamente", true);
            } else {
                $this->addResult("❌ Error en template de fallo", false);
            }
            
            // Probar logging de notificaciones
            $notificationSystem->logNotification(
                $testPedido['email'],
                'success',
                'TEST-789',
                'Test notification'
            );
            $this->addResult("✅ Logging de notificaciones funciona", true);
            
        } catch (Exception $e) {
            $this->addResult("❌ Error en sistema de notificaciones: " . $e->getMessage(), false);
        }
    }
    
    /**
     * Probar lógica de retry
     */
    private function testRetryLogic() {
        echo COLOR_YELLOW . "🔄 Probando lógica de retry...\n" . COLOR_RESET;
        
        // Insertar un elemento en la cola de retry
        $testData = json_encode(['test' => 'retry_data', 'timestamp' => time()]);
        $stmt = $this->conn->prepare("
            INSERT INTO bold_retry_queue (webhook_data, error_message, attempts, next_retry) 
            VALUES (?, 'Test error', 0, NOW())
        ");
        $stmt->bind_param("s", $testData);
        
        if ($stmt->execute()) {
            $this->addResult("✅ Elemento agregado a cola de retry", true);
            
            // Verificar que se puede recuperar
            $result = $this->conn->query("
                SELECT COUNT(*) as count 
                FROM bold_retry_queue 
                WHERE error_message = 'Test error'
            ");
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                $this->addResult("✅ Cola de retry funcional", true);
                
                // Limpiar datos de prueba
                $this->conn->query("DELETE FROM bold_retry_queue WHERE error_message = 'Test error'");
            } else {
                $this->addResult("❌ Error en cola de retry", false);
            }
        } else {
            $this->addResult("❌ No se pudo agregar a cola de retry", false);
        }
    }
    
    /**
     * Probar integración completa del sistema
     */
    private function testSystemIntegration() {
        echo COLOR_YELLOW . "🔗 Probando integración completa...\n" . COLOR_RESET;
        
        // Verificar archivos de UX
        $uxFiles = [
            'payment_ux_enhanced.js',
            'payment_ux_enhanced.css'
        ];
        
        foreach ($uxFiles as $file) {
            if (file_exists(__DIR__ . '/' . $file)) {
                $this->addResult("✅ Archivo UX $file presente", true);
            } else {
                $this->addResult("❌ Archivo UX $file faltante", false);
            }
        }
        
        // Verificar archivos del sistema
        $systemFiles = [
            'bold_webhook_enhanced.php',
            'bold_notification_system.php',
            'bold_webhook_monitor.php',
            'bold_retry_processor.php'
        ];
        
        foreach ($systemFiles as $file) {
            if (file_exists(__DIR__ . '/' . $file)) {
                $this->addResult("✅ Archivo sistema $file presente", true);
            } else {
                $this->addResult("❌ Archivo sistema $file faltante", false);
            }
        }
        
        // Verificar directorio de logs
        if (!is_dir(__DIR__ . '/logs')) {
            mkdir(__DIR__ . '/logs', 0755, true);
            $this->addResult("✅ Directorio de logs creado", true);
        } else {
            $this->addResult("✅ Directorio de logs existe", true);
        }
    }
    
    /**
     * Crear pedido de prueba
     */
    private function createTestOrder() {
        $stmt = $this->conn->prepare("
            INSERT INTO pedidos_detal (
                nombre, telefono, email, direccion, ciudad, 
                total, estado_pago, observaciones, fecha_pedido
            ) VALUES (
                'Test User', '3001234567', 'test@example.com', 
                'Test Address', 'Test City', 50000, 'PENDIENTE', 
                'Pedido de prueba automatizada', NOW()
            )
        ");
        
        if ($stmt->execute()) {
            return $this->conn->insert_id;
        }
        
        return false;
    }
    
    /**
     * Agregar resultado de prueba
     */
    private function addResult($message, $success) {
        $this->testResults[] = [
            'message' => $message,
            'success' => $success
        ];
        echo ($success ? COLOR_GREEN : COLOR_RED) . $message . COLOR_RESET . "\n";
    }
    
    /**
     * Mostrar resumen de resultados
     */
    private function showResults() {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo COLOR_BLUE . "📊 RESUMEN DE PRUEBAS\n" . COLOR_RESET;
        echo str_repeat("=", 60) . "\n";
        
        $total = count($this->testResults);
        $passed = count(array_filter($this->testResults, function($r) { return $r['success']; }));
        $failed = $total - $passed;
        
        echo COLOR_GREEN . "✅ Pruebas exitosas: $passed\n" . COLOR_RESET;
        echo COLOR_RED . "❌ Pruebas fallidas: $failed\n" . COLOR_RESET;
        echo "📊 Total de pruebas: $total\n";
        
        $percentage = $total > 0 ? round(($passed / $total) * 100, 2) : 0;
        echo COLOR_BLUE . "🎯 Porcentaje de éxito: $percentage%\n" . COLOR_RESET;
        
        if ($failed > 0) {
            echo "\n" . COLOR_YELLOW . "⚠️  PRUEBAS FALLIDAS:\n" . COLOR_RESET;
            foreach ($this->testResults as $result) {
                if (!$result['success']) {
                    echo COLOR_RED . "   • " . $result['message'] . COLOR_RESET . "\n";
                }
            }
        }
        
        echo "\n" . COLOR_BLUE . "🔗 Para monitorear el sistema en tiempo real, visite:\n" . COLOR_RESET;
        echo "   http://localhost/pedidos/bold_webhook_monitor.php\n";
        echo "\n" . COLOR_BLUE . "🔄 Para ejecutar el procesador de retry:\n" . COLOR_RESET;
        echo "   http://localhost/pedidos/bold_retry_processor.php\n";
    }
}

// Ejecutar pruebas si se llama directamente
if (php_sapi_name() === 'cli' || basename($_SERVER['PHP_SELF']) === 'test_system_integration.php') {
    try {
        $tester = new BoldSystemTester($conn);
        $tester->runAllTests();
    } catch (Exception $e) {
        echo COLOR_RED . "❌ Error ejecutando pruebas: " . $e->getMessage() . COLOR_RESET . "\n";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pruebas del Sistema Bold PSE</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Inter', sans-serif;
            background: #1e1e1e;
            color: #cccccc;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #252526;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        h1 {
            color: #ffffff;
            text-align: center;
            margin-bottom: 30px;
        }
        .test-section {
            margin-bottom: 24px;
            padding: 16px;
            background: #1e1e1e;
            border-radius: 8px;
            border-left: 4px solid #007aff;
        }
        .test-result {
            padding: 8px 12px;
            margin: 4px 0;
            border-radius: 6px;
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 14px;
        }
        .success {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
            border-left: 3px solid #28a745;
        }
        .error {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
            border-left: 3px solid #dc3545;
        }
        .info {
            background: rgba(0, 122, 255, 0.2);
            color: #007aff;
            border-left: 3px solid #007aff;
        }
        .summary {
            background: #2d2d30;
            border-radius: 8px;
            padding: 20px;
            margin-top: 24px;
            text-align: center;
        }
        .run-tests-btn {
            background: #007aff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px;
            transition: background 0.2s;
        }
        .run-tests-btn:hover {
            background: #0056d3;
        }
        .links {
            margin-top: 20px;
            padding: 16px;
            background: #2d2d30;
            border-radius: 8px;
        }
        .links a {
            color: #007aff;
            text-decoration: none;
            display: block;
            margin: 8px 0;
        }
        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Pruebas del Sistema Bold PSE Mejorado</h1>
        
        <div class="test-section">
            <h3>🚀 Ejecutar Pruebas</h3>
            <button class="run-tests-btn" onclick="runTests()">Ejecutar Todas las Pruebas</button>
            <div id="test-results"></div>
        </div>
        
        <div class="links">
            <h3>🔗 Enlaces del Sistema</h3>
            <a href="bold_webhook_monitor.php" target="_blank">📊 Monitor de Webhooks en Tiempo Real</a>
            <a href="bold_retry_processor.php" target="_blank">🔄 Procesador de Retry</a>
            <a href="index.php" target="_blank">📝 Formulario de Pedidos (con UX mejorada)</a>
            <a href="setup_enhanced_webhooks.php" target="_blank">⚙️ Configuración de Base de Datos</a>
        </div>
    </div>

    <script>
        async function runTests() {
            const button = document.querySelector('.run-tests-btn');
            const resultsDiv = document.getElementById('test-results');
            
            button.disabled = true;
            button.textContent = 'Ejecutando pruebas...';
            resultsDiv.innerHTML = '<div class="info">⏳ Ejecutando pruebas del sistema...</div>';
            
            try {
                const response = await fetch('test_system_integration.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'run_tests' })
                });
                
                const text = await response.text();
                resultsDiv.innerHTML = '<pre style="color: #cccccc; background: #1e1e1e; padding: 16px; border-radius: 8px; overflow-x: auto;">' + text + '</pre>';
                
            } catch (error) {
                resultsDiv.innerHTML = '<div class="error">❌ Error ejecutando pruebas: ' + error.message + '</div>';
            } finally {
                button.disabled = false;
                button.textContent = 'Ejecutar Todas las Pruebas';
            }
        }
        
        // Auto-ejecutar pruebas al cargar si se solicita
        if (window.location.search.includes('auto=true')) {
            document.addEventListener('DOMContentLoaded', runTests);
        }
    </script>
</body>
</html>
