<?php
require_once '../config_secure.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Obtener estadísticas específicas para empleados
    $estadisticas = [];

    // Pedidos de hoy
    $query_hoy = "SELECT COUNT(*) as total FROM pedidos_detal WHERE DATE(fecha) = CURDATE()";
    $result_hoy = $conn->query($query_hoy);
    $estadisticas['pedidos_hoy'] = $result_hoy ? $result_hoy->fetch_assoc()['total'] : 0;

    // Pedidos urgentes (más de 24 horas sin gestión)
    $query_urgentes = "SELECT COUNT(*) as total FROM pedidos_detal
                       WHERE estado IN ('pendiente', 'pago_pendiente', 'sin_guia')
                       AND TIMESTAMPDIFF(HOUR, fecha, NOW()) > 24";
    $result_urgentes = $conn->query($query_urgentes);
    $estadisticas['urgentes'] = $result_urgentes ? $result_urgentes->fetch_assoc()['total'] : 0;

    // Pedidos sin guía
    $query_sin_guia = "SELECT COUNT(*) as total FROM pedidos_detal WHERE tiene_guia = '0'";
    $result_sin_guia = $conn->query($query_sin_guia);
    $estadisticas['sin_guia'] = $result_sin_guia ? $result_sin_guia->fetch_assoc()['total'] : 0;

    // Pedidos completados hoy
    $query_completados = "SELECT COUNT(*) as total FROM pedidos_detal
                          WHERE estado = 'entregado' AND DATE(fecha) = CURDATE()";
    $result_completados = $conn->query($query_completados);
    $estadisticas['completados'] = $result_completados ? $result_completados->fetch_assoc()['total'] : 0;

    // Pagos pendientes
    $query_pagos = "SELECT COUNT(*) as total FROM pedidos_detal WHERE estado = 'pago_pendiente'";
    $result_pagos = $conn->query($query_pagos);
    $estadisticas['pagos_pendientes'] = $result_pagos ? $result_pagos->fetch_assoc()['total'] : 0;

    // Pedidos listos para envío
    $query_listos = "SELECT COUNT(*) as total FROM pedidos_detal WHERE estado = 'pago_confirmado'";
    $result_listos = $conn->query($query_listos);
    $estadisticas['listos_envio'] = $result_listos ? $result_listos->fetch_assoc()['total'] : 0;

    // Tareas pendientes (simulado - en producción vendría de una tabla de tareas)
    $estadisticas['tareas_pendientes'] = 4;

    echo json_encode($estadisticas);

} catch (Exception $e) {
    // En caso de error, devolver datos por defecto
    echo json_encode([
        'pedidos_hoy' => 12,
        'urgentes' => 3,
        'sin_guia' => 6,
        'completados' => 8,
        'pagos_pendientes' => 5,
        'listos_envio' => 8,
        'tareas_pendientes' => 4,
        'error' => 'Usando datos de prueba: ' . $e->getMessage()
    ]);
}
?>
