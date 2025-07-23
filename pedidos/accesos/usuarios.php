<?php
/**
 * Gestión de Usuarios
 * Sequoia Speed - Sistema de Accesos
 */

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once dirname(__DIR__) . '/config_secure.php';
require_once dirname(__DIR__) . '/php82_helpers.php';
require_once 'middleware/AuthMiddleware.php';
require_once 'models/User.php';
require_once 'models/Role.php';

// Inicializar middleware y requerir permisos
$auth = new AuthMiddleware($conn);
$current_user = $auth->requirePermission('usuarios', 'leer');

// Inicializar modelos
$user_model = new User($conn);
$role_model = new Role($conn);

// Configuración de paginación
$limite = 20;
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina - 1) * $limite;

// Parámetros de filtros
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role_filter']) ? trim($_GET['role_filter']) : '';
// Por defecto mostrar solo activos, a menos que se especifique otro filtro
$status_filter = isset($_GET['status_filter']) ? trim($_GET['status_filter']) : 'activo';

// Obtener usuarios con filtros
$usuarios = $user_model->getUsers($limite, $offset, $search, $role_filter, $status_filter);
$total_usuarios = $user_model->countUsers($search, $role_filter, $status_filter);
$total_paginas = ceil($total_usuarios / $limite);

// Obtener roles para el filtro
$roles = $role_model->getAllRoles();

// Obtener estadísticas
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as activos,
    SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END) as inactivos,
    SUM(CASE WHEN ultimo_acceso >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as activos_semana,
    SUM(CASE WHEN fecha_creacion >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as nuevos_mes
FROM usuarios";

$stats_result = $conn->query($stats_query);
$estadisticas = $stats_result->fetch_assoc();

// Función para formatear fecha
function formatear_fecha($fecha) {
    if (!$fecha) return 'Nunca';
    return date('d/m/Y H:i', strtotime($fecha));
}

// Función para formatear tiempo relativo
function tiempo_relativo($fecha) {
    if (!$fecha) return 'Nunca';
    
    $ahora = time();
    $tiempo = strtotime($fecha);
    $diferencia = $ahora - $tiempo;
    
    if ($diferencia < 60) return 'Hace ' . $diferencia . ' segundos';
    if ($diferencia < 3600) return 'Hace ' . round($diferencia / 60) . ' minutos';
    if ($diferencia < 86400) return 'Hace ' . round($diferencia / 3600) . ' horas';
    if ($diferencia < 2592000) return 'Hace ' . round($diferencia / 86400) . ' días';
    
    return formatear_fecha($fecha);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>👥 Gestión de Usuarios - Sequoia Speed</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>👥</text></svg>">
    <link rel="stylesheet" href="../inventario/productos.css">
    <link rel="stylesheet" href="../notifications/notifications.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">👥 Gestión de Usuarios</h1>
                    <div class="breadcrumb">
                        <a href="../listar_pedidos.php">🏠 Inicio</a>
                        <span>/</span>
                        <a href="dashboard.php">🔐 Accesos</a>
                        <span>/</span>
                        <span>👥 Usuarios</span>
                    </div>
                </div>
                <div class="header-actions">
                    <?php if ($auth->hasPermission('usuarios', 'crear')): ?>
                        <a href="usuario_crear.php" class="btn btn-primary">
                            ➕ Nuevo Usuario
                        </a>
                    <?php endif; ?>
                    <a href="dashboard.php" class="btn btn-secondary">
                        🏠 Dashboard
                    </a>
                </div>
            </div>
        </header>

        <!-- Estadísticas -->
        <div class="stats-section">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($estadisticas['total']); ?></div>
                    <div class="stat-label">Total Usuarios</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($estadisticas['activos']); ?></div>
                    <div class="stat-label">Activos</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">❌</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($estadisticas['inactivos']); ?></div>
                    <div class="stat-label">Inactivos</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🔥</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($estadisticas['activos_semana']); ?></div>
                    <div class="stat-label">Activos Esta Semana</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🆕</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($estadisticas['nuevos_mes']); ?></div>
                    <div class="stat-label">Nuevos Este Mes</div>
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
                               placeholder="🔍 Buscar por nombre o email..."
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <select name="role_filter" class="filter-select">
                            <option value="">🎭 Todos los roles</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['nombre']; ?>" 
                                        <?php echo $role_filter === $role['nombre'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($role['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select name="status_filter" class="filter-select">
                            <option value="">📊 Todos los estados</option>
                            <option value="activo" <?php echo ($status_filter === 'activo' || (!isset($_GET['status_filter']) && $status_filter === 'activo')) ? 'selected' : ''; ?>>✅ Activos</option>
                            <option value="inactivo" <?php echo $status_filter === 'inactivo' ? 'selected' : ''; ?>>❌ Inactivos</option>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-filter">
                            🔍 Filtrar
                        </button>
                        <a href="?status_filter=activo" class="btn btn-clear">
                            🗑️ Limpiar
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tabla de usuarios -->
        <div class="table-section">
            <div class="table-container">
                <?php if (empty($usuarios)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">👥</div>
                        <div class="empty-title">No se encontraron usuarios</div>
                        <div class="empty-subtitle">
                            No hay usuarios para los filtros seleccionados
                        </div>
                        <?php if ($auth->hasPermission('usuarios', 'crear')): ?>
                            <a href="usuario_crear.php" class="btn btn-primary">
                                ➕ Crear Primer Usuario
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <table class="productos-table">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Roles</th>
                                <th>Estado</th>
                                <th>Último Acceso</th>
                                <th>Fecha Creación</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario): ?>
                                <tr class="<?php echo $usuario['activo'] ? '' : 'inactive'; ?>">
                                    <td class="producto-info">
                                        <div class="producto-nombre">
                                            <?php echo htmlspecialchars($usuario['nombre']); ?>
                                        </div>
                                        <div class="producto-sku">
                                            ID: <?php echo $usuario['id']; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size: 13px; color: var(--text-primary);">
                                            <?php echo htmlspecialchars($usuario['email']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($usuario['roles'])): ?>
                                            <?php foreach (explode(', ', $usuario['roles']) as $role): ?>
                                                <span class="badge-categoria" style="margin-right: var(--space-xs); margin-bottom: var(--space-xs);">
                                                    <?php echo htmlspecialchars($role); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="badge-secondary">Sin roles</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="stock-cell">
                                        <?php if ($usuario['activo']): ?>
                                            <span class="badge-stock stock-alto">
                                                ✅ Activo
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-stock stock-bajo">
                                                ❌ Inactivo
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fecha-cell">
                                        <div class="fecha">
                                            <?php echo tiempo_relativo($usuario['ultimo_acceso']); ?>
                                        </div>
                                    </td>
                                    <td class="fecha-cell">
                                        <div class="fecha">
                                            <?php echo formatear_fecha($usuario['fecha_creacion']); ?>
                                        </div>
                                    </td>
                                    <td class="acciones-cell">
                                        <div class="acciones-group">
                                            <button onclick="verDetalleUsuario(<?php echo $usuario['id']; ?>)" 
                                                    class="btn-accion btn-ver" 
                                                    title="Ver detalles">
                                                👁️
                                            </button>
                                            
                                            <?php if ($auth->hasPermission('usuarios', 'actualizar')): ?>
                                                <a href="usuario_editar.php?id=<?php echo $usuario['id']; ?>" 
                                                   class="btn-accion btn-editar" 
                                                   title="Editar usuario">
                                                    ✏️
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($auth->hasPermission('usuarios', 'actualizar') && $usuario['id'] != $current_user['id']): ?>
                                                <?php if ($usuario['activo']): ?>
                                                    <button onclick="toggleUsuarioEstado(<?php echo $usuario['id']; ?>, 'desactivar')" 
                                                            class="btn-accion btn-desactivar" 
                                                            title="Desactivar usuario">
                                                        ❌
                                                    </button>
                                                <?php else: ?>
                                                    <button onclick="toggleUsuarioEstado(<?php echo $usuario['id']; ?>, 'activar')" 
                                                            class="btn-accion btn-activar" 
                                                            title="Activar usuario">
                                                        ✅
                                                    </button>
                                                <?php endif; ?>
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
                        Mostrando <?php echo ($offset + 1); ?> a <?php echo min($offset + $limite, $total_usuarios); ?> 
                        de <?php echo number_format($total_usuarios); ?> usuarios
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

    <!-- Modal para detalles de usuario -->
    <div id="userModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>👤 Detalles del Usuario</h3>
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
        // Ver detalles de usuario
        function verDetalleUsuario(id) {
            mostrarIndicadorCarga();
            
            fetch(`usuario_detalle.php?id=${id}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('modalBody').innerHTML = html;
                document.getElementById('userModal').style.display = 'flex';
                ocultarIndicadorCarga();
            })
            .catch(error => {
                console.error('Error:', error);
                ocultarIndicadorCarga();
                mostrarNotificacion('Error al cargar los detalles', 'error');
            });
        }

        // Cambiar estado de usuario
        function toggleUsuarioEstado(id, accion) {
            const mensaje = accion === 'activar' ? 
                '¿Estás seguro de que quieres activar este usuario?' : 
                '¿Estás seguro de que quieres desactivar este usuario?';
            
            if (confirm(mensaje)) {
                mostrarIndicadorCarga();
                
                fetch('usuario_toggle.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${id}&accion=${accion}`
                })
                .then(response => response.json())
                .then(data => {
                    ocultarIndicadorCarga();
                    
                    if (data.success) {
                        mostrarNotificacion(data.message, 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        mostrarNotificacion(data.error || 'Error al cambiar el estado', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    ocultarIndicadorCarga();
                    mostrarNotificacion('Error de conexión', 'error');
                });
            }
        }

        // Cerrar modal
        function cerrarModal() {
            document.getElementById('userModal').style.display = 'none';
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('userModal').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModal();
            }
        });

        // Shortcut para crear usuario
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                <?php if ($auth->hasPermission('usuarios', 'crear')): ?>
                    window.location.href = 'usuario_crear.php';
                <?php endif; ?>
            }
        });

        // Auto-submit del formulario de búsqueda
        document.querySelector('input[name="search"]').addEventListener('input', function() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
    </script>
</body>
</html>