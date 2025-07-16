<?php
/**
 * Server-Sent Events para Notificaciones en Tiempo Real
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Limitar tiempo de ejecución a 5 minutos para prevenir procesos colgados
set_time_limit(300);
ignore_user_abort(false);

// Headers para SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Para nginx

require_once dirname(__DIR__) . '/config_secure.php';
require_once __DIR__ . '/sse_manager.php';

// Verificar si las notificaciones SSE están deshabilitadas
$disableFile = dirname(__DIR__) . '/tmp/sse_disabled.flag';
if (file_exists($disableFile)) {
    http_response_code(503);
    echo "data: " . json_encode(['type' => 'error', 'message' => 'Notificaciones SSE temporalmente deshabilitadas']) . "\n\n";
    exit;
}

// Inicializar gestor de conexiones SSE
$sseManager = new SSEManager();

// Verificar si se pueden crear más conexiones
if (!$sseManager->canCreateConnection()) {
    http_response_code(503);
    echo "data: " . json_encode(['type' => 'error', 'message' => 'Máximo de conexiones alcanzado']) . "\n\n";
    exit;
}

// Registrar esta conexión
$sseManager->registerConnection();

// Función para enviar evento SSE
function sendSSE($data) {
    echo "data: " . json_encode($data) . "\n\n";
    ob_flush();
    flush();
}

// Enviar heartbeat inicial
sendSSE(['type' => 'connected', 'timestamp' => time()]);

// ID de la última notificación enviada
$last_id = isset($_SERVER['HTTP_LAST_EVENT_ID']) ? (int)$_SERVER['HTTP_LAST_EVENT_ID'] : 0;

// Variables de control
$max_execution_time = 300; // 5 minutos
$start_time = time();
$heartbeat_interval = 30;
$last_heartbeat = time();

// Loop con límite de tiempo para mantener la conexión
while (time() - $start_time < $max_execution_time && !connection_aborted()) {
    // Verificar nuevas notificaciones
    $query = "SELECT * FROM notifications 
              WHERE user_id = 'admin' 
              AND id > ? 
              AND read_at IS NULL
              ORDER BY created_at ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $last_id);
    $stmt->execute();
    
    // Usar bind_result para compatibilidad
    $stmt->store_result();
    $stmt->bind_result($id, $user_id, $type, $title, $message, $data_json, $read_at, $created_at, $expires_at);
    
    while ($stmt->fetch()) {
        $notification = [
            'id' => $id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data_json ? json_decode($data_json, true) : null,
            'created_at' => $created_at
        ];
        
        // Enviar notificación
        sendSSE([
            'type' => 'notification',
            'notification' => $notification
        ]);
        
        $last_id = $id;
    }
    
    $stmt->close();
    
    // Enviar heartbeat cada 30 segundos
    if (time() - $last_heartbeat >= $heartbeat_interval) {
        sendSSE(['type' => 'heartbeat', 'timestamp' => time()]);
        $last_heartbeat = time();
        $sseManager->updateHeartbeat();
    }
    
    // Esperar 2 segundos antes de verificar nuevamente
    sleep(2);
}

// Enviar mensaje de desconexión antes de terminar
sendSSE(['type' => 'disconnect', 'reason' => 'timeout_or_abort', 'timestamp' => time()]);

// Limpiar registro de conexión
$sseManager->unregisterConnection();

$conn->close();