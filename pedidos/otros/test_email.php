<?php
// Archivo de prueba para verificar el envío de emails
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test de Envío de Email - Sequoia Speed</h2>";

// Verificar función mail
if (!function_exists('mail')) {
    echo "<p style='color: red;'>❌ La función mail() no está disponible</p>";
    exit;
}

echo "<p style='color: green;'>✅ La función mail() está disponible</p>";

// Incluir la función mejorada de envío
include_once 'smtp_config.php';

// Mostrar información de configuración
echo "<h3>Configuración Actual del Servidor:</h3>";
$config = verificar_configuracion_email();
foreach ($config as $key => $value) {
    $color = ($value === 'No configurado' || $value === 'No disponible') ? 'red' : 'green';
    echo "<p><strong>" . ucfirst(str_replace('_', ' ', $key)) . ":</strong> <span style='color: $color'>$value</span></p>";
}

// Formulario para envío de prueba
if ($_POST) {
    $email_prueba = $_POST['email'] ?? '';
    
    if (filter_var($email_prueba, FILTER_VALIDATE_EMAIL)) {
        // Datos de prueba
        $to = $email_prueba;
        $subject = "Test Email - Sequoia Speed";
        $message = "Este es un email de prueba del sistema Sequoia Speed.\n\n";
        $message .= "Fecha: " . date('Y-m-d H:i:s') . "\n";
        $message .= "Servidor: " . $_SERVER['SERVER_NAME'] . "\n";
        $message .= "IP: " . $_SERVER['SERVER_ADDR'] . "\n\n";
        $message .= "Si recibes este email, la configuración está funcionando correctamente.\n\n";
        $message .= "---\nSequoia Speed System";        // Headers mejorados
        $headers = "From: Sequoia Speed <ventas@sequoiaspeed.com.co>\r\n";
        $headers .= "Reply-To: ventas@sequoiaspeed.com.co\r\n";
        $headers .= "Cc: jorgejosecardozo@gmail.com\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

        echo "<div style='border: 1px solid #ccc; padding: 15px; margin: 10px 0; background: #f9f9f9;'>";
        echo "<h3>Enviando email de prueba...</h3>";
        echo "<p><strong>Para:</strong> " . htmlspecialchars($to) . "</p>";
        echo "<p><strong>Asunto:</strong> " . htmlspecialchars($subject) . "</p>";

        // Intentar enviar el email
        $result = mail($to, $subject, $message, $headers);

        if ($result) {
            echo "<p style='color: green; font-size: 18px;'>✅ <strong>Email enviado exitosamente</strong></p>";
            echo "<p>Revisa la bandeja de entrada y carpeta de spam del destinatario.</p>";
        } else {
            echo "<p style='color: red; font-size: 18px;'>❌ <strong>Error al enviar el email</strong></p>";
            
            echo "<h4>Posibles soluciones:</h4>";
            echo "<ul>";
            echo "<li><strong>Windows (XAMPP/WAMP):</strong> Configurar SMTP en php.ini o usar Mercury Mail</li>";
            echo "<li><strong>Linux:</strong> Instalar sendmail o postfix</li>";
            echo "<li><strong>Hosting compartido:</strong> Contactar al proveedor para configuración SMTP</li>";
            echo "<li><strong>Alternativa:</strong> Usar servicios como SendGrid, Mailgun o Gmail SMTP</li>";
            echo "</ul>";
        }
        echo "</div>";
    } else {
        echo "<p style='color: red;'>❌ Email inválido</p>";
    }
}
?>

<form method="POST" style="margin: 20px 0; padding: 20px; border: 1px solid #ddd; background: #f5f5f5;">
    <h3>Enviar Email de Prueba</h3>
    <p>Ingresa un email válido para probar el envío:</p>
    <input type="email" name="email" placeholder="ejemplo@gmail.com" required style="width: 300px; padding: 8px; margin: 5px;">
    <br><br>
    <button type="submit" style="padding: 10px 20px; background: #007cba; color: white; border: none; cursor: pointer;">Enviar Prueba</button>
</form>

<hr>
<p><a href='index.php'>← Volver al formulario principal</a></p>
<p><a href='listar_pedidos.php'>📋 Ver pedidos</a></p>
