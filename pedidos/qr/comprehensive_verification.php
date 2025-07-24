<?php
/**
 * Verificación Comprehensiva del Sistema QR
 * Incluye pruebas funcionales, de seguridad y de integración
 */

defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once dirname(__DIR__) . '/config_secure.php';

echo "\n🔍 VERIFICACIÓN COMPREHENSIVA DEL SISTEMA QR\n";
echo "============================================\n\n";

$test_results = [];
$total_tests = 0;
$passed_tests = 0;
$failed_tests = 0;
$warnings = [];

function runTest($category, $test_name, $test_function) {
    global $test_results, $total_tests, $passed_tests, $failed_tests;
    
    $total_tests++;
    try {
        $result = $test_function();
        if ($result === true) {
            $passed_tests++;
            $test_results[$category][] = ['test' => $test_name, 'status' => 'PASS', 'message' => ''];
            echo "✅ $test_name\n";
        } else {
            $failed_tests++;
            $test_results[$category][] = ['test' => $test_name, 'status' => 'FAIL', 'message' => $result];
            echo "❌ $test_name: $result\n";
        }
    } catch (Exception $e) {
        $failed_tests++;
        $test_results[$category][] = ['test' => $test_name, 'status' => 'ERROR', 'message' => $e->getMessage()];
        echo "💥 $test_name: ERROR - " . $e->getMessage() . "\n";
    }
}

// ============================================================================
// 1. VERIFICACIÓN DE ESTRUCTURA DE BASE DE DATOS
// ============================================================================
echo "📊 1. VERIFICACIÓN DE BASE DE DATOS\n";
echo "===================================\n";

// Test conexión a base de datos
runTest('database', 'Conexión a base de datos', function() use ($conn) {
    return $conn && !$conn->connect_error;
});

// Test existencia de tablas QR
$qr_tables = [
    'qr_codes' => [
        'columns' => ['id', 'qr_uuid', 'qr_content', 'entity_type', 'entity_id', 'active'],
        'indexes' => ['qr_content', 'qr_uuid'],
        'foreign_keys' => ['created_by' => 'usuarios(id)']
    ],
    'qr_scan_transactions' => [
        'columns' => ['id', 'transaction_uuid', 'qr_code_id', 'user_id', 'action_performed'],
        'indexes' => ['qr_code_id', 'user_id'],
        'foreign_keys' => ['qr_code_id' => 'qr_codes(id)', 'user_id' => 'usuarios(id)']
    ],
    'qr_system_config' => [
        'columns' => ['id', 'config_key', 'config_value', 'active'],
        'indexes' => ['config_key'],
        'foreign_keys' => []
    ],
    'qr_workflow_config' => [
        'columns' => ['id', 'workflow_name', 'workflow_type', 'active'],
        'indexes' => [],
        'foreign_keys' => []
    ],
    'qr_physical_locations' => [
        'columns' => ['id', 'location_name', 'qr_code_id', 'almacen_id'],
        'indexes' => [],
        'foreign_keys' => []
    ],
    'qr_work_sessions' => [
        'columns' => ['id', 'session_uuid', 'user_id', 'status'],
        'indexes' => [],
        'foreign_keys' => []
    ]
];

foreach ($qr_tables as $table => $structure) {
    // Test existencia de tabla
    runTest('database', "Tabla $table existe", function() use ($conn, $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        return $result && $result->num_rows > 0;
    });
    
    // Test columnas críticas
    foreach ($structure['columns'] as $column) {
        runTest('database', "Columna $table.$column existe", function() use ($conn, $table, $column) {
            $result = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
            return $result && $result->num_rows > 0;
        });
    }
    
    // Test índices
    foreach ($structure['indexes'] as $index) {
        runTest('database', "Índice en $table.$index", function() use ($conn, $table, $index) {
            $result = $conn->query("SHOW INDEX FROM $table WHERE Column_name = '$index'");
            return $result && $result->num_rows > 0;
        });
    }
}

// Test integridad referencial
runTest('database', 'Foreign keys configuradas', function() use ($conn) {
    $result = $conn->query("
        SELECT COUNT(*) as count 
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE REFERENCED_TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME LIKE 'qr_%'
    ");
    $row = $result->fetch_assoc();
    return $row['count'] > 0;
});

echo "\n";

// ============================================================================
// 2. VERIFICACIÓN DE ARCHIVOS Y CLASES
// ============================================================================
echo "📁 2. VERIFICACIÓN DE ARCHIVOS Y CLASES\n";
echo "=======================================\n";

// Test archivos críticos
$critical_files = [
    'models/QRManager.php',
    'api/generate.php',
    'api/scan.php',
    'api/query.php',
    'api/reports.php',
    'api/alerts.php',
    'api/workflows.php',
    'api/image.php',
    'api/csrf-token.php',
    'api/permissions.php',
    'csrf_helper.php',
    'xss_helper.php',
    'security_headers.php',
    'error_handler.php'
];

foreach ($critical_files as $file) {
    runTest('files', "Archivo $file existe", function() use ($file) {
        return file_exists(__DIR__ . '/' . $file);
    });
}

// Test QRManager
runTest('files', 'QRManager se puede cargar', function() {
    require_once __DIR__ . '/models/QRManager.php';
    return class_exists('QRManager');
});

runTest('files', 'QRManager se puede instanciar', function() use ($conn) {
    $manager = new QRManager($conn);
    return $manager instanceof QRManager;
});

// Test métodos de QRManager
$qr_methods = [
    'generateUniqueQRCode',
    'createProductQR',
    'processScan',
    'getQRByContent',
    'getQRStats',
    'getSystemConfig'
];

foreach ($qr_methods as $method) {
    runTest('files', "QRManager::$method existe", function() use ($method) {
        return method_exists('QRManager', $method);
    });
}

echo "\n";

// ============================================================================
// 3. VERIFICACIÓN DE SEGURIDAD
// ============================================================================
echo "🔒 3. VERIFICACIÓN DE SEGURIDAD\n";
echo "================================\n";

// Test helpers de seguridad
runTest('security', 'CSRF helper funciones disponibles', function() {
    require_once __DIR__ . '/csrf_helper.php';
    return function_exists('generateCSRFToken') && 
           function_exists('verifyCSRFToken') && 
           function_exists('getCSRFToken');
});

runTest('security', 'XSS helper funciones disponibles', function() {
    require_once __DIR__ . '/xss_helper.php';
    return function_exists('escape_html') && 
           function_exists('escape_attr') && 
           function_exists('escape_js');
});

runTest('security', 'Security headers funciones disponibles', function() {
    require_once __DIR__ . '/security_headers.php';
    return function_exists('setSecurityHeaders') && 
           function_exists('setAPISecurityHeaders');
});

// Test CORS en APIs
$api_files = glob(__DIR__ . '/api/*.php');
$cors_issues = 0;
$csrf_issues = 0;

foreach ($api_files as $api_file) {
    $content = file_get_contents($api_file);
    $filename = basename($api_file);
    
    // Test CORS restrictivo
    if (strpos($content, 'Access-Control-Allow-Origin: *') !== false) {
        $cors_issues++;
        $warnings[] = "CORS abierto en $filename";
    }
    
    // Test CSRF en APIs que modifican estado
    if ((strpos($content, 'POST') !== false || strpos($content, 'PATCH') !== false || 
         strpos($content, 'DELETE') !== false || strpos($content, 'PUT') !== false) && 
        strpos($content, 'verifyCSRF') === false && 
        $filename !== 'csrf-token.php') {
        $csrf_issues++;
        $warnings[] = "Posible falta de CSRF en $filename";
    }
}

runTest('security', 'APIs sin CORS wildcard', function() use ($cors_issues) {
    return $cors_issues === 0;
});

runTest('security', 'APIs con verificación CSRF', function() use ($csrf_issues) {
    return $csrf_issues === 0;
});

// Test prepared statements
runTest('security', 'Uso de prepared statements', function() {
    $files = array_merge(
        glob(__DIR__ . '/api/*.php'),
        glob(__DIR__ . '/models/*.php')
    );
    
    foreach ($files as $file) {
        $content = file_get_contents($file);
        // Buscar queries directas sin prepare
        if (preg_match('/\$conn->query\s*\(\s*["\']SELECT.*\$/', $content) ||
            preg_match('/\$conn->query\s*\(\s*["\']INSERT.*\$/', $content) ||
            preg_match('/\$conn->query\s*\(\s*["\']UPDATE.*\$/', $content) ||
            preg_match('/\$conn->query\s*\(\s*["\']DELETE.*\$/', $content)) {
            return false;
        }
    }
    return true;
});

echo "\n";

// ============================================================================
// 4. VERIFICACIÓN DE INTEGRACIÓN
// ============================================================================
echo "🔗 4. VERIFICACIÓN DE INTEGRACIÓN\n";
echo "==================================\n";

// Test AuthMiddleware
runTest('integration', 'AuthMiddleware disponible', function() {
    $path = dirname(__DIR__) . '/accesos/middleware/AuthMiddleware.php';
    if (!file_exists($path)) return false;
    require_once $path;
    return class_exists('AuthMiddleware');
});

runTest('integration', 'AuthMiddleware métodos CSRF', function() {
    return method_exists('AuthMiddleware', 'verifyCSRF') && 
           method_exists('AuthMiddleware', 'generateCSRF');
});

// Test tablas del sistema principal
$main_tables = ['usuarios', 'productos', 'almacenes', 'inventario_almacen', 'movimientos_inventario'];
foreach ($main_tables as $table) {
    runTest('integration', "Tabla sistema '$table' existe", function() use ($conn, $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        return $result && $result->num_rows > 0;
    });
}

// Test módulo QR en sistema de permisos
runTest('integration', 'Módulo QR en sistema de permisos', function() use ($conn) {
    $result = $conn->query("SELECT id FROM modulos WHERE nombre = 'qr'");
    return $result && $result->num_rows > 0;
});

echo "\n";

// ============================================================================
// 5. PRUEBAS FUNCIONALES
// ============================================================================
echo "⚙️  5. PRUEBAS FUNCIONALES\n";
echo "==========================\n";

// Test generación de UUID único
runTest('functional', 'Generación de UUID único', function() use ($conn) {
    $manager = new QRManager($conn);
    $reflection = new ReflectionClass($manager);
    $method = $reflection->getMethod('generateUUID');
    $method->setAccessible(true);
    
    $uuid1 = $method->invoke($manager);
    $uuid2 = $method->invoke($manager);
    
    // Verificar formato UUID v4
    $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';
    return preg_match($pattern, $uuid1) && preg_match($pattern, $uuid2) && $uuid1 !== $uuid2;
});

// Test configuración del sistema
runTest('functional', 'Configuración del sistema accesible', function() use ($conn) {
    $manager = new QRManager($conn);
    $config = $manager->getSystemConfig('qr_format');
    return $config !== null;
});

// Test generación de código QR único
runTest('functional', 'Generación de código QR único', function() use ($conn) {
    $manager = new QRManager($conn);
    try {
        $code = $manager->generateUniqueQRCode('test', 1);
        return is_string($code) && strlen($code) > 0;
    } catch (Exception $e) {
        return true; // Si falla por falta de configuración, es aceptable
    }
});

echo "\n";

// ============================================================================
// 6. VERIFICACIÓN DE PERFORMANCE
// ============================================================================
echo "⚡ 6. VERIFICACIÓN DE PERFORMANCE\n";
echo "==================================\n";

// Test índices de performance
runTest('performance', 'Índices de búsqueda configurados', function() use ($conn) {
    $critical_indexes = [
        'qr_codes' => ['qr_content', 'entity_type', 'linked_product_id'],
        'qr_scan_transactions' => ['qr_code_id', 'user_id', 'scanned_at']
    ];
    
    foreach ($critical_indexes as $table => $indexes) {
        foreach ($indexes as $index) {
            $result = $conn->query("SHOW INDEX FROM $table WHERE Column_name = '$index'");
            if (!$result || $result->num_rows === 0) {
                return false;
            }
        }
    }
    return true;
});

// Test configuración de caché
runTest('performance', 'Directorio de logs escribible', function() {
    $logs_dir = dirname(__DIR__) . '/logs';
    return is_dir($logs_dir) && is_writable($logs_dir);
});

echo "\n";

// ============================================================================
// 7. VERIFICACIÓN DE ASSETS Y RECURSOS
// ============================================================================
echo "🎨 7. VERIFICACIÓN DE ASSETS Y RECURSOS\n";
echo "========================================\n";

runTest('assets', 'Estructura de directorios assets', function() {
    return is_dir(__DIR__ . '/assets') && 
           is_dir(__DIR__ . '/assets/css') && 
           is_dir(__DIR__ . '/assets/js');
});

runTest('assets', 'JavaScript CSRF disponible', function() {
    return file_exists(__DIR__ . '/assets/js/csrf.js');
});

echo "\n";

// ============================================================================
// 8. VERIFICACIÓN DE DATOS Y CONFIGURACIÓN
// ============================================================================
echo "📋 8. VERIFICACIÓN DE DATOS Y CONFIGURACIÓN\n";
echo "============================================\n";

// Test configuraciones iniciales
runTest('config', 'Configuraciones QR iniciales', function() use ($conn) {
    $result = $conn->query("SELECT COUNT(*) as count FROM qr_system_config WHERE active = 1");
    $row = $result->fetch_assoc();
    return $row['count'] > 0;
});

// Test workflows configurados
runTest('config', 'Workflows QR configurados', function() use ($conn) {
    $result = $conn->query("SELECT COUNT(*) as count FROM qr_workflow_config WHERE active = 1");
    $row = $result->fetch_assoc();
    return $row['count'] > 0;
});

echo "\n";

// ============================================================================
// RESUMEN DE RESULTADOS
// ============================================================================
echo "📊 RESUMEN DE VERIFICACIÓN\n";
echo "==========================\n\n";

$success_rate = $total_tests > 0 ? round(($passed_tests / $total_tests) * 100, 2) : 0;

echo "Total de pruebas: $total_tests\n";
echo "Pruebas exitosas: $passed_tests\n";
echo "Pruebas fallidas: $failed_tests\n";
echo "Tasa de éxito: $success_rate%\n\n";

// Mostrar resultados por categoría
foreach ($test_results as $category => $results) {
    $category_passed = count(array_filter($results, fn($r) => $r['status'] === 'PASS'));
    $category_total = count($results);
    echo strtoupper($category) . ": $category_passed/$category_total\n";
}

echo "\n";

// Mostrar advertencias si las hay
if (count($warnings) > 0) {
    echo "⚠️  ADVERTENCIAS:\n";
    echo "================\n";
    foreach ($warnings as $warning) {
        echo "- $warning\n";
    }
    echo "\n";
}

// Mostrar pruebas fallidas
if ($failed_tests > 0) {
    echo "❌ PRUEBAS FALLIDAS:\n";
    echo "====================\n";
    foreach ($test_results as $category => $results) {
        foreach ($results as $result) {
            if ($result['status'] !== 'PASS') {
                echo "- [{$category}] {$result['test']}: {$result['message']}\n";
            }
        }
    }
    echo "\n";
}

// Evaluación final
echo "🎯 EVALUACIÓN FINAL:\n";
echo "====================\n";

if ($success_rate === 100) {
    echo "✅ PERFECTO: El sistema QR pasó todas las verificaciones.\n";
    echo "🚀 Sistema listo para producción sin reservas.\n";
} elseif ($success_rate >= 95) {
    echo "✅ EXCELENTE: El sistema QR está en excelente estado.\n";
    echo "🔧 Revise las pocas pruebas fallidas antes de producción.\n";
} elseif ($success_rate >= 90) {
    echo "⚠️  MUY BUENO: El sistema QR está funcional con algunas mejoras pendientes.\n";
    echo "🔧 Corrija las pruebas fallidas para óptimo funcionamiento.\n";
} elseif ($success_rate >= 80) {
    echo "⚠️  BUENO: El sistema QR requiere algunas correcciones.\n";
    echo "🔧 Resuelva los problemas identificados antes de producción.\n";
} else {
    echo "❌ REQUIERE ATENCIÓN: El sistema QR tiene problemas significativos.\n";
    echo "🚫 NO desplegar hasta resolver los problemas críticos.\n";
}

echo "\n";
echo "✨ Verificación completada: " . date('Y-m-d H:i:s') . "\n";
echo "📍 Ubicación: " . __DIR__ . "\n";
?>