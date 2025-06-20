<?php
header('Content-Type: application/json');
include 'conexion.php';

if($_SERVER["REQUEST_METHOD"] == "POST"){
    try {
        $id = intval($_POST['id']);
        $estado = $_POST['estado'];
        
        // Validar estado
        if(!in_array($estado, ['sin_enviar','enviado','anulado'])) {
            echo json_encode(['success' => false, 'error' => 'Estado inválido']);
            exit;
        }
        
        // Actualizar estado
        $stmt = $conn->prepare("UPDATE pedidos_detal SET estado = ? WHERE id = ? LIMIT 1");
        $stmt->bind_param("si", $estado, $id);
        
        if($stmt->execute()) {
            if($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
            } else {
                echo json_encode(['success' => false, 'error' => 'No se encontró el pedido o no se realizaron cambios']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al ejecutar la consulta: ' . $conn->error]);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}

$conn->close();
?>