<?php
// Test específico para debuggear el problema de adjuntos
echo "<h1>🔍 Debug del Sistema de Adjuntos - Sequoia Speed</h1>";

// Verificar si las funciones están disponibles
require_once "procesar_orden.php";

echo "<h2>📋 Verificación del Sistema</h2>";

// 1. Verificar funciones
echo "<h3>1. Funciones PHP:</h3>";
echo "mail(): " . (function_exists('mail') ? "✅" : "❌") . "<br>";
echo "mime_content_type(): " . (function_exists('mime_content_type') ? "✅" : "❌") . "<br>";
echo "enviar_email_mejorado(): " . (function_exists('enviar_email_mejorado') ? "✅" : "❌") . "<br>";
echo "enviar_email_con_adjunto(): " . (function_exists('enviar_email_con_adjunto') ? "✅" : "❌") . "<br>";

// 2. Verificar directorio uploads
echo "<h3>2. Directorio uploads:</h3>";
$upload_dir = 'uploads/';
if (!file_exists($upload_dir)) {
    echo "❌ Directorio uploads no existe. Creándolo...<br>";
    mkdir($upload_dir, 0777, true);
    echo "✅ Directorio uploads creado<br>";
} else {
    echo "✅ Directorio uploads existe<br>";
}

// Verificar permisos
if (is_writable($upload_dir)) {
    echo "✅ Directorio uploads es escribible<br>";
} else {
    echo "❌ Directorio uploads NO es escribible<br>";
}

// 3. Crear archivo de prueba
echo "<h3>3. Creando archivo de prueba:</h3>";
$test_file = $upload_dir . 'test_comprobante.txt';
$test_content = "Archivo de prueba para test de adjuntos\nFecha: " . date('Y-m-d H:i:s') . "\nSistema: Sequoia Speed\n";

if (file_put_contents($test_file, $test_content)) {
    echo "✅ Archivo de prueba creado: $test_file<br>";
    echo "📁 Tamaño: " . filesize($test_file) . " bytes<br>";
    echo "📄 Tipo MIME: " . (function_exists('mime_content_type') ? mime_content_type($test_file) : 'N/A') . "<br>";
} else {
    echo "❌ Error creando archivo de prueba<br>";
}

// 4. Test de envío de email con adjunto
echo "<h3>4. Test de envío de email:</h3>";

if (file_exists($test_file)) {
    $to = "jorgejosecardozo@gmail.com";
    $subject = "🧪 Test Adjunto Sequoia Speed - " . date('H:i:s');
    $message = "Este es un test del sistema de adjuntos.\n\n";
    $message .= "Deberías ver un archivo adjunto llamado: " . basename($test_file) . "\n\n";
    $message .= "Información del test:\n";
    $message .= "- Fecha: " . date('Y-m-d H:i:s') . "\n";
    $message .= "- Archivo: $test_file\n";
    $message .= "- Tamaño: " . filesize($test_file) . " bytes\n";
    $message .= "- Sistema: Sequoia Speed Colombia\n";
    
    echo "📧 Enviando email con adjunto...<br>";
    echo "Para: $to<br>";
    echo "Asunto: $subject<br>";
    echo "Archivo adjunto: $test_file<br><br>";
    
    $resultado = enviar_email_mejorado($to, $subject, $message, "", $test_file);
    
    if ($resultado) {
        echo "✅ <strong>Email enviado exitosamente!</strong><br>";
        echo "👀 Revisa tu bandeja de entrada en $to<br>";
    } else {
        echo "❌ <strong>Error enviando email</strong><br>";
    }
} else {
    echo "❌ No se puede hacer test - archivo de prueba no existe<br>";
}

// 5. Test de la función específica de adjuntos
echo "<h3>5. Test directo de función de adjuntos:</h3>";

if (file_exists($test_file) && function_exists('enviar_email_con_adjunto')) {
    $headers_test = "From: Sequoia Speed <ventas@sequoiaspeed.com.co>\r\n";
    
    echo "📧 Probando función enviar_email_con_adjunto directamente...<br>";
    $resultado_directo = enviar_email_con_adjunto(
        "jorgejosecardozo@gmail.com",
        "🔧 Test Directo Adjunto - " . date('H:i:s'),
        "Test directo de la función enviar_email_con_adjunto.\nArchivo adjunto incluido.",
        $headers_test,
        $test_file
    );
    
    if ($resultado_directo) {
        echo "✅ <strong>Función directa funcionó!</strong><br>";
    } else {
        echo "❌ <strong>Función directa falló</strong><br>";
    }
}

// 6. Verificar configuración PHP
echo "<h3>6. Configuración PHP para emails:</h3>";
echo "SMTP: " . ini_get('SMTP') . "<br>";
echo "smtp_port: " . ini_get('smtp_port') . "<br>";
echo "sendmail_from: " . ini_get('sendmail_from') . "<br>";
echo "sendmail_path: " . ini_get('sendmail_path') . "<br>";
echo "max_execution_time: " . ini_get('max_execution_time') . "<br>";
echo "memory_limit: " . ini_get('memory_limit') . "<br>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";

// 7. Limpiar archivo de prueba
echo "<h3>7. Limpieza:</h3>";
if (file_exists($test_file)) {
    unlink($test_file);
    echo "🧹 Archivo de prueba eliminado<br>";
}

echo "<hr>";
echo "<p><strong>Si ves ❌ en alguna parte, esa puede ser la causa del problema con los adjuntos.</strong></p>";
echo "<p><em>Revisa tu email para verificar si los adjuntos llegaron correctamente.</em></p>";
?>
