<?php
/**
 * Script de Verificación de Implementación de Seguridad
 * Verifica que todos los componentes de seguridad estén correctamente configurados
 * 
 * @author Claude Assistant
 * @version 1.0.0
 * @since 2024-12-16
 */

echo "\n";
echo "=========================================\n";
echo "  VERIFICACIÓN DE SEGURIDAD - SEQUOIA SPEED\n";
echo "=========================================\n";
echo "\n";

$errors = [];
$warnings = [];
$success = [];

// 1. Verificar archivo .env
echo "[1/7] Verificando archivo .env...\n";
if (file_exists('.env')) {
    $success[] = "✅ Archivo .env encontrado";
    
    // Verificar permisos
    $perms = fileperms('.env');
    $octal = substr(sprintf('%o', $perms), -4);
    if ($octal === '0600' || $octal === '0644') {
        $success[] = "✅ Permisos de .env correctos: $octal";
    } else {
        $warnings[] = "⚠️  Permisos de .env: $octal (recomendado: 0600 o 0644)";
    }
    
    // Verificar variables críticas
    $env_content = file_get_contents('.env');
    $required_vars = ['DB_HOST', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'APP_ENV', 'APP_DEBUG'];
    foreach ($required_vars as $var) {
        if (strpos($env_content, "$var=") !== false) {
            $success[] = "✅ Variable $var definida";
        } else {
            $errors[] = "❌ Variable $var NO encontrada";
        }
    }
} else {
    $errors[] = "❌ Archivo .env NO encontrado";
}

// 2. Verificar EnvLoader
echo "\n[2/7] Verificando EnvLoader...\n";
if (file_exists('app/config/EnvLoader.php')) {
    $success[] = "✅ EnvLoader.php encontrado";
    
    // Intentar cargar EnvLoader
    require_once 'app/config/EnvLoader.php';
    if (class_exists('EnvLoader')) {
        $success[] = "✅ Clase EnvLoader disponible";
        
        // Verificar que las funciones helper existen
        if (function_exists('env') && function_exists('env_required')) {
            $success[] = "✅ Funciones helper env() y env_required() disponibles";
        } else {
            $errors[] = "❌ Funciones helper NO disponibles";
        }
    } else {
        $errors[] = "❌ Clase EnvLoader NO se pudo cargar";
    }
} else {
    $errors[] = "❌ EnvLoader.php NO encontrado";
}

// 3. Verificar config_secure.php
echo "\n[3/7] Verificando config_secure.php...\n";
if (file_exists('config_secure.php')) {
    $success[] = "✅ config_secure.php encontrado";
    
    // Verificar que conexion.php redirige a config_secure.php
    if (file_exists('conexion.php')) {
        $conexion_content = file_get_contents('conexion.php');
        if (strpos($conexion_content, 'config_secure.php') !== false) {
            $success[] = "✅ conexion.php redirige correctamente a config_secure.php";
        } else {
            $errors[] = "❌ conexion.php NO redirige a config_secure.php";
        }
    }
} else {
    $errors[] = "❌ config_secure.php NO encontrado";
}

// 4. Verificar archivos de backup
echo "\n[4/7] Verificando archivos de backup...\n";
$backup_files = glob('*.backup*');
if (count($backup_files) > 0) {
    $warnings[] = "⚠️  Se encontraron " . count($backup_files) . " archivos de backup:";
    foreach ($backup_files as $file) {
        $warnings[] = "   - $file";
    }
    $warnings[] = "   Considera eliminarlos después de verificar el funcionamiento";
} else {
    $success[] = "✅ No se encontraron archivos de backup";
}

// 5. Verificar conexión a base de datos
echo "\n[5/7] Verificando conexión a base de datos...\n";
try {
    // Definir constante requerida
    defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
    
    // Intentar incluir config_secure.php
    ob_start();
    $db_error = false;
    
    // Capturar cualquier salida o error
    set_error_handler(function($errno, $errstr) use (&$db_error) {
        $db_error = true;
        return true;
    });
    
    include 'config_secure.php';
    
    restore_error_handler();
    $output = ob_get_clean();
    
    if ($db_error || strpos($output, 'Error') !== false) {
        $warnings[] = "⚠️  No se pudo conectar a la base de datos (normal en desarrollo local)";
    } else if (isset($conn) && $conn && !$conn->connect_error) {
        $success[] = "✅ Conexión a base de datos exitosa";
        
        // Verificar charset
        if ($conn->character_set_name() === 'utf8mb4') {
            $success[] = "✅ Charset UTF8MB4 configurado correctamente";
        } else {
            $warnings[] = "⚠️  Charset: " . $conn->character_set_name() . " (esperado: utf8mb4)";
        }
    }
} catch (Exception $e) {
    $warnings[] = "⚠️  Excepción al verificar conexión: " . $e->getMessage();
}

// 6. Verificar archivos sensibles
echo "\n[6/7] Verificando seguridad de archivos sensibles...\n";
$sensitive_patterns = [
    '*.sql' => 'Archivos SQL',
    '*.log' => 'Archivos de log',
    '*.bak' => 'Archivos de respaldo',
    '.env*' => 'Archivos de entorno'
];

foreach ($sensitive_patterns as $pattern => $description) {
    $files = glob($pattern);
    if (count($files) > 0) {
        $warnings[] = "⚠️  Se encontraron " . count($files) . " $description";
    }
}

// Verificar que .env esté en .gitignore
if (file_exists('.gitignore')) {
    $gitignore = file_get_contents('.gitignore');
    if (strpos($gitignore, '.env') !== false) {
        $success[] = "✅ .env está en .gitignore";
    } else {
        $errors[] = "❌ .env NO está en .gitignore - RIESGO DE SEGURIDAD";
    }
}

// 7. Verificar documentación
echo "\n[7/7] Verificando documentación...\n";
$docs = [
    'SEGURIDAD_CORREGIDA.md' => 'Documentación de seguridad',
    '.env.example' => 'Plantilla de variables de entorno',
    'CORRECION_SEGURIDAD_RESUMEN.md' => 'Resumen de correcciones'
];

foreach ($docs as $file => $description) {
    if (file_exists($file)) {
        $success[] = "✅ $description encontrado: $file";
    } else {
        $warnings[] = "⚠️  $description NO encontrado: $file";
    }
}

// Mostrar resumen
echo "\n";
echo "=========================================\n";
echo "  RESUMEN DE VERIFICACIÓN\n";
echo "=========================================\n";
echo "\n";

echo "✅ ÉXITOS: " . count($success) . "\n";
foreach ($success as $msg) {
    echo "   $msg\n";
}

if (count($warnings) > 0) {
    echo "\n⚠️  ADVERTENCIAS: " . count($warnings) . "\n";
    foreach ($warnings as $msg) {
        echo "   $msg\n";
    }
}

if (count($errors) > 0) {
    echo "\n❌ ERRORES: " . count($errors) . "\n";
    foreach ($errors as $msg) {
        echo "   $msg\n";
    }
}

// Resultado final
echo "\n";
echo "=========================================\n";
if (count($errors) === 0) {
    echo "✅ SISTEMA DE SEGURIDAD IMPLEMENTADO CORRECTAMENTE\n";
    if (count($warnings) > 0) {
        echo "   (Con algunas advertencias a revisar)\n";
    }
} else {
    echo "❌ HAY ERRORES QUE DEBEN SER CORREGIDOS\n";
}
echo "=========================================\n";
echo "\n";

// Recomendaciones finales
echo "RECOMENDACIONES:\n";
echo "1. En producción: Ejecutar este script después del despliegue\n";
echo "2. En desarrollo: Copiar .env.example a .env y configurar valores locales\n";
echo "3. Eliminar archivos de backup una vez verificado el funcionamiento\n";
echo "4. Verificar permisos de archivos sensibles\n";
echo "5. Nunca subir .env al repositorio\n";
echo "\n";

// Generar código de salida
exit(count($errors) > 0 ? 1 : 0);
?>