<?php
/**
 * API - Sistema de Alertas QR
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
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-TOKEN');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once dirname(dirname(__DIR__)) . '/config_secure.php';
require_once dirname(dirname(__DIR__)) . '/accesos/middleware/AuthMiddleware.php';
require_once dirname(__DIR__) . '/security_headers.php';

// Establecer headers de seguridad para API
setAPISecurityHeaders();

try {
    // Verificar autenticación
    $auth = new AuthMiddleware($conn);
    $current_user = $auth->requirePermission('qr', 'leer');
    
    // Verificar CSRF token para requests POST (state-changing operations)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!$auth->verifyCSRF($csrf_token)) {
            throw new Exception('Token CSRF inválido', 403);
        }
    }
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        handleGetRequest($conn, $current_user);
    } elseif ($method === 'POST') {
        handlePostRequest($conn, $current_user);
    } else {
        throw new Exception('Método no permitido', 405);
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
    
    error_log("QR Alerts API Error: " . $e->getMessage() . " | Code: " . $error_code);
}

/**
 * Manejar requests GET
 */
function handleGetRequest($conn, $current_user) {
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            echo json_encode(getAlerts($conn));
            break;
            
        case 'check':
            echo json_encode(checkForNewAlerts($conn, $current_user));
            break;
            
        case 'stats':
            echo json_encode(getAlertStats($conn));
            break;
            
        case 'config':
            echo json_encode(getAlertConfig($conn));
            break;
            
        default:
            throw new Exception('Acción no válida', 400);
    }
}

/**
 * Manejar requests POST
 */
function handlePostRequest($conn, $current_user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['action'])) {
        throw new Exception('Acción requerida', 400);
    }
    
    switch ($input['action']) {
        case 'resolve':
            echo json_encode(resolveAlert($conn, $current_user, $input));
            break;
            
        case 'snooze':
            echo json_encode(snoozeAlert($conn, $current_user, $input));
            break;
            
        case 'create':
            echo json_encode(createAlert($conn, $current_user, $input));
            break;
            
        case 'save_config':
            echo json_encode(saveAlertConfig($conn, $current_user, $input));
            break;
            
        default:
            throw new Exception('Acción no válida', 400);
    }
}

/**
 * Obtener lista de alertas
 */
function getAlerts($conn) {
    $filter_priority = $_GET['priority'] ?? '';
    $filter_type = $_GET['type'] ?? '';
    $filter_status = $_GET['status'] ?? '';
    $limit = min((int)($_GET['limit'] ?? 50), 200);
    
    // Validar inputs
    if ($filter_priority && !in_array($filter_priority, ['alta', 'media', 'baja'])) {
        throw new Exception('Prioridad inválida', 400);
    }
    
    if ($filter_type && !preg_match('/^[a-z_]+$/', $filter_type)) {
        throw new Exception('Tipo de alerta inválido', 400);
    }
    
    $where_conditions = ['ai.activa = 1'];
    $params = [];
    $types = '';
    
    if ($filter_priority) {
        $where_conditions[] = 'ai.prioridad = ?';
        $params[] = $filter_priority;
        $types .= 's';
    }
    
    if ($filter_type) {
        $where_conditions[] = 'ai.tipo = ?';
        $params[] = $filter_type;
        $types .= 's';
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    $query = "SELECT 
                 ai.*,
                 p.nombre as producto_name,
                 p.sku as producto_sku,
                 a.nombre as almacen_name,
                 u.nombre as created_by_name
              FROM alertas_inventario ai
              LEFT JOIN productos p ON ai.producto_id = p.id
              LEFT JOIN almacenes a ON ai.almacen_id = a.id
              LEFT JOIN usuarios u ON ai.usuario_responsable = u.id
              $where_clause
              ORDER BY ai.prioridad DESC, ai.fecha_creacion DESC
              LIMIT ?";
    
    $params[] = $limit;
    $types .= 'i';
    
    $stmt = $conn->prepare($query);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $alerts = [];
    while ($row = $result->fetch_assoc()) {
        $alerts[] = $row;
    }
    
    return [
        'success' => true,
        'data' => [
            'alerts' => $alerts,
            'total' => count($alerts),
            'filters' => [
                'priority' => $filter_priority,
                'type' => $filter_type,
                'status' => $filter_status
            ]
        ]
    ];
}

/**
 * Verificar nuevas alertas
 */
function checkForNewAlerts($conn, $current_user) {
    $new_alerts_count = 0;
    
    // 1. Verificar stock bajo
    $new_alerts_count += checkStockAlerts($conn);
    
    // 2. Verificar errores de escaneo
    $new_alerts_count += checkScanErrorAlerts($conn);
    
    // 3. Verificar QR inactivos
    $new_alerts_count += checkInactiveQRAlerts($conn);
    
    // 4. Verificar discrepancias de inventario
    $new_alerts_count += checkInventoryDiscrepancies($conn);
    
    return [
        'success' => true,
        'data' => [
            'new_alerts' => $new_alerts_count,
            'checked_at' => date('Y-m-d H:i:s')
        ]
    ];
}

/**
 * Verificar alertas de stock bajo
 */
function checkStockAlerts($conn) {
    // Obtener umbral de configuración con validación
    $config = getSystemConfig($conn, 'alert_stock_threshold');
    $threshold = 10; // Default seguro
    
    if ($config && isset($config['config_value']['threshold'])) {
        $threshold = (int)$config['config_value']['threshold'];
        if ($threshold <= 0 || $threshold > 10000) {
            error_log("Umbral de stock inválido: $threshold, usando default 10");
            $threshold = 10;
        }
    }
    
    // Buscar productos con stock bajo que no tengan alerta activa
    $query = "SELECT DISTINCT 
                 p.id as producto_id,
                 p.nombre as producto_name,
                 ia.almacen_id,
                 ia.stock_actual,
                 a.nombre as almacen_name
              FROM inventario_almacen ia
              JOIN productos p ON ia.producto_id = p.id
              JOIN almacenes a ON ia.almacen_id = a.id
              LEFT JOIN alertas_inventario ai ON (
                  ai.producto_id = p.id 
                  AND ai.almacen_id = ia.almacen_id 
                  AND ai.tipo = 'stock_bajo' 
                  AND ai.activa = 1
              )
              WHERE ia.stock_actual <= ? 
              AND ia.stock_actual > 0
              AND p.activo = 1 
              AND a.activo = 1
              AND ai.id IS NULL";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $threshold);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $alerts_created = 0;
    
    while ($row = $result->fetch_assoc()) {
        $alert_data = [
            'tipo' => 'stock_bajo',
            'prioridad' => $row['stock_actual'] <= ($threshold / 2) ? 'alta' : 'media',
            'titulo' => 'Stock Bajo - ' . $row['producto_name'],
            'descripcion' => "El producto {$row['producto_name']} tiene stock bajo ({$row['stock_actual']} unidades) en {$row['almacen_name']}",
            'producto_id' => $row['producto_id'],
            'almacen_id' => $row['almacen_id'],
            'datos_adicionales' => json_encode([
                'stock_actual' => $row['stock_actual'],
                'threshold' => $threshold,
                'detection_method' => 'automatic_check'
            ])
        ];
        
        if (createAlertRecord($conn, $alert_data)) {
            $alerts_created++;
        }
    }
    
    return $alerts_created;
}

/**
 * Verificar alertas de errores de escaneo
 */
function checkScanErrorAlerts($conn) {
    // Obtener umbral de configuración
    $config = getSystemConfig($conn, 'alert_error_threshold');
    $threshold = $config ? ($config['config_value']['max_errors_per_hour'] ?? 5) : 5;
    
    // Buscar usuarios con muchos errores en la última hora
    $query = "SELECT 
                 user_id,
                 u.nombre as user_name,
                 COUNT(*) as error_count
              FROM qr_scan_transactions qst
              JOIN usuarios u ON qst.user_id = u.id
              WHERE qst.processing_status = 'failed'
              AND qst.scanned_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
              GROUP BY user_id
              HAVING error_count >= ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $threshold);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $alerts_created = 0;
    
    while ($row = $result->fetch_assoc()) {
        // Verificar si ya existe una alerta similar reciente
        $existing_check = "SELECT id FROM alertas_inventario 
                          WHERE tipo = 'error_scan' 
                          AND JSON_EXTRACT(datos_adicionales, '$.user_id') = ? 
                          AND activa = 1 
                          AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL 2 HOUR)";
        
        $stmt_check = $conn->prepare($existing_check);
        $stmt_check->bind_param('i', $row['user_id']);
        $stmt_check->execute();
        
        if ($stmt_check->get_result()->num_rows === 0) {
            $alert_data = [
                'tipo' => 'error_scan',
                'prioridad' => $row['error_count'] >= ($threshold * 2) ? 'alta' : 'media',
                'titulo' => 'Múltiples Errores de Escaneo',
                'descripcion' => "El usuario {$row['user_name']} ha tenido {$row['error_count']} errores de escaneo en la última hora",
                'datos_adicionales' => json_encode([
                    'user_id' => $row['user_id'],
                    'user_name' => $row['user_name'],
                    'error_count' => $row['error_count'],
                    'threshold' => $threshold,
                    'period' => '1 hour'
                ])
            ];
            
            if (createAlertRecord($conn, $alert_data)) {
                $alerts_created++;
            }
        }
    }
    
    return $alerts_created;
}

/**
 * Verificar QR inactivos
 */
function checkInactiveQRAlerts($conn) {
    // Obtener configuración de días de inactividad
    $config = getSystemConfig($conn, 'alert_inactivity_days');
    $days = $config ? ($config['config_value']['days'] ?? 7) : 7;
    
    // Buscar QRs sin actividad
    $query = "SELECT 
                 qc.id as qr_id,
                 qc.qr_content,
                 qc.entity_type,
                 qc.created_at,
                 qc.last_scanned_at,
                 p.nombre as producto_name,
                 a.nombre as almacen_name
              FROM qr_codes qc
              LEFT JOIN productos p ON qc.linked_product_id = p.id
              LEFT JOIN almacenes a ON qc.linked_almacen_id = a.id
              LEFT JOIN alertas_inventario ai ON (
                  JSON_EXTRACT(ai.datos_adicionales, '$.qr_id') = qc.id
                  AND ai.tipo = 'qr_inactive' 
                  AND ai.activa = 1
              )
              WHERE qc.active = 1
              AND (
                  qc.last_scanned_at IS NULL 
                  OR qc.last_scanned_at < DATE_SUB(NOW(), INTERVAL ? DAY)
              )
              AND qc.created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
              AND ai.id IS NULL";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $days, $days);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $alerts_created = 0;
    
    while ($row = $result->fetch_assoc()) {
        $days_inactive = $row['last_scanned_at'] 
            ? floor((time() - strtotime($row['last_scanned_at'])) / 86400)
            : floor((time() - strtotime($row['created_at'])) / 86400);
        
        $alert_data = [
            'tipo' => 'qr_inactive',
            'prioridad' => $days_inactive >= ($days * 2) ? 'media' : 'baja',
            'titulo' => 'QR Inactivo - ' . ($row['producto_name'] ?? 'Sin producto'),
            'descripcion' => "El código QR {$row['qr_content']} no ha sido escaneado en {$days_inactive} días",
            'producto_id' => $row['producto_name'] ? null : null, // Set if product exists
            'datos_adicionales' => json_encode([
                'qr_id' => $row['qr_id'],
                'qr_content' => $row['qr_content'],
                'days_inactive' => $days_inactive,
                'threshold_days' => $days,
                'last_scanned_at' => $row['last_scanned_at']
            ])
        ];
        
        if (createAlertRecord($conn, $alert_data)) {
            $alerts_created++;
        }
    }
    
    return $alerts_created;
}

/**
 * Verificar discrepancias de inventario
 */
function checkInventoryDiscrepancies($conn) {
    // Buscar conteos recientes con discrepancias
    $query = "SELECT 
                 qst.qr_code_id,
                 qst.quantity_affected as counted_quantity,
                 qc.qr_content,
                 p.nombre as producto_name,
                 a.nombre as almacen_name,
                 ia.stock_actual as system_quantity,
                 ABS(qst.quantity_affected - ia.stock_actual) as discrepancy
              FROM qr_scan_transactions qst
              JOIN qr_codes qc ON qst.qr_code_id = qc.id
              LEFT JOIN productos p ON qc.linked_product_id = p.id
              LEFT JOIN almacenes a ON qc.linked_almacen_id = a.id
              LEFT JOIN inventario_almacen ia ON (p.id = ia.producto_id AND a.id = ia.almacen_id)
              LEFT JOIN alertas_inventario ai ON (
                  ai.producto_id = p.id 
                  AND ai.almacen_id = a.id 
                  AND ai.tipo = 'discrepancia' 
                  AND ai.activa = 1
                  AND ai.fecha_creacion >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
              )
              WHERE qst.action_performed = 'conteo'
              AND qst.scanned_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
              AND ABS(qst.quantity_affected - ia.stock_actual) > 0
              AND ai.id IS NULL";
    
    $result = $conn->query($query);
    $alerts_created = 0;
    
    while ($row = $result->fetch_assoc()) {
        $discrepancy = $row['discrepancy'];
        $percentage_diff = $row['system_quantity'] > 0 
            ? ($discrepancy / $row['system_quantity']) * 100 
            : 100;
        
        // Solo crear alerta si la discrepancia es significativa
        if ($discrepancy >= 5 || $percentage_diff >= 10) {
            $alert_data = [
                'tipo' => 'discrepancia',
                'prioridad' => $percentage_diff >= 25 ? 'alta' : 'media',
                'titulo' => 'Discrepancia de Inventario - ' . $row['producto_name'],
                'descripcion' => "Diferencia encontrada en {$row['producto_name']}: Sistema={$row['system_quantity']}, Conteo={$row['counted_quantity']} (Diferencia: {$discrepancy})",
                'producto_id' => $row['producto_name'] ? null : null,
                'datos_adicionales' => json_encode([
                    'qr_content' => $row['qr_content'],
                    'system_quantity' => $row['system_quantity'],
                    'counted_quantity' => $row['counted_quantity'],
                    'discrepancy' => $discrepancy,
                    'percentage_diff' => round($percentage_diff, 2)
                ])
            ];
            
            if (createAlertRecord($conn, $alert_data)) {
                $alerts_created++;
            }
        }
    }
    
    return $alerts_created;
}

/**
 * Crear registro de alerta
 */
function createAlertRecord($conn, $alert_data) {
    $query = "INSERT INTO alertas_inventario (
                 tipo, prioridad, titulo, descripcion, 
                 producto_id, almacen_id, datos_adicionales,
                 usuario_responsable
              ) VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssssiis',
        $alert_data['tipo'],
        $alert_data['prioridad'],
        $alert_data['titulo'],
        $alert_data['descripcion'],
        $alert_data['producto_id'],
        $alert_data['almacen_id'],
        $alert_data['datos_adicionales']
    );
    
    return $stmt->execute();
}

/**
 * Resolver alerta
 */
function resolveAlert($conn, $current_user, $input) {
    if (!isset($input['alert_id'])) {
        throw new Exception('ID de alerta requerido', 400);
    }
    
    $alert_id = $input['alert_id'];
    $resolution_notes = $input['notes'] ?? '';
    
    $conn->begin_transaction();
    
    try {
        // Verificar que la alerta existe y está activa
        $check_query = "SELECT id, titulo FROM alertas_inventario WHERE id = ? AND activa = 1";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param('i', $alert_id);
        $stmt->execute();
        $alert = $stmt->get_result()->fetch_assoc();
        
        if (!$alert) {
            throw new Exception('Alerta no encontrada', 404);
        }
        
        // Marcar como resuelta
        $resolve_query = "UPDATE alertas_inventario 
                         SET activa = 0, 
                             fecha_resolucion = NOW(),
                             resuelto_por = ?,
                             notas_resolucion = ?
                         WHERE id = ?";
        
        $stmt = $conn->prepare($resolve_query);
        $stmt->bind_param('isi', $current_user['id'], $resolution_notes, $alert_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Error al resolver la alerta');
        }
        
        // Registrar en log
        $log_query = "INSERT INTO logs_accesos (usuario_id, accion, modulo, detalles, ip_address) 
                     VALUES (?, 'resolver_alerta', 'qr', ?, ?)";
        $stmt = $conn->prepare($log_query);
        $details = json_encode([
            'alert_id' => $alert_id,
            'alert_title' => $alert['titulo'],
            'resolution_notes' => $resolution_notes
        ]);
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $stmt->bind_param('iss', $current_user['id'], $details, $ip_address);
        $stmt->execute();
        
        $conn->commit();
        
        return [
            'success' => true,
            'message' => 'Alerta resuelta correctamente'
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * Posponer alerta
 */
function snoozeAlert($conn, $current_user, $input) {
    if (!isset($input['alert_id']) || !isset($input['hours'])) {
        throw new Exception('ID de alerta y horas requeridos', 400);
    }
    
    $alert_id = $input['alert_id'];
    $hours = max(1, min(168, (int)$input['hours'])); // Entre 1 hora y 7 días
    
    $query = "UPDATE alertas_inventario 
             SET fecha_posposicion = NOW(),
                 posposicion_hasta = DATE_ADD(NOW(), INTERVAL ? HOUR),
                 posposicion_por = ?
             WHERE id = ? AND activa = 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iii', $hours, $current_user['id'], $alert_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Error al posponer la alerta');
    }
    
    if ($stmt->affected_rows === 0) {
        throw new Exception('Alerta no encontrada', 404);
    }
    
    return [
        'success' => true,
        'message' => "Alerta pospuesta por {$hours} horas"
    ];
}

/**
 * Obtener configuración de alertas
 */
function getAlertConfig($conn) {
    $query = "SELECT config_key, config_value 
             FROM qr_system_config 
             WHERE config_key LIKE 'alert_%' AND active = 1";
    
    $result = $conn->query($query);
    $config = [];
    
    while ($row = $result->fetch_assoc()) {
        $config[$row['config_key']] = json_decode($row['config_value'], true);
    }
    
    return [
        'success' => true,
        'data' => $config
    ];
}

/**
 * Guardar configuración de alertas
 */
function saveAlertConfig($conn, $current_user, $input) {
    if (!isset($input['config'])) {
        throw new Exception('Configuración requerida', 400);
    }
    
    $config = $input['config'];
    
    $conn->begin_transaction();
    
    try {
        // Configuración de stock
        if (isset($config['stock_threshold'])) {
            $stock_config = [
                'threshold' => (int)$config['stock_threshold'],
                'enabled' => $config['enable_stock_alerts'] ?? false
            ];
            
            saveSystemConfig($conn, 'alert_stock_threshold', $stock_config, $current_user['id']);
        }
        
        // Configuración de errores
        if (isset($config['error_threshold'])) {
            $error_config = [
                'max_errors_per_hour' => (int)$config['error_threshold'],
                'enabled' => $config['enable_error_alerts'] ?? false
            ];
            
            saveSystemConfig($conn, 'alert_error_threshold', $error_config, $current_user['id']);
        }
        
        // Configuración de inactividad
        if (isset($config['inactivity_days'])) {
            $inactivity_config = [
                'days' => (int)$config['inactivity_days'],
                'enabled' => $config['enable_inactivity_alerts'] ?? false
            ];
            
            saveSystemConfig($conn, 'alert_inactivity_days', $inactivity_config, $current_user['id']);
        }
        
        // Configuración de notificaciones
        $notification_config = [
            'email_notifications' => $config['email_notifications'] ?? false,
            'realtime_alerts' => $config['realtime_alerts'] ?? false
        ];
        
        saveSystemConfig($conn, 'alert_notifications', $notification_config, $current_user['id']);
        
        $conn->commit();
        
        return [
            'success' => true,
            'message' => 'Configuración guardada correctamente'
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * Obtener estadísticas de alertas
 */
function getAlertStats($conn) {
    $stats_query = "SELECT 
                       COUNT(*) as total_active,
                       COUNT(CASE WHEN prioridad = 'alta' THEN 1 END) as critical,
                       COUNT(CASE WHEN prioridad = 'media' THEN 1 END) as warning,
                       COUNT(CASE WHEN prioridad = 'baja' THEN 1 END) as info,
                       COUNT(CASE WHEN DATE(fecha_creacion) = CURDATE() THEN 1 END) as created_today
                    FROM alertas_inventario 
                    WHERE activa = 1";
    
    $result = $conn->query($stats_query);
    $stats = $result->fetch_assoc();
    
    // Alertas resueltas hoy
    $resolved_query = "SELECT COUNT(*) as resolved_today 
                      FROM alertas_inventario 
                      WHERE activa = 0 AND DATE(fecha_resolucion) = CURDATE()";
    
    $result = $conn->query($resolved_query);
    $resolved = $result->fetch_assoc();
    
    return [
        'success' => true,
        'data' => array_merge($stats, $resolved)
    ];
}

/**
 * Obtener configuración del sistema
 */
function getSystemConfig($conn, $config_key) {
    $query = "SELECT config_value FROM qr_system_config WHERE config_key = ? AND active = 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $config_key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return [
            'config_key' => $config_key,
            'config_value' => json_decode($row['config_value'], true)
        ];
    }
    
    return null;
}

/**
 * Guardar configuración del sistema
 */
function saveSystemConfig($conn, $config_key, $config_value, $user_id) {
    $config_json = json_encode($config_value);
    
    $query = "INSERT INTO qr_system_config (config_key, config_value, created_by) 
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE 
             config_value = VALUES(config_value),
             updated_at = NOW()";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssi', $config_key, $config_json, $user_id);
    
    return $stmt->execute();
}
?>