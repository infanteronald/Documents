<?php
/**
 * Editar Usuario
 * Sequoia Speed - Sistema de Accesos
 */

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config_secure.php';
require_once 'middleware/AuthMiddleware.php';
require_once 'models/User.php';
require_once 'models/Role.php';

$auth = new AuthMiddleware($conn);
$current_user = $auth->requirePermission('usuarios', 'actualizar', '/accesos/unauthorized.php');

$userModel = new User($conn);
$roleModel = new Role($conn);

// Obtener ID del usuario a editar
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$user_id) {
    header('Location: usuarios.php?error=' . urlencode('Usuario no especificado'));
    exit;
}

// Obtener datos del usuario (incluyendo inactivos para poder editarlos)
$usuario = $userModel->findByIdForEdit($user_id);
if (!$usuario) {
    header('Location: usuarios.php?error=' . urlencode('Usuario no encontrado'));
    exit;
}

// Obtener roles del usuario
$usuario_roles = $userModel->getUserRoles($user_id);
$roles_ids = array_column($usuario_roles, 'rol_id');

// Obtener todos los roles disponibles
$roles = $roleModel->getAllRoles();

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar CSRF
    if (!$auth->verifyCSRF($_POST['csrf_token'] ?? '')) {
        die('Token CSRF inválido');
    }
    
    // Validar datos
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $usuario_login = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    $activo = isset($_POST['activo']) ? 1 : 0;
    $roles_seleccionados = $_POST['roles'] ?? [];
    
    $errors = [];
    
    if (empty($nombre)) {
        $errors[] = 'El nombre es requerido';
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email válido es requerido';
    }
    
    if (empty($usuario_login)) {
        $errors[] = 'El nombre de usuario es requerido';
    }
    
    // Verificar email único (excepto el usuario actual)
    $existing = $userModel->findByEmail($email);
    if ($existing && $existing['id'] != $user_id) {
        $errors[] = 'El email ya está en uso';
    }
    
    // Verificar usuario único (excepto el usuario actual)
    $existing_user = $userModel->findByUsername($usuario_login);
    if ($existing_user && $existing_user['id'] != $user_id) {
        $errors[] = 'El nombre de usuario ya está en uso';
    }
    
    if (empty($errors)) {
        try {
            // Preparar datos para actualización
            $data = [
                'nombre' => $nombre,
                'email' => $email,
                'usuario' => $usuario_login,
                'activo' => $activo,
                'modificado_por' => $current_user['id']
            ];
            
            // Solo actualizar contraseña si se proporcionó una nueva
            if (!empty($password)) {
                if (strlen($password) < 6) {
                    throw new Exception('La contraseña debe tener al menos 6 caracteres');
                }
                $data['password'] = password_hash($password, PASSWORD_DEFAULT);
            }
            
            // Actualizar usuario
            if ($userModel->update($user_id, $data)) {
                // Actualizar roles
                $userModel->syncRoles($user_id, $roles_seleccionados);
                
                // Registrar en auditoría
                $auth->logActivity(
                    'update',
                    'usuarios',
                    "Usuario actualizado: {$nombre} (ID: {$user_id})"
                );
                
                header('Location: usuarios.php?success=' . urlencode('Usuario actualizado exitosamente'));
                exit;
            } else {
                throw new Exception('Error al actualizar el usuario');
            }
            
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
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
    <title>Editar Usuario - Sistema de Accesos</title>
    <link rel="stylesheet" href="../inventario/productos.css">
    <style>
        .form-container {
            max-width: 800px;
            margin: 2rem auto;
            background: var(--bg-secondary);
            padding: 2rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            color: var(--text-primary);
            font-size: 1rem;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--color-primary);
        }
        
        .form-group small {
            display: block;
            margin-top: 0.25rem;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            cursor: pointer;
        }
        
        .roles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 0.5rem;
        }
        
        .role-item {
            background: var(--bg-primary);
            padding: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .role-item:hover {
            border-color: var(--color-primary);
        }
        
        .role-item.selected {
            border-color: var(--color-primary);
            background: rgba(88, 166, 255, 0.1);
        }
        
        .role-name {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }
        
        .role-description {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-color);
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
            background: rgba(248, 113, 113, 0.1);
            border: 1px solid rgba(248, 113, 113, 0.3);
            color: #f87171;
        }
        
        .user-info-display {
            background: var(--bg-primary);
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }
        
        .info-row {
            display: flex;
            gap: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .info-label {
            color: var(--text-secondary);
            min-width: 150px;
        }
        
        .info-value {
            color: var(--text-primary);
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="header-content">
                <h1>✏️ Editar Usuario</h1>
                <div class="header-actions">
                    <a href="usuarios.php" class="btn btn-secondary">
                        ← Volver
                    </a>
                </div>
            </div>
        </header>

        <main class="main-content">
            <div class="form-container">
                <?php if (!empty($errors)): ?>
                    <div class="alert">
                        <ul style="margin: 0; padding-left: 1.5rem;">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Información actual del usuario -->
                <div class="user-info-display">
                    <h3 style="margin-top: 0; margin-bottom: 1rem;">Información Actual</h3>
                    <div class="info-row">
                        <span class="info-label">ID:</span>
                        <span class="info-value">#<?php echo $usuario['id']; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Fecha de registro:</span>
                        <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($usuario['fecha_registro'] ?? $usuario['fecha_creacion'])); ?></span>
                    </div>
                    <?php if ($usuario['ultimo_acceso']): ?>
                    <div class="info-row">
                        <span class="info-label">Último acceso:</span>
                        <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="form-group">
                        <label for="nombre">Nombre completo *</label>
                        <input type="text" id="nombre" name="nombre" 
                               value="<?php echo htmlspecialchars($_POST['nombre'] ?? $usuario['nombre']); ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? $usuario['email']); ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="usuario">Nombre de usuario *</label>
                        <input type="text" id="usuario" name="usuario" 
                               value="<?php echo htmlspecialchars($_POST['usuario'] ?? $usuario['usuario']); ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Nueva contraseña</label>
                        <input type="password" id="password" name="password" 
                               placeholder="Dejar en blanco para mantener la actual">
                        <small>Mínimo 6 caracteres. Solo complete si desea cambiar la contraseña.</small>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="activo" name="activo" value="1" 
                                   <?php echo ($usuario['activo'] ?? 1) ? 'checked' : ''; ?>>
                            <label for="activo">Usuario activo</label>
                        </div>
                        <small>Los usuarios inactivos no pueden acceder al sistema</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Roles asignados</label>
                        <div class="roles-grid">
                            <?php foreach ($roles as $role): ?>
                                <div class="role-item <?php echo in_array($role['id'], $roles_ids) ? 'selected' : ''; ?>">
                                    <div class="checkbox-group">
                                        <input type="checkbox" 
                                               id="role_<?php echo $role['id']; ?>" 
                                               name="roles[]" 
                                               value="<?php echo $role['id']; ?>"
                                               <?php echo in_array($role['id'], $roles_ids) ? 'checked' : ''; ?>>
                                        <label for="role_<?php echo $role['id']; ?>" style="cursor: pointer;">
                                            <div class="role-name"><?php echo htmlspecialchars($role['nombre']); ?></div>
                                            <div class="role-description"><?php echo htmlspecialchars($role['descripcion']); ?></div>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            💾 Guardar Cambios
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
        // Marcar visualmente los roles seleccionados
        document.querySelectorAll('.role-item input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const roleItem = this.closest('.role-item');
                if (this.checked) {
                    roleItem.classList.add('selected');
                } else {
                    roleItem.classList.remove('selected');
                }
            });
        });

        // Confirmación antes de guardar
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            if (password && password.length < 6) {
                e.preventDefault();
                alert('La contraseña debe tener al menos 6 caracteres');
                return false;
            }
        });
    </script>
</body>
</html>