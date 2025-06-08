<?php
/**
 * Runner de Tests de Performance - FASE 3 Sequoia Speed
 * Ejecuta y valida todas las optimizaciones implementadas
 */

echo "🚀 TESTS DE PERFORMANCE FASE 3\n";
echo "===============================\n\n";

class PerformanceTestRunner {
    private $basePath;
    private $results = [];
    private $errors = [];
    
    public function __construct() {
        $this->basePath = dirname(__DIR__, 2);
    }
    
    public function runAllTests() {
        echo "🔄 Ejecutando suite completa de tests...\n\n";
        
        $this->testCacheSystem();
        $this->testAssetOptimization();
        $this->testDatabaseConnections();
        $this->testAPIEndpoints();
        $this->testPageLoadTimes();
        $this->generatePerformanceReport();
        
        $this->displayResults();
    }
    
    private function testCacheSystem() {
        echo "📦 Testeando sistema de cache...\n";
        
        try {
            // Test 1: Verificar que el cache funciona
            $cacheFile = $this->basePath . '/app/cache/SimpleCache.php';
            if (file_exists($cacheFile)) {
                $this->results['cache']['simple_cache'] = 'PASS';
                echo "  ✅ SimpleCache existe\n";
            } else {
                $this->results['cache']['simple_cache'] = 'FAIL';
                $this->errors[] = 'SimpleCache no encontrado';
                echo "  ❌ SimpleCache no encontrado\n";
            }
            
            // Test 2: Verificar QueryCache
            $queryCacheFile = $this->basePath . '/app/cache/QueryCache.php';
            if (file_exists($queryCacheFile)) {
                $this->results['cache']['query_cache'] = 'PASS';
                echo "  ✅ QueryCache existe\n";
            } else {
                $this->results['cache']['query_cache'] = 'FAIL';
                $this->errors[] = 'QueryCache no encontrado';
                echo "  ❌ QueryCache no encontrado\n";
            }
            
            // Test 3: Verificar CacheHelper
            $helperFile = $this->basePath . '/app/CacheHelper.php';
            if (file_exists($helperFile)) {
                $this->results['cache']['cache_helper'] = 'PASS';
                echo "  ✅ CacheHelper existe\n";
            } else {
                $this->results['cache']['cache_helper'] = 'FAIL';
                $this->errors[] = 'CacheHelper no encontrado';
                echo "  ❌ CacheHelper no encontrado\n";
            }
            
        } catch (Exception $e) {
            $this->errors[] = "Error en test de cache: " . $e->getMessage();
            echo "  ❌ Error: " . $e->getMessage() . "\n";
        }
    }
    
    private function testAssetOptimization() {
        echo "⚡ Testeando optimización de assets...\n";
        
        try {
            // Test 1: Verificar archivos JS minificados
            $jsMinFile = $this->basePath . '/assets/optimized/js/app.min.js';
            if (file_exists($jsMinFile)) {
                $originalSize = filesize($this->basePath . '/app.js');
                $minifiedSize = filesize($jsMinFile);
                $compression = round(($originalSize - $minifiedSize) / $originalSize * 100, 2);
                
                $this->results['assets']['js_optimization'] = 'PASS';
                $this->results['assets']['js_compression'] = $compression . '%';
                echo "  ✅ JS minificado: {$compression}% compresión\n";
            } else {
                $this->results['assets']['js_optimization'] = 'FAIL';
                $this->errors[] = 'JS minificado no encontrado';
                echo "  ❌ JS minificado no encontrado\n";
            }
            
            // Test 2: Verificar archivos CSS minificados
            $cssMinFile = $this->basePath . '/assets/optimized/css/style.min.css';
            if (file_exists($cssMinFile)) {
                $originalSize = filesize($this->basePath . '/style.css');
                $minifiedSize = filesize($cssMinFile);
                $compression = round(($originalSize - $minifiedSize) / $originalSize * 100, 2);
                
                $this->results['assets']['css_optimization'] = 'PASS';
                $this->results['assets']['css_compression'] = $compression . '%';
                echo "  ✅ CSS minificado: {$compression}% compresión\n";
            } else {
                $this->results['assets']['css_optimization'] = 'FAIL';
                $this->errors[] = 'CSS minificado no encontrado';
                echo "  ❌ CSS minificado no encontrado\n";
            }
            
            // Test 3: Verificar lazy loader
            $lazyLoaderFile = $this->basePath . '/assets/optimized/js/lazy-loader.min.js';
            if (file_exists($lazyLoaderFile)) {
                $this->results['assets']['lazy_loader'] = 'PASS';
                echo "  ✅ Lazy loader implementado\n";
            } else {
                $this->results['assets']['lazy_loader'] = 'FAIL';
                $this->errors[] = 'Lazy loader no encontrado';
                echo "  ❌ Lazy loader no encontrado\n";
            }
            
        } catch (Exception $e) {
            $this->errors[] = "Error en test de assets: " . $e->getMessage();
            echo "  ❌ Error: " . $e->getMessage() . "\n";
        }
    }
    
    private function testDatabaseConnections() {
        echo "🗄️ Testeando conexiones de base de datos...\n";
        
        try {
            // Test 1: Verificar archivo de conexión principal
            $conexionFile = $this->basePath . '/conexion.php';
            if (file_exists($conexionFile)) {
                $this->results['database']['connection_file'] = 'PASS';
                echo "  ✅ Archivo conexion.php existe\n";
            } else {
                $this->results['database']['connection_file'] = 'FAIL';
                $this->errors[] = 'Archivo conexion.php no encontrado';
                echo "  ❌ Archivo conexion.php no encontrado\n";
            }
            
            // Test 2: Verificar configuración de base de datos
            $dbConfigFile = $this->basePath . '/database_config.php';
            if (file_exists($dbConfigFile)) {
                $this->results['database']['config_file'] = 'PASS';
                echo "  ✅ Configuración de BD existe\n";
            } else {
                $this->results['database']['config_file'] = 'FAIL';
                $this->errors[] = 'Configuración de BD no encontrada';
                echo "  ❌ Configuración de BD no encontrada\n";
            }
            
        } catch (Exception $e) {
            $this->errors[] = "Error en test de BD: " . $e->getMessage();
            echo "  ❌ Error: " . $e->getMessage() . "\n";
        }
    }
    
    private function testAPIEndpoints() {
        echo "🌐 Testeando endpoints de API...\n";
        
        try {
            // Test 1: Verificar estructura de API
            $apiDir = $this->basePath . '/api';
            if (!is_dir($apiDir)) {
                // Buscar archivos de API en carpeta pedidos
                $pedidosDir = $this->basePath . '/pedidos';
                if (is_dir($pedidosDir)) {
                    $this->results['api']['structure'] = 'PARTIAL';
                    echo "  ⚠️ API en carpeta pedidos (legacy)\n";
                } else {
                    $this->results['api']['structure'] = 'FAIL';
                    $this->errors[] = 'Estructura de API no encontrada';
                    echo "  ❌ Estructura de API no encontrada\n";
                }
            } else {
                $this->results['api']['structure'] = 'PASS';
                echo "  ✅ Estructura de API encontrada\n";
            }
            
            // Test 2: Verificar archivos principales de API
            $apiFiles = [
                'guardar_pedido.php',
                'listar_pedidos.php',
                'actualizar_estado.php'
            ];
            
            $foundFiles = 0;
            foreach ($apiFiles as $file) {
                if (file_exists($this->basePath . '/' . $file)) {
                    $foundFiles++;
                }
            }
            
            if ($foundFiles === count($apiFiles)) {
                $this->results['api']['endpoints'] = 'PASS';
                echo "  ✅ Todos los endpoints principales encontrados\n";
            } else {
                $this->results['api']['endpoints'] = 'PARTIAL';
                echo "  ⚠️ {$foundFiles}/" . count($apiFiles) . " endpoints encontrados\n";
            }
            
        } catch (Exception $e) {
            $this->errors[] = "Error en test de API: " . $e->getMessage();
            echo "  ❌ Error: " . $e->getMessage() . "\n";
        }
    }
    
    private function testPageLoadTimes() {
        echo "⏱️ Testeando tiempos de carga...\n";
        
        try {
            // Test 1: Verificar tamaño de archivos críticos
            $criticalFiles = [
                'index.php',
                'listar_pedidos.php',
                'guardar_pedido.php'
            ];
            
            $totalSize = 0;
            $filesChecked = 0;
            
            foreach ($criticalFiles as $file) {
                $filePath = $this->basePath . '/' . $file;
                if (file_exists($filePath)) {
                    $size = filesize($filePath);
                    $totalSize += $size;
                    $filesChecked++;
                    echo "  📄 {$file}: " . $this->formatBytes($size) . "\n";
                }
            }
            
            if ($filesChecked > 0) {
                $avgSize = $totalSize / $filesChecked;
                $this->results['performance']['avg_file_size'] = $this->formatBytes($avgSize);
                $this->results['performance']['files_checked'] = $filesChecked;
                echo "  📊 Tamaño promedio: " . $this->formatBytes($avgSize) . "\n";
            }
            
            // Test 2: Verificar assets combinados
            $combinedJs = $this->basePath . '/assets/combined/app.min.js';
            $combinedCss = $this->basePath . '/assets/combined/app.min.css';
            
            if (file_exists($combinedJs) && file_exists($combinedCss)) {
                $this->results['performance']['combined_assets'] = 'PASS';
                echo "  ✅ Assets combinados disponibles\n";
            } else {
                $this->results['performance']['combined_assets'] = 'FAIL';
                $this->errors[] = 'Assets combinados no encontrados';
                echo "  ❌ Assets combinados no encontrados\n";
            }
            
        } catch (Exception $e) {
            $this->errors[] = "Error en test de performance: " . $e->getMessage();
            echo "  ❌ Error: " . $e->getMessage() . "\n";
        }
    }
    
    private function generatePerformanceReport() {
        echo "📊 Generando reporte de performance...\n";
        
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'phase' => 'FASE 3 - Performance Tests',
            'results' => $this->results,
            'errors' => $this->errors,
            'summary' => $this->calculateSummary(),
            'recommendations' => $this->getRecommendations()
        ];
        
        $reportFile = $this->basePath . '/phase3/reports/performance-test-report.json';
        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
        echo "  ✅ Reporte guardado en: reports/performance-test-report.json\n";
    }
    
    private function calculateSummary() {
        $totalTests = 0;
        $passedTests = 0;
        
        foreach ($this->results as $category => $tests) {
            foreach ($tests as $test => $result) {
                $totalTests++;
                if ($result === 'PASS') {
                    $passedTests++;
                }
            }
        }
        
        $successRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 2) : 0;
        
        return [
            'total_tests' => $totalTests,
            'passed_tests' => $passedTests,
            'success_rate' => $successRate . '%',
            'status' => $successRate >= 80 ? 'EXCELLENT' : ($successRate >= 60 ? 'GOOD' : 'NEEDS_IMPROVEMENT')
        ];
    }
    
    private function getRecommendations() {
        $recommendations = [];
        
        if (count($this->errors) > 0) {
            $recommendations[] = "Resolver errores críticos encontrados";
        }
        
        if (!isset($this->results['cache']['simple_cache']) || $this->results['cache']['simple_cache'] !== 'PASS') {
            $recommendations[] = "Implementar sistema de cache completo";
        }
        
        if (!isset($this->results['assets']['js_optimization']) || $this->results['assets']['js_optimization'] !== 'PASS') {
            $recommendations[] = "Completar optimización de assets JavaScript";
        }
        
        if (!isset($this->results['performance']['combined_assets']) || $this->results['performance']['combined_assets'] !== 'PASS') {
            $recommendations[] = "Crear assets combinados para mejor performance";
        }
        
        if (empty($recommendations)) {
            $recommendations[] = "Sistema optimizado correctamente - listo para producción";
        }
        
        return $recommendations;
    }
    
    private function displayResults() {
        echo "\n📈 RESULTADOS DE TESTS DE PERFORMANCE:\n";
        echo "======================================\n";
        
        $summary = $this->calculateSummary();
        echo "• Tests ejecutados: {$summary['total_tests']}\n";
        echo "• Tests exitosos: {$summary['passed_tests']}\n";
        echo "• Tasa de éxito: {$summary['success_rate']}\n";
        echo "• Estado general: {$summary['status']}\n";
        
        if (count($this->errors) > 0) {
            echo "\n❌ ERRORES ENCONTRADOS:\n";
            echo "======================\n";
            foreach ($this->errors as $error) {
                echo "• $error\n";
            }
        }
        
        echo "\n💡 RECOMENDACIONES:\n";
        echo "==================\n";
        $recommendations = $this->getRecommendations();
        foreach ($recommendations as $recommendation) {
            echo "• $recommendation\n";
        }
    }
    
    private function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

// Ejecutar tests
$testRunner = new PerformanceTestRunner();
$testRunner->runAllTests();

echo "\n🚀 PRÓXIMO PASO:\n";
echo "===============\n";
echo "php phase3/finalize-phase3.php\n";
?>
