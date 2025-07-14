<?php
/**
 * API para actualizar estado de pago Bold
 * Recibe notificaciones desde la ventana de pago
 */

// Configurar headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Función para responder con JSON
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Obtener datos JSON del cuerpo de la petición
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    jsonResponse(['success' => false, 'error' => 'Datos JSON inválidos'], 400);
}

// Validar campos requeridos
$required_fields = ['order_id', 'status'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        jsonResponse(['success' => false, 'error' => "Campo requerido: $field"], 400);
    }
}

$order_id = trim($data['order_id']);
$status = trim($data['status']);
$amount = intval($data['amount'] ?? 0);
$transaction_id = trim($data['transaction_id'] ?? '');
$payment_method = trim($data['payment_method'] ?? 'PSE Bold');

// Log de debugging
error_log("Bold Status Update - Order: $order_id, Status: $status");

try {
    // Configuración de base de datos
    $db_config = [
        'host' => 'localhost',
        'username' => 'motodota_pedidos',
        'password' => 'Blink.182...',
        'database' => 'motodota_pedidos'
    ];

    // Conectar a la base de datos
    $conn = new mysqli(
        $db_config['host'],
        $db_config['username'],
        $db_config['password'],
        $db_config['database']
    );

    if ($conn->connect_error) {
        throw new Exception('Error de conexión: ' . $conn->connect_error);
    }

    $conn->set_charset("utf8");

    // Verificar si el pedido existe
    $stmt = $conn->prepare("SELECT id FROM pedidos_detal WHERE bold_order_id = ? LIMIT 1");
    $stmt->bind_param("s", $order_id);
    $stmt->execute();

    // Usar bind_result para compatibilidad
    $stmt->bind_result($pedido_id);

    if (!$stmt->fetch()) {
        $stmt->close();
        // Crear nuevo registro si no existe
        $stmt = $conn->prepare("INSERT INTO pedidos_detal (bold_order_id, estado_pago, monto, descuento, metodo_pago, bold_transaction_id, fecha) VALUES (?, ?, ?, 0, ?, ?, NOW())");
        $stmt->bind_param("ssiss", $order_id, $status, $amount, $payment_method, $transaction_id);

        if ($stmt->execute()) {
            error_log("Bold Status Update - Nuevo pedido creado: $order_id");
            jsonResponse([
                'success' => true,
                'action' => 'created',
                'order_id' => $order_id,
                'status' => $status
            ]);
        } else {
            throw new Exception('Error creando pedido: ' . $stmt->error);
        }
    } else {
        // Actualizar registro existente
        $update_fields = ['estado_pago = ?'];
        $params = [$status];
        $types = 's';

        if ($amount > 0) {
            $update_fields[] = 'monto = ?';
            $params[] = $amount;
            $types .= 'i';
        }

        if (!empty($transaction_id)) {
            $update_fields[] = 'bold_transaction_id = ?';
            $params[] = $transaction_id;
            $types .= 's';
        }

        $update_fields[] = 'fecha_actualizacion = NOW()';
        $params[] = $order_id;
        $types .= 's';

        $sql = "UPDATE pedidos_detal SET " . implode(', ', $update_fields) . " WHERE bold_order_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            error_log("Bold Status Update - Pedido actualizado: $order_id");
            jsonResponse([
                'success' => true,
                'action' => 'updated',
                'order_id' => $order_id,
                'status' => $status,
                'affected_rows' => $stmt->affected_rows
            ]);
        } else {
            throw new Exception('Error actualizando pedido: ' . $stmt->error);
        }
    }

} catch (Exception $e) {
    error_log("Bold Status Update - Error: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'error' => 'Error del servidor: ' . $e->getMessage()
    ], 500);
}
?>
