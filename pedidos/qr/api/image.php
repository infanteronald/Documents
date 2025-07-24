<?php
/**
 * API - Generador de Imágenes QR
 * Sequoia Speed - Sistema QR
 */

defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once dirname(dirname(__DIR__)) . '/config_secure.php';
require_once dirname(dirname(__DIR__)) . '/accesos/middleware/AuthMiddleware.php';

try {
    // Verificar autenticación
    $auth = new AuthMiddleware($conn);
    $current_user = $auth->requirePermission('qr', 'leer');
    
    // Note: GET requests for image generation typically don't require CSRF token
    // as they are read-only operations
    
    // Obtener parámetros
    $qr_content = $_GET['content'] ?? '';
    $size = intval($_GET['size'] ?? 400);
    $margin = intval($_GET['margin'] ?? 20);
    $error_correction = $_GET['error_correction'] ?? 'H';
    $format = $_GET['format'] ?? 'png';
    
    // Validar parámetros
    if (empty($qr_content)) {
        throw new Exception('Contenido QR requerido', 400);
    }
    
    if ($size < 100 || $size > 1000) {
        throw new Exception('Tamaño debe estar entre 100 y 1000 píxeles', 400);
    }
    
    if (!in_array($error_correction, ['L', 'M', 'Q', 'H'])) {
        throw new Exception('Nivel de corrección de error inválido', 400);
    }
    
    if (!in_array($format, ['png', 'svg'])) {
        throw new Exception('Formato inválido', 400);
    }
    
    // Verificar que el QR existe en la base de datos
    $qr_query = "SELECT id, qr_content, entity_type, entity_id FROM qr_codes WHERE qr_content = ? AND active = 1 LIMIT 1";
    $stmt = $conn->prepare($qr_query);
    $stmt->bind_param('s', $qr_content);
    $stmt->execute();
    $qr_data = $stmt->get_result()->fetch_assoc();
    
    if (!$qr_data) {
        throw new Exception('Código QR no encontrado', 404);
    }
    
    // Para generar QR, usaremos una implementación simple de QR
    // En producción se recomendaría usar una librería como QR Code Generator
    $qr_image = generateQRImage($qr_content, $size, $margin, $error_correction, $format);
    
    if ($format === 'svg') {
        header('Content-Type: image/svg+xml');
        header('Content-Disposition: inline; filename="qr_' . $qr_data['id'] . '.svg"');
    } else {
        header('Content-Type: image/png');
        header('Content-Disposition: inline; filename="qr_' . $qr_data['id'] . '.png"');
    }
    
    // Headers de cache
    header('Cache-Control: public, max-age=3600');
    header('ETag: "' . md5($qr_content . $size . $margin . $error_correction . $format) . '"');
    
    echo $qr_image;
    
} catch (Exception $e) {
    $error_code = $e->getCode() ?: 500;
    http_response_code($error_code);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $error_code,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    error_log("QR Image API Error: " . $e->getMessage() . " | Code: " . $error_code);
}

/**
 * Genera imagen QR simple
 * NOTA: En producción usar una librería dedicada como endroid/qr-code
 */
function generateQRImage($content, $size, $margin, $error_correction, $format) {
    if ($format === 'svg') {
        return generateQRSVG($content, $size, $margin);
    } else {
        return generateQRPNG($content, $size, $margin);
    }
}

/**
 * Genera QR en formato SVG simple
 */
function generateQRSVG($content, $size, $margin) {
    // Implementación básica de QR en SVG
    // En producción usar librería especializada
    
    $qr_size = $size - ($margin * 2);
    $module_size = $qr_size / 25; // Asumiendo matriz 25x25 simplificada
    
    $svg = '<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '">
    <rect width="' . $size . '" height="' . $size . '" fill="white"/>
    <g transform="translate(' . $margin . ',' . $margin . ')">';
    
    // Generar patrón QR básico (simulado)
    $matrix = generateSimpleQRMatrix($content);
    
    for ($y = 0; $y < count($matrix); $y++) {
        for ($x = 0; $x < count($matrix[$y]); $x++) {
            if ($matrix[$y][$x] === 1) {
                $px = $x * $module_size;
                $py = $y * $module_size;
                $svg .= '<rect x="' . $px . '" y="' . $py . '" width="' . $module_size . '" height="' . $module_size . '" fill="black"/>';
            }
        }
    }
    
    $svg .= '</g>
    <!-- Texto de identificación -->
    <text x="' . ($size/2) . '" y="' . ($size - 5) . '" text-anchor="middle" font-family="Arial" font-size="8" fill="gray">' . htmlspecialchars(substr($content, 0, 15)) . '</text>
</svg>';
    
    return $svg;
}

/**
 * Genera QR en formato PNG simple
 */
function generateQRPNG($content, $size, $margin) {
    // Crear imagen básica
    $image = imagecreate($size, $size);
    
    // Colores
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    $gray = imagecolorallocate($image, 128, 128, 128);
    
    // Fondo blanco
    imagefill($image, 0, 0, $white);
    
    // Generar patrón QR básico
    $matrix = generateSimpleQRMatrix($content);
    $qr_size = $size - ($margin * 2);
    $module_size = $qr_size / count($matrix);
    
    for ($y = 0; $y < count($matrix); $y++) {
        for ($x = 0; $x < count($matrix[$y]); $x++) {
            if ($matrix[$y][$x] === 1) {
                $px = $margin + ($x * $module_size);
                $py = $margin + ($y * $module_size);
                imagefilledrectangle($image, $px, $py, $px + $module_size, $py + $module_size, $black);
            }
        }
    }
    
    // Agregar texto identificativo
    imagestring($image, 1, 5, $size - 15, substr($content, 0, 20), $gray);
    
    // Convertir a PNG
    ob_start();
    imagepng($image);
    $png_data = ob_get_contents();
    ob_end_clean();
    
    imagedestroy($image);
    return $png_data;
}

/**
 * Genera matriz QR simplificada
 * IMPORTANTE: Esta es una implementación básica para demostración
 * En producción usar una librería QR completa
 */
function generateSimpleQRMatrix($content) {
    // Matriz 25x25 con patrón básico basado en el contenido
    $size = 25;
    $matrix = array_fill(0, $size, array_fill(0, $size, 0));
    
    // Patrones de posicionamiento (esquinas)
    $patterns = [
        [0, 0], [0, $size-7], [$size-7, 0]
    ];
    
    foreach ($patterns as $pattern) {
        [$startX, $startY] = $pattern;
        // Patrón 7x7
        for ($y = 0; $y < 7; $y++) {
            for ($x = 0; $x < 7; $x++) {
                if (($x == 0 || $x == 6 || $y == 0 || $y == 6) || 
                    ($x >= 2 && $x <= 4 && $y >= 2 && $y <= 4)) {
                    if ($startX + $x < $size && $startY + $y < $size) {
                        $matrix[$startY + $y][$startX + $x] = 1;
                    }
                }
            }
        }
    }
    
    // Generar datos basados en el contenido
    $hash = md5($content);
    for ($i = 0; $i < strlen($hash); $i++) {
        $val = hexdec($hash[$i]);
        $x = 8 + ($i % 10);
        $y = 8 + floor($i / 10);
        
        if ($x < $size && $y < $size) {
            if ($val % 2 == 0) {
                $matrix[$y][$x] = 1;
            }
        }
    }
    
    // Patrón de timing
    for ($i = 8; $i < $size - 8; $i++) {
        if ($i % 2 == 0) {
            $matrix[6][$i] = 1;
            $matrix[$i][6] = 1;
        }
    }
    
    return $matrix;
}
?>