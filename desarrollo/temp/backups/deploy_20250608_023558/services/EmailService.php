<?php
/**
 * Servicio de Email - Sequoia Speed
 * Archivo: app/services/EmailService.php
 */

namespace SequoiaSpeed\Services;

use SequoiaSpeed\Models\Pedido;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private $config;
    private $pedidoModel;
    private $logFile;
    
    public function __construct()
    {
        $this->config = include dirname(dirname(__DIR__)) . '/app/config/smtp.php';
        $this->pedidoModel = new Pedido();
        $this->logFile = dirname(dirname(__DIR__)) . '/storage/logs/emails.log';
    }
    
    /**
     * Configura PHPMailer con la configuración SMTP
     */
    private function setupMailer()
    {
        $mail = new PHPMailer(true);
        
        try {
            // Configuración del servidor
            $mail->isSMTP();
            $mail->Host = $this->config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['username'];
            $mail->Password = $this->config['password'];
            $mail->SMTPSecure = $this->config['encryption'];
            $mail->Port = $this->config['port'];
            $mail->CharSet = 'UTF-8';
            
            // Configuración del remitente
            $mail->setFrom($this->config['from_email'], $this->config['from_name']);
            
            return $mail;
        } catch (Exception $e) {
            $this->log('ERROR', 'Error configurando mailer: ' . $e->getMessage());
            throw new \Exception('Error configurando email: ' . $e->getMessage());
        }
    }
    
    /**
     * Envía email de confirmación de pedido
     */
    public function sendOrderConfirmation($pedidoId)
    {
        try {
            $pedido = $this->pedidoModel->getById($pedidoId);
            
            if (!$pedido) {
                throw new \Exception('Pedido no encontrado');
            }
            
            if (empty($pedido['cliente_email'])) {
                $this->log('WARNING', "No se puede enviar confirmación: pedido {$pedidoId} sin email");
                return false;
            }
            
            $mail = $this->setupMailer();
            
            // Destinatario
            $mail->addAddress($pedido['cliente_email'], $pedido['cliente_nombre']);
            
            // Contenido
            $mail->isHTML(true);
            $mail->Subject = "Confirmación de Pedido #{$pedidoId} - Sequoia Speed";
            $mail->Body = $this->getOrderConfirmationTemplate($pedido);
            $mail->AltBody = $this->getOrderConfirmationText($pedido);
            
            $result = $mail->send();
            
            if ($result) {
                $this->log('INFO', "Confirmación enviada para pedido {$pedidoId} a {$pedido['cliente_email']}");
                return true;
            } else {
                $this->log('ERROR', "Error enviando confirmación para pedido {$pedidoId}");
                return false;
            }
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Error en sendOrderConfirmation: ' . $e->getMessage(), ['pedido_id' => $pedidoId]);
            return false;
        }
    }
    
    /**
     * Envía notificación de cambio de estado
     */
    public function sendStatusUpdate($pedidoId, $nuevoEstado)
    {
        try {
            $pedido = $this->pedidoModel->getById($pedidoId);
            
            if (!$pedido || empty($pedido['cliente_email'])) {
                return false;
            }
            
            $mail = $this->setupMailer();
            
            // Destinatario
            $mail->addAddress($pedido['cliente_email'], $pedido['cliente_nombre']);
            
            // Contenido
            $mail->isHTML(true);
            $mail->Subject = "Actualización de Pedido #{$pedidoId} - " . $this->getEstadoName($nuevoEstado);
            $mail->Body = $this->getStatusUpdateTemplate($pedido, $nuevoEstado);
            $mail->AltBody = $this->getStatusUpdateText($pedido, $nuevoEstado);
            
            $result = $mail->send();
            
            if ($result) {
                $this->log('INFO', "Actualización de estado enviada para pedido {$pedidoId}: {$nuevoEstado}");
                return true;
            } else {
                $this->log('ERROR', "Error enviando actualización para pedido {$pedidoId}");
                return false;
            }
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Error en sendStatusUpdate: ' . $e->getMessage(), [
                'pedido_id' => $pedidoId,
                'estado' => $nuevoEstado
            ]);
            return false;
        }
    }
    
    /**
     * Envía notificación de pago aprobado
     */
    public function sendPaymentApproved($pedidoId)
    {
        try {
            $pedido = $this->pedidoModel->getById($pedidoId);
            
            if (!$pedido || empty($pedido['cliente_email'])) {
                return false;
            }
            
            $mail = $this->setupMailer();
            
            // Destinatario
            $mail->addAddress($pedido['cliente_email'], $pedido['cliente_nombre']);
            
            // Contenido
            $mail->isHTML(true);
            $mail->Subject = "Pago Aprobado - Pedido #{$pedidoId} - Sequoia Speed";
            $mail->Body = $this->getPaymentApprovedTemplate($pedido);
            $mail->AltBody = $this->getPaymentApprovedText($pedido);
            
            $result = $mail->send();
            
            if ($result) {
                $this->log('INFO', "Notificación de pago aprobado enviada para pedido {$pedidoId}");
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Error en sendPaymentApproved: ' . $e->getMessage(), ['pedido_id' => $pedidoId]);
            return false;
        }
    }
    
    /**
     * Envía notificación de envío con guía
     */
    public function sendShippingNotification($pedidoId, $guiaEnvio)
    {
        try {
            $pedido = $this->pedidoModel->getById($pedidoId);
            
            if (!$pedido || empty($pedido['cliente_email'])) {
                return false;
            }
            
            $mail = $this->setupMailer();
            
            // Destinatario
            $mail->addAddress($pedido['cliente_email'], $pedido['cliente_nombre']);
            
            // Contenido
            $mail->isHTML(true);
            $mail->Subject = "Tu Pedido ha sido Enviado #{$pedidoId} - Sequoia Speed";
            $mail->Body = $this->getShippingTemplate($pedido, $guiaEnvio);
            $mail->AltBody = $this->getShippingText($pedido, $guiaEnvio);
            
            $result = $mail->send();
            
            if ($result) {
                $this->log('INFO', "Notificación de envío enviada para pedido {$pedidoId}");
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Error en sendShippingNotification: ' . $e->getMessage(), [
                'pedido_id' => $pedidoId,
                'guia' => $guiaEnvio
            ]);
            return false;
        }
    }
    
    /**
     * Envía email administrativo
     */
    public function sendAdminNotification($subject, $message, $data = [])
    {
        try {
            $adminEmails = explode(',', $this->config['admin_emails'] ?? '');
            
            if (empty($adminEmails)) {
                return false;
            }
            
            $mail = $this->setupMailer();
            
            // Destinatarios
            foreach ($adminEmails as $email) {
                $email = trim($email);
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $mail->addAddress($email);
                }
            }
            
            // Contenido
            $mail->isHTML(true);
            $mail->Subject = "[Sequoia Speed] " . $subject;
            $mail->Body = $this->getAdminNotificationTemplate($subject, $message, $data);
            $mail->AltBody = strip_tags($message);
            
            $result = $mail->send();
            
            if ($result) {
                $this->log('INFO', "Notificación administrativa enviada: {$subject}");
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Error en sendAdminNotification: ' . $e->getMessage(), [
                'subject' => $subject,
                'message' => $message
            ]);
            return false;
        }
    }
    
    /**
     * Template HTML para confirmación de pedido
     */
    private function getOrderConfirmationTemplate($pedido)
    {
        $productos = $pedido['productos'] ?? [];
        $productosHtml = '';
        
        foreach ($productos as $producto) {
            $productosHtml .= "
                <tr>
                    <td style='padding: 10px; border-bottom: 1px solid #eee;'>{$producto['nombre']}</td>
                    <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: center;'>{$producto['cantidad']}</td>
                    <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right;'>\$" . number_format($producto['precio_unitario'], 0, ',', '.') . "</td>
                    <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right;'>\$" . number_format($producto['subtotal'], 0, ',', '.') . "</td>
                </tr>";
        }
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Confirmación de Pedido</title>
        </head>
        <body style='font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: white; padding: 20px; border-radius: 10px;'>
                <h1 style='color: #2c5aa0; text-align: center;'>¡Gracias por tu pedido!</h1>
                
                <p>Hola <strong>{$pedido['cliente_nombre']}</strong>,</p>
                
                <p>Hemos recibido tu pedido correctamente. A continuación encontrarás los detalles:</p>
                
                <div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <h3 style='margin-top: 0; color: #2c5aa0;'>Pedido #{$pedido['id']}</h3>
                    <p><strong>Fecha:</strong> {$pedido['fecha_creacion_formateada']}</p>
                    <p><strong>Estado:</strong> " . $this->getEstadoName($pedido['estado']) . "</p>
                    <p><strong>Total:</strong> \$" . number_format($pedido['total'], 0, ',', '.') . "</p>
                </div>
                
                " . (!empty($productos) ? "
                <h3 style='color: #2c5aa0;'>Productos:</h3>
                <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                    <thead>
                        <tr style='background-color: #2c5aa0; color: white;'>
                            <th style='padding: 10px; text-align: left;'>Producto</th>
                            <th style='padding: 10px; text-align: center;'>Cantidad</th>
                            <th style='padding: 10px; text-align: right;'>Precio</th>
                            <th style='padding: 10px; text-align: right;'>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$productosHtml}
                    </tbody>
                </table>
                " : "") . "
                
                <div style='background-color: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <p style='margin: 0;'><strong>¿Qué sigue?</strong></p>
                    <p style='margin: 5px 0 0 0;'>Te mantendremos informado sobre el estado de tu pedido por email y SMS.</p>
                </div>
                
                <hr style='margin: 30px 0;'>
                
                <p style='text-align: center; color: #666;'>
                    <strong>Sequoia Speed</strong><br>
                    Gracias por confiar en nosotros
                </p>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Template de texto para confirmación de pedido
     */
    private function getOrderConfirmationText($pedido)
    {
        return "
        ¡Gracias por tu pedido!
        
        Hola {$pedido['cliente_nombre']},
        
        Hemos recibido tu pedido correctamente.
        
        Pedido #{$pedido['id']}
        Fecha: {$pedido['fecha_creacion_formateada']}
        Estado: " . $this->getEstadoName($pedido['estado']) . "
        Total: \$" . number_format($pedido['total'], 0, ',', '.') . "
        
        Te mantendremos informado sobre el estado de tu pedido.
        
        Gracias por confiar en Sequoia Speed.
        ";
    }
    
    /**
     * Template para actualización de estado
     */
    private function getStatusUpdateTemplate($pedido, $nuevoEstado)
    {
        $estadoNombre = $this->getEstadoName($nuevoEstado);
        $mensaje = $this->getEstadoMessage($nuevoEstado);
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Actualización de Pedido</title>
        </head>
        <body style='font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: white; padding: 20px; border-radius: 10px;'>
                <h1 style='color: #2c5aa0; text-align: center;'>Actualización de tu Pedido</h1>
                
                <p>Hola <strong>{$pedido['cliente_nombre']}</strong>,</p>
                
                <div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <h3 style='margin-top: 0; color: #2c5aa0;'>Pedido #{$pedido['id']}</h3>
                    <p><strong>Estado actual:</strong> {$estadoNombre}</p>
                </div>
                
                <p>{$mensaje}</p>
                
                <hr style='margin: 30px 0;'>
                
                <p style='text-align: center; color: #666;'>
                    <strong>Sequoia Speed</strong><br>
                    Gracias por confiar en nosotros
                </p>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Template de texto para actualización de estado
     */
    private function getStatusUpdateText($pedido, $nuevoEstado)
    {
        $estadoNombre = $this->getEstadoName($nuevoEstado);
        $mensaje = $this->getEstadoMessage($nuevoEstado);
        
        return "
        Actualización de tu Pedido
        
        Hola {$pedido['cliente_nombre']},
        
        Pedido #{$pedido['id']}
        Estado actual: {$estadoNombre}
        
        {$mensaje}
        
        Gracias por confiar en Sequoia Speed.
        ";
    }
    
    /**
     * Template para pago aprobado
     */
    private function getPaymentApprovedTemplate($pedido)
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Pago Aprobado</title>
        </head>
        <body style='font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: white; padding: 20px; border-radius: 10px;'>
                <h1 style='color: #28a745; text-align: center;'>¡Pago Aprobado!</h1>
                
                <p>Hola <strong>{$pedido['cliente_nombre']}</strong>,</p>
                
                <div style='background-color: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <p style='margin: 0;'><strong>Tu pago ha sido aprobado exitosamente.</strong></p>
                    <p style='margin: 10px 0 0 0;'>Pedido #{$pedido['id']} - \$" . number_format($pedido['total'], 0, ',', '.') . "</p>
                </div>
                
                <p>Ahora procederemos a preparar y enviar tu pedido. Te notificaremos cuando esté listo para el envío.</p>
                
                <hr style='margin: 30px 0;'>
                
                <p style='text-align: center; color: #666;'>
                    <strong>Sequoia Speed</strong><br>
                    Gracias por confiar en nosotros
                </p>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Template de texto para pago aprobado
     */
    private function getPaymentApprovedText($pedido)
    {
        return "
        ¡Pago Aprobado!
        
        Hola {$pedido['cliente_nombre']},
        
        Tu pago ha sido aprobado exitosamente.
        Pedido #{$pedido['id']} - \$" . number_format($pedido['total'], 0, ',', '.') . "
        
        Ahora procederemos a preparar y enviar tu pedido.
        
        Gracias por confiar en Sequoia Speed.
        ";
    }
    
    /**
     * Template para notificación de envío
     */
    private function getShippingTemplate($pedido, $guiaEnvio)
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Pedido Enviado</title>
        </head>
        <body style='font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: white; padding: 20px; border-radius: 10px;'>
                <h1 style='color: #17a2b8; text-align: center;'>¡Tu pedido está en camino!</h1>
                
                <p>Hola <strong>{$pedido['cliente_nombre']}</strong>,</p>
                
                <div style='background-color: #d1ecf1; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <p style='margin: 0;'><strong>Tu pedido ha sido enviado.</strong></p>
                    <p style='margin: 10px 0 0 0;'>Pedido #{$pedido['id']}</p>
                    <p style='margin: 10px 0 0 0;'><strong>Guía de envío:</strong> {$guiaEnvio}</p>
                </div>
                
                <p>Podrás hacer seguimiento de tu envío con la guía proporcionada.</p>
                
                <hr style='margin: 30px 0;'>
                
                <p style='text-align: center; color: #666;'>
                    <strong>Sequoia Speed</strong><br>
                    Gracias por confiar en nosotros
                </p>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Template de texto para envío
     */
    private function getShippingText($pedido, $guiaEnvio)
    {
        return "
        ¡Tu pedido está en camino!
        
        Hola {$pedido['cliente_nombre']},
        
        Tu pedido ha sido enviado.
        Pedido #{$pedido['id']}
        Guía de envío: {$guiaEnvio}
        
        Podrás hacer seguimiento de tu envío con la guía proporcionada.
        
        Gracias por confiar en Sequoia Speed.
        ";
    }
    
    /**
     * Template para notificaciones administrativas
     */
    private function getAdminNotificationTemplate($subject, $message, $data)
    {
        $dataHtml = '';
        if (!empty($data)) {
            $dataHtml = '<h3>Datos adicionales:</h3><pre>' . json_encode($data, JSON_PRETTY_PRINT) . '</pre>';
        }
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>{$subject}</title>
        </head>
        <body style='font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: white; padding: 20px; border-radius: 10px;'>
                <h1 style='color: #dc3545;'>{$subject}</h1>
                <p>{$message}</p>
                {$dataHtml}
                <hr>
                <p><small>Sequoia Speed - " . date('Y-m-d H:i:s') . "</small></p>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Obtiene nombre legible del estado
     */
    private function getEstadoName($estado)
    {
        $estados = [
            'pendiente' => 'Pendiente',
            'confirmado' => 'Confirmado',
            'pago_pendiente' => 'Pago Pendiente',
            'pagado' => 'Pagado',
            'en_proceso' => 'En Proceso',
            'enviado' => 'Enviado',
            'completado' => 'Completado',
            'cancelado' => 'Cancelado',
            'pago_rechazado' => 'Pago Rechazado',
            'reembolsado' => 'Reembolsado'
        ];
        
        return $estados[$estado] ?? ucfirst($estado);
    }
    
    /**
     * Obtiene mensaje descriptivo del estado
     */
    private function getEstadoMessage($estado)
    {
        $mensajes = [
            'confirmado' => 'Tu pedido ha sido confirmado y está siendo procesado.',
            'pago_pendiente' => 'Estamos esperando la confirmación del pago.',
            'pagado' => 'Tu pago ha sido confirmado. Procederemos a preparar tu pedido.',
            'en_proceso' => 'Tu pedido está siendo preparado para el envío.',
            'enviado' => 'Tu pedido ha sido enviado y está en camino.',
            'completado' => '¡Tu pedido ha sido completado exitosamente!',
            'cancelado' => 'Tu pedido ha sido cancelado.',
            'pago_rechazado' => 'El pago de tu pedido fue rechazado. Por favor, intenta nuevamente.',
            'reembolsado' => 'Tu pedido ha sido reembolsado.'
        ];
        
        return $mensajes[$estado] ?? 'El estado de tu pedido ha sido actualizado.';
    }
    
    /**
     * Registra eventos en log
     */
    private function log($level, $message, $data = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message,
            'data' => $data
        ];
        
        $logLine = json_encode($logEntry) . PHP_EOL;
        
        // Crear directorio si no existe
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
}
