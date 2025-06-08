<?php
/**
 * Servicio de Webhooks Bold PSE - Sequoia Speed
 * Archivo: app/services/BoldWebhookService.php
 */

namespace SequoiaSpeed\Services;

use SequoiaSpeed\Models\Pedido;

class BoldWebhookService
{
    private $config;
    private $pedidoModel;
    private $logFile;
    
    public function __construct()
    {
        $this->config = include dirname(dirname(__DIR__)) . '/app/config/bold.php';
        $this->pedidoModel = new Pedido();
        $this->logFile = dirname(dirname(__DIR__)) . '/storage/logs/bold_webhooks.log';
    }
    
    /**
     * Valida si un webhook es válido
     */
    public function validateWebhook($webhookData)
    {
        try {
            // Verificar que tenga los campos requeridos
            if (!isset($webhookData['type']) || !isset($webhookData['data'])) {
                $this->log('ERROR', 'Webhook inválido: faltan campos requeridos', $webhookData);
                return false;
            }
            
            // Verificar firma si está configurada
            if (!empty($this->config['webhook_secret'])) {
                $signature = $_SERVER['HTTP_X_BOLD_SIGNATURE'] ?? '';
                if (!$this->verifySignature($webhookData, $signature)) {
                    $this->log('ERROR', 'Webhook inválido: firma incorrecta', $webhookData);
                    return false;
                }
            }
            
            $this->log('INFO', 'Webhook válido recibido', $webhookData);
            return true;
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Error validando webhook: ' . $e->getMessage(), $webhookData);
            return false;
        }
    }
    
    /**
     * Procesa un webhook de Bold PSE
     */
    public function processWebhook($webhookData)
    {
        try {
            $type = $webhookData['type'];
            $data = $webhookData['data'];
            
            $this->log('INFO', "Procesando webhook tipo: {$type}", $data);
            
            switch ($type) {
                case 'payment.approved':
                    return $this->processPaymentApproved($data);
                    
                case 'payment.declined':
                    return $this->processPaymentDeclined($data);
                    
                case 'payment.pending':
                    return $this->processPaymentPending($data);
                    
                case 'payment.refunded':
                    return $this->processPaymentRefunded($data);
                    
                case 'payment.cancelled':
                    return $this->processPaymentCancelled($data);
                    
                default:
                    $this->log('WARNING', "Tipo de webhook no manejado: {$type}", $data);
                    return [
                        'success' => true,
                        'message' => 'Webhook recibido pero no procesado'
                    ];
            }
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Error procesando webhook: ' . $e->getMessage(), $webhookData);
            return [
                'success' => false,
                'message' => 'Error procesando webhook',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Procesa pago aprobado
     */
    private function processPaymentApproved($data)
    {
        try {
            $transactionId = $data['transaction_id'];
            $orderId = $data['order_id'] ?? null;
            $amount = $data['amount'] ?? 0;
            
            // Buscar pedido por transaction_id o order_id
            $pedido = null;
            if ($transactionId) {
                $pedido = $this->pedidoModel->getByBoldTransactionId($transactionId);
            }
            if (!$pedido && $orderId) {
                $pedido = $this->pedidoModel->getById($orderId);
            }
            
            if (!$pedido) {
                $this->log('ERROR', 'Pedido no encontrado para pago aprobado', $data);
                return [
                    'success' => false,
                    'message' => 'Pedido no encontrado'
                ];
            }
            
            // Verificar monto
            if ($amount != $pedido['total']) {
                $this->log('WARNING', "Monto no coincide. Esperado: {$pedido['total']}, Recibido: {$amount}", $data);
            }
            
            // Actualizar estado del pedido
            $this->pedidoModel->updateStatus($pedido['id'], 'pagado', 'Pago aprobado por Bold PSE');
            
            // Actualizar información de Bold
            $this->pedidoModel->update($pedido['id'], [
                'bold_transaction_id' => $transactionId,
                'bold_payment_data' => json_encode($data)
            ]);
            
            $this->log('INFO', "Pago aprobado procesado para pedido {$pedido['id']}", $data);
            
            // Enviar notificación
            $this->sendPaymentNotification($pedido['id'], 'approved', $data);
            
            return [
                'success' => true,
                'message' => 'Pago aprobado procesado exitosamente'
            ];
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Error procesando pago aprobado: ' . $e->getMessage(), $data);
            return [
                'success' => false,
                'message' => 'Error procesando pago aprobado',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Procesa pago rechazado
     */
    private function processPaymentDeclined($data)
    {
        try {
            $transactionId = $data['transaction_id'];
            $reason = $data['decline_reason'] ?? 'Razón no especificada';
            
            $pedido = $this->pedidoModel->getByBoldTransactionId($transactionId);
            
            if (!$pedido) {
                $this->log('ERROR', 'Pedido no encontrado para pago rechazado', $data);
                return [
                    'success' => false,
                    'message' => 'Pedido no encontrado'
                ];
            }
            
            // Actualizar estado del pedido
            $this->pedidoModel->updateStatus($pedido['id'], 'pago_rechazado', "Pago rechazado: {$reason}");
            
            $this->log('INFO', "Pago rechazado procesado para pedido {$pedido['id']}: {$reason}", $data);
            
            // Enviar notificación
            $this->sendPaymentNotification($pedido['id'], 'declined', $data);
            
            return [
                'success' => true,
                'message' => 'Pago rechazado procesado exitosamente'
            ];
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Error procesando pago rechazado: ' . $e->getMessage(), $data);
            return [
                'success' => false,
                'message' => 'Error procesando pago rechazado',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Procesa pago pendiente
     */
    private function processPaymentPending($data)
    {
        try {
            $transactionId = $data['transaction_id'];
            
            $pedido = $this->pedidoModel->getByBoldTransactionId($transactionId);
            
            if (!$pedido) {
                $this->log('ERROR', 'Pedido no encontrado para pago pendiente', $data);
                return [
                    'success' => false,
                    'message' => 'Pedido no encontrado'
                ];
            }
            
            // Actualizar estado del pedido
            $this->pedidoModel->updateStatus($pedido['id'], 'pago_pendiente', 'Pago en proceso por Bold PSE');
            
            $this->log('INFO', "Pago pendiente procesado para pedido {$pedido['id']}", $data);
            
            return [
                'success' => true,
                'message' => 'Pago pendiente procesado exitosamente'
            ];
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Error procesando pago pendiente: ' . $e->getMessage(), $data);
            return [
                'success' => false,
                'message' => 'Error procesando pago pendiente',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Procesa reembolso
     */
    private function processPaymentRefunded($data)
    {
        try {
            $transactionId = $data['transaction_id'];
            $refundAmount = $data['refund_amount'] ?? 0;
            $reason = $data['refund_reason'] ?? 'Reembolso solicitado';
            
            $pedido = $this->pedidoModel->getByBoldTransactionId($transactionId);
            
            if (!$pedido) {
                $this->log('ERROR', 'Pedido no encontrado para reembolso', $data);
                return [
                    'success' => false,
                    'message' => 'Pedido no encontrado'
                ];
            }
            
            // Actualizar estado del pedido
            $this->pedidoModel->updateStatus($pedido['id'], 'reembolsado', "Reembolsado: {$reason}");
            
            $this->log('INFO', "Reembolso procesado para pedido {$pedido['id']}: \${$refundAmount}", $data);
            
            // Enviar notificación
            $this->sendPaymentNotification($pedido['id'], 'refunded', $data);
            
            return [
                'success' => true,
                'message' => 'Reembolso procesado exitosamente'
            ];
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Error procesando reembolso: ' . $e->getMessage(), $data);
            return [
                'success' => false,
                'message' => 'Error procesando reembolso',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Procesa cancelación de pago
     */
    private function processPaymentCancelled($data)
    {
        try {
            $transactionId = $data['transaction_id'];
            $reason = $data['cancel_reason'] ?? 'Cancelado por el usuario';
            
            $pedido = $this->pedidoModel->getByBoldTransactionId($transactionId);
            
            if (!$pedido) {
                $this->log('ERROR', 'Pedido no encontrado para cancelación', $data);
                return [
                    'success' => false,
                    'message' => 'Pedido no encontrado'
                ];
            }
            
            // Actualizar estado del pedido
            $this->pedidoModel->updateStatus($pedido['id'], 'pago_cancelado', "Pago cancelado: {$reason}");
            
            $this->log('INFO', "Pago cancelado procesado para pedido {$pedido['id']}: {$reason}", $data);
            
            // Enviar notificación
            $this->sendPaymentNotification($pedido['id'], 'cancelled', $data);
            
            return [
                'success' => true,
                'message' => 'Cancelación procesada exitosamente'
            ];
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Error procesando cancelación: ' . $e->getMessage(), $data);
            return [
                'success' => false,
                'message' => 'Error procesando cancelación',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Crea una nueva transacción en Bold PSE
     */
    public function createTransaction($transactionData)
    {
        try {
            $url = $this->config['api_url'] . '/payment/create';
            
            $payload = [
                'merchant_id' => $this->config['merchant_id'],
                'order_id' => $transactionData['order_id'],
                'amount' => $transactionData['amount'],
                'currency' => $transactionData['currency'] ?? 'COP',
                'description' => $transactionData['description'],
                'redirect_url' => $transactionData['redirect_url'],
                'webhook_url' => $transactionData['webhook_url'],
                'customer' => $transactionData['customer']
            ];
            
            $response = $this->makeApiCall($url, $payload);
            
            if ($response['success']) {
                $this->log('INFO', 'Transacción creada exitosamente', $response['data']);
                return [
                    'success' => true,
                    'data' => [
                        'transaction_id' => $response['data']['transaction_id'],
                        'payment_url' => $response['data']['payment_url']
                    ]
                ];
            } else {
                $this->log('ERROR', 'Error creando transacción', $response);
                return [
                    'success' => false,
                    'message' => 'Error creando transacción en Bold PSE',
                    'error' => $response['message'] ?? 'Error desconocido'
                ];
            }
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Error en createTransaction: ' . $e->getMessage(), $transactionData);
            return [
                'success' => false,
                'message' => 'Error creando transacción',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtiene el estado de una transacción
     */
    public function getTransactionStatus($transactionId)
    {
        try {
            $url = $this->config['api_url'] . "/payment/status/{$transactionId}";
            
            $response = $this->makeApiCall($url, null, 'GET');
            
            if ($response['success']) {
                return [
                    'success' => true,
                    'data' => $response['data']
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error consultando estado de transacción',
                    'error' => $response['message'] ?? 'Error desconocido'
                ];
            }
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Error en getTransactionStatus: ' . $e->getMessage(), ['transaction_id' => $transactionId]);
            return [
                'success' => false,
                'message' => 'Error consultando transacción',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Realiza llamada a la API de Bold PSE
     */
    private function makeApiCall($url, $data = null, $method = 'POST')
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->config['api_key'],
                'Content-Type: application/json',
                'Accept: application/json'
            ]
        ]);
        
        if ($data && ($method === 'POST' || $method === 'PUT')) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception("Error de cURL: {$error}");
        }
        
        curl_close($ch);
        
        $responseData = json_decode($response, true);
        
        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'data' => $responseData,
            'message' => $responseData['message'] ?? null
        ];
    }
    
    /**
     * Verifica la firma del webhook
     */
    private function verifySignature($data, $signature)
    {
        $payload = json_encode($data);
        $expectedSignature = hash_hmac('sha256', $payload, $this->config['webhook_secret']);
        
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Envía notificación de pago
     */
    private function sendPaymentNotification($pedidoId, $type, $data)
    {
        try {
            // Aquí se integraría con el servicio de email
            // Por ahora solo registramos en log
            $this->log('INFO', "Notificación de pago enviada: {$type} para pedido {$pedidoId}", $data);
        } catch (Exception $e) {
            $this->log('ERROR', 'Error enviando notificación: ' . $e->getMessage(), $data);
        }
    }
    
    /**
     * Registra eventos en log
     */
    private function log($level, $message, $data = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message,
            'data' => $data
        ];
        
        $logLine = json_encode($logEntry) . PHP_EOL;
        
        // Crear directorio si no existe
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Prueba la conexión con Bold PSE
     */
    public function testConnection()
    {
        try {
            $url = $this->config['api_url'] . '/test';
            $response = $this->makeApiCall($url, [], 'GET');
            
            return [
                'success' => $response['success'],
                'message' => $response['success'] ? 'Conexión exitosa' : 'Error de conexión',
                'data' => $response['data']
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error de conexión: ' . $e->getMessage()
            ];
        }
    }
}
