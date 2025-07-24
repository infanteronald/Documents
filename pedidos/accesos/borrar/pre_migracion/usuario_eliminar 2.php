<?php
/**
 * Eliminar Usuario
 * Sequoia Speed - Sistema de Accesos
 */

require_once dirname(__DIR__) . '/config_secure.php';
require_once 'middleware/AuthMiddleware.php';
require_once 'models/User.php';

$auth = new AuthMiddleware($conn);
$current_user = $auth->requirePermission('acc_usuarios', 'eliminar', '/pedidos/accesos/unauthorized.php');

$userModel = new User($conn);

// Obtener ID del usuario a eliminar
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$user_id) {
    header('Location: usuarios.php?error=' . urlencode('Usuario no especificado'));
    exit;
}

// Verificar que el usuario existe
$usuario = $userModel->findById($user_id);
if (!$usuario) {
    header('Location: usuarios.php?error=' . urlencode('Usuario no encontrado'));
    exit;
}

// Verificar que no se pueda eliminar a sí mismo
if ($user_id == $current_user['id']) {
    header('Location: usuarios.php?error=' . urlencode('No puedes eliminar tu propia cuenta'));
    exit;
}

// Verificar que no se pueda eliminar el super admin principal
if ($usuario['email'] === 'admin@sequoiaspeed.com') {
    header('Location: usuarios.php?error=' . urlencode('No se puede eliminar el administrador principal'));
    exit;
}

// Procesar eliminación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar CSRF
    if (!$auth->verifyCSRF($_POST['csrf_token'] ?? '')) {
        die('Token CSRF inválido');
    }
    
    // Confirmar eliminación
    if (isset($_POST['confirmar']) && $_POST['confirmar'] === 'si') {
        try {
            // Eliminar usuario (la base de datos se encarga de eliminar las relaciones)
            if ($userModel->delete($user_id)) {
                // Registrar en auditoría
                $auth->logActivity(
                    'delete',
                    'acc_usuarios',
                    "Usuario eliminado: {$usuario['nombre']} ({$usuario['email']})"
                );
                
                header('Location: usuarios.php?success=' . urlencode('Usuario eliminado exitosamente'));
                exit;
            } else {
                throw new Exception('Error al eliminar el usuario');
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } else {
        header('Location: usuarios.php');
        exit;
    }
}

// Obtener roles del usuario
$usuario_roles = $userModel->getUserRoles($user_id);

// Obtener estadísticas del usuario
$stats = $userModel->getUserStats($user_id);

// Generar token CSRF
$csrf_token = $auth->generateCSRF();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminar Usuario - Sistema de Accesos</title>
    <link rel="stylesheet" href="../inventario/productos.css">
    <style>
        .delete-container {
            max-width: 600px;
            margin: 2rem auto;
            background: var(--bg-secondary);
            padding: 2rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        
        .warning-box {
            background: rgba(248, 113, 113, 0.1);
            border: 2px solid rgba(248, 113, 113, 0.3);
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .warning-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .warning-title {
            color: #f87171;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .warning-text {
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }
        
        .user-info {
            background: var(--bg-primary);
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }
        
        .user-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .user-row:last-child {
            border-bottom: none;
        }
        
        .user-label {
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .user-value {
            color: var(--text-primary);
            font-weight: 600;
        }
        
        .roles-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .role-badge {
            background: var(--color-primary);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .stat-item {
            background: var(--bg-tertiary);
            padding: 1rem;
            border-radius: 6px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--color-primary);
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }
        
        .btn-danger {
            background: #dc2626;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-danger:hover {
            background: #b91c1c;
            transform: translateY(-2px);
        }
        
        .confirmation-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 1.5rem 0;
            padding: 1rem;
            background: var(--bg-primary);
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }
        
        .confirmation-checkbox input {
            width: 18px;
            height: 18px;
            accent-color: #dc2626;
        }
        
        .confirmation-text {
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .alert-error {
            background: rgba(248, 113, 113, 0.1);
            border: 1px solid rgba(248, 113, 113, 0.3);
            color: #f87171;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="header-content">
                <h1>🗑️ Eliminar Usuario</h1>
                <div class="header-actions">
                    <a href="usuarios.php" class="btn btn-secondary">
                        ← Volver
                    </a>
                </div>
            </div>
        </header>

        <main class="main-content">
            <div class="delete-container">
                <?php if (isset($error)): ?>
                    <div class="alert-error">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="warning-box">
                    <div class="warning-icon">⚠️</div>
                    <div class="warning-title">¡Advertencia!</div>
                    <div class="warning-text">
                        Esta acción no se puede deshacer. Se eliminará permanentemente el usuario y todas sus relaciones.
                    </div>
                </div>

                <!-- Información del usuario -->
                <div class="user-info">
                    <h3 style="margin: 0 0 1rem 0; color: var(--text-primary);">
                        👤 Información del Usuario
                    </h3>
                    
                    <div class="user-row">
                        <span class="user-label">ID:</span>
                        <span class="user-value">#<?php echo $usuario['id']; ?></span>
                    </div>
                    
                    <div class="user-row">
                        <span class="user-label">Nombre:</span>
                        <span class="user-value"><?php echo htmlspecialchars($usuario['nombre']); ?></span>
                    </div>
                    
                    <div class="user-row">
                        <span class="user-label">Email:</span>
                        <span class="user-value"><?php echo htmlspecialchars($usuario['email']); ?></span>
                    </div>
                    
                    <?php if (isset($usuario['usuario'])): ?>
                    <div class="user-row">
                        <span class="user-label">Usuario:</span>
                        <span class="user-value"><?php echo htmlspecialchars($usuario['usuario']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="user-row">
                        <span class="user-label">Estado:</span>
                        <span class="user-value">
                            <?php echo $usuario['activo'] ? '✅ Activo' : '❌ Inactivo'; ?>
                        </span>
                    </div>
                    
                    <div class="user-row">
                        <span class="user-label">Fecha de registro:</span>
                        <span class="user-value">
                            <?php echo date('d/m/Y H:i', strtotime($usuario['fecha_registro'] ?? $usuario['fecha_creacion'])); ?>
                        </span>
                    </div>
                    
                    <?php if ($usuario['ultimo_acceso']): ?>
                    <div class="user-row">
                        <span class="user-label">Último acceso:</span>
                        <span class="user-value">
                            <?php echo date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="user-row">
                        <span class="user-label">Roles:</span>
                        <div class="roles-list">
                            <?php if (empty($usuario_roles)): ?>
                                <span class="user-value">Sin roles asignados</span>
                            <?php else: ?>
                                <?php foreach ($usuario_roles as $role): ?>
                                    <span class="role-badge">
                                        <?php echo htmlspecialchars($role['nombre']); ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Estadísticas (si están disponibles) -->
                <?php if (isset($stats)): ?>
                <div class="user-info">
                    <h3 style="margin: 0 0 1rem 0; color: var(--text-primary);">
                        📊 Estadísticas
                    </h3>
                    
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $stats['total_accesos'] ?? 0; ?></div>
                            <div class="stat-label">Accesos totales</div>
                        </div>
                        
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $stats['pedidos_creados'] ?? 0; ?></div>
                            <div class="stat-label">Pedidos creados</div>
                        </div>
                        
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $stats['dias_activo'] ?? 0; ?></div>
                            <div class="stat-label">Días activo</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Formulario de confirmación -->
                <form method="POST" action="" id="delete-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="confirmar" value="si">
                    
                    <div class="confirmation-checkbox">
                        <input type="checkbox" id="confirmar-eliminacion" required>
                        <label for="confirmar-eliminacion" class="confirmation-text">
                            Confirmo que quiero eliminar permanentemente este usuario
                        </label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-danger" id="btn-eliminar" disabled>
                            🗑️ Eliminar Usuario
                        </button>
                        <a href="usuarios.php" class="btn btn-secondary">
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Habilitar/deshabilitar botón según checkbox
        const checkbox = document.getElementById('confirmar-eliminacion');
        const btnEliminar = document.getElementById('btn-eliminar');
        
        checkbox.addEventListener('change', function() {
            btnEliminar.disabled = !this.checked;
            if (this.checked) {
                btnEliminar.style.opacity = '1';
                btnEliminar.style.cursor = 'pointer';
            } else {
                btnEliminar.style.opacity = '0.5';
                btnEliminar.style.cursor = 'not-allowed';
            }
        });
        
        // Confirmación adicional antes de enviar
        document.getElementById('delete-form').addEventListener('submit', function(e) {
            const userName = "<?php echo htmlspecialchars($usuario['nombre']); ?>";
            const confirmMessage = `¿Estás COMPLETAMENTE SEGURO de que quieres eliminar al usuario "${userName}"?\n\nEsta acción NO se puede deshacer.`;
            
            if (!confirm(confirmMessage)) {
                e.preventDefault();
            }
        });
        
        // Cancelar con ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                window.location.href = 'usuarios.php';
            }
        });
    </script>
</body>
</html>