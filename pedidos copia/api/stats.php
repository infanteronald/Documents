<?php
/**
 * API de Estadísticas en Tiempo Real
 * Sequoia Speed - Sistema Integrado
 */

// Requerir autenticación
require_once '../accesos/auth_helper.php';

// Proteger la API - requiere login
$current_user = auth_require();

// Headers para JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Función para verificar permisos
function canAccess($module) {
    return auth_can($module, 'leer');
}

// Inicializar estadísticas
$stats = [
    'pedidos_hoy' => 0,
    'pagos_pendientes' => 0,
    'listos_envio' => 0,
    'sin_guia' => 0,
    'productos_stock_bajo' => 0,
    'ventas_mes' => 0,
    'usuarios_activos' => 0,
    'pedidos_urgentes' => 0,
    'comprobantes_pendientes' => 0,
    'timestamp' => date('Y-m-d H:i:s')
];

try {
    global $conn;
    
    // Estadísticas de ventas (si tiene permisos)
    if (canAccess('ventas')) {
        // Pedidos de hoy
        $hoy = date('Y-m-d');
        $stmt = $conn->prepare("SELECT COUNT(*) FROM pedidos_detal WHERE DATE(fecha) = ?");
        $stmt->bind_param("s", $hoy);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['pedidos_hoy'] = (int)$result->fetch_row()[0];
        
        // Pagos pendientes
        $stmt = $conn->prepare("SELECT COUNT(*) FROM pedidos_detal WHERE estado = 'Pago Pendiente'");
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['pagos_pendientes'] = (int)$result->fetch_row()[0];
        
        // Listos para envío
        $stmt = $conn->prepare("SELECT COUNT(*) FROM pedidos_detal WHERE estado = 'Pago Confirmado'");
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['listos_envio'] = (int)$result->fetch_row()[0];
        
        // Sin guía
        $stmt = $conn->prepare("SELECT COUNT(*) FROM pedidos_detal WHERE (guia IS NULL OR guia = '') AND estado = 'Pago Confirmado'");
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['sin_guia'] = (int)$result->fetch_row()[0];
        
        // Pedidos urgentes (más de 1 día sin enviar)
        $stmt = $conn->prepare("SELECT COUNT(*) FROM pedidos_detal WHERE DATEDIFF(NOW(), fecha) > 1 AND enviado = 0 AND anulado = 0");
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['pedidos_urgentes'] = (int)$result->fetch_row()[0];
        
        // Comprobantes pendientes de validación
        $stmt = $conn->prepare("SELECT COUNT(*) FROM pedidos_detal WHERE tiene_comprobante = 1 AND pagado = 0");
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['comprobantes_pendientes'] = (int)$result->fetch_row()[0];
        
        // Ventas del mes
        $stmt = $conn->prepare("SELECT COALESCE(SUM(monto), 0) FROM pedidos_detal WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) AND estado != 'Anulado'");
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['ventas_mes'] = (float)$result->fetch_row()[0];
    }
    
    // Estadísticas de inventario (si tiene permisos)
    if (canAccess('inventario')) {
        try {
            // Productos con stock bajo
            $stmt = $conn->prepare("SELECT COUNT(*) FROM productos WHERE stock < stock_minimo");
            if ($stmt) {
                $stmt->execute();
                $result = $stmt->get_result();
                $stats['productos_stock_bajo'] = (int)$result->fetch_row()[0];
            }
        } catch (Exception $e) {
            // Si no existe la tabla productos, mantener en 0
            $stats['productos_stock_bajo'] = 0;
        }
    }
    
    // Estadísticas de usuarios (si tiene permisos)
    if (canAccess('usuarios')) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM usuarios WHERE activo = 1");
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['usuarios_activos'] = (int)$result->fetch_row()[0];
    }
    
    // Registrar acceso a la API
    auth_log('read', 'sistema', 'Consulta API de estadísticas');
    
    // Retornar estadísticas
    echo json_encode([
        'success' => true,
        'data' => $stats,
        'user' => [
            'nombre' => $current_user['nombre'],
            'rol' => $current_user['rol'] ?? 'vendedor'
        ]
    ]);
    
} catch (Exception $e) {
    // Error en la consulta
    error_log("Error en API de estadísticas: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor',
        'data' => $stats // Retornar datos vacíos
    ]);
}
?>