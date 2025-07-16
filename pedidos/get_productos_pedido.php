<?php
/**
 * Obtener productos de un pedido específico
 * Versión compatible con PHP 5.3+ y MySQLi
 */

// Debug activado temporalmente
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir helpers PHP 8.2
require_once 'php82_helpers.php';

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

    // Log para debugging
    error_log("get_productos_pedido.php: Buscando productos para pedido ID: " . $id_pedido);

    // Verificar si la tabla existe
    $check_table = $conn->query("SHOW TABLES LIKE 'pedido_detalle'");
    if (!$check_table || $check_table->num_rows === 0) {
        throw new Exception('Tabla pedido_detalle no encontrada');
    }

    // Obtener datos completos del pedido (cliente + descuento)
    $stmt_pedido = $conn->prepare("SELECT 
        id, monto, descuento,
        nombre_cliente, email_cliente, telefono_cliente, 
        ciudad_cliente, barrio_cliente, direccion_entrega,
        metodo_pago, fecha_pedido, nota_interna,
        pagado, enviado, anulado
        FROM pedidos_detal WHERE id = ? LIMIT 1");
    
    $cliente_data = [];
    $descuento = 0;
    
    if ($stmt_pedido) {
        $stmt_pedido->bind_param("i", $id_pedido);
        $stmt_pedido->execute();
        $result_pedido = $stmt_pedido->get_result();
        
        if ($row = $result_pedido->fetch_assoc()) {
            $cliente_data = $row;
            $descuento = floatval($row['descuento'] ?? 0);
        }
        $stmt_pedido->close();
    }

    // Usar prepared statement para evitar problemas
    $stmt = $conn->prepare("SELECT nombre, precio, cantidad, talla FROM pedido_detalle WHERE pedido_id = ? ORDER BY id");
    if (!$stmt) {
        throw new Exception('Error preparando consulta: ' . $conn->error);
    }

    $stmt->bind_param("i", $id_pedido);

    if (!$stmt->execute()) {
        throw new Exception('Error ejecutando consulta: ' . $stmt->error);
    }

    // Usar bind_result de forma explícita y robusta
    $productos = array();

    if (!$stmt->bind_result($nombre, $precio, $cantidad, $talla)) {
        throw new Exception('Error en bind_result: ' . $stmt->error);
    }

    // Fetch con variables limpias en cada iteración
    while ($stmt->fetch()) {
        // Crear una copia de los valores para evitar problemas de referencia
        $producto = array(
            'nombre' => is_null($nombre) ? 'Sin nombre' : trim((string)$nombre),
            'precio' => is_null($precio) ? 0.0 : (float)$precio,
            'cantidad' => is_null($cantidad) ? 0 : (int)$cantidad,
            'talla' => is_null($talla) ? '' : trim((string)$talla)
        );

        // Log de cada producto para debugging
        error_log("Producto encontrado: " . json_encode($producto));

        $productos[] = $producto;

        // Limpiar variables para siguiente iteración
        $nombre = null;
        $precio = null;
        $cantidad = null;
        $talla = null;
    }

    $stmt->close();

    error_log("get_productos_pedido.php: Productos encontrados: " . count($productos));

    $conn->close();

    // Log del resultado
    error_log("get_productos_pedido.php: Encontrados " . count($productos) . " productos");
    error_log("get_productos_pedido.php: Productos: " . json_encode($productos));

    // Calcular totales
    $subtotal = 0;
    foreach ($productos as $producto) {
        $subtotal += $producto['precio'] * $producto['cantidad'];
    }
    $total_final = $subtotal - $descuento;

    // Log para debugging del descuento
    error_log("get_productos_pedido.php: ID pedido: $id_pedido, Descuento encontrado: $descuento, Subtotal: $subtotal, Total final: $total_final");

    // Respuesta exitosa con datos completos
    enviarRespuesta(true, array(
        'productos' => $productos,
        'total' => count($productos),
        'pedido_id' => $id_pedido,
        'subtotal' => $subtotal,
        'descuento' => $descuento,
        'total_final' => $total_final,
        'cliente' => array(
            'nombre' => $cliente_data['nombre_cliente'] ?? 'No disponible',
            'email' => $cliente_data['email_cliente'] ?? 'No disponible',
            'telefono' => $cliente_data['telefono_cliente'] ?? 'No disponible',
            'ciudad' => $cliente_data['ciudad_cliente'] ?? 'No disponible',
            'barrio' => $cliente_data['barrio_cliente'] ?? 'No disponible',
            'direccion' => $cliente_data['direccion_entrega'] ?? 'No disponible',
            'metodo_pago' => $cliente_data['metodo_pago'] ?? 'No disponible',
            'fecha_pedido' => $cliente_data['fecha_pedido'] ?? 'No disponible',
            'nota_interna' => $cliente_data['nota_interna'] ?? '',
            'pagado' => ($cliente_data['pagado'] ?? 0) == 1,
            'enviado' => ($cliente_data['enviado'] ?? 0) == 1,
            'anulado' => ($cliente_data['anulado'] ?? 0) == 1
        ),
        'debug_info' => "Pedido: $id_pedido, Descuento: $descuento, Subtotal: $subtotal"
    ));

} catch (Exception $e) {
    error_log("Error en get_productos_pedido.php para pedido ID $id_pedido: " . $e->getMessage());
    http_response_code(500);
    enviarRespuesta(false, array(), "Error al cargar productos: " . $e->getMessage());
}
?>
