<?php
/**
 * API - Gestión de Permisos QR
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
    $current_user = $auth->requireLogin();
    
    // Verificar CSRF token para requests POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!$auth->verifyCSRF($csrf_token)) {
            throw new Exception('Token CSRF inválido', 403);
        }
    }
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGetPermissions($conn, $current_user, $auth);
            break;
            
        case 'POST':
            handleUpdatePermissions($conn, $current_user, $auth);
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
    
    error_log("QR Permissions API Error: " . $e->getMessage() . " | Code: " . $error_code);
}

/**
 * Obtener permisos QR del usuario actual
 */
function handleGetPermissions($conn, $current_user, $auth) {
    $user_id = $_GET['user_id'] ?? $current_user['id'];
    
    // Solo admins pueden consultar permisos de otros usuarios
    if ($user_id != $current_user['id'] && !$auth->hasPermission($current_user['id'], 'accesos', 'leer')) {
        throw new Exception('No tiene permisos para consultar otros usuarios', 403);
    }
    
    // Obtener permisos QR del usuario
    $qr_permissions = getQRPermissions($conn, $user_id);
    
    // Obtener información del usuario
    $user_info = getUserInfo($conn, $user_id);
    
    // Obtener acciones disponibles basadas en permisos
    $available_actions = getAvailableActions($qr_permissions);
    
    echo json_encode([
        'success' => true,
        'user' => $user_info,
        'qr_permissions' => $qr_permissions,
        'available_actions' => $available_actions,
        'permissions_summary' => [
            'can_generate' => $qr_permissions['crear'] ?? false,
            'can_scan' => $qr_permissions['escanear'] ?? false,
            'can_view_reports' => $qr_permissions['reportes'] ?? false,
            'can_manage' => $qr_permissions['actualizar'] ?? false,
            'can_delete' => $qr_permissions['eliminar'] ?? false
        ]
    ]);
}

/**
 * Actualizar permisos QR (solo para administradores)
 */
function handleUpdatePermissions($conn, $current_user, $auth) {
    // Verificar permisos de administración
    if (!$auth->hasPermission($current_user['id'], 'accesos', 'actualizar')) {
        throw new Exception('No tiene permisos para actualizar permisos', 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Datos de entrada inválidos', 400);
    }
    
    $required_fields = ['user_id', 'permissions'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field])) {
            throw new Exception("Campo requerido faltante: $field", 400);
        }
    }
    
    $user_id = $input['user_id'];
    $permissions = $input['permissions'];
    
    // Validar que el usuario existe
    $user_info = getUserInfo($conn, $user_id);
    if (!$user_info) {
        throw new Exception('Usuario no encontrado', 404);
    }
    
    // Obtener ID del módulo QR
    $qr_module_query = "SELECT id FROM modulos WHERE nombre = 'qr' LIMIT 1";
    $result = $conn->query($qr_module_query);
    $qr_module = $result->fetch_assoc();
    
    if (!$qr_module) {
        throw new Exception('Módulo QR no encontrado', 404);
    }
    
    $module_id = $qr_module['id'];
    
    // Comenzar transacción
    $conn->begin_transaction();
    
    try {
        // Obtener rol del usuario
        $user_role_query = "SELECT rol_id FROM usuarios WHERE id = ?";
        $stmt = $conn->prepare($user_role_query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $user_role = $stmt->get_result()->fetch_assoc();
        
        if (!$user_role) {
            throw new Exception('Rol de usuario no encontrado', 404);
        }
        
        $rol_id = $user_role['rol_id'];
        
        // Remover permisos QR existentes del rol
        $remove_query = "DELETE rp FROM rol_permisos rp 
                        JOIN permisos p ON rp.permiso_id = p.id 
                        WHERE rp.rol_id = ? AND p.modulo_id = ?";
        $stmt = $conn->prepare($remove_query);
        $stmt->bind_param('ii', $rol_id, $module_id);
        $stmt->execute();
        
        // Agregar nuevos permisos
        $permissions_added = 0;
        foreach ($permissions as $permission_type => $granted) {
            if ($granted) {
                // Obtener ID del permiso
                $permission_query = "SELECT id FROM permisos WHERE modulo_id = ? AND tipo_permiso = ?";
                $stmt = $conn->prepare($permission_query);
                $stmt->bind_param('is', $module_id, $permission_type);
                $stmt->execute();
                $permission = $stmt->get_result()->fetch_assoc();
                
                if ($permission) {
                    // Insertar permiso
                    $insert_query = "INSERT IGNORE INTO rol_permisos (rol_id, permiso_id) VALUES (?, ?)";
                    $stmt = $conn->prepare($insert_query);
                    $stmt->bind_param('ii', $rol_id, $permission['id']);
                    
                    if ($stmt->execute()) {
                        $permissions_added++;
                    }
                }
            }
        }
        
        // Registrar cambio en log de accesos
        $log_query = "INSERT INTO logs_accesos (usuario_id, accion, modulo, detalles, ip_address) 
                     VALUES (?, 'actualizar_permisos_qr', 'qr', ?, ?)";
        $stmt = $conn->prepare($log_query);
        $details = json_encode([
            'target_user_id' => $user_id,
            'permissions_updated' => $permissions,
            'permissions_added' => $permissions_added
        ]);
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $stmt->bind_param('iss', $current_user['id'], $details, $ip_address);
        $stmt->execute();
        
        $conn->commit();
        
        // Obtener permisos actualizados
        $updated_permissions = getQRPermissions($conn, $user_id);
        
        echo json_encode([
            'success' => true,
            'message' => 'Permisos QR actualizados correctamente',
            'permissions_added' => $permissions_added,
            'updated_permissions' => $updated_permissions,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * Obtener permisos QR de un usuario
 */
function getQRPermissions($conn, $user_id) {
    $query = "SELECT p.tipo_permiso, p.descripcion
              FROM usuarios u
              JOIN rol_permisos rp ON u.rol_id = rp.rol_id
              JOIN permisos p ON rp.permiso_id = p.id
              JOIN modulos m ON p.modulo_id = m.id
              WHERE u.id = ? AND m.nombre = 'qr' AND u.activo = 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $permissions = [];
    while ($row = $result->fetch_assoc()) {
        $permissions[$row['tipo_permiso']] = true;
    }
    
    return $permissions;
}

/**
 * Obtener información del usuario
 */
function getUserInfo($conn, $user_id) {
    $query = "SELECT u.id, u.nombre, u.usuario, u.email, u.activo,
                     r.nombre as rol_nombre, r.descripcion as rol_descripcion
              FROM usuarios u
              JOIN roles r ON u.rol_id = r.id
              WHERE u.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

/**
 * Obtener acciones disponibles basadas en permisos
 */
function getAvailableActions($permissions) {
    $actions = [];
    
    // Mapear permisos a acciones
    $permission_action_map = [
        'leer' => [
            'consulta' => ['label' => 'Consultar QR', 'icon' => 'search', 'description' => 'Ver información de códigos QR']
        ],
        'crear' => [
            'generate' => ['label' => 'Generar QR', 'icon' => 'plus-circle', 'description' => 'Crear nuevos códigos QR']
        ],
        'escanear' => [
            'entrada' => ['label' => 'Entrada', 'icon' => 'arrow-down', 'description' => 'Registrar entrada de productos'],
            'salida' => ['label' => 'Salida', 'icon' => 'arrow-up', 'description' => 'Registrar salida de productos'],
            'conteo' => ['label' => 'Conteo', 'icon' => 'list', 'description' => 'Realizar conteo de inventario']
        ],
        'actualizar' => [
            'ajuste' => ['label' => 'Ajustar', 'icon' => 'edit', 'description' => 'Hacer ajustes de inventario'],
            'movimiento' => ['label' => 'Movimiento', 'icon' => 'shuffle', 'description' => 'Transferir entre ubicaciones']
        ],
        'eliminar' => [
            'delete' => ['label' => 'Eliminar QR', 'icon' => 'trash', 'description' => 'Eliminar códigos QR']
        ],
        'reportes' => [
            'reports' => ['label' => 'Ver Reportes', 'icon' => 'bar-chart', 'description' => 'Acceder a reportes y analytics']
        ]
    ];
    
    foreach ($permissions as $permission => $granted) {
        if ($granted && isset($permission_action_map[$permission])) {
            $actions = array_merge($actions, $permission_action_map[$permission]);
        }
    }
    
    return $actions;
}
?>