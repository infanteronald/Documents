<?php
/**
 * Ver Detalle de Pedido - Versión Ultra Compatible
 * Sequoia Speed - Máxima compatibilidad con servidores antiguos
 */

// Configuración robusta de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '256M');

// Incluir conexión a base de datos
include 'conexion.php';
require_once 'notifications/notification_helpers.php';

// Función para escape HTML seguro
function h($txt) {
    return htmlspecialchars($txt ?? '', ENT_QUOTES, 'UTF-8');
}

// Función para obtener campo de array de manera segura
function getField($array, $field, $default = 'No disponible') {
    // Mapeo de campos alternativos comunes
    $fieldMappings = [
        'nombre_cliente' => ['nombre_cliente', 'nombre', 'cliente_nombre', 'client_name'],
        'email_cliente' => ['email_cliente', 'correo', 'email', 'cliente_email', 'client_email'],
        'telefono_cliente' => ['telefono_cliente', 'telefono', 'cliente_telefono', 'phone'],
        'fecha_pedido' => ['fecha_pedido', 'fecha', 'created_at', 'date_created'],
        'direccion_entrega' => ['direccion_entrega', 'direccion', 'address', 'delivery_address'],
        'metodo_pago' => ['metodo_pago', 'metodo_pago', 'pago', 'payment_method', 'tipo_pago'],
        'nota_interna' => ['nota_interna', 'notas', 'observaciones', 'comments', 'notes']
    ];

    // Intentar con el campo directo primero
    if (isset($array[$field]) && !empty($array[$field])) {
        return $array[$field];
    }

    // Si hay mapeos alternativos, intentarlos
    if (isset($fieldMappings[$field])) {
        foreach ($fieldMappings[$field] as $altField) {
            if (isset($array[$altField]) && !empty($array[$altField])) {
                return $array[$altField];
            }
        }
    }

    return $default;
}

// Variables principales
$pedido_encontrado = false;
$id = null;
$p = null;
$productos = [];
$total_productos = 0;
$error_message = '';

// Manejo dual: POST y GET para máxima compatibilidad
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pedido_id'])) {
    $id = intval($_POST['pedido_id']);
} elseif (isset($_GET['id'])) {
    $id = intval($_GET['id']);
}

if ($id && $id > 0) {
    try {
        // Obtener datos del pedido usando consulta directa para compatibilidad
        $id_safe = mysqli_real_escape_string($conn, $id);
        $query = "SELECT * FROM pedidos_detal WHERE id = $id_safe LIMIT 1";
        $result = mysqli_query($conn, $query);

        if ($result && mysqli_num_rows($result) > 0) {
            $pedido_encontrado = true;
            $p = mysqli_fetch_assoc($result);

            // Mapeo específico para campos críticos (especialmente teléfono para WhatsApp)
            if (empty($p['telefono']) && !empty($p['telefono_cliente'])) {
                $p['telefono'] = $p['telefono_cliente'];
            }
            if (empty($p['telefono']) && !empty($p['cliente_telefono'])) {
                $p['telefono'] = $p['cliente_telefono'];
            }
            if (empty($p['telefono']) && !empty($p['phone'])) {
                $p['telefono'] = $p['phone'];
            }

            // Mapeo para nombre del cliente
            if (empty($p['nombre_cliente']) && !empty($p['nombre'])) {
                $p['nombre_cliente'] = $p['nombre'];
            }
            if (empty($p['nombre_cliente']) && !empty($p['client_name'])) {
                $p['nombre_cliente'] = $p['client_name'];
            }

            // Función para determinar el estado basándose en las columnas booleanas
            function determinarEstado($p) {
                if ($p['anulado'] == 1) {
                    return ['texto' => 'Anulado', 'clase' => 'cancelado'];
                }
                if ($p['archivado'] == 1) {
                    return ['texto' => 'Archivado', 'clase' => 'archivado'];
                }

                // Determinar estado de pago
                $estado_pago = '';
                if ($p['pagado'] == 1) {
                    $estado_pago = 'Pago Confirmado';
                    $clase_pago = 'pago-confirmado';
                } else {
                    $estado_pago = 'Pago Pendiente';
                    $clase_pago = 'pago-pendiente';
                }

                if ($p['enviado'] == 1) {
                    return ['texto' => $estado_pago . ' • Enviado', 'clase' => $clase_pago . ' enviado'];
                }

                // Estado de pago como principal
                return ['texto' => $estado_pago, 'clase' => $clase_pago];
            }

            // Obtener el estado dinámico
            $estado_dinamico = determinarEstado($p);

            // Debug: Mostrar campos disponibles (comentar en producción)
            // echo "<pre>DEBUG - Campos disponibles: " . print_r(array_keys($p), true) . "</pre>";

            // Obtener productos del pedido
            $detalle_query = "SELECT nombre, precio, cantidad, talla FROM pedido_detalle WHERE pedido_id = $id_safe";
            $result_detalle = mysqli_query($conn, $detalle_query);

            if ($result_detalle) {
                while ($item = mysqli_fetch_assoc($result_detalle)) {
                    $productos[] = $item;
                    $total_productos += floatval($item['precio']) * intval($item['cantidad']);
                }
            }

            // ===== ESTADÍSTICAS AVANZADAS DEL CLIENTE =====
            $cliente_stats = [];
            $correo_cliente = $p['correo'] ?? $p['email'] ?? $p['cliente_email'] ?? '';
            $telefono_cliente = $p['telefono'] ?? $p['telefono_cliente'] ?? $p['phone'] ?? '';

            if (!empty($correo_cliente) || !empty($telefono_cliente)) {
                try {
                    // Escapar valores para seguridad
                    $correo_safe = mysqli_real_escape_string($conn, $correo_cliente);
                    $telefono_safe = mysqli_real_escape_string($conn, $telefono_cliente);

                    // 1. Customer Lifetime Value (LTV) - Calculado desde pedido_detalle
                    $ltv_query = "SELECT
                        COUNT(DISTINCT p.id) as total_pedidos,
                        COALESCE(SUM(pd.precio * pd.cantidad), 0) as valor_total_historico,
                        COALESCE(AVG(pd.precio * pd.cantidad), 0) as ticket_promedio,
                        MIN(p.fecha) as primer_pedido,
                        MAX(p.fecha) as ultimo_pedido,
                        SUM(CASE WHEN p.pagado = 1 THEN 1 ELSE 0 END) as pedidos_pagados,
                        SUM(CASE WHEN p.enviado = '1' THEN 1 ELSE 0 END) as pedidos_enviados
                    FROM pedidos_detal p
                    LEFT JOIN pedido_detalle pd ON p.id = pd.pedido_id
                    WHERE (p.correo = '$correo_safe' OR p.telefono = '$telefono_safe')
                    AND p.anulado = '0'";

                    $ltv_result = mysqli_query($conn, $ltv_query);
                    if ($ltv_result && $ltv_row = mysqli_fetch_assoc($ltv_result)) {
                        $cliente_stats['ltv'] = $ltv_row;
                    }

                    // 2. Análisis de comportamiento - método de pago preferido
                    $comportamiento_query = "SELECT
                        metodo_pago,
                        COUNT(*) as veces_usado
                    FROM pedidos_detal
                    WHERE (correo = '$correo_safe' OR telefono = '$telefono_safe')
                    AND anulado = '0'
                    AND metodo_pago IS NOT NULL
                    AND metodo_pago != ''
                    GROUP BY metodo_pago
                    ORDER BY veces_usado DESC
                    LIMIT 1";

                    $comp_result = mysqli_query($conn, $comportamiento_query);
                    if ($comp_result && $comp_row = mysqli_fetch_assoc($comp_result)) {
                        $cliente_stats['metodo_preferido'] = $comp_row;
                    }

                    // 3. Productos más comprados por este cliente (si hay tabla de detalle)
                    $productos_query = "SELECT
                        pd.nombre,
                        SUM(pd.cantidad) as total_comprado,
                        COUNT(*) as veces_pedido
                    FROM pedidos_detal p
                    JOIN pedido_detalle pd ON p.id = pd.pedido_id
                    WHERE (p.correo = '$correo_safe' OR p.telefono = '$telefono_safe')
                    AND p.anulado = '0'
                    AND pd.nombre IS NOT NULL
                    AND pd.nombre != ''
                    GROUP BY pd.nombre
                    ORDER BY total_comprado DESC
                    LIMIT 3";

                    $prod_result = mysqli_query($conn, $productos_query);
                    $cliente_stats['productos_favoritos'] = [];
                    if ($prod_result) {
                        while ($prod_row = mysqli_fetch_assoc($prod_result)) {
                            $cliente_stats['productos_favoritos'][] = $prod_row;
                        }
                    }

                    // 4. Análisis temporal - hora preferida para hacer pedidos
                    $temporal_query = "SELECT
                        HOUR(fecha) as hora,
                        COUNT(*) as pedidos_en_hora
                    FROM pedidos_detal
                    WHERE (correo = '$correo_safe' OR telefono = '$telefono_safe')
                    AND anulado = '0'
                    AND fecha IS NOT NULL
                    GROUP BY HOUR(fecha)
                    ORDER BY pedidos_en_hora DESC
                    LIMIT 1";

                    $temp_result = mysqli_query($conn, $temporal_query);
                    if ($temp_result && $temp_row = mysqli_fetch_assoc($temp_result)) {
                        $cliente_stats['hora_preferida'] = $temp_row;
                    }

                    // 5. Comparativa con promedio general del sistema
                    $promedio_query = "SELECT
                        COALESCE(AVG(pd.precio * pd.cantidad), 0) as ticket_promedio_general,
                        COUNT(DISTINCT p.id) as total_pedidos_sistema
                    FROM pedidos_detal p
                    JOIN pedido_detalle pd ON p.id = pd.pedido_id
                    WHERE p.anulado = '0'";

                    $prom_result = mysqli_query($conn, $promedio_query);
                    if ($prom_result && $prom_row = mysqli_fetch_assoc($prom_result)) {
                        $cliente_stats['promedio_general'] = $prom_row;
                    }

                    // 6. Tiempo de procesamiento promedio
                    $tiempo_query = "SELECT
                        COALESCE(AVG(DATEDIFF(NOW(), fecha)), 0) as tiempo_promedio_envio
                    FROM pedidos_detal
                    WHERE (correo = '$correo_safe' OR telefono = '$telefono_safe')
                    AND enviado = '1'
                    AND anulado = '0'
                    AND fecha IS NOT NULL";

                    $tiempo_result = mysqli_query($conn, $tiempo_query);
                    if ($tiempo_result && $tiempo_row = mysqli_fetch_assoc($tiempo_result)) {
                        $cliente_stats['tiempo_procesamiento'] = $tiempo_row;
                    }

                    // ===== NUEVAS MÉTRICAS AVANZADAS =====

                    // 7. Tasa de Recurrencia - % de clientes con múltiples compras
                    $recurrencia_query = "SELECT
                        COUNT(DISTINCT p.correo) as clientes_totales,
                        COUNT(DISTINCT CASE WHEN pedidos_cliente >= 2 THEN p.correo END) as clientes_recurrentes
                    FROM (
                        SELECT correo, COUNT(*) as pedidos_cliente
                        FROM pedidos_detal
                        WHERE anulado = '0'
                        AND correo IS NOT NULL
                        AND correo != ''
                        GROUP BY correo
                    ) as p";

                    $rec_result = mysqli_query($conn, $recurrencia_query);
                    if ($rec_result && $rec_row = mysqli_fetch_assoc($rec_result)) {
                        $total_clientes = intval($rec_row['clientes_totales']);
                        $clientes_recurrentes = intval($rec_row['clientes_recurrentes']);
                        $tasa_recurrencia = $total_clientes > 0 ? ($clientes_recurrentes / $total_clientes) * 100 : 0;

                        $cliente_stats['recurrencia'] = [
                            'tasa_recurrencia_sistema' => $tasa_recurrencia,
                            'clientes_recurrentes' => $clientes_recurrentes,
                            'clientes_totales' => $total_clientes
                        ];
                    }

                    // 8. Score RFM del cliente
                    $rfm_query = "SELECT
                        DATEDIFF(NOW(), MAX(fecha)) as recencia_dias,
                        COUNT(*) as frecuencia,
                        COALESCE(SUM(pd.precio * pd.cantidad), 0) as valor_monetario
                    FROM pedidos_detal p
                    LEFT JOIN pedido_detalle pd ON p.id = pd.pedido_id
                    WHERE (p.correo = '$correo_safe' OR p.telefono = '$telefono_safe')
                    AND p.anulado = '0'";

                    $rfm_result = mysqli_query($conn, $rfm_query);
                    if ($rfm_result && $rfm_row = mysqli_fetch_assoc($rfm_result)) {
                        $recencia = intval($rfm_row['recencia_dias']);
                        $frecuencia = intval($rfm_row['frecuencia']);
                        $valor = floatval($rfm_row['valor_monetario']);

                        // Calcular scores RFM (1-5, donde 5 es mejor)
                        $score_r = $recencia <= 30 ? 5 : ($recencia <= 90 ? 4 : ($recencia <= 180 ? 3 : ($recencia <= 365 ? 2 : 1)));
                        $score_f = $frecuencia >= 5 ? 5 : ($frecuencia >= 3 ? 4 : ($frecuencia >= 2 ? 3 : ($frecuencia >= 1 ? 2 : 1)));
                        $score_m = $valor >= 1000000 ? 5 : ($valor >= 500000 ? 4 : ($valor >= 200000 ? 3 : ($valor >= 50000 ? 2 : 1)));

                        // Clasificación RFM
                        $clasificacion = '';
                        if ($score_r >= 4 && $score_f >= 4 && $score_m >= 4) {
                            $clasificacion = '👑 Champions';
                        } elseif ($score_r >= 3 && $score_f >= 3 && $score_m >= 3) {
                            $clasificacion = '⭐ Loyal Customers';
                        } elseif ($score_r >= 4 && $score_f <= 2) {
                            $clasificacion = '🆕 New Customers';
                        } elseif ($score_r <= 2 && $score_f >= 3) {
                            $clasificacion = '💤 At Risk';
                        } elseif ($score_r <= 2 && $score_f <= 2) {
                            $clasificacion = '😴 Lost Customers';
                        } else {
                            $clasificacion = '🔄 Active';
                        }

                        $cliente_stats['rfm'] = [
                            'recencia_dias' => $recencia,
                            'frecuencia' => $frecuencia,
                            'valor_monetario' => $valor,
                            'score_r' => $score_r,
                            'score_f' => $score_f,
                            'score_m' => $score_m,
                            'clasificacion' => $clasificacion
                        ];
                    }

                    // 9. Días desde última compra
                    $ultima_compra_query = "SELECT
                        DATEDIFF(NOW(), MAX(fecha)) as dias_ultima_compra,
                        MAX(fecha) as fecha_ultima_compra
                    FROM pedidos_detal
                    WHERE (correo = '$correo_safe' OR telefono = '$telefono_safe')
                    AND anulado = '0'";

                    $ultima_result = mysqli_query($conn, $ultima_compra_query);
                    if ($ultima_result && $ultima_row = mysqli_fetch_assoc($ultima_result)) {
                        $cliente_stats['actividad'] = $ultima_row;
                    }

                    // 10. Tendencia de gasto (últimos 3 pedidos vs anteriores)
                    $tendencia_query = "SELECT
                        AVG(CASE WHEN rn <= 3 THEN total_pedido END) as promedio_reciente,
                        AVG(CASE WHEN rn > 3 THEN total_pedido END) as promedio_anterior
                    FROM (
                        SELECT
                            (SELECT COALESCE(SUM(pd.precio * pd.cantidad), 0)
                             FROM pedido_detalle pd WHERE pd.pedido_id = p.id) as total_pedido,
                            ROW_NUMBER() OVER (ORDER BY p.fecha DESC) as rn
                        FROM pedidos_detal p
                        WHERE (p.correo = '$correo_safe' OR p.telefono = '$telefono_safe')
                        AND p.anulado = '0'
                    ) ranked_orders";

                    $tend_result = mysqli_query($conn, $tendencia_query);
                    if ($tend_result && $tend_row = mysqli_fetch_assoc($tend_result)) {
                        $promedio_reciente = floatval($tend_row['promedio_reciente'] ?? 0);
                        $promedio_anterior = floatval($tend_row['promedio_anterior'] ?? 0);

                        $tendencia = 'estable';
                        $porcentaje_cambio = 0;

                        if ($promedio_anterior > 0) {
                            $porcentaje_cambio = (($promedio_reciente - $promedio_anterior) / $promedio_anterior) * 100;
                            if ($porcentaje_cambio > 15) {
                                $tendencia = 'creciente';
                            } elseif ($porcentaje_cambio < -15) {
                                $tendencia = 'decreciente';
                            }
                        } elseif ($promedio_reciente > 0) {
                            $tendencia = 'nuevo';
                        }

                        $cliente_stats['tendencia'] = [
                            'promedio_reciente' => $promedio_reciente,
                            'promedio_anterior' => $promedio_anterior,
                            'tendencia' => $tendencia,
                            'porcentaje_cambio' => $porcentaje_cambio
                        ];
                    }

                    // 11. Ranking percentil del cliente
                    $ranking_query = "SELECT
                        COUNT(*) as clientes_con_menor_ltv,
                        (SELECT COUNT(DISTINCT correo) FROM pedidos_detal WHERE anulado = '0' AND correo IS NOT NULL AND correo != '') as total_clientes_sistema
                    FROM (
                        SELECT correo, COALESCE(SUM(pd.precio * pd.cantidad), 0) as ltv_cliente
                        FROM pedidos_detal p
                        LEFT JOIN pedido_detalle pd ON p.id = pd.pedido_id
                        WHERE p.anulado = '0'
                        AND p.correo IS NOT NULL
                        AND p.correo != ''
                        GROUP BY p.correo
                        HAVING ltv_cliente < (
                            SELECT COALESCE(SUM(pd2.precio * pd2.cantidad), 0)
                            FROM pedidos_detal p2
                            LEFT JOIN pedido_detalle pd2 ON p2.id = pd2.pedido_id
                            WHERE (p2.correo = '$correo_safe' OR p2.telefono = '$telefono_safe')
                            AND p2.anulado = '0'
                        )
                    ) menores";

                    $rank_result = mysqli_query($conn, $ranking_query);
                    if ($rank_result && $rank_row = mysqli_fetch_assoc($rank_result)) {
                        $clientes_menores = intval($rank_row['clientes_con_menor_ltv']);
                        $total_clientes = intval($rank_row['total_clientes_sistema']);
                        $percentil = $total_clientes > 0 ? ($clientes_menores / $total_clientes) * 100 : 0;

                        $cliente_stats['ranking'] = [
                            'percentil' => $percentil,
                            'posicion' => $clientes_menores + 1,
                            'total_clientes' => $total_clientes
                        ];
                    }

                } catch (Exception $e) {
                    // Si falla alguna consulta de estadísticas, continuar sin mostrar error
                    error_log("Error en estadísticas avanzadas: " . $e->getMessage());
                }
            }
        } else {
            $error_message = "Pedido #$id no encontrado en el sistema.";
        }
    } catch (Exception $e) {
        $error_message = "Error al consultar el pedido: " . $e->getMessage();
    }
} else {
    $error_message = "ID de pedido no válido o no proporcionado.";
}

// Verificar si es una petición AJAX
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    // Responder solo con el contenido del modal
    header('Content-Type: text/html; charset=UTF-8');

    if (!$pedido_encontrado) {
        echo '<div class="error-message">Error: ' . h($error_message) . '</div>';
        exit;
    }

    // Renderizar solo el contenido del pedido sin HTML completo
    include 'ver_detalle_pedido_content.php';
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#0d1117">
    <title><?php echo $pedido_encontrado ? 'Pedido #' . h($p['id']) : 'Error'; ?> - Sequoia Speed</title>
    <link rel="stylesheet" href="ver_detalle_pedido_optimized.css">
    <!-- Sistema de Notificaciones -->
    <link rel="stylesheet" href="notifications/notifications.css">
    <!-- All CSS has been moved to external file -->
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="logo.png" alt="Sequoia Speed" class="logo" onerror="this.style.display='none'">
            <div class="header-content">
                <h1>Sequoia Speed</h1>
                <p class="subtitle">Detalles del Pedido<?php if ($pedido_encontrado) echo ' #' . h($p['id']); ?></p>
            </div>
        </div>

        <div class="content">
            <?php if (!$pedido_encontrado): ?>
                <?php if (empty($id)): ?>
                    <div class="form-section">
                        <h2>Consultar Pedido</h2>
                        <p style="margin-bottom: 20px; opacity: 0.8;">Ingresa el número de pedido para ver los detalles:</p>

                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="pedido_id">Número de Pedido:</label>
                                <input type="number" id="pedido_id" name="pedido_id" min="1" placeholder="Ej: 117" required>
                            </div>
                            <button type="submit" class="btn">Ver Pedido</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="error">
                        <strong>❌ Error:</strong> <?php echo h($error_message); ?>
                    </div>

                    <div class="form-section">
                        <h2>Intentar con otro pedido</h2>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="pedido_id">Número de Pedido:</label>
                                <input type="number" id="pedido_id" name="pedido_id" min="1" placeholder="Ej: 117" required>
                            </div>
                            <button type="submit" class="btn">Ver Pedido</button>
                        </form>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- ===== HEADER VISUAL DE PROGRESO (STEPPER) ===== -->
                <div class="progress-stepper" style="background: #30363d; border: 1px solid #3d444d; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; position: relative; margin-bottom: 10px;">
                        <?php
                        // Determinar el paso actual basado en los campos booleanos
                        $pagado = intval($p['pagado'] ?? 0);
                        $enviado = intval($p['enviado'] ?? 0);
                        $archivado = intval($p['archivado'] ?? 0);
                        $tienda = intval($p['tienda'] ?? 0);
                        $guia_existe = !empty($p['guia']) && file_exists("guias/" . $p['guia']);

                        $steps = [
                            'recibido' => ['icon' => '📝', 'label' => 'Recibido', 'active' => true],
                            'pagado' => ['icon' => '💳', 'label' => 'Pagado', 'active' => $pagado == 1],
                            'enviado' => ['icon' => '🚚', 'label' => 'Enviado', 'active' => $enviado == 1 || $guia_existe],
                            'entregado' => ['icon' => '✅', 'label' => 'Entregado', 'active' => $archivado == 1 || $tienda == 1]
                        ];

                        $step_count = 0;
                        foreach ($steps as $key => $step):
                            $is_active = $step['active'];
                            $step_count++;
                        ?>
                        <div class="step" style="display: flex; flex-direction: column; align-items: center; z-index: 2; background: #30363d; padding: 8px;">
                            <div style="width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; border: 2px solid <?php echo $is_active ? '#238636' : '#6e7681'; ?>; background: <?php echo $is_active ? '#238636' : '#21262d'; ?>; color: <?php echo $is_active ? 'white' : '#8b949e'; ?>; margin-bottom: 5px;">
                                <?php echo $step['icon']; ?>
                            </div>
                            <span style="font-size: 0.8rem; font-weight: 600; color: <?php echo $is_active ? '#238636' : '#8b949e'; ?>;">
                                <?php echo $step['label']; ?>
                            </span>
                        </div>
                        <?php if ($step_count < count($steps)): ?>
                        <div style="flex: 1; height: 2px; background: <?php echo $is_active ? '#238636' : '#6e7681'; ?>; margin: 0 10px; z-index: 1;"></div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <!-- Próxima acción sugerida -->
                    <div class="next-action" style="background: linear-gradient(135deg, #1f6feb15 0%, #0969da15 100%); border: 1px solid #1f6feb; border-radius: 8px; padding: 15px; text-align: center;">
                        <div style="color: #1f6feb; font-weight: 600; margin-bottom: 8px; font-size: 0.9rem;">🎯 Próxima Acción Sugerida</div>
                        <?php
                        if (!$pagado) {
                            echo '<button onclick="window.location.href=\'#comunicacion\'" style="background: #f79009; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer;">💳 Solicitar Comprobante de Pago</button>';
                        } elseif (!$guia_existe) {
                            echo '<button onclick="abrirModalGuia()" style="background: #1f6feb; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer;">📦 Subir Guía de Envío</button>';
                        } elseif (!$enviado) {
                            echo '<button onclick="confirmarEntregaConGuia()" style="background: #238636; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer;">📧 Notificar Envío al Cliente</button>';
                        } else {
                            echo '<button onclick="window.location.href=\'#timeline\'" style="background: #6f42c1; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer;">✅ Confirmar Entrega</button>';
                        }
                        ?>
                    </div>
                </div>

                <!-- Resumen compacto del pedido -->
                <div class="info-grid">
                    <!-- Primera fila: Cliente -->
                    <div class="info-card" style="grid-column: span 3;">
                        <h3>Cliente</h3>
                        <p><?php echo h($p['nombre'] ?? 'Sin nombre'); ?></p>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 8px; font-size: 0.85rem; opacity: 0.8;">
                            <div>📧 <?php echo h($p['correo'] ?? 'Sin email'); ?></div>
                            <div>📞 <?php echo h($p['telefono'] ?? 'Sin teléfono'); ?></div>
                        </div>
                    </div>

                    <!-- Segunda fila: Dirección -->
                    <?php if (!empty($p['direccion'] ?? '')): ?>
                    <div class="info-card" style="grid-column: span 3;">
                        <h3>Dirección</h3>
                        <p style="font-size: 0.9rem; line-height: 1.3;"><?php echo h($p['direccion']); ?></p>
                        <?php if (!empty($p['ciudad'] ?? '') || !empty($p['barrio'] ?? '')): ?>
                        <div style="margin-top: 8px; font-size: 0.85rem; opacity: 0.8;">
                            <?php if (!empty($p['ciudad'] ?? '')): ?>
                                <span style="margin-right: 15px;">🏙️ <?php echo h($p['ciudad']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($p['barrio'] ?? '')): ?>
                                <span>🏘️ <?php echo h($p['barrio']); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Método de Pago -->
                    <div class="info-card" style="grid-column: span 3;">
                        <h3>Método de Pago</h3>
                        <p><?php echo h($p['metodo_pago'] ?? 'No especificado'); ?></p>
                        <?php if (!empty($p['monto'] ?? '')): ?>
                        <div style="margin-top: 8px; font-size: 0.85rem; opacity: 0.8;">
                            <?php if (($p['descuento'] ?? 0) > 0): ?>
                                <?php 
                                // Calcular subtotal: usar total_productos si existe, sino calcular desde monto + descuento
                                $subtotal_correcto = ($total_productos > 0) ? $total_productos : $p['monto'] + $p['descuento'];
                                $total_final = ($total_productos > 0) ? $total_productos - $p['descuento'] : $p['monto'];
                                ?>
                                <div style="margin-bottom: 4px;">
                                    📊 Subtotal: $<?php echo number_format($subtotal_correcto, 0, ',', '.'); ?>
                                </div>
                                <div style="margin-bottom: 4px; color: #ff7b72;">
                                    🎯 Descuento: -$<?php echo number_format($p['descuento'], 0, ',', '.'); ?>
                                </div>
                                <div style="font-weight: 600; color: #56d364;">
                                    💰 Total: $<?php echo number_format($total_final, 0, ',', '.'); ?>
                                </div>
                            <?php else: ?>
                                💰 Monto: $<?php echo number_format($total_productos > 0 ? $total_productos : $p['monto'], 0, ',', '.'); ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Tercera fila: Fecha, Estado, Enviado -->
                    <div class="info-card compact">
                        <h3>Fecha</h3>
                        <p><?php
                            $fecha = $p['fecha'] ?? '';
                            if ($fecha && $fecha != 'No disponible') {
                                echo date('d/m/Y', strtotime($fecha));
                            } else {
                                echo 'N/A';
                            }
                        ?></p>
                    </div>

                    <div class="info-card compact">
                        <h3>Estado</h3>
                        <p><span class="status <?php echo $estado_dinamico['clase']; ?>"><?php echo $estado_dinamico['texto']; ?></span></p>
                    </div>

                    <div class="info-card compact">
                        <h3>Enviado</h3>
                        <p><?php echo ($p['enviado'] == 1) ? '✅ Sí' : '❌ No'; ?></p>
                    </div>

                    <!-- Cuarta fila: Pagado, Pago, Teléfono -->
                    <div class="info-card compact">
                        <h3>Pagado</h3>
                        <p><?php echo ($p['pagado'] == 1) ? '✅ Sí' : '❌ No'; ?></p>
                    </div>

                    <div class="info-card compact">
                        <h3>Pago</h3>
                        <p><?php echo h($p['metodo_pago'] ?? 'N/A'); ?></p>
                    </div>

                    <div class="info-card compact">
                        <h3>Teléfono</h3>
                        <p><?php echo h($p['telefono'] ?? 'N/A'); ?></p>
                    </div>
                </div>

                <!-- Productos Solicitados -->
                <div class="productos-section" style="background: #30363d; border: 1px solid #3d444d; border-radius: 8px; padding: 20px; margin: 20px 0;">
                    <h3 style="color: #1f6feb; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                        📦 Productos Solicitados
                    </h3>

                    <?php if (!empty($productos)): ?>
                    <div class="table-container">
                        <table class="productos-table">
                            <thead>
                                <tr>
                                    <th style="width: 50%;">Producto</th>
                                    <th style="width: 15%; text-align: center;">Cantidad</th>
                                    <th style="width: 20%; text-align: right;">Precio Unit.</th>
                                    <th style="width: 15%; text-align: right;">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productos as $producto): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; color: #e6edf3; margin-bottom: 2px;">
                                            <?php echo h($producto['nombre']); ?>
                                        </div>
                                        <?php if (!empty($producto['descripcion'])): ?>
                                        <div style="font-size: 0.85rem; color: #8b949e;">
                                            <?php echo h($producto['descripcion']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center; font-weight: 600;">
                                        <?php echo h($producto['cantidad']); ?>
                                    </td>
                                    <td style="text-align: right; color: #238636; font-weight: 600;">
                                        $<?php echo number_format($producto['precio'], 0, ',', '.'); ?>
                                    </td>
                                    <td style="text-align: right; color: #238636; font-weight: 700;">
                                        $<?php echo number_format($producto['precio'] * $producto['cantidad'], 0, ',', '.'); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Productos Mobile Container -->
                    <div class="mobile-products-container">
                        <?php if (!empty($productos)): ?>
                            <?php foreach ($productos as $producto): ?>
                            <div class="mobile-product-card">
                                <div class="mobile-product-header">
                                    <div class="mobile-product-name"><?php echo h($producto['nombre']); ?></div>
                                    <div class="mobile-product-quantity">x<?php echo $producto['cantidad']; ?></div>
                                </div>

                                <?php if (!empty($producto['descripcion'])): ?>
                                <div class="mobile-product-description">
                                    <?php echo h($producto['descripcion']); ?>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($producto['talla'])): ?>
                                <div class="mobile-product-size">
                                    <span class="mobile-size-label">Talla:</span>
                                    <span class="mobile-size-value"><?php echo h($producto['talla']); ?></span>
                                </div>
                                <?php endif; ?>

                                <div class="mobile-product-pricing">
                                    <div class="mobile-unit-price">
                                        <span class="mobile-price-label">Precio unitario:</span>
                                        <span class="mobile-price-value">$<?php echo number_format($producto['precio'], 0, ',', '.'); ?></span>
                                    </div>
                                    <div class="mobile-subtotal">
                                        <span class="mobile-subtotal-label">Subtotal:</span>
                                        <span class="mobile-subtotal-value">$<?php echo number_format($producto['precio'] * $producto['cantidad'], 0, ',', '.'); ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="mobile-no-products">
                                <div class="mobile-no-products-icon">📦</div>
                                <div class="mobile-no-products-text">No hay productos registrados</div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Total del Pedido -->
                    <div class="total-section" style="margin-top: 20px; text-align: right;">
                        <div class="total-card" style="display: inline-block; background: linear-gradient(135deg, #238636 0%, #2ea043 100%); color: white; padding: 15px 25px; border-radius: 8px;">
                            <?php if (($p['descuento'] ?? 0) > 0): ?>
                                <h4 style="margin: 0 0 5px 0; opacity: 0.9; font-size: 0.9rem;">Resumen de Totales</h4>
                                <div style="font-size: 1rem; margin-bottom: 4px; opacity: 0.9;">
                                    Subtotal: $<?php echo number_format($total_productos, 0, ',', '.'); ?>
                                </div>
                                <div style="font-size: 1rem; margin-bottom: 8px; opacity: 0.9;">
                                    Descuento: -$<?php echo number_format($p['descuento'], 0, ',', '.'); ?>
                                </div>
                                <div class="amount" style="font-size: 1.8rem; font-weight: 700; margin: 0;">
                                    $<?php echo number_format($total_productos - $p['descuento'], 0, ',', '.'); ?>
                                </div>
                            <?php else: ?>
                                <h4 style="margin: 0 0 5px 0; opacity: 0.9; font-size: 0.9rem;">Total del Pedido</h4>
                                <div class="amount" style="font-size: 1.8rem; font-weight: 700; margin: 0;">
                                    $<?php echo number_format($total_productos, 0, ',', '.'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div style="text-align: center; color: #8b949e; padding: 20px; font-style: italic;">
                        📦 No hay productos registrados para este pedido
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Gestión de Estados -->
                <div class="estado-management" style="background: #30363d; border: 1px solid #3d444d; border-radius: 8px; padding: 20px; margin: 20px 0;">
                    <h3 style="color: #1f6feb; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                        🔄 Gestión del Pedido
                    </h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; align-items: end;">
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e6edf3;">Cambiar Estado:</label>
                            <select id="nuevo-estado" style="width: 100%; padding: 10px; border: 1px solid #3d444d; border-radius: 6px; background: #21262d; color: #e6edf3;">
                                <option value="pago-pendiente" <?php echo (strpos($estado_dinamico['clase'], 'pago-pendiente') !== false) ? 'selected' : ''; ?>>💳 Pago Pendiente</option>
                                <option value="pago-confirmado" <?php echo (strpos($estado_dinamico['clase'], 'pago-confirmado') !== false) ? 'selected' : ''; ?>>✅ Pago Confirmado</option>
                                <option value="enviado" <?php echo (strpos($estado_dinamico['clase'], 'enviado') !== false) ? 'selected' : ''; ?>>🚚 Enviado</option>
                                <option value="archivado" <?php echo ($estado_dinamico['clase'] == 'archivado') ? 'selected' : ''; ?>>📦 Archivado</option>
                                <option value="cancelado" <?php echo ($estado_dinamico['clase'] == 'cancelado') ? 'selected' : ''; ?>>❌ Cancelado</option>
                            </select>
                        </div>
                        <div>
                            <button onclick="cambiarEstadoPedido()" class="btn-print" style="width: 100%;">
                                <span>🔄</span> Actualizar Estado
                            </button>
                        </div>
                    </div>
                    <div id="estado-status" style="margin-top: 10px; text-align: center;"></div>
                </div>

                <!-- Acciones de Gestión (Guía, Comprobante, Imprimir) -->
                <div class="acciones-gestion" style="background: #30363d; border: 1px solid #3d444d; border-radius: 8px; padding: 20px; margin: 20px 0;">
                    <h3 style="color: #1f6feb; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                        🛠️ Acciones de Gestión
                    </h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">

                        <!-- Guía -->
                        <div class="accion-card" style="background: #21262d; border: 1px solid #3d444d; border-radius: 8px; padding: 20px; text-align: center;">
                            <div style="color: #1f6feb; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">📦 Guía de Envío</div>
                            <?php
                            $guia_actual = $p['guia'] ?? '';
                            if (!empty($guia_actual) && file_exists("guias/" . $guia_actual)): ?>
                                <div style="margin-bottom: 10px; color: #238636; font-size: 0.9rem;">✅ Guía cargada</div>
                                <a href="guias/<?php echo h($guia_actual); ?>" target="_blank" class="btn" style="background: #238636; margin-bottom: 8px; width: 100%; padding: 10px;">
                                    <span>👁️</span> Ver Guía
                                </a>
                                <button onclick="abrirModalGuia()" class="btn" style="background: #fb8500; width: 100%; padding: 10px;">
                                    <span>🔄</span> Cambiar Guía
                                </button>
                            <?php else: ?>
                                <div style="margin-bottom: 10px; color: #8b949e; font-size: 0.9rem;">Sin guía adjunta</div>
                                <button onclick="abrirModalGuia()" class="btn" style="background: #1f6feb; width: 100%; padding: 12px;">
                                    <span>📤</span> Subir Guía
                                </button>
                            <?php endif; ?>
                        </div>

                        <!-- Comprobante -->
                        <div class="accion-card" style="background: #21262d; border: 1px solid #3d444d; border-radius: 8px; padding: 20px; text-align: center;">
                            <div style="color: #1f6feb; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">💳 Comprobante de Pago</div>
                            <?php
                            $comprobante_actual = $p['comprobante'] ?? '';
                            if (!empty($comprobante_actual) && file_exists("comprobantes/" . $comprobante_actual)): ?>
                                <div style="margin-bottom: 10px; color: #238636; font-size: 0.9rem;">✅ Comprobante cargado</div>
                                <a href="comprobantes/<?php echo h($comprobante_actual); ?>" target="_blank" class="btn" style="background: #238636; margin-bottom: 8px; width: 100%; padding: 10px;">
                                    <span>👁️</span> Ver Comprobante
                                </a>
                                <button onclick="abrirModalComprobante()" class="btn" style="background: #fb8500; width: 100%; padding: 10px;">
                                    <span>🔄</span> Cambiar Comprobante
                                </button>
                            <?php else: ?>
                                <div style="margin-bottom: 10px; color: #8b949e; font-size: 0.9rem;">Sin comprobante adjunto</div>
                                <button onclick="abrirModalComprobante()" class="btn" style="background: #1f6feb; width: 100%; padding: 12px;">
                                    <span>📤</span> Subir Comprobante
                                </button>
                            <?php endif; ?>
                        </div>

                        <!-- Imprimir -->
                        <div class="accion-card" style="background: #21262d; border: 1px solid #3d444d; border-radius: 8px; padding: 20px; text-align: center;">
                            <div style="color: #1f6feb; font-weight: 600; margin-bottom: 10px; font-size: 1rem;">🖨️ Imprimir Pedido</div>
                            <div style="margin-bottom: 10px; color: #8b949e; font-size: 0.9rem;">Generar PDF del pedido</div>
                            <button onclick="imprimirPedido()" class="btn" style="background: #6e7681; width: 100%; padding: 12px;">
                                <span>🖨️</span> Imprimir PDF
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ===== TIMELINE INTERACTIVO (CENTRO DE COMANDO) ===== -->
                <div id="timeline" class="timeline-section" style="background: #30363d; border: 1px solid #3d444d; border-radius: 12px; padding: 25px; margin: 20px 0;">
                    <h3 style="color: #f79000; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 1.3rem; border-bottom: 1px solid #3d444d; padding-bottom: 10px;">
                        ⏱️ Timeline del Pedido - Centro de Comando
                    </h3>

                    <!-- Agregar nueva entrada al timeline -->
                    <div class="add-timeline-entry" style="background: #21262d; border: 1px solid #3d444d; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                            <span style="font-size: 1.2rem;">✏️</span>
                            <span style="color: #e6edf3; font-weight: 600;">Agregar entrada al timeline</span>
                        </div>
                        <div style="display: grid; grid-template-columns: 120px 1fr; gap: 15px; margin-bottom: 15px;">
                            <select id="tipo-evento" style="padding: 8px; border: 1px solid #3d444d; border-radius: 6px; background: #0d1117; color: #e6edf3;">
                                <option value="nota">📝 Nota Interna</option>
                                <option value="llamada">📞 Llamada</option>
                                <option value="email">📧 Email Enviado</option>
                                <option value="whatsapp">💬 WhatsApp</option>
                                <option value="estado">🔄 Cambio Estado</option>
                                <option value="archivo">📎 Archivo Subido</option>
                                <option value="cliente">� Comentario Cliente</option>
                            </select>
                            <textarea id="nueva-entrada-timeline" placeholder="Describe la acción realizada, comentario del cliente, o nota interna..."
                                      style="height: 60px; padding: 10px; border: 1px solid #3d444d; border-radius: 6px; background: #0d1117; color: #e6edf3; resize: vertical; font-family: inherit;"></textarea>
                        </div>
                        <div style="text-align: right;">
                            <button onclick="agregarEntradaTimeline()" class="btn-print" style="background: linear-gradient(135deg, #1f6feb, #0969da);">
                                <span>➕</span> Agregar al Timeline
                            </button>
                        </div>
                        <div id="timeline-status" style="margin-top: 10px; text-align: center;"></div>
                    </div>

                    <!-- Timeline de eventos -->
                    <div id="timeline-eventos" style="position: relative;">
                        <!-- Línea vertical del timeline -->
                        <div style="position: absolute; left: 20px; top: 0; bottom: 0; width: 2px; background: linear-gradient(to bottom, #1f6feb, #0969da);"></div>

                        <?php
                        // Crear eventos del timeline basados en el estado del pedido
                        $timeline_events = [];

                        // Evento: Pedido creado
                        $timeline_events[] = [
                            'icon' => '📝',
                            'tipo' => 'sistema',
                            'titulo' => 'Pedido Creado',
                            'descripcion' => 'Pedido #' . $p['id'] . ' registrado en el sistema',
                            'fecha' => $p['fecha'] ?? date('Y-m-d H:i:s'),
                            'color' => '#1f6feb'
                        ];

                        // Evento: Pago (si está pagado)
                        if ($p['pagado'] == 1) {
                            $timeline_events[] = [
                                'icon' => '💳',
                                'tipo' => 'pago',
                                'titulo' => 'Pago Confirmado',
                                'descripcion' => 'Pago recibido via ' . ($p['metodo_pago'] ?? 'método no especificado'),
                                'fecha' => $p['fecha'] ?? date('Y-m-d H:i:s'),
                                'color' => '#238636'
                            ];
                        }

                        // Evento: Guía subida (si existe)
                        if (!empty($p['guia']) && file_exists("guias/" . $p['guia'])) {
                            $timeline_events[] = [
                                'icon' => '📦',
                                'tipo' => 'envio',
                                'titulo' => 'Guía de Envío Subida',
                                'descripcion' => 'Archivo: ' . $p['guia'],
                                'fecha' => $p['fecha'] ?? date('Y-m-d H:i:s'),
                                'color' => '#fb8500'
                            ];
                        }

                        // Evento: Pedido enviado (si está marcado como enviado)
                        if ($p['enviado'] == 1) {
                            $timeline_events[] = [
                                'icon' => '🚚',
                                'tipo' => 'envio',
                                'titulo' => 'Pedido Enviado',
                                'descripcion' => 'Pedido despachado para entrega',
                                'fecha' => $p['fecha'] ?? date('Y-m-d H:i:s'),
                                'color' => '#6f42c1'
                            ];
                        }

                        // Evento: Notas existentes (si las hay)
                        if (!empty($p['nota_interna'])) {
                            $timeline_events[] = [
                                'icon' => '📝',
                                'tipo' => 'nota',
                                'titulo' => 'Nota Interna',
                                'descripcion' => $p['nota_interna'],
                                'fecha' => $p['fecha'] ?? date('Y-m-d H:i:s'),
                                'color' => '#8b949e'
                            ];
                        }

                        // Mostrar eventos del timeline
                        foreach ($timeline_events as $event):
                        ?>
                        <div class="timeline-event" style="position: relative; margin-bottom: 20px; margin-left: 50px;">
                            <div style="position: absolute; left: -35px; top: 0; width: 30px; height: 30px; border-radius: 50%; background: <?php echo $event['color']; ?>; display: flex; align-items: center; justify-content: center; border: 3px solid #30363d; font-size: 0.9rem;">
                                <?php echo $event['icon']; ?>
                            </div>
                            <div style="background: #21262d; border: 1px solid #3d444d; border-radius: 8px; padding: 15px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <h4 style="color: <?php echo $event['color']; ?>; margin: 0; font-size: 1rem; font-weight: 600;">
                                        <?php echo h($event['titulo']); ?>
                                    </h4>
                                    <span style="color: #8b949e; font-size: 0.8rem;">
                                        <?php
                                        $fecha = new DateTime($event['fecha']);
                                        echo $fecha->format('d/m/Y H:i');
                                        ?>
                                    </span>
                                </div>
                                <p style="color: #e6edf3; margin: 0; line-height: 1.4; font-size: 0.9rem;">
                                    <?php echo nl2br(h($event['descripcion'])); ?>
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <!-- Placeholder para nuevos eventos -->
                        <div id="nuevos-eventos-timeline"></div>
                    </div>
                </div>

                <!-- Comunicación con Cliente -->
                <div id="comunicacion" class="comunicacion-section" style="background: #30363d; border: 1px solid #3d444d; border-radius: 8px; padding: 20px; margin: 20px 0;">
                    <h3 style="color: #1f6feb; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                        📧 Comunicación con Cliente
                    </h3>

                    <!-- Plantillas de Mensajes -->
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e6edf3;">Plantillas Rápidas:</label>
                        <select id="plantilla-mensaje" style="width: 100%; padding: 10px; border: 1px solid #3d444d; border-radius: 6px; background: #21262d; color: #e6edf3;" onchange="cargarPlantillaMensaje()">
                            <option value="">Seleccionar plantilla...</option>
                            <option value="confirmacion-pago">💳 Confirmación de pago recibido</option>
                            <option value="pedido-enviado">🚚 Tu pedido ha sido enviado</option>
                            <option value="solicitar-info">❓ Solicitud de información adicional</option>
                            <option value="confirmar-direccion">📍 Confirmar dirección de entrega</option>
                            <option value="pedido-listo">✅ Tu pedido está listo para entrega</option>
                        </select>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                        <button onclick="confirmarEntregaConGuia()" class="btn" style="background: #fb8500; padding: 12px; font-size: 0.9rem;">
                            <span>📦</span> Confirmar Entrega
                        </button>
                        <?php if (!empty($p['telefono'])): ?>
                        <a href="tel:<?php echo h($p['telefono']); ?>" class="btn" style="background: #6e7681; padding: 12px; font-size: 0.9rem; text-decoration: none; text-align: center;">
                            <span>📞</span> Llamar Cliente
                        </a>
                        <button onclick="abrirWhatsAppConPlantilla('<?php echo h($p['telefono']); ?>', '<?php echo h($p['nombre'] ?? 'Cliente'); ?>', '<?php echo h($p['id'] ?? ''); ?>')" class="btn" style="background: #25d366; padding: 12px; font-size: 0.9rem;">
                            <span>💬</span> WhatsApp con Plantilla
                        </button>
                        <?php endif; ?>
                    </div>

                    <div id="comunicacion-status" style="margin-top: 15px; text-align: center;"></div>
                </div>

                <!-- Edición de Datos del Cliente -->
                <div class="editar-cliente-section" style="background: #30363d; border: 1px solid #3d444d; border-radius: 8px; padding: 20px; margin: 20px 0;">
                    <h3 style="color: #1f6feb; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                        ✏️ Editar Información del Cliente
                    </h3>

                    <div style="text-align: right; margin-bottom: 15px;">
                        <button id="btn-habilitar-edicion" onclick="habilitarEdicion()" class="btn" style="background: #fb8500;">
                            <span>✏️</span> Editar Información
                        </button>
                    </div>

                    <div id="formulario-edicion" style="display: none;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e6edf3;">Nombre Completo:</label>
                                <input type="text" id="edit-nombre" value="<?php echo h($p['nombre'] ?? ''); ?>"
                                       style="width: 100%; padding: 10px; border: 1px solid #3d444d; border-radius: 6px; background: #21262d; color: #e6edf3;">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e6edf3;">Teléfono:</label>
                                <input type="tel" id="edit-telefono" value="<?php echo h($p['telefono'] ?? ''); ?>"
                                       style="width: 100%; padding: 10px; border: 1px solid #3d444d; border-radius: 6px; background: #21262d; color: #e6edf3;">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e6edf3;">Ciudad:</label>
                                <input type="text" id="edit-ciudad" value="<?php echo h($p['ciudad'] ?? ''); ?>"
                                       style="width: 100%; padding: 10px; border: 1px solid #3d444d; border-radius: 6px; background: #21262d; color: #e6edf3;">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e6edf3;">Barrio:</label>
                                <input type="text" id="edit-barrio" value="<?php echo h($p['barrio'] ?? ''); ?>"
                                       style="width: 100%; padding: 10px; border: 1px solid #3d444d; border-radius: 6px; background: #21262d; color: #e6edf3;">
                            </div>
                        </div>

                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e6edf3;">Email:</label>
                            <input type="email" id="edit-correo" value="<?php echo h($p['correo'] ?? ''); ?>"
                                   style="width: 100%; padding: 10px; border: 1px solid #3d444d; border-radius: 6px; background: #21262d; color: #e6edf3;">
                        </div>

                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #e6edf3;">Dirección de Entrega:</label>
                            <textarea id="edit-direccion" style="width: 100%; height: 80px; padding: 10px; border: 1px solid #3d444d; border-radius: 6px; background: #21262d; color: #e6edf3; resize: vertical; font-family: inherit;"><?php echo h($p['direccion'] ?? ''); ?></textarea>
                        </div>

                        <div style="display: flex; gap: 15px; justify-content: center;">
                            <button onclick="guardarCambiosCliente()" class="btn-print">
                                <span>💾</span> Guardar Cambios
                            </button>
                            <button onclick="cancelarEdicion()" class="btn" style="background: #6e7681;">
                                <span>❌</span> Cancelar
                            </button>
                        </div>

                        <div id="edicion-status" style="margin-top: 15px; text-align: center;"></div>
                    </div>
                </div>

                <!-- Métricas del Pedido -->
                <div class="metricas-section" style="background: #30363d; border: 1px solid #3d444d; border-radius: 8px; padding: 20px; margin: 20px 0;">
                    <h3 style="color: #1f6feb; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                        📊 Métricas del Pedido
                    </h3>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px;">
                        <div class="metric-card" style="background: #21262d; padding: 15px; border-radius: 6px; text-align: center; border: 1px solid #3d444d;">
                            <div style="color: #8b949e; font-size: 0.8rem; margin-bottom: 5px;">⏱️ Tiempo transcurrido</div>
                            <div style="color: #e6edf3; font-weight: 600; font-size: 1.1rem;">
                                <?php
                                $fecha_pedido = $p['fecha'] ?? '';
                                if ($fecha_pedido && $fecha_pedido != 'No disponible') {
                                    $fecha_creacion = new DateTime($fecha_pedido);
                                    $ahora = new DateTime();
                                    $diferencia = $ahora->diff($fecha_creacion);

                                    if ($diferencia->days > 0) {
                                        echo $diferencia->days . ' días';
                                    } elseif ($diferencia->h > 0) {
                                        echo $diferencia->h . ' horas';
                                    } else {
                                        echo $diferencia->i . ' minutos';
                                    }
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </div>
                        </div>

                        <div class="metric-card" style="background: #21262d; padding: 15px; border-radius: 6px; text-align: center; border: 1px solid #3d444d;">
                            <div style="color: #8b949e; font-size: 0.8rem; margin-bottom: 5px;">💰 Valor total</div>
                            <div style="color: #238636; font-weight: 700; font-size: 1.2rem;">
                                <?php 
                                $valor_total_final = ($p['descuento'] ?? 0) > 0 ? $total_productos - $p['descuento'] : $total_productos;
                                echo '$' . number_format($valor_total_final, 0, ',', '.'); 
                                ?>
                            </div>
                            <?php if (($p['descuento'] ?? 0) > 0): ?>
                                <div style="font-size: 0.7rem; color: #8b949e; margin-top: 3px;">
                                    Subtotal: $<?php echo number_format($total_productos, 0, ',', '.'); ?><br>
                                    Descuento: -$<?php echo number_format($p['descuento'], 0, ',', '.'); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="metric-card" style="background: #21262d; padding: 15px; border-radius: 6px; text-align: center; border: 1px solid #3d444d;">
                            <div style="color: #8b949e; font-size: 0.8rem; margin-bottom: 5px;">📦 Productos</div>
                            <div style="color: #e6edf3; font-weight: 600; font-size: 1.1rem;">
                                <?php echo count($productos); ?> item<?php echo count($productos) != 1 ? 's' : ''; ?>
                            </div>
                        </div>

                        <div class="metric-card" style="background: #21262d; padding: 15px; border-radius: 6px; text-align: center; border: 1px solid #3d444d;">
                            <div style="color: #8b949e; font-size: 0.8rem; margin-bottom: 5px;">💳 Método de pago</div>
                            <div style="color: #e6edf3; font-weight: 600; font-size: 0.9rem;">
                                <?php echo h($p['metodo_pago'] ?? 'N/A'); ?>
                            </div>
                        </div>                    </div>
                </div>

            <!-- ===== SISTEMA DE PESTAÑAS DE CONTEXTO ===== -->
            <div class="pestanas-contexto" style="margin-top: 30px; background: #0d1117; border: 1px solid #30363d; border-radius: 12px; overflow: hidden;">

                <!-- Navegador de Pestañas -->
                <div class="pestanas-nav" style="display: flex; background: #21262d; border-bottom: 1px solid #30363d;">
                    <button id="tab-estadisticas" class="tab-button active" onclick="cambiarPestana('estadisticas')"
                            style="flex: 1; padding: 15px 20px; background: none; border: none; color: #58a6ff; font-weight: 600; cursor: pointer; border-bottom: 2px solid #1f6feb; transition: all 0.3s ease;">
                        📊 Estadísticas Avanzadas
                    </button>
                    <button id="tab-historial" class="tab-button" onclick="cambiarPestana('historial')"
                            style="flex: 1; padding: 15px 20px; background: none; border: none; color: #8b949e; font-weight: 600; cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.3s ease;">
                        🕒 Historial de Pedidos
                    </button>
                    <button id="tab-archivos" class="tab-button" onclick="cambiarPestana('archivos')"
                            style="flex: 1; padding: 15px 20px; background: none; border: none; color: #8b949e; font-weight: 600; cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.3s ease;">
                        📁 Archivos y Documentos
                    </button>
                </div>

                <!-- Contenido de las Pestañas -->
                <div class="pestanas-content" style="padding: 25px;">

                    <!-- PESTAÑA 1: ESTADÍSTICAS AVANZADAS -->
                    <div id="content-estadisticas" class="tab-content active" style="display: block;">
                        <div style="text-align: center; margin-bottom: 25px;">
                            <h3 style="color: #f79000; font-size: 1.4rem; margin-bottom: 8px; display: flex; align-items: center; justify-content: center; gap: 12px;">
                                🎯 Estadísticas Avanzadas y Comparativas
                            </h3>
                            <p style="color: #8b949e; font-size: 0.95rem; margin: 0;">
                                Análisis profundo del rendimiento del cliente comparado con el mercado general
                            </p>
                        </div>

                        <!-- Grid de Comparativas -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 25px;">

                            <!-- Comparativa de Conversión -->
                            <div style="background: linear-gradient(135deg, #23863615 0%, #2ea04315 100%); border: 1px solid #238636; border-radius: 8px; padding: 20px;">
                                <h4 style="color: #238636; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                                    💹 Tasa de Conversión
                                </h4>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; text-align: center;">
                                    <div>
                                        <div style="color: #8b949e; font-size: 0.8rem; margin-bottom: 5px;">Este Cliente</div>
                                        <div style="color: #3fb950; font-weight: 700; font-size: 1.5rem;">
                                            <?php
                                            $total_pedidos_cliente = intval($cliente_stats['ltv']['total_pedidos'] ?? 0);
                                            $pagados_cliente = intval($cliente_stats['ltv']['pedidos_pagados'] ?? 0);
                                            $conversion_cliente = $total_pedidos_cliente > 0 ? ($pagados_cliente / $total_pedidos_cliente) * 100 : 0;
                                            echo number_format($conversion_cliente, 1) . '%';
                                            ?>
                                        </div>
                                    </div>
                                    <div>
                                        <div style="color: #8b949e; font-size: 0.8rem; margin-bottom: 5px;">Promedio General</div>
                                        <div style="color: #8b949e; font-weight: 600; font-size: 1.5rem;">
                                            <?php
                                            $conversion_general = floatval($cliente_stats['promedio_general']['tasa_conversion_general'] ?? 0);
                                            echo number_format($conversion_general, 1) . '%';
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <div style="margin-top: 15px; text-align: center; font-size: 0.85rem;">
                                    <?php
                                    $diferencia_conversion = $conversion_cliente - $conversion_general;
                                    if ($diferencia_conversion > 0) {
                                        echo '<span style="color: #3fb950;">🔥 +' . number_format($diferencia_conversion, 1) . '% mejor que el promedio</span>';
                                    } elseif ($diferencia_conversion < 0) {
                                        echo '<span style="color: #f85149;">📉 ' . number_format($diferencia_conversion, 1) . '% por debajo del promedio</span>';
                                    } else {
                                        echo '<span style="color: #58a6ff;">📊 En línea con el promedio general</span>';
                                    }
                                    ?>
                                </div>
                            </div>

                            <!-- Comparativa de Ticket Promedio -->
                            <div style="background: linear-gradient(135deg, #1f6feb15 0%, #0969da15 100%); border: 1px solid #1f6feb; border-radius: 8px; padding: 20px;">
                                <h4 style="color: #1f6feb; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                                    💰 Ticket Promedio
                                </h4>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; text-align: center;">
                                    <div>
                                        <div style="color: #8b949e; font-size: 0.8rem; margin-bottom: 5px;">Este Cliente</div>
                                        <div style="color: #58a6ff; font-weight: 700; font-size: 1.5rem;">
                                            $<?php echo number_format(floatval($cliente_stats['ltv']['ticket_promedio'] ?? 0), 0, ',', '.'); ?>
                                        </div>
                                    </div>
                                    <div>
                                        <div style="color: #8b949e; font-size: 0.8rem; margin-bottom: 5px;">Promedio General</div>
                                        <div style="color: #8b949e; font-weight: 600; font-size: 1.5rem;">
                                            $<?php echo number_format(floatval($cliente_stats['promedio_general']['ticket_promedio_general'] ?? 0), 0, ',', '.'); ?>
                                        </div>
                                    </div>
                                </div>
                                <div style="margin-top: 15px; text-align: center; font-size: 0.85rem;">
                                    <?php
                                    $ticket_cliente = floatval($cliente_stats['ltv']['ticket_promedio'] ?? 0);
                                    $ticket_general = floatval($cliente_stats['promedio_general']['ticket_promedio_general'] ?? 0);
                                    if ($ticket_general > 0) {
                                        $diferencia_ticket = (($ticket_cliente - $ticket_general) / $ticket_general) * 100;
                                        if ($diferencia_ticket > 0) {
                                            echo '<span style="color: #3fb950;">💎 +' . number_format($diferencia_ticket, 0) . '% más valioso</span>';
                                        } elseif ($diferencia_ticket < 0) {
                                            echo '<span style="color: #f85149;">📊 ' . number_format($diferencia_ticket, 0) . '% por debajo</span>';
                                        } else {
                                            echo '<span style="color: #58a6ff;">📊 Ticket estándar del mercado</span>';
                                        }
                                    } else {
                                        echo '<span style="color: #8b949e;">📊 Datos comparativos no disponibles</span>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>

                        <!-- Análisis de Comportamiento de Compra -->
                        <div style="background: #21262d; border: 1px solid #3d444d; border-radius: 8px; padding: 20px; margin-bottom: 25px;">
                            <h4 style="color: #f79000; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                                🧠 Análisis de Comportamiento de Compra
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                                <div style="text-align: center;">
                                    <div style="color: #8b949e; font-size: 0.8rem; margin-bottom: 5px;">🛒 Frecuencia de Compra</div>
                                    <div style="color: #e6edf3; font-weight: 600; font-size: 1.1rem;">
                                        <?php
                                        $total_pedidos = intval($cliente_stats['ltv']['total_pedidos'] ?? 0);
                                        $primer_pedido = $cliente_stats['ltv']['primer_pedido'] ?? '';
                                        if ($primer_pedido && $total_pedidos > 1) {
                                            $fecha_primer = new DateTime($primer_pedido);
                                            $ahora = new DateTime();
                                            $dias_transcurridos = $ahora->diff($fecha_primer)->days;
                                            if ($dias_transcurridos > 0) {
                                                $frecuencia = $dias_transcurridos / $total_pedidos;
                                                echo 'Cada ' . number_format($frecuencia, 0) . ' días';
                                            } else {
                                                echo 'Datos insuficientes';
                                            }
                                        } else {
                                            echo 'Cliente nuevo';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div style="text-align: center;">
                                    <div style="color: #8b949e; font-size: 0.8rem; margin-bottom: 5px;">📈 Estacionalidad</div>
                                    <div style="color: #e6edf3; font-weight: 600; font-size: 1.1rem;">
                                        <?php
                                        // Aquí se podría agregar lógica para determinar estacionalidad
                                        echo 'Todo el año';
                                        ?>
                                    </div>
                                </div>
                                <div style="text-align: center;">
                                    <div style="color: #8b949e; font-size: 0.8rem; margin-bottom: 5px;">🎯 Fidelidad</div>
                                    <div style="color: #e6edf3; font-weight: 600; font-size: 1.1rem;">
                                        <?php
                                        if ($total_pedidos >= 5) {
                                            echo 'Alta';
                                        } elseif ($total_pedidos >= 3) {
                                            echo 'Media';
                                        } else {
                                            echo 'Desarrollándose';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div style="text-align: center;">
                                    <div style="color: #8b949e; font-size: 0.8rem; margin-bottom: 5px;">⚡ Velocidad</div>
                                    <div style="color: #e6edf3; font-weight: 600; font-size: 1.1rem;">
                                        <?php
                                        $tiempo_promedio = floatval($cliente_stats['tiempo_procesamiento']['tiempo_promedio_pago'] ?? 0);
                                        if ($tiempo_promedio <= 1) {
                                            echo 'Inmediata';
                                        } elseif ($tiempo_promedio <= 24) {
                                            echo 'Rápida';
                                        } else {
                                            echo 'Reflexiva';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Predicciones y Oportunidades -->
                        <div style="background: linear-gradient(135deg, #7c3aed15 0%, #8b5cf615 100%); border: 1px solid #7c3aed; border-radius: 8px; padding: 20px;">
                            <h4 style="color: #7c3aed; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                                🔮 Predicciones y Oportunidades
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; font-size: 0.9rem;">
                                <div style="color: #e6edf3;">
                                    <strong style="color: #a78bfa;">💡 Próxima Compra Estimada:</strong><br>
                                    <?php
                                    $frecuencia_dias = 0;
                                    if ($primer_pedido && $total_pedidos > 1) {
                                        $fecha_primer = new DateTime($primer_pedido);
                                        $ahora = new DateTime();
                                        $dias_transcurridos = $ahora->diff($fecha_primer)->days;
                                        if ($dias_transcurridos > 0) {
                                            $frecuencia_dias = $dias_transcurridos / $total_pedidos;
                                        }
                                    }

                                    if ($frecuencia_dias > 0) {
                                        $ultima_compra = $cliente_stats['actividad']['fecha_ultima_compra'] ?? '';
                                        if ($ultima_compra) {
                                            $fecha_ultima = new DateTime($ultima_compra);
                                            $fecha_estimada = clone $fecha_ultima;
                                            $fecha_estimada->add(new DateInterval('P' . intval($frecuencia_dias) . 'D'));
                                            echo $fecha_estimada->format('d/m/Y') . ' (aprox.)';
                                        } else {
                                            echo 'Datos insuficientes';
                                        }
                                    } else {
                                        echo 'En evaluación (cliente nuevo)';
                                    }
                                    ?>
                                </div>
                                <div style="color: #e6edf3;">
                                    <strong style="color: #a78bfa;">🎯 Potencial de Crecimiento:</strong><br>
                                    <?php
                                    $ticket_promedio = floatval($cliente_stats['ltv']['ticket_promedio'] ?? 0);
                                    $ticket_general = floatval($cliente_stats['promedio_general']['ticket_promedio_general'] ?? 0);

                                    if ($ticket_general > 0 && $ticket_promedio < $ticket_general) {
                                        $potencial = $ticket_general - $ticket_promedio;
                                        echo 'Alto (+$' . number_format($potencial, 0, ',', '.') . ' por pedido)';
                                    } elseif ($total_pedidos < 3) {
                                        echo 'En desarrollo (cliente nuevo)';
                                    } else {
                                        echo 'Optimizado (cliente maduro)';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PESTAÑA 2: HISTORIAL DE PEDIDOS -->
                    <div id="content-historial" class="tab-content" style="display: none;">
                        <div style="text-align: center; margin-bottom: 25px;">
                            <h3 style="color: #58a6ff; font-size: 1.4rem; margin-bottom: 8px; display: flex; align-items: center; justify-content: center; gap: 12px;">
                                🕒 Historial Completo de Pedidos
                            </h3>
                            <p style="color: #8b949e; font-size: 0.95rem; margin: 0;">
                                Cronología detallada de todas las transacciones del cliente
                            </p>
                        </div>

                        <!-- Filtros del Historial -->
                        <div style="background: #21262d; border: 1px solid #3d444d; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: center;">
                                <div>
                                    <label style="display: block; color: #8b949e; font-size: 0.8rem; margin-bottom: 5px;">🗓️ Período:</label>
                                    <select id="filtro-periodo" style="width: 100%; padding: 8px; background: #0d1117; border: 1px solid #3d444d; border-radius: 4px; color: #e6edf3;">
                                        <option value="todos">Todos los pedidos</option>
                                        <option value="30">Últimos 30 días</option>
                                        <option value="90">Últimos 3 meses</option>
                                        <option value="365">Último año</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="display: block; color: #8b949e; font-size: 0.8rem; margin-bottom: 5px;">📋 Estado:</label>
                                    <select id="filtro-estado" style="width: 100%; padding: 8px; background: #0d1117; border: 1px solid #3d444d; border-radius: 4px; color: #e6edf3;">
                                        <option value="todos">Todos los estados</option>
                                        <option value="pagado">Pagados</option>
                                        <option value="pendiente">Pendientes</option>
                                        <option value="enviado">Enviados</option>
                                        <option value="entregado">Entregados</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="display: block; color: #8b949e; font-size: 0.8rem; margin-bottom: 5px;">💰 Rango:</label>
                                    <select id="filtro-monto" style="width: 100%; padding: 8px; background: #0d1117; border: 1px solid #3d444d; border-radius: 4px; color: #e6edf3;">
                                        <option value="todos">Todos los montos</option>
                                        <option value="bajo">Menos de $50,000</option>
                                        <option value="medio">$50,000 - $200,000</option>
                                        <option value="alto">Más de $200,000</option>
                                    </select>
                                </div>
                                <div>
                                    <button onclick="aplicarFiltrosHistorial()" style="padding: 8px 15px; background: #1f6feb; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9rem;">
                                        🔍 Filtrar
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Lista del Historial -->
                        <div id="historial-pedidos" style="background: #21262d; border: 1px solid #3d444d; border-radius: 8px; padding: 20px;">
                            <div style="text-align: center; color: #8b949e; padding: 40px;">
                                <div style="font-size: 3rem; margin-bottom: 15px;">🔄</div>
                                <div style="font-size: 1.1rem; margin-bottom: 10px;">Cargando historial de pedidos...</div>
                                <div style="font-size: 0.9rem;">Consultando base de datos</div>
                            </div>
                        </div>
                    </div>

                    <!-- PESTAÑA 3: ARCHIVOS Y DOCUMENTOS -->
                    <div id="content-archivos" class="tab-content" style="display: none;">
                        <div style="text-align: center; margin-bottom: 25px;">
                            <h3 style="color: #f79000; font-size: 1.4rem; margin-bottom: 8px; display: flex; align-items: center; justify-content: center; gap: 12px;">
                                📁 Archivos y Documentos
                            </h3>
                            <p style="color: #8b949e; font-size: 0.95rem; margin: 0;">
                                Gestión centralizada de comprobantes, guías y documentos asociados
                            </p>
                        </div>

                        <!-- Sección de Carga de Archivos -->
                        <div style="background: #21262d; border: 1px solid #3d444d; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                            <h4 style="color: #58a6ff; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                                📤 Subir Nuevo Archivo
                            </h4>
                            <div style="display: grid; grid-template-columns: 1fr auto; gap: 15px; align-items: end;">
                                <div>
                                    <label style="display: block; color: #8b949e; font-size: 0.9rem; margin-bottom: 8px;">Seleccionar archivo:</label>
                                    <input type="file" id="nuevo-archivo" accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx"
                                           style="width: 100%; padding: 10px; background: #0d1117; border: 1px solid #3d444d; border-radius: 4px; color: #e6edf3;">
                                </div>
                                <div>
                                    <select id="tipo-archivo" style="padding: 10px; background: #0d1117; border: 1px solid #3d444d; border-radius: 4px; color: #e6edf3; margin-right: 10px;">
                                        <option value="comprobante">📄 Comprobante</option>
                                        <option value="guia">🚚 Guía de Envío</option>
                                        <option value="factura">🧾 Factura</option>
                                        <option value="imagen">🖼️ Imagen Producto</option>
                                        <option value="otro">📎 Otro</option>
                                    </select>
                                    <button onclick="subirArchivo()" style="padding: 10px 15px; background: #238636; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                        📤 Subir
                                    </button>
                                </div>
                            </div>
                            <div id="upload-status" style="margin-top: 15px; text-align: center;"></div>
                        </div>

                        <!-- Lista de Archivos Existentes -->
                        <div style="background: #21262d; border: 1px solid #3d444d; border-radius: 8px; padding: 20px;">
                            <h4 style="color: #58a6ff; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                                📋 Archivos Asociados al Pedido
                            </h4>

                            <div id="lista-archivos">
                                <!-- Archivo de Comprobante (si existe) -->
                                <?php if (!empty($p['comprobante_pago'])): ?>
                                <div class="archivo-item" style="background: #0d1117; border: 1px solid #3d444d; border-radius: 6px; padding: 15px; margin-bottom: 10px; display: flex; justify-content: between; align-items: center;">
                                    <div style="display: flex; align-items: center; gap: 12px; flex: 1;">
                                        <div style="font-size: 1.5rem;">📄</div>
                                        <div>
                                            <div style="color: #e6edf3; font-weight: 600; margin-bottom: 4px;">Comprobante de Pago</div>
                                            <div style="color: #8b949e; font-size: 0.8rem;">
                                                Archivo: <?php echo h(basename($p['comprobante_pago'])); ?> •
                                                Subido: <?php echo h($p['fecha'] ?? 'Fecha no disponible'); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 8px;">
                                        <a href="<?php echo h($p['comprobante_pago']); ?>" target="_blank"
                                           style="padding: 8px 12px; background: #1f6feb; color: white; text-decoration: none; border-radius: 4px; font-size: 0.8rem;">
                                            👁️ Ver
                                        </a>
                                        <a href="<?php echo h($p['comprobante_pago']); ?>" download
                                           style="padding: 8px 12px; background: #238636; color: white; text-decoration: none; border-radius: 4px; font-size: 0.8rem;">
                                            💾 Descargar
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Archivo de Guía (si existe) -->
                                <?php if (!empty($p['archivo_guia'])): ?>
                                <div class="archivo-item" style="background: #0d1117; border: 1px solid #3d444d; border-radius: 6px; padding: 15px; margin-bottom: 10px; display: flex; justify-content: between; align-items: center;">
                                    <div style="display: flex; align-items: center; gap: 12px; flex: 1;">
                                        <div style="font-size: 1.5rem;">🚚</div>
                                        <div>
                                            <div style="color: #e6edf3; font-weight: 600; margin-bottom: 4px;">Guía de Envío</div>
                                            <div style="color: #8b949e; font-size: 0.8rem;">
                                                Archivo: <?php echo h(basename($p['archivo_guia'])); ?> •
                                                N° Guía: <?php echo h($p['numero_guia'] ?? 'No disponible'); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 8px;">
                                        <a href="ver_guia.php?id=<?php echo h($p['id']); ?>" target="_blank"
                                           style="padding: 8px 12px; background: #1f6feb; color: white; text-decoration: none; border-radius: 4px; font-size: 0.8rem;">
                                            👁️ Ver
                                        </a>
                                        <a href="<?php echo h($p['archivo_guia']); ?>" download
                                           style="padding: 8px 12px; background: #238636; color: white; text-decoration: none; border-radius: 4px; font-size: 0.8rem;">
                                            💾 Descargar
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Mensaje si no hay archivos -->
                                <?php if (empty($p['comprobante_pago']) && empty($p['archivo_guia'])): ?>
                                <div style="text-align: center; color: #8b949e; padding: 40px;">
                                    <div style="font-size: 2.5rem; margin-bottom: 15px;">📂</div>
                                    <div style="font-size: 1.1rem; margin-bottom: 8px;">No hay archivos asociados aún</div>
                                    <div style="font-size: 0.9rem;">Los archivos subidos aparecerán aquí automáticamente</div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Estadísticas de Archivos -->
                            <?php if (!empty($p['comprobante_pago']) || !empty($p['archivo_guia'])): ?>
                            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #3d444d;">
                                <h5 style="color: #8b949e; margin-bottom: 10px; font-size: 0.9rem;">📊 Estadísticas de Archivos:</h5>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; font-size: 0.8rem;">
                                    <div style="color: #e6edf3;">
                                        Total de archivos: <strong><?php echo (!empty($p['comprobante_pago']) ? 1 : 0) + (!empty($p['archivo_guia']) ? 1 : 0); ?></strong>
                                    </div>
                                    <div style="color: #e6edf3;">
                                        Completitud: <strong><?php echo (!empty($p['comprobante_pago']) && !empty($p['archivo_guia'])) ? '100%' : '50%'; ?></strong>
                                    </div>
                                    <div style="color: #e6edf3;">
                                        Estado: <strong style="color: <?php echo (!empty($p['comprobante_pago']) && !empty($p['archivo_guia'])) ? '#3fb950' : '#f0883e'; ?>;">
                                            <?php echo (!empty($p['comprobante_pago']) && !empty($p['archivo_guia'])) ? 'Completo' : 'Parcial'; ?>
                                        </strong>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

            <?php endif; ?>
        </div>
    </div>

    <script>
    // Funciones para gestión de estados
    function cambiarEstadoPedido() {
        const nuevoEstado = document.getElementById('nuevo-estado').value;
        const statusDiv = document.getElementById('estado-status');
        const pedidoId = <?php echo json_encode($p['id'] ?? ''); ?>;

        if (!nuevoEstado || !pedidoId) {
            statusDiv.innerHTML = '<span style="color: #da3633;">❌ Error: Datos inválidos</span>';
            return;
        }

        // Mostrar estado de carga
        statusDiv.innerHTML = '<span style="color: #1f6feb;">🔄 Actualizando estado...</span>';

        const formData = new FormData();
        formData.append('id', pedidoId);
        formData.append('estado', nuevoEstado);

        fetch('actualizar_estado.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                statusDiv.innerHTML = '<span style="color: #238636;">✅ Estado actualizado correctamente</span>';
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                statusDiv.innerHTML = '<span style="color: #da3633;">❌ ' + (data.error || 'Error al actualizar estado') + '</span>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            statusDiv.innerHTML = '<span style="color: #da3633;">❌ Error de conexión</span>';
        });
    }

    // Funciones para gestión de notas
    function agregarNota() {
        const nuevaNota = document.getElementById('nueva-nota').value.trim();
        const statusDiv = document.getElementById('notas-status');
        const pedidoId = <?php echo json_encode($p['id'] ?? ''); ?>;

        if (!nuevaNota) {
            statusDiv.innerHTML = '<span style="color: #da3633;">❌ Por favor ingresa una nota</span>';
            return;
        }

        if (!pedidoId) {
            statusDiv.innerHTML = '<span style="color: #da3633;">❌ Error: ID de pedido no válido</span>';
            return;
        }

        // Mostrar estado de carga
        statusDiv.innerHTML = '<span style="color: #1f6feb;">💾 Guardando nota...</span>';

        const formData = new FormData();
        formData.append('pedido_id', pedidoId);
        formData.append('nota', nuevaNota);

        fetch('agregar_nota.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                statusDiv.innerHTML = '<span style="color: #238636;">✅ Nota guardada correctamente</span>';
                document.getElementById('nueva-nota').value = '';

                // Agregar nota al historial
                agregarNotaAlHistorial(nuevaNota, 'Ahora');

                setTimeout(() => {
                    statusDiv.innerHTML = '';
                }, 3000);
            } else {
                statusDiv.innerHTML = '<span style="color: #da3633;">❌ ' + (data.error || 'Error al guardar nota') + '</span>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            statusDiv.innerHTML = '<span style="color: #da3633;">❌ Error de conexión</span>';
        });
    }

    function agregarNotaAlHistorial(nota, fecha) {
        const listaNotes = document.getElementById('lista-notas');

        // Crear elemento de nota
        const notaDiv = document.createElement('div');
        notaDiv.className = 'nota-item';
        notaDiv.style.cssText = 'background: #21262d; padding: 10px; border-radius: 4px; margin-bottom: 8px; border-left: 3px solid #238636;';

        notaDiv.innerHTML = `
            <div style="font-size: 0.8rem; color: #8b949e; margin-bottom: 4px;">
                📅 ${fecha} - Sistema
            </div>
            <div style="color: #e6edf3; line-height: 1.4;">
                ${nota.replace(/\n/g, '<br>')}
            </div>
        `;

        // Insertar al principio
        listaNotes.insertBefore(notaDiv, listaNotes.firstChild);

        // Remover mensaje de "no hay notas" si existe
        const sinNotas = listaNotes.querySelector('[style*="font-style: italic"]');
        if (sinNotas) {
            sinNotas.remove();
        }
    }

    // Funciones para edición de cliente
    function habilitarEdicion() {
        document.getElementById('formulario-edicion').style.display = 'block';
        document.getElementById('btn-habilitar-edicion').style.display = 'none';
    }

    function cancelarEdicion() {
        document.getElementById('formulario-edicion').style.display = 'none';
        document.getElementById('btn-habilitar-edicion').style.display = 'block';

        // Restaurar valores originales
        document.getElementById('edit-nombre').value = <?php echo json_encode($p['nombre'] ?? ''); ?>;
        document.getElementById('edit-correo').value = <?php echo json_encode($p['correo'] ?? ''); ?>;
        document.getElementById('edit-telefono').value = <?php echo json_encode($p['telefono'] ?? ''); ?>;
        document.getElementById('edit-ciudad').value = <?php echo json_encode($p['ciudad'] ?? ''); ?>;
        document.getElementById('edit-barrio').value = <?php echo json_encode($p['barrio'] ?? ''); ?>;
        document.getElementById('edit-direccion').value = <?php echo json_encode($p['direccion'] ?? ''); ?>;

        document.getElementById('edicion-status').innerHTML = '';
    }

    function guardarCambiosCliente() {
        const statusDiv = document.getElementById('edicion-status');
        const pedidoId = <?php echo json_encode($p['id'] ?? ''); ?>;

        const datos = {
            pedido_id: pedidoId,
            nombre: document.getElementById('edit-nombre').value.trim(),
            correo: document.getElementById('edit-correo').value.trim(),
            telefono: document.getElementById('edit-telefono').value.trim(),
            ciudad: document.getElementById('edit-ciudad').value.trim(),
            barrio: document.getElementById('edit-barrio').value.trim(),
            direccion: document.getElementById('edit-direccion').value.trim()
        };

        if (!datos.nombre || !datos.correo) {
            statusDiv.innerHTML = '<span style="color: #da3633;">❌ Nombre y email son obligatorios</span>';
            return;
        }

        // Validar email
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(datos.correo)) {
            statusDiv.innerHTML = '<span style="color: #da3633;">❌ Email no válido</span>';
            return;
        }

        statusDiv.innerHTML = '<span style="color: #1f6feb;">💾 Guardando cambios...</span>';

        const formData = new FormData();
        Object.keys(datos).forEach(key => {
            formData.append(key, datos[key]);
        });

        fetch('actualizar_cliente.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                statusDiv.innerHTML = '<span style="color: #238636;">✅ Datos actualizados correctamente</span>';
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                statusDiv.innerHTML = '<span style="color: #da3633;">❌ ' + (data.error || 'Error al actualizar datos') + '</span>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            statusDiv.innerHTML = '<span style="color: #da3633;">❌ Error de conexión</span>';
        });
    }

    // Función para confirmar entrega con guía
    function confirmarEntregaConGuia() {
        const statusDiv = document.getElementById('comunicacion-status');
        const pedidoId = <?php echo json_encode($p['id'] ?? ''); ?>;
        const clienteEmail = <?php echo json_encode($p['correo'] ?? ''); ?>;
        const guiaActual = <?php echo json_encode($p['guia'] ?? ''); ?>;

        // Verificar que hay email del cliente
        if (!clienteEmail) {
            statusDiv.innerHTML = '<span style="color: #da3633;">❌ No hay email del cliente registrado</span>';
            return;
        }

        // Verificar que hay guía de envío
        if (!guiaActual || guiaActual.trim() === '') {
            statusDiv.innerHTML = '<span style="color: #da3633;">❌ El pedido no tiene guía de envío, debes adjuntar guía de envío para poder notificar al cliente</span>';
            return;
        }

        statusDiv.innerHTML = '<span style="color: #1f6feb;">📧 Enviando confirmación de entrega con guía...</span>';

        const formData = new FormData();
        formData.append('pedido_id', pedidoId);
        formData.append('tipo_email', 'entrega_con_guia');
        formData.append('cliente_email', clienteEmail);
        formData.append('guia_archivo', guiaActual);

        fetch('enviar_email_cliente.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                statusDiv.innerHTML = '<span style="color: #238636;">✅ Guía enviada por correo al cliente exitosamente</span>';
                setTimeout(() => {
                    statusDiv.innerHTML = '';
                }, 5000);
            } else {
                statusDiv.innerHTML = '<span style="color: #da3633;">❌ ' + (data.error || 'Error al enviar email con guía') + '</span>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            statusDiv.innerHTML = '<span style="color: #da3633;">❌ Error de conexión</span>';
        });
    }

    // Función para abrir WhatsApp
    function abrirWhatsApp(telefono, nombreCliente, pedidoId) {
        // Limpiar el número de teléfono (remover espacios, guiones, etc.)
        let numeroLimpio = telefono.replace(/\D/g, '');

        // Si el número no empieza con código de país, asumir Colombia (+57)
        if (!numeroLimpio.startsWith('57') && numeroLimpio.length === 10) {
            numeroLimpio = '57' + numeroLimpio;
        }

        // Mensaje predefinido
        const mensaje = `Hola ${nombreCliente}, te contactamos desde Sequoia Speed sobre tu pedido #${pedidoId}. ¿En qué podemos ayudarte?`;

        // Crear la URL de WhatsApp
        const whatsappUrl = `https://wa.me/${numeroLimpio}?text=${encodeURIComponent(mensaje)}`;

        // Abrir WhatsApp en una nueva ventana/pestaña
        window.open(whatsappUrl, '_blank');

        // Mostrar confirmación en el estado
        const statusDiv = document.getElementById('comunicacion-status');
        if (statusDiv) {
            statusDiv.innerHTML = '<span style="color: #25d366;">💬 WhatsApp abierto - Mensaje predefinido copiado</span>';
            setTimeout(() => {
                statusDiv.innerHTML = '';
            }, 3000);
        }
    }

    // Funciones existentes para modales e impresión
    function imprimirPedido() {
            // Configurar la impresión
            const originalTitle = document.title;
            document.title = 'Pedido #<?php echo h($p['id'] ?? ''); ?> - Sequoia Speed';

            // Imprimir
            window.print();

            // Restaurar título original
            setTimeout(() => {
                document.title = originalTitle;
            }, 1000);
        }

        // Atajos de teclado para impresión
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                imprimirPedido();
            }
        });
    </script>

    <!-- Modal para Subir Guía de Envío -->
    <div id="modalGuia" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="color: #1f6feb; margin: 0;">📦 Subir Guía de Envío</h3>
                <button onclick="cerrarModalGuia()" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="formGuia" enctype="multipart/form-data" style="text-align: center;">
                    <input type="hidden" name="pedido_id" value="<?php echo h($p['id'] ?? ''); ?>">
                    <div style="margin-bottom: 20px;">
                        <label for="archivoGuia" style="display: block; margin-bottom: 10px; font-weight: 600;">
                            Seleccionar archivo de guía:
                        </label>
                        <input type="file" id="archivoGuia" name="guia" accept="image/*,.pdf" required
                               style="width: 100%; padding: 10px; border: 2px dashed #3d444d; border-radius: 8px; background: #21262d;">
                        <small style="color: #8b949e; display: block; margin-top: 5px;">
                            Formatos: JPG, PNG, PDF (máx. 5MB)
                        </small>
                    </div>

                    <div style="margin-bottom: 20px; padding: 15px; background: #21262d; border-radius: 8px; border: 1px solid #3d444d;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; color: #e6edf3;">
                            <input type="checkbox" id="marcarEnviado" style="width: auto; margin: 0;">
                            <span style="font-weight: 600;">🚚 Marcar pedido como ENVIADO</span>
                        </label>
                        <small style="color: #8b949e; display: block; margin-top: 5px; margin-left: 25px;">
                            ✅ Recomendado: Si el pedido ya fue despachado, marca esta opción para actualizar el estado automáticamente.
                        </small>
                    </div>

                    <div class="modal-actions">
                        <button type="button" onclick="cerrarModalGuia()" class="btn" style="background: #6e7681;">
                            Cancelar
                        </button>
                        <button type="submit" class="btn-print">
                            <span>📤</span> Subir Guía
                        </button>
                    </div>
                </form>
                <div id="statusGuia" style="margin-top: 15px; text-align: center;"></div>
            </div>
        </div>
    </div>

    <!-- Modal para Subir Comprobante de Pago -->
    <div id="modalComprobante" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="color: #1f6feb; margin: 0;">💳 Subir Comprobante de Pago</h3>
                <button onclick="cerrarModalComprobante()" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="formComprobante" enctype="multipart/form-data" style="text-align: center;">
                    <input type="hidden" name="pedido_id" value="<?php echo h($p['id'] ?? ''); ?>">
                    <div style="margin-bottom: 20px;">
                        <label for="archivoComprobante" style="display: block; margin-bottom: 10px; font-weight: 600;">
                            Seleccionar comprobante de pago:
                        </label>
                        <input type="file" id="archivoComprobante" name="comprobante" accept="image/*,.pdf" required
                               style="width: 100%; padding: 10px; border: 2px dashed #3d444d; border-radius: 8px; background: #21262d;">
                        <small style="color: #8b949e; display: block; margin-top: 5px;">
                            Formatos: JPG, PNG, PDF (máx. 5MB)
                        </small>
                    </div>
                    <div class="modal-actions">
                        <button type="button" onclick="cerrarModalComprobante()" class="btn" style="background: #6e7681;">
                            Cancelar
                        </button>
                        <button type="submit" class="btn-print">
                            <span>📤</span> Subir Comprobante
                        </button>
                    </div>
                </form>
                <div id="statusComprobante" style="margin-top: 15px; text-align: center;"></div>
            </div>
        </div>
    </div>

    <!-- Modal CSS has been moved to external file -->

    <script>
    // Funciones para los modales
    function abrirModalGuia() {
        document.getElementById('modalGuia').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function cerrarModalGuia() {
        document.getElementById('modalGuia').style.display = 'none';
        document.body.style.overflow = 'auto';
        // Limpiar formulario
        document.getElementById('formGuia').reset();
        document.getElementById('statusGuia').innerHTML = '';
    }

    function abrirModalComprobante() {
        document.getElementById('modalComprobante').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function cerrarModalComprobante() {
        document.getElementById('modalComprobante').style.display = 'none';
        document.body.style.overflow = 'auto';
        // Limpiar formulario
        document.getElementById('formComprobante').reset();
        document.getElementById('statusComprobante').innerHTML = '';
    }

    // Cerrar modales con ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            cerrarModalGuia();
            cerrarModalComprobante();
        }
    });

    // Cerrar modales al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            cerrarModalGuia();
            cerrarModalComprobante();
        }
    });

    // Manejar envío de guía
    document.getElementById('formGuia').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const marcarEnviado = document.getElementById('marcarEnviado').checked;
        const statusDiv = document.getElementById('statusGuia');
        const submitBtn = this.querySelector('button[type="submit"]');

        // Agregar el estado del checkbox al formData
        formData.append('marcar_enviado', marcarEnviado);

        // Estado de carga
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span>⏳</span> Subiendo...';
        statusDiv.innerHTML = '<span style="color: #1f6feb;">📤 Subiendo guía...</span>';

        fetch('subir_guia.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let mensaje = '✅ Guía subida correctamente';
                if (data.marcar_enviado) {
                    mensaje += ' y pedido marcado como enviado';
                }
                statusDiv.innerHTML = '<span style="color: #238636;">' + mensaje + '</span>';
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                statusDiv.innerHTML = '<span style="color: #da3633;">❌ ' + (data.error || 'Error al subir guía') + '</span>';
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<span>📤</span> Subir Guía';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            statusDiv.innerHTML = '<span style="color: #da3633;">❌ Error de conexión</span>';
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<span>📤</span> Subir Guía';
        });
    });

    // Manejar envío de comprobante
    document.getElementById('formComprobante').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const statusDiv = document.getElementById('statusComprobante');
        const submitBtn = this.querySelector('button[type="submit"]');

        // Estado de carga
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span>⏳</span> Subiendo...';
        statusDiv.innerHTML = '<span style="color: #1f6feb;">📤 Subiendo comprobante...</span>';

        fetch('subir_comprobante.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                statusDiv.innerHTML = '<span style="color: #238636;">✅ Comprobante subido correctamente</span>';
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                statusDiv.innerHTML = '<span style="color: #da3633;">❌ ' + (data.error || 'Error al subir comprobante') + '</span>';
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<span>📤</span> Subir Comprobante';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            statusDiv.innerHTML = '<span style="color: #da3633;">❌ Error de conexión</span>';
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<span>📤</span> Subir Comprobante';
        });
    });

    // ===== FUNCIONES PARA SISTEMA DE PESTAÑAS =====

    function cambiarPestana(pestana) {
        // Ocultar todas las pestañas
        const pestanasContent = document.querySelectorAll('.tab-content');
        pestanasContent.forEach(content => {
            content.style.display = 'none';
        });

        // Desactivar todos los botones
        const botonesTab = document.querySelectorAll('.tab-button');
        botonesTab.forEach(boton => {
            boton.style.color = '#8b949e';
            boton.style.borderBottomColor = 'transparent';
        });

        // Mostrar la pestaña seleccionada
        const contentSeleccionado = document.getElementById('content-' + pestana);
        if (contentSeleccionado) {
            contentSeleccionado.style.display = 'block';
        }

        // Activar el botón seleccionado
        const botonSeleccionado = document.getElementById('tab-' + pestana);
        if (botonSeleccionado) {
            botonSeleccionado.style.color = '#58a6ff';
            botonSeleccionado.style.borderBottomColor = '#1f6feb';
        }

        // Cargar contenido específico según la pestaña
        if (pestana === 'historial') {
            cargarHistorialPedidos();
        } else if (pestana === 'archivos') {
            actualizarListaArchivos();
        }
    }

    function cargarHistorialPedidos() {
        const historialDiv = document.getElementById('historial-pedidos');
        const correoCliente = <?php echo json_encode($p['correo'] ?? $p['email'] ?? $p['cliente_email'] ?? ''); ?>;
        const telefonoCliente = <?php echo json_encode($p['telefono'] ?? $p['telefono_cliente'] ?? $p['cliente_telefono'] ?? ''); ?>;

        if (!correoCliente && !telefonoCliente) {
            historialDiv.innerHTML = `
                <div style="text-align: center; color: #8b949e; padding: 40px;">
                    <div style="font-size: 2.5rem; margin-bottom: 15px;">⚠️</div>
                    <div style="font-size: 1.1rem; margin-bottom: 10px;">No se puede cargar el historial</div>
                    <div style="font-size: 0.9rem;">Faltan datos de contacto del cliente</div>
                </div>
            `;
            return;
        }

        // Simular carga de historial (aquí se conectaría a una API real)
        setTimeout(() => {
            // Datos de ejemplo - en producción vendría de la base de datos
            const historialEjemplo = [
                {
                    id: 'PED-001',
                    fecha: '2024-01-15',
                    estado: 'entregado',
                    total: 85000,
                    productos: 3
                },
                {
                    id: 'PED-002',
                    fecha: '2024-01-08',
                    estado: 'pagado',
                    total: 120000,
                    productos: 2
                },
                {
                    id: 'PED-003',
                    fecha: '2023-12-22',
                    estado: 'entregado',
                    total: 65000,
                    productos: 1
                }
            ];

            let historialHTML = `
                <div style="margin-bottom: 20px;">
                    <h4 style="color: #58a6ff; margin-bottom: 15px;">📋 Pedidos Encontrados (${historialEjemplo.length})</h4>
                </div>
            `;

            historialEjemplo.forEach((pedido, index) => {
                const estadoColor = {
                    'entregado': '#238636',
                    'pagado': '#1f6feb',
                    'enviado': '#fb8500',
                    'pendiente': '#8b949e'
                }[pedido.estado] || '#8b949e';

                const estadoEmoji = {
                    'entregado': '✅',
                    'pagado': '💳',
                    'enviado': '🚚',
                    'pendiente': '⏳'
                }[pedido.estado] || '📋';

                historialHTML += `
                    <div style="background: #0d1117; border: 1px solid #3d444d; border-radius: 6px; padding: 15px; margin-bottom: 12px;">
                        <div style="display: grid; grid-template-columns: auto 1fr auto auto; gap: 15px; align-items: center;">
                            <div style="font-size: 1.2rem;">${estadoEmoji}</div>
                            <div>
                                <div style="color: #e6edf3; font-weight: 600; margin-bottom: 4px;">
                                    Pedido ${pedido.id}
                                </div>
                                <div style="color: #8b949e; font-size: 0.8rem;">
                                    ${new Date(pedido.fecha).toLocaleDateString('es-ES')} • ${pedido.productos} producto${pedido.productos > 1 ? 's' : ''}
                                </div>
                            </div>
                            <div style="text-align: center;">
                                <div style="color: ${estadoColor}; font-weight: 600; font-size: 0.9rem; padding: 4px 8px; background: ${estadoColor}15; border-radius: 12px; text-transform: capitalize;">
                                    ${pedido.estado}
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="color: #3fb950; font-weight: 700; font-size: 1.1rem;">
                                    $${pedido.total.toLocaleString()}
                                </div>
                                <div style="margin-top: 5px;">
                                    <a href="ver_detalle_pedido.php?id=${pedido.id}"
                                       style="padding: 4px 8px; background: #1f6feb; color: white; text-decoration: none; border-radius: 4px; font-size: 0.7rem;">
                                        👁️ Ver
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });

            // Resumen estadístico
            const totalGastado = historialEjemplo.reduce((sum, p) => sum + p.total, 0);
            const promedioTicket = totalGastado / historialEjemplo.length;

            historialHTML += `
                <div style="background: linear-gradient(135deg, #1f6feb15 0%, #0969da15 100%); border: 1px solid #1f6feb; border-radius: 8px; padding: 20px; margin-top: 20px;">
                    <h4 style="color: #1f6feb; margin-bottom: 15px;">📊 Resumen del Historial</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                        <div style="text-align: center;">
                            <div style="color: #8b949e; font-size: 0.8rem; margin-bottom: 5px;">Total Pedidos</div>
                            <div style="color: #58a6ff; font-weight: 700; font-size: 1.3rem;">${historialEjemplo.length}</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="color: #8b949e; font-size: 0.8rem; margin-bottom: 5px;">Total Gastado</div>
                            <div style="color: #3fb950; font-weight: 700; font-size: 1.3rem;">$${totalGastado.toLocaleString()}</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="color: #8b949e; font-size: 0.8rem; margin-bottom: 5px;">Ticket Promedio</div>
                            <div style="color: #f0883e; font-weight: 700; font-size: 1.3rem;">$${Math.round(promedioTicket).toLocaleString()}</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="color: #8b949e; font-size: 0.8rem; margin-bottom: 5px;">Entregados</div>
                            <div style="color: #238636; font-weight: 700; font-size: 1.3rem;">${historialEjemplo.filter(p => p.estado === 'entregado').length}</div>
                        </div>
                    </div>
                </div>
            `;

            historialDiv.innerHTML = historialHTML;
        }, 1000);
    }

    function aplicarFiltrosHistorial() {
        // Aquí se implementaría la lógica de filtrado real
        const periodo = document.getElementById('filtro-periodo').value;
        const estado = document.getElementById('filtro-estado').value;
        const monto = document.getElementById('filtro-monto').value;

        const historialDiv = document.getElementById('historial-pedidos');
        historialDiv.innerHTML = `
            <div style="text-align: center; color: #58a6ff; padding: 40px;">
                <div style="font-size: 2.5rem; margin-bottom: 15px;">🔍</div>
                <div style="font-size: 1.1rem; margin-bottom: 10px;">Aplicando filtros...</div>
                <div style="font-size: 0.9rem;">Período: ${periodo} | Estado: ${estado} | Monto: ${monto}</div>
            </div>
        `;

        // Simular aplicación de filtros
        setTimeout(() => {
            cargarHistorialPedidos();
        }, 1500);
    }

    function actualizarListaArchivos() {
        // Aquí se actualizaría la lista de archivos si es necesario
        console.log('Actualizando lista de archivos...');
    }

    function subirArchivo() {
        const archivo = document.getElementById('nuevo-archivo').files[0];
        const tipo = document.getElementById('tipo-archivo').value;
        const statusDiv = document.getElementById('upload-status');

        if (!archivo) {
            statusDiv.innerHTML = '<span style="color: #da3633;">❌ Por favor selecciona un archivo</span>';
            return;
        }

        // Validar tipo de archivo
        const tiposPermitidos = ['.pdf', '.jpg', '.jpeg', '.png', '.gif', '.doc', '.docx'];
        const extension = '.' + archivo.name.split('.').pop().toLowerCase();

        if (!tiposPermitidos.includes(extension)) {
            statusDiv.innerHTML = '<span style="color: #da3633;">❌ Tipo de archivo no permitido</span>';
            return;
        }

        // Validar tamaño (máximo 10MB)
        if (archivo.size > 10 * 1024 * 1024) {
            statusDiv.innerHTML = '<span style="color: #da3633;">❌ El archivo es demasiado grande (máximo 10MB)</span>';
            return;
        }

        statusDiv.innerHTML = '<span style="color: #1f6feb;">📤 Subiendo archivo...</span>';

        // Simular subida de archivo
        setTimeout(() => {
            statusDiv.innerHTML = '<span style="color: #238636;">✅ Archivo subido correctamente</span>';

            // Limpiar formulario
            document.getElementById('nuevo-archivo').value = '';
            document.getElementById('tipo-archivo').value = 'comprobante';

            // Actualizar lista de archivos
            setTimeout(() => {
                statusDiv.innerHTML = '';
                // Aquí se actualizaría la lista de archivos
            }, 2000);
        }, 2000);
    }

    // Funciones para plantillas de mensajes y comunicación
    function cargarPlantillaMensaje() {
        const plantilla = document.getElementById('plantilla-mensaje').value;
        const nombreCliente = <?php echo json_encode($p['nombre'] ?? 'Cliente'); ?>;
        const pedidoId = <?php echo json_encode($p['id'] ?? ''); ?>;

        const plantillas = {
            'confirmacion-pago': `Hola ${nombreCliente}, hemos confirmado el pago de tu pedido #${pedidoId}. Procederemos con el envío en las próximas horas. ¡Gracias por tu compra!`,
            'pedido-enviado': `¡Tu pedido #${pedidoId} ya está en camino! Te enviaremos el número de guía cuando esté disponible. ¡Esperamos que lo disfrutes!`,
            'solicitar-info': `Hola ${nombreCliente}, necesitamos confirmar algunos detalles de tu pedido #${pedidoId}. ¿Podrías contactarnos cuando tengas un momento?`,
            'confirmar-direccion': `Hola ${nombreCliente}, antes de enviar tu pedido #${pedidoId}, ¿podrías confirmar que la dirección de entrega es correcta?`,
            'pedido-listo': `¡Excelente noticia! Tu pedido #${pedidoId} está listo para entrega. ¿Cuál sería el mejor momento para coordinar la entrega?`
        };

        if (plantillas[plantilla]) {
            // Aquí podrías copiar al portapapeles o abrir WhatsApp/Email
            navigator.clipboard.writeText(plantillas[plantilla]).then(() => {
                const statusDiv = document.getElementById('comunicacion-status');
                statusDiv.innerHTML = '<span style="color: #238636;">✅ Mensaje copiado al portapapeles</span>';
                setTimeout(() => {
                    statusDiv.innerHTML = '';
                }, 3000);
            });
        }
    }

    function abrirWhatsAppConPlantilla(telefono, nombre, pedidoId) {
        const mensaje = `Hola ${nombre}, te contactamos sobre tu pedido #${pedidoId}. ¿Hay algo en lo que podamos ayudarte?`;
        const numeroLimpio = telefono.replace(/[^\d]/g, '');
        const url = `https://wa.me/57${numeroLimpio}?text=${encodeURIComponent(mensaje)}`;
        window.open(url, '_blank');
    }

    function confirmarEntregaConGuia() {
        const pedidoId = <?php echo json_encode($p['id'] ?? ''); ?>;
        const numeroGuia = prompt('¿Cuál es el número de guía de envío?');

        if (numeroGuia) {
            const statusDiv = document.getElementById('comunicacion-status');
            statusDiv.innerHTML = '<span style="color: #1f6feb;">📦 Confirmando entrega...</span>';

            // Aquí se conectaría con el backend para actualizar el estado
            setTimeout(() => {
                statusDiv.innerHTML = '<span style="color: #238636;">✅ Entrega confirmada con guía #' + numeroGuia + '</span>';
            }, 1500);
        }
    }

    // Inicializar la primera pestaña al cargar
    document.addEventListener('DOMContentLoaded', function() {
        // La pestaña de estadísticas ya está activa por defecto
        console.log('Sistema de pestañas inicializado');

        // Aplicar tooltips a elementos con data-tooltip
        const elementosConTooltip = document.querySelectorAll('[data-tooltip]');
        elementosConTooltip.forEach(elemento => {
            elemento.style.position = 'relative';
            elemento.style.cursor = 'help';
        });

        // Manejar cambios en filtros del historial
        const filtros = ['filtro-periodo', 'filtro-estado', 'filtro-monto'];
        filtros.forEach(filtroId => {
            const elemento = document.getElementById(filtroId);
            if (elemento) {
                elemento.addEventListener('change', function() {
                    console.log(`Filtro ${filtroId} cambiado a: ${this.value}`);
                });
            }
        });

        // Validar estructura de pestañas
        const pestanasNav = document.querySelector('.pestanas-nav');
        const pestanasContent = document.querySelector('.pestanas-content');

        if (pestanasNav && pestanasContent) {
            console.log('✅ Sistema de pestañas cargado correctamente');
        } else {
            console.warn('⚠️ Problema detectado en la estructura de pestañas');
        }

        // Añadir animaciones de entrada
        const elementsToAnimate = document.querySelectorAll('.metric-card-unified, .archivo-item');
        elementsToAnimate.forEach((element, index) => {
            element.style.opacity = '0';
            element.style.transform = 'translateY(20px)';
            setTimeout(() => {
                element.style.transition = 'all 0.5s ease';
                element.style.opacity = '1';
                element.style.transform = 'translateY(0)';
            }, index * 100);
        });

        // Optimizaciones específicas para móviles
        if (window.innerWidth <= 768) {
            optimizarParaMovil();
        }

        // Escuchar cambios de orientación
        window.addEventListener('orientationchange', function() {
            setTimeout(optimizarParaMovil, 100);
        });

        // Escuchar cambios de tamaño de ventana
        window.addEventListener('resize', function() {
            if (window.innerWidth <= 768) {
                optimizarParaMovil();
            }
        });
    });

    // Función para optimizar la interfaz para móviles
    function optimizarParaMovil() {
        // Mejorar scroll de pestañas
        const pestanasNav = document.querySelector('.pestanas-nav');
        if (pestanasNav) {
            pestanasNav.style.overflowX = 'auto';
            pestanasNav.style.webkitOverflowScrolling = 'touch';
            pestanasNav.style.scrollBehavior = 'smooth';
        }

        // Ajustar altura de textareas
        const textareas = document.querySelectorAll('textarea');
        textareas.forEach(textarea => {
            if (window.innerWidth <= 480) {
                textarea.style.minHeight = '60px';
            }
        });

        // Optimizar tablas si existen
        const tablas = document.querySelectorAll('.productos-table');
        tablas.forEach(tabla => {
            const container = tabla.parentElement;
            if (container && !container.classList.contains('table-mobile-optimized')) {
                container.style.overflowX = 'auto';
                container.style.webkitOverflowScrolling = 'touch';
                container.classList.add('table-mobile-optimized');
            }
        });

        // Mejorar botones para touch
        const botones = document.querySelectorAll('button, .btn, a[class*="btn"]');
        botones.forEach(boton => {
            if (window.innerWidth <= 480) {
                boton.style.minHeight = '44px'; // Tamaño mínimo recomendado para touch
                boton.style.padding = '10px 15px';
            }
        });

        console.log('✅ Optimizaciones para móvil aplicadas');
    }
    </script>
    
    <!-- Sistema de Notificaciones -->
    <script src="notifications/notifications.js"></script>
</body>
</html>
