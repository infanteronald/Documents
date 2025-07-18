<?php
/**
 * Exportar Productos a Excel
 * Sequoia Speed - Módulo de Inventario
 */

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once '../config_secure.php';
require_once '../notifications/notification_helpers.php';
require_once '../php82_helpers.php';

// Verificar que sea una petición GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Location: productos.php');
    exit;
}

try {
    // Obtener parámetros de filtrado
    $buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
    $categoria = isset($_GET['categoria']) ? trim($_GET['categoria']) : '';
    $almacen = isset($_GET['almacen']) ? trim($_GET['almacen']) : '';
    $stock_nivel = isset($_GET['stock_nivel']) ? $_GET['stock_nivel'] : '';
    $activo = isset($_GET['activo']) ? $_GET['activo'] : '';
    
    // Construir query base
    $query = "SELECT 
                id, nombre, descripcion, categoria, precio, 
                stock_actual, stock_minimo, stock_maximo, 
                almacen, activo, sku, 
                fecha_creacion, fecha_actualizacion
              FROM productos 
              WHERE 1=1";
    
    $params = [];
    $types = '';
    
    // Aplicar filtros
    if (!empty($buscar)) {
        $query .= " AND (nombre LIKE ? OR descripcion LIKE ? OR sku LIKE ?)";
        $buscar_param = '%' . $buscar . '%';
        $params[] = $buscar_param;
        $params[] = $buscar_param;
        $params[] = $buscar_param;
        $types .= 'sss';
    }
    
    if (!empty($categoria)) {
        $query .= " AND categoria = ?";
        $params[] = $categoria;
        $types .= 's';
    }
    
    if (!empty($almacen)) {
        $query .= " AND almacen = ?";
        $params[] = $almacen;
        $types .= 's';
    }
    
    if ($activo !== '') {
        $query .= " AND activo = ?";
        $params[] = intval($activo);
        $types .= 'i';
    }
    
    // Filtro por nivel de stock
    if (!empty($stock_nivel)) {
        switch ($stock_nivel) {
            case 'bajo':
                $query .= " AND stock_actual <= stock_minimo";
                break;
            case 'medio':
                $query .= " AND stock_actual > stock_minimo AND stock_actual <= (stock_minimo + (stock_maximo - stock_minimo) * 0.3)";
                break;
            case 'alto':
                $query .= " AND stock_actual > (stock_minimo + (stock_maximo - stock_minimo) * 0.3)";
                break;
        }
    }
    
    $query .= " ORDER BY nombre ASC";
    
    // Ejecutar consulta
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $productos = $result->fetch_all(MYSQLI_ASSOC);
    
    // Configurar headers para descarga de Excel
    $filename = 'productos_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    
    // Crear output stream
    $output = fopen('php://output', 'w');
    
    // Escribir BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Escribir encabezados
    $encabezados = [
        'ID',
        'Nombre',
        'Descripción',
        'Categoría',
        'Precio',
        'Stock Actual',
        'Stock Mínimo',
        'Stock Máximo',
        'Almacén',
        'Estado',
        'SKU',
        'Fecha Creación',
        'Fecha Actualización'
    ];
    
    fputcsv($output, $encabezados, ';');
    
    // Escribir datos
    foreach ($productos as $producto) {
        $fila = [
            $producto['id'],
            $producto['nombre'],
            $producto['descripcion'],
            $producto['categoria'],
            number_format($producto['precio'], 0, ',', '.'),
            $producto['stock_actual'],
            $producto['stock_minimo'],
            $producto['stock_maximo'],
            $producto['almacen'],
            $producto['activo'] == '1' ? 'Activo' : 'Inactivo',
            $producto['sku'],
            date('d/m/Y H:i', strtotime($producto['fecha_creacion'])),
            !empty($producto['fecha_actualizacion']) ? date('d/m/Y H:i', strtotime($producto['fecha_actualizacion'])) : ''
        ];
        
        fputcsv($output, $fila, ';');
    }
    
    fclose($output);
    
} catch (Exception $e) {
    // En caso de error, redirigir con mensaje
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION['errores'] = ['Error al exportar: ' . $e->getMessage()];
    header('Location: productos.php');
    exit;
}

exit;
?>