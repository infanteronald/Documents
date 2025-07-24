<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config_secure.php';
require_once 'php82_helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$pedido_id = isset($_POST['pedido_id']) ? intval($_POST['pedido_id']) : 0;
$numero_guia = isset($_POST['numero_guia']) ? trim($_POST['numero_guia']) : '';
$transportadora = isset($_POST['transportadora']) ? trim($_POST['transportadora']) : '';

if ($pedido_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de pedido inválido']);
    exit;
}

if (empty($numero_guia)) {
    echo json_encode(['success' => false, 'error' => 'Número de guía requerido']);
    exit;
}

if (empty($transportadora)) {
    echo json_encode(['success' => false, 'error' => 'Transportadora requerida']);
    exit;
}

try {
    // Obtener datos del pedido
    $stmt = $conn->prepare("SELECT nombre, correo, telefono, ciudad, direccion, barrio FROM pedidos_detal WHERE id = ?");
    $stmt->bind_param('i', $pedido_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($pedido = $result->fetch_assoc()) {
        $nombre_cliente = $pedido['nombre'];
        $email_cliente = $pedido['correo'];
        $telefono_cliente = $pedido['telefono'];
        $direccion_completa = $pedido['direccion'] . ', ' . $pedido['barrio'] . ', ' . $pedido['ciudad'];
        
        // Preparar el email
        $asunto = "🚚 Tu pedido #{$pedido_id} está en camino - Guía de envío";
        
        $mensaje = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #1e3a8a; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 8px 8px; }
                .info-box { background: white; padding: 15px; margin: 15px 0; border-radius: 8px; border-left: 4px solid #3b82f6; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
                .btn { display: inline-block; background: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🚚 VitalCarga - Tu pedido está en camino</h2>
                </div>
                <div class='content'>
                    <h3>¡Hola {$nombre_cliente}!</h3>
                    <p>Te informamos que tu pedido <strong>#{$pedido_id}</strong> ya ha sido enviado y está en camino hacia tu dirección.</p>
                    
                    <div class='info-box'>
                        <h4>📦 Información de envío:</h4>
                        <p><strong>Número de guía:</strong> {$numero_guia}</p>
                        <p><strong>Transportadora:</strong> {$transportadora}</p>
                        <p><strong>Dirección de entrega:</strong> {$direccion_completa}</p>
                        <p><strong>Teléfono de contacto:</strong> {$telefono_cliente}</p>
                    </div>
                    
                    <div class='info-box'>
                        <h4>📱 Próximos pasos:</h4>
                        <ul>
                            <li>Nuestro transportista se pondrá en contacto contigo pronto</li>
                            <li>Mantén tu teléfono disponible para coordinar la entrega</li>
                            <li>Prepara el pago si tu pedido es contra entrega</li>
                        </ul>
                    </div>
                    
                    <p>Si tienes alguna pregunta o necesitas reprogramar la entrega, no dudes en contactarnos.</p>
                    
                    <p>¡Gracias por confiar en nosotros!</p>
                </div>
                <div class='footer'>
                    <p>© VitalCarga - Sistema de gestión de entregas</p>
                    <p>Este es un email automático, por favor no responder</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Configurar headers para email HTML
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: VitalCarga <no-reply@vitalcarga.com>',
            'Reply-To: soporte@vitalcarga.com',
            'X-Mailer: PHP/' . phpversion()
        ];
        
        // Enviar email
        if (mail($email_cliente, $asunto, $mensaje, implode("\r\n", $headers))) {
            // Registrar el envío en la base de datos si existe tabla de log
            try {
                $stmt_log = $conn->prepare("INSERT INTO historial_estados_entrega (pedido_id, estado_nuevo, notas, fecha_cambio) VALUES (?, 'email_enviado', ?, NOW())");
                $nota_log = "Email enviado a {$email_cliente} - Guía: {$numero_guia}";
                $stmt_log->bind_param('is', $pedido_id, $nota_log);
                $stmt_log->execute();
            } catch (Exception $e) {
                // Si no existe la tabla, continuar sin error
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Email enviado exitosamente',
                'email' => $email_cliente,
                'numero_guia' => $numero_guia
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al enviar el email']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Pedido no encontrado']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>