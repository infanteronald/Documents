<?php
/**
 * API de verificación de estado de pago Bold - Versión Robusta
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

try {
    // Log de debugging con más información
    error_log("Bold Status API - Checking order: " . $order_id);
    error_log("Bold Status API - Script iniciado en: " . __FILE__);
    error_log("Bold Status API - Directorio actual: " . __DIR__);

    // Usar archivo de conexión existente
    require_once __DIR__ . '/../config_secure.php';

    if (!isset($conn) || !$conn) {
        throw new Exception('Error: conexión a base de datos no disponible');
    }

    error_log("Bold Status API - Conexión establecida correctamente");

    // Configurar charset
    if (!$conn->set_charset("utf8")) {
        error_log("Bold Status API - Error configurando charset: " . $conn->error);
    }

    // Log de éxito de conexión
    error_log("Bold Status API - Conexión exitosa a base de datos");

    // Verificar que la tabla existe
    $result_check = $conn->query("SHOW TABLES LIKE 'pedidos_detal'");
    if ($result_check->num_rows === 0) {
        throw new Exception('Tabla pedidos_detal no encontrada');
    }
    error_log("Bold Status API - Tabla pedidos_detal encontrada");

    // Buscar el pedido por bold_order_id
    $stmt = $conn->prepare("SELECT id, monto, descuento, estado_pago, bold_transaction_id, fecha FROM pedidos_detal WHERE bold_order_id = ? ORDER BY fecha DESC LIMIT 1");

    if (!$stmt) {
        error_log("Bold Status API - Error preparando consulta: " . $conn->error);
        throw new Exception('Error preparando consulta: ' . $conn->error);
    }

    $stmt->bind_param("s", $order_id);
    error_log("Bold Status API - Consulta preparada para order_id: " . $order_id);

    if (!$stmt->execute()) {
        throw new Exception('Error ejecutando consulta: ' . $stmt->error);
    }

    // Usar bind_result para compatibilidad (en lugar de get_result)
    $pedidoId = null;
    $monto = null;
    $descuento = null;
    $estadoPago = null;
    $boldTransactionId = null;
    $fecha = null;

    $stmt->bind_result($pedidoId, $monto, $descuento, $estadoPago, $boldTransactionId, $fecha);

    if (!$stmt->fetch()) {
        error_log("Bold Status API - Pedido no encontrado: " . $order_id);
        $stmt->close();
        jsonResponse([
            'success' => true,
            'payment_completed' => false,
            'status' => 'not_found',
            'message' => 'Pedido no encontrado'
        ]);
    }

    $stmt->close();

    // Verificar estado del pago usando las variables correctas
    $payment_completed = false;
    $payment_status = 'pending';

    $estadoPagoLower = strtolower($estadoPago);

    if ($estadoPagoLower === 'pagado' || $estadoPagoLower === 'completado' || $estadoPagoLower === 'aprobado') {
        $payment_completed = true;
        $payment_status = 'completed';
    } elseif ($estadoPagoLower === 'fallido' || $estadoPagoLower === 'cancelado' || $estadoPagoLower === 'rechazado') {
        $payment_status = 'failed';
    }

    // Log del resultado
    error_log("Bold Status API - Result: payment_completed=" . ($payment_completed ? 'true' : 'false') . ", status=" . $payment_status);

    // Cerrar conexión
    $conn->close();

    jsonResponse([
        'success' => true,
        'payment_completed' => $payment_completed,
        'status' => $payment_status,
        'order_id' => $order_id,
        'pedido_id' => $pedidoId,
        'amount' => intval($monto),
        'discount' => intval($descuento ?? 0),
        'subtotal' => intval($monto + ($descuento ?? 0)),
        'payment_method' => 'PSE Bold',
        'transaction_id' => $boldTransactionId,
        'updated_at' => $fecha,
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
