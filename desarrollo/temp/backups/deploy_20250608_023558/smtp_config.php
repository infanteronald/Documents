<?php
// Configuración opcional de SMTP para servidores que lo requieran
// Este archivo se puede incluir en procesar_orden.php si es necesario

// Configuración básica de SMTP (solo si es necesario)
function configurar_smtp_basico() {
    // Para XAMPP en Windows, puedes descomentar estas líneas:
    /*
    ini_set('SMTP', 'smtp.gmail.com');
    ini_set('smtp_port', '587');
    ini_set('sendmail_from', 'noreply@sequoiaspeed.com');
    */
    
    // Para otros servidores, consulta con tu proveedor de hosting
    // sobre la configuración SMTP correcta
}

// Función alternativa usando sockets (si mail() no funciona)
function enviar_email_socket($to, $subject, $message, $from = 'noreply@sequoiaspeed.com') {
    // Esta función se puede implementar como fallback
    // si la función mail() nativa no funciona
    
    // Para implementación futura si es necesario
    return false;
}

// Verificar configuración del servidor
function verificar_configuracion_email() {
    $info = [];
    $info['mail_function'] = function_exists('mail') ? 'Disponible' : 'No disponible';
    $info['smtp'] = ini_get('SMTP') ?: 'No configurado';
    $info['smtp_port'] = ini_get('smtp_port') ?: 'No configurado';
    $info['sendmail_from'] = ini_get('sendmail_from') ?: 'No configurado';
    $info['sendmail_path'] = ini_get('sendmail_path') ?: 'No configurado';
    
    return $info;
}
?>
