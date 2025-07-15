<?php
/**
 * Manejador unificado para operaciones de entrega en tienda
 * Soporta tanto marcar como entregado como revertir la entrega
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
$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'No se recibieron datos JSON válidos. Raw input: ' . $raw_input]);
    exit;
}

$pedido_id = isset($input['pedido_id']) ? intval($input['pedido_id']) : 0;
$action = isset($input['action']) ? $input['action'] : '';

if (!$pedido_id || $pedido_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de pedido inválido: ' . $pedido_id]);
    exit;
}

if (!in_array($action, ['entregar', 'revertir'])) {
    echo json_encode(['success' => false, 'error' => 'Acción inválida. Use "entregar" o "revertir"']);
    exit;
}

try {
    // Log para debug
    error_log("Tienda Handler - Pedido ID: $pedido_id, Acción: $action");
    
    // Verificar conexión
    if ($conn->connect_error) {
        throw new Exception('Error de conexión a BD: ' . $conn->connect_error);
    }
    
    // Verificar que el pedido existe
    $query = "SELECT id, nombre, tienda FROM pedidos_detal WHERE id = " . intval($pedido_id) . " LIMIT 1";
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        throw new Exception('Error en consulta de verificación: ' . mysqli_error($conn));
    }
    
    if (mysqli_num_rows($result) === 0) {
        throw new Exception('Pedido no encontrado con ID: ' . $pedido_id);
    }
    
    $pedido = mysqli_fetch_assoc($result);
    
    // Ejecutar acción según el tipo
    if ($action === 'entregar') {
        // Verificar que no esté ya entregado
        if ($pedido['tienda'] === '1') {
            throw new Exception('El pedido ya está marcado como entregado en tienda');
        }
        
        // Marcar como entregado en tienda
        $update_query = "
            UPDATE pedidos_detal 
            SET tienda = '1', 
                enviado = '1', 
                tiene_guia = '1',
                guia = 'entrega-tienda.jpg',
                nota_interna = CONCAT(COALESCE(nota_interna, ''), '\n[" . date('Y-m-d H:i:s') . "] - Pedido entregado en tienda físicamente')
            WHERE id = " . intval($pedido_id);
        
        $success_message = 'Pedido marcado como entregado en tienda exitosamente';
        $expected_values = ['tienda' => '1', 'enviado' => '1', 'tiene_guia' => '1'];
        
    } else { // action === 'revertir'
        // Verificar que esté marcado como entregado en tienda
        if ($pedido['tienda'] !== '1') {
            throw new Exception('El pedido no está marcado como entregado en tienda');
        }
        
        // Revertir entrega en tienda
        $update_query = "
            UPDATE pedidos_detal 
            SET tienda = '0', 
                enviado = '0', 
                tiene_guia = '0',
                guia = '',
                nota_interna = CONCAT(COALESCE(nota_interna, ''), '\n[" . date('Y-m-d H:i:s') . "] - Se revirtió la entrega en tienda')
            WHERE id = " . intval($pedido_id);
        
        $success_message = 'Entrega en tienda revertida exitosamente';
        $expected_values = ['tienda' => '0', 'enviado' => '0', 'tiene_guia' => '0'];
    }
    
    // Ejecutar actualización
    $update_result = mysqli_query($conn, $update_query);
    
    if (!$update_result) {
        throw new Exception('Error en actualización: ' . mysqli_error($conn));
    }
    
    // Verificar que el pedido se actualizó correctamente
    $verify_query = "SELECT tienda, enviado, tiene_guia FROM pedidos_detal WHERE id = " . intval($pedido_id);
    $verify_result = mysqli_query($conn, $verify_query);
    $updated_pedido = mysqli_fetch_assoc($verify_result);
    
    // Verificar que todos los campos tienen los valores esperados
    foreach ($expected_values as $field => $expected_value) {
        if ($updated_pedido[$field] !== $expected_value) {
            throw new Exception("El campo {$field} no se actualizó correctamente. Valor actual: {$updated_pedido[$field]}, esperado: {$expected_value}");
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => $success_message,
        'pedido_id' => $pedido_id,
        'cliente' => $pedido['nombre'],
        'action' => $action
    ]);
    
} catch (Exception $e) {
    error_log("Error en tienda_handler.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($conn) && $conn) {
        mysqli_close($conn);
    }
}
?>