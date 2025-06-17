<?php
// Servir archivos JavaScript con Content-Type correcto
$file = $_GET['file'] ?? '';

// Validar que el archivo existe y es seguro
$allowed_files = [
    'bold_realtime_payment_ui.js',
    'bold_payment_enhanced_handler_v3.js'
];

if (!in_array($file, $allowed_files)) {
    http_response_code(404);
    exit('File not found');
}

$filepath = __DIR__ . '/' . $file;

if (!file_exists($filepath)) {
    http_response_code(404);
    exit('File not found');
}

// Servir con Content-Type correcto
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: public, max-age=3600');

readfile($filepath);
?>
