<?php
/**
 * Dashboard de Reportes - Sistema de Análisis
 * Sequoia Speed - Sistema Integrado
 */

// Requerir autenticación
require_once '../accesos/auth_helper.php';

// Proteger la página - requiere permisos de reportes
$current_user = auth_require('reportes', 'leer');

// Registrar acceso
auth_log('read', 'reportes', 'Acceso al dashboard de reportes');

// Función para verificar permisos
function canAccess($module) {
    return auth_can($module, 'leer');
}

// Obtener datos para reportes
$stats = [
    'ventas_hoy' => 0,
    'ventas_mes' => 0,
    'ventas_año' => 0,
    'pedidos_hoy' => 0,
    'pedidos_mes' => 0,
    'ticket_promedio' => 0,
    'productos_mas_vendidos' => [],
    'ventas_por_dia' => []
];

try {
    global $conn;
    
    // Ventas de hoy
    $hoy = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM pedidos_detal WHERE DATE(fecha) = ? AND estado != 'Anulado'");
    $stmt->bind_param("s", $hoy);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['ventas_hoy'] = $result->fetch_row()[0];
    
    // Ventas del mes
    $stmt = $conn->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM pedidos_detal WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) AND estado != 'Anulado'");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['ventas_mes'] = $result->fetch_row()[0];
    
    // Ventas del año
    $stmt = $conn->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM pedidos_detal WHERE YEAR(fecha) = YEAR(CURDATE()) AND estado != 'Anulado'");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['ventas_año'] = $result->fetch_row()[0];
    
    // Pedidos de hoy
    $stmt = $conn->prepare("SELECT COUNT(*) FROM pedidos_detal WHERE DATE(fecha) = ?");
    $stmt->bind_param("s", $hoy);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['pedidos_hoy'] = $result->fetch_row()[0];
    
    // Pedidos del mes
    $stmt = $conn->prepare("SELECT COUNT(*) FROM pedidos_detal WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['pedidos_mes'] = $result->fetch_row()[0];
    
    // Ticket promedio
    if ($stats['pedidos_mes'] > 0) {
        $stats['ticket_promedio'] = $stats['ventas_mes'] / $stats['pedidos_mes'];
    }
    
    // Ventas por día (últimos 30 días)
    $stmt = $conn->prepare("
        SELECT DATE(fecha) as dia, COALESCE(SUM(monto), 0) as total 
        FROM pedidos_detal 
        WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
        AND estado != 'Anulado'
        GROUP BY DATE(fecha) 
        ORDER BY dia DESC 
        LIMIT 30
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $stats['ventas_por_dia'][] = $row;
    }
    
    // Productos más vendidos (simulado - ajustar según estructura real)
    $stmt = $conn->prepare("
        SELECT 
            SUBSTRING_INDEX(SUBSTRING_INDEX(pedido, '\n', 1), ':', 1) as producto,
            COUNT(*) as cantidad
        FROM pedidos_detal 
        WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
        AND estado != 'Anulado'
        GROUP BY producto
        ORDER BY cantidad DESC
        LIMIT 10
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $stats['productos_mas_vendidos'][] = $row;
    }
    
} catch (Exception $e) {
    error_log("Error obteniendo estadísticas de reportes: " . $e->getMessage());
}

// Obtener datos para gráficos
$chart_data = [
    'ventas_dias' => json_encode(array_reverse($stats['ventas_por_dia'])),
    'productos_vendidos' => json_encode($stats['productos_mas_vendidos'])
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Reportes - Sequoia Speed</title>
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--space-lg);
            margin-bottom: var(--space-xl);
        }

        .stat-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            padding: var(--space-xl);
            text-align: center;
            transition: all var(--transition-base);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            opacity: 0.8;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .stat-card.ventas-hoy::before {
            background: linear-gradient(90deg, var(--apple-teal), var(--apple-blue));
        }

        .stat-card.ventas-mes::before {
            background: linear-gradient(90deg, var(--apple-blue), var(--apple-purple));
        }

        .stat-card.ventas-año::before {
            background: linear-gradient(90deg, var(--apple-purple), var(--apple-pink));
        }

        .stat-card.pedidos::before {
            background: linear-gradient(90deg, var(--apple-orange), var(--apple-red));
        }

        .stat-card.ticket::before {
            background: linear-gradient(90deg, var(--apple-teal), var(--apple-orange));
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: var(--space-md);
        }

        .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: var(--space-sm);
            color: var(--vscode-text-light);
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--vscode-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
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

        .productos-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .producto-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--space-md);
            border-bottom: 1px solid var(--vscode-border);
        }

        .producto-item:last-child {
            border-bottom: none;
        }

        .producto-nombre {
            font-weight: 500;
            color: var(--vscode-text-light);
        }

        .producto-cantidad {
            background: var(--apple-blue);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: var(--space-lg);
        }

        .action-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            padding: var(--space-xl);
            text-align: center;
            transition: all var(--transition-base);
        }

        .action-card:hover {
            transform: translateY(-2px);
            border-color: var(--apple-blue);
        }

        .action-icon {
            font-size: 2rem;
            margin-bottom: var(--space-md);
        }

        .action-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--vscode-text-light);
            margin-bottom: var(--space-sm);
        }

        .action-description {
            color: var(--vscode-text-muted);
            margin-bottom: var(--space-lg);
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: var(--space-md);
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }
    </style>
</head>

<body>
    <header class="header">
        <div class="header-content">
            <h1>📊 Dashboard de Reportes</h1>
            <div class="nav-buttons">
                <a href="../index.php" class="btn btn-secondary">🏠 Inicio</a>
                <a href="ventas.php" class="btn btn-primary">💰 Ventas</a>
                <a href="productos.php" class="btn btn-primary">📦 Productos</a>
                <a href="../exportar_excel.php" class="btn btn-primary">📊 Exportar</a>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Estadísticas principales -->
        <div class="stats-grid">
            <div class="stat-card ventas-hoy">
                <div class="stat-icon">💰</div>
                <div class="stat-number">$<?php echo number_format($stats['ventas_hoy'], 0, ',', '.'); ?></div>
                <div class="stat-label">Ventas Hoy</div>
            </div>
            <div class="stat-card ventas-mes">
                <div class="stat-icon">📈</div>
                <div class="stat-number">$<?php echo number_format($stats['ventas_mes'], 0, ',', '.'); ?></div>
                <div class="stat-label">Ventas Este Mes</div>
            </div>
            <div class="stat-card ventas-año">
                <div class="stat-icon">🎯</div>
                <div class="stat-number">$<?php echo number_format($stats['ventas_año'], 0, ',', '.'); ?></div>
                <div class="stat-label">Ventas Este Año</div>
            </div>
            <div class="stat-card pedidos">
                <div class="stat-icon">📦</div>
                <div class="stat-number"><?php echo number_format($stats['pedidos_mes']); ?></div>
                <div class="stat-label">Pedidos Este Mes</div>
            </div>
            <div class="stat-card ticket">
                <div class="stat-icon">🎫</div>
                <div class="stat-number">$<?php echo number_format($stats['ticket_promedio'], 0, ',', '.'); ?></div>
                <div class="stat-label">Ticket Promedio</div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="charts-grid">
            <div class="chart-card">
                <h3 class="chart-title">📈 Ventas Últimos 30 Días</h3>
                <div class="chart-container">
                    <canvas id="ventasChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h3 class="chart-title">🏆 Productos Más Vendidos</h3>
                <div class="productos-list">
                    <?php if (empty($stats['productos_mas_vendidos'])): ?>
                        <div class="producto-item">
                            <span class="producto-nombre">No hay datos disponibles</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($stats['productos_mas_vendidos'] as $producto): ?>
                            <div class="producto-item">
                                <span class="producto-nombre"><?php echo htmlspecialchars($producto['producto']); ?></span>
                                <span class="producto-cantidad"><?php echo $producto['cantidad']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Acciones rápidas -->
        <div class="actions-grid">
            <div class="action-card">
                <div class="action-icon">📊</div>
                <h3 class="action-title">Reporte de Ventas</h3>
                <p class="action-description">Análisis detallado de ventas por período</p>
                <a href="ventas.php" class="btn btn-primary">Ver Reporte</a>
            </div>
            <div class="action-card">
                <div class="action-icon">📦</div>
                <h3 class="action-title">Reporte de Productos</h3>
                <p class="action-description">Análisis de productos más vendidos</p>
                <a href="productos.php" class="btn btn-primary">Ver Reporte</a>
            </div>
            <div class="action-card">
                <div class="action-icon">📋</div>
                <h3 class="action-title">Exportar Datos</h3>
                <p class="action-description">Exportar información en formato Excel</p>
                <a href="../exportar_excel.php" class="btn btn-primary">Exportar</a>
            </div>
        </div>
    </div>

    <script>
        // Datos para gráficos
        const ventasData = <?php echo $chart_data['ventas_dias']; ?>;
        const productosData = <?php echo $chart_data['productos_vendidos']; ?>;

        // Gráfico de ventas
        const ctx = document.getElementById('ventasChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ventasData.map(item => {
                    const date = new Date(item.dia);
                    return date.toLocaleDateString('es-ES', { 
                        day: '2-digit', 
                        month: '2-digit' 
                    });
                }),
                datasets: [{
                    label: 'Ventas ($)',
                    data: ventasData.map(item => item.total),
                    borderColor: '#007aff',
                    backgroundColor: 'rgba(0, 122, 255, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
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
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            },
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

        // Actualizar datos cada 5 minutos
        setInterval(function() {
            fetch('../api/stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Actualizar estadísticas
                        document.querySelector('.ventas-hoy .stat-number').textContent = 
                            '$' + data.data.ventas_hoy.toLocaleString();
                        document.querySelector('.ventas-mes .stat-number').textContent = 
                            '$' + data.data.ventas_mes.toLocaleString();
                        document.querySelector('.pedidos .stat-number').textContent = 
                            data.data.pedidos_mes.toLocaleString();
                    }
                })
                .catch(error => console.error('Error actualizando datos:', error));
        }, 300000); // 5 minutos

        console.log('Dashboard de Reportes inicializado');
        console.log('Ventas hoy: $<?php echo number_format($stats['ventas_hoy'], 0, ',', '.'); ?>');
        console.log('Ventas mes: $<?php echo number_format($stats['ventas_mes'], 0, ',', '.'); ?>');
    </script>
</body>
</html>