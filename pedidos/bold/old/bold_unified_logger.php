<?php
// Bold Unified Logger - Sistema unificado de logging para Bold
require_once 'conexion.php';

class BoldUnifiedLogger
{
    private static $conn = null;

    private static function getConnection()
    {
        if (self::$conn === null) {
            global $conn;
            self::$conn = $conn;
        }
        return self::$conn;
    }

    /**
     * Log de actividad general en bold_logs
     */
    public static function logActivity($order_id, $activity_type, $details, $status = 'info')
    {
        try {
            $conn = self::getConnection();

            $sql = "INSERT INTO bold_logs (order_id, transaction_id, event_type, event_data, ip_address, user_agent)
                    VALUES (?, '', ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            $event_data = json_encode([
                'activity_type' => $activity_type,
                'details' => $details,
                'status' => $status,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            $stmt->bind_param("ssss", $order_id, $activity_type, $event_data, $ip_address, $user_agent);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("BoldUnifiedLogger::logActivity Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log de transacciones Bold específicas
     */
    public static function logBoldTransaction($order_id, $transaction_id, $amount, $status, $payment_method, $response_data = null)
    {
        try {
            $conn = self::getConnection();

            // Log en bold_logs
            $details = "Transacción Bold - ID: $transaction_id, Monto: $amount, Método: $payment_method";
            self::logActivity($order_id, 'bold_transaction', $details, $status);

            // Log detallado en bold_webhook_logs si tenemos response_data
            if ($response_data) {
                $sql = "INSERT INTO bold_webhook_logs (order_id, event_type, payload, processed_at, status)
                        VALUES (?, 'transaction_log', ?, NOW(), ?)";

                $stmt = $conn->prepare($sql);
                $stmt->execute([$order_id, json_encode([
                    'transaction_id' => $transaction_id,
                    'amount' => $amount,
                    'payment_method' => $payment_method,
                    'response' => $response_data
                ]), $status]);
            }

            return true;
        } catch (Exception $e) {
            error_log("BoldUnifiedLogger::logBoldTransaction Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log de webhook recibido
     */
    public static function logWebhook($order_id, $event_type, $payload, $status = 'received')
    {
        try {
            $conn = self::getConnection();

            $sql = "INSERT INTO bold_webhook_logs (order_id, event_type, payload, processed_at, status)
                    VALUES (?, ?, ?, NOW(), ?)";

            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([$order_id, $event_type, json_encode($payload), $status]);

            // También log en actividad general
            self::logActivity($order_id, 'webhook_received', "Webhook: $event_type", $status);

            return $result;
        } catch (Exception $e) {
            error_log("BoldUnifiedLogger::logWebhook Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualizar estadísticas Bold
     */
    public static function updateStats($event_type, $increment = 1)
    {
        try {
            $conn = self::getConnection();

            // Verificar si ya existe el evento
            $sql = "SELECT id, event_count FROM bold_webhook_stats WHERE event_type = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$event_type]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                // Actualizar existente
                $new_count = $result['event_count'] + $increment;
                $sql = "UPDATE bold_webhook_stats SET event_count = ?, last_updated = NOW() WHERE id = ?";
                $stmt = $conn->prepare($sql);
                return $stmt->execute([$new_count, $result['id']]);
            } else {
                // Crear nuevo
                $sql = "INSERT INTO bold_webhook_stats (event_type, event_count, last_updated) VALUES (?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                return $stmt->execute([$event_type, $increment]);
            }
        } catch (Exception $e) {
            error_log("BoldUnifiedLogger::updateStats Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener logs de un pedido específico
     */
    public static function getOrderLogs($order_id)
    {
        try {
            $conn = self::getConnection();

            $logs = [];

            // Logs de bold_logs
            $sql = "SELECT * FROM bold_logs WHERE order_id = ? ORDER BY created_at DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$order_id]);
            $logs['activity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Logs de bold_webhook_logs
            $sql = "SELECT * FROM bold_webhook_logs WHERE order_id = ? ORDER BY processed_at DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$order_id]);
            $logs['webhooks'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Info del pedido
            $sql = "SELECT id, bold_order_id, bold_transaction_id, estado_pago, fecha_pedido, metodo_pago
                    FROM pedidos_detal WHERE id = ? OR bold_order_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$order_id, $order_id]);
            $logs['order_info'] = $stmt->fetch(PDO::FETCH_ASSOC);

            return $logs;
        } catch (Exception $e) {
            error_log("BoldUnifiedLogger::getOrderLogs Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener estadísticas completas del sistema Bold
     */
    public static function getSystemStats()
    {
        try {
            $conn = self::getConnection();

            $stats = [];

            // Stats de bold_logs
            $sql = "SELECT status, COUNT(*) as count FROM bold_logs GROUP BY status";
            $stmt = $conn->query($sql);
            $stats['logs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Stats de bold_webhook_logs
            $sql = "SELECT status, COUNT(*) as count FROM bold_webhook_logs GROUP BY status";
            $stmt = $conn->query($sql);
            $stats['webhooks'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Pedidos con Bold
            $sql = "SELECT COUNT(*) as count FROM pedidos_detal WHERE metodo_pago LIKE '%Bold%'";
            $stmt = $conn->query($sql);
            $stats['bold_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            return $stats;
        } catch (Exception $e) {
            error_log("BoldUnifiedLogger::getSystemStats Error: " . $e->getMessage());
            return [];
        }
    }
}
