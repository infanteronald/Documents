<?php
/*
 * Test de diagnóstico para orden_pedido.php
 * Verificar que funciona correctamente en el servidor
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🧪 Test Diagnóstico - orden_pedido.php</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #252526;
            border-radius: 12px;
            padding: 30px;
            border: 1px solid #333;
        }
        h1 {
            color: #e0e0e0;
            text-align: center;
            margin-bottom: 30px;
        }
        .test-section {
            background: #2a2a2a;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #444;
        }
        .test-title {
            color: #007aff;
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 15px;
        }
        .test-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #333;
        }
        .test-item:last-child {
            border-bottom: none;
        }
        .status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status.ok {
            background: #30d158;
            color: white;
        }
        .status.error {
            background: #ff453a;
            color: white;
        }
        .btn {
            background: #007aff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 10px 5px;
            transition: all 0.2s;
        }
        .btn:hover {
            background: #0056b3;
            transform: translateY(-1px);
        }
        .instructions {
            background: #3c3a1a;
            color: #ffcb6b;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .instructions h3 {
            margin-top: 0;
            color: #ffcb6b;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Test Diagnóstico - orden_pedido.php</h1>
        
        <div class="test-section">
            <div class="test-title">📋 Archivos Requeridos</div>
            
            <div class="test-item">
                <span>orden_pedido.php</span>
                <span class="status <?php echo file_exists('../orden_pedido.php') ? 'ok' : 'error'; ?>">
                    <?php echo file_exists('../orden_pedido.php') ? '✓ Existe' : '✗ No encontrado'; ?>
                </span>
            </div>
            
            <div class="test-item">
                <span>productos_por_categoria.php</span>
                <span class="status <?php echo file_exists('../productos_por_categoria.php') ? 'ok' : 'error'; ?>">
                    <?php echo file_exists('../productos_por_categoria.php') ? '✓ Existe' : '✗ No encontrado'; ?>
                </span>
            </div>
            
            <div class="test-item">
                <span>crear_pedido_inicial.php</span>
                <span class="status <?php echo file_exists('../crear_pedido_inicial.php') ? 'ok' : 'error'; ?>">
                    <?php echo file_exists('../crear_pedido_inicial.php') ? '✓ Existe' : '✗ No encontrado'; ?>
                </span>
            </div>
            
            <div class="test-item">
                <span>conexion.php</span>
                <span class="status <?php echo file_exists('../conexion.php') ? 'ok' : 'error'; ?>">
                    <?php echo file_exists('../conexion.php') ? '✓ Existe' : '✗ No encontrado'; ?>
                </span>
            </div>
        </div>

        <div class="test-section">
            <div class="test-title">🔌 Test de Conectividad</div>
            
            <div class="test-item">
                <span>Conexión a Base de Datos</span>
                <?php
                try {
                    include_once '../conexion.php';
                    if (isset($conn) && $conn->ping()) {
                        echo '<span class="status ok">✓ Conectado</span>';
                    } else {
                        echo '<span class="status error">✗ Error de conexión</span>';
                    }
                } catch (Exception $e) {
                    echo '<span class="status error">✗ Error: ' . htmlspecialchars($e->getMessage()) . '</span>';
                }
                ?>
            </div>
            
            <div class="test-item">
                <span>API productos_por_categoria.php</span>
                <?php
                $apiUrl = '../productos_por_categoria.php?cat=test';
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 5,
                        'method' => 'GET'
                    ]
                ]);
                
                $response = @file_get_contents($apiUrl, false, $context);
                if ($response !== false) {
                    $data = json_decode($response, true);
                    if (isset($data['productos'])) {
                        echo '<span class="status ok">✓ API Funcional</span>';
                    } else {
                        echo '<span class="status error">✗ API Error</span>';
                    }
                } else {
                    echo '<span class="status error">✗ No responde</span>';
                }
                ?>
            </div>
        </div>

        <div class="test-section">
            <div class="test-title">🛠️ Acciones de Prueba</div>
            
            <a href="../orden_pedido.php" class="btn" target="_blank">
                🛒 Abrir orden_pedido.php
            </a>
            
            <a href="../productos_por_categoria.php?cat=ropa" class="btn" target="_blank">
                📦 Test API Productos
            </a>
            
            <a href="../index.php" class="btn" target="_blank">
                🏠 Ir a index.php
            </a>
        </div>

        <div class="instructions">
            <h3>📝 Instrucciones de Prueba:</h3>
            <ol>
                <li><strong>Verificar archivos:</strong> Todos los archivos deben mostrar "✓ Existe"</li>
                <li><strong>Test de conectividad:</strong> Base de datos y API deben estar "✓ Conectado" y "✓ API Funcional"</li>
                <li><strong>Abrir orden_pedido.php:</strong> Verificar que carga correctamente</li>
                <li><strong>Seleccionar categoría:</strong> Probar que carga productos</li>
                <li><strong>Agregar productos:</strong> Verificar que se agregan al carrito</li>
                <li><strong>Producto personalizado:</strong> Crear y agregar uno personalizado</li>
                <li><strong>Finalizar pedido:</strong> Verificar generación de URL</li>
            </ol>
            
            <p><strong>Nota:</strong> Si algún elemento muestra "✗", revisar los archivos o la configuración de la base de datos.</p>
        </div>
    </div>
</body>
</html>
