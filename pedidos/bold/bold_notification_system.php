<?php
/**
 * Bold PSE - Sistema de Notificaciones Mejorado
 * Integración con el sistema de pagos existente
 */

require_once "conexion.php";

class BoldNotificationSystem {
    private $conn;
    private $config;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->loadConfig();
    }
    
    private function loadConfig() {
        $this->config = [
            'admin_email' => 'ventas@sequoiaspeed.com.co',
            'from_name' => 'Sequoia Speed',
            'from_email' => 'ventas@sequoiaspeed.com.co',
            'whatsapp_number' => '573142162979',
            'website_url' => 'https://sequoiaspeed.com',
            'smtp_enabled' => true, // Cambiar según configuración SMTP
            'templates' => [
                'customer_success' => 'templates/customer_payment_success.html',
                'customer_pending' => 'templates/customer_payment_pending.html',
                'admin_notification' => 'templates/admin_payment_notification.html',
                'payment_failed' => 'templates/payment_failed.html'
            ]
        ];
    }
    
    /**
     * Enviar notificación de pago exitoso al cliente
     */
    public function sendCustomerSuccessNotification($pedidoId, $paymentData = []) {
        try {
            $pedido = $this->getPedidoDetails($pedidoId);
            if (!$pedido || empty($pedido['correo'])) {
                throw new Exception("No se pudo obtener información del pedido o email del cliente");
            }
            
            $emailData = [
                'to_email' => $pedido['correo'],
                'to_name' => $pedido['nombre'],
                'subject' => '✅ Pago Confirmado - Pedido #' . $pedido['pedido'],
                'template_data' => [
                    'customer_name' => $pedido['nombre'],
                    'order_id' => $pedido['pedido'],
                    'amount' => number_format($pedido['monto'], 0, ',', '.'),
                    'payment_method' => $pedido['metodo_pago'],
                    'transaction_id' => $paymentData['transaction_id'] ?? 'N/A',
                    'payment_date' => date('d/m/Y H:i'),
                    'delivery_address' => $pedido['direccion'],
                    'contact_phone' => $this->config['whatsapp_number'],
                    'website_url' => $this->config['website_url']
                ]
            ];
            
            $this->sendEmail($emailData, 'customer_success');
            
            // Log de la notificación
            $this->logNotification($pedidoId, 'customer_success', $pedido['correo'], 'sent');
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error enviando notificación de éxito al cliente: " . $e->getMessage());
            $this->logNotification($pedidoId, 'customer_success', $pedido['correo'] ?? '', 'failed', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enviar notificación de pago pendiente al cliente
     */
    public function sendCustomerPendingNotification($pedidoId, $paymentData = []) {
        try {
            $pedido = $this->getPedidoDetails($pedidoId);
            if (!$pedido || empty($pedido['correo'])) return false;
            
            $emailData = [
                'to_email' => $pedido['correo'],
                'to_name' => $pedido['nombre'],
                'subject' => '⏳ Pago en Proceso - Pedido #' . $pedido['pedido'],
                'template_data' => [
                    'customer_name' => $pedido['nombre'],
                    'order_id' => $pedido['pedido'],
                    'amount' => number_format($pedido['monto'], 0, ',', '.'),
                    'payment_method' => $pedido['metodo_pago'],
                    'transaction_id' => $paymentData['transaction_id'] ?? 'N/A',
                    'estimated_time' => '15-30 minutos',
                    'contact_phone' => $this->config['whatsapp_number'],
                    'website_url' => $this->config['website_url']
                ]
            ];
            
            $this->sendEmail($emailData, 'customer_pending');
            $this->logNotification($pedidoId, 'customer_pending', $pedido['correo'], 'sent');
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error enviando notificación pendiente al cliente: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enviar notificación de pago fallido al cliente
     */
    public function sendCustomerFailedNotification($pedidoId, $paymentData = []) {
        try {
            $pedido = $this->getPedidoDetails($pedidoId);
            if (!$pedido || empty($pedido['correo'])) return false;
            
            $emailData = [
                'to_email' => $pedido['correo'],
                'to_name' => $pedido['nombre'],
                'subject' => '❌ Error en el Pago - Pedido #' . $pedido['pedido'],
                'template_data' => [
                    'customer_name' => $pedido['nombre'],
                    'order_id' => $pedido['pedido'],
                    'amount' => number_format($pedido['monto'], 0, ',', '.'),
                    'error_reason' => $paymentData['error_message'] ?? 'Error en la transacción',
                    'retry_url' => $this->config['website_url'] . '/index.php?retry=' . $pedido['pedido'],
                    'contact_phone' => $this->config['whatsapp_number'],
                    'alternative_methods' => $this->getAlternativePaymentMethods()
                ]
            ];
            
            $this->sendEmail($emailData, 'payment_failed');
            $this->logNotification($pedidoId, 'customer_failed', $pedido['correo'], 'sent');
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error enviando notificación de fallo al cliente: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enviar notificación al administrador
     */
    public function sendAdminNotification($pedidoId, $status, $paymentData = []) {
        try {
            $pedido = $this->getPedidoDetails($pedidoId);
            if (!$pedido) return false;
            
            $statusEmojis = [
                'success' => '✅',
                'pending' => '⏳',
                'failed' => '❌'
            ];
            
            $emoji = $statusEmojis[$status] ?? '📧';
            
            $emailData = [
                'to_email' => $this->config['admin_email'],
                'to_name' => 'Administrador Sequoia',
                'subject' => $emoji . ' Pago ' . ucfirst($status) . ' - Pedido #' . $pedido['pedido'],
                'template_data' => [
                    'status' => $status,
                    'status_emoji' => $emoji,
                    'order_id' => $pedido['pedido'],
                    'customer_name' => $pedido['nombre'],
                    'customer_email' => $pedido['correo'],
                    'customer_phone' => $pedido['telefono'],
                    'amount' => number_format($pedido['monto'], 0, ',', '.'),
                    'payment_method' => $pedido['metodo_pago'],
                    'transaction_id' => $paymentData['transaction_id'] ?? 'N/A',
                    'bold_order_id' => $paymentData['order_id'] ?? 'N/A',
                    'delivery_address' => $pedido['direccion'],
                    'order_details' => $pedido['pedido_detalle'] ?? 'Ver en sistema',
                    'payment_date' => date('d/m/Y H:i'),
                    'webhook_data' => json_encode($paymentData, JSON_PRETTY_PRINT)
                ]
            ];
            
            $this->sendEmail($emailData, 'admin_notification');
            $this->logNotification($pedidoId, 'admin_notification', $this->config['admin_email'], 'sent');
            
            // Enviar también notificación por WhatsApp si es pago exitoso
            if ($status === 'success') {
                $this->sendWhatsAppNotification($pedido, $paymentData);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error enviando notificación al admin: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enviar notificación por WhatsApp (webhook)
     */
    private function sendWhatsAppNotification($pedido, $paymentData) {
        try {
            $message = "🎉 *NUEVO PAGO CONFIRMADO*\n\n";
            $message .= "📦 *Pedido:* #{$pedido['pedido']}\n";
            $message .= "👤 *Cliente:* {$pedido['nombre']}\n";
            $message .= "💰 *Monto:* $" . number_format($pedido['monto'], 0, ',', '.') . "\n";
            $message .= "💳 *Método:* {$pedido['metodo_pago']}\n";
            $message .= "📍 *Dirección:* {$pedido['direccion']}\n";
            $message .= "📞 *Teléfono:* {$pedido['telefono']}\n";
            $message .= "✅ *Estado:* PAGADO\n";
            $message .= "⏰ *Fecha:* " . date('d/m/Y H:i');
            
            // Aquí integrarías con tu API de WhatsApp preferida
            // Por ejemplo: WhatsApp Business API, Twilio, etc.
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error enviando WhatsApp: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener detalles del pedido
     */
    private function getPedidoDetails($pedidoId) {
        $stmt = $this->conn->prepare("
            SELECT * FROM pedidos_detal 
            WHERE id = ? OR bold_order_id = ?
        ");
        $stmt->bind_param("ss", $pedidoId, $pedidoId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    /**
     * Enviar email usando PHP mail() o SMTP
     */
    private function sendEmail($emailData, $templateType) {
        $template = $this->loadEmailTemplate($templateType, $emailData['template_data']);
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: ' . $this->config['from_name'] . ' <' . $this->config['from_email'] . '>',
            'Reply-To: ' . $this->config['from_email'],
            'X-Mailer: Bold PSE Notification System'
        ];
        
        $success = mail(
            $emailData['to_email'],
            $emailData['subject'],
            $template,
            implode("\r\n", $headers)
        );
        
        if (!$success) {
            throw new Exception("Error enviando email a " . $emailData['to_email']);
        }
        
        return true;
    }
    
    /**
     * Cargar y procesar template de email
     */
    private function loadEmailTemplate($templateType, $data) {
        $templateFile = __DIR__ . '/' . ($this->config['templates'][$templateType] ?? '');
        
        if (file_exists($templateFile)) {
            $template = file_get_contents($templateFile);
        } else {
            // Template por defecto si no existe el archivo
            $template = $this->getDefaultTemplate($templateType);
        }
        
        // Reemplazar variables en el template
        foreach ($data as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        
        return $template;
    }
    
    /**
     * Templates por defecto
     */
    private function getDefaultTemplate($type) {
        $templates = [
            'customer_success' => '
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="utf-8">
                    <title>Pago Confirmado</title>
                    <style>
                        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
                        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
                        .header { background: #007aff; color: white; padding: 30px; text-align: center; }
                        .content { padding: 30px; }
                        .success-icon { font-size: 48px; margin-bottom: 20px; }
                        .button { display: inline-block; background: #007aff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; margin: 20px 0; }
                        .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 14px; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <div class="success-icon">✅</div>
                            <h1>¡Pago Confirmado!</h1>
                        </div>
                        <div class="content">
                            <p>Hola <strong>{{customer_name}}</strong>,</p>
                            <p>Tu pago ha sido procesado exitosamente. Aquí están los detalles de tu pedido:</p>
                            
                            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                                <p><strong>Número de Pedido:</strong> {{order_id}}</p>
                                <p><strong>Monto Pagado:</strong> ${{amount}} COP</p>
                                <p><strong>Método de Pago:</strong> {{payment_method}}</p>
                                <p><strong>ID de Transacción:</strong> {{transaction_id}}</p>
                                <p><strong>Fecha de Pago:</strong> {{payment_date}}</p>
                                <p><strong>Dirección de Entrega:</strong> {{delivery_address}}</p>
                            </div>
                            
                            <p>Nuestro equipo se pondrá en contacto contigo pronto para coordinar la entrega.</p>
                            <p>Si tienes alguna pregunta, puedes contactarnos por WhatsApp:</p>
                            
                            <a href="https://wa.me/{{contact_phone}}" class="button">📱 Contactar por WhatsApp</a>
                            
                            <p>¡Gracias por tu compra!</p>
                        </div>
                        <div class="footer">
                            <p>Sequoia Speed - Tu tienda de confianza</p>
                            <p>{{website_url}}</p>
                        </div>
                    </div>
                </body>
                </html>
            ',
            
            'customer_pending' => '
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="utf-8">
                    <title>Pago en Proceso</title>
                    <style>
                        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
                        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
                        .header { background: #ff9f0a; color: white; padding: 30px; text-align: center; }
                        .content { padding: 30px; }
                        .pending-icon { font-size: 48px; margin-bottom: 20px; }
                        .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 14px; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <div class="pending-icon">⏳</div>
                            <h1>Pago en Proceso</h1>
                        </div>
                        <div class="content">
                            <p>Hola <strong>{{customer_name}}</strong>,</p>
                            <p>Tu pago está siendo procesado. Los pagos PSE pueden tomar algunos minutos en confirmarse.</p>
                            
                            <div style="background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ff9f0a;">
                                <p><strong>Número de Pedido:</strong> {{order_id}}</p>
                                <p><strong>Monto:</strong> ${{amount}} COP</p>
                                <p><strong>Tiempo Estimado:</strong> {{estimated_time}}</p>
                            </div>
                            
                            <p>Te notificaremos tan pronto como se confirme tu pago.</p>
                            <p>Si tienes alguna pregunta, contáctanos por WhatsApp: {{contact_phone}}</p>
                        </div>
                        <div class="footer">
                            <p>Sequoia Speed</p>
                        </div>
                    </div>
                </body>
                </html>
            ',
            
            'admin_notification' => '
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="utf-8">
                    <title>Notificación de Pago</title>
                    <style>
                        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
                        .container { max-width: 700px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; }
                        .header { background: #1e1e1e; color: white; padding: 20px; }
                        .content { padding: 20px; }
                        .data-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        .data-table th, .data-table td { padding: 10px; border: 1px solid #ddd; text-align: left; }
                        .data-table th { background: #f8f9fa; }
                        .status-success { color: #28a745; }
                        .status-pending { color: #ffc107; }
                        .status-failed { color: #dc3545; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1>{{status_emoji}} Notificación de Pago - {{status}}</h1>
                        </div>
                        <div class="content">
                            <h2>Detalles del Pedido</h2>
                            <table class="data-table">
                                <tr><th>Pedido</th><td>{{order_id}}</td></tr>
                                <tr><th>Cliente</th><td>{{customer_name}}</td></tr>
                                <tr><th>Email</th><td>{{customer_email}}</td></tr>
                                <tr><th>Teléfono</th><td>{{customer_phone}}</td></tr>
                                <tr><th>Monto</th><td>${{amount}} COP</td></tr>
                                <tr><th>Método</th><td>{{payment_method}}</td></tr>
                                <tr><th>Transacción ID</th><td>{{transaction_id}}</td></tr>
                                <tr><th>Bold Order ID</th><td>{{bold_order_id}}</td></tr>
                                <tr><th>Dirección</th><td>{{delivery_address}}</td></tr>
                                <tr><th>Fecha</th><td>{{payment_date}}</td></tr>
                            </table>
                            
                            <h3>Datos del Webhook</h3>
                            <pre style="background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px;">{{webhook_data}}</pre>
                        </div>
                    </div>
                </body>
                </html>
            '
        ];
        
        return $templates[$type] ?? '<html><body><h1>Notificación de Pago</h1><p>Template no disponible</p></body></html>';
    }
    
    /**
     * Obtener métodos de pago alternativos
     */
    private function getAlternativePaymentMethods() {
        return [
            ['name' => 'Nequi / Transfiya', 'number' => '3213260357'],
            ['name' => 'Bancolombia', 'details' => 'Ahorros 03500000175 Ronald Infante'],
            ['name' => 'WhatsApp', 'number' => $this->config['whatsapp_number']]
        ];
    }
    
    /**
     * Log de notificaciones enviadas
     */
    private function logNotification($pedidoId, $type, $recipient, $status, $error = null) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO notification_logs (
                    pedido_id, notification_type, recipient, status, error_message, created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->bind_param("issss", $pedidoId, $type, $recipient, $status, $error);
            $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Error logging notification: " . $e->getMessage());
        }
    }
    
    /**
     * Crear tabla de logs si no existe
     */
    public function createNotificationLogsTable() {
        $sql = "
            CREATE TABLE IF NOT EXISTS notification_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pedido_id INT,
                notification_type VARCHAR(50),
                recipient VARCHAR(255),
                status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
                error_message TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_pedido (pedido_id),
                INDEX idx_type (notification_type),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        return $this->conn->query($sql);
    }
}

// Función helper para usar desde otros archivos
function sendBoldNotification($pedidoId, $status, $paymentData = []) {
    global $conn;
    
    try {
        $notificationSystem = new BoldNotificationSystem($conn);
        
        switch ($status) {
            case 'success':
            case 'approved':
                $notificationSystem->sendCustomerSuccessNotification($pedidoId, $paymentData);
                $notificationSystem->sendAdminNotification($pedidoId, 'success', $paymentData);
                break;
                
            case 'pending':
                $notificationSystem->sendCustomerPendingNotification($pedidoId, $paymentData);
                $notificationSystem->sendAdminNotification($pedidoId, 'pending', $paymentData);
                break;
                
            case 'failed':
            case 'rejected':
                $notificationSystem->sendCustomerFailedNotification($pedidoId, $paymentData);
                $notificationSystem->sendAdminNotification($pedidoId, 'failed', $paymentData);
                break;
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error en sistema de notificaciones: " . $e->getMessage());
        return false;
    }
}

// Auto-crear tabla si no existe
if (isset($conn)) {
    $notificationSystem = new BoldNotificationSystem($conn);
    $notificationSystem->createNotificationLogsTable();
}
?>
