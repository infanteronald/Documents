<?php
/**
 * Verificar Estado de Pago Bold
 * Permite verificar el estado de una transacción Bold desde el frontend
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once "conexion.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Obtener datos del POST
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['order_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'order_id es requerido']);
    exit;
}

$order_id = $input['order_id'];

try {
    // Buscar en tabla de pedidos por bold_order_id
    $sql = "SELECT estado_pago, bold_transaction_id, fecha_pago 
            FROM pedidos_detal 
            WHERE bold_order_id = ? 
            ORDER BY fecha DESC LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error preparando consulta: " . $conn->error);
    }
    
    $stmt->bind_param("s", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $pedido = $result->fetch_assoc();
        
        // Pedido encontrado en nuestra base de datos
        $response = [
            'success' => true,
            'found' => true,
            'status' => $pedido['estado_pago'] ?: 'pendiente',
            'transaction_id' => $pedido['bold_transaction_id'],
            'payment_date' => $pedido['fecha_pago'],
            'order_id' => $order_id
        ];
        
    } else {
        // Pedido no encontrado aún en nuestra BD (puede estar procesándose)
        $response = [
            'success' => true,
            'found' => false,
            'status' => 'processing',
            'message' => 'Transacción en procesamiento',
            'order_id' => $order_id
        ];
    }
    
    // Log de la consulta para debugging
    error_log("Check payment status - Order ID: $order_id, Found: " . ($response['found'] ? 'Yes' : 'No') . ", Status: " . $response['status']);
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error en check_payment_status: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
    ]);
}

$conn->close();
?>
