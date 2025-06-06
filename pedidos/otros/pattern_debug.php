<?php
/**
 * Diagnóstico específico para "string did not match expected pattern"
 * Identifica problemas de encoding, regex y validaciones
 */

// Headers y configuración
header('Content-Type: text/html; charset=UTF-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>🔍 Diagnóstico: String Pattern Error</h1>";
echo "<style>
body { font-family: -apple-system, sans-serif; background: #1e1e1e; color: #fff; padding: 20px; }
h1, h2, h3 { color: #4fc3f7; }
.test-box { background: #2d2d30; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #4fc3f7; }
.error { color: #ff6b6b; }
.success { color: #51cf66; }
.warning { color: #ffd43b; }
pre { background: #1a1a1a; padding: 10px; border-radius: 4px; overflow-x: auto; }
</style>";

// Test 1: Verificar si el error está en la función preg_match o similar
echo "<div class='test-box'>";
echo "<h2>1. 🔤 Test de Patrones Regex</h2>";

$testStrings = [
    "simple_string",
    "string_with_numbers_123",
    "string-with-dashes",
    "string.with.dots",
    "string/with/slashes",
    "string with spaces",
    "string_with_áéíóú",
    "string_with_emojis_🚀",
    "{'json': 'string'}",
    "CREATE TABLE test",
    "migration_gradual.php"
];

$patterns = [
    '/^[\w\-\.]+$/' => 'Alphanumeric with dash/dot',
    '/^[a-zA-Z0-9_]+$/' => 'Simple alphanumeric',
    '/[\w\s\-\.]+/' => 'Word chars with spaces',
    '/^[\x20-\x7E]+$/' => 'ASCII printable',
    '/^.+$/' => 'Any character',
    '/[áéíóúñüç]/u' => 'Spanish chars (Unicode)',
    '/\{.*\}/' => 'JSON-like braces'
];

foreach ($testStrings as $str) {
    echo "<h3>Testing: '$str'</h3>";
    foreach ($patterns as $pattern => $desc) {
        try {
            $result = preg_match($pattern, $str);
            $error = preg_last_error();
            
            if ($error === PREG_NO_ERROR) {
                echo "<span class='success'>✅</span> $desc: " . ($result ? 'MATCH' : 'NO MATCH') . "<br>";
            } else {
                $errorMessages = [
                    PREG_INTERNAL_ERROR => 'PREG_INTERNAL_ERROR',
                    PREG_BACKTRACK_LIMIT_ERROR => 'PREG_BACKTRACK_LIMIT_ERROR', 
                    PREG_RECURSION_LIMIT_ERROR => 'PREG_RECURSION_LIMIT_ERROR',
                    PREG_BAD_UTF8_ERROR => 'PREG_BAD_UTF8_ERROR',
                    PREG_BAD_UTF8_OFFSET_ERROR => 'PREG_BAD_UTF8_OFFSET_ERROR'
                ];
                echo "<span class='error'>❌</span> $desc: ERROR - " . ($errorMessages[$error] ?? "Unknown error $error") . "<br>";
            }
        } catch (Exception $e) {
            echo "<span class='error'>❌</span> $desc: EXCEPTION - " . $e->getMessage() . "<br>";
        }
    }
    echo "<br>";
}
echo "</div>";

// Test 2: Verificar encoding específico
echo "<div class='test-box'>";
echo "<h2>2. 🌐 Test de Encoding</h2>";

$testString = "Test with special chars: áéíóú ñüç 🚀📊";
echo "<pre>";
echo "Original string: $testString\n";
echo "strlen(): " . strlen($testString) . "\n";
echo "mb_strlen(): " . mb_strlen($testString) . "\n";
echo "mb_detect_encoding(): " . mb_detect_encoding($testString) . "\n";
echo "mb_internal_encoding(): " . mb_internal_encoding() . "\n";
echo "iconv(): " . (function_exists('iconv') ? 'Available' : 'Not available') . "\n";
echo "mbstring extension: " . (extension_loaded('mbstring') ? 'Loaded' : 'Not loaded') . "\n";

// Test conversion
try {
    $utf8 = mb_convert_encoding($testString, 'UTF-8', 'auto');
    echo "UTF-8 conversion: " . ($utf8 === $testString ? 'No change needed' : 'Converted') . "\n";
} catch (Exception $e) {
    echo "UTF-8 conversion error: " . $e->getMessage() . "\n";
}

// Test validation
if (mb_check_encoding($testString, 'UTF-8')) {
    echo "<span class='success'>✅ String is valid UTF-8</span>\n";
} else {
    echo "<span class='error'>❌ String is NOT valid UTF-8</span>\n";
}
echo "</pre>";
echo "</div>";

// Test 3: Simular exactamente lo que hace la migración
echo "<div class='test-box'>";
echo "<h2>3. 🧪 Simulación de Migración</h2>";

try {
    echo "<h3>Step 1: JSON Processing</h3>";
    $inputData = ['action' => 'migrate'];
    $jsonString = json_encode($inputData);
    echo "JSON encode: $jsonString<br>";
    
    $decoded = json_decode($jsonString, true);
    echo "JSON decode success: " . (is_array($decoded) ? 'YES' : 'NO') . "<br>";
    
    echo "<h3>Step 2: Database Connection</h3>";
    require_once "conexion.php";
    if ($conn->ping()) {
        echo "<span class='success'>✅ Database connection OK</span><br>";
        
        // Test charset
        $result = $conn->query("SELECT @@character_set_connection as charset, @@collation_connection as collation");
        if ($result) {
            $charInfo = $result->fetch_assoc();
            echo "DB Charset: {$charInfo['charset']}<br>";
            echo "DB Collation: {$charInfo['collation']}<br>";
        }
    } else {
        echo "<span class='error'>❌ Database connection failed</span><br>";
    }
    
    echo "<h3>Step 3: Directory Operations</h3>";
    $testDir = __DIR__ . '/test_migration_debug';
    if (!is_dir($testDir)) {
        if (mkdir($testDir, 0755, true)) {
            echo "<span class='success'>✅ Directory creation OK</span><br>";
            rmdir($testDir);
        } else {
            echo "<span class='error'>❌ Directory creation failed</span><br>";
        }
    }
    
    echo "<h3>Step 4: File Operations</h3>";
    $testFile = __DIR__ . '/test_debug_file.txt';
    $testContent = "Test content with special chars: áéíóú 🚀\nJSON: " . json_encode(['test' => 'value']);
    
    if (file_put_contents($testFile, $testContent) !== false) {
        echo "<span class='success'>✅ File write OK</span><br>";
        
        $readContent = file_get_contents($testFile);
        if ($readContent === $testContent) {
            echo "<span class='success'>✅ File read OK</span><br>";
        } else {
            echo "<span class='error'>❌ File content mismatch</span><br>";
        }
        unlink($testFile);
    } else {
        echo "<span class='error'>❌ File write failed</span><br>";
    }
    
    echo "<h3>Step 5: SQL Operations</h3>";
    if (isset($conn)) {
        // Test simple query
        $result = $conn->query("SELECT 1 as test");
        if ($result) {
            echo "<span class='success'>✅ Simple query OK</span><br>";
        } else {
            echo "<span class='error'>❌ Simple query failed: " . $conn->error . "</span><br>";
        }
        
        // Test table access
        $result = $conn->query("SHOW TABLES LIKE 'pedidos_detal'");
        if ($result && $result->num_rows > 0) {
            echo "<span class='success'>✅ Table pedidos_detal exists</span><br>";
            
            // Test SHOW CREATE TABLE (this might be where the error occurs)
            $result = $conn->query("SHOW CREATE TABLE pedidos_detal");
            if ($result) {
                $createTable = $result->fetch_assoc();
                $tableSQL = $createTable['Create Table'] ?? '';
                echo "<span class='success'>✅ SHOW CREATE TABLE OK</span> (Length: " . strlen($tableSQL) . " chars)<br>";
                
                // Check for problematic characters in the CREATE TABLE statement
                if (preg_match('/[^\x20-\x7E]/', $tableSQL)) {
                    echo "<span class='warning'>⚠️ Non-ASCII characters found in table definition</span><br>";
                } else {
                    echo "<span class='success'>✅ Table definition contains only ASCII characters</span><br>";
                }
            } else {
                echo "<span class='error'>❌ SHOW CREATE TABLE failed: " . $conn->error . "</span><br>";
            }
        } else {
            echo "<span class='error'>❌ Table pedidos_detal not found</span><br>";
        }
    }
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Simulation error: " . $e->getMessage() . "</span><br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
} catch (Error $e) {
    echo "<span class='error'>❌ Fatal error: " . $e->getMessage() . "</span><br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}

echo "</div>";

// Test 4: Error log analysis
echo "<div class='test-box'>";
echo "<h2>4. 📋 Error Log Analysis</h2>";

$errorLogPaths = [
    __DIR__ . '/logs/migration.log',
    __DIR__ . '/otros/error.log',
    __DIR__ . '/otros/diagnostic.log',
    ini_get('error_log')
];

foreach ($errorLogPaths as $logPath) {
    if ($logPath && file_exists($logPath)) {
        echo "<h3>Log: $logPath</h3>";
        $logContent = file_get_contents($logPath);
        $lines = explode("\n", $logContent);
        $recentLines = array_slice($lines, -10);
        
        echo "<pre>";
        foreach ($recentLines as $line) {
            if (trim($line) && (
                stripos($line, 'pattern') !== false ||
                stripos($line, 'match') !== false ||
                stripos($line, 'error') !== false ||
                stripos($line, 'migration') !== false
            )) {
                echo htmlspecialchars($line) . "\n";
            }
        }
        echo "</pre>";
    } else {
        echo "<p>Log not found: $logPath</p>";
    }
}

echo "</div>";

echo "<div class='test-box'>";
echo "<h2>5. 💡 Recomendaciones</h2>";
echo "<p>Si este script se ejecuta correctamente, el problema específico del 'pattern' puede estar en:</p>";
echo "<ul>";
echo "<li>🔍 Una validación específica dentro del código de migración</li>";
echo "<li>🗄️ Un problema con la consulta SHOW CREATE TABLE</li>";
echo "<li>📁 Permisos de archivos o directorios</li>";
echo "<li>🌐 Problemas de encoding en el servidor web</li>";
echo "<li>🔧 Configuración específica de PHP en el servidor</li>";
echo "</ul>";
echo "<p><strong>Siguiente paso:</strong> Ejecutar la migración mejorada y revisar los logs detallados.</p>";
echo "</div>";

echo "<p style='color: #4fc3f7;'>✅ Diagnóstico completado - " . date('Y-m-d H:i:s') . "</p>";
?>
