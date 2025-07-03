<?php
/**
 * Duplicar un pedido existente
 * Archivo auxiliar para el sistema de listado de pedidos modernizado
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$id_pedido = isset($_POST['id_pedido']) ? intval($_POST['id_pedido']) : 0;

if (!$id_pedido) {
    echo json_encode(['success' => false, 'message' => 'ID de pedido requerido']);
    exit;
}

try {
    require_once 'conexion.php';

    // Obtener información del pedido original
    $stmt = $pdo->prepare("
        SELECT
            cliente_nombre,
            cliente_email,
            cliente_telefono,
            cliente_ciudad,
            cliente_direccion,
            cliente_barrio,
            metodo_pago,
            subtotal,
            impuestos,
            descuento,
            total,
            notas
        FROM pedidos
        WHERE id = ?
    ");

    $stmt->execute([$id_pedido]);
    $pedido_original = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido_original) {
        echo json_encode(['success' => false, 'message' => 'Pedido original no encontrado']);
        exit;
    }

    // Obtener productos del pedido original
    $productos_stmt = $pdo->prepare("
        SELECT
            producto_id,
            cantidad,
            precio_unitario,
            subtotal
        FROM pedidos_productos
        WHERE pedido_id = ?
    ");

    $productos_stmt->execute([$id_pedido]);
    $productos = $productos_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Iniciar transacción
    $pdo->beginTransaction();

    // Crear nuevo pedido
    $nuevo_stmt = $pdo->prepare("
        INSERT INTO pedidos (
            cliente_nombre,
            cliente_email,
            cliente_telefono,
            cliente_ciudad,
            cliente_direccion,
            cliente_barrio,
            metodo_pago,
            subtotal,
            impuestos,
            descuento,
            total,
            estado,
            fecha_creacion,
            notas
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', NOW(), ?)
    ");

    $notas_duplicado = ($pedido_original['notas'] ? $pedido_original['notas'] . "\n\n" : '') .
                      'Pedido duplicado del #' . $id_pedido . ' el ' . date('d/m/Y H:i:s');

    $nuevo_stmt->execute([
        $pedido_original['cliente_nombre'],
        $pedido_original['cliente_email'],
        $pedido_original['cliente_telefono'],
        $pedido_original['cliente_ciudad'],
        $pedido_original['cliente_direccion'],
        $pedido_original['cliente_barrio'],
        $pedido_original['metodo_pago'],
        $pedido_original['subtotal'],
        $pedido_original['impuestos'],
        $pedido_original['descuento'],
        $pedido_original['total'],
        $notas_duplicado
    ]);

    $nuevo_id = $pdo->lastInsertId();

    // Duplicar productos del pedido
    if (!empty($productos)) {
        $productos_insert_stmt = $pdo->prepare("
            INSERT INTO pedidos_productos (
                pedido_id,
                producto_id,
                cantidad,
                precio_unitario,
                subtotal
            ) VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($productos as $producto) {
            $productos_insert_stmt->execute([
                $nuevo_id,
                $producto['producto_id'],
                $producto['cantidad'],
                $producto['precio_unitario'],
                $producto['subtotal']
            ]);
        }
    }

    // Registrar nota de duplicación en el pedido original
    try {
        $nota_stmt = $pdo->prepare("
            INSERT INTO pedidos_notas (pedido_id, nota, fecha_creacion)
            VALUES (?, ?, NOW())
        ");
        $nota_stmt->execute([
            $id_pedido,
            'Pedido duplicado. Nuevo pedido #' . $nuevo_id . ' creado'
        ]);
    } catch (Exception $e) {
        // No es crítico si falla el registro de la nota
    }

    // Confirmar transacción
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Pedido duplicado exitosamente',
        'data' => [
            'pedido_original' => $id_pedido,
            'pedido_nuevo' => $nuevo_id,
            'productos_duplicados' => count($productos),
            'total' => $pedido_original['total']
        ]
    ]);

} catch (PDOException $e) {
    $pdo->rollback();
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    $pdo->rollback();
    echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
}
?>
