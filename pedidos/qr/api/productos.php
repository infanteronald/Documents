<?php
/**
 * API - Productos
 * Devuelve lista de productos activos para el sistema QR
 */

header('Content-Type: application/json');

defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once dirname(dirname(__DIR__)) . '/config_secure.php';
require_once dirname(dirname(__DIR__)) . '/accesos/middleware/AuthMiddleware.php';

try {
    // Verificar autenticación
    $auth = new AuthMiddleware($conn);
    $current_user = $auth->requirePermission('qr', 'leer');
    
    // Obtener parámetros
    $almacen_id = isset($_GET['almacen_id']) ? (int)$_GET['almacen_id'] : null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Construir consulta base
    $query = "SELECT p.id, p.nombre, p.sku, p.descripcion, p.precio,
                     c.nombre as categoria_nombre";
    
    // Si se especifica almacén, incluir stock
    if ($almacen_id) {
        $query .= ", COALESCE(ia.stock_actual, 0) as stock_actual";
    }
    
    $query .= " FROM productos p
                LEFT JOIN categorias_productos c ON p.categoria_id = c.id";
    
    // Join con inventario si se especifica almacén
    if ($almacen_id) {
        $query .= " LEFT JOIN inventario_almacen ia ON p.id = ia.producto_id AND ia.almacen_id = ?";
    }
    
    $query .= " WHERE p.activo = 1";
    
    // Agregar búsqueda si se especifica
    if ($search) {
        $query .= " AND (p.nombre LIKE ? OR p.sku LIKE ?)";
    }
    
    $query .= " ORDER BY p.nombre LIMIT ?";
    
    // Preparar statement
    $stmt = $conn->prepare($query);
    
    // Bind parameters
    $params = [];
    $types = '';
    
    if ($almacen_id) {
        $params[] = $almacen_id;
        $types .= 'i';
    }
    
    if ($search) {
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= 'ss';
    }
    
    $params[] = $limit;
    $types .= 'i';
    
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $productos = [];
    while ($row = $result->fetch_assoc()) {
        // Sanitizar datos
        $producto = [
            'id' => (int)$row['id'],
            'nombre' => htmlspecialchars($row['nombre'], ENT_QUOTES, 'UTF-8'),
            'sku' => htmlspecialchars($row['sku'] ?? '', ENT_QUOTES, 'UTF-8'),
            'descripcion' => htmlspecialchars($row['descripcion'] ?? '', ENT_QUOTES, 'UTF-8'),
            'precio' => (float)($row['precio'] ?? 0),
            'categoria_nombre' => htmlspecialchars($row['categoria_nombre'] ?? '', ENT_QUOTES, 'UTF-8')
        ];
        
        // Agregar stock si está disponible
        if ($almacen_id && isset($row['stock_actual'])) {
            $producto['stock_actual'] = (int)$row['stock_actual'];
        }
        
        $productos[] = $producto;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $productos,
        'total' => count($productos),
        'almacen_id' => $almacen_id,
        'search' => $search,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>