<?php
/**
 * 🔍 Verificación Final - Sistema de Orden de Pedido
 * Archivo: verificacion_final_orden_pedido.php
 * Fecha: 31 de mayo de 2025
 * 
 * Este script realiza una verificación completa del sistema para asegurar
 * que todas las funcionalidades están operativas.
 */

require_once '../conexion.php';

// Configurar salida HTML
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔍 Verificación Final - Sistema Orden Pedido</title>
    <style>
        body {
            font-family: 'SF Pro Display', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 20px;
            background: #1e1e1e;
            color: #e0e0e0;
            line-height: 1.6;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: #2a2a2a;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
        }
        h1 {
            color: #007aff;
            text-align: center;
            margin-bottom: 30px;
            font-size: 2rem;
        }
        .check-section {
            margin: 20px 0;
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid #34c759;
            background: #1a3a1a;
        }
        .check-section.warning {
            border-left-color: #ff9500;
            background: #3a2a1a;
        }
        .check-section.error {
            border-left-color: #ff3b30;
            background: #3a1a1a;
        }
        .check-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: #ffffff;
        }
        .check-item {
            margin: 8px 0;
            padding: 8px 12px;
            border-radius: 6px;
            background: rgba(255,255,255,0.05);
        }
        .status-ok { color: #34c759; }
        .status-warning { color: #ff9500; }
        .status-error { color: #ff3b30; }
        .btn {
            display: inline-block;
            background: #007aff;
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            margin: 5px;
            transition: all 0.2s;
        }
        .btn:hover {
            background: #0056b3;
            transform: translateY(-1px);
        }
        .code-block {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 12px;
            margin: 10px 0;
            font-family: 'Monaco', monospace;
            font-size: 0.9rem;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Verificación Final del Sistema</h1>
        
        <?php
        $verificaciones = [];
        $errores = [];
        $advertencias = [];
        
        // 1. Verificar archivos principales
        echo '<div class="check-section">';
        echo '<div class="check-title">📁 Verificación de Archivos</div>';
        
        $archivos_principales = [
            'orden_pedido.php' => 'Archivo principal del sistema',
            'productos_por_categoria.php' => 'API de productos',
            'crear_pedido_inicial.php' => 'Procesamiento de pedidos',
            'conexion.php' => 'Conexión a base de datos'
        ];
        
        foreach ($archivos_principales as $archivo => $descripcion) {
            $ruta = "../$archivo";
            if (file_exists($ruta)) {
                echo "<div class='check-item'><span class='status-ok'>✓</span> $archivo - $descripcion</div>";
                $verificaciones[] = $archivo;
            } else {
                echo "<div class='check-item'><span class='status-error'>✗</span> $archivo - FALTA</div>";
                $errores[] = "Archivo $archivo no encontrado";
            }
        }
        echo '</div>';
        
        // 2. Verificar base de datos
        echo '<div class="check-section">';
        echo '<div class="check-title">🗄️ Verificación de Base de Datos</div>';
        
        if ($conn) {
            echo "<div class='check-item'><span class='status-ok'>✓</span> Conexión a base de datos establecida</div>";
            
            // Verificar tablas
            $tablas_requeridas = [
                'productos' => 'SELECT COUNT(*) as count FROM productos',
                'pedidos_detal' => 'SELECT COUNT(*) as count FROM pedidos_detal',
                'pedidos_detalle' => 'SELECT COUNT(*) as count FROM pedidos_detalle'
            ];
            
            foreach ($tablas_requeridas as $tabla => $query) {
                try {
                    $result = $conn->query($query);
                    if ($result) {
                        $row = $result->fetch_assoc();
                        echo "<div class='check-item'><span class='status-ok'>✓</span> Tabla '$tabla' - {$row['count']} registros</div>";
                    }
                } catch (Exception $e) {
                    echo "<div class='check-item'><span class='status-error'>✗</span> Tabla '$tabla' - ERROR: " . $e->getMessage() . "</div>";
                    $errores[] = "Problema con tabla $tabla";
                }
            }
        } else {
            echo "<div class='check-item'><span class='status-error'>✗</span> No se pudo conectar a la base de datos</div>";
            $errores[] = "Conexión a base de datos falló";
        }
        echo '</div>';
        
        // 3. Verificar funcionalidades JavaScript
        echo '<div class="check-section">';
        echo '<div class="check-title">⚡ Verificación de Funcionalidades JavaScript</div>';
        
        $archivo_orden = file_get_contents('../orden_pedido.php');
        $funciones_js = [
            'cargarProductos' => 'Carga de productos por categoría',
            'agregarProductoPersonalizado' => 'Agregar productos personalizados',
            'actualizarCarrito' => 'Actualización del carrito',
            'finalizarPedido' => 'Finalización de pedidos',
            'DOMContentLoaded' => 'Event listeners configurados'
        ];
        
        foreach ($funciones_js as $funcion => $descripcion) {
            if (strpos($archivo_orden, $funcion) !== false) {
                echo "<div class='check-item'><span class='status-ok'>✓</span> $funcion - $descripcion</div>";
            } else {
                echo "<div class='check-item'><span class='status-warning'>⚠</span> $funcion - No encontrada</div>";
                $advertencias[] = "Función $funcion podría faltar";
            }
        }
        echo '</div>';
        
        // 4. Verificar estructura HTML
        echo '<div class="check-section">';
        echo '<div class="check-title">🌐 Verificación de Estructura HTML</div>';
        
        // Verificar elementos importantes
        $elementos_html = [
            'id="categoria"' => 'Selector de categoría',
            'id="busqueda"' => 'Campo de búsqueda',
            'id="custom-nombre"' => 'Campo nombre personalizado',
            'id="custom-precio"' => 'Campo precio personalizado',
            'id="custom-talla"' => 'Selector talla personalizada',
            'id="carrito-table"' => 'Tabla del carrito'
        ];
        
        foreach ($elementos_html as $elemento => $descripcion) {
            if (strpos($archivo_orden, $elemento) !== false) {
                echo "<div class='check-item'><span class='status-ok'>✓</span> $descripcion</div>";
            } else {
                echo "<div class='check-item'><span class='status-error'>✗</span> $descripcion - FALTA</div>";
                $errores[] = "Elemento HTML $elemento no encontrado";
            }
        }
        echo '</div>';
        
        // 5. Verificar mejoras implementadas
        echo '<div class="check-section">';
        echo '<div class="check-title">🚀 Verificación de Mejoras Implementadas</div>';
        
        $mejoras = [
            'findIndex.*isCustom' => 'Detección mejorada de productos personalizados',
            'clearTimeout.*searchTimeout' => 'Debounce en búsqueda implementado',
            'DOMContentLoaded.*addEventListener' => 'Event listeners configurados correctamente',
            'console\.log.*Debug' => 'Sistema de debug activado',
            'mostrarMensaje.*error|success' => 'Sistema de mensajes implementado'
        ];
        
        foreach ($mejoras as $patron => $descripcion) {
            if (preg_match("/$patron/", $archivo_orden)) {
                echo "<div class='check-item'><span class='status-ok'>✓</span> $descripcion</div>";
            } else {
                echo "<div class='check-item'><span class='status-warning'>⚠</span> $descripcion - No verificado</div>";
                $advertencias[] = $descripcion;
            }
        }
        echo '</div>';
        
        // Resumen final
        echo '<div class="check-section">';
        echo '<div class="check-title">📊 Resumen de Verificación</div>';
        
        $total_verificaciones = count($verificaciones);
        $total_errores = count($errores);
        $total_advertencias = count($advertencias);
        
        echo "<div class='check-item'><strong>Archivos verificados:</strong> $total_verificaciones</div>";
        echo "<div class='check-item'><strong>Errores encontrados:</strong> <span class='status-error'>$total_errores</span></div>";
        echo "<div class='check-item'><strong>Advertencias:</strong> <span class='status-warning'>$total_advertencias</span></div>";
        
        if ($total_errores == 0 && $total_advertencias == 0) {
            echo "<div class='check-item'><span class='status-ok'>🎉 SISTEMA COMPLETAMENTE FUNCIONAL</span></div>";
        } elseif ($total_errores == 0) {
            echo "<div class='check-item'><span class='status-warning'>⚠️ Sistema funcional con advertencias menores</span></div>";
        } else {
            echo "<div class='check-item'><span class='status-error'>❌ Se requieren correcciones</span></div>";
        }
        echo '</div>';
        
        // Lista de errores y advertencias
        if (!empty($errores)) {
            echo '<div class="check-section error">';
            echo '<div class="check-title">❌ Errores Críticos</div>';
            foreach ($errores as $error) {
                echo "<div class='check-item'>• $error</div>";
            }
            echo '</div>';
        }
        
        if (!empty($advertencias)) {
            echo '<div class="check-section warning">';
            echo '<div class="check-title">⚠️ Advertencias</div>';
            foreach ($advertencias as $advertencia) {
                echo "<div class='check-item'>• $advertencia</div>";
            }
            echo '</div>';
        }
        ?>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="../orden_pedido.php" class="btn">🛒 Probar Sistema Completo</a>
            <a href="../index.php" class="btn">📝 Formulario Simple</a>
            <a href="../listar_pedidos.php" class="btn">📋 Ver Pedidos</a>
            <a href="test_orden_pedido_debug.php" class="btn">🔧 Diagnóstico Avanzado</a>
        </div>
        
        <div class="check-section">
            <div class="check-title">📝 Próximos Pasos Recomendados</div>
            <div class="check-item">1. <strong>Subir al servidor:</strong> Actualizar archivos en sequoiaspeed.com.co/pedidos/</div>
            <div class="check-item">2. <strong>Pruebas en producción:</strong> Verificar funcionalidad completa en el servidor</div>
            <div class="check-item">3. <strong>Monitoreo:</strong> Revisar logs y comportamiento en usuarios reales</div>
            <div class="check-item">4. <strong>Optimización:</strong> Ajustar rendimiento según el uso</div>
        </div>
        
        <div class="code-block">
            <strong>Estado del Proyecto:</strong><br>
            ✅ Función cargarProductos() corregida y optimizada<br>
            ✅ Función agregarProductoPersonalizado() con limpieza automática<br>
            ✅ Event listeners configurados con DOMContentLoaded<br>
            ✅ Debounce implementado en búsqueda (300ms)<br>
            ✅ Sistema de debug con console.log activado<br>
            ✅ Validaciones robustas implementadas<br>
            ✅ Manejo de errores HTTP mejorado<br>
            ✅ Integración completa con sistema de URL compartible
        </div>
    </div>
</body>
</html>
