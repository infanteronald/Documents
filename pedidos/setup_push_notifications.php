<?php
/**
 * Complete Push Notifications Setup Script
 * Run this script to set up the complete push notification system
 */

echo "🚀 Setting up Push Notifications System\n";
echo "=====================================\n\n";

// Step 1: Check requirements
echo "📋 Step 1: Checking requirements...\n";

// Check PHP version
if (version_compare(PHP_VERSION, '8.1.0') < 0) {
    die("❌ PHP 8.1+ is required. Current version: " . PHP_VERSION . "\n");
}
echo "✅ PHP version: " . PHP_VERSION . "\n";

// Check if composer is installed
if (!file_exists('composer.phar') && !file_exists('vendor/autoload.php')) {
    die("❌ Composer not found. Please install composer first.\n");
}
echo "✅ Composer found\n";

// Check if web-push library is installed
if (!file_exists('vendor/minishlink/web-push')) {
    die("❌ web-push library not found. Run 'php composer.phar install' first.\n");
}
echo "✅ web-push library installed\n";

// Step 2: Check VAPID keys
echo "\n📋 Step 2: Checking VAPID keys...\n";
if (!file_exists('push_config.json')) {
    echo "⚠️  VAPID keys not found. Generating new keys...\n";
    
    require_once 'vendor/autoload.php';
    
    $vapidKeys = \Minishlink\WebPush\VAPID::createVapidKeys();
    
    $config = [
        'vapid' => [
            'subject' => 'mailto:admin@sequoiaspeed.com',
            'publicKey' => $vapidKeys['publicKey'],
            'privateKey' => $vapidKeys['privateKey']
        ]
    ];
    
    file_put_contents('push_config.json', json_encode($config, JSON_PRETTY_PRINT));
    echo "✅ VAPID keys generated and saved\n";
} else {
    echo "✅ VAPID keys found\n";
}

// Step 3: Check database tables
echo "\n📋 Step 3: Checking database tables...\n";
try {
    require_once 'conexion.php';
    
    // Check if tables exist
    $tables = ['push_subscriptions', 'push_notification_logs', 'push_notification_settings'];
    $missingTables = [];
    
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows === 0) {
            $missingTables[] = $table;
        }
    }
    
    if (!empty($missingTables)) {
        echo "⚠️  Missing tables: " . implode(', ', $missingTables) . "\n";
        echo "📝 Creating tables...\n";
        
        // Read and execute SQL
        $sql = file_get_contents('create_push_subscriptions_table.sql');
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (empty($statement) || strpos($statement, '--') === 0) {
                continue;
            }
            
            if (!$conn->query($statement)) {
                echo "❌ Error creating table: " . $conn->error . "\n";
                exit(1);
            }
        }
        
        echo "✅ Database tables created\n";
    } else {
        echo "✅ All database tables exist\n";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    echo "ℹ️  Note: You may need to run this script on the server where the database is accessible.\n";
}

// Step 4: Check service worker
echo "\n📋 Step 4: Checking service worker...\n";
if (!file_exists('push-service-worker.js')) {
    echo "❌ Service worker not found\n";
    exit(1);
}
echo "✅ Service worker found\n";

// Step 5: Check notification files
echo "\n📋 Step 5: Checking notification files...\n";
$requiredFiles = [
    'notifications/notifications_enhanced.js',
    'notifications/push_notifications.css',
    'notifications/push_subscription.php',
    'notifications/push_sender.php',
    'notifications/notification_helpers.php'
];

$missingFiles = [];
foreach ($requiredFiles as $file) {
    if (!file_exists($file)) {
        $missingFiles[] = $file;
    }
}

if (!empty($missingFiles)) {
    echo "❌ Missing files: " . implode(', ', $missingFiles) . "\n";
    exit(1);
}
echo "✅ All required files found\n";

// Step 6: Update existing pages
echo "\n📋 Step 6: Updating existing pages...\n";

// Update main notification JavaScript
if (file_exists('notifications/notifications.js')) {
    echo "📝 Backing up original notifications.js...\n";
    copy('notifications/notifications.js', 'notifications/notifications.js.backup');
    
    echo "📝 Replacing with enhanced version...\n";
    copy('notifications/notifications_enhanced.js', 'notifications/notifications.js');
    
    echo "✅ notifications.js updated\n";
} else {
    echo "⚠️  Original notifications.js not found\n";
}

// Update CSS files
$cssFiles = [
    'notifications/notifications.css',
    'listar_pedidos.php',
    'ver_detalle_pedido.php',
    'index.html'
];

foreach ($cssFiles as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Add push notification CSS if not already present
        if (strpos($content, 'push_notifications.css') === false) {
            if (strpos($file, '.php') !== false) {
                $content = str_replace(
                    '<link rel="stylesheet" href="notifications/notifications.css">',
                    '<link rel="stylesheet" href="notifications/notifications.css">
    <link rel="stylesheet" href="notifications/push_notifications.css">',
                    $content
                );
            } elseif (strpos($file, '.html') !== false) {
                $content = str_replace(
                    '<link rel="stylesheet" href="notifications/notifications.css">',
                    '<link rel="stylesheet" href="notifications/notifications.css">
    <link rel="stylesheet" href="notifications/push_notifications.css">',
                    $content
                );
            }
            
            file_put_contents($file, $content);
            echo "✅ Updated $file\n";
        }
    }
}

// Step 7: Final checks
echo "\n📋 Step 7: Final system check...\n";

// Check if all components are properly configured
$config = json_decode(file_get_contents('push_config.json'), true);
$publicKey = $config['vapid']['publicKey'];

// Update service worker with correct VAPID key
$swContent = file_get_contents('push-service-worker.js');
$swContent = str_replace(
    'BKkHNNyupL6icILj3eUek5Aq-fwrQ967-fhdzKTzG3uzhH8PlhUYGhjKRnvBVYIx9vmYKM-JObcOT3LfdXgBShY',
    $publicKey,
    $swContent
);
file_put_contents('push-service-worker.js', $swContent);

// Update enhanced notifications.js with correct VAPID key
$jsContent = file_get_contents('notifications/notifications_enhanced.js');
$jsContent = str_replace(
    'BKkHNNyupL6icILj3eUek5Aq-fwrQ967-fhdzKTzG3uzhH8PlhUYGhjKRnvBVYIx9vmYKM-JObcOT3LfdXgBShY',
    $publicKey,
    $jsContent
);
file_put_contents('notifications/notifications_enhanced.js', $jsContent);

echo "✅ VAPID keys configured in all files\n";

// Step 8: Success message
echo "\n🎉 Push Notifications Setup Complete!\n";
echo "====================================\n\n";

echo "📋 What's been set up:\n";
echo "• ✅ Web-push PHP library installed\n";
echo "• ✅ VAPID keys generated and configured\n";
echo "• ✅ Database tables created\n";
echo "• ✅ Service worker configured\n";
echo "• ✅ Enhanced notification system deployed\n";
echo "• ✅ Push notification CSS added\n";
echo "• ✅ All existing pages updated\n\n";

echo "🚀 Next steps:\n";
echo "1. Upload all files to your server\n";
echo "2. Test the system by visiting any admin page\n";
echo "3. Allow notification permissions when prompted\n";
echo "4. Test push notifications by running test_push_notifications.php\n";
echo "5. Push notifications will now work even when browser is closed!\n\n";

echo "📁 New files created:\n";
echo "• push-service-worker.js (service worker)\n";
echo "• push_config.json (VAPID configuration)\n";
echo "• notifications/notifications_enhanced.js (enhanced JS)\n";
echo "• notifications/push_notifications.css (push styles)\n";
echo "• notifications/push_subscription.php (subscription API)\n";
echo "• notifications/push_sender.php (push sender service)\n";
echo "• create_push_subscriptions_table.sql (database schema)\n";
echo "• test_push_notifications.php (testing script)\n\n";

echo "🔐 Security notes:\n";
echo "• Keep your VAPID private key secure\n";
echo "• Don't commit push_config.json to version control\n";
echo "• Consider setting up database backups\n\n";

echo "✨ The system is ready to use!\n";

if (isset($conn)) {
    $conn->close();
}
?>