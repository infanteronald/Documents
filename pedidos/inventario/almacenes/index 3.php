<?php
/**
 * Listado Principal de Almacenes
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

// Obtener filtros de búsqueda
$search = $_GET['search'] ?? '';
$filter_activo = $_GET['activo'] ?? '';

// Construir consulta base
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "(a.nombre LIKE ? OR a.descripcion LIKE ? OR a.ubicacion LIKE ?)";
    $search_param = '%' . $search . '%';
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $param_types .= 'sss';
}

if ($filter_activo !== '') {
    $where_conditions[] = "a.activo = ?";
    $params[] = $filter_activo;
    $param_types .= 'i';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Consulta principal con JOINs
$query = "
    SELECT 
        a.id, a.nombre as almacen, a.descripcion, a.ubicacion, a.capacidad_maxima, a.activo,
        COUNT(DISTINCT ia.producto_id) as total_productos,
        SUM(ia.stock_actual) as stock_total,
        SUM(CASE WHEN ia.stock_actual <= ia.stock_minimo THEN 1 ELSE 0 END) as productos_criticos,
        SUM(CASE WHEN ia.stock_actual = 0 THEN 1 ELSE 0 END) as productos_sin_stock,
        AVG(p.precio) as precio_promedio,
        MAX(ia.fecha_actualizacion) as ultima_actualizacion
    FROM almacenes a
    LEFT JOIN inventario_almacen ia ON a.id = ia.almacen_id
    LEFT JOIN productos p ON ia.producto_id = p.id AND p.activo = 1
    $where_clause
    GROUP BY a.id, a.nombre, a.descripcion, a.ubicacion, a.capacidad_maxima, a.activo
    ORDER BY a.activo DESC, a.nombre ASC
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$almacenes = $result->fetch_all(MYSQLI_ASSOC);

// Estadísticas generales
$stats_query = "
    SELECT 
        COUNT(a.id) as total_almacenes,
        SUM(CASE WHEN a.activo = 1 THEN 1 ELSE 0 END) as almacenes_activos,
        SUM(vap.total_productos) as total_productos,
        SUM(vap.stock_total) as stock_total,
        SUM(vap.productos_criticos) as productos_criticos
    FROM almacenes a
    LEFT JOIN vista_almacenes_productos vap ON a.id = vap.id
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Mensajes de sesión
$mensaje_exito = $_SESSION['mensaje_exito'] ?? '';
$mensaje_error = $_SESSION['mensaje_error'] ?? '';
unset($_SESSION['mensaje_exito'], $_SESSION['mensaje_error']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏪 Gestión de Almacenes - Sequoia Speed</title>
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
                    <h1 class="page-title">🏪 Gestión de Almacenes</h1>
                    <div class="breadcrumb">
                        <a href="../../index.php">🏠 Inicio</a>
                        <span>/</span>
                        <a href="../productos.php">📦 Inventario</a>
                        <span>/</span>
                        <span>🏪 Almacenes</span>
                    </div>
                </div>
                <div class="header-actions">
                    <span class="user-info">
                        👤 <?php echo htmlspecialchars($current_user['nombre']); ?>
                    </span>
                    <?php if (auth_can('inventario', 'crear')): ?>
                        <a href="crear.php" class="btn btn-primary">
                            ➕ Nuevo Almacén
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <!-- Mensajes -->
        <?php if (!empty($mensaje_exito)): ?>
            <div class="mensaje mensaje-exito">
                <div class="mensaje-contenido">
                    <span class="mensaje-icono">✅</span>
                    <span><?php echo htmlspecialchars($mensaje_exito); ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="mensaje-cerrar">×</button>
            </div>
        <?php endif; ?>

        <?php if (!empty($mensaje_error)): ?>
            <div class="mensaje mensaje-error">
                <div class="mensaje-contenido">
                    <span class="mensaje-icono">❌</span>
                    <span><?php echo htmlspecialchars($mensaje_error); ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="mensaje-cerrar">×</button>
            </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['almacenes_activos']); ?></div>
                <div class="stat-label">Almacenes Activos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_productos']); ?></div>
                <div class="stat-label">Total Productos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['stock_total']); ?></div>
                <div class="stat-label">Stock Total</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['productos_criticos']); ?></div>
                <div class="stat-label">Productos Críticos</div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-container">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <input type="text" 
                           name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="🔍 Buscar por nombre, descripción o ubicación..." 
                           class="filter-input">
                </div>
                <div class="filter-group">
                    <select name="activo" class="filter-select">
                        <option value="">Todos los estados</option>
                        <option value="1" <?php echo $filter_activo === '1' ? 'selected' : ''; ?>>✅ Solo Activos</option>
                        <option value="0" <?php echo $filter_activo === '0' ? 'selected' : ''; ?>>❌ Solo Inactivos</option>
                    </select>
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn btn-filter">🔍 Buscar</button>
                    <a href="index.php" class="btn btn-clear">🔄 Limpiar</a>
                </div>
            </form>
        </div>

        <!-- Tabla de almacenes -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Almacén</th>
                        <th>Ubicación</th>
                        <th>Capacidad</th>
                        <th>Productos</th>
                        <th>Stock Total</th>
                        <th>Alertas</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($almacenes)): ?>
                        <tr>
                            <td colspan="8" class="no-data">
                                No se encontraron almacenes
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($almacenes as $almacen): ?>
                            <tr>
                                <td>
                                    <div class="almacen-info">
                                        <strong><?php echo htmlspecialchars($almacen['almacen']); ?></strong>
                                        <?php if (!empty($almacen['descripcion'])): ?>
                                            <small><?php echo htmlspecialchars($almacen['descripcion']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($almacen['ubicacion']); ?></td>
                                <td>
                                    <?php if ($almacen['capacidad_maxima'] > 0): ?>
                                        <?php echo number_format($almacen['capacidad_maxima']); ?> m²
                                    <?php else: ?>
                                        <span class="text-muted">No definida</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-info">
                                        <?php echo number_format($almacen['total_productos']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-secondary">
                                        <?php echo number_format($almacen['stock_total']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($almacen['productos_criticos'] > 0): ?>
                                        <span class="badge badge-danger">
                                            🔴 <?php echo $almacen['productos_criticos']; ?>
                                        </span>
                                    <?php elseif ($almacen['productos_sin_stock'] > 0): ?>
                                        <span class="badge badge-warning">
                                            🟡 <?php echo $almacen['productos_sin_stock']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-success">🟢 OK</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?php echo $almacen['activo'] ? 'badge-success' : 'badge-secondary'; ?>">
                                        <?php echo $almacen['activo'] ? '✅ Activo' : '❌ Inactivo'; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="action-buttons">
                                        <a href="detalle.php?id=<?php echo $almacen['id']; ?>" 
                                           class="btn-action btn-info" 
                                           title="Ver detalles">
                                            👁️
                                        </a>
                                        
                                        <?php if (auth_can('inventario', 'actualizar')): ?>
                                            <a href="editar.php?id=<?php echo $almacen['id']; ?>" 
                                               class="btn-action btn-edit" 
                                               title="Editar">
                                                ✏️
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if (auth_can('inventario', 'eliminar') && $almacen['total_productos'] == 0): ?>
                                            <button onclick="confirmarEliminacion(<?php echo $almacen['id']; ?>, <?php echo json_encode($almacen['almacen']); ?>)" 
                                                    class="btn-action btn-delete" 
                                                    title="Eliminar">
                                                🗑️
                                            </button>
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

    <!-- Modal de confirmación -->
    <div id="modalConfirmacion" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitulo" class="modal-title">🗑️ Confirmar Eliminación</h3>
                <button onclick="cerrarModal()" class="modal-close">×</button>
            </div>
            <div class="modal-body">
                <p id="modalMensaje" class="modal-message"></p>
                <div class="modal-actions">
                    <button id="btnConfirmar" class="btn-modal-confirm">Eliminar</button>
                    <button onclick="cerrarModal()" class="btn-modal-cancel">Cancelar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Función para confirmar eliminación
        function confirmarEliminacion(id, nombre) {
            document.getElementById('modalMensaje').textContent = 
                `¿Estás seguro de que quieres eliminar el almacén "${nombre}"? Esta acción no se puede deshacer.`;
            
            document.getElementById('btnConfirmar').onclick = function() {
                eliminarAlmacen(id);
            };
            
            document.getElementById('modalConfirmacion').style.display = 'flex';
        }

        // Función para eliminar almacén
        function eliminarAlmacen(id) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'procesar.php';
            
            const accionInput = document.createElement('input');
            accionInput.type = 'hidden';
            accionInput.name = 'accion';
            accionInput.value = 'eliminar';
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id';
            idInput.value = id;
            
            form.appendChild(accionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        }

        // Función para cerrar modal
        function cerrarModal() {
            document.getElementById('modalConfirmacion').style.display = 'none';
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('modalConfirmacion').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModal();
            }
        });
    </script>
</body>
</html>