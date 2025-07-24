<?php
/**
 * Roles con Permiso Específico (AJAX)
 * Sequoia Speed - Sistema de Accesos
 */

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once dirname(__DIR__) . '/config_secure.php';
require_once dirname(__DIR__) . '/php82_helpers.php';
require_once 'middleware/AuthMiddleware.php';
require_once 'models/Permission.php';

// Inicializar middleware y requerir permisos
$auth = new AuthMiddleware($conn);
$current_user = $auth->requirePermission('acc_usuarios', 'leer');

// Obtener ID del permiso
$permiso_id = intval($_GET['id'] ?? 0);

if ($permiso_id <= 0) {
    echo '<div class="alert alert-error">ID de permiso inválido</div>';
    exit;
}

try {
    $permission_model = new Permission($conn);
    
    // Obtener información del permiso
    $permiso = $permission_model->findById($permiso_id);
    if (!$permiso) {
        echo '<div class="alert alert-error">Permiso no encontrado</div>';
        exit;
    }
    
    // Obtener roles con este permiso
    $roles = $permission_model->getPermissionRoles($permiso_id);
    
    // Función para formatear fecha
    function formatear_fecha($fecha) {
        if (!$fecha) return 'No disponible';
        return date('d/m/Y H:i', strtotime($fecha));
    }
    
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
            'super_admin' => 'Nivel 1 - Máximo',
            'admin' => 'Nivel 2 - Alto',
            'gerente' => 'Nivel 3 - Medio-Alto',
            'supervisor' => 'Nivel 4 - Medio',
            'vendedor' => 'Nivel 5 - Básico',
            'consultor' => 'Nivel 6 - Consulta'
        ];
        
        return $levels[$role_name] ?? 'Personalizado';
    }
    
    // Función para formatear tipo de permiso
    function formatear_permiso($tipo) {
        $tipos = [
            'leer' => ['icon' => '👁️', 'name' => 'Leer', 'color' => 'info'],
            'crear' => ['icon' => '➕', 'name' => 'Crear', 'color' => 'success'],
            'actualizar' => ['icon' => '✏️', 'name' => 'Actualizar', 'color' => 'warning'],
            'eliminar' => ['icon' => '🗑️', 'name' => 'Eliminar', 'color' => 'danger']
        ];
        
        return $tipos[$tipo] ?? ['icon' => '❓', 'name' => ucfirst($tipo), 'color' => 'secondary'];
    }
    
    $permiso_info = formatear_permiso($permiso['tipo_permiso']);
    
} catch (Exception $e) {
    echo '<div class="alert alert-error">Error al cargar los datos: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>

<div class="permission-roles-detail">
    <!-- Información del permiso -->
    <div class="permission-info" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--space-lg); margin-bottom: var(--space-lg);">
        <div style="display: flex; align-items: center; gap: var(--space-md);">
            <div style="font-size: 48px;">
                <?php echo $permiso_info['icon']; ?>
            </div>
            <div style="flex: 1;">
                <h4 style="color: var(--text-primary); margin-bottom: var(--space-sm); display: flex; align-items: center; gap: var(--space-sm);">
                    📦 <?php echo htmlspecialchars($permiso['modulo_nombre']); ?>
                    <span style="font-size: 16px; color: var(--text-secondary);">></span>
                    <?php echo $permiso_info['name']; ?>
                </h4>
                <p style="color: var(--text-secondary); margin-bottom: var(--space-sm);">
                    <?php echo htmlspecialchars($permiso['descripcion']); ?>
                </p>
                <div style="font-size: 12px; color: var(--text-muted);">
                    ID: <?php echo $permiso['id']; ?> | 
                    Módulo: <?php echo htmlspecialchars($permiso['modulo_descripcion']); ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Roles con este permiso -->
    <div class="roles-list">
        <h4 style="color: var(--text-primary); margin-bottom: var(--space-lg); display: flex; align-items: center; gap: var(--space-sm);">
            🎭 Roles con este Permiso
            <span style="background: var(--color-primary); color: white; padding: 2px 8px; border-radius: 10px; font-size: 12px;">
                <?php echo count($roles); ?>
            </span>
        </h4>
        
        <?php if (empty($roles)): ?>
            <div class="empty-state" style="padding: var(--space-xl); text-align: center; background: var(--bg-tertiary); border-radius: var(--border-radius);">
                <div style="font-size: 48px; margin-bottom: var(--space-md); opacity: 0.5;">🎭</div>
                <div style="color: var(--text-primary); font-size: 16px; margin-bottom: var(--space-sm);">
                    No hay roles asignados
                </div>
                <div style="color: var(--text-secondary); font-size: 14px;">
                    Ningún rol tiene actualmente este permiso
                </div>
            </div>
        <?php else: ?>
            <div class="roles-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-md);">
                <?php foreach ($roles as $role): ?>
                    <div class="role-card" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--space-lg); position: relative; transition: all var(--transition-fast);">
                        <div style="display: flex; align-items: center; gap: var(--space-md); margin-bottom: var(--space-md);">
                            <div style="font-size: 32px;">
                                <?php echo get_role_icon($role['nombre']); ?>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: var(--text-primary); margin-bottom: var(--space-xs);">
                                    <?php echo htmlspecialchars($role['nombre']); ?>
                                </div>
                                <div style="font-size: 12px; color: var(--text-secondary);">
                                    <?php echo get_role_level($role['nombre']); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: var(--space-md);">
                            <div style="font-size: 13px; color: var(--text-secondary); line-height: 1.4;">
                                <?php echo htmlspecialchars($role['descripcion']); ?>
                            </div>
                        </div>
                        
                        <div class="role-meta" style="display: flex; justify-content: space-between; align-items: center; padding-top: var(--space-md); border-top: 1px solid var(--border-color);">
                            <div style="font-size: 11px; color: var(--text-muted);">
                                Asignado: <?php echo formatear_fecha($role['fecha_asignacion']); ?>
                            </div>
                            <div style="display: flex; gap: var(--space-sm);">
                                <button onclick="verDetalleRol(<?php echo $role['id']; ?>)" 
                                        class="btn-mini btn-info" 
                                        style="padding: 4px 8px; font-size: 11px; border-radius: 4px; background: var(--color-info); color: white; border: none; cursor: pointer;"
                                        title="Ver detalles del rol">
                                    👁️
                                </button>
                                <?php if ($auth->hasPermission('acc_usuarios', 'actualizar')): ?>
                                    <a href="rol_editar.php?id=<?php echo $role['id']; ?>" 
                                       class="btn-mini btn-warning" 
                                       style="padding: 4px 8px; font-size: 11px; border-radius: 4px; background: var(--color-warning); color: white; text-decoration: none; display: inline-block;"
                                       title="Editar rol">
                                        ✏️
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Estadísticas -->
    <div class="permission-stats" style="margin-top: var(--space-lg); padding-top: var(--space-lg); border-top: 2px solid var(--border-color);">
        <h4 style="color: var(--text-primary); margin-bottom: var(--space-md); display: flex; align-items: center; gap: var(--space-sm);">
            📊 Estadísticas del Permiso
        </h4>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: var(--space-md);">
            <div class="stat-item" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--space-md); text-align: center;">
                <div style="font-size: 24px; margin-bottom: var(--space-sm);">🎭</div>
                <div style="font-size: 20px; font-weight: 600; color: var(--text-primary);">
                    <?php echo count($roles); ?>
                </div>
                <div style="font-size: 12px; color: var(--text-secondary);">
                    Roles con Permiso
                </div>
            </div>
            
            <div class="stat-item" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--space-md); text-align: center;">
                <div style="font-size: 24px; margin-bottom: var(--space-sm);">🔐</div>
                <div style="font-size: 20px; font-weight: 600; color: var(--text-primary);">
                    <?php echo $permiso_info['name']; ?>
                </div>
                <div style="font-size: 12px; color: var(--text-secondary);">
                    Tipo de Permiso
                </div>
            </div>
            
            <div class="stat-item" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--space-md); text-align: center;">
                <div style="font-size: 24px; margin-bottom: var(--space-sm);">📦</div>
                <div style="font-size: 20px; font-weight: 600; color: var(--text-primary);">
                    <?php echo htmlspecialchars($permiso['modulo_nombre']); ?>
                </div>
                <div style="font-size: 12px; color: var(--text-secondary);">
                    Módulo
                </div>
            </div>
        </div>
    </div>
    
    <!-- Acciones -->
    <?php if ($auth->hasPermission('acc_usuarios', 'actualizar')): ?>
        <div class="permission-actions" style="margin-top: var(--space-lg); padding-top: var(--space-lg); border-top: 2px solid var(--border-color); display: flex; gap: var(--space-md); justify-content: center;">
            <a href="matriz_permisos.php" class="btn btn-primary">
                🔍 Ver Matriz Completa
            </a>
            <a href="permisos.php" class="btn btn-secondary">
                📋 Volver a Permisos
            </a>
        </div>
    <?php endif; ?>
</div>

<script>
// Función para ver detalles del rol
function verDetalleRol(roleId) {
    cerrarModal(); // Cerrar modal actual
    setTimeout(() => {
        verDetalleRol(roleId); // Función global definida en roles.php
    }, 100);
}

// Efectos hover para las tarjetas de roles
document.querySelectorAll('.role-card').forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-2px)';
        this.style.boxShadow = 'var(--shadow-md)';
        this.style.borderColor = 'var(--color-primary)';
    });
    
    card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
        this.style.boxShadow = 'none';
        this.style.borderColor = 'var(--border-color)';
    });
});

// Efectos hover para las estadísticas
document.querySelectorAll('.stat-item').forEach(item => {
    item.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-1px)';
        this.style.boxShadow = 'var(--shadow-sm)';
    });
    
    item.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
        this.style.boxShadow = 'none';
    });
});
</script>

<style>
.permission-roles-detail {
    padding: var(--space-md);
}

.role-card {
    transition: all var(--transition-fast);
}

.stat-item {
    transition: all var(--transition-fast);
}

.btn-mini {
    transition: all var(--transition-fast);
}

.btn-mini:hover {
    transform: translateY(-1px);
    opacity: 0.9;
}
</style>