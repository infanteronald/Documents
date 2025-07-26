<?php
/**
 * Obtener Detalles de Movimiento (AJAX)
 * Sequoia Speed - Módulo de Inventario
 */

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once '../config_secure.php';
require_once '../notifications/notification_helpers.php';
require_once '../php82_helpers.php';

// Configurar header para JSON
header('Content-Type: application/json');

// Verificar que sea una petición GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Obtener ID del movimiento
$movimiento_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($movimiento_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de movimiento inválido']);
    exit;
}

try {
    // Obtener datos del movimiento
    $query = "SELECT 
                m.*,
                p.nombre as producto_nombre,
                p.sku as producto_sku,
                p.imagen as producto_imagen,
                a.nombre as almacen_nombre,
                a.codigo as almacen_codigo,
                ad.nombre as almacen_destino_nombre,
                ad.codigo as almacen_destino_codigo
              FROM movimientos_inventario m
              INNER JOIN productos p ON m.producto_id = p.id
              INNER JOIN almacenes a ON m.almacen_id = a.id
              LEFT JOIN almacenes ad ON m.almacen_destino_id = ad.id
              WHERE m.id = ? 
              LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $movimiento_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $movimiento = $result->fetch_assoc();
    
    if (!$movimiento) {
        echo json_encode(['success' => false, 'error' => 'Movimiento no encontrado']);
        exit;
    }
    
    // Formatear datos para el frontend
    $movimiento_formateado = [
        'id' => $movimiento['id'],
        'producto_id' => $movimiento['producto_id'],
        'producto_nombre' => $movimiento['producto_nombre'],
        'producto_sku' => $movimiento['producto_sku'],
        'producto_imagen' => $movimiento['producto_imagen'],
        'almacen_id' => $movimiento['almacen_id'],
        'almacen_nombre' => $movimiento['almacen_nombre'],
        'almacen_codigo' => $movimiento['almacen_codigo'],
        'almacen_destino_id' => $movimiento['almacen_destino_id'],
        'almacen_destino_nombre' => $movimiento['almacen_destino_nombre'],
        'almacen_destino_codigo' => $movimiento['almacen_destino_codigo'],
        'tipo_movimiento' => $movimiento['tipo_movimiento'],
        'cantidad' => intval($movimiento['cantidad']),
        'cantidad_anterior' => intval($movimiento['cantidad_anterior']),
        'cantidad_nueva' => intval($movimiento['cantidad_nueva']),
        'costo_unitario' => floatval($movimiento['costo_unitario']),
        'motivo' => $movimiento['motivo'],
        'documento_referencia' => $movimiento['documento_referencia'],
        'usuario_responsable' => $movimiento['usuario_responsable'],
        'fecha_movimiento' => $movimiento['fecha_movimiento'],
        'observaciones' => $movimiento['observaciones']
    ];
    
    echo json_encode([
        'success' => true,
        'movimiento' => $movimiento_formateado
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener los datos del movimiento: ' . $e->getMessage()
    ]);
}

exit;
?>