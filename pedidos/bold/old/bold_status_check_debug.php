<?php
// Bold Status Check V6 - Debugging Simple
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // Obtener datos del request
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !isset($data['order_number'])) {
        throw new Exception('order_number es requerido');
    }

    $orderNumber = trim($data['order_number']);

    // Intentar conectar a la base de datos
    require_once __DIR__ . '/../conexion.php';

    if (!isset($conn) || !$conn) {
        throw new Exception('Error de conexión a base de datos');
    }    // Buscar el pedido (método compatible)
    $stmt = $conn->prepare("SELECT id, bold_order_id, estado_pago, monto FROM pedidos_detal WHERE bold_order_id = ? OR id = ? LIMIT 1");

    if (!$stmt) {
        throw new Exception('Error preparando consulta: ' . $conn->error);
    }

    $stmt->bind_param("ss", $orderNumber, $orderNumber);
    $stmt->execute();

    // Usar bind_result en lugar de get_result (compatible con versiones antiguas)
    $pedidoId = null;
    $boldOrderId = null;
    $estadoPago = null;
    $monto = null;

    $stmt->bind_result($pedidoId, $boldOrderId, $estadoPago, $monto);

    if (!$stmt->fetch()) {
        echo json_encode([
            'success' => true,
            'payment_completed' => false,
            'payment_failed' => false,
            'status' => 'not_found',
            'message' => 'Pedido no encontrado',
            'order_number' => $orderNumber
        ]);
        exit;
    }

    $stmt->close();

    // Determinar estado del pago
    $estadoPagoLower = strtolower($estadoPago);
    $paymentCompleted = false;
    $paymentFailed = false;
    $status = 'pending';
    $message = 'Pago en proceso';

    switch ($estadoPagoLower) {
        case 'pagado':
        case 'aprobado':
        case 'completed':
        case 'success':
            $paymentCompleted = true;
            $status = 'completed';
            $message = 'Pago completado exitosamente';
            break;

        case 'rechazado':
        case 'failed':
        case 'rejected':
        case 'error':
            $paymentFailed = true;
            $status = 'failed';
            $message = 'Pago rechazado o fallido';
            break;

        default:
            $status = 'pending';
            $message = 'Pago pendiente de confirmación';
    }

    // Respuesta
    echo json_encode([
        'success' => true,
        'payment_completed' => $paymentCompleted,
        'payment_failed' => $paymentFailed,
        'status' => $status,
        'message' => $message,
        'order_number' => $orderNumber,
        'order_id' => $pedidoId,
        'amount' => $monto,
        'debug' => [
            'estado_pago_original' => $estadoPago,
            'estado_pago_lower' => $estadoPagoLower,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'file' => __FILE__,
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
}
?>
