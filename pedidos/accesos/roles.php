<?php
/**
 * Gestión de Roles
 * Sequoia Speed - Sistema de Accesos
 */

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once dirname(__DIR__) . '/config_secure.php';
require_once dirname(__DIR__) . '/php82_helpers.php';
require_once 'middleware/AuthMiddleware.php';
require_once 'models/Role.php';
require_once 'models/Permission.php';

// Inicializar middleware y requerir permisos
$auth = new AuthMiddleware($conn);
$current_user = $auth->requirePermission('usuarios', 'leer');

// Inicializar modelos
$role_model = new Role($conn);
$permission_model = new Permission($conn);

// Obtener roles con estadísticas
$roles = $role_model->getRolesWithStats();

// Obtener estadísticas generales
$stats_query = "SELECT 
    COUNT(*) as total_roles,
    SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as activos,
    SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END) as inactivos
FROM acc_roles";

$stats_result = $conn->query($stats_query);
$estadisticas = $stats_result->fetch_assoc();

// Función para obtener icono del rol
function get_role_icon($role_name) {
    $icons = [
        'super_admin' => '👑',
        'admin' => '👨‍💼',
        'gerente' => '👔',
        'supervisor' => '👨‍🔧',
        'vendedor' => '🛒',
        'consultor' => '🔍'
    ];
    
    return $icons[$role_name] ?? '🎭';
}

// Función para obtener descripción del nivel
function get_role_level($role_name) {
    $levels = [
        'super_admin' => ['level' => 1, 'name' => 'Nivel 1 - Máximo'],
        'admin' => ['level' => 2, 'name' => 'Nivel 2 - Alto'],
        'gerente' => ['level' => 3, 'name' => 'Nivel 3 - Medio-Alto'],
        'supervisor' => ['level' => 4, 'name' => 'Nivel 4 - Medio'],
        'vendedor' => ['level' => 5, 'name' => 'Nivel 5 - Básico'],
        'consultor' => ['level' => 6, 'name' => 'Nivel 6 - Consulta']
    ];
    
    return $levels[$role_name] ?? ['level' => 7, 'name' => 'Personalizado'];
}

// Función para formatear fecha
function formatear_fecha($fecha) {
    if (!$fecha) return 'No disponible';
    return date('d/m/Y H:i', strtotime($fecha));
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎭 Gestión de Roles - Sequoia Speed</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎭</text></svg>">
    <link rel="stylesheet" href="../inventario/productos.css">
    <link rel="stylesheet" href="../notifications/notifications.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">🎭 Gestión de Roles</h1>
                    <div class="breadcrumb">
                        <a href="../listar_pedidos.php">🏠 Inicio</a>
                        <span>/</span>
                        <a href="dashboard.php">🔐 Accesos</a>
                        <span>/</span>
                        <span>🎭 Roles</span>
                    </div>
                </div>
                <div class="header-actions">
                    <?php if ($auth->hasPermission('acc_usuarios', 'crear')): ?>
                        <a href="rol_crear.php" class="btn btn-primary">
                            ➕ Nuevo Rol
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
                <div class="stat-icon">🎭</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($estadisticas['total_roles']); ?></div>
                    <div class="stat-label">Total Roles</div>
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
        </div>

        <!-- Información sobre jerarquía -->
        <div class="info-section" style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--space-lg); margin-bottom: var(--space-lg);">
            <h3 style="color: var(--text-primary); margin-bottom: var(--space-md); display: flex; align-items: center; gap: var(--space-sm);">
                📋 Jerarquía de Roles
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--space-md);">
                <?php 
                $hierarchy = $role_model->getRoleHierarchy();
                foreach ($hierarchy as $role_name => $info): 
                ?>
                    <div class="hierarchy-item" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--space-md);">
                        <div style="display: flex; align-items: center; gap: var(--space-sm); margin-bottom: var(--space-sm);">
                            <span style="font-size: 24px;"><?php echo get_role_icon($role_name); ?></span>
                            <div>
                                <div style="font-weight: 600; color: var(--text-primary);">
                                    <?php echo htmlspecialchars($info['name']); ?>
                                </div>
                                <div style="font-size: 12px; color: var(--text-secondary);">
                                    <?php echo get_role_level($role_name)['name']; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Tabla de roles -->
        <div class="table-section">
            <div class="table-container">
                <?php if (empty($roles)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">🎭</div>
                        <div class="empty-title">No hay roles configurados</div>
                        <div class="empty-subtitle">
                            Crea el primer rol para comenzar
                        </div>
                        <?php if ($auth->hasPermission('acc_usuarios', 'crear')): ?>
                            <a href="rol_crear.php" class="btn btn-primary">
                                ➕ Crear Primer Rol
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <table class="productos-table">
                        <thead>
                            <tr>
                                <th>Rol</th>
                                <th>Descripción</th>
                                <th>Usuarios</th>
                                <th>Permisos</th>
                                <th>Estado</th>
                                <th>Fecha Creación</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roles as $role): ?>
                                <tr class="<?php echo $role['activo'] ? '' : 'inactive'; ?>">
                                    <td class="producto-info">
                                        <div style="display: flex; align-items: center; gap: var(--space-sm);">
                                            <span style="font-size: 24px;"><?php echo get_role_icon($role['nombre']); ?></span>
                                            <div>
                                                <div class="producto-nombre">
                                                    <?php echo htmlspecialchars($role['nombre']); ?>
                                                </div>
                                                <div class="producto-sku">
                                                    <?php echo get_role_level($role['nombre'])['name']; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size: 13px; color: var(--text-primary); max-width: 200px;">
                                            <?php echo htmlspecialchars($role['descripcion']); ?>
                                        </div>
                                    </td>
                                    <td class="stock-cell">
                                        <div style="display: flex; align-items: center; gap: var(--space-sm);">
                                            <span style="font-size: 20px;">👥</span>
                                            <div>
                                                <div class="stock-valor">
                                                    <?php echo number_format($role['total_usuarios']); ?>
                                                </div>
                                                <div class="stock-info">
                                                    usuarios
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="stock-cell">
                                        <div style="display: flex; align-items: center; gap: var(--space-sm);">
                                            <span style="font-size: 20px;">🔐</span>
                                            <div>
                                                <div class="stock-valor">
                                                    <?php echo number_format($role['total_permisos']); ?>
                                                </div>
                                                <div class="stock-info">
                                                    permisos
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="stock-cell">
                                        <?php if ($role['activo']): ?>
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
                                            <?php echo formatear_fecha($role['fecha_creacion']); ?>
                                        </div>
                                    </td>
                                    <td class="acciones-cell">
                                        <div class="acciones-group">
                                            <button onclick="verDetalleRol(<?php echo $role['id']; ?>)" 
                                                    class="btn-accion btn-ver" 
                                                    title="Ver detalles">
                                                👁️
                                            </button>
                                            
                                            <?php if ($auth->hasPermission('acc_usuarios', 'actualizar')): ?>
                                                <a href="rol_editar.php?id=<?php echo $role['id']; ?>" 
                                                   class="btn-accion btn-editar" 
                                                   title="Editar rol">
                                                    ✏️
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($auth->hasPermission('acc_usuarios', 'actualizar')): ?>
                                                <a href="rol_permisos.php?id=<?php echo $role['id']; ?>" 
                                                   class="btn-accion btn-info" 
                                                   title="Gestionar permisos">
                                                    🔐
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($auth->hasPermission('acc_usuarios', 'actualizar')): ?>
                                                <?php if ($role['activo']): ?>
                                                    <button onclick="toggleRolEstado(<?php echo $role['id']; ?>, 'desactivar')" 
                                                            class="btn-accion btn-desactivar" 
                                                            title="Desactivar rol">
                                                        ❌
                                                    </button>
                                                <?php else: ?>
                                                    <button onclick="toggleRolEstado(<?php echo $role['id']; ?>, 'activar')" 
                                                            class="btn-accion btn-activar" 
                                                            title="Activar rol">
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
        </div>
    </div>

    <!-- Modal para detalles de rol -->
    <div id="roleModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>🎭 Detalles del Rol</h3>
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
        // Ver detalles de rol
        function verDetalleRol(id) {
            mostrarIndicadorCarga();
            
            fetch(`rol_detalle.php?id=${id}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('modalBody').innerHTML = html;
                document.getElementById('roleModal').style.display = 'flex';
                ocultarIndicadorCarga();
            })
            .catch(error => {
                console.error('Error:', error);
                ocultarIndicadorCarga();
                mostrarNotificacion('Error al cargar los detalles', 'error');
            });
        }

        // Cambiar estado de rol
        function toggleRolEstado(id, accion) {
            const mensaje = accion === 'activar' ? 
                '¿Estás seguro de que quieres activar este rol?' : 
                '¿Estás seguro de que quieres desactivar este rol?';
            
            if (confirm(mensaje)) {
                mostrarIndicadorCarga();
                
                fetch('rol_toggle.php', {
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
            document.getElementById('roleModal').style.display = 'none';
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('roleModal').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModal();
            }
        });

        // Shortcut para crear rol
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                <?php if ($auth->hasPermission('acc_usuarios', 'crear')): ?>
                    window.location.href = 'rol_crear.php';
                <?php endif; ?>
            }
        });

        // Añadir efectos hover a las hierarchy items
        document.querySelectorAll('.hierarchy-item').forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = 'var(--shadow-md)';
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = 'none';
            });
        });
    </script>
</body>
</html>