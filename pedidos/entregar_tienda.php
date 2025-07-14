<?php
/**
 * Marca un pedido como entregado en tienda
 * Cambia tienda=1, enviado=1, tiene_guia=1
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Obtener datos del POST
$input = json_decode(file_get_contents('php://input'), true);
$pedido_id = isset($input['pedido_id']) ? intval($input['pedido_id']) : 0;

if (!$pedido_id || $pedido_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de pedido inválido']);
    exit;
}

try {
    // Iniciar transacción
    $conn->begin_transaction();
    
    // Verificar que el pedido existe
    $stmt = $conn->prepare("SELECT id, nombre, correo FROM pedidos_detal WHERE id = ? LIMIT 1");
    if (!$stmt) {
        throw new Exception('Error preparando verificación: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $pedido_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Pedido no encontrado');
    }
    
    $pedido = $result->fetch_assoc();
    $stmt->close();
    
    // Actualizar el pedido con entrega en tienda
    $stmt = $conn->prepare("
        UPDATE pedidos_detal 
        SET tienda = '1', 
            enviado = '1', 
            tiene_guia = '1',
            guia = 'entrega-tienda.jpg',
            nota_interna = CONCAT(COALESCE(nota_interna, ''), '\n[', NOW(), '] - Pedido entregado en tienda físicamente')
        WHERE id = ?
    ");
    
    if (!$stmt) {
        throw new Exception('Error preparando actualización: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $pedido_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Error ejecutando actualización: ' . $stmt->error);
    }
    
    if ($stmt->affected_rows === 0) {
        throw new Exception('No se pudo actualizar el pedido');
    }
    
    $stmt->close();
    
    // Confirmar transacción
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Pedido marcado como entregado en tienda exitosamente',
        'pedido_id' => $pedido_id,
        'cliente' => $pedido['nombre']
    ]);
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conn->rollback();
    
    error_log("Error en entregar_tienda.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error interno: ' . $e->getMessage()
    ]);
}

$conn->close();
?>