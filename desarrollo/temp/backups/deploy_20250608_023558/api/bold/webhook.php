<?php
/**
 * API de Bold PSE - Webhook
 * Sequoia Speed - Sistema de pagos Bold PSE
 * 
 * Endpoint: POST /public/api/bold/webhook.php
 * Migrado desde: bold_webhook_enhanced.php
 */

require_once __DIR__ . '/../../../bootstrap.php';

use SequoiaSpeed\Controllers\BoldController;
use SequoiaSpeed\Services\BoldWebhookService;
use SequoiaSpeed\Services\EmailService;

// Configurar headers para API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Bold-Signature, X-Legacy-Compatibility');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

try {
    // Obtener datos del webhook
    $input = file_get_contents('php://input');
    $webhookData = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Datos JSON inválidos del webhook');
    }

    // Log del webhook recibido
    error_log("Bold Webhook recibido: " . $input);

    // Verificar firma si está presente
    $signature = $_SERVER['HTTP_X_BOLD_SIGNATURE'] ?? '';
    if (!empty($signature)) {
        $webhookService = new BoldWebhookService();
        if (!$webhookService->verifySignature($input, $signature)) {
            throw new Exception('Firma del webhook inválida');
        }
    }

    // Validar estructura básica del webhook
    if (!isset($webhookData['type']) || !isset($webhookData['data'])) {
        throw new Exception('Estructura del webhook inválida');
    }

    $webhookType = $webhookData['type'];
    $data = $webhookData['data'];

    // Crear controlador de Bold
    $boldController = new BoldController();
    
    // Procesar según el tipo de webhook
    switch ($webhookType) {
        case 'payment.completed':
        case 'transaction.completed':
            $result = $boldController->handlePaymentCompleted($data);
            break;
            
        case 'payment.failed':
        case 'transaction.failed':
            $result = $boldController->handlePaymentFailed($data);
            break;
            
        case 'payment.pending':
        case 'transaction.pending':
            $result = $boldController->handlePaymentPending($data);
            break;
            
        case 'payment.cancelled':
        case 'transaction.cancelled':
            $result = $boldController->handlePaymentCancelled($data);
            break;
            
        case 'payment.refunded':
        case 'transaction.refunded':
            $result = $boldController->handlePaymentRefunded($data);
            break;
            
        default:
            // Log de tipo no reconocido pero no fallar
            error_log("Tipo de webhook Bold no reconocido: " . $webhookType);
            $result = ['success' => true, 'message' => 'Webhook procesado (tipo no manejado)'];
    }

    // Enviar notificaciones por email si es necesario
    try {
        if ($result['success'] && isset($data['orderId'])) {
            $emailService = new EmailService();
            
            switch ($webhookType) {
                case 'payment.completed':
                case 'transaction.completed':
                    $emailService->sendPaymentConfirmation($data['orderId'], $data);
                    break;
                    
                case 'payment.failed':
                case 'transaction.failed':
                    $emailService->sendPaymentFailed($data['orderId'], $data);
                    break;
            }
        }
    } catch (Exception $e) {
        error_log("Error enviando notificación de webhook: " . $e->getMessage());
    }

    // Respuesta exitosa
    $response = [
        'success' => true,
        'message' => 'Webhook procesado exitosamente',
        'webhook_type' => $webhookType,
        'processed_at' => date('Y-m-d H:i:s'),
        'result' => $result
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(400);
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'webhook_type' => $webhookType ?? 'unknown',
        'received_at' => date('Y-m-d H:i:s')
    ];
    
    error_log("Error procesando webhook Bold: " . $e->getMessage());
    echo json_encode($response);
    
} catch (Error $e) {
    http_response_code(500);
    $response = [
        'success' => false,
        'error' => 'Error interno del servidor',
        'webhook_type' => $webhookType ?? 'unknown',
        'received_at' => date('Y-m-d H:i:s')
    ];
    
    error_log("Error fatal procesando webhook Bold: " . $e->getMessage());
    echo json_encode($response);
}

// Función helper para respuesta rápida a Bold
function respondToBold($success = true, $message = '') {
    $response = [
        'received' => true,
        'success' => $success,
        'message' => $message,
        'timestamp' => time()
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
    // Asegurar que Bold reciba la respuesta rápidamente
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
}
