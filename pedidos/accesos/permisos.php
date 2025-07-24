<?php
/**
 * Gestión de Permisos
 * Sequoia Speed - Sistema de Accesos
 */

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once dirname(__DIR__) . '/config_secure.php';
require_once dirname(__DIR__) . '/php82_helpers.php';
require_once 'middleware/AuthMiddleware.php';
require_once 'models/Permission.php';
require_once 'models/Module.php';

// Inicializar middleware y requerir permisos
$auth = new AuthMiddleware($conn);
$current_user = $auth->requirePermission('usuarios', 'leer');

// Inicializar modelos
$permission_model = new Permission($conn);
$module_model = new Module($conn);

// Obtener permisos agrupados por módulo
$permisos_por_modulo = $permission_model->getPermissionsByModule();

// Obtener estadísticas de permisos
$estadisticas = $permission_model->getPermissionStats();

// Obtener tipos de permisos
$tipos_permisos = $permission_model->getPermissionTypes();

// Función para formatear fecha
function formatear_fecha($fecha) {
    if (!$fecha) return 'No disponible';
    return date('d/m/Y H:i', strtotime($fecha));
}

// Función para obtener icono del módulo
function get_module_icon($module_name) {
    $icons = [
        'ventas' => '🛒',
        'inventario' => '📦',
        'acc_usuarios' => '👥',
        'reportes' => '📊',
        'configuracion' => '⚙️'
    ];
    
    return $icons[$module_name] ?? '📁';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔐 Gestión de Permisos - Sequoia Speed</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔐</text></svg>">
    <link rel="stylesheet" href="../inventario/productos.css">
    <link rel="stylesheet" href="../notifications/notifications.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">🔐 Gestión de Permisos</h1>
                    <div class="breadcrumb">
                        <a href="../listar_pedidos.php">🏠 Inicio</a>
                        <span>/</span>
                        <a href="dashboard.php">🔐 Accesos</a>
                        <span>/</span>
                        <span>🔐 Permisos</span>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="matriz_permisos.php" class="btn btn-info">
                        🔍 Matriz de Permisos
                    </a>
                    <a href="dashboard.php" class="btn btn-secondary">
                        🏠 Dashboard
                    </a>
                </div>
            </div>
        </header>

        <!-- Información sobre tipos de permisos -->
        <div class="info-section" style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--space-lg); margin-bottom: var(--space-lg);">
            <h3 style="color: var(--text-primary); margin-bottom: var(--space-md); display: flex; align-items: center; gap: var(--space-sm);">
                📋 Tipos de Permisos (CRUD)
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-md);">
                <?php foreach ($tipos_permisos as $tipo => $info): ?>
                    <div class="permission-type-card" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--space-md); text-align: center;">
                        <div style="font-size: 32px; margin-bottom: var(--space-sm);">
                            <?php echo $info['icon']; ?>
                        </div>
                        <div style="font-weight: 600; color: var(--text-primary); margin-bottom: var(--space-sm);">
                            <?php echo $info['name']; ?>
                        </div>
                        <div style="font-size: 12px; color: var(--text-secondary);">
                            <?php echo $info['description']; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Estadísticas por módulo -->
        <div class="stats-section">
            <?php foreach ($estadisticas as $stat): ?>
                <div class="stat-card">
                    <div class="stat-icon"><?php echo get_module_icon($stat['modulo']); ?></div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($stat['total_permisos']); ?></div>
                        <div class="stat-label"><?php echo htmlspecialchars($stat['modulo']); ?></div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: var(--space-xs);">
                            <?php echo number_format($stat['roles_con_permisos']); ?> roles con acceso
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Permisos por módulo -->
        <div class="modules-section">
            <?php foreach ($permisos_por_modulo as $modulo_nombre => $modulo_data): ?>
                <div class="module-card" style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--space-lg); margin-bottom: var(--space-lg);">
                    <div class="module-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-lg);">
                        <div>
                            <h3 style="color: var(--text-primary); margin-bottom: var(--space-sm); display: flex; align-items: center; gap: var(--space-sm);">
                                <?php echo get_module_icon($modulo_nombre); ?>
                                <?php echo htmlspecialchars($modulo_data['modulo_nombre']); ?>
                            </h3>
                            <p style="color: var(--text-secondary); margin: 0;">
                                <?php echo htmlspecialchars($modulo_data['modulo_descripcion']); ?>
                            </p>
                        </div>
                        <div style="display: flex; gap: var(--space-sm);">
                            <button onclick="verMatrizModulo('<?php echo $modulo_nombre; ?>')" 
                                    class="btn btn-info" 
                                    style="padding: var(--space-sm) var(--space-md);">
                                🔍 Ver Matriz
                            </button>
                        </div>
                    </div>
                    
                    <div class="permissions-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-md);">
                        <?php foreach ($modulo_data['acc_permisos'] as $permiso): ?>
                            <?php $permiso_info = $permission_model->formatPermissionType($permiso['tipo_permiso']); ?>
                            <div class="permission-card" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--space-md); position: relative;">
                                <div class="permission-header" style="display: flex; align-items: center; gap: var(--space-sm); margin-bottom: var(--space-md);">
                                    <span style="font-size: 24px;"><?php echo $permiso_info['icon']; ?></span>
                                    <div>
                                        <div style="font-weight: 600; color: var(--text-primary);">
                                            <?php echo $permiso_info['name']; ?>
                                        </div>
                                        <div style="font-size: 12px; color: var(--text-secondary);">
                                            ID: <?php echo $permiso['id']; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="permission-description" style="font-size: 12px; color: var(--text-secondary); margin-bottom: var(--space-md);">
                                    <?php echo htmlspecialchars($permiso['descripcion']); ?>
                                </div>
                                
                                <div class="permission-actions">
                                    <button onclick="verRolesPermiso(<?php echo $permiso['id']; ?>)" 
                                            class="btn btn-secondary" 
                                            style="width: 100%; padding: var(--space-sm); font-size: 12px;">
                                        🎭 Ver Roles
                                    </button>
                                </div>
                                
                                <?php if (!$permiso['activo']): ?>
                                    <div class="permission-inactive" style="position: absolute; top: var(--space-sm); right: var(--space-sm);">
                                        <span class="badge-stock stock-bajo" style="font-size: 10px; padding: 2px 6px;">
                                            ❌ Inactivo
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Modal para roles con permiso -->
    <div id="rolesModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>🎭 Roles con este Permiso</h3>
                <button onclick="cerrarModal()" class="btn-close">×</button>
            </div>
            <div class="modal-body" id="rolesModalBody">
                <!-- Contenido cargado dinámicamente -->
            </div>
        </div>
    </div>

    <!-- Modal para matriz de módulo -->
    <div id="matrizModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 90%; max-height: 90%;">
            <div class="modal-header">
                <h3>🔍 Matriz de Permisos</h3>
                <button onclick="cerrarMatrizModal()" class="btn-close">×</button>
            </div>
            <div class="modal-body" id="matrizModalBody">
                <!-- Contenido cargado dinámicamente -->
            </div>
        </div>
    </div>

    <!-- Sistema de notificaciones -->
    <div id="notification-container"></div>

    <script src="../inventario/productos.js"></script>
    <script>
        // Ver roles que tienen un permiso específico
        function verRolesPermiso(permisoId) {
            mostrarIndicadorCarga();
            
            fetch(`permiso_roles.php?id=${permisoId}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('rolesModalBody').innerHTML = html;
                document.getElementById('rolesModal').style.display = 'flex';
                ocultarIndicadorCarga();
            })
            .catch(error => {
                console.error('Error:', error);
                ocultarIndicadorCarga();
                mostrarNotificacion('Error al cargar los roles', 'error');
            });
        }

        // Ver matriz de permisos de un módulo
        function verMatrizModulo(modulo) {
            mostrarIndicadorCarga();
            
            fetch(`matriz_modulo.php?modulo=${modulo}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('matrizModalBody').innerHTML = html;
                document.getElementById('matrizModal').style.display = 'flex';
                ocultarIndicadorCarga();
            })
            .catch(error => {
                console.error('Error:', error);
                ocultarIndicadorCarga();
                mostrarNotificacion('Error al cargar la matriz', 'error');
            });
        }

        // Cerrar modal de roles
        function cerrarModal() {
            document.getElementById('rolesModal').style.display = 'none';
        }

        // Cerrar modal de matriz
        function cerrarMatrizModal() {
            document.getElementById('matrizModal').style.display = 'none';
        }

        // Cerrar modales al hacer clic fuera
        document.getElementById('rolesModal').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModal();
            }
        });

        document.getElementById('matrizModal').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarMatrizModal();
            }
        });

        // Efectos hover para las tarjetas
        document.querySelectorAll('.permission-type-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = 'var(--shadow-md)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = 'none';
            });
        });

        document.querySelectorAll('.permission-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-1px)';
                this.style.boxShadow = 'var(--shadow-sm)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = 'none';
            });
        });

        document.querySelectorAll('.module-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-1px)';
                this.style.boxShadow = 'var(--shadow-md)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = 'none';
            });
        });

        // Añadir transiciones CSS
        const style = document.createElement('style');
        style.textContent = `
            .permission-type-card, .permission-card, .module-card {
                transition: all var(--transition-fast);
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>