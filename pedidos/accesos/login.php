<?php
/**
 * Sistema de Login
 * Sequoia Speed - Sistema de Accesos
 */

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once dirname(__DIR__) . '/config_secure.php';
require_once dirname(__DIR__) . '/php82_helpers.php';
require_once 'models/User.php';
require_once 'middleware/AuthMiddleware.php';

// Inicializar middleware
$auth = new AuthMiddleware($conn);

// Si ya está autenticado, redirigir
if ($auth->isAuthenticated()) {
    header('Location: ../index.php');
    exit;
}

// Iniciar sesión
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    
    // Validaciones básicas
    if (empty($username) || empty($password)) {
        $error = 'Por favor ingresa usuario y contraseña';
    } else {
        try {
            $user_model = new User($conn);
            $user = $user_model->findByUsername($username);
            
            if ($user && $user_model->verifyPassword($password, $user['password'])) {
                // Verificar que el usuario esté activo
                if (!$user['activo']) {
                    $error = 'Tu cuenta está desactivada. Contacta al administrador.';
                } else {
                    // Login exitoso
                    $auth->login($user['id'], $remember_me);
                    
                    // Redirigir a la página solicitada o index principal
                    $redirect_url = $_GET['redirect'] ?? '../index.php';
                    header("Location: $redirect_url");
                    exit;
                }
            } else {
                $error = 'Usuario o contraseña incorrectos';
                
                // Registrar intento fallido
                if ($user) {
                    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
                    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    
                    $query = "INSERT INTO auditoria_accesos (usuario_id, accion, modulo, descripcion, ip_address, user_agent) 
                              VALUES (?, 'login_failed', 'usuarios', 'Intento de login fallido', ?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param('iss', $user['id'], $ip_address, $user_agent);
                    $stmt->execute();
                }
            }
        } catch (Exception $e) {
            $error = 'Error interno del servidor. Intenta nuevamente.';
            error_log("Login error: " . $e->getMessage());
        }
    }
}

// Procesar logout
if (isset($_GET['logout'])) {
    $auth->logout();
    $success = 'Has cerrado sesión exitosamente';
}

// Generar token CSRF
$csrf_token = $auth->generateCSRF();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔐 Iniciar Sesión - Sequoia Speed</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔐</text></svg>">
    <style>
        :root {
            --bg-primary: #0d1117;
            --bg-secondary: #161b22;
            --bg-tertiary: #21262d;
            --bg-hover: #30363d;
            --text-primary: #e6edf3;
            --text-secondary: #8b949e;
            --text-muted: #656d76;
            --color-primary: #58a6ff;
            --color-success: #238636;
            --color-danger: #da3633;
            --color-warning: #f0ad4e;
            --border-color: #30363d;
            --border-radius: 6px;
            --space-xs: 4px;
            --space-sm: 8px;
            --space-md: 16px;
            --space-lg: 24px;
            --space-xl: 32px;
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.3);
            --shadow-md: 0 4px 8px rgba(0, 0, 0, 0.4);
            --shadow-lg: 0 8px 16px rgba(0, 0, 0, 0.5);
            --transition-fast: 0.15s ease;
            --transition-normal: 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'SF Pro Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
            color: var(--text-primary);
            line-height: 1.6;
            font-size: 14px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: var(--space-lg);
        }
        
        .login-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: var(--space-xl);
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }
        
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--color-primary), var(--color-success));
        }
        
        .login-header {
            text-align: center;
            margin-bottom: var(--space-xl);
        }
        
        .login-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: var(--space-sm);
        }
        
        .login-subtitle {
            font-size: 14px;
            color: var(--text-secondary);
        }
        
        .form-group {
            margin-bottom: var(--space-lg);
        }
        
        .form-label {
            display: block;
            margin-bottom: var(--space-sm);
            font-size: 13px;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .form-input {
            width: 100%;
            padding: var(--space-md);
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            color: var(--text-primary);
            font-size: 14px;
            transition: all var(--transition-fast);
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 2px rgba(88, 166, 255, 0.2);
        }
        
        .form-input::placeholder {
            color: var(--text-muted);
        }
        
        .form-checkbox {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            margin-bottom: var(--space-lg);
        }
        
        .form-checkbox input {
            width: 16px;
            height: 16px;
            accent-color: var(--color-primary);
        }
        
        .form-checkbox label {
            font-size: 13px;
            color: var(--text-secondary);
            cursor: pointer;
        }
        
        .btn {
            width: 100%;
            padding: var(--space-md);
            background: var(--color-primary);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition-fast);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-sm);
        }
        
        .btn:hover {
            background: #4493f8;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .alert {
            padding: var(--space-md);
            border-radius: var(--border-radius);
            margin-bottom: var(--space-lg);
            display: flex;
            align-items: flex-start;
            gap: var(--space-sm);
            font-size: 13px;
            line-height: 1.4;
        }
        
        .alert-success {
            background: rgba(35, 134, 54, 0.1);
            border: 1px solid var(--color-success);
            color: var(--color-success);
        }
        
        .alert-error {
            background: rgba(218, 54, 51, 0.1);
            border: 1px solid var(--color-danger);
            color: var(--color-danger);
        }
        
        .alert-icon {
            font-size: 16px;
            line-height: 1;
        }
        
        .login-footer {
            text-align: center;
            margin-top: var(--space-xl);
            padding-top: var(--space-lg);
            border-top: 1px solid var(--border-color);
        }
        
        .login-footer a {
            color: var(--color-primary);
            text-decoration: none;
            font-size: 13px;
            transition: color var(--transition-fast);
        }
        
        .login-footer a:hover {
            color: var(--text-primary);
        }
        
        .system-info {
            text-align: center;
            margin-top: var(--space-lg);
            padding: var(--space-md);
            background: var(--bg-tertiary);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
        }
        
        .system-info h3 {
            font-size: 14px;
            color: var(--text-primary);
            margin-bottom: var(--space-sm);
        }
        
        .system-info p {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .hide {
            display: none;
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: var(--space-md);
            }
            
            .login-card {
                padding: var(--space-lg);
            }
            
            .login-title {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1 class="login-title">🔐 Iniciar Sesión</h1>
                <p class="login-subtitle">Sistema de Accesos - Sequoia Speed</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <span class="alert-icon">⚠️</span>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <span class="alert-icon">✅</span>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <label for="username" class="form-label">👤 Usuario</label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           class="form-input" 
                           placeholder="Tu nombre de usuario"
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                           required>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">🔒 Contraseña</label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-input" 
                           placeholder="Tu contraseña"
                           required>
                </div>
                
                <div class="form-checkbox">
                    <input type="checkbox" id="remember_me" name="remember_me">
                    <label for="remember_me">Recordarme por 30 días</label>
                </div>
                
                <button type="submit" class="btn" id="loginBtn">
                    <span id="btnText">🚀 Iniciar Sesión</span>
                    <span id="btnLoading" class="loading hide"></span>
                </button>
            </form>
            
            <div class="login-footer">
                <a href="recuperar_password.php">🔓 ¿Olvidaste tu contraseña?</a>
                <br><br>
                <a href="../index.php">← Volver al sistema principal</a>
            </div>
        </div>
        
        <div class="system-info">
            <h3>💡 Información del Sistema</h3>
            <p>
                <strong>Roles disponibles:</strong> Super Admin, Admin, Gerente, Supervisor, Vendedor, Consultor<br>
                <strong>Módulos:</strong> Ventas, Inventario, Usuarios, Reportes, Configuración
            </p>
        </div>
    </div>
    
    <script>
        // Manejo del formulario de login
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            const btnText = document.getElementById('btnText');
            const btnLoading = document.getElementById('btnLoading');
            
            // Mostrar loading
            btn.disabled = true;
            btnText.classList.add('hide');
            btnLoading.classList.remove('hide');
            
            // El formulario se envía normalmente
            // El loading se oculta automáticamente cuando se recarga la página
        });
        
        // Auto-focus en el campo de usuario
        document.getElementById('username').focus();
        
        // Limpiar mensajes después de 5 segundos
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 300);
            });
        }, 5000);
    </script>
</body>
</html>