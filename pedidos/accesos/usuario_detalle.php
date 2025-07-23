<?php
/**
 * Detalles de Usuario (AJAX)
 * Sequoia Speed - Sistema de Accesos
 */

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once dirname(__DIR__) . '/config_secure.php';
require_once dirname(__DIR__) . '/php82_helpers.php';
require_once 'middleware/AuthMiddleware.php';
require_once 'models/User.php';

// Inicializar middleware y requerir permisos
$auth = new AuthMiddleware($conn);
$current_user = $auth->requirePermission('usuarios', 'leer');

// Obtener ID del usuario
$user_id = intval($_GET['id'] ?? 0);

if ($user_id <= 0) {
    echo '<div class="alert alert-error">ID de usuario inválido</div>';
    exit;
}

try {
    $user_model = new User($conn);
    
    // Obtener información del usuario
    $usuario = $user_model->findById($user_id);
    if (!$usuario) {
        echo '<div class="alert alert-error">Usuario no encontrado</div>';
        exit;
    }
    
    // Obtener roles del usuario
    $roles = $user_model->getUserRoles($user_id);
    
    // Obtener permisos del usuario
    $permisos = $user_model->getUserPermissions($user_id);
    
    // Obtener sesiones activas
    $sesiones = $auth->getActiveSessions($user_id);
    
    // Obtener actividad reciente
    $query_actividad = "SELECT 
        accion, modulo, descripcion, fecha_accion, ip_address
    FROM auditoria_accesos 
    WHERE usuario_id = ? 
    ORDER BY fecha_accion DESC 
    LIMIT 10";
    
    $stmt = $conn->prepare($query_actividad);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $actividad = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
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
    
    // Agrupar permisos por módulo
    $permisos_agrupados = [];
    foreach ($permisos as $permiso) {
        $permisos_agrupados[$permiso['modulo']][] = $permiso;
    }
    
} catch (Exception $e) {
    echo '<div class="alert alert-error">Error al cargar los datos: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>

<div class="user-details">
    <!-- Información básica -->
    <div class="detail-section">
        <h4 style="color: var(--text-primary); margin-bottom: var(--space-md); display: flex; align-items: center; gap: var(--space-sm);">
            👤 Información Personal
        </h4>
        <div class="info-grid">
            <div class="info-item">
                <strong>ID:</strong>
                <span><?php echo $usuario['id']; ?></span>
            </div>
            <div class="info-item">
                <strong>Nombre:</strong>
                <span><?php echo htmlspecialchars($usuario['nombre']); ?></span>
            </div>
            <div class="info-item">
                <strong>Email:</strong>
                <span><?php echo htmlspecialchars($usuario['email']); ?></span>
            </div>
            <div class="info-item">
                <strong>Estado:</strong>
                <span>
                    <?php if ($usuario['activo']): ?>
                        <span class="badge-stock stock-alto">✅ Activo</span>
                    <?php else: ?>
                        <span class="badge-stock stock-bajo">❌ Inactivo</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <strong>Último acceso:</strong>
                <span><?php echo tiempo_relativo($usuario['ultimo_acceso']); ?></span>
            </div>
            <div class="info-item">
                <strong>Fecha creación:</strong>
                <span><?php echo formatear_fecha($usuario['fecha_creacion']); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Roles -->
    <div class="detail-section" style="margin-top: var(--space-lg);">
        <h4 style="color: var(--text-primary); margin-bottom: var(--space-md); display: flex; align-items: center; gap: var(--space-sm);">
            🎭 Roles Asignados
        </h4>
        <?php if (empty($roles)): ?>
            <div class="empty-state" style="padding: var(--space-md); text-align: center;">
                <div style="color: var(--text-secondary);">No tiene roles asignados</div>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-wrap: wrap; gap: var(--space-sm);">
                <?php foreach ($roles as $role): ?>
                    <div class="role-detail" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--space-md);">
                        <div style="font-weight: 500; color: var(--text-primary); margin-bottom: var(--space-xs);">
                            <?php echo htmlspecialchars($role['nombre']); ?>
                        </div>
                        <div style="font-size: 12px; color: var(--text-secondary);">
                            <?php echo htmlspecialchars($role['descripcion']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Permisos -->
    <div class="detail-section" style="margin-top: var(--space-lg);">
        <h4 style="color: var(--text-primary); margin-bottom: var(--space-md); display: flex; align-items: center; gap: var(--space-sm);">
            🔐 Permisos
        </h4>
        <?php if (empty($permisos_agrupados)): ?>
            <div class="empty-state" style="padding: var(--space-md); text-align: center;">
                <div style="color: var(--text-secondary);">No tiene permisos asignados</div>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--space-md);">
                <?php foreach ($permisos_agrupados as $modulo => $permisos_modulo): ?>
                    <div class="module-permissions" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--space-md);">
                        <div style="font-weight: 500; color: var(--text-primary); margin-bottom: var(--space-sm);">
                            📦 <?php echo htmlspecialchars($modulo); ?>
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: var(--space-xs);">
                            <?php foreach ($permisos_modulo as $permiso): ?>
                                <span class="badge-tipo badge-info" style="font-size: 10px; padding: 2px 6px;">
                                    <?php echo htmlspecialchars($permiso['tipo_permiso']); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Sesiones activas -->
    <div class="detail-section" style="margin-top: var(--space-lg);">
        <h4 style="color: var(--text-primary); margin-bottom: var(--space-md); display: flex; align-items: center; gap: var(--space-sm);">
            🔥 Sesiones Activas
        </h4>
        <?php if (empty($sesiones)): ?>
            <div class="empty-state" style="padding: var(--space-md); text-align: center;">
                <div style="color: var(--text-secondary);">No hay sesiones activas</div>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: var(--space-sm);">
                <?php foreach ($sesiones as $sesion): ?>
                    <div class="session-item" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--space-md);">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div style="flex: 1;">
                                <div style="font-weight: 500; color: var(--text-primary); margin-bottom: var(--space-xs);">
                                    📱 <?php echo htmlspecialchars($sesion['ip_address']); ?>
                                </div>
                                <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: var(--space-xs);">
                                    <?php echo htmlspecialchars(substr($sesion['user_agent'], 0, 60)); ?>...
                                </div>
                                <div style="font-size: 11px; color: var(--text-muted);">
                                    Inicio: <?php echo formatear_fecha($sesion['fecha_inicio']); ?><br>
                                    Expira: <?php echo formatear_fecha($sesion['fecha_expiracion']); ?>
                                </div>
                            </div>
                            <?php if ($auth->hasPermission('usuarios', 'actualizar') && $user_id != $current_user['id']): ?>
                                <button onclick="cerrarSesion(<?php echo $sesion['id']; ?>)" 
                                        class="btn-accion btn-desactivar" 
                                        style="margin-left: var(--space-sm);"
                                        title="Cerrar sesión">
                                    🚪
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Actividad reciente -->
    <div class="detail-section" style="margin-top: var(--space-lg);">
        <h4 style="color: var(--text-primary); margin-bottom: var(--space-md); display: flex; align-items: center; gap: var(--space-sm);">
            📊 Actividad Reciente
        </h4>
        <?php if (empty($actividad)): ?>
            <div class="empty-state" style="padding: var(--space-md); text-align: center;">
                <div style="color: var(--text-secondary);">No hay actividad reciente</div>
            </div>
        <?php else: ?>
            <div style="max-height: 300px; overflow-y: auto;">
                <?php foreach ($actividad as $accion): ?>
                    <div class="activity-item" style="display: flex; justify-content: space-between; align-items: start; padding: var(--space-sm) 0; border-bottom: 1px solid var(--border-color);">
                        <div style="flex: 1;">
                            <div style="font-weight: 500; color: var(--text-primary); margin-bottom: var(--space-xs);">
                                <?php echo htmlspecialchars($accion['accion']); ?>
                            </div>
                            <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: var(--space-xs);">
                                📦 <?php echo htmlspecialchars($accion['modulo']); ?>
                            </div>
                            <?php if (!empty($accion['descripcion'])): ?>
                                <div style="font-size: 11px; color: var(--text-muted);">
                                    <?php echo htmlspecialchars($accion['descripcion']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 11px; color: var(--text-secondary);">
                                <?php echo tiempo_relativo($accion['fecha_accion']); ?>
                            </div>
                            <div style="font-size: 10px; color: var(--text-muted);">
                                <?php echo htmlspecialchars($accion['ip_address']); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Acciones -->
    <?php if ($auth->hasPermission('usuarios', 'actualizar')): ?>
        <div class="detail-actions" style="margin-top: var(--space-lg); padding-top: var(--space-lg); border-top: 2px solid var(--border-color); display: flex; gap: var(--space-md); justify-content: center;">
            <a href="usuario_editar.php?id=<?php echo $usuario['id']; ?>" class="btn btn-primary">
                ✏️ Editar Usuario
            </a>
            
            <?php if ($user_id != $current_user['id']): ?>
                <?php if ($usuario['activo']): ?>
                    <button onclick="toggleUsuarioEstado(<?php echo $usuario['id']; ?>, 'desactivar')" class="btn btn-warning">
                        ❌ Desactivar
                    </button>
                <?php else: ?>
                    <button onclick="toggleUsuarioEstado(<?php echo $usuario['id']; ?>, 'activar')" class="btn btn-success">
                        ✅ Activar
                    </button>
                <?php endif; ?>
                
                <button onclick="cerrarTodasSesiones(<?php echo $usuario['id']; ?>)" class="btn btn-secondary">
                    🚪 Cerrar Todas las Sesiones
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Función para cerrar sesión específica
function cerrarSesion(sessionId) {
    if (confirm('¿Cerrar esta sesión?')) {
        fetch('cerrar_sesion.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `session_id=${sessionId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarNotificacion('Sesión cerrada', 'success');
                verDetalleUsuario(<?php echo $user_id; ?>); // Recargar detalles
            } else {
                mostrarNotificacion(data.error || 'Error al cerrar sesión', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarNotificacion('Error de conexión', 'error');
        });
    }
}

// Función para cerrar todas las sesiones
function cerrarTodasSesiones(userId) {
    if (confirm('¿Cerrar todas las sesiones de este usuario?')) {
        fetch('cerrar_todas_sesiones.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `user_id=${userId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarNotificacion('Todas las sesiones cerradas', 'success');
                verDetalleUsuario(userId); // Recargar detalles
            } else {
                mostrarNotificacion(data.error || 'Error al cerrar sesiones', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarNotificacion('Error de conexión', 'error');
        });
    }
}
</script>

<style>
.detail-section {
    margin-bottom: var(--space-lg);
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--space-md);
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: var(--space-xs);
}

.info-item strong {
    font-size: 12px;
    color: var(--text-secondary);
}

.info-item span {
    font-size: 14px;
    color: var(--text-primary);
    font-weight: 500;
}

.user-details {
    padding: var(--space-md);
}

.activity-item:last-child {
    border-bottom: none;
}

.session-item:hover {
    background: var(--bg-hover);
}

.activity-item:hover {
    background: rgba(88, 166, 255, 0.05);
}
</style>