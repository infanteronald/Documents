<?php
/**
 * Agregar Comentario Cliente - Compatible con PHP/MySQL antiguos
 * Sequoia Speed - Los clientes pueden agregar comentarios a sus pedidos
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config_secure.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $pedido_id = intval($_POST['pedido_id'] ?? 0);
        $comentario = trim($_POST['comentario'] ?? '');

        if (!$pedido_id || empty($comentario)) {
            echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
            exit;
        }

        // Verificar que el pedido existe usando método compatible
        $query = "SELECT nombre, correo FROM pedidos_detal WHERE id = ? LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $pedido_id);
        $stmt->execute();
        $stmt->bind_result($nombre_cliente, $correo_cliente);

        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Pedido no encontrado']);
            exit;
        }
        $stmt->close();

        // Obtener comentarios existentes del cliente
        $query = "SELECT comentarios_cliente FROM pedidos_detal WHERE id = ? LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $pedido_id);
        $stmt->execute();
        $stmt->bind_result($comentarios_existentes);
        $stmt->fetch();
        $stmt->close();

        // Si comentarios_existentes es null, inicializar como string vacío
        if ($comentarios_existentes === null) {
            $comentarios_existentes = '';
        }

        // Crear timestamp con zona horaria de Colombia
        date_default_timezone_set('America/Bogota');
        $timestamp = date('Y-m-d H:i:s');

        // Formatear nuevo comentario con timestamp
        $comentario_con_timestamp = "[$timestamp - $nombre_cliente] $comentario";

        // Combinar con comentarios existentes
        if (!empty($comentarios_existentes)) {
            $nuevos_comentarios = $comentarios_existentes . "\n\n" . $comentario_con_timestamp;
        } else {
            $nuevos_comentarios = $comentario_con_timestamp;
        }

        // Actualizar comentarios_cliente
        $query = "UPDATE pedidos_detal SET comentarios_cliente = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $nuevos_comentarios, $pedido_id);

        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'error' => 'Error al guardar comentario']);
            exit;
        }
        $stmt->close();

        // También agregar a nota_interna para que el staff lo vea
        $nota_para_staff = "COMENTARIO CLIENTE [$timestamp]: $comentario";
        $query = "UPDATE pedidos_detal SET nota_interna = CONCAT(COALESCE(nota_interna, ''), '\n\n', ?) WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $nota_para_staff, $pedido_id);
        $stmt->execute();
        $stmt->close();

        // Enviar emails de notificación
        include_once 'email_templates.php';

        // Email para el equipo
        $emailEquipo = EmailTemplates::generarEmailComentarioEquipo([
            'numero_pedido' => $pedido_id,
            'nombre_cliente' => $nombre_cliente,
            'correo_cliente' => $correo_cliente,
            'comentario' => $comentario,
            'timestamp' => $timestamp
        ]);

        // Email para el cliente
        $emailCliente = EmailTemplates::generarEmailComentarioCliente([
            'numero_pedido' => $pedido_id,
            'nombre_cliente' => $nombre_cliente,
            'comentario' => $comentario,
            'timestamp' => $timestamp
        ]);

        // Configurar headers
        $headers = "From: Sequoia Speed <ventas@sequoiaspeed.com.co>\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        // Enviar email al equipo
        mail("ventas@sequoiaspeed.com.co",
             "💬 Nuevo Comentario - Pedido #$pedido_id",
             $emailEquipo,
             $headers);

        // Enviar email al cliente
        $headersCliente = "From: Sequoia Speed <ventas@sequoiaspeed.com.co>\r\n";
        $headersCliente .= "MIME-Version: 1.0\r\n";
        $headersCliente .= "Content-Type: text/html; charset=UTF-8\r\n";

        mail($correo_cliente,
             "✅ Comentario Recibido - Pedido #$pedido_id",
             $emailCliente,
             $headersCliente);

        echo json_encode(['success' => true, 'message' => 'Comentario agregado correctamente']);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}
?>
