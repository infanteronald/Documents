<?php
/**
 * Procesador de Movimientos de Inventario
 * Sequoia Speed - Módulo de Inventario
 */

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once '../config_secure.php';
require_once '../notifications/notification_helpers.php';
require_once '../php82_helpers.php';

// Iniciar sesión para mensajes
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: movimientos.php');
    exit;
}

$errores = [];
$almacen_codigo = $_POST['almacen_codigo'] ?? 'TIENDA_BOG';

// Validar campos requeridos
$campos_requeridos = [
    'almacen_id' => 'El almacén es requerido',
    'tipo_movimiento' => 'El tipo de movimiento es requerido',
    'producto_id' => 'El producto es requerido',
    'cantidad' => 'La cantidad es requerida',
    'motivo' => 'El motivo es requerido',
    'usuario_responsable' => 'El usuario responsable es requerido'
];

foreach ($campos_requeridos as $campo => $mensaje) {
    if (empty($_POST[$campo])) {
        $errores[] = $mensaje;
    }
}

// Validar tipos de datos
if (!empty($_POST['cantidad']) && (!is_numeric($_POST['cantidad']) || $_POST['cantidad'] <= 0)) {
    $errores[] = 'La cantidad debe ser un número mayor a 0';
}

if (!empty($_POST['costo_unitario']) && (!is_numeric($_POST['costo_unitario']) || $_POST['costo_unitario'] < 0)) {
    $errores[] = 'El costo unitario debe ser un número mayor o igual a 0';
}

// Validar almacén destino para transferencias
if (!empty($_POST['tipo_movimiento']) && $_POST['tipo_movimiento'] === 'transferencia') {
    if (empty($_POST['almacen_destino_id'])) {
        $errores[] = 'El almacén destino es requerido para transferencias';
    }
    
    if (!empty($_POST['almacen_destino_id']) && $_POST['almacen_destino_id'] == $_POST['almacen_id']) {
        $errores[] = 'El almacén destino debe ser diferente al almacén origen';
    }
}

// Validar que el producto exista en el almacén
if (!empty($_POST['producto_id']) && !empty($_POST['almacen_id'])) {
    $query_producto = "SELECT ia.stock_actual, p.nombre 
                       FROM inventario_almacen ia 
                       INNER JOIN productos p ON ia.producto_id = p.id 
                       WHERE ia.producto_id = ? AND ia.almacen_id = ?";
    $stmt_producto = $conn->prepare($query_producto);
    $stmt_producto->bind_param('ii', $_POST['producto_id'], $_POST['almacen_id']);
    $stmt_producto->execute();
    $producto_almacen = $stmt_producto->get_result()->fetch_assoc();
    
    if (!$producto_almacen) {
        $errores[] = 'El producto no existe en el almacén seleccionado';
    }
}

// Validar stock disponible para salidas y transferencias
if (!empty($_POST['tipo_movimiento']) && !empty($_POST['cantidad']) && isset($producto_almacen)) {
    $tipo = $_POST['tipo_movimiento'];
    $cantidad = intval($_POST['cantidad']);
    $stock_actual = intval($producto_almacen['stock_actual']);
    
    if (($tipo === 'salida' || $tipo === 'transferencia') && $cantidad > $stock_actual) {
        $errores[] = "No hay suficiente stock disponible. Stock actual: $stock_actual";
    }
}

// Si hay errores, regresar al formulario
if (!empty($errores)) {
    $_SESSION['errores'] = $errores;
    $_SESSION['form_data'] = $_POST;
    header('Location: registrar_movimiento.php?almacen=' . $almacen_codigo);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Preparar datos del movimiento
    $datos = [
        'producto_id' => intval($_POST['producto_id']),
        'almacen_id' => intval($_POST['almacen_id']),
        'tipo_movimiento' => $_POST['tipo_movimiento'],
        'cantidad' => intval($_POST['cantidad']),
        'cantidad_anterior' => intval($producto_almacen['stock_actual']),
        'costo_unitario' => floatval($_POST['costo_unitario'] ?? 0),
        'motivo' => trim($_POST['motivo']),
        'documento_referencia' => trim($_POST['documento_referencia']),
        'usuario_responsable' => trim($_POST['usuario_responsable']),
        'almacen_destino_id' => !empty($_POST['almacen_destino_id']) ? intval($_POST['almacen_destino_id']) : null,
        'observaciones' => trim($_POST['observaciones'])
    ];
    
    // Calcular cantidad nueva según tipo de movimiento
    switch ($datos['tipo_movimiento']) {
        case 'entrada':
            $datos['cantidad_nueva'] = $datos['cantidad_anterior'] + $datos['cantidad'];
            break;
        case 'salida':
            $datos['cantidad_nueva'] = $datos['cantidad_anterior'] - $datos['cantidad'];
            break;
        case 'ajuste':
            $datos['cantidad_nueva'] = $datos['cantidad']; // Para ajustes, cantidad es el stock final
            break;
        case 'transferencia':
            $datos['cantidad_nueva'] = $datos['cantidad_anterior'] - $datos['cantidad'];
            break;
    }
    
    // Registrar movimiento principal
    if ($datos['tipo_movimiento'] === 'transferencia') {
        // Para transferencias, crear dos movimientos
        
        // 1. Movimiento de salida en almacén origen
        $query_salida = "INSERT INTO movimientos_inventario 
                         (producto_id, almacen_id, tipo_movimiento, cantidad, cantidad_anterior, cantidad_nueva, 
                          costo_unitario, motivo, documento_referencia, usuario_responsable, almacen_destino_id, observaciones) 
                         VALUES (?, ?, 'transferencia_salida', ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt_salida = $conn->prepare($query_salida);
        $stmt_salida->bind_param('iisiidsssis', 
            $datos['producto_id'],
            $datos['almacen_id'],
            $datos['cantidad'],
            $datos['cantidad_anterior'],
            $datos['cantidad_nueva'],
            $datos['costo_unitario'],
            $datos['motivo'],
            $datos['documento_referencia'],
            $datos['usuario_responsable'],
            $datos['almacen_destino_id'],
            $datos['observaciones']
        );
        
        if (!$stmt_salida->execute()) {
            throw new Exception('Error al registrar movimiento de salida: ' . $stmt_salida->error);
        }
        
        // 2. Obtener stock actual del almacén destino
        $query_stock_destino = "SELECT stock_actual FROM inventario_almacen 
                                WHERE producto_id = ? AND almacen_id = ?";
        $stmt_stock_destino = $conn->prepare($query_stock_destino);
        $stmt_stock_destino->bind_param('ii', $datos['producto_id'], $datos['almacen_destino_id']);
        $stmt_stock_destino->execute();
        $stock_destino = $stmt_stock_destino->get_result()->fetch_assoc();
        
        // Si no existe registro en el almacén destino, crearlo
        if (!$stock_destino) {
            $query_crear_destino = "INSERT INTO inventario_almacen (producto_id, almacen_id, stock_actual, stock_minimo, stock_maximo) 
                                     VALUES (?, ?, 0, 5, 100)";
            $stmt_crear_destino = $conn->prepare($query_crear_destino);
            $stmt_crear_destino->bind_param('ii', $datos['producto_id'], $datos['almacen_destino_id']);
            $stmt_crear_destino->execute();
            $stock_destino = ['stock_actual' => 0];
        }
        
        $stock_anterior_destino = intval($stock_destino['stock_actual']);
        $stock_nuevo_destino = $stock_anterior_destino + $datos['cantidad'];
        
        // 3. Movimiento de entrada en almacén destino
        $query_entrada = "INSERT INTO movimientos_inventario 
                          (producto_id, almacen_id, tipo_movimiento, cantidad, cantidad_anterior, cantidad_nueva, 
                           costo_unitario, motivo, documento_referencia, usuario_responsable, observaciones) 
                          VALUES (?, ?, 'transferencia_entrada', ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt_entrada = $conn->prepare($query_entrada);
        $stmt_entrada->bind_param('iisiidssss', 
            $datos['producto_id'],
            $datos['almacen_destino_id'],
            $datos['cantidad'],
            $stock_anterior_destino,
            $stock_nuevo_destino,
            $datos['costo_unitario'],
            $datos['motivo'],
            $datos['documento_referencia'],
            $datos['usuario_responsable'],
            $datos['observaciones']
        );
        
        if (!$stmt_entrada->execute()) {
            throw new Exception('Error al registrar movimiento de entrada: ' . $stmt_entrada->error);
        }
        
        // 4. Actualizar stock en almacén destino
        $query_actualizar_destino = "UPDATE inventario_almacen 
                                     SET stock_actual = ? 
                                     WHERE producto_id = ? AND almacen_id = ?";
        $stmt_actualizar_destino = $conn->prepare($query_actualizar_destino);
        $stmt_actualizar_destino->bind_param('iii', $stock_nuevo_destino, $datos['producto_id'], $datos['almacen_destino_id']);
        
        if (!$stmt_actualizar_destino->execute()) {
            throw new Exception('Error al actualizar stock en almacén destino: ' . $stmt_actualizar_destino->error);
        }
        
    } else {
        // Para otros tipos de movimiento, crear un solo registro
        $query_movimiento = "INSERT INTO movimientos_inventario 
                             (producto_id, almacen_id, tipo_movimiento, cantidad, cantidad_anterior, cantidad_nueva, 
                              costo_unitario, motivo, documento_referencia, usuario_responsable, observaciones) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt_movimiento = $conn->prepare($query_movimiento);
        $stmt_movimiento->bind_param('iisiidssss', 
            $datos['producto_id'],
            $datos['almacen_id'],
            $datos['tipo_movimiento'],
            $datos['cantidad'],
            $datos['cantidad_anterior'],
            $datos['cantidad_nueva'],
            $datos['costo_unitario'],
            $datos['motivo'],
            $datos['documento_referencia'],
            $datos['usuario_responsable'],
            $datos['observaciones']
        );
        
        if (!$stmt_movimiento->execute()) {
            throw new Exception('Error al registrar el movimiento: ' . $stmt_movimiento->error);
        }
    }
    
    // Actualizar stock en almacén origen
    $query_actualizar_stock = "UPDATE inventario_almacen 
                               SET stock_actual = ? 
                               WHERE producto_id = ? AND almacen_id = ?";
    $stmt_actualizar_stock = $conn->prepare($query_actualizar_stock);
    $stmt_actualizar_stock->bind_param('iii', $datos['cantidad_nueva'], $datos['producto_id'], $datos['almacen_id']);
    
    if (!$stmt_actualizar_stock->execute()) {
        throw new Exception('Error al actualizar el stock: ' . $stmt_actualizar_stock->error);
    }
    
    $conn->commit();
    
    // Mensaje de éxito
    $tipo_texto = [
        'entrada' => 'Entrada',
        'salida' => 'Salida',
        'ajuste' => 'Ajuste',
        'transferencia' => 'Transferencia'
    ];
    
    $mensaje = "{$tipo_texto[$datos['tipo_movimiento']]} registrada exitosamente para {$producto_almacen['nombre']}";
    
    // Verificar si hay alertas de stock bajo y crear alertas automáticas
    require_once 'sistema_alertas.php';
    $sistema_alertas = new SistemaAlertas($conn);
    
    // Verificar alertas para este producto específico
    if ($datos['cantidad_nueva'] <= 5) { // Asumiendo stock mínimo de 5
        $mensaje .= " ⚠️ Advertencia: El stock actual está por debajo del mínimo";
        
        // Crear alerta automática si no existe una pendiente
        $query_check = "SELECT id FROM alertas_inventario 
                        WHERE tipo_alerta = 'stock_bajo' 
                        AND producto_id = ? 
                        AND almacen_id = ? 
                        AND estado = 'pendiente'
                        AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        
        $stmt_check = $conn->prepare($query_check);
        $stmt_check->bind_param('ii', $datos['producto_id'], $datos['almacen_id']);
        $stmt_check->execute();
        
        if ($stmt_check->get_result()->num_rows == 0) {
            // No existe alerta pendiente, crear una nueva
            $query_producto = "SELECT p.nombre, a.nombre as almacen_nombre 
                               FROM productos p, almacenes a 
                               WHERE p.id = ? AND a.id = ?";
            $stmt_producto = $conn->prepare($query_producto);
            $stmt_producto->bind_param('ii', $datos['producto_id'], $datos['almacen_id']);
            $stmt_producto->execute();
            $info_producto = $stmt_producto->get_result()->fetch_assoc();
            
            $mensaje_alerta = "Stock bajo después de movimiento: {$info_producto['nombre']} en {$info_producto['almacen_nombre']} ({$datos['cantidad_nueva']} unidades)";
            
            $query_alerta = "INSERT INTO alertas_inventario 
                             (tipo_alerta, producto_id, almacen_id, mensaje, nivel_prioridad, datos_adicionales) 
                             VALUES ('stock_bajo', ?, ?, ?, 'alta', ?)";
            
            $datos_adicionales = json_encode([
                'stock_anterior' => $datos['cantidad_anterior'],
                'stock_nuevo' => $datos['cantidad_nueva'],
                'tipo_movimiento' => $datos['tipo_movimiento'],
                'cantidad_movimiento' => $datos['cantidad']
            ]);
            
            $stmt_alerta = $conn->prepare($query_alerta);
            $stmt_alerta->bind_param('iiss', $datos['producto_id'], $datos['almacen_id'], $mensaje_alerta, $datos_adicionales);
            $stmt_alerta->execute();
        }
    }
    
    // Verificar stock crítico (stock = 0)
    if ($datos['cantidad_nueva'] == 0) {
        $query_check_critico = "SELECT id FROM alertas_inventario 
                                WHERE tipo_alerta = 'stock_critico' 
                                AND producto_id = ? 
                                AND almacen_id = ? 
                                AND estado = 'pendiente'
                                AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        
        $stmt_check_critico = $conn->prepare($query_check_critico);
        $stmt_check_critico->bind_param('ii', $datos['producto_id'], $datos['almacen_id']);
        $stmt_check_critico->execute();
        
        if ($stmt_check_critico->get_result()->num_rows == 0) {
            $query_producto = "SELECT p.nombre, a.nombre as almacen_nombre 
                               FROM productos p, almacenes a 
                               WHERE p.id = ? AND a.id = ?";
            $stmt_producto = $conn->prepare($query_producto);
            $stmt_producto->bind_param('ii', $datos['producto_id'], $datos['almacen_id']);
            $stmt_producto->execute();
            $info_producto = $stmt_producto->get_result()->fetch_assoc();
            
            $mensaje_alerta = "Stock crítico después de movimiento: {$info_producto['nombre']} en {$info_producto['almacen_nombre']} (SIN STOCK)";
            
            $query_alerta = "INSERT INTO alertas_inventario 
                             (tipo_alerta, producto_id, almacen_id, mensaje, nivel_prioridad, datos_adicionales) 
                             VALUES ('stock_critico', ?, ?, ?, 'critica', ?)";
            
            $datos_adicionales = json_encode([
                'stock_anterior' => $datos['cantidad_anterior'],
                'stock_nuevo' => $datos['cantidad_nueva'],
                'tipo_movimiento' => $datos['tipo_movimiento'],
                'cantidad_movimiento' => $datos['cantidad']
            ]);
            
            $stmt_alerta = $conn->prepare($query_alerta);
            $stmt_alerta->bind_param('iiss', $datos['producto_id'], $datos['almacen_id'], $mensaje_alerta, $datos_adicionales);
            $stmt_alerta->execute();
        }
    }
    
    $_SESSION['mensaje_exito'] = $mensaje;
    
    // Redirigir a la lista de movimientos
    header('Location: movimientos.php?almacen=' . $almacen_codigo);
    exit;
    
} catch (Exception $e) {
    $conn->rollback();
    
    $_SESSION['errores'] = [$e->getMessage()];
    $_SESSION['form_data'] = $_POST;
    
    header('Location: registrar_movimiento.php?almacen=' . $almacen_codigo);
    exit;
}
?>