<?php
/**
 * API de Notificaciones
 * Maneja todas las operaciones CRUD de notificaciones
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../conexion.php';

header('Content-Type: application/json');

// Función auxiliar para enviar respuesta JSON
function jsonResponse($success, $data = [], $error = null) {
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'error' => $error,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Función para crear notificación
function createNotification($conn, $type, $title, $message, $data = null, $user_id = 'admin') {
    $query = "INSERT INTO notifications (user_id, type, title, message, data_json) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    
    $data_json = $data ? json_encode($data) : null;
    $stmt->bind_param("sssss", $user_id, $type, $title, $message, $data_json);
    
    if ($stmt->execute()) {
        return $stmt->insert_id;
    }
    return false;
}

// Función helper para crear notificaciones internas
function addNotification($type, $title, $message, $data = null) {
    global $conn;
    return createNotification($conn, $type, $title, $message, $data);
}

// Manejar peticiones GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_unread':
            $query = "SELECT * FROM notifications 
                      WHERE user_id = 'admin' 
                      AND read_at IS NULL 
                      AND (expires_at IS NULL OR expires_at > NOW())
                      ORDER BY created_at DESC 
                      LIMIT 50";
            
            $result = $conn->query($query);
            $notifications = [];
            
            while ($row = $result->fetch_assoc()) {
                $row['data'] = $row['data_json'] ? json_decode($row['data_json'], true) : null;
                unset($row['data_json']);
                $notifications[] = $row;
            }
            
            jsonResponse(true, ['notifications' => $notifications]);
            break;
            
        case 'get_all':
            $limit = (int)($_GET['limit'] ?? 100);
            $offset = (int)($_GET['offset'] ?? 0);
            
            $query = "SELECT * FROM notifications 
                      WHERE user_id = 'admin'
                      ORDER BY created_at DESC 
                      LIMIT ? OFFSET ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $notifications = [];
            while ($row = $result->fetch_assoc()) {
                $row['data'] = $row['data_json'] ? json_decode($row['data_json'], true) : null;
                unset($row['data_json']);
                $notifications[] = $row;
            }
            
            jsonResponse(true, ['notifications' => $notifications]);
            break;
            
        case 'get_preferences':
            $query = "SELECT * FROM notification_preferences WHERE user_id = 'admin'";
            $result = $conn->query($query);
            $preferences = $result->fetch_assoc();
            
            if (!$preferences) {
                // Crear preferencias por defecto
                $conn->query("INSERT INTO notification_preferences (user_id) VALUES ('admin')");
                $preferences = [
                    'sound_enabled' => true,
                    'auto_dismiss_seconds' => 10,
                    'position' => 'top-right'
                ];
            }
            
            jsonResponse(true, ['preferences' => $preferences]);
            break;
            
        default:
            jsonResponse(false, [], 'Invalid action');
    }
}

// Manejar peticiones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $notification = $input['notification'] ?? [];
            
            if (empty($notification['type']) || empty($notification['title']) || empty($notification['message'])) {
                jsonResponse(false, [], 'Missing required fields');
            }
            
            $id = createNotification(
                $conn,
                $notification['type'],
                $notification['title'],
                $notification['message'],
                $notification['data'] ?? null,
                $notification['user_id'] ?? 'admin'
            );
            
            if ($id) {
                jsonResponse(true, ['notification_id' => $id]);
            } else {
                jsonResponse(false, [], 'Failed to create notification');
            }
            break;
            
        case 'mark_read':
            $notification_id = (int)($input['notification_id'] ?? 0);
            
            if (!$notification_id) {
                jsonResponse(false, [], 'Invalid notification ID');
            }
            
            $query = "UPDATE notifications SET read_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $notification_id);
            
            if ($stmt->execute()) {
                jsonResponse(true);
            } else {
                jsonResponse(false, [], 'Failed to mark as read');
            }
            break;
            
        case 'mark_all_read':
            $query = "UPDATE notifications SET read_at = NOW() WHERE user_id = 'admin' AND read_at IS NULL";
            
            if ($conn->query($query)) {
                jsonResponse(true, ['affected' => $conn->affected_rows]);
            } else {
                jsonResponse(false, [], 'Failed to mark all as read');
            }
            break;
            
        case 'delete':
            $notification_id = (int)($input['notification_id'] ?? 0);
            
            if (!$notification_id) {
                jsonResponse(false, [], 'Invalid notification ID');
            }
            
            $query = "DELETE FROM notifications WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $notification_id);
            
            if ($stmt->execute()) {
                jsonResponse(true);
            } else {
                jsonResponse(false, [], 'Failed to delete notification');
            }
            break;
            
        case 'update_preferences':
            $preferences = $input['preferences'] ?? [];
            
            $query = "UPDATE notification_preferences 
                      SET sound_enabled = ?, auto_dismiss_seconds = ?, position = ?
                      WHERE user_id = 'admin'";
            
            $stmt = $conn->prepare($query);
            $sound = (bool)($preferences['sound_enabled'] ?? true);
            $dismiss = (int)($preferences['auto_dismiss_seconds'] ?? 10);
            $position = $preferences['position'] ?? 'top-right';
            
            $stmt->bind_param("iis", $sound, $dismiss, $position);
            
            if ($stmt->execute()) {
                jsonResponse(true);
            } else {
                jsonResponse(false, [], 'Failed to update preferences');
            }
            break;
            
        default:
            jsonResponse(false, [], 'Invalid action');
    }
}

// Función para limpiar notificaciones antiguas (llamar desde cron)
function cleanOldNotifications($conn, $days = 30) {
    $query = "DELETE FROM notifications 
              WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
              OR (expires_at IS NOT NULL AND expires_at < NOW())";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $days);
    return $stmt->execute();
}

// Si se llama con parámetro clean, limpiar notificaciones antiguas
if (isset($_GET['clean'])) {
    $days = (int)($_GET['days'] ?? 30);
    if (cleanOldNotifications($conn, $days)) {
        jsonResponse(true, ['message' => 'Old notifications cleaned']);
    } else {
        jsonResponse(false, [], 'Failed to clean notifications');
    }
}
?>