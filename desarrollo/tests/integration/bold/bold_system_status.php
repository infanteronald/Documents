<?php
/**
 * Verificador de Estado de Sistema Bold
 * Comprueba el estado de la base de datos, webhook y configuraciones
 */

require_once "conexion.php";

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $status = [
        'timestamp' => date('Y-m-d H:i:s'),
        'database' => [],
        'webhook' => [],
        'bold_config' => [],
        'recent_transactions' => []
    ];

    // 1. Verificar conexión a base de datos
    if ($conn->ping()) {
        $status['database']['connection'] = 'OK';
        
        // Verificar tabla pedidos_detal
        $result = $conn->query("SHOW TABLES LIKE 'pedidos_detal'");
        $status['database']['table_pedidos_detal'] = $result->num_rows > 0 ? 'EXISTS' : 'MISSING';
        
        // Contar pedidos Bold
        $result = $conn->query("SELECT COUNT(*) as count FROM pedidos_detal WHERE metodo_pago IN ('PSE Bold', 'Botón Bancolombia', 'Tarjeta de Crédito o Débito')");
        $row = $result->fetch_assoc();
        $status['database']['bold_orders_count'] = $row['count'];
        
        // Últimas transacciones Bold (últimas 24 horas)
        $result = $conn->query("
            SELECT id, bold_order_id, metodo_pago, estado_pago, monto, fecha 
            FROM pedidos_detal 
            WHERE metodo_pago IN ('PSE Bold', 'Botón Bancolombia', 'Tarjeta de Crédito o Débito') 
            AND fecha >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY fecha DESC 
            LIMIT 10
        ");
        
        while ($row = $result->fetch_assoc()) {
            $status['recent_transactions'][] = [
                'id' => $row['id'],
                'order_id' => $row['bold_order_id'],
                'method' => $row['metodo_pago'],
                'status' => $row['estado_pago'],
                'amount' => $row['monto'],
                'date' => $row['fecha']
            ];
        }
        
    } else {
        $status['database']['connection'] = 'ERROR';
        $status['database']['error'] = 'No se pudo conectar a la base de datos';
    }

    // 2. Verificar archivos del sistema
    $required_files = [
        'bold_webhook_enhanced.php' => 'Webhook Principal',
        'bold_hash.php' => 'Generador Hash',
        'bold_payment.php' => 'Ventana de Pago',
        'bold_notification_system.php' => 'Sistema Notificaciones'
    ];

    foreach ($required_files as $file => $description) {
        $file_path = __DIR__ . '/' . $file;
        $status['webhook'][$file] = [
            'exists' => file_exists($file_path),
            'readable' => file_exists($file_path) && is_readable($file_path),
            'description' => $description
        ];
    }

    // 3. Verificar configuración Bold
    $bold_hash_content = file_get_contents(__DIR__ . '/bold_hash.php');
    $status['bold_config']['api_key_configured'] = strpos($bold_hash_content, '0yRP5iNsgcqoOGTaNLrzKNBLHbAaEOxhJPmLJpMevCg') !== false;
    $status['bold_config']['secret_key_configured'] = strpos($bold_hash_content, '9BhbT6HQPb7QnKmrMheJkQ') !== false;

    // 4. Verificar logs
    $logs_dir = __DIR__ . '/logs';
    $status['webhook']['logs_directory'] = is_dir($logs_dir) && is_writable($logs_dir);
    
    if (is_dir($logs_dir)) {
        $log_files = glob($logs_dir . '/*.log');
        $status['webhook']['log_files'] = count($log_files);
        
        // Leer últimas entradas del log si existe
        $webhook_log = $logs_dir . '/bold_webhook.log';
        if (file_exists($webhook_log)) {
            $log_content = file_get_contents($webhook_log);
            $status['webhook']['last_log_size'] = strlen($log_content);
            $status['webhook']['last_log_lines'] = substr_count($log_content, "\n");
        }
    }

    // 5. Estado general
    $status['overall_status'] = 'OK';
    
    // Verificar condiciones críticas
    if (!$status['database']['connection'] === 'OK') {
        $status['overall_status'] = 'DATABASE_ERROR';
    } elseif (!$status['bold_config']['api_key_configured'] || !$status['bold_config']['secret_key_configured']) {
        $status['overall_status'] = 'CONFIG_ERROR';
    } elseif (!$status['webhook']['bold_webhook_enhanced.php']['exists']) {
        $status['overall_status'] = 'WEBHOOK_ERROR';
    }

    echo json_encode($status, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
?>
