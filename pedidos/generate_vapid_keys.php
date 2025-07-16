<?php
/**
 * Generate VAPID keys for Web Push Notifications
 * Run this script once to generate your VAPID keys
 */

require_once 'vendor/autoload.php';

use Minishlink\WebPush\VAPID;

// Generate VAPID keys
$vapidKeys = VAPID::createVapidKeys();

echo "VAPID Keys Generated Successfully!\n";
echo "================================\n\n";

echo "Public Key:\n";
echo $vapidKeys['publicKey'] . "\n\n";

echo "Private Key:\n";
echo $vapidKeys['privateKey'] . "\n\n";

echo "IMPORTANT: Store these keys securely and never share the private key!\n";
echo "Add these to your configuration file.\n\n";

// Create a configuration file
$config = [
    'vapid' => [
        'subject' => 'mailto:admin@sequoiaspeed.com',
        'publicKey' => $vapidKeys['publicKey'],
        'privateKey' => $vapidKeys['privateKey']
    ]
];

file_put_contents('push_config.json', json_encode($config, JSON_PRETTY_PRINT));
echo "Configuration saved to push_config.json\n";
?>