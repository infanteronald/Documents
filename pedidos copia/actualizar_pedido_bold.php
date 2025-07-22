<?php
/**
 * Actualizar Pedido Bold - Maneja UPDATE vs INSERT inteligentemente
 * Usado cuando el usuario selecciona Bold en un pedido existente
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
require_once 'config_secure.php';

// Log para debugging
error_log("=== INICIO ACTUALIZAR_PEDIDO_BOLD === " . date('Y-m-d H:i:s'));

$input = file_get_contents('php://input');
error_log("Input recibido: " . $input);

$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("Error al decodificar JSON: " . json_last_error_msg());
    echo json_encode(['success' => false, 'error' => 'Datos inválidos recibidos.']);
    exit;
}

$bold_order_id = $data['bold_order_id'] ?? null;
$pedido_id = $data['pedido_id'] ?? null; // ID del pedido existente desde URL
$monto = $data['monto'] ?? 0;
$descuento = $data['descuento'] ?? 0;
$nombre = $data['nombre'] ?? '';
$correo = $data['correo'] ?? '';
$telefono = $data['telefono'] ?? '';
$direccion = $data['direccion'] ?? '';
$metodo_pago = $data['metodo_pago'] ?? '';

error_log("Bold Order ID: " . $bold_order_id);
error_log("Pedido ID desde URL: " . $pedido_id);
error_log("Monto: " . $monto);
error_log("Nombre: " . $nombre);

if (empty($bold_order_id)) {
    echo json_encode(['success' => false, 'error' => 'bold_order_id requerido']);
    exit;
}

try {
    $pedidoExistente = false;
    $pedidoIdFinal = null;

    // PASO 1: Si viene pedido_id desde URL, usar ese directamente
    if ($pedido_id && is_numeric($pedido_id)) {
        error_log("Usando pedido_id desde URL: " . $pedido_id);

        // Verificar que el pedido existe
        $stmt = $conn->prepare("SELECT id FROM pedidos_detal WHERE id = ? LIMIT 1");
        if (!$stmt) {
            throw new Exception('Error preparando verificación: ' . $conn->error);
        }

        $stmt->bind_param("i", $pedido_id);
        $stmt->execute();

        $stmt->bind_result($pedidoIdFinal);
        if ($stmt->fetch()) {
            $pedidoExistente = true;
            error_log("Pedido encontrado por ID: " . $pedidoIdFinal);
        }
        $stmt->close();
    }

    // PASO 2: Si no hay pedido_id o no se encontró, buscar por datos del cliente
    if (!$pedidoExistente) {
        error_log("Buscando pedido por datos del cliente...");

        $stmt = $conn->prepare("
            SELECT id
            FROM pedidos_detal
            WHERE bold_order_id IS NULL
            AND (nombre = ? OR correo = ? OR telefono = ?)
            ORDER BY fecha DESC
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception('Error preparando búsqueda: ' . $conn->error);
        }

        $stmt->bind_param("sss", $nombre, $correo, $telefono);
        $stmt->execute();

        $stmt->bind_result($pedidoIdFinal);
        if ($stmt->fetch()) {
            $pedidoExistente = true;
            error_log("Pedido encontrado por datos: " . $pedidoIdFinal);
        }
        $stmt->close();
    }

    if ($pedidoExistente && $pedidoIdFinal) {
        // ACTUALIZAR pedido existente
        error_log("Actualizando pedido existente ID: " . $pedidoIdFinal);

        $stmt = $conn->prepare("
            UPDATE pedidos_detal
            SET bold_order_id = ?,
                metodo_pago = ?,
                nombre = ?,
                correo = ?,
                telefono = ?,
                direccion = ?,
                monto = CASE WHEN monto = 0 THEN ? ELSE monto END,
                descuento = CASE WHEN descuento = 0 THEN ? ELSE descuento END
            WHERE id = ?
        ");

        if (!$stmt) {
            throw new Exception('Error preparando actualización: ' . $conn->error);
        }

        $stmt->bind_param("sssssssdi",
            $bold_order_id, $metodo_pago, $nombre, $correo, $telefono,
            $direccion, $monto, $descuento, $pedidoIdFinal
        );

        if (!$stmt->execute()) {
            throw new Exception('Error actualizando pedido: ' . $stmt->error);
        }

        $stmt->close();

        error_log("Pedido actualizado exitosamente ID: " . $pedidoIdFinal);

        echo json_encode([
            'success' => true,
            'action' => 'updated',
            'pedido_id' => $pedidoIdFinal,
            'message' => 'Pedido actualizado con información Bold'
        ]);

    } else {
        // CREAR nuevo pedido si no se encuentra uno existente
        error_log("Creando nuevo pedido Bold");

        // Crear carrito genérico para Bold
        $carrito = [
            [
                'nombre' => 'Pago Bold PSE',
                'precio' => $monto,
                'cantidad' => 1,
                'talla' => ''
            ]
        ];

        $productos_texto = "Pago realizado por Bold PSE por valor de $" . number_format($monto, 0, ',', '.');

        $stmt = $conn->prepare("
            INSERT INTO pedidos_detal (
                bold_order_id,
                nombre,
                correo,
                telefono,
                direccion,
                monto,
                descuento,
                metodo_pago,
                productos,
                estado_pago,
                fecha,
                estado
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', NOW(), 'pendiente')
        ");

        if (!$stmt) {
            throw new Exception('Error preparando inserción: ' . $conn->error);
        }

        $stmt->bind_param("sssssdds",
            $bold_order_id, $nombre, $correo, $telefono, $direccion,
            $monto, $descuento, $metodo_pago, $productos_texto
        );

        if (!$stmt->execute()) {
            throw new Exception('Error insertando pedido: ' . $stmt->error);
        }

        $nuevoPedidoId = $conn->insert_id;
        $stmt->close();

        // Insertar detalles del producto
        $stmt = $conn->prepare("
            INSERT INTO pedido_detalle (pedido_id, nombre, precio, cantidad, talla)
            VALUES (?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            throw new Exception('Error preparando detalle: ' . $conn->error);
        }

        foreach ($carrito as $item) {
            $stmt->bind_param("isdis",
                $nuevoPedidoId,
                $item['nombre'],
                $item['precio'],
                $item['cantidad'],
                $item['talla']
            );

            if (!$stmt->execute()) {
                throw new Exception('Error insertando detalle: ' . $stmt->error);
            }
        }

        $stmt->close();

        error_log("Nuevo pedido creado exitosamente ID: " . $nuevoPedidoId);

        echo json_encode([
            'success' => true,
            'action' => 'created',
            'pedido_id' => $nuevoPedidoId,
            'message' => 'Nuevo pedido creado con información Bold'
        ]);
    }

} catch (Exception $e) {
    error_log("Error en actualizar_pedido_bold: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor: ' . $e->getMessage()
    ]);
}

error_log("=== FIN ACTUALIZAR_PEDIDO_BOLD ===");
?>
