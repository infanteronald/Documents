<?php
// Bold Status Check - Endpoint para verificar estado de pagos Bold
require_once 'conexion.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $action = $_GET['action'] ?? '';
    $order_id = $_GET['order_id'] ?? '';
    $pedido_id = $_GET['pedido_id'] ?? '';

    switch ($action) {
        case 'check_status':
            if (empty($order_id) && empty($pedido_id)) {
                throw new Exception('Se requiere order_id o pedido_id');
            }

            $search_id = !empty($order_id) ? $order_id : $pedido_id;
            $result = checkPaymentStatus($search_id);
            echo json_encode($result);
            break;

        case 'system_status':
            $result = getSystemStatus();
            echo json_encode($result);
            break;

        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function checkPaymentStatus($search_id)
{
    global $conn;

    try {
        $sql = "SELECT id, bold_order_id, bold_transaction_id, estado_pago, fecha_pedido,
                       metodo_pago, total, nombre_cliente, telefono_cliente, email_cliente
                FROM pedidos_detal
                WHERE id = ? OR bold_order_id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->execute([$search_id, $search_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return [
                'success' => false,
                'message' => 'Pedido no encontrado',
                'order_id' => $search_id
            ];
        }

        return [
            'success' => true,
            'order' => $order,
            'has_bold_data' => !empty($order['bold_order_id'])
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function getSystemStatus()
{
    global $conn;

    try {
        $system_info = [
            'timestamp' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'database_connected' => $conn ? true : false
        ];

        return [
            'success' => true,
            'system_info' => $system_info
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
