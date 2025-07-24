<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config_secure.php';
require_once 'php82_helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$pedido_id = isset($_POST['pedido_id']) ? intval($_POST['pedido_id']) : 0;
$notas = isset($_POST['notas']) ? trim($_POST['notas']) : '';

if ($pedido_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de pedido inválido']);
    exit;
}

if (empty($notas)) {
    echo json_encode(['success' => false, 'error' => 'Las notas no pueden estar vacías']);
    exit;
}

try {
    // Actualizar las notas del transportista
    $stmt = $conn->prepare("UPDATE pedidos_detal SET notas_transportista = ? WHERE id = ?");
    $stmt->bind_param('si', $notas, $pedido_id);
    
    if ($stmt->execute()) {
        // Insertar en historial si existe la tabla
        try {
            $stmt_historial = $conn->prepare("INSERT INTO historial_estados_entrega (pedido_id, estado_nuevo, notas, fecha_cambio) VALUES (?, 'nota_agregada', ?, NOW())");
            $stmt_historial->bind_param('is', $pedido_id, $notas);
            $stmt_historial->execute();
        } catch (Exception $e) {
            // Si no existe la tabla historial, continuar sin error
        }
        
        echo json_encode(['success' => true, 'message' => 'Notas actualizadas exitosamente']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al actualizar las notas']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
}

$conn->close();
?>