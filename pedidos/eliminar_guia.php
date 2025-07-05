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
    // Obtener información de la guía actual
    $stmt = $conn->prepare("SELECT guia FROM pedidos_detal WHERE id = ?");
    $stmt->bind_param("i", $id_pedido);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Pedido no encontrado']);
        exit;
    }

    $row = $result->fetch_assoc();
    $archivo_guia = $row['guia'];
    $stmt->close();

    // Actualizar base de datos
    $stmt = $conn->prepare("UPDATE pedidos_detal SET
        guia = '',
        tiene_guia = '0',
        enviado = '0'
        WHERE id = ?");
    $stmt->bind_param("i", $id_pedido);

    if ($stmt->execute()) {
        // Intentar eliminar el archivo físico si existe
        if (!empty($archivo_guia) && file_exists("guias/" . $archivo_guia)) {
            unlink("guias/" . $archivo_guia);
        }

        // Agregar nota interna del cambio
        date_default_timezone_set('America/Bogota');
        $timestamp = date('Y-m-d H:i:s');
        $nota_cambio = "[$timestamp - Sistema] Guía de envío eliminada";

        // Obtener nota actual y agregar la nueva
        $stmt = $conn->prepare("SELECT nota_interna FROM pedidos_detal WHERE id = ?");
        $stmt->bind_param("i", $id_pedido);
        $stmt->execute();
        $result = $stmt->get_result();
        $nota_row = $result->fetch_assoc();
        $notas_existentes = $nota_row['nota_interna'] ?? '';
        $stmt->close();

        if (!empty($notas_existentes)) {
            $todas_las_notas = $nota_cambio . "\n\n" . $notas_existentes;
        } else {
            $todas_las_notas = $nota_cambio;
        }

        // Actualizar nota
        $stmt = $conn->prepare("UPDATE pedidos_detal SET nota_interna = ? WHERE id = ?");
        $stmt->bind_param("si", $todas_las_notas, $id_pedido);
        $stmt->execute();
        $stmt->close();

        echo json_encode([
            'success' => true,
            'message' => 'Guía eliminada exitosamente y pedido marcado como no enviado'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar la base de datos']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}

$conn->close();
?>
