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

// Verificar si existe la tabla categorias_productos
$tabla_existe = false;
try {
    $check_tabla = $conn->query("SHOW TABLES LIKE 'categorias_productos'");
    if ($check_tabla && $check_tabla->num_rows > 0) {
        $tabla_existe = true;
    }
} catch (Exception $e) {
    // Ignorar error
}

// Si no existe la tabla, crearla
if (!$tabla_existe) {
    try {
        $create_table = "CREATE TABLE IF NOT EXISTS categorias_productos (
            id INT PRIMARY KEY AUTO_INCREMENT,
            nombre VARCHAR(100) NOT NULL UNIQUE,
            descripcion TEXT,
            icono VARCHAR(10) DEFAULT '🏷️',
            color VARCHAR(7) DEFAULT '#58a6ff',
            activa BOOLEAN DEFAULT TRUE,
            orden INT DEFAULT 0,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_nombre (nombre),
            INDEX idx_activa (activa),
            INDEX idx_orden (orden)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $conn->query($create_table);
        
        // Insertar algunas categorías por defecto
        $categorias_default = [
            ['Repuestos', 'Repuestos y partes automotrices', '🔧', '#ff6b6b'],
            ['Accesorios', 'Accesorios para vehículos', '✨', '#4ecdc4'],
            ['Filtros', 'Filtros de aire, aceite y combustible', '🛡️', '#45b7d1'],
            ['Aceites', 'Aceites y lubricantes', '🛢️', '#f9ca24'],
            ['Neumáticos', 'Llantas y neumáticos', '🛞', '#6c5ce7']
        ];
        
        $stmt_insert = $conn->prepare("INSERT IGNORE INTO categorias_productos (nombre, descripcion, icono, color, orden) VALUES (?, ?, ?, ?, ?)");
        foreach ($categorias_default as $index => $cat) {
            $stmt_insert->bind_param("ssssi", $cat[0], $cat[1], $cat[2], $cat[3], ($index + 1) * 10);
            $stmt_insert->execute();
        }
        
    } catch (Exception $e) {
        error_log("Error creando tabla categorias_productos: " . $e->getMessage());
    }
}

// Obtener filtros
$search = $_GET['search'] ?? '';
$filter_activa = $_GET['activa'] ?? '';
$order_by = $_GET['order'] ?? 'orden';

// Construir consulta
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "(cp.nombre LIKE ? OR cp.descripcion LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ss';
}

if ($filter_activa !== '') {
    $where_conditions[] = "cp.activa = ?";
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

// Usar consulta directa simplificada para evitar problemas con la vista
$query = "SELECT 
    cp.id,
    cp.nombre,
    cp.descripcion,
    cp.icono,
    cp.color,
    cp.activa,
    cp.orden,
    cp.fecha_creacion,
    cp.fecha_actualizacion,
    COALESCE(COUNT(p.id), 0) as total_productos,
    COALESCE(COUNT(CASE WHEN p.activo = 1 THEN 1 END), 0) as productos_activos,
    0 as stock_total,
    COALESCE(AVG(CASE WHEN p.activo = 1 THEN p.precio END), 0) as precio_promedio
FROM categorias_productos cp
LEFT JOIN productos p ON cp.id = p.categoria_id
$where_clause 
GROUP BY cp.id, cp.nombre, cp.descripcion, cp.icono, cp.color, cp.activa, cp.orden, cp.fecha_creacion, cp.fecha_actualizacion
ORDER BY cp.$order_by ASC, cp.nombre ASC";

try {
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $categorias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Debug: Log the number of categories found
    error_log("Categorías encontradas: " . count($categorias));
    
} catch (Exception $e) {
    // Si hay error en la consulta, usar arreglo vacío
    $categorias = [];
    error_log("Error en consulta de categorías: " . $e->getMessage());
    error_log("SQL Query: " . $query);
}

// Obtener estadísticas generales usando consulta directa
$stats_query = "SELECT 
    COUNT(DISTINCT cp.id) as total_categorias,
    COUNT(DISTINCT CASE WHEN cp.activa = 1 THEN cp.id END) as categorias_activas,
    COUNT(DISTINCT p.id) as total_productos_asignados,
    COUNT(DISTINCT CASE WHEN p.id IS NULL THEN cp.id END) as categorias_vacias
    FROM categorias_productos cp
    LEFT JOIN productos p ON cp.id = p.categoria_id";

try {
    $stats = $conn->query($stats_query)->fetch_assoc();
} catch (Exception $e) {
    // Si hay error, usar valores por defecto
    $stats = [
        'total_categorias' => 0,
        'categorias_activas' => 0,
        'total_productos_asignados' => 0,
        'categorias_vacias' => 0
    ];
    error_log("Error en consulta de estadísticas: " . $e->getMessage());
}

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
                            <?php if ($stats['total_categorias'] > 0): ?>
                                Error cargando las categorías. Total en BD: <?php echo $stats['total_categorias']; ?>
                            <?php else: ?>
                                Comienza creando tu primera categoría
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php if (auth_can('inventario', 'crear')): ?>
                        <a href="crear_categoria.php" class="btn btn-primary">➕ Crear Categoría</a>
                    <?php endif; ?>
                    
                    <!-- Debug info -->
                    <?php if (isset($_GET['debug'])): ?>
                        <div style="background: #f0f0f0; padding: 10px; margin-top: 20px; font-family: monospace; text-align: left;">
                            <strong>Debug Info:</strong><br>
                            Total categorías en stats: <?php echo $stats['total_categorias']; ?><br>
                            Array categorías count: <?php echo count($categorias); ?><br>
                            WHERE clause: <?php echo htmlspecialchars($where_clause); ?><br>
                            Params: <?php echo htmlspecialchars(json_encode($params)); ?><br>
                        </div>
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