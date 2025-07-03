<?php
/**
 * Obtener productos de un pedido específico
 * Versión compatible con PHP 5.3+ y MySQLi
 */

// Debug activado temporalmente
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Headers necesarios
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Función para enviar respuesta JSON
function enviarRespuesta($success, $data = array(), $error = '') {
    $response = array('success' => $success);

    if ($success) {
        $response['productos'] = isset($data['productos']) ? $data['productos'] : array();
        $response['total'] = isset($data['total']) ? $data['total'] : 0;
        $response['pedido_id'] = isset($data['pedido_id']) ? $data['pedido_id'] : 0;
    } else {
        $response['error'] = $error;
    }

    echo json_encode($response);
    exit;
}

// Verificar parámetros de entrada
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    enviarRespuesta(false, array(), 'ID de pedido requerido');
}

$id_pedido = intval($_GET['id']);
if ($id_pedido <= 0) {
    http_response_code(400);
    enviarRespuesta(false, array(), 'ID de pedido inválido: ' . $_GET['id']);
}

try {
    // Conexión manual para evitar problemas
    $servername = "68.66.226.124";
    $username = "motodota_facturacion";
    $password = "Blink.182...";
    $dbname = "motodota_factura_electronica";

    $conn = new mysqli($servername, $username, $password, $dbname);

    // Verificar conexión
    if ($conn->connect_error) {
        throw new Exception('Error de conexión: ' . $conn->connect_error);
    }

    // Establecer charset
    if (!$conn->set_charset("utf8mb4")) {
        error_log("Warning: No se pudo establecer charset utf8mb4");
    }

    // Verificar si la tabla existe
    $check_table = $conn->query("SHOW TABLES LIKE 'pedido_detalle'");
    if (!$check_table || $check_table->num_rows === 0) {
        throw new Exception('Tabla pedido_detalle no encontrada');
    }

    // Consulta simple y directa
    $query = "SELECT nombre, precio, cantidad, talla FROM pedido_detalle WHERE pedido_id = " . $id_pedido . " ORDER BY id";
    $result = $conn->query($query);

    if (!$result) {
        throw new Exception('Error en consulta: ' . $conn->error);
    }

    $productos = array();
    while ($row = $result->fetch_assoc()) {
        $productos[] = array(
            'nombre' => isset($row['nombre']) ? $row['nombre'] : 'Sin nombre',
            'precio' => isset($row['precio']) ? floatval($row['precio']) : 0,
            'cantidad' => isset($row['cantidad']) ? intval($row['cantidad']) : 0,
            'talla' => isset($row['talla']) && !empty($row['talla']) ? $row['talla'] : ''
        );
    }

    $conn->close();

    // Respuesta exitosa
    enviarRespuesta(true, array(
        'productos' => $productos,
        'total' => count($productos),
        'pedido_id' => $id_pedido
    ));

} catch (Exception $e) {
    error_log("Error en get_productos_pedido.php: " . $e->getMessage());
    http_response_code(500);
    enviarRespuesta(false, array(), $e->getMessage());
}
?>
