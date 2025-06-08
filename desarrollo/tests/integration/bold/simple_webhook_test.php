<?php
echo "Iniciando prueba del webhook mejorado...\n";

// Datos de prueba
$testData = json_encode([
    "reference" => "TEST_" . time(),
    "status" => "APPROVED",
    "transaction_id" => "TXN_" . uniqid(),
    "amount" => 50000
]);

echo "Datos de prueba: " . $testData . "\n";

// Usar file_get_contents para simular POST
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $testData
    ]
]);

echo "Enviando webhook...\n";

// Capturar la salida del webhook
ob_start();
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';
$GLOBALS['HTTP_RAW_POST_DATA'] = $testData;

// Simular input de PHP
$mockInput = fopen('php://memory', 'r+');
fwrite($mockInput, $testData);
rewind($mockInput);

include 'bold_webhook_enhanced.php';

$output = ob_get_clean();

echo "Respuesta del webhook: " . $output . "\n";

// Verificar si se crearon logs
if (file_exists('logs/bold_webhook.log')) {
    echo "✅ Log creado exitosamente\n";
    echo "Contenido del log:\n";
    echo file_get_contents('logs/bold_webhook.log');
} else {
    echo "❌ No se creó el log\n";
}

if (file_exists('logs/bold_errors.log')) {
    echo "\n⚠️ Se encontraron errores:\n";
    echo file_get_contents('logs/bold_errors.log');
}
?>
