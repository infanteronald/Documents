<?php
/**
 * Auditoría del Sistema
 * Sequoia Speed - Sistema de Accesos
 */

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once dirname(__DIR__) . '/config_secure.php';
require_once dirname(__DIR__) . '/php82_helpers.php';
require_once 'middleware/AuthMiddleware.php';

// Inicializar middleware y requerir permisos
$auth = new AuthMiddleware($conn);
$current_user = $auth->requirePermission('usuarios', 'leer');

// Configuración de paginación
$limite = 50;
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina - 1) * $limite;

// Parámetros de filtros
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$usuario_filter = isset($_GET['usuario_filter']) ? trim($_GET['usuario_filter']) : '';
$accion_filter = isset($_GET['accion_filter']) ? trim($_GET['accion_filter']) : '';
$modulo_filter = isset($_GET['modulo_filter']) ? trim($_GET['modulo_filter']) : '';
$fecha_desde = isset($_GET['fecha_desde']) ? trim($_GET['fecha_desde']) : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? trim($_GET['fecha_hasta']) : '';

// Construir consulta con filtros
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(u.nombre LIKE ? OR u.email LIKE ? OR aa.descripcion LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($usuario_filter)) {
    $where_conditions[] = "u.id = ?";
    $params[] = $usuario_filter;
    $types .= 'i';
}

if (!empty($accion_filter)) {
    $where_conditions[] = "aa.accion = ?";
    $params[] = $accion_filter;
    $types .= 's';
}

if (!empty($modulo_filter)) {
    $where_conditions[] = "aa.modulo = ?";
    $params[] = $modulo_filter;
    $types .= 's';
}

if (!empty($fecha_desde)) {
    $where_conditions[] = "DATE(aa.fecha_accion) >= ?";
    $params[] = $fecha_desde;
    $types .= 's';
}

if (!empty($fecha_hasta)) {
    $where_conditions[] = "DATE(aa.fecha_accion) <= ?";
    $params[] = $fecha_hasta;
    $types .= 's';
}

// Construir WHERE clause
$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Consulta principal con paginación
$query = "SELECT 
    aa.*,
    u.nombre as usuario_nombre,
    u.email as usuario_email
FROM acc_auditoria_accesos aa
INNER JOIN acc_usuarios u ON aa.usuario_id = u.id
$where_clause
ORDER BY aa.fecha_accion DESC
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
$actividades = $result->fetch_all(MYSQLI_ASSOC);

// Consulta para contar total de actividades
$count_query = "SELECT COUNT(*) as total 
FROM acc_auditoria_accesos aa
INNER JOIN acc_usuarios u ON aa.usuario_id = u.id
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
$total_actividades = $count_stmt->get_result()->fetch_assoc()['total'];
$total_paginas = ceil($total_actividades / $limite);

// Obtener estadísticas generales
$stats_query = "SELECT 
    COUNT(*) as total_actividades,
    COUNT(DISTINCT usuario_id) as usuarios_activos,
    COUNT(DISTINCT accion) as tipos_accion,
    COUNT(DISTINCT modulo) as modulos_activos,
    COUNT(CASE WHEN DATE(fecha_accion) = CURDATE() THEN 1 END) as hoy,
    COUNT(CASE WHEN DATE(fecha_accion) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as semana,
    COUNT(CASE WHEN accion = 'login' THEN 1 END) as logins,
    COUNT(CASE WHEN accion = 'login_failed' THEN 1 END) as login_fallidos
FROM acc_auditoria_accesos aa
INNER JOIN acc_usuarios u ON aa.usuario_id = u.id
$where_clause";

$stats_stmt = $conn->prepare($stats_query);
if (!empty($where_conditions)) {
    $stats_stmt->bind_param($count_types, ...$count_params);
}
$stats_stmt->execute();
$estadisticas = $stats_stmt->get_result()->fetch_assoc();

// Obtener usuarios para filtro
$usuarios_query = "SELECT DISTINCT u.id, u.nombre, u.email 
FROM acc_usuarios u 
INNER JOIN acc_auditoria_accesos aa ON u.id = aa.usuario_id 
ORDER BY u.nombre LIMIT 50";
$usuarios = $conn->query($usuarios_query)->fetch_all(MYSQLI_ASSOC);

// Obtener acciones para filtro
$acciones_query = "SELECT DISTINCT accion FROM acc_auditoria_accesos ORDER BY accion";
$acciones = $conn->query($acciones_query)->fetch_all(MYSQLI_ASSOC);

// Obtener módulos para filtro
$modulos_query = "SELECT DISTINCT modulo FROM acc_auditoria_accesos ORDER BY modulo";
$modulos = $conn->query($modulos_query)->fetch_all(MYSQLI_ASSOC);

// Función para formatear fecha
function formatear_fecha($fecha) {
    return date('d/m/Y H:i:s', strtotime($fecha));
}

// Función para formatear acción
function formatear_accion($accion) {
    $acciones = [
        'login' => ['🔐', 'Inicio de sesión', 'success'],
        'logout' => ['🚪', 'Cierre de sesión', 'info'],
        'login_failed' => ['❌', 'Login fallido', 'danger'],
        'create' => ['➕', 'Crear', 'success'],
        'read' => ['👁️', 'Consultar', 'info'],
        'update' => ['✏️', 'Actualizar', 'warning'],
        'delete' => ['🗑️', 'Eliminar', 'danger'],
        'export' => ['📄', 'Exportar', 'info'],
        'import' => ['📥', 'Importar', 'warning']
    ];
    
    return $acciones[$accion] ?? ['📝', ucfirst($accion), 'secondary'];
}

// Función para formatear módulo
function formatear_modulo($modulo) {
    $modulos = [
        'acc_usuarios' => ['👥', 'Usuarios', 'primary'],
        'ventas' => ['🛒', 'Ventas', 'success'],
        'inventario' => ['📦', 'Inventario', 'info'],
        'reportes' => ['📊', 'Reportes', 'warning'],
        'configuracion' => ['⚙️', 'Configuración', 'secondary']
    ];
    
    return $modulos[$modulo] ?? ['📁', ucfirst($modulo), 'secondary'];
}

// Función para obtener clase de prioridad por acción
function get_priority_class($accion) {
    $priorities = [
        'login_failed' => 'danger',
        'delete' => 'danger',
        'create' => 'success',
        'update' => 'warning',
        'login' => 'success',
        'logout' => 'info'
    ];
    
    return $priorities[$accion] ?? 'secondary';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📋 Auditoría del Sistema - Sequoia Speed</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📋</text></svg>">
    <link rel="stylesheet" href="../inventario/productos.css">
    <link rel="stylesheet" href="../notifications/notifications.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">📋 Auditoría del Sistema</h1>
                    <div class="breadcrumb">
                        <a href="../listar_pedidos.php">🏠 Inicio</a>
                        <span>/</span>
                        <a href="dashboard.php">🔐 Accesos</a>
                        <span>/</span>
                        <span>📋 Auditoría</span>
                    </div>
                </div>
                <div class="header-actions">
                    <button onclick="exportarAuditoria()" class="btn btn-info">
                        📄 Exportar
                    </button>
                    <button onclick="limpiarFiltros()" class="btn btn-secondary">
                        🗑️ Limpiar Filtros
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        🏠 Dashboard
                    </a>
                </div>
            </div>
        </header>

        <!-- Estadísticas -->
        <div class="stats-section">
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($estadisticas['total_actividades']); ?></div>
                    <div class="stat-label">Total Actividades</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($estadisticas['usuarios_activos']); ?></div>
                    <div class="stat-label">Usuarios Activos</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($estadisticas['hoy']); ?></div>
                    <div class="stat-label">Hoy</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📈</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($estadisticas['semana']); ?></div>
                    <div class="stat-label">Esta Semana</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🔐</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($estadisticas['logins']); ?></div>
                    <div class="stat-label">Logins</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">❌</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($estadisticas['login_fallidos']); ?></div>
                    <div class="stat-label">Login Fallidos</div>
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
                               class="filter-input search-input" 
                               placeholder="🔍 Buscar en descripción..."
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <select name="usuario_filter" class="filter-select">
                            <option value="">👥 Todos los usuarios</option>
                            <?php foreach ($usuarios as $usuario): ?>
                                <option value="<?php echo $usuario['id']; ?>" 
                                        <?php echo $usuario_filter == $usuario['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($usuario['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select name="accion_filter" class="filter-select">
                            <option value="">📝 Todas las acciones</option>
                            <?php foreach ($acciones as $accion): ?>
                                <?php $accion_info = formatear_accion($accion['accion']); ?>
                                <option value="<?php echo $accion['accion']; ?>" 
                                        <?php echo $accion_filter === $accion['accion'] ? 'selected' : ''; ?>>
                                    <?php echo $accion_info[0]; ?> <?php echo $accion_info[1]; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select name="modulo_filter" class="filter-select">
                            <option value="">📦 Todos los módulos</option>
                            <?php foreach ($modulos as $modulo): ?>
                                <?php $modulo_info = formatear_modulo($modulo['modulo']); ?>
                                <option value="<?php echo $modulo['modulo']; ?>" 
                                        <?php echo $modulo_filter === $modulo['modulo'] ? 'selected' : ''; ?>>
                                    <?php echo $modulo_info[0]; ?> <?php echo $modulo_info[1]; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="filters-row" style="margin-top: var(--space-md);">
                    <div class="filter-group">
                        <input type="date" 
                               name="fecha_desde" 
                               class="filter-input" 
                               value="<?php echo $fecha_desde; ?>"
                               placeholder="Fecha desde">
                    </div>
                    
                    <div class="filter-group">
                        <input type="date" 
                               name="fecha_hasta" 
                               class="filter-input" 
                               value="<?php echo $fecha_hasta; ?>"
                               placeholder="Fecha hasta">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-filter">
                            🔍 Filtrar
                        </button>
                        <a href="?" class="btn btn-clear">
                            🗑️ Limpiar
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tabla de auditoría -->
        <div class="table-section">
            <div class="table-container">
                <?php if (empty($actividades)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📋</div>
                        <div class="empty-title">No se encontraron actividades</div>
                        <div class="empty-subtitle">
                            No hay actividades para los filtros seleccionados
                        </div>
                    </div>
                <?php else: ?>
                    <table class="productos-table">
                        <thead>
                            <tr>
                                <th>Fecha/Hora</th>
                                <th>Usuario</th>
                                <th>Acción</th>
                                <th>Módulo</th>
                                <th>Descripción</th>
                                <th>IP</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($actividades as $actividad): ?>
                                <?php 
                                $accion_info = formatear_accion($actividad['accion']);
                                $modulo_info = formatear_modulo($actividad['modulo']);
                                $priority_class = get_priority_class($actividad['accion']);
                                ?>
                                <tr class="audit-row <?php echo $priority_class; ?>">
                                    <td class="fecha-cell">
                                        <div class="fecha" style="font-weight: 500;">
                                            <?php echo formatear_fecha($actividad['fecha_accion']); ?>
                                        </div>
                                    </td>
                                    <td class="producto-info">
                                        <div class="producto-nombre">
                                            <?php echo htmlspecialchars($actividad['usuario_nombre']); ?>
                                        </div>
                                        <div class="producto-sku">
                                            <?php echo htmlspecialchars($actividad['usuario_email']); ?>
                                        </div>
                                    </td>
                                    <td class="tipo-cell">
                                        <span class="badge-tipo badge-<?php echo $accion_info[2]; ?>">
                                            <?php echo $accion_info[0]; ?> <?php echo $accion_info[1]; ?>
                                        </span>
                                    </td>
                                    <td class="categoria-cell">
                                        <span class="badge-categoria">
                                            <?php echo $modulo_info[0]; ?> <?php echo $modulo_info[1]; ?>
                                        </span>
                                    </td>
                                    <td class="descripcion-cell" style="max-width: 300px;">
                                        <div class="descripcion-texto" style="font-size: 13px; color: var(--text-primary); line-height: 1.4;">
                                            <?php echo htmlspecialchars($actividad['descripcion']); ?>
                                        </div>
                                    </td>
                                    <td class="ip-cell">
                                        <div style="font-size: 12px; color: var(--text-secondary); font-family: monospace;">
                                            <?php echo htmlspecialchars($actividad['ip_address']); ?>
                                        </div>
                                    </td>
                                    <td class="acciones-cell">
                                        <div class="acciones-group">
                                            <button onclick="verDetalleActividad(<?php echo $actividad['id']; ?>)" 
                                                    class="btn-accion btn-ver" 
                                                    title="Ver detalles">
                                                👁️
                                            </button>
                                            
                                            <?php if (!empty($actividad['user_agent'])): ?>
                                                <button onclick="verUserAgent(<?php echo $actividad['id']; ?>)" 
                                                        class="btn-accion btn-info" 
                                                        title="Ver navegador">
                                                    🌐
                                                </button>
                                            <?php endif; ?>
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
                        Mostrando <?php echo ($offset + 1); ?> a <?php echo min($offset + $limite, $total_actividades); ?> 
                        de <?php echo number_format($total_actividades); ?> actividades
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

    <!-- Modal para detalles de actividad -->
    <div id="activityModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>📋 Detalles de la Actividad</h3>
                <button onclick="cerrarModal()" class="btn-close">×</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Contenido cargado dinámicamente -->
            </div>
        </div>
    </div>

    <!-- Sistema de notificaciones -->
    <div id="notification-container"></div>

    <script src="../inventario/productos.js"></script>
    <script>
        // Ver detalles de actividad
        function verDetalleActividad(id) {
            mostrarIndicadorCarga();
            
            fetch(`actividad_detalle.php?id=${id}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('modalBody').innerHTML = html;
                document.getElementById('activityModal').style.display = 'flex';
                ocultarIndicadorCarga();
            })
            .catch(error => {
                console.error('Error:', error);
                ocultarIndicadorCarga();
                mostrarNotificacion('Error al cargar los detalles', 'error');
            });
        }

        // Ver user agent
        function verUserAgent(id) {
            mostrarIndicadorCarga();
            
            fetch(`user_agent.php?id=${id}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('modalBody').innerHTML = html;
                document.getElementById('activityModal').style.display = 'flex';
                ocultarIndicadorCarga();
            })
            .catch(error => {
                console.error('Error:', error);
                ocultarIndicadorCarga();
                mostrarNotificacion('Error al cargar la información', 'error');
            });
        }

        // Exportar auditoría
        function exportarAuditoria() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', '1');
            
            mostrarNotificacion('Preparando exportación...', 'info');
            
            fetch(`auditoria_export.php?${params.toString()}`)
            .then(response => response.blob())
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `auditoria_${new Date().toISOString().split('T')[0]}.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                
                mostrarNotificacion('Auditoría exportada exitosamente', 'success');
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarNotificacion('Error al exportar', 'error');
            });
        }

        // Limpiar filtros
        function limpiarFiltros() {
            window.location.href = 'auditoria.php';
        }

        // Cerrar modal
        function cerrarModal() {
            document.getElementById('activityModal').style.display = 'none';
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('activityModal').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModal();
            }
        });

        // Auto-submit del formulario de búsqueda
        document.querySelector('input[name="search"]').addEventListener('input', function() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 1000);
        });

        // Actualizar cada 30 segundos
        setInterval(function() {
            if (document.querySelector('input[name="search"]').value === '' && 
                document.querySelector('select[name="usuario_filter"]').value === '') {
                location.reload();
            }
        }, 30000);

        // Agregar clases de prioridad
        document.querySelectorAll('.audit-row').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.backgroundColor = 'rgba(88, 166, 255, 0.1)';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
            });
        });
    </script>

    <style>
        .audit-row.danger {
            border-left: 4px solid var(--color-danger);
        }
        
        .audit-row.success {
            border-left: 4px solid var(--color-success);
        }
        
        .audit-row.warning {
            border-left: 4px solid var(--color-warning);
        }
        
        .audit-row.info {
            border-left: 4px solid var(--color-info);
        }
        
        .descripcion-cell {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .ip-cell {
            width: 120px;
            text-align: center;
        }
        
        .audit-row {
            transition: all var(--transition-fast);
        }
    </style>
</body>
</html>