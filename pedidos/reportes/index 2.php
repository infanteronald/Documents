<?php
/**
 * Centro de Reportes - Sequoia Speed
 * Dashboard Principal del Módulo de Reportes
 */

// Requerir autenticación
require_once '../accesos/auth_helper.php';

// Proteger la página - requiere permisos de reportes
$current_user = auth_require('reportes', 'leer');

// Registrar acceso
auth_log('read', 'reportes', 'Acceso al centro de reportes');

// Obtener información del usuario
$user_roles = AuthHelper::getCurrentUserRoles();
$user_permissions = AuthHelper::getCurrentUserPermissions();

// Función para verificar si el usuario puede acceder a un módulo
function canAccess($module) {
    return auth_can($module, 'leer');
}

// Obtener estadísticas básicas para los reportes
$stats = [
    'ventas_mes' => 0,
    'pedidos_total' => 0,
    'productos_activos' => 0,
    'usuarios_activos' => 0,
    'reportes_generados' => 0,
    'ultima_actualizacion' => date('Y-m-d H:i:s')
];

if (canAccess('ventas')) {
    try {
        global $conn;
        
        // Ventas del mes actual
        $stmt = $conn->prepare("SELECT SUM(monto) FROM pedidos_detal WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) AND estado != 'Anulado'");
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['ventas_mes'] = $result->fetch_row()[0] ?? 0;
        
        // Total de pedidos
        $stmt = $conn->prepare("SELECT COUNT(*) FROM pedidos_detal WHERE estado != 'Anulado'");
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['pedidos_total'] = $result->fetch_row()[0];
        
    } catch (Exception $e) {
        error_log("Error obteniendo estadísticas de ventas: " . $e->getMessage());
    }
}

if (canAccess('inventario')) {
    try {
        // Productos activos
        $stmt = $conn->prepare("SELECT COUNT(*) FROM productos WHERE activo = 1");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            $stats['productos_activos'] = $result->fetch_row()[0];
        }
    } catch (Exception $e) {
        // Ignorar si no existe tabla productos
    }
}

if (canAccess('usuarios')) {
    try {
        // Usuarios activos
        $stmt = $conn->prepare("SELECT COUNT(*) FROM usuarios WHERE activo = 1");
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['usuarios_activos'] = $result->fetch_row()[0];
    } catch (Exception $e) {
        error_log("Error obteniendo estadísticas de usuarios: " . $e->getMessage());
    }
}

// Determinar rol principal para personalización
$primary_role = $user_roles[0]['nombre'] ?? 'vendedor';
$is_admin = in_array($primary_role, ['super_admin', 'admin']);
$is_manager = in_array($primary_role, ['gerente', 'supervisor']);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sequoia Speed - Centro de Reportes</title>
    <link rel="icon" href="../logo.png" type="image/png">

    <!-- Sistema de Notificaciones -->
    <link rel="stylesheet" href="../notifications/notifications.css">
    <link rel="stylesheet" href="../notifications/push_notifications.css">

    <style>
        /* VSCode Dark Theme - Paleta de colores */
        :root {
            --vscode-bg: #1e1e1e;
            --vscode-sidebar: #252526;
            --vscode-border: #3e3e42;
            --vscode-text: #cccccc;
            --vscode-text-muted: #999999;
            --vscode-text-light: #ffffff;
            --apple-blue: #007aff;
            --apple-blue-hover: #0056d3;
            --apple-teal: #30d158;
            --apple-orange: #ff9f0a;
            --apple-red: #ff453a;
            --apple-purple: #bf5af2;
            --apple-gray: #8e8e93;
            --glass-bg: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
            --space-xs: 4px;
            --space-sm: 8px;
            --space-md: 16px;
            --space-lg: 24px;
            --space-xl: 32px;
            --space-2xl: 48px;
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
            overflow-x: hidden;
        }

        /* Header mejorado */
        .header {
            background: linear-gradient(135deg, var(--vscode-sidebar) 0%, #2d2d30 100%);
            border-bottom: 1px solid var(--vscode-border);
            padding: var(--space-md) 0;
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(20px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 var(--space-lg);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: var(--space-md);
        }

        .logo {
            height: 50px;
            width: auto;
            border-radius: var(--radius-md);
        }

        .header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--vscode-text-light);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: var(--space-md);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            color: var(--vscode-text-muted);
            font-size: 0.9rem;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--apple-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1rem;
        }

        .role-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: var(--space-xs);
        }

        .role-super_admin { background: var(--apple-red); color: white; }
        .role-admin { background: var(--apple-orange); color: white; }
        .role-gerente { background: var(--apple-purple); color: white; }
        .role-supervisor { background: var(--apple-teal); color: white; }
        .role-vendedor { background: var(--apple-blue); color: white; }
        .role-consultor { background: var(--apple-gray); color: white; }

        .btn {
            padding: var(--space-sm) var(--space-md);
            border: none;
            border-radius: var(--radius-sm);
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
            color: white;
        }

        .btn-primary:hover {
            background: var(--apple-blue-hover);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: var(--vscode-text);
            border: 1px solid var(--vscode-border);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        /* Container principal */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: var(--space-lg);
        }

        /* Breadcrumb */
        .breadcrumb {
            margin-bottom: var(--space-lg);
            font-size: 0.9rem;
            color: var(--vscode-text-muted);
        }

        .breadcrumb a {
            color: var(--apple-blue);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Grid de secciones mejorado */
        .sections-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: var(--space-xl);
            margin-top: var(--space-md);
        }

        /* Cards de sección mejorados */
        .section-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            padding: var(--space-xl);
            backdrop-filter: blur(20px);
            transition: all var(--transition-base);
            position: relative;
            overflow: hidden;
        }

        .section-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--apple-blue), var(--apple-teal));
            opacity: 0;
            transition: opacity var(--transition-base);
        }

        .section-card:hover {
            transform: translateY(-5px);
            border-color: var(--apple-blue);
            box-shadow: 0 20px 40px rgba(0, 122, 255, 0.15);
        }

        .section-card:hover::before {
            opacity: 1;
        }

        .section-card.disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        .section-card.disabled::after {
            content: '🔒 Sin permisos';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: var(--space-sm) var(--space-md);
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
        }

        /* Secciones con colores específicos */
        .section-card.ventas::before {
            background: linear-gradient(90deg, var(--apple-teal), var(--apple-blue));
        }

        .section-card.financiero::before {
            background: linear-gradient(90deg, var(--apple-orange), var(--apple-red));
        }

        .section-card.inventario::before {
            background: linear-gradient(90deg, var(--apple-purple), var(--apple-blue));
        }

        .section-card.logistica::before {
            background: linear-gradient(90deg, var(--apple-blue), var(--apple-teal));
        }

        .section-card.usuarios::before {
            background: linear-gradient(90deg, var(--apple-red), var(--apple-orange));
        }

        .section-card.ejecutivo::before {
            background: linear-gradient(90deg, var(--apple-purple), var(--apple-red));
        }

        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: var(--space-lg);
        }

        .section-icon {
            font-size: 2.2rem;
            margin-right: var(--space-md);
        }

        .section-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--vscode-text-light);
        }

        .section-description {
            color: var(--vscode-text-muted);
            margin-bottom: var(--space-lg);
            font-size: 0.95rem;
            line-height: 1.5;
        }

        /* Lista de opciones mejorada */
        .options-list {
            list-style: none;
        }

        .option-item {
            margin-bottom: var(--space-sm);
        }

        .option-link {
            display: flex;
            align-items: center;
            padding: var(--space-md);
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: var(--radius-md);
            color: var(--vscode-text);
            text-decoration: none;
            transition: all var(--transition-base);
            position: relative;
            overflow: hidden;
        }

        .option-link::before {
            content: '';
            position: absolute;
            left: -100%;
            top: 0;
            bottom: 0;
            width: 3px;
            background: var(--apple-blue);
            transition: left var(--transition-base);
        }

        .option-link:hover {
            background: rgba(0, 122, 255, 0.1);
            border-color: var(--apple-blue);
            transform: translateX(5px);
            color: var(--vscode-text-light);
        }

        .option-link:hover::before {
            left: 0;
        }

        .option-link.priority {
            border-color: var(--apple-orange);
            background: rgba(255, 159, 10, 0.05);
        }

        .option-link.priority:hover {
            border-color: var(--apple-orange);
            background: rgba(255, 159, 10, 0.15);
        }

        .option-link.priority::before {
            background: var(--apple-orange);
        }

        .option-icon {
            font-size: 1.3rem;
            margin-right: var(--space-md);
            min-width: 28px;
        }

        .option-text {
            flex: 1;
        }

        .option-title {
            font-weight: 500;
            margin-bottom: 2px;
            font-size: 0.95rem;
        }

        .option-desc {
            font-size: 0.8rem;
            color: var(--vscode-text-muted);
            line-height: 1.3;
        }

        /* Badge para indicadores */
        .option-badge {
            background: var(--apple-red);
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: var(--space-xs);
            font-weight: 600;
        }

        .option-badge.warning {
            background: var(--apple-orange);
        }

        .option-badge.success {
            background: var(--apple-teal);
        }

        .option-badge.info {
            background: var(--apple-blue);
        }

        /* Responsive mejorado */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: var(--space-md);
            }
            
            .container {
                padding: var(--space-lg) var(--space-md);
            }

            .sections-grid {
                grid-template-columns: 1fr;
                gap: var(--space-lg);
                margin-top: var(--space-md);
            }

            .section-card {
                padding: var(--space-lg);
            }

            .header h1 {
                font-size: 1.4rem;
            }
        }

        /* Animaciones */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .section-card {
            animation: fadeInUp 0.6s ease-out;
        }

        .section-card:nth-child(1) { animation-delay: 0.1s; }
        .section-card:nth-child(2) { animation-delay: 0.2s; }
        .section-card:nth-child(3) { animation-delay: 0.3s; }
        .section-card:nth-child(4) { animation-delay: 0.4s; }
        .section-card:nth-child(5) { animation-delay: 0.5s; }
        .section-card:nth-child(6) { animation-delay: 0.6s; }

        /* Footer simplificado */
        .footer {
            text-align: center;
            padding: var(--space-xl) var(--space-lg);
            color: var(--vscode-text-muted);
            border-top: 1px solid var(--vscode-border);
            margin-top: var(--space-2xl);
            font-size: 0.85rem;
        }

        /* Estadísticas en tiempo real */
        .stats-mini {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: var(--space-md);
            padding: var(--space-sm);
            background: rgba(255, 255, 255, 0.02);
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            color: var(--vscode-text-muted);
        }

        .stats-mini .stat-item {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .stats-mini .stat-value {
            color: var(--apple-blue);
            font-weight: 600;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="header-left">
                <img src="../logo.png" alt="Sequoia Speed" class="logo">
                <h1>Centro de Reportes</h1>
            </div>
            <div class="header-right">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($current_user['nombre'], 0, 1)); ?>
                    </div>
                    <div>
                        <div><?php echo htmlspecialchars($current_user['nombre']); ?></div>
                        <div style="font-size: 0.8rem;">
                            <?php 
                            foreach ($user_roles as $role) {
                                echo '<span class="role-badge role-' . $role['nombre'] . '">' . ucfirst($role['nombre']) . '</span>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <a href="../index.php" class="btn btn-secondary">🏠 Inicio</a>
                <a href="../accesos/logout.php" class="btn btn-primary">🚪 Salir</a>
            </div>
        </div>
    </header>

    <!-- Container principal -->
    <div class="container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="../index.php">🏠 Inicio</a>
            <span> / </span>
            <span>📊 Centro de Reportes</span>
        </div>

        <!-- Grid de secciones -->
        <div class="sections-grid">
            <!-- Análisis de Ventas -->
            <div class="section-card ventas <?php echo !canAccess('ventas') ? 'disabled' : ''; ?>">
                <div class="section-header">
                    <div class="section-icon">📈</div>
                    <div class="section-title">Análisis de Ventas</div>
                </div>
                <div class="section-description">
                    Reportes detallados de ventas, tendencias y rendimiento comercial
                </div>
                <ul class="options-list">
                    <li class="option-item">
                        <a href="ventas.php" class="option-link priority <?php echo !canAccess('ventas') ? 'disabled' : ''; ?>">
                            <div class="option-icon">📊</div>
                            <div class="option-text">
                                <div class="option-title">Dashboard de Ventas</div>
                                <div class="option-desc">Métricas principales y tendencias</div>
                            </div>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="ventas.php?tipo=productos" class="option-link <?php echo !canAccess('ventas') ? 'disabled' : ''; ?>">
                            <div class="option-icon">📦</div>
                            <div class="option-text">
                                <div class="option-title">Productos Más Vendidos</div>
                                <div class="option-desc">Ranking y análisis de productos</div>
                            </div>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="ventas.php?tipo=clientes" class="option-link <?php echo !canAccess('ventas') ? 'disabled' : ''; ?>">
                            <div class="option-icon">👥</div>
                            <div class="option-text">
                                <div class="option-title">Análisis de Clientes</div>
                                <div class="option-desc">Comportamiento y segmentación</div>
                            </div>
                        </a>
                    </li>
                </ul>
                <div class="stats-mini">
                    <div class="stat-item">
                        <span>💰 Mes actual:</span>
                        <span class="stat-value">$<?php echo number_format($stats['ventas_mes'], 0, ',', '.'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span>📦 Pedidos:</span>
                        <span class="stat-value"><?php echo number_format($stats['pedidos_total']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Reportes Financieros -->
            <div class="section-card financiero <?php echo !canAccess('ventas') ? 'disabled' : ''; ?>">
                <div class="section-header">
                    <div class="section-icon">💰</div>
                    <div class="section-title">Reportes Financieros</div>
                </div>
                <div class="section-description">
                    Análisis financiero, flujo de caja y rentabilidad del negocio
                </div>
                <ul class="options-list">
                    <li class="option-item">
                        <a href="financiero.php" class="option-link priority <?php echo !canAccess('ventas') ? 'disabled' : ''; ?>">
                            <div class="option-icon">💸</div>
                            <div class="option-text">
                                <div class="option-title">Flujo de Caja</div>
                                <div class="option-desc">Ingresos, gastos y proyecciones</div>
                            </div>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="financiero.php?tipo=pagos" class="option-link <?php echo !canAccess('ventas') ? 'disabled' : ''; ?>">
                            <div class="option-icon">💳</div>
                            <div class="option-text">
                                <div class="option-title">Métodos de Pago</div>
                                <div class="option-desc">Análisis por forma de pago</div>
                            </div>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="financiero.php?tipo=rentabilidad" class="option-link <?php echo !canAccess('ventas') ? 'disabled' : ''; ?>">
                            <div class="option-icon">📈</div>
                            <div class="option-text">
                                <div class="option-title">Rentabilidad</div>
                                <div class="option-desc">Márgenes y ROI por producto</div>
                            </div>
                        </a>
                    </li>
                </ul>
                <div class="stats-mini">
                    <div class="stat-item">
                        <span>💵 Ingresos:</span>
                        <span class="stat-value">$<?php echo number_format($stats['ventas_mes'], 0, ',', '.'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span>📊 Margen:</span>
                        <span class="stat-value">25%</span>
                    </div>
                </div>
            </div>

            <!-- Inventario y Productos -->
            <?php if (canAccess('inventario')): ?>
            <div class="section-card inventario">
                <div class="section-header">
                    <div class="section-icon">📦</div>
                    <div class="section-title">Inventario y Productos</div>
                </div>
                <div class="section-description">
                    Análisis de stock, rotación y gestión de inventario
                </div>
                <ul class="options-list">
                    <li class="option-item">
                        <a href="inventario.php" class="option-link priority">
                            <div class="option-icon">📊</div>
                            <div class="option-text">
                                <div class="option-title">Estado de Inventario</div>
                                <div class="option-desc">Stock actual y movimientos</div>
                            </div>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="inventario.php?tipo=rotacion" class="option-link">
                            <div class="option-icon">🔄</div>
                            <div class="option-text">
                                <div class="option-title">Rotación de Inventario</div>
                                <div class="option-desc">Análisis de movimientos</div>
                            </div>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="inventario.php?tipo=alertas" class="option-link">
                            <div class="option-icon">⚠️</div>
                            <div class="option-text">
                                <div class="option-title">Alertas de Stock</div>
                                <div class="option-desc">Stock bajo y reabastecimiento</div>
                            </div>
                        </a>
                    </li>
                </ul>
                <div class="stats-mini">
                    <div class="stat-item">
                        <span>📦 Productos:</span>
                        <span class="stat-value"><?php echo number_format($stats['productos_activos']); ?></span>
                    </div>
                    <div class="stat-item">
                        <span>⚠️ Alertas:</span>
                        <span class="stat-value">12</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Logística y Envíos -->
            <div class="section-card logistica <?php echo !canAccess('ventas') ? 'disabled' : ''; ?>">
                <div class="section-header">
                    <div class="section-icon">🚚</div>
                    <div class="section-title">Logística y Envíos</div>
                </div>
                <div class="section-description">
                    Análisis de entregas, tiempos y rendimiento logístico
                </div>
                <ul class="options-list">
                    <li class="option-item">
                        <a href="logistica.php" class="option-link priority <?php echo !canAccess('ventas') ? 'disabled' : ''; ?>">
                            <div class="option-icon">📋</div>
                            <div class="option-text">
                                <div class="option-title">Dashboard Logístico</div>
                                <div class="option-desc">Métricas de entregas y tiempos</div>
                            </div>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="logistica.php?tipo=transportistas" class="option-link <?php echo !canAccess('ventas') ? 'disabled' : ''; ?>">
                            <div class="option-icon">🚛</div>
                            <div class="option-text">
                                <div class="option-title">Rendimiento Transportistas</div>
                                <div class="option-desc">Análisis por proveedor logístico</div>
                            </div>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="logistica.php?tipo=rutas" class="option-link <?php echo !canAccess('ventas') ? 'disabled' : ''; ?>">
                            <div class="option-icon">🗺️</div>
                            <div class="option-text">
                                <div class="option-title">Análisis de Rutas</div>
                                <div class="option-desc">Optimización y costos</div>
                            </div>
                        </a>
                    </li>
                </ul>
                <div class="stats-mini">
                    <div class="stat-item">
                        <span>📦 Entregas:</span>
                        <span class="stat-value">1,234</span>
                    </div>
                    <div class="stat-item">
                        <span>⏱️ Tiempo:</span>
                        <span class="stat-value">2.5 días</span>
                    </div>
                </div>
            </div>

            <!-- Usuarios y Accesos -->
            <?php if (canAccess('usuarios')): ?>
            <div class="section-card usuarios">
                <div class="section-header">
                    <div class="section-icon">👥</div>
                    <div class="section-title">Usuarios y Accesos</div>
                </div>
                <div class="section-description">
                    Análisis de actividad, auditoría y rendimiento del equipo
                </div>
                <ul class="options-list">
                    <li class="option-item">
                        <a href="usuarios.php" class="option-link priority">
                            <div class="option-icon">📊</div>
                            <div class="option-text">
                                <div class="option-title">Actividad del Equipo</div>
                                <div class="option-desc">Rendimiento y métricas</div>
                            </div>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="usuarios.php?tipo=auditoria" class="option-link">
                            <div class="option-icon">🔍</div>
                            <div class="option-text">
                                <div class="option-title">Auditoría de Accesos</div>
                                <div class="option-desc">Registros y seguridad</div>
                            </div>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="usuarios.php?tipo=sesiones" class="option-link">
                            <div class="option-icon">🔐</div>
                            <div class="option-text">
                                <div class="option-title">Sesiones Activas</div>
                                <div class="option-desc">Usuarios conectados</div>
                            </div>
                        </a>
                    </li>
                </ul>
                <div class="stats-mini">
                    <div class="stat-item">
                        <span>👤 Usuarios:</span>
                        <span class="stat-value"><?php echo number_format($stats['usuarios_activos']); ?></span>
                    </div>
                    <div class="stat-item">
                        <span>🔥 Activos:</span>
                        <span class="stat-value">8</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Dashboard Ejecutivo -->
            <?php if ($is_admin || $is_manager): ?>
            <div class="section-card ejecutivo">
                <div class="section-header">
                    <div class="section-icon">💼</div>
                    <div class="section-title">Dashboard Ejecutivo</div>
                </div>
                <div class="section-description">
                    Métricas ejecutivas, KPIs y análisis estratégico del negocio
                </div>
                <ul class="options-list">
                    <li class="option-item">
                        <a href="ejecutivo.php" class="option-link priority">
                            <div class="option-icon">📈</div>
                            <div class="option-text">
                                <div class="option-title">KPIs Principales</div>
                                <div class="option-desc">Métricas clave del negocio</div>
                            </div>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="ejecutivo.php?tipo=comparativo" class="option-link">
                            <div class="option-icon">📊</div>
                            <div class="option-text">
                                <div class="option-title">Análisis Comparativo</div>
                                <div class="option-desc">Períodos y tendencias</div>
                            </div>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="ejecutivo.php?tipo=proyecciones" class="option-link">
                            <div class="option-icon">🎯</div>
                            <div class="option-text">
                                <div class="option-title">Proyecciones</div>
                                <div class="option-desc">Forecasting y objetivos</div>
                            </div>
                        </a>
                    </li>
                </ul>
                <div class="stats-mini">
                    <div class="stat-item">
                        <span>📊 ROI:</span>
                        <span class="stat-value">+18%</span>
                    </div>
                    <div class="stat-item">
                        <span>🎯 Meta:</span>
                        <span class="stat-value">87%</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; 2025 Sequoia Speed - Centro de Reportes v1.0</p>
        <p>Última actualización: <?php echo date('d/m/Y H:i'); ?> | Usuario: <?php echo htmlspecialchars($current_user['nombre']); ?> | Rol: <?php echo ucfirst($primary_role); ?></p>
    </footer>

    <script>
        // Información del usuario disponible en JavaScript
        window.currentUser = <?php echo json_encode($current_user); ?>;
        window.userPermissions = <?php echo json_encode($user_permissions); ?>;
        window.userRoles = <?php echo json_encode($user_roles); ?>;
        window.primaryRole = '<?php echo $primary_role; ?>';
        window.isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
        window.isManager = <?php echo $is_manager ? 'true' : 'false'; ?>;
        
        // Función para verificar permisos en JavaScript
        function canUser(module, permission) {
            return window.userPermissions.some(p => 
                p.modulo === module && p.tipo_permiso === permission
            );
        }
        
        // Función para verificar roles en JavaScript
        function hasRole(roleName) {
            return window.userRoles.some(r => r.nombre === roleName);
        }
        
        // Efectos mejorados
        document.querySelectorAll('.option-link:not(.disabled)').forEach(link => {
            link.addEventListener('mouseenter', function () {
                this.style.transform = 'translateX(8px) scale(1.02)';
            });

            link.addEventListener('mouseleave', function () {
                this.style.transform = 'translateX(0) scale(1)';
            });
        });

        // Actualizar estadísticas cada 30 segundos
        setInterval(function() {
            fetch('api/stats.php')
                .then(response => response.json())
                .then(data => {
                    // Actualizar valores estadísticos
                    document.querySelectorAll('.stat-value').forEach(element => {
                        // Lógica para actualizar estadísticas específicas
                    });
                })
                .catch(error => console.error('Error actualizando estadísticas:', error));
        }, 30000);

        console.log('Centro de Reportes inicializado - v1.0');
        console.log('Usuario:', window.currentUser.nombre);
        console.log('Rol principal:', window.primaryRole);
        console.log('Permisos:', window.userPermissions.length);
        console.log('Es admin:', window.isAdmin);
    </script>

    <!-- Sistema de Notificaciones -->
    <script src="../notifications/notifications.js"></script>
</body>

</html>