<?php
/**
 * Configuración SMTP
 * 
 * Centraliza la configuración de correo electrónico manteniendo
 * compatibilidad con smtp_config.php existente
 */

return [
    'host' => getenv('SMTP_HOST') ?: 'mail.sequoiaspeed.com.co',
    'port' => (int)(getenv('SMTP_PORT') ?: 587),
    'username' => getenv('SMTP_USERNAME') ?: '',
    'password' => getenv('SMTP_PASSWORD') ?: '',
    'encryption' => 'tls',
    'from' => [
        'email' => getenv('SMTP_FROM_EMAIL') ?: 'info@sequoiaspeed.com.co',
        'name' => getenv('SMTP_FROM_NAME') ?: 'Sequoia Speed'
    ],
    'timeout' => 30,
    'local_domain' => 'sequoiaspeed.com.co'
];
