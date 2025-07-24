<?php
/**
 * API de Estadísticas para Reportes
 * Sequoia Speed - Centro de Reportes
 */

// Requerir autenticación
require_once '../../accesos/auth_helper.php';

// Proteger la página - requiere permisos de reportes
$current_user = auth_require('reportes', 'leer');

// Configurar respuesta JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Obtener parámetros
$modulo = $_GET['modulo'] ?? 'general';
$periodo = $_GET['periodo'] ?? 'hoy';

// Configurar fechas según período
$fecha_inicio = date('Y-m-d');
$fecha_fin = date('Y-m-d');

switch ($periodo) {
    case 'hoy':
        $fecha_inicio = $fecha_fin = date('Y-m-d');
        break;
    case 'ayer':
        $fecha_inicio = $fecha_fin = date('Y-m-d', strtotime('-1 day'));
        break;
    case 'semana':
        $fecha_inicio = date('Y-m-d', strtotime('monday this week'));
        $fecha_fin = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'mes':
        $fecha_inicio = date('Y-m-01');
        $fecha_fin = date('Y-m-t');
        break;
    case 'trimestre':
        $mes_actual = date('n');
        $trimestre = ceil($mes_actual / 3);
        $fecha_inicio = date('Y-' . sprintf('%02d', ($trimestre - 1) * 3 + 1) . '-01');
        $fecha_fin = date('Y-m-t', strtotime($fecha_inicio . ' +2 months'));
        break;
    case 'año':
        $fecha_inicio = date('Y-01-01');
        $fecha_fin = date('Y-12-31');
        break;
}

$stats = [
    'success' => true,
    'modulo' => $modulo,
    'periodo' => $periodo,
    'fecha_inicio' => $fecha_inicio,
    'fecha_fin' => $fecha_fin,
    'timestamp' => date('Y-m-d H:i:s'),
    'data' => []
];

try {
    global $conn;
    
    switch ($modulo) {
        case 'ventas':
            // Estadísticas de ventas
            if (auth_can('ventas', 'leer')) {
                $stmt = $conn->prepare("
                    SELECT 
                        COUNT(*) as total_pedidos,
                        SUM(monto) as total_ventas,
                        AVG(monto) as ticket_promedio,
                        COUNT(DISTINCT ciudad) as ciudades_atendidas,
                        MAX(monto) as venta_mayor,
                        MIN(monto) as venta_menor
                    FROM pedidos_detal 
                    WHERE DATE(fecha) BETWEEN ? AND ? 
                    AND estado != 'Anulado'
                ");
                $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
                $stmt->execute();
                $result = $stmt->get_result();
                $stats['data'] = $result->fetch_assoc();
                
                // Convertir a números
                $stats['data']['total_pedidos'] = (int) $stats['data']['total_pedidos'];
                $stats['data']['total_ventas'] = (float) $stats['data']['total_ventas'];
                $stats['data']['ticket_promedio'] = (float) $stats['data']['ticket_promedio'];
                $stats['data']['ciudades_atendidas'] = (int) $stats['data']['ciudades_atendidas'];
                $stats['data']['venta_mayor'] = (float) $stats['data']['venta_mayor'];
                $stats['data']['venta_menor'] = (float) $stats['data']['venta_menor'];
                
                // Tendencia vs período anterior
                $periodo_anterior_inicio = date('Y-m-d', strtotime($fecha_inicio . ' -1 ' . $periodo));
                $periodo_anterior_fin = date('Y-m-d', strtotime($fecha_fin . ' -1 ' . $periodo));
                
                $stmt = $conn->prepare("
                    SELECT 
                        COUNT(*) as pedidos_anterior,
                        SUM(monto) as ventas_anterior
                    FROM pedidos_detal 
                    WHERE DATE(fecha) BETWEEN ? AND ? 
                    AND estado != 'Anulado'
                ");
                $stmt->bind_param("ss", $periodo_anterior_inicio, $periodo_anterior_fin);
                $stmt->execute();
                $result = $stmt->get_result();
                $anterior = $result->fetch_assoc();
                
                $stats['data']['crecimiento_ventas'] = $anterior['ventas_anterior'] > 0 
                    ? (($stats['data']['total_ventas'] - $anterior['ventas_anterior']) / $anterior['ventas_anterior']) * 100 
                    : 0;
                $stats['data']['crecimiento_pedidos'] = $anterior['pedidos_anterior'] > 0 
                    ? (($stats['data']['total_pedidos'] - $anterior['pedidos_anterior']) / $anterior['pedidos_anterior']) * 100 
                    : 0;
            }
            break;
            
        case 'inventario':
            // Estadísticas de inventario
            if (auth_can('inventario', 'leer')) {
                try {
                    $stmt = $conn->prepare("
                        SELECT 
                            COUNT(*) as total_productos,
                            SUM(stock_actual) as stock_total,
                            AVG(stock_actual) as stock_promedio,
                            COUNT(CASE WHEN stock_actual <= stock_minimo THEN 1 END) as productos_stock_bajo,
                            COUNT(CASE WHEN activo = 1 THEN 1 END) as productos_activos,
                            COUNT(CASE WHEN activo = 0 THEN 1 END) as productos_inactivos
                        FROM productos
                    ");
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $stats['data'] = $result->fetch_assoc();
                    
                    // Convertir a números
                    $stats['data']['total_productos'] = (int) $stats['data']['total_productos'];
                    $stats['data']['stock_total'] = (int) $stats['data']['stock_total'];
                    $stats['data']['stock_promedio'] = (float) $stats['data']['stock_promedio'];
                    $stats['data']['productos_stock_bajo'] = (int) $stats['data']['productos_stock_bajo'];
                    $stats['data']['productos_activos'] = (int) $stats['data']['productos_activos'];
                    $stats['data']['productos_inactivos'] = (int) $stats['data']['productos_inactivos'];
                    
                } catch (Exception $e) {
                    $stats['data'] = [
                        'total_productos' => 0,
                        'stock_total' => 0,
                        'stock_promedio' => 0,
                        'productos_stock_bajo' => 0,
                        'productos_activos' => 0,
                        'productos_inactivos' => 0,
                        'error' => 'Tabla productos no disponible'
                    ];
                }
            }
            break;
            
        case 'usuarios':
            // Estadísticas de usuarios
            if (auth_can('usuarios', 'leer')) {
                $stmt = $conn->prepare("
                    SELECT 
                        COUNT(*) as total_usuarios,
                        COUNT(CASE WHEN activo = 1 THEN 1 END) as usuarios_activos,
                        COUNT(CASE WHEN activo = 0 THEN 1 END) as usuarios_inactivos,
                        COUNT(CASE WHEN ultimo_acceso >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as usuarios_activos_semana,
                        COUNT(CASE WHEN ultimo_acceso >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as usuarios_activos_mes
                    FROM usuarios
                ");
                $stmt->execute();
                $result = $stmt->get_result();
                $stats['data'] = $result->fetch_assoc();
                
                // Convertir a números
                $stats['data']['total_usuarios'] = (int) $stats['data']['total_usuarios'];
                $stats['data']['usuarios_activos'] = (int) $stats['data']['usuarios_activos'];
                $stats['data']['usuarios_inactivos'] = (int) $stats['data']['usuarios_inactivos'];
                $stats['data']['usuarios_activos_semana'] = (int) $stats['data']['usuarios_activos_semana'];
                $stats['data']['usuarios_activos_mes'] = (int) $stats['data']['usuarios_activos_mes'];
                
                // Sesiones activas
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as sesiones_activas
                    FROM sesiones 
                    WHERE activa = 1 AND fecha_expiracion > NOW()
                ");
                $stmt->execute();
                $result = $stmt->get_result();
                $sesiones = $result->fetch_assoc();
                $stats['data']['sesiones_activas'] = (int) $sesiones['sesiones_activas'];
            }
            break;
            
        case 'general':
        default:
            // Estadísticas generales
            $stats['data'] = [
                'ventas' => [],
                'inventario' => [],
                'usuarios' => []
            ];
            
            // Ventas (si tiene permisos)
            if (auth_can('ventas', 'leer')) {
                $stmt = $conn->prepare("
                    SELECT 
                        COUNT(*) as pedidos,
                        SUM(monto) as ventas
                    FROM pedidos_detal 
                    WHERE DATE(fecha) BETWEEN ? AND ? 
                    AND estado != 'Anulado'
                ");
                $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
                $stmt->execute();
                $result = $stmt->get_result();
                $stats['data']['ventas'] = $result->fetch_assoc();
                $stats['data']['ventas']['pedidos'] = (int) $stats['data']['ventas']['pedidos'];
                $stats['data']['ventas']['ventas'] = (float) $stats['data']['ventas']['ventas'];
            }
            
            // Inventario (si tiene permisos)
            if (auth_can('inventario', 'leer')) {
                try {
                    $stmt = $conn->prepare("
                        SELECT 
                            COUNT(*) as productos,
                            COUNT(CASE WHEN stock_actual <= stock_minimo THEN 1 END) as alertas
                        FROM productos WHERE activo = 1
                    ");
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $stats['data']['inventario'] = $result->fetch_assoc();
                    $stats['data']['inventario']['productos'] = (int) $stats['data']['inventario']['productos'];
                    $stats['data']['inventario']['alertas'] = (int) $stats['data']['inventario']['alertas'];
                } catch (Exception $e) {
                    $stats['data']['inventario'] = ['productos' => 0, 'alertas' => 0];
                }
            }
            
            // Usuarios (si tiene permisos)
            if (auth_can('usuarios', 'leer')) {
                $stmt = $conn->prepare("
                    SELECT 
                        COUNT(*) as usuarios,
                        COUNT(CASE WHEN ultimo_acceso >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as activos_hoy
                    FROM usuarios WHERE activo = 1
                ");
                $stmt->execute();
                $result = $stmt->get_result();
                $stats['data']['usuarios'] = $result->fetch_assoc();
                $stats['data']['usuarios']['usuarios'] = (int) $stats['data']['usuarios']['usuarios'];
                $stats['data']['usuarios']['activos_hoy'] = (int) $stats['data']['usuarios']['activos_hoy'];
            }
            break;
    }
    
    // Registrar acceso a la API
    auth_log('read', 'reportes', "API stats consultada - módulo: $modulo, período: $periodo");
    
} catch (Exception $e) {
    $stats = [
        'success' => false,
        'error' => 'Error interno del servidor',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    error_log("Error en API stats: " . $e->getMessage());
}

// Enviar respuesta
echo json_encode($stats, JSON_PRETTY_PRINT);
exit;
?>