<?php
/**
 * API - CSRF Token Refresh
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
require_once dirname(__DIR__) . '/csrf_helper.php';

try {
    // Verificar autenticación
    $auth = new AuthMiddleware($conn);
    $current_user = $auth->requireLogin();
    
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método no permitido', 405);
    }
    
    // Generar nuevo token CSRF
    $token = generateCSRFToken();
    
    echo json_encode([
        'success' => true,
        'token' => $token,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    $error_code = $e->getCode() ?: 500;
    http_response_code($error_code);
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $error_code,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    error_log("CSRF Token API Error: " . $e->getMessage() . " | Code: " . $error_code);
}
?>