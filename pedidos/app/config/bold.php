<?php
/**
 * Configuración Bold PSE
 * 
 * Centraliza toda la configuración de Bold PSE
 */

return [
    'api_key' => getenv('BOLD_API_KEY') ?: '',
    'public_key' => getenv('BOLD_PUBLIC_KEY') ?: '',
    'environment' => getenv('BOLD_ENVIRONMENT') ?: 'production',
    'webhook_secret' => getenv('BOLD_WEBHOOK_SECRET') ?: '',
    'production_mode' => filter_var(getenv('BOLD_PRODUCTION_MODE') ?: 'true', FILTER_VALIDATE_BOOLEAN),
    
    // URLs de Bold según el ambiente
    'urls' => [
        'production' => [
            'checkout' => 'https://checkout.bold.co',
            'api' => 'https://api.bold.co',
            'webhook' => 'https://api.bold.co/webhook'
        ],
        'sandbox' => [
            'checkout' => 'https://checkout-sandbox.bold.co',
            'api' => 'https://api-sandbox.bold.co',
            'webhook' => 'https://api-sandbox.bold.co/webhook'
        ]
    ],
    
    // Configuración del webhook
    'webhook' => [
        'enhanced_percentage' => 100, // 100% usa webhook mejorado
        'retry_attempts' => 5,
        'retry_delay' => 300, // 5 minutos
        'timeout' => 30
    ],
    
    // Métodos de pago disponibles
    'payment_methods' => [
        'PSE',
        'CARD',
        'NEQUI',
        'DAVIPLATA'
    ],
    
    // Configuración de seguridad
    'security' => [
        'hash_algorithm' => 'sha256',
        'validate_ip' => true,
        'allowed_ips' => [
            '52.28.85.141',
            '18.156.184.242',
            '3.121.84.229'
        ]
    ]
];
