<?php
// Bold Payment Callback - Procesador de resultados de pago Bold
require_once 'conexion.php';
require_once 'bold_unified_logger.php';
require_once 'smtp_config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

function processPaymentCallback()
{
    global $conn;

    try {
        // Obtener parámetros del callback
        $order_id = $_GET['order_id'] ?? $_POST['order_id'] ?? '';
        $status = $_GET['status'] ?? $_POST['status'] ?? '';
        $transaction_id = $_GET['transaction_id'] ?? $_POST['transaction_id'] ?? '';

        if (empty($order_id)) {
            throw new Exception('order_id requerido');
        }

        // Log del callback recibido
        BoldUnifiedLogger::logActivity($order_id, 'callback_received', "Status: $status, Transaction: $transaction_id", 'info');

        // Buscar pedido en base de datos
        $sql = "SELECT *, descuento FROM pedidos_detal WHERE bold_order_id = ? OR id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$order_id, $order_id]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pedido) {
            throw new Exception("Pedido no encontrado: $order_id");
        }

        // Determinar nuevo estado
        $nuevo_estado = determineOrderStatus($status);

        // Actualizar pedido en base de datos
        $sql = "UPDATE pedidos_detal
                SET estado_pago = ?,
                    bold_transaction_id = ?,
                    fecha_actualizacion = NOW()
                WHERE id = ?";

        $stmt = $conn->prepare($sql);
        $success = $stmt->execute([$nuevo_estado, $transaction_id, $pedido['id']]);

        if ($success) {
            // Log de actualización exitosa
            BoldUnifiedLogger::logActivity($order_id, 'status_updated', "Estado actualizado a: $nuevo_estado", 'success');

            // Enviar email de confirmación si el pago fue exitoso
            if ($nuevo_estado === 'Completado') {
                sendConfirmationEmail($pedido, $transaction_id);
            }

            return [
                'success' => true,
                'message' => 'Pago procesado correctamente',
                'order_id' => $order_id,
                'new_status' => $nuevo_estado,
                'transaction_id' => $transaction_id
            ];
        } else {
            throw new Exception('Error al actualizar el pedido');
        }
    } catch (Exception $e) {
        BoldUnifiedLogger::logActivity($order_id ?? 'unknown', 'callback_error', $e->getMessage(), 'error');
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function determineOrderStatus($bold_status)
{
    switch (strtolower($bold_status)) {
        case 'success':
        case 'completed':
        case 'approved':
            return 'Completado';
        case 'pending':
        case 'processing':
            return 'Pendiente';
        case 'failed':
        case 'rejected':
        case 'declined':
            return 'Cancelado';
        default:
            return 'Pendiente';
    }
}

function sendConfirmationEmail($pedido, $transaction_id)
{
    try {
        if (empty($pedido['email_cliente'])) {
            return false;
        }

        $subject = "Confirmación de Pago - Pedido #{$pedido['id']} - Sequoia Speed";

        $message = "
        <html>
        <head>
            <title>Confirmación de Pago</title>
        </head>
        <body>
            <h2>¡Pago Confirmado!</h2>
            <p>Estimado/a {$pedido['nombre_cliente']},</p>

            <p>Su pago ha sido procesado exitosamente:</p>

            <ul>
                <li><strong>Pedido:</strong> #{$pedido['id']}</li>
                <li><strong>Transacción:</strong> {$transaction_id}</li>";
                
                // Mostrar desglose con descuento si aplica
                $descuento = $pedido['descuento'] ?? 0;
                $monto_final = $pedido['monto'] ?? 0;
                
                if ($descuento > 0) {
                    $subtotal = $monto_final + $descuento;
                    $email_body .= "
                    <li><strong>Subtotal:</strong> $" . number_format($subtotal, 0, ',', '.') . "</li>
                    <li><strong>Descuento:</strong> <span style='color: #28a745;'>-$" . number_format($descuento, 0, ',', '.') . "</span></li>
                    <li><strong>Total Final:</strong> $" . number_format($monto_final, 0, ',', '.') . "</li>";
                } else {
                    $email_body .= "
                    <li><strong>Total:</strong> $" . number_format($monto_final, 0, ',', '.') . "</li>";
                }
                
                $email_body .= "
                <li><strong>Fecha:</strong> " . date('Y-m-d H:i:s') . "</li>
            </ul>

            <p>Pronto nos contactaremos para coordinar la entrega.</p>

            <p>Gracias por su compra en Sequoia Speed Colombia.</p>

            <hr>
            <p><small>Este es un mensaje automático, por favor no responder.</small></p>
        </body>
        </html>
        ";

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ventas@sequoiaspeed.com.co',
            'Reply-To: ventas@sequoiaspeed.com.co',
            'X-Mailer: PHP/' . phpversion()
        ];

        $sent = mail($pedido['email_cliente'], $subject, $message, implode("\r\n", $headers));

        if ($sent) {
            BoldUnifiedLogger::logActivity($pedido['bold_order_id'], 'email_sent', 'Email de confirmación enviado', 'success');
        } else {
            BoldUnifiedLogger::logActivity($pedido['bold_order_id'], 'email_failed', 'Error enviando email de confirmación', 'warning');
        }

        return $sent;
    } catch (Exception $e) {
        BoldUnifiedLogger::logActivity($pedido['bold_order_id'] ?? 'unknown', 'email_error', $e->getMessage(), 'error');
        return false;
    }
}

// Procesar el callback si se llama directamente
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = processPaymentCallback();
    echo json_encode($result);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Método no permitido'
    ]);
}
