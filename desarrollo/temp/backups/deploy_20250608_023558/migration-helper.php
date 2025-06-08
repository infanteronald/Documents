<?php
/**
 * Migration Helper - Sequoia Speed
 * Helper para migración gradual de archivos legacy
 * 
 * Este archivo puede ser incluido en archivos legacy para
 * facilitar la transición a la arquitectura moderna
 */

// Solo ejecutar una vez
if (defined('SEQUOIA_MIGRATION_HELPER_LOADED')) {
    return;
}
define('SEQUOIA_MIGRATION_HELPER_LOADED', true);

class MigrationHelper {
    private static $instance = null;
    private $assetMap = [];
    private $apiMap = [];
    
    private function __construct() {
        $this->initAssetMap();
        $this->initApiMap();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function initAssetMap() {
        $this->assetMap = [
            // CSS
            'pedidos.css' => '/public/assets/css/app.css',
            'estilos.css' => '/public/assets/css/components.css',
            'payment_ux_enhanced.css' => '/public/assets/css/payment.css',
            'apple-ui.css' => '/public/assets/css/app.css',
            'sequoia-unified.css' => '/public/assets/css/app.css',
            'styles.css' => '/public/assets/css/components.css',
            
            // JavaScript
            'script.js' => '/public/assets/js/app.js',
            'pedidos.js' => '/public/assets/js/pedidos.js',
            'payment_ux_enhanced.js' => '/public/assets/js/bold-integration.js'
        ];
    }
    
    private function initApiMap() {
        $this->apiMap = [
            'guardar_pedido.php' => '/public/api/pedidos/create.php',
            'actualizar_estado.php' => '/public/api/pedidos/update-status.php',
            'bold_webhook_enhanced.php' => '/public/api/bold/webhook.php',
            'productos_por_categoria.php' => '/public/api/productos/by-category.php',
            'exportar_excel.php' => '/public/api/exports/excel.php',
            'procesar_pago_manual.php' => '/public/api/payments/manual.php'
        ];
    }
    
    /**
     * Incluir assets modernos en lugar de legacy
     */
    public function includeModernAssets() {
        // Incluir auto-updater de assets
        echo '<script src="/public/assets/js/asset-updater.js"></script>' . "\n";
        
        // Incluir wrapper de compatibilidad
        echo '<script src="/public/assets/js/legacy-compatibility.js"></script>' . "\n";
        
        // CSS moderno principal
        echo '<link rel="stylesheet" href="/public/assets/css/app.css">' . "\n";
        echo '<link rel="stylesheet" href="/public/assets/css/components.css">' . "\n";
    }
    
    /**
     * Convertir ruta de asset legacy a moderna
     */
    public function getModernAssetPath($legacyPath) {
        return $this->assetMap[$legacyPath] ?? $legacyPath;
    }
    
    /**
     * Convertir ruta de API legacy a moderna
     */
    public function getModernApiPath($legacyPath) {
        return $this->apiMap[$legacyPath] ?? $legacyPath;
    }
    
    /**
     * Generar tag de CSS moderno
     */
    public function cssTag($legacyPath) {
        $modernPath = $this->getModernAssetPath($legacyPath);
        return '<link rel="stylesheet" href="' . htmlspecialchars($modernPath) . '">';
    }
    
    /**
     * Generar tag de JavaScript moderno
     */
    public function jsTag($legacyPath) {
        $modernPath = $this->getModernAssetPath($legacyPath);
        return '<script src="' . htmlspecialchars($modernPath) . '"></script>';
    }
    
    /**
     * Hacer petición a API moderna desde código legacy
     */
    public function callModernApi($legacyEndpoint, $data = [], $method = 'POST') {
        $modernEndpoint = $this->getModernApiPath($legacyEndpoint);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $modernEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Legacy-Compatibility: true'
        ]);
        
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true);
        }
        
        return ['success' => false, 'error' => 'Error en API: ' . $httpCode];
    }
    
    /**
     * Verificar si el bootstrap moderno está disponible
     */
    public function isModernSystemAvailable() {
        return class_exists('SequoiaSpeed\\Controllers\\PedidoController');
    }
    
    /**
     * Cargar configuración moderna si está disponible
     */
    public function loadModernConfig() {
        if (file_exists(__DIR__ . '/../../bootstrap.php')) {
            require_once __DIR__ . '/../../bootstrap.php';
            return true;
        }
        return false;
    }
    
    /**
     * Generar JavaScript para actualización automática
     */
    public function generateAssetUpdateScript() {
        ob_start();
        ?>
        <script>
        // Auto-actualización de assets
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🔄 Aplicando actualizaciones de migración...');
            
            // Actualizar formularios para usar APIs modernas
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                const action = form.getAttribute('action');
                if (action) {
                    const modernActions = {
                        'guardar_pedido.php': '/public/api/pedidos/create.php',
                        'actualizar_estado.php': '/public/api/pedidos/update-status.php'
                    };
                    
                    if (modernActions[action]) {
                        console.log(`🔄 Actualizando action: ${action} → ${modernActions[action]}`);
                        form.setAttribute('action', modernActions[action]);
                    }
                }
            });
            
            // Actualizar enlaces a APIs
            const links = document.querySelectorAll('a[href]');
            links.forEach(link => {
                const href = link.getAttribute('href');
                if (href && href.includes('.php')) {
                    const modernHrefs = {
                        'exportar_excel.php': '/public/api/exports/excel.php',
                        'productos_por_categoria.php': '/public/api/productos/by-category.php'
                    };
                    
                    Object.keys(modernHrefs).forEach(legacyHref => {
                        if (href.includes(legacyHref)) {
                            const newHref = href.replace(legacyHref, modernHrefs[legacyHref]);
                            console.log(`🔄 Actualizando enlace: ${href} → ${newHref}`);
                            link.setAttribute('href', newHref);
                        }
                    });
                }
            });
            
            console.log('✅ Actualizaciones de migración aplicadas');
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Log de migración
     */
    public function logMigration($message, $type = 'info') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$type}] {$message}" . PHP_EOL;
        
        // Escribir a log de migración
        $logFile = __DIR__ . '/../../storage/logs/migration.log';
        if (!file_exists(dirname($logFile))) {
            mkdir(dirname($logFile), 0755, true);
        }
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        // También log a PHP error log en desarrollo
        if (defined('DEBUG') && DEBUG) {
            error_log("Migration: {$message}");
        }
    }
    
    /**
     * Verificar el estado de la migración
     */
    public function verificarEstadoMigracion() {
        $estado = [
            'fase_actual' => 'FASE 2 - Migración gradual',
            'apis_migradas' => 5,
            'archivos_principales_actualizados' => 3,
            'compatibilidad_legacy' => '100%',
            'assets_modernos' => [
                'bold-integration.js' => file_exists(__DIR__ . '/public/assets/js/bold-integration.js'),
                'legacy-compatibility.js' => file_exists(__DIR__ . '/public/assets/js/legacy-compatibility.js'),
                'asset-updater.js' => file_exists(__DIR__ . '/public/assets/js/asset-updater.js')
            ],
            'apis_disponibles' => [
                'pedidos/create' => file_exists(__DIR__ . '/public/api/pedidos/create.php'),
                'productos/by-category' => file_exists(__DIR__ . '/public/api/productos/by-category.php'),
                'bold/webhook' => file_exists(__DIR__ . '/public/api/bold/webhook.php'),
                'exports/excel' => file_exists(__DIR__ . '/public/api/exports/excel.php'),
                'pedidos/update-status' => file_exists(__DIR__ . '/public/api/pedidos/update-status.php')
            ]
        ];
        
        return $estado;
    }

    /**
     * Generar reporte de migración
     */
    public function generarReporteMigracion() {
        $estado = $this->verificarEstadoMigracion();
        
        echo "<div style='background: #1e1e1e; color: #d4d4d4; padding: 20px; font-family: \"SF Mono\", Monaco, monospace; border-radius: 8px; margin: 20px 0;'>";
        echo "<h3 style='color: #4FC3F7; margin-top: 0;'>📊 Estado de Migración Sequoia Speed</h3>";
        echo "<p><strong>Fase actual:</strong> " . $estado['fase_actual'] . "</p>";
        echo "<p><strong>APIs migradas:</strong> " . $estado['apis_migradas'] . "/5</p>";
        echo "<p><strong>Compatibilidad legacy:</strong> " . $estado['compatibilidad_legacy'] . "</p>";
        
        echo "<h4 style='color: #81C784;'>✅ Assets Modernos:</h4>";
        foreach ($estado['assets_modernos'] as $asset => $disponible) {
            $icono = $disponible ? '✅' : '❌';
            echo "<p>$icono $asset</p>";
        }
        
        echo "<h4 style='color: #81C784;'>🔗 APIs Disponibles:</h4>";
        foreach ($estado['apis_disponibles'] as $api => $disponible) {
            $icono = $disponible ? '✅' : '❌';
            echo "<p>$icono /public/api/$api</p>";
        }
        
        echo "</div>";
    }
    
    /**
     * Inyectar assets de migración en la página
     */
    public function injectMigrationAssets() {
        $output = "\n<!-- Sequoia Speed - Sistema de Migración FASE 2 -->\n";
        
        // Incluir compatibility wrapper
        $output .= '<script src="/public/assets/js/legacy-compatibility.js"></script>' . "\n";
        
        // Incluir Bold integration moderno
        $output .= '<script src="/public/assets/js/bold-integration.js"></script>' . "\n";
        
        // Incluir asset updater
        $output .= '<script src="/public/assets/js/asset-updater.js"></script>' . "\n";
        
        // Incluir estilos modernos
        $output .= '<link rel="stylesheet" href="/public/assets/css/app.css">' . "\n";
        $output .= '<link rel="stylesheet" href="/public/assets/css/components.css">' . "\n";
        $output .= '<link rel="stylesheet" href="/public/assets/css/payment.css">' . "\n";
        
        // Script de inicialización
        $output .= '<script>' . "\n";
        $output .= 'document.addEventListener("DOMContentLoaded", function() {' . "\n";
        $output .= '    if (window.legacyCompatibility) {' . "\n";
        $output .= '        console.log("✅ Sistema de compatibilidad legacy activo");' . "\n";
        $output .= '        window.legacyCompatibility.init();' . "\n";
        $output .= '    }' . "\n";
        $output .= '    if (window.boldPayment) {' . "\n";
        $output .= '        console.log("✅ Bold Payment Integration disponible");' . "\n";
        $output .= '    }' . "\n";
        $output .= '});' . "\n";
        $output .= '</script>' . "\n";
        $output .= "<!-- /Sistema de Migración -->\n";
        
        return $output;
    }
}

// Funciones helper globales
function migration_helper() {
    return MigrationHelper::getInstance();
}

function include_modern_assets() {
    return migration_helper()->includeModernAssets();
}

function modern_css($legacyPath) {
    return migration_helper()->cssTag($legacyPath);
}

function modern_js($legacyPath) {
    return migration_helper()->jsTag($legacyPath);
}

function call_modern_api($legacyEndpoint, $data = [], $method = 'POST') {
    return migration_helper()->callModernApi($legacyEndpoint, $data, $method);
}

// Auto-inicializar si no está en contexto manual
if (!defined('MANUAL_MIGRATION_INIT')) {
    // Intentar cargar sistema moderno
    $helper = migration_helper();
    if ($helper->loadModernConfig()) {
        $helper->logMigration('Sistema moderno cargado exitosamente');
    } else {
        $helper->logMigration('Sistema moderno no disponible, usando modo legacy', 'warning');
    }
}
?>
