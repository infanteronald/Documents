<?php
// Bold Payment Result Handler - Versión simplificada
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

function logPaymentResult($message, $data = []) {
    $timestamp = date("Y-m-d H:i:s");
    error_log("[$timestamp] Bold Payment Result: $message - " . json_encode($data));
}

try {
    $method = $_SERVER["REQUEST_METHOD"];
    
    if ($method === "POST") {
        // Procesar resultado de pago
        $input = file_get_contents("php://input");
        $data = json_decode($input, true);
        
        if (empty($data)) {
            $data = array_merge($_GET, $_POST);
        }
        
        logPaymentResult("Resultado de pago recibido", $data);
        
        // Extraer información del pago
        $order_id = $data["order_id"] ?? $data["orderId"] ?? $data["reference"] ?? null;
        $transaction_id = $data["transaction_id"] ?? $data["transactionId"] ?? $data["id"] ?? null;
        $status = $data["status"] ?? $data["state"] ?? "unknown";
        $amount = $data["amount"] ?? $data["total"] ?? 0;
        $payment_method = $data["payment_method"] ?? $data["paymentMethod"] ?? "Bold";
        
        if (!$order_id) {
            echo json_encode(["success" => false, "error" => "Order ID no encontrado"]);
            exit;
        }
        
        // Determinar nuevo estado
        $nuevo_estado = "pendiente";
        $estado_pago = "pendiente";
        
        switch (strtolower($status)) {
            case "approved":
            case "success":
            case "successful":
            case "completed":
                $nuevo_estado = "confirmado";
                $estado_pago = "pagado";
                break;
            case "declined":
            case "failed":
            case "error":
            case "rejected":
                $nuevo_estado = "cancelado";
                $estado_pago = "fallido";
                break;
        }
        
        logPaymentResult("Procesando pago", [
            "order_id" => $order_id,
            "status" => $status,
            "nuevo_estado" => $nuevo_estado,
            "estado_pago" => $estado_pago
        ]);
        
        echo json_encode([
            "success" => true,
            "message" => "Resultado de pago procesado correctamente",
            "order_id" => $order_id,
            "status" => $status,
            "new_state" => $nuevo_estado,
            "payment_status" => $estado_pago,
            "transaction_id" => $transaction_id,
            "amount" => $amount,
            "payment_method" => $payment_method,
            "timestamp" => date("Y-m-d H:i:s")
        ]);
        
    } elseif ($method === "GET") {
        // Información básica
        $order_id = $_GET["order_id"] ?? null;
        
        if ($order_id) {
            echo json_encode([
                "success" => true,
                "message" => "Consulta de pago procesada",
                "order_id" => $order_id,
                "timestamp" => date("Y-m-d H:i:s")
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "error" => "Order ID requerido para consulta"
            ]);
        }
        
    } else {
        echo json_encode([
            "success" => false, 
            "error" => "Método no permitido"
        ]);
    }
    
} catch (Exception $e) {
    logPaymentResult("Exception en payment result handler", ["error" => $e->getMessage()]);
    echo json_encode([
        "success" => false, 
        "error" => "Error interno del servidor: " . $e->getMessage()
    ]);
}
?>
