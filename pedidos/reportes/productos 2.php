<?php
/**
 * Reporte de Productos - Sistema de Análisis de Inventario
 * Sequoia Speed - Sistema Integrado
 */

// Requerir autenticación
require_once '../accesos/auth_helper.php';

// Proteger la página - requiere permisos de reportes
$current_user = auth_require('reportes', 'leer');

// Registrar acceso
auth_log('read', 'reportes', 'Acceso al reporte de productos');

// Obtener datos de productos (simulado basado en pedidos)
$productos = [];
$resumen = [
    'total_productos' => 0,
    'productos_populares' => [],
    'categorias_top' => [],
    'productos_por_mes' => [],
    'ventas_por_producto' => []
];

try {
    global $conn;
    
    // Análisis de productos más vendidos basado en pedidos
    $stmt = $conn->prepare("
        SELECT 
            SUBSTRING_INDEX(SUBSTRING_INDEX(pedido, '\n', 1), ':', 1) as producto_nombre,
            COUNT(*) as cantidad_vendida,
            SUM(monto) as total_ventas,
            AVG(monto) as precio_promedio
        FROM pedidos_detal 
        WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        AND estado != 'Anulado'
        AND pedido IS NOT NULL
        AND pedido != ''
        GROUP BY producto_nombre
        HAVING producto_nombre IS NOT NULL
        AND producto_nombre != ''
        ORDER BY cantidad_vendida DESC
        LIMIT 20
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $resumen['productos_populares'][] = $row;
    }
    
    // Análisis por categorías (simulado)
    $categorias = [
        'Electrónicos' => ['cantidad' => 0, 'ventas' => 0],
        'Ropa' => ['cantidad' => 0, 'ventas' => 0],
        'Hogar' => ['cantidad' => 0, 'ventas' => 0],
        'Deportes' => ['cantidad' => 0, 'ventas' => 0],
        'Otros' => ['cantidad' => 0, 'ventas' => 0]
    ];
    
    // Simular categorización de productos
    foreach ($resumen['productos_populares'] as $producto) {
        $nombre = strtolower($producto['producto_nombre']);
        $categoria = 'Otros';
        
        if (strpos($nombre, 'celular') !== false || strpos($nombre, 'laptop') !== false || strpos($nombre, 'tv') !== false) {
            $categoria = 'Electrónicos';
        } elseif (strpos($nombre, 'camisa') !== false || strpos($nombre, 'pantalon') !== false || strpos($nombre, 'zapatos') !== false) {
            $categoria = 'Ropa';
        } elseif (strpos($nombre, 'mesa') !== false || strpos($nombre, 'silla') !== false || strpos($nombre, 'cama') !== false) {
            $categoria = 'Hogar';
        } elseif (strpos($nombre, 'balon') !== false || strpos($nombre, 'raqueta') !== false || strpos($nombre, 'bicicleta') !== false) {
            $categoria = 'Deportes';
        }
        
        $categorias[$categoria]['cantidad'] += $producto['cantidad_vendida'];
        $categorias[$categoria]['ventas'] += $producto['total_ventas'];
    }
    
    $resumen['categorias_top'] = $categorias;
    $resumen['total_productos'] = count($resumen['productos_populares']);
    
    // Análisis de ventas por mes (últimos 6 meses)
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(fecha, '%Y-%m') as mes,
            COUNT(DISTINCT SUBSTRING_INDEX(SUBSTRING_INDEX(pedido, '\n', 1), ':', 1)) as productos_unicos,
            COUNT(*) as total_ventas,
            SUM(monto) as total_ingresos
        FROM pedidos_detal 
        WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        AND estado != 'Anulado'
        AND pedido IS NOT NULL
        AND pedido != ''
        GROUP BY DATE_FORMAT(fecha, '%Y-%m')
        ORDER BY mes DESC
        LIMIT 6
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $resumen['productos_por_mes'][] = $row;
    }
    
} catch (Exception $e) {
    error_log("Error obteniendo reporte de productos: " . $e->getMessage());
}

// Formatear datos para gráficos
$chart_data = [
    'productos_populares' => json_encode($resumen['productos_populares']),
    'categorias_top' => json_encode($resumen['categorias_top']),
    'productos_por_mes' => json_encode(array_reverse($resumen['productos_por_mes']))
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Productos - Sequoia Speed</title>
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
            background: linear-gradient(90deg, var(--apple-blue), var(--apple-teal));
            opacity: 0.8;
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
            grid-template-columns: 1fr 1fr;
            gap: var(--space-xl);
            margin-bottom: var(--space-xl);
        }

        .chart-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            padding: var(--space-xl);
        }

        .chart-card.full-width {
            grid-column: 1 / -1;
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

        .productos-ranking {
            max-height: 400px;
            overflow-y: auto;
        }

        .producto-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--space-md);
            border-bottom: 1px solid var(--vscode-border);
            transition: all var(--transition-base);
        }

        .producto-item:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .producto-item:last-child {
            border-bottom: none;
        }

        .producto-info {
            flex: 1;
        }

        .producto-nombre {
            font-weight: 500;
            color: var(--vscode-text-light);
            margin-bottom: var(--space-xs);
        }

        .producto-stats {
            display: flex;
            gap: var(--space-md);
            font-size: 0.8rem;
            color: var(--vscode-text-muted);
        }

        .producto-ranking-badge {
            background: var(--apple-blue);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-right: var(--space-md);
        }

        .categoria-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--space-md);
            border-bottom: 1px solid var(--vscode-border);
            transition: all var(--transition-base);
        }

        .categoria-item:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .categoria-nombre {
            font-weight: 500;
            color: var(--vscode-text-light);
        }

        .categoria-stats {
            display: flex;
            gap: var(--space-md);
            align-items: center;
        }

        .categoria-badge {
            background: var(--apple-teal);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
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
            <h1>📦 Reporte de Productos</h1>
            <div class="nav-buttons">
                <a href="../index.php" class="btn btn-secondary">🏠 Inicio</a>
                <a href="dashboard.php" class="btn btn-primary">📊 Dashboard</a>
                <a href="ventas.php" class="btn btn-primary">💰 Ventas</a>
                <a href="../inventario/productos.php" class="btn btn-primary">📦 Inventario</a>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Resumen -->
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-icon">📦</div>
                <div class="summary-number"><?php echo number_format($resumen['total_productos']); ?></div>
                <div class="summary-label">Productos Analizados</div>
            </div>
            <div class="summary-card">
                <div class="summary-icon">🏆</div>
                <div class="summary-number"><?php echo !empty($resumen['productos_populares']) ? $resumen['productos_populares'][0]['cantidad_vendida'] : 0; ?></div>
                <div class="summary-label">Más Vendido</div>
            </div>
            <div class="summary-card">
                <div class="summary-icon">🏷️</div>
                <div class="summary-number"><?php echo count($resumen['categorias_top']); ?></div>
                <div class="summary-label">Categorías</div>
            </div>
            <div class="summary-card">
                <div class="summary-icon">📈</div>
                <div class="summary-number">$<?php echo number_format(array_sum(array_column($resumen['productos_populares'], 'total_ventas')), 0, ',', '.'); ?></div>
                <div class="summary-label">Ventas Totales</div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="charts-section">
            <div class="chart-card">
                <h3 class="chart-title">🏆 Top 10 Productos Más Vendidos</h3>
                <div class="chart-container">
                    <canvas id="productosChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h3 class="chart-title">🏷️ Ventas por Categoría</h3>
                <div class="chart-container">
                    <canvas id="categoriasChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Tabla de productos más vendidos -->
        <div class="chart-card full-width">
            <h3 class="chart-title">📋 Ranking Detallado de Productos</h3>
            <div class="productos-ranking">
                <?php if (empty($resumen['productos_populares'])): ?>
                    <div class="empty-state">
                        📦 No hay datos de productos disponibles<br>
                        <small>Los productos aparecerán aquí cuando se registren ventas</small>
                    </div>
                <?php else: ?>
                    <?php foreach ($resumen['productos_populares'] as $index => $producto): ?>
                        <div class="producto-item">
                            <div class="producto-info">
                                <div class="producto-ranking-badge">#<?php echo $index + 1; ?></div>
                                <div class="producto-nombre"><?php echo htmlspecialchars($producto['producto_nombre']); ?></div>
                                <div class="producto-stats">
                                    <span>📊 <?php echo $producto['cantidad_vendida']; ?> vendidos</span>
                                    <span>💰 $<?php echo number_format($producto['total_ventas'], 0, ',', '.'); ?></span>
                                    <span>🎯 Promedio: $<?php echo number_format($producto['precio_promedio'], 0, ',', '.'); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Análisis por categorías -->
        <div class="chart-card full-width">
            <h3 class="chart-title">🏷️ Análisis por Categorías</h3>
            <div>
                <?php foreach ($resumen['categorias_top'] as $categoria => $datos): ?>
                    <div class="categoria-item">
                        <div class="categoria-nombre"><?php echo htmlspecialchars($categoria); ?></div>
                        <div class="categoria-stats">
                            <span><?php echo $datos['cantidad']; ?> vendidos</span>
                            <span class="categoria-badge">$<?php echo number_format($datos['ventas'], 0, ',', '.'); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        // Datos para gráficos
        const productosData = <?php echo $chart_data['productos_populares']; ?>;
        const categoriasData = <?php echo $chart_data['categorias_top']; ?>;

        // Gráfico de productos más vendidos
        const productosCtx = document.getElementById('productosChart').getContext('2d');
        new Chart(productosCtx, {
            type: 'bar',
            data: {
                labels: productosData.slice(0, 10).map(item => item.producto_nombre.length > 15 ? item.producto_nombre.substring(0, 15) + '...' : item.producto_nombre),
                datasets: [{
                    label: 'Cantidad Vendida',
                    data: productosData.slice(0, 10).map(item => item.cantidad_vendida),
                    backgroundColor: '#007aff',
                    borderColor: '#0056d3',
                    borderWidth: 1
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
                            color: '#999999',
                            maxRotation: 45
                        },
                        grid: {
                            color: '#3e3e42'
                        }
                    }
                }
            }
        });

        // Gráfico de categorías
        const categoriasCtx = document.getElementById('categoriasChart').getContext('2d');
        new Chart(categoriasCtx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(categoriasData),
                datasets: [{
                    data: Object.values(categoriasData).map(item => item.ventas),
                    backgroundColor: [
                        '#007aff',
                        '#30d158',
                        '#ff9f0a',
                        '#ff453a',
                        '#bf5af2'
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

        console.log('Reporte de Productos inicializado');
        console.log('Total productos:', <?php echo $resumen['total_productos']; ?>);
        console.log('Productos populares:', productosData.length);
    </script>
</body>
</html>