<?php
/**
 * API de Reportes de Ventas - Sequoia Speed
 * Endpoint: GET /api/reports/ventas
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Solo permitir GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

try {
    // Incluir configuración
    require_once '../../../config_secure.php';
    
    // Parámetros de consulta
    $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01'); // Primer día del mes actual
    $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d'); // Hoy
    $categoria = $_GET['categoria'] ?? null;
    $limite = intval($_GET['limite'] ?? 50);
    
    // Validar fechas
    if (!strtotime($fecha_inicio) || !strtotime($fecha_fin)) {
        http_response_code(400);
        echo json_encode(['error' => 'Formato de fecha inválido']);
        exit();
    }
    
    // Consulta base
    $sql = "
        SELECT 
            p.id,
            p.fecha_pedido,
            p.nombre_cliente,
            p.telefono_cliente,
            p.direccion_entrega,
            p.total,
            p.estado,
            p.metodo_pago,
            GROUP_CONCAT(
                CONCAT(pp.cantidad, 'x ', pr.nombre, ' ($', pp.precio_unitario, ')')
                SEPARATOR ', '
            ) as productos
        FROM pedidos p
        LEFT JOIN pedido_productos pp ON p.id = pp.pedido_id
        LEFT JOIN productos pr ON pp.producto_id = pr.id
        LEFT JOIN categorias_productos c ON pr.categoria_id = c.id
        WHERE p.fecha_pedido BETWEEN ? AND ?
    ";
    
    $params = [$fecha_inicio, $fecha_fin . ' 23:59:59'];
    
    // Filtro por categoría si se especifica
    if ($categoria) {
        $sql .= " AND c.nombre = ?";
        $params[] = $categoria;
    }
    
    $sql .= " 
        GROUP BY p.id 
        ORDER BY p.fecha_pedido DESC 
        LIMIT ?
    ";
    $params[] = $limite;
    
    // Ejecutar consulta principal
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Consulta de resumen
    $sql_resumen = "
        SELECT 
            COUNT(*) as total_pedidos,
            COALESCE(SUM(total), 0) as total_ventas,
            COALESCE(AVG(total), 0) as promedio_pedido,
            COUNT(DISTINCT DATE(fecha_pedido)) as dias_activos
        FROM pedidos 
        WHERE fecha_pedido BETWEEN ? AND ?
    ";
    
    $params_resumen = [$fecha_inicio, $fecha_fin . ' 23:59:59'];
    
    if ($categoria) {
        $sql_resumen .= " AND id IN (
            SELECT DISTINCT pp.pedido_id 
            FROM pedido_productos pp 
            JOIN productos pr ON pp.producto_id = pr.id 
            JOIN categorias_productos c ON pr.categoria_id = c.id
            WHERE c.nombre = ?
        )";
        $params_resumen[] = $categoria;
    }
    
    $stmt_resumen = $pdo->prepare($sql_resumen);
    $stmt_resumen->execute($params_resumen);
    $resumen = $stmt_resumen->fetch(PDO::FETCH_ASSOC);
    
    // Consulta de productos más vendidos
    $sql_productos = "
        SELECT 
            pr.nombre,
            c.nombre as categoria,
            SUM(pp.cantidad) as total_vendido,
            SUM(pp.cantidad * pp.precio_unitario) as total_ingresos
        FROM pedido_productos pp
        JOIN productos pr ON pp.producto_id = pr.id
        LEFT JOIN categorias_productos c ON pr.categoria_id = c.id
        JOIN pedidos p ON pp.pedido_id = p.id
        WHERE p.fecha_pedido BETWEEN ? AND ?
    ";
    
    $params_productos = [$fecha_inicio, $fecha_fin . ' 23:59:59'];
    
    if ($categoria) {
        $sql_productos .= " AND c.nombre = ?";
        $params_productos[] = $categoria;
    }
    
    $sql_productos .= "
        GROUP BY pr.id, pr.nombre, c.nombre
        ORDER BY total_vendido DESC
        LIMIT 10
    ";
    
    $stmt_productos = $pdo->prepare($sql_productos);
    $stmt_productos->execute($params_productos);
    $productos_populares = $stmt_productos->fetchAll(PDO::FETCH_ASSOC);
    
    // Respuesta exitosa
    $response = [
        'status' => 'success',
        'data' => [
            'ventas' => $ventas,
            'resumen' => [
                'total_pedidos' => intval($resumen['total_pedidos']),
                'total_ventas' => floatval($resumen['total_ventas']),
                'promedio_pedido' => floatval($resumen['promedio_pedido']),
                'dias_activos' => intval($resumen['dias_activos'])
            ],
            'productos_populares' => $productos_populares,
            'filtros' => [
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin' => $fecha_fin,
                'categoria' => $categoria,
                'limite' => $limite
            ]
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error de base de datos',
        'details' => $e->getMessage()
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error interno del servidor',
        'details' => $e->getMessage()
    ]);
}
?>
