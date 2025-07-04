<?php
/**
 * MONITOR OPERATIVO DE PEDIDOS v2.0
 * Diseñado específicamente para pantallas de pared en entornos de producción
 * Basado en mejores prácticas de sistemas como McDonald's, KFC, etc.
 *
 * CARACTERÍSTICAS AVANZADAS:
 * - Priorización visual por tiempo de espera
 * - Estados operativos claros
 * - Diseño optimizado para visualización a distancia
 * - Información relevante para operaciones
 * - Alertas visuales y sonoras
 */

ini_set('display_errors', 0); // En producción, no mostrar errores
error_reporting(E_ALL);

// Headers anti-cache
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once 'conexion.php';

// Configuración del monitor operativo
$limite_pedidos = 15; // Menos pedidos pero más grandes
$tiempo_critico = 30; // minutos para considerar pedido crítico
$tiempo_urgente = 20; // minutos para considerar pedido urgente

$pedidos = array();
$stats = array(
    'total_activos' => 0,
    'criticos' => 0,
    'urgentes' => 0,
    'normales' => 0,
    'pagados_listos' => 0
);

try {
    // Consulta optimizada con cálculo de tiempo de espera
    $sql_pedidos = "SELECT
                        p.id,
                        p.nombre,
                        p.telefono,
                        p.ciudad,
                        p.fecha,
                        p.pagado,
                        p.enviado,
                        p.tiene_comprobante,
                        p.anulado,
                        COALESCE(SUM(pd.cantidad * pd.precio), 0) as monto,
                        TIMESTAMPDIFF(MINUTE, p.fecha, NOW()) as minutos_espera,
                        CASE
                            WHEN TIMESTAMPDIFF(MINUTE, p.fecha, NOW()) >= $tiempo_critico THEN 'CRITICO'
                            WHEN TIMESTAMPDIFF(MINUTE, p.fecha, NOW()) >= $tiempo_urgente THEN 'URGENTE'
                            ELSE 'NORMAL'
                        END as prioridad,
                        CASE
                            WHEN p.pagado = '1' AND p.tiene_comprobante = '1' THEN 'LISTO_ENVIO'
                            WHEN p.pagado = '1' THEN 'PAGO_CONFIRMADO'
                            WHEN p.tiene_comprobante = '1' THEN 'VERIFICAR_PAGO'
                            ELSE 'PENDIENTE_PAGO'
                        END as estado_operativo
                    FROM pedidos_detal p
                    LEFT JOIN pedido_detalle pd ON p.id = pd.pedido_id
                    WHERE p.enviado = '0' AND p.anulado = '0'
                    GROUP BY p.id
                    ORDER BY
                        CASE
                            WHEN TIMESTAMPDIFF(MINUTE, p.fecha, NOW()) >= $tiempo_critico THEN 1
                            WHEN TIMESTAMPDIFF(MINUTE, p.fecha, NOW()) >= $tiempo_urgente THEN 2
                            ELSE 3
                        END ASC,
                        p.fecha ASC
                    LIMIT $limite_pedidos";

    $result = $conn->query($sql_pedidos);

    if (!$result) {
        throw new Exception("Error en la consulta: " . $conn->error);
    }

    while ($row = $result->fetch_assoc()) {
        $pedidos[] = $row;

        // Calcular estadísticas
        $stats['total_activos']++;

        switch($row['prioridad']) {
            case 'CRITICO':
                $stats['criticos']++;
                break;
            case 'URGENTE':
                $stats['urgentes']++;
                break;
            default:
                $stats['normales']++;
        }

        if ($row['estado_operativo'] == 'LISTO_ENVIO') {
            $stats['pagados_listos']++;
        }
    }

} catch (Exception $e) {
    error_log("Error en monitor operativo: " . $e->getMessage());
    $pedidos = array();
}

// Información de tiempo
$hora_actual = date('H:i:s');
$fecha_actual = date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Operativo - Sequoia Speed</title>

    <!-- Auto-refresh cada 5 segundos (más lento para dar tiempo a leer) -->
    <meta http-equiv="refresh" content="5">

    <style>
        /* ===== DISEÑO OPERATIVO PARA PANTALLA DE PARED ===== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial Black', Arial, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #ffffff;
            overflow: hidden;
            height: 100vh;
        }

        /* === HEADER OPERATIVO === */
        .header-operativo {
            background: linear-gradient(90deg, #2c3e50 0%, #34495e 100%);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 4px solid #e74c3c;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }

        .titulo-operativo {
            font-size: 32px;
            font-weight: 900;
            color: #ecf0f1;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }

        .info-tiempo {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            font-size: 18px;
            color: #bdc3c7;
        }

        .hora-grande {
            font-size: 28px;
            font-weight: bold;
            color: #e74c3c;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }

        /* === PANEL DE ESTADÍSTICAS OPERATIVAS === */
        .stats-operativo {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            padding: 20px 30px;
            background: rgba(52, 73, 94, 0.3);
        }

        .stat-operativo {
            background: linear-gradient(135deg, var(--bg-color, #34495e) 0%, var(--bg-color-dark, #2c3e50) 100%);
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            border: 3px solid var(--border-color, #34495e);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            position: relative;
            overflow: hidden;
        }

        .stat-operativo::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--accent-color, #3498db);
        }

        .stat-numero {
            font-size: 36px;
            font-weight: 900;
            color: var(--accent-color, #3498db);
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
            margin-bottom: 8px;
        }

        .stat-etiqueta {
            font-size: 14px;
            color: #ecf0f1;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: bold;
        }

        /* Colores específicos por tipo de estadística */
        .stat-total { --accent-color: #3498db; --bg-color: #2980b9; --bg-color-dark: #1f618d; --border-color: #3498db; }
        .stat-critico { --accent-color: #e74c3c; --bg-color: #c0392b; --bg-color-dark: #a93226; --border-color: #e74c3c; }
        .stat-urgente { --accent-color: #f39c12; --bg-color: #d68910; --bg-color-dark: #b7950b; --border-color: #f39c12; }
        .stat-normal { --accent-color: #27ae60; --bg-color: #229954; --bg-color-dark: #1e8449; --border-color: #27ae60; }
        .stat-listo { --accent-color: #9b59b6; --bg-color: #8e44ad; --bg-color-dark: #7d3c98; --border-color: #9b59b6; }

        /* Animación para estadísticas críticas */
        .stat-critico .stat-numero {
            animation: pulse-critico 2s infinite;
        }

        @keyframes pulse-critico {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        /* === TABLA OPERATIVA === */
        .container-tabla {
            padding: 20px 30px;
            height: calc(100vh - 220px);
            overflow: hidden;
        }

        .tabla-operativa {
            width: 100%;
            border-collapse: collapse;
            background: rgba(44, 62, 80, 0.9);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
        }

        .tabla-operativa th {
            background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
            padding: 20px 15px;
            font-size: 16px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #ecf0f1;
            text-align: center;
            border-bottom: 3px solid #e74c3c;
        }

        .tabla-operativa td {
            padding: 18px 15px;
            border-bottom: 2px solid #34495e;
            font-size: 16px;
            vertical-align: middle;
            text-align: center;
        }

        /* === FILAS POR PRIORIDAD === */
        .fila-critica {
            background: linear-gradient(90deg, rgba(231, 76, 60, 0.3) 0%, rgba(192, 57, 43, 0.3) 100%);
            border-left: 8px solid #e74c3c;
            animation: pulse-fila 3s infinite;
        }

        .fila-urgente {
            background: linear-gradient(90deg, rgba(243, 156, 18, 0.3) 0%, rgba(214, 137, 16, 0.3) 100%);
            border-left: 8px solid #f39c12;
        }

        .fila-normal {
            background: linear-gradient(90deg, rgba(39, 174, 96, 0.2) 0%, rgba(34, 153, 84, 0.2) 100%);
            border-left: 8px solid #27ae60;
        }

        @keyframes pulse-fila {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        /* === COLUMNAS OPERATIVAS === */
        .col-id-operativo {
            width: 80px;
            font-size: 24px;
            font-weight: 900;
            color: #3498db;
        }

        .col-cliente-operativo {
            width: 200px;
            text-align: left;
        }

        .col-tiempo-operativo {
            width: 150px;
            font-weight: bold;
        }

        .col-monto-operativo {
            width: 120px;
            font-size: 18px;
            font-weight: bold;
            color: #27ae60;
        }

        .col-estado-operativo {
            width: 180px;
        }

        .col-accion-operativo {
            width: 120px;
        }

        /* === BADGES OPERATIVOS === */
        .badge-operativo {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: 2px solid;
            text-align: center;
            min-width: 120px;
        }

        .badge-listo-envio {
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
            color: #ffffff;
            border-color: #9b59b6;
            box-shadow: 0 4px 15px rgba(155, 89, 182, 0.4);
        }

        .badge-pago-confirmado {
            background: linear-gradient(135deg, #27ae60, #229954);
            color: #ffffff;
            border-color: #27ae60;
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.4);
        }

        .badge-verificar-pago {
            background: linear-gradient(135deg, #f39c12, #d68910);
            color: #ffffff;
            border-color: #f39c12;
            box-shadow: 0 4px 15px rgba(243, 156, 18, 0.4);
        }

        .badge-pendiente-pago {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: #ffffff;
            border-color: #e74c3c;
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.4);
        }

        /* === INDICADORES DE TIEMPO === */
        .tiempo-critico {
            color: #e74c3c;
            font-weight: 900;
            font-size: 18px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }

        .tiempo-urgente {
            color: #f39c12;
            font-weight: bold;
            font-size: 16px;
        }

        .tiempo-normal {
            color: #27ae60;
            font-weight: normal;
        }

        /* === INFORMACIÓN DEL CLIENTE SIMPLIFICADA === */
        .cliente-operativo {
            text-align: left;
        }

        .cliente-nombre-operativo {
            font-size: 16px;
            font-weight: bold;
            color: #ecf0f1;
            margin-bottom: 4px;
        }

        .cliente-contacto-operativo {
            font-size: 13px;
            color: #bdc3c7;
        }

        /* === SIN PEDIDOS === */
        .sin-pedidos-operativo {
            text-align: center;
            padding: 80px;
            color: #bdc3c7;
            font-size: 24px;
        }

        .sin-pedidos-icono {
            font-size: 72px;
            margin-bottom: 20px;
            opacity: 0.6;
        }

        /* === RESPONSIVE PARA DIFERENTES TAMAÑOS DE PANTALLA === */
        @media (max-width: 1600px) {
            .titulo-operativo { font-size: 28px; }
            .stats-operativo { grid-template-columns: repeat(5, 1fr); gap: 15px; }
            .stat-numero { font-size: 32px; }
        }

        @media (max-width: 1200px) {
            .titulo-operativo { font-size: 24px; }
            .stats-operativo { grid-template-columns: repeat(3, 1fr); }
            .tabla-operativa th, .tabla-operativa td { padding: 15px 10px; font-size: 14px; }
        }
    </style>
</head>
<body>
    <!-- Header Operativo -->
    <header class="header-operativo">
        <div class="titulo-operativo">
            🚀 SEQUOIA SPEED - CENTRO DE OPERACIONES
        </div>
        <div class="info-tiempo">
            <div><?php echo $fecha_actual; ?></div>
            <div class="hora-grande"><?php echo $hora_actual; ?></div>
        </div>
    </header>

    <!-- Panel de Estadísticas Operativas -->
    <section class="stats-operativo">
        <div class="stat-operativo stat-total">
            <div class="stat-numero"><?php echo $stats['total_activos']; ?></div>
            <div class="stat-etiqueta">Total Activos</div>
        </div>
        <div class="stat-operativo stat-critico">
            <div class="stat-numero"><?php echo $stats['criticos']; ?></div>
            <div class="stat-etiqueta">Críticos</div>
        </div>
        <div class="stat-operativo stat-urgente">
            <div class="stat-numero"><?php echo $stats['urgentes']; ?></div>
            <div class="stat-etiqueta">Urgentes</div>
        </div>
        <div class="stat-operativo stat-normal">
            <div class="stat-numero"><?php echo $stats['normales']; ?></div>
            <div class="stat-etiqueta">Normales</div>
        </div>
        <div class="stat-operativo stat-listo">
            <div class="stat-numero"><?php echo $stats['pagados_listos']; ?></div>
            <div class="stat-etiqueta">Listos Envío</div>
        </div>
    </section>

    <!-- Tabla Operativa -->
    <section class="container-tabla">
        <?php if (empty($pedidos)): ?>
            <div class="sin-pedidos-operativo">
                <div class="sin-pedidos-icono">✅</div>
                <div>¡EXCELENTE! No hay pedidos pendientes</div>
            </div>
        <?php else: ?>
            <table class="tabla-operativa">
                <thead>
                    <tr>
                        <th class="col-id-operativo">ID</th>
                        <th class="col-cliente-operativo">Cliente</th>
                        <th class="col-tiempo-operativo">Tiempo Espera</th>
                        <th class="col-monto-operativo">Monto</th>
                        <th class="col-estado-operativo">Estado Operativo</th>
                        <th class="col-accion-operativo">Prioridad</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedidos as $pedido): ?>
                        <tr class="fila-<?php echo strtolower($pedido['prioridad']); ?>">
                            <td class="col-id-operativo">
                                #<?php echo $pedido['id']; ?>
                            </td>
                            <td class="col-cliente-operativo">
                                <div class="cliente-operativo">
                                    <div class="cliente-nombre-operativo">
                                        <?php echo htmlspecialchars($pedido['nombre']); ?>
                                    </div>
                                    <div class="cliente-contacto-operativo">
                                        📱 <?php echo htmlspecialchars($pedido['telefono']); ?> | 📍 <?php echo htmlspecialchars($pedido['ciudad']); ?>
                                    </div>
                                </div>
                            </td>
                            <td class="col-tiempo-operativo">
                                <span class="tiempo-<?php echo strtolower($pedido['prioridad']); ?>">
                                    <?php echo $pedido['minutos_espera']; ?> min
                                </span>
                            </td>
                            <td class="col-monto-operativo">
                                $<?php echo number_format($pedido['monto'], 0, ',', '.'); ?>
                            </td>
                            <td class="col-estado-operativo">
                                <span class="badge-operativo badge-<?php echo strtolower(str_replace('_', '-', $pedido['estado_operativo'])); ?>">
                                    <?php
                                    switch($pedido['estado_operativo']) {
                                        case 'LISTO_ENVIO':
                                            echo '🚀 LISTO ENVÍO';
                                            break;
                                        case 'PAGO_CONFIRMADO':
                                            echo '✅ PAGO OK';
                                            break;
                                        case 'VERIFICAR_PAGO':
                                            echo '🔍 VERIFICAR';
                                            break;
                                        case 'PENDIENTE_PAGO':
                                            echo '⏳ PENDIENTE';
                                            break;
                                    }
                                    ?>
                                </span>
                            </td>
                            <td class="col-accion-operativo">
                                <span class="badge-operativo badge-<?php echo strtolower($pedido['prioridad']); ?>">
                                    <?php echo $pedido['prioridad']; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <!-- Audio para alertas críticas -->
    <?php if ($stats['criticos'] > 0): ?>
        <audio autoplay loop>
            <source src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmMaAjSE1O/TgSYEJHnI8N+WTQU+Y7vs66NTBACi6eujYRYELIHO8+CQQA0GY7rs66NTBATBhTB8AQA=" type="audio/wav">
        </audio>
    <?php endif; ?>

</body>
</html>
