<?php
/**
 * Verificación Final del Sistema QR
 * Análisis exhaustivo de funcionalidades, base de datos y código
 */

defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once dirname(__DIR__) . '/config_secure.php';

echo "\n🔍 VERIFICACIÓN FINAL DEL SISTEMA QR\n";
echo "=====================================\n\n";

$errors_found = [];
$warnings_found = [];
$total_checks = 0;
$passed_checks = 0;

function addError($message, $file = '', $line = '') {
    global $errors_found;
    $location = $file ? " ($file" . ($line ? ":$line" : "") . ")" : "";
    $errors_found[] = "❌ ERROR: $message$location";
}

function addWarning($message, $file = '', $line = '') {
    global $warnings_found;
    $location = $file ? " ($file" . ($line ? ":$line" : "") . ")" : "";
    $warnings_found[] = "⚠️  WARNING: $message$location";
}

function checkPassed($message) {
    global $total_checks, $passed_checks;
    $total_checks++;
    $passed_checks++;
    echo "✅ $message\n";
}

function checkFailed($message) {
    global $total_checks;
    $total_checks++;
    echo "❌ $message\n";
}

// ============================================================================
// 1. VERIFICACIÓN DE BASE DE DATOS
// ============================================================================
echo "🗄️  VERIFICANDO BASE DE DATOS\n";
echo "------------------------------\n";

try {
    if (!$conn || $conn->connect_error) {
        addError("No se puede conectar a la base de datos: " . ($conn->connect_error ?? 'Conexión no establecida'));
    } else {
        checkPassed("Conexión a base de datos establecida");
        
        // Verificar tablas QR
        $required_tables = [
            'qr_codes' => [
                'id', 'qr_uuid', 'qr_content', 'entity_type', 'entity_id', 
                'linked_product_id', 'linked_almacen_id', 'linked_inventory_id',
                'base_data', 'context_rules', 'scan_count', 'active', 'created_by',
                'created_at', 'updated_at', 'last_scanned_at'
            ],
            'qr_scan_transactions' => [
                'id', 'transaction_uuid', 'qr_code_id', 'user_id', 'action_performed',
                'quantity_affected', 'device_info', 'processing_status', 'error_message',
                'processing_duration_ms', 'generated_movement_id', 'scanned_at'
            ],
            'qr_system_config' => [
                'id', 'config_key', 'config_value', 'created_by', 'active',
                'created_at', 'updated_at'
            ],
            'qr_workflow_config' => [
                'id', 'workflow_name', 'workflow_type', 'config_data', 'active',
                'created_by', 'created_at', 'updated_at'
            ],
            'qr_physical_locations' => [
                'id', 'location_name', 'location_code', 'almacen_id', 'coordinates',
                'active', 'created_at'
            ],
            'qr_work_sessions' => [
                'id', 'session_uuid', 'user_id', 'almacen_id', 'session_type',
                'started_at', 'ended_at', 'total_scans', 'status'
            ]
        ];
        
        foreach ($required_tables as $table => $columns) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if ($result && $result->num_rows > 0) {
                checkPassed("Tabla $table existe");
                
                // Verificar columnas
                $column_result = $conn->query("DESCRIBE $table");
                $existing_columns = [];
                while ($col = $column_result->fetch_assoc()) {
                    $existing_columns[] = $col['Field'];
                }
                
                foreach ($columns as $column) {
                    if (!in_array($column, $existing_columns)) {
                        addError("Columna '$column' faltante en tabla '$table'");
                    }
                }
                
                // Verificar datos de ejemplo
                $count_result = $conn->query("SELECT COUNT(*) as count FROM $table");
                if ($count_result) {
                    $count = $count_result->fetch_assoc()['count'];
                    if ($count > 0) {
                        checkPassed("Tabla $table tiene datos ($count registros)");
                    } else {
                        addWarning("Tabla $table está vacía", $table);
                    }
                }
            } else {
                addError("Tabla '$table' no existe");
            }
        }
        
        // Verificar índices importantes
        $indexes_to_check = [
            'qr_codes' => ['qr_content', 'qr_uuid', 'entity_type', 'linked_product_id'],
            'qr_scan_transactions' => ['qr_code_id', 'user_id', 'scanned_at'],
            'qr_system_config' => ['config_key']
        ];
        
        foreach ($indexes_to_check as $table => $indexes) {
            foreach ($indexes as $index) {
                $index_result = $conn->query("SHOW INDEX FROM $table WHERE Column_name = '$index'");
                if ($index_result && $index_result->num_rows > 0) {
                    checkPassed("Índice en $table.$index existe");
                } else {
                    addWarning("Falta índice en $table.$index para performance", $table);
                }
            }
        }
        
        // Verificar foreign keys
        $fk_result = $conn->query("
            SELECT 
                CONSTRAINT_NAME,
                TABLE_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
            WHERE REFERENCED_TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME LIKE 'qr_%'
        ");
        
        if ($fk_result && $fk_result->num_rows > 0) {
            checkPassed("Foreign keys configuradas en tablas QR");
        } else {
            addWarning("No se encontraron foreign keys en tablas QR");
        }
    }
} catch (Exception $e) {
    addError("Error verificando base de datos: " . $e->getMessage());
}

echo "\n";

// ============================================================================
// 2. VERIFICACIÓN DE ARCHIVOS CRÍTICOS
// ============================================================================
echo "📁 VERIFICANDO ARCHIVOS CRÍTICOS\n";
echo "---------------------------------\n";

$critical_files = [
    // Modelos
    'models/QRManager.php' => ['createProductQR', 'processScan', 'generateUniqueQRCode'],
    
    // APIs
    'api/generate.php' => ['POST'],
    'api/scan.php' => ['POST'],
    'api/query.php' => ['GET'],
    'api/alerts.php' => ['GET', 'POST'],
    'api/reports.php' => ['GET'],
    'api/workflows.php' => ['GET', 'POST', 'PATCH', 'DELETE'],
    'api/image.php' => ['GET'],
    'api/csrf-token.php' => ['GET'],
    
    // Páginas web
    'index.php' => [],
    'scanner.php' => [],
    'reports.php' => [],
    'alerts.php' => [],
    'workflows.php' => [],
    
    // Helpers de seguridad
    'csrf_helper.php' => ['generateCSRFToken', 'verifyCSRFToken'],
    'xss_helper.php' => ['escape_html', 'escape_attr'],
    'security_headers.php' => ['setSecurityHeaders'],
    'error_handler.php' => ['setupErrorHandler']
];

foreach ($critical_files as $file => $functions) {
    $file_path = __DIR__ . '/' . $file;
    
    if (file_exists($file_path)) {
        checkPassed("Archivo $file existe");
        
        $content = file_get_contents($file_path);
        
        // Verificar funciones requeridas
        foreach ($functions as $function) {
            if (strpos($content, $function) !== false) {
                checkPassed("Función/método $function encontrado en $file");
            } else {
                addError("Función/método '$function' no encontrado en '$file'", $file);
            }
        }
        
        // Verificar posibles errores de sintaxis
        $syntax_check = shell_exec("php -l '$file_path' 2>&1");
        if (strpos($syntax_check, 'No syntax errors') !== false) {
            checkPassed("Sintaxis PHP válida en $file");
        } else {
            addError("Error de sintaxis en '$file': $syntax_check", $file);
        }
        
        // Verificar vulnerabilidades comunes
        if (strpos($content, '$_GET[') !== false && strpos($content, 'htmlspecialchars') === false && 
            strpos($content, 'escape_html') === false) {
            addWarning("Posible XSS: \$_GET usado sin escape en '$file'", $file);
        }
        
        if (strpos($content, '$_POST[') !== false && strpos($content, 'verifyCSRF') === false && 
            strpos($file, 'api/') !== false) {
            addWarning("Posible falta de CSRF: \$_POST usado sin verificación en '$file'", $file);
        }
        
        if (strpos($content, 'SELECT') !== false && strpos($content, 'prepare') === false) {
            addWarning("Posible SQL injection: Query sin prepare en '$file'", $file);
        }
        
    } else {
        addError("Archivo crítico '$file' no existe");
    }
}

echo "\n";

// ============================================================================
// 3. VERIFICACIÓN DE FUNCIONALIDADES CORE
// ============================================================================
echo "⚙️  VERIFICANDO FUNCIONALIDADES CORE\n";
echo "------------------------------------\n";

// Verificar QRManager
if (file_exists(__DIR__ . '/models/QRManager.php')) {
    require_once __DIR__ . '/models/QRManager.php';
    
    try {
        $qr_manager = new QRManager($conn);
        checkPassed("QRManager se puede instanciar");
        
        // Verificar métodos críticos
        $critical_methods = [
            'createProductQR', 'processScan', 'generateUniqueQRCode', 
            'getQRStats', 'getSystemConfig'
        ];
        
        foreach ($critical_methods as $method) {
            if (method_exists($qr_manager, $method)) {
                checkPassed("Método QRManager::$method existe");
            } else {
                addError("Método crítico '$method' no existe en QRManager");
            }
        }
        
    } catch (Exception $e) {
        addError("Error instanciando QRManager: " . $e->getMessage());
    }
} else {
    addError("QRManager.php no encontrado");
}

// Verificar helpers de seguridad
$security_helpers = [
    'csrf_helper.php' => ['generateCSRFToken', 'verifyCSRFToken', 'getCSRFToken'],
    'xss_helper.php' => ['escape_html', 'escape_attr', 'escape_js', 'sanitize_html'],
    'security_headers.php' => ['setSecurityHeaders', 'setAPISecurityHeaders'],
    'error_handler.php' => ['setupErrorHandler', 'logError', 'handleError']
];

foreach ($security_helpers as $helper => $functions) {
    if (file_exists(__DIR__ . '/' . $helper)) {
        require_once __DIR__ . '/' . $helper;
        checkPassed("Helper $helper cargado");
        
        foreach ($functions as $function) {
            if (function_exists($function)) {
                checkPassed("Función $function disponible");
            } else {
                addError("Función '$function' no está disponible desde '$helper'");
            }
        }
    } else {
        addError("Helper crítico '$helper' no encontrado");
    }
}

echo "\n";

// ============================================================================
// 4. VERIFICACIÓN DE CONFIGURACIÓN DE SEGURIDAD
// ============================================================================
echo "🔐 VERIFICANDO CONFIGURACIÓN DE SEGURIDAD\n";
echo "-----------------------------------------\n";

// Verificar CORS en APIs
$api_files = glob(__DIR__ . '/api/*.php');
foreach ($api_files as $api_file) {
    $content = file_get_contents($api_file);
    $filename = basename($api_file);
    
    if (strpos($content, 'Access-Control-Allow-Origin: *') !== false) {
        addError("CORS wildcard encontrado en '$filename'", $filename);
    } else if (strpos($content, 'allowed_origins') !== false) {
        checkPassed("CORS restrictivo configurado en $filename");
    } else {
        addWarning("CORS no configurado en '$filename'", $filename);
    }
    
    // Verificar CSRF en APIs POST
    if (strpos($content, 'POST') !== false || strpos($content, 'PATCH') !== false || 
        strpos($content, 'DELETE') !== false) {
        if (strpos($content, 'verifyCSRF') !== false) {
            checkPassed("Verificación CSRF en $filename");
        } else {
            addError("Falta verificación CSRF en API '$filename'", $filename);
        }
    }
}

// Verificar headers de seguridad en páginas web
$web_pages = ['index.php', 'scanner.php', 'reports.php', 'alerts.php'];
foreach ($web_pages as $page) {
    if (file_exists(__DIR__ . '/' . $page)) {
        $content = file_get_contents(__DIR__ . '/' . $page);
        
        if (strpos($content, 'setSecurityHeaders') !== false) {
            checkPassed("Headers de seguridad configurados en $page");
        } else {
            addWarning("Headers de seguridad no configurados en '$page'", $page);
        }
        
        if (strpos($content, 'csrfMetaTag') !== false || strpos($content, 'csrf-token') !== false) {
            checkPassed("Meta tag CSRF en $page");
        } else {
            addWarning("Meta tag CSRF no encontrado en '$page'", $page);
        }
    }
}

echo "\n";

// ============================================================================
// 5. VERIFICACIÓN DE INTEGRACIÓN CON SISTEMA EXISTENTE
// ============================================================================
echo "🔗 VERIFICANDO INTEGRACIÓN CON SISTEMA EXISTENTE\n";
echo "------------------------------------------------\n";

// Verificar AuthMiddleware
$auth_path = dirname(__DIR__) . '/accesos/middleware/AuthMiddleware.php';
if (file_exists($auth_path)) {
    checkPassed("AuthMiddleware encontrado");
    
    require_once $auth_path;
    if (class_exists('AuthMiddleware')) {
        checkPassed("Clase AuthMiddleware disponible");
        
        $auth_methods = ['requirePermission', 'verifyCSRF', 'generateCSRF', 'hasPermission'];
        foreach ($auth_methods as $method) {
            if (method_exists('AuthMiddleware', $method)) {
                checkPassed("AuthMiddleware::$method existe");
            } else {
                addError("Método '$method' no existe en AuthMiddleware");
            }
        }
    } else {
        addError("Clase AuthMiddleware no se puede cargar");
    }
} else {
    addError("AuthMiddleware no encontrado en $auth_path");
}

// Verificar tablas del sistema principal
$main_tables = ['usuarios', 'productos', 'almacenes', 'inventario_almacen', 'movimientos_inventario'];
foreach ($main_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        checkPassed("Tabla principal '$table' existe");
    } else {
        addError("Tabla principal '$table' no encontrada - requerida para integración");
    }
}

echo "\n";

// ============================================================================
// 6. VERIFICACIÓN DE ASSETS Y RECURSOS
// ============================================================================
echo "🎨 VERIFICANDO ASSETS Y RECURSOS\n";
echo "--------------------------------\n";

// Verificar directorio assets
if (is_dir(__DIR__ . '/assets')) {
    checkPassed("Directorio assets existe");
    
    // Verificar CSS
    if (is_dir(__DIR__ . '/assets/css')) {
        checkPassed("Directorio assets/css existe");
    } else {
        addWarning("Directorio assets/css no encontrado");
    }
    
    // Verificar JS
    if (is_dir(__DIR__ . '/assets/js')) {
        checkPassed("Directorio assets/js existe");
        
        if (file_exists(__DIR__ . '/assets/js/csrf.js')) {
            checkPassed("Script CSRF JavaScript existe");
        } else {
            addWarning("Script CSRF JavaScript no encontrado");
        }
    } else {
        addWarning("Directorio assets/js no encontrado");
    }
} else {
    addWarning("Directorio assets no encontrado");
}

// Verificar logs directory
$logs_dir = dirname(__DIR__) . '/logs';
if (is_dir($logs_dir)) {
    checkPassed("Directorio logs existe");
    if (is_writable($logs_dir)) {
        checkPassed("Directorio logs es escribible");
    } else {
        addError("Directorio logs no es escribible");
    }
} else {
    addWarning("Directorio logs no existe");
}

echo "\n";

// ============================================================================
// RESUMEN FINAL
// ============================================================================
echo "📊 RESUMEN DE VERIFICACIÓN FINAL\n";
echo "=================================\n";

echo "Verificaciones totales: $total_checks\n";
echo "Verificaciones exitosas: $passed_checks\n";
echo "Errores encontrados: " . count($errors_found) . "\n";
echo "Advertencias encontradas: " . count($warnings_found) . "\n";

$success_rate = $total_checks > 0 ? round(($passed_checks / $total_checks) * 100, 1) : 0;
echo "Tasa de éxito: $success_rate%\n\n";

if (count($errors_found) > 0) {
    echo "🚨 ERRORES CRÍTICOS ENCONTRADOS:\n";
    echo "================================\n";
    foreach ($errors_found as $error) {
        echo "$error\n";
    }
    echo "\n";
}

if (count($warnings_found) > 0) {
    echo "⚠️  ADVERTENCIAS:\n";
    echo "=================\n";
    foreach ($warnings_found as $warning) {
        echo "$warning\n";
    }
    echo "\n";
}

// Evaluación final
if (count($errors_found) == 0) {
    echo "🎉 SISTEMA QR: VERIFICACIÓN EXITOSA\n";
    echo "✅ No se encontraron errores críticos\n";
    echo "✅ Sistema listo para producción\n";
} else if (count($errors_found) <= 3) {
    echo "⚠️  SISTEMA QR: REQUIERE CORRECCIONES MENORES\n";
    echo "🔧 Corregir errores antes de producción\n";
} else {
    echo "❌ SISTEMA QR: REQUIERE CORRECCIONES CRÍTICAS\n";
    echo "🚫 NO desplegar hasta resolver errores\n";
}

echo "\n";
echo "✨ Verificación completada: " . date('Y-m-d H:i:s') . "\n";
?>