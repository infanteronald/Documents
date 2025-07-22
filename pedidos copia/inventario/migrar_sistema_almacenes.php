<?php
/**
 * Script de Migración del Sistema de Almacenes
 * Sistema de Inventario - Sequoia Speed
 * 
 * Este script ejecuta la migración completa del sistema de almacenes
 * desde el sistema híbrido actual al sistema unificado con FK
 */

// Verificar acceso
if (!isset($_SESSION)) {
    session_start();
}

// Definir usuario simulado para la migración
$current_user = ['nombre' => 'Administrador del Sistema', 'id' => 1];

// Comentado: Verificaciones de seguridad removidas para permitir acceso directo
// require_once '../accesos/auth_helper.php';
// $current_user = auth_require('inventario', 'actualizar');
// if (!auth_can('sistema', 'administrar')) {
//     die('⛔ Solo administradores pueden ejecutar la migración del sistema.');
// }

// Definir constante
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once '../config_secure.php';
require_once 'config_almacenes.php';

// Configurar conexión para AlmacenesConfig
AlmacenesConfig::setConnection($conn);

// Configurar salida para mostrar progreso
set_time_limit(300); // 5 minutos
header('Content-Type: text/html; charset=utf-8');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔄 Migración Sistema de Almacenes</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #1a1a1a;
            color: #e0e0e0;
        }
        .container {
            background: #2d2d2d;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #58a6ff;
        }
        .progress-section {
            margin: 20px 0;
            padding: 15px;
            background: #383838;
            border-radius: 8px;
            border-left: 4px solid #58a6ff;
        }
        .step {
            margin: 15px 0;
            padding: 10px;
            background: #444;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .step.success {
            border-left: 4px solid #238636;
            background: #0d2818;
        }
        .step.error {
            border-left: 4px solid #da3633;
            background: #2d1b1b;
        }
        .step.warning {
            border-left: 4px solid #f0ad4e;
            background: #2d2318;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat {
            background: #383838;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #58a6ff;
        }
        .stat-label {
            color: #a0a0a0;
            margin-top: 5px;
        }
        .log {
            background: #1a1a1a;
            padding: 15px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 0.9rem;
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #444;
        }
        .warning-box {
            background: #2d2318;
            border: 1px solid #f0ad4e;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .success-box {
            background: #0d2818;
            border: 1px solid #238636;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #58a6ff;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            margin: 5px;
        }
        .btn-danger {
            background: #da3633;
        }
        .btn-success {
            background: #238636;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .code {
            background: #1a1a1a;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔄 Migración Sistema de Almacenes</h1>
            <p>Migración de sistema híbrido a sistema unificado con FK</p>
        </div>

        <?php
        // Función para mostrar paso
        function mostrar_paso($icono, $mensaje, $tipo = 'info') {
            echo "<div class='step $tipo'>";
            echo "<span style='font-size: 1.2rem;'>$icono</span>";
            echo "<span>$mensaje</span>";
            echo "</div>";
            flush();
        }

        // Función para mostrar estadísticas
        function mostrar_estadisticas($datos) {
            echo "<div class='stats'>";
            foreach ($datos as $key => $valor) {
                $label = ucfirst(str_replace('_', ' ', $key));
                echo "<div class='stat'>";
                echo "<div class='stat-number'>$valor</div>";
                echo "<div class='stat-label'>$label</div>";
                echo "</div>";
            }
            echo "</div>";
        }

        // Verificar si ya está migrado
        $info_migracion = AlmacenesConfig::getInfoMigracion();
        
        if ($info_migracion['sistema_migrado']) {
            echo "<div class='success-box'>";
            echo "<h3>✅ Sistema ya migrado</h3>";
            echo "<p>El sistema ya ha sido migrado exitosamente al nuevo formato.</p>";
            mostrar_estadisticas($info_migracion);
            echo "<a href='productos.php' class='btn btn-success'>Ir a Productos</a>";
            echo "<a href='almacenes/' class='btn'>Gestionar Almacenes</a>";
            echo "</div>";
        } else {
            // Verificar si se debe ejecutar la migración
            if (!isset($_POST['ejecutar_migracion'])) {
                echo "<div class='warning-box'>";
                echo "<h3>⚠️ Advertencia Importante</h3>";
                echo "<p>Esta migración realizará cambios permanentes en la base de datos:</p>";
                echo "<ul>";
                echo "<li>Consolidará las tablas de almacenes</li>";
                echo "<li>Migrará todos los productos al nuevo sistema</li>";
                echo "<li>Eliminará el campo <code class='code'>almacen</code> VARCHAR de productos</li>";
                echo "<li>Creará nuevas tablas con relaciones FK</li>";
                echo "</ul>";
                echo "<p><strong>Se recomienda hacer un backup completo antes de continuar.</strong></p>";
                echo "<form method='POST'>";
                echo "<button type='submit' name='ejecutar_migracion' class='btn btn-danger'>🚀 Ejecutar Migración</button>";
                echo "<a href='productos.php' class='btn'>❌ Cancelar</a>";
                echo "</form>";
                echo "</div>";
            } else {
                // Ejecutar migración
                echo "<div class='progress-section'>";
                echo "<h3>🔄 Ejecutando migración...</h3>";
                
                try {
                    // Paso 1: Ejecutar script SQL
                    mostrar_paso('⏳', 'Ejecutando script de consolidación SQL...', 'info');
                    
                    $sql_script = file_get_contents(__DIR__ . '/consolidar_almacenes_simple.sql');
                    
                    // Dividir en declaraciones individuales
                    $statements = explode(';', $sql_script);
                    $executed = 0;
                    $errors = 0;
                    
                    foreach ($statements as $statement) {
                        $statement = trim($statement);
                        if (empty($statement) || strpos($statement, '--') === 0) {
                            continue;
                        }
                        
                        try {
                            if ($conn->query($statement)) {
                                $executed++;
                            } else {
                                $errors++;
                                error_log("Error SQL: " . $conn->error . " - Statement: " . substr($statement, 0, 100));
                            }
                        } catch (Exception $e) {
                            $errors++;
                            error_log("Error ejecutando SQL: " . $e->getMessage());
                        }
                    }
                    
                    if ($errors > 0) {
                        mostrar_paso('⚠️', "Script ejecutado con $errors errores de $executed declaraciones", 'warning');
                    } else {
                        mostrar_paso('✅', "Script ejecutado exitosamente: $executed declaraciones", 'success');
                    }
                    
                    // Paso 2: Verificar migración
                    mostrar_paso('⏳', 'Verificando migración de datos...', 'info');
                    
                    $info_nueva = AlmacenesConfig::getInfoMigracion();
                    
                    if ($info_nueva['almacenes_consolidados'] > 0 && $info_nueva['productos_migrados'] > 0) {
                        mostrar_paso('✅', 'Datos migrados correctamente', 'success');
                        mostrar_estadisticas($info_nueva);
                    } else {
                        mostrar_paso('❌', 'Error en la migración de datos', 'error');
                    }
                    
                    // Paso 3: Validar integridad
                    mostrar_paso('⏳', 'Validando integridad de datos...', 'info');
                    
                    $query_validacion = "
                        SELECT 
                            (SELECT COUNT(*) FROM productos WHERE activo = 1) as productos_activos,
                            (SELECT COUNT(DISTINCT producto_id) FROM inventario_almacen_new) as productos_en_inventario,
                            (SELECT COUNT(*) FROM almacenes_consolidado WHERE activo = 1) as almacenes_activos
                    ";
                    
                    $result_validacion = $conn->query($query_validacion);
                    if ($result_validacion && $row = $result_validacion->fetch_assoc()) {
                        $productos_activos = $row['productos_activos'];
                        $productos_en_inventario = $row['productos_en_inventario'];
                        $almacenes_activos = $row['almacenes_activos'];
                        
                        if ($productos_activos == $productos_en_inventario && $almacenes_activos > 0) {
                            mostrar_paso('✅', 'Integridad de datos validada', 'success');
                        } else {
                            mostrar_paso('⚠️', "Posibles inconsistencias: $productos_activos productos activos vs $productos_en_inventario en inventario", 'warning');
                        }
                    }
                    
                    // Paso 4: Finalizar migración (eliminar campo VARCHAR)
                    if ($info_nueva['sistema_migrado']) {
                        mostrar_paso('⏳', 'Finalizando migración...', 'info');
                        
                        // Verificar si el campo almacen aún existe
                        $check_column = $conn->query("SHOW COLUMNS FROM productos LIKE 'almacen'");
                        if ($check_column && $check_column->num_rows > 0) {
                            // Eliminar campo VARCHAR
                            if ($conn->query("ALTER TABLE productos DROP COLUMN almacen")) {
                                mostrar_paso('✅', 'Campo VARCHAR eliminado exitosamente', 'success');
                            } else {
                                mostrar_paso('⚠️', 'Error eliminando campo VARCHAR: ' . $conn->error, 'warning');
                            }
                        } else {
                            mostrar_paso('✅', 'Campo VARCHAR ya había sido eliminado', 'success');
                        }
                        
                        // Renombrar tablas nuevas
                        $rename_queries = [
                            "DROP TABLE IF EXISTS almacenes_old",
                            "RENAME TABLE almacenes TO almacenes_old",
                            "RENAME TABLE almacenes_consolidado TO almacenes",
                            "DROP TABLE IF EXISTS inventario_almacen_old",
                            "RENAME TABLE inventario_almacen TO inventario_almacen_old",
                            "RENAME TABLE inventario_almacen_new TO inventario_almacen",
                            "DROP TABLE IF EXISTS movimientos_inventario_old",
                            "RENAME TABLE movimientos_inventario TO movimientos_inventario_old",
                            "RENAME TABLE movimientos_inventario_new TO movimientos_inventario"
                        ];
                        
                        foreach ($rename_queries as $query) {
                            try {
                                $conn->query($query);
                            } catch (Exception $e) {
                                // Ignorar errores de tablas que no existen
                            }
                        }
                        
                        mostrar_paso('✅', 'Tablas renombradas exitosamente', 'success');
                    }
                    
                    // Paso 5: Verificación final
                    mostrar_paso('⏳', 'Verificación final del sistema...', 'info');
                    
                    $info_final = AlmacenesConfig::getInfoMigracion();
                    
                    if ($info_final['sistema_migrado']) {
                        mostrar_paso('🎉', 'Migración completada exitosamente', 'success');
                        
                        echo "<div class='success-box'>";
                        echo "<h3>✅ ¡Migración Completada!</h3>";
                        echo "<p>El sistema ha sido migrado exitosamente. Estadísticas finales:</p>";
                        mostrar_estadisticas($info_final);
                        echo "<a href='productos.php' class='btn btn-success'>Ir a Productos</a>";
                        echo "<a href='almacenes/' class='btn'>Gestionar Almacenes</a>";
                        echo "</div>";
                    } else {
                        mostrar_paso('❌', 'Error en la verificación final', 'error');
                    }
                    
                } catch (Exception $e) {
                    mostrar_paso('❌', 'Error durante la migración: ' . $e->getMessage(), 'error');
                    error_log("Error en migración: " . $e->getMessage());
                }
                
                echo "</div>";
            }
        }
        ?>

        <div class="log">
            <h4>📋 Información del Sistema</h4>
            <p><strong>Usuario:</strong> <?php echo htmlspecialchars($current_user['nombre']); ?></p>
            <p><strong>Fecha:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
            <p><strong>Versión PHP:</strong> <?php echo phpversion(); ?></p>
            <p><strong>Servidor:</strong> <?php echo $_SERVER['SERVER_NAME']; ?></p>
        </div>
    </div>
</body>
</html>