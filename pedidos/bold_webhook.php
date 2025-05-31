<?php
/**
 * Bold PSE Webhook Handler
 * Maneja las notificaciones de Bold cuando se completa/falla un pago
 */

require_once "conexion.php";

// Log de depuración
error_log("Bold Webhook recibido: " . file_get_contents('php://input'));

// Permitir GET solo para diagnóstico (mostrar estado)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/html; charset=utf-8');    echo '<!DOCTYPE html>';
    echo '<html><head><title>Bold Webhook Status</title><link rel="icon" type="image/x-icon" href="favicon.ico">';
    echo '<style>body{font-family:Arial;padding:20px;background:#f5f5f5;}';
    echo '.status{background:white;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);}';
    echo '.ok{color:#28a745;} .error{color:#dc3545;}</style></head><body>';
    echo '<div class="status">';
    echo '<h2>🔗 Bold PSE Webhook - Sequoia Speed</h2>';
    echo '<p class="ok">✅ Webhook está funcionando correctamente</p>';
    echo '<p><strong>URL:</strong> ' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '</p>';
    echo '<p><strong>Método esperado:</strong> POST (Bold envía automáticamente)</p>';
    echo '<p><strong>Estado:</strong> Listo para recibir notificaciones de Bold</p>';
    echo '<hr>';
    echo '<p>📝 <strong>Para configurar en Bold:</strong></p>';
    echo '<ol>';
    echo '<li>Panel Bold → Configuración → Webhooks</li>';
    echo '<li>URL: https://sequoiaspeed.com.co/pedidos/bold_webhook.php</li>';
    echo '<li>Eventos: payment_intent.succeeded, payment_intent.failed</li>';
    echo '</ol>';
    echo '<hr>';
    echo '<p><a href="test_webhook.php">🧪 Ir a página de pruebas</a> | ';
    echo '<a href="index.php">🏠 Inicio</a></p>';
    echo '</div></body></html>';
    exit;
}

// Verificar que sea un POST para el webhook real
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido - Use POST para webhooks');
}

// Obtener datos del webhook
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    exit('Datos inválidos');
}

// Log del evento recibido
error_log("Bold Webhook data: " . print_r($data, true));

try {
    // Validar estructura del webhook
    if (!isset($data['type']) || !isset($data['data'])) {
        throw new Exception('Estructura de webhook inválida');
    }

    $eventType = $data['type'];
    $paymentData = $data['data'];

    // Extraer información relevante
    $orderId = $paymentData['order_id'] ?? '';
    $transactionId = $paymentData['transaction_id'] ?? '';
    $amount = $paymentData['amount'] ?? 0;
    $currency = $paymentData['currency'] ?? 'COP';
    $status = $paymentData['status'] ?? '';
    $paymentMethod = $paymentData['payment_method'] ?? 'PSE Bold';

    // Validar que tenemos los datos mínimos necesarios
    if (empty($orderId) || empty($status)) {
        throw new Exception('Datos de pago incompletos');
    }

    // Procesar según el tipo de evento
    switch ($eventType) {
        case 'payment.success':
        case 'payment.approved':
            handlePaymentSuccess($orderId, $transactionId, $amount, $currency, $paymentData);
            break;
            
        case 'payment.failed':
        case 'payment.rejected':
            handlePaymentFailed($orderId, $transactionId, $paymentData);
            break;
            
        case 'payment.pending':
            handlePaymentPending($orderId, $transactionId, $paymentData);
            break;
            
        default:
            error_log("Tipo de evento Bold no manejado: $eventType");
            break;
    }

    // Responder OK a Bold
    http_response_code(200);
    echo "OK";

} catch (Exception $e) {
    error_log("Error en webhook Bold: " . $e->getMessage());
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}

/**
 * Manejar pago exitoso
 */
function handlePaymentSuccess($orderId, $transactionId, $amount, $currency, $paymentData) {
    global $conn;
    
    error_log("Procesando pago exitoso para orden: $orderId");
      // Buscar el pedido por ID de orden Bold
    $stmt = $conn->prepare("SELECT * FROM pedidos_detal WHERE bold_order_id = ? OR id = ?");
    $stmt->bind_param("ss", $orderId, $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Si no existe el pedido, crearlo con la información disponible
        createPendingOrder($orderId, $transactionId, $amount, $currency, $paymentData);
    } else {
        // Actualizar pedido existente
        $pedido = $result->fetch_assoc();
        updateOrderPaymentStatus($pedido['id'], 'pagado', $transactionId, $paymentData);
    }
}

/**
 * Manejar pago fallido
 */
function handlePaymentFailed($orderId, $transactionId, $paymentData) {
    global $conn;
    
    error_log("Procesando pago fallido para orden: $orderId");
      $stmt = $conn->prepare("UPDATE pedidos_detal SET estado_pago = 'fallido', bold_transaction_id = ?, bold_response = ? WHERE bold_order_id = ?");
    $response = json_encode($paymentData);
    $stmt->bind_param("sss", $transactionId, $response, $orderId);
    $stmt->execute();
}

/**
 * Manejar pago pendiente
 */
function handlePaymentPending($orderId, $transactionId, $paymentData) {
    global $conn;
    
    error_log("Procesando pago pendiente para orden: $orderId");
      $stmt = $conn->prepare("UPDATE pedidos_detal SET estado_pago = 'pendiente', bold_transaction_id = ?, bold_response = ? WHERE bold_order_id = ?");
    $response = json_encode($paymentData);
    $stmt->bind_param("sss", $transactionId, $response, $orderId);
    $stmt->execute();
}

/**
 * Crear orden pendiente desde webhook
 */
function createPendingOrder($orderId, $transactionId, $amount, $currency, $paymentData) {
    global $conn;
    
    // Extraer datos del cliente si están disponibles
    $customerEmail = $paymentData['customer']['email'] ?? '';
    $customerName = $paymentData['customer']['full_name'] ?? 'Cliente Bold';
    $customerPhone = $paymentData['customer']['phone'] ?? '';
      $stmt = $conn->prepare("
        INSERT INTO pedidos_detal (
            bold_order_id, 
            bold_transaction_id, 
            nombre, 
            correo, 
            telefono, 
            metodo_pago, 
            monto, 
            estado_pago, 
            bold_response,
            fecha
        ) VALUES (?, ?, ?, ?, ?, 'PSE Bold', ?, 'pagado', ?, NOW())
    ");
    
    $response = json_encode($paymentData);
    $stmt->bind_param("sssssds", $orderId, $transactionId, $customerName, $customerEmail, $customerPhone, $amount, $response);
    $stmt->execute();
    
    error_log("Orden creada desde webhook: $orderId");
}

/**
 * Actualizar estado de pago de orden existente
 */
function updateOrderPaymentStatus($pedidoId, $estado, $transactionId, $paymentData) {
    global $conn;
      $stmt = $conn->prepare("
        UPDATE pedidos_detal 
        SET estado_pago = ?, 
            bold_transaction_id = ?, 
            bold_response = ?,
            fecha_pago = NOW()
        WHERE id = ?
    ");
    
    $response = json_encode($paymentData);
    $stmt->bind_param("sssi", $estado, $transactionId, $response, $pedidoId);
    $stmt->execute();
    
    error_log("Orden actualizada: $pedidoId - Estado: $estado");
    
    // Opcional: Enviar notificación por email
    // sendPaymentNotification($pedidoId, $estado);
}

/**
 * Función opcional para enviar notificaciones por email
 */
function sendPaymentNotification($pedidoId, $estado) {
    // Implementar envío de email de confirmación
    // TODO: Integrar con servicio de email
}

// Cambiar todas las consultas para usar pedidos_detal
if (isset($data['order_id'])) {
    $order_id = $data['order_id'];
    
    // Buscar el pedido en pedidos_detal
    $stmt = $conn->prepare("SELECT id FROM pedidos_detal WHERE bold_order_id = ?");
    $stmt->bind_param("s", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $pedido = $result->fetch_assoc();
        
        // Actualizar estado del pago en pedidos_detal
        $stmt_update = $conn->prepare("UPDATE pedidos_detal SET 
            bold_transaction_id = ?, 
            estado_pago = ?, 
            fecha_pago = NOW(), 
            bold_response = ? 
            WHERE bold_order_id = ?");
        
        $transaction_id = $data['transaction_id'] ?? '';
        $payment_status = $data['status'] ?? 'pendiente';
        $bold_response_json = json_encode($data);
        
        $stmt_update->bind_param("ssss", $transaction_id, $payment_status, $bold_response_json, $order_id);
        $stmt_update->execute();
        
        error_log("Webhook: Pedido actualizado en pedidos_detal - Order ID: $order_id, Status: $payment_status");
        
        $stmt_update->close();
    } else {
        error_log("Webhook: No se encontró pedido con bold_order_id: $order_id en pedidos_detal");
    }
    
    $stmt->close();
}
?>
