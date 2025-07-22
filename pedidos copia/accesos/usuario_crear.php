<?php
/**
 * Crear Usuario
 * Sequoia Speed - Sistema de Accesos
 */

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once '../config_secure.php';
require_once '../php82_helpers.php';
require_once 'middleware/AuthMiddleware.php';
require_once 'models/User.php';
require_once 'models/Role.php';

// Inicializar middleware y requerir permisos
$auth = new AuthMiddleware($conn);
$current_user = $auth->requirePermission('usuarios', 'crear');

// Inicializar modelos
$user_model = new User($conn);
$role_model = new Role($conn);

// Iniciar sesión para mensajes
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Obtener roles disponibles
$roles = $role_model->getAllRoles();

$errores = [];
$datos_formulario = [];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener datos del formulario
    $datos_formulario = [
        'nombre' => trim($_POST['nombre'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'password_confirm' => $_POST['password_confirm'] ?? '',
        'roles' => $_POST['roles'] ?? [],
        'activo' => isset($_POST['activo']) ? 1 : 0,
        'creado_por' => $current_user['id']
    ];

    // Validaciones
    if (empty($datos_formulario['nombre'])) {
        $errores[] = 'El nombre es requerido';
    } elseif (strlen($datos_formulario['nombre']) < 2) {
        $errores[] = 'El nombre debe tener al menos 2 caracteres';
    }

    if (empty($datos_formulario['email'])) {
        $errores[] = 'El email es requerido';
    } elseif (!filter_var($datos_formulario['email'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El email no tiene un formato válido';
    } else {
        // Verificar que el email no exista
        if ($user_model->findByEmail($datos_formulario['email'])) {
            $errores[] = 'Ya existe un usuario con este email';
        }
    }

    if (empty($datos_formulario['password'])) {
        $errores[] = 'La contraseña es requerida';
    } elseif (strlen($datos_formulario['password']) < 6) {
        $errores[] = 'La contraseña debe tener al menos 6 caracteres';
    }

    if ($datos_formulario['password'] !== $datos_formulario['password_confirm']) {
        $errores[] = 'Las contraseñas no coinciden';
    }

    if (empty($datos_formulario['roles'])) {
        $errores[] = 'Debe seleccionar al menos un rol';
    } else {
        // Verificar que los roles existen
        foreach ($datos_formulario['roles'] as $rol_id) {
            if (!$role_model->findById($rol_id)) {
                $errores[] = 'Uno o más roles seleccionados no son válidos';
                break;
            }
        }
    }

    // Si no hay errores, crear usuario
    if (empty($errores)) {
        try {
            $usuario_id = $user_model->create($datos_formulario);
            
            // Asignar roles adicionales
            foreach ($datos_formulario['roles'] as $rol_id) {
                $user_model->assignRole($usuario_id, $rol_id, $current_user['id']);
            }

            // Registrar auditoría
            $auth->logActivity('create', 'usuarios', 'Usuario creado: ' . $datos_formulario['email']);

            $_SESSION['mensaje_exito'] = 'Usuario creado exitosamente';
            header('Location: usuarios.php');
            exit;

        } catch (Exception $e) {
            $errores[] = 'Error al crear el usuario: ' . $e->getMessage();
        }
    }
}

// Generar token CSRF
$csrf_token = $auth->generateCSRF();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>➕ Crear Usuario - Sequoia Speed</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>➕</text></svg>">
    <link rel="stylesheet" href="../inventario/productos.css">
    <link rel="stylesheet" href="../notifications/notifications.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">➕ Crear Usuario</h1>
                    <div class="breadcrumb">
                        <a href="../listar_pedidos.php">🏠 Inicio</a>
                        <span>/</span>
                        <a href="dashboard.php">🔐 Accesos</a>
                        <span>/</span>
                        <a href="usuarios.php">👥 Usuarios</a>
                        <span>/</span>
                        <span>➕ Crear</span>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="usuarios.php" class="btn btn-secondary">
                        ← Volver a Usuarios
                    </a>
                </div>
            </div>
        </header>

        <!-- Mensajes de error -->
        <?php if (!empty($errores)): ?>
            <div class="mensaje mensaje-error">
                <div class="mensaje-contenido">
                    <span class="mensaje-icono">⚠️</span>
                    <div>
                        <ul>
                            <?php foreach ($errores as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <button class="mensaje-cerrar" onclick="this.parentElement.remove()">×</button>
            </div>
        <?php endif; ?>

        <!-- Formulario -->
        <div class="table-section">
            <form method="POST" class="form-container" style="max-width: 600px; margin: 0 auto;">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-section">
                    <h3 style="color: var(--text-primary); margin-bottom: var(--space-lg); padding-bottom: var(--space-md); border-bottom: 2px solid var(--border-color);">
                        📋 Información Personal
                    </h3>
                    
                    <div class="form-group">
                        <label for="nombre" class="form-label">👤 Nombre Completo *</label>
                        <input type="text" 
                               id="nombre" 
                               name="nombre" 
                               class="filter-input" 
                               value="<?php echo htmlspecialchars($datos_formulario['nombre'] ?? ''); ?>"
                               required 
                               maxlength="100"
                               placeholder="Ingresa el nombre completo">
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">📧 Email *</label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="filter-input" 
                               value="<?php echo htmlspecialchars($datos_formulario['email'] ?? ''); ?>"
                               required 
                               maxlength="100"
                               placeholder="usuario@ejemplo.com">
                    </div>
                    
                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-md);">
                        <div class="form-group">
                            <label for="password" class="form-label">🔒 Contraseña *</label>
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   class="filter-input" 
                                   required 
                                   minlength="6"
                                   placeholder="Mínimo 6 caracteres">
                        </div>
                        
                        <div class="form-group">
                            <label for="password_confirm" class="form-label">🔒 Confirmar Contraseña *</label>
                            <input type="password" 
                                   id="password_confirm" 
                                   name="password_confirm" 
                                   class="filter-input" 
                                   required 
                                   minlength="6"
                                   placeholder="Repetir contraseña">
                        </div>
                    </div>
                </div>

                <div class="form-section" style="margin-top: var(--space-xl);">
                    <h3 style="color: var(--text-primary); margin-bottom: var(--space-lg); padding-bottom: var(--space-md); border-bottom: 2px solid var(--border-color);">
                        🎭 Roles y Permisos
                    </h3>
                    
                    <div class="form-group">
                        <label class="form-label">🎭 Roles Asignados *</label>
                        <div class="roles-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-md); margin-top: var(--space-sm);">
                            <?php foreach ($roles as $role): ?>
                                <label class="role-card" style="display: flex; align-items: center; gap: var(--space-sm); padding: var(--space-md); background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius); cursor: pointer; transition: all var(--transition-fast);">
                                    <input type="checkbox" 
                                           name="roles[]" 
                                           value="<?php echo $role['id']; ?>"
                                           <?php echo in_array($role['id'], $datos_formulario['roles'] ?? []) ? 'checked' : ''; ?>
                                           style="width: 16px; height: 16px; accent-color: var(--color-primary);">
                                    <div style="flex: 1;">
                                        <div style="font-weight: 500; color: var(--text-primary); margin-bottom: var(--space-xs);">
                                            <?php echo htmlspecialchars($role['nombre']); ?>
                                        </div>
                                        <div style="font-size: 12px; color: var(--text-secondary);">
                                            <?php echo htmlspecialchars($role['descripcion']); ?>
                                        </div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="form-section" style="margin-top: var(--space-xl);">
                    <h3 style="color: var(--text-primary); margin-bottom: var(--space-lg); padding-bottom: var(--space-md); border-bottom: 2px solid var(--border-color);">
                        ⚙️ Configuración
                    </h3>
                    
                    <div class="form-group">
                        <label class="form-checkbox" style="display: flex; align-items: center; gap: var(--space-sm);">
                            <input type="checkbox" 
                                   name="activo" 
                                   <?php echo ($datos_formulario['activo'] ?? 1) ? 'checked' : ''; ?>
                                   style="width: 16px; height: 16px; accent-color: var(--color-primary);">
                            <span style="color: var(--text-primary);">✅ Usuario activo</span>
                        </label>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: var(--space-xs);">
                            Los usuarios inactivos no pueden iniciar sesión
                        </div>
                    </div>
                </div>

                <div class="form-actions" style="margin-top: var(--space-xl); padding-top: var(--space-lg); border-top: 2px solid var(--border-color); display: flex; gap: var(--space-md); justify-content: center;">
                    <button type="submit" class="btn btn-primary">
                        💾 Crear Usuario
                    </button>
                    <a href="usuarios.php" class="btn btn-secondary">
                        ❌ Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Sistema de notificaciones -->
    <div id="notification-container"></div>

    <script src="../inventario/productos.js"></script>
    <script>
        // Validación de contraseñas en tiempo real
        document.getElementById('password_confirm').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Las contraseñas no coinciden');
                this.style.borderColor = 'var(--color-danger)';
            } else {
                this.setCustomValidity('');
                this.style.borderColor = 'var(--color-success)';
            }
        });

        // Hover effect para role cards
        document.querySelectorAll('.role-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.backgroundColor = 'var(--bg-hover)';
                this.style.borderColor = 'var(--color-primary)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.backgroundColor = 'var(--bg-tertiary)';
                this.style.borderColor = 'var(--border-color)';
            });

            // Cambiar estado al hacer clic en la card
            card.addEventListener('click', function(e) {
                if (e.target.type !== 'checkbox') {
                    const checkbox = this.querySelector('input[type="checkbox"]');
                    checkbox.checked = !checkbox.checked;
                }
            });
        });

        // Validación del formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            const rolesSeleccionados = document.querySelectorAll('input[name="roles[]"]:checked');
            
            if (rolesSeleccionados.length === 0) {
                e.preventDefault();
                mostrarNotificacion('Debe seleccionar al menos un rol', 'error');
                return;
            }

            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('password_confirm').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                mostrarNotificacion('Las contraseñas no coinciden', 'error');
                return;
            }

            mostrarIndicadorCarga();
        });

        // Focus en el primer campo
        document.getElementById('nombre').focus();

        // Validación de email en tiempo real
        document.getElementById('email').addEventListener('blur', function() {
            const email = this.value;
            if (email) {
                fetch('verificar_email.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `email=${encodeURIComponent(email)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.existe) {
                        this.style.borderColor = 'var(--color-danger)';
                        mostrarNotificacion('Este email ya está registrado', 'error');
                    } else {
                        this.style.borderColor = 'var(--color-success)';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            }
        });

        // Shortcut para guardar
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                document.querySelector('form').submit();
            }
        });
    </script>
</body>
</html>