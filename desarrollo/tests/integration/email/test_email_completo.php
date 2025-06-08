<?php
// Test completo del sistema de emails con adjuntos
require_once "procesar_orden.php";

echo "<h2>🧪 Test del Sistema de Emails con Adjuntos</h2>";

// Crear un archivo de prueba
$test_file = "test_comprobante.txt";
$test_content = "Este es un archivo de prueba para testing de adjuntos de email\n";
$test_content .= "Fecha: " . date('Y-m-d H:i:s') . "\n";
$test_content .= "Sistema: Sequoia Speed\n";
$test_content .= "Test: Adjunto de email\n";

file_put_contents($test_file, $test_content);

echo "<p>✅ Archivo de prueba creado: $test_file</p>";

// Test 1: Email sin adjunto
echo "<h3>Test 1: Email sin adjunto</h3>";
$resultado1 = enviar_email_mejorado(
    "jorgejosecardozo@gmail.com",
    "Test Sequoia Speed - Sin adjunto",
    "Este es un email de prueba sin archivo adjunto.\n\nFecha: " . date('Y-m-d H:i:s')
);
echo $resultado1 ? "✅ Email sin adjunto enviado" : "❌ Error enviando email sin adjunto";
echo "<br>";

// Test 2: Email con adjunto
echo "<h3>Test 2: Email con adjunto</h3>";
$resultado2 = enviar_email_mejorado(
    "jorgejosecardozo@gmail.com",
    "Test Sequoia Speed - Con adjunto",
    "Este es un email de prueba CON archivo adjunto.\n\nDeberías ver un archivo adjunto llamado '$test_file'.\n\nFecha: " . date('Y-m-d H:i:s'),
    "", // headers por defecto
    $test_file // archivo adjunto
);
echo $resultado2 ? "✅ Email con adjunto enviado" : "❌ Error enviando email con adjunto";
echo "<br>";

// Test 3: Verificar que el archivo existe
echo "<h3>Test 3: Verificación del archivo</h3>";
if (file_exists($test_file)) {
    echo "✅ Archivo existe: $test_file<br>";
    echo "📁 Tamaño: " . filesize($test_file) . " bytes<br>";
    echo "📄 Tipo MIME: " . mime_content_type($test_file) . "<br>";
    echo "📝 Contenido:<br><pre>" . htmlspecialchars(file_get_contents($test_file)) . "</pre>";
} else {
    echo "❌ Archivo no encontrado: $test_file<br>";
}

// Test 4: Simulación de email completo del sistema
echo "<h3>Test 4: Simulación de email del sistema</h3>";

$mensaje_admin = "NUEVO PEDIDO RECIBIDO\n\n";
$mensaje_admin .= "Orden: #TEST123\n";
$mensaje_admin .= "Cliente: Jorge Test\n";
$mensaje_admin .= "Email: jorgejosecardozo@gmail.com\n";
$mensaje_admin .= "Teléfono: 3213260357\n";
$mensaje_admin .= "Dirección: Dirección de prueba\n";
$mensaje_admin .= "Método de pago: Efectivo\n";
$mensaje_admin .= "Monto: $50,000\n\n";
$mensaje_admin .= "PEDIDO:\n";
$mensaje_admin .= "- Producto de prueba x1\n\n";
$mensaje_admin .= "Comprobante adjunto: $test_file\n";

$headers_admin = "From: Sequoia Speed <ventas@sequoiaspeed.com.co>\r\n";
$headers_admin .= "Reply-To: jorgejosecardozo@gmail.com\r\n";
$headers_admin .= "Cc: jorgejosecardozo@gmail.com\r\n";
$headers_admin .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers_admin .= "X-Mailer: PHP/" . phpversion();

$resultado3 = enviar_email_mejorado(
    "ventas@sequoiaspeed.com.co",
    "🛍️ Nuevo Pedido #TEST123 - Con Comprobante",
    $mensaje_admin,
    $headers_admin,
    $test_file
);

echo $resultado3 ? "✅ Email de administrador simulado enviado" : "❌ Error enviando email de administrador";
echo "<br><br>";

// Información del sistema
echo "<h3>📋 Información del Sistema</h3>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "OS: " . PHP_OS . "<br>";
echo "Mail function available: " . (function_exists('mail') ? "✅ Sí" : "❌ No") . "<br>";
echo "SMTP Server: " . ini_get('SMTP') . "<br>";
echo "SMTP Port: " . ini_get('smtp_port') . "<br>";
echo "Sendmail Path: " . ini_get('sendmail_path') . "<br>";

// Cleanup
echo "<h3>🧹 Limpieza</h3>";
if (file_exists($test_file)) {
    unlink($test_file);
    echo "✅ Archivo de prueba eliminado<br>";
}

echo "<p><strong>Test completado. Revisa tu email para verificar que los adjuntos se enviaron correctamente.</strong></p>";
?>
