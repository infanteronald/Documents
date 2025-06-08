<?php
/**
 * Servicio de Pagos
 * Migrado desde bold_payment.php y archivos relacionados
 */

class PaymentService 
{
    private $conn;
    private $boldConfig;
    
    public function __construct() 
    {
        require_once __DIR__ . "/../../conexion.php";
        global $conn;
        $this->conn = $conn;
        
        // Configuración de Bold
        $this->boldConfig = [
            "api_url" => "https://api.bold.co/v1/",
            "api_key" => $_ENV["BOLD_API_KEY"] ?? "",
            "webhook_secret" => $_ENV["BOLD_WEBHOOK_SECRET"] ?? ""
        ];
    }
    
    public function procesarPagoBold($datos) 
    {
        // Implementar lógica de pago Bold
        // Migrado desde bold_payment.php
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->boldConfig["api_url"] . "payments",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($datos),
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $this->boldConfig["api_key"],
                "Content-Type: application/json"
            ]
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($httpCode !== 200) {
            throw new Exception("Error en pago Bold: " . $response);
        }
        
        return json_decode($response, true);
    }
    
    public function procesarWebhookBold($payload) 
    {
        // Verificar signature del webhook
        $signature = $_SERVER["HTTP_X_BOLD_SIGNATURE"] ?? "";
        $expectedSignature = hash_hmac("sha256", $payload, $this->boldConfig["webhook_secret"]);
        
        if (!hash_equals($expectedSignature, $signature)) {
            throw new Exception("Signature inválida");
        }
        
        $data = json_decode($payload, true);
        
        // Procesar según el tipo de evento
        switch ($data["event_type"]) {
            case "payment.completed":
                return $this->procesarPagoCompletado($data);
            case "payment.failed":
                return $this->procesarPagoFallido($data);
            default:
                return ["status" => "ignored"];
        }
    }
    
    private function procesarPagoCompletado($data) 
    {
        // Actualizar estado del pedido
        $pedidoId = $data["metadata"]["pedido_id"] ?? null;
        
        if ($pedidoId) {
            $stmt = $this->conn->prepare("UPDATE pedidos_detal SET estado = ?, pago_id = ? WHERE id = ?");
            $estado = "pagado";
            $stmt->bind_param("ssi", $estado, $data["payment_id"], $pedidoId);
            $stmt->execute();
        }
        
        return ["status" => "processed"];
    }
    
    private function procesarPagoFallido($data) 
    {
        // Manejar pago fallido
        $pedidoId = $data["metadata"]["pedido_id"] ?? null;
        
        if ($pedidoId) {
            $stmt = $this->conn->prepare("UPDATE pedidos_detal SET estado = ?, notas = ? WHERE id = ?");
            $estado = "pago_fallido";
            $notas = "Pago fallido: " . ($data["failure_reason"] ?? "Motivo desconocido");
            $stmt->bind_param("ssi", $estado, $notas, $pedidoId);
            $stmt->execute();
        }
        
        return ["status" => "processed"];
    }
    
    public function procesarPagoManual($datos) 
    {
        // Implementar pago manual
        $pedidoId = $datos["pedido_id"];
        $metodo = $datos["metodo"];
        $monto = $datos["monto"];
        
        $stmt = $this->conn->prepare("UPDATE pedidos_detal SET estado = ?, metodo_pago = ?, monto_pagado = ? WHERE id = ?");
        $estado = "pagado_manual";
        $stmt->bind_param("ssdi", $estado, $metodo, $monto, $pedidoId);
        
        if ($stmt->execute()) {
            return ["success" => true, "message" => "Pago manual registrado"];
        }
        
        throw new Exception("Error al registrar pago manual");
    }
}