<?php
/**
 * Bold PSE Enhanced Webhook Handler - Versión Híbrida
 * Sistema que funciona con BD remota y local para pruebas
 */

// Determinar si estamos en modo de prueba
$test_mode = isset($_GET['test_mode']) || isset($_POST['test_mode']) || 
            (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') !== false);

if ($test_mode) {
    require_once "conexion_local.php";
} else {
    require_once "conexion.php";
}

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
class BoldLogger {
    public static function info($message, $data = null) {
        self::log('INFO', $message, $data);
    }
    
    public static function warning($message, $data = null) {
        self::log('WARNING', $message, $data);
    }
    
    public static function error($message, $data = null) {
        self::log('ERROR', $message, $data, ERROR_LOG_FILE);
    }
    
    private static function log($level, $message, $data = null, $file = WEBHOOK_LOG_FILE) {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        $logEntry = "[{$timestamp}] [{$level}] [IP: {$ip}] {$message}";
        
        if ($data) {
            $logEntry .= "\nData: " . json_encode($data, JSON_PRETTY_PRINT);
        }
        
        $logEntry .= "\n" . str_repeat('-', 80) . "\n";
        
        file_put_contents($file, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Manejador de conexión de base de datos híbrido
 */
function getDatabaseConnection() {
    global $test_mode;
    
    if ($test_mode) {
        return getTestConnection();
    } else {
        global $conexion;
        return $conexion;
    }
}

/**
 * Detector de transacciones duplicadas mejorado
 */
class DuplicateDetector {
    private static $processed_transactions = [];
    
    public static function isDuplicate($transaction_id, $reference) {
        $key = $transaction_id . '_' . $reference;
        
        if (in_array($key, self::$processed_transactions)) {
            return true;
        }
        
        // Verificar en base de datos
        $conexion = getDatabaseConnection();
        if (!$conexion) return false;
        
        try {
            $stmt = $conexion->prepare("SELECT COUNT(*) FROM pedidos_bold_test WHERE bold_transaction_id = ? AND referencia = ?");
            $stmt->execute([$transaction_id, $reference]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                self::$processed_transactions[] = $key;
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            BoldLogger::error("Error verificando duplicados", ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    public static function markAsProcessed($transaction_id, $reference) {
        $key = $transaction_id . '_' . $reference;
        if (!in_array($key, self::$processed_transactions)) {
            self::$processed_transactions[] = $key;
        }
    }
}

/**
 * Procesador principal del webhook con retry logic
 */
class WebhookProcessor {
    private $data;
    private $attempt_count = 0;
    
    public function __construct($webhook_data) {
        $this->data = $webhook_data;
    }
    
    public function process() {
        BoldLogger::info("Iniciando procesamiento webhook", $this->data);
        
        // Validar estructura del webhook
        if (!$this->validateWebhookData()) {
            BoldLogger::error("Datos del webhook inválidos", $this->data);
            $this->sendErrorResponse("Invalid webhook data structure");
            return;
        }
        
        $transaction_id = $this->data['data']['id'] ?? '';
        $reference = $this->data['data']['reference'] ?? '';
        
        // Verificar duplicados
        if (DuplicateDetector::isDuplicate($transaction_id, $reference)) {
            BoldLogger::warning("Webhook duplicado detectado", [
                'transaction_id' => $transaction_id,
                'reference' => $reference
            ]);
            $this->sendSuccessResponse("Duplicate transaction ignored");
            return;
        }
        
        // Procesar con retry logic
        $this->processWithRetry();
    }
    
    private function processWithRetry() {
        $this->attempt_count++;
        
        try {
            $this->updateTransaction();
            DuplicateDetector::markAsProcessed(
                $this->data['data']['id'] ?? '',
                $this->data['data']['reference'] ?? ''
            );
            $this->sendSuccessResponse("Transaction processed successfully");
            
        } catch (Exception $e) {
            BoldLogger::error("Error en intento {$this->attempt_count}", [
                'error' => $e->getMessage(),
                'data' => $this->data
            ]);
            
            if ($this->attempt_count < MAX_RETRY_ATTEMPTS) {
                sleep(RETRY_DELAY_SECONDS);
                $this->processWithRetry();
            } else {
                $this->sendErrorResponse("Failed after " . MAX_RETRY_ATTEMPTS . " attempts");
            }
        }
    }
    
    private function validateWebhookData() {
        return isset($this->data['data']) && 
               isset($this->data['data']['id']) && 
               isset($this->data['data']['reference']) &&
               isset($this->data['data']['status']);
    }
    
    private function updateTransaction() {
        $conexion = getDatabaseConnection();
        if (!$conexion) {
            throw new Exception("Database connection failed");
        }
        
        $transaction_data = $this->data['data'];
        $transaction_id = $transaction_data['id'];
        $reference = $transaction_data['reference'];
        $status = $transaction_data['status'];
        
        BoldLogger::info("Actualizando transacción", [
            'transaction_id' => $transaction_id,
            'reference' => $reference,
            'status' => $status
        ]);
        
        // Mapear estados de Bold a estados locales
        $estado_local = $this->mapBoldStatus($status);
        
        $sql = "UPDATE pedidos_bold_test SET 
                bold_transaction_id = ?, 
                bold_status = ?, 
                estado = ?, 
                fecha_actualizacion = CURRENT_TIMESTAMP,
                webhook_data = ?,
                intentos_webhook = intentos_webhook + 1
                WHERE referencia = ?";
        
        $stmt = $conexion->prepare($sql);
        $result = $stmt->execute([
            $transaction_id,
            $status,
            $estado_local,
            json_encode($this->data),
            $reference
        ]);
        
        if (!$result) {
            throw new Exception("Failed to update transaction in database");
        }
        
        // Enviar notificaciones si es necesario
        if ($estado_local === 'completado') {
            $this->sendNotifications($reference, $transaction_data);
        }
        
        BoldLogger::info("Transacción actualizada exitosamente", [
            'reference' => $reference,
            'new_status' => $estado_local
        ]);
    }
    
    private function mapBoldStatus($bold_status) {
        $status_map = [
            'APPROVED' => 'completado',
            'DECLINED' => 'rechazado',
            'ERROR' => 'error',
            'PENDING' => 'pendiente',
            'CANCELLED' => 'cancelado'
        ];
        
        return $status_map[$bold_status] ?? 'pendiente';
    }
    
    private function sendNotifications($reference, $transaction_data) {
        try {
            $notification_system = new BoldNotificationSystem();
            $notification_system->sendPaymentConfirmation($reference, $transaction_data);
        } catch (Exception $e) {
            BoldLogger::warning("Error enviando notificaciones", [
                'error' => $e->getMessage(),
                'reference' => $reference
            ]);
        }
    }
    
    private function sendSuccessResponse($message) {
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => $message]);
        BoldLogger::info("Respuesta exitosa enviada", ['message' => $message]);
    }
    
    private function sendErrorResponse($message) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $message]);
        BoldLogger::error("Respuesta de error enviada", ['message' => $message]);
    }
}

// === PUNTO DE ENTRADA PRINCIPAL ===
header('Content-Type: application/json');

// Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    BoldLogger::warning("Método HTTP inválido", ['method' => $_SERVER['REQUEST_METHOD']]);
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Obtener datos del webhook
$raw_input = file_get_contents('php://input');
$webhook_data = json_decode($raw_input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    BoldLogger::error("JSON inválido recibido", ['raw_input' => $raw_input]);
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

// Procesar webhook
try {
    $processor = new WebhookProcessor($webhook_data);
    $processor->process();
} catch (Exception $e) {
    BoldLogger::error("Error fatal en webhook", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
}
?>
