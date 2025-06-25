<?php
/**
 * Enviar Email Cliente - Sistema de comunicación con clientes
 * Sequoia Speed - Envío de notificaciones y actualizaciones
 */

header('Content-Type: application/json');
include 'conexion.php';
include 'email_templates.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $pedido_id = intval($_POST['pedido_id'] ?? 0);
        $tipo_email = $_POST['tipo_email'] ?? '';
        $cliente_email = trim($_POST['cliente_email'] ?? '');
        $guia_archivo = $_POST['guia_archivo'] ?? '';

        // Validaciones básicas
        if (!$pedido_id || empty($tipo_email) || empty($cliente_email)) {
            echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
            exit;
        }

        // Obtener datos del pedido
        $stmt = $conn->prepare("SELECT * FROM pedidos_detal WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $pedido_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'Pedido no encontrado']);
            exit;
        }

        $pedido = $result->fetch_assoc();
        $nombre_cliente = $pedido['nombre'] ?? 'Cliente';

        // Configurar contenido según tipo de email
        $asunto = '';
        $contenido_html = '';

        switch ($tipo_email) {
            case 'actualizacion':
                $asunto = "Actualización de tu pedido #$pedido_id - Sequoia Speed";
                $contenido_html = EmailTemplates::emailActualizacionPedido($pedido_id, $nombre_cliente, $pedido);
                break;

            case 'seguimiento':
                $asunto = "Solicitud de seguimiento - Pedido #$pedido_id";
                $contenido_html = EmailTemplates::emailSolicitudSeguimiento($pedido_id, $nombre_cliente, $pedido);
                break;

            case 'entrega':
                $asunto = "Confirmación de entrega - Pedido #$pedido_id";
                $contenido_html = EmailTemplates::emailConfirmacionEntrega($pedido_id, $nombre_cliente, $pedido);
                break;

            case 'entrega_con_guia':
                $asunto = "📦 Guía de envío - Pedido #$pedido_id - Sequoia Speed";
                $contenido_html = EmailTemplates::emailEntregaConGuia($pedido_id, $nombre_cliente, $pedido);
                break;

            default:
                echo json_encode(['success' => false, 'error' => 'Tipo de email no válido']);
                exit;
        }

        // Configurar headers para email
        $from = "ventas@sequoiaspeed.com.co";
        $enviado_exitosamente = false;

        // Manejar envío con adjunto para entrega_con_guia
        if ($tipo_email === 'entrega_con_guia') {
            // Verificar si existe la guía
            $archivo_guia = '';
            if (!empty($guia_archivo)) {
                $ruta_guia = "guias/" . $guia_archivo;
                if (file_exists($ruta_guia)) {
                    $archivo_guia = $ruta_guia;
                }
            }

            if (!empty($archivo_guia)) {
                // Envío con adjunto usando HTML
                $boundary = md5(time());

                $headers = "From: Sequoia Speed <$from>\r\n";
                $headers .= "Reply-To: $from\r\n";
                $headers .= "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
                $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

                $mensaje_completo = "--$boundary\r\n";
                $mensaje_completo .= "Content-Type: text/html; charset=UTF-8\r\n";
                $mensaje_completo .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
                $mensaje_completo .= $contenido_html . "\r\n\r\n";

                // Adjuntar guía
                $archivo_contenido = file_get_contents($archivo_guia);
                $archivo_encoded = chunk_split(base64_encode($archivo_contenido));

                $extension = pathinfo($archivo_guia, PATHINFO_EXTENSION);
                $content_type = ($extension === 'pdf') ? 'application/pdf' : 'image/jpeg';

                $mensaje_completo .= "--$boundary\r\n";
                $mensaje_completo .= "Content-Type: $content_type; name=\"guia_envio_pedido_{$pedido_id}.$extension\"\r\n";
                $mensaje_completo .= "Content-Disposition: attachment; filename=\"guia_envio_pedido_{$pedido_id}.$extension\"\r\n";
                $mensaje_completo .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $mensaje_completo .= $archivo_encoded . "\r\n";
                $mensaje_completo .= "--$boundary--\r\n";

                $enviado_exitosamente = mail($cliente_email, $asunto, $mensaje_completo, $headers);
            } else {
                echo json_encode(['success' => false, 'error' => 'No se encontró la guía de envío']);
                exit;
            }
        } else {
            // Envío normal HTML sin adjuntos
            $headers = "From: Sequoia Speed <$from>\r\n";
            $headers .= "Reply-To: $from\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();

            $enviado_exitosamente = mail($cliente_email, $asunto, $contenido_html, $headers);
        }

        // Enviar email
        if ($enviado_exitosamente) {
            // Registrar envío en notas
            date_default_timezone_set('America/Bogota');
            $timestamp = date('Y-m-d H:i:s');
            $nota_email = "[$timestamp - Sistema] Email enviado al cliente ($tipo_email): $asunto";

            // Obtener nota actual y agregar la nueva
            $stmt_nota = $conn->prepare("SELECT nota_interna FROM pedidos_detal WHERE id = ? LIMIT 1");
            $stmt_nota->bind_param("i", $pedido_id);
            $stmt_nota->execute();
            $result_nota = $stmt_nota->get_result();

            if ($result_nota->num_rows > 0) {
                $row_nota = $result_nota->fetch_assoc();
                $notas_existentes = $row_nota['nota_interna'] ?? '';

                if (!empty($notas_existentes)) {
                    $todas_las_notas = $nota_email . "\n\n" . $notas_existentes;
                } else {
                    $todas_las_notas = $nota_email;
                }

                // Actualizar nota
                $stmt_update_nota = $conn->prepare("UPDATE pedidos_detal SET nota_interna = ? WHERE id = ? LIMIT 1");
                $stmt_update_nota->bind_param("si", $todas_las_notas, $pedido_id);
                $stmt_update_nota->execute();
            }

            echo json_encode([
                'success' => true,
                'message' => 'Email enviado correctamente',
                'tipo' => $tipo_email,
                'destinatario' => $cliente_email
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al enviar el email']);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}
?>
