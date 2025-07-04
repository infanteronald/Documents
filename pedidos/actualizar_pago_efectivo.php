<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

include 'conexion.php';

// Recibir datos JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id_pedido']) || !isset($input['es_efectivo'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$id_pedido = intval($input['id_pedido']);
$es_efectivo = intval($input['es_efectivo']);

try {
    // Si es efectivo, marcar como pagado y sin comprobante
    if ($es_efectivo == 1) {
        $stmt = $conn->prepare("UPDATE pedidos_detal SET
            pagado = '1',
            tiene_comprobante = '0',
            comprobante = '',
            metodo_pago = CASE
                WHEN metodo_pago NOT LIKE '%efectivo%' THEN CONCAT(metodo_pago, ' (efectivo)')
                ELSE metodo_pago
            END
            WHERE id = ?");
    } else {
        // Desmarcar como efectivo, resetear estado de pago
        $stmt = $conn->prepare("UPDATE pedidos_detal SET
            pagado = '0',
            tiene_comprobante = '0',
            metodo_pago = REPLACE(metodo_pago, ' (efectivo)', '')
            WHERE id = ?");
    }

    $stmt->bind_param("i", $id_pedido);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Pago en efectivo ' . ($es_efectivo ? 'confirmado' : 'desmarcado') . ' exitosamente'
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
