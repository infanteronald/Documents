<?php
/**
 * Test básico para APIs de Sequoia Speed
 */

use PHPUnit\Framework\TestCase;

class SequoiaAPITest extends TestCase {
    
    public function testIndexPageLoads() {
        $this->assertTrue(file_exists("index.php"));
        $content = file_get_contents("index.php");
        $this->assertStringContainsString("<?php", $content);
    }
    
    public function testMigrationHelperExists() {
        $this->assertTrue(file_exists("migration-helper.php"));
    }
    
    public function testAPIEndpointsExist() {
        $apis = [
            "public/api/pedidos/create.php",
            "public/api/productos/by-category.php",
            "public/api/bold/webhook.php"
        ];
        
        foreach ($apis as $api) {
            $this->assertTrue(file_exists($api), "API $api no encontrada");
        }
    }
    
    public function testJavaScriptAssetsExist() {
        $assets = [
            "public/assets/js/bold-integration.js",
            "public/assets/js/legacy-compatibility.js"
        ];
        
        foreach ($assets as $asset) {
            $this->assertTrue(file_exists($asset), "Asset $asset no encontrado");
        }
    }
}
?>