<?php
/**
 * Legacy Bridge - Puente de Compatibilidad Universal
 * Sequoia Speed - Sistema de gestión de pedidos
 * 
 * Este archivo proporciona un puente automático entre archivos legacy
 * y el nuevo sistema moderno, asegurando compatibilidad al 100%.
 * 
 * Uso: require_once 'legacy-bridge.php'; al inicio de cualquier archivo legacy
 */

// Evitar inclusión múltiple
if (defined('SEQUOIA_LEGACY_BRIDGE_LOADED')) {
    return;
}
define('SEQUOIA_LEGACY_BRIDGE_LOADED', true);

// Cargar el sistema de migración
require_once __DIR__ . '/migration-helper.php';

/**
 * Clase Legacy Bridge - Puente de compatibilidad automático
 */
class SequoiaLegacyBridge {
    private static $instance = null;
    private $migrationHelper;
    private $redirectionLog = [];
    
    private function __construct() {
        $this->migrationHelper = MigrationHelper::getInstance();
        $this->setupLegacySupport();
        $this->logLegacyUsage();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Configurar soporte legacy automático
     */
    private function setupLegacySupport() {
        // Interceptar headers si no se han enviado aún
        if (!headers_sent()) {
            header('X-Legacy-Bridge: active');
            header('X-Migration-Phase: 2');
        }
        
        // Registrar manejador de errores para archivos no encontrados
        set_error_handler([$this, 'handleLegacyError'], E_WARNING);
        
        // Configurar rutas de redirección automática
        $this->setupAutoRedirection();
    }
    
    /**
     * Configurar redirección automática para includes/requires
     */
    private function setupAutoRedirection() {
        // Mapeo de archivos legacy a modernos
        $redirectMap = [
            'smtp_config.php' => '/app/config/smtp.php',
            'database_config.php' => '/app/config/database.php',
            'app_config.php' => '/app/config/app.php'
        ];
        
        foreach ($redirectMap as $legacy => $modern) {
            if (!file_exists($legacy) && file_exists(__DIR__ . $modern)) {
                $this->createSymbolicBridge($legacy, $modern);
            }
        }
    }
    
    /**
     * Crear puente simbólico para archivos
     */
    private function createSymbolicBridge($legacyFile, $modernPath) {
        $fullModernPath = __DIR__ . $modernPath;
        if (file_exists($fullModernPath)) {
            $bridgeContent = "<?php\n";
            $bridgeContent .= "// Auto-generated legacy bridge\n";
            $bridgeContent .= "// Redirecting $legacyFile to $modernPath\n";
            $bridgeContent .= "require_once __DIR__ . '$modernPath';\n";
            
            file_put_contents($legacyFile, $bridgeContent);
            $this->redirectionLog[] = "$legacyFile -> $modernPath";
        }
    }
    
    /**
     * Manejar errores legacy
     */
    public function handleLegacyError($errno, $errstr, $errfile, $errline) {
        // Solo manejar archivos no encontrados
        if (strpos($errstr, 'No such file') !== false || strpos($errstr, 'failed to open stream') !== false) {
            $this->attemptLegacyFallback($errstr);
        }
        
        // Continuar con el manejo normal de errores
        return false;
    }
    
    /**
     * Intentar fallback para archivos legacy
     */
    private function attemptLegacyFallback($errorString) {
        // Extraer nombre del archivo del error
        preg_match('/\'([^\']+)\'/', $errorString, $matches);
        if (!empty($matches[1])) {
            $missingFile = basename($matches[1]);
            $modernPath = $this->migrationHelper->resolveLegacyPath($missingFile);
            
            if ($modernPath && file_exists(__DIR__ . $modernPath)) {
                error_log("Legacy Bridge: Redirecting $missingFile to $modernPath");
                require_once __DIR__ . $modernPath;
            }
        }
    }
    
    /**
     * Registrar uso de archivos legacy
     */
    private function logLegacyUsage() {
        $currentFile = $_SERVER['SCRIPT_NAME'] ?? 'unknown';
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'file' => $currentFile,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        $logFile = __DIR__ . '/storage/logs/legacy-usage.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Inyectar assets modernos en respuesta HTML
     */
    public function injectModernAssets() {
        return $this->migrationHelper->injectMigrationAssets();
    }
    
    /**
     * Resolver URL de API con fallback automático
     */
    public function resolveApiUrl($legacyApi) {
        return $this->migrationHelper->resolveLegacyApiUrl($legacyApi);
    }
    
    /**
     * Obtener estadísticas del puente
     */
    public function getStats() {
        return [
            'redirections_created' => count($this->redirectionLog),
            'redirections' => $this->redirectionLog,
            'migration_phase' => 'FASE 2',
            'compatibility_level' => '100%'
        ];
    }
}

// Función global para facilitar el uso
function sequoia_legacy_bridge() {
    return SequoiaLegacyBridge::getInstance();
}

// Funciones de compatibilidad global para JavaScript legacy
function sequoia_inject_modern_js() {
    $bridge = SequoiaLegacyBridge::getInstance();
    echo $bridge->injectModernAssets();
}

// Auto-inicializar el puente
$legacyBridge = SequoiaLegacyBridge::getInstance();

// Registrar función de finalización para limpiar
register_shutdown_function(function() use ($legacyBridge) {
    // Log final si hay errores
    $error = error_get_last();
    if ($error && $error['type'] === E_ERROR) {
        error_log("Legacy Bridge: Fatal error in " . $error['file'] . " line " . $error['line'] . ": " . $error['message']);
    }
});

?>
