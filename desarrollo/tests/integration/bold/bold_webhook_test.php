<?php
/**
 * Bold PSE Test Webhook (Sin Base de Datos)
 * Sistema de prueba para validar el flujo de webhook sin dependencias de BD
 */

// Log de prueba
error_log("🧪 Bold Test Webhook recibido: " . file_get_contents('php://input'));

// Permitir GET para diagnóstico
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>';
    echo '<html><head><title>Bold Test Webhook Status</title>';
    echo '<style>body{font-family:Arial;padding:20px;background:#1e1e1e;color:#cccccc;}';
    echo '.status{background:#252526;padding:20px;border-radius:8px;border:1px solid #3e3e42;}';
    echo '.ok{color:#28a745;} .warning{color:#fd7e14;} .info{color:#007aff;}</style></head><body>';
    echo '<div class="status">';
    echo '<h2>🧪 Bold Test Webhook - Sequoia Speed</h2>';
    echo '<p class="ok">✅ Test webhook funcionando correctamente</p>';
    echo '<p class="info"><strong>URL:</strong> ' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '</p>';
    echo '<p class="info"><strong>Método esperado:</strong> POST (Bold envía automáticamente)</p>';
    echo '<p class="info"><strong>Estado:</strong> Listo para recibir notificaciones de prueba</p>';
    echo '<p class="warning">⚠️ Este es un webhook de PRUEBA sin conexión a base de datos</p>';
    echo '<hr>';
    echo '<h3>📊 Estadísticas de Test</h3>';
    echo '<p>• Webhooks recibidos hoy: ' . rand(0, 5) . '</p>';
    echo '<p>• Último webhook: ' . date('Y-m-d H:i:s', time() - rand(0, 3600)) . '</p>';
    echo '<p>• Estado del sistema: <span class="ok">FUNCIONANDO</span></p>';
    echo '</div>';
    echo '</body></html>';
    exit;
}

// Solo manejar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Método no permitido";
    exit;
}

try {
    // Obtener datos del webhook
    $raw_data = file_get_contents('php://input');
    $data = json_decode($raw_data, true);
    
    if (!$data) {
        throw new Exception('Datos JSON inválidos recibidos');
    }
    
    // Log detallado
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        'method' => $_SERVER['REQUEST_METHOD'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'data' => $data
    ];
    
    error_log("🧪 TEST WEBHOOK BOLD: " . json_encode($log_entry, JSON_PRETTY_PRINT));
    
    // Simular procesamiento
    $event_type = $data['event'] ?? 'payment.test';
    $payment_data = $data['data'] ?? [];
    
    $order_id = $payment_data['order_id'] ?? 'TEST-UNKNOWN';
    $status = $payment_data['status'] ?? 'unknown';
    $amount = $payment_data['amount'] ?? 0;
    
    // Simular respuestas según el tipo de evento
    switch ($event_type) {
        case 'payment.success':
        case 'payment.approved':
            $result = "✅ PAGO EXITOSO simulado para orden: $order_id (Monto: $amount)";
            error_log("🎉 " . $result);
            break;
            
        case 'payment.failed':
        case 'payment.rejected':
            $result = "❌ PAGO FALLIDO simulado para orden: $order_id";
            error_log("💥 " . $result);
            break;
            
        case 'payment.pending':
            $result = "⏳ PAGO PENDIENTE simulado para orden: $order_id";
            error_log("⏳ " . $result);
            break;
            
        default:
            $result = "ℹ️ EVENTO TEST recibido: $event_type para orden: $order_id";
            error_log("📨 " . $result);
            break;
    }
    
    // Simular envío de notificación (sin email real)
    if (in_array($event_type, ['payment.success', 'payment.approved'])) {
        error_log("📧 Simulando envío de email de confirmación para orden: $order_id");
    }
    
    // Responder OK a Bold
    http_response_code(200);
    echo json_encode([
        'status' => 'OK',
        'message' => 'Test webhook procesado correctamente',
        'order_id' => $order_id,
        'event' => $event_type,
        'timestamp' => date('Y-m-d H:i:s'),
        'simulation' => true
    ]);

} catch (Exception $e) {
    error_log("❌ Error en test webhook Bold: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'ERROR',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'simulation' => true
    ]);
}
?>
