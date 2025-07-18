<?php
/**
 * Reporte Financiero - Análisis de Ingresos y Gastos
 * Sequoia Speed - Sistema Integrado
 */

// Requerir autenticación
require_once '../accesos/auth_helper.php';

// Proteger la página - requiere permisos de reportes
$current_user = auth_require('reportes', 'leer');

// Registrar acceso
auth_log('read', 'reportes', 'Acceso al reporte financiero');

// Configurar período de reporte
$periodo = $_GET['periodo'] ?? 'mes';
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');

// Configurar filtros según período
switch ($periodo) {
    case 'hoy':
        $fecha_inicio = $fecha_fin = date('Y-m-d');
        break;
    case 'ayer':
        $fecha_inicio = $fecha_fin = date('Y-m-d', strtotime('-1 day'));
        break;
    case 'semana':
        $fecha_inicio = date('Y-m-d', strtotime('monday this week'));
        $fecha_fin = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'mes':
        $fecha_inicio = date('Y-m-01');
        $fecha_fin = date('Y-m-t');
        break;
    case 'trimestre':
        $mes_actual = date('n');
        $trimestre = ceil($mes_actual / 3);
        $fecha_inicio = date('Y-' . sprintf('%02d', ($trimestre - 1) * 3 + 1) . '-01');
        $fecha_fin = date('Y-m-t', strtotime($fecha_inicio . ' +2 months'));
        break;
    case 'año':
        $fecha_inicio = date('Y-01-01');
        $fecha_fin = date('Y-12-31');
        break;
}

// Obtener datos financieros
$resumen = [
    'ingresos_brutos' => 0,
    'descuentos' => 0,
    'ingresos_netos' => 0,
    'comisiones' => 0,
    'utilidad' => 0,
    'ventas_por_metodo' => [],
    'flujo_diario' => [],
    'top_ciudades' => []
];

try {
    global $conn;
    
    // Consulta principal de ingresos
    $stmt = $conn->prepare("
        SELECT 
            SUM(monto) as ingresos_brutos,
            SUM(descuento) as descuentos_totales,
            SUM(monto - descuento) as ingresos_netos,
            AVG(monto) as ticket_promedio,
            COUNT(*) as total_transacciones,
            metodo_pago,
            DATE(fecha) as fecha_dia
        FROM pedidos_detal 
        WHERE DATE(fecha) BETWEEN ? AND ?
        AND estado != 'Anulado'
        GROUP BY metodo_pago, DATE(fecha)
        ORDER BY fecha_dia DESC
    ");
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $flujo_diario = [];
    $metodos_pago = [];
    
    while ($row = $result->fetch_assoc()) {
        $resumen['ingresos_brutos'] += $row['ingresos_brutos'];
        $resumen['descuentos'] += $row['descuentos_totales'];
        $resumen['ingresos_netos'] += $row['ingresos_netos'];
        
        // Flujo diario
        $dia = $row['fecha_dia'];
        if (!isset($flujo_diario[$dia])) {
            $flujo_diario[$dia] = 0;
        }
        $flujo_diario[$dia] += $row['ingresos_netos'];
        
        // Métodos de pago
        $metodo = $row['metodo_pago'] ?? 'No especificado';
        if (!isset($metodos_pago[$metodo])) {
            $metodos_pago[$metodo] = 0;
        }
        $metodos_pago[$metodo] += $row['ingresos_netos'];
    }
    
    $resumen['flujo_diario'] = $flujo_diario;
    $resumen['ventas_por_metodo'] = $metodos_pago;
    
    // Calcular comisiones estimadas (3% del total)
    $resumen['comisiones'] = $resumen['ingresos_netos'] * 0.03;
    $resumen['utilidad'] = $resumen['ingresos_netos'] - $resumen['comisiones'];
    
    // Top ciudades por ingresos
    $stmt = $conn->prepare("
        SELECT 
            ciudad,
            SUM(monto - descuento) as ingresos,
            COUNT(*) as pedidos
        FROM pedidos_detal 
        WHERE DATE(fecha) BETWEEN ? AND ?
        AND estado != 'Anulado'
        GROUP BY ciudad
        ORDER BY ingresos DESC
        LIMIT 10
    ");
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $resumen['top_ciudades'][] = $row;
    }
    
} catch (Exception $e) {
    error_log("Error obteniendo reporte financiero: " . $e->getMessage());
}

// Formatear datos para gráficos
$chart_data = [
    'flujo_diario' => json_encode($resumen['flujo_diario']),
    'metodos_pago' => json_encode($resumen['ventas_por_metodo']),
    'ciudades' => json_encode(array_column($resumen['top_ciudades'], 'ingresos'))
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Financiero - Sequoia Speed</title>
    <link rel="icon" href="../logo.png" type="image/png">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --vscode-bg: #1e1e1e;
            --vscode-sidebar: #252526;
            --vscode-border: #3e3e42;
            --vscode-text: #cccccc;
            --vscode-text-muted: #999999;
            --vscode-text-light: #ffffff;
            --apple-blue: #007aff;
            --apple-teal: #30d158;
            --apple-orange: #ff9f0a;
            --apple-red: #ff453a;
            --apple-purple: #bf5af2;
            --apple-pink: #ff2d92;
            --glass-bg: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
            --space-sm: 8px;
            --space-md: 16px;
            --space-lg: 24px;
            --space-xl: 32px;
            --radius-sm: 6px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --transition-base: 0.2s ease;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'SF Pro Display', 'Helvetica Neue', Arial, sans-serif;
            background: var(--vscode-bg);
            color: var(--vscode-text);
            line-height: 1.6;
            min-height: 100vh;
        }

        .header {
            background: var(--vscode-sidebar);
            border-bottom: 1px solid var(--vscode-border);
            padding: var(--space-lg) 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 var(--space-lg);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 1.8rem;
            color: var(--vscode-text-light);
            display: flex;
            align-items: center;
            gap: var(--space-md);
        }

        .nav-buttons {
            display: flex;
            gap: var(--space-md);
        }

        .btn {
            padding: var(--space-sm) var(--space-md);
            border: none;
            border-radius: var(--radius-sm);
            color: white;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all var(--transition-base);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .btn-primary {
            background: var(--apple-blue);
        }

        .btn-primary:hover {
            background: #0056d3;
        }

        .btn-secondary {
            background: var(--apple-purple);
        }

        .btn-secondary:hover {
            background: #9d4edd;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: var(--space-lg);
        }

        .filters-section {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-md);
            padding: var(--space-lg);
            margin-bottom: var(--space-xl);
        }

        .filters-form {
            display: flex;
            gap: var(--space-md);
            flex-wrap: wrap;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: var(--space-sm);
        }

        .filter-label {
            font-size: 0.9rem;
            color: var(--vscode-text-light);
            font-weight: 500;
        }

        .filter-select,
        .filter-input {
            padding: var(--space-sm) var(--space-md);
            background: var(--vscode-sidebar);
            border: 1px solid var(--vscode-border);
            border-radius: var(--radius-sm);
            color: var(--vscode-text);
            font-size: 0.9rem;
        }

        .filter-select:focus,
        .filter-input:focus {
            outline: none;
            border-color: var(--apple-blue);
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--space-lg);
            margin-bottom: var(--space-xl);
        }

        .summary-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            padding: var(--space-xl);
            text-align: center;
            transition: all var(--transition-base);
            position: relative;
            overflow: hidden;
        }

        .summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            opacity: 0.8;
        }

        .summary-card.ingresos-brutos::before {
            background: linear-gradient(90deg, var(--apple-teal), var(--apple-blue));
        }

        .summary-card.ingresos-netos::before {
            background: linear-gradient(90deg, var(--apple-blue), var(--apple-purple));
        }

        .summary-card.utilidad::before {
            background: linear-gradient(90deg, var(--apple-orange), var(--apple-red));
        }

        .summary-card.comisiones::before {
            background: linear-gradient(90deg, var(--apple-red), var(--apple-pink));
        }

        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .summary-icon {
            font-size: 2.5rem;
            margin-bottom: var(--space-md);
        }

        .summary-number {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: var(--space-sm);
            color: var(--vscode-text-light);
        }

        .summary-label {
            font-size: 0.9rem;
            color: var(--vscode-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .charts-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: var(--space-xl);
            margin-bottom: var(--space-xl);
        }

        .chart-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            padding: var(--space-xl);
        }

        .chart-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--vscode-text-light);
            margin-bottom: var(--space-lg);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        .table-section {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }

        .table-header {
            padding: var(--space-lg);
            border-bottom: 1px solid var(--vscode-border);
        }

        .table-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--vscode-text-light);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .table-container {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: var(--space-md);
            text-align: left;
            border-bottom: 1px solid var(--vscode-border);
        }

        .table th {
            background: var(--vscode-sidebar);
            font-weight: 600;
            color: var(--vscode-text-light);
            font-size: 0.9rem;
        }

        .table tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .monto {
            color: var(--apple-teal);
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: var(--space-xl) var(--space-lg);
            color: var(--vscode-text-muted);
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: var(--space-md);
            }
            
            .filters-form {
                flex-direction: column;
            }
            
            .charts-section {
                grid-template-columns: 1fr;
            }
            
            .summary-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }
    </style>
</head>

<body>
    <header class="header">
        <div class="header-content">
            <h1>💰 Reporte Financiero</h1>
            <div class="nav-buttons">
                <a href="index.php" class="btn btn-secondary">📊 Reportes</a>
                <a href="../index.php" class="btn btn-secondary">🏠 Inicio</a>
                <a href="ventas.php" class="btn btn-primary">📈 Ventas</a>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Filtros -->
        <div class="filters-section">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label class="filter-label">Período</label>
                    <select name="periodo" class="filter-select" onchange="this.form.submit()">
                        <option value="hoy" <?php echo $periodo === 'hoy' ? 'selected' : ''; ?>>Hoy</option>
                        <option value="ayer" <?php echo $periodo === 'ayer' ? 'selected' : ''; ?>>Ayer</option>
                        <option value="semana" <?php echo $periodo === 'semana' ? 'selected' : ''; ?>>Esta Semana</option>
                        <option value="mes" <?php echo $periodo === 'mes' ? 'selected' : ''; ?>>Este Mes</option>
                        <option value="trimestre" <?php echo $periodo === 'trimestre' ? 'selected' : ''; ?>>Este Trimestre</option>
                        <option value="año" <?php echo $periodo === 'año' ? 'selected' : ''; ?>>Este Año</option>
                        <option value="personalizado" <?php echo $periodo === 'personalizado' ? 'selected' : ''; ?>>Personalizado</option>
                    </select>
                </div>
                
                <?php if ($periodo === 'personalizado'): ?>
                <div class="filter-group">
                    <label class="filter-label">Fecha Inicio</label>
                    <input type="date" name="fecha_inicio" class="filter-input" value="<?php echo $fecha_inicio; ?>">
                </div>
                <div class="filter-group">
                    <label class="filter-label">Fecha Fin</label>
                    <input type="date" name="fecha_fin" class="filter-input" value="<?php echo $fecha_fin; ?>">
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Resumen -->
        <div class="summary-grid">
            <div class="summary-card ingresos-brutos">
                <div class="summary-icon">💵</div>
                <div class="summary-number">$<?php echo number_format($resumen['ingresos_brutos'], 0, ',', '.'); ?></div>
                <div class="summary-label">Ingresos Brutos</div>
            </div>
            <div class="summary-card ingresos-netos">
                <div class="summary-icon">💰</div>
                <div class="summary-number">$<?php echo number_format($resumen['ingresos_netos'], 0, ',', '.'); ?></div>
                <div class="summary-label">Ingresos Netos</div>
            </div>
            <div class="summary-card utilidad">
                <div class="summary-icon">📈</div>
                <div class="summary-number">$<?php echo number_format($resumen['utilidad'], 0, ',', '.'); ?></div>
                <div class="summary-label">Utilidad Estimada</div>
            </div>
            <div class="summary-card comisiones">
                <div class="summary-icon">🔄</div>
                <div class="summary-number">$<?php echo number_format($resumen['comisiones'], 0, ',', '.'); ?></div>
                <div class="summary-label">Comisiones</div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="charts-section">
            <div class="chart-card">
                <h3 class="chart-title">📊 Flujo de Caja Diario</h3>
                <div class="chart-container">
                    <canvas id="flujoChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h3 class="chart-title">💳 Ingresos por Método de Pago</h3>
                <div class="chart-container">
                    <canvas id="metodosChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Tabla de ciudades -->
        <div class="table-section">
            <div class="table-header">
                <h3 class="table-title">🏙️ Top Ciudades por Ingresos</h3>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Ciudad</th>
                            <th>Ingresos</th>
                            <th>Pedidos</th>
                            <th>Promedio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($resumen['top_ciudades'])): ?>
                            <tr>
                                <td colspan="4" class="empty-state">
                                    📊 No hay datos en el período seleccionado
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($resumen['top_ciudades'] as $ciudad): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($ciudad['ciudad']); ?></strong></td>
                                    <td class="monto">$<?php echo number_format($ciudad['ingresos'], 0, ',', '.'); ?></td>
                                    <td><?php echo $ciudad['pedidos']; ?></td>
                                    <td class="monto">$<?php echo number_format($ciudad['ingresos'] / $ciudad['pedidos'], 0, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Datos para gráficos
        const flujoData = <?php echo $chart_data['flujo_diario']; ?>;
        const metodosData = <?php echo $chart_data['metodos_pago']; ?>;

        // Gráfico de flujo diario
        const flujoCtx = document.getElementById('flujoChart').getContext('2d');
        new Chart(flujoCtx, {
            type: 'line',
            data: {
                labels: Object.keys(flujoData),
                datasets: [{
                    label: 'Ingresos Diarios',
                    data: Object.values(flujoData),
                    borderColor: '#30d158',
                    backgroundColor: 'rgba(48, 209, 88, 0.1)',
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#999999'
                        },
                        grid: {
                            color: '#3e3e42'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#999999'
                        },
                        grid: {
                            color: '#3e3e42'
                        }
                    }
                }
            }
        });

        // Gráfico de métodos de pago
        const metodosCtx = document.getElementById('metodosChart').getContext('2d');
        new Chart(metodosCtx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(metodosData),
                datasets: [{
                    data: Object.values(metodosData),
                    backgroundColor: [
                        '#007aff',
                        '#30d158',
                        '#ff9f0a',
                        '#ff453a',
                        '#bf5af2',
                        '#ff2d92'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#cccccc'
                        }
                    }
                }
            }
        });

        console.log('Reporte Financiero inicializado');
    </script>
</body>
</html>