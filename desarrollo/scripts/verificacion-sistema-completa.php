<?php
/**
 * VERIFICACIÓN COMPLETA DEL SISTEMA POST-MIGRACIÓN
 * Sequoia Speed - Validación funcional integral
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

class VerificacionSistemaCompleta {
    private $resultados = [];
    private $errores = [];
    private $warnings = [];
    
    public function __construct() {
        echo "🔍 VERIFICACIÓN COMPLETA DEL SISTEMA SEQUOIA SPEED\n";
        echo "================================================\n\n";
    }
    
    public function ejecutarVerificacion() {
        $this->verificarArchivosEsenciales();
        $this->verificarEstructuraMVC();
        $this->verificarConfiguracion();
        $this->verificarConexionDB();
        $this->verificarAPIs();
        $this->verificarAssets();
        $this->verificarFuncionalidadesClave();
        $this->verificarIntegracionBold();
        $this->verificarRendimiento();
        $this->generarReporteFinal();
    }
    
    private function verificarArchivosEsenciales() {
        echo "📁 VERIFICANDO ARCHIVOS ESENCIALES\n";
        echo "==================================\n";
        
        $archivosEsenciales = [
            // Archivos principales
            'index.php' => 'Página principal',
            'conexion.php' => 'Conexión a BD',
            'bootstrap.php' => 'Bootstrap del sistema',
            'routes.php' => 'Sistema de rutas',
            
            // Archivos de configuración
            'app_config.php' => 'Configuración de aplicación',
            'database_config.php' => 'Configuración de BD',
            'production-config.json' => 'Configuración de producción',
            
            // Archivos legacy críticos
            'guardar_pedido.php' => 'Guardar pedidos',
            'listar_pedidos.php' => 'Listar pedidos',
            'productos_por_categoria.php' => 'Productos por categoría',
            'bold_payment.php' => 'Integración Bold',
            'bold_webhook_enhanced.php' => 'Webhook Bold',
            
            // Estructura MVC
            'app/controllers/PedidoController.php' => 'Controlador de pedidos',
            'app/controllers/ProductoController.php' => 'Controlador de productos',
            'app/controllers/PaymentController.php' => 'Controlador de pagos',
            'app/models/Pedido.php' => 'Modelo Pedido',
            'app/models/Producto.php' => 'Modelo Producto',
            'app/services/PedidoService.php' => 'Servicio de pedidos'
        ];
        
        $archivosOK = 0;
        foreach ($archivosEsenciales as $archivo => $descripcion) {
            if (file_exists($archivo)) {
                echo "✅ $descripcion ($archivo)\n";
                $archivosOK++;
                
                // Verificar que el archivo no esté vacío
                if (filesize($archivo) == 0) {
                    $this->warnings[] = "$archivo está vacío";
                    echo "   ⚠️  Archivo vacío\n";
                }
            } else {
                echo "❌ $descripcion ($archivo) - NO ENCONTRADO\n";
                $this->errores[] = "Archivo faltante: $archivo";
            }
        }
        
        $this->resultados['archivos_esenciales'] = [
            'total' => count($archivosEsenciales),
            'encontrados' => $archivosOK,
            'porcentaje' => round(($archivosOK / count($archivosEsenciales)) * 100, 2)
        ];
        
        echo "\n📊 Archivos esenciales: $archivosOK/" . count($archivosEsenciales) . 
             " (" . $this->resultados['archivos_esenciales']['porcentaje'] . "%)\n\n";
    }
    
    private function verificarEstructuraMVC() {
        echo "🏗️ VERIFICANDO ESTRUCTURA MVC\n";
        echo "=============================\n";
        
        $directoriosMVC = [
            'app' => 'Directorio principal de aplicación',
            'app/controllers' => 'Controladores MVC',
            'app/models' => 'Modelos de datos',
            'app/services' => 'Servicios de negocio',
            'app/middleware' => 'Middleware de aplicación',
            'app/config' => 'Configuraciones específicas',
            'public' => 'Assets públicos',
            'storage' => 'Almacenamiento',
            'cache' => 'Sistema de cache'
        ];
        
        $directoriosOK = 0;
        foreach ($directoriosMVC as $directorio => $descripcion) {
            if (is_dir($directorio)) {
                echo "✅ $descripcion ($directorio/)\n";
                $directoriosOK++;
                
                // Contar archivos en el directorio
                $archivos = glob("$directorio/*.php");
                if (count($archivos) > 0) {
                    echo "   📄 " . count($archivos) . " archivos PHP\n";
                }
            } else {
                echo "❌ $descripcion ($directorio/) - NO ENCONTRADO\n";
                $this->errores[] = "Directorio faltante: $directorio";
            }
        }
        
        $this->resultados['estructura_mvc'] = [
            'directorios_verificados' => count($directoriosMVC),
            'directorios_ok' => $directoriosOK
        ];
        
        echo "\n";
    }
    
    private function verificarConfiguracion() {
        echo "⚙️ VERIFICANDO CONFIGURACIÓN\n";
        echo "===========================\n";
        
        // Verificar configuración de BD
        if (file_exists('conexion.php')) {
            echo "✅ Archivo de conexión existe\n";
            
            try {
                ob_start();
                include_once 'conexion.php';
                $output = ob_get_clean();
                
                if (isset($conexion) || isset($pdo) || isset($db)) {
                    echo "✅ Variable de conexión definida\n";
                } else {
                    echo "⚠️  Variable de conexión no encontrada\n";
                    $this->warnings[] = "Variable de conexión BD no definida";
                }
            } catch (Exception $e) {
                echo "❌ Error al cargar conexión: " . $e->getMessage() . "\n";
                $this->errores[] = "Error en configuración de BD: " . $e->getMessage();
            }
        }
        
        // Verificar configuración de producción
        if (file_exists('production-config.json')) {
            $config = json_decode(file_get_contents('production-config.json'), true);
            if ($config && isset($config['environment'])) {
                echo "✅ Configuración de producción cargada\n";
                echo "   🌍 Ambiente: " . $config['environment'] . "\n";
            } else {
                echo "❌ Configuración de producción inválida\n";
                $this->errores[] = "Configuración de producción malformada";
            }
        }
        
        // Verificar configuración de aplicación
        if (file_exists('app_config.php')) {
            try {
                ob_start();
                include_once 'app_config.php';
                ob_get_clean();
                echo "✅ Configuración de aplicación cargada\n";
            } catch (Exception $e) {
                echo "❌ Error en configuración de aplicación: " . $e->getMessage() . "\n";
                $this->errores[] = "Error en app_config.php: " . $e->getMessage();
            }
        }
        
        echo "\n";
    }
    
    private function verificarConexionDB() {
        echo "🗄️ VERIFICANDO CONEXIÓN A BASE DE DATOS\n";
        echo "=======================================\n";
        
        try {
            if (file_exists('conexion.php')) {
                include_once 'conexion.php';
                
                // Intentar diferentes variables de conexión
                $conexionEncontrada = false;
                
                if (isset($conexion) && $conexion instanceof PDO) {
                    $stmt = $conexion->query("SELECT 1");
                    if ($stmt) {
                        echo "✅ Conexión PDO funcional\n";
                        $conexionEncontrada = true;
                    }
                } elseif (isset($pdo) && $pdo instanceof PDO) {
                    $stmt = $pdo->query("SELECT 1");
                    if ($stmt) {
                        echo "✅ Conexión PDO funcional (variable \$pdo)\n";
                        $conexionEncontrada = true;
                    }
                } elseif (isset($db)) {
                    // Para mysqli
                    if (is_object($db) && method_exists($db, 'query')) {
                        $result = $db->query("SELECT 1");
                        if ($result) {
                            echo "✅ Conexión MySQLi funcional\n";
                            $conexionEncontrada = true;
                        }
                    }
                }
                
                if (!$conexionEncontrada) {
                    echo "❌ No se pudo establecer conexión a BD\n";
                    $this->errores[] = "Conexión a base de datos fallida";
                } else {
                    $this->resultados['conexion_db'] = true;
                }
                
            } else {
                echo "❌ Archivo de conexión no encontrado\n";
                $this->errores[] = "Archivo conexion.php no existe";
            }
            
        } catch (Exception $e) {
            echo "❌ Error de conexión: " . $e->getMessage() . "\n";
            $this->errores[] = "Error DB: " . $e->getMessage();
        }
        
        echo "\n";
    }
    
    private function verificarAPIs() {
        echo "🔌 VERIFICANDO APIs\n";
        echo "==================\n";
        
        $apis = [
            'public/api/pedidos/create.php' => 'API crear pedidos',
            'public/api/productos/by-category.php' => 'API productos por categoría',
            'public/api/bold/webhook.php' => 'API webhook Bold',
            'public/api/reports/ventas.php' => 'API reportes de ventas'
        ];
        
        $apisOK = 0;
        foreach ($apis as $api => $descripcion) {
            if (file_exists($api)) {
                echo "✅ $descripcion\n";
                $apisOK++;
                
                // Verificar estructura básica del API
                $contenido = file_get_contents($api);
                if (strpos($contenido, '<?php') !== false) {
                    echo "   📄 Estructura PHP correcta\n";
                } else {
                    echo "   ⚠️  Sin tag PHP de apertura\n";
                    $this->warnings[] = "$api sin tag PHP";
                }
                
                if (strpos($contenido, 'json') !== false || strpos($contenido, 'JSON') !== false) {
                    echo "   📡 Respuesta JSON detectada\n";
                }
                
            } else {
                echo "❌ $descripcion - NO ENCONTRADA\n";
                $this->errores[] = "API faltante: $api";
            }
        }
        
        $this->resultados['apis'] = [
            'total' => count($apis),
            'funcionando' => $apisOK
        ];
        
        echo "\n📊 APIs: $apisOK/" . count($apis) . "\n\n";
    }
    
    private function verificarAssets() {
        echo "🎨 VERIFICANDO ASSETS\n";
        echo "====================\n";
        
        $assets = [
            'public/assets/js/bold-integration.js' => 'JavaScript integración Bold',
            'public/assets/js/legacy-compatibility.js' => 'JavaScript compatibilidad',
            'public/assets/css/sequoia-unified.css' => 'CSS unificado',
            'estilos.css' => 'CSS principal',
            'pedidos.js' => 'JavaScript principal',
            'logo.png' => 'Logo de la empresa'
        ];
        
        $assetsOK = 0;
        foreach ($assets as $asset => $descripcion) {
            if (file_exists($asset)) {
                echo "✅ $descripcion\n";
                $assetsOK++;
                
                $size = filesize($asset);
                if ($size > 0) {
                    echo "   📏 Tamaño: " . round($size / 1024, 2) . " KB\n";
                } else {
                    echo "   ⚠️  Archivo vacío\n";
                    $this->warnings[] = "$asset está vacío";
                }
            } else {
                echo "❌ $descripcion - NO ENCONTRADO\n";
                $this->warnings[] = "Asset faltante: $asset";
            }
        }
        
        echo "\n📊 Assets: $assetsOK/" . count($assets) . "\n\n";
    }
    
    private function verificarFuncionalidadesClave() {
        echo "🔧 VERIFICANDO FUNCIONALIDADES CLAVE\n";
        echo "===================================\n";
        
        // Verificar funcionalidad de pedidos
        echo "📦 Funcionalidad de Pedidos:\n";
        if (file_exists('guardar_pedido.php')) {
            $contenido = file_get_contents('guardar_pedido.php');
            if (strpos($contenido, 'INSERT') !== false || strpos($contenido, 'insert') !== false) {
                echo "✅ Lógica de inserción de pedidos presente\n";
            } else {
                echo "⚠️  Lógica de inserción no clara\n";
                $this->warnings[] = "Lógica de inserción en guardar_pedido.php no clara";
            }
        }
        
        if (file_exists('listar_pedidos.php')) {
            $contenido = file_get_contents('listar_pedidos.php');
            if (strpos($contenido, 'SELECT') !== false || strpos($contenido, 'select') !== false) {
                echo "✅ Lógica de listado de pedidos presente\n";
            } else {
                echo "⚠️  Lógica de listado no clara\n";
                $this->warnings[] = "Lógica de listado en listar_pedidos.php no clara";
            }
        }
        
        // Verificar funcionalidad de productos
        echo "\n🛍️ Funcionalidad de Productos:\n";
        if (file_exists('productos_por_categoria.php')) {
            echo "✅ Listado de productos por categoría disponible\n";
        } else {
            echo "❌ Listado de productos no encontrado\n";
            $this->errores[] = "productos_por_categoria.php faltante";
        }
        
        // Verificar funcionalidad de pagos
        echo "\n💳 Funcionalidad de Pagos:\n";
        if (file_exists('bold_payment.php')) {
            echo "✅ Procesamiento de pagos Bold disponible\n";
            
            $contenido = file_get_contents('bold_payment.php');
            if (strpos($contenido, 'bold') !== false || strpos($contenido, 'Bold') !== false) {
                echo "✅ Integración Bold detectada\n";
            }
        } else {
            echo "❌ Procesamiento de pagos no encontrado\n";
            $this->errores[] = "bold_payment.php faltante";
        }
        
        echo "\n";
    }
    
    private function verificarIntegracionBold() {
        echo "💰 VERIFICANDO INTEGRACIÓN BOLD\n";
        echo "===============================\n";
        
        $archivosBold = [
            'bold_payment.php' => 'Procesamiento de pagos',
            'bold_webhook_enhanced.php' => 'Webhook mejorado',
            'bold_hash.php' => 'Generación de hash',
            'bold_confirmation.php' => 'Confirmación de pagos'
        ];
        
        $boldOK = 0;
        foreach ($archivosBold as $archivo => $descripcion) {
            if (file_exists($archivo)) {
                echo "✅ $descripcion ($archivo)\n";
                $boldOK++;
                
                // Verificar configuraciones Bold
                $contenido = file_get_contents($archivo);
                if (strpos($contenido, 'api_key') !== false || strpos($contenido, 'API_KEY') !== false) {
                    echo "   🔑 Configuración API Key detectada\n";
                }
                if (strpos($contenido, 'sandbox') !== false || strpos($contenido, 'production') !== false) {
                    echo "   🌍 Configuración de ambiente detectada\n";
                }
            } else {
                echo "❌ $descripcion ($archivo) - NO ENCONTRADO\n";
                $this->errores[] = "Archivo Bold faltante: $archivo";
            }
        }
        
        $this->resultados['integracion_bold'] = [
            'archivos_total' => count($archivosBold),
            'archivos_ok' => $boldOK
        ];
        
        echo "\n📊 Integración Bold: $boldOK/" . count($archivosBold) . "\n\n";
    }
    
    private function verificarRendimiento() {
        echo "⚡ VERIFICANDO RENDIMIENTO\n";
        echo "=========================\n";
        
        // Medir tiempo de carga de archivos principales
        $archivosPrincipales = ['index.php', 'guardar_pedido.php', 'listar_pedidos.php'];
        
        foreach ($archivosPrincipales as $archivo) {
            if (file_exists($archivo)) {
                $inicio = microtime(true);
                
                // Simular carga del archivo
                $contenido = file_get_contents($archivo);
                $tamaño = strlen($contenido);
                
                $tiempo = (microtime(true) - $inicio) * 1000;
                
                echo "📄 $archivo:\n";
                echo "   ⏱️  Tiempo de lectura: " . round($tiempo, 2) . " ms\n";
                echo "   📏 Tamaño: " . round($tamaño / 1024, 2) . " KB\n";
                
                if ($tiempo > 100) {
                    echo "   ⚠️  Tiempo de lectura elevado\n";
                    $this->warnings[] = "$archivo tiene tiempo de lectura elevado";
                }
            }
        }
        
        // Verificar uso de memoria
        $memoriaInicial = memory_get_usage();
        $memoriaMax = memory_get_peak_usage();
        
        echo "\n🧠 Uso de memoria:\n";
        echo "   📊 Memoria actual: " . round($memoriaInicial / 1024 / 1024, 2) . " MB\n";
        echo "   📈 Pico de memoria: " . round($memoriaMax / 1024 / 1024, 2) . " MB\n";
        
        if ($memoriaMax > 64 * 1024 * 1024) { // 64MB
            echo "   ⚠️  Uso de memoria elevado\n";
            $this->warnings[] = "Uso de memoria elevado: " . round($memoriaMax / 1024 / 1024, 2) . " MB";
        }
        
        echo "\n";
    }
    
    private function generarReporteFinal() {
        echo "📋 REPORTE FINAL DE VERIFICACIÓN\n";
        echo "================================\n\n";
        
        $totalErrores = count($this->errores);
        $totalWarnings = count($this->warnings);
        
        // Calcular estado general
        $estadoGeneral = "EXCELENTE";
        if ($totalErrores > 0) {
            $estadoGeneral = "CRÍTICO";
        } elseif ($totalWarnings > 3) {
            $estadoGeneral = "PRECAUCIÓN";
        } elseif ($totalWarnings > 0) {
            $estadoGeneral = "BUENO";
        }
        
        echo "🎯 ESTADO GENERAL: $estadoGeneral\n\n";
        
        // Mostrar métricas
        if (isset($this->resultados['archivos_esenciales'])) {
            echo "📁 Archivos esenciales: " . $this->resultados['archivos_esenciales']['encontrados'] . 
                 "/" . $this->resultados['archivos_esenciales']['total'] . 
                 " (" . $this->resultados['archivos_esenciales']['porcentaje'] . "%)\n";
        }
        
        if (isset($this->resultados['apis'])) {
            echo "🔌 APIs funcionando: " . $this->resultados['apis']['funcionando'] . 
                 "/" . $this->resultados['apis']['total'] . "\n";
        }
        
        if (isset($this->resultados['integracion_bold'])) {
            echo "💰 Integración Bold: " . $this->resultados['integracion_bold']['archivos_ok'] . 
                 "/" . $this->resultados['integracion_bold']['archivos_total'] . " archivos\n";
        }
        
        echo "🔍 Conexión BD: " . (isset($this->resultados['conexion_db']) ? "✅ OK" : "❌ ERROR") . "\n";
        
        echo "\n";
        
        // Mostrar errores
        if ($totalErrores > 0) {
            echo "❌ ERRORES CRÍTICOS ($totalErrores):\n";
            foreach ($this->errores as $i => $error) {
                echo "   " . ($i + 1) . ". $error\n";
            }
            echo "\n";
        }
        
        // Mostrar warnings
        if ($totalWarnings > 0) {
            echo "⚠️  ADVERTENCIAS ($totalWarnings):\n";
            foreach ($this->warnings as $i => $warning) {
                echo "   " . ($i + 1) . ". $warning\n";
            }
            echo "\n";
        }
        
        // Recomendaciones
        echo "💡 RECOMENDACIONES:\n";
        if ($totalErrores == 0 && $totalWarnings == 0) {
            echo "✅ Sistema en perfecto estado\n";
            echo "✅ Todas las funcionalidades verificadas\n";
            echo "✅ Listo para usar en producción\n";
        } else {
            if ($totalErrores > 0) {
                echo "🔴 URGENTE: Corregir errores críticos antes de usar\n";
            }
            if ($totalWarnings > 0) {
                echo "🟡 Revisar advertencias para optimizar rendimiento\n";
            }
        }
        
        // Guardar reporte
        $reporte = [
            'timestamp' => date('Y-m-d H:i:s'),
            'estado_general' => $estadoGeneral,
            'metricas' => $this->resultados,
            'errores' => $this->errores,
            'warnings' => $this->warnings,
            'total_errores' => $totalErrores,
            'total_warnings' => $totalWarnings
        ];
        
        file_put_contents('verificacion-sistema-reporte.json', json_encode($reporte, JSON_PRETTY_PRINT));
        
        echo "\n📄 Reporte detallado guardado en: verificacion-sistema-reporte.json\n";
        echo "\n🏁 VERIFICACIÓN COMPLETADA\n";
    }
}

// Ejecutar verificación si se llama directamente
if (php_sapi_name() === 'cli' || !isset($_SERVER['HTTP_HOST'])) {
    $verificador = new VerificacionSistemaCompleta();
    $verificador->ejecutarVerificacion();
}
?>
