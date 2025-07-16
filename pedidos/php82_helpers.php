<?php
/**
 * PHP 8.2 Compatibility Helper Functions
 * Soluciona problemas de deprecated warnings con null values
 */

/**
 * Función segura para htmlspecialchars que maneja null values
 * Compatible con PHP 8.2+
 */
function h($value, $flags = ENT_QUOTES, $encoding = 'UTF-8', $double_encode = true) {
    if ($value === null) {
        return '';
    }
    return htmlspecialchars($value, $flags, $encoding, $double_encode);
}

/**
 * Función segura para valores de base de datos
 * Convierte null a string vacío
 */
function safe_db_value($value, $default = '') {
    return $value ?? $default;
}

/**
 * Función segura para atributos HTML
 * Escapa y maneja null values
 */
function safe_attr($value, $default = '') {
    return h(safe_db_value($value, $default));
}

/**
 * Función segura para texto mostrado
 * Maneja null values con un valor por defecto
 */
function safe_text($value, $default = 'No especificado') {
    return h(safe_db_value($value, $default));
}

/**
 * Función segura para números
 * Convierte null a 0 o valor especificado
 */
function safe_number($value, $default = 0) {
    return $value ?? $default;
}

/**
 * Función segura para fechas
 * Maneja null values en fechas
 */
function safe_date($value, $format = 'Y-m-d', $default = '') {
    if ($value === null) {
        return $default;
    }
    if (is_string($value)) {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $default;
        }
        return date($format, $timestamp);
    }
    return $default;
}

/**
 * Función segura para URLs
 * Maneja null values en URLs
 */
function safe_url($value, $default = '#') {
    return h(safe_db_value($value, $default));
}

/**
 * Función segura para email
 * Maneja null values en emails
 */
function safe_email($value, $default = '') {
    return h(safe_db_value($value, $default));
}

/**
 * Función segura para teléfonos
 * Maneja null values en teléfonos
 */
function safe_phone($value, $default = '') {
    return h(safe_db_value($value, $default));
}

/**
 * Función segura para JSON
 * Maneja null values en JSON
 */
function safe_json($value, $default = '{}') {
    if ($value === null) {
        return $default;
    }
    return json_encode($value);
}
?>