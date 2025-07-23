<?php
/**
 * Dashboard del Sistema de Accesos
 * Sequoia Speed - Sistema de Accesos
 */

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once dirname(__DIR__) . '/config_secure.php';
require_once dirname(__DIR__) . '/php82_helpers.php';
require_once 'middleware/AuthMiddleware.php';
require_once 'models/User.php';
require_once 'models/Role.php';
require_once 'models/Module.php';

// Inicializar middleware y requerir autenticación
$auth = new AuthMiddleware($conn);
$current_user = $auth->requireAuth();

// Obtener información de sesión
$session_info = $auth->getSessionInfo();
$user_roles = $session_info['roles'];
$user_permissions = $session_info['permissions'];

// Inicializar modelos
$user_model = new User($conn);
$role_model = new Role($conn);
$module_model = new Module($conn);

// Obtener estadísticas del sistema
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM usuarios WHERE activo = 1) as total_usuarios,
    (SELECT COUNT(*) FROM roles WHERE activo = 1) as total_roles,
    (SELECT COUNT(*) FROM modulos WHERE activo = 1) as total_modulos,
    (SELECT COUNT(*) FROM sesiones WHERE activa = 1 AND fecha_expiracion > NOW()) as sesiones_activas,
    (SELECT COUNT(*) FROM auditoria_accesos WHERE DATE(fecha_accion) = CURDATE()) as acciones_hoy";

$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Obtener actividad reciente
$recent_activity_query = "SELECT 
    u.nombre as usuario_nombre,
    aa.accion,
    aa.modulo,
    aa.descripcion,
    aa.fecha_accion,
    aa.ip_address
FROM auditoria_accesos aa
INNER JOIN usuarios u ON aa.usuario_id = u.id
ORDER BY aa.fecha_accion DESC
LIMIT 10";

$recent_activity = $conn->query($recent_activity_query)->fetch_all(MYSQLI_ASSOC);

// Obtener resumen de módulos
$module_summary = $module_model->getAccessSummary($current_user['id']);

// Obtener usuarios recientes
$recent_users_query = "SELECT 
    nombre, email, ultimo_acceso, fecha_creacion
FROM usuarios 
WHERE activo = 1 
ORDER BY fecha_creacion DESC 
LIMIT 5";

$recent_users = $conn->query($recent_users_query)->fetch_all(MYSQLI_ASSOC);

// Función para formatear fecha
function formatear_fecha($fecha) {
    if (!$fecha) return 'Nunca';
    return date('d/m/Y H:i', strtotime($fecha));
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
        'delete' => ['🗑️', 'Eliminar', 'danger']
    ];
    
    return $acciones[$accion] ?? ['📝', ucfirst($accion), 'secondary'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏠 Dashboard - Sistema de Accesos</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏠</text></svg>">
    <link rel="stylesheet" href="../inventario/productos.css">
    <link rel="stylesheet" href="../notifications/notifications.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">🏠 Dashboard - Sistema de Accesos</h1>
                    <div class="breadcrumb">
                        <a href="../listar_pedidos.php">🏠 Inicio</a>
                        <span>/</span>
                        <span>🔐 Accesos</span>
                        <span>/</span>
                        <span>Dashboard</span>
                    </div>
                </div>
                <div class="header-actions">
                    <span class="user-info">
                        👤 <?php echo htmlspecialchars($current_user['nombre']); ?>
                    </span>
                    <a href="logout.php" class="btn btn-secondary">
                        🚪 Cerrar Sesión
                    </a>
                </div>
            </div>
        </header>

        <!-- Información del usuario -->
        <div class="user-info-section" style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--space-lg); margin-bottom: var(--space-lg);">
            <div style="display: flex; align-items: center; gap: var(--space-lg); flex-wrap: wrap;">
                <div style="flex: 1;">
                    <h3 style="color: var(--text-primary); margin-bottom: var(--space-sm);">
                        👤 <?php echo htmlspecialchars($current_user['nombre']); ?>
                    </h3>
                    <p style="color: var(--text-secondary); margin-bottom: var(--space-sm);">
                        📧 <?php echo htmlspecialchars($current_user['email']); ?>
                    </p>
                    <p style="color: var(--text-secondary); font-size: 12px;">
                        🕐 Último acceso: <?php echo formatear_fecha($current_user['ultimo_acceso']); ?>
                    </p>
                </div>
                <div>
                    <strong style="color: var(--text-primary); display: block; margin-bottom: var(--space-sm);">
                        🎭 Roles:
                    </strong>
                    <?php foreach ($user_roles as $role): ?>
                        <span class="badge-categoria" style="margin-right: var(--space-sm); margin-bottom: var(--space-xs);">
                            <?php echo htmlspecialchars($role['nombre']); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="stats-section">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stats['total_usuarios']); ?></div>
                    <div class="stat-label">Usuarios Activos</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🎭</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stats['total_roles']); ?></div>
                    <div class="stat-label">Roles</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stats['total_modulos']); ?></div>
                    <div class="stat-label">Módulos</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🔥</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stats['sesiones_activas']); ?></div>
                    <div class="stat-label">Sesiones Activas</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stats['acciones_hoy']); ?></div>
                    <div class="stat-label">Acciones Hoy</div>
                </div>
            </div>
        </div>

        <!-- Accesos rápidos -->
        <div class="quick-access-section" style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--space-lg); margin-bottom: var(--space-lg);">
            <h3 style="color: var(--text-primary); margin-bottom: var(--space-lg);">🚀 Accesos Rápidos</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-md);">
                
                <?php if ($auth->hasAnyPermission('usuarios', ['leer'])): ?>
                <a href="usuarios.php" class="quick-link" style="display: flex; align-items: center; gap: var(--space-md); padding: var(--space-lg); background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius); text-decoration: none; color: var(--text-primary); transition: all var(--transition-fast);">
                    <div style="font-size: 32px;">👥</div>
                    <div>
                        <div style="font-weight: 600; margin-bottom: var(--space-xs);">Usuarios</div>
                        <div style="font-size: 12px; color: var(--text-secondary);">Gestionar usuarios</div>
                    </div>
                </a>
                <?php endif; ?>
                
                <?php if ($auth->hasAnyPermission('usuarios', ['leer'])): ?>
                <a href="roles.php" class="quick-link" style="display: flex; align-items: center; gap: var(--space-md); padding: var(--space-lg); background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius); text-decoration: none; color: var(--text-primary); transition: all var(--transition-fast);">
                    <div style="font-size: 32px;">🎭</div>
                    <div>
                        <div style="font-weight: 600; margin-bottom: var(--space-xs);">Roles</div>
                        <div style="font-size: 12px; color: var(--text-secondary);">Administrar roles</div>
                    </div>
                </a>
                <?php endif; ?>
                
                <?php if ($auth->hasAnyPermission('usuarios', ['leer'])): ?>
                <a href="permisos.php" class="quick-link" style="display: flex; align-items: center; gap: var(--space-md); padding: var(--space-lg); background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius); text-decoration: none; color: var(--text-primary); transition: all var(--transition-fast);">
                    <div style="font-size: 32px;">🔐</div>
                    <div>
                        <div style="font-weight: 600; margin-bottom: var(--space-xs);">Permisos</div>
                        <div style="font-size: 12px; color: var(--text-secondary);">Configurar permisos</div>
                    </div>
                </a>
                <?php endif; ?>
                
                <?php if ($auth->hasAnyPermission('usuarios', ['leer'])): ?>
                <a href="auditoria.php" class="quick-link" style="display: flex; align-items: center; gap: var(--space-md); padding: var(--space-lg); background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius); text-decoration: none; color: var(--text-primary); transition: all var(--transition-fast);">
                    <div style="font-size: 32px;">📋</div>
                    <div>
                        <div style="font-weight: 600; margin-bottom: var(--space-xs);">Auditoría</div>
                        <div style="font-size: 12px; color: var(--text-secondary);">Revisar actividad</div>
                    </div>
                </a>
                <?php endif; ?>
                
                <a href="../listar_pedidos.php" class="quick-link" style="display: flex; align-items: center; gap: var(--space-md); padding: var(--space-lg); background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius); text-decoration: none; color: var(--text-primary); transition: all var(--transition-fast);">
                    <div style="font-size: 32px;">🛒</div>
                    <div>
                        <div style="font-weight: 600; margin-bottom: var(--space-xs);">Ventas</div>
                        <div style="font-size: 12px; color: var(--text-secondary);">Sistema principal</div>
                    </div>
                </a>
                
                <a href="../inventario/productos.php" class="quick-link" style="display: flex; align-items: center; gap: var(--space-md); padding: var(--space-lg); background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius); text-decoration: none; color: var(--text-primary); transition: all var(--transition-fast);">
                    <div style="font-size: 32px;">📦</div>
                    <div>
                        <div style="font-weight: 600; margin-bottom: var(--space-xs);">Inventario</div>
                        <div style="font-size: 12px; color: var(--text-secondary);">Gestión de productos</div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Contenido principal en dos columnas -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: var(--space-lg);">
            <!-- Actividad reciente -->
            <div class="table-section">
                <h3 style="color: var(--text-primary); margin-bottom: var(--space-lg); padding: var(--space-md);">
                    📊 Actividad Reciente
                </h3>
                <div class="table-container">
                    <?php if (empty($recent_activity)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📊</div>
                            <div class="empty-title">No hay actividad reciente</div>
                            <div class="empty-subtitle">Las acciones de los usuarios aparecerán aquí</div>
                        </div>
                    <?php else: ?>
                        <table class="productos-table">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Acción</th>
                                    <th>Módulo</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_activity as $activity): ?>
                                    <?php $accion_info = formatear_accion($activity['accion']); ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 500; color: var(--text-primary);">
                                                <?php echo htmlspecialchars($activity['usuario_nombre']); ?>
                                            </div>
                                            <div style="font-size: 10px; color: var(--text-secondary);">
                                                <?php echo htmlspecialchars($activity['ip_address']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge-tipo badge-<?php echo $accion_info[2]; ?>">
                                                <?php echo $accion_info[0]; ?> <?php echo $accion_info[1]; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge-categoria">
                                                <?php echo htmlspecialchars($activity['modulo']); ?>
                                            </span>
                                        </td>
                                        <td class="fecha-cell">
                                            <div class="fecha">
                                                <?php echo formatear_fecha($activity['fecha_accion']); ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Panel lateral -->
            <div>
                <!-- Mis permisos -->
                <div class="table-section" style="margin-bottom: var(--space-lg);">
                    <h3 style="color: var(--text-primary); margin-bottom: var(--space-lg); padding: var(--space-md);">
                        🔐 Mis Permisos
                    </h3>
                    <div style="padding: var(--space-md);">
                        <?php foreach ($module_summary as $module): ?>
                            <?php if ($module['has_access']): ?>
                                <div style="margin-bottom: var(--space-md);">
                                    <div style="display: flex; align-items: center; gap: var(--space-sm); margin-bottom: var(--space-xs);">
                                        <span style="font-size: 16px;"><?php echo $module['icon']; ?></span>
                                        <strong style="color: var(--text-primary); font-size: 13px;">
                                            <?php echo htmlspecialchars($module['name']); ?>
                                        </strong>
                                    </div>
                                    <div style="display: flex; flex-wrap: wrap; gap: var(--space-xs);">
                                        <?php foreach ($module['user_permissions'] as $permission): ?>
                                            <span class="badge-tipo badge-info" style="font-size: 10px; padding: 2px 6px;">
                                                <?php echo htmlspecialchars($permission); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Usuarios recientes -->
                <div class="table-section">
                    <h3 style="color: var(--text-primary); margin-bottom: var(--space-lg); padding: var(--space-md);">
                        👥 Usuarios Recientes
                    </h3>
                    <div style="padding: var(--space-md);">
                        <?php foreach ($recent_users as $user): ?>
                            <div style="display: flex; align-items: center; gap: var(--space-md); padding: var(--space-md) 0; border-bottom: 1px solid var(--border-color);">
                                <div style="flex: 1;">
                                    <div style="font-weight: 500; color: var(--text-primary); margin-bottom: var(--space-xs);">
                                        <?php echo htmlspecialchars($user['nombre']); ?>
                                    </div>
                                    <div style="font-size: 12px; color: var(--text-secondary);">
                                        <?php echo htmlspecialchars($user['email']); ?>
                                    </div>
                                    <div style="font-size: 10px; color: var(--text-muted);">
                                        Último acceso: <?php echo formatear_fecha($user['ultimo_acceso']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sistema de notificaciones -->
    <div id="notification-container"></div>

    <script src="../inventario/productos.js"></script>
    <script>
        // Agregar hover effects a los quick links
        document.querySelectorAll('.quick-link').forEach(link => {
            link.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = 'var(--shadow-md)';
            });
            
            link.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = 'none';
            });
        });

        // Actualizar actividad cada 30 segundos
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>