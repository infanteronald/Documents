<?php
// Bold Status Check - Endpoint para verificar estado de pagos Bold V6
require_once '../config_secure.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Función de logging
function logCheck($message, $data = null) {
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

    // Manejar tanto GET como POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Nuevo formato JSON para v6
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data || !isset($data['order_number'])) {
            throw new Exception('order_number es requerido en POST JSON');
        }

        $orderNumber = trim($data['order_number']);
        logCheck("POST request para orden: {$orderNumber}");

        $result = checkPaymentStatusV6($orderNumber);
        echo json_encode($result);

    } else {
        // Formato GET legacy
        $action = $_GET['action'] ?? '';
        $order_id = $_GET['order_id'] ?? '';
        $pedido_id = $_GET['pedido_id'] ?? '';

        switch ($action) {
            case 'check_status':
                if (empty($order_id) && empty($pedido_id)) {
                    throw new Exception('Se requiere order_id o pedido_id');
                }

                $search_id = !empty($order_id) ? $order_id : $pedido_id;
                $result = checkPaymentStatusLegacy($search_id);
                echo json_encode($result);
                break;

            case 'system_status':
                $result = getSystemStatus();
                echo json_encode($result);
                break;

            default:
                throw new Exception('Acción no válida');
        }
    }
} catch (Exception $e) {
    logCheck("Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Nueva función para v6 (formato mejorado)
function checkPaymentStatusV6($orderNumber)
{
    global $conn;

    try {
        logCheck("Verificando estado v6 para: {$orderNumber}");

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
            logCheck("Pedido no encontrado: {$orderNumber}");

            return [
                'success' => true,
                'payment_completed' => false,
                'payment_failed' => false,
                'status' => 'not_found',
                'message' => 'Pedido no encontrado',
                'order_number' => $orderNumber
            ];
        }

        $pedido = $result->fetch_assoc();

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

            default:
                $status = 'unknown';
                $message = 'Estado de pago desconocido';
        }

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
            'payment_method' => $pedido['metodo_pago']
        ];

        logCheck("Estado verificado exitosamente", $response);
        return $response;

    } catch (Exception $e) {
        logCheck("Error verificando estado: " . $e->getMessage());
        throw $e;
    }
}

// Función legacy para compatibilidad
function checkPaymentStatusLegacy($search_id)
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
