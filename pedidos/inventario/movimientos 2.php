<?php
/**
 * Gestión de Movimientos de Inventario
 * Sequoia Speed - Módulo de Inventario
 */

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once '../config_secure.php';
require_once '../notifications/notification_helpers.php';
require_once '../php82_helpers.php';

// Configuración de paginación
$limite = 20;
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina - 1) * $limite;

// Parámetros de filtros
$almacen_seleccionado = isset($_GET['almacen']) ? trim($_GET['almacen']) : 'TIENDA_BOG';
$tipo_movimiento = isset($_GET['tipo_movimiento']) ? trim($_GET['tipo_movimiento']) : '';
$fecha_desde = isset($_GET['fecha_desde']) ? trim($_GET['fecha_desde']) : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? trim($_GET['fecha_hasta']) : '';
$producto_buscar = isset($_GET['producto_buscar']) ? trim($_GET['producto_buscar']) : '';

// Obtener información del almacén seleccionado
$query_almacen = "SELECT * FROM almacenes WHERE codigo = ? AND activo = 1 LIMIT 1";
$stmt_almacen = $conn->prepare($query_almacen);
$stmt_almacen->bind_param('s', $almacen_seleccionado);
$stmt_almacen->execute();
$almacen_actual = $stmt_almacen->get_result()->fetch_assoc();

// Si no se encuentra el almacén, usar Tienda Bogotá por defecto
if (!$almacen_actual) {
    $almacen_seleccionado = 'TIENDA_BOG';
    $stmt_almacen->bind_param('s', $almacen_seleccionado);
    $stmt_almacen->execute();
    $almacen_actual = $stmt_almacen->get_result()->fetch_assoc();
}

// Obtener lista de almacenes para el selector
$query_almacenes = "SELECT * FROM almacenes WHERE activo = 1 ORDER BY 
    CASE codigo 
        WHEN 'TIENDA_BOG' THEN 1 
        WHEN 'TIENDA_MED' THEN 2 
        WHEN 'FABRICA' THEN 3 
        ELSE 4 
    END, nombre";
$almacenes = $conn->query($query_almacenes)->fetch_all(MYSQLI_ASSOC);

// Obtener tipos de movimiento
$query_tipos = "SELECT * FROM tipos_movimiento WHERE activo = 1 ORDER BY nombre";
$tipos_movimiento = $conn->query($query_tipos)->fetch_all(MYSQLI_ASSOC);

// Construir consulta con filtros
$where_conditions = ["m.almacen_id = ?"];
$params = [$almacen_actual['id']];
$types = 'i';

if (!empty($tipo_movimiento)) {
    $where_conditions[] = "m.tipo_movimiento = ?";
    $params[] = $tipo_movimiento;
    $types .= 's';
}

if (!empty($fecha_desde)) {
    $where_conditions[] = "DATE(m.fecha_movimiento) >= ?";
    $params[] = $fecha_desde;
    $types .= 's';
}

if (!empty($fecha_hasta)) {
    $where_conditions[] = "DATE(m.fecha_movimiento) <= ?";
    $params[] = $fecha_hasta;
    $types .= 's';
}

if (!empty($producto_buscar)) {
    $where_conditions[] = "(p.nombre LIKE ? OR p.sku LIKE ?)";
    $search_term = "%$producto_buscar%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

// Construir WHERE clause
$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Consulta principal con paginación
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
$where_clause
ORDER BY m.fecha_movimiento DESC
LIMIT ? OFFSET ?";

$params[] = $limite;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$movimientos = $result->fetch_all(MYSQLI_ASSOC);

// Consulta para contar total de movimientos
$count_query = "SELECT COUNT(*) as total 
FROM movimientos_inventario m
INNER JOIN productos p ON m.producto_id = p.id
INNER JOIN almacenes a ON m.almacen_id = a.id
LEFT JOIN almacenes ad ON m.almacen_destino_id = ad.id
$where_clause";

$count_stmt = $conn->prepare($count_query);
$count_params = array_slice($params, 0, -2); // Remover límite y offset
$count_types = substr($types, 0, -2);
if (!empty($count_params)) {
    $count_stmt->bind_param($count_types, ...$count_params);
}
$count_stmt->execute();
$total_movimientos = $count_stmt->get_result()->fetch_assoc()['total'];
$total_paginas = ceil($total_movimientos / $limite);

// Obtener estadísticas del período
$stats_query = "SELECT 
    COUNT(*) as total_movimientos,
    SUM(CASE WHEN tipo_movimiento IN ('entrada', 'transferencia_entrada') THEN cantidad ELSE 0 END) as total_entradas,
    SUM(CASE WHEN tipo_movimiento IN ('salida', 'transferencia_salida') THEN cantidad ELSE 0 END) as total_salidas,
    SUM(CASE WHEN tipo_movimiento = 'ajuste' THEN 
        CASE WHEN cantidad > cantidad_anterior THEN cantidad - cantidad_anterior ELSE 0 END 
    ELSE 0 END) as total_ajustes_positivos,
    SUM(CASE WHEN tipo_movimiento = 'ajuste' THEN 
        CASE WHEN cantidad < cantidad_anterior THEN cantidad_anterior - cantidad ELSE 0 END 
    ELSE 0 END) as total_ajustes_negativos
FROM movimientos_inventario m
INNER JOIN productos p ON m.producto_id = p.id
INNER JOIN almacenes a ON m.almacen_id = a.id
$where_clause";

$stats_stmt = $conn->prepare($stats_query);
if (!empty($count_params)) {
    $stats_stmt->bind_param($count_types, ...$count_params);
}
$stats_stmt->execute();
$estadisticas = $stats_stmt->get_result()->fetch_assoc();

// Función para formatear tipo de movimiento
function formatear_tipo_movimiento($tipo) {
    $tipos = [
        'entrada' => ['icono' => '📥', 'color' => 'success', 'texto' => 'Entrada'],
        'salida' => ['icono' => '📤', 'color' => 'danger', 'texto' => 'Salida'],
        'ajuste' => ['icono' => '⚖️', 'color' => 'warning', 'texto' => 'Ajuste'],
        'transferencia_salida' => ['icono' => '🔄', 'color' => 'info', 'texto' => 'Transferencia Salida'],
        'transferencia_entrada' => ['icono' => '🔄', 'color' => 'info', 'texto' => 'Transferencia Entrada']
    ];
    
    return $tipos[$tipo] ?? ['icono' => '❓', 'color' => 'secondary', 'texto' => 'Desconocido'];
}

// Función para formatear fecha
function formatear_fecha($fecha) {
    return date('d/m/Y H:i', strtotime($fecha));
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📊 Movimientos de Inventario - Sequoia Speed</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📊</text></svg>">
    <link rel="stylesheet" href="productos.css">
    <link rel="stylesheet" href="../notifications/notifications.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">📊 Movimientos de Inventario</h1>
                    <div class="breadcrumb">
                        <a href="../listar_pedidos.php">🏠 Inicio</a>
                        <span>/</span>
                        <a href="productos.php">📦 Inventario</a>
                        <span>/</span>
                        <span>📊 Movimientos</span>
                        <span>/</span>
                        <span class="almacen-actual">🏪 <?php echo htmlspecialchars($almacen_actual['nombre']); ?></span>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="registrar_movimiento.php?almacen=<?php echo $almacen_seleccionado; ?>" class="btn btn-primary">
                        ➕ Registrar Movimiento
                    </a>
                    <a href="productos.php?almacen=<?php echo $almacen_seleccionado; ?>" class="btn btn-secondary">
                        📦 Ver Productos
                    </a>
                </div>
            </div>
        </header>

        <!-- Selector de Almacén -->
        <div class="almacen-selector-section">
            <div class="almacen-selector-header">
                <h3>🏪 Seleccionar Almacén</h3>
                <p>Selecciona un almacén para ver sus movimientos</p>
            </div>
            <div class="almacen-selector-grid">
                <?php foreach ($almacenes as $almacen): ?>
                    <a href="?almacen=<?php echo $almacen['codigo']; ?>" 
                       class="almacen-card <?php echo $almacen['codigo'] === $almacen_seleccionado ? 'active' : ''; ?>">
                        <div class="almacen-icon">
                            <?php 
                            $iconos = [
                                'FABRICA' => '🏭',
                                'TIENDA_BOG' => '🏬',
                                'TIENDA_MED' => '🏪',
                                'BODEGA_1' => '📦',
                                'BODEGA_2' => '📦',
                                'BODEGA_3' => '📦'
                            ];
                            echo $iconos[$almacen['codigo']] ?? '🏪';
                            ?>
                        </div>
                        <div class="almacen-info">
                            <h4><?php echo htmlspecialchars($almacen['nombre']); ?></h4>
                            <p><?php echo htmlspecialchars($almacen['encargado']); ?></p>
                            <?php if ($almacen['codigo'] === $almacen_seleccionado): ?>
                                <span class="almacen-badge">✓ Seleccionado</span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="stats-section">
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($estadisticas['total_movimientos']); ?></div>
                    <div class="stat-label">Total Movimientos</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📥</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($estadisticas['total_entradas']); ?></div>
                    <div class="stat-label">Total Entradas</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📤</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($estadisticas['total_salidas']); ?></div>
                    <div class="stat-label">Total Salidas</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⚖️</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($estadisticas['total_ajustes_positivos'] + $estadisticas['total_ajustes_negativos']); ?></div>
                    <div class="stat-label">Total Ajustes</div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-section">
            <form method="GET" class="filters-form">
                <input type="hidden" name="almacen" value="<?php echo $almacen_seleccionado; ?>">
                <div class="filters-row">
                    <div class="filter-group">
                        <input type="text" 
                               name="producto_buscar" 
                               value="<?php echo htmlspecialchars($producto_buscar); ?>"
                               placeholder="🔍 Buscar productos..."
                               class="filter-input search-input">
                    </div>
                    
                    <div class="filter-group">
                        <select name="tipo_movimiento" class="filter-select">
                            <option value="">📋 Todos los tipos</option>
                            <?php foreach ($tipos_movimiento as $tipo): ?>
                                <option value="<?php echo $tipo['codigo']; ?>" 
                                        <?php echo $tipo_movimiento === $tipo['codigo'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tipo['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <input type="date" 
                               name="fecha_desde" 
                               value="<?php echo htmlspecialchars($fecha_desde); ?>"
                               placeholder="Fecha desde"
                               class="filter-input">
                    </div>
                    
                    <div class="filter-group">
                        <input type="date" 
                               name="fecha_hasta" 
                               value="<?php echo htmlspecialchars($fecha_hasta); ?>"
                               placeholder="Fecha hasta"
                               class="filter-input">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-filter">
                            🔍 Filtrar
                        </button>
                        <a href="?almacen=<?php echo $almacen_seleccionado; ?>" class="btn btn-clear">
                            🗑️ Limpiar
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tabla de movimientos -->
        <div class="table-section">
            <div class="table-container">
                <?php if (empty($movimientos)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📊</div>
                        <div class="empty-title">No se encontraron movimientos</div>
                        <div class="empty-subtitle">
                            No hay movimientos registrados para los filtros seleccionados
                        </div>
                        <a href="registrar_movimiento.php?almacen=<?php echo $almacen_seleccionado; ?>" class="btn btn-primary">
                            ➕ Registrar Primer Movimiento
                        </a>
                    </div>
                <?php else: ?>
                    <table class="productos-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Producto</th>
                                <th>Tipo</th>
                                <th>Cantidad</th>
                                <th>Stock Anterior</th>
                                <th>Stock Nuevo</th>
                                <th>Motivo</th>
                                <th>Responsable</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movimientos as $movimiento): ?>
                                <?php $tipo_info = formatear_tipo_movimiento($movimiento['tipo_movimiento']); ?>
                                <tr>
                                    <td class="fecha-cell">
                                        <div class="fecha"><?php echo formatear_fecha($movimiento['fecha_movimiento']); ?></div>
                                    </td>
                                    <td class="producto-info">
                                        <div class="producto-nombre"><?php echo htmlspecialchars($movimiento['producto_nombre']); ?></div>
                                        <?php if (!empty($movimiento['producto_sku'])): ?>
                                            <div class="producto-sku">SKU: <?php echo htmlspecialchars($movimiento['producto_sku']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="tipo-cell">
                                        <span class="badge-tipo badge-<?php echo $tipo_info['color']; ?>">
                                            <?php echo $tipo_info['icono']; ?> <?php echo $tipo_info['texto']; ?>
                                        </span>
                                        <?php if (!empty($movimiento['almacen_destino_nombre'])): ?>
                                            <div class="destino-info">
                                                → <?php echo htmlspecialchars($movimiento['almacen_destino_nombre']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="cantidad-cell">
                                        <span class="cantidad-valor <?php echo in_array($movimiento['tipo_movimiento'], ['entrada', 'transferencia_entrada']) ? 'positivo' : 'negativo'; ?>">
                                            <?php echo in_array($movimiento['tipo_movimiento'], ['entrada', 'transferencia_entrada']) ? '+' : '-'; ?><?php echo number_format($movimiento['cantidad']); ?>
                                        </span>
                                    </td>
                                    <td class="stock-cell">
                                        <span class="stock-valor"><?php echo number_format($movimiento['cantidad_anterior']); ?></span>
                                    </td>
                                    <td class="stock-cell">
                                        <span class="stock-valor"><?php echo number_format($movimiento['cantidad_nueva']); ?></span>
                                    </td>
                                    <td class="motivo-cell">
                                        <div class="motivo-texto"><?php echo htmlspecialchars($movimiento['motivo']); ?></div>
                                        <?php if (!empty($movimiento['documento_referencia'])): ?>
                                            <div class="documento-ref">Ref: <?php echo htmlspecialchars($movimiento['documento_referencia']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="responsable-cell">
                                        <div class="responsable-nombre"><?php echo htmlspecialchars($movimiento['usuario_responsable']); ?></div>
                                    </td>
                                    <td class="acciones-cell">
                                        <div class="acciones-group">
                                            <button onclick="verDetalleMovimiento(<?php echo $movimiento['id']; ?>)" 
                                                    class="btn-accion btn-ver" 
                                                    title="Ver detalles">
                                                👁️
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Paginación -->
            <?php if ($total_paginas > 1): ?>
                <div class="pagination-section">
                    <div class="pagination-info">
                        Mostrando <?php echo ($offset + 1); ?> a <?php echo min($offset + $limite, $total_movimientos); ?> 
                        de <?php echo number_format($total_movimientos); ?> movimientos
                    </div>
                    <div class="pagination-controls">
                        <?php if ($pagina > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])); ?>" class="btn-pagination">
                                ← Anterior
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $pagina - 2); $i <= min($total_paginas, $pagina + 2); $i++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>" 
                               class="btn-pagination <?php echo $i === $pagina ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($pagina < $total_paginas): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])); ?>" class="btn-pagination">
                                Siguiente →
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para detalles del movimiento -->
    <div id="modalDetalleMovimiento" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>📊 Detalle del Movimiento</h3>
                <button onclick="cerrarModalDetalle()" class="btn-close">×</button>
            </div>
            <div class="modal-body" id="detalleMovimientoBody">
                <!-- Contenido cargado dinámicamente -->
            </div>
        </div>
    </div>

    <!-- Sistema de notificaciones -->
    <div id="notification-container"></div>

    <script src="productos.js"></script>
    <script>
        // Función para ver detalle del movimiento
        function verDetalleMovimiento(id) {
            fetch(`obtener_movimiento.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarModalDetalleMovimiento(data.movimiento);
                } else {
                    mostrarNotificacion(data.error || 'Error al cargar el detalle', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarNotificacion('Error de conexión', 'error');
            });
        }

        // Mostrar modal con detalles del movimiento
        function mostrarModalDetalleMovimiento(movimiento) {
            const modal = document.getElementById('modalDetalleMovimiento');
            const modalBody = document.getElementById('detalleMovimientoBody');
            
            modalBody.innerHTML = `
                <div class="movimiento-detalle">
                    <div class="detalle-grid">
                        <div class="detalle-item">
                            <strong>📅 Fecha:</strong>
                            <span>${new Date(movimiento.fecha_movimiento).toLocaleString('es-CO')}</span>
                        </div>
                        <div class="detalle-item">
                            <strong>📦 Producto:</strong>
                            <span>${movimiento.producto_nombre}</span>
                        </div>
                        <div class="detalle-item">
                            <strong>🔄 Tipo:</strong>
                            <span>${movimiento.tipo_movimiento}</span>
                        </div>
                        <div class="detalle-item">
                            <strong>📊 Cantidad:</strong>
                            <span>${movimiento.cantidad}</span>
                        </div>
                        <div class="detalle-item">
                            <strong>📈 Stock Anterior:</strong>
                            <span>${movimiento.cantidad_anterior}</span>
                        </div>
                        <div class="detalle-item">
                            <strong>📈 Stock Nuevo:</strong>
                            <span>${movimiento.cantidad_nueva}</span>
                        </div>
                        <div class="detalle-item">
                            <strong>🏪 Almacén:</strong>
                            <span>${movimiento.almacen_nombre}</span>
                        </div>
                        <div class="detalle-item">
                            <strong>💰 Costo Unitario:</strong>
                            <span>$${parseFloat(movimiento.costo_unitario).toLocaleString('es-CO')}</span>
                        </div>
                        <div class="detalle-item">
                            <strong>📝 Motivo:</strong>
                            <span>${movimiento.motivo || 'Sin especificar'}</span>
                        </div>
                        <div class="detalle-item">
                            <strong>📋 Documento:</strong>
                            <span>${movimiento.documento_referencia || 'Sin documento'}</span>
                        </div>
                        <div class="detalle-item">
                            <strong>👤 Responsable:</strong>
                            <span>${movimiento.usuario_responsable}</span>
                        </div>
                        ${movimiento.observaciones ? `
                            <div class="detalle-item detalle-observaciones">
                                <strong>📝 Observaciones:</strong>
                                <span>${movimiento.observaciones}</span>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
            
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        // Cerrar modal de detalle
        function cerrarModalDetalle() {
            const modal = document.getElementById('modalDetalleMovimiento');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Cerrar modal al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                cerrarModalDetalle();
            }
        });

        // Cerrar modal con Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarModalDetalle();
            }
        });
    </script>
</body>
</html>