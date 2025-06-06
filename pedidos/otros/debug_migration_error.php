<?php
/**
 * Debug específico para el error "The string did not match the expected pattern"
 * Este script identifica exactamente dónde ocurre el error
 */

header('Content-Type: text/html; charset=UTF-8');
echo "<h1>🔍 Debug: Migration Pattern Error</h1>";

// Configurar error reporting
ini_set('log_errors', 1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>1. 📋 Información del Sistema</h2>";
echo "<pre>";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
echo "SAPI: " . php_sapi_name() . "\n";
echo "Error Reporting: " . error_reporting() . "\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n";
echo "Max Execution Time: " . ini_get('max_execution_time') . "\n";
echo "</pre>";

echo "<h2>2. 🔤 Testing String Functions</h2>";
$testStrings = [
    "Simple string",
    "String with áéíóú special chars",
    "String with emojis 🚀 📊 ✅",
    "JSON: {\"test\": \"value\"}",
    "SQL: CREATE TABLE test (id INT)",
    "Path: /path/to/file.php"
];

foreach ($testStrings as $i => $str) {
    echo "<h3>Test String $i:</h3>";
    echo "<pre>";
    echo "Original: " . htmlspecialchars($str) . "\n";
    echo "Length: " . strlen($str) . "\n";
    echo "MB Length: " . mb_strlen($str) . "\n";
    echo "Encoding: " . mb_detect_encoding($str) . "\n";
    echo "JSON Encode: " . json_encode($str) . "\n";
    echo "JSON Error: " . json_last_error_msg() . "\n";
    echo "</pre>";
}

echo "<h2>3. 🗄️ Database Connection Test</h2>";
try {
    require_once "conexion.php";
    echo "<p>✅ Connection successful</p>";
    
    // Test charset
    $result = $conn->query("SELECT @@character_set_connection, @@collation_connection");
    if ($result) {
        $charset = $result->fetch_assoc();
        echo "<p>Database charset: " . json_encode($charset) . "</p>";
    }
    
    // Test simple query
    $result = $conn->query("SELECT 1 as test");
    if ($result) {
        echo "<p>✅ Simple query works</p>";
    } else {
        echo "<p>❌ Simple query failed: " . $conn->error . "</p>";
    }
    
    // Test table existence
    $result = $conn->query("SHOW TABLES LIKE 'pedidos_detal'");
    if ($result && $result->num_rows > 0) {
        echo "<p>✅ Table pedidos_detal exists</p>";
    } else {
        echo "<p>❌ Table pedidos_detal not found</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Connection failed: " . $e->getMessage() . "</p>";
}

echo "<h2>4. 📁 File System Test</h2>";
$testDir = __DIR__ . '/test_debug';
$testFile = $testDir . '/test.txt';

echo "<pre>";
try {
    // Test directory creation
    if (!is_dir($testDir)) {
        if (mkdir($testDir, 0755, true)) {
            echo "✅ Directory created: $testDir\n";
        } else {
            echo "❌ Failed to create directory: $testDir\n";
        }
    } else {
        echo "✅ Directory exists: $testDir\n";
    }
    
    // Test file creation
    $content = "Test file with special chars: áéíóú ñüç 🚀\nJSON: {\"test\": \"value\"}\n";
    if (file_put_contents($testFile, $content) !== false) {
        echo "✅ File created: $testFile\n";
        echo "Content length: " . strlen($content) . "\n";
        
        // Test file reading
        $readContent = file_get_contents($testFile);
        if ($readContent === $content) {
            echo "✅ File read correctly\n";
        } else {
            echo "❌ File content mismatch\n";
            echo "Expected: " . bin2hex($content) . "\n";
            echo "Got: " . bin2hex($readContent) . "\n";
        }
        
        // Cleanup
        unlink($testFile);
        rmdir($testDir);
        echo "✅ Cleanup completed\n";
    } else {
        echo "❌ Failed to create file: $testFile\n";
    }
    
} catch (Exception $e) {
    echo "❌ File system error: " . $e->getMessage() . "\n";
}
echo "</pre>";

echo "<h2>5. 🧪 JSON Test</h2>";
echo "<pre>";
$testData = [
    'action' => 'migrate',
    'special_chars' => 'áéíóú ñüç',
    'emoji' => '🚀📊✅',
    'nested' => [
        'key1' => 'value1',
        'key2' => 'value2'
    ]
];

echo "Original data: " . print_r($testData, true) . "\n";

$json = json_encode($testData);
echo "JSON encoded: " . $json . "\n";
echo "JSON error: " . json_last_error_msg() . "\n";

$decoded = json_decode($json, true);
echo "JSON decoded: " . print_r($decoded, true) . "\n";
echo "JSON error after decode: " . json_last_error_msg() . "\n";

if ($testData === $decoded) {
    echo "✅ JSON round-trip successful\n";
} else {
    echo "❌ JSON round-trip failed\n";
}
echo "</pre>";

echo "<h2>6. 🔧 Pattern Testing</h2>";
echo "<pre>";
// Test common regex patterns that might cause issues
$patterns = [
    '/^[a-zA-Z0-9_]+$/',
    '/[\w\s\-\.]+/',
    '/[áéíóúñüç]/u',
    '/\{.*\}/',
    '/CREATE\s+TABLE/i'
];

$testString = "test_string_with_chars áéíóú";

foreach ($patterns as $i => $pattern) {
    echo "Pattern $i: $pattern\n";
    try {
        $result = preg_match($pattern, $testString);
        echo "Result: " . ($result ? 'MATCH' : 'NO MATCH') . "\n";
        
        if (preg_last_error() !== PREG_NO_ERROR) {
            echo "PREG Error: " . preg_last_error() . "\n";
        }
    } catch (Exception $e) {
        echo "Exception: " . $e->getMessage() . "\n";
    }
    echo "\n";
}
echo "</pre>";

echo "<h2>7. 🚀 Migration Test Simulation</h2>";
echo "<pre>";
try {
    // Simulate the AJAX call that's failing
    $simulatedInput = json_encode(['action' => 'migrate']);
    echo "Simulated input: $simulatedInput\n";
    
    $decoded = json_decode($simulatedInput, true);
    echo "Decoded: " . print_r($decoded, true) . "\n";
    echo "Action: " . ($decoded['action'] ?? 'undefined') . "\n";
    
    if (($decoded['action'] ?? '') === 'migrate') {
        echo "✅ Action detection works\n";
    } else {
        echo "❌ Action detection failed\n";
    }
    
} catch (Exception $e) {
    echo "❌ Simulation error: " . $e->getMessage() . "\n";
}
echo "</pre>";

echo "<h2>✅ Debug Completed</h2>";
echo "<p>Si ves este mensaje, el servidor puede ejecutar PHP básico correctamente.</p>";
echo "<p>Revisa cada sección para identificar dónde puede estar el problema.</p>";
?>
