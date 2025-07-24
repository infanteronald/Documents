<?php
/**
 * CSRF Helper - Sistema QR
 * Funciones helper para manejo de tokens CSRF
 */

/**
 * Generar token CSRF
 */
function generateCSRFToken() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

/**
 * Verificar token CSRF
 */
function verifyCSRFToken($token) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    $session_token = $_SESSION['csrf_token'] ?? '';
    return hash_equals($session_token, $token);
}

/**
 * Obtener token CSRF actual
 */
function getCSRFToken() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    return $_SESSION['csrf_token'] ?? generateCSRFToken();
}

/**
 * HTML para input CSRF token
 */
function csrfTokenInput() {
    $token = getCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Meta tag para CSRF token (para JavaScript)
 */
function csrfMetaTag() {
    $token = getCSRFToken();
    return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}
?>