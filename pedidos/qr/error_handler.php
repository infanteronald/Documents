<?php
/**
 * Error Handler - Sistema QR
 * Manejo centralizado de errores para el sistema QR
 */

/**
 * Configurar manejo de errores personalizado
 */
function setupErrorHandler() {
    // Configurar nivel de reporte de errores
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    
    // Establecer manejador de errores personalizado
    set_error_handler('customErrorHandler');
    
    // Establecer manejador de excepciones no capturadas
    set_exception_handler('customExceptionHandler');
    
    // Establecer función de cierre para errores fatales
    register_shutdown_function('checkForFatalError');
}

/**
 * Manejador personalizado de errores
 */
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    // No procesar errores suprimidos con @
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    $error_type = getErrorType($errno);
    $error_message = "[$error_type] $errstr en $errfile línea $errline";
    
    // Log del error
    error_log("QR System Error: $error_message");
    
    // Si es un error fatal, mostrar mensaje amigable
    if ($errno & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR)) {
        handleFatalError($error_message);
    }
    
    return true;
}

/**
 * Manejador de excepciones no capturadas
 */
function customExceptionHandler($exception) {
    $message = "Excepción no capturada: " . $exception->getMessage();
    $file = $exception->getFile();
    $line = $exception->getLine();
    
    error_log("QR System Exception: $message en $file línea $line");
    
    // Mostrar mensaje de error amigable
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }
    
    echo json_encode([
        'success' => false,
        'error' => 'Error crítico del sistema',
        'code' => 'SYSTEM_ERROR',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    exit;
}

/**
 * Verificar errores fatales al cerrar el script
 */
function checkForFatalError() {
    $error = error_get_last();
    
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        $error_message = "Error Fatal: {$error['message']} en {$error['file']} línea {$error['line']}";
        error_log("QR System Fatal Error: $error_message");
        
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
            
            echo json_encode([
                'success' => false,
                'error' => 'Error crítico del sistema',
                'code' => 'FATAL_ERROR',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
    }
}

/**
 * Manejar errores fatales
 */
function handleFatalError($message) {
    error_log("QR System Fatal: $message");
    
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }
    
    echo json_encode([
        'success' => false,
        'error' => 'Error crítico del sistema',
        'code' => 'FATAL_ERROR',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    exit;
}

/**
 * Obtener tipo de error como string
 */
function getErrorType($errno) {
    $error_types = [
        E_ERROR => 'E_ERROR',
        E_WARNING => 'E_WARNING',
        E_PARSE => 'E_PARSE',
        E_NOTICE => 'E_NOTICE',
        E_CORE_ERROR => 'E_CORE_ERROR',
        E_CORE_WARNING => 'E_CORE_WARNING',
        E_COMPILE_ERROR => 'E_COMPILE_ERROR',
        E_COMPILE_WARNING => 'E_COMPILE_WARNING',
        E_USER_ERROR => 'E_USER_ERROR',
        E_USER_WARNING => 'E_USER_WARNING',
        E_USER_NOTICE => 'E_USER_NOTICE',
        E_STRICT => 'E_STRICT',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_DEPRECATED => 'E_DEPRECATED',
        E_USER_DEPRECATED => 'E_USER_DEPRECATED'
    ];
    
    return $error_types[$errno] ?? 'UNKNOWN_ERROR';
}

/**
 * Log de errores específicos del sistema QR
 */
function logQRError($message, $context = []) {
    $log_message = "QR System: $message";
    
    if (!empty($context)) {
        $log_message .= " Context: " . json_encode($context);
    }
    
    error_log($log_message);
}

/**
 * Validar y sanitizar entrada para prevenir errores
 */
function validateInput($input, $type = 'string', $max_length = 255) {
    if ($input === null || $input === '') {
        return null;
    }
    
    switch ($type) {
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT);
            
        case 'float':
            return filter_var($input, FILTER_VALIDATE_FLOAT);
            
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL);
            
        case 'url':
            return filter_var($input, FILTER_VALIDATE_URL);
            
        case 'string':
        default:
            $sanitized = htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
            return strlen($sanitized) <= $max_length ? $sanitized : substr($sanitized, 0, $max_length);
    }
}
?>