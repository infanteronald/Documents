<?php
/**
 * Test Runner Principal - FASE 3 Sequoia Speed
 */

require_once "tests/SimpleTest.php";
require_once "tests/ApiTest.php";
require_once "tests/PerformanceTest.php";

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
        
        // Crear directorio si no existe
        if (!is_dir("reports/testing")) {
            mkdir("reports/testing", 0755, true);
        }
        
        file_put_contents("reports/testing/consolidated-test-report.json", json_encode($consolidatedReport, JSON_PRETTY_PRINT));
        
        echo "\n📊 RESUMEN CONSOLIDADO:\n";
        echo "======================\n";
        foreach ($this->results as $testType => $result) {
            if (isset($result["test_results"])) {
                $res = $result["test_results"];
                echo "✓ $testType: {$res["passed"]}/{$res["total"]} tests passed ({$res["success_rate"]}%)\n";
            } elseif (isset($result["total"])) {
                echo "✓ $testType: {$result["passed"]}/{$result["total"]} tests passed ({$result["success_rate"]}%)\n";
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
            } elseif (isset($result["total"])) {
                $totalTests += $result["total"];
                $totalPassed += $result["passed"];
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
}
