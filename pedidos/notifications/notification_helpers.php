<?php
/**
 * Funciones auxiliares para crear notificaciones
 * Incluir este archivo donde se necesite crear notificaciones
 */

// Solo incluir conexión, no el archivo API completo
require_once dirname(__DIR__) . '/config_secure.php';

// Incluir push_sender.php solo si existe y composer está instalado
if (file_exists('push_sender.php') && file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once 'push_sender.php';
}

/**
 * Función para crear notificación en base de datos
 */
function createNotification($conn, $type, $title, $message, $data = null, $user_id = 'admin') {
    $query = "INSERT INTO notifications (user_id, type, title, message, data_json) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    
    $data_json = $data ? json_encode($data) : null;
    $stmt->bind_param("sssss", $user_id, $type, $title, $message, $data_json);
    
    if ($stmt->execute()) {
        $notification_id = $stmt->insert_id;
        
        // Also send push notification
        $pushData = $data ? $data : [];
        $pushData['notification_id'] = $notification_id;
        
        // Map notification types to push types
        $pushType = mapNotificationTypeToPushType($type, $data);
        
        // Solo enviar push notification si la función existe
        if (function_exists('sendPushNotification')) {
            sendPushNotification($title, $message, $pushData, $user_id, $pushType);
        }
        
        return $notification_id;
    }
    return false;
}

/**
 * Map notification types to push notification types
 */
function mapNotificationTypeToPushType($type, $data) {
    // Check data to determine more specific type
    if (isset($data['pedido_id'])) {
        if (strpos(strtolower($data['actions'][0]['label'] ?? ''), 'pedido') !== false) {
            return 'new_order';
        }
        if (strpos(strtolower($data['actions'][0]['label'] ?? ''), 'pago') !== false) {
            return 'payment';
        }
        if (strpos(strtolower($data['actions'][0]['label'] ?? ''), 'guía') !== false) {
            return 'shipment';
        }
    }
    
    // Default mapping
    $typeMap = [
        'info' => 'status_change',
        'success' => 'status_change',
        'error' => 'error',
        'warning' => 'warning'
    ];
    
    return $typeMap[$type] ?? 'status_change';
}

/**
 * Notificar nuevo pedido
 */
function notificarNuevoPedido($pedido_id, $nombre_cliente, $monto) {
    global $conn;
    
    $title = "Nuevo Pedido Recibido";
    $message = "Pedido #{$pedido_id} de {$nombre_cliente} por $" . number_format($monto, 0, ',', '.');
    
    $data = [
        'pedido_id' => $pedido_id,
        'cliente' => $nombre_cliente,
        'monto' => $monto,
        'actions' => [
            [
                'label' => 'Ver Pedido',
                'url' => "/pedidos/ver_detalle_pedido.php?id={$pedido_id}",
                'target' => '_blank'
            ]
        ]
    ];
    
    return createNotification($conn, 'info', $title, $message, $data);
}

/**
 * Notificar pago confirmado
 */
function notificarPagoConfirmado($pedido_id, $monto, $metodo_pago) {
    global $conn;
    
    $title = "Pago Confirmado";
    $message = "Pedido #{$pedido_id} - $" . number_format($monto, 0, ',', '.') . " via {$metodo_pago}";
    
    $data = [
        'pedido_id' => $pedido_id,
        'monto' => $monto,
        'metodo_pago' => $metodo_pago,
        'actions' => [
            [
                'label' => 'Ver Detalles',
                'url' => "/pedidos/ver_detalle_pedido.php?id={$pedido_id}",
                'target' => '_blank'
            ]
        ]
    ];
    
    return createNotification($conn, 'success', $title, $message, $data);
}

/**
 * Notificar guía adjuntada
 */
function notificarGuiaAdjuntada($pedido_id, $numero_guia = null) {
    global $conn;
    
    $title = "Guía de Envío Adjuntada";
    $message = "Se adjuntó guía para pedido #{$pedido_id}";
    if ($numero_guia) {
        $message .= " - Guía: {$numero_guia}";
    }
    
    $data = [
        'pedido_id' => $pedido_id,
        'numero_guia' => $numero_guia,
        'actions' => [
            [
                'label' => 'Ver Guía',
                'url' => "/pedidos/ver_detalle_pedido.php?id={$pedido_id}",
                'target' => '_blank'
            ]
        ]
    ];
    
    return createNotification($conn, 'success', $title, $message, $data);
}

/**
 * Notificar comprobante subido
 */
function notificarComprobanteSubido($pedido_id, $tipo_comprobante = 'pago') {
    global $conn;
    
    $title = "Comprobante Recibido";
    $message = "Nuevo comprobante de {$tipo_comprobante} para pedido #{$pedido_id}";
    
    $data = [
        'pedido_id' => $pedido_id,
        'tipo' => $tipo_comprobante,
        'actions' => [
            [
                'label' => 'Revisar',
                'url' => "/pedidos/ver_detalle_pedido.php?id={$pedido_id}",
                'target' => '_blank'
            ]
        ]
    ];
    
    return createNotification($conn, 'info', $title, $message, $data);
}

/**
 * Notificar cambio de estado
 */
function notificarCambioEstado($pedido_id, $estado_anterior, $estado_nuevo) {
    global $conn;
    
    $estados = [
        'enviado' => '🚚 Enviado',
        'pagado' => '💰 Pagado',
        'anulado' => '❌ Anulado',
        'archivado' => '📁 Archivado',
        'tienda' => '🏪 Entrega en Tienda'
    ];
    
    $title = "Estado Actualizado";
    $message = "Pedido #{$pedido_id} cambió a: " . ($estados[$estado_nuevo] ?? $estado_nuevo);
    
    $type = ($estado_nuevo === 'anulado') ? 'error' : 'info';
    
    $data = [
        'pedido_id' => $pedido_id,
        'estado_anterior' => $estado_anterior,
        'estado_nuevo' => $estado_nuevo,
        'actions' => [
            [
                'label' => 'Ver Pedido',
                'url' => "/pedidos/ver_detalle_pedido.php?id={$pedido_id}",
                'target' => '_blank'
            ]
        ]
    ];
    
    return createNotification($conn, $type, $title, $message, $data);
}

/**
 * Notificar pedido anulado
 */
function notificarPedidoAnulado($pedido_id, $razon = null) {
    global $conn;
    
    $title = "Pedido Anulado";
    $message = "El pedido #{$pedido_id} ha sido anulado";
    if ($razon) {
        $message .= ". Razón: {$razon}";
    }
    
    $data = [
        'pedido_id' => $pedido_id,
        'razon' => $razon,
        'actions' => [
            [
                'label' => 'Ver Detalles',
                'url' => "/pedidos/ver_detalle_pedido.php?id={$pedido_id}",
                'target' => '_blank'
            ]
        ]
    ];
    
    return createNotification($conn, 'error', $title, $message, $data);
}

/**
 * Notificar error en proceso
 */
function notificarError($proceso, $mensaje_error, $pedido_id = null) {
    global $conn;
    
    $title = "Error en {$proceso}";
    $message = $mensaje_error;
    
    $data = [
        'proceso' => $proceso,
        'error' => $mensaje_error
    ];
    
    if ($pedido_id) {
        $data['pedido_id'] = $pedido_id;
        $data['actions'] = [
            [
                'label' => 'Ver Pedido',
                'url' => "/pedidos/ver_detalle_pedido.php?id={$pedido_id}",
                'target' => '_blank'
            ]
        ];
    }
    
    return createNotification($conn, 'error', $title, $message, $data);
}

/**
 * Notificar acción exitosa genérica
 */
function notificarExito($titulo, $mensaje, $pedido_id = null) {
    global $conn;
    
    $data = [];
    if ($pedido_id) {
        $data['pedido_id'] = $pedido_id;
        $data['actions'] = [
            [
                'label' => 'Ver Pedido',
                'url' => "/pedidos/ver_detalle_pedido.php?id={$pedido_id}",
                'target' => '_blank'
            ]
        ];
    }
    
    return createNotification($conn, 'success', $titulo, $mensaje, $data);
}

/**
 * Notificar advertencia
 */
function notificarAdvertencia($titulo, $mensaje, $acciones = []) {
    global $conn;
    
    $data = [];
    if (!empty($acciones)) {
        $data['actions'] = $acciones;
    }
    
    return createNotification($conn, 'warning', $titulo, $mensaje, $data);
}
?>