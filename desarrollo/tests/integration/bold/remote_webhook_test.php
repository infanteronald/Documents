<?php
/**
 * Prueba Remota del Webhook Mejorado
 * Ejecutar este archivo en el servidor para probar el webhook
 */

echo "🧪 PRUEBA REMOTA DEL WEBHOOK MEJORADO\n";
echo str_repeat('=', 50) . "\n";

// Datos de prueba realistas
$testWebhookData = [
    "reference" => "REMOTE_TEST_" . time(),
    "status" => "APPROVED",
    "transaction_id" => "TXN_REMOTE_" . uniqid(),
    "amount" => 75000,
    "payment_method" => "PSE",
    "timestamp" => date('Y-m-d H:i:s'),
    "description" => "Prueba remota del webhook - " . date('Y-m-d H:i:s'),
    "customer_email" => "test@example.com",
    "customer_name" => "Usuario Prueba Remoto"
];

echo "📋 Datos de prueba:\n";
echo json_encode($testWebhookData, JSON_PRETTY_PRINT) . "\n\n";

// Simular variables de entorno como lo haría Bold
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';
$_SERVER['HTTP_USER_AGENT'] = 'Bold-Webhook/1.0';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '192.168.1.100'; // IP simulada
$_SERVER['REMOTE_ADDR'] = '192.168.1.100';

// Crear un stream temporal con los datos JSON
$jsonData = json_encode($testWebhookData);
$tempFile = tmpfile();
fwrite($tempFile, $jsonData);
rewind($tempFile);

// Redirigir php://input al archivo temporal
stream_wrapper_unregister("php");
stream_wrapper_register("php", "TestInputWrapper");

class TestInputWrapper {
    public $context;
    private static $data;
    
    public static function setData($data) {
        self::$data = $data;
    }
    
    public function stream_open($path, $mode, $options, &$opened_path) {
        if ($path === 'php://input') {
            return true;
        }
        return false;
    }
    
    public function stream_read($count) {
        if (self::$data) {
            $result = substr(self::$data, 0, $count);
            self::$data = substr(self::$data, $count);
            return $result;
        }
        return '';
    }
    
    public function stream_eof() {
        return empty(self::$data);
    }
    
    public function stream_stat() {
        return [];
    }
}

TestInputWrapper::setData($jsonData);

echo "🚀 Ejecutando webhook mejorado...\n\n";

// Capturar salida
ob_start();

try {
    // Verificar que el archivo existe
    if (!file_exists('bold_webhook_enhanced.php')) {
        echo "❌ Error: bold_webhook_enhanced.php no encontrado\n";
        exit;
    }
    
    // Incluir el webhook
    include 'bold_webhook_enhanced.php';
    
} catch (Exception $e) {
    echo "❌ Error durante la ejecución: " . $e->getMessage() . "\n";
} catch (Error $e) {
    echo "❌ Error fatal: " . $e->getMessage() . "\n";
}

$output = ob_get_clean();

// Restaurar el wrapper original
stream_wrapper_restore("php");

echo "📤 RESPUESTA DEL WEBHOOK:\n";
echo $output ? $output : "(Sin respuesta visible)\n";

echo "\n📊 VERIFICACIÓN POST-EJECUCIÓN:\n";

// Verificar logs generados
$logFiles = [
    'logs/bold_webhook.log' => 'Log principal',
    'logs/bold_errors.log' => 'Log de errores', 
    'logs/webhook_enhanced.log' => 'Log webhook mejorado'
];

foreach ($logFiles as $file => $description) {
    if (file_exists($file)) {
        $size = filesize($file);
        $modified = date('H:i:s', filemtime($file));
        echo "  ✅ {$description}: {$size} bytes (modificado: {$modified})\n";
        
        // Mostrar últimas líneas
        $content = file_get_contents($file);
        $lines = explode("\n", $content);
        $recentLines = array_slice($lines, -3);
        foreach ($recentLines as $line) {
            if (trim($line)) {
                echo "     → " . substr($line, 0, 70) . (strlen($line) > 70 ? '...' : '') . "\n";
            }
        }
    } else {
        echo "  ⚪ {$description}: No generado\n";
    }
}

// Verificar base de datos
echo "\n🗄️  VERIFICACIÓN DE BD:\n";
try {
    require_once 'conexion.php';
    
    // Verificar logs de webhook
    $result = $conn->query("SELECT COUNT(*) as total FROM bold_webhook_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
    if ($result) {
        $count = $result->fetch_assoc()['total'];
        echo "  📝 Logs de webhook (último minuto): {$count}\n";
    }
    
    // Verificar cola de reintentos
    $result = $conn->query("SELECT COUNT(*) as total FROM bold_retry_queue WHERE status = 'pending'");
    if ($result) {
        $count = $result->fetch_assoc()['total'];
        echo "  🔄 Cola de reintentos pendientes: {$count}\n";
    }
    
} catch (Exception $e) {
    echo "  ❌ Error BD: " . $e->getMessage() . "\n";
}

echo "\n✅ PRUEBA REMOTA COMPLETADA\n";
echo str_repeat('=', 50) . "\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
?>
