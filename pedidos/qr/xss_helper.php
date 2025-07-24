<?php
/**
 * XSS Protection Helper - Sistema QR
 * Funciones helper para prevenir ataques XSS
 */

/**
 * Escapar contenido HTML
 */
function escape_html($string) {
    if ($string === null) {
        return '';
    }
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Escapar atributos HTML
 */
function escape_attr($string) {
    if ($string === null) {
        return '';
    }
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Escapar contenido para JavaScript
 */
function escape_js($string) {
    if ($string === null) {
        return '';
    }
    return json_encode($string, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
}

/**
 * Escapar contenido para URL
 */
function escape_url($string) {
    if ($string === null) {
        return '';
    }
    return urlencode($string);
}

/**
 * Sanitizar contenido HTML (permite tags seguros)
 */
function sanitize_html($string) {
    if ($string === null) {
        return '';
    }
    
    // Lista de tags permitidos
    $allowed_tags = '<p><br><strong><b><em><i><u><ul><ol><li><a>';
    
    // Remover tags no permitidos
    $clean = strip_tags($string, $allowed_tags);
    
    // Escapar atributos peligrosos en tags permitidos
    $clean = preg_replace_callback('/<a\s+[^>]*href\s*=\s*["\']([^"\']*)["\'][^>]*>/i', function($matches) {
        $href = $matches[1];
        // Solo permitir URLs http, https, mailto
        if (preg_match('/^(https?:\/\/|mailto:)/i', $href)) {
            return '<a href="' . escape_attr($href) . '">';
        }
        return '<span>';
    }, $clean);
    
    return $clean;
}

/**
 * Validar y sanitizar nombre de archivo
 */
function sanitize_filename($filename) {
    // Remover caracteres peligrosos
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    
    // Prevenir nombres de archivo peligrosos
    $dangerous_names = ['con', 'prn', 'aux', 'nul', 'com1', 'com2', 'lpt1', 'lpt2'];
    $name_without_ext = pathinfo($filename, PATHINFO_FILENAME);
    
    if (in_array(strtolower($name_without_ext), $dangerous_names)) {
        $filename = 'safe_' . $filename;
    }
    
    return $filename;
}

/**
 * Crear nonce para CSP
 */
function generate_csp_nonce() {
    return base64_encode(random_bytes(16));
}

/**
 * Verificar si una URL es segura
 */
function is_safe_url($url) {
    // Verificar que no sea JavaScript u otros esquemas peligrosos
    if (preg_match('/^(javascript|data|vbscript):/i', $url)) {
        return false;
    }
    
    // Verificar que sea una URL válida
    if (!filter_var($url, FILTER_VALIDATE_URL) && !preg_match('/^\/[^\/]/', $url)) {
        return false;
    }
    
    return true;
}

/**
 * Generar URL segura
 */
function safe_url($url, $default = '#') {
    return is_safe_url($url) ? escape_attr($url) : $default;
}

/**
 * Formatear número de forma segura
 */
function safe_number($number, $decimals = 0) {
    if (!is_numeric($number)) {
        return '0';
    }
    return number_format((float)$number, $decimals);
}

/**
 * Formatear fecha de forma segura
 */
function safe_date($date, $format = 'Y-m-d H:i:s') {
    if (empty($date) || $date === '0000-00-00 00:00:00') {
        return '';
    }
    
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return '';
    }
    
    return date($format, $timestamp);
}
?>