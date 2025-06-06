<?php
/**
 * Monitor y Gestor del Modo Dual - Bold PSE
 * 
 * Herramienta para monitorear y ajustar el porcentaje de tráfico
 * durante la migración gradual.
 */

require_once "dual_mode_config.php";

// Función para actualizar el porcentaje
function updateEnhancedPercentage($newPercentage) {
    $configFile = __DIR__ . '/dual_mode_config.php';
    $content = file_get_contents($configFile);
    
    // Buscar y reemplazar la línea del porcentaje
    $pattern = "/define\('ENHANCED_WEBHOOK_PERCENTAGE',\s*(\d+)\);/";
    $replacement = "define('ENHANCED_WEBHOOK_PERCENTAGE', $newPercentage);";
    
    $newContent = preg_replace($pattern, $replacement, $content);
    
    if ($newContent !== $content) {
        file_put_contents($configFile, $newContent);
        return true;
    }
    
    return false;
}

// Función para leer estadísticas de logs
function getDualModeStats() {
    $logFile = DUAL_MODE_LOG_FILE;
    
    if (!file_exists($logFile)) {
        return [
            'total_requests' => 0,
            'enhanced_requests' => 0,
            'original_requests' => 0,
            'error_requests' => 0,
            'last_24h' => []
        ];
    }
    
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $stats = [
        'total_requests' => 0,
        'enhanced_requests' => 0,
        'original_requests' => 0,
        'error_requests' => 0,
        'last_24h' => []
    ];
    
    $yesterday = time() - (24 * 60 * 60);
    
    foreach ($lines as $line) {
        if (strpos($line, 'Webhook routing decision') !== false) {
            $stats['total_requests']++;
            
            // Extraer timestamp
            if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                $timestamp = strtotime($matches[1]);
                if ($timestamp > $yesterday) {
                    $stats['last_24h'][] = $line;
                }
            }
            
            if (strpos($line, '"use_enhanced":true') !== false) {
                $stats['enhanced_requests']++;
            } else {
                $stats['original_requests']++;
            }
        }
        
        if (strpos($line, 'Error in webhook processing') !== false) {
            $stats['error_requests']++;
        }
    }
    
    return $stats;
}

// Manejo de POST requests para actualizar configuración
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'update_percentage':
            $newPercentage = intval($_POST['percentage'] ?? 0);
            
            if ($newPercentage < 0 || $newPercentage > 100) {
                echo json_encode(['success' => false, 'error' => 'Porcentaje debe estar entre 0 y 100']);
                exit;
            }
            
            $success = updateEnhancedPercentage($newPercentage);
            echo json_encode(['success' => $success]);
            exit;
            
        case 'get_stats':
            $stats = getDualModeStats();
            echo json_encode(['success' => true, 'stats' => $stats]);
            exit;
    }
}

// Interface HTML
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor del Modo Dual - Bold PSE</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Inter', sans-serif;
            background: #1e1e1e;
            color: #cccccc;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: #252526;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        h1, h2 { color: #ffffff; }
        .card {
            background: #1e1e1e;
            border-radius: 8px;
            padding: 20px;
            margin: 16px 0;
            border-left: 4px solid #007aff;
        }
        .warning {
            background: rgba(255, 193, 7, 0.2);
            border-left: 4px solid #ffc107;
        }
        .success {
            background: rgba(40, 167, 69, 0.2);
            border-left: 4px solid #28a745;
        }
        .info {
            background: rgba(0, 122, 255, 0.2);
            border-left: 4px solid #007aff;
        }
        .btn {
            background: #007aff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 10px 10px 0;
            transition: background 0.2s;
        }
        .btn:hover { background: #0056d3; }
        .btn:disabled { background: #666; cursor: not-allowed; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin: 20px 0;
        }
        .stat-card {
            background: #2d2d30;
            padding: 16px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #007aff;
        }
        .stat-label {
            color: #999;
            font-size: 0.9em;
        }
        .percentage-control {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 20px 0;
        }
        .percentage-slider {
            flex: 1;
            height: 8px;
            border-radius: 4px;
            background: #3e3e42;
            outline: none;
            -webkit-appearance: none;
        }
        .percentage-slider::-webkit-slider-thumb {
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #007aff;
            cursor: pointer;
        }
        .percentage-display {
            font-size: 1.5em;
            font-weight: bold;
            color: #007aff;
            min-width: 60px;
        }
        .log-output {
            background: #1e1e1e;
            border: 1px solid #3e3e42;
            border-radius: 6px;
            padding: 16px;
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 12px;
            color: #cccccc;
            max-height: 300px;
            overflow-y: auto;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Monitor del Modo Dual - Bold PSE</h1>
        
        <div class="info card">
            <strong>🔄 Estado Actual:</strong><br>
            Porcentaje de tráfico al webhook mejorado: <strong><?= ENHANCED_WEBHOOK_PERCENTAGE ?>%</strong><br>
            Modo dual: <strong><?= DUAL_MODE_ENABLED ? 'Activo' : 'Inactivo' ?></strong>
        </div>

        <div class="card">
            <h2>⚙️ Control de Porcentaje</h2>
            <div class="warning">
                <strong>⚠️ Importante:</strong> Aumenta el porcentaje gradualmente (10% → 25% → 50% → 75% → 100%) y monitorea los logs después de cada cambio.
            </div>
            
            <div class="percentage-control">
                <label>Porcentaje:</label>
                <input type="range" 
                       id="percentage-slider" 
                       class="percentage-slider" 
                       min="0" 
                       max="100" 
                       step="5" 
                       value="<?= ENHANCED_WEBHOOK_PERCENTAGE ?>"
                       oninput="updatePercentageDisplay(this.value)">
                <span id="percentage-display" class="percentage-display"><?= ENHANCED_WEBHOOK_PERCENTAGE ?>%</span>
                <button class="btn" onclick="updatePercentage()">Actualizar</button>
            </div>
        </div>

        <div class="card">
            <h2>📈 Estadísticas</h2>
            <button class="btn" onclick="loadStats()">Actualizar Estadísticas</button>
            
            <div id="stats-container" class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number" id="total-requests">-</div>
                    <div class="stat-label">Total Requests</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="enhanced-requests">-</div>
                    <div class="stat-label">Webhook Mejorado</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="original-requests">-</div>
                    <div class="stat-label">Webhook Original</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="error-requests">-</div>
                    <div class="stat-label">Errores</div>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>📋 Logs Recientes</h2>
            <button class="btn" onclick="loadLogs()">Actualizar Logs</button>
            <div id="logs-container" class="log-output">
                Cargando logs...
            </div>
        </div>

        <div class="card">
            <h2>🎯 Pasos Recomendados</h2>
            <ol>
                <li><strong>Fase 1 (10-25%):</strong> Monitorear logs por errores durante 1-2 horas</li>
                <li><strong>Fase 2 (25-50%):</strong> Verificar que ambos sistemas procesen correctamente</li>
                <li><strong>Fase 3 (50-75%):</strong> Comparar rendimiento y tiempos de respuesta</li>
                <li><strong>Fase 4 (75-100%):</strong> Preparar para migración completa</li>
                <li><strong>Finalización:</strong> Cambiar URL a <code>/bold_webhook_enhanced.php</code></li>
            </ol>
        </div>
    </div>

    <script>
        function updatePercentageDisplay(value) {
            document.getElementById('percentage-display').textContent = value + '%';
        }

        async function updatePercentage() {
            const percentage = document.getElementById('percentage-slider').value;
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=update_percentage&percentage=${percentage}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✅ Porcentaje actualizado exitosamente');
                    location.reload();
                } else {
                    alert('❌ Error: ' + (result.error || 'Error desconocido'));
                }
                
            } catch (error) {
                alert('❌ Error al actualizar: ' + error.message);
            }
        }

        async function loadStats() {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=get_stats'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    const stats = result.stats;
                    document.getElementById('total-requests').textContent = stats.total_requests;
                    document.getElementById('enhanced-requests').textContent = stats.enhanced_requests;
                    document.getElementById('original-requests').textContent = stats.original_requests;
                    document.getElementById('error-requests').textContent = stats.error_requests;
                } else {
                    alert('Error cargando estadísticas');
                }
                
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        async function loadLogs() {
            const logsContainer = document.getElementById('logs-container');
            logsContainer.textContent = 'Cargando logs...';
            
            try {
                // Simular carga de logs (implementar según necesidades)
                setTimeout(() => {
                    logsContainer.textContent = 'Logs se mostrarán aquí cuando el sistema comience a recibir webhooks.\n\nPara ver logs en tiempo real, puedes usar:\ntail -f logs/dual_mode.log';
                }, 1000);
                
            } catch (error) {
                logsContainer.textContent = 'Error cargando logs: ' + error.message;
            }
        }

        // Cargar estadísticas al inicio
        loadStats();
        loadLogs();

        // Auto-refresh cada 30 segundos
        setInterval(() => {
            loadStats();
        }, 30000);
    </script>
</body>
</html>
