<?php
/**
 * Push Notification Sender Service
 * Sends push notifications to subscribed users
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config_secure.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class PushNotificationSender {
    private $conn;
    private $webPush;
    private $vapidKeys;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->loadVapidKeys();
        $this->initializeWebPush();
    }
    
    /**
     * Load VAPID keys from configuration
     */
    private function loadVapidKeys() {
        $configFile = dirname(__DIR__) . '/push_config.json';
        
        if (!file_exists($configFile)) {
            throw new Exception('Push configuration file not found');
        }
        
        $config = json_decode(file_get_contents($configFile), true);
        
        if (!$config || !isset($config['vapid'])) {
            throw new Exception('Invalid push configuration');
        }
        
        $this->vapidKeys = $config['vapid'];
    }
    
    /**
     * Initialize WebPush client
     */
    private function initializeWebPush() {
        $auth = [
            'VAPID' => [
                'subject' => $this->vapidKeys['subject'],
                'publicKey' => $this->vapidKeys['publicKey'],
                'privateKey' => $this->vapidKeys['privateKey']
            ]
        ];
        
        $this->webPush = new WebPush($auth);
        
        // Set default options
        $this->webPush->setDefaultOptions([
            'TTL' => 3600, // 1 hour
            'urgency' => 'normal',
            'batchSize' => 200
        ]);
    }
    
    /**
     * Send push notification to all subscribed users
     */
    public function sendPushNotification($title, $message, $data = [], $user_id = 'admin', $notification_type = 'info') {
        try {
            // Check if user has push notifications enabled
            if (!$this->isPushEnabledForUser($user_id, $notification_type)) {
                return ['success' => true, 'message' => 'Push notifications disabled for this user/type'];
            }
            
            // Get active subscriptions for user
            $subscriptions = $this->getActiveSubscriptions($user_id);
            
            if (empty($subscriptions)) {
                return ['success' => true, 'message' => 'No active subscriptions found'];
            }
            
            // Prepare notification payload
            $payload = [
                'title' => $title,
                'body' => $message,
                'icon' => '/logo.png',
                'badge' => '/logo.png',
                'vibrate' => [100, 50, 100],
                'data' => array_merge([
                    'timestamp' => time(),
                    'type' => $notification_type,
                    'url' => '/'
                ], $data),
                'actions' => $this->getNotificationActions($notification_type, $data),
                'requireInteraction' => in_array($notification_type, ['error', 'warning']),
                'tag' => 'sequoia-' . $notification_type
            ];
            
            $results = [];
            $successCount = 0;
            $failureCount = 0;
            
            // Send to each subscription
            foreach ($subscriptions as $subscription) {
                try {
                    $pushSubscription = Subscription::create([
                        'endpoint' => $subscription['endpoint'],
                        'keys' => [
                            'p256dh' => $subscription['p256dh_key'],
                            'auth' => $subscription['auth_token']
                        ]
                    ]);
                    
                    $this->webPush->queueNotification(
                        $pushSubscription,
                        json_encode($payload)
                    );
                    
                } catch (Exception $e) {
                    $failureCount++;
                    $this->logPushError($subscription['id'], $title, $message, $e->getMessage());
                }
            }
            
            // Send all queued notifications
            $reports = $this->webPush->flush();
            
            // Process results
            foreach ($reports as $report) {
                if ($report->isSuccess()) {
                    $successCount++;
                } else {
                    $failureCount++;
                    $this->handlePushFailure($report);
                }
            }
            
            // Log successful notifications
            $this->logPushNotifications($subscriptions, $title, $message, $payload, $successCount);
            
            return [
                'success' => true,
                'sent' => $successCount,
                'failed' => $failureCount,
                'total' => count($subscriptions)
            ];
            
        } catch (Exception $e) {
            error_log('Push notification error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check if push notifications are enabled for user and type
     */
    private function isPushEnabledForUser($user_id, $notification_type) {
        $query = "SELECT push_enabled, new_orders, payment_confirmations, status_changes, shipment_updates, errors_and_warnings 
                  FROM push_notification_settings 
                  WHERE user_id = ? LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        
        $stmt->store_result();
        $stmt->bind_result($push_enabled, $new_orders, $payment_confirmations, $status_changes, $shipment_updates, $errors_and_warnings);
        
        if (!$stmt->fetch()) {
            $stmt->close();
            return true; // Default to enabled if no settings found
        }
        
        $stmt->close();
        
        if (!$push_enabled) {
            return false;
        }
        
        // Check specific notification type settings
        $typeMap = [
            'new_order' => $new_orders,
            'payment' => $payment_confirmations,
            'status_change' => $status_changes,
            'shipment' => $shipment_updates,
            'error' => $errors_and_warnings,
            'warning' => $errors_and_warnings
        ];
        
        return $typeMap[$notification_type] ?? true;
    }
    
    /**
     * Get active subscriptions for user
     */
    private function getActiveSubscriptions($user_id) {
        $query = "SELECT id, endpoint, p256dh_key, auth_token 
                  FROM push_subscriptions 
                  WHERE user_id = ? AND is_active = 1 
                  ORDER BY created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        
        $stmt->store_result();
        $stmt->bind_result($id, $endpoint, $p256dh_key, $auth_token);
        
        $subscriptions = [];
        while ($stmt->fetch()) {
            $subscriptions[] = [
                'id' => $id,
                'endpoint' => $endpoint,
                'p256dh_key' => $p256dh_key,
                'auth_token' => $auth_token
            ];
        }
        
        $stmt->close();
        return $subscriptions;
    }
    
    /**
     * Get notification actions based on type
     */
    private function getNotificationActions($type, $data) {
        $actions = [];
        
        switch ($type) {
            case 'new_order':
                if (isset($data['pedido_id'])) {
                    $actions[] = [
                        'action' => 'view',
                        'title' => 'Ver Pedido',
                        'icon' => '/logo.png'
                    ];
                }
                break;
                
            case 'payment':
                if (isset($data['pedido_id'])) {
                    $actions[] = [
                        'action' => 'view',
                        'title' => 'Ver Detalles',
                        'icon' => '/logo.png'
                    ];
                }
                break;
                
            case 'status_change':
                if (isset($data['pedido_id'])) {
                    $actions[] = [
                        'action' => 'view',
                        'title' => 'Ver Estado',
                        'icon' => '/logo.png'
                    ];
                }
                break;
                
            case 'shipment':
                if (isset($data['pedido_id'])) {
                    $actions[] = [
                        'action' => 'view',
                        'title' => 'Ver Guía',
                        'icon' => '/logo.png'
                    ];
                }
                break;
        }
        
        // Add dismiss action for all notifications
        $actions[] = [
            'action' => 'dismiss',
            'title' => 'Cerrar',
            'icon' => '/logo.png'
        ];
        
        return $actions;
    }
    
    /**
     * Handle push notification failures
     */
    private function handlePushFailure($report) {
        $endpoint = $report->getEndpoint();
        $statusCode = $report->getResponse()->getStatusCode();
        $reason = $report->getResponse()->getReasonPhrase();
        
        error_log("Push notification failed for endpoint: $endpoint, Status: $statusCode, Reason: $reason");
        
        // Handle specific failure cases
        if (in_array($statusCode, [400, 404, 410, 413])) {
            // Invalid subscription, remove it
            $this->deactivateSubscription($endpoint);
        }
    }
    
    /**
     * Deactivate invalid subscription
     */
    private function deactivateSubscription($endpoint) {
        $query = "UPDATE push_subscriptions SET is_active = 0 WHERE endpoint = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $endpoint);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Log push notification errors
     */
    private function logPushError($subscription_id, $title, $message, $error) {
        $query = "INSERT INTO push_notification_logs (subscription_id, title, message, status, error_message) 
                  VALUES (?, ?, ?, 'failed', ?)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("isss", $subscription_id, $title, $message, $error);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Log successful push notifications
     */
    private function logPushNotifications($subscriptions, $title, $message, $payload, $successCount) {
        if ($successCount > 0) {
            $query = "INSERT INTO push_notification_logs (subscription_id, title, message, payload_json, status, sent_at) 
                      VALUES (?, ?, ?, ?, 'sent', NOW())";
            
            $stmt = $this->conn->prepare($query);
            $payloadJson = json_encode($payload);
            
            foreach ($subscriptions as $subscription) {
                $stmt->bind_param("isss", $subscription['id'], $title, $message, $payloadJson);
                $stmt->execute();
            }
            
            $stmt->close();
        }
    }
}

// Global function for easy use
function sendPushNotification($title, $message, $data = [], $user_id = 'admin', $notification_type = 'info') {
    global $conn;
    
    try {
        $sender = new PushNotificationSender($conn);
        return $sender->sendPushNotification($title, $message, $data, $user_id, $notification_type);
    } catch (Exception $e) {
        error_log('Push notification service error: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

?>