<?php
/**
 * Obtener información del comprobante de un pedido
 * Archivo auxiliar para el sistema de listado de pedidos modernizado
 */

header('Content-Type: application/json');

// Verificar que se proporcione el ID del pedido
if (!isset($_GET['id_pedido']) || empty($_GET['id_pedido'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ID de pedido requerido'
    ]);
    exit;
}

$id_pedido = intval($_GET['id_pedido']);

try {
    // Incluir la configuración de la base de datos
    require_once 'config_secure.php';

    // Consultar información del comprobante del pedido
    $stmt = $pdo->prepare("
        SELECT
            id,
            comprobante_pago,
            metodo_pago,
            estado,
            total,
            fecha_creacion,
            fecha_actualizacion
        FROM pedidos
        WHERE id = ?
    ");

    $stmt->execute([$id_pedido]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Pedido no encontrado'
        ]);
        exit;
    }

    // Preparar la respuesta
    $response = [
        'success' => true,
        'data' => [
            'id_pedido' => $pedido['id'],
            'comprobante_pago' => $pedido['comprobante_pago'],
            'metodo_pago' => $pedido['metodo_pago'],
            'estado' => $pedido['estado'],
            'total' => $pedido['total'],
            'fecha_creacion' => $pedido['fecha_creacion'],
            'fecha_actualizacion' => $pedido['fecha_actualizacion'],
            'tiene_comprobante' => !empty($pedido['comprobante_pago']),
            'url_comprobante' => !empty($pedido['comprobante_pago']) ? 'comprobantes/' . $pedido['comprobante_pago'] : null
        ]
    ];

    // Si existe el archivo de comprobante, verificar que esté disponible
    if (!empty($pedido['comprobante_pago'])) {
        $ruta_comprobante = 'comprobantes/' . $pedido['comprobante_pago'];

        if (file_exists($ruta_comprobante)) {
            $response['data']['archivo_existe'] = true;
            $response['data']['tamano_archivo'] = filesize($ruta_comprobante);
            $response['data']['tipo_archivo'] = pathinfo($ruta_comprobante, PATHINFO_EXTENSION);
        } else {
            $response['data']['archivo_existe'] = false;
            $response['data']['error'] = 'Archivo de comprobante no encontrado en el servidor';
        }
    }

    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno: ' . $e->getMessage()
    ]);
}
?>
