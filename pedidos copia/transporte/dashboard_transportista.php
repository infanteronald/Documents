<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config_secure.php';
require_once '../ui-helpers.php';
require_once '../php82_helpers.php';

// Obtener estadísticas del transportista
function obtenerEstadisticasTransportista($conn, $transportista_id = null) {
    $fecha_hoy = date('Y-m-d');
    $fecha_semana = date('Y-m-d', strtotime('-7 days'));
    $fecha_mes = date('Y-m-d', strtotime('-30 days'));
    
    $stats = [
        'hoy' => [
            'total' => 0,
            'entregados' => 0,
            'en_ruta' => 0,
            'reintento' => 0,
            'devueltos' => 0
        ],
        'semana' => [
            'total' => 0,
            'entregados' => 0,
            'en_ruta' => 0,
            'reintento' => 0,
            'devueltos' => 0
        ],
        'mes' => [
            'total' => 0,
            'entregados' => 0,
            'en_ruta' => 0,
            'reintento' => 0,
            'devueltos' => 0
        ],
        'general' => [
            'pendientes_sin_guia' => 0,
            'con_recaudo' => 0,
            'tiempo_promedio' => 0,
            'urgentes' => 0
        ]
    ];
    
    try {
        // Estadísticas de hoy
        $query = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN estado_entrega = 'entregado' THEN 1 ELSE 0 END) as entregados,
            SUM(CASE WHEN estado_entrega = 'en_ruta' THEN 1 ELSE 0 END) as en_ruta,
            SUM(CASE WHEN estado_entrega = 'reintento' THEN 1 ELSE 0 END) as reintento,
            SUM(CASE WHEN estado_entrega = 'devuelto' THEN 1 ELSE 0 END) as devueltos
            FROM pedidos_detal 
            WHERE DATE(fecha) = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $fecha_hoy);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stats['hoy'] = [
                'total' => (int)$row['total'],
                'entregados' => (int)$row['entregados'],
                'en_ruta' => (int)$row['en_ruta'],
                'reintento' => (int)$row['reintento'],
                'devueltos' => (int)$row['devueltos']
            ];
        }
        
        // Estadísticas de la semana
        $query = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN estado_entrega = 'entregado' THEN 1 ELSE 0 END) as entregados,
            SUM(CASE WHEN estado_entrega = 'en_ruta' THEN 1 ELSE 0 END) as en_ruta,
            SUM(CASE WHEN estado_entrega = 'reintento' THEN 1 ELSE 0 END) as reintento,
            SUM(CASE WHEN estado_entrega = 'devuelto' THEN 1 ELSE 0 END) as devueltos
            FROM pedidos_detal 
            WHERE DATE(fecha) >= ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $fecha_semana);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stats['semana'] = [
                'total' => (int)$row['total'],
                'entregados' => (int)$row['entregados'],
                'en_ruta' => (int)$row['en_ruta'],
                'reintento' => (int)$row['reintento'],
                'devueltos' => (int)$row['devueltos']
            ];
        }
        
        // Estadísticas del mes
        $query = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN estado_entrega = 'entregado' THEN 1 ELSE 0 END) as entregados,
            SUM(CASE WHEN estado_entrega = 'en_ruta' THEN 1 ELSE 0 END) as en_ruta,
            SUM(CASE WHEN estado_entrega = 'reintento' THEN 1 ELSE 0 END) as reintento,
            SUM(CASE WHEN estado_entrega = 'devuelto' THEN 1 ELSE 0 END) as devueltos
            FROM pedidos_detal 
            WHERE DATE(fecha) >= ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $fecha_mes);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stats['mes'] = [
                'total' => (int)$row['total'],
                'entregados' => (int)$row['entregados'],
                'en_ruta' => (int)$row['en_ruta'],
                'reintento' => (int)$row['reintento'],
                'devueltos' => (int)$row['devueltos']
            ];
        }
        
        // Estadísticas generales
        $query = "SELECT 
            COUNT(*) as pendientes_sin_guia,
            SUM(CASE WHEN recaudo = '1' THEN 1 ELSE 0 END) as con_recaudo,
            AVG(CASE WHEN tiempo_transcurrido_minutos > 0 THEN tiempo_transcurrido_minutos ELSE NULL END) as tiempo_promedio,
            SUM(CASE WHEN prioridad_urgencia = 'rojo' THEN 1 ELSE 0 END) as urgentes
            FROM pedidos_detal 
            WHERE tiene_guia = '0'";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stats['general'] = [
                'pendientes_sin_guia' => (int)$row['pendientes_sin_guia'],
                'con_recaudo' => (int)$row['con_recaudo'],
                'tiempo_promedio' => round((float)$row['tiempo_promedio'], 0),
                'urgentes' => (int)$row['urgentes']
            ];
        }
        
    } catch (Exception $e) {
        error_log("Error obteniendo estadísticas: " . $e->getMessage());
    }
    
    return $stats;
}

// Obtener estadísticas por ciudades
function obtenerEstadisticasPorCiudad($conn) {
    $ciudades = [];
    
    try {
        $query = "SELECT 
            ciudad,
            COUNT(*) as total,
            SUM(CASE WHEN estado_entrega = 'entregado' THEN 1 ELSE 0 END) as entregados,
            SUM(CASE WHEN tiene_guia = '0' THEN 1 ELSE 0 END) as sin_guia,
            SUM(CASE WHEN recaudo = '1' THEN 1 ELSE 0 END) as con_recaudo
            FROM pedidos_detal 
            WHERE ciudad IS NOT NULL AND ciudad != ''
            GROUP BY ciudad
            ORDER BY total DESC
            LIMIT 10";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $ciudades[] = [
                'ciudad' => $row['ciudad'],
                'total' => (int)$row['total'],
                'entregados' => (int)$row['entregados'],
                'sin_guia' => (int)$row['sin_guia'],
                'con_recaudo' => (int)$row['con_recaudo'],
                'porcentaje_entrega' => $row['total'] > 0 ? round(($row['entregados'] / $row['total']) * 100, 1) : 0
            ];
        }
        
    } catch (Exception $e) {
        error_log("Error obteniendo estadísticas por ciudad: " . $e->getMessage());
    }
    
    return $ciudades;
}

// Obtener datos para el gráfico de tendencias
function obtenerDatosTendencia($conn) {
    $tendencias = [];
    
    try {
        $query = "SELECT 
            DATE(fecha) as fecha,
            COUNT(*) as total,
            SUM(CASE WHEN estado_entrega = 'entregado' THEN 1 ELSE 0 END) as entregados
            FROM pedidos_detal 
            WHERE DATE(fecha) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(fecha)
            ORDER BY fecha DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $tendencias[] = [
                'fecha' => $row['fecha'],
                'total' => (int)$row['total'],
                'entregados' => (int)$row['entregados']
            ];
        }
        
    } catch (Exception $e) {
        error_log("Error obteniendo datos de tendencia: " . $e->getMessage());
    }
    
    return array_reverse($tendencias);
}

// Obtener las estadísticas
$stats = obtenerEstadisticasTransportista($conn);
$ciudades = obtenerEstadisticasPorCiudad($conn);
$tendencias = obtenerDatosTendencia($conn);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Transportista - VitalCarga</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📊</text></svg>">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#0d1117">
    <link rel="stylesheet" href="../listar_pedidos.css">
    
    <style>
        .dashboard-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .dashboard-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .dashboard-title {
            color: #58a6ff;
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .dashboard-subtitle {
            color: #8b949e;
            font-size: 1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #21262d;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 20px;
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            border-color: #58a6ff;
        }
        
        .stat-card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .stat-icon {
            font-size: 1.5rem;
        }
        
        .stat-title {
            color: #e6edf3;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #58a6ff;
            margin-bottom: 10px;
        }
        
        .stat-details {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .stat-detail {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
        }
        
        .stat-detail-value {
            font-weight: 600;
        }
        
        .entregados { color: #28a745; }
        .en-ruta { color: #007bff; }
        .reintento { color: #ffc107; }
        .devueltos { color: #dc3545; }
        .recaudo { color: #ff6b35; }
        .urgentes { color: #ff1744; }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #30363d;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 10px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745 0%, #20c997 100%);
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        .ciudades-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .ciudad-card {
            background: #21262d;
            border: 1px solid #30363d;
            border-radius: 6px;
            padding: 15px;
        }
        
        .ciudad-nombre {
            font-size: 1.1rem;
            font-weight: 600;
            color: #58a6ff;
            margin-bottom: 10px;
        }
        
        .ciudad-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .ciudad-stat {
            text-align: center;
        }
        
        .ciudad-stat-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: #e6edf3;
        }
        
        .ciudad-stat-label {
            font-size: 0.8rem;
            color: #8b949e;
        }
        
        .tendencia-container {
            background: #21262d;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .tendencia-title {
            color: #e6edf3;
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .tendencia-grafico {
            display: flex;
            align-items: end;
            gap: 10px;
            height: 200px;
            padding: 10px;
            border-bottom: 1px solid #30363d;
        }
        
        .tendencia-barra {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }
        
        .barra-visual {
            background: linear-gradient(to top, #58a6ff, #79c0ff);
            border-radius: 3px;
            min-height: 20px;
            width: 100%;
            transition: height 0.3s ease;
        }
        
        .barra-fecha {
            font-size: 0.7rem;
            color: #8b949e;
            writing-mode: vertical-lr;
            text-orientation: mixed;
        }
        
        .barra-valor {
            font-size: 0.8rem;
            color: #e6edf3;
            font-weight: 500;
        }
        
        .actions-container {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 20px;
        }
        
        .btn-action {
            background: #238636;
            color: white;
            border: 1px solid #238636;
            border-radius: 6px;
            padding: 12px 20px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-action:hover {
            background: #2ea043;
            border-color: #2ea043;
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: #21262d;
            color: #e6edf3;
            border: 1px solid #30363d;
        }
        
        .btn-secondary:hover {
            background: #30363d;
            border-color: #8b949e;
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 10px;
            }
            
            .dashboard-title {
                font-size: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-value {
                font-size: 2rem;
            }
            
            .ciudades-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .tendencia-grafico {
                height: 150px;
            }
            
            .actions-container {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <div class="dashboard-header">
        <h1 class="dashboard-title">📊 Dashboard Transportista</h1>
        <p class="dashboard-subtitle">Estadísticas y métricas de rendimiento - VitalCarga</p>
    </div>
    
    <!-- Estadísticas principales -->
    <div class="stats-grid">
        <!-- Estadísticas de Hoy -->
        <div class="stat-card">
            <div class="stat-card-header">
                <span class="stat-icon">📅</span>
                <span class="stat-title">Entregas Hoy</span>
            </div>
            <div class="stat-value"><?php echo $stats['hoy']['total']; ?></div>
            <div class="stat-details">
                <div class="stat-detail entregados">
                    <span>✅</span>
                    <span class="stat-detail-value"><?php echo $stats['hoy']['entregados']; ?></span>
                    <span>Entregados</span>
                </div>
                <div class="stat-detail en-ruta">
                    <span>🚛</span>
                    <span class="stat-detail-value"><?php echo $stats['hoy']['en_ruta']; ?></span>
                    <span>En Ruta</span>
                </div>
                <div class="stat-detail reintento">
                    <span>🔄</span>
                    <span class="stat-detail-value"><?php echo $stats['hoy']['reintento']; ?></span>
                    <span>Reintento</span>
                </div>
            </div>
            <?php if ($stats['hoy']['total'] > 0): ?>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo ($stats['hoy']['entregados'] / $stats['hoy']['total']) * 100; ?>%"></div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Estadísticas de la Semana -->
        <div class="stat-card">
            <div class="stat-card-header">
                <span class="stat-icon">📊</span>
                <span class="stat-title">Entregas esta Semana</span>
            </div>
            <div class="stat-value"><?php echo $stats['semana']['total']; ?></div>
            <div class="stat-details">
                <div class="stat-detail entregados">
                    <span>✅</span>
                    <span class="stat-detail-value"><?php echo $stats['semana']['entregados']; ?></span>
                    <span>Entregados</span>
                </div>
                <div class="stat-detail devueltos">
                    <span>↩️</span>
                    <span class="stat-detail-value"><?php echo $stats['semana']['devueltos']; ?></span>
                    <span>Devueltos</span>
                </div>
            </div>
            <?php if ($stats['semana']['total'] > 0): ?>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo ($stats['semana']['entregados'] / $stats['semana']['total']) * 100; ?>%"></div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Estadísticas del Mes -->
        <div class="stat-card">
            <div class="stat-card-header">
                <span class="stat-icon">📈</span>
                <span class="stat-title">Entregas este Mes</span>
            </div>
            <div class="stat-value"><?php echo $stats['mes']['total']; ?></div>
            <div class="stat-details">
                <div class="stat-detail entregados">
                    <span>✅</span>
                    <span class="stat-detail-value"><?php echo $stats['mes']['entregados']; ?></span>
                    <span>Entregados</span>
                </div>
                <div class="stat-detail">
                    <span>⏱️</span>
                    <span class="stat-detail-value"><?php echo $stats['general']['tiempo_promedio']; ?></span>
                    <span>min promedio</span>
                </div>
            </div>
            <?php if ($stats['mes']['total'] > 0): ?>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo ($stats['mes']['entregados'] / $stats['mes']['total']) * 100; ?>%"></div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Estadísticas Generales -->
        <div class="stat-card">
            <div class="stat-card-header">
                <span class="stat-icon">🚚</span>
                <span class="stat-title">Estado General</span>
            </div>
            <div class="stat-value"><?php echo $stats['general']['pendientes_sin_guia']; ?></div>
            <div class="stat-details">
                <div class="stat-detail recaudo">
                    <span>💰</span>
                    <span class="stat-detail-value"><?php echo $stats['general']['con_recaudo']; ?></span>
                    <span>Con Recaudo</span>
                </div>
                <div class="stat-detail urgentes">
                    <span>🚨</span>
                    <span class="stat-detail-value"><?php echo $stats['general']['urgentes']; ?></span>
                    <span>Urgentes</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tendencia de los últimos 7 días -->
    <div class="tendencia-container">
        <h3 class="tendencia-title">📈 Tendencia de Entregas (Últimos 7 días)</h3>
        <div class="tendencia-grafico">
            <?php foreach ($tendencias as $dia): ?>
                <?php $altura = $dia['total'] > 0 ? ($dia['total'] / max(array_column($tendencias, 'total'))) * 100 : 5; ?>
                <div class="tendencia-barra">
                    <div class="barra-valor"><?php echo $dia['total']; ?></div>
                    <div class="barra-visual" style="height: <?php echo $altura; ?>%"></div>
                    <div class="barra-fecha"><?php echo date('d/m', strtotime($dia['fecha'])); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Estadísticas por Ciudad -->
    <div class="ciudades-grid">
        <h3 style="grid-column: 1 / -1; color: #e6edf3; font-size: 1.2rem; margin-bottom: 15px;">🏙️ Entregas por Ciudad</h3>
        <?php foreach (array_slice($ciudades, 0, 6) as $ciudad): ?>
            <div class="ciudad-card">
                <div class="ciudad-nombre"><?php echo htmlspecialchars($ciudad['ciudad']); ?></div>
                <div class="ciudad-stats">
                    <div class="ciudad-stat">
                        <div class="ciudad-stat-value"><?php echo $ciudad['total']; ?></div>
                        <div class="ciudad-stat-label">Total</div>
                    </div>
                    <div class="ciudad-stat">
                        <div class="ciudad-stat-value entregados"><?php echo $ciudad['entregados']; ?></div>
                        <div class="ciudad-stat-label">Entregados</div>
                    </div>
                    <div class="ciudad-stat">
                        <div class="ciudad-stat-value"><?php echo $ciudad['sin_guia']; ?></div>
                        <div class="ciudad-stat-label">Sin Guía</div>
                    </div>
                    <div class="ciudad-stat">
                        <div class="ciudad-stat-value recaudo"><?php echo $ciudad['con_recaudo']; ?></div>
                        <div class="ciudad-stat-label">C/Recaudo</div>
                    </div>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $ciudad['porcentaje_entrega']; ?>%"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Acciones -->
    <div class="actions-container">
        <a href="vitalcarga.php" class="btn-action">
            <span>🚚</span>
            <span>Ir a Gestión de Guías</span>
        </a>
        <a href="#" onclick="window.print()" class="btn-action btn-secondary">
            <span>🖨️</span>
            <span>Imprimir Reporte</span>
        </a>
        <a href="#" onclick="location.reload()" class="btn-action btn-secondary">
            <span>🔄</span>
            <span>Actualizar Datos</span>
        </a>
    </div>
</div>

<script>
// Actualización automática cada 5 minutos
setInterval(function() {
    location.reload();
}, 300000);

// Mostrar última actualización
document.addEventListener('DOMContentLoaded', function() {
    console.log('Dashboard cargado:', new Date().toLocaleString());
});
</script>
</body>
</html>