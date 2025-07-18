<?php
/**
 * Cambiar Estado de Rol (AJAX)
 * Sequoia Speed - Sistema de Accesos
 */

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once '../config_secure.php';
require_once '../php82_helpers.php';
require_once 'middleware/AuthMiddleware.php';
require_once 'models/Role.php';

// Configurar header para JSON
header('Content-Type: application/json');

// Inicializar middleware y requerir permisos
$auth = new AuthMiddleware($conn);
$current_user = $auth->requirePermission('usuarios', 'actualizar');

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Obtener datos
$role_id = intval($_POST['id'] ?? 0);
$accion = $_POST['accion'] ?? '';

// Validaciones
if ($role_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de rol inválido']);
    exit;
}

if (!in_array($accion, ['activar', 'desactivar'])) {
    echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    exit;
}

try {
    $role_model = new Role($conn);
    
    // Verificar que el rol existe
    $rol = $role_model->findById($role_id);
    if (!$rol) {
        echo json_encode(['success' => false, 'error' => 'Rol no encontrado']);
        exit;
    }
    
    // Verificar si es un rol del sistema que no se puede desactivar
    $roles_sistema = ['super_admin', 'admin'];
    if ($accion === 'desactivar' && in_array($rol['nombre'], $roles_sistema)) {
        echo json_encode(['success' => false, 'error' => 'No se puede desactivar este rol del sistema']);
        exit;
    }
    
    // Realizar la acción
    if ($accion === 'activar') {
        $result = $role_model->activate($role_id, $current_user['id']);
        $mensaje = 'Rol activado correctamente';
        $descripcion = "Rol activado: {$rol['nombre']}";
    } else {
        $result = $role_model->deactivate($role_id, $current_user['id']);
        $mensaje = 'Rol desactivado correctamente';
        $descripcion = "Rol desactivado: {$rol['nombre']}";
    }
    
    if ($result) {
        // Registrar auditoría
        $auth->logActivity('update', 'usuarios', $descripcion);
        
        echo json_encode([
            'success' => true,
            'message' => $mensaje
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Error al cambiar el estado del rol'
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