<?php
/**
 * API - Gestión de Workflows QR
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
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
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
    $current_user = $auth->requireLogin();
    
    // Verificar CSRF token para requests state-changing (POST, PATCH, DELETE)
    $state_changing_methods = ['POST', 'PATCH', 'DELETE'];
    if (in_array($_SERVER['REQUEST_METHOD'], $state_changing_methods)) {
        $csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!$auth->verifyCSRF($csrf_token)) {
            throw new Exception('Token CSRF inválido', 403);
        }
    }
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGetWorkflows($conn, $current_user, $auth);
            break;
            
        case 'POST':
            handleCreateWorkflow($conn, $current_user, $auth);
            break;
            
        case 'PATCH':
            handleUpdateWorkflow($conn, $current_user, $auth);
            break;
            
        case 'DELETE':
            handleDeleteWorkflow($conn, $current_user, $auth);
            break;
            
        default:
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
    
    error_log("QR Workflows API Error: " . $e->getMessage() . " | Code: " . $error_code);
}

/**
 * Obtener workflows
 */
function handleGetWorkflows($conn, $current_user, $auth) {
    // Verificar permisos de lectura
    if (!$auth->hasPermission($current_user['id'], 'qr', 'leer')) {
        throw new Exception('No tiene permisos para ver workflows', 403);
    }
    
    // Validar y sanitizar parámetros
    $workflow_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $workflow_type = isset($_GET['type']) ? trim($_GET['type']) : null;
    
    // Validar workflow_type si se proporciona
    if ($workflow_type) {
        $valid_types = ['entrada', 'salida', 'conteo', 'movimiento', 'auditoria', 'transferencia', 'ajuste'];
        if (!in_array($workflow_type, $valid_types)) {
            throw new Exception('Tipo de workflow inválido', 400);
        }
    }
    
    if ($workflow_id) {
        // Validar que sea un ID válido
        if ($workflow_id <= 0) {
            throw new Exception('ID de workflow inválido', 400);
        }
        // Obtener workflow específico
        $workflow = getWorkflowById($conn, $workflow_id);
        if (!$workflow) {
            throw new Exception('Workflow no encontrado', 404);
        }
        
        // Obtener estadísticas del workflow
        $stats = getWorkflowStats($conn, $workflow_id);
        
        echo json_encode([
            'success' => true,
            'workflow' => $workflow,
            'stats' => $stats
        ]);
        
    } else {
        // Obtener todos los workflows
        $where_clause = '';
        $params = [];
        $types = '';
        
        if ($workflow_type) {
            $where_clause = 'WHERE workflow_type = ?';
            $params[] = $workflow_type;
            $types = 's';
        }
        
        // Solo mostrar workflows activos por defecto
        $show_inactive = isset($_GET['show_inactive']) && $_GET['show_inactive'] === '1';
        if (!$show_inactive) {
            $where_clause = $where_clause ? $where_clause . ' AND active = 1' : 'WHERE active = 1';
        }
        
        $query = "SELECT id, workflow_name, workflow_type, config_data, validation_rules,
                         ui_config, required_permissions, active, created_at, updated_at
                  FROM qr_workflow_config 
                  $where_clause
                  ORDER BY workflow_type, workflow_name";
        
        if ($params) {
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($query);
        }
        
        $workflows = [];
        while ($row = $result->fetch_assoc()) {
            $row['config_data'] = json_decode($row['config_data'], true);
            $row['validation_rules'] = json_decode($row['validation_rules'], true);
            $row['ui_config'] = json_decode($row['ui_config'], true);
            $row['required_permissions'] = json_decode($row['required_permissions'], true);
            
            // Agregar estadísticas básicas
            $row['usage_stats'] = getWorkflowStats($conn, $row['id']);
            
            $workflows[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'workflows' => $workflows,
            'total' => count($workflows)
        ]);
    }
}

/**
 * Crear nuevo workflow
 */
function handleCreateWorkflow($conn, $current_user, $auth) {
    // Verificar permisos de creación
    if (!$auth->hasPermission($current_user['id'], 'qr', 'crear')) {
        throw new Exception('No tiene permisos para crear workflows', 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Datos de entrada inválidos', 400);
    }
    
    // Validar y sanitizar campos requeridos
    if (!isset($input['workflow_name']) || empty($input['workflow_name'])) {
        throw new Exception('Nombre de workflow requerido', 400);
    }
    
    if (!isset($input['workflow_type']) || empty($input['workflow_type'])) {
        throw new Exception('Tipo de workflow requerido', 400);
    }
    
    if (!isset($input['config_data']) || empty($input['config_data'])) {
        throw new Exception('Configuración de workflow requerida', 400);
    }
    
    // Sanitizar nombre del workflow
    $workflow_name = trim($input['workflow_name']);
    if (strlen($workflow_name) < 3 || strlen($workflow_name) > 100) {
        throw new Exception('Nombre del workflow debe tener entre 3 y 100 caracteres', 400);
    }
    
    // Validar caracteres permitidos en el nombre
    if (!preg_match('/^[a-zA-Z0-9_\s\-]+$/', $workflow_name)) {
        throw new Exception('Nombre del workflow contiene caracteres no permitidos', 400);
    }
    
    // Validar tipo de workflow
    $workflow_type = trim($input['workflow_type']);
    $valid_types = ['entrada', 'salida', 'conteo', 'movimiento', 'auditoria', 'transferencia', 'ajuste'];
    if (!in_array($workflow_type, $valid_types)) {
        throw new Exception('Tipo de workflow inválido', 400);
    }
    
    // Verificar que no existe otro workflow con el mismo nombre
    $check_query = "SELECT id FROM qr_workflow_config WHERE workflow_name = ? LIMIT 1";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param('s', $workflow_name);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('Ya existe un workflow con ese nombre', 409);
    }
    
    // Comenzar transacción
    $conn->begin_transaction();
    
    try {
        // Preparar y validar datos JSON
        $config_data = is_array($input['config_data']) ? json_encode($input['config_data']) : $input['config_data'];
        if (!json_decode($config_data)) {
            throw new Exception('Configuración de datos inválida (JSON malformado)', 400);
        }
        
        $validation_rules = isset($input['validation_rules']) && is_array($input['validation_rules']) 
            ? json_encode($input['validation_rules']) 
            : json_encode([]);
            
        $ui_config = isset($input['ui_config']) && is_array($input['ui_config']) 
            ? json_encode($input['ui_config']) 
            : json_encode([]);
            
        $required_permissions = isset($input['required_permissions']) && is_array($input['required_permissions']) 
            ? json_encode($input['required_permissions']) 
            : json_encode([]);
        
        // Insertar workflow
        $insert_query = "INSERT INTO qr_workflow_config (
            workflow_name, workflow_type, config_data, validation_rules,
            ui_config, required_permissions, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param('ssssssi',
            $workflow_name,
            $workflow_type,
            $config_data,
            $validation_rules,
            $ui_config,
            $required_permissions,
            $current_user['id']
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Error al crear workflow: ' . $stmt->error);
        }
        
        $workflow_id = $conn->insert_id;
        
        // Registrar en log
        $log_query = "INSERT INTO logs_accesos (usuario_id, accion, modulo, detalles, ip_address) 
                     VALUES (?, 'crear_workflow', 'qr', ?, ?)";
        $stmt = $conn->prepare($log_query);
        $details = json_encode([
            'workflow_id' => $workflow_id,
            'workflow_name' => $workflow_name,
            'workflow_type' => $workflow_type
        ]);
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $stmt->bind_param('iss', $current_user['id'], $details, $ip_address);
        $stmt->execute();
        
        $conn->commit();
        
        // Obtener workflow creado
        $created_workflow = getWorkflowById($conn, $workflow_id);
        
        echo json_encode([
            'success' => true,
            'message' => 'Workflow creado exitosamente',
            'workflow' => $created_workflow,
            'workflow_id' => $workflow_id
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * Actualizar workflow
 */
function handleUpdateWorkflow($conn, $current_user, $auth) {
    // Verificar permisos de actualización
    if (!$auth->hasPermission($current_user['id'], 'qr', 'actualizar')) {
        throw new Exception('No tiene permisos para actualizar workflows', 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['id'])) {
        throw new Exception('ID de workflow requerido', 400);
    }
    
    // Validar ID del workflow
    $workflow_id = (int)$input['id'];
    if ($workflow_id <= 0) {
        throw new Exception('ID de workflow inválido', 400);
    }
    
    // Verificar que el workflow existe
    $existing_workflow = getWorkflowById($conn, $workflow_id);
    if (!$existing_workflow) {
        throw new Exception('Workflow no encontrado', 404);
    }
    
    $conn->begin_transaction();
    
    try {
        $update_fields = [];
        $params = [];
        $types = '';
        
        // Campos actualizables
        $updatable_fields = [
            'workflow_name' => 's',
            'workflow_type' => 's', 
            'config_data' => 's',
            'validation_rules' => 's',
            'ui_config' => 's',
            'required_permissions' => 's',
            'active' => 'i'
        ];
        
        foreach ($updatable_fields as $field => $type) {
            if (isset($input[$field])) {
                $update_fields[] = "$field = ?";
                
                if (in_array($field, ['config_data', 'validation_rules', 'ui_config', 'required_permissions'])) {
                    $params[] = json_encode($input[$field]);
                } else {
                    $params[] = $input[$field];
                }
                
                $types .= $type;
            }
        }
        
        if (empty($update_fields)) {
            throw new Exception('No hay campos para actualizar', 400);
        }
        
        // Agregar updated_at
        $update_fields[] = 'updated_at = NOW()';
        
        // Agregar ID para WHERE
        $params[] = $workflow_id;
        $types .= 'i';
        
        $update_query = "UPDATE qr_workflow_config SET " . implode(', ', $update_fields) . " WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            throw new Exception('Error al actualizar workflow: ' . $stmt->error);
        }
        
        // Registrar en log
        $log_query = "INSERT INTO logs_accesos (usuario_id, accion, modulo, detalles, ip_address) 
                     VALUES (?, 'actualizar_workflow', 'qr', ?, ?)";
        $stmt = $conn->prepare($log_query);
        $details = json_encode([
            'workflow_id' => $workflow_id,
            'updated_fields' => array_keys($input),
            'previous_state' => $existing_workflow
        ]);
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $stmt->bind_param('iss', $current_user['id'], $details, $ip_address);
        $stmt->execute();
        
        $conn->commit();
        
        // Obtener workflow actualizado
        $updated_workflow = getWorkflowById($conn, $workflow_id);
        
        echo json_encode([
            'success' => true,
            'message' => 'Workflow actualizado exitosamente',
            'workflow' => $updated_workflow
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * Eliminar workflow
 */
function handleDeleteWorkflow($conn, $current_user, $auth) {
    // Verificar permisos de eliminación
    if (!$auth->hasPermission($current_user['id'], 'qr', 'eliminar')) {
        throw new Exception('No tiene permisos para eliminar workflows', 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['id'])) {
        throw new Exception('ID de workflow requerido', 400);
    }
    
    // Validar ID del workflow
    $workflow_id = (int)$input['id'];
    if ($workflow_id <= 0) {
        throw new Exception('ID de workflow inválido', 400);
    }
    
    // Verificar que el workflow existe
    $existing_workflow = getWorkflowById($conn, $workflow_id);
    if (!$existing_workflow) {
        throw new Exception('Workflow no encontrado', 404);
    }
    
    // Verificar si el workflow está siendo usado
    $usage_check = "SELECT COUNT(*) as usage_count FROM qr_scan_transactions WHERE workflow_type = ?";
    $stmt = $conn->prepare($usage_check);
    $stmt->bind_param('s', $existing_workflow['workflow_name']);
    $stmt->execute();
    $usage = $stmt->get_result()->fetch_assoc();
    
    if ($usage['usage_count'] > 0) {
        throw new Exception('No se puede eliminar: el workflow está siendo usado en transacciones', 409);
    }
    
    $conn->begin_transaction();
    
    try {
        // Eliminar workflow
        $delete_query = "DELETE FROM qr_workflow_config WHERE id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param('i', $workflow_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Error al eliminar workflow: ' . $stmt->error);
        }
        
        // Registrar en log
        $log_query = "INSERT INTO logs_accesos (usuario_id, accion, modulo, detalles, ip_address) 
                     VALUES (?, 'eliminar_workflow', 'qr', ?, ?)";
        $stmt = $conn->prepare($log_query);
        $details = json_encode([
            'workflow_id' => $workflow_id,
            'deleted_workflow' => $existing_workflow
        ]);
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $stmt->bind_param('iss', $current_user['id'], $details, $ip_address);
        $stmt->execute();
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Workflow eliminado exitosamente'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * Obtener workflow por ID
 */
function getWorkflowById($conn, $workflow_id) {
    $query = "SELECT id, workflow_name, workflow_type, config_data, validation_rules,
                     ui_config, required_permissions, active, created_at, updated_at
              FROM qr_workflow_config WHERE id = ? LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $workflow_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $workflow = $result->fetch_assoc();
        $workflow['config_data'] = json_decode($workflow['config_data'], true);
        $workflow['validation_rules'] = json_decode($workflow['validation_rules'], true);
        $workflow['ui_config'] = json_decode($workflow['ui_config'], true);
        $workflow['required_permissions'] = json_decode($workflow['required_permissions'], true);
        return $workflow;
    }
    
    return null;
}

/**
 * Obtener estadísticas de uso del workflow
 */
function getWorkflowStats($conn, $workflow_id) {
    // Obtener nombre del workflow
    $workflow_query = "SELECT workflow_name FROM qr_workflow_config WHERE id = ?";
    $stmt = $conn->prepare($workflow_query);
    $stmt->bind_param('i', $workflow_id);
    $stmt->execute();
    $workflow = $stmt->get_result()->fetch_assoc();
    
    if (!$workflow) {
        return null;
    }
    
    $workflow_name = $workflow['workflow_name'];
    
    // Estadísticas de uso
    $stats_query = "SELECT 
                        COUNT(*) as total_uses,
                        COUNT(CASE WHEN processing_status = 'success' THEN 1 END) as successful_uses,
                        COUNT(CASE WHEN processing_status = 'failed' THEN 1 END) as failed_uses,
                        COUNT(CASE WHEN DATE(scanned_at) = CURDATE() THEN 1 END) as uses_today,
                        COUNT(CASE WHEN DATE(scanned_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as uses_week,
                        AVG(processing_duration_ms) as avg_processing_time,
                        COUNT(DISTINCT user_id) as unique_users,
                        MAX(scanned_at) as last_used_at
                    FROM qr_scan_transactions 
                    WHERE workflow_type = ?";
    
    $stmt = $conn->prepare($stats_query);
    $stmt->bind_param('s', $workflow_name);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    
    // Calcular tasa de éxito
    $success_rate = 0;
    if ($stats['total_uses'] > 0) {
        $success_rate = round(($stats['successful_uses'] / $stats['total_uses']) * 100, 2);
    }
    
    return [
        'total_uses' => (int)$stats['total_uses'],
        'successful_uses' => (int)$stats['successful_uses'],
        'failed_uses' => (int)$stats['failed_uses'],
        'success_rate' => $success_rate,
        'uses_today' => (int)$stats['uses_today'],
        'uses_week' => (int)$stats['uses_week'],
        'avg_processing_time' => round($stats['avg_processing_time'] ?? 0, 2),
        'unique_users' => (int)$stats['unique_users'],
        'last_used_at' => $stats['last_used_at']
    ];
}
?>