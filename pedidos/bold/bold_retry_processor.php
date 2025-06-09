<?php
/**
 * Procesador de Cola de Retry para Webhooks Bold
 * Procesa webhooks fallidos de manera asíncrona con retry logic inteligente
 */

require_once "conexion.php";

set_time_limit(300); // 5 minutos máximo de ejecución

/**
 * Clase para procesar la cola de retry
 */
class BoldRetryProcessor {
    private $conn;
    private $maxRetries = 5;
    private $retryDelays = [300, 900, 1800, 3600, 7200]; // 5min, 15min, 30min, 1h, 2h
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Procesar todos los elementos pendientes en la cola
     */
    public function processRetryQueue() {
        $processedCount = 0;
        $successCount = 0;
        $failedCount = 0;
        
        echo "<h2>🔄 Procesando Cola de Retry Bold</h2>\n";
        echo "<p>Iniciado a las: " . date('Y-m-d H:i:s') . "</p>\n";
        
        // Obtener elementos listos para retry
        $pendingItems = $this->getPendingRetryItems();
        
        if (empty($pendingItems)) {
            echo "<p style='color: blue;'>ℹ️ No hay elementos pendientes para procesar</p>\n";
            return;
        }
        
        echo "<p>📋 Encontrados " . count($pendingItems) . " elementos para procesar</p>\n";
        echo "<hr>\n";
        
        foreach ($pendingItems as $item) {
            $processedCount++;
            echo "<h3>Procesando elemento #{$item['id']} (Intento {$item['attempts']})</h3>\n";
            
            try {
                // Marcar como en procesamiento
                $this->updateRetryStatus($item['id'], 'processing');
                
                // Intentar procesar el webhook
                $result = $this->retryWebhook($item);
                
                if ($result['success']) {
                    $this->markAsCompleted($item['id'], $result['message']);
                    echo "<p style='color: green;'>✅ Procesado exitosamente: {$result['message']}</p>\n";
                    $successCount++;
                } else {
                    $this->handleRetryFailure($item, $result['error']);
                    echo "<p style='color: orange;'>⚠️ Falló el reintento: {$result['error']}</p>\n";
                    $failedCount++;
                }
                
            } catch (Exception $e) {
                $this->handleRetryFailure($item, $e->getMessage());
                echo "<p style='color: red;'>❌ Error crítico: {$e->getMessage()}</p>\n";
                $failedCount++;
            }
            
            echo "<hr>\n";
            
            // Pequeña pausa entre procesamiento
            usleep(500000); // 0.5 segundos
        }
        
        echo "<h3>📊 Resumen del Procesamiento</h3>\n";
        echo "<ul>\n";
        echo "<li><strong>Total procesados:</strong> {$processedCount}</li>\n";
        echo "<li><strong style='color: green;'>Exitosos:</strong> {$successCount}</li>\n";
        echo "<li><strong style='color: red;'>Fallidos:</strong> {$failedCount}</li>\n";
        echo "</ul>\n";
        
        // Limpiar elementos completados antiguos
        $this->cleanupOldItems();
    }
    
    /**
     * Obtener elementos pendientes para retry
     */
    private function getPendingRetryItems() {
        $sql = "
        SELECT * FROM bold_retry_queue 
        WHERE status = 'pending' 
        AND (next_retry_at IS NULL OR next_retry_at <= NOW())
        AND attempts < ?
        ORDER BY created_at ASC
        LIMIT 20
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->maxRetries);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Actualizar estado del elemento en la cola
     */
    private function updateRetryStatus($id, $status) {
        $sql = "UPDATE bold_retry_queue SET status = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
    }
    
    /**
     * Reintentar procesar un webhook
     */
    private function retryWebhook($item) {
        try {
            // Decodificar datos del webhook
            $webhookData = json_decode($item['webhook_data'], true);
            
            if (!$webhookData) {
                throw new Exception("Datos de webhook inválidos");
            }
            
            // Simular el procesamiento del webhook original
            return $this->processWebhookData($webhookData);
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Procesar datos del webhook (lógica similar al webhook original)
     */
    private function processWebhookData($data) {
        // Validar estructura
        if (!isset($data['type']) || !isset($data['data'])) {
            throw new Exception('Estructura de webhook inválida');
        }

        $eventType = $data['type'];
        $paymentData = $data['data'];

        // Extraer información básica
        $orderId = $paymentData['order_id'] ?? '';
        $transactionId = $paymentData['transaction_id'] ?? '';
        $amount = floatval($paymentData['amount'] ?? 0);
        $currency = $paymentData['currency'] ?? 'COP';
        $status = $paymentData['status'] ?? '';

        if (empty($orderId) || empty($status)) {
            throw new Exception('Datos de pago incompletos');
        }

        // Procesar según el tipo de evento
        switch ($eventType) {
            case 'payment.success':
            case 'payment.approved':
            case 'SALE_APPROVED':
                return $this->handlePaymentSuccess($orderId, $transactionId, $amount, $paymentData);
                
            case 'payment.failed':
            case 'payment.rejected':
            case 'SALE_REJECTED':
                return $this->handlePaymentFailed($orderId, $transactionId, $paymentData);
                
            case 'payment.pending':
            case 'SALE_PENDING':
                return $this->handlePaymentPending($orderId, $transactionId, $paymentData);
                
            default:
                return [
                    'success' => true,
                    'message' => "Evento {$eventType} registrado (no requiere acción)"
                ];
        }
    }
    
    /**
     * Manejar pago exitoso en retry
     */
    private function handlePaymentSuccess($orderId, $transactionId, $amount, $paymentData) {
        // Verificar si ya fue procesado
        $stmt = $this->conn->prepare("
            SELECT id FROM pedidos_detal 
            WHERE (bold_order_id = ? OR id = ?) 
            AND bold_transaction_id = ? 
            AND estado_pago = 'pagado'
        ");
        $stmt->bind_param("sss", $orderId, $orderId, $transactionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return [
                'success' => true,
                'message' => 'Pago ya procesado anteriormente'
            ];
        }
        
        // Buscar el pedido
        $stmt = $this->conn->prepare("SELECT * FROM pedidos_detal WHERE bold_order_id = ? OR id = ?");
        $stmt->bind_param("ss", $orderId, $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // Crear orden si no existe
            $this->createOrderFromRetry($orderId, $transactionId, $amount, $paymentData);
            return [
                'success' => true,
                'message' => 'Orden creada y pago procesado'
            ];
        } else {
            // Actualizar orden existente
            $pedido = $result->fetch_assoc();
            $this->updateOrderPaymentStatus($pedido['id'], 'pagado', $transactionId, $paymentData);
            return [
                'success' => true,
                'message' => 'Orden actualizada a pagado'
            ];
        }
    }
    
    /**
     * Crear orden desde retry
     */
    private function createOrderFromRetry($orderId, $transactionId, $amount, $paymentData) {
        $customerData = $paymentData['customer'] ?? [];
        $customerEmail = htmlspecialchars($customerData['email'] ?? '', ENT_QUOTES, 'UTF-8');
        $customerName = htmlspecialchars($customerData['full_name'] ?? 'Cliente Bold PSE', ENT_QUOTES, 'UTF-8');
        $customerPhone = htmlspecialchars($customerData['phone'] ?? '', ENT_QUOTES, 'UTF-8');
        
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
            throw new Exception("Error al crear orden en retry: " . $stmt->error);
        }
    }
    
    /**
     * Actualizar estado de pago en retry
     */
    private function updateOrderPaymentStatus($pedidoId, $estado, $transactionId, $paymentData) {
        $stmt = $this->conn->prepare("
            UPDATE pedidos_detal 
            SET estado_pago = ?, 
                bold_transaction_id = ?, 
                bold_response = ?,
                fecha_pago = NOW(),
                retry_count = COALESCE(retry_count, 0) + 1
            WHERE id = ?
        ");
        
        $response = json_encode($paymentData);
        $stmt->bind_param("sssi", $estado, $transactionId, $response, $pedidoId);
        
        if (!$stmt->execute()) {
            throw new Exception("Error al actualizar orden en retry: " . $stmt->error);
        }
    }
    
    /**
     * Manejar pago fallido en retry
     */
    private function handlePaymentFailed($orderId, $transactionId, $paymentData) {
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
            throw new Exception("Error al procesar pago fallido en retry: " . $stmt->error);
        }
        
        return [
            'success' => true,
            'message' => 'Pago fallido procesado'
        ];
    }
    
    /**
     * Manejar pago pendiente en retry
     */
    private function handlePaymentPending($orderId, $transactionId, $paymentData) {
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
            throw new Exception("Error al procesar pago pendiente en retry: " . $stmt->error);
        }
        
        return [
            'success' => true,
            'message' => 'Pago pendiente procesado'
        ];
    }
    
    /**
     * Manejar fallo en el retry
     */
    private function handleRetryFailure($item, $error) {
        $newAttempts = $item['attempts'] + 1;
        
        if ($newAttempts >= $this->maxRetries) {
            // Marcar como fallido permanentemente
            $this->markAsFailed($item['id'], $error);
        } else {
            // Programar siguiente intento
            $delay = $this->retryDelays[$newAttempts - 1] ?? 7200; // Default 2 horas
            $this->scheduleNextRetry($item['id'], $newAttempts, $delay, $error);
        }
    }
    
    /**
     * Marcar como completado
     */
    private function markAsCompleted($id, $message) {
        $sql = "
        UPDATE bold_retry_queue 
        SET status = 'completed', 
            processed_at = NOW(), 
            updated_at = NOW()
        WHERE id = ?
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        // Log del éxito
        $this->logRetryResult($id, 'success', $message);
    }
    
    /**
     * Marcar como fallido permanentemente
     */
    private function markAsFailed($id, $error) {
        $sql = "
        UPDATE bold_retry_queue 
        SET status = 'failed', 
            error_message = ?, 
            updated_at = NOW()
        WHERE id = ?
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("si", $error, $id);
        $stmt->execute();
        
        // Log del fallo permanente
        $this->logRetryResult($id, 'permanent_failure', $error);
    }
    
    /**
     * Programar siguiente intento
     */
    private function scheduleNextRetry($id, $attempts, $delaySeconds, $error) {
        $sql = "
        UPDATE bold_retry_queue 
        SET status = 'pending',
            attempts = ?,
            next_retry_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
            error_message = ?,
            updated_at = NOW()
        WHERE id = ?
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iisi", $attempts, $delaySeconds, $error, $id);
        $stmt->execute();
        
        echo "<p style='color: blue;'>📅 Programado para retry en " . ($delaySeconds / 60) . " minutos</p>\n";
    }
    
    /**
     * Log del resultado del retry
     */
    private function logRetryResult($retryId, $status, $message) {
        // Opcional: insertar en tabla de logs
        error_log("Bold Retry #{$retryId}: {$status} - {$message}");
    }
    
    /**
     * Limpiar elementos antiguos completados
     */
    private function cleanupOldItems() {
        $sql = "
        DELETE FROM bold_retry_queue 
        WHERE status = 'completed' 
        AND processed_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ";
        
        $result = $this->conn->query($sql);
        $deletedCount = $this->conn->affected_rows;
        
        if ($deletedCount > 0) {
            echo "<p style='color: blue;'>🧹 Limpieza: {$deletedCount} elementos antiguos eliminados</p>\n";
        }
    }
}

// ==================== EJECUCIÓN PRINCIPAL ====================

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/html; charset=utf-8');
    
    echo '<!DOCTYPE html>';
    echo '<html><head><title>Bold Retry Processor</title><link rel="icon" type="image/x-icon" href="favicon.ico">';
    echo '<style>body{font-family:Arial;max-width:800px;margin:20px auto;padding:20px;background:#f5f5f5;}';
    echo '.container{background:white;padding:30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);}';
    echo 'h2{color:#333;border-bottom:2px solid #007bff;padding-bottom:10px;}';
    echo '.btn{background:#007bff;color:white;padding:12px 24px;border:none;border-radius:5px;cursor:pointer;text-decoration:none;display:inline-block;margin:10px 5px;}';
    echo '.btn:hover{background:#0056b3;}</style></head><body>';
    
    echo '<div class="container">';
    echo '<h2>🔄 Procesador de Cola de Retry Bold</h2>';
    echo '<p>Este script procesa los webhooks fallidos que están en la cola de retry.</p>';
    
    echo '<h3>📋 Estado Actual de la Cola</h3>';
    
    // Mostrar estadísticas de la cola
    $stats = $conn->query("
        SELECT 
            status,
            COUNT(*) as count,
            MIN(created_at) as oldest,
            MAX(created_at) as newest
        FROM bold_retry_queue 
        GROUP BY status
        ORDER BY status
    ");
    
    if ($stats && $stats->num_rows > 0) {
        echo '<table border="1" style="width:100%;border-collapse:collapse;margin:20px 0;">';
        echo '<tr style="background:#f8f9fa;"><th>Estado</th><th>Cantidad</th><th>Más Antiguo</th><th>Más Reciente</th></tr>';
        
        while ($stat = $stats->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . ucfirst($stat['status']) . '</td>';
            echo '<td>' . number_format($stat['count']) . '</td>';
            echo '<td>' . ($stat['oldest'] ? date('d/m/Y H:i', strtotime($stat['oldest'])) : '-') . '</td>';
            echo '<td>' . ($stat['newest'] ? date('d/m/Y H:i', strtotime($stat['newest'])) : '-') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p style="color:green;">✅ No hay elementos en la cola de retry</p>';
    }
    
    echo '<h3>🚀 Acciones</h3>';
    echo '<a href="?action=process" class="btn">🔄 Procesar Cola</a>';
    echo '<a href="bold_webhook_monitor.php" class="btn">📊 Ver Monitor</a>';
    echo '<a href="index.php" class="btn">🏠 Inicio</a>';
    
    echo '</div></body></html>';
    
} elseif ($_GET['action'] === 'process') {
    header('Content-Type: text/html; charset=utf-8');
    
    echo '<!DOCTYPE html>';
    echo '<html><head><title>Procesando Cola de Retry</title><link rel="icon" type="image/x-icon" href="favicon.ico">';
    echo '<style>body{font-family:Arial;max-width:800px;margin:20px auto;padding:20px;background:#f5f5f5;}';
    echo '.container{background:white;padding:30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);}';
    echo 'h2,h3{color:#333;border-bottom:2px solid #007bff;padding-bottom:5px;}';
    echo '.btn{background:#007bff;color:white;padding:12px 24px;border:none;border-radius:5px;cursor:pointer;text-decoration:none;display:inline-block;margin:10px 5px;}';
    echo '.btn:hover{background:#0056b3;}</style></head><body>';
    
    echo '<div class="container">';
    
    try {
        $processor = new BoldRetryProcessor($conn);
        $processor->processRetryQueue();
        
        echo '<h3>✅ Procesamiento Completado</h3>';
        echo '<a href="bold_retry_processor.php" class="btn">🔄 Volver</a>';
        echo '<a href="bold_webhook_monitor.php" class="btn">📊 Ver Monitor</a>';
        
    } catch (Exception $e) {
        echo '<h3 style="color:red;">❌ Error en Procesamiento</h3>';
        echo '<p style="color:red;">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<a href="bold_retry_processor.php" class="btn">🔙 Volver</a>';
    }
    
    echo '</div></body></html>';
}
?>
