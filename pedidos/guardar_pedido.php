<?php
// Activar error reporting para debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // No mostrar en output
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.log');

header('Content-Type: application/json'); // Asegurar que la respuesta sea JSON
require_once "conexion.php";

// Log para debugging
error_log("=== INICIO GUARDAR_PEDIDO === " . date('Y-m-d H:i:s'));

// Verificar que las tablas existen
$tables_check = ['productos', 'pedidos_detal', 'pedido_detalle'];
foreach ($tables_check as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows == 0) {
        error_log("ERROR: Tabla '$table' no existe en la base de datos");
        echo json_encode(['success' => false, 'error' => "Error de configuración del servidor (tabla $table faltante)"]);
        exit;
    }
}
error_log("Todas las tablas necesarias existen");

$input = file_get_contents('php://input');
error_log("Input recibido: " . $input);

$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("Error al decodificar JSON: " . json_last_error_msg());
    echo json_encode(['success' => false, 'error' => 'Datos inválidos recibidos.']);
    exit;
}

$carrito = $data['carrito'] ?? [];
$monto = $data['monto'] ?? 0;

// Extraer información adicional del formulario
$nombre = $data['nombre'] ?? '';
$correo = $data['correo'] ?? '';
$telefono = $data['telefono'] ?? '';
$ciudad = $data['ciudad'] ?? '';
$barrio = $data['barrio'] ?? '';
$direccion = $data['direccion'] ?? '';
$metodo_pago = $data['metodo_pago'] ?? '';
$bold_order_id = $data['bold_order_id'] ?? null;

error_log("Carrito recibido: " . print_r($carrito, true));
error_log("Monto recibido: " . $monto);
error_log("Método de pago: " . $metodo_pago);
error_log("Bold Order ID: " . $bold_order_id);

// Validar estructura del carrito - EXCEPCIÓN para pedidos Bold
if (empty($carrito) && empty($bold_order_id)) {
    error_log("Carrito vacío o inválido (y no es pedido Bold)");
    echo json_encode(['success' => false, 'error' => 'Carrito vacío']);
    exit;
}

// Para pedidos Bold sin carrito, crear un item genérico
if (empty($carrito) && !empty($bold_order_id)) {
    error_log("Creando item genérico para pedido Bold sin carrito");
    $carrito = [
        [
            'nombre' => 'Pago Bold PSE',
            'precio' => $monto, // Usar monto real, incluyendo 0
            'cantidad' => 1,
            'categoria' => 'Pago Online',
            'descripcion' => 'Pago procesado mediante Bold PSE'
        ]
    ];
}

// Validar cada item del carrito
foreach ($carrito as $index => $item) {
    if (!isset($item['nombre']) || !isset($item['precio']) || !isset($item['cantidad'])) {
        error_log("Item del carrito inválido en índice $index: " . print_r($item, true));
        echo json_encode(['success' => false, 'error' => 'Datos de producto inválidos']);
        exit;
    }

    // Limpiar y validar datos
    $item['nombre'] = trim($item['nombre']);
    $item['precio'] = floatval($item['precio']);
    $item['cantidad'] = intval($item['cantidad']);

    if (empty($item['nombre']) || $item['precio'] <= 0 || $item['cantidad'] <= 0) {
        error_log("Datos de producto inválidos después de limpieza en índice $index: " . print_r($item, true));
        echo json_encode(['success' => false, 'error' => 'Datos de producto inválidos']);
        exit;
    }

    // Actualizar el item en el carrito con datos limpios
    $carrito[$index] = $item;
}

// Obtener los nombres de los productos separados por coma
$nombres = array_map(function ($item) {
    return $item['nombre'];
}, $carrito);
$pedido_str = implode(', ', $nombres);

// Insertar pedido en pedidos_detal con todos los campos necesarios
$sql = "INSERT INTO pedidos_detal (pedido, monto, nombre, direccion, telefono, ciudad, barrio, correo, metodo_pago, estado";

// Agregar campos de Bold si es un pago Bold
if ($bold_order_id) {
    $sql .= ", bold_order_id, estado_pago";
}

$sql .= ") VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'sin_enviar'";

if ($bold_order_id) {
    $sql .= ", ?, 'pendiente'";
}

$sql .= ")";

$stmt_main_pedido = $conn->prepare($sql);
if ($stmt_main_pedido === false) {
    error_log("Error al preparar la consulta para pedidos_detal: " . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor (código PED_PREP_FAIL).']);
    exit;
}

// Bind parameters según si es Bold o no
if ($bold_order_id) {
    $stmt_main_pedido->bind_param("sdssssssss", $pedido_str, $monto, $nombre, $direccion, $telefono, $ciudad, $barrio, $correo, $metodo_pago, $bold_order_id);
} else {
    $stmt_main_pedido->bind_param("sdsssssss", $pedido_str, $monto, $nombre, $direccion, $telefono, $ciudad, $barrio, $correo, $metodo_pago);
}
if ($stmt_main_pedido->execute() === false) {
    error_log("Error al ejecutar la inserción en pedidos_detal: " . $stmt_main_pedido->error);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor (código PED_EXEC_FAIL).']);
    $stmt_main_pedido->close();
    exit;
}
$pedido_id = $conn->insert_id;
$stmt_main_pedido->close();

// Insertar detalles en pedido_detalle
$stmt_detalle_pedido = $conn->prepare("INSERT INTO pedido_detalle (pedido_id, producto_id, nombre, precio, cantidad, talla) VALUES (?, ?, ?, ?, ?, ?)");
if ($stmt_detalle_pedido === false) {
    error_log("Error al preparar la consulta para pedido_detalle: " . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor (código DET_PREP_FAIL).']);
    exit;
}

foreach ($carrito as $item) {
    // Verificar si es un producto personalizado
    $es_personalizado = isset($item['personalizado']) && $item['personalizado'];
    $producto_id = $es_personalizado ? 0 : intval($item['id'] ?? 0);

    // Log para debugging
    error_log("Procesando item: " . print_r($item, true));
    error_log("Es personalizado: " . ($es_personalizado ? 'SÍ' : 'NO'));
    error_log("producto_id asignado: " . $producto_id);

    // Si es un producto personalizado, primero debemos agregarlo a la tabla de productos
    if ($es_personalizado) {
        // Verificar si ya existe un producto con el mismo nombre
        $checkStmt = $conn->prepare("SELECT id FROM productos WHERE nombre = ? LIMIT 1");
        if ($checkStmt === false) {
            // Log del error para el administrador del servidor
            error_log("Error al preparar la consulta para verificar producto existente: " . $conn->error);
            // Respuesta JSON para el cliente
            echo json_encode(['success' => false, 'error' => 'Error interno del servidor (código 1).']);
            exit;
        }
        $checkStmt->bind_param("s", $item['nombre']);
        $checkStmt->execute();
        if ($checkStmt->error) {
            // Log del error para el administrador del servidor
            error_log("Error al ejecutar la verificación de producto existente: " . $checkStmt->error);
            // Respuesta JSON para el cliente
            echo json_encode(['success' => false, 'error' => 'Error interno del servidor (código 4).']);
            $checkStmt->close();
            exit;
        }

        // Usar bind_result en lugar de get_result para compatibilidad con PHP 8.0.30
        $existingProductCount = 0;
        $checkStmt->store_result();
        $existingProductCount = $checkStmt->num_rows;
        if ($existingProductCount == 0) {
            // El producto no existe, lo agregamos a la tabla de productos
            // Verificar que el nombre no sea demasiado largo
            $nombre_truncado = substr($item['nombre'], 0, 255); // Asegurar que no exceda límites

            $insertProductStmt = $conn->prepare("INSERT INTO productos (nombre, precio, activo, categoria) VALUES (?, ?, 1, 'Personalizado')");
            if ($insertProductStmt === false) {
                // Log del error para el administrador del servidor
                error_log("Error al preparar la consulta para insertar nuevo producto personalizado: " . $conn->error);
                // Respuesta JSON para el cliente
                echo json_encode(['success' => false, 'error' => 'Error interno del servidor (código 2).']);
                $checkStmt->close(); // Cerrar la declaración anterior si esta falla
                exit;
            }
            $insertProductStmt->bind_param("sd", $nombre_truncado, $item['precio']);

            if ($insertProductStmt->execute() === false) {
                // Log detallado del error para el administrador del servidor
                error_log("Error al ejecutar la inserción del nuevo producto personalizado:");
                error_log("- Nombre: " . $nombre_truncado);
                error_log("- Precio: " . $item['precio']);
                error_log("- Error MySQL: " . $insertProductStmt->error);
                error_log("- Error conexión: " . $conn->error);

                // Respuesta JSON para el cliente
                echo json_encode([
                    'success' => false,
                    'error' => 'Error interno del servidor (código 3).',
                    'debug_info' => [
                        'mysql_error' => $insertProductStmt->error,
                        'producto_nombre' => $nombre_truncado,
                        'producto_precio' => $item['precio']
                    ]
                ]);
                $insertProductStmt->close();
                $checkStmt->close();
                exit;
            }
            $producto_id = $conn->insert_id; // Obtenemos el ID del nuevo producto
            error_log("Producto personalizado insertado exitosamente con ID: " . $producto_id);
            $insertProductStmt->close();
        } else {
            // El producto ya existe, necesitamos obtener su ID
            $checkStmt->bind_result($existing_product_id);
            $checkStmt->fetch();
            $producto_id = $existing_product_id;
        }
        $checkStmt->close();
    }    // Agregamos el detalle del pedido
    $talla = isset($item['talla']) ? trim($item['talla']) : 'N/A';
    $precio_decimal = floatval($item['precio']);
    $cantidad_int = intval($item['cantidad']);

    // Log para debugging del detalle
    error_log("Insertando detalle - pedido_id: $pedido_id, producto_id: $producto_id, nombre: {$item['nombre']}, precio: $precio_decimal, cantidad: $cantidad_int, talla: $talla");

    $stmt_detalle_pedido->bind_param("iisdis", $pedido_id, $producto_id, $item['nombre'], $precio_decimal, $cantidad_int, $talla);
    if ($stmt_detalle_pedido->execute() === false) {
        error_log("Error al ejecutar la inserción en pedido_detalle para producto '{$item['nombre']}': " . $stmt_detalle_pedido->error);
        echo json_encode(['success' => false, 'error' => "Error interno del servidor (código DET_EXEC_FAIL) al procesar producto: {$item['nombre']}."]);
        $stmt_detalle_pedido->close();
        exit;
    }
}
$stmt_detalle_pedido->close();

echo json_encode(['success' => true, 'pedido_id' => $pedido_id]);
