<?php
/**
 * Monitor de Webhooks Bold en Tiempo Real
 * Dashboard para monitorear el estado de webhooks, estadísticas y retry queue
 */

require_once "conexion.php";

// Obtener estadísticas de los últimos 7 días
function getWebhookStats($conn) {
    $sql = "
    SELECT 
        DATE(created_at) as fecha,
        event_type,
        status,
        COUNT(*) as total,
        COUNT(DISTINCT order_id) as ordenes_unicas
    FROM bold_webhook_logs 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at), event_type, status
    ORDER BY fecha DESC, event_type
    ";
    
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Obtener elementos en cola de retry
function getRetryQueue($conn) {
    $sql = "
    SELECT *
    FROM bold_retry_queue 
    WHERE status IN ('pending', 'processing')
    ORDER BY next_retry_at ASC, created_at ASC
    LIMIT 50
    ";
    
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Obtener logs recientes
function getRecentLogs($conn, $limit = 20) {
    $sql = "
    SELECT *
    FROM bold_webhook_logs 
    ORDER BY created_at DESC
    LIMIT ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Obtener resumen del día actual
function getTodaysSummary($conn) {
    $sql = "
    SELECT 
        COUNT(*) as total_webhooks,
        COUNT(CASE WHEN status = 'success' THEN 1 END) as exitosos,
        COUNT(CASE WHEN status = 'error' THEN 1 END) as errores,
        COUNT(CASE WHEN status = 'warning' THEN 1 END) as advertencias,
        COUNT(DISTINCT order_id) as ordenes_procesadas,
        AVG(processing_time_ms) as tiempo_promedio
    FROM bold_webhook_logs 
    WHERE DATE(created_at) = CURDATE()
    ";
    
    $result = $conn->query($sql);
    return $result ? $result->fetch_assoc() : [];
}

$stats = getWebhookStats($conn);
$retryQueue = getRetryQueue($conn);
$recentLogs = getRecentLogs($conn);
$todaysSummary = getTodaysSummary($conn);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor de Webhooks Bold - Sequoia Speed</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <style>
        :root {
            --primary: #007bff;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
            --light: #f8f9fa;
            --dark: #343a40;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--light);
            color: var(--dark);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: linear-gradient(135deg, var(--primary), var(--info));
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,123,255,0.3);
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 300;
        }

        .header .subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .success { color: var(--success); }
        .warning { color: var(--warning); }
        .danger { color: var(--danger); }
        .info { color: var(--info); }
        .primary { color: var(--primary); }

        .section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .section h2 {
            margin-bottom: 20px;
            color: var(--dark);
            font-size: 1.5rem;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 10px;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: var(--light);
            font-weight: 600;
            color: var(--dark);
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-success {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .status-error {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        .status-warning {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }

        .status-info {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info);
        }

        .status-pending {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }

        .refresh-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 20px;
            font-size: 1rem;
            transition: background 0.2s ease;
        }

        .refresh-btn:hover {
            background: #0056b3;
        }

        .auto-refresh {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .toggle-switch {
            position: relative;
            width: 50px;
            height: 25px;
        }

        .toggle-input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.4s;
            border-radius: 25px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 19px;
            width: 19px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }

        .toggle-input:checked + .toggle-slider {
            background-color: var(--success);
        }

        .toggle-input:checked + .toggle-slider:before {
            transform: translateX(25px);
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .stat-number {
                font-size: 2rem;
            }
            
            table {
                font-size: 0.8rem;
            }
            
            th, td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Monitor de Webhooks Bold</h1>
            <div class="subtitle">Sistema de monitoreo en tiempo real - Sequoia Speed</div>
        </div>

        <!-- Resumen del día actual -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number primary"><?= number_format($todaysSummary['total_webhooks'] ?? 0) ?></div>
                <div class="stat-label">Webhooks Hoy</div>
            </div>
            <div class="stat-card">
                <div class="stat-number success"><?= number_format($todaysSummary['exitosos'] ?? 0) ?></div>
                <div class="stat-label">Exitosos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number danger"><?= number_format($todaysSummary['errores'] ?? 0) ?></div>
                <div class="stat-label">Errores</div>
            </div>
            <div class="stat-card">
                <div class="stat-number warning"><?= number_format($todaysSummary['advertencias'] ?? 0) ?></div>
                <div class="stat-label">Advertencias</div>
            </div>
            <div class="stat-card">
                <div class="stat-number info"><?= number_format($todaysSummary['ordenes_procesadas'] ?? 0) ?></div>
                <div class="stat-label">Órdenes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number primary"><?= number_format($todaysSummary['tiempo_promedio'] ?? 0, 0) ?>ms</div>
                <div class="stat-label">Tiempo Promedio</div>
            </div>
        </div>

        <!-- Controles -->
        <div class="auto-refresh">
            <button class="refresh-btn" onclick="location.reload()">🔄 Actualizar</button>
            <label class="toggle-switch">
                <input type="checkbox" class="toggle-input" id="auto-refresh-toggle">
                <span class="toggle-slider"></span>
            </label>
            <span>Auto-actualizar cada 30s</span>
        </div>

        <!-- Cola de Retry -->
        <div class="section">
            <h2>🔄 Cola de Retry</h2>
            <?php if (empty($retryQueue)): ?>
                <div class="empty-state">
                    <div>✅</div>
                    <p>No hay elementos en la cola de retry</p>
                    <small>Todos los webhooks se están procesando correctamente</small>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Creado</th>
                                <th>Intentos</th>
                                <th>Próximo Retry</th>
                                <th>Estado</th>
                                <th>Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($retryQueue as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['id']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($item['created_at'])) ?></td>
                                <td><?= $item['attempts'] ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($item['next_retry_at'])) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $item['status'] ?>">
                                        <?= ucfirst($item['status']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars(substr($item['error_message'], 0, 100)) ?>...</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Logs Recientes -->
        <div class="section">
            <h2>📝 Logs Recientes</h2>
            <?php if (empty($recentLogs)): ?>
                <div class="empty-state">
                    <div>📭</div>
                    <p>No hay logs disponibles</p>
                    <small>Los logs aparecerán aquí cuando se procesen webhooks</small>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Evento</th>
                                <th>Order ID</th>
                                <th>Estado</th>
                                <th>Mensaje</th>
                                <th>Tiempo (ms)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentLogs as $log): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                                <td><?= htmlspecialchars($log['event_type']) ?></td>
                                <td><?= htmlspecialchars($log['order_id']) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $log['status'] ?>">
                                        <?= ucfirst($log['status']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars(substr($log['message'], 0, 80)) ?><?= strlen($log['message']) > 80 ? '...' : '' ?></td>
                                <td><?= $log['processing_time_ms'] ?? '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Estadísticas por Día -->
        <div class="section">
            <h2>📈 Estadísticas por Día (Últimos 7 días)</h2>
            <?php if (empty($stats)): ?>
                <div class="empty-state">
                    <div>📊</div>
                    <p>No hay estadísticas disponibles</p>
                    <small>Las estadísticas aparecerán cuando se procesen webhooks</small>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo de Evento</th>
                                <th>Estado</th>
                                <th>Total Eventos</th>
                                <th>Órdenes Únicas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats as $stat): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($stat['fecha'])) ?></td>
                                <td><?= htmlspecialchars($stat['event_type']) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $stat['status'] ?>">
                                        <?= ucfirst($stat['status']) ?>
                                    </span>
                                </td>
                                <td><?= number_format($stat['total']) ?></td>
                                <td><?= number_format($stat['ordenes_unicas']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Enlaces útiles -->
        <div class="section">
            <h2>🔗 Enlaces Útiles</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <a href="bold_webhook_enhanced.php" style="display: block; padding: 15px; background: var(--primary); color: white; text-decoration: none; border-radius: 8px; text-align: center;">
                    🚀 Webhook Mejorado
                </a>
                <a href="bold_retry_processor.php" style="display: block; padding: 15px; background: var(--success); color: white; text-decoration: none; border-radius: 8px; text-align: center;">
                    🔄 Procesar Retry Queue
                </a>
                <a href="bold_webhook.php" style="display: block; padding: 15px; background: var(--info); color: white; text-decoration: none; border-radius: 8px; text-align: center;">
                    📎 Webhook Original
                </a>
                <a href="index.php" style="display: block; padding: 15px; background: var(--dark); color: white; text-decoration: none; border-radius: 8px; text-align: center;">
                    🏠 Inicio
                </a>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh functionality
        let autoRefreshInterval;
        const autoRefreshToggle = document.getElementById('auto-refresh-toggle');

        autoRefreshToggle.addEventListener('change', function() {
            if (this.checked) {
                autoRefreshInterval = setInterval(() => {
                    location.reload();
                }, 30000); // 30 segundos
                console.log('Auto-refresh activado');
            } else {
                clearInterval(autoRefreshInterval);
                console.log('Auto-refresh desactivado');
            }
        });

        // Mostrar timestamp de última actualización
        const now = new Date();
        console.log('Monitor cargado a las: ' + now.toLocaleString());
    </script>
</body>
</html>
