<?php
/**
 * Panel de Control Principal - Sequoia Speed
 * Dashboard Unificado con Sistema de Accesos Integrado
 */

// Requerir autenticación
require_once 'accesos/auth_helper.php';

// Proteger la página - requiere login
$current_user = auth_require();

// Registrar acceso
auth_log('read', 'sistema', 'Acceso al panel principal');

// Obtener información del usuario
$user_roles = AuthHelper::getCurrentUserRoles();
$user_permissions = AuthHelper::getCurrentUserPermissions();

// Función para verificar si el usuario puede acceder a un módulo
function canAccess($module) {
    return auth_can($module, 'leer');
}

// Obtener estadísticas básicas si el usuario tiene permisos
$stats = [
    'pedidos_hoy' => 0,
    'pagos_pendientes' => 0,
    'listos_envio' => 0,
    'sin_guia' => 0,
    'productos_stock_bajo' => 0,
    'ventas_mes' => 0,
    'usuarios_activos' => 0
];

if (canAccess('ventas')) {
    try {
        global $conn;
        
        // Pedidos de hoy
        $hoy = date('Y-m-d');
        $stmt = $conn->prepare("SELECT COUNT(*) FROM pedidos_detal WHERE DATE(fecha) = ?");
        $stmt->bind_param("s", $hoy);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['pedidos_hoy'] = $result->fetch_row()[0];
        
        // Pagos pendientes
        $stmt = $conn->prepare("SELECT COUNT(*) FROM pedidos_detal WHERE estado = 'Pago Pendiente'");
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['pagos_pendientes'] = $result->fetch_row()[0];
        
        // Listos para envío
        $stmt = $conn->prepare("SELECT COUNT(*) FROM pedidos_detal WHERE estado = 'Pago Confirmado'");
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['listos_envio'] = $result->fetch_row()[0];
        
        // Sin guía
        $stmt = $conn->prepare("SELECT COUNT(*) FROM pedidos_detal WHERE (guia IS NULL OR guia = '') AND estado = 'Pago Confirmado'");
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['sin_guia'] = $result->fetch_row()[0];
        
        // Ventas del mes
        $stmt = $conn->prepare("SELECT SUM(monto) FROM pedidos_detal WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) AND estado != 'Anulado'");
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['ventas_mes'] = $result->fetch_row()[0] ?? 0;
        
    } catch (Exception $e) {
        error_log("Error obteniendo estadísticas: " . $e->getMessage());
    }
}

// Estadísticas de inventario
if (canAccess('inventario')) {
    try {
        // Productos con stock bajo (simulado - ajustar según estructura real)
        $stmt = $conn->prepare("SELECT COUNT(*) FROM productos WHERE stock < stock_minimo");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            $stats['productos_stock_bajo'] = $result->fetch_row()[0];
        }
    } catch (Exception $e) {
        // Ignorar si no existe tabla productos
    }
}

// Estadísticas de usuarios
if (canAccess('usuarios')) {
    try {
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
    <title>Sequoia Speed - Panel de Control</title>
    <link rel="icon" href="logo.png" type="image/png">

    <!-- Sistema de Notificaciones -->
    <link rel="stylesheet" href="notifications/notifications.css">
    <link rel="stylesheet" href="notifications/push_notifications.css">

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

        .logout-btn {
            background: rgba(255, 69, 58, 0.1);
            color: var(--apple-red);
            border: 1px solid rgba(255, 69, 58, 0.3);
            padding: var(--space-sm) var(--space-md);
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all var(--transition-base);
        }

        .logout-btn:hover {
            background: rgba(255, 69, 58, 0.2);
        }


        /* Container principal */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: var(--space-lg);
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
        .section-card.diaria::before {
            background: linear-gradient(90deg, var(--apple-teal), var(--apple-blue));
        }

        .section-card.envios::before {
            background: linear-gradient(90deg, var(--apple-orange), var(--apple-red));
        }

        .section-card.inventario::before {
            background: linear-gradient(90deg, var(--apple-purple), var(--apple-blue));
        }

        .section-card.accesos::before {
            background: linear-gradient(90deg, var(--apple-red), var(--apple-orange));
        }

        .section-card.cliente::before {
            background: linear-gradient(90deg, var(--apple-blue), var(--apple-purple));
        }

        .section-card.reportes::before {
            background: linear-gradient(90deg, var(--apple-teal), var(--apple-orange));
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

        .option-link.disabled {
            opacity: 0.5;
            pointer-events: none;
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

        /* Accesos rápidos personalizados */
        .quick-actions {
            margin-top: var(--space-lg);
            padding: var(--space-lg);
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-md);
        }

        .quick-actions h3 {
            color: var(--vscode-text-light);
            margin-bottom: var(--space-md);
            font-size: 1.1rem;
        }

        .quick-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-sm);
        }

        .quick-btn {
            padding: var(--space-sm) var(--space-md);
            background: var(--apple-blue);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-size: 0.85rem;
            transition: all var(--transition-base);
            cursor: pointer;
        }

        .quick-btn:hover {
            background: var(--apple-blue-hover);
            transform: translateY(-1px);
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
    </style>
</head>

<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="header-left">
                <img src="logo.png" alt="Sequoia Speed" class="logo">
                <h1>Panel de Control</h1>
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
                <a href="accesos/logout.php" class="logout-btn">🚪 Salir</a>
            </div>
        </div>
    </header>


    <!-- Container principal -->
    <div class="container">

        <!-- Grid de secciones -->
        <div class="sections-grid">
            <!-- Gestión Diaria -->
            <div class="section-card diaria <?php echo !canAccess('ventas') ? 'disabled' : ''; ?>">
                <div class="section-header">
                    <div class="section-icon">📦</div>
                    <div class="section-title">Gestión Diaria</div>
                </div>
                <div class="section-description">
                    Herramientas para el trabajo diario de ventas y pedidos
                </div>
                <ul class="options-list">
                    <li class="option-item">
                        <a href="orden_pedido.php" class="option-link priority <?php echo !canAccess('ventas') ? 'disabled' : ''; ?>">
                            <div class="option-icon">🆕</div>
                            <div class="option-text">
                                <div class="option-title">Crear Nuevo Pedido</div>
                                <div class="option-desc">Formulario rápido y sencillo</div>
                            </div>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="listar_pedidos.php" class="option-link <?php echo !canAccess('ventas') ? 'disabled' : ''; ?>">
                            <div class="option-icon">📋</div>
                            <div class="option-text">
                                <div class="option-title">Ver Todos los Pedidos</div>
                                <div class="option-desc">Lista completa con filtros básicos</div>
                            </div>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="listar_pedidos.php?filtro=hoy" class="option-link <?php echo !canAccess('ventas') ? 'disabled' : ''; ?>">
                            <div class="option-icon">🔍</div>
                            <div class="option-text">
                                <div class="option-title">Pedidos de Hoy</div>
                                <div class="option-desc">Vista del día actual</div>
                            </div>
                            <span class="option-badge warning"><?php echo $stats['pedidos_hoy']; ?></span>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="listar_pedidos.php?filtro=pago_pendiente" class="option-link <?php echo !canAccess('ventas') ? 'disabled' : ''; ?>">
                            <div class="option-icon">⏳</div>
                            <div class="option-text">
                                <div class="option-title">Pagos Pendientes</div>
                                <div class="option-desc">Seguimiento de pagos</div>
                            </div>
                            <span class="option-badge"><?php echo $stats['pagos_pendientes']; ?></span>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="buscar_cliente.php" class="option-link <?php echo !canAccess('ventas') ? 'disabled' : ''; ?>">
                            <div class="option-icon">🔍</div>
                            <div class="option-text">
                                <div class="option-title">Buscar Cliente</div>
                                <div class="option-desc">Por teléfono, nombre o pedido</div>
                            </div>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Envíos y Guías -->
            <div class="section-card envios <?php echo !canAccess('ventas') ? 'disabled' : ''; ?>">
                <div class="section-header">
                    <div class="section-icon">🚚</div>
                    <div class="section-title">Envíos y Guías</div>
                </div>
                <div class="section-description">
                    Gestión completa de envíos y seguimiento de guías
                </div>
                <ul class="options-list">
                    <li class="option-item">
                        <a href="transporte/vitalcarga.php" class="option-link priority <?php echo !canAccess('ventas') ? 'disabled' : ''; ?>">
                            <div class="option-icon">🚛</div>
                            <div class="option-text">
                                <div class="option-title">VitalCarga - Gestión de Guías</div>
                                <div class="option-desc">Interface especializada para transportistas</div>
                            </div>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="listar_pedidos.php?filtro=pago_confirmado" class="option-link <?php echo !canAccess('ventas') ? 'disabled' : ''; ?>">
                            <div class="option-icon">📦</div>
                            <div class="option-text">
                                <div class="option-title">Listos para Envío</div>
                                <div class="option-desc">Pedidos pagados pendientes</div>
                            </div>
                            <span class="option-badge success"><?php echo $stats['listos_envio']; ?></span>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="listar_pedidos.php?filtro=sin_guia" class="option-link <?php echo !canAccess('ventas') ? 'disabled' : ''; ?>">
                            <div class="option-icon">📋</div>
                            <div class="option-text">
                                <div class="option-title">Pedidos Sin Guía</div>
                                <div class="option-desc">Requieren asignación de transportista</div>
                            </div>
                            <span class="option-badge warning"><?php echo $stats['sin_guia']; ?></span>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="monitor.php" class="option-link <?php echo !canAccess('ventas') ? 'disabled' : ''; ?>">
                            <div class="option-icon">📺</div>
                            <div class="option-text">
                                <div class="option-title">Monitor de Pedidos</div>
                                <div class="option-desc">Vista en tiempo real</div>
                            </div>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Inventario -->
            <?php if (canAccess('inventario')): ?>
            <div class="section-card inventario">
                <div class="section-header">
                    <div class="section-icon">📦</div>
                    <div class="section-title">Inventario</div>
                </div>
                <div class="section-description">
                    Control completo de productos y gestión de stock
                </div>
                <ul class="options-list">
                    <li class="option-item">
                        <a href="inventario/productos.php" class="option-link priority">
                            <div class="option-icon">📋</div>
                            <div class="option-text">
                                <div class="option-title">Gestión de Productos</div>
                                <div class="option-desc">CRUD completo de productos</div>
                            </div>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="inventario/almacenes/" class="option-link">
                            <div class="option-icon">🏪</div>
                            <div class="option-text">
                                <div class="option-title">Gestión de Almacenes</div>
                                <div class="option-desc">Administrar ubicaciones y stock</div>
                            </div>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="inventario/categorias.php" class="option-link">
                            <div class="option-icon">🏷️</div>
                            <div class="option-text">
                                <div class="option-title">Categorías</div>
                                <div class="option-desc">Organización por categorías</div>
                            </div>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="inventario/movimientos.php" class="option-link">
                            <div class="option-icon">🔄</div>
                            <div class="option-text">
                                <div class="option-title">Movimientos</div>
                                <div class="option-desc">Historial de entradas y salidas</div>
                            </div>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="inventario/alertas.php" class="option-link">
                            <div class="option-icon">⚠️</div>
                            <div class="option-text">
                                <div class="option-title">Alertas de Stock</div>
                                <div class="option-desc">Stock bajo y vencimientos</div>
                            </div>
                            <span class="option-badge warning"><?php echo $stats['productos_stock_bajo']; ?></span>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="inventario/proveedores.php" class="option-link">
                            <div class="option-icon">🏢</div>
                            <div class="option-text">
                                <div class="option-title">Proveedores</div>
                                <div class="option-desc">Gestión de proveedores</div>
                            </div>
                        </a>
                    </li>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Reportes y Análisis -->
            <?php if (canAccess('reportes')): ?>
            <div class="section-card reportes">
                <div class="section-header">
                    <div class="section-icon">📊</div>
                    <div class="section-title">Reportes y Análisis</div>
                </div>
                <div class="section-description">
                    Informes detallados y análisis de datos
                </div>
                <ul class="options-list">
                    <li class="option-item">
                        <a href="reportes/dashboard.php" class="option-link priority">
                            <div class="option-icon">📈</div>
                            <div class="option-text">
                                <div class="option-title">Dashboard Analytics</div>
                                <div class="option-desc">Vista general con gráficos</div>
                            </div>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="exportar_excel.php" class="option-link">
                            <div class="option-icon">📄</div>
                            <div class="option-text">
                                <div class="option-title">Exportar a Excel</div>
                                <div class="option-desc">Datos de pedidos y ventas</div>
                            </div>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="reportes/ventas.php" class="option-link">
                            <div class="option-icon">💰</div>
                            <div class="option-text">
                                <div class="option-title">Reporte de Ventas</div>
                                <div class="option-desc">Análisis de ventas por período</div>
                            </div>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="reportes/productos.php" class="option-link">
                            <div class="option-icon">📦</div>
                            <div class="option-text">
                                <div class="option-title">Reporte de Productos</div>
                                <div class="option-desc">Productos más vendidos</div>
                            </div>
                        </a>
                    </li>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Sistema de Accesos -->
            <?php if (canAccess('usuarios')): ?>
            <div class="section-card accesos">
                <div class="section-header">
                    <div class="section-icon">🔐</div>
                    <div class="section-title">Sistema de Accesos</div>
                </div>
                <div class="section-description">
                    Administración de usuarios, roles y permisos
                </div>
                <ul class="options-list">
                    <li class="option-item">
                        <a href="accesos/dashboard.php" class="option-link priority">
                            <div class="option-icon">🏠</div>
                            <div class="option-text">
                                <div class="option-title">Dashboard de Accesos</div>
                                <div class="option-desc">Panel de administración</div>
                            </div>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="accesos/usuarios.php" class="option-link">
                            <div class="option-icon">👥</div>
                            <div class="option-text">
                                <div class="option-title">Gestión de Usuarios</div>
                                <div class="option-desc">Crear, editar y administrar usuarios</div>
                            </div>
                            <span class="option-badge info"><?php echo $stats['usuarios_activos']; ?></span>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="accesos/roles.php" class="option-link">
                            <div class="option-icon">🎭</div>
                            <div class="option-text">
                                <div class="option-title">Roles y Permisos</div>
                                <div class="option-desc">Configuración de roles</div>
                            </div>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="accesos/auditoria.php" class="option-link">
                            <div class="option-icon">📊</div>
                            <div class="option-text">
                                <div class="option-title">Auditoría</div>
                                <div class="option-desc">Registro de actividades</div>
                            </div>
                        </a>
                    </li>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Atención al Cliente -->
            <div class="section-card cliente">
                <div class="section-header">
                    <div class="section-icon">👥</div>
                    <div class="section-title">Atención al Cliente</div>
                </div>
                <div class="section-description">
                    Herramientas para atención y soporte al cliente
                </div>
                <ul class="options-list">
                    <li class="option-item">
                        <a href="buscar_cliente.php" class="option-link priority">
                            <div class="option-icon">🔍</div>
                            <div class="option-text">
                                <div class="option-title">Buscar Cliente</div>
                                <div class="option-desc">Por teléfono, nombre o pedido</div>
                            </div>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="tareas_diarias.php" class="option-link">
                            <div class="option-icon">📝</div>
                            <div class="option-text">
                                <div class="option-title">Tareas Diarias</div>
                                <div class="option-desc">Gestión de tareas pendientes</div>
                            </div>
                        </a>
                    </li>
                    <li class="option-item">
                        <a href="alertas_urgentes.php" class="option-link">
                            <div class="option-icon">🚨</div>
                            <div class="option-text">
                                <div class="option-title">Alertas Urgentes</div>
                                <div class="option-desc">Notificaciones importantes</div>
                            </div>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; 2025 Sequoia Speed - Sistema Integrado de Gestión v3.0</p>
        <p>Sistema de Accesos Completo - Usuario: <?php echo htmlspecialchars($current_user['nombre']); ?> | Rol: <?php echo ucfirst($primary_role); ?></p>
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
                    // Actualizar badges con nuevos datos
                    document.querySelectorAll('.option-badge').forEach(badge => {
                        // Lógica para actualizar badges específicos
                    });
                })
                .catch(error => console.error('Error actualizando estadísticas:', error));
        }, 30000);

        console.log('Panel de Control inicializado - Sistema Integrado v3.0');
        console.log('Usuario:', window.currentUser.nombre);
        console.log('Rol principal:', window.primaryRole);
        console.log('Permisos:', window.userPermissions.length);
        console.log('Es admin:', window.isAdmin);
    </script>

    <!-- Sistema de Notificaciones -->
    <script src="notifications/notifications.js"></script>
</body>

</html>