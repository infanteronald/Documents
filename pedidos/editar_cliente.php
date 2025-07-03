<?php
/**
 * Editar información del cliente de un pedido
 * Archivo auxiliar para el sistema de listado de pedidos modernizado
 */

header('Content-Type: application/json');

// Verificar parámetros requeridos
$id_pedido = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

if (!$id_pedido) {
    echo json_encode(['success' => false, 'error' => 'ID de pedido requerido']);
    exit;
}

try {
    require_once 'conexion.php';

    // Si es GET, devolver datos del cliente
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $conn->prepare("
            SELECT
                id,
                nombre,
                correo,
                telefono,
                ciudad,
                direccion,
                barrio
            FROM pedidos_detal
            WHERE id = ?
        ");

        $stmt->bind_param('i', $id_pedido);
        $stmt->execute();

        // Usar bind_result para compatibilidad
        $stmt->bind_result($id, $nombre, $correo, $telefono, $direccion, $ciudad, $barrio);

        if (!$stmt->fetch()) {
            $stmt->close();
            echo json_encode(['success' => false, 'error' => 'Pedido no encontrado']);
            exit;
        }

        $stmt->close();

        $pedido = [
            'id' => $id,
            'nombre' => $nombre,
            'correo' => $correo,
            'telefono' => $telefono,
            'direccion' => $direccion,
            'ciudad' => $ciudad,
            'barrio' => $barrio
        ];

        echo json_encode([
            'success' => true,
            'cliente' => [
                'nombre' => $pedido['nombre'],
                'correo' => $pedido['correo'],
                'telefono' => $pedido['telefono'],
                'ciudad' => $pedido['ciudad'],
                'direccion' => $pedido['direccion'],
                'barrio' => $pedido['barrio']
            ]
        ]);
        exit;
    }

    // Si es POST, actualizar datos del cliente
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
        $correo = isset($_POST['correo']) ? trim($_POST['correo']) : '';
        $telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';
        $ciudad = isset($_POST['ciudad']) ? trim($_POST['ciudad']) : '';
        $direccion = isset($_POST['direccion']) ? trim($_POST['direccion']) : '';
        $barrio = isset($_POST['barrio']) ? trim($_POST['barrio']) : '';

        // Validaciones básicas
        if (empty($nombre)) {
            echo json_encode(['success' => false, 'error' => 'El nombre es requerido']);
            exit;
        }

        if (empty($telefono)) {
            echo json_encode(['success' => false, 'error' => 'El teléfono es requerido']);
            exit;
        }

        if (!empty($correo) && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'error' => 'Formato de correo inválido']);
            exit;
        }

        // Actualizar datos del cliente
        $stmt = $conn->prepare("
            UPDATE pedidos_detal
            SET
                nombre = ?,
                correo = ?,
                telefono = ?,
                ciudad = ?,
                direccion = ?,
                barrio = ?
            WHERE id = ?
        ");

        $stmt->bind_param('ssssssi', $nombre, $correo, $telefono, $ciudad, $direccion, $barrio, $id_pedido);

        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Cliente actualizado correctamente'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Error al actualizar el cliente: ' . $conn->error
            ]);
        }
        exit;
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error interno: ' . $e->getMessage()
    ]);
}
?>
            'data' => [
                'id_pedido' => $pedido['id'],
                'nombre' => $pedido['cliente_nombre'],
                'email' => $pedido['cliente_email'],
                'telefono' => $pedido['cliente_telefono'],
                'ciudad' => $pedido['cliente_ciudad'],
                'direccion' => $pedido['cliente_direccion'],
                'barrio' => $pedido['cliente_barrio'] ?? ''
            ]
        ]);
        exit;
    }

    // Actualizar información del cliente
    $nombre = trim($_POST['cliente_nombre']);
    $email = trim($_POST['cliente_email']);
    $telefono = trim($_POST['cliente_telefono']);
    $ciudad = trim($_POST['cliente_ciudad']);
    $direccion = trim($_POST['cliente_direccion']);
    $barrio = trim($_POST['cliente_barrio'] ?? '');

    // Validaciones básicas
    if (empty($nombre) || empty($email) || empty($telefono)) {
        echo json_encode(['success' => false, 'message' => 'Nombre, email y teléfono son obligatorios']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Email no válido']);
        exit;
    }

    // Actualizar pedido
    $stmt = $pdo->prepare("
        UPDATE pedidos
        SET
            cliente_nombre = ?,
            cliente_email = ?,
            cliente_telefono = ?,
            cliente_ciudad = ?,
            cliente_direccion = ?,
            cliente_barrio = ?,
            fecha_actualizacion = NOW()
        WHERE id = ?
    ");

    $result = $stmt->execute([
        $nombre,
        $email,
        $telefono,
        $ciudad,
        $direccion,
        $barrio,
        $id_pedido
    ]);

    if ($result) {
        // Registrar la actualización
        try {
            $nota_stmt = $pdo->prepare("
                INSERT INTO pedidos_notas (pedido_id, nota, fecha_creacion)
                VALUES (?, ?, NOW())
            ");
            $nota_stmt->execute([
                $id_pedido,
                'Información del cliente actualizada: ' . $nombre . ' (' . $email . ')'
            ]);
        } catch (Exception $e) {
            // No es crítico si falla el registro de la nota
        }

        echo json_encode([
            'success' => true,
            'message' => 'Información del cliente actualizada correctamente',
            'data' => [
                'id_pedido' => $id_pedido,
                'nombre' => $nombre,
                'email' => $email,
                'telefono' => $telefono,
                'ciudad' => $ciudad,
                'direccion' => $direccion,
                'barrio' => $barrio
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar la información']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
}
?>
