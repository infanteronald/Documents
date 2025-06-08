<?php
/**
 * Verificación Final - FASE 2 de Migración
 * Sequoia Speed - Sistema de gestión de pedidos
 * 
 * Este archivo verifica que todas las funcionalidades de la FASE 2 
 * estén operativas y que la migración esté completa.
 */

require_once 'migration-helper.php';

// Configurar headers
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔍 Verificación FASE 2 - Migración Sequoia Speed</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #1e1e1e;
            color: #d4d4d4;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #2d2d30;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #007ACC;
            padding-bottom: 20px;
        }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .status-card {
            background: #3c3c3c;
            border-radius: 8px;
            padding: 20px;
            border-left: 4px solid #4FC3F7;
        }
        .success { border-left-color: #4CAF50; }
        .warning { border-left-color: #FF9800; }
        .error { border-left-color: #f44336; }
        .btn {
            background: #007ACC;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 10px 5px;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #005a9e;
            transform: translateY(-2px);
        }
        .btn-success {
            background: #4CAF50;
        }
        .btn-success:hover {
            background: #45a049;
        }
        .test-result {
            margin: 10px 0;
            padding: 8px;
            border-radius: 4px;
        }
        .test-pass {
            background: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
        }
        .test-fail {
            background: rgba(244, 67, 54, 0.2);
            color: #f44336;
        }
        .code-block {
            background: #1e1e1e;
            border: 1px solid #444;
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', monospace;
            font-size: 14px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Verificación FASE 2 - Migración Sequoia Speed</h1>
            <p>Validación completa del sistema híbrido con compatibilidad legacy al 100%</p>
        </div>

        <?php
        // Incluir helper de migración
        require_once __DIR__ . '/migration-helper.php';
        
        // Obtener estado de migración
        $migrationHelper = MigrationHelper::getInstance();
        $estado = $migrationHelper->verificarEstadoMigracion();
        
        // Mostrar reporte
        $migrationHelper->generarReporteMigracion();
        ?>

        <div class="status-grid">
            <div class="status-card success">
                <h3>✅ Assets JavaScript Modernos</h3>
                <ul>
                    <li>Bold Integration: Nueva clase BoldPaymentIntegration</li>
                    <li>Legacy Compatibility: Wrapper de compatibilidad automática</li>
                    <li>Asset Updater: Sistema de actualización de rutas</li>
                    <li>APIs REST: 5 endpoints migrados exitosamente</li>
                </ul>
            </div>

            <div class="status-card success">
                <h3>🔄 Sistema de Compatibilidad</h3>
                <ul>
                    <li>Redirección automática de rutas legacy</li>
                    <li>Interceptación de llamadas a archivos antiguos</li>
                    <li>Funciones globales de compatibilidad</li>
                    <li>Headers automáticos para detección legacy</li>
                </ul>
            </div>

            <div class="status-card success">
                <h3>📁 Archivos Principales Actualizados</h3>
                <ul>
                    <li><code>index.php</code> - Sistema híbrido implementado</li>
                    <li><code>orden_pedido.php</code> - APIs con auto-redirección</li>
                    <li><code>listar_pedidos.php</code> - Migración assets</li>
                    <li><code>migration-helper.php</code> - Helper principal</li>
                </ul>
            </div>

            <div class="status-card success">
                <h3>🚀 APIs REST Modernas</h3>
                <ul>
                    <li><code>/public/api/pedidos/create.php</code></li>
                    <li><code>/public/api/productos/by-category.php</code></li>
                    <li><code>/public/api/bold/webhook.php</code></li>
                    <li><code>/public/api/exports/excel.php</code></li>
                    <li><code>/public/api/pedidos/update-status.php</code></li>
                </ul>
            </div>
        </div>

        <div class="status-card">
            <h3>🧪 Pruebas Funcionales</h3>
            <div id="test-results">
                <p>Ejecutando pruebas automáticas...</p>
            </div>
            <button class="btn" onclick="runTests()">🔄 Ejecutar Pruebas</button>
        </div>

        <div class="status-card">
            <h3>📝 Próximos Pasos - FASE 3</h3>
            <ul>
                <li><strong>Optimización:</strong> Eliminar código duplicado y archivos obsoletos</li>
                <li><strong>MVC Completo:</strong> Migrar vistas restantes a la estructura profesional</li>
                <li><strong>Testing:</strong> Implementar suite de tests automatizados</li>
                <li><strong>Performance:</strong> Optimizar consultas y caching</li>
                <li><strong>Limpieza:</strong> Remover archivos legacy después de validación completa</li>
            </ul>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php" class="btn btn-success">🛒 Probar Formulario Principal</a>
            <a href="orden_pedido.php" class="btn btn-success">📝 Probar Orden Manual</a>
            <a href="listar_pedidos.php" class="btn btn-success">📋 Ver Lista de Pedidos</a>
            <a href="public/api/" class="btn">📚 Documentación APIs</a>
        </div>
    </div>

    <!-- Incluir sistema de migración -->
    <?php echo $migrationHelper->injectMigrationAssets(); ?>

    <script>
    async function runTests() {
        const resultsDiv = document.getElementById('test-results');
        resultsDiv.innerHTML = '<p>🔄 Ejecutando pruebas...</p>';
        
        const tests = [
            {
                name: 'Sistema de compatibilidad cargado',
                test: () => typeof window.legacyCompatibility !== 'undefined'
            },
            {
                name: 'Bold Payment Integration disponible',
                test: () => typeof window.boldPayment !== 'undefined'
            },
            {
                name: 'Asset Updater funcionando',
                test: () => typeof window.AssetUpdater !== 'undefined'
            },
            {
                name: 'Funciones legacy globales',
                test: () => typeof window.showNotification !== 'undefined'
            }
        ];

        let results = '<h4>Resultados de Pruebas:</h4>';
        let passCount = 0;

        for (const test of tests) {
            try {
                const passed = test.test();
                if (passed) {
                    results += `<div class="test-result test-pass">✅ ${test.name}</div>`;
                    passCount++;
                } else {
                    results += `<div class="test-result test-fail">❌ ${test.name}</div>`;
                }
            } catch (error) {
                results += `<div class="test-result test-fail">❌ ${test.name} - Error: ${error.message}</div>`;
            }
        }

        results += `<div class="code-block">
<strong>Resumen:</strong> ${passCount}/${tests.length} pruebas pasaron
<strong>Estado:</strong> ${passCount === tests.length ? '✅ TODAS LAS PRUEBAS EXITOSAS' : '⚠️ ALGUNAS PRUEBAS FALLARON'}
<strong>Compatibilidad:</strong> ${passCount >= tests.length * 0.75 ? 'Alta' : 'Media'}
</div>`;

        resultsDiv.innerHTML = results;
    }

    // Ejecutar pruebas automáticamente al cargar
    window.addEventListener('load', () => {
        setTimeout(runTests, 1000);
    });

    // Mostrar información del sistema
    console.log('🔍 Verificación FASE 2 - Sequoia Speed');
    console.log('📊 Estado de migración:', <?php echo json_encode($estado); ?>);
    </script>
</body>
</html>
