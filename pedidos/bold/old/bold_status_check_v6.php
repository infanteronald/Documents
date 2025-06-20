<?php
// Bold Status Check V6 - Verificación de estado de pagos
require_once __DIR__ . '/../conexion.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Función de logging
function logCheckV6($message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [STATUS_V6] {$message}";
    if ($data) $logEntry .= "\nData: " . json_encode($data);
    $logEntry .= "\n";
    @file_put_contents(__DIR__ . '/logs/status_check_v6.log', $logEntry, FILE_APPEND | LOCK_EX);
}

try {
    // Crear directorio de logs si no existe
    if (!is_dir(__DIR__ . '/logs')) {
        mkdir(__DIR__ . '/logs', 0755, true);
    }

    // Obtener datos del request
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !isset($data['order_number'])) {
        throw new Exception('order_number es requerido');
    }

    $orderNumber = trim($data['order_number']);
    logCheckV6("Verificando estado para orden: {$orderNumber}");

    // Buscar el pedido en la base de datos
    $stmt = $conn->prepare("
        SELECT
            id,
            bold_order_id,
            bold_transaction_id,
            estado_pago,
            monto,
            metodo_pago,
            fecha,
            fecha_pago,
            bold_response,
            estado
        FROM pedidos_detal
        WHERE bold_order_id = ? OR id = ?
        ORDER BY fecha DESC
        LIMIT 1
    ");

    $stmt->bind_param("ss", $orderNumber, $orderNumber);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        logCheckV6("Pedido no encontrado: {$orderNumber}");

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

    $pedido = $result->fetch_assoc();

    logCheckV6("Pedido encontrado", [
        'id' => $pedido['id'],
        'estado_pago' => $pedido['estado_pago'],
        'transaction_id' => $pedido['bold_transaction_id']
    ]);

    // Determinar estado del pago
    $estadoPago = strtolower($pedido['estado_pago']);
    $paymentCompleted = false;
    $paymentFailed = false;
    $status = 'pending';
    $message = 'Pago en proceso';

    switch ($estadoPago) {
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

        case 'pendiente':
        case 'pending':
        case 'processing':
            $status = 'pending';
            $message = 'Pago pendiente de confirmación';
            break;

        case 'cancelado':
        case 'cancelled':
            $paymentFailed = true;
            $status = 'cancelled';
            $message = 'Pago cancelado';
            break;

        default:
            $status = 'unknown';
            $message = 'Estado de pago desconocido';
    }

    // Respuesta completa
    $response = [
        'success' => true,
        'payment_completed' => $paymentCompleted,
        'payment_failed' => $paymentFailed,
        'status' => $status,
        'message' => $message,
        'order_number' => $orderNumber,
        'order_id' => $pedido['id'],
        'transaction_id' => $pedido['bold_transaction_id'],
        'amount' => $pedido['monto'],
        'payment_method' => $pedido['metodo_pago'],
        'created_at' => $pedido['fecha'],
        'paid_at' => $pedido['fecha_pago']
    ];

    logCheckV6("Estado verificado exitosamente", $response);
    echo json_encode($response);

} catch (Exception $e) {
    logCheckV6("Error verificando estado: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'order_number' => $orderNumber ?? 'unknown'
    ]);
}
?>
