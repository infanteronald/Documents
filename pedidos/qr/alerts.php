<?php
/**
 * Sistema de Alertas QR
 * Sequoia Speed - Sistema QR
 */

defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once dirname(__DIR__) . '/config_secure.php';
require_once dirname(__DIR__) . '/accesos/middleware/AuthMiddleware.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/xss_helper.php';
require_once __DIR__ . '/security_headers.php';

// Establecer headers de seguridad
setSecurityHeaders(true);

$auth = new AuthMiddleware($conn);
$current_user = $auth->requirePermission('qr', 'leer');

// Obtener alertas activas
$alerts_query = "SELECT 
                    ai.*,
                    p.nombre as producto_name,
                    p.sku as producto_sku,
                    a.nombre as almacen_name,
                    u.nombre as created_by_name
                 FROM alertas_inventario ai
                 LEFT JOIN productos p ON ai.producto_id = p.id
                 LEFT JOIN almacenes a ON ai.almacen_id = a.id
                 LEFT JOIN usuarios u ON ai.usuario_responsable = u.id
                 WHERE ai.activa = 1
                 ORDER BY ai.prioridad DESC, ai.fecha_creacion DESC
                 LIMIT 50";

$alerts_result = $conn->query($alerts_query);
$active_alerts = [];
while ($row = $alerts_result->fetch_assoc()) {
    $active_alerts[] = $row;
}

// Obtener configuraciones de alertas QR
$qr_alert_configs_query = "SELECT * FROM qr_system_config WHERE config_key LIKE 'alert_%' AND active = 1";
$configs_result = $conn->query($qr_alert_configs_query);
$alert_configs = [];
while ($row = $configs_result->fetch_assoc()) {
    $alert_configs[$row['config_key']] = json_decode($row['config_value'], true);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alertas QR - Sequoia Speed</title>
    <?php echo csrfMetaTag(); ?>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1e293b;
            --secondary-color: #334155;
            --success-color: #22c55e;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --info-color: #3b82f6;
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-color: #334155;
        }
        
        body {
            background-color: var(--bg-color);
            color: var(--text-primary);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            padding: 30px 0;
            margin-bottom: 30px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .alert-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            border-left: 4px solid;
            transition: all 0.3s ease;
        }
        
        .alert-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
        }
        
        .alert-card.priority-alta {
            border-left-color: var(--error-color);
        }
        
        .alert-card.priority-media {
            border-left-color: var(--warning-color);
        }
        
        .alert-card.priority-baja {
            border-left-color: var(--info-color);
        }
        
        .alert-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .alert-title {
            font-size: 1.1em;
            font-weight: 600;
            margin: 0;
        }
        
        .alert-meta {
            color: var(--text-secondary);
            font-size: 0.9em;
            margin-bottom: 10px;
        }
        
        .alert-description {
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .alert-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .priority-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .priority-alta {
            background: rgba(239, 68, 68, 0.2);
            color: var(--error-color);
        }
        
        .priority-media {
            background: rgba(245, 158, 11, 0.2);
            color: var(--warning-color);
        }
        
        .priority-baja {
            background: rgba(59, 130, 246, 0.2);
            color: var(--info-color);
        }
        
        .config-section {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }
        
        .form-control, .form-select {
            background: var(--bg-color);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .form-control:focus, .form-select:focus {
            background: var(--bg-color);
            border-color: var(--info-color);
            color: var(--text-primary);
            box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.25);
        }
        
        .alert-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid var(--border-color);
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9em;
        }
        
        .no-alerts {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        
        .alert-filters {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
        }
        
        .btn-alert {
            background: var(--error-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-alert:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 40px;
        }
        
        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--border-color);
            border-top-color: var(--info-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="page-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col">
                    <h1><i class="bi bi-exclamation-triangle"></i> Alertas QR</h1>
                    <p class="mb-0">Sistema de monitoreo y alertas para códigos QR</p>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#configModal">
                        <i class="bi bi-gear"></i> Configurar Alertas
                    </button>
                    <button class="btn btn-success ms-2" onclick="checkAlerts()">
                        <i class="bi bi-arrow-clockwise"></i> Verificar
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container-fluid">
        <!-- Alert Statistics -->
        <div class="alert-stats" id="alertStats">
            <div class="stat-card">
                <div class="stat-number" style="color: var(--error-color);" id="criticalAlerts">0</div>
                <div class="stat-label">Alertas Críticas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: var(--warning-color);" id="warningAlerts">0</div>
                <div class="stat-label">Advertencias</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: var(--info-color);" id="infoAlerts">0</div>
                <div class="stat-label">Informativas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: var(--success-color);" id="resolvedToday">0</div>
                <div class="stat-label">Resueltas Hoy</div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="alert-filters">
            <div class="row">
                <div class="col-md-3">
                    <label for="priorityFilter" class="form-label">Prioridad</label>
                    <select class="form-select" id="priorityFilter" onchange="filterAlerts()">
                        <option value="">Todas las prioridades</option>
                        <option value="alta">Alta</option>
                        <option value="media">Media</option>
                        <option value="baja">Baja</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="typeFilter" class="form-label">Tipo</label>
                    <select class="form-select" id="typeFilter" onchange="filterAlerts()">
                        <option value="">Todos los tipos</option>
                        <option value="stock_bajo">Stock Bajo</option>
                        <option value="error_scan">Error de Escaneo</option>
                        <option value="qr_inactive">QR Inactivo</option>
                        <option value="discrepancia">Discrepancia</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="statusFilter" class="form-label">Estado</label>
                    <select class="form-select" id="statusFilter" onchange="filterAlerts()">
                        <option value="">Todos los estados</option>
                        <option value="nueva">Nueva</option>
                        <option value="en_proceso">En Proceso</option>
                        <option value="resuelta">Resuelta</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="searchFilter" class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="searchFilter" placeholder="Buscar alertas..." onkeyup="filterAlerts()">
                </div>
            </div>
        </div>
        
        <!-- Loading -->
        <div class="loading" id="loading">
            <div class="loading-spinner"></div>
            <p>Cargando alertas...</p>
        </div>
        
        <!-- Alerts List -->
        <div id="alertsList">
            <?php if (empty($active_alerts)): ?>
                <div class="no-alerts">
                    <i class="bi bi-check-circle" style="font-size: 4em; color: var(--success-color);"></i>
                    <h3>¡No hay alertas activas!</h3>
                    <p>El sistema QR está funcionando correctamente.</p>
                </div>
            <?php else: ?>
                <?php foreach ($active_alerts as $alert): ?>
                    <div class="alert-card priority-<?= htmlspecialchars($alert['prioridad']) ?>" data-alert-id="<?= $alert['id'] ?>">
                        <div class="alert-header">
                            <div class="flex-grow-1">
                                <h5 class="alert-title"><?= htmlspecialchars($alert['titulo']) ?></h5>
                                <div class="alert-meta">
                                    <span class="priority-badge priority-<?= htmlspecialchars($alert['prioridad']) ?>">
                                        <?= ucfirst($alert['prioridad']) ?>
                                    </span>
                                    <span class="ms-3">
                                        <i class="bi bi-clock"></i> <?= date('d/m/Y H:i', strtotime($alert['fecha_creacion'])) ?>
                                    </span>
                                    <?php if ($alert['producto_name']): ?>
                                        <span class="ms-3">
                                            <i class="bi bi-box"></i> <?= htmlspecialchars($alert['producto_name']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert-description">
                            <?= htmlspecialchars($alert['descripcion']) ?>
                        </div>
                        
                        <div class="alert-actions">
                            <button class="btn btn-sm btn-primary" onclick="viewAlertDetails(<?= $alert['id'] ?>)">
                                <i class="bi bi-eye"></i> Ver Detalles
                            </button>
                            <button class="btn btn-sm btn-success" onclick="resolveAlert(<?= $alert['id'] ?>)">
                                <i class="bi bi-check"></i> Resolver
                            </button>
                            <button class="btn btn-sm btn-warning" onclick="snoozeAlert(<?= $alert['id'] ?>)">
                                <i class="bi bi-clock"></i> Posponer
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alert Configuration Modal -->
    <div class="modal fade" id="configModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="background: var(--card-bg); border: 1px solid var(--border-color);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title">Configuración de Alertas</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="alertConfigForm">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Alertas de Stock</h6>
                                <div class="mb-3">
                                    <label for="stockThreshold" class="form-label">Umbral de Stock Bajo</label>
                                    <input type="number" class="form-control" id="stockThreshold" value="10" min="0">
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="enableStockAlerts" checked>
                                        <label class="form-check-label" for="enableStockAlerts">
                                            Habilitar alertas de stock bajo
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h6>Alertas de Errores</h6>
                                <div class="mb-3">
                                    <label for="errorThreshold" class="form-label">Errores máximos por hora</label>
                                    <input type="number" class="form-control" id="errorThreshold" value="5" min="1">
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="enableErrorAlerts" checked>
                                        <label class="form-check-label" for="enableErrorAlerts">
                                            Habilitar alertas de errores de escaneo
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Alertas de Inactividad</h6>
                                <div class="mb-3">
                                    <label for="inactivityDays" class="form-label">Días sin escaneos</label>
                                    <input type="number" class="form-control" id="inactivityDays" value="7" min="1">
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="enableInactivityAlerts" checked>
                                        <label class="form-check-label" for="enableInactivityAlerts">
                                            Alertar QR sin actividad
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h6>Notificaciones</h6>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="emailNotifications" checked>
                                        <label class="form-check-label" for="emailNotifications">
                                            Enviar notificaciones por email
                                        </label>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="realTimeAlerts" checked>
                                        <label class="form-check-label" for="realTimeAlerts">
                                            Alertas en tiempo real
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="saveAlertConfig()">Guardar Configuración</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize alerts system
        document.addEventListener('DOMContentLoaded', function() {
            loadAlertStats();
            
            // Auto-refresh every 2 minutes
            setInterval(() => {
                if (!document.hidden) {
                    checkAlerts();
                }
            }, 120000);
        });
        
        async function loadAlertStats() {
            try {
                // Mock data for now - in production, this would call an API
                document.getElementById('criticalAlerts').textContent = '<?= count(array_filter($active_alerts, fn($a) => $a["prioridad"] === "alta")) ?>';
                document.getElementById('warningAlerts').textContent = '<?= count(array_filter($active_alerts, fn($a) => $a["prioridad"] === "media")) ?>';
                document.getElementById('infoAlerts').textContent = '<?= count(array_filter($active_alerts, fn($a) => $a["prioridad"] === "baja")) ?>';
                document.getElementById('resolvedToday').textContent = '0'; // Would be calculated from API
                
            } catch (error) {
                console.error('Error loading alert stats:', error);
            }
        }
        
        async function checkAlerts() {
            showLoading(true);
            
            try {
                // Call the alert checking API
                const response = await fetch('/qr/api/alerts.php?action=check');
                const result = await response.json();
                
                if (result.success) {
                    if (result.new_alerts > 0) {
                        showNotification(`Se encontraron ${result.new_alerts} nuevas alertas`, 'warning');
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        showNotification('Sistema verificado - Sin nuevas alertas', 'success');
                    }
                }
                
            } catch (error) {
                console.error('Error checking alerts:', error);
                showNotification('Error al verificar alertas', 'error');
            } finally {
                showLoading(false);
            }
        }
        
        async function resolveAlert(alertId) {
            if (!confirm('¿Está seguro de que desea resolver esta alerta?')) {
                return;
            }
            
            try {
                const response = await fetch('/qr/api/alerts.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'resolve',
                        alert_id: alertId
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    const alertCard = document.querySelector(`[data-alert-id="${alertId}"]`);
                    if (alertCard) {
                        alertCard.style.opacity = '0.5';
                        alertCard.style.transform = 'scale(0.95)';
                        setTimeout(() => {
                            alertCard.remove();
                            updateAlertStats();
                        }, 300);
                    }
                    showNotification('Alerta resuelta correctamente', 'success');
                } else {
                    showNotification('Error al resolver la alerta: ' + result.error, 'error');
                }
                
            } catch (error) {
                console.error('Error resolving alert:', error);
                showNotification('Error de conexión', 'error');
            }
        }
        
        async function snoozeAlert(alertId) {
            const hours = prompt('¿Por cuántas horas desea posponer esta alerta?', '2');
            if (!hours || isNaN(hours)) {
                return;
            }
            
            try {
                const response = await fetch('/qr/api/alerts.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'snooze',
                        alert_id: alertId,
                        hours: parseInt(hours)
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification(`Alerta pospuesta por ${hours} horas`, 'info');
                } else {
                    showNotification('Error al posponer la alerta: ' + result.error, 'error');
                }
                
            } catch (error) {
                console.error('Error snoozing alert:', error);
                showNotification('Error de conexión', 'error');
            }
        }
        
        function viewAlertDetails(alertId) {
            // TODO: Implement alert details modal
            alert(`Ver detalles de la alerta ${alertId} - Funcionalidad en desarrollo`);
        }
        
        function filterAlerts() {
            const priority = document.getElementById('priorityFilter').value;
            const type = document.getElementById('typeFilter').value;
            const status = document.getElementById('statusFilter').value;
            const search = document.getElementById('searchFilter').value.toLowerCase();
            
            const alerts = document.querySelectorAll('.alert-card');
            
            alerts.forEach(alert => {
                let show = true;
                
                // Filter by priority
                if (priority && !alert.classList.contains(`priority-${priority}`)) {
                    show = false;
                }
                
                // Filter by search term
                if (search && !alert.textContent.toLowerCase().includes(search)) {
                    show = false;
                }
                
                alert.style.display = show ? 'block' : 'none';
            });
        }
        
        async function saveAlertConfig() {
            const config = {
                stock_threshold: document.getElementById('stockThreshold').value,
                enable_stock_alerts: document.getElementById('enableStockAlerts').checked,
                error_threshold: document.getElementById('errorThreshold').value,
                enable_error_alerts: document.getElementById('enableErrorAlerts').checked,
                inactivity_days: document.getElementById('inactivityDays').value,
                enable_inactivity_alerts: document.getElementById('enableInactivityAlerts').checked,
                email_notifications: document.getElementById('emailNotifications').checked,
                realtime_alerts: document.getElementById('realTimeAlerts').checked
            };
            
            try {
                const response = await fetch('/qr/api/alerts.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'save_config',
                        config: config
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Configuración guardada correctamente', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('configModal')).hide();
                } else {
                    showNotification('Error al guardar la configuración: ' + result.error, 'error');
                }
                
            } catch (error) {
                console.error('Error saving config:', error);
                showNotification('Error de conexión', 'error');
            }
        }
        
        function updateAlertStats() {
            const activeAlerts = document.querySelectorAll('.alert-card:not([style*="display: none"])');
            const criticalCount = document.querySelectorAll('.alert-card.priority-alta:not([style*="display: none"])').length;
            const warningCount = document.querySelectorAll('.alert-card.priority-media:not([style*="display: none"])').length;
            const infoCount = document.querySelectorAll('.alert-card.priority-baja:not([style*="display: none"])').length;
            
            document.getElementById('criticalAlerts').textContent = criticalCount;
            document.getElementById('warningAlerts').textContent = warningCount;
            document.getElementById('infoAlerts').textContent = infoCount;
        }
        
        function showLoading(show) {
            document.getElementById('loading').style.display = show ? 'block' : 'none';
        }
        
        function showNotification(message, type = 'info') {
            // Create toast notification
            const toast = document.createElement('div');
            toast.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            toast.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(toast);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 5000);
        }
    </script>
</body>
</html>