<?php
include 'conexion.php';
header('Content-Type: application/json');

$id = isset($_POST['id_pedido']) ? intval($_POST['id_pedido']) : 0;
if (!$id) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0; // Fallback para compatibilidad
}

if(!$id) {
    echo json_encode(['success' => false, 'error' => 'ID inválido']);
    exit;
}

// Verificar conexión
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión a la base de datos']);
    exit;
}

// Restaurar pedido: cambiar anulado a 0 y estado a sin_enviar
$stmt = $conn->prepare("UPDATE pedidos_detal SET anulado = '0', estado = 'sin_enviar' WHERE id = ? AND anulado = '1' LIMIT 1");

if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Error al preparar consulta: ' . $conn->error]);
    exit;
}

$stmt->bind_param("i", $id);
$result = $stmt->execute();

if ($result && $conn->affected_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Pedido restaurado correctamente']);
} else {
    echo json_encode(['success' => false, 'error' => 'No se pudo restaurar. ¿El pedido ya fue restaurado o no existe?']);
}

$stmt->close();
$conn->close();
?>
