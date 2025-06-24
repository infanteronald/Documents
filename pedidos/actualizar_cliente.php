<?php
/**
 * Actualizar Cliente - Compatible con PHP/MySQL antiguos
 * Sequoia Speed - Actualización de información del cliente
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $pedido_id = intval($_POST['pedido_id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $persona_recibe = trim($_POST['persona_recibe'] ?? '');
        $horarios = trim($_POST['horarios'] ?? '');

        // Validaciones básicas
        if (!$pedido_id) {
            echo json_encode(['success' => false, 'error' => 'ID de pedido no válido']);
            exit;
        }

        if (empty($nombre) || empty($correo)) {
            echo json_encode(['success' => false, 'error' => 'Nombre y email son obligatorios']);
            exit;
        }

        // Validar formato de email
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'error' => 'Formato de email no válido']);
            exit;
        }

        // Verificar que el pedido existe usando método compatible
        $stmt = $conn->prepare("SELECT id FROM pedidos_detal WHERE id = ? LIMIT 1");
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Error en preparación de consulta: ' . $conn->error]);
            exit;
        }

        $stmt->bind_param("i", $pedido_id);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'error' => 'Error al verificar pedido: ' . $stmt->error]);
            exit;
        }

        $stmt->bind_result($found_id);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Pedido no encontrado']);
            exit;
        }
        $stmt->close();

        // Actualizar datos del cliente
        $stmt = $conn->prepare("
            UPDATE pedidos_detal
            SET nombre = ?,
                correo = ?,
                telefono = ?,
                direccion = ?,
                persona_recibe = ?,
                horarios = ?
            WHERE id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Error en preparación de actualización: ' . $conn->error]);
            exit;
        }

        $stmt->bind_param("ssssssi", $nombre, $correo, $telefono, $direccion, $persona_recibe, $horarios, $pedido_id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $stmt->close();

                // Agregar nota automática del cambio
                date_default_timezone_set('America/Bogota');
                $timestamp = date('Y-m-d H:i:s');
                $nota_cambio = "[$timestamp - Sistema] Datos del cliente actualizados";

                // Obtener nota actual usando método compatible
                $stmt_nota = $conn->prepare("SELECT nota_interna FROM pedidos_detal WHERE id = ? LIMIT 1");
                if ($stmt_nota) {
                    $stmt_nota->bind_param("i", $pedido_id);
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
                        $stmt_update_nota->bind_param("si", $todas_las_notas, $pedido_id);
                        $stmt_update_nota->execute();
                        $stmt_update_nota->close();
                    }
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Datos del cliente actualizados correctamente',
                    'timestamp' => $timestamp
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'No se realizaron cambios']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al actualizar en base de datos: ' . $stmt->error]);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}

$conn->close();
?>
