<?php
/**
 * Webhook receiver para pruebas
 * Simula la recepción de webhooks de Bold durante las pruebas
 */

require_once '../config_test.php';

// Log del webhook recibido
function logWebhookTest($data) {
    $logFile = TEST_LOGS_DIR . 'webhook_test.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] Webhook Test Received: " . json_encode($data) . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Función para validar signature (simulada para pruebas)
function validateTestWebhookSignature($payload, $signature) {
    // En pruebas, siempre retornar true
    // En producción esto validaría la firma real de Bold
    return true;
}

// Procesar webhook de prueba
function processTestWebhook($payload) {
    $data = json_decode($payload, true);
    
    if (!$data) {
        return ['status' => 'error', 'message' => 'Invalid JSON payload'];
    }
    
    // Simular procesamiento según el tipo de evento
    switch ($data['type'] ?? '') {
        case 'payment.approved':
            return [
                'status' => 'success',
                'message' => 'Payment approved webhook processed',
                'order_id' => $data['data']['reference'] ?? 'unknown'
            ];
            
        case 'payment.declined':
            return [
                'status' => 'success',
                'message' => 'Payment declined webhook processed',
                'order_id' => $data['data']['reference'] ?? 'unknown'
            ];
            
        case 'payment.pending':
            return [
                'status' => 'success',
                'message' => 'Payment pending webhook processed',
                'order_id' => $data['data']['reference'] ?? 'unknown'
            ];
            
        default:
            return [
                'status' => 'warning',
                'message' => 'Unknown webhook type: ' . ($data['type'] ?? 'none')
            ];
    }
}

// Punto de entrada principal
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_X_BOLD_SIGNATURE'] ?? '';
    
    // Log la recepción
    logWebhookTest([
        'method' => 'POST',
        'payload' => $payload,
        'signature' => $signature,
        'headers' => getallheaders()
    ]);
    
    // Validar signature (simulado en pruebas)
    if (!validateTestWebhookSignature($payload, $signature)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid signature']);
        exit;
    }
    
    // Procesar el webhook
    $result = processTestWebhook($payload);
    
    // Retornar respuesta
    http_response_code(200);
    echo json_encode($result);
    
} else {
    // GET request - mostrar información del webhook
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Webhook Test Receiver</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .info { background: #f0f8ff; padding: 15px; border-radius: 5px; }
            .logs { background: #f8f8f8; padding: 15px; border-radius: 5px; margin-top: 20px; }
            pre { overflow-x: auto; }
        </style>
    </head>
    <body>
        <h1>🔗 Webhook Test Receiver</h1>
        
        <div class="info">
            <h3>Información del Endpoint</h3>
            <p><strong>URL:</strong> <?php echo TEST_WEBHOOK_URL; ?></p>
            <p><strong>Método:</strong> POST</p>
            <p><strong>Estado:</strong> ✅ Activo para pruebas</p>
        </div>
        
        <div class="logs">
            <h3>Logs Recientes</h3>
            <?php
            $logFile = TEST_LOGS_DIR . 'webhook_test.log';
            if (file_exists($logFile)) {
                $logs = file_get_contents($logFile);
                echo '<pre>' . htmlspecialchars($logs) . '</pre>';
            } else {
                echo '<p>No hay logs disponibles aún.</p>';
            }
            ?>
        </div>
        
        <div style="margin-top: 20px;">
            <h3>Probar Webhook</h3>
            <p>Puedes usar curl para probar el webhook:</p>
            <pre>curl -X POST <?php echo TEST_WEBHOOK_URL; ?> \
  -H "Content-Type: application/json" \
  -H "X-Bold-Signature: test_signature" \
  -d '{"type":"payment.approved","data":{"reference":"ORDER_1","amount":50000}}'</pre>
        </div>
    </body>
    </html>
    <?php
}
