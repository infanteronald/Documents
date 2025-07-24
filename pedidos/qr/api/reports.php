<?php
/**
 * API - Reportes y Analytics QR
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
    
    // Note: GET requests typically don't require CSRF token
    // but this could be added if needed for sensitive reports
    
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método no permitido', 405);
    }
    
    // Validar y sanitizar tipo de reporte
    $report_type = isset($_GET['type']) ? trim($_GET['type']) : 'dashboard';
    $allowed_types = ['dashboard', 'activity', 'performance', 'usage', 'inventory_impact', 'user_activity', 'workflow_analytics', 'qr_lifecycle'];
    
    if (!in_array($report_type, $allowed_types)) {
        throw new Exception('Tipo de reporte no válido', 400);
    }
    
    switch ($report_type) {
        case 'dashboard':
            echo json_encode(getDashboardStats($conn));
            break;
            
        case 'activity':
            echo json_encode(getActivityReport($conn));
            break;
            
        case 'performance':
            echo json_encode(getPerformanceReport($conn));
            break;
            
        case 'usage':
            echo json_encode(getUsageReport($conn));
            break;
            
        case 'inventory_impact':
            echo json_encode(getInventoryImpactReport($conn));
            break;
            
        case 'user_activity':
            echo json_encode(getUserActivityReport($conn));
            break;
            
        case 'workflow_analytics':
            echo json_encode(getWorkflowAnalytics($conn));
            break;
            
        case 'qr_lifecycle':
            echo json_encode(getQRLifecycleReport($conn));
            break;
            
        default:
            throw new Exception('Tipo de reporte no válido', 400);
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
    
    error_log("QR Reports API Error: " . $e->getMessage() . " | Code: " . $error_code);
}

/**
 * Estadísticas del dashboard principal
 */
function getDashboardStats($conn) {
    $stats = [];
    
    // Estadísticas generales
    $general_query = "SELECT 
                        COUNT(DISTINCT qc.id) as total_qr_codes,
                        COUNT(DISTINCT qst.id) as total_scans,
                        COUNT(DISTINCT qst.user_id) as active_users,
                        COUNT(DISTINCT qst.id) / COUNT(DISTINCT qc.id) as avg_scans_per_qr
                      FROM qr_codes qc
                      LEFT JOIN qr_scan_transactions qst ON qc.id = qst.qr_code_id
                      WHERE qc.active = 1";
    
    $result = $conn->query($general_query);
    $general_stats = $result->fetch_assoc();
    
    // Estadísticas de hoy
    $today_query = "SELECT 
                        COUNT(*) as scans_today,
                        COUNT(CASE WHEN action_performed = 'entrada' THEN 1 END) as entries_today,
                        COUNT(CASE WHEN action_performed = 'salida' THEN 1 END) as exits_today,
                        COUNT(CASE WHEN action_performed = 'conteo' THEN 1 END) as counts_today,
                        COUNT(CASE WHEN processing_status = 'failed' THEN 1 END) as errors_today
                    FROM qr_scan_transactions 
                    WHERE DATE(scanned_at) = CURDATE()";
    
    $result = $conn->query($today_query);
    $today_stats = $result->fetch_assoc();
    
    // Estadísticas por almacén
    $warehouse_query = "SELECT 
                            a.nombre as almacen_name,
                            a.codigo as almacen_code,
                            COUNT(DISTINCT qc.id) as qr_codes,
                            COUNT(qst.id) as total_scans,
                            COUNT(CASE WHEN DATE(qst.scanned_at) = CURDATE() THEN qst.id END) as scans_today
                        FROM almacenes a
                        LEFT JOIN qr_codes qc ON a.id = qc.linked_almacen_id AND qc.active = 1
                        LEFT JOIN qr_scan_transactions qst ON qc.id = qst.qr_code_id
                        WHERE a.activo = 1
                        GROUP BY a.id
                        ORDER BY total_scans DESC";
    
    $result = $conn->query($warehouse_query);
    $warehouse_stats = [];
    while ($row = $result->fetch_assoc()) {
        $warehouse_stats[] = $row;
    }
    
    // Top productos escaneados
    $top_products_query = "SELECT 
                              p.nombre as producto_name,
                              p.sku as producto_sku,
                              COUNT(qst.id) as scan_count,
                              AVG(qst.processing_duration_ms) as avg_processing_time
                           FROM qr_codes qc
                           JOIN productos p ON qc.linked_product_id = p.id
                           JOIN qr_scan_transactions qst ON qc.id = qst.qr_code_id
                           WHERE qc.active = 1 AND DATE(qst.scanned_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                           GROUP BY p.id
                           ORDER BY scan_count DESC
                           LIMIT 10";
    
    $result = $conn->query($top_products_query);
    $top_products = [];
    while ($row = $result->fetch_assoc()) {
        $top_products[] = $row;
    }
    
    // Evolución temporal (últimos 7 días)
    $temporal_query = "SELECT 
                          DATE(scanned_at) as date,
                          COUNT(*) as total_scans,
                          COUNT(CASE WHEN action_performed = 'entrada' THEN 1 END) as entries,
                          COUNT(CASE WHEN action_performed = 'salida' THEN 1 END) as exits,
                          COUNT(CASE WHEN processing_status = 'failed' THEN 1 END) as errors
                       FROM qr_scan_transactions 
                       WHERE DATE(scanned_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                       GROUP BY DATE(scanned_at)
                       ORDER BY date";
    
    $result = $conn->query($temporal_query);
    $temporal_data = [];
    while ($row = $result->fetch_assoc()) {
        $temporal_data[] = $row;
    }
    
    return [
        'success' => true,
        'data' => [
            'general_stats' => [
                'total_qr_codes' => (int)$general_stats['total_qr_codes'],
                'total_scans' => (int)$general_stats['total_scans'],
                'active_users' => (int)$general_stats['active_users'],
                'avg_scans_per_qr' => round($general_stats['avg_scans_per_qr'], 2)
            ],
            'today_stats' => [
                'scans_today' => (int)$today_stats['scans_today'],
                'entries_today' => (int)$today_stats['entries_today'],
                'exits_today' => (int)$today_stats['exits_today'],
                'counts_today' => (int)$today_stats['counts_today'],
                'errors_today' => (int)$today_stats['errors_today']
            ],
            'warehouse_stats' => $warehouse_stats,
            'top_products' => $top_products,
            'temporal_data' => $temporal_data
        ],
        'generated_at' => date('Y-m-d H:i:s')
    ];
}

/**
 * Reporte de actividad detallada
 */
function getActivityReport($conn) {
    // Validar y sanitizar parámetros de fecha
    $date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : date('Y-m-d', strtotime('-7 days'));
    $date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : date('Y-m-d');
    
    // Validar formato de fecha
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) || !strtotime($date_from)) {
        $date_from = date('Y-m-d', strtotime('-7 days'));
    }
    
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to) || !strtotime($date_to)) {
        $date_to = date('Y-m-d');
    }
    
    // Validar rango de fechas
    if (strtotime($date_from) > strtotime($date_to)) {
        $temp = $date_from;
        $date_from = $date_to;
        $date_to = $temp;
    }
    
    // Limitar rango a máximo 90 días
    if ((strtotime($date_to) - strtotime($date_from)) > (90 * 24 * 3600)) {
        $date_from = date('Y-m-d', strtotime($date_to . ' -90 days'));
    }
    
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    $limit = max(1, min($limit, 1000)); // Entre 1 y 1000
    
    $activity_query = "SELECT 
                          qst.transaction_uuid,
                          qst.scanned_at,
                          qst.action_performed,
                          qst.processing_status,
                          qst.quantity_affected,
                          qst.processing_duration_ms,
                          qst.scan_location,
                          qst.notes,
                          qc.qr_content,
                          qc.entity_type,
                          u.nombre as user_name,
                          u.usuario as username,
                          p.nombre as producto_name,
                          p.sku as producto_sku,
                          a.nombre as almacen_name
                       FROM qr_scan_transactions qst
                       JOIN qr_codes qc ON qst.qr_code_id = qc.id
                       JOIN usuarios u ON qst.user_id = u.id
                       LEFT JOIN productos p ON qc.linked_product_id = p.id
                       LEFT JOIN almacenes a ON qc.linked_almacen_id = a.id
                       WHERE DATE(qst.scanned_at) BETWEEN ? AND ?
                       ORDER BY qst.scanned_at DESC
                       LIMIT ?";
    
    $stmt = $conn->prepare($activity_query);
    $stmt->bind_param('ssi', $date_from, $date_to, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $activities = [];
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
    
    // Resumen del período
    $summary_query = "SELECT 
                         COUNT(*) as total_transactions,
                         COUNT(CASE WHEN processing_status = 'success' THEN 1 END) as successful,
                         COUNT(CASE WHEN processing_status = 'failed' THEN 1 END) as failed,
                         AVG(processing_duration_ms) as avg_processing_time,
                         COUNT(DISTINCT user_id) as unique_users,
                         COUNT(DISTINCT qr_code_id) as unique_qr_codes
                      FROM qr_scan_transactions 
                      WHERE DATE(scanned_at) BETWEEN ? AND ?";
    
    $stmt = $conn->prepare($summary_query);
    $stmt->bind_param('ss', $date_from, $date_to);
    $stmt->execute();
    $summary = $stmt->get_result()->fetch_assoc();
    
    return [
        'success' => true,
        'data' => [
            'activities' => $activities,
            'summary' => [
                'total_transactions' => (int)$summary['total_transactions'],
                'successful' => (int)$summary['successful'],
                'failed' => (int)$summary['failed'],
                'success_rate' => $summary['total_transactions'] > 0 ? 
                    round(($summary['successful'] / $summary['total_transactions']) * 100, 2) : 0,
                'avg_processing_time' => round($summary['avg_processing_time'], 2),
                'unique_users' => (int)$summary['unique_users'],
                'unique_qr_codes' => (int)$summary['unique_qr_codes']
            ],
            'period' => [
                'date_from' => $date_from,
                'date_to' => $date_to,
                'limit' => $limit
            ]
        ],
        'generated_at' => date('Y-m-d H:i:s')
    ];
}

/**
 * Reporte de rendimiento del sistema
 */
function getPerformanceReport($conn) {
    // Métricas de rendimiento por hora del día
    $hourly_query = "SELECT 
                        HOUR(scanned_at) as hour,
                        COUNT(*) as scan_count,
                        AVG(processing_duration_ms) as avg_processing_time,
                        COUNT(CASE WHEN processing_status = 'failed' THEN 1 END) as error_count
                     FROM qr_scan_transactions 
                     WHERE DATE(scanned_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                     GROUP BY HOUR(scanned_at)
                     ORDER BY hour";
    
    $result = $conn->query($hourly_query);
    $hourly_performance = [];
    while ($row = $result->fetch_assoc()) {
        $hourly_performance[] = $row;
    }
    
    // Top errores más frecuentes
    $errors_query = "SELECT 
                        error_message,
                        COUNT(*) as error_count,
                        COUNT(*) * 100.0 / (SELECT COUNT(*) FROM qr_scan_transactions WHERE processing_status = 'failed') as error_percentage
                     FROM qr_scan_transactions 
                     WHERE processing_status = 'failed' AND error_message IS NOT NULL
                     GROUP BY error_message
                     ORDER BY error_count DESC
                     LIMIT 10";
    
    $result = $conn->query($errors_query);
    $top_errors = [];
    while ($row = $result->fetch_assoc()) {
        $top_errors[] = $row;
    }
    
    // Rendimiento por método de escaneo
    $method_query = "SELECT 
                        scan_method,
                        COUNT(*) as scan_count,
                        AVG(processing_duration_ms) as avg_processing_time,
                        COUNT(CASE WHEN processing_status = 'failed' THEN 1 END) as error_count
                     FROM qr_scan_transactions 
                     WHERE DATE(scanned_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                     GROUP BY scan_method
                     ORDER BY scan_count DESC";
    
    $result = $conn->query($method_query);
    $method_performance = [];
    while ($row = $result->fetch_assoc()) {
        $method_performance[] = $row;
    }
    
    // Estadísticas de la base de datos
    $db_stats_query = "SELECT 
                          (SELECT COUNT(*) FROM qr_codes WHERE active = 1) as active_qr_codes,
                          (SELECT COUNT(*) FROM qr_scan_transactions WHERE DATE(scanned_at) = CURDATE()) as today_transactions,
                          (SELECT AVG(CHAR_LENGTH(base_data)) FROM qr_codes WHERE active = 1) as avg_qr_data_size,
                          (SELECT COUNT(*) FROM qr_workflow_config WHERE active = 1) as active_workflows";
    
    $result = $conn->query($db_stats_query);
    $db_stats = $result->fetch_assoc();
    
    return [
        'success' => true,
        'data' => [
            'hourly_performance' => $hourly_performance,
            'top_errors' => $top_errors,
            'method_performance' => $method_performance,
            'db_stats' => [
                'active_qr_codes' => (int)$db_stats['active_qr_codes'],
                'today_transactions' => (int)$db_stats['today_transactions'],
                'avg_qr_data_size' => round($db_stats['avg_qr_data_size'], 2),
                'active_workflows' => (int)$db_stats['active_workflows']
            ]
        ],
        'generated_at' => date('Y-m-d H:i:s')
    ];
}

/**
 * Reporte de uso e impacto en inventario
 */
function getInventoryImpactReport($conn) {
    // Validar y sanitizar parámetros de fecha
    $date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
    $date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : date('Y-m-d');
    
    // Validar formato de fecha
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) || !strtotime($date_from)) {
        $date_from = date('Y-m-d', strtotime('-30 days'));
    }
    
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to) || !strtotime($date_to)) {
        $date_to = date('Y-m-d');
    }
    
    // Validar rango de fechas
    if (strtotime($date_from) > strtotime($date_to)) {
        $temp = $date_from;
        $date_from = $date_to;
        $date_to = $temp;
    }
    
    // Movimientos generados por QR
    $movements_query = "SELECT 
                           mi.tipo_movimiento,
                           COUNT(*) as movement_count,
                           SUM(mi.cantidad) as total_quantity,
                           COUNT(DISTINCT mi.producto_id) as products_affected,
                           COUNT(DISTINCT mi.almacen_id) as warehouses_affected
                        FROM movimientos_inventario mi
                        JOIN qr_scan_transactions qst ON mi.id = qst.generated_movement_id
                        WHERE DATE(mi.fecha_movimiento) BETWEEN ? AND ?
                        GROUP BY mi.tipo_movimiento
                        ORDER BY movement_count DESC";
    
    $stmt = $conn->prepare($movements_query);
    $stmt->bind_param('ss', $date_from, $date_to);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $qr_movements = [];
    while ($row = $result->fetch_assoc()) {
        $qr_movements[] = $row;
    }
    
    // Productos más impactados
    $products_query = "SELECT 
                          p.nombre as producto_name,
                          p.sku as producto_sku,
                          COUNT(mi.id) as movement_count,
                          SUM(CASE WHEN mi.tipo_movimiento = 'entrada' THEN mi.cantidad ELSE 0 END) as total_entries,
                          SUM(CASE WHEN mi.tipo_movimiento = 'salida' THEN mi.cantidad ELSE 0 END) as total_exits,
                          (SELECT stock_actual FROM inventario_almacen WHERE producto_id = p.id LIMIT 1) as current_stock
                       FROM productos p
                       JOIN movimientos_inventario mi ON p.id = mi.producto_id
                       JOIN qr_scan_transactions qst ON mi.id = qst.generated_movement_id
                       WHERE DATE(mi.fecha_movimiento) BETWEEN ? AND ?
                       GROUP BY p.id
                       ORDER BY movement_count DESC
                       LIMIT 20";
    
    $stmt = $conn->prepare($products_query);
    $stmt->bind_param('ss', $date_from, $date_to);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $impacted_products = [];
    while ($row = $result->fetch_assoc()) {
        $impacted_products[] = $row;
    }
    
    // Comparación QR vs Manual
    $comparison_query = "SELECT 
                            'QR' as source,
                            COUNT(*) as movement_count,
                            AVG(cantidad) as avg_quantity
                         FROM movimientos_inventario mi
                         JOIN qr_scan_transactions qst ON mi.id = qst.generated_movement_id
                         WHERE DATE(mi.fecha_movimiento) BETWEEN ? AND ?
                         
                         UNION ALL
                         
                         SELECT 
                            'Manual' as source,
                            COUNT(*) as movement_count,
                            AVG(cantidad) as avg_quantity
                         FROM movimientos_inventario mi
                         LEFT JOIN qr_scan_transactions qst ON mi.id = qst.generated_movement_id
                         WHERE qst.id IS NULL AND DATE(mi.fecha_movimiento) BETWEEN ? AND ?";
    
    $stmt = $conn->prepare($comparison_query);
    $stmt->bind_param('ssss', $date_from, $date_to, $date_from, $date_to);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $source_comparison = [];
    while ($row = $result->fetch_assoc()) {
        $source_comparison[] = $row;
    }
    
    return [
        'success' => true,
        'data' => [
            'qr_movements' => $qr_movements,
            'impacted_products' => $impacted_products,
            'source_comparison' => $source_comparison,
            'period' => [
                'date_from' => $date_from,
                'date_to' => $date_to
            ]
        ],
        'generated_at' => date('Y-m-d H:i:s')
    ];
}

/**
 * Reporte de actividad de usuarios
 */
function getUserActivityReport($conn) {
    $user_stats_query = "SELECT 
                            u.id,
                            u.nombre,
                            u.usuario,
                            r.nombre as rol_name,
                            COUNT(qst.id) as total_scans,
                            COUNT(CASE WHEN DATE(qst.scanned_at) = CURDATE() THEN qst.id END) as scans_today,
                            COUNT(CASE WHEN DATE(qst.scanned_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN qst.id END) as scans_week,
                            COUNT(CASE WHEN qst.processing_status = 'failed' THEN qst.id END) as failed_scans,
                            AVG(qst.processing_duration_ms) as avg_processing_time,
                            MAX(qst.scanned_at) as last_activity
                         FROM usuarios u
                         JOIN roles r ON u.rol_id = r.id
                         LEFT JOIN qr_scan_transactions qst ON u.id = qst.user_id
                         WHERE u.activo = 1
                         GROUP BY u.id
                         HAVING total_scans > 0
                         ORDER BY total_scans DESC";
    
    $result = $conn->query($user_stats_query);
    $user_stats = [];
    while ($row = $result->fetch_assoc()) {
        $user_stats[] = $row;
    }
    
    // Acciones más frecuentes por usuario
    $actions_query = "SELECT 
                         u.nombre,
                         qst.action_performed,
                         COUNT(*) as action_count
                      FROM usuarios u
                      JOIN qr_scan_transactions qst ON u.id = qst.user_id
                      WHERE DATE(qst.scanned_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                      GROUP BY u.id, qst.action_performed
                      ORDER BY u.nombre, action_count DESC";
    
    $result = $conn->query($actions_query);
    $user_actions = [];
    while ($row = $result->fetch_assoc()) {
        $user_actions[] = $row;
    }
    
    return [
        'success' => true,
        'data' => [
            'user_stats' => $user_stats,
            'user_actions' => $user_actions
        ],
        'generated_at' => date('Y-m-d H:i:s')
    ];
}

/**
 * Analytics de workflows
 */
function getWorkflowAnalytics($conn) {
    $workflow_stats_query = "SELECT 
                                wf.workflow_name,
                                wf.workflow_type,
                                COUNT(qst.id) as usage_count,
                                COUNT(CASE WHEN qst.processing_status = 'success' THEN 1 END) as successful_uses,
                                COUNT(CASE WHEN qst.processing_status = 'failed' THEN 1 END) as failed_uses,
                                AVG(qst.processing_duration_ms) as avg_processing_time,
                                COUNT(DISTINCT qst.user_id) as unique_users
                             FROM qr_workflow_config wf
                             LEFT JOIN qr_scan_transactions qst ON wf.workflow_name = qst.workflow_type
                             WHERE wf.active = 1
                             GROUP BY wf.id
                             ORDER BY usage_count DESC";
    
    $result = $conn->query($workflow_stats_query);
    $workflow_analytics = [];
    while ($row = $result->fetch_assoc()) {
        $success_rate = $row['usage_count'] > 0 ? 
            round(($row['successful_uses'] / $row['usage_count']) * 100, 2) : 0;
        
        $row['success_rate'] = $success_rate;
        $workflow_analytics[] = $row;
    }
    
    return [
        'success' => true,
        'data' => [
            'workflow_analytics' => $workflow_analytics
        ],
        'generated_at' => date('Y-m-d H:i:s')
    ];
}

/**
 * Reporte de ciclo de vida de QR
 */
function getQRLifecycleReport($conn) {
    $lifecycle_query = "SELECT 
                           qc.qr_content,
                           qc.entity_type,
                           qc.created_at,
                           qc.scan_count,
                           qc.first_scanned_at,
                           qc.last_scanned_at,
                           p.nombre as producto_name,
                           DATEDIFF(CURDATE(), qc.created_at) as days_since_creation,
                           CASE 
                               WHEN qc.first_scanned_at IS NULL THEN 'never_scanned'
                               WHEN qc.last_scanned_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'active'
                               WHEN qc.last_scanned_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'low_activity'
                               ELSE 'inactive'
                           END as lifecycle_status
                        FROM qr_codes qc
                        LEFT JOIN productos p ON qc.linked_product_id = p.id
                        WHERE qc.active = 1
                        ORDER BY qc.created_at DESC";
    
    $result = $conn->query($lifecycle_query);
    $qr_lifecycle = [];
    while ($row = $result->fetch_assoc()) {
        $qr_lifecycle[] = $row;
    }
    
    // Resumen por estado
    $status_summary_query = "SELECT 
                                CASE 
                                    WHEN first_scanned_at IS NULL THEN 'never_scanned'
                                    WHEN last_scanned_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'active'
                                    WHEN last_scanned_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'low_activity'
                                    ELSE 'inactive'
                                END as lifecycle_status,
                                COUNT(*) as qr_count,
                                AVG(scan_count) as avg_scans
                             FROM qr_codes
                             WHERE active = 1
                             GROUP BY lifecycle_status";
    
    $result = $conn->query($status_summary_query);
    $status_summary = [];
    while ($row = $result->fetch_assoc()) {
        $status_summary[] = $row;
    }
    
    return [
        'success' => true,
        'data' => [
            'qr_lifecycle' => $qr_lifecycle,
            'status_summary' => $status_summary
        ],
        'generated_at' => date('Y-m-d H:i:s')
    ];
}
?>