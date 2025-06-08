<?php
/**
 * Bootstrap del Sistema Sequoia Speed
 * 
 * Este archivo inicializa el sistema y mantiene compatibilidad
 * con la estructura actual durante la migración.
 */

// Definir rutas principales
define('ROOT_PATH', __DIR__);
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('TESTS_PATH', ROOT_PATH . '/tests');

// Configurar autoload
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Configurar manejo de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', STORAGE_PATH . '/logs/php_errors.log');

// Crear directorio de logs si no existe
if (!is_dir(STORAGE_PATH . '/logs')) {
    mkdir(STORAGE_PATH . '/logs', 0755, true);
}

/**
 * Función helper para cargar configuraciones
 */
function loadConfig($name) {
    $legacyFile = ROOT_PATH . '/' . $name . '.php';
    $newFile = APP_PATH . '/config/' . $name . '.php';
    
    // Priorizar archivos nuevos si existen, sino usar los legados
    if (file_exists($newFile)) {
        return require $newFile;
    } elseif (file_exists($legacyFile)) {
        return require $legacyFile;
    }
    
    return null;
}

/**
 * Función helper para logging estructurado
 */
function logMessage($level, $message, $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $level: $message";
    
    if (!empty($context)) {
        $logEntry .= ' ' . json_encode($context);
    }
    
    $logEntry .= "\n";
    
    file_put_contents(STORAGE_PATH . '/logs/app.log', $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Función helper para compatibilidad con archivos legacy
 */
function requireLegacyFile($filename) {
    $fullPath = ROOT_PATH . '/' . $filename;
    if (file_exists($fullPath)) {
        return require_once $fullPath;
    }
    
    logMessage('ERROR', "Legacy file not found: $filename");
    return false;
}

// Log de inicialización
logMessage('INFO', 'Bootstrap initialized', [
    'php_version' => PHP_VERSION,
    'root_path' => ROOT_PATH,
    'timestamp' => time()
]);

// Configurar zona horaria
date_default_timezone_set('America/Bogota');

// Configurar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
