<?php
/**
 * Script de prueba para el webhook mejorado
 * Simula un webhook de Bold PSE para verificar funcionamiento
 */

// Datos de prueba simulando un webhook real de Bold
$testWebhookData = [
    "reference" => "TEST_" . time(),
    "status" => "APPROVED",
    "transaction_id" => "TXN_" . uniqid(),
    "amount" => 50000,
    "payment_method" => "PSE",
    "timestamp" => date('Y-m-d H:i:s'),
    "description" => "Prueba del webhook mejorado - " . date('Y-m-d H:i:s')
];

echo "🧪 PRUEBA DEL WEBHOOK MEJORADO\n";
echo "=" . str_repeat('=', 50) . "\n";
echo "Datos de prueba:\n";
echo json_encode($testWebhookData, JSON_PRETTY_PRINT) . "\n\n";

// Simular el webhook
echo "📡 Enviando webhook de prueba...\n";

$webhookUrl = "http://localhost" . dirname($_SERVER['REQUEST_URI']) . "/bold_webhook_enhanced.php";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $webhookUrl);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testWebhookData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'User-Agent: Bold-Webhook-Test/1.0'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "📊 RESULTADOS:\n";
echo "- Código HTTP: " . $httpCode . "\n";
echo "- Respuesta: " . ($response ? $response : 'Sin respuesta') . "\n";

if ($error) {
    echo "- Error cURL: " . $error . "\n";
}

// Verificar logs
echo "\n📋 VERIFICANDO LOGS:\n";

$logFile = __DIR__ . '/logs/bold_webhook.log';
if (file_exists($logFile)) {
    echo "✅ Log principal encontrado\n";
    $logContent = file_get_contents($logFile);
    $lines = explode("\n", $logContent);
    $recentLines = array_slice($lines, -10);
    echo "Últimas líneas del log:\n";
    foreach ($recentLines as $line) {
        if (trim($line)) {
            echo "  " . $line . "\n";
        }
    }
} else {
    echo "❌ No se encontró el archivo de log principal\n";
}

$errorLogFile = __DIR__ . '/logs/bold_errors.log';
if (file_exists($errorLogFile)) {
    echo "\n⚠️  Log de errores encontrado\n";
    $errorContent = file_get_contents($errorLogFile);
    if (trim($errorContent)) {
        echo "Últimos errores:\n";
        $errorLines = explode("\n", $errorContent);
        $recentErrors = array_slice($errorLines, -5);
        foreach ($recentErrors as $line) {
            if (trim($line)) {
                echo "  " . $line . "\n";
            }
        }
    } else {
        echo "✅ No hay errores recientes\n";
    }
}

echo "\n🎯 PRUEBA COMPLETADA\n";
echo "=" . str_repeat('=', 50) . "\n";
?>
