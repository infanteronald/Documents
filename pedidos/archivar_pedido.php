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

// Archivar pedido: cambiar archivado a 1
$stmt = $conn->prepare("UPDATE pedidos_detal SET archivado = '1' WHERE id = ? LIMIT 1");

if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Error al preparar consulta: ' . $conn->error]);
    exit;
}

$stmt->bind_param("i", $id);
$result = $stmt->execute();

if ($result && $conn->affected_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Pedido archivado correctamente']);
} else {
    echo json_encode(['success' => false, 'error' => 'No se pudo archivar. ¿El pedido ya fue archivado o no existe?']);
}

$stmt->close();
$conn->close();
?>
