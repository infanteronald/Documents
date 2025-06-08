<?php
/**
 * Configuración de Seguridad - Producción
 * Sequoia Speed - FASE 4
 */

class SecurityConfig {
    
    /**
     * Configurar headers de seguridad
     */
    public static function setSecurityHeaders() {
        // Prevenir XSS
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        
        // HTTPS
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        
        // Política de referencia
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // CSP básico
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'");
    }
    
    /**
     * Validar y sanitizar entrada
     */
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        
        $input = trim($input);
        $input = stripslashes($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        
        return $input;
    }
    
    /**
     * Generar token CSRF
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verificar token CSRF
     */
    public static function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Rate limiting básico
     */
    public static function checkRateLimit($identifier, $maxRequests = 100, $timeWindow = 3600) {
        $key = 'rate_limit_' . md5($identifier);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'start' => time()];
        }
        
        $data = $_SESSION[$key];
        
        // Reset si pasó el tiempo
        if (time() - $data['start'] > $timeWindow) {
            $_SESSION[$key] = ['count' => 1, 'start' => time()];
            return true;
        }
        
        // Verificar límite
        if ($data['count'] >= $maxRequests) {
            return false;
        }
        
        $_SESSION[$key]['count']++;
        return true;
    }
    
    /**
     * Validar API Key
     */
    public static function validateApiKey($apiKey) {
        $validKeys = [
            'sequoia_api_key_2024',
            'bold_integration_key'
        ];
        
        return in_array($apiKey, $validKeys);
    }
    
    /**
     * Encriptar datos sensibles
     */
    public static function encrypt($data, $key = null) {
        $key = $key ?: hash('sha256', 'sequoia_speed_2024', true);
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Desencriptar datos
     */
    public static function decrypt($data, $key = null) {
        $key = $key ?: hash('sha256', 'sequoia_speed_2024', true);
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Log de seguridad
     */
    public static function logSecurityEvent($event, $details = []) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'details' => $details
        ];
        
        $logFile = __DIR__ . '/../../logs/security.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
    }
}
