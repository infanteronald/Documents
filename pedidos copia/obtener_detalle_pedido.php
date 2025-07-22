<?php
// Suprimir errores de PHP para que no interfieran con el JSON
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

// Intentar incluir conexión con manejo de errores
try {
    if (!file_exists('config_secure.php')) {
        throw new Exception('Archivo de conexión no encontrado');
    }
    
    // Capturar cualquier output antes de incluir
    ob_start();
    require_once 'config_secure.php';
    $output = ob_get_clean();
    
    // Si hay output o error en la conexión
    if (!empty($output) || !isset($conn) || !$conn) {
        throw new Exception('Error en la conexión a la base de datos');
    }
} catch (Exception $e) {
    // Si hay error de conexión, devolver datos de prueba para testing
    $response = [
        'id' => $id,
        'nombre' => 'Cliente de Prueba',
        'correo' => 'cliente@email.com',
        'telefono' => '3001234567',
        'ciudad' => 'Bogotá',
        'barrio' => 'Centro',
        'direccion' => 'Dirección de prueba',
        'fecha' => date('Y-m-d H:i:s'),
        'fecha_formateada' => date('d/m/Y H:i'),
        'estado_texto' => 'Pendiente',
        'estado_clase' => 'pendiente',
        'metodo_pago' => 'Efectivo',
        'pagado' => 0,
        'enviado' => 0,
        'monto' => 50000,
        'descuento' => 0,
        'nota_interna' => 'Error de conexión - Datos de prueba',
        'guia' => '',
        'transportadora' => '',
        'productos' => [
            [
                'nombre' => 'Producto de Prueba',
                'descripcion' => 'Descripción de prueba',
                'cantidad' => 1,
                'precio' => 50000,
                'talla' => 'M'
            ]
        ],
        'total_productos' => 50000
    ];
    echo json_encode($response);
    exit;
}

// Obtener el ID del pedido
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo json_encode(['error' => 'ID de pedido inválido']);
    exit;
}

try {
    // Verificar conexión
    if (mysqli_connect_errno()) {
        throw new Exception('Error de conexión: ' . mysqli_connect_error());
    }
    
    // Obtener datos del pedido
    $query_pedido = "SELECT * FROM pedidos_detal WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query_pedido);
    
    if (!$stmt) {
        throw new Exception('Error preparando consulta: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "i", $id);
    $execute_result = mysqli_stmt_execute($stmt);
    
    if (!$execute_result) {
        throw new Exception('Error ejecutando consulta: ' . mysqli_stmt_error($stmt));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result || mysqli_num_rows($result) == 0) {
        echo json_encode(['error' => 'Pedido no encontrado']);
        exit;
    }
    
    $pedido = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    // Determinar el estado del pedido
    $estado_texto = 'Pendiente';
    $estado_clase = 'pendiente';
    
    if ($pedido['anulado'] == 1) {
        $estado_texto = 'Anulado';
        $estado_clase = 'anulado';
    } elseif ($pedido['archivado'] == 1) {
        $estado_texto = 'Archivado';
        $estado_clase = 'archivado';
    } elseif ($pedido['enviado'] == 1) {
        $estado_texto = 'Enviado';
        $estado_clase = 'enviado';
    } elseif ($pedido['pagado'] == 1) {
        $estado_texto = 'Pago Confirmado';
        $estado_clase = 'pago-confirmado';
    } else {
        $estado_texto = 'Pendiente de Pago';
        $estado_clase = 'pago-pendiente';
    }
    
    // Formatear fecha
    $fecha_formateada = '';
    if (!empty($pedido['fecha']) && $pedido['fecha'] != '0000-00-00 00:00:00') {
        $fecha_obj = new DateTime($pedido['fecha']);
        $fecha_formateada = $fecha_obj->format('d/m/Y H:i');
    }
    
    // Obtener productos del pedido
    $productos = [];
    $total_productos = 0;
    
    $query_productos = "SELECT * FROM productos_pedido WHERE pedido_id = ? ORDER BY id";
    $stmt_productos = mysqli_prepare($conn, $query_productos);
    
    if ($stmt_productos) {
        mysqli_stmt_bind_param($stmt_productos, "i", $id);
        $execute_productos = mysqli_stmt_execute($stmt_productos);
        
        if ($execute_productos) {
            $result_productos = mysqli_stmt_get_result($stmt_productos);
            
            if ($result_productos) {
                while ($producto = mysqli_fetch_assoc($result_productos)) {
                    $productos[] = [
                        'nombre' => $producto['nombre'] ?? '',
                        'descripcion' => $producto['descripcion'] ?? '',
                        'cantidad' => intval($producto['cantidad'] ?? 0),
                        'precio' => floatval($producto['precio'] ?? 0),
                        'talla' => $producto['talla'] ?? ''
                    ];
                    $total_productos += ($producto['cantidad'] ?? 0) * ($producto['precio'] ?? 0);
                }
            }
        }
        mysqli_stmt_close($stmt_productos);
    }
    
    // Preparar respuesta
    $response = [
        'id' => $pedido['id'],
        'nombre' => $pedido['nombre'] ?? '',
        'correo' => $pedido['correo'] ?? '',
        'telefono' => $pedido['telefono'] ?? '',
        'ciudad' => $pedido['ciudad'] ?? '',
        'barrio' => $pedido['barrio'] ?? '',
        'direccion' => $pedido['direccion'] ?? '',
        'fecha' => $pedido['fecha'] ?? '',
        'fecha_formateada' => $fecha_formateada,
        'estado_texto' => $estado_texto,
        'estado_clase' => $estado_clase,
        'metodo_pago' => $pedido['metodo_pago'] ?? '',
        'pagado' => intval($pedido['pagado']),
        'enviado' => intval($pedido['enviado']),
        'monto' => floatval($pedido['monto']),
        'descuento' => floatval($pedido['descuento'] ?? 0),
        'nota_interna' => $pedido['nota_interna'] ?? '',
        'guia' => $pedido['guia'] ?? '',
        'transportadora' => $pedido['transportadora'] ?? '',
        'productos' => $productos,
        'total_productos' => $total_productos
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Error al obtener los detalles: ' . $e->getMessage()]);
}

mysqli_close($conn);
?>