<?php
/**
 * Reporte de Ventas - Sistema de Análisis Detallado
 * Sequoia Speed - Sistema Integrado
 */

// Requerir autenticación
require_once '../accesos/auth_helper.php';

// Proteger la página - requiere permisos de reportes
$current_user = auth_require('reportes', 'leer');

// Registrar acceso
auth_log('read', 'reportes', 'Acceso al reporte de ventas');

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

// Obtener datos de ventas
$ventas = [];
$resumen = [
    'total_ventas' => 0,
    'total_pedidos' => 0,
    'ticket_promedio' => 0,
    'ventas_por_dia' => [],
    'metodos_pago' => [],
    'estados_pedidos' => [],
    'ciudades_top' => []
];

try {
    global $conn;
    
    // Consulta principal de ventas
    $stmt = $conn->prepare("
        SELECT 
            id,
            nombre,
            telefono,
            correo,
            ciudad,
            monto,
            descuento,
            metodo_pago,
            estado,
            fecha,
            DATE(fecha) as fecha_dia
        FROM pedidos_detal 
        WHERE DATE(fecha) BETWEEN ? AND ?
        ORDER BY fecha DESC
    ");
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $ventas[] = $row;
        
        // Acumular para resumen
        if ($row['estado'] != 'Anulado') {
            $resumen['total_ventas'] += $row['monto'];
            $resumen['total_pedidos']++;
            
            // Ventas por día
            $dia = $row['fecha_dia'];
            if (!isset($resumen['ventas_por_dia'][$dia])) {
                $resumen['ventas_por_dia'][$dia] = ['ventas' => 0, 'pedidos' => 0];
            }
            $resumen['ventas_por_dia'][$dia]['ventas'] += $row['monto'];
            $resumen['ventas_por_dia'][$dia]['pedidos']++;
            
            // Métodos de pago
            $metodo = $row['metodo_pago'] ?? 'No especificado';
            if (!isset($resumen['metodos_pago'][$metodo])) {
                $resumen['metodos_pago'][$metodo] = 0;
            }
            $resumen['metodos_pago'][$metodo]++;
            
            // Estados de pedidos
            $estado = $row['estado'] ?? 'No especificado';
            if (!isset($resumen['estados_pedidos'][$estado])) {
                $resumen['estados_pedidos'][$estado] = 0;
            }
            $resumen['estados_pedidos'][$estado]++;
            
            // Ciudades top
            $ciudad = $row['ciudad'] ?? 'No especificada';
            if (!isset($resumen['ciudades_top'][$ciudad])) {
                $resumen['ciudades_top'][$ciudad] = ['ventas' => 0, 'pedidos' => 0];
            }
            $resumen['ciudades_top'][$ciudad]['ventas'] += $row['monto'];
            $resumen['ciudades_top'][$ciudad]['pedidos']++;
        }
    }
    
    // Calcular ticket promedio
    if ($resumen['total_pedidos'] > 0) {
        $resumen['ticket_promedio'] = $resumen['total_ventas'] / $resumen['total_pedidos'];
    }
    
    // Ordenar datos para gráficos
    arsort($resumen['metodos_pago']);
    arsort($resumen['estados_pedidos']);
    uasort($resumen['ciudades_top'], function($a, $b) {
        return $b['ventas'] - $a['ventas'];
    });
    
} catch (Exception $e) {
    error_log("Error obteniendo reporte de ventas: " . $e->getMessage());
}

// Formatear datos para gráficos
$chart_data = [
    'ventas_dias' => json_encode(array_values($resumen['ventas_por_dia'])),
    'metodos_pago' => json_encode($resumen['metodos_pago']),
    'estados_pedidos' => json_encode($resumen['estados_pedidos'])
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Ventas - Sequoia Speed</title>
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

        .summary-card.total-ventas::before {
            background: linear-gradient(90deg, var(--apple-teal), var(--apple-blue));
        }

        .summary-card.total-pedidos::before {
            background: linear-gradient(90deg, var(--apple-blue), var(--apple-purple));
        }

        .summary-card.ticket-promedio::before {
            background: linear-gradient(90deg, var(--apple-orange), var(--apple-red));
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

        .estado-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .estado-confirmado {
            background: var(--apple-teal);
            color: white;
        }

        .estado-pendiente {
            background: var(--apple-orange);
            color: white;
        }

        .estado-anulado {
            background: var(--apple-red);
            color: white;
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
            <h1>💰 Reporte de Ventas</h1>
            <div class="nav-buttons">
                <a href="../index.php" class="btn btn-secondary">🏠 Inicio</a>
                <a href="dashboard.php" class="btn btn-primary">📊 Dashboard</a>
                <a href="productos.php" class="btn btn-primary">📦 Productos</a>
                <a href="../exportar_excel.php" class="btn btn-primary">📋 Exportar</a>
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
            <div class="summary-card total-ventas">
                <div class="summary-icon">💰</div>
                <div class="summary-number">$<?php echo number_format($resumen['total_ventas'], 0, ',', '.'); ?></div>
                <div class="summary-label">Total Ventas</div>
            </div>
            <div class="summary-card total-pedidos">
                <div class="summary-icon">📦</div>
                <div class="summary-number"><?php echo number_format($resumen['total_pedidos']); ?></div>
                <div class="summary-label">Total Pedidos</div>
            </div>
            <div class="summary-card ticket-promedio">
                <div class="summary-icon">🎫</div>
                <div class="summary-number">$<?php echo number_format($resumen['ticket_promedio'], 0, ',', '.'); ?></div>
                <div class="summary-label">Ticket Promedio</div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="charts-section">
            <div class="chart-card">
                <h3 class="chart-title">📊 Métodos de Pago</h3>
                <div class="chart-container">
                    <canvas id="metodosChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h3 class="chart-title">📈 Estados de Pedidos</h3>
                <div class="chart-container">
                    <canvas id="estadosChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Tabla de ventas -->
        <div class="table-section">
            <div class="table-header">
                <h3 class="table-title">📋 Detalle de Ventas</h3>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Ciudad</th>
                            <th>Monto</th>
                            <th>Método Pago</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ventas)): ?>
                            <tr>
                                <td colspan="7" class="empty-state">
                                    📊 No hay ventas en el período seleccionado
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ventas as $venta): ?>
                                <tr>
                                    <td><strong>#<?php echo $venta['id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($venta['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($venta['ciudad']); ?></td>
                                    <td class="monto">$<?php echo number_format($venta['monto'], 0, ',', '.'); ?></td>
                                    <td><?php echo htmlspecialchars($venta['metodo_pago']); ?></td>
                                    <td>
                                        <?php
                                        $estado = $venta['estado'];
                                        $clase = 'estado-pendiente';
                                        if (strpos($estado, 'Confirmado') !== false) $clase = 'estado-confirmado';
                                        if (strpos($estado, 'Anulado') !== false) $clase = 'estado-anulado';
                                        ?>
                                        <span class="estado-badge <?php echo $clase; ?>">
                                            <?php echo htmlspecialchars($estado); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($venta['fecha'])); ?></td>
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
        const metodosData = <?php echo $chart_data['metodos_pago']; ?>;
        const estadosData = <?php echo $chart_data['estados_pedidos']; ?>;

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

        // Gráfico de estados de pedidos
        const estadosCtx = document.getElementById('estadosChart').getContext('2d');
        new Chart(estadosCtx, {
            type: 'bar',
            data: {
                labels: Object.keys(estadosData),
                datasets: [{
                    label: 'Pedidos',
                    data: Object.values(estadosData),
                    backgroundColor: '#007aff'
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

        console.log('Reporte de Ventas inicializado');
        console.log('Período:', '<?php echo $periodo; ?>');
        console.log('Total ventas:', <?php echo $resumen['total_ventas']; ?>);
        console.log('Total pedidos:', <?php echo $resumen['total_pedidos']; ?>);
    </script>
</body>
</html>