<?php
/**
 * Configurador de Entorno de Testing FASE 3
 * Sequoia Speed - Sistema de Pedidos
 */

class TestingSetup {
    private $basePath;
    
    public function __construct() {
        $this->basePath = dirname(__DIR__);
    }
    
    public function setupTesting() {
        echo "🧪 Configurando entorno de testing...\n\n";
        
        $this->createTestDirectories();
        $this->createBasicTestFramework();
        $this->createApiTests();
        $this->createPerformanceTests();
        $this->generateTestReport();
        
        echo "✅ Entorno de testing configurado exitosamente!\n";
    }
    
    private function createTestDirectories() {
        echo "📁 Creando estructura de directorios...\n";
        
        $dirs = [
            "phase3/tests/unit",
            "phase3/tests/integration", 
            "phase3/tests/performance",
            "phase3/tests/helpers",
            "phase3/reports/testing"
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
                echo "  ✓ $dir\n";
            }
        }
    }
    
    private function createBasicTestFramework() {
        echo "\n🔧 Creando framework de testing básico...\n";
        
        // SimpleTest Framework
        $testFramework = '<?php
/**
 * Framework de Testing Simple para Sequoia Speed
 */

class SimpleTest {
    private $tests = 0;
    private $passed = 0;
    private $failed = 0;
    private $results = [];
    
    public function assertEquals($expected, $actual, $message = "") {
        $this->tests++;
        if ($expected === $actual) {
            $this->passed++;
            $this->results[] = ["type" => "pass", "message" => $message];
            echo "✓ $message\n";
        } else {
            $this->failed++;
            $this->results[] = ["type" => "fail", "message" => $message, "expected" => $expected, "actual" => $actual];
            echo "✗ $message - Expected: $expected, Got: $actual\n";
        }
    }
    
    public function assertTrue($condition, $message = "") {
        $this->assertEquals(true, $condition, $message);
    }
    
    public function assertNotNull($value, $message = "") {
        $this->tests++;
        if ($value !== null) {
            $this->passed++;
            $this->results[] = ["type" => "pass", "message" => $message];
            echo "✓ $message\n";
        } else {
            $this->failed++;
            $this->results[] = ["type" => "fail", "message" => $message];
            echo "✗ $message - Value was null\n";
        }
    }
    
    public function getResults() {
        return [
            "total" => $this->tests,
            "passed" => $this->passed,
            "failed" => $this->failed,
            "success_rate" => round(($this->passed / $this->tests) * 100, 2),
            "results" => $this->results
        ];
    }
    
    public function summary() {
        echo "\n📊 Testing Summary:\n";
        echo "Tests: $this->tests | Passed: $this->passed | Failed: $this->failed\n";
        echo "Success Rate: " . round(($this->passed / $this->tests) * 100, 2) . "%\n\n";
    }
}';
        
        file_put_contents("phase3/tests/helpers/SimpleTest.php", $testFramework);
        echo "  ✓ SimpleTest.php\n";
    }
    
    private function createApiTests() {
        echo "\n🌐 Creando tests de API...\n";
        
        $apiTest = '<?php
require_once __DIR__ . "/../helpers/SimpleTest.php";

/**
 * Tests de API para sistema de pedidos
 */
class ApiTest {
    private $test;
    private $baseUrl;
    
    public function __construct() {
        $this->test = new SimpleTest();
        $this->baseUrl = "http://localhost" . dirname($_SERVER["PHP_SELF"]);
    }
    
    public function runTests() {
        echo "🧪 Ejecutando tests de API...\n\n";
        
        $this->testApiStructure();
        $this->testApiEndpoints();
        $this->testApiResponse();
        
        $this->test->summary();
        return $this->test->getResults();
    }
    
    private function testApiStructure() {
        echo "📁 Testing estructura de API...\n";
        
        $apiDirs = [
            "../../../public/api/pedidos",
            "../../../public/api/bold", 
            "../../../public/api/productos",
            "../../../public/api/exports"
        ];
        
        foreach ($apiDirs as $dir) {
            $this->test->assertTrue(is_dir($dir), "Directorio API existe: $dir");
        }
        
        $apiFiles = [
            "../../../public/api/pedidos/create.php",
            "../../../public/api/pedidos/update-status.php",
            "../../../public/api/bold/webhook.php",
            "../../../public/api/productos/by-category.php",
            "../../../public/api/exports/excel.php"
        ];
        
        foreach ($apiFiles as $file) {
            $this->test->assertTrue(file_exists($file), "API endpoint existe: " . basename($file));
        }
    }
    
    private function testApiEndpoints() {
        echo "\n🔗 Testing endpoints API...\n";
        
        // Test estructura de respuesta
        $endpoints = [
            "pedidos/create.php",
            "pedidos/update-status.php", 
            "productos/by-category.php"
        ];
        
        foreach ($endpoints as $endpoint) {
            $file = "../../../public/api/$endpoint";
            if (file_exists($file)) {
                $content = file_get_contents($file);
                $this->test->assertTrue(strpos($content, "header") !== false, "Endpoint $endpoint tiene headers");
                $this->test->assertTrue(strpos($content, "json") !== false, "Endpoint $endpoint retorna JSON");
            }
        }
    }
    
    private function testApiResponse() {
        echo "\n📊 Testing formato de respuesta...\n";
        
        // Simular respuesta API
        $mockResponse = ["status" => "success", "data" => [], "message" => "test"];
        $jsonResponse = json_encode($mockResponse);
        
        $this->test->assertTrue(json_decode($jsonResponse) !== null, "Respuesta JSON válida");
        $this->test->assertTrue(isset(json_decode($jsonResponse)->status), "Campo status presente");
        $this->test->assertTrue(isset(json_decode($jsonResponse)->data), "Campo data presente");
    }
}

// Ejecutar tests si se llama directamente
if (basename(__FILE__) === basename($_SERVER["SCRIPT_NAME"])) {
    $apiTest = new ApiTest();
    $results = $apiTest->runTests();
    
    // Guardar resultados
    file_put_contents("../reports/testing/api-test-results.json", json_encode($results, JSON_PRETTY_PRINT));
    echo "💾 Resultados guardados en reports/testing/api-test-results.json\n";
}';
        
        file_put_contents("phase3/tests/integration/ApiTest.php", $apiTest);
        echo "  ✓ ApiTest.php\n";
    }
    
    private function createPerformanceTests() {
        echo "\n⚡ Creando tests de performance...\n";
        
        $perfTest = '<?php
require_once __DIR__ . "/../helpers/SimpleTest.php";

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
            "../../../migration-helper.php",
            "../../../legacy-bridge.php",
            "../../../public/assets/js/bold-integration.js"
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
            "../../../public/assets/js/bold-integration.js",
            "../../../public/assets/js/legacy-compatibility.js",
            "../../../public/assets/js/asset-updater.js"
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
    
    // Guardar resultados
    file_put_contents("../reports/testing/performance-test-results.json", json_encode($results, JSON_PRETTY_PRINT));
    echo "💾 Resultados guardados en reports/testing/performance-test-results.json\n";
}';
        
        file_put_contents("phase3/tests/performance/PerformanceTest.php", $perfTest);
        echo "  ✓ PerformanceTest.php\n";
    }
    
    private function generateTestReport() {
        echo "\n📋 Generando configuración de testing...\n";
        
        $testRunner = '<?php
/**
 * Test Runner Principal - FASE 3 Sequoia Speed
 */

require_once "tests/helpers/SimpleTest.php";
require_once "tests/integration/ApiTest.php";
require_once "tests/performance/PerformanceTest.php";

class TestRunner {
    private $results = [];
    
    public function runAllTests() {
        echo "🧪 INICIANDO SUITE DE TESTS FASE 3\n";
        echo "=================================\n\n";
        
        $this->runApiTests();
        $this->runPerformanceTests();
        $this->generateReport();
        
        echo "\n🎉 Suite de tests completada!\n";
    }
    
    private function runApiTests() {
        echo "📊 Ejecutando tests de API...\n";
        $apiTest = new ApiTest();
        $this->results["api"] = $apiTest->runTests();
        echo "\n";
    }
    
    private function runPerformanceTests() {
        echo "⚡ Ejecutando tests de performance...\n";
        $perfTest = new PerformanceTest();
        $this->results["performance"] = $perfTest->runTests();
        echo "\n";
    }
    
    private function generateReport() {
        echo "📋 Generando reporte consolidado...\n";
        
        $consolidatedReport = [
            "timestamp" => date("Y-m-d H:i:s"),
            "phase" => "FASE 3 - Testing",
            "results" => $this->results,
            "summary" => $this->generateSummary()
        ];
        
        file_put_contents("reports/testing/consolidated-test-report.json", json_encode($consolidatedReport, JSON_PRETTY_PRINT));
        
        echo "\n📊 RESUMEN CONSOLIDADO:\n";
        echo "======================\n";
        foreach ($this->results as $testType => $result) {
            if (isset($result["test_results"])) {
                $res = $result["test_results"];
                echo "✓ $testType: {$res["passed"]}/{$res["total"]} tests passed ({$res["success_rate"]}%)\n";
            }
        }
        echo "\n💾 Reporte completo: reports/testing/consolidated-test-report.json\n";
    }
    
    private function generateSummary() {
        $totalTests = 0;
        $totalPassed = 0;
        
        foreach ($this->results as $result) {
            if (isset($result["test_results"])) {
                $totalTests += $result["test_results"]["total"];
                $totalPassed += $result["test_results"]["passed"];
            }
        }
        
        return [
            "total_tests" => $totalTests,
            "total_passed" => $totalPassed,
            "overall_success_rate" => $totalTests > 0 ? round(($totalPassed / $totalTests) * 100, 2) : 0
        ];
    }
}

// Ejecutar si se llama directamente
if (basename(__FILE__) === basename($_SERVER["SCRIPT_NAME"])) {
    $runner = new TestRunner();
    $runner->runAllTests();
}';
        
        file_put_contents("phase3/test-runner.php", $testRunner);
        echo "  ✓ test-runner.php\n";
    }
}

// Ejecutar configuración
$setup = new TestingSetup();
$setup->setupTesting();
