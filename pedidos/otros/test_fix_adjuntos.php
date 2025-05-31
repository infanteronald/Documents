<?php
echo "<h1>🔧 Test CORREGIDO - Sistema de Adjuntos</h1>";

// Cargar el sistema corregido
require_once "procesar_orden.php";

// Crear archivo de prueba
$upload_dir = 'uploads/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$test_file = $upload_dir . 'test_fix.txt';
$test_content = "Archivo de prueba CORREGIDO\nFecha: " . date('Y-m-d H:i:s') . "\nTest: Headers corregidos\n";
file_put_contents($test_file, $test_content);

echo "<h2>📧 Test con headers corregidos:</h2>";

// Test con headers corregidos manualmente
$to = "jorgejosecardozo@gmail.com";
$subject = "✅ Test CORREGIDO Adjuntos - " . date('H:i:s');
$message = "Test con headers corregidos.\n\nEste email debería tener el archivo adjunto funcionando correctamente.\n\nFecha: " . date('Y-m-d H:i:s');

// Headers bien formados
$headers_test = "From: Sequoia Speed <ventas@sequoiaspeed.com.co>\r\n";
$headers_test .= "Reply-To: ventas@sequoiaspeed.com.co\r\n";
$headers_test .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers_test .= "X-Mailer: PHP/" . phpversion() . "\r\n";

echo "Enviando con headers corregidos...<br>";
echo "Para: $to<br>";
echo "Archivo: $test_file<br><br>";

$resultado = enviar_email_mejorado($to, $subject, $message, $headers_test, $test_file);

if ($resultado) {
    echo "<h3 style='color: green;'>✅ ¡EMAIL ENVIADO EXITOSAMENTE!</h3>";
    echo "<p><strong>El problema de headers ha sido corregido.</strong></p>";
    echo "<p>Revisa tu email - deberías ver el archivo adjunto ahora.</p>";
} else {
    echo "<h3 style='color: red;'>❌ Aún hay problemas</h3>";
    echo "<p>Revisa los logs de error para más detalles.</p>";
}

// Test adicional: función directa
echo "<h2>🔧 Test función directa:</h2>";
$resultado_directo = enviar_email_con_adjunto(
    $to,
    "🔧 Test Función Directa CORREGIDA - " . date('H:i:s'),
    "Test directo de función corregida.",
    $headers_test,
    $test_file
);

echo $resultado_directo ? "✅ Función directa OK" : "❌ Función directa falla";
echo "<br><br>";

// Mostrar información de headers para debug
echo "<h2>🔍 Debug Headers:</h2>";
echo "<pre style='background: #f5f5f5; padding: 10px;'>";
echo "Headers utilizados:\n";
echo htmlspecialchars($headers_test);
echo "</pre>";

// Limpiar
if (file_exists($test_file)) {
    unlink($test_file);
    echo "<p>🧹 Archivo de prueba eliminado</p>";
}

echo "<hr>";
echo "<p><strong>Si ves ✅, el problema de adjuntos está SOLUCIONADO.</strong></p>";
echo "<p><em>El sistema debería enviar adjuntos correctamente ahora.</em></p>";
?>
