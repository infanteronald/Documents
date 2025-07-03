<?php
/**
 * API Router - Sequoia Speed
 * Sistema de enrutamiento para APIs modernas
 *
 * Este archivo maneja el enrutamiento automático de las APIs
 * y proporciona información sobre los endpoints disponibles
 */

require_once __DIR__ . '/../../bootstrap.php';

// Configurar headers comunes
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Legacy-Compatibility, X-Bold-Signature');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo para solicitudes GET mostrar la documentación
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Use GET para ver la documentación de la API']);
    exit;
}

header('Content-Type: application/json');

$baseUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);

$apiDocumentation = [
    'name' => 'Sequoia Speed API',
    'version' => '2.0.0',
    'description' => 'API moderna para el sistema de gestión de pedidos Sequoia Speed',
    'base_url' => $baseUrl,
    'documentation_url' => $baseUrl . '/pedido.php',
    'compatibility' => [
        'legacy_support' => true,
        'modern_features' => true,
        'migration_status' => 'Fase 2 - Migración gradual'
    ],
    'endpoints' => [
        'pedidos' => [
            'create' => [
                'url' => $baseUrl . '/pedidos/create.php',
                'method' => 'POST',
                'description' => 'Crear un nuevo pedido',
                'legacy_equivalent' => 'guardar_pedido.php',
                'parameters' => [
                    'nombre' => 'string (requerido) - Nombre del cliente',
                    'correo' => 'string (requerido) - Email del cliente',
                    'telefono' => 'string (requerido) - Teléfono del cliente',
                    'direccion' => 'string (requerido) - Dirección de entrega',
                    'productos' => 'array (requerido) - Lista de productos',
                    'monto' => 'number - Monto total del pedido',
                    'metodo_pago' => 'string - Método de pago',
                    'notas' => 'string - Notas adicionales'
                ],
                'response' => [
                    'success' => 'boolean',
                    'message' => 'string',
                    'data' => [
                        'pedido_id' => 'number',
                        'numero_pedido' => 'string',
                        'estado' => 'string'
                    ]
                ]
            ],
            'update-status' => [
                'url' => $baseUrl . '/pedidos/update-status.php',
                'method' => 'POST',
                'description' => 'Actualizar el estado de un pedido',
                'legacy_equivalent' => 'actualizar_estado.php',
                'parameters' => [
                    'pedido_id' => 'number (requerido) - ID del pedido',
                    'estado' => 'string (requerido) - Nuevo estado',
                    'notas' => 'string - Notas adicionales',
                    'guia' => 'string - Número de guía de envío'
                ],
                'valid_states' => [
                    'pendiente', 'confirmado', 'preparando',
                    'enviado', 'entregado', 'cancelado', 'devuelto'
                ]
            ]
        ],
        'productos' => [
            'by-category' => [
                'url' => $baseUrl . '/productos/by-category.php',
                'method' => 'GET',
                'description' => 'Obtener productos por categoría',
                'legacy_equivalent' => 'productos_por_categoria.php',
                'parameters' => [
                    'categoria' => 'string - Categoría específica (opcional)',
                    'limite' => 'number - Productos por página (máximo 100)',
                    'pagina' => 'number - Número de página',
                    'buscar' => 'string - Término de búsqueda',
                    'activos' => 'boolean - Solo productos activos'
                ]
            ]
        ],
        'bold' => [
            'webhook' => [
                'url' => $baseUrl . '/bold/webhook.php',
                'method' => 'POST',
                'description' => 'Webhook para notificaciones de Bold PSE',
                'legacy_equivalent' => 'bold_webhook_enhanced.php',
                'headers' => [
                    'X-Bold-Signature' => 'Firma de verificación de Bold'
                ],
                'webhook_types' => [
                    'payment.completed', 'payment.failed', 'payment.pending',
                    'payment.cancelled', 'payment.refunded'
                ]
            ]
        ],
        'exports' => [
            'excel' => [
                'url' => $baseUrl . '/exports/excel.php',
                'method' => 'GET',
                'description' => 'Exportar datos a Excel/CSV/JSON',
                'legacy_equivalent' => 'exportar_excel.php',
                'parameters' => [
                    'tipo' => 'string (requerido) - Tipo de exportación',
                    'formato' => 'string - Formato del archivo (xlsx, csv, json)',
                    'fecha_inicio' => 'string - Fecha de inicio (YYYY-MM-DD)',
                    'fecha_fin' => 'string - Fecha de fin (YYYY-MM-DD)',
                    'estado' => 'string - Filtrar por estado'
                ],
                'export_types' => ['pedidos', 'productos', 'clientes', 'ventas']
            ]
        ]
    ],
    'authentication' => [
        'type' => 'none',
        'note' => 'Autenticación a implementar en fases futuras'
    ],
    'headers' => [
        'Content-Type' => 'application/json',
        'X-Legacy-Compatibility' => 'Marca peticiones como legacy para compatibilidad',
        'X-Bold-Signature' => 'Firma de verificación para webhooks de Bold'
    ],
    'error_handling' => [
        'format' => [
            'success' => 'boolean',
            'error' => 'string',
            'code' => 'number'
        ],
        'http_codes' => [
            200 => 'Éxito',
            400 => 'Solicitud inválida',
            404 => 'Recurso no encontrado',
            405 => 'Método no permitido',
            500 => 'Error interno del servidor'
        ]
    ],
    'migration_info' => [
        'status' => 'Fase 2 - Migración gradual',
        'compatibility' => '100% compatible con archivos legacy',
        'modern_features' => [
            'Estructura MVC profesional',
            'APIs RESTful modernas',
            'Validación mejorada',
            'Manejo de errores robusto',
            'Logging centralizado',
            'Headers CORS configurados'
        ],
        'next_phase' => 'Fase 3 - Optimización y limpieza'
    ],
    'examples' => [
        'crear_pedido' => [
            'url' => $baseUrl . '/pedidos/create.php',
            'method' => 'POST',
            'body' => [
                'nombre' => 'Juan Pérez',
                'correo' => 'juan@example.com',
                'telefono' => '3001234567',
                'direccion' => 'Calle 123 #45-67, Bogotá',
                'productos' => [
                    ['id' => 1, 'nombre' => 'Producto A', 'cantidad' => 2, 'precio' => 50000],
                    ['id' => 2, 'nombre' => 'Producto B', 'cantidad' => 1, 'precio' => 75000]
                ],
                'monto' => 175000,
                'metodo_pago' => 'PSE Bold'
            ]
        ],
        'productos_por_categoria' => [
            'url' => $baseUrl . '/productos/by-category.php?categoria=electronica&limite=20&pagina=1',
            'method' => 'GET'
        ]
    ]
];

echo json_encode($apiDocumentation, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
