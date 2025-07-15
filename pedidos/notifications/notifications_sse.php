<?php
/**
 * Server-Sent Events para Notificaciones en Tiempo Real
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Prevenir timeout
set_time_limit(0);
ignore_user_abort(true);

// Headers para SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Para nginx

require_once dirname(__DIR__) . '/conexion.php';

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

// Loop infinito para mantener la conexión
while (true) {
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
    if (time() % 30 == 0) {
        sendSSE(['type' => 'heartbeat', 'timestamp' => time()]);
    }
    
    // Esperar 2 segundos antes de verificar nuevamente
    sleep(2);
    
    // Verificar si la conexión sigue activa
    if (connection_aborted()) {
        break;
    }
}

$conn->close();
?>