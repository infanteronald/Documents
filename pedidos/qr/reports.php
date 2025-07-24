<?php
/**
 * Dashboard de Analytics QR
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

// Obtener almacenes para filtros
$almacenes_query = "SELECT id, nombre, codigo FROM almacenes WHERE activo = 1 ORDER BY nombre";
$almacenes_result = $conn->query($almacenes_query);
$almacenes = [];
while ($row = $almacenes_result->fetch_assoc()) {
    $almacenes[] = $row;
}

// Obtener workflows para filtros
$workflows_query = "SELECT id, workflow_name, workflow_type FROM qr_workflow_config WHERE active = 1 ORDER BY workflow_name";
$workflows_result = $conn->query($workflows_query);
$workflows = [];
while ($row = $workflows_result->fetch_assoc()) {
    $workflows[] = $row;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics QR - Sequoia Speed</title>
    <?php echo csrfMetaTag(); ?>
    
    <!-- CSS Framework -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    
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
        
        .metric-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
        }
        
        .metric-value {
            font-size: 2.5em;
            font-weight: 700;
            margin: 10px 0;
        }
        
        .metric-label {
            color: var(--text-secondary);
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .metric-change {
            font-size: 0.85em;
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 4px;
            margin-top: 10px;
        }
        
        .metric-change.positive {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success-color);
        }
        
        .metric-change.negative {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
        }
        
        .chart-container {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }
        
        .filter-section {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
        }
        
        .filter-section label {
            color: var(--text-secondary);
            font-size: 0.9em;
            margin-bottom: 5px;
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
        
        .table-container {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            overflow-x: auto;
        }
        
        .table {
            color: var(--text-primary);
        }
        
        .table th {
            background: var(--bg-color);
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85em;
            letter-spacing: 0.5px;
            border-color: var(--border-color);
        }
        
        .table td {
            border-color: var(--border-color);
        }
        
        .activity-timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .activity-timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--border-color);
        }
        
        .activity-item {
            position: relative;
            padding-bottom: 20px;
        }
        
        .activity-item::before {
            content: '';
            position: absolute;
            left: -24px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--info-color);
            border: 2px solid var(--card-bg);
        }
        
        .activity-item.success::before {
            background: var(--success-color);
        }
        
        .activity-item.error::before {
            background: var(--error-color);
        }
        
        .btn-refresh {
            background: var(--info-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-refresh:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }
        
        .export-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .export-btn {
            background: var(--secondary-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.9em;
            transition: all 0.3s ease;
        }
        
        .export-btn:hover {
            background: var(--primary-color);
            transform: translateY(-1px);
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--border-color);
            border-top-color: var(--info-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .metric-value {
                font-size: 2em;
            }
            
            .export-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Header -->
    <div class="page-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col">
                    <h1><i class="bi bi-graph-up"></i> Analytics QR</h1>
                    <p class="mb-0">Dashboard de métricas y reportes del sistema QR</p>
                </div>
                <div class="col-auto">
                    <button class="btn-refresh" onclick="refreshDashboard()">
                        <i class="bi bi-arrow-clockwise"></i> Actualizar
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container-fluid">
        <!-- Filters -->
        <div class="filter-section">
            <div class="row">
                <div class="col-md-3">
                    <label for="dateRange">Período</label>
                    <select class="form-select" id="dateRange" onchange="updateDateRange()">
                        <option value="today">Hoy</option>
                        <option value="yesterday">Ayer</option>
                        <option value="week" selected>Últimos 7 días</option>
                        <option value="month">Últimos 30 días</option>
                        <option value="quarter">Últimos 90 días</option>
                        <option value="custom">Personalizado</option>
                    </select>
                </div>
                <div class="col-md-2" id="customDateFrom" style="display: none;">
                    <label for="dateFrom">Desde</label>
                    <input type="date" class="form-control" id="dateFrom">
                </div>
                <div class="col-md-2" id="customDateTo" style="display: none;">
                    <label for="dateTo">Hasta</label>
                    <input type="date" class="form-control" id="dateTo">
                </div>
                <div class="col-md-3">
                    <label for="warehouseFilter">Almacén</label>
                    <select class="form-select" id="warehouseFilter">
                        <option value="">Todos los almacenes</option>
                        <?php foreach ($almacenes as $almacen): ?>
                            <option value="<?= $almacen['id'] ?>"><?= htmlspecialchars($almacen['codigo'] . ' - ' . $almacen['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="workflowFilter">Workflow</label>
                    <select class="form-select" id="workflowFilter">
                        <option value="">Todos los workflows</option>
                        <?php foreach ($workflows as $workflow): ?>
                            <option value="<?= $workflow['id'] ?>"><?= htmlspecialchars($workflow['workflow_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button class="btn btn-primary w-100" onclick="applyFilters()">
                        <i class="bi bi-funnel"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Key Metrics -->
        <div class="row" id="keyMetrics">
            <div class="col-lg-3 col-md-6">
                <div class="metric-card">
                    <div class="metric-label">Total Escaneos</div>
                    <div class="metric-value" id="totalScans">0</div>
                    <div class="metric-change positive">
                        <i class="bi bi-arrow-up"></i> <span id="scansChange">0%</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="metric-card">
                    <div class="metric-label">QR Activos</div>
                    <div class="metric-value" id="activeQR">0</div>
                    <div class="metric-change positive">
                        <i class="bi bi-arrow-up"></i> <span id="qrChange">0%</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="metric-card">
                    <div class="metric-label">Usuarios Activos</div>
                    <div class="metric-value" id="activeUsers">0</div>
                    <div class="metric-change positive">
                        <i class="bi bi-arrow-up"></i> <span id="usersChange">0%</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="metric-card">
                    <div class="metric-label">Tasa de Éxito</div>
                    <div class="metric-value" id="successRate">0%</div>
                    <div class="metric-change positive">
                        <i class="bi bi-arrow-up"></i> <span id="rateChange">0%</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts Row 1 -->
        <div class="row">
            <div class="col-lg-8">
                <div class="chart-container">
                    <h5>Actividad por Período</h5>
                    <canvas id="activityChart" height="100"></canvas>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-container">
                    <h5>Distribución por Acción</h5>
                    <canvas id="actionChart" height="100"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Charts Row 2 -->
        <div class="row">
            <div class="col-lg-6">
                <div class="chart-container">
                    <h5>Rendimiento por Hora</h5>
                    <canvas id="hourlyChart" height="100"></canvas>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="chart-container">
                    <h5>Top Productos Escaneados</h5>
                    <canvas id="productsChart" height="100"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Activity Timeline & Top Users -->
        <div class="row">
            <div class="col-lg-6">
                <div class="table-container">
                    <h5>Actividad Reciente</h5>
                    <div class="activity-timeline" id="activityTimeline">
                        <!-- Timeline items will be loaded here -->
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="table-container">
                    <h5>Top Usuarios</h5>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Escaneos</th>
                                <th>Tasa Éxito</th>
                                <th>Última Actividad</th>
                            </tr>
                        </thead>
                        <tbody id="topUsersTable">
                            <!-- User rows will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Export Options -->
        <div class="export-buttons">
            <button class="export-btn" onclick="exportReport('pdf')">
                <i class="bi bi-file-pdf"></i> Exportar PDF
            </button>
            <button class="export-btn" onclick="exportReport('excel')">
                <i class="bi bi-file-excel"></i> Exportar Excel
            </button>
            <button class="export-btn" onclick="exportReport('csv')">
                <i class="bi bi-file-text"></i> Exportar CSV
            </button>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Dashboard Script -->
    <script>
        // Chart instances
        let activityChart, actionChart, hourlyChart, productsChart;
        
        // Current filters
        let currentFilters = {
            dateRange: 'week',
            dateFrom: null,
            dateTo: null,
            warehouse: '',
            workflow: ''
        };
        
        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
            loadDashboardData();
            
            // Auto-refresh every 5 minutes
            setInterval(() => {
                if (!document.hidden) {
                    loadDashboardData();
                }
            }, 300000);
        });
        
        function initializeCharts() {
            // Chart.js default configurations for dark theme
            Chart.defaults.color = '#94a3b8';
            Chart.defaults.borderColor = '#334155';
            
            // Activity Chart
            const activityCtx = document.getElementById('activityChart').getContext('2d');
            activityChart = new Chart(activityCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Escaneos',
                        data: [],
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Errores',
                        data: [],
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#334155'
                            }
                        },
                        x: {
                            grid: {
                                color: '#334155'
                            }
                        }
                    }
                }
            });
            
            // Action Distribution Chart
            const actionCtx = document.getElementById('actionChart').getContext('2d');
            actionChart = new Chart(actionCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Entrada', 'Salida', 'Conteo', 'Consulta'],
                    datasets: [{
                        data: [],
                        backgroundColor: [
                            '#22c55e',
                            '#ef4444',
                            '#3b82f6',
                            '#f59e0b'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    }
                }
            });
            
            // Hourly Performance Chart
            const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
            hourlyChart = new Chart(hourlyCtx, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Escaneos por hora',
                        data: [],
                        backgroundColor: '#3b82f6'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#334155'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
            
            // Top Products Chart
            const productsCtx = document.getElementById('productsChart').getContext('2d');
            productsChart = new Chart(productsCtx, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Escaneos',
                        data: [],
                        backgroundColor: '#22c55e'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: {
                                color: '#334155'
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }
        
        async function loadDashboardData() {
            showLoading(true);
            
            try {
                // Load dashboard stats
                const response = await fetch('/qr/api/reports.php?type=dashboard');
                const result = await response.json();
                
                if (result.success) {
                    updateMetrics(result.data);
                    updateCharts(result.data);
                    updateTables(result.data);
                }
                
                // Load recent activity
                loadRecentActivity();
                
            } catch (error) {
                console.error('Error loading dashboard:', error);
                showError('Error al cargar el dashboard');
            } finally {
                showLoading(false);
            }
        }
        
        function updateMetrics(data) {
            const { general_stats, today_stats } = data;
            
            // Update metric values
            document.getElementById('totalScans').textContent = formatNumber(general_stats.total_scans);
            document.getElementById('activeQR').textContent = formatNumber(general_stats.total_qr_codes);
            document.getElementById('activeUsers').textContent = formatNumber(general_stats.active_users);
            
            // Calculate success rate
            const successRate = today_stats.scans_today > 0 
                ? ((today_stats.scans_today - today_stats.errors_today) / today_stats.scans_today * 100).toFixed(1)
                : 100;
            document.getElementById('successRate').textContent = successRate + '%';
            
            // Update change indicators (mock data for demo)
            updateChangeIndicator('scansChange', 12.5);
            updateChangeIndicator('qrChange', 5.2);
            updateChangeIndicator('usersChange', 8.7);
            updateChangeIndicator('rateChange', 2.1);
        }
        
        function updateCharts(data) {
            // Update Activity Chart
            if (data.temporal_data) {
                const labels = data.temporal_data.map(d => formatDate(d.date));
                const scans = data.temporal_data.map(d => d.total_scans);
                const errors = data.temporal_data.map(d => d.errors);
                
                activityChart.data.labels = labels;
                activityChart.data.datasets[0].data = scans;
                activityChart.data.datasets[1].data = errors;
                activityChart.update();
            }
            
            // Update Action Distribution
            if (data.today_stats) {
                const actions = [
                    data.today_stats.entries_today,
                    data.today_stats.exits_today,
                    data.today_stats.counts_today,
                    data.today_stats.scans_today - data.today_stats.entries_today - data.today_stats.exits_today - data.today_stats.counts_today
                ];
                
                actionChart.data.datasets[0].data = actions;
                actionChart.update();
            }
            
            // Update Top Products
            if (data.top_products) {
                const labels = data.top_products.map(p => p.producto_name.substring(0, 20) + '...');
                const counts = data.top_products.map(p => p.scan_count);
                
                productsChart.data.labels = labels;
                productsChart.data.datasets[0].data = counts;
                productsChart.update();
            }
            
            // Load hourly data separately
            loadHourlyData();
        }
        
        async function loadHourlyData() {
            try {
                const response = await fetch('/qr/api/reports.php?type=performance');
                const result = await response.json();
                
                if (result.success && result.data.hourly_performance) {
                    const hours = result.data.hourly_performance.map(h => h.hour + ':00');
                    const counts = result.data.hourly_performance.map(h => h.scan_count);
                    
                    hourlyChart.data.labels = hours;
                    hourlyChart.data.datasets[0].data = counts;
                    hourlyChart.update();
                }
            } catch (error) {
                console.error('Error loading hourly data:', error);
            }
        }
        
        async function loadRecentActivity() {
            try {
                const response = await fetch('/qr/api/query.php?type=recent_activity&limit=10');
                const result = await response.json();
                
                if (result.success) {
                    const timeline = document.getElementById('activityTimeline');
                    timeline.innerHTML = '';
                    
                    result.data.recent_activity.forEach(activity => {
                        const statusClass = activity.processing_status === 'success' ? 'success' : 'error';
                        const timeAgo = getTimeAgo(activity.scanned_at);
                        
                        const item = `
                            <div class="activity-item ${statusClass}">
                                <div class="d-flex justify-content-between">
                                    <strong>${activity.user_name}</strong>
                                    <small class="text-muted">${timeAgo}</small>
                                </div>
                                <div class="text-muted">
                                    ${activity.action_performed} - ${activity.producto_name || 'N/A'}
                                    ${activity.quantity_affected > 1 ? `(x${activity.quantity_affected})` : ''}
                                </div>
                            </div>
                        `;
                        
                        timeline.innerHTML += item;
                    });
                }
            } catch (error) {
                console.error('Error loading recent activity:', error);
            }
        }
        
        function updateTables(data) {
            // Update top users table
            if (data.warehouse_stats) {
                const tbody = document.getElementById('topUsersTable');
                tbody.innerHTML = '';
                
                // This is warehouse data, we need to load user data separately
                loadTopUsers();
            }
        }
        
        async function loadTopUsers() {
            try {
                const response = await fetch('/qr/api/reports.php?type=user_activity');
                const result = await response.json();
                
                if (result.success && result.data.user_stats) {
                    const tbody = document.getElementById('topUsersTable');
                    tbody.innerHTML = '';
                    
                    result.data.user_stats.slice(0, 5).forEach(user => {
                        const successRate = user.total_scans > 0 
                            ? ((user.total_scans - user.failed_scans) / user.total_scans * 100).toFixed(1)
                            : 100;
                        
                        const row = `
                            <tr>
                                <td>${user.nombre}</td>
                                <td>${user.total_scans}</td>
                                <td>${successRate}%</td>
                                <td>${formatDateTime(user.last_activity)}</td>
                            </tr>
                        `;
                        
                        tbody.innerHTML += row;
                    });
                }
            } catch (error) {
                console.error('Error loading top users:', error);
            }
        }
        
        function updateDateRange() {
            const range = document.getElementById('dateRange').value;
            const customFrom = document.getElementById('customDateFrom');
            const customTo = document.getElementById('customDateTo');
            
            if (range === 'custom') {
                customFrom.style.display = 'block';
                customTo.style.display = 'block';
            } else {
                customFrom.style.display = 'none';
                customTo.style.display = 'none';
            }
        }
        
        function applyFilters() {
            currentFilters.dateRange = document.getElementById('dateRange').value;
            currentFilters.warehouse = document.getElementById('warehouseFilter').value;
            currentFilters.workflow = document.getElementById('workflowFilter').value;
            
            if (currentFilters.dateRange === 'custom') {
                currentFilters.dateFrom = document.getElementById('dateFrom').value;
                currentFilters.dateTo = document.getElementById('dateTo').value;
            }
            
            loadDashboardData();
        }
        
        function refreshDashboard() {
            loadDashboardData();
        }
        
        async function exportReport(format) {
            showLoading(true);
            
            try {
                // TODO: Implement export functionality
                alert(`Exportar a ${format.toUpperCase()} - Funcionalidad en desarrollo`);
            } catch (error) {
                console.error('Error exporting report:', error);
                showError('Error al exportar el reporte');
            } finally {
                showLoading(false);
            }
        }
        
        // Utility functions
        function formatNumber(num) {
            return new Intl.NumberFormat('es-CO').format(num);
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('es-CO', { month: 'short', day: 'numeric' });
        }
        
        function formatDateTime(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleString('es-CO', { 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        function getTimeAgo(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const seconds = Math.floor((now - date) / 1000);
            
            if (seconds < 60) return 'hace ' + seconds + ' segundos';
            const minutes = Math.floor(seconds / 60);
            if (minutes < 60) return 'hace ' + minutes + ' minutos';
            const hours = Math.floor(minutes / 60);
            if (hours < 24) return 'hace ' + hours + ' horas';
            const days = Math.floor(hours / 24);
            return 'hace ' + days + ' días';
        }
        
        function updateChangeIndicator(elementId, value) {
            const element = document.getElementById(elementId);
            const parent = element.parentElement;
            
            element.textContent = Math.abs(value) + '%';
            
            if (value >= 0) {
                parent.classList.add('positive');
                parent.classList.remove('negative');
                parent.querySelector('i').className = 'bi bi-arrow-up';
            } else {
                parent.classList.add('negative');
                parent.classList.remove('positive');
                parent.querySelector('i').className = 'bi bi-arrow-down';
            }
        }
        
        function showLoading(show) {
            document.getElementById('loadingOverlay').style.display = show ? 'flex' : 'none';
        }
        
        function showError(message) {
            // TODO: Implement better error handling
            console.error(message);
        }
    </script>
</body>
</html>