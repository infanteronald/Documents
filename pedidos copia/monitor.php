<?php
/**
 * MONITOR OPERATIVO v3.0 - ESTILO VS CODE DARK
 * Diseño minimalista para pantallas de pared
 * - Solo colores críticos (rojo/amarillo)
 * - Filas compactas para ver más pedidos
 * - Tiempo en días en lugar de minutos
 * - Todos los pedidos no enviados sin límite de tiempo
 */

// Requerir autenticación
require_once 'accesos/auth_helper.php';

// Proteger la página - requiere permisos de lectura en ventas
$current_user = auth_require('ventas', 'leer');

// Headers anti-cache
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once 'config_secure.php';
require_once 'php82_helpers.php';

// Configuración simplificada
$limite_pedidos = 25; // Más pedidos con filas compactas
$dias_critico = 1; // 1 día para considerar pedido crítico (más sensible)
$dias_urgente = 0; // Pedidos de hoy mismo son urgentes

$pedidos = array();
$stats = array(
    'total_sin_enviar' => 0,
    'criticos' => 0,
    'urgentes' => 0,
    'pagados_listos' => 0
);

try {
    // === USANDO LA MISMA LÓGICA EXACTA QUE LISTAR_PEDIDOS.PHP ===
    $where = "enviado = '0' AND anulado = '0' AND archivado = '0'";

    // Consulta simplificada primero para verificar
    $sql_pedidos = "SELECT
                        p.id, p.nombre, p.telefono, p.ciudad, p.fecha,
                        p.pagado, p.enviado, p.tiene_comprobante, p.tiene_guia,
                        p.archivado, p.anulado,
                        COALESCE(SUM(pd.cantidad * pd.precio), 0) as monto
                    FROM pedidos_detal p
                    LEFT JOIN pedido_detalle pd ON p.id = pd.pedido_id
                    WHERE $where
                    GROUP BY p.id
                    ORDER BY p.fecha DESC
                    LIMIT $limite_pedidos";

    $result = $conn->query($sql_pedidos);

    if (!$result) {
        throw new Exception("Error en la consulta: " . $conn->error);
    }

    while ($row = $result->fetch_assoc()) {
        // Calcular días de espera en PHP
        $fecha_pedido = new DateTime($row['fecha']);
        $fecha_actual = new DateTime();
        $dias_espera = $fecha_actual->diff($fecha_pedido)->days;

        // Determinar prioridad (lógica más agresiva para testing)
        if ($dias_espera >= $dias_critico) {
            $prioridad = 'CRITICO';
        } elseif ($dias_espera >= $dias_urgente) {
            $prioridad = 'URGENTE';
        } else {
            $prioridad = 'NORMAL';        }

        // Determinar estado operativo - Solo PENDIENTE para monitor
        $estado_operativo = 'PENDIENTE';

        // Agregar datos calculados
        $row['dias_espera'] = $dias_espera;
        $row['prioridad'] = $prioridad;
        $row['estado_operativo'] = $estado_operativo;

        $pedidos[] = $row;

        $stats['total_sin_enviar']++;

        switch($prioridad) {
            case 'CRITICO':
                $stats['criticos']++;
                break;
            case 'URGENTE':
                $stats['urgentes']++;
                break;
        }

        // Ya no necesitamos contar "listos para envío" ya que todos son PENDIENTE
    }

    // Ordenar pedidos por prioridad
    usort($pedidos, function($a, $b) {
        $prioridad_orden = ['CRITICO' => 1, 'URGENTE' => 2, 'NORMAL' => 3];
        $orden_a = $prioridad_orden[$a['prioridad']] ?? 3;
        $orden_b = $prioridad_orden[$b['prioridad']] ?? 3;

        if ($orden_a == $orden_b) {
            // Si tienen la misma prioridad, ordenar por fecha (más antiguos primero)
            return strtotime($a['fecha']) - strtotime($b['fecha']);
        }

        return $orden_a - $orden_b;
    });

} catch (Exception $e) {
    error_log("Error en monitor operativo: " . $e->getMessage());
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
    <title>Monitor Operativo - Sequoia Speed</title>

    <!-- Auto-refresh cada 5 segundos -->
    <meta http-equiv="refresh" content="5">

    <style>
        /* ===== ESTILO VS CODE DARK - MINIMALISTA ===== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
            background-color: #1e1e1e;
            color: #d4d4d4;
            overflow: hidden;
            height: 100vh;
        }

        /* === HEADER MINIMALISTA === */
        .header-vscode {
            background-color: #2d2d30;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #3e3e42;
        }

        .titulo-vscode {
            font-size: 18px;
            font-weight: 600;
            color: #cccccc;
            font-family: 'Segoe UI', sans-serif;
        }

        .info-tiempo-vscode {
            font-size: 14px;
            color: #858585;
        }

        .hora-vscode {
            font-size: 16px;
            font-weight: bold;
            color: #d4d4d4;
            margin-left: 15px;
        }

        /* === PANEL DE ESTADÍSTICAS SIMPLE === */
        .stats-vscode {
            display: flex;
            padding: 12px 20px;
            background-color: #252526;
            border-bottom: 1px solid #3e3e42;
            gap: 30px;
        }

        .stat-vscode {
            display: flex;
            align-items: center;
            font-size: 14px;
        }

        .stat-numero-vscode {
            font-weight: bold;
            font-size: 16px;
            margin-right: 8px;
        }

        .stat-label-vscode {
            color: #858585;
        }

        /* Solo dos colores: rojo para crítico, amarillo para urgente */
        .stat-total .stat-numero-vscode { color: #d4d4d4; }
        .stat-critico .stat-numero-vscode { color: #f14c4c; }
        .stat-urgente .stat-numero-vscode { color: #ffcc02; }

        /* === TABLA ESTILO VS CODE === */
        .container-tabla-vscode {
            padding: 15px 20px;
            height: calc(100vh - 120px);
            overflow-y: auto;
        }

        .tabla-vscode {
            width: 100%;
            border-collapse: collapse;
            background-color: #1e1e1e;
            font-size: 13px;
        }

        .tabla-vscode th {
            background-color: #2d2d30;
            padding: 10px 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: #cccccc;
            text-align: left;
            border-bottom: 1px solid #3e3e42;
            position: sticky;
            top: 0;
        }

        .tabla-vscode td {
            padding: 8px 12px;
            border-bottom: 1px solid #2d2d30;
            vertical-align: middle;
        }

        .tabla-vscode tr:hover {
            background-color: #2a2d2e;
        }

        /* === FILAS POR PRIORIDAD (SOLO ROJO/AMARILLO) === */
        .fila-critica {
            background-color: rgba(241, 76, 76, 0.2) !important;
            border-left: 4px solid #f14c4c !important;
        }

        .fila-urgente {
            background-color: rgba(255, 204, 2, 0.2) !important;
            border-left: 4px solid #ffcc02 !important;
        }

        .fila-normal {
            background-color: transparent;
            border-left: 3px solid transparent;
        }

        /* === COLUMNAS COMPACTAS === */
        .col-id {
            width: 60px;
            font-weight: bold;
            color: #569cd6;
        }

        .col-cliente {
            width: 180px;
            color: #d4d4d4;
        }

        .col-contacto {
            width: 140px;
            font-size: 11px;
            color: #858585;
        }

        .col-tiempo {
            width: 70px;
            text-align: center;
            font-weight: bold;
        }

        .col-monto {
            width: 90px;
            text-align: right;
            color: #4ec9b0;
            font-weight: bold;
        }

        .col-estado {
            width: 100px;
            text-align: center;
        }

        /* === BADGES MINIMALISTAS === */
        .badge-vscode {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-pendiente {
            background-color: rgba(241, 76, 76, 0.2);
            color: #f14c4c;
            border: 1px solid #f14c4c;
        }

        /* === INDICADORES DE TIEMPO === */
        .tiempo-critico {
            color: #f14c4c !important;
            font-weight: bold;
            text-shadow: 0 0 5px rgba(241, 76, 76, 0.5);
        }

        .tiempo-urgente {
            color: #ffcc02 !important;
            font-weight: bold;
            text-shadow: 0 0 5px rgba(255, 204, 2, 0.5);
        }

        .tiempo-normal {
            color: #858585;
        }

        /* === SIN PEDIDOS === */
        .sin-pedidos-vscode {
            text-align: center;
            padding: 60px 20px;
            color: #858585;
            font-size: 16px;
        }

        .sin-pedidos-icono-vscode {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.6;
        }

        /* === SCROLLBAR ESTILO VS CODE === */
        .container-tabla-vscode::-webkit-scrollbar {
            width: 8px;
        }

        .container-tabla-vscode::-webkit-scrollbar-track {
            background: #1e1e1e;
        }

        .container-tabla-vscode::-webkit-scrollbar-thumb {
            background: #424242;
            border-radius: 4px;
        }

        .container-tabla-vscode::-webkit-scrollbar-thumb:hover {
            background: #4e4e4e;
        }

        /* === RESPONSIVE === */
        @media (max-width: 1400px) {
            .tabla-vscode { font-size: 12px; }
            .tabla-vscode th, .tabla-vscode td { padding: 6px 10px; }
        }
    </style>
</head>
<body>
    <!-- Header VS Code Style -->
    <header class="header-vscode">
        <div class="titulo-vscode">
            SEQUOIA SPEED - Monitor de Pedidos
        </div>
        <div class="info-tiempo-vscode">
            <?php echo $fecha_actual; ?>
            <span class="hora-vscode"><?php echo $hora_actual; ?></span>
        </div>
    </header>

    <!-- Panel de Estadísticas Minimalista -->
    <section class="stats-vscode">
        <div class="stat-vscode stat-total">
            <span class="stat-numero-vscode"><?php echo $stats['total_sin_enviar']; ?></span>
            <span class="stat-label-vscode">Sin Enviar</span>
        </div>
        <div class="stat-vscode stat-critico">
            <span class="stat-numero-vscode"><?php echo $stats['criticos']; ?></span>
            <span class="stat-label-vscode">Críticos (+1 día)</span>
        </div>
        <div class="stat-vscode stat-urgente">
            <span class="stat-numero-vscode"><?php echo $stats['urgentes']; ?></span>
            <span class="stat-label-vscode">Urgentes (hoy)</span>
        </div>
    </section>

    <!-- Tabla de Pedidos -->
    <section class="container-tabla-vscode">
        <?php if (empty($pedidos)): ?>
            <div class="sin-pedidos-vscode">
                <div class="sin-pedidos-icono-vscode">✅</div>
                <div>No hay pedidos sin enviar</div>
            </div>
        <?php else: ?>
            <table class="tabla-vscode">
                <thead>
                    <tr>
                        <th class="col-id">ID</th>
                        <th class="col-cliente">Cliente</th>
                        <th class="col-contacto">Contacto</th>
                        <th class="col-tiempo">Días</th>
                        <th class="col-monto">Monto</th>
                        <th class="col-estado">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedidos as $pedido): ?>
                        <tr class="fila-<?php echo strtolower($pedido['prioridad']); ?>">
                            <td class="col-id">
                                #<?php echo $pedido['id']; ?>
                            </td>
                            <td class="col-cliente">
                                <?php echo h($pedido['nombre']); ?>
                            </td>
                            <td class="col-contacto">
                                📱 <?php echo h($pedido['telefono']); ?><br>
                                📍 <?php echo h($pedido['ciudad']); ?>
                            </td>
                            <td class="col-tiempo">
                                <span class="tiempo-<?php echo strtolower($pedido['prioridad']); ?>">
                                    <?php
                                    $dias = $pedido['dias_espera'];
                                    if ($dias == 0) {
                                        echo 'HOY';
                                    } else {
                                        echo $dias . 'd';
                                    }
                                    ?>
                                </span>
                            </td>
                            <td class="col-monto">
                                $<?php echo number_format($pedido['monto'], 0, ',', '.'); ?>
                            </td>
                            <td class="col-estado">
                                <span class="badge-vscode badge-pendiente">
                                    PENDIENTE
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

</body>
</html>
