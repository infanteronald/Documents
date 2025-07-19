<?php
/**
 * Gestión de Categorías de Productos
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

// Obtener filtros
$search = $_GET['search'] ?? '';
$filter_activa = $_GET['activa'] ?? '';
$order_by = $_GET['order'] ?? 'orden';

// Construir consulta
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "(nombre LIKE ? OR descripcion LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ss';
}

if ($filter_activa !== '') {
    $where_conditions[] = "activa = ?";
    $params[] = (int)$filter_activa;
    $param_types .= 'i';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Validar orden
$valid_orders = ['orden', 'nombre', 'fecha_creacion', 'total_productos'];
if (!in_array($order_by, $valid_orders)) {
    $order_by = 'orden';
}

// Obtener categorías con estadísticas
$query = "SELECT * FROM vista_categorias_estadisticas 
          $where_clause 
          ORDER BY $order_by ASC, nombre ASC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$categorias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener estadísticas generales
$stats_query = "SELECT 
    COUNT(*) as total_categorias,
    COUNT(CASE WHEN activa = 1 THEN 1 END) as categorias_activas,
    COALESCE(SUM(total_productos), 0) as total_productos_asignados,
    COUNT(CASE WHEN total_productos = 0 THEN 1 END) as categorias_vacias
    FROM vista_categorias_estadisticas";
$stats = $conn->query($stats_query)->fetch_assoc();

// Mensajes de sesión
$mensaje_exito = $_SESSION['mensaje_exito'] ?? '';
unset($_SESSION['mensaje_exito']);

$mensaje_error = $_SESSION['mensaje_error'] ?? '';
unset($_SESSION['mensaje_error']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🗂️ Gestión de Categorías - Sequoia Speed</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🗂️</text></svg>">
    <link rel="stylesheet" href="../productos.css">
    <link rel="stylesheet" href="categorias.css">
    <link rel="stylesheet" href="../../notifications/notifications.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">🗂️ Gestión de Categorías</h1>
                    <div class="breadcrumb">
                        <a href="../../listar_pedidos.php">🏠 Inicio</a>
                        <span>/</span>
                        <a href="../productos.php">📦 Inventario</a>
                        <span>/</span>
                        <span>🗂️ Categorías</span>
                    </div>
                </div>
                <div class="header-actions">
                    <?php if (auth_can('inventario', 'crear')): ?>
                        <a href="crear_categoria.php" class="btn btn-primary">
                            ➕ Nueva Categoría
                        </a>
                    <?php endif; ?>
                    <a href="../productos.php" class="btn btn-secondary">
                        📦 Ver Productos
                    </a>
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
        <div class="stats-section">
            <div class="stat-card">
                <div class="stat-icon">🗂️</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stats['total_categorias']); ?></div>
                    <div class="stat-label">Total Categorías</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stats['categorias_activas']); ?></div>
                    <div class="stat-label">Activas</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stats['total_productos_asignados']); ?></div>
                    <div class="stat-label">Productos Asignados</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📋</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stats['categorias_vacias']); ?></div>
                    <div class="stat-label">Sin Productos</div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-section">
            <form method="GET" class="filters-form">
                <div class="filters-row">
                    <div class="filter-group">
                        <input type="text" 
                               name="search" 
                               value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="🔍 Buscar categorías..."
                               class="filter-input search-input">
                    </div>
                    
                    <div class="filter-group">
                        <select name="activa" class="filter-select">
                            <option value="">📊 Todos los estados</option>
                            <option value="1" <?php echo $filter_activa === '1' ? 'selected' : ''; ?>>✅ Activas</option>
                            <option value="0" <?php echo $filter_activa === '0' ? 'selected' : ''; ?>>❌ Inactivas</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select name="order" class="filter-select">
                            <option value="orden" <?php echo $order_by === 'orden' ? 'selected' : ''; ?>>📊 Por Orden</option>
                            <option value="nombre" <?php echo $order_by === 'nombre' ? 'selected' : ''; ?>>🔤 Por Nombre</option>
                            <option value="total_productos" <?php echo $order_by === 'total_productos' ? 'selected' : ''; ?>>📦 Por Productos</option>
                            <option value="fecha_creacion" <?php echo $order_by === 'fecha_creacion' ? 'selected' : ''; ?>>📅 Por Fecha</option>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-filter">🔍 Filtrar</button>
                        <a href="index.php" class="btn btn-clear">🗑️ Limpiar</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Grid de categorías -->
        <div class="categorias-section">
            <?php if (empty($categorias)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🗂️</div>
                    <div class="empty-title">No hay categorías</div>
                    <div class="empty-subtitle">
                        <?php if (!empty($search)): ?>
                            No se encontraron categorías con el término "<?php echo htmlspecialchars($search); ?>"
                        <?php else: ?>
                            Comienza creando tu primera categoría
                        <?php endif; ?>
                    </div>
                    <?php if (auth_can('inventario', 'crear')): ?>
                        <a href="crear_categoria.php" class="btn btn-primary">➕ Crear Categoría</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="categorias-grid">
                    <?php foreach ($categorias as $categoria): ?>
                        <div class="categoria-card <?php echo !$categoria['activa'] ? 'inactive' : ''; ?>" 
                             data-id="<?php echo $categoria['id']; ?>">
                            
                            <div class="categoria-header">
                                <div class="categoria-icon" style="color: <?php echo htmlspecialchars($categoria['color']); ?>">
                                    <?php echo htmlspecialchars($categoria['icono']); ?>
                                </div>
                                <div class="categoria-info">
                                    <h3 class="categoria-nombre"><?php echo htmlspecialchars($categoria['nombre']); ?></h3>
                                    <p class="categoria-descripcion"><?php echo htmlspecialchars($categoria['descripcion'] ?? ''); ?></p>
                                </div>
                                <div class="categoria-estado">
                                    <span class="estado-badge <?php echo $categoria['activa'] ? 'activo' : 'inactivo'; ?>">
                                        <?php echo $categoria['activa'] ? '✅ Activa' : '❌ Inactiva'; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="categoria-stats">
                                <div class="stat-item">
                                    <span class="stat-value"><?php echo number_format($categoria['total_productos']); ?></span>
                                    <span class="stat-label">Productos</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-value"><?php echo number_format($categoria['productos_activos']); ?></span>
                                    <span class="stat-label">Activos</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-value"><?php echo number_format($categoria['stock_total']); ?></span>
                                    <span class="stat-label">Stock</span>
                                </div>
                                <?php if ($categoria['precio_promedio'] > 0): ?>
                                    <div class="stat-item">
                                        <span class="stat-value">$<?php echo number_format($categoria['precio_promedio'], 0); ?></span>
                                        <span class="stat-label">Precio Prom.</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="categoria-actions">
                                <?php if (auth_can('inventario', 'actualizar')): ?>
                                    <a href="editar_categoria.php?id=<?php echo $categoria['id']; ?>" 
                                       class="btn-accion btn-editar" 
                                       title="Editar categoría">
                                        ✏️
                                    </a>
                                <?php endif; ?>
                                
                                <a href="../productos.php?categoria=<?php echo urlencode($categoria['nombre']); ?>" 
                                   class="btn-accion btn-ver" 
                                   title="Ver productos">
                                    👁️
                                </a>
                                
                                <?php if (auth_can('inventario', 'eliminar') && $categoria['total_productos'] == 0): ?>
                                    <button onclick="confirmarEliminacion(<?php echo $categoria['id']; ?>, '<?php echo htmlspecialchars($categoria['nombre'], ENT_QUOTES); ?>')" 
                                            class="btn-accion btn-eliminar" 
                                            title="Eliminar categoría">
                                        🗑️
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de confirmación -->
    <div id="modalConfirmacion" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>🗑️ Confirmar Eliminación</h3>
                <button onclick="cerrarModal()" class="btn-close">×</button>
            </div>
            <div class="modal-body">
                <p id="mensajeConfirmacion"></p>
                <div class="modal-actions">
                    <button id="btnConfirmar" class="btn btn-danger">🗑️ Eliminar</button>
                    <button onclick="cerrarModal()" class="btn btn-secondary">❌ Cancelar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Sistema de notificaciones -->
    <div id="notification-container"></div>

    <script src="../productos.js"></script>
    <script>
        function confirmarEliminacion(id, nombre) {
            document.getElementById('mensajeConfirmacion').textContent = 
                `¿Estás seguro de que quieres eliminar la categoría "${nombre}"? Esta acción no se puede deshacer.`;
            
            document.getElementById('btnConfirmar').onclick = function() {
                window.location.href = `procesar_categoria.php?accion=eliminar&id=${id}`;
            };
            
            document.getElementById('modalConfirmacion').style.display = 'flex';
        }
        
        function cerrarModal() {
            document.getElementById('modalConfirmacion').style.display = 'none';
        }
        
        // Cerrar modal al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                cerrarModal();
            }
        });
        
        // Cerrar modal con Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarModal();
            }
        });
    </script>
</body>
</html>