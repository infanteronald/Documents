<?php
/**
 * MONITOR DE PEDIDOS - Versión simple sin enviar
 * Solo muestra pedidos SIN ENVIAR sin importar la fecha
 * Actualización automática cada 3 segundos
 */

// Debugging activado
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Headers para evitar cache
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Incluir conexión a la base de datos
require_once 'conexion.php';

$pedidos = array();
$stats = array(
    'total_sin_enviar' => 0,
    'pendientes_pago' => 0,
    'pagados_sin_enviar' => 0,
    'monto_total' => 0
);

try {
    // Consulta SIMPLE: Solo pedidos sin enviar y no anulados
    $sql = "SELECT
                id, nombre, correo, telefono, ciudad, fecha,
                pagado, enviado, comprobante_subido, guia_subida,
                archivado, anulado
            FROM pedidos_detal
            WHERE enviado = 0 AND anulado = 0
            ORDER BY fecha DESC
            LIMIT 20";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Error preparando consulta: " . $conn->error);
    }

    $stmt->execute();

    // Variables para bind_result
    $id = $nombre = $correo = $telefono = $ciudad = $fecha = '';
    $pagado = $enviado = $comprobante_subido = $guia_subida = $archivado = $anulado = 0;

    $stmt->bind_result($id, $nombre, $correo, $telefono, $ciudad, $fecha,
                      $pagado, $enviado, $comprobante_subido, $guia_subida,
                      $archivado, $anulado);

    while ($stmt->fetch()) {
        $pedidos[] = array(
            'id' => $id,
            'nombre' => $nombre,
            'correo' => $correo,
            'telefono' => $telefono,
            'ciudad' => $ciudad,
            'fecha' => $fecha,
            'pagado' => $pagado,
            'enviado' => $enviado,
            'comprobante_subido' => $comprobante_subido,
            'guia_subida' => $guia_subida,
            'archivado' => $archivado,
            'anulado' => $anulado,
            'monto' => 0
        );
    }
    $stmt->close();

    // Calcular montos individualmente (método más confiable)
    foreach ($pedidos as $index => $pedido) {
        $sql_monto = "SELECT COALESCE(SUM(precio * cantidad), 0) as monto
                      FROM pedido_detalle
                      WHERE pedido_id = ?";
        $stmt_monto = $conn->prepare($sql_monto);
        if ($stmt_monto) {
            $stmt_monto->bind_param("i", $pedido['id']);
            $stmt_monto->execute();
            $monto = 0;
            $stmt_monto->bind_result($monto);
            if ($stmt_monto->fetch()) {
                $pedidos[$index]['monto'] = floatval($monto);
                $stats['monto_total'] += floatval($monto);
            }
            $stmt_monto->close();
        }
    }

    // Calcular estadísticas
    $stats['total_sin_enviar'] = count($pedidos);
    foreach ($pedidos as $p) {
        if ($p['pagado'] == 0) {
            $stats['pendientes_pago']++;
        } else {
            $stats['pagados_sin_enviar']++;
        }
    }

} catch (Exception $e) {
    error_log("Error en monitor: " . $e->getMessage());
    $pedidos = array();
}

$hora_actual = date('H:i:s');
$fecha_actual = date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor - Pedidos Sin Enviar</title>

    <!-- Auto-refresh cada 3 segundos -->
    <meta http-equiv="refresh" content="3">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI', Roboto, Arial, sans-serif;
            background: #0a0e17;
            color: #ffffff;
            line-height: 1.4;
        }

        .monitor-header {
            background: linear-gradient(135deg, #1a1f2e, #0f1419);
            padding: 20px 30px;
            border-bottom: 2px solid #00d4ff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0, 212, 255, 0.1);
        }

        .monitor-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #00d4ff;
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.5);
        }

        .monitor-info {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 5px;
        }

        .monitor-time {
            font-size: 2rem;
            font-weight: 600;
            font-family: 'SF Mono', 'Monaco', 'Consolas', monospace;
        }

        .monitor-date {
            font-size: 1.2rem;
            color: #a0a8b8;
        }

        .auto-refresh {
            font-size: 0.9rem;
            color: #00d4ff;
            opacity: 0.8;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .stats-panel {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            padding: 20px 30px;
            background: #1a1f2e;
        }

        .stat-card {
            background: linear-gradient(145deg, #1e2a3a, #2d3748);
            border: 1px solid #2d3748;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 1rem;
            color: #a0a8b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-total { color: #ff6b6b; }
        .stat-pendientes { color: #feca57; }
        .stat-pagados { color: #48bb78; }
        .stat-monto { color: #00d4ff; }

        .monitor-table-container {
            padding: 0 30px 30px 30px;
        }

        .monitor-table {
            width: 100%;
            background: #1a1f2e;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.4);
        }

        .monitor-table thead {
            background: linear-gradient(135deg, #2d3748, #4a5568);
        }

        .monitor-table th {
            padding: 15px 12px;
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #00d4ff;
        }

        .monitor-table td {
            padding: 12px;
            border-bottom: 1px solid #2d3748;
            font-size: 0.95rem;
        }

        .monitor-table tr:hover {
            background: rgba(0, 212, 255, 0.05);
        }

        .col-id {
            width: 70px;
            text-align: center;
            font-weight: 600;
            color: #00d4ff;
        }

        .col-fecha {
            width: 90px;
            text-align: center;
            font-family: 'SF Mono', monospace;
            font-size: 0.85rem;
        }

        .col-cliente {
            width: 250px;
        }

        .col-monto {
            width: 110px;
            text-align: right;
            font-weight: 600;
            color: #48bb78;
        }

        .col-status {
            width: 60px;
            text-align: center;
        }

        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin: 0 4px;
        }

        .status-si { background: #48bb78; }
        .status-no { background: #ff6b6b; }

        .cliente-info {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .cliente-nombre {
            font-weight: 600;
        }

        .cliente-contacto {
            font-size: 0.8rem;
            color: #a0a8b8;
        }

        .fecha-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
            font-size: 0.8rem;
        }

        .fecha-dia {
            font-weight: 600;
        }

        .fecha-hora {
            color: #a0a8b8;
        }

        .mensaje-vacio {
            text-align: center;
            padding: 60px 20px;
            color: #a0a8b8;
        }

        .mensaje-vacio-icono {
            font-size: 4rem;
            margin-bottom: 20px;
        }

        .mensaje-vacio-texto {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .mensaje-vacio-sub {
            font-size: 1rem;
            opacity: 0.7;
        }
    </style>
</head>
<body>
    <!-- Header del Monitor -->
    <div class="monitor-header">
        <div class="monitor-title">
            🚚 Monitor - Pedidos Sin Enviar
        </div>
        <div class="monitor-info">
            <div class="monitor-time"><?php echo $hora_actual; ?></div>
            <div class="monitor-date"><?php echo $fecha_actual; ?></div>
            <div class="auto-refresh">● Auto-refresh: 3s</div>
        </div>
    </div>

    <!-- Panel de Estadísticas -->
    <div class="stats-panel">
        <div class="stat-card">
            <div class="stat-number stat-total"><?php echo $stats['total_sin_enviar']; ?></div>
            <div class="stat-label">Sin Enviar</div>
        </div>
        <div class="stat-card">
            <div class="stat-number stat-pendientes"><?php echo $stats['pendientes_pago']; ?></div>
            <div class="stat-label">Pendientes Pago</div>
        </div>
        <div class="stat-card">
            <div class="stat-number stat-pagados"><?php echo $stats['pagados_sin_enviar']; ?></div>
            <div class="stat-label">Pagados Sin Enviar</div>
        </div>
        <div class="stat-card">
            <div class="stat-number stat-monto">$<?php echo number_format($stats['monto_total'], 0, ',', '.'); ?></div>
            <div class="stat-label">Monto Total</div>
        </div>
    </div>

    <!-- Tabla de Pedidos -->
    <div class="monitor-table-container">
        <table class="monitor-table">
            <thead>
                <tr>
                    <th class="col-id">ID</th>
                    <th class="col-fecha">Fecha</th>
                    <th class="col-cliente">Cliente</th>
                    <th class="col-monto">Monto</th>
                    <th class="col-status">💳</th>
                    <th class="col-status">📄</th>
                    <th class="col-status">📦</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($pedidos) == 0): ?>
                    <tr>
                        <td colspan="7" class="mensaje-vacio">
                            <div class="mensaje-vacio-icono">🎉</div>
                            <div class="mensaje-vacio-texto">¡Excelente!</div>
                            <div class="mensaje-vacio-sub">Todos los pedidos han sido enviados</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach($pedidos as $p): ?>
                        <tr>
                            <!-- ID -->
                            <td class="col-id">#<?php echo $p['id']; ?></td>

                            <!-- Fecha -->
                            <td class="col-fecha">
                                <div class="fecha-info">
                                    <div class="fecha-dia"><?php echo date('d/m/Y', strtotime($p['fecha'])); ?></div>
                                    <div class="fecha-hora"><?php echo date('H:i', strtotime($p['fecha'])); ?></div>
                                </div>
                            </td>

                            <!-- Cliente -->
                            <td class="col-cliente">
                                <div class="cliente-info">
                                    <div class="cliente-nombre"><?php echo htmlspecialchars($p['nombre']); ?></div>
                                    <div class="cliente-contacto"><?php echo htmlspecialchars($p['correo']); ?></div>
                                    <div class="cliente-contacto"><?php echo htmlspecialchars($p['telefono']) . ' - ' . htmlspecialchars($p['ciudad']); ?></div>
                                </div>
                            </td>

                            <!-- Monto -->
                            <td class="col-monto">$<?php echo number_format($p['monto'], 0, ',', '.'); ?></td>

                            <!-- Estados -->
                            <td class="col-status">
                                <span class="status-indicator <?php echo $p['pagado'] ? 'status-si' : 'status-no'; ?>"></span>
                            </td>
                            <td class="col-status">
                                <span class="status-indicator <?php echo $p['comprobante_subido'] ? 'status-si' : 'status-no'; ?>"></span>
                            </td>
                            <td class="col-status">
                                <span class="status-indicator <?php echo $p['guia_subida'] ? 'status-si' : 'status-no'; ?>"></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Debug info (comentado para producción) -->
    <!--
    DEBUG: <?php echo count($pedidos); ?> pedidos encontrados
    Consulta: SELECT * FROM pedidos_detal WHERE enviado = 0 AND anulado = 0
    -->
</body>
</html>
