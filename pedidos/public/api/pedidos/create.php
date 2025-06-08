<?php
/**
 * API de Pedidos - Crear Pedido
 * Sequoia Speed - Sistema de gestión de pedidos
 * 
 * Endpoint: POST /public/api/pedidos/create.php
 * Migrado desde: guardar_pedido.php
 */

require_once __DIR__ . '/../../../bootstrap.php';

use SequoiaSpeed\Controllers\PedidoController;
use SequoiaSpeed\Services\EmailService;

// Configurar headers para API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Legacy-Compatibility');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

try {
    // Detectar si es una petición legacy
    $isLegacy = isset($_SERVER['HTTP_X_LEGACY_COMPATIBILITY']) || 
                isset($_POST['nombre']) || 
                !empty($_POST);

    if ($isLegacy) {
        // Procesar datos del formato legacy (form-data)
        $data = [
            'nombre' => $_POST['nombre'] ?? '',
            'correo' => $_POST['correo'] ?? '',
            'telefono' => $_POST['telefono'] ?? '',
            'direccion' => $_POST['direccion'] ?? '',
            'productos' => json_decode($_POST['productos'] ?? '[]', true),
            'monto' => $_POST['monto'] ?? 0,
            'metodo_pago' => $_POST['metodo_pago'] ?? '',
            'notas' => $_POST['notas'] ?? '',
            'payment_order_id' => $_POST['payment_order_id'] ?? null,
            'payment_status' => $_POST['payment_status'] ?? 'pending',
            'payment_data' => $_POST['payment_data'] ?? null
        ];
    } else {
        // Procesar datos del formato moderno (JSON)
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Datos JSON inválidos');
        }
    }

    // Validar datos requeridos
    $requiredFields = ['nombre', 'correo', 'telefono', 'direccion', 'productos'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            throw new Exception("El campo '{$field}' es requerido");
        }
    }

    // Validar email
    if (!filter_var($data['correo'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email inválido');
    }

    // Validar productos
    if (!is_array($data['productos']) || empty($data['productos'])) {
        throw new Exception('Debe incluir al menos un producto');
    }

    // Crear controlador de pedidos
    $pedidoController = new PedidoController();
    
    // Crear el pedido
    $pedidoId = $pedidoController->create($data);
    
    if (!$pedidoId) {
        throw new Exception('Error al crear el pedido');
    }

    // Enviar email de confirmación si está configurado
    try {
        $emailService = new EmailService();
        $emailService->sendOrderConfirmation($pedidoId, $data);
    } catch (Exception $e) {
        // Log del error pero no fallar el pedido
        error_log("Error enviando email de confirmación: " . $e->getMessage());
    }

    // Respuesta exitosa
    $response = [
        'success' => true,
        'message' => 'Pedido creado exitosamente',
        'data' => [
            'pedido_id' => $pedidoId,
            'numero_pedido' => 'SEQ-' . str_pad($pedidoId, 6, '0', STR_PAD_LEFT),
            'estado' => 'pendiente',
            'monto' => $data['monto'],
            'payment_order_id' => $data['payment_order_id'] ?? null
        ]
    ];

    // Para compatibilidad legacy, también incluir datos en formato legacy
    if ($isLegacy) {
        $response['pedido_id'] = $pedidoId;
        $response['numero_pedido'] = 'SEQ-' . str_pad($pedidoId, 6, '0', STR_PAD_LEFT);
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(400);
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode() ?: 400
    ];
    
    // Log del error
    error_log("Error en API create pedido: " . $e->getMessage());
    
    echo json_encode($response);
} catch (Error $e) {
    http_response_code(500);
    $response = [
        'success' => false,
        'error' => 'Error interno del servidor',
        'code' => 500
    ];
    
    // Log del error
    error_log("Error fatal en API create pedido: " . $e->getMessage());
    
    echo json_encode($response);
}
