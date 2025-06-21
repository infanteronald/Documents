<?php
/**
 * Script para limpiar la base de datos de producción
 * USAR CON EXTREMA PRECAUCIÓN - ELIMINA TODOS LOS DATOS
 */

// Incluir conexión a la base de datos
require_once 'conexion.php';

// Verificar que estamos en el entorno correcto
$confirm = readline("¿Estás seguro de que quieres limpiar la base de datos? (escribe 'SI_ESTOY_SEGURO' para continuar): ");

if ($confirm !== 'SI_ESTOY_SEGURO') {
    echo "Operación cancelada por seguridad.\n";
    exit(1);
}

echo "Iniciando limpieza de base de datos...\n";

try {
    // Leer el archivo SQL
    $sql_content = file_get_contents('limpiar_bd_produccion.sql');

    if ($sql_content === false) {
        throw new Exception("No se pudo leer el archivo SQL");
    }

    // Dividir en comandos individuales
    $commands = array_filter(
        array_map('trim', explode(';', $sql_content)),
        function($cmd) {
            return !empty($cmd) && !preg_match('/^--/', $cmd);
        }
    );

    echo "Ejecutando " . count($commands) . " comandos SQL...\n";

    // Ejecutar cada comando
    foreach ($commands as $index => $command) {
        if (empty(trim($command))) continue;

        echo "Ejecutando comando " . ($index + 1) . "...\n";

        if (!$conn->query($command)) {
            // No fallar si la tabla no existe, solo mostrar advertencia
            if (strpos($conn->error, "doesn't exist") !== false) {
                echo "Advertencia: " . $conn->error . "\n";
            } else {
                throw new Exception("Error en comando " . ($index + 1) . ": " . $conn->error);
            }
        }
    }

    echo "✅ Base de datos limpiada exitosamente!\n";
    echo "✅ AUTO_INCREMENT reseteado a 1 en todas las tablas\n";
    echo "✅ Listo para producción\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
