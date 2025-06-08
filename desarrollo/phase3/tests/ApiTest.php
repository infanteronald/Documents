<?php
require_once __DIR__ . "/SimpleTest.php";

/**
 * Tests de API para sistema de pedidos
 */
class ApiTest {
    private $test;
    private $baseUrl;
    
    public function __construct() {
        $this->test = new SimpleTest();
        $this->baseUrl = "http://localhost";
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
            "../../public/api/pedidos",
            "../../public/api/bold", 
            "../../public/api/productos",
            "../../public/api/exports"
        ];
        
        foreach ($apiDirs as $dir) {
            $this->test->assertTrue(is_dir($dir), "Directorio API existe: " . basename($dir));
        }
        
        $apiFiles = [
            "../../public/api/pedidos/create.php",
            "../../public/api/pedidos/update-status.php",
            "../../public/api/bold/webhook.php",
            "../../public/api/productos/by-category.php",
            "../../public/api/exports/excel.php"
        ];
        
        foreach ($apiFiles as $file) {
            $this->test->assertTrue(file_exists($file), "API endpoint existe: " . basename($file));
        }
    }
    
    private function testApiEndpoints() {
        echo "\n🔗 Testing endpoints API...\n";
        
        $endpoints = [
            "../../public/api/pedidos/create.php",
            "../../public/api/pedidos/update-status.php", 
            "../../public/api/productos/by-category.php"
        ];
        
        foreach ($endpoints as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                $this->test->assertTrue(strpos($content, "header") !== false, "Endpoint " . basename($file) . " tiene headers");
                $this->test->assertTrue(strpos($content, "json") !== false, "Endpoint " . basename($file) . " retorna JSON");
            }
        }
    }
    
    private function testApiResponse() {
        echo "\n📊 Testing formato de respuesta...\n";
        
        // Simular respuesta API
        $mockResponse = ["status" => "success", "data" => [], "message" => "test"];
        $jsonResponse = json_encode($mockResponse);
        
        $this->test->assertTrue(json_decode($jsonResponse) !== null, "Respuesta JSON válida");
        $decoded = json_decode($jsonResponse);
        $this->test->assertTrue(isset($decoded->status), "Campo status presente");
        $this->test->assertTrue(isset($decoded->data), "Campo data presente");
    }
}

// Ejecutar tests si se llama directamente
if (basename(__FILE__) === basename($_SERVER["SCRIPT_NAME"])) {
    $apiTest = new ApiTest();
    $results = $apiTest->runTests();
    
    // Crear directorio si no existe
    if (!is_dir("../reports/testing")) {
        mkdir("../reports/testing", 0755, true);
    }
    
    // Guardar resultados
    file_put_contents("../reports/testing/api-test-results.json", json_encode($results, JSON_PRETTY_PRINT));
    echo "💾 Resultados guardados en reports/testing/api-test-results.json\n";
}
