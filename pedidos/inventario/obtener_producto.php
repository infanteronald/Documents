<?php
/**
 * Obtener Detalles de Producto (AJAX)
 * Sequoia Speed - Módulo de Inventario
 */

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once '../config_secure.php';
require_once '../notifications/notification_helpers.php';
require_once '../php82_helpers.php';

// Configurar header para JSON
header('Content-Type: application/json');

// Verificar que sea una petición GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Obtener ID del producto
$producto_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($producto_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de producto inválido']);
    exit;
}

try {
    // Obtener datos del producto con información de almacén
    $query = "SELECT 
                p.id, p.nombre, p.descripcion, p.categoria, p.precio, 
                p.activo, p.sku, p.imagen, 
                p.fecha_creacion, p.fecha_actualizacion,
                ia.stock_actual, ia.stock_minimo, ia.stock_maximo,
                a.id as almacen_id, a.nombre as almacen_nombre,
                a.codigo as almacen_codigo
              FROM productos p
              LEFT JOIN inventario_almacen ia ON p.id = ia.producto_id
              LEFT JOIN almacenes a ON ia.almacen_id = a.id
              WHERE p.id = ? 
              LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $producto_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $producto = $result->fetch_assoc();
    
    if (!$producto) {
        echo json_encode(['success' => false, 'error' => 'Producto no encontrado']);
        exit;
    }
    
    // Formatear datos para el frontend
    $producto_formateado = [
        'id' => $producto['id'],
        'nombre' => $producto['nombre'],
        'descripcion' => $producto['descripcion'],
        'categoria' => $producto['categoria'],
        'precio' => intval($producto['precio']),
        'stock_actual' => intval($producto['stock_actual'] ?? 0),
        'stock_minimo' => intval($producto['stock_minimo'] ?? 5),
        'stock_maximo' => intval($producto['stock_maximo'] ?? 100),
        'almacen_id' => $producto['almacen_id'],
        'almacen_nombre' => $producto['almacen_nombre'],
        'almacen_codigo' => $producto['almacen_codigo'],
        'activo' => $producto['activo'],
        'sku' => $producto['sku'],
        'imagen' => $producto['imagen'],
        'fecha_creacion' => $producto['fecha_creacion'],
        'fecha_actualizacion' => $producto['fecha_actualizacion']
    ];
    
    echo json_encode([
        'success' => true,
        'producto' => $producto_formateado
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener los datos del producto: ' . $e->getMessage()
    ]);
}

exit;
?>