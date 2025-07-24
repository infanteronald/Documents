<?php
/**
 * API - Generador de Códigos QR
 * Sequoia Speed - Sistema QR
 */

header('Content-Type: application/json');
// Configuración CORS restrictiva - cambiar por el dominio de producción
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
require_once dirname(__DIR__) . '/error_handler.php';

// Configurar manejo de errores
setupErrorHandler();

// Establecer headers de seguridad para API
setAPISecurityHeaders();

try {
    // Verificar autenticación
    $auth = new AuthMiddleware($conn);
    $current_user = $auth->requirePermission('qr', 'crear');
    
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
    
    // Validar campos requeridos
    $required_fields = ['producto_id', 'almacen_id'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            throw new Exception("Campo requerido faltante: $field", 400);
        }
        
        // Validar que sean enteros positivos
        if (!filter_var($input[$field], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            throw new Exception("Campo $field debe ser un entero positivo", 400);
        }
    }
    
    // Validar que el producto existe y está activo
    $producto_id = (int)$input['producto_id'];
    $producto_query = "SELECT id, nombre, sku FROM productos WHERE id = ? AND activo = 1";
    $stmt = $conn->prepare($producto_query);
    $stmt->bind_param('i', $producto_id);
    $stmt->execute();
    $producto = $stmt->get_result()->fetch_assoc();
    
    if (!$producto) {
        throw new Exception('Producto no encontrado o inactivo', 404);
    }
    
    // Escapar datos del producto para evitar XSS
    $producto['nombre'] = htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8');
    $producto['sku'] = htmlspecialchars($producto['sku'], ENT_QUOTES, 'UTF-8');
    
    // Validar que el almacén existe y está activo
    $almacen_query = "SELECT id, nombre, codigo FROM almacenes WHERE id = ? AND activo = 1";
    $stmt = $conn->prepare($almacen_query);
    $stmt->bind_param('i', $input['almacen_id']);
    $stmt->execute();
    $almacen = $stmt->get_result()->fetch_assoc();
    
    if (!$almacen) {
        throw new Exception('Almacén no encontrado o inactivo', 404);
    }
    
    // Verificar si ya existe un QR activo para este producto-almacén
    $existing_qr_query = "SELECT qr_content, qr_uuid FROM qr_codes 
                          WHERE linked_product_id = ? AND linked_almacen_id = ? AND active = 1 LIMIT 1";
    $stmt = $conn->prepare($existing_qr_query);
    $stmt->bind_param('ii', $input['producto_id'], $input['almacen_id']);
    $stmt->execute();
    $existing_qr = $stmt->get_result()->fetch_assoc();
    
    // Si existe un QR y no se fuerza la regeneración, devolver el existente
    if ($existing_qr && !($input['force_regenerate'] ?? false)) {
        echo json_encode([
            'success' => true,
            'message' => 'QR ya existe para este producto-almacén',
            'qr_content' => $existing_qr['qr_content'],
            'qr_uuid' => $existing_qr['qr_uuid'],
            'regenerated' => false,
            'producto' => $producto,
            'almacen' => $almacen
        ]);
        exit;
    }
    
    // Preparar datos adicionales
    $additional_data = [
        'generated_via' => 'api',
        'client_info' => [
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        ],
        'custom_data' => $input['custom_data'] ?? []
    ];
    
    // Crear instancia del QR Manager
    $qr_manager = new QRManager($conn);
    
    // Si hay un QR existente y se fuerza la regeneración, desactivarlo
    if ($existing_qr && ($input['force_regenerate'] ?? false)) {
        $deactivate_query = "UPDATE qr_codes SET active = 0, updated_at = NOW() 
                            WHERE linked_product_id = ? AND linked_almacen_id = ? AND active = 1";
        $stmt = $conn->prepare($deactivate_query);
        $stmt->bind_param('ii', $input['producto_id'], $input['almacen_id']);
        $stmt->execute();
    }
    
    // Generar nuevo QR
    $result = $qr_manager->createProductQR(
        $input['producto_id'],
        $input['almacen_id'],
        $current_user['id'],
        $additional_data
    );
    
    if ($result['success']) {
        // Preparar respuesta completa
        $response = [
            'success' => true,
            'message' => 'Código QR generado exitosamente',
            'qr_id' => $result['qr_id'],
            'qr_content' => $result['qr_content'],
            'qr_uuid' => $result['qr_uuid'],
            'regenerated' => isset($existing_qr),
            'producto' => $producto,
            'almacen' => $almacen,
            'base_data' => $result['base_data'],
            'generated_at' => date('Y-m-d H:i:s'),
            'generated_by' => [
                'id' => $current_user['id'],
                'nombre' => $current_user['nombre'],
                'usuario' => $current_user['usuario']
            ]
        ];
        
        // Agregar URL de visualización del QR si se solicita
        if ($input['include_qr_url'] ?? false) {
            $base_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
            $response['qr_image_url'] = $base_url . '/qr/api/image.php?content=' . urlencode($result['qr_content']);
            $response['qr_view_url'] = $base_url . '/qr/view.php?uuid=' . $result['qr_uuid'];
        }
        
        echo json_encode($response);
        
    } else {
        throw new Exception('Error generando código QR', 500);
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
    
    // Log del error para debugging
    error_log("QR Generation API Error: " . $e->getMessage() . " | Code: " . $error_code);
}
?>