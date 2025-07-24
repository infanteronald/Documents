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
$current_user = $auth->requirePermission('usuarios', 'actualizar', '/pedidos/accesos/unauthorized.php');

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

// Obtener rol del usuario (solo uno)
$usuario_roles = $userModel->getUserRoles($user_id);
$rol_actual = !empty($usuario_roles) ? $usuario_roles[0]['id'] : '';

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
    $roles_seleccionados = $_POST['acc_roles'] ?? '';
    
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
    
    // Verificar email único (excepto el usuario actual, incluyendo inactivos)
    $existing = $userModel->findByEmailIncludingInactive($email);
    if ($existing && $existing['id'] != $user_id) {
        if ($existing['activo']) {
            $errors[] = 'El email ya está en uso por un usuario activo';
        } else {
            $errors[] = 'El email ya está en uso por un usuario inactivo';
        }
    }
    
    // Verificar usuario único (excepto el usuario actual, incluyendo inactivos)
    $existing_user = $userModel->findByUsernameIncludingInactive($usuario_login);
    if ($existing_user && $existing_user['id'] != $user_id) {
        if ($existing_user['activo']) {
            $errors[] = 'El nombre de usuario ya está en uso por un usuario activo';
        } else {
            $errors[] = 'El nombre de usuario ya está en uso por un usuario inactivo';
        }
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
                // Actualizar rol (eliminar actual y asignar nuevo)
                if (!empty($roles_seleccionados)) {
                    // Primero eliminar rol actual si existe
                    if ($rol_actual) {
                        $userModel->removeRole($user_id, $rol_actual);
                    }
                    // Asignar nuevo rol
                    $userModel->assignRole($user_id, $roles_seleccionados, $current_user['id']);
                }
                
                // Registrar en auditoría
                $auth->logActivity(
                    'update',
                    'acc_usuarios',
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
        
        /* CSS simplificado ya que ahora usamos select simple */
        
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
                        <label for="acc_roles">Rol asignado</label>
                        <select id="acc_roles" name="acc_roles" style="width: 100%; padding: 0.75rem; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 4px; color: var(--text-primary); font-size: 1rem;">
                            <option value="">Sin rol asignado</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>"
                                        <?php echo ($rol_actual == $role['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($role['nombre']); ?> - <?php echo htmlspecialchars($role['descripcion']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Cada usuario puede tener únicamente un rol asignado</small>
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