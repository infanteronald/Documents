<?php
/**
 * API - Consultas QR
 * Sequoia Speed - Sistema QR
 */

header('Content-Type: application/json');
// Configuración CORS restrictiva
$allowed_origins = [
    'http://localhost',
    'http://localhost:8000',
    'https://sequoiaspeed.com',
    'https://www.sequoiaspeed.com'
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-TOKEN');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once dirname(dirname(__DIR__)) . '/config_secure.php';
require_once dirname(dirname(__DIR__)) . '/accesos/middleware/AuthMiddleware.php';

try {
    // Verificar autenticación
    $auth = new AuthMiddleware($conn);
    $current_user = $auth->requirePermission('qr', 'leer');
    
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método no permitido', 405);
    }
    
    // Validar y sanitizar tipo de consulta
    $query_type = isset($_GET['type']) ? trim($_GET['type']) : '';
    $allowed_types = ['qr_by_content', 'qr_by_product', 'qr_by_warehouse', 'search_qr', 'transaction_history', 'recent_activity', 'qr_stats', 'validate_qr', 'bulk_query'];
    
    if (!in_array($query_type, $allowed_types)) {
        throw new Exception('Tipo de consulta no válido', 400);
    }
    
    switch ($query_type) {
        case 'qr_by_content':
            $content = isset($_GET['content']) ? trim($_GET['content']) : '';
            echo json_encode(getQRByContent($conn, $content));
            break;
            
        case 'qr_by_product':
            $product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
            echo json_encode(getQRByProduct($conn, $product_id));
            break;
            
        case 'qr_by_warehouse':
            $warehouse_id = isset($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : 0;
            echo json_encode(getQRByWarehouse($conn, $warehouse_id));
            break;
            
        case 'search_qr':
            $search = isset($_GET['search']) ? trim($_GET['search']) : '';
            echo json_encode(searchQR($conn, $search));
            break;
            
        case 'transaction_history':
            $qr_id = isset($_GET['qr_id']) ? (int)$_GET['qr_id'] : 0;
            echo json_encode(getTransactionHistory($conn, $qr_id));
            break;
            
        case 'recent_activity':
            echo json_encode(getRecentActivity($conn));
            break;
            
        case 'qr_stats':
            $qr_id = isset($_GET['qr_id']) ? (int)$_GET['qr_id'] : 0;
            echo json_encode(getQRStats($conn, $qr_id));
            break;
            
        case 'validate_qr':
            $content = isset($_GET['content']) ? trim($_GET['content']) : '';
            echo json_encode(validateQR($conn, $content));
            break;
            
        case 'bulk_query':
            echo json_encode(handleBulkQuery($conn));
            break;
    }
    
} catch (Exception $e) {
    $error_code = $e->getCode() ?: 500;
    http_response_code($error_code);
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $error_code,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    error_log("QR Query API Error: " . $e->getMessage() . " | Code: " . $error_code);
}

/**
 * Buscar QR por contenido
 */
function getQRByContent($conn, $content) {
    if (empty($content)) {
        throw new Exception('Contenido QR requerido', 400);
    }
    
    // Sanitizar y validar contenido QR
    $content = trim($content);
    if (strlen($content) < 5 || strlen($content) > 255) {
        throw new Exception('Contenido QR inválido (longitud 5-255 caracteres)', 400);
    }
    
    // Validar caracteres permitidos
    if (!preg_match('/^[A-Za-z0-9\-_]+$/', $content)) {
        throw new Exception('Contenido QR contiene caracteres no permitidos', 400);
    }
    
    $query = "SELECT 
                 qc.*,
                 p.nombre as producto_name,
                 p.sku as producto_sku,
                 a.nombre as almacen_name,
                 a.codigo as almacen_code,
                 ia.stock_actual,
                 ia.ubicacion_fisica,
                 u.nombre as created_by_name
              FROM qr_codes qc
              LEFT JOIN productos p ON qc.linked_product_id = p.id
              LEFT JOIN almacenes a ON qc.linked_almacen_id = a.id
              LEFT JOIN inventario_almacen ia ON qc.linked_inventory_id = ia.id
              LEFT JOIN usuarios u ON qc.created_by = u.id
              WHERE qc.qr_content = ? AND qc.active = 1
              LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $content);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Código QR no encontrado', 404);
    }
    
    $qr_data = $result->fetch_assoc();
    $qr_data['base_data'] = json_decode($qr_data['base_data'], true);
    $qr_data['context_rules'] = json_decode($qr_data['context_rules'], true);
    
    // Obtener estadísticas de uso
    $stats_query = "SELECT 
                       COUNT(*) as total_scans,
                       COUNT(CASE WHEN processing_status = 'success' THEN 1 END) as successful_scans,
                       COUNT(CASE WHEN processing_status = 'failed' THEN 1 END) as failed_scans,
                       MAX(scanned_at) as last_scan,
                       COUNT(DISTINCT user_id) as unique_users
                    FROM qr_scan_transactions 
                    WHERE qr_code_id = ?";
    
    $stmt = $conn->prepare($stats_query);
    $stmt->bind_param('i', $qr_data['id']);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    
    return [
        'success' => true,
        'data' => [
            'qr_info' => $qr_data,
            'usage_stats' => $stats
        ]
    ];
}

/**
 * Buscar QRs por producto
 */
function getQRByProduct($conn, $product_id) {
    if (!$product_id || !filter_var($product_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
        throw new Exception('ID de producto requerido y debe ser un entero positivo', 400);
    }
    
    $product_id = (int)$product_id;
    
    $query = "SELECT 
                 qc.*,
                 p.nombre as producto_name,
                 p.sku as producto_sku,
                 a.nombre as almacen_name,
                 a.codigo as almacen_code,
                 ia.stock_actual
              FROM qr_codes qc
              JOIN productos p ON qc.linked_product_id = p.id
              LEFT JOIN almacenes a ON qc.linked_almacen_id = a.id
              LEFT JOIN inventario_almacen ia ON qc.linked_inventory_id = ia.id
              WHERE qc.linked_product_id = ? AND qc.active = 1
              ORDER BY qc.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $qr_codes = [];
    while ($row = $result->fetch_assoc()) {
        $row['base_data'] = json_decode($row['base_data'], true);
        $row['context_rules'] = json_decode($row['context_rules'], true);
        $qr_codes[] = $row;
    }
    
    // Información del producto
    $product_query = "SELECT * FROM productos WHERE id = ? AND activo = 1";
    $stmt = $conn->prepare($product_query);
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $product_info = $stmt->get_result()->fetch_assoc();
    
    if (!$product_info) {
        throw new Exception('Producto no encontrado', 404);
    }
    
    return [
        'success' => true,
        'data' => [
            'product_info' => $product_info,
            'qr_codes' => $qr_codes,
            'total_qr_codes' => count($qr_codes)
        ]
    ];
}

/**
 * Buscar QRs por almacén
 */
function getQRByWarehouse($conn, $warehouse_id) {
    if (!$warehouse_id || !filter_var($warehouse_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
        throw new Exception('ID de almacén requerido y debe ser un entero positivo', 400);
    }
    
    $warehouse_id = (int)$warehouse_id;
    $limit = isset($_GET['limit']) ? max(1, min((int)$_GET['limit'], 200)) : 50;
    $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
    
    $query = "SELECT 
                 qc.*,
                 p.nombre as producto_name,
                 p.sku as producto_sku,
                 ia.stock_actual,
                 ia.ubicacion_fisica
              FROM qr_codes qc
              LEFT JOIN productos p ON qc.linked_product_id = p.id
              LEFT JOIN inventario_almacen ia ON qc.linked_inventory_id = ia.id
              WHERE qc.linked_almacen_id = ? AND qc.active = 1
              ORDER BY qc.created_at DESC
              LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iii', $warehouse_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $qr_codes = [];
    while ($row = $result->fetch_assoc()) {
        $row['base_data'] = json_decode($row['base_data'], true);
        $row['context_rules'] = json_decode($row['context_rules'], true);
        $qr_codes[] = $row;
    }
    
    // Información del almacén
    $warehouse_query = "SELECT * FROM almacenes WHERE id = ? AND activo = 1";
    $stmt = $conn->prepare($warehouse_query);
    $stmt->bind_param('i', $warehouse_id);
    $stmt->execute();
    $warehouse_info = $stmt->get_result()->fetch_assoc();
    
    if (!$warehouse_info) {
        throw new Exception('Almacén no encontrado', 404);
    }
    
    // Total de QRs en el almacén
    $count_query = "SELECT COUNT(*) as total FROM qr_codes WHERE linked_almacen_id = ? AND active = 1";
    $stmt = $conn->prepare($count_query);
    $stmt->bind_param('i', $warehouse_id);
    $stmt->execute();
    $total_count = $stmt->get_result()->fetch_assoc()['total'];
    
    return [
        'success' => true,
        'data' => [
            'warehouse_info' => $warehouse_info,
            'qr_codes' => $qr_codes,
            'pagination' => [
                'total' => (int)$total_count,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total_count
            ]
        ]
    ];
}

/**
 * Búsqueda general de QRs
 */
function searchQR($conn, $search_term) {
    if (empty($search_term)) {
        throw new Exception('Término de búsqueda requerido', 400);
    }
    
    // Sanitizar término de búsqueda
    $search_term = trim($search_term);
    if (strlen($search_term) < 2 || strlen($search_term) > 100) {
        throw new Exception('Término de búsqueda debe tener entre 2 y 100 caracteres', 400);
    }
    
    // Escapar caracteres especiales para LIKE
    $search_term = str_replace(['%', '_'], ['\%', '\_'], $search_term);
    
    $limit = isset($_GET['limit']) ? max(1, min((int)$_GET['limit'], 100)) : 20;
    $search_param = "%$search_term%";
    
    $query = "SELECT 
                 qc.*,
                 p.nombre as producto_name,
                 p.sku as producto_sku,
                 a.nombre as almacen_name,
                 a.codigo as almacen_code,
                 ia.stock_actual,
                 CASE 
                     WHEN qc.qr_content LIKE ? THEN 'qr_content'
                     WHEN p.nombre LIKE ? THEN 'product_name'
                     WHEN p.sku LIKE ? THEN 'product_sku'
                     WHEN a.nombre LIKE ? THEN 'warehouse_name'
                     ELSE 'other'
                 END as match_type
              FROM qr_codes qc
              LEFT JOIN productos p ON qc.linked_product_id = p.id
              LEFT JOIN almacenes a ON qc.linked_almacen_id = a.id
              LEFT JOIN inventario_almacen ia ON qc.linked_inventory_id = ia.id
              WHERE qc.active = 1 AND (
                  qc.qr_content LIKE ? OR
                  p.nombre LIKE ? OR
                  p.sku LIKE ? OR
                  a.nombre LIKE ? OR
                  a.codigo LIKE ?
              )
              ORDER BY 
                  CASE match_type
                      WHEN 'qr_content' THEN 1
                      WHEN 'product_sku' THEN 2
                      WHEN 'product_name' THEN 3
                      ELSE 4
                  END,
                  qc.scan_count DESC
              LIMIT ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sssssssssi', 
        $search_param, $search_param, $search_param, $search_param,
        $search_param, $search_param, $search_param, $search_param, $search_param,
        $limit
    );
    $stmt->execute();
    $result = $stmt->get_result();
    
    $search_results = [];
    while ($row = $result->fetch_assoc()) {
        $row['base_data'] = json_decode($row['base_data'], true);
        $search_results[] = $row;
    }
    
    return [
        'success' => true,
        'data' => [
            'search_term' => $search_term,
            'results' => $search_results,
            'total_results' => count($search_results)
        ]
    ];
}

/**
 * Historial de transacciones de un QR
 */
function getTransactionHistory($conn, $qr_id) {
    if (!$qr_id || !filter_var($qr_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
        throw new Exception('ID de QR requerido y debe ser un entero positivo', 400);
    }
    
    $qr_id = (int)$qr_id;
    $limit = isset($_GET['limit']) ? max(1, min((int)$_GET['limit'], 200)) : 50;
    
    $query = "SELECT 
                 qst.*,
                 u.nombre as user_name,
                 u.usuario as username,
                 mi.tipo_movimiento,
                 mi.cantidad as movement_quantity,
                 mi.cantidad_anterior,
                 mi.cantidad_nueva
              FROM qr_scan_transactions qst
              JOIN usuarios u ON qst.user_id = u.id
              LEFT JOIN movimientos_inventario mi ON qst.generated_movement_id = mi.id
              WHERE qst.qr_code_id = ?
              ORDER BY qst.scanned_at DESC
              LIMIT ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $qr_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $row['device_info'] = json_decode($row['device_info'], true);
        $transactions[] = $row;
    }
    
    // Información del QR
    $qr_query = "SELECT qr_content, entity_type, entity_id FROM qr_codes WHERE id = ?";
    $stmt = $conn->prepare($qr_query);
    $stmt->bind_param('i', $qr_id);
    $stmt->execute();
    $qr_info = $stmt->get_result()->fetch_assoc();
    
    if (!$qr_info) {
        throw new Exception('QR no encontrado', 404);
    }
    
    return [
        'success' => true,
        'data' => [
            'qr_info' => $qr_info,
            'transactions' => $transactions,
            'total_transactions' => count($transactions)
        ]
    ];
}

/**
 * Actividad reciente del sistema
 */
function getRecentActivity($conn) {
    $limit = isset($_GET['limit']) ? max(1, min((int)$_GET['limit'], 100)) : 20;
    $hours = isset($_GET['hours']) ? max(1, min((int)$_GET['hours'], 168)) : 24; // Máximo 7 días
    
    $query = "SELECT 
                 qst.transaction_uuid,
                 qst.scanned_at,
                 qst.action_performed,
                 qst.processing_status,
                 qst.quantity_affected,
                 qc.qr_content,
                 qc.entity_type,
                 u.nombre as user_name,
                 p.nombre as producto_name,
                 a.nombre as almacen_name
              FROM qr_scan_transactions qst
              JOIN qr_codes qc ON qst.qr_code_id = qc.id
              JOIN usuarios u ON qst.user_id = u.id
              LEFT JOIN productos p ON qc.linked_product_id = p.id
              LEFT JOIN almacenes a ON qc.linked_almacen_id = a.id
              WHERE qst.scanned_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
              ORDER BY qst.scanned_at DESC
              LIMIT ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $hours, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $recent_activity = [];
    while ($row = $result->fetch_assoc()) {
        $recent_activity[] = $row;
    }
    
    return [
        'success' => true,
        'data' => [
            'recent_activity' => $recent_activity,
            'period_hours' => $hours,
            'limit' => $limit
        ]
    ];
}

/**
 * Estadísticas de un QR específico
 */
function getQRStats($conn, $qr_id) {
    if (!$qr_id || !filter_var($qr_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
        throw new Exception('ID de QR requerido y debe ser un entero positivo', 400);
    }
    
    $qr_id = (int)$qr_id;
    
    $stats_query = "SELECT 
                       COUNT(*) as total_scans,
                       COUNT(CASE WHEN processing_status = 'success' THEN 1 END) as successful_scans,
                       COUNT(CASE WHEN processing_status = 'failed' THEN 1 END) as failed_scans,
                       COUNT(CASE WHEN action_performed = 'entrada' THEN 1 END) as entries,
                       COUNT(CASE WHEN action_performed = 'salida' THEN 1 END) as exits,  
                       COUNT(CASE WHEN action_performed = 'conteo' THEN 1 END) as counts,
                       COUNT(CASE WHEN action_performed = 'consulta' THEN 1 END) as queries,
                       SUM(quantity_affected) as total_quantity_affected,
                       AVG(processing_duration_ms) as avg_processing_time,
                       COUNT(DISTINCT user_id) as unique_users,
                       MIN(scanned_at) as first_scan,
                       MAX(scanned_at) as last_scan
                    FROM qr_scan_transactions 
                    WHERE qr_code_id = ?";
    
    $stmt = $conn->prepare($stats_query);
    $stmt->bind_param('i', $qr_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    
    // Actividad por día (últimos 30 días)
    $daily_activity_query = "SELECT 
                                DATE(scanned_at) as date,
                                COUNT(*) as scan_count,
                                COUNT(CASE WHEN processing_status = 'success' THEN 1 END) as successful_scans
                             FROM qr_scan_transactions 
                             WHERE qr_code_id = ? AND DATE(scanned_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                             GROUP BY DATE(scanned_at)
                             ORDER BY date";
    
    $stmt = $conn->prepare($daily_activity_query);
    $stmt->bind_param('i', $qr_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $daily_activity = [];
    while ($row = $result->fetch_assoc()) {
        $daily_activity[] = $row;
    }
    
    // Top usuarios
    $top_users_query = "SELECT 
                           u.nombre,
                           COUNT(*) as scan_count
                        FROM qr_scan_transactions qst
                        JOIN usuarios u ON qst.user_id = u.id
                        WHERE qst.qr_code_id = ?
                        GROUP BY u.id
                        ORDER BY scan_count DESC
                        LIMIT 5";
    
    $stmt = $conn->prepare($top_users_query);
    $stmt->bind_param('i', $qr_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $top_users = [];
    while ($row = $result->fetch_assoc()) {
        $top_users[] = $row;
    }
    
    return [
        'success' => true,
        'data' => [
            'general_stats' => $stats,
            'daily_activity' => $daily_activity,
            'top_users' => $top_users
        ]
    ];
}

/**
 * Validar existencia y estado de QR
 */
function validateQR($conn, $content) {
    if (empty($content)) {
        throw new Exception('Contenido QR requerido', 400);
    }
    
    // Sanitizar y validar contenido QR
    $content = trim($content);
    if (strlen($content) < 5 || strlen($content) > 255) {
        throw new Exception('Contenido QR inválido (longitud 5-255 caracteres)', 400);
    }
    
    // Validar caracteres permitidos
    if (!preg_match('/^[A-Za-z0-9\-_]+$/', $content)) {
        throw new Exception('Contenido QR contiene caracteres no permitidos', 400);
    }
    
    $query = "SELECT 
                 id, qr_content, entity_type, entity_id, active,
                 scan_count, created_at, last_scanned_at
              FROM qr_codes 
              WHERE qr_content = ?
              LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $content);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return [
            'success' => true,
            'data' => [
                'valid' => false,
                'reason' => 'QR no encontrado'
            ]
        ];
    }
    
    $qr = $result->fetch_assoc();
    
    if (!$qr['active']) {
        return [
            'success' => true,
            'data' => [
                'valid' => false,
                'reason' => 'QR desactivado',
                'qr_info' => $qr
            ]
        ];
    }
    
    return [
        'success' => true,
        'data' => [
            'valid' => true,
            'qr_info' => $qr
        ]
    ];
}

/**
 * Consulta múltiple (bulk)
 */
function handleBulkQuery($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['queries'])) {
        throw new Exception('Lista de consultas requerida', 400);
    }
    
    $queries = $input['queries'];
    if (count($queries) > 100) {
        throw new Exception('Máximo 100 consultas por lote', 400);
    }
    
    $results = [];
    
    foreach ($queries as $query) {
        try {
            switch ($query['type']) {
                case 'validate':
                    $result = validateQR($conn, $query['content']);
                    break;
                case 'info':
                    $result = getQRByContent($conn, $query['content']);
                    break;
                default:
                    $result = [
                        'success' => false,
                        'error' => 'Tipo de consulta no válido: ' . $query['type']
                    ];
            }
            
            $results[] = [
                'query' => $query,
                'result' => $result
            ];
            
        } catch (Exception $e) {
            $results[] = [
                'query' => $query,
                'result' => [
                    'success' => false,
                    'error' => $e->getMessage()
                ]
            ];
        }
    }
    
    return [
        'success' => true,
        'data' => [
            'results' => $results,
            'total_queries' => count($queries),
            'processed' => count($results)
        ]
    ];
}
?>