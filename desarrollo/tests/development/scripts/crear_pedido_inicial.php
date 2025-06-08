<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Leer datos JSON del carrito
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['productos']) || !is_array($data['productos'])) {
        echo json_encode(['success' => false, 'error' => 'Datos de productos inválidos']);
        exit;
    }
    
    $productos = $data['productos'];
    $monto_total = isset($data['monto_total']) ? floatval($data['monto_total']) : 0;
    
    if (count($productos) === 0) {
        echo json_encode(['success' => false, 'error' => 'El carrito está vacío']);
        exit;
    }
    
    try {
        // Iniciar transacción
        $conn->begin_transaction();
        
        // 1. Crear pedido principal en pedidos_detal con estado 'borrador'
        $pedido_descripcion = "Pedido con " . count($productos) . " productos";
        $stmt_pedido = $conn->prepare("INSERT INTO pedidos_detal (pedido, monto, estado, fecha) VALUES (?, ?, 'borrador', NOW())");
        $stmt_pedido->bind_param("sd", $pedido_descripcion, $monto_total);
        
        if (!$stmt_pedido->execute()) {
            throw new Exception("Error al crear pedido principal: " . $stmt_pedido->error);
        }
        
        $pedido_id = $conn->insert_id;
        $stmt_pedido->close();
        
        // 2. Procesar productos personalizados primero (crear en tabla productos)
        foreach ($productos as &$producto) {
            // Si es un producto personalizado, crear primero en tabla productos
            if (isset($producto['isCustom']) && $producto['isCustom'] === true) {
                $stmt_producto = $conn->prepare("INSERT INTO productos (nombre, precio, categoria, activo) VALUES (?, ?, 'Personalizado', 1)");
                $stmt_producto->bind_param("sd", $producto['nombre'], $producto['precio']);
                
                if ($stmt_producto->execute()) {
                    $nuevo_id = $conn->insert_id;
                    $producto['id'] = $nuevo_id; // Actualizar ID en el array
                    $stmt_producto->close();
                } else {
                    throw new Exception("Error al crear producto personalizado: " . $stmt_producto->error);
                }
            }
        }
        
        // 3. Guardar productos en pedidos_detalle
        $stmt_detalle = $conn->prepare("INSERT INTO pedidos_detalle (pedido_id, producto_id, nombre, precio, cantidad, talla) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($productos as $producto) {
            $producto_id = isset($producto['id']) ? intval($producto['id']) : 0;
            $nombre = isset($producto['nombre']) ? $producto['nombre'] : 'Producto sin nombre';
            $precio = isset($producto['precio']) ? floatval($producto['precio']) : 0;
            $cantidad = isset($producto['cantidad']) ? intval($producto['cantidad']) : 1;
            $talla = isset($producto['talla']) ? $producto['talla'] : 'N/A';
            
            $stmt_detalle->bind_param("iisdis", $pedido_id, $producto_id, $nombre, $precio, $cantidad, $talla);
            
            if (!$stmt_detalle->execute()) {
                throw new Exception("Error al guardar producto: " . $stmt_detalle->error);
            }
        }
        
        $stmt_detalle->close();
        
        // Confirmar transacción
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'pedido_id' => $pedido_id,
            'message' => "Pedido inicial creado correctamente con " . count($productos) . " productos"
        ]);
        
    } catch (Exception $e) {
        // Rollback en caso de error
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
} else {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}
?>
