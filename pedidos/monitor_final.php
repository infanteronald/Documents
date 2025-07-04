<?php
/**
 * MONITOR DE PEDIDOS - Versión Final Optimizada
 * Muestra SOLO pedidos SIN ENVIAR sin importar la fecha
 * Auto-refresh cada 3 segundos para monitor de pared
 * Compatible con PHP 8 y MySQLi usando bind_result
 */

// Configuración de debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Headers para evitar cache
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Incluir conexión a la base de datos
require_once 'conexion.php';

// Configuración para monitor
$limite_pedidos = 20; // Máximo 20 pedidos visibles en monitor

$pedidos = array();
$stats = array(
    'total_sin_enviar' => 0,
    'pendientes_pago' => 0,
    'pagados_sin_enviar' => 0,
    'con_comprobante' => 0,
    'monto_total_pendiente' => 0
);

try {
    // === CONSULTA PRINCIPAL: Solo pedidos SIN ENVIAR (usando la misma lógica que listar_pedidos.php) ===
    $where_monitor = "enviado = '0' AND anulado = '0'";

    $sql_pedidos = "SELECT
                        p.id, p.nombre, p.correo, p.telefono, p.ciudad, p.fecha,
                        p.pagado, p.enviado, p.tiene_comprobante, p.tiene_guia,
                        p.archivado, p.anulado,
                        COALESCE(SUM(pd.cantidad * pd.precio), 0) as monto
                    FROM pedidos_detal p
                    LEFT JOIN pedido_detalle pd ON p.id = pd.pedido_id
                    WHERE $where_monitor
                    GROUP BY p.id
                    ORDER BY p.fecha DESC
                    LIMIT $limite_pedidos";

    $result = $conn->query($sql_pedidos);

    if (!$result) {
        throw new Exception("Error en la consulta: " . $conn->error);
    }

    $pedidos = [];
    while ($row = $result->fetch_assoc()) {
        $pedidos[] = $row;
    }

    // === ESTADÍSTICAS usando fetch_assoc ===
    $sql_stats = "SELECT
                    COUNT(*) as total_sin_enviar,
                    SUM(CASE WHEN pagado = '0' THEN 1 ELSE 0 END) as pendientes_pago,
                    SUM(CASE WHEN pagado = '1' THEN 1 ELSE 0 END) as pagados_sin_enviar,
                    SUM(CASE WHEN tiene_comprobante = '1' THEN 1 ELSE 0 END) as con_comprobante
                  FROM pedidos_detal
                  WHERE $where_monitor";

    $result_stats = $conn->query($sql_stats);
    if ($result_stats) {
        $stats_row = $result_stats->fetch_assoc();
        $stats['total_sin_enviar'] = intval($stats_row['total_sin_enviar']);
        $stats['pendientes_pago'] = intval($stats_row['pendientes_pago']);
        $stats['pagados_sin_enviar'] = intval($stats_row['pagados_sin_enviar']);
        $stats['con_comprobante'] = intval($stats_row['con_comprobante']);
    }

    // Calcular monto total pendiente usando fetch_assoc
    $sql_monto_total = "SELECT COALESCE(SUM(pd.cantidad * pd.precio), 0) as monto_total
                        FROM pedidos_detal p
                        LEFT JOIN pedido_detalle pd ON p.id = pd.pedido_id
                        WHERE $where_monitor";

    $result_monto_total = $conn->query($sql_monto_total);
    if ($result_monto_total) {
        $monto_row = $result_monto_total->fetch_assoc();
        $stats['monto_total_pendiente'] = floatval($monto_row['monto_total']);
    }

} catch (Exception $e) {
    error_log("Error en monitor_final.php: " . $e->getMessage());
    $pedidos = array();
    $stats = array(
        'total_sin_enviar' => 0,
        'pendientes_pago' => 0,
        'pagados_sin_enviar' => 0,
        'con_comprobante' => 0,
        'monto_total_pendiente' => 0
    );
}

// Información de tiempo actual
$hora_actual = date('H:i:s');
$fecha_actual = date('d/m/Y');
$ultimo_update = "Última actualización: " . $hora_actual;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Pedidos - Sequoia Speed</title>

    <!-- Auto-refresh cada 3 segundos -->
    <meta http-equiv="refresh" content="3">

    <style>
        /* ===== ESTILOS OPTIMIZADOS PARA MONITOR DE PARED ===== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0a0e17 0%, #1a1f2e 100%);
            color: #ffffff;
            overflow-x: auto;
            min-height: 100vh;
        }

        /* === HEADER DEL MONITOR === */
        .monitor-header {
            background: rgba(26, 31, 46, 0.9);
            padding: 15px 20px;
            border-bottom: 2px solid #00d4ff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(10px);
        }

        .monitor-title {
            font-size: 24px;
            font-weight: bold;
            color: #00d4ff;
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.3);
        }

        .monitor-info {
            display: flex;
            gap: 20px;
            align-items: center;
            font-size: 14px;
            color: #a0a8b8;
        }

        .update-time {
            background: rgba(0, 212, 255, 0.1);
            padding: 5px 12px;
            border-radius: 15px;
            border: 1px solid rgba(0, 212, 255, 0.3);
            animation: pulse 3s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        /* === PANEL DE ESTADÍSTICAS === */
        .stats-panel {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            padding: 20px;
            background: rgba(26, 31, 46, 0.5);
        }

        .stat-card {
            background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
            padding: 15px;
            border-radius: 10px;
            border: 1px solid #4a5568;
            text-align: center;
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
            background: var(--accent-color, #00d4ff);
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: var(--accent-color, #00d4ff);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 12px;
            color: #a0a8b8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Colores específicos para estadísticas */
        .stat-card.total { --accent-color: #00d4ff; }
        .stat-card.pendientes { --accent-color: #ed8936; }
        .stat-card.pagados { --accent-color: #48bb78; }
        .stat-card.comprobantes { --accent-color: #9f7aea; }
        .stat-card.monto { --accent-color: #38b2ac; }

        /* === TABLA DE PEDIDOS === */
        .table-container {
            padding: 20px;
            overflow-x: auto;
        }

        .pedidos-table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(26, 31, 46, 0.8);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .pedidos-table th {
            background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
            padding: 12px 8px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #ffffff;
            border-bottom: 2px solid #2b6cb0;
        }

        .pedidos-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #4a5568;
            font-size: 13px;
            vertical-align: middle;
        }

        .pedidos-table tr:nth-child(even) {
            background: rgba(45, 55, 72, 0.3);
        }

        .pedidos-table tr:hover {
            background: rgba(66, 153, 225, 0.1);
            transform: scale(1.005);
            transition: all 0.2s ease;
        }

        /* === COLUMNAS ESPECÍFICAS === */
        .col-id {
            width: 60px;
            font-weight: bold;
            color: #00d4ff;
        }

        .col-cliente {
            min-width: 200px;
            max-width: 250px;
        }

        .col-fecha {
            width: 130px;
            font-size: 11px;
        }

        .col-monto {
            width: 100px;
            text-align: right;
            font-weight: bold;
            color: #38b2ac;
        }

        .col-estado {
            width: 120px;
            text-align: center;
        }

        /* === ESTADOS Y BADGES === */
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge.pendiente {
            background: rgba(237, 137, 54, 0.2);
            color: #ed8936;
            border: 1px solid #ed8936;
        }

        .badge.pagado {
            background: rgba(72, 187, 120, 0.2);
            color: #48bb78;
            border: 1px solid #48bb78;
        }

        .badge.comprobante {
            background: rgba(159, 122, 234, 0.2);
            color: #9f7aea;
            border: 1px solid #9f7aea;
        }

        /* === INFORMACIÓN DEL CLIENTE === */
        .cliente-info {
            line-height: 1.3;
        }

        .cliente-nombre {
            font-weight: bold;
            color: #ffffff;
            margin-bottom: 2px;
        }

        .cliente-datos {
            font-size: 11px;
            color: #a0a8b8;
        }

        .cliente-telefono {
            color: #00d4ff;
            text-decoration: none;
        }

        /* === RESPONSIVE PARA MONITOR === */
        @media (max-width: 1200px) {
            .monitor-title {
                font-size: 20px;
            }

            .stats-panel {
                grid-template-columns: repeat(3, 1fr);
            }

            .pedidos-table th,
            .pedidos-table td {
                padding: 8px 6px;
                font-size: 12px;
            }
        }

        /* === ESTADO DE CONEXIÓN === */
        .connection-status {
            position: fixed;
            top: 10px;
            right: 10px;
            background: rgba(72, 187, 120, 0.9);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 11px;
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* === NO HAY PEDIDOS === */
        .no-pedidos {
            text-align: center;
            padding: 40px;
            color: #a0a8b8;
            font-size: 16px;
        }

        .no-pedidos-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <!-- Indicador de conexión -->
    <div class="connection-status">
        🟢 CONECTADO
    </div>

    <!-- Header del Monitor -->
    <header class="monitor-header">
        <div class="monitor-title">
            🚀 SEQUOIA SPEED - MONITOR DE PEDIDOS
        </div>
        <div class="monitor-info">
            <span>📅 <?php echo $fecha_actual; ?></span>
            <span class="update-time">🔄 <?php echo $ultimo_update; ?></span>
        </div>
    </header>

    <!-- Panel de Estadísticas -->
    <section class="stats-panel">
        <div class="stat-card total">
            <div class="stat-number"><?php echo $stats['total_sin_enviar']; ?></div>
            <div class="stat-label">Sin Enviar</div>
        </div>
        <div class="stat-card pendientes">
            <div class="stat-number"><?php echo $stats['pendientes_pago']; ?></div>
            <div class="stat-label">Pendientes Pago</div>
        </div>
        <div class="stat-card pagados">
            <div class="stat-number"><?php echo $stats['pagados_sin_enviar']; ?></div>
            <div class="stat-label">Pagados Sin Enviar</div>
        </div>
        <div class="stat-card comprobantes">
            <div class="stat-number"><?php echo $stats['con_comprobante']; ?></div>
            <div class="stat-label">Con Comprobante</div>
        </div>
        <div class="stat-card monto">
            <div class="stat-number">$<?php echo number_format($stats['monto_total_pendiente'], 0, ',', '.'); ?></div>
            <div class="stat-label">Monto Pendiente</div>
        </div>
    </section>

    <!-- Tabla de Pedidos -->
    <section class="table-container">
        <?php if (empty($pedidos)): ?>
            <div class="no-pedidos">
                <div class="no-pedidos-icon">📋</div>
                <div>No hay pedidos sin enviar en este momento</div>
            </div>
        <?php else: ?>
            <table class="pedidos-table">
                <thead>
                    <tr>
                        <th class="col-id">ID</th>
                        <th class="col-cliente">Cliente</th>
                        <th class="col-fecha">Fecha</th>
                        <th class="col-monto">Monto</th>
                        <th class="col-estado">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedidos as $pedido): ?>
                        <tr>
                            <td class="col-id">#<?php echo $pedido['id']; ?></td>
                            <td class="col-cliente">
                                <div class="cliente-info">
                                    <div class="cliente-nombre"><?php echo htmlspecialchars($pedido['nombre']); ?></div>
                                    <div class="cliente-datos">
                                        📧 <?php echo htmlspecialchars($pedido['correo']); ?><br>
                                        📱 <a href="tel:<?php echo htmlspecialchars($pedido['telefono']); ?>" class="cliente-telefono"><?php echo htmlspecialchars($pedido['telefono']); ?></a><br>
                                        📍 <?php echo htmlspecialchars($pedido['ciudad']); ?>
                                    </div>
                                </div>
                            </td>
                            <td class="col-fecha">
                                <?php
                                $fecha_formateada = date('d/m/Y H:i', strtotime($pedido['fecha']));
                                echo $fecha_formateada;
                                ?>
                            </td>
                            <td class="col-monto">
                                $<?php echo number_format($pedido['monto'], 0, ',', '.'); ?>
                            </td>                            <td class="col-estado">
                                <?php if ($pedido['pagado'] == '1'): ?>
                                    <span class="badge pagado">✅ Pagado</span>
                                <?php else: ?>
                                    <span class="badge pendiente">⏳ Pendiente</span>
                                <?php endif; ?>

                                <?php if ($pedido['tiene_comprobante'] == '1'): ?>
                                    <br><span class="badge comprobante">📄 Comprobante</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <!-- Debug Info (solo visible en desarrollo) -->
    <?php if (isset($_GET['debug'])): ?>
        <div style="position: fixed; bottom: 10px; left: 10px; background: rgba(0,0,0,0.8); color: #00ff00; padding: 10px; border-radius: 5px; font-family: monospace; font-size: 11px; max-width: 300px;">
            <strong>DEBUG INFO:</strong><br>
            Pedidos encontrados: <?php echo count($pedidos); ?><br>
            Último update: <?php echo date('H:i:s'); ?><br>
            Memoria: <?php echo round(memory_get_usage()/1024/1024, 2); ?>MB
        </div>
    <?php endif; ?>

</body>
</html>
