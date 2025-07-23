<?php
/**
 * Verificar Email Existente (AJAX)
 * Sequoia Speed - Sistema de Accesos
 */

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once dirname(__DIR__) . '/config_secure.php';
require_once dirname(__DIR__) . '/php82_helpers.php';
require_once 'middleware/AuthMiddleware.php';
require_once 'models/User.php';

// Configurar header para JSON
header('Content-Type: application/json');

// Inicializar middleware
$auth = new AuthMiddleware($conn);

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Obtener email
$email = trim($_POST['email'] ?? '');

// Validar email
if (empty($email)) {
    echo json_encode(['success' => false, 'error' => 'Email no especificado']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Email no válido']);
    exit;
}

try {
    $user_model = new User($conn);
    $usuario_existente = $user_model->findByEmail($email);
    
    echo json_encode([
        'success' => true,
        'existe' => $usuario_existente !== null,
        'email' => $email
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error al verificar el email: ' . $e->getMessage()
    ]);
}

exit;
?>