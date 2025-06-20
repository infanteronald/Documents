<?php
/**
 * API de verificación de estado de pago Bold
 * Permite verificar si un pago fue exitoso desde la ventana de pago
 */

// Configurar manejo de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Configurar headers antes que nada
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Función para responder con JSON y salir
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Obtener parámetros de forma segura
$order_id = trim($_GET['order_id'] ?? $_POST['order_id'] ?? '');

if (empty($order_id)) {
    jsonResponse([
        'success' => false,
        'error' => 'order_id requerido'
    ], 400);
}

// Log de debugging
error_log("Bold Status API - Checking order: " . $order_id);

try {
    // Incluir conexión desde el directorio correcto
    $conexion_path = __DIR__ . '/../conexion.php';

    if (!file_exists($conexion_path)) {
        throw new Exception('Archivo de conexión no encontrado: ' . $conexion_path);
    }

    require_once $conexion_path;

    if (!isset($conn) || !$conn) {
        throw new Exception('Conexión a base de datos no disponible');
    }

    // Verificar conexión
    if ($conn->connect_error) {
        throw new Exception('Error de conexión: ' . $conn->connect_error);
    }

    // Buscar el pedido por bold_order_id
    $stmt = $conn->prepare("SELECT id, monto, estado_pago, bold_transaction_id, fecha FROM pedidos_detal WHERE bold_order_id = ? ORDER BY fecha DESC LIMIT 1");

    if (!$stmt) {
        throw new Exception('Error preparando consulta: ' . $conn->error);
    }

    $stmt->bind_param("s", $order_id);

    if (!$stmt->execute()) {
        throw new Exception('Error ejecutando consulta: ' . $stmt->error);
    }

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        jsonResponse([
            'success' => true,
            'payment_completed' => false,
            'status' => 'not_found',
            'message' => 'Pedido no encontrado'
        ]);
    }

    $pedido = $result->fetch_assoc();
    $stmt->close();

    // Verificar estado del pago
    $payment_completed = false;
    $payment_status = 'pending';

    if ($pedido['estado_pago'] === 'pagado' || $pedido['estado_pago'] === 'Completado') {
        $payment_completed = true;
        $payment_status = 'completed';
    } elseif ($pedido['estado_pago'] === 'fallido' || $pedido['estado_pago'] === 'Cancelado') {
        $payment_status = 'failed';
    }

    // Log del resultado
    error_log("Bold Status API - Result: payment_completed=" . ($payment_completed ? 'true' : 'false') . ", status=" . $payment_status);

    jsonResponse([
        'success' => true,
        'payment_completed' => $payment_completed,
        'status' => $payment_status,
        'order_id' => $order_id,
        'pedido_id' => $pedido['id'],
        'amount' => intval($pedido['monto']),
        'payment_method' => 'PSE Bold',
        'transaction_id' => $pedido['bold_transaction_id'] ?? null,
        'updated_at' => $pedido['fecha'] ?? null,
        'message' => $payment_completed ? 'Pago completado exitosamente' : 'Pago pendiente'
    ]);

} catch (Exception $e) {
    error_log("Bold Status API - Error: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'error' => 'Error del servidor: ' . $e->getMessage()
    ], 500);
}
?>
