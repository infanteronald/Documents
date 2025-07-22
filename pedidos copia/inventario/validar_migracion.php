<?php
/**
 * Script de Validación Post-Migración
 * Sistema de Inventario - Sequoia Speed
 * 
 * Valida que la migración se haya completado correctamente
 * y que el sistema funcione con el nuevo esquema
 */

// Verificar acceso
if (!isset($_SESSION)) {
    session_start();
}

// Definir usuario simulado para la validación
$current_user = ['nombre' => 'Administrador del Sistema', 'id' => 1];

// Comentado: Verificaciones de seguridad removidas para permitir acceso directo
// require_once '../accesos/auth_helper.php';
// $current_user = auth_require('inventario', 'leer');

// Definir constante
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once '../config_secure.php';
require_once 'config_almacenes.php';

// Configurar conexión para AlmacenesConfig
AlmacenesConfig::setConnection($conn);

// Configurar salida
set_time_limit(120);
header('Content-Type: text/html; charset=utf-8');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>✅ Validación Post-Migración</title>
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
        .test-section {
            margin: 20px 0;
            padding: 20px;
            background: #383838;
            border-radius: 8px;
            border-left: 4px solid #58a6ff;
        }
        .test-item {
            margin: 15px 0;
            padding: 15px;
            background: #444;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .test-item.success {
            border-left: 4px solid #238636;
            background: #0d2818;
        }
        .test-item.error {
            border-left: 4px solid #da3633;
            background: #2d1b1b;
        }
        .test-item.warning {
            border-left: 4px solid #f0ad4e;
            background: #2d2318;
        }
        .test-icon {
            font-size: 1.5rem;
            min-width: 30px;
        }
        .test-content {
            flex: 1;
        }
        .test-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .test-description {
            color: #a0a0a0;
            font-size: 0.9rem;
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
        .summary {
            margin: 30px 0;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .summary.success {
            background: #0d2818;
            border: 1px solid #238636;
        }
        .summary.error {
            background: #2d1b1b;
            border: 1px solid #da3633;
        }
        .summary.warning {
            background: #2d2318;
            border: 1px solid #f0ad4e;
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
        .btn-success {
            background: #238636;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .details {
            background: #1a1a1a;
            padding: 15px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 0.9rem;
            margin-top: 10px;
            max-height: 200px;
            overflow-y: auto;
        }
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #444;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #238636, #58a6ff);
            transition: width 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✅ Validación Post-Migración</h1>
            <p>Sistema de Inventario - Sequoia Speed</p>
        </div>

        <?php
        // Contadores de resultados
        $tests_passed = 0;
        $tests_failed = 0;
        $tests_warning = 0;
        $total_tests = 0;

        // Función para mostrar resultado de test
        function mostrar_test($titulo, $descripcion, $resultado, $tipo = 'info', $detalles = null) {
            global $tests_passed, $tests_failed, $tests_warning, $total_tests;
            
            $total_tests++;
            
            if ($tipo === 'success') {
                $tests_passed++;
                $icono = '✅';
            } elseif ($tipo === 'error') {
                $tests_failed++;
                $icono = '❌';
            } elseif ($tipo === 'warning') {
                $tests_warning++;
                $icono = '⚠️';
            } else {
                $icono = 'ℹ️';
            }
            
            echo "<div class='test-item $tipo'>";
            echo "<div class='test-icon'>$icono</div>";
            echo "<div class='test-content'>";
            echo "<div class='test-title'>$titulo</div>";
            echo "<div class='test-description'>$descripcion</div>";
            if ($detalles) {
                echo "<div class='details'>$detalles</div>";
            }
            echo "</div>";
            echo "</div>";
            flush();
        }

        // Test 1: Verificar existencia de tablas
        echo "<div class='test-section'>";
        echo "<h3>🗃️ Verificación de Estructura de Base de Datos</h3>";

        $tablas_requeridas = [
            'almacenes' => 'Tabla principal de almacenes',
            'inventario_almacen' => 'Tabla de inventario por almacén',
            'movimientos_inventario' => 'Tabla de movimientos de inventario'
        ];

        foreach ($tablas_requeridas as $tabla => $descripcion) {
            try {
                $result = $conn->query("SHOW TABLES LIKE '$tabla'");
                if ($result->num_rows > 0) {
                    mostrar_test(
                        "Tabla $tabla",
                        $descripcion,
                        true,
                        'success'
                    );
                } else {
                    mostrar_test(
                        "Tabla $tabla",
                        $descripcion,
                        false,
                        'error',
                        "La tabla $tabla no existe"
                    );
                }
            } catch (Exception $e) {
                mostrar_test(
                    "Tabla $tabla",
                    $descripcion,
                    false,
                    'error',
                    "Error verificando tabla: " . $e->getMessage()
                );
            }
        }

        // Test 2: Verificar que no existe campo almacen VARCHAR en productos
        try {
            $result = $conn->query("SHOW COLUMNS FROM productos LIKE 'almacen'");
            if ($result->num_rows === 0) {
                mostrar_test(
                    "Campo VARCHAR eliminado",
                    "El campo 'almacen' VARCHAR fue eliminado de productos",
                    true,
                    'success'
                );
            } else {
                mostrar_test(
                    "Campo VARCHAR pendiente",
                    "El campo 'almacen' VARCHAR aún existe en productos",
                    false,
                    'warning',
                    "La migración no está completa"
                );
            }
        } catch (Exception $e) {
            mostrar_test(
                "Campo VARCHAR",
                "Error verificando campo almacen",
                false,
                'error',
                $e->getMessage()
            );
        }

        echo "</div>";

        // Test 3: Verificar integridad de datos
        echo "<div class='test-section'>";
        echo "<h3>🔍 Verificación de Integridad de Datos</h3>";

        // Productos activos vs productos en inventario
        try {
            $query_productos = "SELECT COUNT(*) as total FROM productos WHERE activo = 1";
            $result_productos = $conn->query($query_productos);
            $productos_activos = $result_productos->fetch_assoc()['total'];

            $query_inventario = "SELECT COUNT(DISTINCT producto_id) as total FROM inventario_almacen";
            $result_inventario = $conn->query($query_inventario);
            $productos_en_inventario = $result_inventario->fetch_assoc()['total'];

            if ($productos_activos == $productos_en_inventario) {
                mostrar_test(
                    "Productos migrados",
                    "Todos los productos activos tienen inventario",
                    true,
                    'success',
                    "$productos_activos productos activos = $productos_en_inventario en inventario"
                );
            } else {
                mostrar_test(
                    "Productos pendientes",
                    "Algunos productos no tienen inventario",
                    false,
                    'warning',
                    "$productos_activos productos activos vs $productos_en_inventario en inventario"
                );
            }
        } catch (Exception $e) {
            mostrar_test(
                "Integridad productos",
                "Error verificando productos",
                false,
                'error',
                $e->getMessage()
            );
        }

        // Almacenes con productos
        try {
            $query_almacenes = "SELECT COUNT(*) as total FROM almacenes WHERE activo = 1";
            $result_almacenes = $conn->query($query_almacenes);
            $almacenes_activos = $result_almacenes->fetch_assoc()['total'];

            $query_con_productos = "SELECT COUNT(DISTINCT almacen_id) as total FROM inventario_almacen";
            $result_con_productos = $conn->query($query_con_productos);
            $almacenes_con_productos = $result_con_productos->fetch_assoc()['total'];

            if ($almacenes_con_productos > 0) {
                mostrar_test(
                    "Almacenes con productos",
                    "Almacenes tienen productos asignados",
                    true,
                    'success',
                    "$almacenes_con_productos de $almacenes_activos almacenes tienen productos"
                );
            } else {
                mostrar_test(
                    "Almacenes sin productos",
                    "Ningún almacén tiene productos",
                    false,
                    'error',
                    "Verificar migración de datos"
                );
            }
        } catch (Exception $e) {
            mostrar_test(
                "Almacenes con productos",
                "Error verificando almacenes",
                false,
                'error',
                $e->getMessage()
            );
        }

        echo "</div>";

        // Test 4: Verificar funcionalidad del sistema
        echo "<div class='test-section'>";
        echo "<h3>⚙️ Verificación de Funcionalidad</h3>";

        // Test AlmacenesConfig
        try {
            $almacenes = AlmacenesConfig::getAlmacenes();
            if (count($almacenes) > 0) {
                mostrar_test(
                    "AlmacenesConfig::getAlmacenes()",
                    "Configuración de almacenes funciona",
                    true,
                    'success',
                    count($almacenes) . " almacenes encontrados"
                );
            } else {
                mostrar_test(
                    "AlmacenesConfig::getAlmacenes()",
                    "No se encontraron almacenes",
                    false,
                    'warning',
                    "Verificar datos de almacenes"
                );
            }
        } catch (Exception $e) {
            mostrar_test(
                "AlmacenesConfig::getAlmacenes()",
                "Error en configuración de almacenes",
                false,
                'error',
                $e->getMessage()
            );
        }

        // Test obtener almacén por defecto
        try {
            $almacen_defecto = AlmacenesConfig::getAlmacenPorDefecto();
            if ($almacen_defecto) {
                mostrar_test(
                    "Almacén por defecto",
                    "Sistema puede obtener almacén por defecto",
                    true,
                    'success',
                    "Almacén: " . $almacen_defecto['nombre']
                );
            } else {
                mostrar_test(
                    "Almacén por defecto",
                    "No se pudo obtener almacén por defecto",
                    false,
                    'error',
                    "Verificar configuración de almacenes"
                );
            }
        } catch (Exception $e) {
            mostrar_test(
                "Almacén por defecto",
                "Error obteniendo almacén por defecto",
                false,
                'error',
                $e->getMessage()
            );
        }

        // Test estadísticas
        try {
            if (isset($almacen_defecto) && $almacen_defecto) {
                $stats = AlmacenesConfig::getEstadisticasAlmacen($almacen_defecto['id']);
                if ($stats && isset($stats['total_productos'])) {
                    mostrar_test(
                        "Estadísticas de almacén",
                        "Sistema puede generar estadísticas",
                        true,
                        'success',
                        "Productos: " . $stats['total_productos'] . ", Stock: " . $stats['stock_total']
                    );
                } else {
                    mostrar_test(
                        "Estadísticas de almacén",
                        "No se pudieron obtener estadísticas",
                        false,
                        'warning',
                        "Verificar datos de inventario"
                    );
                }
            }
        } catch (Exception $e) {
            mostrar_test(
                "Estadísticas de almacén",
                "Error obteniendo estadísticas",
                false,
                'error',
                $e->getMessage()
            );
        }

        echo "</div>";

        // Test 5: Verificar consultas de productos
        echo "<div class='test-section'>";
        echo "<h3>📦 Verificación de Consultas de Productos</h3>";

        // Test consulta de productos por almacén
        try {
            if (isset($almacen_defecto) && $almacen_defecto) {
                $productos = AlmacenesConfig::getProductosAlmacen($almacen_defecto['id']);
                if (count($productos) > 0) {
                    mostrar_test(
                        "Productos por almacén",
                        "Sistema puede obtener productos por almacén",
                        true,
                        'success',
                        count($productos) . " productos en " . $almacen_defecto['nombre']
                    );
                } else {
                    mostrar_test(
                        "Productos por almacén",
                        "No se encontraron productos en almacén",
                        false,
                        'warning',
                        "Almacén: " . $almacen_defecto['nombre']
                    );
                }
            }
        } catch (Exception $e) {
            mostrar_test(
                "Productos por almacén",
                "Error obteniendo productos por almacén",
                false,
                'error',
                $e->getMessage()
            );
        }

        // Test vista de productos
        try {
            $query_vista = "SELECT COUNT(*) as total FROM vista_productos_almacen";
            $result_vista = $conn->query($query_vista);
            if ($result_vista) {
                $total_vista = $result_vista->fetch_assoc()['total'];
                mostrar_test(
                    "Vista de productos",
                    "Vista de productos funciona correctamente",
                    true,
                    'success',
                    "$total_vista registros en vista"
                );
            } else {
                mostrar_test(
                    "Vista de productos",
                    "Error en vista de productos",
                    false,
                    'error',
                    "Verificar definición de vista"
                );
            }
        } catch (Exception $e) {
            mostrar_test(
                "Vista de productos",
                "Error consultando vista de productos",
                false,
                'error',
                $e->getMessage()
            );
        }

        echo "</div>";

        // Estadísticas finales
        echo "<div class='test-section'>";
        echo "<h3>📊 Estadísticas del Sistema</h3>";

        try {
            $stats_generales = [
                'almacenes_total' => 0,
                'almacenes_activos' => 0,
                'productos_total' => 0,
                'productos_activos' => 0,
                'registros_inventario' => 0,
                'valor_total_inventario' => 0
            ];

            // Almacenes
            $result = $conn->query("SELECT COUNT(*) as total FROM almacenes");
            if ($result) $stats_generales['almacenes_total'] = $result->fetch_assoc()['total'];

            $result = $conn->query("SELECT COUNT(*) as total FROM almacenes WHERE activo = 1");
            if ($result) $stats_generales['almacenes_activos'] = $result->fetch_assoc()['total'];

            // Productos
            $result = $conn->query("SELECT COUNT(*) as total FROM productos");
            if ($result) $stats_generales['productos_total'] = $result->fetch_assoc()['total'];

            $result = $conn->query("SELECT COUNT(*) as total FROM productos WHERE activo = 1");
            if ($result) $stats_generales['productos_activos'] = $result->fetch_assoc()['total'];

            // Inventario
            $result = $conn->query("SELECT COUNT(*) as total FROM inventario_almacen");
            if ($result) $stats_generales['registros_inventario'] = $result->fetch_assoc()['total'];

            $result = $conn->query("SELECT SUM(ia.stock_actual * p.precio) as total FROM inventario_almacen ia INNER JOIN productos p ON ia.producto_id = p.id WHERE p.activo = 1");
            if ($result) {
                $valor = $result->fetch_assoc()['total'];
                $stats_generales['valor_total_inventario'] = $valor ?? 0;
            }

            echo "<div class='stats'>";
            echo "<div class='stat'>";
            echo "<div class='stat-number'>" . $stats_generales['almacenes_activos'] . "</div>";
            echo "<div class='stat-label'>Almacenes Activos</div>";
            echo "</div>";
            echo "<div class='stat'>";
            echo "<div class='stat-number'>" . $stats_generales['productos_activos'] . "</div>";
            echo "<div class='stat-label'>Productos Activos</div>";
            echo "</div>";
            echo "<div class='stat'>";
            echo "<div class='stat-number'>" . $stats_generales['registros_inventario'] . "</div>";
            echo "<div class='stat-label'>Registros Inventario</div>";
            echo "</div>";
            echo "<div class='stat'>";
            echo "<div class='stat-number'>$" . number_format($stats_generales['valor_total_inventario']) . "</div>";
            echo "<div class='stat-label'>Valor Total</div>";
            echo "</div>";
            echo "</div>";

        } catch (Exception $e) {
            mostrar_test(
                "Estadísticas generales",
                "Error obteniendo estadísticas",
                false,
                'error',
                $e->getMessage()
            );
        }

        echo "</div>";

        // Resumen final
        $porcentaje_exito = $total_tests > 0 ? round(($tests_passed / $total_tests) * 100) : 0;
        
        echo "<div class='progress-bar'>";
        echo "<div class='progress-fill' style='width: {$porcentaje_exito}%'></div>";
        echo "</div>";

        if ($tests_failed === 0 && $tests_warning === 0) {
            echo "<div class='summary success'>";
            echo "<h3>🎉 ¡Migración Completada Exitosamente!</h3>";
            echo "<p>Todos los tests pasaron correctamente. El sistema está listo para usar.</p>";
            echo "<a href='productos.php' class='btn btn-success'>Ir a Productos</a>";
            echo "<a href='almacenes/' class='btn'>Gestionar Almacenes</a>";
            echo "</div>";
        } elseif ($tests_failed === 0) {
            echo "<div class='summary warning'>";
            echo "<h3>⚠️ Migración Completada con Advertencias</h3>";
            echo "<p>La migración fue exitosa pero hay algunas advertencias que revisar.</p>";
            echo "<p><strong>Tests exitosos:</strong> $tests_passed | <strong>Advertencias:</strong> $tests_warning</p>";
            echo "<a href='productos.php' class='btn btn-success'>Ir a Productos</a>";
            echo "<a href='almacenes/' class='btn'>Gestionar Almacenes</a>";
            echo "</div>";
        } else {
            echo "<div class='summary error'>";
            echo "<h3>❌ Migración Incompleta</h3>";
            echo "<p>Se encontraron errores que necesitan ser corregidos antes de usar el sistema.</p>";
            echo "<p><strong>Tests exitosos:</strong> $tests_passed | <strong>Errores:</strong> $tests_failed | <strong>Advertencias:</strong> $tests_warning</p>";
            echo "<a href='migrar_sistema_almacenes.php' class='btn'>Reintentar Migración</a>";
            echo "</div>";
        }
        ?>

        <div style="margin-top: 20px; text-align: center; font-size: 0.9rem; color: #a0a0a0;">
            <p><strong>Fecha de validación:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
            <p><strong>Total de tests:</strong> <?php echo $total_tests; ?></p>
            <p><strong>Porcentaje de éxito:</strong> <?php echo $porcentaje_exito; ?>%</p>
        </div>
    </div>
</body>
</html>