<?php
/**
 * API de Pedidos - Actualizar Estado
 * Sequoia Speed - Sistema de gestión de pedidos
 * 
 * Endpoint: POST /public/api/pedidos/update-status.php
 * Migrado desde: actualizar_estado.php
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
                isset($_POST['id']) || 
                !empty($_POST);

    if ($isLegacy) {
        // Procesar datos del formato legacy
        $pedidoId = $_POST['id'] ?? '';
        $nuevoEstado = $_POST['estado'] ?? '';
        $notas = $_POST['notas'] ?? '';
        $guia = $_POST['guia'] ?? '';
    } else {
        // Procesar datos del formato moderno (JSON)
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Datos JSON inválidos');
        }
        
        $pedidoId = $data['pedido_id'] ?? '';
        $nuevoEstado = $data['estado'] ?? '';
        $notas = $data['notas'] ?? '';
        $guia = $data['guia'] ?? '';
    }

    // Validar datos requeridos
    if (empty($pedidoId)) {
        throw new Exception('ID del pedido es requerido');
    }

    if (empty($nuevoEstado)) {
        throw new Exception('Nuevo estado es requerido');
    }

    // Validar estado
    $estadosValidos = [
        'pendiente',
        'confirmado', 
        'preparando',
        'enviado',
        'entregado',
        'cancelado',
        'devuelto'
    ];

    if (!in_array($nuevoEstado, $estadosValidos)) {
        throw new Exception('Estado no válido');
    }

    // Crear controlador de pedidos
    $pedidoController = new PedidoController();
    
    // Obtener datos actuales del pedido
    $pedidoActual = $pedidoController->getById($pedidoId);
    if (!$pedidoActual) {
        throw new Exception('Pedido no encontrado');
    }

    // Actualizar el estado
    $updateData = [
        'estado' => $nuevoEstado,
        'fecha_actualizacion' => date('Y-m-d H:i:s')
    ];

    if (!empty($notas)) {
        $updateData['notas'] = $notas;
    }

    if (!empty($guia)) {
        $updateData['guia_envio'] = $guia;
    }

    $success = $pedidoController->updateStatus($pedidoId, $updateData);
    
    if (!$success) {
        throw new Exception('Error al actualizar el estado del pedido');
    }

    // Enviar notificación por email si es necesario
    try {
        $emailService = new EmailService();
        
        // Enviar notificación según el estado
        switch ($nuevoEstado) {
            case 'confirmado':
                $emailService->sendOrderConfirmed($pedidoId, $pedidoActual);
                break;
            case 'enviado':
                $emailService->sendOrderShipped($pedidoId, $pedidoActual, $guia);
                break;
            case 'entregado':
                $emailService->sendOrderDelivered($pedidoId, $pedidoActual);
                break;
            case 'cancelado':
                $emailService->sendOrderCancelled($pedidoId, $pedidoActual, $notas);
                break;
        }
    } catch (Exception $e) {
        // Log del error pero no fallar la actualización
        error_log("Error enviando notificación de estado: " . $e->getMessage());
    }

    // Obtener datos actualizados
    $pedidoActualizado = $pedidoController->getById($pedidoId);

    // Respuesta exitosa
    $response = [
        'success' => true,
        'message' => 'Estado actualizado exitosamente',
        'data' => [
            'pedido_id' => $pedidoId,
            'estado_anterior' => $pedidoActual['estado'],
            'estado_nuevo' => $nuevoEstado,
            'fecha_actualizacion' => $updateData['fecha_actualizacion'],
            'pedido' => $pedidoActualizado
        ]
    ];

    // Para compatibilidad legacy
    if ($isLegacy) {
        $response['estado'] = $nuevoEstado;
        $response['fecha'] = $updateData['fecha_actualizacion'];
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(400);
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode() ?: 400
    ];
    
    error_log("Error en API update status: " . $e->getMessage());
    echo json_encode($response);
    
} catch (Error $e) {
    http_response_code(500);
    $response = [
        'success' => false,
        'error' => 'Error interno del servidor',
        'code' => 500
    ];
    
    error_log("Error fatal en API update status: " . $e->getMessage());
    echo json_encode($response);
}
