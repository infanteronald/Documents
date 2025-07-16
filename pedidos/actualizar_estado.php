<?php
/**
 * Actualizar Estado de Pedido - Con notificaciones por email
 * Sequoia Speed - Actualiza estado y envía notificaciones
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config_secure.php';
require_once 'notifications/notification_helpers.php';

if($_SERVER["REQUEST_METHOD"] == "POST"){
    try {
        $id = intval($_POST['id']);
        $estado = $_POST['estado'];

        if (!$id || empty($estado)) {
            echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
            exit;
        }

        // Obtener datos del pedido para las notificaciones
        $query = "SELECT nombre, correo, telefono, ciudad, barrio, monto, descuento, enviado, archivado, anulado, pagado FROM pedidos_detal WHERE id = ? LIMIT 1";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Error en preparación de consulta: ' . $conn->error]);
            exit;
        }

        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'error' => 'Error al ejecutar consulta: ' . $stmt->error]);
            exit;
        }

        $stmt->bind_result($nombre_cliente, $correo_cliente, $telefono_cliente, $ciudad_cliente, $barrio_cliente, $monto, $descuento, $enviado_anterior, $archivado_anterior, $anulado_anterior, $pagado_anterior);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Pedido no encontrado']);
            exit;
        }
        $stmt->close();

        // Resetear todos los estados a '0' primero
        $stmt = $conn->prepare("UPDATE pedidos_detal SET enviado = '0', archivado = '0', anulado = '0' WHERE id = ? LIMIT 1");
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Error en preparación de reset: ' . $conn->error]);
            exit;
        }
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'error' => 'Error al resetear estados: ' . $stmt->error]);
            exit;
        }
        $stmt->close();

        $updated = false;
        $estado_texto = '';

        // Establecer el nuevo estado
        switch($estado) {
            case 'sin_enviar':
            case 'pendiente':
                $updated = true;
                $estado_texto = 'Pendiente';
                break;
            case 'confirmado':
                $updated = true;
                $estado_texto = 'Confirmado';
                break;
            case 'pago-pendiente':
            case 'pago_pendiente':
                $stmt = $conn->prepare("UPDATE pedidos_detal SET pagado = '0' WHERE id = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                    $updated = $stmt->execute();
                    $stmt->close();
                }
                $estado_texto = 'Pago Pendiente';
                break;
            case 'pago-confirmado':
            case 'pago_confirmado':
                $stmt = $conn->prepare("UPDATE pedidos_detal SET pagado = '1' WHERE id = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                    $updated = $stmt->execute();
                    $stmt->close();
                    
                    // Agregar notificación
                    if ($updated) {
                        notificarPagoConfirmado($id, $monto, 'Manual');
                    }
                }
                $estado_texto = 'Pago Confirmado';
                break;
            case 'enviado':
                $stmt = $conn->prepare("UPDATE pedidos_detal SET enviado = '1' WHERE id = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                    $updated = $stmt->execute();
                    $stmt->close();
                    
                    // Agregar notificación
                    if ($updated) {
                        notificarCambioEstado($id, 'pendiente', 'enviado');
                    }
                }
                $estado_texto = 'Enviado';
                break;
            case 'anulado':
            case 'cancelado':
                $stmt = $conn->prepare("UPDATE pedidos_detal SET anulado = '1' WHERE id = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                    $updated = $stmt->execute();
                    $stmt->close();
                    
                    // Agregar notificación
                    if ($updated) {
                        notificarPedidoAnulado($id);
                    }
                }
                $estado_texto = 'Cancelado';
                break;
            case 'archivado':
                $stmt = $conn->prepare("UPDATE pedidos_detal SET archivado = '1' WHERE id = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                    $updated = $stmt->execute();
                    $stmt->close();
                    
                    // Agregar notificación
                    if ($updated) {
                        notificarCambioEstado($id, 'pendiente', 'archivado');
                    }
                }
                $estado_texto = 'Archivado';
                break;
            default:
                echo json_encode(['success' => false, 'error' => 'Estado inválido: ' . $estado]);
                exit;
        }

        if($updated) {
            // Agregar nota automática del cambio de estado
            date_default_timezone_set('America/Bogota');
            $timestamp = date('Y-m-d H:i:s');
            $nota_cambio = "[$timestamp - Sistema] Estado cambiado a: $estado_texto";

            // Obtener nota actual y agregar la nueva
            $stmt_nota = $conn->prepare("SELECT nota_interna FROM pedidos_detal WHERE id = ? LIMIT 1");
            if ($stmt_nota) {
                $stmt_nota->bind_param("i", $id);
                $stmt_nota->execute();
                $stmt_nota->bind_result($notas_existentes);
                $stmt_nota->fetch();
                $stmt_nota->close();

                if (!empty($notas_existentes)) {
                    $todas_las_notas = $nota_cambio . "\n\n" . $notas_existentes;
                } else {
                    $todas_las_notas = $nota_cambio;
                }

                // Actualizar nota
                $stmt_update_nota = $conn->prepare("UPDATE pedidos_detal SET nota_interna = ? WHERE id = ? LIMIT 1");
                if ($stmt_update_nota) {
                    $stmt_update_nota->bind_param("si", $todas_las_notas, $id);
                    $stmt_update_nota->execute();
                    $stmt_update_nota->close();
                }
            }

            // Enviar notificaciones por email
            include_once 'email_templates.php';

            try {
                // Email para el equipo
                $emailEquipo = EmailTemplates::generarEmailCambioEstado([
                    'numero_pedido' => $id,
                    'nombre_cliente' => $nombre_cliente,
                    'correo_cliente' => $correo_cliente,
                    'telefono_cliente' => $telefono_cliente,
                    'ciudad_cliente' => $ciudad_cliente,
                    'barrio_cliente' => $barrio_cliente,
                    'monto' => $monto,
                    'descuento' => $descuento ?? 0,
                    'subtotal' => $monto + ($descuento ?? 0),
                    'nuevo_estado' => $estado_texto,
                    'timestamp' => $timestamp
                ]);

                // Email para el cliente
                $emailCliente = EmailTemplates::generarEmailCambioEstadoCliente([
                    'numero_pedido' => $id,
                    'nombre_cliente' => $nombre_cliente,
                    'monto' => $monto,
                    'descuento' => $descuento ?? 0,
                    'subtotal' => $monto + ($descuento ?? 0),
                    'nuevo_estado' => $estado_texto,
                    'timestamp' => $timestamp
                ]);

                // Configurar headers
                $headers = "From: Sequoia Speed <ventas@sequoiaspeed.com.co>\r\n";
                $headers .= "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

                // Enviar email al equipo
                mail("ventas@sequoiaspeed.com.co",
                     "$estado_texto - Pedido #$id",
                     $emailEquipo,
                     $headers);

                // Enviar email al cliente
                $headersCliente = "From: Sequoia Speed <ventas@sequoiaspeed.com.co>\r\n";
                $headersCliente .= "MIME-Version: 1.0\r\n";
                $headersCliente .= "Content-Type: text/html; charset=UTF-8\r\n";

                mail($correo_cliente,
                     "$estado_texto - Pedido #$id - Sequoia Speed",
                     $emailCliente,
                     $headersCliente);

            } catch (Exception $e) {
                // Los emails fallan silenciosamente, no afectan la actualización del estado
                error_log("Error al enviar emails de notificación: " . $e->getMessage());
            }

            echo json_encode([
                'success' => true,
                'message' => 'Estado actualizado correctamente y notificaciones enviadas',
                'nuevo_estado' => $estado,
                'estado_texto' => $estado_texto,
                'timestamp' => $timestamp
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al actualizar el estado']);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}

$conn->close();
?>
