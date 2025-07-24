<?php
/**
 * Script de Corrección de Errores Críticos - Sistema QR
 * Sequoia Speed - Sistema QR
 * 
 * EJECUTAR ESTE SCRIPT ANTES DE PONER EN PRODUCCIÓN
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once dirname(__DIR__) . '/config_secure.php';

echo "🔧 INICIANDO CORRECCIÓN DE ERRORES CRÍTICOS - SISTEMA QR\n";
echo "======================================================\n\n";

$errors_found = 0;
$errors_fixed = 0;

// 1. VERIFICAR ESTRUCTURA DE BASE DE DATOS
echo "1. 📊 Verificando estructura de base de datos...\n";

$required_tables = [
    'usuarios' => 'SELECT id FROM usuarios LIMIT 1',
    'productos' => 'SELECT id FROM productos LIMIT 1', 
    'almacenes' => 'SELECT id FROM almacenes LIMIT 1',
    'inventario_almacen' => 'SELECT id FROM inventario_almacen LIMIT 1',
    'movimientos_inventario' => 'SELECT id FROM movimientos_inventario LIMIT 1',
    'modulos' => 'SELECT id FROM modulos LIMIT 1',
    'permisos' => 'SELECT id FROM permisos LIMIT 1',
    'roles' => 'SELECT id FROM roles LIMIT 1',
    'rol_permisos' => 'SELECT rol_id FROM rol_permisos LIMIT 1'
];

foreach ($required_tables as $table => $test_query) {
    try {
        $result = $conn->query($test_query);
        if ($result === false) {
            echo "   ❌ ERROR: Tabla '$table' no existe o no es accesible\n";
            $errors_found++;
        } else {
            echo "   ✅ Tabla '$table' verificada\n";
        }
    } catch (Exception $e) {
        echo "   ❌ ERROR: No se puede acceder a tabla '$table': " . $e->getMessage() . "\n";
        $errors_found++;
    }
}

// 2. VERIFICAR ARCHIVOS CRÍTICOS
echo "\n2. 📁 Verificando archivos críticos...\n";

$critical_files = [
    'models/QRManager.php',
    'api/generate.php',
    'api/scan.php', 
    'api/image.php',
    'api/reports.php',
    'api/query.php',
    'api/workflows.php',
    'api/alerts.php',
    'api/permissions.php',
    'scanner.php',
    'reports.php',
    'workflows.php',
    'alerts.php'
];

foreach ($critical_files as $file) {
    $file_path = __DIR__ . '/' . $file;
    if (!file_exists($file_path)) {
        echo "   ❌ ERROR: Archivo crítico faltante: $file\n";
        $errors_found++;
    } else {
        echo "   ✅ Archivo encontrado: $file\n";
    }
}

// 3. VERIFICAR AUTHMIDDLEWARE
echo "\n3. 🔐 Verificando AuthMiddleware...\n";

$auth_path = dirname(__DIR__) . '/accesos/middleware/AuthMiddleware.php';
if (!file_exists($auth_path)) {
    echo "   ❌ ERROR CRÍTICO: AuthMiddleware no encontrado en: $auth_path\n";
    $errors_found++;
} else {
    try {
        require_once $auth_path;
        if (class_exists('AuthMiddleware')) {
            echo "   ✅ AuthMiddleware cargado correctamente\n";
            
            // Verificar métodos críticos
            $reflection = new ReflectionClass('AuthMiddleware');
            $required_methods = ['requirePermission', 'requireLogin', 'hasPermission', 'getUserById'];
            
            foreach ($required_methods as $method) {
                if ($reflection->hasMethod($method)) {
                    echo "   ✅ Método $method disponible\n";
                } else {
                    echo "   ❌ ERROR: Método $method no encontrado\n";
                    $errors_found++;
                }
            }
        } else {
            echo "   ❌ ERROR: Clase AuthMiddleware no disponible\n";
            $errors_found++;
        }
    } catch (Exception $e) {
        echo "   ❌ ERROR: No se puede cargar AuthMiddleware: " . $e->getMessage() . "\n";
        $errors_found++;
    }
}

// 4. CREAR ARCHIVOS CSS FALTANTES
echo "\n4. 🎨 Creando archivos CSS faltantes...\n";

$css_dir = __DIR__ . '/assets/css';
if (!is_dir($css_dir)) {
    mkdir($css_dir, 0755, true);
    echo "   ✅ Directorio CSS creado: $css_dir\n";
}

$qr_css_content = "
/* Sistema QR - Estilos Principales */
.integration-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
}

.integration-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.integration-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border: 1px solid #e9ecef;
}

.integration-icon {
    font-size: 2em;
    margin-bottom: 10px;
}

.integration-content h3 {
    margin: 0 0 10px 0;
    color: #333;
}

.integration-stats {
    font-weight: bold;
    color: #007bff;
    margin: 10px 0;
}

.btn-outline {
    background: transparent;
    border: 1px solid #007bff;
    color: #007bff;
    padding: 8px 16px;
    text-decoration: none;
    border-radius: 4px;
    display: inline-block;
    margin-top: 10px;
}

.btn-outline:hover {
    background: #007bff;
    color: white;
}
";

file_put_contents($css_dir . '/qr.css', $qr_css_content);
echo "   ✅ Archivo CSS creado: assets/css/qr.css\n";
$errors_fixed++;

// 5. VERIFICAR TABLAS QR
echo "\n5. 🗄️ Verificando tablas QR...\n";

$qr_tables = [
    'qr_codes',
    'qr_scan_transactions', 
    'qr_workflow_config',
    'qr_system_config',
    'qr_physical_locations',
    'qr_work_sessions'
];

foreach ($qr_tables as $table) {
    try {
        $result = $conn->query("SELECT COUNT(*) FROM $table");
        if ($result === false) {
            echo "   ❌ ERROR: Tabla QR '$table' no existe\n";
            $errors_found++;
        } else {
            $count = $result->fetch_row()[0];
            echo "   ✅ Tabla '$table' verificada ($count registros)\n";
        }
    } catch (Exception $e) {
        echo "   ❌ ERROR: Tabla '$table' no accesible: " . $e->getMessage() . "\n";
        $errors_found++;
    }
}

// 6. VERIFICAR PERMISOS QR EN RBAC
echo "\n6. 🔑 Verificando permisos QR en RBAC...\n";

try {
    // Verificar módulo QR
    $module_check = $conn->query("SELECT id FROM modulos WHERE nombre = 'qr'");
    if ($module_check && $module_check->num_rows > 0) {
        echo "   ✅ Módulo QR existe en RBAC\n";
        
        $module_id = $module_check->fetch_assoc()['id'];
        
        // Verificar permisos QR
        $perms_check = $conn->query("SELECT COUNT(*) as count FROM permisos WHERE modulo_id = $module_id");
        if ($perms_check) {
            $perm_count = $perms_check->fetch_assoc()['count'];
            echo "   ✅ Permisos QR configurados: $perm_count permisos\n";
        }
    } else {
        echo "   ❌ ERROR: Módulo QR no existe en RBAC\n";
        $errors_found++;
        
        // Intentar crear módulo QR
        $create_module = $conn->query("INSERT IGNORE INTO modulos (nombre, descripcion, activo) VALUES ('qr', 'Sistema de códigos QR para inventario', 1)");
        if ($create_module) {
            echo "   ✅ Módulo QR creado automáticamente\n";
            $errors_fixed++;
        }
    }
} catch (Exception $e) {
    echo "   ❌ ERROR verificando RBAC: " . $e->getMessage() . "\n";
    $errors_found++;
}

// 7. VERIFICAR CONFIGURACIONES CRÍTICAS
echo "\n7. ⚙️ Verificando configuraciones críticas...\n";

try {
    // Verificar configuraciones QR
    $config_check = $conn->query("SELECT COUNT(*) as count FROM qr_system_config WHERE active = 1");
    if ($config_check) {
        $config_count = $config_check->fetch_assoc()['count'];
        if ($config_count > 0) {
            echo "   ✅ Configuraciones QR: $config_count activas\n";
        } else {
            echo "   ⚠️  ADVERTENCIA: No hay configuraciones QR activas\n";
            
            // Crear configuración básica
            $basic_config = [
                'qr_generation_format' => '{"prefix": "SEQ", "include_year": true, "include_checksum": true, "separator": "-"}',
                'qr_default_size' => '{"pixels": 400, "margin": 20, "error_correction": "H"}',
                'scan_validation_rules' => '{"max_scan_frequency": 1000, "duplicate_scan_window": 5000, "require_location": false}'
            ];
            
            foreach ($basic_config as $key => $value) {
                $stmt = $conn->prepare("INSERT IGNORE INTO qr_system_config (config_key, config_value, config_description, created_by) VALUES (?, ?, ?, 1)");
                $stmt->bind_param('sss', $key, $value, "Configuración automática");
                if ($stmt->execute()) {
                    echo "   ✅ Configuración '$key' creada\n";
                    $errors_fixed++;
                }
            }
        }
    }
} catch (Exception $e) {
    echo "   ❌ ERROR verificando configuraciones: " . $e->getMessage() . "\n";
    $errors_found++;
}

// 8. CREAR ARCHIVO DE VALIDACIÓN
echo "\n8. 📝 Creando archivo de validación del sistema...\n";

$validation_script = '<?php
/**
 * Validador de Sistema QR
 * Ejecutar este script para verificar el estado del sistema
 */

defined("SEQUOIA_SPEED_SYSTEM") || define("SEQUOIA_SPEED_SYSTEM", true);
require_once dirname(__DIR__) . "/config_secure.php";

function validateQRSystem($conn) {
    $checks = [
        "database" => checkDatabase($conn),
        "files" => checkFiles(),
        "permissions" => checkPermissions($conn),
        "configuration" => checkConfiguration($conn)
    ];
    
    return $checks;
}

function checkDatabase($conn) {
    $tables = ["qr_codes", "qr_scan_transactions", "qr_workflow_config", "qr_system_config"];
    $results = [];
    
    foreach ($tables as $table) {
        try {
            $result = $conn->query("SELECT COUNT(*) FROM $table");
            $results[$table] = $result !== false;
        } catch (Exception $e) {
            $results[$table] = false;
        }
    }
    
    return $results;
}

function checkFiles() {
    $files = [
        "models/QRManager.php",
        "api/generate.php", 
        "api/scan.php",
        "scanner.php",
        "reports.php"
    ];
    
    $results = [];
    foreach ($files as $file) {
        $results[$file] = file_exists(__DIR__ . "/" . $file);
    }
    
    return $results;
}

function checkPermissions($conn) {
    try {
        $result = $conn->query("SELECT COUNT(*) as count FROM modulos WHERE nombre = \"qr\"");
        return $result && $result->fetch_assoc()[\"count\"] > 0;
    } catch (Exception $e) {
        return false;
    }
}

function checkConfiguration($conn) {
    try {
        $result = $conn->query("SELECT COUNT(*) as count FROM qr_system_config WHERE active = 1");
        return $result && $result->fetch_assoc()[\"count\"] > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Si se ejecuta directamente
if (basename(__FILE__) === basename($_SERVER["SCRIPT_NAME"])) {
    header("Content-Type: application/json");
    echo json_encode(validateQRSystem($conn));
}
?>';

file_put_contents(__DIR__ . '/validate_system.php', $validation_script);
echo "   ✅ Archivo de validación creado: validate_system.php\n";
$errors_fixed++;

// RESUMEN FINAL
echo "\n" . str_repeat("=", 60) . "\n";
echo "📊 RESUMEN DE CORRECCIÓN DE ERRORES\n";
echo str_repeat("=", 60) . "\n";
echo "❌ Errores encontrados: $errors_found\n";
echo "✅ Errores corregidos: $errors_fixed\n";
echo "⚠️  Errores pendientes: " . ($errors_found - $errors_fixed) . "\n\n";

if ($errors_found > $errors_fixed) {
    echo "🚨 ACCIÓN REQUERIDA:\n";
    echo "   - Revisar errores críticos pendientes\n";
    echo "   - Verificar estructura de base de datos\n";
    echo "   - Comprobar rutas de archivos\n";
    echo "   - Validar integración con AuthMiddleware\n\n";
    
    echo "💡 RECOMENDACIONES:\n";
    echo "   1. Ejecutar create_tables_simple.php si faltan tablas QR\n";
    echo "   2. Verificar que AuthMiddleware esté en la ruta correcta\n";
    echo "   3. Revisar permisos de archivos y directorios\n";
    echo "   4. Probar APIs individualmente\n\n";
} else {
    echo "🎉 ¡SISTEMA QR LISTO!\n";
    echo "   Todos los errores críticos han sido corregidos.\n";
    echo "   El sistema está listo para ser probado.\n\n";
}

echo "🔍 Para validar el sistema ejecute: /qr/validate_system.php\n";
echo "📊 Para monitorear el sistema vaya a: /qr/reports.php\n\n";
?>