<?php
/**
 * Prueba directa del webhook mejorado
 */

echo "🧪 PRUEBA DIRECTA DEL WEBHOOK MEJORADO\n";
echo str_repeat('=', 50) . "\n";

// Simular datos POST
$_POST = [
    'reference' => 'TEST_' . time(),
    'status' => 'APPROVED',
    'transaction_id' => 'TXN_' . uniqid(),
    'amount' => 50000,
    'payment_method' => 'PSE'
];

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';

echo "Datos simulados:\n";
print_r($_POST);
echo "\n";

echo "Ejecutando webhook...\n";

// Incluir el webhook
require_once 'bold_webhook_enhanced.php';

echo "\nPrueba completada.\n";

// Verificar logs
if (is_dir('logs')) {
    echo "\n📋 VERIFICANDO LOGS:\n";
    $files = scandir('logs');
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "Archivo: $file\n";
            $content = file_get_contents("logs/$file");
            if (trim($content)) {
                echo "Contenido:\n" . $content . "\n";
            } else {
                echo "Archivo vacío\n";
            }
            echo str_repeat('-', 30) . "\n";
        }
    }
} else {
    echo "❌ Directorio de logs no existe\n";
}
?>
