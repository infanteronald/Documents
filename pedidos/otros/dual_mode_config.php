<?php
/**
 * Configuración del Modo Dual - Bold PSE Migration
 * 
 * Este archivo controla el porcentaje de tráfico que se envía
 * al webhook mejorado durante la migración gradual.
 */

// Porcentaje de webhooks que van al sistema mejorado (0-100)
// TRANSICIÓN COMPLETA: 100% al webhook mejorado
define('ENHANCED_WEBHOOK_PERCENTAGE', 100);

// Configuración de logging para el modo dual
define('DUAL_MODE_LOG_FILE', __DIR__ . '/logs/dual_mode.log');
define('DUAL_MODE_ENABLED', true);

// Lista de IPs que siempre van al webhook mejorado (para testing)
$ENHANCED_WEBHOOK_IPS = [
    // Agregar IPs de prueba aquí
    // '192.168.1.100',
];

// Lista de transaction IDs que van al webhook mejorado (para testing específico)
$ENHANCED_WEBHOOK_TRANSACTIONS = [
    // Agregar transaction IDs específicos para testing
    // 'TEST_TRANSACTION_123',
];

/**
 * Función para determinar si un webhook debe ir al sistema mejorado
 */
function shouldUseEnhancedWebhook($clientIP = null, $transactionId = null) {
    global $ENHANCED_WEBHOOK_IPS, $ENHANCED_WEBHOOK_TRANSACTIONS;
    
    // Si hay IPs específicas configuradas, verificar primero
    if (!empty($ENHANCED_WEBHOOK_IPS) && $clientIP) {
        if (in_array($clientIP, $ENHANCED_WEBHOOK_IPS)) {
            return true;
        }
    }
    
    // Si hay transaction IDs específicos, verificar
    if (!empty($ENHANCED_WEBHOOK_TRANSACTIONS) && $transactionId) {
        if (in_array($transactionId, $ENHANCED_WEBHOOK_TRANSACTIONS)) {
            return true;
        }
    }
    
    // Usar porcentaje aleatorio para el resto
    $random = mt_rand(1, 100);
    return $random <= ENHANCED_WEBHOOK_PERCENTAGE;
}

/**
 * Log para el modo dual
 */
function logDualMode($message, $data = []) {
    if (!DUAL_MODE_ENABLED) return;
    
    $logDir = dirname(DUAL_MODE_LOG_FILE);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    $logEntry = "[{$timestamp}] [IP: {$ip}] {$message}";
    
    if (!empty($data)) {
        $logEntry .= " | Data: " . json_encode($data);
    }
    
    $logEntry .= "\n";
    
    file_put_contents(DUAL_MODE_LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
}
?>
