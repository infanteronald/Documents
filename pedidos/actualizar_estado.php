<?php
header('Content-Type: application/json');
include 'conexion.php';

if($_SERVER["REQUEST_METHOD"] == "POST"){
    try {
        $id = intval($_POST['id']);
        $estado = $_POST['estado'];

        // Resetear todos los estados a '0' primero
        $stmt = $conn->prepare("UPDATE pedidos_detal SET enviado = '0', archivado = '0', anulado = '0' WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        // Establecer el nuevo estado
        switch($estado) {
            case 'sin_enviar':
                // Todos los estados ya están en '0', no hay que hacer nada más
                $updated = true;
                break;
            case 'enviado':
                $stmt = $conn->prepare("UPDATE pedidos_detal SET enviado = '1' WHERE id = ? LIMIT 1");
                $stmt->bind_param("i", $id);
                $updated = $stmt->execute();
                $stmt->close();
                break;
            case 'anulado':
                $stmt = $conn->prepare("UPDATE pedidos_detal SET anulado = '1' WHERE id = ? LIMIT 1");
                $stmt->bind_param("i", $id);
                $updated = $stmt->execute();
                $stmt->close();
                break;
            case 'archivado':
                $stmt = $conn->prepare("UPDATE pedidos_detal SET archivado = '1' WHERE id = ? LIMIT 1");
                $stmt->bind_param("i", $id);
                $updated = $stmt->execute();
                $stmt->close();
                break;
            default:
                echo json_encode(['success' => false, 'error' => 'Estado inválido']);
                exit;
        }

        if($updated) {
            echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
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
