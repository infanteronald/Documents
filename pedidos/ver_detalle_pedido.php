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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pedido_encontrado ? 'Pedido #' . h($p['id']) : 'Error'; ?> - Sequoia Speed</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #0d1117 0%, #161b22 100%);
            color: #e6edf3;
            line-height: 1.6;
            min-height: 100vh;
            padding: 20px;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
            text-size-adjust: 100%;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #21262d;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #1f6feb 0%, #0969da 100%);
            padding: 20px 30px;
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }

        .logo {
            max-width: 60px;
            max-height: 60px;
            width: auto;
            height: auto;
            display: block;
            position: relative;
            z-index: 1;
            object-fit: contain;
            flex-shrink: 0;
            margin-right: 15px;
        }

        .header-content {
            position: relative;
            z-index: 1;
        }

        .header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: white;
            margin-bottom: 4px;
            position: relative;
            z-index: 1;
        }

        .header .subtitle {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            position: relative;
            z-index: 1;
        }

        .content {
            padding: 40px;
        }

        .error {
            background: linear-gradient(135deg, #da3633 0%, #f85149 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
            font-weight: 600;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .info-card {
            background: linear-gradient(135deg, #30363d 0%, #2d333b 100%);
            border: 1px solid #3d444d;
            border-radius: 10px;
            padding: 12px 16px;
            transition: all 0.3s ease;
            min-height: 70px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .info-card:hover {
            border-color: #1f6feb;
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(31, 111, 235, 0.15);
        }

        .info-card h3 {
            color: #1f6feb;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 4px;
            line-height: 1.2;
        }

        .info-card p {
            font-size: 0.95rem;
            font-weight: 500;
            margin: 2px 0;
            line-height: 1.3;
        }

        .info-card.compact {
            padding: 10px 14px;
            min-height: 60px;
        }

        .info-card.compact h3 {
            font-size: 0.75rem;
            margin-bottom: 3px;
        }

        .info-card.compact p {
            font-size: 0.9rem;
        }

        /* Tarjeta especial para información principal */
        .info-card.primary {
            background: linear-gradient(135deg, #1f6feb 0%, #0969da 100%);
            color: white;
            border-color: #0969da;
        }

        .info-card.primary h3 {
            color: #ffffff;
            opacity: 0.9;
        }

        .info-card.primary p {
            color: #ffffff;
            font-weight: 600;
        }

        .productos-section {
            margin-top: 30px;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #e6edf3;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title::before {
            content: '📦';
            font-size: 1.2rem;
        }

        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 8px;
            margin-bottom: 0;
        }

        .productos-table {
            width: 100%;
            border-collapse: collapse;
            background: #30363d;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .productos-table th {
            background: linear-gradient(135deg, #1f6feb 0%, #0969da 100%);
            color: white;
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .productos-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #3d444d;
            vertical-align: middle;
        }

        .productos-table tbody tr:hover {
            background: #3d444d;
        }

        .productos-table tbody tr:last-child td {
            border-bottom: none;
        }

        .precio {
            font-weight: 600;
            color: #238636;
        }

        .total-section {
            margin-top: 30px;
            text-align: right;
        }

        .total-card {
            display: inline-block;
            background: linear-gradient(135deg, #1f6feb 0%, #0969da 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(35, 134, 54, 0.3);
        }

        .total-card h3 {
            font-size: 1.1rem;
            margin-bottom: 5px;
            opacity: 0.9;
        }

        .total-card .amount {
            font-size: 2rem;
            font-weight: 700;
        }

        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status.pendiente {
            background: linear-gradient(135deg, #fb8500 0%, #ffb700 100%);
            color: white;
        }

        .status.enviado {
            background: linear-gradient(135deg, #238636 0%, #2ea043 100%);
            color: white;
        }

        .status.archivado {
            background: linear-gradient(135deg, #6e7681 0%, #8b949e 100%);
            color: white;
        }

        .status.confirmado {
            background: linear-gradient(135deg, #1f6feb 0%, #0969da 100%);
            color: white;
        }

        .status.cancelado {
            background: linear-gradient(135deg, #da3633 0%, #f85149 100%);
            color: white;
        }

        .status.pago-pendiente {
            background: linear-gradient(135deg, #da3633 0%, #f85149 100%);
            color: white;
            animation: pulse 2s infinite;
        }

        .status.pago-confirmado {
            background: linear-gradient(135deg, #238636 0%, #3fb950 100%);
            color: white;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        .form-section {
            background: #30363d;
            border: 1px solid #3d444d;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            margin: 20px 0;
        }

        .form-section h2 {
            color: #1f6feb;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #e6edf3;
        }

        .form-group input {
            width: 200px;
            padding: 12px;
            border: 1px solid #3d444d;
            border-radius: 6px;
            background: #21262d;
            color: #e6edf3;
            font-size: 1rem;
            text-align: center;
        }

        .form-group input:focus {
            outline: none;
            border-color: #1f6feb;
            box-shadow: 0 0 0 2px rgba(31, 111, 235, 0.3);
        }

        .btn {
            background: linear-gradient(135deg, #1f6feb 0%, #0969da 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(31, 111, 235, 0.4);
        }

        /* Contenedor de estadísticas siempre visible */
        .estadisticas-unificadas {
            transition: all 0.3s ease;
        }

        .print-section {
            text-align: center;
        }

        .print-card {
            background: #30363d;
            border: 1px solid #3d444d;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            margin: 20px 0;
        }

        .print-card h2 {
            color: #1f6feb;
            margin-bottom: 15px;
            font-size: 1.4rem;
        }

        .btn-print {
            background: linear-gradient(135deg, #1f6feb 0%, #0969da 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 0 0 2px rgba(31, 111, 235, 0.3);
        }

        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(35, 134, 54, 0.4);
        }

        .print-icon {
            font-size: 1.2rem;
        }

        /* Estilos de impresión para tamaño carta */
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            @page {
                size: letter; /* Tamaño carta: 8.5 x 11 pulgadas */
                margin: 0.5in; /* Márgenes más pequeños */
            }

            body {
                background: white !important;
                color: #333 !important;
                font-size: 10pt; /* Texto más pequeño */
                line-height: 1.2; /* Espaciado más compacto */
            }

            .container {
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                border: none !important;
            }

            .header {
                background: #1f6feb !important;
                color: white !important;
                padding: 8pt 12pt !important; /* Reducido significativamente */
                margin-bottom: 8pt !important;
                border-radius: 0 !important;
                display: flex !important;
                align-items: center !important;
                gap: 10pt !important;
            }

            .logo {
                max-width: 40pt !important; /* Logo más pequeño */
                max-height: 40pt !important;
                width: auto !important;
                height: auto !important;
                margin: 0 !important;
                box-shadow: none !important;
                object-fit: contain !important;
                flex-shrink: 0 !important;
            }

            .header h1 {
                font-size: 14pt !important; /* Título más pequeño */
                color: white !important;
                margin: 0 !important;
            }

            .header .subtitle {
                font-size: 10pt !important;
                color: white !important;
                margin: 0 !important;
            }

            .content {
                padding: 0 !important;
            }

            .info-grid {
                display: grid !important;
                grid-template-columns: 1fr 1fr 1fr !important; /* 3 columnas para ahorrar espacio */
                gap: 6pt !important;
                margin-bottom: 8pt !important;
            }

            .info-card {
                background: #f8f9fa !important;
                border: 0.5pt solid #dee2e6 !important;
                border-radius: 2pt !important;
                padding: 6pt !important; /* Padding reducido */
                margin-bottom: 4pt !important;
                page-break-inside: avoid;
            }

            .info-card h3 {
                color: #1f6feb !important;
                font-size: 8pt !important; /* Títulos más pequeños */
                margin-bottom: 2pt !important;
                font-weight: bold !important;
            }

            .info-card p {
                font-size: 9pt !important;
                color: #333 !important;
                margin: 0 !important;
            }

            .productos-table {
                background: white !important;
                border: 0.5pt solid #333 !important;
                border-collapse: collapse !important;
                width: 100% !important;
                margin: 8pt 0 !important; /* Márgenes reducidos */
                page-break-inside: avoid;
                font-size: 9pt !important; /* Tabla más pequeña */
            }

            .productos-table th {
                background: #1f6feb !important;
                color: white !important;
                padding: 4pt 6pt !important; /* Padding muy reducido */
                border: 0.5pt solid #333 !important;
                font-size: 8pt !important; /* Texto del header más pequeño */
                font-weight: bold !important;
            }

            .productos-table td {
                padding: 3pt 6pt !important; /* Padding muy reducido */
                border: 0.5pt solid #333 !important;
                font-size: 9pt !important;
                color: #333 !important;
                vertical-align: middle !important;
            }

            .section-title {
                font-size: 11pt !important; /* Título de sección más pequeño */
                margin-bottom: 6pt !important;
                color: #333 !important;
            }

            .total-section {
                margin-top: 8pt !important; /* Menos espacio */
                text-align: right !important;
            }

            .total-card {
                background: #238636 !important;
                color: white !important;
                padding: 8pt 12pt !important; /* Padding reducido */
                border-radius: 3pt !important;
                text-align: center !important;
                margin: 8pt 0 !important;
                display: inline-block !important;
            }

            .total-card h3 {
                font-size: 10pt !important; /* Título más pequeño */
                color: white !important;
                margin-bottom: 2pt !important;
            }

            .total-card .amount {
                font-size: 14pt !important; /* Cantidad más pequeña */
                color: white !important;
                font-weight: bold !important;
            }

            .total-card .amount {
                font-size: 16pt !important;
                color: white !important;
            }

            .status {
                padding: 3pt 8pt !important;
                border-radius: 12pt !important;
                font-size: 9pt !important;
                color: white !important;
            }

            .print-section,
            .print-card,
            .btn-print {
                display: none !important;
            }

            .error,
            .form-section {
                display: none !important;
            }
        }

        /* Media queries para diferentes tamaños de pantalla */

        /* Tablets y pantallas medianas */
        @media (max-width: 1024px) {
            .container {
                margin: 10px;
                border-radius: 8px;
            }

            .info-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 15px;
            }

            .productos-table th,
            .productos-table td {
                padding: 12px 8px;
                font-size: 0.9rem;
            }
        }

        /* Móviles grandes */
        @media (max-width: 768px) {
            body {
                padding: 5px;
                font-size: 16px; /* Base font size for better scaling */
                line-height: 1.5; /* Mejor legibilidad */
            }

            .container {
                margin: 0;
                border-radius: 8px;
                max-width: 100%;
                box-shadow: none;
                overflow: hidden; /* Evitar desbordamientos */
            }

            .header {
                padding: 20px 15px;
                flex-direction: row;
                justify-content: flex-start;
                align-items: center;
                text-align: left;
                gap: 15px;
                border-radius: 8px 8px 0 0;
                min-height: 70px; /* Altura mínima para touch targets */
            }

            .header h1 {
                font-size: 1.4rem;
                line-height: 1.2;
                margin: 0;
                flex: 1; /* Ocupa el espacio disponible */
            }

            .header .subtitle {
                font-size: 0.85rem;
                opacity: 0.9;
                margin: 2px 0 0 0;
            }

            .logo {
                max-width: 50px;
                max-height: 50px;
                border-radius: 4px;
                flex-shrink: 0; /* No se contraiga */
            }

            .content {
                padding: 15px 10px;
            }

            .info-grid {
                grid-template-columns: 1fr 1fr 1fr;
                gap: 8px;
                margin-bottom: 15px;
            }

            .info-card {
                padding: 8px 10px;
                min-height: 45px;
                border-radius: 6px;
            }

            .info-card.compact {
                padding: 6px 8px;
                min-height: 40px;
            }

            .info-card.primary {
                padding: 8px 10px;
                min-height: 45px;
            }

            .info-card h3 {
                font-size: 0.65rem;
                margin-bottom: 1px;
                letter-spacing: 0.2px;
            }

            .info-card p {
                font-size: 0.8rem;
                line-height: 1.1;
                margin: 1px 0;
            }

            .info-card.compact h3 {
                font-size: 0.6rem;
                margin-bottom: 1px;
            }

            .info-card.compact p {
                font-size: 0.75rem;
                line-height: 1.1;
            }

            /* Cliente span 3 en móvil pero más compacto */
            .info-card[style*="grid-column: span 3"] {
                grid-column: span 3;
                padding: 8px 10px;
                min-height: 45px;
            }

            .info-card[style*="grid-column: span 3"] p {
                margin: 1px 0;
            }

            /* Email más pequeño en móvil */
            .info-card[style*="grid-column: span 2"] p[style*="font-size: 0.85rem"] {
                font-size: 0.7rem !important;
                opacity: 0.7;
                margin-top: 2px;
            }

            .section-title {
                font-size: 1.3rem;
                margin-bottom: 18px;
                text-align: left;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .section-title::before {
                font-size: 1.1rem;
            }

            .productos-section {
                margin-top: 25px;
            }

            /* Mejora del scroll horizontal para tablas */
            .table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                margin: 0 -15px 20px -15px;
                padding: 0 15px;
                border-radius: 8px;
                background: #30363d;
            }

            .productos-table {
                min-width: 650px; /* Aumentado para mejor legibilidad */
                font-size: 0.9rem;
                background: #30363d;
            }

            .productos-table th {
                padding: 12px 8px;
                font-size: 0.8rem;
                letter-spacing: 0.3px;
                background: linear-gradient(135deg, #1f6feb 0%, #0969da 100%);
                position: sticky;
                top: 0;
                z-index: 10;
            }

            .productos-table td {
                padding: 12px 8px;
                font-size: 0.85rem;
                white-space: nowrap;
                border-bottom: 1px solid #3d444d;
            }

            /* Cards móviles para productos (alternativa) */
            .productos-mobile {
                display: none; /* Mantenemos la tabla por ahora */
            }

            .total-section {
                margin-top: 25px;
                text-align: center;
                padding: 0 5px;
            }

            .total-card {
                padding: 20px 25px;
                margin: 20px auto;
                max-width: 300px;
                border-radius: 10px;
                box-shadow: 0 4px 15px rgba(31, 111, 235, 0.3);
            }

            .total-card h3 {
                font-size: 1rem;
                margin-bottom: 8px;
            }

            .total-card .amount {
                font-size: 1.8rem;
                font-weight: 700;
            }

            /* Botones mejorados para móvil */
            .btn, .btn-print {
                padding: 15px 25px;
                font-size: 1rem;
                border-radius: 8px;
                min-height: 44px; /* Touch target size */
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
            }

            .form-section {
                padding: 25px 20px;
                margin: 20px 0;
            }

            .form-group input {
                width: 100%;
                max-width: 250px;
                padding: 15px;
                font-size: 1rem;
                border-radius: 8px;
            }

            .print-card {
                padding: 25px 20px;
                margin: 20px 0;
            }
        }

        /* Media query para pantallas muy pequeñas - Ultra compacto */
        @media (max-width: 480px) {
            body {
                padding: 3px;
                font-size: 14px;
            }

            .container {
                border-radius: 6px;
            }

            .header {
                padding: 12px 10px;
                min-height: 60px;
            }

            .header h1 {
                font-size: 1.2rem;
            }

            .header .subtitle {
                font-size: 0.8rem;
            }

            .logo {
                max-width: 40px;
                max-height: 40px;
            }

            .content {
                padding: 10px 8px;
            }

            .info-grid {
                gap: 6px;
                margin-bottom: 12px;
            }

            .info-card {
                padding: 6px 8px;
                min-height: 38px;
                border-radius: 4px;
            }

            .info-card.compact {
                padding: 5px 6px;
                min-height: 35px;
            }

            .info-card.primary {
                padding: 6px 8px;
                min-height: 38px;
            }

            .info-card h3 {
                font-size: 0.6rem;
                margin-bottom: 1px;
            }

            .info-card p {
                font-size: 0.75rem;
                line-height: 1.0;
                margin: 0;
            }

            .info-card.compact h3 {
                font-size: 0.55rem;
            }

            .info-card.compact p {
                font-size: 0.7rem;
            }

            .info-card[style*="grid-column: span 2"] {
                padding: 6px 8px;
                min-height: 38px;
            }

            .info-card[style*="grid-column: span 2"] p[style*="font-size: 0.85rem"] {
                font-size: 0.65rem !important;
                opacity: 0.7;
                margin-top: 1px;
            }

            .section-title {
                font-size: 1.1rem;
                margin-bottom: 12px;
            }

            .productos-table {
                font-size: 0.8rem;
            }

            .productos-table th {
                padding: 8px 6px;
                font-size: 0.7rem;
            }

            .productos-table td {
                padding: 8px 6px;
                font-size: 0.75rem;
            }
        }

        /* Móviles pequeños */
        @media (max-width: 480px) {
            body {
                padding: 2px;
                font-size: 15px;
            }

            .container {
                border-radius: 6px;
            }

            .header {
                padding: 15px 12px;
                flex-direction: row;
                text-align: left;
                gap: 12px;
                justify-content: flex-start;
                align-items: center;
            }

            .header h1 {
                font-size: 1.2rem;
            }

            .header .subtitle {
                font-size: 0.8rem;
            }

            .logo {
                max-width: 45px;
                max-height: 45px;
            }

            .content {
                padding: 15px 12px;
            }

            .info-card {
                padding: 15px;
            }

            .info-card h3 {
                font-size: 0.75rem;
            }

            .info-card p {
                font-size: 1rem;
            }

            .section-title {
                font-size: 1.1rem;
                margin-bottom: 15px;
            }

            .table-container {
                margin: 0 -12px 15px -12px;
                padding: 0 12px;
            }

            .productos-table {
                min-width: 600px;
                font-size: 0.8rem;
            }

            .productos-table th {
                padding: 10px 6px;
                font-size: 0.7rem;
            }

            .productos-table td {
                padding: 10px 6px;
                font-size: 0.75rem;
            }

            .total-card {
                padding: 18px 20px;
                max-width: 280px;
            }

            .total-card .amount {
                font-size: 1.6rem;
            }

            .btn, .btn-print {
                padding: 12px 20px;
                font-size: 0.9rem;
            }

            .total-card h3 {
                font-size: 1rem;
                margin-bottom: 4px;
            }

            .total-card .amount {
                font-size: 1.6rem;
            }

            .print-section {
                margin-top: 30px;
            }

            .print-card {
                padding: 20px 15px;
                margin: 15px 0;
            }

            .print-card h2 {
                font-size: 1.2rem;
                margin-bottom: 12px;
            }

            .print-card p {
                font-size: 0.9rem;
                margin-bottom: 15px;
            }

            .btn-print {
                padding: 12px 24px;
                font-size: 1rem;
                width: 100%;
                max-width: 280px;
            }

            .status {
                font-size: 0.75rem;
                padding: 4px 8px;
            }

            .form-section {
                padding: 20px 15px;
                margin: 15px 0;
            }

            .form-section h2 {
                font-size: 1.2rem;
                margin-bottom: 15px;
            }

            .form-group input {
                width: 100%;
                max-width: 280px;
                font-size: 1rem;
                padding: 14px;
            }

            .error {
                padding: 15px;
                margin: 15px 0;
                font-size: 0.9rem;
                border-radius: 6px;
            }
        }

        /* Estilos específicos para pantallas muy pequeñas */
        @media (max-width: 480px) {
            body {
                padding: 5px;
            }

            .header {
                padding: 12px 15px;
            }

            .header h1 {
                font-size: 1.4rem;
            }

            .content {
                padding: 12px;
            }

            .info-card {
                padding: 12px;
            }

            .info-card h3 {
                font-size: 0.75rem;
            }

            .info-card p {
                font-size: 0.9rem;
            }

            .section-title {
                font-size: 1.1rem;
            }

            .productos-table {
                min-width: 500px;
            }

            .productos-table th,
            .productos-table td {
                padding: 6px 4px;
                font-size: 0.75rem;
            }

            .total-card {
                padding: 12px 15px;
            }

            .total-card .amount {
                font-size: 1.4rem;
            }

            .print-card {
                padding: 15px 12px;
            }

            .btn-print {
                padding: 10px 20px;
                font-size: 0.9rem;
            }
        }

        /* Móviles muy pequeños - Vista alternativa de productos en cards */
        @media (max-width: 380px) {
            .table-container {
                display: none; /* Ocultar tabla en pantallas muy pequeñas */
            }

            .total-compacto {
                display: none !important; /* Ocultar total de tabla en vista móvil */
            }

            .productos-mobile {
                display: block !important;
            }

            .producto-card {
                background: #30363d;
                border: 1px solid #3d444d;
                border-radius: 8px;
                padding: 15px;
                margin-bottom: 12px;
                transition: all 0.3s ease;
            }

            .producto-card:hover {
                border-color: #1f6feb;
                transform: translateY(-1px);
                box-shadow: 0 3px 10px rgba(31, 111, 235, 0.15);
            }

            .producto-card .producto-nombre {
                color: #e6edf3;
                font-weight: 600;
                font-size: 1rem;
                margin-bottom: 8px;
                display: block;
            }

            .producto-card .producto-info {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
                font-size: 0.85rem;
                margin-bottom: 10px;
            }

            .producto-card .producto-info span {
                color: #8b949e;
            }

            .producto-card .producto-info strong {
                color: #e6edf3;
            }

            .producto-card .producto-subtotal {
                text-align: right;
                font-size: 1.1rem;
                font-weight: 700;
                color: #238636;
                border-top: 1px solid #3d444d;
                padding-top: 8px;
                margin-top: 8px;
            }
        }

        /* Mejoras adicionales para accesibilidad móvil */
        .info-card .status {
            display: inline-block;
            margin-top: 5px;
        }

        /* Smooth scrolling para navegación */
        html {
            scroll-behavior: smooth;
        }

        /* Mejoras de contraste para texto */
        .info-card p {
            color: #e6edf3;
            line-height: 1.4;
        }

        /* Botones más accesibles en móvil */
        @media (max-width: 768px) {
            .btn, .btn-print {
                min-width: 120px;
                position: relative;
                overflow: hidden;
            }

            .btn::before, .btn-print::before {
                content: '';
                position: absolute;
                top: 50%;
                left: 50%;
                width: 0;
                height: 0;
                background: rgba(255, 255, 255, 0.2);
                border-radius: 50%;
                transform: translate(-50%, -50%);
                transition: width 0.3s, height 0.3s;
            }

            .btn:active::before, .btn-print:active::before {
                width: 300px;
                height: 300px;
            }

            /* Mejorar la tabla en móvil */
            .table-container {
                position: relative;
            }

            .table-container::after {
                content: '👉 Desliza para ver más';
                position: absolute;
                bottom: -25px;
                right: 0;
                font-size: 0.7rem;
                color: #8b949e;
                font-style: italic;
            }
        }

        /* Mejoras para experiencia táctil */
        @media (max-width: 768px) {
            /* Prevenir zoom accidental en inputs */
            input, select, textarea {
                font-size: 16px !important;
            }

            /* Mejorar área de toque para elementos interactivos */
            .info-card:hover {
                transform: none; /* Remover hover effects en móvil */
            }

            .producto-card:hover {
                transform: none;
            }

            /* Evitar overflow horizontal */
            body, html {
                overflow-x: hidden;
            }

            /* Mejorar contraste para lectores */
            .info-card h3 {
                color: #58a6ff; /* Azul más claro para mejor contraste */
            }

            /* Ajustar espaciado para dedos */
            .info-grid {
                gap: 16px; /* Espaciado ligeramente mayor */
            }

            /* Optimizar el scroll de la tabla */
            .table-container {
                scrollbar-width: thin;
                scrollbar-color: #1f6feb #21262d;
            }
        }

        /* Focus states mejorados para navegación por teclado */
        .btn:focus, .btn-print:focus {
            outline: 2px solid #58a6ff;
            outline-offset: 2px;
        }

        .info-card:focus-within {
            border-color: #58a6ff;
            box-shadow: 0 0 0 2px rgba(88, 166, 255, 0.3);
        }        /* Grid ultra compacto para móviles pequeños */
        @media (max-width: 480px) {
            .info-grid {
                grid-template-columns: 1fr 1fr 1fr;
                gap: 4px;
            }

            /* Cliente y Dirección aparecen primero */
            .info-card[style*="grid-column: span 3"] {
                grid-column: span 3;
                order: -1;
            }

            /* Acciones ultra compactas en móvil */
            .acciones-ultra-compactas {
                margin-top: 15px !important;
            }

            .grid-acciones {
                gap: 3px !important;
                max-width: 100% !important;
                margin: 0 !important;
            }

            .accion-micro {
                padding: 6px 2px !important;
                border-radius: 4px !important;
            }

            .accion-micro div:first-child {
                font-size: 0.55rem !important;
                margin-bottom: 2px !important;
            }

            .accion-micro div:not(:first-child) {
                font-size: 0.5rem !important;
                margin-bottom: 2px !important;
            }

            .accion-micro button,
            .accion-micro a {
                font-size: 0.5rem !important;
                padding: 2px 1px !important;
                border-radius: 2px !important;
            }
        }

        @media (max-width: 380px) {
            .info-grid {
                grid-template-columns: 1fr 1fr;
                gap: 3px;
            }

            .info-card[style*="grid-column: span 3"] {
                grid-column: span 2;
                order: -1;
            }

            /* Acciones súper compactas */
            .grid-acciones {
                gap: 2px !important;
            }

            .accion-micro {
                padding: 4px 1px !important;
            }

            .accion-micro div:first-child {
                font-size: 0.5rem !important;
                margin-bottom: 1px !important;
            }

            .accion-micro div:not(:first-child) {
                font-size: 0.45rem !important;
                margin-bottom: 1px !important;
            }

            .accion-micro button,
            .accion-micro a {
                font-size: 0.45rem !important;
                padding: 1px !important;
            }

            /* Estilos responsivos para nuevas secciones */
            .estado-management,
            .notas-section,
            .editar-cliente-section,
            .comunicacion-section,
            .metricas-section {
                margin: 10px 0 !important;
                padding: 12px !important;
            }

            .estado-management > div:first-of-type,
            .editar-cliente-section #formulario-edicion > div:first-child,
            .editar-cliente-section #formulario-edicion > div:last-of-type {
                grid-template-columns: 1fr !important;
                gap: 8px !important;
            }

            .comunicacion-section > div:last-of-type {
                grid-template-columns: 1fr !important;
                gap: 8px !important;
            }

            .metricas-section > div:last-of-type {
                grid-template-columns: 1fr 1fr !important;
                gap: 8px !important;
            }

            .metric-card {
                padding: 10px !important;
                font-size: 0.8rem !important;
            }
        }

        /* Mejoras para el total compacto */
        .total-compacto {
            transition: all 0.3s ease;
        }

        .total-compacto:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(31, 111, 235, 0.2);
        }

        /* Responsive para total compacto */
        @media (max-width: 768px) {
            .total-compacto {
                max-width: 250px !important;
                margin: 8px auto !important;
                padding: 6px 10px !important;
                text-align: center !important;
            }

            .total-compacto div {
                font-size: 0.8rem !important;
            }

            .total-compacto span:last-child {
                font-size: 1rem !important;
                margin-left: 6px !important;
            }

            .total-mobile-compacto {
                margin: 6px 0 !important;
                padding: 4px 8px !important;
            }

            .total-mobile-compacto div {
                font-size: 0.75rem !important;
            }

            .total-mobile-compacto span:last-child {
                font-size: 0.9rem !important;
                margin-left: 4px !important;
            }
        }

        @media (max-width: 480px) {
            .total-compacto {
                max-width: 200px !important;
                margin: 6px auto !important;
                padding: 4px 8px !important;
                border-radius: 4px !important;
            }

            .total-compacto div {
                font-size: 0.75rem !important;
            }

            .total-compacto span:last-child {
                font-size: 0.9rem !important;
                margin-left: 4px !important;
            }

            .total-mobile-compacto {
                margin: 4px 0 !important;
                padding: 3px 6px !important;
            }

            .total-mobile-compacto div {
                font-size: 0.7rem !important;
            }

            .total-mobile-compacto span:last-child {
                font-size: 0.8rem !important;
                margin-left: 3px !important;
            }
        }

        /* ===== ESTILOS PARA SISTEMA DE PESTAÑAS ===== */
        .pestanas-contexto {
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }

        .pestanas-nav {
            position: relative;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            white-space: nowrap;
        }

        .tab-button {
            position: relative;
            transition: all 0.3s ease;
            white-space: nowrap;
            min-width: max-content;
        }

        .tab-button:hover {
            background: rgba(88, 166, 255, 0.1) !important;
            color: #58a6ff !important;
        }

        .tab-button.active {
            background: rgba(31, 111, 235, 0.1);
        }

        .tab-content {
            animation: fadeIn 0.4s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Tooltips para métricas */
        .metric-card-unified {
            position: relative;
            transition: all 0.3s ease;
        }

        .metric-card-unified:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        }

        /* Responsive para pestañas */
        @media (max-width: 768px) {
            .pestanas-nav {
                border-bottom: 1px solid #30363d;
            }

            .tab-button {
                font-size: 0.8rem !important;
                padding: 12px 10px !important;
                min-width: 33.33%;
                text-align: center;
            }

            .pestanas-content {
                padding: 15px !important;
            }

            .metric-card-unified {
                padding: 12px !important;
            }

            /* Grid responsive para comparativas */
            .content-estadisticas > div:first-child {
                grid-template-columns: 1fr !important;
                gap: 15px !important;
            }

            /* Lista de archivos responsive */
            .archivo-item {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 10px !important;
            }

            .archivo-item > div:last-child {
                align-self: flex-end !important;
            }
        }

        @media (max-width: 480px) {
            .tab-button {
                font-size: 0.7rem !important;
                padding: 10px 5px !important;
            }

            .pestanas-content {
                padding: 10px !important;
            }

            /* Grids de 1 columna en móviles pequeños */
            .pestanas-content [style*="grid-template-columns"] {
                grid-template-columns: 1fr !important;
            }

            /* Texto más pequeño en móviles */
            .metric-card-unified .color {
                font-size: 0.7rem !important;
            }

            .metric-card-unified div[style*="font-size: 1.3rem"] {
                font-size: 1.1rem !important;
            }
        }

        /* Efectos de hover adicionales */
        .archivo-item:hover {
            background: #161b22 !important;
            border-color: #58a6ff !important;
            transition: all 0.3s ease;
        }

        .archivo-item a:hover {
            transform: scale(1.05);
            transition: all 0.2s ease;
        }

        /* Loading states */
        .loading-shimmer {
            background: linear-gradient(90deg, #21262d 25%, #30363d 50%, #21262d 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
        }

        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }

        /* ===== ESTILOS PARA SISTEMA DE PESTAÑAS ===== */
        .pestanas-contexto {
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }

        .pestanas-nav {
            position: relative;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .tab-button {
            transition: all 0.3s ease;
            white-space: nowrap;
            position: relative;
            overflow: hidden;
        }

        .tab-button:hover {
            background: rgba(88, 166, 255, 0.1) !important;
            color: #58a6ff !important;
        }

        .tab-button:active {
            transform: translateY(1px);
        }

        .tab-content {
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.3s ease;
        }

        .tab-content.active {
            opacity: 1;
            transform: translateY(0);
        }

        /* Efectos de hover para cards de métricas */
        .metric-card-unified {
            transition: all 0.3s ease;
            cursor: default;
        }

        .metric-card-unified:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        }

        /* Animaciones para elementos de archivo */
        .archivo-item {
            transition: all 0.3s ease;
        }

        .archivo-item:hover {
            background: #161b22 !important;
            border-color: #58a6ff !important;
        }

        /* Responsive para pestañas */
        @media (max-width: 768px) {
            .pestanas-contexto {
                margin-left: -10px;
                margin-right: -10px;
                border-radius: 8px;
            }

            .pestanas-nav {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scroll-behavior: smooth;
                padding-bottom: 2px; /* Para la sombra del scroll */
            }

            .tab-button {
                flex: none !important;
                min-width: 140px !important;
                padding: 12px 16px !important;
                font-size: 0.85rem !important;
                white-space: nowrap;
                border-bottom: 2px solid transparent !important;
                border-left: none !important;
            }

            .tab-button.active {
                border-bottom-color: #1f6feb !important;
                background: rgba(31, 111, 235, 0.1) !important;
            }

            .pestanas-content {
                padding: 15px !important;
            }

            /* Simplificar grids en móvil */
            .pestanas-content [style*="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr))"] {
                grid-template-columns: 1fr !important;
                gap: 15px !important;
            }

            .pestanas-content [style*="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr))"] {
                grid-template-columns: 1fr !important;
                gap: 12px !important;
            }

            .pestanas-content [style*="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr))"] {
                grid-template-columns: 1fr 1fr !important;
                gap: 10px !important;
            }

            .pestanas-content [style*="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr))"] {
                grid-template-columns: 1fr 1fr !important;
                gap: 8px !important;
            }

            .pestanas-content [style*="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr))"] {
                grid-template-columns: 1fr 1fr !important;
                gap: 8px !important;
            }

            /* Ajustar cards de métricas */
            .metric-card-unified {
                padding: 12px !important;
                margin-bottom: 10px;
                text-align: center !important;
            }

            .metric-card-unified > div:first-child {
                font-size: 0.75rem !important;
            }

            .metric-card-unified > div:nth-child(2) {
                font-size: 1.1rem !important;
            }

            .metric-card-unified > div:last-child {
                font-size: 0.65rem !important;
            }

            /* Ajustar archivos en móvil */
            .archivo-item {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 10px !important;
                padding: 12px !important;
            }

            .archivo-item > div:last-child {
                align-self: stretch !important;
                display: flex !important;
                gap: 8px !important;
            }

            .archivo-item a {
                flex: 1 !important;
                text-align: center !important;
                font-size: 0.75rem !important;
                padding: 8px 6px !important;
            }
        }

        @media (max-width: 480px) {
            .pestanas-contexto {
                margin-left: -15px;
                margin-right: -15px;
                border-radius: 0;
                border-left: none;
                border-right: none;
            }

            .tab-button {
                min-width: 120px !important;
                font-size: 0.8rem !important;
                padding: 10px 12px !important;
            }

            /* Simplificar todo a una columna en móviles pequeños */
            .pestanas-content [style*="grid-template-columns: 1fr 1fr"] {
                grid-template-columns: 1fr !important;
            }

            /* Títulos más pequeños en pestañas */
            .pestanas-content h3 {
                font-size: 1.2rem !important;
                text-align: center !important;
                gap: 8px !important;
            }

            .pestanas-content h4 {
                font-size: 1rem !important;
                flex-direction: column !important;
                text-align: center !important;
                gap: 5px !important;
            }

            /* Cards de estadísticas más compactas */
            .metric-card-unified {
                padding: 10px !important;
                margin-bottom: 8px !important;
            }

            .metric-card-unified > div:first-child {
                font-size: 0.7rem !important;
                margin-bottom: 4px !important;
            }

            .metric-card-unified > div:nth-child(2) {
                font-size: 1rem !important;
                margin-bottom: 3px !important;
            }

            .metric-card-unified > div:last-child {
                font-size: 0.6rem !important;
            }

            /* Botones de archivo stack completo */
            .archivo-item > div:last-child {
                flex-direction: column !important;
                gap: 6px !important;
            }

            .archivo-item a {
                font-size: 0.7rem !important;
                padding: 6px 8px !important;
            }

            /* Ajustar padding del contenido de pestañas */
            .pestanas-content {
                padding: 10px !important;
            }

            /* Tooltips deshabilitados en móvil pequeño para performance */
            .metric-card-unified[data-tooltip]:hover::after,
            .metric-card-unified[data-tooltip]:hover::before {
                display: none !important;
            }

            /* Texto más pequeño en comparativas */
            .pestanas-content [style*="font-size: 0.85rem"] {
                font-size: 0.75rem !important;
            }

            .pestanas-content [style*="font-size: 0.9rem"] {
                font-size: 0.8rem !important;
            }

            /* Optimizaciones específicas para el stepper de progreso en móvil */
            .progress-stepper {
                padding: 15px 10px !important;
                margin-bottom: 15px !important;
            }

            .progress-stepper .step {
                padding: 4px !important;
            }

            .progress-stepper .step > div:first-child {
                width: 35px !important;
                height: 35px !important;
                font-size: 1rem !important;
            }

            .progress-stepper .step > span {
                font-size: 0.7rem !important;
                max-width: 60px;
                text-align: center;
                word-wrap: break-word;
            }

            .progress-stepper .next-action {
                padding: 12px 8px !important;
            }

            .progress-stepper .next-action button {
                font-size: 0.8rem !important;
                padding: 8px 16px !important;
                width: 100%;
                max-width: 280px;
            }

            /* Optimizaciones específicas para el timeline en móvil */
            .timeline-section {
                padding: 15px 10px !important;
                margin: 15px 0 !important;
            }

            .timeline-section h3 {
                font-size: 1.1rem !important;
                margin-bottom: 15px !important;
                flex-direction: column !important;
                text-align: center !important;
                gap: 5px !important;
            }

            .add-timeline-entry {
                padding: 12px !important;
                margin-bottom: 15px !important;
            }

            .add-timeline-entry [style*="grid-template-columns: 120px 1fr"] {
                grid-template-columns: 1fr !important;
                gap: 10px !important;
            }

            .add-timeline-entry select {
                margin-bottom: 10px;
            }

            .add-timeline-entry textarea {
                height: 50px !important;
                font-size: 0.9rem !important;
            }

            /* Eventos del timeline más compactos */
            .timeline-event {
                margin-bottom: 15px !important;
                padding: 10px !important;
            }

            .timeline-event [style*="padding-left: 50px"] {
                padding-left: 40px !important;
            }

            /* Línea del timeline ajustada */
            #timeline-eventos > div[style*="position: absolute; left: 20px"] {
                left: 15px !important;
            }

            /* Iconos del timeline más pequeños */
            .timeline-event [style*="width: 35px; height: 35px"] {
                width: 30px !important;
                height: 30px !important;
                left: 0px !important;
                font-size: 0.9rem !important;
            }
        }

            .tab-button {
                font-size: 0.8rem !important;
                padding: 10px 12px !important;
            }

            /* Simplificar grids en pantallas muy pequeñas */
            [style*="grid-template-columns: repeat(auto-fit, minmax("] {
                grid-template-columns: 1fr !important;
            }

            /* Ajustar cards de estadísticas */
            .metric-card-unified {
                text-align: left !important;
                padding: 12px !important;
            }

            /* Botones de archivo más pequeños */
            .archivo-item a {
                font-size: 0.7rem !important;
                padding: 6px 8px !important;
            }
        }

        /* Tooltips para métricas */
        .metric-card-unified[data-tooltip] {
            position: relative;
            cursor: help;
        }

        .metric-card-unified[data-tooltip]:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #21262d;
            color: #e6edf3;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            white-space: nowrap;
            z-index: 1000;
            border: 1px solid #3d444d;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
            opacity: 0;
            animation: tooltip-fade-in 0.3s ease forwards;
        }

        .metric-card-unified[data-tooltip]:hover::before {
            content: '';
            position: absolute;
            bottom: 94%;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 6px solid transparent;
            border-right: 6px solid transparent;
            border-top: 6px solid #21262d;
            z-index: 1001;
            opacity: 0;
            animation: tooltip-fade-in 0.3s ease forwards;
        }

        @keyframes tooltip-fade-in {
            from { opacity: 0; transform: translateX(-50%) translateY(5px); }
            to { opacity: 1; transform: translateX(-50%) translateY(0); }
        }

        /* Estados de carga para contenido dinámico */
        .loading-content {
            background: linear-gradient(135deg, #21262d15 0%, #30363d15 100%);
            border: 1px dashed #3d444d;
            border-radius: 8px;
            padding: 40px 20px;
            text-align: center;
            color: #8b949e;
        }

        .loading-content .spinner {
            animation: spin 1s linear infinite;
            font-size: 2rem;
            margin-bottom: 15px;
            display: inline-block;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Mejoras de accesibilidad */
        .tab-button:focus {
            outline: 2px solid #1f6feb;
            outline-offset: 2px;
        }

        .archivo-item:focus-within {
            border-color: #1f6feb !important;
            box-shadow: 0 0 0 2px rgba(31, 111, 235, 0.3);
        }

        /* Animaciones de entrada para contenido */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Estados de éxito y error */
        .status-success {
            background: linear-gradient(135deg, #23863615 0%, #2ea04315 100%);
            border: 1px solid #238636;
            color: #3fb950;
            padding: 12px 16px;
            border-radius: 6px;
            margin: 10px 0;
        }

        .status-error {
            background: linear-gradient(135deg, #da363315 0%, #f8514915 100%);
            border: 1px solid #da3633;
            color: #f85149;
            padding: 12px 16px;
            border-radius: 6px;
            margin: 10px 0;
        }

        .status-info {
            background: linear-gradient(135deg, #1f6feb15 0%, #0969da15 100%);
            border: 1px solid #1f6feb;
            color: #58a6ff;
            padding: 12px 16px;
            border-radius: 6px;
            margin: 10px 0;
        }
    </style>
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
                        // Determinar el paso actual basado en el estado del pedido
                        $estado_actual = strtolower($p['estado'] ?? '');
                        $pagado = intval($p['pagado'] ?? 0);
                        $enviado = intval($p['enviado'] ?? 0);
                        $guia_existe = !empty($p['guia']) && file_exists("guias/" . $p['guia']);

                        $steps = [
                            'recibido' => ['icon' => '📝', 'label' => 'Recibido', 'active' => true],
                            'pagado' => ['icon' => '💳', 'label' => 'Pagado', 'active' => $pagado == 1],
                            'enviado' => ['icon' => '🚚', 'label' => 'Enviado', 'active' => $enviado == 1 || $guia_existe],
                            'entregado' => ['icon' => '✅', 'label' => 'Entregado', 'active' => strpos($estado_actual, 'entregado') !== false || strpos($estado_actual, 'archivado') !== false]
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
                        <p style="font-size: 0.85rem; opacity: 0.8;"><?php echo h($p['correo'] ?? 'Sin email'); ?></p>
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

                    <!-- Total del Pedido -->
                    <div class="total-section" style="margin-top: 20px; text-align: right;">
                        <div class="total-card" style="display: inline-block; background: linear-gradient(135deg, #238636 0%, #2ea043 100%); color: white; padding: 15px 25px; border-radius: 8px;">
                            <h4 style="margin: 0 0 5px 0; opacity: 0.9; font-size: 0.9rem;">Total del Pedido</h4>
                            <div class="amount" style="font-size: 1.8rem; font-weight: 700; margin: 0;">
                                $<?php echo number_format($total_productos, 0, ',', '.'); ?>
                            </div>
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
                                $<?php echo number_format($total_productos, 0, ',', '.'); ?>
                            </div>
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

                    <?php if (!empty($correo_cliente) || !empty($telefono_cliente)): ?>

                    <!-- ===== SISTEMA UNIFICADO DE ESTADÍSTICAS DEL CLIENTE ===== -->
                    <div class="estadisticas-unificadas" style="margin-top: 30px; background: #30363d; border: 1px solid #3d444d; border-radius: 12px; padding: 25px;">

                        <!-- Encabezado del módulo -->
                        <div style="text-align: center; margin-bottom: 25px;">
                            <h3 style="color: #f79000; font-size: 1.3rem; margin-bottom: 8px; display: flex; align-items: center; justify-content: center; gap: 10px;">
                                🚀 Análisis Completo del Cliente
                            </h3>
                            <p style="color: #8b949e; font-size: 0.9rem; margin: 0;">
                                Dashboard integral con todas las métricas de comportamiento, valor y rendimiento
                            </p>
                        </div>

                        <!-- SECCIÓN 1: MÉTRICAS PRINCIPALES -->
                        <div style="margin-bottom: 30px;">
                            <h4 style="color: #58a6ff; font-size: 1rem; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #3d444d; padding-bottom: 8px;">
                                📈 Métricas Principales
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px;">

                                <!-- LTV -->
                                <div class="metric-card-unified" style="background: linear-gradient(135deg, #1f6feb15 0%, #0969da15 100%); padding: 16px; border-radius: 8px; text-align: center; border: 1px solid #1f6feb;"
                                     data-tooltip="LTV (Lifetime Value): Valor total histórico de todas las compras del cliente. Métrica clave para identificar clientes valiosos.">
                                    <div style="color: #1f6feb; font-size: 0.8rem; margin-bottom: 6px; font-weight: 600;">👤 LTV Cliente</div>
                                    <div style="color: #58a6ff; font-weight: 700; font-size: 1.3rem; margin-bottom: 4px;">
                                        $<?php echo number_format(floatval($cliente_stats['ltv']['valor_total_historico'] ?? 0), 0, ',', '.'); ?>
                                    </div>
                                    <div style="color: #8b949e; font-size: 0.7rem;">
                                        <?php echo intval($cliente_stats['ltv']['total_pedidos'] ?? 0); ?> pedidos realizados
                                    </div>
                                </div>

                                <!-- Ticket Promedio -->
                                <div class="metric-card-unified" style="background: linear-gradient(135deg, #23863615 0%, #2ea04315 100%); padding: 16px; border-radius: 8px; text-align: center; border: 1px solid #238636; cursor: help;"
                                     title="Ticket Promedio: Valor promedio de compra del cliente. Se calcula dividiendo el total gastado por el número de pedidos realizados.">
                                    <div style="color: #238636; font-size: 0.8rem; margin-bottom: 6px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 4px;">
                                        💰 Ticket Promedio
                                        <span style="font-size: 0.6rem; opacity: 0.7; cursor: help;" title="Valor promedio por pedido del cliente">ℹ️</span>
                                    </div>
                                    <div style="color: #3fb950; font-weight: 700; font-size: 1.3rem; margin-bottom: 4px;">
                                        $<?php echo number_format(floatval($cliente_stats['ltv']['ticket_promedio'] ?? 0), 0, ',', '.'); ?>
                                    </div>
                                    <div style="color: #8b949e; font-size: 0.7rem;">
                                        <?php
                                        $general = floatval($cliente_stats['promedio_general']['ticket_promedio_general'] ?? 0);
                                        $cliente_prom = floatval($cliente_stats['ltv']['ticket_promedio'] ?? 0);
                                        if ($general > 0) {
                                            $diferencia = (($cliente_prom - $general) / $general) * 100;
                                            echo ($diferencia > 0 ? '+' : '') . number_format($diferencia, 0) . '% vs promedio';
                                        } else {
                                            echo 'vs promedio general';
                                        }
                                        ?>
                                    </div>
                                </div>

                                <!-- Score RFM -->
                                <div class="metric-card-unified" style="background: linear-gradient(135deg, #dc262615 0%, #f8514915 100%); padding: 16px; border-radius: 8px; text-align: center; border: 1px solid #dc2626; cursor: help;"
                                     title="RFM: Recency (recencia), Frequency (frecuencia), Monetary (monetario). Clasifica al cliente según cuándo, qué tan frecuente y cuánto compra.">
                                    <div style="color: #dc2626; font-size: 0.8rem; margin-bottom: 6px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 4px;">
                                        📊 Clasificación RFM
                                        <span style="font-size: 0.6rem; opacity: 0.7; cursor: help;" title="RFM analiza Recencia, Frecuencia y Valor Monetario">ℹ️</span>
                                    </div>
                                    <div style="color: #f87171; font-weight: 700; font-size: 1rem; margin-bottom: 4px; line-height: 1.2;">
                                        <?php echo h($cliente_stats['rfm']['clasificacion'] ?? '🔄 Active'); ?>
                                    </div>
                                    <div style="color: #8b949e; font-size: 0.7rem;">
                                        R<?php echo intval($cliente_stats['rfm']['score_r'] ?? 0); ?>F<?php echo intval($cliente_stats['rfm']['score_f'] ?? 0); ?>M<?php echo intval($cliente_stats['rfm']['score_m'] ?? 0); ?> •
                                        <?php echo intval($cliente_stats['rfm']['recencia_dias'] ?? 0); ?> días
                                    </div>
                                </div>

                                <!-- Tasa de Éxito -->
                                <div class="metric-card-unified" style="background: linear-gradient(135deg, #a855f715 0%, #9333ea15 100%); padding: 16px; border-radius: 8px; text-align: center; border: 1px solid #a855f7;">
                                    <div style="color: #a855f7; font-size: 0.8rem; margin-bottom: 6px; font-weight: 600;">📈 Tasa de Éxito</div>
                                    <div style="color: #c084fc; font-weight: 700; font-size: 1.3rem; margin-bottom: 4px;">
                                        <?php
                                        $pagados = intval($cliente_stats['ltv']['pedidos_pagados'] ?? 0);
                                        $total = intval($cliente_stats['ltv']['total_pedidos'] ?? 0);
                                        $tasa = $total > 0 ? ($pagados / $total) * 100 : 0;
                                        echo number_format($tasa, 0) . '%';
                                        ?>
                                    </div>
                                    <div style="color: #8b949e; font-size: 0.7rem;">
                                        <?php echo $pagados; ?>/<?php echo $total; ?> pedidos pagados
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SECCIÓN 2: ANÁLISIS DE COMPORTAMIENTO -->
                        <div style="margin-bottom: 30px;">
                            <h4 style="color: #58a6ff; font-size: 1rem; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #3d444d; padding-bottom: 8px;">
                                🧠 Análisis de Comportamiento
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px;">

                                <!-- Última Actividad -->
                                <div class="metric-card-unified" style="background: linear-gradient(135deg, #05966915 0%, #10b98115 100%); padding: 16px; border-radius: 8px; text-align: center; border: 1px solid #059669;">
                                    <div style="color: #059669; font-size: 0.8rem; margin-bottom: 6px; font-weight: 600;">📅 Última Actividad</div>
                                    <div style="color: #34d399; font-weight: 700; font-size: 1.3rem; margin-bottom: 4px;">
                                        <?php
                                        $dias_ultima = intval($cliente_stats['actividad']['dias_ultima_compra'] ?? 0);
                                        if ($dias_ultima == 0) {
                                            echo 'Hoy';
                                        } elseif ($dias_ultima == 1) {
                                            echo 'Ayer';
                                        } else {
                                            echo $dias_ultima . ' días';
                                        }
                                        ?>
                                    </div>
                                    <div style="color: #8b949e; font-size: 0.7rem;">
                                        desde última compra
                                    </div>
                                </div>

                                <!-- Tendencia de Gasto -->
                                <div class="metric-card-unified" style="background: linear-gradient(135deg, #ea580c15 0%, #f9731615 100%); padding: 16px; border-radius: 8px; text-align: center; border: 1px solid #ea580c;">
                                    <div style="color: #ea580c; font-size: 0.8rem; margin-bottom: 6px; font-weight: 600;">📈 Tendencia Gasto</div>
                                    <div style="color: #fb923c; font-weight: 700; font-size: 1.1rem; margin-bottom: 4px;">
                                        <?php
                                        $tendencia = $cliente_stats['tendencia']['tendencia'] ?? 'estable';
                                        $cambio = floatval($cliente_stats['tendencia']['porcentaje_cambio'] ?? 0);

                                        if ($tendencia == 'creciente') {
                                            echo '📈 +' . number_format(abs($cambio), 0) . '%';
                                        } elseif ($tendencia == 'decreciente') {
                                            echo '📉 -' . number_format(abs($cambio), 0) . '%';
                                        } elseif ($tendencia == 'nuevo') {
                                            echo '🆕 Cliente Nuevo';
                                        } else {
                                            echo '➡️ Estable';
                                        }
                                        ?>
                                    </div>
                                    <div style="color: #8b949e; font-size: 0.7rem;">
                                        últimos 3 pedidos
                                    </div>
                                </div>

                                <!-- Hora Preferida -->
                                <div class="metric-card-unified" style="background: #21262d; padding: 16px; border-radius: 8px; text-align: center; border: 1px solid #3d444d;">
                                    <div style="color: #8b949e; font-size: 0.8rem; margin-bottom: 6px; font-weight: 600;">⏰ Hora Preferida</div>
                                    <div style="color: #e6edf3; font-weight: 600; font-size: 1.1rem; margin-bottom: 4px;">
                                        <?php
                                        $hora = intval($cliente_stats['hora_preferida']['hora'] ?? 0);
                                        if ($hora >= 6 && $hora < 12) {
                                            echo $hora . ':00 🌅';
                                        } elseif ($hora >= 12 && $hora < 18) {
                                            echo $hora . ':00 ☀️';
                                        } elseif ($hora >= 18 && $hora < 22) {
                                            echo $hora . ':00 🌆';
                                        } else {
                                            echo $hora . ':00 🌙';
                                        }
                                        ?>
                                    </div>
                                    <div style="color: #8b949e; font-size: 0.7rem;">
                                        <?php echo intval($cliente_stats['hora_preferida']['pedidos_en_hora'] ?? 0); ?> pedidos en esa hora
                                    </div>
                                </div>

                                <!-- Método de Pago Preferido -->
                                <div class="metric-card-unified" style="background: #21262d; padding: 16px; border-radius: 8px; text-align: center; border: 1px solid #3d444d;">
                                    <div style="color: #8b949e; font-size: 0.8rem; margin-bottom: 6px; font-weight: 600;">� Pago Preferido</div>
                                    <div style="color: #e6edf3; font-weight: 600; font-size: 0.95rem; margin-bottom: 4px; line-height: 1.2;">
                                        <?php echo h($cliente_stats['metodo_preferido']['metodo_pago'] ?? 'N/A'); ?>
                                    </div>
                                    <div style="color: #8b949e; font-size: 0.7rem;">
                                        usado <?php echo intval($cliente_stats['metodo_preferido']['veces_usado'] ?? 0); ?> veces
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SECCIÓN 3: POSICIONAMIENTO Y RENDIMIENTO -->
                        <div style="margin-bottom: 30px;">
                            <h4 style="color: #58a6ff; font-size: 1rem; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #3d444d; padding-bottom: 8px;">
                                🏆 Posicionamiento y Rendimiento
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px;">

                                <!-- Ranking Global -->
                                <div class="metric-card-unified" style="background: linear-gradient(135deg, #be185d15 0%, #ec489915 100%); padding: 16px; border-radius: 8px; text-align: center; border: 1px solid #be185d;">
                                    <div style="color: #be185d; font-size: 0.8rem; margin-bottom: 6px; font-weight: 600;">🏆 Ranking Global</div>
                                    <div style="color: #f472b6; font-weight: 700; font-size: 1.3rem; margin-bottom: 4px;">
                                        Top <?php echo number_format(100 - floatval($cliente_stats['ranking']['percentil'] ?? 0), 0); ?>%
                                    </div>
                                    <div style="color: #8b949e; font-size: 0.7rem;">
                                        Posición #<?php echo intval($cliente_stats['ranking']['posicion'] ?? 0); ?> de <?php echo intval($cliente_stats['ranking']['total_clientes'] ?? 0); ?>
                                    </div>
                                </div>

                                <!-- Tipo de Cliente -->
                                <div class="metric-card-unified" style="background: linear-gradient(135deg, #fb850015 0%, #fd7e1415 100%); padding: 16px; border-radius: 8px; text-align: center; border: 1px solid #fb8500;">
                                    <div style="color: #fb8500; font-size: 0.8rem; margin-bottom: 6px; font-weight: 600;">🎯 Tipo de Cliente</div>
                                    <div style="color: #f0883e; font-weight: 700; font-size: 1rem; margin-bottom: 4px;">
                                        <?php
                                        $total_pedidos = intval($cliente_stats['ltv']['total_pedidos'] ?? 0);
                                        $valor_total = floatval($cliente_stats['ltv']['valor_total_historico'] ?? 0);

                                        if ($total_pedidos >= 5 && $valor_total >= 500000) {
                                            echo '👑 VIP';
                                        } elseif ($total_pedidos >= 3 || $valor_total >= 200000) {
                                            echo '⭐ Recurrente';
                                        } elseif ($total_pedidos == 1) {
                                            echo '🆕 Nuevo';
                                        } else {
                                            echo '🔄 Activo';
                                        }
                                        ?>
                                    </div>
                                    <div style="color: #8b949e; font-size: 0.7rem;">
                                        <?php
                                        $primer_pedido = $cliente_stats['ltv']['primer_pedido'] ?? '';
                                        if ($primer_pedido) {
                                            $fecha_primer = new DateTime($primer_pedido);
                                            $ahora = new DateTime();
                                            $antiguedad = $ahora->diff($fecha_primer);
                                            if ($antiguedad->days > 365) {
                                                echo 'Cliente desde ' . $antiguedad->y . ' año' . ($antiguedad->y > 1 ? 's' : '');
                                            } elseif ($antiguedad->days > 30) {
                                                echo 'Cliente desde ' . intval($antiguedad->days / 30) . ' mes' . (intval($antiguedad->days / 30) > 1 ? 'es' : '');
                                            } else {
                                                echo 'Cliente desde ' . $antiguedad->days . ' día' . ($antiguedad->days > 1 ? 's' : '');
                                            }
                                        } else {
                                            echo 'Cliente nuevo';
                                        }
                                        ?>
                                    </div>
                                </div>

                                <!-- Tasa de Recurrencia del Sistema -->
                                <div class="metric-card-unified" style="background: linear-gradient(135deg, #7c3aed15 0%, #8b5cf615 100%); padding: 16px; border-radius: 8px; text-align: center; border: 1px solid #7c3aed;">
                                    <div style="color: #7c3aed; font-size: 0.8rem; margin-bottom: 6px; font-weight: 600;">� Recurrencia Sistema</div>
                                    <div style="color: #a78bfa; font-weight: 700; font-size: 1.3rem; margin-bottom: 4px;">
                                        <?php
                                        $tasa_recurrencia = floatval($cliente_stats['recurrencia']['tasa_recurrencia_sistema'] ?? 0);
                                        echo number_format($tasa_recurrencia, 1) . '%';
                                        ?>
                                    </div>
                                    <div style="color: #8b949e; font-size: 0.7rem;">
                                        clientes realizan compras múltiples
                                    </div>
                                </div>

                                <!-- Tiempo Promedio de Envío -->
                                <div class="metric-card-unified" style="background: #21262d; padding: 16px; border-radius: 8px; text-align: center; border: 1px solid #3d444d;">
                                    <div style="color: #8b949e; font-size: 0.8rem; margin-bottom: 6px; font-weight: 600;">� Tiempo Envío</div>
                                    <div style="color: #e6edf3; font-weight: 600; font-size: 1.1rem; margin-bottom: 4px;">
                                        <?php
                                        $tiempo_promedio = floatval($cliente_stats['tiempo_procesamiento']['tiempo_promedio_envio'] ?? 0);
                                        if ($tiempo_promedio > 0) {
                                            echo number_format($tiempo_promedio, 1) . ' días';
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </div>
                                    <div style="color: #8b949e; font-size: 0.7rem;">
                                        promedio histórico
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SECCIÓN 4: PRODUCTOS Y PREFERENCIAS -->
                        <?php if (!empty($cliente_stats['productos_favoritos'])): ?>
                        <div style="margin-bottom: 30px;">
                            <h4 style="color: #58a6ff; font-size: 1rem; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #3d444d; padding-bottom: 8px;">
                                🛍️ Productos y Preferencias
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                                <?php foreach ($cliente_stats['productos_favoritos'] as $index => $producto): ?>
                                <div class="metric-card-unified" style="background: #21262d; padding: 16px; border-radius: 8px; text-align: center; border: 1px solid #3d444d;">
                                    <div style="color: #8b949e; font-size: 0.8rem; margin-bottom: 6px; font-weight: 600;">
                                        <?php
                                        if ($index == 0) echo '🥇 Producto #1';
                                        elseif ($index == 1) echo '🥈 Producto #2';
                                        else echo '🥉 Producto #3';
                                        ?>
                                    </div>
                                    <div style="color: #e6edf3; font-weight: 600; font-size: 0.9rem; margin-bottom: 4px; line-height: 1.3;">
                                        <?php echo h($producto['nombre']); ?>
                                    </div>
                                    <div style="color: #8b949e; font-size: 0.7rem;">
                                        <?php echo intval($producto['total_comprado']); ?> unidades • <?php echo intval($producto['veces_pedido']); ?> pedidos
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- SECCIÓN 5: INSIGHTS Y RECOMENDACIONES -->
                        <div>
                            <h4 style="color: #58a6ff; font-size: 1rem; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #3d444d; padding-bottom: 8px;">
                                💡 Insights y Recomendaciones
                            </h4>
                            <div style="background: linear-gradient(135deg, #f7931e15 0%, #f7931e05 100%); border: 1px solid #f7931e; border-radius: 8px; padding: 18px;">
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; font-size: 0.85rem;">

                                    <div style="color: #e6edf3;">
                                        📊 <strong>Performance General:</strong>
                                        <?php
                                        $tasa_exito = $total > 0 ? ($pagados / $total) * 100 : 0;
                                        if ($tasa_exito >= 80) {
                                            echo '<span style="color: #3fb950;">Excelente cliente con alta conversión</span>';
                                        } elseif ($tasa_exito >= 60) {
                                            echo '<span style="color: #f0883e;">Cliente confiable con buena conversión</span>';
                                        } else {
                                            echo '<span style="color: #f85149;">Requiere seguimiento y atención especial</span>';
                                        }
                                        ?>
                                    </div>

                                    <div style="color: #e6edf3;">
                                        🎯 <strong>Estrategia Recomendada:</strong>
                                        <?php
                                        $clasificacion_rfm = $cliente_stats['rfm']['clasificacion'] ?? '';
                                        if (strpos($clasificacion_rfm, 'Champions') !== false) {
                                            echo '<span style="color: #f79000;">Programa VIP exclusivo y beneficios premium</span>';
                                        } elseif (strpos($clasificacion_rfm, 'Loyal') !== false) {
                                            echo '<span style="color: #58a6ff;">Programa de fidelización y recompensas</span>';
                                        } elseif (strpos($clasificacion_rfm, 'At Risk') !== false) {
                                            echo '<span style="color: #f85149;">Campaña de retención urgente y ofertas especiales</span>';
                                        } elseif (strpos($clasificacion_rfm, 'New') !== false) {
                                            echo '<span style="color: #3fb950;">Incentivar segunda compra con descuentos</span>';
                                        } else {
                                            echo '<span style="color: #8b949e;">Mantener engagement con contenido relevante</span>';
                                        }
                                        ?>
                                    </div>

                                    <div style="color: #e6edf3;">
                                        📈 <strong>Análisis de Tendencia:</strong>
                                        <?php
                                        $tendencia = $cliente_stats['tendencia']['tendencia'] ?? 'estable';
                                        if ($tendencia == 'creciente') {
                                            echo '<span style="color: #3fb950;">Cliente en crecimiento - potencial upselling</span>';
                                        } elseif ($tendencia == 'decreciente') {
                                            echo '<span style="color: #f85149;">Revisar satisfacción y ofrecer soporte</span>';
                                        } else {
                                            echo '<span style="color: #58a6ff;">Comportamiento estable - mantener relación</span>';
                                        }
                                        ?>
                                    </div>

                                    <div style="color: #e6edf3;">
                                        ⏰ <strong>Estado de Actividad:</strong>
                                        <?php
                                        $dias_ultima = intval($cliente_stats['actividad']['dias_ultima_compra'] ?? 0);
                                        if ($dias_ultima <= 30) {
                                            echo '<span style="color: #3fb950;">Cliente muy activo - momento ideal para ofertas</span>';
                                        } elseif ($dias_ultima <= 90) {
                                            echo '<span style="color: #f0883e;">Cliente activo - mantener comunicación regular</span>';
                                        } elseif ($dias_ultima <= 180) {
                                            echo '<span style="color: #f85149;">Riesgo de inactividad - campaña de reactivación</span>';
                                        } else {
                                            echo '<span style="color: #6e7681;">Cliente inactivo - estrategia de recuperación</span>';
                                        }
                                        ?>
                                    </div>

                                    <div style="color: #e6edf3;">
                                        🏆 <strong>Posición Competitiva:</strong>
                                        <?php
                                        $percentil = floatval($cliente_stats['ranking']['percentil'] ?? 0);
                                        if ($percentil >= 90) {
                                            echo '<span style="color: #f79000;">Top 10% - Cliente elite con máxima prioridad</span>';
                                        } elseif ($percentil >= 75) {
                                            echo '<span style="color: #3fb950;">Top 25% - Cliente premium con alta prioridad</span>';
                                        } elseif ($percentil >= 50) {
                                            echo '<span style="color: #58a6ff;">Top 50% - Cliente valioso con potencial</span>';
                                        } else {
                                            echo '<span style="color: #8b949e;">Gran potencial de crecimiento y desarrollo</span>';
                                        }
                                        ?>
                                    </div>

                                    <div style="color: #e6edf3;">
                                        🔄 <strong>Contexto del Sistema:</strong>
                                        <?php
                                        $tasa_recurrencia = floatval($cliente_stats['recurrencia']['tasa_recurrencia_sistema'] ?? 0);
                                        if ($tasa_recurrencia >= 50) {
                                            echo '<span style="color: #3fb950;">Sistema con alta fidelidad de clientes</span>';
                                        } elseif ($tasa_recurrencia >= 30) {
                                            echo '<span style="color: #f0883e;">Retención promedio del mercado</span>';
                                        } else {
                                            echo '<span style="color: #f85149;">Oportunidad de mejorar estrategia de retención</span>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php endif; ?>
                </div>

            <?php endif; ?>

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

    <style>
    /* Estilos para los modales */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        backdrop-filter: blur(5px);
    }

    .modal-content {
        background: #161b22;
        border: 1px solid #3d444d;
        border-radius: 12px;
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 25px;
        border-bottom: 1px solid #3d444d;
    }

    .modal-close {
        background: none;
        border: none;
        color: #8b949e;
        font-size: 24px;
        cursor: pointer;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        transition: all 0.3s ease;
    }

    .modal-close:hover {
        background: #3d444d;
        color: #e6edf3;
    }

    .modal-body {
        padding: 25px;
    }

    .modal-actions {
        display: flex;
        gap: 15px;
        justify-content: center;
        margin-top: 20px;
    }

    .modulo-archivos .info-card {
        transition: all 0.3s ease;
        border: 1px solid #3d444d;
    }

    .modulo-archivos .info-card:hover {
        border-color: #1f6feb;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(31, 111, 235, 0.15);
    }

    /* Responsive para modales */
    @media (max-width: 768px) {
        .modal-content {
            width: 95%;
            margin: 10px;
        }

        .modal-header,
        .modal-body {
            padding: 15px 20px;
        }

        .modal-actions {
            flex-direction: column;
        }

        .gestion-archivos > div {
            grid-template-columns: 1fr !important;
        }
    }
    </style>

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
</body>
</html>
