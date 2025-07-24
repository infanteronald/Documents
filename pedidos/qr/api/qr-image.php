<?php
/**
 * API - Generador de Imágenes QR
 * Genera imágenes QR usando QR Server API
 */

defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once dirname(dirname(__DIR__)) . '/config_secure.php';
require_once dirname(dirname(__DIR__)) . '/accesos/middleware/AuthMiddleware.php';

function generateSimpleQR($content, $size) {
    // Generar una imagen simple con el código como texto si todo falla
    $image = imagecreate($size, $size);
    $bg_color = imagecolorallocate($image, 255, 255, 255);
    $text_color = imagecolorallocate($image, 0, 0, 0);
    
    imagefill($image, 0, 0, $bg_color);
    
    // Texto centrado
    $font_size = 2;
    $text_lines = str_split($content, 15); // Dividir en líneas de 15 caracteres
    
    $y_start = ($size - (count($text_lines) * imagefontheight($font_size))) / 2;
    
    foreach ($text_lines as $i => $line) {
        $text_width = imagefontwidth($font_size) * strlen($line);
        $x = ($size - $text_width) / 2;
        $y = $y_start + ($i * imagefontheight($font_size));
        
        imagestring($image, $font_size, $x, $y, $line, $text_color);
    }
    
    ob_start();
    imagepng($image);
    $image_data = ob_get_contents();
    ob_end_clean();
    imagedestroy($image);
    
    return $image_data;
}

try {
    // Verificar autenticación
    $auth = new AuthMiddleware($conn);
    $current_user = $auth->requirePermission('qr', 'leer');
    
    // Obtener parámetros
    $qr_content = $_GET['content'] ?? '';
    $size = (int)($_GET['size'] ?? 200);
    $margin = (int)($_GET['margin'] ?? 10);
    
    if (empty($qr_content)) {
        throw new Exception('Contenido QR requerido');
    }
    
    // Validar tamaño
    if ($size < 100 || $size > 1000) {
        $size = 200;
    }
    
    // Usar QR Server API como primera opción (gratuito y confiable)
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?" . http_build_query([
        'size' => $size . 'x' . $size,
        'data' => $qr_content,
        'format' => 'png',
        'margin' => $margin,
        'ecc' => 'H'
    ]);
    
    // Obtener la imagen
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Sequoia Speed QR System',
            'method' => 'GET'
        ]
    ]);
    
    $image_data = file_get_contents($qr_url, false, $context);
    
    if ($image_data === false) {
        // Fallback: generar QR simple usando PHP GD
        $image_data = generateSimpleQR($qr_content, $size);
    }
    
    // Establecer headers para imagen PNG
    header('Content-Type: image/png');
    header('Content-Length: ' . strlen($image_data));
    header('Cache-Control: public, max-age=3600'); // Cache por 1 hora
    
    echo $image_data;
    
} catch (Exception $e) {
    // En caso de error, generar una imagen de error simple
    header('Content-Type: image/png');
    
    // Crear imagen de error de 200x200
    $image = imagecreate(200, 200);
    $bg_color = imagecolorallocate($image, 240, 240, 240);
    $text_color = imagecolorallocate($image, 200, 50, 50);
    
    imagefill($image, 0, 0, $bg_color);
    
    // Texto de error
    $error_text = "Error QR";
    $font_size = 3;
    $text_width = imagefontwidth($font_size) * strlen($error_text);
    $text_height = imagefontheight($font_size);
    
    $x = (200 - $text_width) / 2;
    $y = (200 - $text_height) / 2;
    
    imagestring($image, $font_size, $x, $y, $error_text, $text_color);
    
    imagepng($image);
    imagedestroy($image);
}
?>