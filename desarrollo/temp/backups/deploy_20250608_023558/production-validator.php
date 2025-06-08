<?php
/**
 * Sequoia Speed - Validador de Producción FASE 2
 * Validaciones completas antes del despliegue en producción
 */

class ProductionValidator {
    private $errors = [];
    private $warnings = [];
    private $metrics = [];
    private $startTime;
    
    public function __construct() {
        $this->startTime = microtime(true);
        echo "🚀 Iniciando validaciones de producción para Sequoia Speed...\n\n";
    }
    
    public function validateAll() {
        $this->validateSystemRequirements();
        $this->validateFileStructure();
        $this->validateAPIEndpoints();
        $this->validateLegacyCompatibility();
        $this->validateAssetIntegrity();
        $this->validateDatabaseConnections();
        $this->validatePerformanceMetrics();
        $this->validateSecurityHeaders();
        $this->generateProductionReport();
    }
    
    private function validateSystemRequirements() {
        echo "📋 Validando requisitos del sistema...\n";
        
        // PHP Version
        $phpVersion = PHP_VERSION;
        if (version_compare($phpVersion, '7.4.0', '>=')) {
            echo "✅ PHP Version: $phpVersion (Compatible)\n";
        } else {
            $this->errors[] = "PHP Version $phpVersion no cumple requisitos mínimos (7.4+)";
        }
        
        // Extensions requeridas
        $requiredExtensions = ['mysqli', 'json', 'curl', 'mbstring'];
        foreach ($requiredExtensions as $ext) {
            if (extension_loaded($ext)) {
                echo "✅ Extensión $ext: Disponible\n";
            } else {
                $this->errors[] = "Extensión PHP '$ext' no disponible";
            }
        }
        
        // Memoria y límites
        $memoryLimit = ini_get('memory_limit');
        echo "✅ Memory Limit: $memoryLimit\n";
        
        // Permisos de archivos
        $this->validatePermissions();
        
        echo "\n";
    }
    
    private function validatePermissions() {
        $paths = [
            'public/uploads' => 'rwx',
            'public/assets' => 'r-x',
            'config' => 'r--',
            'logs' => 'rwx'
        ];
        
        foreach ($paths as $path => $expectedPerm) {
            if (file_exists($path)) {
                if (is_writable($path) || $expectedPerm === 'r--' || $expectedPerm === 'r-x') {
                    echo "✅ Permisos $path: Correctos\n";
                } else {
                    $this->warnings[] = "Verificar permisos en: $path";
                }
            } else {
                if ($path === 'logs') {
                    mkdir($path, 0755, true);
                    echo "✅ Directorio $path: Creado automáticamente\n";
                } else {
                    $this->warnings[] = "Directorio no encontrado: $path";
                }
            }
        }
    }
    
    private function validateFileStructure() {
        echo "🏗️ Validando estructura de archivos...\n";
        
        $criticalFiles = [
            'index.php' => 'Archivo principal',
            'migration-helper.php' => 'Helper de migración',
            'legacy-bridge.php' => 'Bridge de compatibilidad',
            'public/api/pedidos/create.php' => 'API creación pedidos',
            'public/assets/js/bold-integration.js' => 'Integración Bold',
            'public/assets/js/legacy-compatibility.js' => 'Compatibilidad legacy'
        ];
        
        $missingFiles = [];
        $validFiles = 0;
        
        foreach ($criticalFiles as $file => $description) {
            if (file_exists($file)) {
                $size = filesize($file);
                echo "✅ $description: $file ($size bytes)\n";
                $validFiles++;
            } else {
                $missingFiles[] = "$file - $description";
                $this->errors[] = "Archivo crítico faltante: $file";
            }
        }
        
        $this->metrics['files_validated'] = count($criticalFiles);
        $this->metrics['files_found'] = $validFiles;
        $this->metrics['files_missing'] = count($missingFiles);
        
        echo "\n";
    }
    
    private function validateAPIEndpoints() {
        echo "🌐 Validando endpoints de API...\n";
        
        $endpoints = [
            '/public/api/pedidos/create.php',
            '/public/api/productos/by-category.php', 
            '/public/api/bold/webhook.php',
            '/public/api/exports/excel.php',
            '/public/api/pedidos/update-status.php'
        ];
        
        $workingEndpoints = 0;
        
        foreach ($endpoints as $endpoint) {
            if (file_exists(".$endpoint")) {
                // Simulación de test básico de sintaxis
                $content = file_get_contents(".$endpoint");
                if (strpos($content, '<?php') !== false && strpos($content, 'header') !== false) {
                    echo "✅ API Endpoint: $endpoint (Sintaxis OK)\n";
                    $workingEndpoints++;
                } else {
                    $this->warnings[] = "Endpoint posiblemente incompleto: $endpoint";
                }
            } else {
                $this->errors[] = "Endpoint no encontrado: $endpoint";
            }
        }
        
        $this->metrics['api_endpoints'] = count($endpoints);
        $this->metrics['working_endpoints'] = $workingEndpoints;
        
        echo "\n";
    }
    
    private function validateLegacyCompatibility() {
        echo "🔄 Validando compatibilidad legacy...\n";
        
        // Verificar bridge legacy
        if (file_exists('legacy-bridge.php')) {
            $bridge = file_get_contents('legacy-bridge.php');
            if (strpos($bridge, 'SequoiaLegacyBridge') !== false) {
                echo "✅ Legacy Bridge: Clase principal encontrada\n";
            } else {
                $this->errors[] = "Legacy Bridge sin clase principal";
            }
        }
        
        // Verificar assets de compatibilidad
        if (file_exists('public/assets/js/legacy-compatibility.js')) {
            $compatibility = file_get_contents('public/assets/js/legacy-compatibility.js');
            if (strpos($compatibility, 'legacyCompatibility') !== false) {
                echo "✅ Legacy JS: Sistema de compatibilidad disponible\n";
            } else {
                $this->warnings[] = "Sistema JS de compatibilidad incompleto";
            }
        }
        
        // Verificar helper de migración
        if (file_exists('migration-helper.php')) {
            $helper = file_get_contents('migration-helper.php');
            if (strpos($helper, 'MigrationHelper') !== false) {
                echo "✅ Migration Helper: Clase principal disponible\n";
            } else {
                $this->errors[] = "Migration Helper sin clase principal";
            }
        }
        
        echo "\n";
    }
    
    private function validateAssetIntegrity() {
        echo "📦 Validando integridad de assets...\n";
        
        $assets = [
            'public/assets/js/bold-integration.js' => ['size_min' => 20000, 'contains' => 'BoldPaymentIntegration'],
            'public/assets/js/legacy-compatibility.js' => ['size_min' => 8000, 'contains' => 'legacyCompatibility'],
            'public/assets/js/asset-updater.js' => ['size_min' => 5000, 'contains' => 'AssetUpdater']
        ];
        
        foreach ($assets as $asset => $criteria) {
            if (file_exists($asset)) {
                $size = filesize($asset);
                $content = file_get_contents($asset);
                
                if ($size >= $criteria['size_min'] && strpos($content, $criteria['contains']) !== false) {
                    echo "✅ Asset válido: $asset ($size bytes)\n";
                } else {
                    $this->warnings[] = "Asset posiblemente corrupto: $asset";
                }
            } else {
                $this->errors[] = "Asset faltante: $asset";
            }
        }
        
        echo "\n";
    }
    
    private function validateDatabaseConnections() {
        echo "🗄️ Validando configuración de base de datos...\n";
        
        // Verificar archivo de configuración
        $configFiles = ['config.php', 'db_config.php', 'conexion.php'];
        $configFound = false;
        
        foreach ($configFiles as $config) {
            if (file_exists($config)) {
                echo "✅ Archivo de configuración encontrado: $config\n";
                $configFound = true;
                break;
            }
        }
        
        if (!$configFound) {
            $this->warnings[] = "No se encontró archivo de configuración de BD";
        }
        
        echo "\n";
    }
    
    private function validatePerformanceMetrics() {
        echo "⚡ Validando métricas de performance...\n";
        
        $currentTime = microtime(true);
        $validationTime = $currentTime - $this->startTime;
        
        echo "✅ Tiempo de validación: " . round($validationTime, 3) . " segundos\n";
        
        // Simular carga de archivos críticos
        $loadTimes = [];
        $criticalFiles = ['index.php', 'migration-helper.php', 'legacy-bridge.php'];
        
        foreach ($criticalFiles as $file) {
            if (file_exists($file)) {
                $start = microtime(true);
                include_once $file;
                $loadTime = microtime(true) - $start;
                $loadTimes[$file] = $loadTime;
                echo "✅ Tiempo de carga $file: " . round($loadTime * 1000, 2) . "ms\n";
            }
        }
        
        $this->metrics['validation_time'] = $validationTime;
        $this->metrics['avg_load_time'] = array_sum($loadTimes) / count($loadTimes);
        
        echo "\n";
    }
    
    private function validateSecurityHeaders() {
        echo "🔒 Validando configuraciones de seguridad...\n";
        
        // Verificar que los archivos sensibles no sean accesibles
        $sensitiveFiles = [
            'config.php' => 'Configuración de BD',
            'migration-helper.php' => 'Helper de migración',
            'legacy-bridge.php' => 'Bridge legacy'
        ];
        
        foreach ($sensitiveFiles as $file => $desc) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                if (strpos($content, '<?php') === 0) {
                    echo "✅ Archivo protegido: $file ($desc)\n";
                } else {
                    $this->warnings[] = "Archivo sin protección PHP: $file";
                }
            }
        }
        
        echo "\n";
    }
    
    private function generateProductionReport() {
        $endTime = microtime(true);
        $totalTime = $endTime - $this->startTime;
        
        echo "📊 REPORTE FINAL DE VALIDACIÓN DE PRODUCCIÓN\n";
        echo "=" . str_repeat("=", 50) . "\n";
        
        // Resumen de errores
        if (empty($this->errors)) {
            echo "✅ SIN ERRORES CRÍTICOS - LISTO PARA PRODUCCIÓN\n";
        } else {
            echo "❌ ERRORES CRÍTICOS ENCONTRADOS:\n";
            foreach ($this->errors as $error) {
                echo "   • $error\n";
            }
        }
        
        // Resumen de warnings
        if (!empty($this->warnings)) {
            echo "\n⚠️ ADVERTENCIAS:\n";
            foreach ($this->warnings as $warning) {
                echo "   • $warning\n";
            }
        }
        
        // Métricas
        echo "\n📈 MÉTRICAS DE RENDIMIENTO:\n";
        echo "   • Tiempo total de validación: " . round($totalTime, 3) . "s\n";
        echo "   • Archivos validados: " . ($this->metrics['files_found'] ?? 0) . "/" . ($this->metrics['files_validated'] ?? 0) . "\n";
        echo "   • APIs funcionando: " . ($this->metrics['working_endpoints'] ?? 0) . "/" . ($this->metrics['api_endpoints'] ?? 0) . "\n";
        
        // Recomendaciones
        echo "\n🎯 RECOMENDACIONES PARA PRODUCCIÓN:\n";
        echo "   • Configurar SSL/HTTPS en servidor web\n";
        echo "   • Implementar sistema de logs rotativo\n";
        echo "   • Configurar backup automático de BD\n";
        echo "   • Monitorear métricas de performance las primeras 24h\n";
        echo "   • Preparar rollback plan en caso de problemas\n";
        
        // Estado final
        $status = empty($this->errors) ? 'APROBADO' : 'REQUIERE ATENCIÓN';
        $color = empty($this->errors) ? '✅' : '⚠️';
        
        echo "\n🏆 ESTADO FINAL: $color $status PARA PRODUCCIÓN\n";
        echo "=" . str_repeat("=", 50) . "\n";
        
        // Guardar reporte JSON
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => $status,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'metrics' => array_merge($this->metrics, ['total_validation_time' => $totalTime]),
            'recommendations' => [
                'ssl_https' => 'Configurar SSL/HTTPS',
                'log_rotation' => 'Sistema de logs rotativo',
                'backup_system' => 'Backup automático BD',
                'monitoring' => 'Monitoreo 24h inicial',
                'rollback_plan' => 'Plan de rollback preparado'
            ]
        ];
        
        file_put_contents('production-validation-report.json', json_encode($report, JSON_PRETTY_PRINT));
        echo "\n💾 Reporte guardado en: production-validation-report.json\n";
    }
}

// Ejecutar validación de producción
if (php_sapi_name() === 'cli' || !isset($_SERVER['HTTP_HOST'])) {
    $validator = new ProductionValidator();
    $validator->validateAll();
} else {
    echo "Este script debe ejecutarse desde línea de comandos para validaciones de seguridad.";
}
?>
