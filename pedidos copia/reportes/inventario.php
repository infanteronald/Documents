<?php
/**
 * Reporte de Inventario - Análisis de Stock y Productos
 * Sequoia Speed - Sistema Integrado
 */

// Requerir autenticación
require_once '../accesos/auth_helper.php';

// Proteger la página - requiere permisos de inventario
$current_user = auth_require('inventario', 'leer');

// Registrar acceso
auth_log('read', 'reportes', 'Acceso al reporte de inventario');

// Obtener datos de inventario
$productos = [];
$resumen = [
    'total_productos' => 0,
    'productos_activos' => 0,
    'productos_inactivos' => 0,
    'stock_total' => 0,
    'stock_bajo' => 0,
    'sin_stock' => 0,
    'categorias' => [],
    'valor_inventario' => 0
];

$alertas = [];

try {
    global $conn;
    
    // Verificar si existe la tabla productos
    $stmt = $conn->prepare("SHOW TABLES LIKE 'productos'");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Consulta principal de productos con categorías
        $stmt = $conn->prepare("
            SELECT 
                p.id,
                p.nombre,
                p.descripcion,
                p.precio,
                p.stock_actual,
                p.stock_minimo,
                COALESCE(c.nombre, 'Sin categoría') as categoria,
                p.activo,
                p.fecha_creacion
            FROM productos p
            LEFT JOIN categorias_productos c ON p.categoria_id = c.id
            ORDER BY p.stock_actual ASC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $productos[] = $row;
            
            // Acumular para resumen
            $resumen['total_productos']++;
            if ($row['activo']) {
                $resumen['productos_activos']++;
            } else {
                $resumen['productos_inactivos']++;
            }
            
            $resumen['stock_total'] += $row['stock_actual'];
            $resumen['valor_inventario'] += $row['precio'] * $row['stock_actual'];
            
            // Verificar stock bajo
            if ($row['stock_actual'] <= $row['stock_minimo'] && $row['activo']) {
                if ($row['stock_actual'] == 0) {
                    $resumen['sin_stock']++;
                    $alertas[] = [
                        'tipo' => 'sin_stock',
                        'producto' => $row['nombre'],
                        'stock' => $row['stock_actual'],
                        'minimo' => $row['stock_minimo']
                    ];
                } else {
                    $resumen['stock_bajo']++;
                    $alertas[] = [
                        'tipo' => 'stock_bajo',
                        'producto' => $row['nombre'],
                        'stock' => $row['stock_actual'],
                        'minimo' => $row['stock_minimo']
                    ];
                }
            }
            
            // Categorías
            $categoria = $row['categoria'] ?? 'Sin categoría';
            if (!isset($resumen['categorias'][$categoria])) {
                $resumen['categorias'][$categoria] = 0;
            }
            $resumen['categorias'][$categoria]++;
        }
    }
    
} catch (Exception $e) {
    error_log("Error obteniendo reporte de inventario: " . $e->getMessage());
    $productos = [];
}

// Formatear datos para gráficos
$chart_data = [
    'categorias' => json_encode($resumen['categorias']),
    'stock_labels' => json_encode(['Activos', 'Inactivos', 'Stock Bajo', 'Sin Stock']),
    'stock_data' => json_encode([
        $resumen['productos_activos'], 
        $resumen['productos_inactivos'], 
        $resumen['stock_bajo'], 
        $resumen['sin_stock']
    ])
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Inventario - Sequoia Speed</title>
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
            opacity: 0.8;
        }

        .summary-card.total-productos::before {
            background: linear-gradient(90deg, var(--apple-teal), var(--apple-blue));
        }

        .summary-card.productos-activos::before {
            background: linear-gradient(90deg, var(--apple-blue), var(--apple-purple));
        }

        .summary-card.stock-bajo::before {
            background: linear-gradient(90deg, var(--apple-orange), var(--apple-red));
        }

        .summary-card.valor-inventario::before {
            background: linear-gradient(90deg, var(--apple-purple), var(--apple-pink));
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

        .alerts-section {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            margin-bottom: var(--space-xl);
        }

        .alerts-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--vscode-text-light);
            margin-bottom: var(--space-lg);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .alert-item {
            background: var(--vscode-sidebar);
            border: 1px solid var(--vscode-border);
            border-radius: var(--radius-sm);
            padding: var(--space-md);
            margin-bottom: var(--space-sm);
            display: flex;
            align-items: center;
            gap: var(--space-md);
        }

        .alert-sin-stock {
            border-left: 4px solid var(--apple-red);
        }

        .alert-stock-bajo {
            border-left: 4px solid var(--apple-orange);
        }

        .alert-icon {
            font-size: 1.5rem;
        }

        .alert-info {
            flex: 1;
        }

        .alert-producto {
            font-weight: 600;
            color: var(--vscode-text-light);
        }

        .alert-detalle {
            font-size: 0.9rem;
            color: var(--vscode-text-muted);
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
            max-height: 600px;
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
            position: sticky;
            top: 0;
        }

        .table tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .stock-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .stock-normal {
            background: var(--apple-teal);
            color: white;
        }

        .stock-bajo {
            background: var(--apple-orange);
            color: white;
        }

        .stock-agotado {
            background: var(--apple-red);
            color: white;
        }

        .precio {
            color: var(--apple-teal);
            font-weight: 600;
        }

        .estado-activo {
            color: var(--apple-teal);
        }

        .estado-inactivo {
            color: var(--apple-red);
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
            <h1>📦 Reporte de Inventario</h1>
            <div class="nav-buttons">
                <a href="index.php" class="btn btn-secondary">📊 Reportes</a>
                <a href="../index.php" class="btn btn-secondary">🏠 Inicio</a>
                <a href="../inventario/productos.php" class="btn btn-primary">📋 Inventario</a>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Resumen -->
        <div class="summary-grid">
            <div class="summary-card total-productos">
                <div class="summary-icon">📦</div>
                <div class="summary-number"><?php echo number_format($resumen['total_productos']); ?></div>
                <div class="summary-label">Total Productos</div>
            </div>
            <div class="summary-card productos-activos">
                <div class="summary-icon">✅</div>
                <div class="summary-number"><?php echo number_format($resumen['productos_activos']); ?></div>
                <div class="summary-label">Productos Activos</div>
            </div>
            <div class="summary-card stock-bajo">
                <div class="summary-icon">⚠️</div>
                <div class="summary-number"><?php echo number_format($resumen['stock_bajo'] + $resumen['sin_stock']); ?></div>
                <div class="summary-label">Alertas de Stock</div>
            </div>
            <div class="summary-card valor-inventario">
                <div class="summary-icon">💰</div>
                <div class="summary-number">$<?php echo number_format($resumen['valor_inventario'], 0, ',', '.'); ?></div>
                <div class="summary-label">Valor Inventario</div>
            </div>
        </div>

        <!-- Alertas -->
        <?php if (!empty($alertas)): ?>
        <div class="alerts-section">
            <h3 class="alerts-title">⚠️ Alertas de Stock</h3>
            <?php foreach ($alertas as $alerta): ?>
                <div class="alert-item alert-<?php echo $alerta['tipo']; ?>">
                    <div class="alert-icon">
                        <?php echo $alerta['tipo'] === 'sin_stock' ? '🔴' : '🟡'; ?>
                    </div>
                    <div class="alert-info">
                        <div class="alert-producto"><?php echo htmlspecialchars($alerta['producto']); ?></div>
                        <div class="alert-detalle">
                            Stock actual: <?php echo $alerta['stock']; ?> | 
                            Mínimo: <?php echo $alerta['minimo']; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Gráficos -->
        <div class="charts-section">
            <div class="chart-card">
                <h3 class="chart-title">📊 Productos por Categoría</h3>
                <div class="chart-container">
                    <canvas id="categoriasChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h3 class="chart-title">📈 Estado de Productos</h3>
                <div class="chart-container">
                    <canvas id="estadoChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Tabla de productos -->
        <div class="table-section">
            <div class="table-header">
                <h3 class="table-title">📋 Detalle de Productos</h3>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th>Precio</th>
                            <th>Stock</th>
                            <th>Mínimo</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($productos)): ?>
                            <tr>
                                <td colspan="6" class="empty-state">
                                    📦 No hay productos en el inventario
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($productos as $producto): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong>
                                        <br>
                                        <small><?php echo htmlspecialchars($producto['descripcion']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($producto['categoria'] ?? 'Sin categoría'); ?></td>
                                    <td class="precio">$<?php echo number_format($producto['precio'], 0, ',', '.'); ?></td>
                                    <td>
                                        <?php
                                        $stock = $producto['stock_actual'];
                                        $minimo = $producto['stock_minimo'];
                                        $clase = 'stock-normal';
                                        if ($stock == 0) {
                                            $clase = 'stock-agotado';
                                        } elseif ($stock <= $minimo) {
                                            $clase = 'stock-bajo';
                                        }
                                        ?>
                                        <span class="stock-badge <?php echo $clase; ?>">
                                            <?php echo $stock; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $minimo; ?></td>
                                    <td>
                                        <span class="<?php echo $producto['activo'] ? 'estado-activo' : 'estado-inactivo'; ?>">
                                            <?php echo $producto['activo'] ? '✅ Activo' : '❌ Inactivo'; ?>
                                        </span>
                                    </td>
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
        const categoriasData = <?php echo $chart_data['categorias']; ?>;
        const stockLabels = <?php echo $chart_data['stock_labels']; ?>;
        const stockData = <?php echo $chart_data['stock_data']; ?>;

        // Gráfico de categorías
        const categoriasCtx = document.getElementById('categoriasChart').getContext('2d');
        new Chart(categoriasCtx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(categoriasData),
                datasets: [{
                    data: Object.values(categoriasData),
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

        // Gráfico de estado
        const estadoCtx = document.getElementById('estadoChart').getContext('2d');
        new Chart(estadoCtx, {
            type: 'bar',
            data: {
                labels: stockLabels,
                datasets: [{
                    label: 'Productos',
                    data: stockData,
                    backgroundColor: ['#30d158', '#ff453a', '#ff9f0a', '#bf5af2']
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

        console.log('Reporte de Inventario inicializado');
    </script>
</body>
</html>