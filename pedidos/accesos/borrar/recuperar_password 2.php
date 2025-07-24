<?php
/**
 * Sistema de Recuperación de Contraseña
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
$step = $_GET['step'] ?? 'request';

// Procesar solicitud de recuperación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'request') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Por favor ingresa tu email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Por favor ingresa un email válido';
    } else {
        try {
            $user_model = new User($conn);
            $user = $user_model->findByEmail($email);
            
            if ($user) {
                // Generar token de recuperación
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Guardar token en base de datos
                $query = "INSERT INTO password_reset_tokens (usuario_id, token, email, fecha_expiracion) 
                          VALUES (?, ?, ?, ?) 
                          ON DUPLICATE KEY UPDATE 
                          token = VALUES(token), 
                          fecha_expiracion = VALUES(fecha_expiracion), 
                          usado = 0";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('isss', $user['id'], $token, $email, $expires);
                $stmt->execute();
                
                // Enviar email (simulado por ahora)
                $reset_link = "https://sequoiaspeed.com.co/pedidos/accesos/recuperar_password.php?step=reset&token=" . $token;
                
                // TODO: Implementar envío de email real
                // Por ahora mostrar el enlace en pantalla para pruebas
                $success = "Se ha enviado un enlace de recuperación a tu email. El enlace expira en 1 hora.<br><br>
                           <strong>Para pruebas:</strong><br>
                           <a href='recuperar_password.php?step=reset&token=$token'>Restablecer contraseña</a>";
                
                // Registrar auditoría
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $query = "INSERT INTO acc_auditoria_accesos (usuario_id, accion, modulo, descripcion, ip_address, user_agent) 
                          VALUES (?, 'password_reset_request', 'acc_usuarios', 'Solicitud de recuperación de contraseña', ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('iss', $user['id'], $ip_address, $user_agent);
                $stmt->execute();
            } else {
                // Por seguridad, mostramos el mismo mensaje aunque el email no exista
                $success = "Si el email existe en nuestro sistema, recibirás un enlace de recuperación.";
            }
        } catch (Exception $e) {
            $error = 'Error interno del servidor. Intenta nuevamente.';
            error_log("Password recovery error: " . $e->getMessage());
        }
    }
}

// Procesar restablecimiento de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'reset') {
    $token = $_POST['token'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Por favor completa todos los campos';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Las contraseñas no coinciden';
    } elseif (strlen($new_password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } else {
        try {
            // Verificar token válido
            $query = "SELECT usuario_id FROM password_reset_tokens 
                      WHERE token = ? AND fecha_expiracion > NOW() AND usado = 0 LIMIT 1";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('s', $token);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $row = $result->fetch_assoc();
                $usuario_id = $row['usuario_id'];
                
                // Actualizar contraseña
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $query = "UPDATE acc_usuarios SET password = ? WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('si', $password_hash, $usuario_id);
                $stmt->execute();
                
                // Marcar token como usado
                $query = "UPDATE password_reset_tokens SET usado = 1 WHERE token = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('s', $token);
                $stmt->execute();
                
                // Registrar auditoría
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $query = "INSERT INTO acc_auditoria_accesos (usuario_id, accion, modulo, descripcion, ip_address, user_agent) 
                          VALUES (?, 'password_reset_completed', 'acc_usuarios', 'Contraseña restablecida exitosamente', ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('iss', $usuario_id, $ip_address, $user_agent);
                $stmt->execute();
                
                $success = 'Contraseña restablecida exitosamente. Ya puedes iniciar sesión.';
                $step = 'completed';
            } else {
                $error = 'Token inválido o expirado. Solicita un nuevo enlace de recuperación.';
            }
        } catch (Exception $e) {
            $error = 'Error interno del servidor. Intenta nuevamente.';
            error_log("Password reset error: " . $e->getMessage());
        }
    }
}

// Verificar token para mostrar formulario de reset
if ($step === 'reset' && isset($_GET['token'])) {
    $token = $_GET['token'];
    $query = "SELECT 1 FROM password_reset_tokens 
              WHERE token = ? AND fecha_expiracion > NOW() AND usado = 0 LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $error = 'Token inválido o expirado. Solicita un nuevo enlace de recuperación.';
        $step = 'request';
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
    <title>🔓 Recuperar Contraseña - Sequoia Speed</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔓</text></svg>">
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
        
        .recovery-container {
            width: 100%;
            max-width: 400px;
            padding: var(--space-lg);
        }
        
        .recovery-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: var(--space-xl);
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }
        
        .recovery-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--color-primary), var(--color-success));
        }
        
        .recovery-header {
            text-align: center;
            margin-bottom: var(--space-xl);
        }
        
        .recovery-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: var(--space-sm);
        }
        
        .recovery-subtitle {
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
        
        .recovery-footer {
            text-align: center;
            margin-top: var(--space-xl);
            padding-top: var(--space-lg);
            border-top: 1px solid var(--border-color);
        }
        
        .recovery-footer a {
            color: var(--color-primary);
            text-decoration: none;
            font-size: 13px;
            transition: color var(--transition-fast);
        }
        
        .recovery-footer a:hover {
            color: var(--text-primary);
        }
        
        @media (max-width: 480px) {
            .recovery-container {
                padding: var(--space-md);
            }
            
            .recovery-card {
                padding: var(--space-lg);
            }
            
            .recovery-title {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="recovery-container">
        <div class="recovery-card">
            <div class="recovery-header">
                <?php if ($step === 'request'): ?>
                    <h1 class="recovery-title">🔓 Recuperar Contraseña</h1>
                    <p class="recovery-subtitle">Ingresa tu email para recibir un enlace de recuperación</p>
                <?php elseif ($step === 'reset'): ?>
                    <h1 class="recovery-title">🔒 Nueva Contraseña</h1>
                    <p class="recovery-subtitle">Ingresa tu nueva contraseña</p>
                <?php else: ?>
                    <h1 class="recovery-title">✅ Contraseña Restablecida</h1>
                    <p class="recovery-subtitle">Tu contraseña ha sido actualizada exitosamente</p>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <span class="alert-icon">⚠️</span>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <span class="alert-icon">✅</span>
                    <span><?php echo $success; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($step === 'request' && empty($success)): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="form-group">
                        <label for="email" class="form-label">📧 Email</label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-input" 
                               placeholder="tu@email.com"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                               required>
                    </div>
                    
                    <button type="submit" class="btn">
                        🚀 Enviar Enlace de Recuperación
                    </button>
                </form>
            <?php elseif ($step === 'reset'): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token'] ?? ''); ?>">
                    
                    <div class="form-group">
                        <label for="new_password" class="form-label">🔒 Nueva Contraseña</label>
                        <input type="password" 
                               id="new_password" 
                               name="new_password" 
                               class="form-input" 
                               placeholder="Mínimo 6 caracteres"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">🔒 Confirmar Contraseña</label>
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               class="form-input" 
                               placeholder="Repite la contraseña"
                               required>
                    </div>
                    
                    <button type="submit" class="btn">
                        🔑 Restablecer Contraseña
                    </button>
                </form>
            <?php endif; ?>
            
            <div class="recovery-footer">
                <?php if ($step === 'completed' || !empty($success)): ?>
                    <a href="login.php">🔐 Iniciar Sesión</a>
                    <br><br>
                <?php endif; ?>
                <a href="login.php">← Volver al login</a>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-focus en el primer campo
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.querySelector('input[type="email"], input[type="password"]');
            if (firstInput) {
                firstInput.focus();
            }
        });
        
        // Validar que las contraseñas coincidan
        const confirmPassword = document.getElementById('confirm_password');
        if (confirmPassword) {
            confirmPassword.addEventListener('input', function() {
                const newPassword = document.getElementById('new_password').value;
                const confirmValue = this.value;
                
                if (confirmValue && newPassword !== confirmValue) {
                    this.setCustomValidity('Las contraseñas no coinciden');
                } else {
                    this.setCustomValidity('');
                }
            });
        }
    </script>
</body>
</html>