<?php
/**
 * Vista Detallada del Almacén
 * Sistema de Inventario - Sequoia Speed
 */

// Iniciar sesión
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Requerir autenticación
require_once '../../accesos/auth_helper.php';
$current_user = auth_require('inventario', 'leer');

// Definir constante y conexión
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once '../../config_secure.php';

// Obtener ID del almacén
$almacen_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($almacen_id <= 0) {
    $_SESSION['mensaje_error'] = 'ID de almacén inválido';
    header('Location: index.php');
    exit;
}

// Obtener datos del almacén
try {
    $query = "SELECT * FROM almacenes WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $almacen_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $almacen = $result->fetch_assoc();

    if (!$almacen) {
        $_SESSION['mensaje_error'] = 'Almacén no encontrado';
        header('Location: index.php');
        exit;
    }
} catch (Exception $e) {
    error_log('Error obteniendo almacén: ' . $e->getMessage());
    $_SESSION['mensaje_error'] = 'Error al obtener el almacén';
    header('Location: index.php');
    exit;
}

// Obtener productos del almacén
$search = $_GET['search'] ?? '';
$filter_stock = $_GET['stock'] ?? '';

$where_conditions = ['ia.almacen_id = ?'];
$params = [$almacen_id];
$param_types = 'i';

if (!empty($search)) {
    $where_conditions[] = "(p.nombre LIKE ? OR c.nombre LIKE ? OR p.sku LIKE ?)";
    $search_param = '%' . $search . '%';
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $param_types .= 'sss';
}

if ($filter_stock === 'critico') {
    $where_conditions[] = 'p.stock_actual <= p.stock_minimo';
} elseif ($filter_stock === 'bajo') {
    $where_conditions[] = 'p.stock_actual <= (p.stock_minimo * 1.5)';
} elseif ($filter_stock === 'sin_stock') {
    $where_conditions[] = 'p.stock_actual = 0';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

$productos_query = "
    SELECT 
        p.id, p.nombre, c.nombre as categoria, p.sku, p.precio,
        p.stock_actual, p.stock_minimo, p.stock_maximo,
        p.activo, p.fecha_actualizacion,
        ia.stock_actual as stock_almacen,
        CASE 
            WHEN p.stock_actual = 0 THEN 'sin_stock'
            WHEN p.stock_actual <= p.stock_minimo THEN 'critico'
            WHEN p.stock_actual <= (p.stock_minimo * 1.5) THEN 'bajo'
            ELSE 'ok'
        END as estado_stock
    FROM productos p
    LEFT JOIN categorias_productos c ON p.categoria_id = c.id
    INNER JOIN inventario_almacen ia ON p.id = ia.producto_id
    $where_clause
    ORDER BY 
        CASE 
            WHEN p.stock_actual = 0 THEN 1
            WHEN p.stock_actual <= p.stock_minimo THEN 2
            WHEN p.stock_actual <= (p.stock_minimo * 1.5) THEN 3
            ELSE 4
        END,
        p.nombre ASC
";

$productos_stmt = $conn->prepare($productos_query);
if (!empty($params)) {
    $productos_stmt->bind_param($param_types, ...$params);
}
$productos_stmt->execute();
$productos_result = $productos_stmt->get_result();
$productos = $productos_result->fetch_all(MYSQLI_ASSOC);

// Estadísticas del almacén
$stats_query = "
    SELECT 
        COUNT(DISTINCT p.id) as total_productos,
        COUNT(DISTINCT CASE WHEN p.activo = 1 THEN p.id END) as productos_activos,
        SUM(ia.stock_actual) as stock_total,
        AVG(ia.stock_actual) as stock_promedio,
        SUM(CASE WHEN ia.stock_actual = 0 THEN 1 ELSE 0 END) as sin_stock,
        SUM(CASE WHEN ia.stock_actual <= ia.stock_minimo THEN 1 ELSE 0 END) as stock_critico,
        SUM(CASE WHEN ia.stock_actual <= (ia.stock_minimo * 1.5) THEN 1 ELSE 0 END) as stock_bajo,
        SUM(ia.stock_actual * p.precio) as valor_inventario,
        MAX(ia.fecha_actualizacion) as ultima_actualizacion
    FROM productos p
    INNER JOIN inventario_almacen ia ON p.id = ia.producto_id
    WHERE ia.almacen_id = ?
";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param('i', $almacen_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏪 <?php echo htmlspecialchars($almacen['nombre']); ?> - Detalle</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏪</text></svg>">
    <link rel="stylesheet" href="../productos.css">
    <link rel="stylesheet" href="almacenes.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">🏪 <?php echo htmlspecialchars($almacen['nombre']); ?></h1>
                    <div class="breadcrumb">
                        <a href="../../index.php">🏠 Inicio</a>
                        <span>/</span>
                        <a href="../productos.php">📦 Inventario</a>
                        <span>/</span>
                        <a href="index.php">🏪 Almacenes</a>
                        <span>/</span>
                        <span>👁️ Detalle</span>
                    </div>
                </div>
                <div class="header-actions">
                    <span class="user-info">
                        👤 <?php echo htmlspecialchars($current_user['nombre']); ?>
                    </span>
                    <a href="index.php" class="btn btn-secondary">
                        ← Volver al Listado
                    </a>
                    <?php if (auth_can('inventario', 'actualizar')): ?>
                        <a href="editar.php?id=<?php echo $almacen['id']; ?>" class="btn btn-primary">
                            ✏️ Editar
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <!-- Información del almacén -->
        <div class="almacen-detail-card">
            <div class="almacen-detail-header">
                <div class="almacen-detail-info">
                    <h2><?php echo htmlspecialchars($almacen['nombre']); ?></h2>
                    <p class="almacen-descripcion"><?php echo htmlspecialchars($almacen['descripcion'] ?? ''); ?></p>
                    <div class="almacen-meta">
                        <span class="meta-item">
                            <strong>📍 Ubicación:</strong> <?php echo htmlspecialchars($almacen['ubicacion']); ?>
                        </span>
                        <?php if ($almacen['capacidad_maxima'] > 0): ?>
                            <span class="meta-item">
                                <strong>📦 Capacidad:</strong> <?php echo number_format($almacen['capacidad_maxima']); ?> m²
                            </span>
                        <?php endif; ?>
                        <span class="meta-item">
                            <strong>🔧 Estado:</strong> 
                            <span class="estado-badge <?php echo $almacen['activo'] ? 'activo' : 'inactivo'; ?>">
                                <?php echo $almacen['activo'] ? '✅ Activo' : '❌ Inactivo'; ?>
                            </span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estadísticas del almacén -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_productos']); ?></div>
                <div class="stat-label">Total Productos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['stock_total']); ?></div>
                <div class="stat-label">Stock Total</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($stats['valor_inventario']); ?></div>
                <div class="stat-label">Valor Inventario</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['stock_critico']); ?></div>
                <div class="stat-label">Stock Crítico</div>
            </div>
        </div>

        <!-- Filtros de productos -->
        <div class="filters-container">
            <form method="GET" class="filters-form">
                <input type="hidden" name="id" value="<?php echo $almacen['id']; ?>">
                <div class="filter-group">
                    <input type="text" 
                           name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="🔍 Buscar productos..." 
                           class="filter-input">
                </div>
                <div class="filter-group">
                    <select name="stock" class="filter-select">
                        <option value="">Todos los productos</option>
                        <option value="critico" <?php echo $filter_stock === 'critico' ? 'selected' : ''; ?>>🔴 Stock Crítico</option>
                        <option value="bajo" <?php echo $filter_stock === 'bajo' ? 'selected' : ''; ?>>🟡 Stock Bajo</option>
                        <option value="sin_stock" <?php echo $filter_stock === 'sin_stock' ? 'selected' : ''; ?>>⚫ Sin Stock</option>
                    </select>
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn btn-filter">🔍 Filtrar</button>
                    <a href="detalle.php?id=<?php echo $almacen['id']; ?>" class="btn btn-clear">🔄 Limpiar</a>
                </div>
            </form>
        </div>

        <!-- Tabla de productos -->
        <div class="products-section">
            <div class="section-header">
                <h3>📦 Productos en este Almacén</h3>
                <div class="section-actions">
                    <?php if (auth_can('inventario', 'crear')): ?>
                        <a href="../crear_producto.php?almacen=<?php echo urlencode($almacen['nombre']); ?>" class="btn btn-primary">
                            ➕ Agregar Producto
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th>SKU</th>
                            <th>Stock</th>
                            <th>Precio</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($productos)): ?>
                            <tr>
                                <td colspan="7" class="no-data">
                                    <?php if (!empty($search) || !empty($filter_stock)): ?>
                                        No se encontraron productos con los filtros aplicados
                                    <?php else: ?>
                                        Este almacén no tiene productos asociados
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($productos as $producto): ?>
                                <tr>
                                    <td>
                                        <div class="product-info">
                                            <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong>
                                            <small>ID: #<?php echo $producto['id']; ?></small>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($producto['categoria']); ?></td>
                                    <td>
                                        <?php if (!empty($producto['sku'])): ?>
                                            <code><?php echo htmlspecialchars($producto['sku']); ?></code>
                                        <?php else: ?>
                                            <span class="text-muted">Sin SKU</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="stock-info">
                                            <span class="stock-current <?php echo $producto['estado_stock']; ?>">
                                                <?php echo number_format($producto['stock_actual']); ?>
                                            </span>
                                            <small>/ <?php echo number_format($producto['stock_maximo']); ?></small>
                                        </div>
                                    </td>
                                    <td>$<?php echo number_format($producto['precio']); ?></td>
                                    <td class="text-center">
                                        <?php
                                        $estado_info = [
                                            'sin_stock' => ['🔴', 'Sin Stock', 'danger'],
                                            'critico' => ['🔴', 'Crítico', 'danger'],
                                            'bajo' => ['🟡', 'Bajo', 'warning'],
                                            'ok' => ['🟢', 'OK', 'success']
                                        ];
                                        $estado = $estado_info[$producto['estado_stock']];
                                        ?>
                                        <span class="badge badge-<?php echo $estado[2]; ?>">
                                            <?php echo $estado[0] . ' ' . $estado[1]; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="action-buttons">
                                            <?php if (auth_can('inventario', 'actualizar')): ?>
                                                <a href="../editar_producto.php?id=<?php echo $producto['id']; ?>" 
                                                   class="btn-action btn-edit" 
                                                   title="Editar producto">
                                                    ✏️
                                                </a>
                                            <?php endif; ?>
                                            <?php if (auth_can('inventario', 'leer')): ?>
                                                <a href="../ver_detalle_producto.php?id=<?php echo $producto['id']; ?>" 
                                                   class="btn-action btn-info" 
                                                   title="Ver detalle">
                                                    👁️
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>