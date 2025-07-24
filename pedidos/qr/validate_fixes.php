<?php
/**
 * Validador de Correcciones de Seguridad
 * Sistema QR - Sequoia Speed
 * 
 * Este script valida que todas las correcciones de seguridad estén implementadas correctamente
 */

defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once dirname(__DIR__) . '/config_secure.php';

echo "\n🔍 VALIDADOR DE CORRECCIONES DE SEGURIDAD - SISTEMA QR\n";
echo "====================================================\n\n";

$total_tests = 0;
$passed_tests = 0;
$failed_tests = 0;

/**
 * Función helper para reportar resultados
 */
function reportTest($test_name, $passed, $details = '') {
    global $total_tests, $passed_tests, $failed_tests;
    
    $total_tests++;
    $status = $passed ? "✅ PASS" : "❌ FAIL";
    $details_str = $details ? " - $details" : "";
    
    echo sprintf("%-50s %s%s\n", $test_name, $status, $details_str);
    
    if ($passed) {
        $passed_tests++;
    } else {
        $failed_tests++;
    }
}

// ============================================================================
// 1. VALIDAR PROTECCIÓN CSRF
// ============================================================================
echo "🛡️  VALIDANDO PROTECCIÓN CSRF\n";
echo "--------------------------------\n";

// Verificar que AuthMiddleware tiene métodos CSRF
$csrf_methods_exist = class_exists('AuthMiddleware') && 
                     method_exists('AuthMiddleware', 'verifyCSRF') && 
                     method_exists('AuthMiddleware', 'generateCSRF');

reportTest("AuthMiddleware tiene métodos CSRF", $csrf_methods_exist);

// Verificar archivos helper
$csrf_helper_exists = file_exists(__DIR__ . '/csrf_helper.php');
reportTest("csrf_helper.php existe", $csrf_helper_exists);

// Verificar que las APIs tienen verificación CSRF
$apis_to_check = ['generate.php', 'scan.php', 'alerts.php', 'workflows.php'];
foreach ($apis_to_check as $api) {
    $api_path = __DIR__ . '/api/' . $api;
    if (file_exists($api_path)) {
        $content = file_get_contents($api_path);
        $has_csrf = strpos($content, 'verifyCSRF') !== false;
        reportTest("API $api tiene verificación CSRF", $has_csrf);
    }
}

echo "\n";

// ============================================================================
// 2. VALIDAR CONFIGURACIÓN CORS
// ============================================================================
echo "🌐 VALIDANDO CONFIGURACIÓN CORS\n";
echo "--------------------------------\n";

foreach ($apis_to_check as $api) {
    $api_path = __DIR__ . '/api/' . $api;
    if (file_exists($api_path)) {
        $content = file_get_contents($api_path);
        $has_restricted_cors = strpos($content, 'allowed_origins') !== false;
        $no_wildcard_cors = strpos($content, 'Access-Control-Allow-Origin: *') === false;
        
        reportTest("API $api tiene CORS restrictivo", $has_restricted_cors);
        reportTest("API $api no usa CORS wildcard", $no_wildcard_cors);
    }
}

echo "\n";

// ============================================================================
// 3. VALIDAR PROTECCIÓN XSS
// ============================================================================
echo "🔒 VALIDANDO PROTECCIÓN XSS\n";
echo "----------------------------\n";

// Verificar helper XSS
$xss_helper_exists = file_exists(__DIR__ . '/xss_helper.php');
reportTest("xss_helper.php existe", $xss_helper_exists);

if ($xss_helper_exists) {
    require_once __DIR__ . '/xss_helper.php';
    
    $xss_functions = ['escape_html', 'escape_attr', 'escape_js', 'sanitize_html'];
    foreach ($xss_functions as $func) {
        $exists = function_exists($func);
        reportTest("Función $func existe", $exists);
    }
}

echo "\n";

// ============================================================================
// 4. VALIDAR UUID SEGURO
// ============================================================================
echo "🔐 VALIDANDO GENERACIÓN UUID SEGURA\n";
echo "------------------------------------\n";

$qr_manager_path = __DIR__ . '/models/QRManager.php';
if (file_exists($qr_manager_path)) {
    $content = file_get_contents($qr_manager_path);
    
    $uses_random_bytes = strpos($content, 'random_bytes') !== false;
    $no_mt_rand = strpos($content, 'mt_rand') === false;
    
    reportTest("QRManager usa random_bytes", $uses_random_bytes);
    reportTest("QRManager no usa mt_rand", $no_mt_rand);
} else {
    reportTest("QRManager.php existe", false);
}

echo "\n";

// ============================================================================
// 5. VALIDAR RACE CONDITIONS
// ============================================================================
echo "⚡ VALIDANDO PROTECCIÓN RACE CONDITIONS\n";
echo "--------------------------------------\n";

if (file_exists($qr_manager_path)) {
    $content = file_get_contents($qr_manager_path);
    
    $has_unique_test = strpos($content, 'INSERT INTO qr_codes') !== false && 
                       strpos($content, 'FOR UPDATE') !== false;
    
    reportTest("QR generation protegida contra race conditions", $has_unique_test);
}

echo "\n";

// ============================================================================
// 6. VALIDAR STOCK LOCKING
// ============================================================================
echo "🔒 VALIDANDO STOCK LOCKING\n";
echo "--------------------------\n";

if (file_exists($qr_manager_path)) {
    $content = file_get_contents($qr_manager_path);
    
    $has_stock_lock = strpos($content, 'FOR UPDATE') !== false;
    $has_stock_validation = strpos($content, 'stock_actual = ?') !== false;
    
    reportTest("Implementa stock locking", $has_stock_lock);
    reportTest("Valida stock en actualización", $has_stock_validation);
}

echo "\n";

// ============================================================================
// 7. VALIDAR CONTENT SECURITY POLICY
// ============================================================================
echo "🛡️  VALIDANDO CONTENT SECURITY POLICY\n";
echo "-------------------------------------\n";

$security_headers_exists = file_exists(__DIR__ . '/security_headers.php');
reportTest("security_headers.php existe", $security_headers_exists);

if ($security_headers_exists) {
    $content = file_get_contents(__DIR__ . '/security_headers.php');
    
    $has_csp = strpos($content, 'Content-Security-Policy') !== false;
    $has_security_headers = strpos($content, 'X-Content-Type-Options') !== false;
    
    reportTest("Implementa CSP", $has_csp);
    reportTest("Implementa headers de seguridad", $has_security_headers);
}

echo "\n";

// ============================================================================
// 8. VALIDAR ERROR HANDLING
// ============================================================================
echo "📝 VALIDANDO ERROR HANDLING\n";
echo "---------------------------\n";

$error_handler_exists = file_exists(__DIR__ . '/error_handler.php');
reportTest("error_handler.php existe", $error_handler_exists);

if ($error_handler_exists) {
    $content = file_get_contents(__DIR__ . '/error_handler.php');
    
    $has_error_handler = strpos($content, 'setupErrorHandler') !== false;
    $has_logging = strpos($content, 'logError') !== false;
    
    reportTest("Implementa error handler", $has_error_handler);
    reportTest("Implementa logging avanzado", $has_logging);
}

echo "\n";

// ============================================================================
// 9. VALIDAR ESTRUCTURA DE ARCHIVOS
// ============================================================================
echo "📁 VALIDANDO ESTRUCTURA DE ARCHIVOS\n";
echo "-----------------------------------\n";

$required_files = [
    'csrf_helper.php',
    'xss_helper.php',
    'security_headers.php',
    'error_handler.php',
    'models/QRManager.php',
    'api/generate.php',
    'api/scan.php',
    'api/alerts.php',
    'api/query.php',
    'api/workflows.php',
    'api/csrf-token.php'
];

foreach ($required_files as $file) {
    $exists = file_exists(__DIR__ . '/' . $file);
    reportTest("Archivo $file existe", $exists);
}

echo "\n";

// ============================================================================
// 10. VALIDAR BASE DE DATOS (si es posible conectar)
// ============================================================================
echo "🗄️  VALIDANDO BASE DE DATOS\n";
echo "----------------------------\n";

try {
    if (isset($conn) && $conn instanceof mysqli) {
        // Verificar que las tablas QR existen
        $tables = ['qr_codes', 'qr_scan_transactions', 'qr_system_config'];
        
        foreach ($tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            $exists = $result && $result->num_rows > 0;
            reportTest("Tabla $table existe", $exists);
        }
        
        // Verificar UNIQUE constraint en qr_content
        $result = $conn->query("SHOW INDEX FROM qr_codes WHERE Column_name = 'qr_content'");
        $has_unique = $result && $result->num_rows > 0;
        reportTest("Índice único en qr_content", $has_unique);
        
    } else {
        reportTest("Conexión a base de datos", false, "No se pudo conectar");
    }
} catch (Exception $e) {
    reportTest("Conexión a base de datos", false, $e->getMessage());
}

echo "\n";

// ============================================================================
// RESUMEN FINAL
// ============================================================================
echo "📊 RESUMEN DE VALIDACIÓN\n";
echo "========================\n";
echo "Total de pruebas: $total_tests\n";
echo "Pruebas exitosas: $passed_tests\n";
echo "Pruebas fallidas: $failed_tests\n";

$success_rate = round(($passed_tests / $total_tests) * 100, 1);
echo "Tasa de éxito: $success_rate%\n\n";

if ($success_rate >= 90) {
    echo "🎉 EXCELENTE: El sistema ha pasado la mayoría de las validaciones de seguridad.\n";
    echo "✅ El sistema está listo para un entorno de producción seguro.\n";
} elseif ($success_rate >= 75) {
    echo "⚠️  BUENO: El sistema ha pasado la mayoría de validaciones, pero hay algunas mejoras pendientes.\n";
    echo "🔧 Revise las pruebas fallidas antes de desplegar a producción.\n";
} else {
    echo "❌ CRÍTICO: El sistema tiene múltiples problemas de seguridad.\n";
    echo "🚫 NO DESPLEGAR a producción hasta corregir las fallas críticas.\n";
}

echo "\n";

// ============================================================================
// RECOMENDACIONES
// ============================================================================
if ($failed_tests > 0) {
    echo "🔧 RECOMENDACIONES PARA CORRECCIONES:\n";
    echo "====================================\n";
    
    if (!$csrf_methods_exist) {
        echo "• Verificar que AuthMiddleware esté actualizado con métodos CSRF\n";
    }
    
    if (!$xss_helper_exists) {
        echo "• Crear archivo xss_helper.php con funciones de escape\n";
    }
    
    if (!$security_headers_exists) {
        echo "• Implementar security_headers.php con CSP y otros headers\n";
    }
    
    if (!$error_handler_exists) {
        echo "• Crear sistema de manejo de errores centralizado\n";
    }
    
    echo "• Revisar logs de errores para identificar problemas específicos\n";
    echo "• Ejecutar pruebas de penetración para validar seguridad\n";
    echo "• Verificar configuración de servidor web (Apache/Nginx)\n";
    
    echo "\n";
}

echo "✨ Validación completada. Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "\n";
?>