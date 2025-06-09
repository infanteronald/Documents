<?php
// Bold Payment Callback - Versión simple sin dependencias externas
require_once "conexion.php";

// Headers básicos
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

// Función simple de logging
function simpleLog($message) {
    $timestamp = date("Y-m-d H:i:s");
    error_log("[$timestamp] Bold Callback: $message");
}

// Función de respuesta
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

try {
    simpleLog("Callback iniciado");
    
    // Obtener datos
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);
    
    if (empty($data)) {
        $data = array_merge($_GET, $_POST);
    }
    
    simpleLog("Datos: " . json_encode($data));
    
    // Extraer información básica
    $order_id = $data["order_id"] ?? $data["orderId"] ?? $data["reference"] ?? null;
    $status = $data["status"] ?? $data["state"] ?? "unknown";
    $transaction_id = $data["transaction_id"] ?? $data["id"] ?? null;
    
    if (!$order_id) {
        simpleLog("Error: Order ID no encontrado");
        jsonResponse(["error" => "Order ID required"], 400);
    }
    
    // Buscar pedido
    $sql = "SELECT * FROM pedidos_detal WHERE id = ? OR bold_order_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$order_id, $order_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pedido) {
        simpleLog("Error: Pedido no encontrado - $order_id");
        jsonResponse(["error" => "Order not found"], 404);
    }
    
    // Determinar nuevo estado
    $nuevo_estado = "pendiente";
    $estado_pago = "pendiente";
    
    switch (strtolower($status)) {
        case "approved":
        case "success":
        case "successful":
            $nuevo_estado = "confirmado";
            $estado_pago = "pagado";
            break;
        case "declined":
        case "failed":
        case "error":
            $nuevo_estado = "cancelado";
            $estado_pago = "fallido";
            break;
    }
    
    // Actualizar pedido
    $update_sql = "UPDATE pedidos_detal SET 
                   estado = ?, 
                   estado_pago = ?,
                   bold_transaction_id = ?,
                   bold_status = ?,
                   bold_callback_data = ?
                   WHERE id = ?";
    
    $stmt = $conn->prepare($update_sql);
    $result = $stmt->execute([
        $nuevo_estado,
        $estado_pago,
        $transaction_id,
        $status,
        json_encode($data),
        $pedido["id"]
    ]);
    
    if ($result) {
        simpleLog("Pedido actualizado: " . $pedido["id"] . " - Estado: $status");
        jsonResponse([
            "success" => true,
            "order_id" => $pedido["id"],
            "status" => $status,
            "new_state" => $nuevo_estado
        ]);
    } else {
        simpleLog("Error actualizando pedido");
        jsonResponse(["error" => "Update failed"], 500);
    }
    
} catch (Exception $e) {
    simpleLog("Exception: " . $e->getMessage());
    jsonResponse(["error" => "Server error"], 500);
}
?>
