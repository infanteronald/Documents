<?php
// Archivo de prueba para verificar envío de emails con adjuntos
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test de Email con Archivo Adjunto - Sequoia Speed</h2>";

// Incluir las funciones de email
require_once 'procesar_orden.php';

if ($_POST) {
    $email_prueba = $_POST['email'] ?? '';
    
    if (filter_var($email_prueba, FILTER_VALIDATE_EMAIL)) {
        // Manejar archivo de prueba
        $archivo_test = null;
        if (isset($_FILES['archivo_test']) && $_FILES['archivo_test']['error'] == 0) {
            $upload_dir = 'uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $archivo_test = $upload_dir . 'test_' . time() . '_' . $_FILES['archivo_test']['name'];
            
            if (move_uploaded_file($_FILES['archivo_test']['tmp_name'], $archivo_test)) {
                echo "<p style='color: green;'>✅ Archivo subido: " . basename($archivo_test) . "</p>";
            } else {
                echo "<p style='color: red;'>❌ Error subiendo archivo</p>";
                $archivo_test = null;
            }
        }
        
        // Datos del email de prueba
        $subject = "Test Email con Adjunto - Sequoia Speed";
        $message = "Este es un email de prueba con archivo adjunto.\n\n";
        $message .= "Fecha: " . date('Y-m-d H:i:s') . "\n";
        $message .= "Servidor: " . $_SERVER['SERVER_NAME'] . "\n\n";
        
        if ($archivo_test) {
            $message .= "Este email incluye un archivo adjunto de prueba.\n\n";
        } else {
            $message .= "Este email NO incluye archivo adjunto.\n\n";
        }
        
        $message .= "---\nSequoia Speed Colombia Test";

        // Headers
        $headers = "From: Sequoia Speed <ventas@sequoiaspeed.com.co>\r\n";
        $headers .= "Reply-To: ventas@sequoiaspeed.com.co\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        echo "<div style='border: 1px solid #ccc; padding: 15px; margin: 10px 0; background: #f9f9f9;'>";
        echo "<h3>Enviando email de prueba...</h3>";
        echo "<p><strong>Para:</strong> " . htmlspecialchars($email_prueba) . "</p>";
        echo "<p><strong>Asunto:</strong> " . htmlspecialchars($subject) . "</p>";
        echo "<p><strong>Archivo adjunto:</strong> " . ($archivo_test ? basename($archivo_test) : "Ninguno") . "</p>";

        // Enviar email usando la función mejorada
        $resultado = enviar_email_mejorado($email_prueba, $subject, $message, $headers, $archivo_test);

        if ($resultado) {
            echo "<p style='color: green; font-size: 18px;'>✅ <strong>Email enviado exitosamente</strong></p>";
            if ($archivo_test) {
                echo "<p style='color: blue;'>📎 El archivo debería llegar como adjunto en el email</p>";
            }
            echo "<p>Revisa la bandeja de entrada y carpeta de spam del destinatario.</p>";
        } else {
            echo "<p style='color: red; font-size: 18px;'>❌ <strong>Error al enviar el email</strong></p>";
            echo "<p>Revisa el archivo error.log para más detalles</p>";
        }
        
        // Limpiar archivo de prueba después de enviarlo
        if ($archivo_test && file_exists($archivo_test)) {
            unlink($archivo_test);
            echo "<p style='color: gray; font-size: 12px;'>Archivo de prueba eliminado del servidor</p>";
        }
        
        echo "</div>";
    } else {
        echo "<p style='color: red;'>❌ Email inválido</p>";
    }
}
?>

<form method="POST" enctype="multipart/form-data" style="margin: 20px 0; padding: 20px; border: 1px solid #ddd; background: #f5f5f5;">
    <h3>Probar Email con Archivo Adjunto</h3>
    <p>Ingresa un email válido y selecciona un archivo para probar:</p>
    
    <label for="email">Email destinatario:</label><br>
    <input type="email" name="email" id="email" placeholder="ejemplo@gmail.com" required style="width: 300px; padding: 8px; margin: 5px 0;">
    <br><br>
    
    <label for="archivo_test">Archivo de prueba (opcional):</label><br>
    <input type="file" name="archivo_test" id="archivo_test" style="margin: 5px 0;">
    <br><br>
    
    <button type="submit" style="padding: 10px 20px; background: #007cba; color: white; border: none; cursor: pointer;">
        📧 Enviar Email de Prueba
    </button>
</form>

<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0;">
    <h4>💡 Información importante:</h4>
    <ul>
        <li><strong>Con archivo:</strong> El email se envía en formato multipart/mixed con el archivo adjunto</li>
        <li><strong>Sin archivo:</strong> El email se envía como texto plano normal</li>
        <li><strong>Al administrador:</strong> Recibe el comprobante como adjunto real (no solo el nombre)</li>
        <li><strong>Al cliente:</strong> Recibe confirmación sin adjunto para mantener el email limpio</li>
    </ul>
</div>

<hr>
<p><a href='index.php'>← Volver al formulario principal</a></p>
<p><a href='test_email.php'>📧 Test email básico</a></p>
