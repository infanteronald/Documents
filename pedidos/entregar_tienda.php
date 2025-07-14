<?php
/**
 * Marca un pedido como entregado en tienda - Versión compatible
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'conexion.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Obtener datos del POST
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'No se recibieron datos JSON']);
    exit;
}

$pedido_id = isset($input['pedido_id']) ? intval($input['pedido_id']) : 0;

if (!$pedido_id || $pedido_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de pedido inválido: ' . $pedido_id]);
    exit;
}

try {
    // Verificar conexión
    if ($conn->connect_error) {
        throw new Exception('Error de conexión a BD: ' . $conn->connect_error);
    }
    
    // Verificar que el pedido existe usando consulta simple
    $query = "SELECT id, nombre FROM pedidos_detal WHERE id = " . intval($pedido_id) . " LIMIT 1";
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        throw new Exception('Error en consulta de verificación: ' . mysqli_error($conn));
    }
    
    if (mysqli_num_rows($result) === 0) {
        throw new Exception('Pedido no encontrado con ID: ' . $pedido_id);
    }
    
    $pedido = mysqli_fetch_assoc($result);
    
    // Actualizar el pedido usando consulta simple
    $update_query = "
        UPDATE pedidos_detal 
        SET tienda = '1', 
            enviado = '1', 
            tiene_guia = '1',
            guia = 'entrega-tienda.jpg',
            nota_interna = CONCAT(COALESCE(nota_interna, ''), '\n[" . date('Y-m-d H:i:s') . "] - Pedido entregado en tienda físicamente')
        WHERE id = " . intval($pedido_id);
    
    $update_result = mysqli_query($conn, $update_query);
    
    if (!$update_result) {
        throw new Exception('Error en actualización: ' . mysqli_error($conn));
    }
    
    if (mysqli_affected_rows($conn) === 0) {
        throw new Exception('No se pudo actualizar el pedido (sin cambios)');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Pedido marcado como entregado en tienda exitosamente',
        'pedido_id' => $pedido_id,
        'cliente' => $pedido['nombre']
    ]);
    
} catch (Exception $e) {
    error_log("Error en entregar_tienda.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

mysqli_close($conn);
?>