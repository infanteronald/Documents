<?php
/**
 * Anular un pedido
 * Archivo auxiliar para el sistema de listado de pedidos modernizado
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener ID del pedido
$id_pedido = isset($_POST['id_pedido']) ? intval($_POST['id_pedido']) : 0;
if (!$id_pedido) {
    $id_pedido = isset($_POST['id']) ? intval($_POST['id']) : 0; // Fallback para consistencia
}
$motivo = isset($_POST['motivo']) ? trim($_POST['motivo']) : '';

if (!$id_pedido) {
    echo json_encode(['success' => false, 'message' => 'ID de pedido requerido']);
    exit;
}

try {
    // Incluir conexión
    require_once 'conexion.php';

    // Verificar conexión
    if ($conn->connect_error) {
        echo json_encode(['success' => false, 'message' => 'Error de conexión: ' . $conn->connect_error]);
        exit;
    }

    // Verificar que el pedido existe y obtener sus datos
    $stmt = $conn->prepare("SELECT id, nombre, correo, monto, estado, anulado FROM pedidos_detal WHERE id = ?");

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Error al preparar consulta: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param("i", $id_pedido);

    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Error al ejecutar consulta: ' . $stmt->error]);
        $stmt->close();
        exit;
    }

    // Usar bind_result en lugar de get_result para compatibilidad
    $stmt->bind_result($pedido_id, $pedido_nombre, $pedido_correo, $pedido_monto, $pedido_estado, $pedido_anulado);

    if (!$stmt->fetch()) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Pedido no encontrado']);
        exit;
    }

    $stmt->close();

    if ($pedido_anulado == '1') {
        echo json_encode(['success' => false, 'message' => 'El pedido ya está anulado']);
        exit;
    }

    // Anular el pedido
    $update_stmt = $conn->prepare("UPDATE pedidos_detal SET anulado = '1', estado = 'anulado' WHERE id = ?");

    if (!$update_stmt) {
        echo json_encode(['success' => false, 'message' => 'Error al preparar actualización: ' . $conn->error]);
        exit;
    }

    $update_stmt->bind_param("i", $id_pedido);
    $result = $update_stmt->execute();

    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Error al ejecutar actualización: ' . $update_stmt->error]);
        $update_stmt->close();
        exit;
    }

    if ($conn->affected_rows > 0) {
        // Agregar nota interna si existe motivo
        if ($motivo) {
            $nota_stmt = $conn->prepare("UPDATE pedidos_detal SET nota_interna = CONCAT(IFNULL(nota_interna, ''), ?) WHERE id = ?");
            if ($nota_stmt) {
                $nota_texto = "[" . date('Y-m-d H:i:s') . "] Pedido anulado. Motivo: " . $motivo . "\n";
                $nota_stmt->bind_param("si", $nota_texto, $id_pedido);
                $nota_stmt->execute();
                $nota_stmt->close();
            }
        }

        // Intentar enviar email de notificación (si hay correo)
        if (!empty($pedido_correo)) {
            try {
                $asunto = "Pedido #$id_pedido anulado - Sequoia Speed";
                $mensaje = "Estimado/a {$pedido_nombre},\n\n";
                $mensaje .= "Le informamos que su pedido #$id_pedido ha sido anulado.\n\n";
                if ($motivo) {
                    $mensaje .= "Motivo: $motivo\n\n";
                }
                $mensaje .= "Si tiene alguna consulta, no dude en contactarnos.\n\n";
                $mensaje .= "Saludos,\nEquipo Sequoia Speed";

                $headers = "From: ventas@sequoiaspeed.com.co\r\n";
                $headers .= "Reply-To: ventas@sequoiaspeed.com.co\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

                @mail($pedido_correo, $asunto, $mensaje, $headers);
            } catch (Exception $e) {
                // No es crítico si falla el envío del email
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Pedido anulado correctamente',
            'data' => [
                'id_pedido' => $id_pedido,
                'cliente' => $pedido_nombre,
                'total' => $pedido_monto,
                'motivo' => $motivo,
                'fecha_anulacion' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo anular el pedido. Es posible que ya esté anulado.']);
    }

    $update_stmt->close();

} catch (mysqli_sql_exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>
