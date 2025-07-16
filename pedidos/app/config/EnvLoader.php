<?php

/**
 * Cargador de Variables de Entorno
 * Clase segura para cargar configuraciones desde archivo .env
 * 
 * @author Claude Assistant
 * @version 1.0.0
 * @since 2024-12-16
 */

class EnvLoader
{
    private static $loaded = false;
    private static $variables = [];
    private static $instance = null;

    /**
     * Singleton pattern para evitar múltiples cargas
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Cargar variables de entorno desde archivo .env
     * 
     * @param string $path Ruta al archivo .env
     * @return bool True si se cargó correctamente
     */
    public static function load(string $path = null): bool
    {
        if (self::$loaded) {
            return true;
        }

        // Determinar ruta del archivo .env
        if ($path === null) {
            $path = dirname(__DIR__, 2) . '/.env';
        }

        // Verificar si el archivo existe
        if (!file_exists($path)) {
            error_log("❌ Archivo .env no encontrado en: $path");
            return false;
        }

        // Verificar permisos de lectura
        if (!is_readable($path)) {
            error_log("❌ Archivo .env no tiene permisos de lectura: $path");
            return false;
        }

        try {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                // Saltar comentarios y líneas vacías
                if (strpos(trim($line), '#') === 0 || trim($line) === '') {
                    continue;
                }

                // Procesar línea con formato KEY=VALUE
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    
                    $key = trim($key);
                    $value = trim($value);
                    
                    // Remover comillas si existen
                    $value = self::removeQuotes($value);
                    
                    // Procesar variables especiales
                    $value = self::processSpecialValues($value);
                    
                    // Almacenar en array interno y $_ENV
                    self::$variables[$key] = $value;
                    $_ENV[$key] = $value;
                    
                    // También disponible en $_SERVER para compatibilidad
                    $_SERVER[$key] = $value;
                }
            }

            self::$loaded = true;
            error_log("✅ Variables de entorno cargadas correctamente desde: $path");
            return true;

        } catch (Exception $e) {
            error_log("❌ Error cargando variables de entorno: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener variable de entorno con valor por defecto
     * 
     * @param string $key Nombre de la variable
     * @param mixed $default Valor por defecto
     * @return mixed Valor de la variable o default
     */
    public static function get(string $key, $default = null)
    {
        // Prioridad: variables cargadas > $_ENV > $_SERVER > default
        if (isset(self::$variables[$key])) {
            return self::$variables[$key];
        }

        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        return $default;
    }

    /**
     * Verificar si una variable existe
     * 
     * @param string $key Nombre de la variable
     * @return bool True si existe
     */
    public static function has(string $key): bool
    {
        return isset(self::$variables[$key]) || 
               isset($_ENV[$key]) || 
               isset($_SERVER[$key]);
    }

    /**
     * Obtener todas las variables cargadas
     * 
     * @return array Variables de entorno
     */
    public static function all(): array
    {
        return self::$variables;
    }

    /**
     * Obtener variable requerida (lanza excepción si no existe)
     * 
     * @param string $key Nombre de la variable
     * @return mixed Valor de la variable
     * @throws Exception Si la variable no existe
     */
    public static function getRequired(string $key)
    {
        $value = self::get($key);
        
        if ($value === null) {
            throw new Exception("Variable de entorno requerida no encontrada: $key");
        }
        
        return $value;
    }

    /**
     * Remover comillas de un valor
     * 
     * @param string $value Valor a procesar
     * @return string Valor sin comillas
     */
    private static function removeQuotes(string $value): string
    {
        // Remover comillas dobles
        if (strlen($value) >= 2 && $value[0] === '"' && $value[-1] === '"') {
            return substr($value, 1, -1);
        }
        
        // Remover comillas simples
        if (strlen($value) >= 2 && $value[0] === "'" && $value[-1] === "'") {
            return substr($value, 1, -1);
        }
        
        return $value;
    }

    /**
     * Procesar valores especiales como booleanos
     * 
     * @param string $value Valor a procesar
     * @return mixed Valor procesado
     */
    private static function processSpecialValues(string $value)
    {
        // Valores booleanos
        $lowerValue = strtolower($value);
        
        if (in_array($lowerValue, ['true', 'yes', '1', 'on'])) {
            return true;
        }
        
        if (in_array($lowerValue, ['false', 'no', '0', 'off', ''])) {
            return false;
        }
        
        // Valores nulos
        if (in_array($lowerValue, ['null', 'none', ''])) {
            return null;
        }
        
        // Números
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float)$value : (int)$value;
        }
        
        return $value;
    }

    /**
     * Validar configuración crítica
     * 
     * @return array Errores encontrados
     */
    public static function validate(): array
    {
        $errors = [];
        
        // Variables críticas requeridas
        $required = [
            'DB_HOST' => 'Host de base de datos',
            'DB_USERNAME' => 'Usuario de base de datos',
            'DB_PASSWORD' => 'Contraseña de base de datos',
            'DB_DATABASE' => 'Nombre de base de datos'
        ];
        
        foreach ($required as $key => $description) {
            if (!self::has($key) || empty(self::get($key))) {
                $errors[] = "❌ Variable requerida faltante: $key ($description)";
            }
        }
        
        // Validaciones específicas
        if (self::has('DB_PASSWORD')) {
            $password = self::get('DB_PASSWORD');
            if (strlen($password) < 8) {
                $errors[] = "⚠️ Contraseña de BD muy corta (mínimo 8 caracteres)";
            }
            if ($password === 'your_secure_password_here') {
                $errors[] = "❌ Contraseña de BD no ha sido configurada";
            }
        }
        
        return $errors;
    }

    /**
     * Generar claves de seguridad aleatorias
     * 
     * @param int $length Longitud de la clave
     * @return string Clave generada
     */
    public static function generateSecureKey(int $length = 32): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()_+-=[]{}|;:,.<>?';
        $key = '';
        
        for ($i = 0; $i < $length; $i++) {
            $key .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        return $key;
    }
}

// Función helper global para facilitar el uso
if (!function_exists('env')) {
    /**
     * Función helper para obtener variables de entorno
     * 
     * @param string $key Nombre de la variable
     * @param mixed $default Valor por defecto
     * @return mixed Valor de la variable
     */
    function env(string $key, $default = null)
    {
        return EnvLoader::get($key, $default);
    }
}

if (!function_exists('env_required')) {
    /**
     * Función helper para obtener variables requeridas
     * 
     * @param string $key Nombre de la variable
     * @return mixed Valor de la variable
     * @throws Exception Si no existe
     */
    function env_required(string $key)
    {
        return EnvLoader::getRequired($key);
    }
}