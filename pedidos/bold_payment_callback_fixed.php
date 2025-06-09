<?php
// Bold Payment Callback - Procesador de resultados de pago Bold (Versión corregida sin SMTP)
require_once "conexion.php";
require_once "bold_unified_logger.php";

// Configuración de headers para evitar errores CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Manejar preflight requests
if ($_SERVER["REQUEST_METHOD"] == "OPTIONS") {
    http_response_code(200);
    exit();
}

// Función para logging simplificado
function logMessage($message, $level = "INFO") {
    $timestamp = date("Y-m-d H:i:s");
    error_log("[$timestamp] [$level] Bold Callback: $message");
}

// Función para respuesta JSON
function sendJsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

try {
    logMessage("Iniciando procesamiento de callback Bold");
    
    // Obtener datos del callback
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);
    
    // También revisar $_GET y $_POST
    if (empty($data)) {
        $data = array_merge($_GET, $_POST);
    }
    
    logMessage("Datos recibidos: " . json_encode($data));
    
    // Validar datos mínimos
    if (empty($data)) {
        logMessage("No se recibieron datos en el callback", "ERROR");
        sendJsonResponse(["error" => "No data received"], 400);
    }
    
    // Extraer información del pago
    $order_id = $data["order_id"] ?? $data["orderId"] ?? $data["reference"] ?? null;
    $transaction_id = $data["transaction_id"] ?? $data["transactionId"] ?? $data["id"] ?? null;
    $status = $data["status"] ?? $data["state"] ?? "unknown";
    $amount = $data["amount"] ?? $data["total"] ?? 0;
    $payment_method = $data["payment_method"] ?? $data["paymentMethod"] ?? "Bold";
    
    logMessage("Order ID: $order_id, Transaction ID: $transaction_id, Status: $status");
    
    if (!$order_id) {
        logMessage("Order ID no encontrado en los datos", "ERROR");
        sendJsonResponse(["error" => "Order ID not found"], 400);
    }
    
    // Log en sistema unificado
    BoldUnifiedLogger::logWebhook($order_id, "payment_callback", $data, "processing");
    BoldUnifiedLogger::logBoldTransaction($order_id, $transaction_id, $amount, $status, $payment_method, $data);
    
    // Buscar el pedido en la base de datos
    $sql = "SELECT * FROM pedidos_detal WHERE id = ? OR bold_order_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$order_id, $order_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pedido) {
        logMessage("Pedido no encontrado: $order_id", "ERROR");
        BoldUnifiedLogger::logActivity($order_id, "error", "Pedido no encontrado en callback", "error");
        sendJsonResponse(["error" => "Order not found"], 404);
    }
    
    logMessage("Pedido encontrado: " . $pedido["id"]);
    
    // Determinar estado del pago basado en el status de Bold
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
        case "pending":
        case "processing":
            $nuevo_estado = "pendiente";
            $estado_pago = "pendiente";
            break;
    }
    
    // Actualizar el pedido
    $update_sql = "UPDATE pedidos_detal SET 
                   estado = ?, 
                   estado_pago = ?,
                   bold_transaction_id = ?,
                   bold_status = ?,
                   bold_amount = ?,
                   bold_payment_method = ?,
                   bold_callback_data = ?,
                   bold_updated_at = NOW()
                   WHERE id = ?";
    
    $stmt = $conn->prepare($update_sql);
    $result = $stmt->execute([
        $nuevo_estado,
        $estado_pago,
        $transaction_id,
        $status,
        $amount,
        $payment_method,
        json_encode($data),
        $pedido["id"]
    ]);
    
    if ($result) {
        logMessage("Pedido actualizado exitosamente: " . $pedido["id"]);
        BoldUnifiedLogger::logActivity($pedido["id"], "payment_updated", "Pago actualizado vía callback - Estado: $status", "success");
        BoldUnifiedLogger::updateStats("payment_callback_success");
        
        sendJsonResponse([
            "success" => true,
            "message" => "Payment processed successfully",
            "order_id" => $pedido["id"],
            "status" => $status,
            "new_state" => $nuevo_estado
        ]);
    } else {
        logMessage("Error actualizando pedido: " . $pedido["id"], "ERROR");
        BoldUnifiedLogger::logActivity($pedido["id"], "error", "Error actualizando pago en callback", "error");
        sendJsonResponse(["error" => "Failed to update order"], 500);
    }
    
} catch (Exception $e) {
    logMessage("Error en callback: " . $e->getMessage(), "ERROR");
    BoldUnifiedLogger::logActivity($order_id ?? "unknown", "error", "Exception en callback: " . $e->getMessage(), "error");
    sendJsonResponse(["error" => "Internal server error"], 500);
}
?>
