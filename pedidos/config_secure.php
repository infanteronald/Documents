<?php

/**
 * Configuración Segura de Base de Datos
 * Reemplaza conexion.php con manejo seguro de credenciales
 * 
 * @author Claude Assistant
 * @version 1.0.0
 * @since 2024-12-16
 */

// Prevenir acceso directo al archivo
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

// Detectar si se está ejecutando desde CLI para testing
$is_cli = php_sapi_name() === 'cli';

// Cargar el cargador de variables de entorno
require_once __DIR__ . '/app/config/EnvLoader.php';

// Cargar variables de entorno
if (!EnvLoader::load()) {
    die("❌ Error crítico: No se pudieron cargar las variables de entorno");
}

// Validar configuración crítica
$validation_errors = EnvLoader::validate();
if (!empty($validation_errors)) {
    error_log("❌ Errores de configuración encontrados:");
    foreach ($validation_errors as $error) {
        error_log($error);
    }
    
    // En producción, mostrar error genérico
    if (env('APP_ENV') === 'production') {
        die("❌ Error de configuración del sistema. Contacte al administrador.");
    } else {
        die("❌ Errores de configuración:\n" . implode("\n", $validation_errors));
    }
}

try {
    // Configurar parámetros de conexión desde variables de entorno
    $db_config = [
        'host' => env_required('DB_HOST'),
        'username' => env_required('DB_USERNAME'),
        'password' => env_required('DB_PASSWORD'),
        'database' => env_required('DB_DATABASE'),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
        'port' => env('DB_PORT', 3306)
    ];

    // Crear conexión MySQL con manejo de errores mejorado
    $conn = new mysqli(
        $db_config['host'],
        $db_config['username'],
        $db_config['password'],
        $db_config['database'],
        $db_config['port']
    );

    // Verificar conexión
    if ($conn->connect_error) {
        // Log del error real para depuración
        error_log("❌ Error de conexión DB: " . $conn->connect_error);
        error_log("❌ Código de error: " . $conn->connect_errno);
        error_log("❌ Host: " . $db_config['host']);
        error_log("❌ Usuario: " . $db_config['username']);
        error_log("❌ Base de datos: " . $db_config['database']);
        
        // Si es CLI (testing), no enviar headers
        if ($is_cli) {
            echo "❌ Error de conexión DB: " . $conn->connect_error . "\n";
            echo "❌ Código de error: " . $conn->connect_errno . "\n";
            return; // No salir para permitir testing
        }
        
        // Respuesta segura al cliente web
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Error crítico: No se pudo conectar a la base de datos.',
            'code' => 'DB_CONNECTION_ERROR',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }

    // Configurar charset de forma segura (solo si la conexión es exitosa)
    if (!$conn->connect_error && !$conn->set_charset($db_config['charset'])) {
        error_log("❌ Error configurando charset: " . $conn->error);
        
        // Si es CLI (testing), no enviar headers
        if ($is_cli) {
            echo "❌ Error configurando charset: " . $conn->error . "\n";
            return; // No salir para permitir testing
        }
        
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Error crítico: Configuración de codificación de base de datos fallida.',
            'code' => 'DB_CHARSET_ERROR',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        $conn->close();
        exit;
    }

    // Configurar timezone de MySQL (usar offset en lugar de nombre de zona)
    $timezone_offset = env('DB_TIMEZONE', '-05:00'); // Colombia UTC-5
    $conn->query("SET time_zone = '$timezone_offset'");

    // Configurar modo SQL para mayor compatibilidad
    $conn->query("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");

    // Configurar autocommit
    $conn->autocommit(true);

    // Log de conexión exitosa (sin datos sensibles)
    if (env('APP_DEBUG', false)) {
        error_log("✅ Conexión BD exitosa - Host: " . $db_config['host'] . " - DB: " . $db_config['database']);
    }

    // Función helper para ejecutar consultas preparadas de forma segura
    function ejecutar_consulta_segura($conn, $sql, $params = [], $types = '')
    {
        try {
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                error_log("❌ Error preparando consulta: " . $conn->error);
                return false;
            }

            if (!empty($params)) {
                if (empty($types)) {
                    // Auto-detectar tipos
                    $types = str_repeat('s', count($params));
                }
                $stmt->bind_param($types, ...$params);
            }

            $result = $stmt->execute();
            
            if (!$result) {
                error_log("❌ Error ejecutando consulta: " . $stmt->error);
                return false;
            }

            return $stmt;

        } catch (Exception $e) {
            error_log("❌ Excepción en consulta: " . $e->getMessage());
            return false;
        }
    }

    // Función helper para obtener configuración de BD
    function get_db_config($key, $default = null)
    {
        $config = [
            'host' => env('DB_HOST'),
            'username' => env('DB_USERNAME'),
            'database' => env('DB_DATABASE'),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'port' => env('DB_PORT', 3306)
        ];
        
        return $config[$key] ?? $default;
    }

    // Configurar manejo de errores específico para BD
    function manejar_error_db($conn, $context = '')
    {
        $error_info = [
            'error' => $conn->error,
            'errno' => $conn->errno,
            'context' => $context,
            'timestamp' => date('Y-m-d H:i:s'),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        ];
        
        error_log("❌ Error BD: " . json_encode($error_info));
        
        // En producción, no mostrar detalles del error
        if (env('APP_ENV') === 'production') {
            return [
                'success' => false,
                'error' => 'Error interno del servidor',
                'code' => 'INTERNAL_ERROR'
            ];
        } else {
            return [
                'success' => false,
                'error' => $conn->error,
                'errno' => $conn->errno,
                'context' => $context
            ];
        }
    }

    // Registrar shutdown function para cerrar conexión
    register_shutdown_function(function() use ($conn) {
        if ($conn && !$conn->connect_error) {
            $conn->close();
        }
    });

} catch (Exception $e) {
    error_log("❌ Excepción crítica en configuración DB: " . $e->getMessage());
    error_log("❌ Stack trace: " . $e->getTraceAsString());
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error crítico del sistema',
        'code' => 'SYSTEM_ERROR',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Verificar si la conexión sigue activa (para scripts de larga duración)
function verificar_conexion_db($conn)
{
    if (!$conn->ping()) {
        error_log("⚠️ Conexión BD perdida, reintentando...");
        
        // Reintentar conexión
        $conn = new mysqli(
            env('DB_HOST'),
            env('DB_USERNAME'),
            env('DB_PASSWORD'),
            env('DB_DATABASE'),
            env('DB_PORT', 3306)
        );
        
        if ($conn->connect_error) {
            error_log("❌ Error reconectando DB: " . $conn->connect_error);
            return false;
        }
        
        $conn->set_charset(env('DB_CHARSET', 'utf8mb4'));
        error_log("✅ Reconexión BD exitosa");
    }
    
    return $conn;
}

// Función para obtener estadísticas de conexión (opcional)
function obtener_stats_conexion($conn)
{
    if (env('APP_DEBUG', false)) {
        return [
            'server_info' => $conn->server_info,
            'host_info' => $conn->host_info,
            'protocol_version' => $conn->protocol_version,
            'client_info' => $conn->client_info,
            'character_set' => $conn->character_set_name(),
            'stat' => $conn->stat()
        ];
    }
    
    return null;
}