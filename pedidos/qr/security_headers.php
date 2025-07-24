<?php
/**
 * Security Headers - Sistema QR
 * Funciones para establecer headers de seguridad
 */

/**
 * Establecer headers de seguridad para páginas web
 */
function setSecurityHeaders($strict = false) {
    // Content Security Policy
    if ($strict) {
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: https:; font-src 'self' https://cdn.jsdelivr.net; connect-src 'self';");
    } else {
        header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' https: data:;");
    }
    
    // X-Frame-Options
    header("X-Frame-Options: SAMEORIGIN");
    
    // X-Content-Type-Options
    header("X-Content-Type-Options: nosniff");
    
    // X-XSS-Protection
    header("X-XSS-Protection: 1; mode=block");
    
    // Referrer Policy
    header("Referrer-Policy: strict-origin-when-cross-origin");
    
    // Feature Policy / Permissions Policy
    header("Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()");
    
    // HTTPS Strict Transport Security (solo para HTTPS)
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
    }
}

/**
 * Establecer headers de seguridad específicos para APIs
 */
function setAPISecurityHeaders() {
    // Headers básicos de seguridad
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: no-referrer");
    
    // Cache control para APIs
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    // Content Type ya debe estar establecido por la API
}

/**
 * Limpiar headers de información del servidor
 */
function cleanServerHeaders() {
    // Intentar ocultar información del servidor
    if (function_exists('header_remove')) {
        header_remove('X-Powered-By');
        header_remove('Server');
    }
}

// Ejecutar limpieza automáticamente
cleanServerHeaders();
?>