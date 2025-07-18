<?php
/**
 * Verificar Alertas Automáticas (AJAX)
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

// Verificar que sea una petición GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

try {
    // Inicializar sistema de alertas
    $sistema_alertas = new SistemaAlertas($conn);
    
    // Verificar todas las alertas
    $resultados = $sistema_alertas->verificarTodasLasAlertas();
    
    // Calcular estadísticas
    $total_productos = 0;
    $total_alertas = 0;
    $tipos_verificados = [];
    
    foreach ($resultados as $resultado) {
        if ($resultado['success']) {
            $total_productos += $resultado['productos_encontrados'];
            $total_alertas += $resultado['alertas_creadas'];
            $tipos_verificados[] = $resultado['tipo'];
        }
    }
    
    // Generar mensaje de respuesta
    $mensaje = "Verificación completada: ";
    $mensaje .= count($tipos_verificados) . " tipos verificados, ";
    $mensaje .= $total_productos . " productos analizados, ";
    $mensaje .= $total_alertas . " nuevas alertas creadas";
    
    echo json_encode([
        'success' => true,
        'message' => $mensaje,
        'estadisticas' => [
            'tipos_verificados' => count($tipos_verificados),
            'productos_analizados' => $total_productos,
            'alertas_creadas' => $total_alertas
        ],
        'resultados' => $resultados
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error al verificar alertas: ' . $e->getMessage()
    ]);
}

exit;
?>