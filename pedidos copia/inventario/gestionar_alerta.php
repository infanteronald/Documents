<?php
/**
 * Gestionar Alertas (AJAX)
 * Sequoia Speed - Módulo de Inventario
 */

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once '../config_secure.php';
require_once '../notifications/notification_helpers.php';
require_once '../php82_helpers.php';
require_once 'sistema_alertas.php';

// Configurar header para JSON
header('Content-Type: application/json');

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Obtener acción
$accion = $_POST['accion'] ?? '';

if (empty($accion)) {
    echo json_encode(['success' => false, 'error' => 'Acción no especificada']);
    exit;
}

try {
    // Inicializar sistema de alertas
    $sistema_alertas = new SistemaAlertas($conn);
    
    switch ($accion) {
        case 'marcar_vista':
            $id = intval($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de alerta inválido']);
                exit;
            }
            
            if ($sistema_alertas->marcarComoVista($id)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Alerta marcada como vista'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Error al marcar la alerta como vista'
                ]);
            }
            break;
            
        case 'resolver':
            $id = intval($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de alerta inválido']);
                exit;
            }
            
            if ($sistema_alertas->resolverAlerta($id)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Alerta resuelta correctamente'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Error al resolver la alerta'
                ]);
            }
            break;
            
        case 'marcar_todas_vistas':
            $query = "UPDATE alertas_inventario 
                      SET estado = 'vista', usuario_resolucion = 1 
                      WHERE estado = 'pendiente'";
            
            if ($conn->query($query)) {
                $alertas_actualizadas = $conn->affected_rows;
                echo json_encode([
                    'success' => true,
                    'message' => "Se marcaron {$alertas_actualizadas} alertas como vistas"
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Error al marcar las alertas como vistas'
                ]);
            }
            break;
            
        case 'ignorar':
            $id = intval($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de alerta inválido']);
                exit;
            }
            
            $query = "UPDATE alertas_inventario 
                      SET estado = 'ignorada', usuario_resolucion = 1 
                      WHERE id = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $id);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Alerta ignorada correctamente'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Error al ignorar la alerta'
                ]);
            }
            break;
            
        case 'eliminar_resueltas':
            $query = "DELETE FROM alertas_inventario 
                      WHERE estado = 'resuelta' 
                      AND fecha_resolucion < DATE_SUB(NOW(), INTERVAL 30 DAY)";
            
            if ($conn->query($query)) {
                $alertas_eliminadas = $conn->affected_rows;
                echo json_encode([
                    'success' => true,
                    'message' => "Se eliminaron {$alertas_eliminadas} alertas resueltas antiguas"
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Error al eliminar las alertas resueltas'
                ]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error al gestionar la alerta: ' . $e->getMessage()
    ]);
}

exit;
?>