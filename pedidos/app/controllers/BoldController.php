<?php
/**
 * Controlador de Bold PSE - Sequoia Speed
 * Archivo: app/controllers/BoldController.php
 */

namespace SequoiaSpeed\Controllers;

use SequoiaSpeed\Services\BoldWebhookService;
use Exception;

class BoldController
{
    private $boldService;
    private $pedidoModel;
    
    public function __construct()
    {
        $this->boldService = new BoldWebhookService();
        require_once dirname(dirname(__DIR__)) . "/app/models/Pedido.php";
        require_once dirname(dirname(__DIR__)) . "/config_secure.php";
        global $conn;
        $this->pedidoModel = new \Pedido($conn);
    }
    
    /**
     * Procesa webhooks de Bold PSE
     */
    public function webhook()
    {
        try {
            // Obtener datos del webhook
            $input = file_get_contents('php://input');
            $webhookData = json_decode($input, true);
            
            // Validar que sea un webhook válido
            if (!$this->boldService->validateWebhook($webhookData)) {
                http_response_code(400);
                return [
                    'success' => false,
                    'message' => 'Webhook inválido'
                ];
            }
            
            // Procesar el webhook
            $resultado = $this->boldService->processWebhook($webhookData);
            
            if ($resultado['success']) {
                http_response_code(200);
            } else {
                http_response_code(500);
            }
            
            return $resultado;
            
        } catch (Exception $e) {
            error_log("Error en BoldController::webhook - " . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'message' => 'Error interno del servidor',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Crea una nueva transacción Bold PSE
     */
    public function createTransaction($pedidoId)
    {
        try {
            $pedido = $this->pedidoModel->getById($pedidoId);
            
            if (!$pedido) {
                return [
                    'success' => false,
                    'message' => 'Pedido no encontrado'
                ];
            }
            
            // Verificar que el pedido esté en estado válido para pago
            if (!in_array($pedido['estado'], ['pendiente', 'confirmado'])) {
                return [
                    'success' => false,
                    'message' => 'El pedido no está en un estado válido para procesar pago'
                ];
            }
            
            $transactionData = [
                'order_id' => $pedidoId,
                'amount' => $pedido['total'],
                'currency' => 'COP',
                'description' => "Pago pedido #{$pedidoId} - " . $pedido['cliente_nombre'],
                'redirect_url' => $_ENV['APP_URL'] . "/payment-success.php?order_id={$pedidoId}",
                'webhook_url' => $_ENV['APP_URL'] . "/api/bold/webhook.php",
                'customer' => [
                    'name' => $pedido['cliente_nombre'],
                    'email' => $pedido['cliente_email'] ?? '',
                    'phone' => $pedido['cliente_telefono'],
                    'identification' => $pedido['cliente_cedula'] ?? ''
                ]
            ];
            
            $transaction = $this->boldService->createTransaction($transactionData);
            
            if ($transaction['success']) {
                // Actualizar el pedido con el ID de transacción
                $this->pedidoModel->update($pedidoId, [
                    'bold_transaction_id' => $transaction['data']['transaction_id'],
                    'estado' => 'pago_pendiente'
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Transacción creada exitosamente',
                    'data' => $transaction['data']
                ];
            } else {
                return $transaction;
            }
            
        } catch (Exception $e) {
            error_log("Error en BoldController::createTransaction - " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al crear transacción Bold',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Consulta el estado de una transacción Bold
     */
    public function checkTransactionStatus($transactionId)
    {
        try {
            $status = $this->boldService->getTransactionStatus($transactionId);
            
            if ($status['success']) {
                // Actualizar el estado del pedido si es necesario
                $pedido = $this->pedidoModel->getByBoldTransactionId($transactionId);
                
                if ($pedido) {
                    $this->boldService->updateOrderStatus($pedido['id'], $status['data']);
                }
            }
            
            return $status;
            
        } catch (Exception $e) {
            error_log("Error en BoldController::checkTransactionStatus - " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al consultar estado de transacción',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtiene todas las transacciones de un período
     */
    public function getTransactions($fechaInicio = null, $fechaFin = null)
    {
        try {
            $transactions = $this->boldService->getTransactions($fechaInicio, $fechaFin);
            
            return [
                'success' => true,
                'data' => $transactions
            ];
            
        } catch (Exception $e) {
            error_log("Error en BoldController::getTransactions - " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al obtener transacciones',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Procesa una reversión/reembolso
     */
    public function reverseTransaction($transactionId, $motivo = null)
    {
        try {
            $reversal = $this->boldService->reverseTransaction($transactionId, $motivo);
            
            if ($reversal['success']) {
                // Actualizar el estado del pedido
                $pedido = $this->pedidoModel->getByBoldTransactionId($transactionId);
                
                if ($pedido) {
                    $this->pedidoModel->updateStatus($pedido['id'], 'reembolsado', $motivo);
                }
            }
            
            return $reversal;
            
        } catch (Exception $e) {
            error_log("Error en BoldController::reverseTransaction - " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al procesar reversión',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtiene estadísticas de pagos Bold
     */
    public function getPaymentStats($fechaInicio = null, $fechaFin = null)
    {
        try {
            $stats = $this->boldService->getPaymentStats($fechaInicio, $fechaFin);
            
            return [
                'success' => true,
                'data' => $stats
            ];
            
        } catch (Exception $e) {
            error_log("Error en BoldController::getPaymentStats - " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al obtener estadísticas de pagos',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verifica la conectividad con Bold PSE
     */
    public function testConnection()
    {
        try {
            $test = $this->boldService->testConnection();
            
            return [
                'success' => true,
                'message' => 'Conexión exitosa con Bold PSE',
                'data' => $test
            ];
            
        } catch (Exception $e) {
            error_log("Error en BoldController::testConnection - " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error de conexión con Bold PSE',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Maneja errores de webhook
     */
    public function handleWebhookError($error)
    {
        try {
            // Log del error
            error_log("Error en webhook Bold PSE: " . json_encode($error));
            
            // Enviar notificación a administradores si es crítico
            if (isset($error['level']) && $error['level'] === 'critical') {
                $this->boldService->notifyAdmins($error);
            }
            
            return [
                'success' => true,
                'message' => 'Error de webhook procesado'
            ];
            
        } catch (Exception $e) {
            error_log("Error en BoldController::handleWebhookError - " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al procesar error de webhook',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Genera reporte de reconciliación
     */
    public function generateReconciliationReport($fecha)
    {
        try {
            $reporte = $this->boldService->generateReconciliationReport($fecha);
            
            return [
                'success' => true,
                'data' => $reporte,
                'message' => 'Reporte de reconciliación generado exitosamente'
            ];
            
        } catch (Exception $e) {
            error_log("Error en BoldController::generateReconciliationReport - " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al generar reporte de reconciliación',
                'error' => $e->getMessage()
            ];
        }
    }
}
