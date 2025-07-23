<?php
/**
 * Detalles de Rol (AJAX)
 * Sequoia Speed - Sistema de Accesos
 */

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once dirname(__DIR__) . '/config_secure.php';
require_once dirname(__DIR__) . '/php82_helpers.php';
require_once 'middleware/AuthMiddleware.php';
require_once 'models/Role.php';

// Inicializar middleware y requerir permisos
$auth = new AuthMiddleware($conn);
$current_user = $auth->requirePermission('usuarios', 'leer');

// Obtener ID del rol
$role_id = intval($_GET['id'] ?? 0);

if ($role_id <= 0) {
    echo '<div class="alert alert-error">ID de rol inválido</div>';
    exit;
}

try {
    $role_model = new Role($conn);
    
    // Obtener información del rol
    $role = $role_model->findById($role_id);
    if (!$role) {
        echo '<div class="alert alert-error">Rol no encontrado</div>';
        exit;
    }
    
    // Obtener permisos del rol
    $permisos = $role_model->getRolePermissions($role_id);
    
    // Obtener usuarios con este rol
    $usuarios = $role_model->getRoleUsers($role_id);
    
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
            'super_admin' => ['level' => 1, 'name' => 'Nivel 1 - Máximo'],
            'admin' => ['level' => 2, 'name' => 'Nivel 2 - Alto'],
            'gerente' => ['level' => 3, 'name' => 'Nivel 3 - Medio-Alto'],
            'supervisor' => ['level' => 4, 'name' => 'Nivel 4 - Medio'],
            'vendedor' => ['level' => 5, 'name' => 'Nivel 5 - Básico'],
            'consultor' => ['level' => 6, 'name' => 'Nivel 6 - Consulta']
        ];
        
        return $levels[$role_name] ?? ['level' => 7, 'name' => 'Personalizado'];
    }
    
    // Agrupar permisos por módulo
    $permisos_agrupados = [];
    foreach ($permisos as $permiso) {
        $permisos_agrupados[$permiso['modulo_nombre']][] = $permiso;
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
    
} catch (Exception $e) {
    echo '<div class="alert alert-error">Error al cargar los datos: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>

<div class="role-details">
    <!-- Información básica -->
    <div class="detail-section">
        <h4 style="color: var(--text-primary); margin-bottom: var(--space-md); display: flex; align-items: center; gap: var(--space-sm);">
            <?php echo get_role_icon($role['nombre']); ?> Información del Rol
        </h4>
        <div class="info-grid">
            <div class="info-item">
                <strong>ID:</strong>
                <span><?php echo $role['id']; ?></span>
            </div>
            <div class="info-item">
                <strong>Nombre:</strong>
                <span><?php echo htmlspecialchars($role['nombre']); ?></span>
            </div>
            <div class="info-item">
                <strong>Nivel:</strong>
                <span><?php echo get_role_level($role['nombre'])['name']; ?></span>
            </div>
            <div class="info-item">
                <strong>Estado:</strong>
                <span>
                    <?php if ($role['activo']): ?>
                        <span class="badge-stock stock-alto">✅ Activo</span>
                    <?php else: ?>
                        <span class="badge-stock stock-bajo">❌ Inactivo</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <strong>Fecha creación:</strong>
                <span><?php echo formatear_fecha($role['fecha_creacion']); ?></span>
            </div>
            <div class="info-item">
                <strong>Última modificación:</strong>
                <span><?php echo formatear_fecha($role['fecha_modificacion']); ?></span>
            </div>
        </div>
        
        <?php if (!empty($role['descripcion'])): ?>
            <div style="margin-top: var(--space-md); padding: var(--space-md); background: var(--bg-tertiary); border-radius: var(--border-radius); border-left: 4px solid var(--color-primary);">
                <strong style="color: var(--text-primary); display: block; margin-bottom: var(--space-sm);">Descripción:</strong>
                <p style="color: var(--text-secondary); margin: 0;">
                    <?php echo htmlspecialchars($role['descripcion']); ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Permisos -->
    <div class="detail-section" style="margin-top: var(--space-lg);">
        <h4 style="color: var(--text-primary); margin-bottom: var(--space-md); display: flex; align-items: center; gap: var(--space-sm);">
            🔐 Permisos Asignados
        </h4>
        <?php if (empty($permisos_agrupados)): ?>
            <div class="empty-state" style="padding: var(--space-md); text-align: center;">
                <div style="color: var(--text-secondary);">No tiene permisos asignados</div>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--space-md);">
                <?php foreach ($permisos_agrupados as $modulo => $permisos_modulo): ?>
                    <div class="module-permissions" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--space-md);">
                        <div style="font-weight: 500; color: var(--text-primary); margin-bottom: var(--space-md); display: flex; align-items: center; gap: var(--space-sm);">
                            <span style="font-size: 20px;">📦</span>
                            <span><?php echo htmlspecialchars($modulo); ?></span>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: var(--space-sm);">
                            <?php foreach ($permisos_modulo as $permiso): ?>
                                <?php $permiso_info = formatear_permiso($permiso['tipo_permiso']); ?>
                                <div class="permission-item" style="background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--space-sm); text-align: center;">
                                    <div style="font-size: 16px; margin-bottom: var(--space-xs);">
                                        <?php echo $permiso_info['icon']; ?>
                                    </div>
                                    <div style="font-size: 11px; color: var(--text-primary); font-weight: 500;">
                                        <?php echo $permiso_info['name']; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Usuarios con este rol -->
    <div class="detail-section" style="margin-top: var(--space-lg);">
        <h4 style="color: var(--text-primary); margin-bottom: var(--space-md); display: flex; align-items: center; gap: var(--space-sm);">
            👥 Usuarios con este Rol
        </h4>
        <?php if (empty($usuarios)): ?>
            <div class="empty-state" style="padding: var(--space-md); text-align: center;">
                <div style="color: var(--text-secondary);">No hay usuarios con este rol</div>
            </div>
        <?php else: ?>
            <div style="max-height: 300px; overflow-y: auto;">
                <?php foreach ($usuarios as $usuario): ?>
                    <div class="user-item" style="display: flex; justify-content: space-between; align-items: center; padding: var(--space-md); border-bottom: 1px solid var(--border-color); background: var(--bg-tertiary); margin-bottom: var(--space-sm); border-radius: var(--border-radius);">
                        <div style="flex: 1;">
                            <div style="font-weight: 500; color: var(--text-primary); margin-bottom: var(--space-xs);">
                                👤 <?php echo htmlspecialchars($usuario['nombre']); ?>
                            </div>
                            <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: var(--space-xs);">
                                📧 <?php echo htmlspecialchars($usuario['email']); ?>
                            </div>
                            <div style="font-size: 11px; color: var(--text-muted);">
                                Asignado: <?php echo formatear_fecha($usuario['fecha_asignacion']); ?>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <?php if ($usuario['activo']): ?>
                                <span class="badge-stock stock-alto" style="font-size: 10px; padding: 2px 6px;">✅ Activo</span>
                            <?php else: ?>
                                <span class="badge-stock stock-bajo" style="font-size: 10px; padding: 2px 6px;">❌ Inactivo</span>
                            <?php endif; ?>
                            <div style="font-size: 10px; color: var(--text-muted); margin-top: var(--space-xs);">
                                Último acceso: <?php echo $usuario['ultimo_acceso'] ? formatear_fecha($usuario['ultimo_acceso']) : 'Nunca'; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Estadísticas -->
    <div class="detail-section" style="margin-top: var(--space-lg);">
        <h4 style="color: var(--text-primary); margin-bottom: var(--space-md); display: flex; align-items: center; gap: var(--space-sm);">
            📊 Estadísticas
        </h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: var(--space-md);">
            <div class="stat-item" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--space-md); text-align: center;">
                <div style="font-size: 24px; margin-bottom: var(--space-sm);">👥</div>
                <div style="font-size: 18px; font-weight: 600; color: var(--text-primary);">
                    <?php echo count($usuarios); ?>
                </div>
                <div style="font-size: 12px; color: var(--text-secondary);">
                    Usuarios Totales
                </div>
            </div>
            <div class="stat-item" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--space-md); text-align: center;">
                <div style="font-size: 24px; margin-bottom: var(--space-sm);">🔐</div>
                <div style="font-size: 18px; font-weight: 600; color: var(--text-primary);">
                    <?php echo count($permisos); ?>
                </div>
                <div style="font-size: 12px; color: var(--text-secondary);">
                    Permisos Totales
                </div>
            </div>
            <div class="stat-item" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--space-md); text-align: center;">
                <div style="font-size: 24px; margin-bottom: var(--space-sm);">📦</div>
                <div style="font-size: 18px; font-weight: 600; color: var(--text-primary);">
                    <?php echo count($permisos_agrupados); ?>
                </div>
                <div style="font-size: 12px; color: var(--text-secondary);">
                    Módulos con Acceso
                </div>
            </div>
            <div class="stat-item" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--space-md); text-align: center;">
                <div style="font-size: 24px; margin-bottom: var(--space-sm);">✅</div>
                <div style="font-size: 18px; font-weight: 600; color: var(--text-primary);">
                    <?php echo count(array_filter($usuarios, function($u) { return $u['activo']; })); ?>
                </div>
                <div style="font-size: 12px; color: var(--text-secondary);">
                    Usuarios Activos
                </div>
            </div>
        </div>
    </div>
    
    <!-- Acciones -->
    <?php if ($auth->hasPermission('usuarios', 'actualizar')): ?>
        <div class="detail-actions" style="margin-top: var(--space-lg); padding-top: var(--space-lg); border-top: 2px solid var(--border-color); display: flex; gap: var(--space-md); justify-content: center;">
            <a href="rol_editar.php?id=<?php echo $role['id']; ?>" class="btn btn-primary">
                ✏️ Editar Rol
            </a>
            
            <a href="rol_permisos.php?id=<?php echo $role['id']; ?>" class="btn btn-info">
                🔐 Gestionar Permisos
            </a>
            
            <?php if ($role['activo']): ?>
                <button onclick="toggleRolEstado(<?php echo $role['id']; ?>, 'desactivar')" class="btn btn-warning">
                    ❌ Desactivar
                </button>
            <?php else: ?>
                <button onclick="toggleRolEstado(<?php echo $role['id']; ?>, 'activar')" class="btn btn-success">
                    ✅ Activar
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

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

.role-details {
    padding: var(--space-md);
}

.user-item:hover {
    background: var(--bg-hover);
}

.permission-item:hover {
    background: var(--bg-secondary);
    transform: translateY(-1px);
}

.stat-item:hover {
    background: var(--bg-hover);
    transform: translateY(-1px);
}

.module-permissions {
    transition: all var(--transition-fast);
}

.module-permissions:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.permission-item {
    transition: all var(--transition-fast);
}

.stat-item {
    transition: all var(--transition-fast);
}
</style>