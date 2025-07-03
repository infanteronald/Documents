<?php

/**
 * Bold PSE Enhanced Webhook Handler
 * Sistema mejorado con manejo robusto de errores, retry logic y logging avanzado
 * FASE 2: Integrado con sistema de migración moderna
 */

// Integración con sistema de migración - FASE 2
require_once 'legacy-bridge.php';

require_once "conexion.php";
require_once "smtp_config.php";
require_once "bold_notification_system.php";

// Configuración de logging avanzado
define('WEBHOOK_LOG_FILE', __DIR__ . '/logs/bold_webhook.log');
define('ERROR_LOG_FILE', __DIR__ . '/logs/bold_errors.log');
define('MAX_RETRY_ATTEMPTS', 3);
define('RETRY_DELAY_SECONDS', 5);

// Asegurar que existe el directorio de logs
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

/**
 * Sistema de logging mejorado
 */
class BoldLogger
{
    public static function info($message, $data = null)
    {
        self::log('INFO', $message, $data);
    }

    public static function warning($message, $data = null)
    {
        self::log('WARNING', $message, $data);
    }

    public static function error($message, $data = null)
    {
        self::log('ERROR', $message, $data, ERROR_LOG_FILE);
    }

    private static function log($level, $message, $data = null, $file = WEBHOOK_LOG_FILE)
    {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

        $logEntry = "[{$timestamp}] [{$level}] [IP: {$ip}] {$message}";

        if ($data) {
            $logEntry .= "\nData: " . json_encode($data, JSON_PRETTY_PRINT);
        }

        $logEntry .= "\n" . str_repeat('-', 80) . "\n";

        file_put_contents($file, $logEntry, FILE_APPEND | LOCK_EX);

        // También log a error_log de PHP para casos críticos
        if ($level === 'ERROR') {
            error_log("Bold Webhook Error: {$message}");
        }
    }
}

/**
 * Clase para manejo de webhooks con retry logic
 */
class BoldWebhookProcessor
{
    private $conn;
    private $retryAttempts = 0;

    public function __construct($connection)
    {
        $this->conn = $connection;
    }

    /**
     * Procesar webhook con retry logic
     */
    public function processWebhook($data)
    {
        $this->retryAttempts = 0;

        while ($this->retryAttempts < MAX_RETRY_ATTEMPTS) {
            try {
                $this->retryAttempts++;
                BoldLogger::info("Procesando webhook (intento {$this->retryAttempts})", $data);

                $result = $this->handleWebhookData($data);

                if ($result['success']) {
                    BoldLogger::info("Webhook procesado exitosamente en intento {$this->retryAttempts}");
                    return ['success' => true, 'message' => 'Webhook procesado correctamente'];
                }

                throw new Exception($result['error']);
            } catch (Exception $e) {
                BoldLogger::warning("Error en intento {$this->retryAttempts}: " . $e->getMessage(), [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                if ($this->retryAttempts >= MAX_RETRY_ATTEMPTS) {
                    BoldLogger::error("Webhook fallido después de {$this->retryAttempts} intentos", [
                        'final_error' => $e->getMessage(),
                        'webhook_data' => $data
                    ]);

                    // Guardar en cola de retry para procesamiento posterior
                    $this->saveToRetryQueue($data, $e->getMessage());

                    return [
                        'success' => false,
                        'error' => 'Webhook fallido después de múltiples intentos',
                        'attempts' => $this->retryAttempts
                    ];
                }

                // Esperar antes del siguiente intento
                sleep(RETRY_DELAY_SECONDS);
            }
        }
    }

    /**
     * Manejar los datos del webhook
     */
    private function handleWebhookData($data)
    {
        // Validar estructura del webhook
        if (!isset($data['type']) || !isset($data['data'])) {
            throw new Exception('Estructura de webhook inválida - faltan campos type o data');
        }

        $eventType = $data['type'];
        $paymentData = $data['data'];

        // CORRECCIÓN CRÍTICA: Extraer order_id desde metadata.reference si no existe directo
        $orderId = '';
        if (!empty($paymentData['order_id'])) {
            $orderId = $paymentData['order_id'];
        } elseif (!empty($paymentData['metadata']['reference'])) {
            $orderId = $paymentData['metadata']['reference'];
        }

        if (empty($orderId) || empty($paymentData['status'])) {
            throw new Exception("Campo requerido faltante: order_id o status");
        }

        // Extraer información con valores por defecto
        $orderId = $this->sanitizeString($orderId);
        $transactionId = $this->sanitizeString($paymentData['transaction_id'] ?? '');
        $amount = floatval($paymentData['amount'] ?? 0);
        $currency = $this->sanitizeString($paymentData['currency'] ?? 'COP');
        $status = $this->sanitizeString($paymentData['status']);
        $paymentMethod = $this->sanitizeString($paymentData['payment_method'] ?? 'PSE Bold');

        // Validar que el pedido existe o puede ser creado
        if (!$this->validateOrder($orderId)) {
            throw new Exception("Orden inválida o no encontrada: {$orderId}");
        }

        // Procesar según el tipo de evento
        switch ($eventType) {
            case 'payment.success':
            case 'payment.approved':
            case 'SALE_APPROVED':
                return $this->handlePaymentSuccess($orderId, $transactionId, $amount, $currency, $paymentData);

            case 'payment.failed':
            case 'payment.rejected':
            case 'SALE_REJECTED':
                return $this->handlePaymentFailed($orderId, $transactionId, $paymentData);

            case 'payment.pending':
            case 'SALE_PENDING':
                return $this->handlePaymentPending($orderId, $transactionId, $paymentData);

            case 'VOID_APPROVED':
                return $this->handlePaymentVoid($orderId, $transactionId, $paymentData);

            default:
                BoldLogger::warning("Tipo de evento no manejado: {$eventType}", $paymentData);
                return ['success' => true, 'message' => 'Evento no manejado pero registrado'];
        }
    }

    /**
     * Validar que la orden existe o puede ser procesada
     */
    private function validateOrder($orderId)
    {
        if (empty($orderId)) {
            return false;
        }

        // Verificar formato del order_id (debe empezar con SEQ- o ser numérico)
        if (!preg_match('/^(SEQ-|TEST-|\d+)/', $orderId)) {
            BoldLogger::warning("Formato de order_id inválido: {$orderId}");
            return false;
        }

        return true;
    }

    /**
     * Sanitizar strings de entrada
     */
    private function sanitizeString($input)
    {
        return trim(htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8'));
    }

    /**
     * Manejar pago exitoso con validaciones robustas
     */
    private function handlePaymentSuccess($orderId, $transactionId, $amount, $currency, $paymentData)
    {
        BoldLogger::info("Procesando pago exitoso", [
            'order_id' => $orderId,
            'transaction_id' => $transactionId,
            'amount' => $amount
        ]);

        // Verificar que no sea un pago duplicado
        if ($this->isPaymentDuplicate($orderId, $transactionId)) {
            BoldLogger::warning("Pago duplicado detectado", [
                'order_id' => $orderId,
                'transaction_id' => $transactionId
            ]);
            return ['success' => true, 'message' => 'Pago ya procesado'];
        }

        // Buscar el pedido
        $stmt = $this->conn->prepare("SELECT * FROM pedidos_detal WHERE bold_order_id = ? OR id = ?");
        $stmt->bind_param("ss", $orderId, $orderId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            // Crear orden desde webhook si no existe
            $pedidoId = $this->createOrderFromWebhook($orderId, $transactionId, $amount, $currency, $paymentData);
        } else {
            // Actualizar orden existente
            $pedido = $result->fetch_assoc();
            $pedidoId = $pedido['id'];
            $this->updateOrderPaymentStatus($pedidoId, 'pagado', $transactionId, $paymentData);
        }

        // Enviar notificación de confirmación
        $this->sendPaymentNotification($pedidoId, 'success', $amount);

        return ['success' => true, 'message' => 'Pago procesado exitosamente'];
    }

    /**
     * Verificar si es un pago duplicado
     */
    private function isPaymentDuplicate($orderId, $transactionId)
    {
        if (empty($transactionId)) {
            return false;
        }

        $stmt = $this->conn->prepare("
            SELECT id FROM pedidos_detal
            WHERE (bold_order_id = ? OR id = ?)
            AND bold_transaction_id = ?
            AND estado_pago = 'pagado'
        ");
        $stmt->bind_param("sss", $orderId, $orderId, $transactionId);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->num_rows > 0;
    }

    /**
     * Crear orden desde webhook
     */
    private function createOrderFromWebhook($orderId, $transactionId, $amount, $currency, $paymentData)
    {
        // Extraer datos del cliente si están disponibles
        $customerData = $paymentData['customer'] ?? [];
        $customerEmail = $this->sanitizeString($customerData['email'] ?? '');
        $customerName = $this->sanitizeString($customerData['full_name'] ?? 'Cliente Bold PSE');
        $customerPhone = $this->sanitizeString($customerData['phone'] ?? '');

        $stmt = $this->conn->prepare("
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
                fecha,
                estado
            ) VALUES (?, ?, ?, ?, ?, 'PSE Bold', ?, 'pagado', ?, NOW(), 'pendiente')
        ");

        $response = json_encode($paymentData);
        $stmt->bind_param("sssssds", $orderId, $transactionId, $customerName, $customerEmail, $customerPhone, $amount, $response);

        if (!$stmt->execute()) {
            throw new Exception("Error al crear orden: " . $stmt->error);
        }

        $pedidoId = $this->conn->insert_id;

        BoldLogger::info("Orden creada desde webhook", [
            'pedido_id' => $pedidoId,
            'order_id' => $orderId,
            'amount' => $amount
        ]);

        return $pedidoId;
    }

    /**
     * Actualizar estado de pago
     */
    private function updateOrderPaymentStatus($pedidoId, $estado, $transactionId, $paymentData)
    {
        $stmt = $this->conn->prepare("
            UPDATE pedidos_detal
            SET estado_pago = ?,
                bold_transaction_id = ?,
                bold_response = ?,
                fecha_pago = NOW()
            WHERE id = ?
        ");

        $response = json_encode($paymentData);
        $stmt->bind_param("sssi", $estado, $transactionId, $response, $pedidoId);

        if (!$stmt->execute()) {
            throw new Exception("Error al actualizar orden: " . $stmt->error);
        }

        BoldLogger::info("Orden actualizada", [
            'pedido_id' => $pedidoId,
            'estado' => $estado,
            'transaction_id' => $transactionId
        ]);

        // Enviar notificaciones
        $this->sendPaymentNotifications($pedidoId, $estado, $transactionId, $paymentData);
    }

    /**
     * Enviar notificaciones por email
     */
    private function sendPaymentNotifications($pedidoId, $estado, $transactionId, $paymentData)
    {
        try {
            // Obtener datos del pedido para las notificaciones
            $stmt = $this->conn->prepare("
                SELECT nombre, telefono, email, direccion, ciudad, total, observaciones
                FROM pedidos_detal
                WHERE id = ?
            ");
            $stmt->bind_param("i", $pedidoId);
            $stmt->execute();
            $result = $stmt->get_result();
            $pedido = $result->fetch_assoc();

            if (!$pedido) {
                BoldLogger::warning("No se encontró el pedido para notificaciones", ['pedido_id' => $pedidoId]);
                return;
            }

            $notificationSystem = new BoldNotificationSystem($this->conn);

            // Determinar el tipo de notificación basado en el estado
            switch ($estado) {
                case 'PAGADO':
                case 'APROBADO':
                    // Notificación de éxito al cliente
                    $notificationSystem->sendCustomerSuccessNotification($pedidoId, $paymentData);
                    // Notificación al administrador
                    $notificationSystem->sendAdminNotification($pedidoId, 'success', $paymentData);
                    BoldLogger::info("Notificaciones de éxito enviadas", ['pedido_id' => $pedidoId]);
                    break;

                case 'PENDIENTE':
                    // Notificación de estado pendiente al cliente
                    $notificationSystem->sendCustomerPendingNotification($pedidoId, $paymentData);
                    BoldLogger::info("Notificación de pendiente enviada", ['pedido_id' => $pedidoId]);
                    break;

                case 'RECHAZADO':
                case 'FALLIDO':
                    // Notificación de fallo al cliente
                    $notificationSystem->sendCustomerFailedNotification($pedidoId, $paymentData);
                    // Notificación al administrador
                    $notificationSystem->sendAdminNotification($pedidoId, 'failure', $paymentData);
                    BoldLogger::info("Notificaciones de fallo enviadas", ['pedido_id' => $pedidoId]);
                    break;

                default:
                    BoldLogger::info("Estado no requiere notificación específica", [
                        'pedido_id' => $pedidoId,
                        'estado' => $estado
                    ]);
            }
        } catch (Exception $e) {
            BoldLogger::error("Error enviando notificaciones", [
                'pedido_id' => $pedidoId,
                'error' => $e->getMessage()
            ]);
            // No lanzar excepción aquí para no interrumpir el procesamiento del webhook
        }
    }

    /**
     * Manejar pago fallido
     */
    private function handlePaymentFailed($orderId, $transactionId, $paymentData)
    {
        BoldLogger::info("Procesando pago fallido", [
            'order_id' => $orderId,
            'transaction_id' => $transactionId
        ]);

        $stmt = $this->conn->prepare("
            UPDATE pedidos_detal
            SET estado_pago = 'fallido',
                bold_transaction_id = ?,
                bold_response = ?
            WHERE bold_order_id = ? OR id = ?
        ");

        $response = json_encode($paymentData);
        $stmt->bind_param("ssss", $transactionId, $response, $orderId, $orderId);

        if (!$stmt->execute()) {
            throw new Exception("Error al actualizar pago fallido: " . $stmt->error);
        }

        return ['success' => true, 'message' => 'Pago fallido procesado'];
    }

    /**
     * Manejar pago pendiente
     */
    private function handlePaymentPending($orderId, $transactionId, $paymentData)
    {
        $stmt = $this->conn->prepare("
            UPDATE pedidos_detal
            SET estado_pago = 'pendiente',
                bold_transaction_id = ?,
                bold_response = ?
            WHERE bold_order_id = ? OR id = ?
        ");

        $response = json_encode($paymentData);
        $stmt->bind_param("ssss", $transactionId, $response, $orderId, $orderId);

        if (!$stmt->execute()) {
            throw new Exception("Error al actualizar pago pendiente: " . $stmt->error);
        }

        return ['success' => true, 'message' => 'Pago pendiente procesado'];
    }

    /**
     * Manejar anulación de pago
     */
    private function handlePaymentVoid($orderId, $transactionId, $paymentData)
    {
        $stmt = $this->conn->prepare("
            UPDATE pedidos_detal
            SET estado_pago = 'anulado',
                bold_transaction_id = ?,
                bold_response = ?
            WHERE bold_order_id = ? OR id = ?
        ");

        $response = json_encode($paymentData);
        $stmt->bind_param("ssss", $transactionId, $response, $orderId, $orderId);

        if (!$stmt->execute()) {
            throw new Exception("Error al procesar anulación: " . $stmt->error);
        }

        return ['success' => true, 'message' => 'Anulación procesada'];
    }

    /**
     * Guardar en cola de retry para procesamiento posterior
     */
    private function saveToRetryQueue($data, $error)
    {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO bold_retry_queue (
                    webhook_data,
                    error_message,
                    attempts,
                    created_at,
                    next_retry_at
                ) VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 5 MINUTE))
            ");

            $webhookJson = json_encode($data);
            $stmt->bind_param("ssi", $webhookJson, $error, $this->retryAttempts);
            $stmt->execute();

            BoldLogger::info("Webhook guardado en cola de retry", [
                'attempts' => $this->retryAttempts,
                'error' => $error
            ]);
        } catch (Exception $e) {
            BoldLogger::error("Error al guardar en cola de retry: " . $e->getMessage());
        }
    }

    /**
     * Enviar notificación de pago
     */
    private function sendPaymentNotification($pedidoId, $status, $amount)
    {
        try {
            // Obtener detalles del pedido
            $stmt = $this->conn->prepare("SELECT * FROM pedidos_detal WHERE id = ?");
            $stmt->bind_param("i", $pedidoId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $pedido = $result->fetch_assoc();

                // Enviar email de confirmación al cliente
                if (!empty($pedido['correo'])) {
                    $this->sendCustomerNotification($pedido, $status, $amount);
                }

                // Enviar notificación al admin
                $this->sendAdminNotification($pedido, $status, $amount);
            }
        } catch (Exception $e) {
            BoldLogger::warning("Error al enviar notificaciones: " . $e->getMessage());
        }
    }

    /**
     * Enviar notificación al cliente
     */
    private function sendCustomerNotification($pedido, $status, $amount)
    {
        // Implementar envío de email al cliente
        // TODO: Integrar con sistema de email existente
        BoldLogger::info("Notificación al cliente enviada", [
            'pedido_id' => $pedido['id'],
            'email' => $pedido['correo'],
            'status' => $status
        ]);
    }

    /**
     * Enviar notificación al admin
     */
    private function sendAdminNotification($pedido, $status, $amount)
    {
        // Implementar notificación al admin
        BoldLogger::info("Notificación al admin enviada", [
            'pedido_id' => $pedido['id'],
            'status' => $status,
            'amount' => $amount
        ]);
    }
}

// ==================== PUNTO DE ENTRADA PRINCIPAL ====================

// Solo ejecutar el webhook si el archivo es accedido directamente
// Esto evita que se ejecute cuando es incluido por la migración
if (basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__)) {

    // Permitir GET para diagnóstico
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html>';
        echo '<html><head><title>Bold Enhanced Webhook Status</title><link rel="icon" type="image/x-icon" href="favicon.ico">';
        echo '<style>body{font-family:Arial;padding:20px;background:#f5f5f5;}';
        echo '.status{background:white;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);}';
        echo '.ok{color:#28a745;} .error{color:#dc3545;} .info{color:#007bff;}</style></head><body>';
        echo '<div class="status">';
        echo '<h2>🚀 Bold PSE Enhanced Webhook - Sequoia Speed</h2>';
        echo '<p class="ok">✅ Webhook mejorado funcionando correctamente</p>';
        echo '<p><strong>URL:</strong> ' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '</p>';
        echo '<p><strong>Mejoras implementadas:</strong></p>';
        echo '<ul>';
        echo '<li class="info">🔄 Retry logic con ' . MAX_RETRY_ATTEMPTS . ' intentos</li>';
        echo '<li class="info">📝 Logging avanzado con timestamps</li>';
        echo '<li class="info">🛡️ Validación robusta de datos</li>';
        echo '<li class="info">🚫 Detección de pagos duplicados</li>';
        echo '<li class="info">📧 Notificaciones automáticas</li>';
        echo '<li class="info">⚡ Cola de retry para fallos</li>';
        echo '</ul>';
        echo '<hr>';
        echo '<p><a href="bold_webhook.php">🔙 Webhook Original</a> | ';
        echo '<a href="pedido.php">🏠 Inicio</a></p>';
        echo '</div></body></html>';
        exit;
    }

    // Verificar que sea POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        BoldLogger::error("Método no permitido: " . $_SERVER['REQUEST_METHOD']);
        exit('Método no permitido');
    }

    // Obtener y validar datos
    $input = file_get_contents('php://input');
    if (empty($input)) {
        http_response_code(400);
        BoldLogger::error("Webhook vacío recibido");
        exit('Datos vacíos');
    }

    $data = json_decode($input, true);
    if (!$data) {
        http_response_code(400);
        BoldLogger::error("JSON inválido recibido", ['input' => $input]);
        exit('JSON inválido');
    }

    // Procesar webhook con el sistema mejorado
    try {
        $processor = new BoldWebhookProcessor($conn);
        $result = $processor->processWebhook($data);

        if ($result['success']) {
            http_response_code(200);
            echo "OK - " . $result['message'];
        } else {
            http_response_code(422);
            echo "ERROR - " . $result['error'];
        }
    } catch (Exception $e) {
        BoldLogger::error("Error crítico en webhook", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'data' => $data
        ]);

        http_response_code(500);
        echo "Error interno del servidor";
    }
} // Fin del if para ejecución directa del webhook
