<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

include 'conexion.php';

// Recibir datos JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id_pedido'])) {
    echo json_encode(['success' => false, 'message' => 'ID de pedido no proporcionado']);
    exit;
}

$id_pedido = intval($input['id_pedido']);

try {
    // Obtener información del comprobante actual
    $stmt = $conn->prepare("SELECT comprobante FROM pedidos_detal WHERE id = ?");
    $stmt->bind_param("i", $id_pedido);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Pedido no encontrado']);
        exit;
    }

    $row = $result->fetch_assoc();
    $archivo_comprobante = $row['comprobante'];
    $stmt->close();

    // Actualizar base de datos
    $stmt = $conn->prepare("UPDATE pedidos_detal SET
        comprobante = '',
        tiene_comprobante = '0',
        pagado = '0'
        WHERE id = ?");
    $stmt->bind_param("i", $id_pedido);

    if ($stmt->execute()) {
        // Intentar eliminar el archivo físico si existe
        if (!empty($archivo_comprobante) && file_exists("comprobantes/" . $archivo_comprobante)) {
            unlink("comprobantes/" . $archivo_comprobante);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Comprobante eliminado exitosamente'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar la base de datos']);
    }

    $stmt->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>
