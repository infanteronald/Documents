<?php
/**
 * Script para ejecutar todas las pruebas del sistema de pedidos
 * Uso: php tests/run_tests.php [tipo]
 * 
 * Tipos disponibles:
 * - unit: Ejecuta solo pruebas unitarias
 * - integration: Ejecuta solo pruebas de integración
 * - functional: Ejecuta solo pruebas funcionales
 * - all: Ejecuta todas las pruebas (por defecto)
 */

class TestRunner {
    private $baseDir;
    private $results = [];
    
    public function __construct() {
        $this->baseDir = dirname(__FILE__);
    }
    
    public function run($type = 'all') {
        echo "🧪 Ejecutando pruebas del Sistema de Pedidos\n";
        echo "============================================\n\n";
        
        switch($type) {
            case 'unit':
                $this->runUnitTests();
                break;
            case 'integration':
                $this->runIntegrationTests();
                break;
            case 'functional':
                $this->runFunctionalTests();
                break;
            case 'all':
            default:
                $this->runUnitTests();
                $this->runIntegrationTests();
                $this->runFunctionalTests();
                break;
        }
        
        $this->printSummary();
    }
    
    private function runUnitTests() {
        echo "📋 Ejecutando Pruebas Unitarias...\n";
        $this->runTestsInDirectory('unit');
    }
    
    private function runIntegrationTests() {
        echo "\n🔗 Ejecutando Pruebas de Integración...\n";
        $this->runTestsInDirectory('integration');
    }
    
    private function runFunctionalTests() {
        echo "\n🎯 Ejecutando Pruebas Funcionales...\n";
        $this->runTestsInDirectory('functional');
    }
    
    private function runTestsInDirectory($type) {
        $dir = $this->baseDir . '/' . $type;
        
        if (!is_dir($dir)) {
            echo "❌ Directorio $type no encontrado\n";
            return;
        }
        
        $files = glob($dir . '/*.php');
        $htmlFiles = glob($dir . '/*.html');
        
        // Ejecutar archivos PHP
        foreach ($files as $file) {
            $this->runPhpTest($file, $type);
        }
        
        // Listar archivos HTML (requieren ejecución manual)
        foreach ($htmlFiles as $file) {
            $this->listHtmlTest($file, $type);
        }
    }
    
    private function runPhpTest($file, $type) {
        $filename = basename($file);
        echo "  ▶️  $filename... ";
        
        // Ejecutar el archivo PHP y capturar la salida
        ob_start();
        $error = '';
        
        try {
            include $file;
            $output = ob_get_clean();
            echo "✅ Completado\n";
            $this->results[$type]['passed'][] = $filename;
        } catch (Exception $e) {
            ob_end_clean();
            echo "❌ Error: " . $e->getMessage() . "\n";
            $this->results[$type]['failed'][] = $filename;
        }
    }
    
    private function listHtmlTest($file, $type) {
        $filename = basename($file);
        echo "  📄 $filename (HTML - requiere ejecución manual)\n";
        $this->results[$type]['manual'][] = $filename;
    }
    
    private function printSummary() {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "📊 RESUMEN DE PRUEBAS\n";
        echo str_repeat("=", 50) . "\n";
        
        $totalPassed = 0;
        $totalFailed = 0;
        $totalManual = 0;
        
        foreach (['unit', 'integration', 'functional'] as $type) {
            if (isset($this->results[$type])) {
                $passed = count($this->results[$type]['passed'] ?? []);
                $failed = count($this->results[$type]['failed'] ?? []);
                $manual = count($this->results[$type]['manual'] ?? []);
                
                echo "\n" . ucfirst($type) . ":\n";
                echo "  ✅ Exitosas: $passed\n";
                echo "  ❌ Fallidas: $failed\n";
                echo "  📄 Manuales: $manual\n";
                
                $totalPassed += $passed;
                $totalFailed += $failed;
                $totalManual += $manual;
            }
        }
        
        echo "\n" . str_repeat("-", 30) . "\n";
        echo "TOTAL:\n";
        echo "  ✅ Exitosas: $totalPassed\n";
        echo "  ❌ Fallidas: $totalFailed\n";
        echo "  📄 Manuales: $totalManual\n";
        
        if ($totalFailed > 0) {
            echo "\n⚠️  Hay pruebas que requieren atención\n";
        } else {
            echo "\n🎉 Todas las pruebas automatizadas pasaron correctamente\n";
        }
    }
}

// Manejo de argumentos de línea de comandos
function showHelp() {
    echo "🧪 Test Runner - Sistema de Pedidos\n";
    echo "=====================================\n\n";
    echo "Uso: php run_tests.php [opciones] [tipo]\n\n";
    echo "Tipos de prueba:\n";
    echo "  unit         - Ejecutar solo pruebas unitarias\n";
    echo "  integration  - Ejecutar solo pruebas de integración\n";
    echo "  functional   - Ejecutar solo pruebas funcionales\n";
    echo "  all          - Ejecutar todas las pruebas (por defecto)\n\n";
    echo "Opciones:\n";
    echo "  --help, -h   - Mostrar esta ayuda\n";
    echo "  --setup      - Configurar entorno de pruebas\n";
    echo "  --clean      - Limpiar datos de prueba\n\n";
    echo "Ejemplos:\n";
    echo "  php run_tests.php unit\n";
    echo "  php run_tests.php --setup\n";
    echo "  php run_tests.php --clean\n";
}

// Procesar argumentos
$args = array_slice($argv, 1);

if (in_array('--help', $args) || in_array('-h', $args)) {
    showHelp();
    exit(0);
}

if (in_array('--setup', $args)) {
    echo "🔧 Configurando entorno de pruebas...\n";
    require_once 'config_test.php';
    setupTestEnvironment();
    echo "✅ Entorno de pruebas configurado correctamente\n";
    exit(0);
}

if (in_array('--clean', $args)) {
    echo "🧹 Limpiando datos de prueba...\n";
    require_once 'config_test.php';
    cleanTestData();
    echo "✅ Datos de prueba limpiados\n";
    exit(0);
}

// Obtener tipo de prueba
$type = 'all';
foreach ($args as $arg) {
    if (in_array($arg, ['unit', 'integration', 'functional', 'all'])) {
        $type = $arg;
        break;
    }
}

// Ejecutar pruebas
$runner = new TestRunner();
$runner->run($type);
