<?php
// Script para verificar conexión y credenciales de BD en servidor remoto
echo "=== VERIFICACIÓN CONEXIÓN BASE DE DATOS ===\n\n";

// Intentar leer el archivo de conexión existente
if (file_exists('conexion.php')) {
    echo "✅ Archivo conexion.php encontrado\n";
    
    // Leer el contenido del archivo
    $contenido = file_get_contents('conexion.php');
    
    // Extraer información de conexión (método básico)
    if (preg_match('/\$servidor\s*=\s*["\']([^"\']+)["\']/', $contenido, $matches)) {
        $servidor = $matches[1];
        echo "Servidor: $servidor\n";
    }
    
    if (preg_match('/\$usuario\s*=\s*["\']([^"\']+)["\']/', $contenido, $matches)) {
        $usuario = $matches[1];
        echo "Usuario: $usuario\n";
    }
    
    if (preg_match('/\$base_datos\s*=\s*["\']([^"\']+)["\']/', $contenido, $matches)) {
        $base_datos = $matches[1];
        echo "Base de datos: $base_datos\n";
    }
    
    echo "\n";
    
    // Intentar incluir y usar la conexión
    try {
        include 'conexion.php';
        
        if (isset($conn) && $conn instanceof mysqli) {
            if ($conn->ping()) {
                echo "✅ Conexión a la base de datos EXITOSA\n";
                
                // Verificar tablas principales
                echo "\n--- Verificando tablas principales ---\n";
                $tablas = ['pedidos_detal', 'pedido_detalle', 'bold_transactions'];
                
                foreach ($tablas as $tabla) {
                    $result = $conn->query("SHOW TABLES LIKE '$tabla'");
                    if ($result && $result->num_rows > 0) {
                        // Contar registros
                        $count_result = $conn->query("SELECT COUNT(*) as total FROM $tabla");
                        $count = $count_result->fetch_assoc()['total'];
                        echo "✅ Tabla '$tabla' existe - $count registros\n";
                    } else {
                        echo "❌ Tabla '$tabla' no encontrada\n";
                    }
                }
                
            } else {
                echo "❌ Conexión establecida pero no responde (ping failed)\n";
            }
        } else {
            echo "❌ Variable de conexión no válida\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Error al conectar: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "❌ Archivo conexion.php no encontrado\n";
    echo "Buscando archivos de configuración alternativos...\n";
    
    // Buscar otros archivos de configuración
    $config_files = glob('*config*.php');
    $conexion_files = glob('*conexion*.php');
    $db_files = glob('*db*.php');
    
    $all_files = array_merge($config_files, $conexion_files, $db_files);
    
    if (!empty($all_files)) {
        echo "Archivos de configuración encontrados:\n";
        foreach ($all_files as $file) {
            echo "- $file\n";
        }
    } else {
        echo "No se encontraron archivos de configuración\n";
    }
}

echo "\n=== FIN VERIFICACIÓN ===\n";
?>
