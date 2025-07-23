<?php
/**
 * Página de Acceso No Autorizado
 * Sequoia Speed - Sistema de Accesos
 */

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once dirname(__DIR__) . '/config_secure.php';
require_once 'middleware/AuthMiddleware.php';

// Inicializar middleware
$auth = new AuthMiddleware($conn);

// Verificar si está autenticado
$current_user = $auth->getCurrentUser();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚫 Acceso No Autorizado - Sequoia Speed</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🚫</text></svg>">
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
        
        .error-container {
            text-align: center;
            max-width: 600px;
            padding: var(--space-xl);
        }
        
        .error-icon {
            font-size: 120px;
            margin-bottom: var(--space-lg);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .error-title {
            font-size: 48px;
            font-weight: 700;
            color: var(--color-danger);
            margin-bottom: var(--space-md);
        }
        
        .error-subtitle {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: var(--space-lg);
        }
        
        .error-description {
            font-size: 16px;
            color: var(--text-secondary);
            margin-bottom: var(--space-xl);
            line-height: 1.8;
        }
        
        .error-actions {
            display: flex;
            gap: var(--space-md);
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-md) var(--space-xl);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all var(--transition-fast);
            white-space: nowrap;
        }
        
        .btn-primary {
            background: var(--color-primary);
            color: white;
            border-color: var(--color-primary);
        }
        
        .btn-primary:hover {
            background: #4493f8;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border-color: var(--border-color);
        }
        
        .btn-secondary:hover {
            background: var(--bg-hover);
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }
        
        .user-info {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: var(--space-lg);
            margin-bottom: var(--space-xl);
            text-align: left;
        }
        
        .user-info h3 {
            color: var(--text-primary);
            margin-bottom: var(--space-md);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }
        
        .user-info p {
            color: var(--text-secondary);
            margin-bottom: var(--space-sm);
        }
        
        .contact-info {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: var(--space-lg);
            margin-top: var(--space-xl);
        }
        
        .contact-info h4 {
            color: var(--text-primary);
            margin-bottom: var(--space-md);
        }
        
        .contact-info p {
            color: var(--text-secondary);
            font-size: 13px;
        }
        
        @media (max-width: 768px) {
            .error-container {
                padding: var(--space-lg);
            }
            
            .error-icon {
                font-size: 80px;
            }
            
            .error-title {
                font-size: 36px;
            }
            
            .error-subtitle {
                font-size: 20px;
            }
            
            .error-description {
                font-size: 14px;
            }
            
            .error-actions {
                flex-direction: column;
            }
            
            .btn {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">🚫</div>
        <div class="error-title">403</div>
        <div class="error-subtitle">Acceso No Autorizado</div>
        <div class="error-description">
            Lo sentimos, no tienes permisos suficientes para acceder a esta página o realizar esta acción.
            <br><br>
            Si crees que deberías tener acceso, por favor contacta al administrador del sistema.
        </div>
        
        <?php if ($current_user): ?>
            <div class="user-info">
                <h3>👤 Información de tu sesión</h3>
                <p><strong>Usuario:</strong> <?php echo htmlspecialchars($current_user['nombre']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($current_user['email']); ?></p>
                <p><strong>Último acceso:</strong> <?php echo $current_user['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($current_user['ultimo_acceso'])) : 'Nunca'; ?></p>
            </div>
        <?php endif; ?>
        
        <div class="error-actions">
            <a href="javascript:history.back()" class="btn btn-secondary">
                ← Volver Atrás
            </a>
            
            <?php if ($current_user): ?>
                <a href="dashboard.php" class="btn btn-primary">
                    🏠 Ir al Dashboard
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary">
                    🔐 Iniciar Sesión
                </a>
            <?php endif; ?>
            
            <a href="../listar_pedidos.php" class="btn btn-secondary">
                🛒 Sistema Principal
            </a>
        </div>
        
        <div class="contact-info">
            <h4>📞 ¿Necesitas ayuda?</h4>
            <p>
                Si necesitas permisos adicionales o tienes problemas de acceso, 
                contacta al administrador del sistema en <strong>admin@sequoiaspeed.com</strong>
            </p>
        </div>
    </div>
    
    <script>
        // Auto-redirigir después de 30 segundos si no está autenticado
        <?php if (!$current_user): ?>
        setTimeout(function() {
            window.location.href = 'login.php';
        }, 30000);
        <?php endif; ?>
        
        // Mostrar mensaje de redirección
        <?php if (!$current_user): ?>
        setTimeout(function() {
            const description = document.querySelector('.error-description');
            description.innerHTML += '<br><br><em style="color: var(--color-warning);">Serás redirigido al login en ' + 
                '<span id="countdown">30</span> segundos...</em>';
            
            let countdown = 30;
            const countdownElement = document.getElementById('countdown');
            
            const interval = setInterval(function() {
                countdown--;
                if (countdownElement) {
                    countdownElement.textContent = countdown;
                }
                
                if (countdown <= 0) {
                    clearInterval(interval);
                }
            }, 1000);
        }, 1000);
        <?php endif; ?>
    </script>
</body>
</html>