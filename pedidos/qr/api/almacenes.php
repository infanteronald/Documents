<?php
/**
 * API - Almacenes
 * Devuelve lista de almacenes activos para el sistema QR
 */

header('Content-Type: application/json');

defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once dirname(dirname(__DIR__)) . '/config_secure.php';
require_once dirname(dirname(__DIR__)) . '/accesos/middleware/AuthMiddleware.php';

try {
    // Verificar autenticación
    $auth = new AuthMiddleware($conn);
    $current_user = $auth->requirePermission('qr', 'leer');
    
    // Obtener almacenes activos
    $query = "SELECT id, nombre, codigo, descripcion 
              FROM almacenes 
              WHERE activo = 1 
              ORDER BY nombre";
    
    $result = $conn->query($query);
    $almacenes = [];
    
    while ($row = $result->fetch_assoc()) {
        // Sanitizar datos
        $almacenes[] = [
            'id' => (int)$row['id'],
            'nombre' => htmlspecialchars($row['nombre'], ENT_QUOTES, 'UTF-8'),
            'codigo' => htmlspecialchars($row['codigo'], ENT_QUOTES, 'UTF-8'),
            'descripcion' => htmlspecialchars($row['descripcion'] ?? '', ENT_QUOTES, 'UTF-8')
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $almacenes,
        'total' => count($almacenes),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>