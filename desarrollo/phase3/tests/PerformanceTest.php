<?php
require_once __DIR__ . "/SimpleTest.php";

/**
 * Tests de Performance para Sequoia Speed
 */
class PerformanceTest {
    private $test;
    private $metrics = [];
    
    public function __construct() {
        $this->test = new SimpleTest();
    }
    
    public function runTests() {
        echo "⚡ Ejecutando tests de performance...\n\n";
        
        $this->testFileLoadTime();
        $this->testMemoryUsage();
        $this->testAssetSizes();
        
        $this->test->summary();
        return [
            "test_results" => $this->test->getResults(),
            "performance_metrics" => $this->metrics
        ];
    }
    
    private function testFileLoadTime() {
        echo "⏱️ Testing tiempo de carga...\n";
        
        $files = [
            "../../migration-helper.php",
            "../../legacy-bridge.php",
            "../../public/assets/js/bold-integration.js"
        ];
        
        foreach ($files as $file) {
            if (file_exists($file)) {
                $startTime = microtime(true);
                $content = file_get_contents($file);
                $loadTime = microtime(true) - $startTime;
                
                $this->metrics["load_time_" . basename($file)] = $loadTime;
                $this->test->assertTrue($loadTime < 0.1, "Carga rápida: " . basename($file) . " (" . round($loadTime * 1000, 2) . "ms)");
            }
        }
    }
    
    private function testMemoryUsage() {
        echo "\n💾 Testing uso de memoria...\n";
        
        $startMemory = memory_get_usage();
        
        // Simular carga de sistema
        $data = [];
        for ($i = 0; $i < 1000; $i++) {
            $data[] = "test_data_" . $i;
        }
        
        $endMemory = memory_get_usage();
        $memoryUsed = $endMemory - $startMemory;
        
        $this->metrics["memory_usage"] = $memoryUsed;
        $this->test->assertTrue($memoryUsed < 1024 * 1024, "Uso memoria eficiente: " . round($memoryUsed/1024, 2) . " KB");
        
        unset($data); // Limpiar memoria
    }
    
    private function testAssetSizes() {
        echo "\n📦 Testing tamaño de assets...\n";
        
        $assets = [
            "../../public/assets/js/bold-integration.js",
            "../../public/assets/js/legacy-compatibility.js",
            "../../public/assets/js/asset-updater.js"
        ];
        
        $totalSize = 0;
        foreach ($assets as $asset) {
            if (file_exists($asset)) {
                $size = filesize($asset);
                $totalSize += $size;
                $this->metrics["asset_size_" . basename($asset)] = $size;
                $this->test->assertTrue($size < 50 * 1024, "Asset size OK: " . basename($asset) . " (" . round($size/1024, 2) . " KB)");
            }
        }
        
        $this->metrics["total_asset_size"] = $totalSize;
        $this->test->assertTrue($totalSize < 200 * 1024, "Total assets size OK: " . round($totalSize/1024, 2) . " KB");
    }
}

// Ejecutar tests si se llama directamente
if (basename(__FILE__) === basename($_SERVER["SCRIPT_NAME"])) {
    $perfTest = new PerformanceTest();
    $results = $perfTest->runTests();
    
    // Crear directorio si no existe
    if (!is_dir("../reports/testing")) {
        mkdir("../reports/testing", 0755, true);
    }
    
    // Guardar resultados
    file_put_contents("../reports/testing/performance-test-results.json", json_encode($results, JSON_PRETTY_PRINT));
    echo "💾 Resultados guardados en reports/testing/performance-test-results.json\n";
}
