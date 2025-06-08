<?php
/**
 * Configuración general de la aplicación Sequoia Speed
 * Archivo: app/config/app.php
 */

return [
    // Información básica de la aplicación
    'name' => $_ENV['APP_NAME'] ?? 'Sequoia Speed',
    'version' => $_ENV['APP_VERSION'] ?? '2.0.0',
    'environment' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'America/Bogota',
    
    // URLs y rutas
    'url' => $_ENV['APP_URL'] ?? 'https://sequoiaspeed.com',
    'base_path' => dirname(dirname(__DIR__)),
    'public_path' => dirname(dirname(__DIR__)) . '/public',
    
    // Configuración de uploads
    'uploads' => [
        'max_size' => $_ENV['UPLOAD_MAX_SIZE'] ?? '10M',
        'allowed_types' => ['jpg', 'jpeg', 'png', 'pdf', 'gif'],
        'comprobantes_path' => 'comprobantes/',
        'guias_path' => 'guias/',
        'temp_path' => 'uploads/',
    ],
    
    // Configuración de archivos
    'files' => [
        'comprobantes_retention_days' => 365,
        'logs_retention_days' => 30,
        'backup_retention_days' => 90,
    ],
    
    // Configuración de sesiones
    'session' => [
        'name' => 'sequoia_session',
        'lifetime' => 7200, // 2 horas
        'secure' => $_ENV['APP_ENV'] === 'production',
        'httponly' => true,
    ],
    
    // Configuración de caché
    'cache' => [
        'default' => 'file',
        'stores' => [
            'file' => [
                'driver' => 'file',
                'path' => dirname(dirname(__DIR__)) . '/storage/cache',
            ],
        ],
        'prefix' => 'sequoia_',
    ],
    
    // Configuración de logs
    'logging' => [
        'default' => 'daily',
        'channels' => [
            'daily' => [
                'driver' => 'daily',
                'path' => dirname(dirname(__DIR__)) . '/storage/logs/app.log',
                'level' => $_ENV['LOG_LEVEL'] ?? 'info',
                'days' => 14,
            ],
            'error' => [
                'driver' => 'single',
                'path' => dirname(dirname(__DIR__)) . '/storage/logs/error.log',
                'level' => 'error',
            ],
        ],
    ],
    
    // Configuración de seguridad
    'security' => [
        'csrf_token_name' => 'csrf_token',
        'csrf_token_lifetime' => 3600,
        'password_min_length' => 8,
        'max_login_attempts' => 5,
        'lockout_duration' => 900, // 15 minutos
    ],
    
    // Configuración específica de pedidos
    'orders' => [
        'auto_archive_days' => 30,
        'max_notes_per_order' => 100,
        'notification_delay' => 300, // 5 minutos
        'pdf_generation_timeout' => 30,
    ],
    
    // Configuración de notificaciones
    'notifications' => [
        'email_enabled' => filter_var($_ENV['NOTIFICATIONS_EMAIL'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'sms_enabled' => filter_var($_ENV['NOTIFICATIONS_SMS'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'webhook_enabled' => filter_var($_ENV['NOTIFICATIONS_WEBHOOK'] ?? true, FILTER_VALIDATE_BOOLEAN),
    ],
    
    // Configuración de mantenimiento
    'maintenance' => [
        'enabled' => filter_var($_ENV['MAINTENANCE_MODE'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'message' => $_ENV['MAINTENANCE_MESSAGE'] ?? 'Sistema en mantenimiento. Volveremos pronto.',
        'allowed_ips' => explode(',', $_ENV['MAINTENANCE_ALLOWED_IPS'] ?? ''),
    ],
];
