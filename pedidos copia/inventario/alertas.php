<?php
/**
 * Dashboard de Alertas de Inventario
 * Sequoia Speed - Módulo de Inventario
 */

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once '../config_secure.php';
require_once '../notifications/notification_helpers.php';
require_once '../php82_helpers.php';
require_once 'sistema_alertas.php';

// Inicializar sistema de alertas
$sistema_alertas = new SistemaAlertas($conn);

// Configuración de paginación
$limite = 20;
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina - 1) * $limite;

// Parámetros de filtros
$almacen_seleccionado = isset($_GET['almacen']) ? trim($_GET['almacen']) : '';
$tipo_alerta = isset($_GET['tipo_alerta']) ? trim($_GET['tipo_alerta']) : '';
$estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';
$prioridad = isset($_GET['prioridad']) ? trim($_GET['prioridad']) : '';

// Obtener información del almacén seleccionado
$almacen_actual = null;
if (!empty($almacen_seleccionado)) {
    $query_almacen = "SELECT * FROM almacenes WHERE codigo = ? AND activo = 1 LIMIT 1";
    $stmt_almacen = $conn->prepare($query_almacen);
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

// Construir consulta con filtros
$where_conditions = [];
$params = [];
$types = '';

if (!empty($almacen_seleccionado) && $almacen_actual) {
    $where_conditions[] = "ai.almacen_id = ?";
    $params[] = $almacen_actual['id'];
    $types .= 'i';
}

if (!empty($tipo_alerta)) {
    $where_conditions[] = "ai.tipo_alerta = ?";
    $params[] = $tipo_alerta;
    $types .= 's';
}

if (!empty($estado)) {
    $where_conditions[] = "ai.estado = ?";
    $params[] = $estado;
    $types .= 's';
}

if (!empty($prioridad)) {
    $where_conditions[] = "ai.nivel_prioridad = ?";
    $params[] = $prioridad;
    $types .= 's';
}

// Construir WHERE clause
$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Consulta principal con paginación
$query = "SELECT 
    ai.*,
    p.nombre as producto_nombre,
    p.sku as producto_sku,
    p.imagen as producto_imagen,
    a.nombre as almacen_nombre,
    a.codigo as almacen_codigo
FROM alertas_inventario ai
INNER JOIN productos p ON ai.producto_id = p.id
INNER JOIN almacenes a ON ai.almacen_id = a.id
$where_clause
ORDER BY 
    CASE ai.nivel_prioridad 
        WHEN 'critica' THEN 1 
        WHEN 'alta' THEN 2 
        WHEN 'media' THEN 3 
        WHEN 'baja' THEN 4 
    END,
    ai.fecha_creacion DESC
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
$alertas = $result->fetch_all(MYSQLI_ASSOC);

// Consulta para contar total de alertas
$count_query = "SELECT COUNT(*) as total 
FROM alertas_inventario ai
INNER JOIN productos p ON ai.producto_id = p.id
INNER JOIN almacenes a ON ai.almacen_id = a.id
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
$total_alertas = $count_stmt->get_result()->fetch_assoc()['total'];
$total_paginas = ceil($total_alertas / $limite);

// Obtener estadísticas
$stats_query = "SELECT 
    COUNT(*) as total_alertas,
    SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
    SUM(CASE WHEN estado = 'vista' THEN 1 ELSE 0 END) as vistas,
    SUM(CASE WHEN estado = 'resuelta' THEN 1 ELSE 0 END) as resueltas,
    SUM(CASE WHEN nivel_prioridad = 'critica' THEN 1 ELSE 0 END) as criticas,
    SUM(CASE WHEN nivel_prioridad = 'alta' THEN 1 ELSE 0 END) as altas,
    SUM(CASE WHEN DATE(fecha_creacion) = CURDATE() THEN 1 ELSE 0 END) as hoy
FROM alertas_inventario ai
INNER JOIN productos p ON ai.producto_id = p.id
INNER JOIN almacenes a ON ai.almacen_id = a.id
$where_clause";

$stats_stmt = $conn->prepare($stats_query);
if (!empty($where_conditions)) {
    $stats_stmt->bind_param($count_types, ...$count_params);
}
$stats_stmt->execute();
$estadisticas = $stats_stmt->get_result()->fetch_assoc();

// Función para formatear tipo de alerta
function formatear_tipo_alerta($tipo) {
    $tipos = [
        'stock_bajo' => ['icono' => '📉', 'color' => 'warning', 'texto' => 'Stock Bajo'],
        'stock_critico' => ['icono' => '🚨', 'color' => 'danger', 'texto' => 'Stock Crítico'],
        'stock_alto' => ['icono' => '📈', 'color' => 'info', 'texto' => 'Stock Alto'],
        'sin_movimiento' => ['icono' => '⏰', 'color' => 'secondary', 'texto' => 'Sin Movimiento'],
        'vencimiento' => ['icono' => '⏳', 'color' => 'warning', 'texto' => 'Próximo Vencimiento']
    ];
    
    return $tipos[$tipo] ?? ['icono' => '❓', 'color' => 'secondary', 'texto' => 'Desconocido'];
}

// Función para formatear prioridad
function formatear_prioridad($prioridad) {
    $prioridades = [
        'critica' => ['icono' => '🚨', 'color' => 'danger', 'texto' => 'Crítica'],
        'alta' => ['icono' => '⚠️', 'color' => 'warning', 'texto' => 'Alta'],
        'media' => ['icono' => '📋', 'color' => 'info', 'texto' => 'Media'],
        'baja' => ['icono' => 'ℹ️', 'color' => 'secondary', 'texto' => 'Baja']
    ];
    
    return $prioridades[$prioridad] ?? ['icono' => '❓', 'color' => 'secondary', 'texto' => 'Desconocida'];
}

// Función para formatear estado
function formatear_estado($estado) {
    $estados = [
        'pendiente' => ['icono' => '⏳', 'color' => 'warning', 'texto' => 'Pendiente'],
        'vista' => ['icono' => '👁️', 'color' => 'info', 'texto' => 'Vista'],
        'resuelta' => ['icono' => '✅', 'color' => 'success', 'texto' => 'Resuelta'],
        'ignorada' => ['icono' => '❌', 'color' => 'secondary', 'texto' => 'Ignorada']
    ];
    
    return $estados[$estado] ?? ['icono' => '❓', 'color' => 'secondary', 'texto' => 'Desconocido'];
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
    <title>🚨 Alertas de Inventario - Sequoia Speed</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🚨</text></svg>">
    <link rel="stylesheet" href="productos.css">
    <link rel="stylesheet" href="../notifications/notifications.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">🚨 Alertas de Inventario</h1>
                    <div class="breadcrumb">
                        <a href="../listar_pedidos.php">🏠 Inicio</a>
                        <span>/</span>
                        <a href="productos.php">📦 Inventario</a>
                        <span>/</span>
                        <span>🚨 Alertas</span>
                        <?php if ($almacen_actual): ?>
                            <span>/</span>
                            <span class="almacen-actual">🏪 <?php echo htmlspecialchars($almacen_actual['nombre']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="header-actions">
                    <button onclick="verificarAlertas()" class="btn btn-primary">
                        🔄 Verificar Alertas
                    </button>
                    <button onclick="marcarTodasVistas()" class="btn btn-info">
                        👁️ Marcar Todas Vistas
                    </button>
                    <a href="productos.php" class="btn btn-secondary">
                        📦 Ver Productos
                    </a>
                </div>
            </div>
        </header>

        <!-- Selector de Almacén -->
        <div class="almacen-selector-section">
            <div class="almacen-selector-header">
                <h3>🏪 Filtrar por Almacén</h3>
                <p>Selecciona un almacén para ver sus alertas específicas</p>
            </div>
            <div class="almacen-selector-grid">
                <a href="?" class="almacen-card <?php echo empty($almacen_seleccionado) ? 'active' : ''; ?>">
                    <div class="almacen-icon">🌐</div>
                    <div class="almacen-info">
                        <h4>Todos los Almacenes</h4>
                        <p>Ver alertas de todos los almacenes</p>
                        <?php if (empty($almacen_seleccionado)): ?>
                            <span class="almacen-badge">✓ Seleccionado</span>
                        <?php endif; ?>
                    </div>
                </a>
                
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
                <div class="stat-icon">🚨</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($estadisticas['total_alertas']); ?></div>
                    <div class="stat-label">Total Alertas</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($estadisticas['pendientes']); ?></div>
                    <div class="stat-label">Pendientes</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🚨</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($estadisticas['criticas']); ?></div>
                    <div class="stat-label">Críticas</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($estadisticas['hoy']); ?></div>
                    <div class="stat-label">Hoy</div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-section">
            <form method="GET" class="filters-form">
                <?php if (!empty($almacen_seleccionado)): ?>
                    <input type="hidden" name="almacen" value="<?php echo $almacen_seleccionado; ?>">
                <?php endif; ?>
                
                <div class="filters-row">
                    <div class="filter-group">
                        <select name="tipo_alerta" class="filter-select">
                            <option value="">🚨 Todos los tipos</option>
                            <option value="stock_bajo" <?php echo $tipo_alerta === 'stock_bajo' ? 'selected' : ''; ?>>📉 Stock Bajo</option>
                            <option value="stock_critico" <?php echo $tipo_alerta === 'stock_critico' ? 'selected' : ''; ?>>🚨 Stock Crítico</option>
                            <option value="stock_alto" <?php echo $tipo_alerta === 'stock_alto' ? 'selected' : ''; ?>>📈 Stock Alto</option>
                            <option value="sin_movimiento" <?php echo $tipo_alerta === 'sin_movimiento' ? 'selected' : ''; ?>>⏰ Sin Movimiento</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select name="estado" class="filter-select">
                            <option value="">📊 Todos los estados</option>
                            <option value="pendiente" <?php echo $estado === 'pendiente' ? 'selected' : ''; ?>>⏳ Pendiente</option>
                            <option value="vista" <?php echo $estado === 'vista' ? 'selected' : ''; ?>>👁️ Vista</option>
                            <option value="resuelta" <?php echo $estado === 'resuelta' ? 'selected' : ''; ?>>✅ Resuelta</option>
                            <option value="ignorada" <?php echo $estado === 'ignorada' ? 'selected' : ''; ?>>❌ Ignorada</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select name="prioridad" class="filter-select">
                            <option value="">⚠️ Todas las prioridades</option>
                            <option value="critica" <?php echo $prioridad === 'critica' ? 'selected' : ''; ?>>🚨 Crítica</option>
                            <option value="alta" <?php echo $prioridad === 'alta' ? 'selected' : ''; ?>>⚠️ Alta</option>
                            <option value="media" <?php echo $prioridad === 'media' ? 'selected' : ''; ?>>📋 Media</option>
                            <option value="baja" <?php echo $prioridad === 'baja' ? 'selected' : ''; ?>>ℹ️ Baja</option>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-filter">
                            🔍 Filtrar
                        </button>
                        <a href="?<?php echo !empty($almacen_seleccionado) ? 'almacen=' . $almacen_seleccionado : ''; ?>" class="btn btn-clear">
                            🗑️ Limpiar
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tabla de alertas -->
        <div class="table-section">
            <div class="table-container">
                <?php if (empty($alertas)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">🚨</div>
                        <div class="empty-title">No se encontraron alertas</div>
                        <div class="empty-subtitle">
                            No hay alertas para los filtros seleccionados
                        </div>
                        <button onclick="verificarAlertas()" class="btn btn-primary">
                            🔄 Verificar Alertas
                        </button>
                    </div>
                <?php else: ?>
                    <table class="productos-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Producto</th>
                                <th>Almacén</th>
                                <th>Tipo</th>
                                <th>Prioridad</th>
                                <th>Estado</th>
                                <th>Mensaje</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alertas as $alerta): ?>
                                <?php 
                                $tipo_info = formatear_tipo_alerta($alerta['tipo_alerta']);
                                $prioridad_info = formatear_prioridad($alerta['nivel_prioridad']);
                                $estado_info = formatear_estado($alerta['estado']);
                                ?>
                                <tr class="alerta-row <?php echo $alerta['estado']; ?> <?php echo $alerta['nivel_prioridad']; ?>">
                                    <td class="fecha-cell">
                                        <div class="fecha"><?php echo formatear_fecha($alerta['fecha_creacion']); ?></div>
                                    </td>
                                    <td class="producto-info">
                                        <div class="producto-nombre"><?php echo htmlspecialchars($alerta['producto_nombre']); ?></div>
                                        <?php if (!empty($alerta['producto_sku'])): ?>
                                            <div class="producto-sku">SKU: <?php echo htmlspecialchars($alerta['producto_sku']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="almacen-cell">
                                        <div class="almacen-nombre"><?php echo htmlspecialchars($alerta['almacen_nombre']); ?></div>
                                    </td>
                                    <td class="tipo-cell">
                                        <span class="badge-tipo badge-<?php echo $tipo_info['color']; ?>">
                                            <?php echo $tipo_info['icono']; ?> <?php echo $tipo_info['texto']; ?>
                                        </span>
                                    </td>
                                    <td class="prioridad-cell">
                                        <span class="badge-prioridad badge-<?php echo $prioridad_info['color']; ?>">
                                            <?php echo $prioridad_info['icono']; ?> <?php echo $prioridad_info['texto']; ?>
                                        </span>
                                    </td>
                                    <td class="estado-cell">
                                        <span class="badge-estado badge-<?php echo $estado_info['color']; ?>">
                                            <?php echo $estado_info['icono']; ?> <?php echo $estado_info['texto']; ?>
                                        </span>
                                    </td>
                                    <td class="mensaje-cell">
                                        <div class="mensaje-texto"><?php echo htmlspecialchars($alerta['mensaje']); ?></div>
                                    </td>
                                    <td class="acciones-cell">
                                        <div class="acciones-group">
                                            <?php if ($alerta['estado'] === 'pendiente'): ?>
                                                <button onclick="marcarVista(<?php echo $alerta['id']; ?>)" 
                                                        class="btn-accion btn-info" 
                                                        title="Marcar como vista">
                                                    👁️
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($alerta['estado'] !== 'resuelta'): ?>
                                                <button onclick="resolverAlerta(<?php echo $alerta['id']; ?>)" 
                                                        class="btn-accion btn-success" 
                                                        title="Resolver alerta">
                                                    ✅
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button onclick="verDetalleAlerta(<?php echo $alerta['id']; ?>)" 
                                                    class="btn-accion btn-primary" 
                                                    title="Ver detalles">
                                                📋
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
                        Mostrando <?php echo ($offset + 1); ?> a <?php echo min($offset + $limite, $total_alertas); ?> 
                        de <?php echo number_format($total_alertas); ?> alertas
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

    <!-- Sistema de notificaciones -->
    <div id="notification-container"></div>

    <script src="productos.js"></script>
    <script>
        // Verificar alertas automáticamente
        function verificarAlertas() {
            mostrarIndicadorCarga();
            
            fetch('verificar_alertas.php')
            .then(response => response.json())
            .then(data => {
                ocultarIndicadorCarga();
                
                if (data.success) {
                    mostrarNotificacion(data.message, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    mostrarNotificacion(data.error || 'Error al verificar alertas', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                ocultarIndicadorCarga();
                mostrarNotificacion('Error de conexión', 'error');
            });
        }

        // Marcar alerta como vista
        function marcarVista(id) {
            fetch('gestionar_alerta.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `accion=marcar_vista&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarNotificacion('Alerta marcada como vista', 'success');
                    location.reload();
                } else {
                    mostrarNotificacion(data.error || 'Error al marcar como vista', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarNotificacion('Error de conexión', 'error');
            });
        }

        // Resolver alerta
        function resolverAlerta(id) {
            if (confirm('¿Estás seguro de que quieres resolver esta alerta?')) {
                fetch('gestionar_alerta.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `accion=resolver&id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarNotificacion('Alerta resuelta correctamente', 'success');
                        location.reload();
                    } else {
                        mostrarNotificacion(data.error || 'Error al resolver alerta', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarNotificacion('Error de conexión', 'error');
                });
            }
        }

        // Marcar todas las alertas como vistas
        function marcarTodasVistas() {
            if (confirm('¿Estás seguro de que quieres marcar todas las alertas pendientes como vistas?')) {
                fetch('gestionar_alerta.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'accion=marcar_todas_vistas'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarNotificacion(data.message, 'success');
                        location.reload();
                    } else {
                        mostrarNotificacion(data.error || 'Error al marcar alertas', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarNotificacion('Error de conexión', 'error');
                });
            }
        }

        // Ver detalle de alerta
        function verDetalleAlerta(id) {
            mostrarNotificacion('Funcionalidad de detalle en desarrollo', 'info');
        }

        // Verificar alertas automáticamente cada 5 minutos
        setInterval(function() {
            verificarAlertas();
        }, 300000); // 5 minutos
    </script>
</body>
</html>