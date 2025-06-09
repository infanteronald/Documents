<?php
// Bold Payment Result Handler - Manejo mejorado de resultados de pago
require_once "conexion.php";

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

function logPaymentResult($message, $data = []) {
    $timestamp = date("Y-m-d H:i:s");
    $logData = json_encode(array_merge($data, ["timestamp" => $timestamp]));
    error_log("[$timestamp] Bold Payment Result: $message - Data: $logData");
}

function processPaymentResult($data) {
    global $conn;
    
    logPaymentResult("Procesando resultado de pago", $data);
    
    // Extraer información del pago
    $order_id = $data["order_id"] ?? $data["orderId"] ?? $data["reference"] ?? null;
    $transaction_id = $data["transaction_id"] ?? $data["transactionId"] ?? $data["id"] ?? null;
    $status = $data["status"] ?? $data["state"] ?? "unknown";
    $amount = $data["amount"] ?? $data["total"] ?? 0;
    $payment_method = $data["payment_method"] ?? $data["paymentMethod"] ?? "Bold";
    
    if (!$order_id) {
        return ["success" => false, "error" => "Order ID no encontrado"];
    }
    
    // Buscar el pedido
    $sql = "SELECT * FROM pedidos_detal WHERE id = ? OR bold_order_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$order_id, $order_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pedido) {
        logPaymentResult("Pedido no encontrado", ["order_id" => $order_id]);
        return ["success" => false, "error" => "Pedido no encontrado"];
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
        logPaymentResult("Pedido actualizado exitosamente", [
            "pedido_id" => $pedido["id"],
            "nuevo_estado" => $nuevo_estado,
            "estado_pago" => $estado_pago,
            "transaction_id" => $transaction_id
        ]);
        
        return [
            "success" => true,
            "order_id" => $pedido["id"],
            "status" => $status,
            "new_state" => $nuevo_estado,
            "payment_status" => $estado_pago,
            "transaction_id" => $transaction_id,
            "amount" => $amount
        ];
    } else {
        logPaymentResult("Error actualizando pedido", ["pedido_id" => $pedido["id"]]);
        return ["success" => false, "error" => "Error al actualizar pedido"];
    }
}

// Función para obtener información detallada de un pago
function getPaymentDetails($order_id) {
    global $conn;
    
    $sql = "SELECT 
                id, 
                bold_order_id,
                bold_transaction_id,
                bold_status,
                bold_amount,
                bold_payment_method,
                bold_callback_data,
                bold_updated_at,
                estado,
                estado_pago,
                nombre,
                metodo_pago,
                monto,
                fecha_pedido
            FROM pedidos_detal 
            WHERE id = ? OR bold_order_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$order_id, $order_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pedido) {
        return ["success" => false, "error" => "Pedido no encontrado"];
    }
    
    // Decodificar datos del callback si existen
    $callback_data = null;
    if ($pedido["bold_callback_data"]) {
        $callback_data = json_decode($pedido["bold_callback_data"], true);
    }
    
    return [
        "success" => true,
        "payment_info" => [
            "order_id" => $pedido["id"],
            "bold_order_id" => $pedido["bold_order_id"],
            "transaction_id" => $pedido["bold_transaction_id"],
            "status" => $pedido["bold_status"],
            "amount" => $pedido["bold_amount"],
            "payment_method" => $pedido["bold_payment_method"],
            "updated_at" => $pedido["bold_updated_at"],
            "order_status" => $pedido["estado"],
            "payment_status" => $pedido["estado_pago"],
            "customer_name" => $pedido["nombre"],
            "original_method" => $pedido["metodo_pago"],
            "original_amount" => $pedido["monto"],
            "order_date" => $pedido["fecha_pedido"],
            "callback_data" => $callback_data
        ]
    ];
}

// Manejar la petición
try {
    $method = $_SERVER["REQUEST_METHOD"];
    
    if ($method === "POST") {
        // Procesar resultado de pago
        $input = file_get_contents("php://input");
        $data = json_decode($input, true);
        
        if (empty($data)) {
            $data = array_merge($_GET, $_POST);
        }
        
        $result = processPaymentResult($data);
        echo json_encode($result);
        
    } elseif ($method === "GET" && isset($_GET["order_id"])) {
        // Obtener información de pago
        $result = getPaymentDetails($_GET["order_id"]);
        echo json_encode($result);
        
    } else {
        echo json_encode([
            "success" => false, 
            "error" => "Método no permitido o parámetros faltantes"
        ]);
    }
    
} catch (Exception $e) {
    logPaymentResult("Exception en payment result handler", ["error" => $e->getMessage()]);
    echo json_encode([
        "success" => false, 
        "error" => "Error interno del servidor"
    ]);
}
?>
