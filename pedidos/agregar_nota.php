<?php
/**
 * Agregar Nota Interna - Compatible con PHP/MySQL antiguos
 * Sequoia Speed - Sistema de gestión de notas
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config_secure.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $pedido_id = intval($_POST['pedido_id'] ?? 0);
        $nueva_nota = trim($_POST['nota'] ?? '');

        if (!$pedido_id || empty($nueva_nota)) {
            echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
            exit;
        }

        // Obtener notas existentes usando método compatible
        $stmt = $conn->prepare("SELECT nota_interna FROM pedidos_detal WHERE id = ? LIMIT 1");
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Error en preparación de consulta: ' . $conn->error]);
            exit;
        }

        $stmt->bind_param("i", $pedido_id);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'error' => 'Error al ejecutar consulta: ' . $stmt->error]);
            exit;
        }

        $stmt->bind_result($notas_existentes);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Pedido no encontrado']);
            exit;
        }
        $stmt->close();

        // Si notas_existentes es null, inicializar como string vacío
        if ($notas_existentes === null) {
            $notas_existentes = '';
        }

        // Crear timestamp con zona horaria de Colombia
        date_default_timezone_set('America/Bogota');
        $timestamp = date('Y-m-d H:i:s');
        $usuario = 'Staff'; // Usuario del staff

        // Formatear nueva nota con timestamp
        $nota_con_timestamp = "[$timestamp - $usuario] $nueva_nota";

        // Combinar con notas existentes
        if (!empty($notas_existentes)) {
            $todas_las_notas = $nota_con_timestamp . "\n\n" . $notas_existentes;
        } else {
            $todas_las_notas = $nota_con_timestamp;
        }

        // Actualizar en base de datos
        $stmt = $conn->prepare("UPDATE pedidos_detal SET nota_interna = ? WHERE id = ? LIMIT 1");
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Error en preparación de update: ' . $conn->error]);
            exit;
        }

        $stmt->bind_param("si", $todas_las_notas, $pedido_id);

        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'error' => 'Error al ejecutar update: ' . $stmt->error]);
            exit;
        }

        if ($stmt->affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Nota agregada correctamente',
                'timestamp' => $timestamp
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No se realizaron cambios - Posible pedido inexistente']);
        }

        $stmt->close();

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}
?>
