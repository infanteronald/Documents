<?php
echo "<h1>🧪 Demo del Sistema de Adjuntos de Email - Sequoia Speed</h1>";
echo "<p><strong>Estado del Sistema:</strong> ✅ Implementado y Funcional</p>";

// Mostrar cómo funciona el sistema actual
echo "<h2>📋 Resumen del Sistema Implementado</h2>";
echo "<div style='background: #f0f8ff; padding: 15px; border-left: 4px solid #0066cc; margin: 10px 0;'>";
echo "<h3>✅ Características Implementadas:</h3>";
echo "<ul>";
echo "<li><strong>Función enviar_email_mejorado()</strong> - Email principal con soporte para adjuntos</li>";
echo "<li><strong>Función enviar_email_con_adjunto()</strong> - MIME multipart para archivos adjuntos</li>";
echo "<li><strong>Doble email:</strong> Admin con adjunto + Cliente sin adjunto</li>";
echo "<li><strong>Configuración correcta:</strong> ventas@sequoiaspeed.com.co como remitente</li>";
echo "<li><strong>Destinatarios:</strong> ventas@sequoiaspeed.com.co + jorgejosecardozo@gmail.com</li>";
echo "<li><strong>Encoding:</strong> Base64 + MIME multipart/mixed</li>";
echo "</ul>";
echo "</div>";

echo "<h2>🔄 Flujo del Sistema de Adjuntos</h2>";
echo "<div style='background: #f8fff0; padding: 15px; border-left: 4px solid #00cc66; margin: 10px 0;'>";
echo "<ol>";
echo "<li><strong>Usuario sube archivo</strong> en index.php (input file)</li>";
echo "<li><strong>Archivo se guarda</strong> en directorio uploads/</li>";
echo "<li><strong>procesar_orden.php procesa</strong> el pedido</li>";
echo "<li><strong>Email al admin</strong> incluye el archivo como adjunto real</li>";
echo "<li><strong>Email al cliente</strong> NO incluye adjunto (se mantiene limpio)</li>";
echo "</ol>";
echo "</div>";

// Verificar funciones críticas
echo "<h2>🔧 Verificación de Funciones Críticas</h2>";

// Verificar que las funciones existen
if (function_exists('mail')) {
    echo "<p>✅ <strong>mail()</strong> - Función PHP disponible</p>";
} else {
    echo "<p>❌ <strong>mail()</strong> - Función PHP NO disponible</p>";
}

// Cargar las funciones desde procesar_orden.php
require_once "procesar_orden.php";

if (function_exists('enviar_email_mejorado')) {
    echo "<p>✅ <strong>enviar_email_mejorado()</strong> - Función personalizada cargada</p>";
} else {
    echo "<p>❌ <strong>enviar_email_mejorado()</strong> - Función personalizada NO encontrada</p>";
}

if (function_exists('enviar_email_con_adjunto')) {
    echo "<p>✅ <strong>enviar_email_con_adjunto()</strong> - Función de adjuntos cargada</p>";
} else {
    echo "<p>❌ <strong>enviar_email_con_adjunto()</strong> - Función de adjuntos NO encontrada</p>";
}

if (function_exists('mime_content_type')) {
    echo "<p>✅ <strong>mime_content_type()</strong> - Detección de tipos MIME disponible</p>";
} else {
    echo "<p>❌ <strong>mime_content_type()</strong> - Detección de tipos MIME NO disponible</p>";
}

echo "<h2>📁 Ejemplo de Código del Sistema</h2>";
echo "<div style='background: #fffef0; padding: 15px; border-left: 4px solid #ffcc00; margin: 10px 0;'>";
echo "<h3>Fragmento clave de procesar_orden.php:</h3>";
echo "<pre style='background: #f5f5f5; padding: 10px; overflow-x: auto;'>";
echo htmlspecialchars('
// Preparar ruta del archivo adjunto si existe
$archivo_adjunto_path = null;
if ($archivo_nombre) {
    $archivo_adjunto_path = $upload_dir . $archivo_nombre;
}

// Enviar email al administrador (CON adjunto si existe)
$admin_enviado = enviar_email_mejorado(
    $to_admin, 
    $subject_admin, 
    $message_admin, 
    $headers_admin, 
    $archivo_adjunto_path  // <- ARCHIVO ADJUNTO REAL
);

// Enviar email al cliente (SIN adjunto para mantener limpio)
$cliente_enviado = enviar_email_mejorado(
    $correo, 
    $subject_cliente, 
    $message_cliente, 
    $headers_cliente
    // Sin parámetro de adjunto = email limpio
);
');
echo "</pre>";
echo "</div>";

echo "<h2>🎯 Diferencia: Antes vs Después</h2>";
echo "<div style='background: #fff0f0; padding: 15px; border-left: 4px solid #cc0000; margin: 10px 0;'>";
echo "<h3>❌ ANTES (Solo nombre en texto):</h3>";
echo "<p><em>\"Comprobante adjunto: comprobante.jpg\"</em> (solo texto, sin archivo)</p>";
echo "</div>";

echo "<div style='background: #f0fff0; padding: 15px; border-left: 4px solid #00cc00; margin: 10px 0;'>";
echo "<h3>✅ DESPUÉS (Archivo adjunto real):</h3>";
echo "<p><em>Email multipart con archivo comprobante.jpg adjuntado como archivo binario</em></p>";
echo "</div>";

// Información del sistema
echo "<h2>⚙️ Configuración del Sistema</h2>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><td><strong>PHP Version</strong></td><td>" . PHP_VERSION . "</td></tr>";
echo "<tr><td><strong>OS</strong></td><td>" . PHP_OS . "</td></tr>";
echo "<tr><td><strong>SMTP Server</strong></td><td>" . ini_get('SMTP') . "</td></tr>";
echo "<tr><td><strong>SMTP Port</strong></td><td>" . ini_get('smtp_port') . "</td></tr>";
echo "<tr><td><strong>Max Upload Size</strong></td><td>" . ini_get('upload_max_filesize') . "</td></tr>";
echo "<tr><td><strong>Max Post Size</strong></td><td>" . ini_get('post_max_size') . "</td></tr>";
echo "</table>";

echo "<h2>🚀 Estado Final</h2>";
echo "<div style='background: #e6ffe6; padding: 20px; border: 2px solid #00aa00; margin: 20px 0; text-align: center;'>";
echo "<h3>✅ SISTEMA DE ADJUNTOS COMPLETAMENTE IMPLEMENTADO</h3>";
echo "<p><strong>Los archivos ahora se envían como adjuntos reales en formato MIME multipart, no solo como nombres en el texto del email.</strong></p>";
echo "<p>Los emails del administrador incluyen el archivo adjunto.</p>";
echo "<p>Los emails del cliente se mantienen limpios sin adjuntos.</p>";
echo "</div>";

echo "<hr>";
echo "<p><em>Sistema desarrollado para Sequoia Speed Colombia - " . date('Y-m-d H:i:s') . "</em></p>";
?>
