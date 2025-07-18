<?php
/**
 * Obtener Estadísticas de Productos (AJAX)
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

try {
    // Obtener estadísticas generales
    $estadisticas = [];
    
    // Total de productos
    $query = "SELECT COUNT(*) as total FROM productos";
    $result = $conn->query($query);
    $estadisticas['total_productos'] = $result->fetch_assoc()['total'];
    
    // Productos activos
    $query = "SELECT COUNT(*) as total FROM productos WHERE activo = 1";
    $result = $conn->query($query);
    $estadisticas['activos'] = $result->fetch_assoc()['total'];
    
    // Productos inactivos
    $estadisticas['inactivos'] = $estadisticas['total_productos'] - $estadisticas['activos'];
    
    // Productos con stock bajo (stock_actual <= stock_minimo)
    $query = "SELECT COUNT(*) as total FROM productos WHERE stock_actual <= stock_minimo AND activo = 1";
    $result = $conn->query($query);
    $estadisticas['stock_bajo'] = $result->fetch_assoc()['total'];
    
    // Productos con stock medio (stock_actual > stock_minimo pero <= 30% del rango)
    $query = "SELECT COUNT(*) as total FROM productos 
              WHERE stock_actual > stock_minimo 
              AND stock_actual <= (stock_minimo + (stock_maximo - stock_minimo) * 0.3)
              AND activo = 1";
    $result = $conn->query($query);
    $estadisticas['stock_medio'] = $result->fetch_assoc()['total'];
    
    // Productos con stock alto
    $query = "SELECT COUNT(*) as total FROM productos 
              WHERE stock_actual > (stock_minimo + (stock_maximo - stock_minimo) * 0.3)
              AND activo = 1";
    $result = $conn->query($query);
    $estadisticas['stock_alto'] = $result->fetch_assoc()['total'];
    
    // Valor total del inventario
    $query = "SELECT SUM(precio * stock_actual) as valor_total FROM productos WHERE activo = 1";
    $result = $conn->query($query);
    $estadisticas['valor_total'] = intval($result->fetch_assoc()['valor_total'] ?? 0);
    
    // Categorías con más productos
    $query = "SELECT categoria, COUNT(*) as total 
              FROM productos 
              WHERE activo = 1 
              GROUP BY categoria 
              ORDER BY total DESC 
              LIMIT 5";
    $result = $conn->query($query);
    $estadisticas['categorias_top'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Almacenes con más productos
    $query = "SELECT almacen, COUNT(*) as total 
              FROM productos 
              WHERE activo = 1 
              GROUP BY almacen 
              ORDER BY total DESC 
              LIMIT 5";
    $result = $conn->query($query);
    $estadisticas['almacenes_top'] = $result->fetch_all(MYSQLI_ASSOC);
    
    // Productos creados en los últimos 30 días
    $query = "SELECT COUNT(*) as total FROM productos 
              WHERE fecha_creacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $result = $conn->query($query);
    $estadisticas['productos_nuevos'] = $result->fetch_assoc()['total'];
    
    // Productos actualizados en los últimos 7 días
    $query = "SELECT COUNT(*) as total FROM productos 
              WHERE fecha_actualizacion >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $result = $conn->query($query);
    $estadisticas['productos_actualizados'] = $result->fetch_assoc()['total'];
    
    echo json_encode([
        'success' => true,
        'estadisticas' => $estadisticas
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener las estadísticas: ' . $e->getMessage()
    ]);
}

exit;
?>