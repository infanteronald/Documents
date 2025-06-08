<?php
/**
 * Bold PSE Webhook Distributor
 * 
 * Este archivo maneja la distribución de webhooks entre el sistema
 * original y el sistema mejorado durante la migración gradual.
 */

require_once "dual_mode_config.php";

// Logging inicial
logDualMode("Webhook distributor accessed", [
    'method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
]);

// Solo procesar POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    logDualMode("Invalid method", ['method' => $_SERVER['REQUEST_METHOD']]);
    exit('Method not allowed');
}

// Obtener datos del webhook
$input = file_get_contents('php://input');
if (empty($input)) {
    http_response_code(400);
    logDualMode("Empty webhook data");
    exit('Empty webhook data');
}

// Parsear datos
$data = json_decode($input, true);
if (!$data) {
    http_response_code(400);
    logDualMode("Invalid JSON data", ['input_preview' => substr($input, 0, 200)]);
    exit('Invalid JSON data');
}

// Obtener información para la decisión
$clientIP = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
$transactionId = $data['transaction']['id'] ?? $data['transactionId'] ?? null;

// Decidir qué webhook usar
$useEnhanced = shouldUseEnhancedWebhook($clientIP, $transactionId);

logDualMode("Webhook routing decision", [
    'transaction_id' => $transactionId,
    'client_ip' => $clientIP,
    'use_enhanced' => $useEnhanced,
    'percentage' => ENHANCED_WEBHOOK_PERCENTAGE
]);

try {
    if ($useEnhanced) {
        // Usar webhook mejorado
        logDualMode("Routing to enhanced webhook", ['transaction_id' => $transactionId]);
        
        // Include the enhanced webhook
        require_once "bold_webhook_enhanced.php";
        
    } else {
        // Usar webhook original
        logDualMode("Routing to original webhook", ['transaction_id' => $transactionId]);
        
        // Include the original webhook
        require_once "bold_webhook.php";
    }
    
} catch (Exception $e) {
    logDualMode("Error in webhook processing", [
        'error' => $e->getMessage(),
        'transaction_id' => $transactionId,
        'enhanced' => $useEnhanced
    ]);
    
    http_response_code(500);
    echo "Internal server error";
}
?>
