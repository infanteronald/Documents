<?php
/**
 * API - Escáner de Códigos QR
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
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-TOKEN');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once dirname(dirname(__DIR__)) . '/config_secure.php';
require_once dirname(dirname(__DIR__)) . '/accesos/middleware/AuthMiddleware.php';
require_once dirname(__DIR__) . '/models/QRManager.php';
require_once dirname(__DIR__) . '/security_headers.php';

// Establecer headers de seguridad para API
setAPISecurityHeaders();

try {
    // Verificar autenticación
    $auth = new AuthMiddleware($conn);
    $current_user = $auth->requirePermission('qr', 'leer');
    
    // Verificar CSRF token para requests POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!$auth->verifyCSRF($csrf_token)) {
            throw new Exception('Token CSRF inválido', 403);
        }
    }
    
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido', 405);
    }
    
    // Obtener datos del request
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Datos de entrada inválidos', 400);
    }
    
    // Validar y sanitizar campos requeridos
    if (!isset($input['qr_content']) || empty($input['qr_content'])) {
        throw new Exception('Contenido QR requerido', 400);
    }
    
    // Sanitizar y validar contenido QR
    $qr_content = trim($input['qr_content']);
    if (strlen($qr_content) < 5 || strlen($qr_content) > 255) {
        throw new Exception('Contenido QR inválido (longitud 5-255 caracteres)', 400);
    }
    
    // Validar caracteres permitidos en QR
    if (!preg_match('/^[A-Za-z0-9\-_]+$/', $qr_content)) {
        throw new Exception('Contenido QR contiene caracteres no permitidos', 400);
    }
    
    if (!isset($input['action']) || empty($input['action'])) {
        throw new Exception('Acción requerida', 400);
    }
    
    // Validar acción
    $valid_actions = ['consulta', 'entrada', 'salida', 'conteo', 'movimiento', 'ajuste'];
    $action = trim($input['action']);
    if (!in_array($action, $valid_actions)) {
        throw new Exception('Acción no válida: ' . htmlspecialchars($action), 400);
    }
    
    // Preparar contexto del escaneo con sanitización
    $scan_method = isset($input['scan_method']) ? trim($input['scan_method']) : 'camera_mobile';
    $location = isset($input['location']) ? htmlspecialchars(trim($input['location']), ENT_QUOTES, 'UTF-8') : '';
    $notes = isset($input['notes']) ? htmlspecialchars(trim($input['notes']), ENT_QUOTES, 'UTF-8') : '';
    $workflow_type = isset($input['workflow_type']) ? trim($input['workflow_type']) : null;
    $quantity = isset($input['quantity']) ? (int)$input['quantity'] : 1;
    
    // Validar método de escaneo
    $allowed_scan_methods = ['camera_mobile', 'camera_desktop', 'manual_input', 'barcode_scanner'];
    if (!in_array($scan_method, $allowed_scan_methods)) {
        $scan_method = 'camera_mobile';
    }
    
    // Validar workflow si se proporciona
    if ($workflow_type && !preg_match('/^[a-z_]+$/', $workflow_type)) {
        $workflow_type = null;
    }
    
    // Validar cantidad
    if ($quantity < 0 || $quantity > 99999) {
        throw new Exception('Cantidad debe estar entre 0 y 99999', 400);
    }
    
    $context = [
        'scan_method' => $scan_method,
        'device_info' => [
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ],
        'location' => $location,
        'quantity' => $quantity,
        'notes' => $notes,
        'workflow_type' => $workflow_type,
        'user_role' => $current_user['rol_nombre'] ?? ''
    ];
    
    // Validaciones específicas por acción
    switch ($action) {
        case 'entrada':
        case 'salida':
            if ($quantity <= 0) {
                throw new Exception('La cantidad debe ser mayor a cero', 400);
            }
            break;
            
        case 'conteo':
            if (!isset($input['quantity'])) {
                throw new Exception('Cantidad contada requerida para conteo', 400);
            }
            if ($quantity < 0) {
                throw new Exception('La cantidad contada no puede ser negativa', 400);
            }
            break;
    }
    
    // Crear instancia del QR Manager
    $qr_manager = new QRManager($conn);
    
    // Registrar tiempo de inicio
    $start_time = microtime(true);
    
    // Procesar el escaneo con datos validados
    $result = $qr_manager->processScan(
        $qr_content,
        $current_user['id'],
        $action,
        $context
    );
    
    // Calcular tiempo de procesamiento
    $processing_time = round((microtime(true) - $start_time) * 1000);
    
    if ($result['success']) {
        // Actualizar tiempo de procesamiento en la transacción
        $update_query = "UPDATE qr_scan_transactions 
                        SET processing_duration_ms = ? 
                        WHERE transaction_uuid = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('is', $processing_time, $result['transaction_id']);
        $stmt->execute();
        
        // Preparar respuesta completa
        $response = [
            'success' => true,
            'message' => $result['message'],
            'transaction_id' => $result['transaction_id'],
            'action_performed' => $action,
            'processing_time_ms' => $processing_time,
            'qr_data' => [
                'id' => $result['qr_data']['id'],
                'qr_content' => $result['qr_data']['qr_content'],
                'entity_type' => $result['qr_data']['entity_type'],
                'entity_id' => $result['qr_data']['entity_id'],
                'scan_count' => $result['qr_data']['scan_count'] + 1
            ],
            'contextual_data' => $result['contextual_data'],
            'timestamp' => date('Y-m-d H:i:s'),
            'user' => [
                'id' => $current_user['id'],
                'nombre' => $current_user['nombre'],
                'usuario' => $current_user['usuario']
            ]
        ];
        
        // Agregar información del movimiento si se creó
        if ($result['movement_id']) {
            $movement_query = "SELECT id, tipo_movimiento, cantidad, cantidad_anterior, cantidad_nueva 
                              FROM movimientos_inventario WHERE id = ?";
            $stmt = $conn->prepare($movement_query);
            $stmt->bind_param('i', $result['movement_id']);
            $stmt->execute();
            $movement = $stmt->get_result()->fetch_assoc();
            
            if ($movement) {
                $response['movement'] = $movement;
            }
        }
        
        // Obtener información actualizada del stock si es producto
        if ($result['qr_data']['entity_type'] === 'producto' && $result['qr_data']['linked_product_id']) {
            $stock_query = "SELECT stock_actual, fecha_ultima_entrada, fecha_ultima_salida 
                           FROM inventario_almacen 
                           WHERE producto_id = ? AND almacen_id = ?";
            $stmt = $conn->prepare($stock_query);
            $stmt->bind_param('ii', $result['qr_data']['linked_product_id'], $result['qr_data']['linked_almacen_id']);
            $stmt->execute();
            $stock_info = $stmt->get_result()->fetch_assoc();
            
            if ($stock_info) {
                $response['stock_info'] = $stock_info;
            }
        }
        
        // Agregar sugerencias contextuales
        $response['suggestions'] = generateActionSuggestions($result['qr_data'], $input['action'], $current_user);
        
        echo json_encode($response);
        
    } else {
        throw new Exception('Error procesando escaneo', 500);
    }
    
} catch (Exception $e) {
    $error_code = $e->getCode() ?: 500;
    http_response_code($error_code);
    
    // Registrar error de escaneo si tenemos los datos básicos
    if (isset($input['qr_content']) && isset($current_user)) {
        try {
            $error_uuid = bin2hex(random_bytes(16));
            
            $error_query = "INSERT INTO qr_scan_transactions (
                transaction_uuid, qr_code_id, user_id, action_performed,
                processing_status, error_message, scanned_at
            ) VALUES (?, 0, ?, ?, 'failed', ?, NOW())";
            
            $stmt = $conn->prepare($error_query);
            $error_action = isset($input['action']) ? htmlspecialchars(trim($input['action'])) : 'unknown';
            $error_message = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            $stmt->bind_param('siss', $error_uuid, $current_user['id'], $error_action, $error_message);
            $stmt->execute();
            
        } catch (Exception $log_error) {
            error_log("Error logging scan failure: " . $log_error->getMessage());
        }
    }
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $error_code,
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => isset($input['action']) ? htmlspecialchars(trim($input['action'])) : 'unknown'
    ]);
    
    error_log("QR Scan API Error: " . $e->getMessage() . " | Code: " . $error_code);
}

/**
 * Genera sugerencias contextuales basadas en el escaneo
 */
function generateActionSuggestions($qr_data, $current_action, $user) {
    $suggestions = [];
    
    // Sugerencias basadas en el tipo de entidad
    if ($qr_data['entity_type'] === 'producto') {
        $base_data = $qr_data['base_data'];
        $stock_actual = $base_data['stock_actual'] ?? 0;
        
        switch ($current_action) {
            case 'consulta':
                $suggestions[] = [
                    'action' => 'entrada',
                    'label' => 'Registrar Entrada',
                    'icon' => 'arrow-down-circle',
                    'description' => 'Agregar stock al inventario'
                ];
                
                if ($stock_actual > 0) {
                    $suggestions[] = [
                        'action' => 'salida',
                        'label' => 'Registrar Salida',
                        'icon' => 'arrow-up-circle',
                        'description' => 'Retirar stock del inventario'
                    ];
                }
                
                $suggestions[] = [
                    'action' => 'conteo',
                    'label' => 'Contar Inventario',
                    'icon' => 'calculator',
                    'description' => 'Verificar cantidad física'
                ];
                break;
                
            case 'entrada':
                $suggestions[] = [
                    'action' => 'conteo',
                    'label' => 'Verificar Conteo',
                    'icon' => 'check-circle',
                    'description' => 'Confirmar cantidad ingresada'
                ];
                break;
                
            case 'salida':
                if ($stock_actual > 0) {
                    $suggestions[] = [
                        'action' => 'salida',
                        'label' => 'Salida Adicional',
                        'icon' => 'arrow-up-circle',
                        'description' => 'Registrar otra salida'
                    ];
                }
                break;
                
            case 'conteo':
                $suggestions[] = [
                    'action' => 'entrada',
                    'label' => 'Ajustar Stock',
                    'icon' => 'edit',
                    'description' => 'Corregir diferencias encontradas'
                ];
                break;
        }
    }
    
    // Sugerencias basadas en el rol del usuario
    if ($user['rol_nombre'] === 'auditor' || $user['rol_nombre'] === 'super_admin') {
        $suggestions[] = [
            'action' => 'ajuste',
            'label' => 'Ajuste Manual',
            'icon' => 'settings',
            'description' => 'Realizar ajuste administrativo'
        ];
    }
    
    // Siempre incluir opción de consulta
    if ($current_action !== 'consulta') {
        array_unshift($suggestions, [
            'action' => 'consulta',
            'label' => 'Ver Información',
            'icon' => 'info-circle',
            'description' => 'Consultar detalles del producto'
        ]);
    }
    
    return $suggestions;
}
?>