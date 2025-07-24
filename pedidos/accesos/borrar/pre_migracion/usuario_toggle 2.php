<?php
/**
 * Cambiar Estado de Usuario (AJAX)
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

// Inicializar middleware y requerir permisos
$auth = new AuthMiddleware($conn);
$current_user = $auth->requirePermission('acc_usuarios', 'actualizar');

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Obtener datos
$user_id = intval($_POST['id'] ?? 0);
$accion = $_POST['accion'] ?? '';

// Validaciones
if ($user_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de usuario inválido']);
    exit;
}

if (!in_array($accion, ['activar', 'desactivar'])) {
    echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    exit;
}

// No permitir desactivar su propia cuenta
if ($user_id == $current_user['id']) {
    echo json_encode(['success' => false, 'error' => 'No puedes desactivar tu propia cuenta']);
    exit;
}

try {
    $user_model = new User($conn);
    
    // Verificar que el usuario existe
    $usuario = $user_model->findById($user_id);
    if (!$usuario) {
        echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
        exit;
    }
    
    // Realizar la acción
    if ($accion === 'activar') {
        $result = $user_model->activate($user_id, $current_user['id']);
        $mensaje = 'Usuario activado correctamente';
        $descripcion = "Usuario activado: {$usuario['email']}";
    } else {
        $result = $user_model->deactivate($user_id, $current_user['id']);
        $mensaje = 'Usuario desactivado correctamente';
        $descripcion = "Usuario desactivado: {$usuario['email']}";
    }
    
    if ($result) {
        // Registrar auditoría
        $auth->logActivity('update', 'acc_usuarios', $descripcion);
        
        echo json_encode([
            'success' => true,
            'message' => $mensaje
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Error al cambiar el estado del usuario'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error interno: ' . $e->getMessage()
    ]);
}

exit;
?>