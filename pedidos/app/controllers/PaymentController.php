<?php
/**
 * Controlador de Pagos
 * Migrado desde bold_payment.php y archivos relacionados
 */

require_once __DIR__ . "/../services/PaymentService.php";

class PaymentController 
{
    private $paymentService;
    
    public function __construct() 
    {
        $this->paymentService = new PaymentService();
    }
    
    /**
     * Procesar pago con Bold
     * Migrado desde bold_payment.php
     */
    public function processBoldPayment() 
    {
        try {
            $input = file_get_contents("php://input");
            $data = json_decode($input, true);
            
            $result = $this->paymentService->procesarPagoBold($data);
            
            header("Content-Type: application/json");
            echo json_encode($result);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
    }
    
    /**
     * Webhook de Bold
     * Migrado desde bold_webhook_enhanced.php
     */
    public function handleBoldWebhook() 
    {
        try {
            $input = file_get_contents("php://input");
            $result = $this->paymentService->procesarWebhookBold($input);
            
            header("Content-Type: application/json");
            echo json_encode($result);
            
        } catch (Exception $e) {
            error_log("Error webhook Bold: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
    }
    
    /**
     * Procesar pago manual
     * Migrado desde procesar_pago_manual.php
     */
    public function processManualPayment() 
    {
        try {
            $input = file_get_contents("php://input");
            $data = json_decode($input, true);
            
            $result = $this->paymentService->procesarPagoManual($data);
            
            header("Content-Type: application/json");
            echo json_encode($result);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
    }
}