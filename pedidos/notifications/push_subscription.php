<?php
/**
 * Push Subscription Management API
 * Handles subscription, unsubscription, and management of push notifications
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once dirname(__DIR__) . '/conexion.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Function to send JSON response
function jsonResponse($success, $data = [], $error = null) {
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'error' => $error,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $user_id = $_GET['user_id'] ?? 'admin';
    
    switch ($action) {
        case 'get_subscriptions':
            try {
                $query = "SELECT * FROM push_subscriptions WHERE user_id = ? AND is_active = 1 ORDER BY created_at DESC";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("s", $user_id);
                $stmt->execute();
                
                // Use bind_result for compatibility
                $stmt->store_result();
                $stmt->bind_result($id, $user_id, $endpoint, $p256dh_key, $auth_token, $user_agent, $created_at, $updated_at, $last_used_at, $is_active);
                
                $subscriptions = [];
                while ($stmt->fetch()) {
                    $subscriptions[] = [
                        'id' => $id,
                        'user_id' => $user_id,
                        'endpoint' => $endpoint,
                        'keys' => [
                            'p256dh' => $p256dh_key,
                            'auth' => $auth_token
                        ],
                        'user_agent' => $user_agent,
                        'created_at' => $created_at,
                        'updated_at' => $updated_at,
                        'last_used_at' => $last_used_at,
                        'is_active' => $is_active
                    ];
                }
                
                $stmt->close();
                jsonResponse(true, ['subscriptions' => $subscriptions]);
            } catch (Exception $e) {
                jsonResponse(false, [], 'Error fetching subscriptions: ' . $e->getMessage());
            }
            break;
            
        case 'get_settings':
            try {
                $query = "SELECT * FROM push_notification_settings WHERE user_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("s", $user_id);
                $stmt->execute();
                
                $stmt->store_result();
                $stmt->bind_result($id, $user_id, $push_enabled, $new_orders, $payment_confirmations, $status_changes, $shipment_updates, $errors_and_warnings, $created_at, $updated_at);
                
                if ($stmt->fetch()) {
                    $settings = [
                        'push_enabled' => (bool)$push_enabled,
                        'new_orders' => (bool)$new_orders,
                        'payment_confirmations' => (bool)$payment_confirmations,
                        'status_changes' => (bool)$status_changes,
                        'shipment_updates' => (bool)$shipment_updates,
                        'errors_and_warnings' => (bool)$errors_and_warnings
                    ];
                } else {
                    // Create default settings
                    $settings = [
                        'push_enabled' => true,
                        'new_orders' => true,
                        'payment_confirmations' => true,
                        'status_changes' => true,
                        'shipment_updates' => true,
                        'errors_and_warnings' => true
                    ];
                }
                
                $stmt->close();
                jsonResponse(true, ['settings' => $settings]);
            } catch (Exception $e) {
                jsonResponse(false, [], 'Error fetching settings: ' . $e->getMessage());
            }
            break;
            
        default:
            jsonResponse(false, [], 'Invalid action');
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        jsonResponse(false, [], 'Invalid JSON input');
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'subscribe':
            try {
                $subscription = $input['subscription'] ?? null;
                $user_agent = $input['userAgent'] ?? '';
                $user_id = $input['user_id'] ?? 'admin';
                
                if (!$subscription || !isset($subscription['endpoint']) || !isset($subscription['keys'])) {
                    jsonResponse(false, [], 'Invalid subscription data');
                }
                
                $endpoint = $subscription['endpoint'];
                $p256dh_key = $subscription['keys']['p256dh'] ?? '';
                $auth_token = $subscription['keys']['auth'] ?? '';
                
                if (empty($endpoint) || empty($p256dh_key) || empty($auth_token)) {
                    jsonResponse(false, [], 'Missing required subscription fields');
                }
                
                // Check if subscription already exists
                $check_query = "SELECT id FROM push_subscriptions WHERE user_id = ? AND endpoint = ? LIMIT 1";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bind_param("ss", $user_id, $endpoint);
                $check_stmt->execute();
                $check_stmt->store_result();
                
                if ($check_stmt->num_rows > 0) {
                    // Update existing subscription
                    $check_stmt->close();
                    
                    $update_query = "UPDATE push_subscriptions SET p256dh_key = ?, auth_token = ?, user_agent = ?, updated_at = CURRENT_TIMESTAMP, is_active = 1 WHERE user_id = ? AND endpoint = ?";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bind_param("sssss", $p256dh_key, $auth_token, $user_agent, $user_id, $endpoint);
                    
                    if ($update_stmt->execute()) {
                        $update_stmt->close();
                        jsonResponse(true, ['message' => 'Subscription updated successfully']);
                    } else {
                        $update_stmt->close();
                        jsonResponse(false, [], 'Failed to update subscription');
                    }
                } else {
                    // Create new subscription
                    $check_stmt->close();
                    
                    $insert_query = "INSERT INTO push_subscriptions (user_id, endpoint, p256dh_key, auth_token, user_agent) VALUES (?, ?, ?, ?, ?)";
                    $insert_stmt = $conn->prepare($insert_query);
                    $insert_stmt->bind_param("sssss", $user_id, $endpoint, $p256dh_key, $auth_token, $user_agent);
                    
                    if ($insert_stmt->execute()) {
                        $subscription_id = $insert_stmt->insert_id;
                        $insert_stmt->close();
                        jsonResponse(true, ['message' => 'Subscription created successfully', 'subscription_id' => $subscription_id]);
                    } else {
                        $insert_stmt->close();
                        jsonResponse(false, [], 'Failed to create subscription');
                    }
                }
                
            } catch (Exception $e) {
                jsonResponse(false, [], 'Error processing subscription: ' . $e->getMessage());
            }
            break;
            
        case 'unsubscribe':
            try {
                $endpoint = $input['endpoint'] ?? '';
                $user_id = $input['user_id'] ?? 'admin';
                
                if (empty($endpoint)) {
                    jsonResponse(false, [], 'Missing endpoint');
                }
                
                $query = "UPDATE push_subscriptions SET is_active = 0 WHERE user_id = ? AND endpoint = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ss", $user_id, $endpoint);
                
                if ($stmt->execute()) {
                    $stmt->close();
                    jsonResponse(true, ['message' => 'Unsubscribed successfully']);
                } else {
                    $stmt->close();
                    jsonResponse(false, [], 'Failed to unsubscribe');
                }
                
            } catch (Exception $e) {
                jsonResponse(false, [], 'Error processing unsubscription: ' . $e->getMessage());
            }
            break;
            
        case 'update_settings':
            try {
                $user_id = $input['user_id'] ?? 'admin';
                $settings = $input['settings'] ?? [];
                
                // Validate settings
                $valid_settings = [
                    'push_enabled' => (bool)($settings['push_enabled'] ?? true),
                    'new_orders' => (bool)($settings['new_orders'] ?? true),
                    'payment_confirmations' => (bool)($settings['payment_confirmations'] ?? true),
                    'status_changes' => (bool)($settings['status_changes'] ?? true),
                    'shipment_updates' => (bool)($settings['shipment_updates'] ?? true),
                    'errors_and_warnings' => (bool)($settings['errors_and_warnings'] ?? true)
                ];
                
                // Check if settings exist
                $check_query = "SELECT id FROM push_notification_settings WHERE user_id = ? LIMIT 1";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bind_param("s", $user_id);
                $check_stmt->execute();
                $check_stmt->store_result();
                
                if ($check_stmt->num_rows > 0) {
                    // Update existing settings
                    $check_stmt->close();
                    
                    $update_query = "UPDATE push_notification_settings SET push_enabled = ?, new_orders = ?, payment_confirmations = ?, status_changes = ?, shipment_updates = ?, errors_and_warnings = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bind_param("iiiiiiis", 
                        $valid_settings['push_enabled'],
                        $valid_settings['new_orders'],
                        $valid_settings['payment_confirmations'],
                        $valid_settings['status_changes'],
                        $valid_settings['shipment_updates'],
                        $valid_settings['errors_and_warnings'],
                        $user_id
                    );
                    
                    if ($update_stmt->execute()) {
                        $update_stmt->close();
                        jsonResponse(true, ['message' => 'Settings updated successfully']);
                    } else {
                        $update_stmt->close();
                        jsonResponse(false, [], 'Failed to update settings');
                    }
                } else {
                    // Create new settings
                    $check_stmt->close();
                    
                    $insert_query = "INSERT INTO push_notification_settings (user_id, push_enabled, new_orders, payment_confirmations, status_changes, shipment_updates, errors_and_warnings) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $insert_stmt = $conn->prepare($insert_query);
                    $insert_stmt->bind_param("siiiiii", 
                        $user_id,
                        $valid_settings['push_enabled'],
                        $valid_settings['new_orders'],
                        $valid_settings['payment_confirmations'],
                        $valid_settings['status_changes'],
                        $valid_settings['shipment_updates'],
                        $valid_settings['errors_and_warnings']
                    );
                    
                    if ($insert_stmt->execute()) {
                        $insert_stmt->close();
                        jsonResponse(true, ['message' => 'Settings created successfully']);
                    } else {
                        $insert_stmt->close();
                        jsonResponse(false, [], 'Failed to create settings');
                    }
                }
                
            } catch (Exception $e) {
                jsonResponse(false, [], 'Error updating settings: ' . $e->getMessage());
            }
            break;
            
        case 'cleanup_inactive':
            try {
                // Remove subscriptions inactive for more than 30 days
                $query = "DELETE FROM push_subscriptions WHERE is_active = 0 AND updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
                $result = $conn->query($query);
                
                if ($result) {
                    $deleted = $conn->affected_rows;
                    jsonResponse(true, ['message' => "Cleaned up $deleted inactive subscriptions"]);
                } else {
                    jsonResponse(false, [], 'Failed to cleanup inactive subscriptions');
                }
                
            } catch (Exception $e) {
                jsonResponse(false, [], 'Error cleaning up subscriptions: ' . $e->getMessage());
            }
            break;
            
        default:
            jsonResponse(false, [], 'Invalid action');
    }
}

// Close connection
$conn->close();
?>