<?php
/**
 * Sistema de Gestión de Productos - Listado Principal
 * Sequoia Speed - Módulo de Inventario
 * ACTUALIZADO: Integración con sistema unificado de almacenes
 */

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once '../config_secure.php';
require_once '../notifications/notification_helpers.php';
require_once '../php82_helpers.php';
require_once 'config_almacenes.php';

// Configurar conexión para AlmacenesConfig
AlmacenesConfig::setConnection($conn);

// Configuración de paginación
$limite = 20;
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina - 1) * $limite;

// Parámetros de búsqueda y filtros
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$categoria = isset($_GET['categoria']) ? trim($_GET['categoria']) : '';
$estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';
$stock_filter = isset($_GET['stock_filter']) ? trim($_GET['stock_filter']) : '';
$almacen_id = isset($_GET['almacen']) ? intval($_GET['almacen']) : 0;

// Obtener información del almacén seleccionado usando la nueva configuración
if ($almacen_id > 0) {
    $almacen_actual = AlmacenesConfig::getAlmacenPorId($almacen_id);
} else {
    $almacen_actual = AlmacenesConfig::getAlmacenPorDefecto();
}

// Si no se encuentra el almacén, usar el por defecto
if (!$almacen_actual) {
    $almacen_actual = AlmacenesConfig::getAlmacenPorDefecto();
}

// Obtener lista de almacenes para el selector
$almacenes = AlmacenesConfig::getAlmacenesPorPrioridad();

// Construir consulta con filtros para inventario por almacén
$where_conditions = ["ia.almacen_id = ?"];
$params = [$almacen_actual['id']];
$types = 'i';

if (!empty($buscar)) {
    $where_conditions[] = "(p.nombre LIKE ? OR p.descripcion LIKE ? OR p.categoria LIKE ?)";
    $search_term = "%$buscar%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'sss';
}

if (!empty($categoria)) {
    $where_conditions[] = "c.nombre = ?";
    $params[] = $categoria;
    $types .= 's';
}

if (!empty($estado)) {
    $where_conditions[] = "p.activo = ?";
    $params[] = $estado;
    $types .= 's';
}

if (!empty($stock_filter)) {
    switch ($stock_filter) {
        case 'bajo':
            $where_conditions[] = "ia.stock_actual <= ia.stock_minimo";
            break;
        case 'critico':
            $where_conditions[] = "ia.stock_actual < ia.stock_minimo";
            break;
        case 'alto':
            $where_conditions[] = "ia.stock_actual > ia.stock_minimo * 2";
            break;
    }
}

// Construir WHERE clause
$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Consulta principal con paginación usando el nuevo sistema
$query = "SELECT 
    p.id,
    p.nombre,
    p.descripcion,
    p.categoria,
    p.precio,
    p.sku,
    p.imagen,
    p.activo,
    p.fecha_creacion,
    p.fecha_actualizacion,
    ia.stock_actual,
    ia.stock_minimo,
    ia.stock_maximo,
    ia.ubicacion_fisica,
    a.id as almacen_id,
    a.nombre as almacen_nombre,
    a.icono as almacen_icono,
    CASE 
        WHEN ia.stock_actual = 0 THEN 'sin_stock'
        WHEN ia.stock_actual <= ia.stock_minimo THEN 'critico'
        WHEN ia.stock_actual <= (ia.stock_minimo * 1.5) THEN 'bajo'
        ELSE 'ok'
    END as nivel_stock,
    CASE 
        WHEN ia.stock_actual = 0 THEN '🔴'
        WHEN ia.stock_actual <= ia.stock_minimo THEN '🔴'
        WHEN ia.stock_actual <= (ia.stock_minimo * 1.5) THEN '🟡'
        ELSE '🟢'
    END as icono_stock
FROM productos p
INNER JOIN inventario_almacen ia ON p.id = ia.producto_id
INNER JOIN almacenes a ON ia.almacen_id = a.id
LEFT JOIN categorias_productos c ON p.categoria_id = c.id 
$where_clause 
ORDER BY fecha_creacion DESC 
LIMIT ? OFFSET ?";

$params[] = $limite;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$productos = $result->fetch_all(MYSQLI_ASSOC);

// Consulta para contar total de productos
$count_query = "SELECT COUNT(*) as total 
FROM productos p
INNER JOIN inventario_almacen ia ON p.id = ia.producto_id
INNER JOIN almacenes a ON ia.almacen_id = a.id
LEFT JOIN categorias_productos c ON p.categoria_id = c.id 
$where_clause";
$count_stmt = $conn->prepare($count_query);
if (!empty($where_conditions)) {
    $count_params = array_slice($params, 0, -2); // Remover límite y offset
    $count_types = substr($types, 0, -2);
    if (!empty($count_params)) {
        $count_stmt->bind_param($count_types, ...$count_params);
    }
}
$count_stmt->execute();
$total_productos = $count_stmt->get_result()->fetch_assoc()['total'];
$total_paginas = ceil($total_productos / $limite);

// Obtener categorías para filtro
$categorias_query = "SELECT c.id, c.nombre, c.icono 
                     FROM categorias_productos c
                     WHERE c.activa = 1
                     ORDER BY c.orden ASC, c.nombre ASC";
$categorias_result = $conn->query($categorias_query);
$categorias = $categorias_result->fetch_all(MYSQLI_ASSOC);

// Función para formatear precio
function formatear_precio($precio) {
    return '$' . number_format($precio, 0, ',', '.');
}

// Función para generar badge de stock
function generar_badge_stock($stock_actual, $stock_minimo, $nivel_stock, $icono_stock) {
    $clases = [
        'bajo' => 'stock-bajo',
        'medio' => 'stock-medio',
        'alto' => 'stock-alto'
    ];
    
    $clase = $clases[$nivel_stock] ?? 'stock-medio';
    
    return "<span class='badge-stock $clase' title='Stock: $stock_actual / Mínimo: $stock_minimo'>$icono_stock $stock_actual</span>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📦 Gestión de Productos - Sequoia Speed</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📦</text></svg>">
    <link rel="stylesheet" href="productos.css">
    <link rel="stylesheet" href="../notifications/notifications.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">📦 Gestión de Productos</h1>
                    <div class="breadcrumb">
                        <a href="../listar_pedidos.php">🏠 Inicio</a>
                        <span>/</span>
                        <span>📦 Inventario</span>
                        <span>/</span>
                        <span class="almacen-actual">🏪 <?php echo htmlspecialchars($almacen_actual['nombre']); ?></span>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="crear_producto.php?almacen_id=<?php echo $almacen_actual['id']; ?>" class="btn btn-primary">
                        ➕ Nuevo Producto
                    </a>
                    <button onclick="exportarExcel()" class="btn btn-secondary">
                        📊 Exportar Excel
                    </button>
                </div>
            </div>
        </header>

        <!-- Selector de Almacén -->
        <div class="almacen-selector-section">
            <div class="almacen-selector-header">
                <h3>🏪 Seleccionar Almacén</h3>
                <p>Selecciona un almacén para ver su inventario</p>
            </div>
            <div class="almacen-selector-grid">
                <?php foreach ($almacenes as $almacen): ?>
                    <a href="?almacen=<?php echo $almacen['id']; ?>" 
                       class="almacen-card <?php echo $almacen['id'] == $almacen_actual['id'] ? 'active' : ''; ?>">
                        <div class="almacen-icon">
                            <?php echo AlmacenesConfig::getIconoAlmacen($almacen); ?>
                        </div>
                        <div class="almacen-info">
                            <h4><?php echo htmlspecialchars($almacen['nombre']); ?></h4>
                            <p><?php echo htmlspecialchars($almacen['encargado'] ?? $almacen['descripcion'] ?? ''); ?></p>
                            <?php if ($almacen['id'] == $almacen_actual['id']): ?>
                                <span class="almacen-badge">✓ Seleccionado</span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-section">
            <form method="GET" class="filters-form">
                <input type="hidden" name="almacen" value="<?php echo $almacen_actual['id']; ?>">
                <div class="filters-row">
                    <div class="filter-group">
                        <input type="text" 
                               name="buscar" 
                               value="<?php echo htmlspecialchars($buscar); ?>"
                               placeholder="🔍 Buscar productos..."
                               class="filter-input search-input">
                    </div>
                    
                    <div class="filter-group">
                        <select name="categoria" class="filter-select">
                            <option value="">🏷️ Todas las categorías</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['nombre']); ?>" 
                                        <?php echo $categoria === $cat['nombre'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['icono'] . ' ' . $cat['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select name="estado" class="filter-select">
                            <option value="">📊 Todos los estados</option>
                            <option value="1" <?php echo $estado === '1' ? 'selected' : ''; ?>>✅ Activos</option>
                            <option value="0" <?php echo $estado === '0' ? 'selected' : ''; ?>>❌ Inactivos</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select name="stock_filter" class="filter-select">
                            <option value="">📦 Todos los stocks</option>
                            <option value="bajo" <?php echo $stock_filter === 'bajo' ? 'selected' : ''; ?>>🔴 Stock bajo</option>
                            <option value="critico" <?php echo $stock_filter === 'critico' ? 'selected' : ''; ?>>⚠️ Stock crítico</option>
                            <option value="alto" <?php echo $stock_filter === 'alto' ? 'selected' : ''; ?>>🟢 Stock alto</option>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-filter">🔍 Filtrar</button>
                        <a href="productos.php" class="btn btn-clear">🗑️ Limpiar</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Estadísticas -->
        <div class="stats-section">
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($total_productos); ?></div>
                    <div class="stat-label">Total Productos</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🔴</div>
                <div class="stat-content">
                    <div class="stat-number">
                        <?php 
                        $stock_bajo = $conn->query("SELECT COUNT(*) as total FROM inventario_almacen ia INNER JOIN productos p ON ia.producto_id = p.id WHERE ia.stock_actual <= ia.stock_minimo AND p.activo = '1'")->fetch_assoc()['total'];
                        echo $stock_bajo;
                        ?>
                    </div>
                    <div class="stat-label">Stock Bajo</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <div class="stat-number">
                        <?php 
                        $activos = $conn->query("SELECT COUNT(*) as total FROM productos WHERE activo = '1'")->fetch_assoc()['total'];
                        echo $activos;
                        ?>
                    </div>
                    <div class="stat-label">Activos</div>
                </div>
            </div>
        </div>

        <!-- Tabla de productos -->
        <div class="table-section">
            <div class="table-container">
                <table class="productos-table">
                    <thead>
                        <tr>
                            <th>📷 Imagen</th>
                            <th>📦 Producto</th>
                            <th>🏷️ Categoría</th>
                            <th>💰 Precio</th>
                            <th>📊 Stock</th>
                            <th>🏪 Almacén</th>
                            <th>📅 Fecha</th>
                            <th>⚙️ Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($productos)): ?>
                            <tr>
                                <td colspan="8" class="empty-state">
                                    <div class="empty-icon">📦</div>
                                    <div class="empty-title">No hay productos</div>
                                    <div class="empty-subtitle">
                                        <?php if (!empty($buscar)): ?>
                                            No se encontraron productos con el término "<?php echo htmlspecialchars($buscar); ?>"
                                        <?php else: ?>
                                            Comienza agregando tu primer producto
                                        <?php endif; ?>
                                    </div>
                                    <a href="crear_producto.php" class="btn btn-primary">➕ Crear Producto</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($productos as $producto): ?>
                                <tr class="producto-row <?php echo $producto['activo'] == '0' ? 'inactive' : ''; ?>">
                                    <td class="imagen-cell">
                                        <?php if (!empty($producto['imagen'])): ?>
                                            <img src="uploads/productos/<?php echo htmlspecialchars($producto['imagen']); ?>" 
                                                 alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                                 class="producto-imagen">
                                        <?php else: ?>
                                            <div class="imagen-placeholder">📦</div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="producto-info">
                                        <div class="producto-nombre"><?php echo htmlspecialchars($producto['nombre']); ?></div>
                                        <div class="producto-descripcion"><?php echo htmlspecialchars($producto['descripcion']); ?></div>
                                        <?php if ($producto['activo'] == '0'): ?>
                                            <span class="badge-inactive">❌ Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="categoria-cell">
                                        <span class="badge-categoria"><?php echo htmlspecialchars($producto['categoria']); ?></span>
                                    </td>
                                    
                                    <td class="precio-cell">
                                        <span class="precio"><?php echo formatear_precio($producto['precio']); ?></span>
                                    </td>
                                    
                                    <td class="stock-cell">
                                        <?php echo generar_badge_stock($producto['stock_actual'], $producto['stock_minimo'], $producto['nivel_stock'], $producto['icono_stock']); ?>
                                        <div class="stock-info">
                                            <small>Min: <?php echo $producto['stock_minimo']; ?> | Max: <?php echo $producto['stock_maximo']; ?></small>
                                        </div>
                                    </td>
                                    
                                    <td class="almacen-cell">
                                        <span class="badge-almacen">🏪 <?php echo htmlspecialchars($producto['almacen_nombre']); ?></span>
                                    </td>
                                    
                                    <td class="fecha-cell">
                                        <span class="fecha"><?php echo date('d/m/Y', strtotime($producto['fecha_creacion'])); ?></span>
                                    </td>
                                    
                                    <td class="acciones-cell">
                                        <div class="acciones-group">
                                            <a href="editar_producto.php?id=<?php echo $producto['id']; ?>" 
                                               class="btn-accion btn-editar" 
                                               title="Editar producto">
                                                ✏️
                                            </a>
                                            
                                            <button onclick="toggleActivo(<?php echo $producto['id']; ?>, <?php echo $producto['activo']; ?>)" 
                                                    class="btn-accion <?php echo $producto['activo'] == '1' ? 'btn-desactivar' : 'btn-activar'; ?>" 
                                                    title="<?php echo $producto['activo'] == '1' ? 'Desactivar' : 'Activar'; ?> producto">
                                                <?php echo $producto['activo'] == '1' ? '❌' : '✅'; ?>
                                            </button>
                                            
                                            <button onclick="verDetalles(<?php echo $producto['id']; ?>)" 
                                                    class="btn-accion btn-ver" 
                                                    title="Ver detalles">
                                                👁️
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Paginación -->
        <?php if ($total_paginas > 1): ?>
            <div class="pagination-section">
                <div class="pagination-info">
                    Mostrando <?php echo count($productos); ?> de <?php echo number_format($total_productos); ?> productos
                </div>
                
                <div class="pagination-controls">
                    <?php if ($pagina > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])); ?>" class="btn-pagination">
                            ⬅️ Anterior
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $pagina - 2); $i <= min($total_paginas, $pagina + 2); $i++): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>" 
                           class="btn-pagination <?php echo $i == $pagina ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($pagina < $total_paginas): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])); ?>" class="btn-pagination">
                            Siguiente ➡️
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal de detalles -->
    <div id="modalDetalles" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>📦 Detalles del Producto</h3>
                <button onclick="cerrarModal()" class="btn-close">×</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Contenido dinámico -->
            </div>
        </div>
    </div>

    <!-- Sistema de notificaciones -->
    <div id="notification-container"></div>

    <script src="productos.js"></script>
</body>
</html>