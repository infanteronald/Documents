<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config_secure.php';
require_once '../filters.php';
require_once '../ui-helpers.php';
require_once '../notifications/notification_helpers.php';
require_once '../php82_helpers.php';

// Filtrar solo pedidos sin guía (tiene_guia = 0)
try {
    $filter = new PedidosFilter($conn);

    // Modificar query para filtrar solo pedidos sin guía
    $filter->setCustomWhere("tiene_guia = '0'");

    $filter_data = $filter->processFilters();

    // Extraer datos
    $pedidos = $filter_data['pedidos'];
    $total_pedidos = $filter_data['total_pedidos'];
    $monto_total_real = $filter_data['monto_total_real'];
    $total_paginas = $filter_data['total_paginas'];
    $metodos_pago = $filter_data['metodos_pago'];
    $ciudades = $filter_data['ciudades'];

    // Obtener ciudades específicas para pedidos sin guía
    $ciudades_sin_guia = [];
    $query_ciudades = "SELECT DISTINCT ciudad FROM pedidos_detal WHERE tiene_guia = '0' AND ciudad IS NOT NULL AND ciudad != '' ORDER BY ciudad";
    $result_ciudades = $conn->query($query_ciudades);
    if ($result_ciudades) {
        while ($row = $result_ciudades->fetch_assoc()) {
            $ciudades_sin_guia[] = $row['ciudad'];
        }
    }

    // Parámetros para la vista
    $params = $filter_data['params'];
    $filtro = $params['filtro'];
    $buscar = $params['buscar'];
    $metodo_pago = $params['metodo_pago'];
    $ciudad = $params['ciudad'];
    $fecha_desde = $params['fecha_desde'];
    $fecha_hasta = $params['fecha_hasta'];
    $page = $params['page'];
    $limite = $params['limite'];
    $offset = ($page - 1) * $limite;

} catch (Exception $e) {
    die("Error en los filtros: " . $e->getMessage());
}

// Función para generar información completa del cliente
function generate_full_customer_info($pedido) {
    $nombre = !empty($pedido['nombre']) ? htmlspecialchars($pedido['nombre']) : 'Sin nombre';
    $telefono = !empty($pedido['telefono']) ? htmlspecialchars($pedido['telefono']) : 'Sin teléfono';
    $correo = !empty($pedido['correo']) ? htmlspecialchars($pedido['correo']) : 'Sin correo';
    $ciudad = !empty($pedido['ciudad']) ? htmlspecialchars($pedido['ciudad']) : 'Sin ciudad';
    $barrio = !empty($pedido['barrio']) ? htmlspecialchars($pedido['barrio']) : 'Sin barrio';
    $direccion = !empty($pedido['direccion']) ? htmlspecialchars($pedido['direccion']) : 'Sin dirección';

    // Formatear teléfono para WhatsApp
    $telefono_whatsapp = preg_replace('/[^0-9]/', '', $telefono);
    if (strlen($telefono_whatsapp) === 10) {
        $telefono_whatsapp = '57' . $telefono_whatsapp;
    }

    return [
        'nombre' => $nombre,
        'telefono' => $telefono,
        'telefono_whatsapp' => $telefono_whatsapp,
        'correo' => $correo,
        'ciudad' => $ciudad,
        'barrio' => $barrio,
        'direccion' => $direccion,
        'direccion_completa' => $direccion . ', ' . $barrio . ', ' . $ciudad
    ];
}

// Función para calcular prioridad y tiempo transcurrido
function calculate_priority_and_time($pedido) {
    $fecha_pedido = new DateTime($pedido['fecha']);
    $fecha_actual = new DateTime();
    $diferencia = $fecha_actual->diff($fecha_pedido);

    $horas_transcurridas = ($diferencia->days * 24) + $diferencia->h;
    $minutos_transcurridos = ($horas_transcurridas * 60) + $diferencia->i;

    // Determinar prioridad
    $prioridad = 'verde';
    if ($horas_transcurridas > 48) {
        $prioridad = 'rojo';
    } elseif ($horas_transcurridas > 24) {
        $prioridad = 'amarillo';
    }

    // Formatear tiempo transcurrido
    $tiempo_formato = '';
    if ($diferencia->days > 0) {
        $tiempo_formato = $diferencia->days . 'd ' . $diferencia->h . 'h';
    } elseif ($diferencia->h > 0) {
        $tiempo_formato = $diferencia->h . 'h ' . $diferencia->i . 'm';
    } else {
        $tiempo_formato = $diferencia->i . 'm';
    }

    return [
        'prioridad' => $prioridad,
        'tiempo_transcurrido' => $tiempo_formato,
        'minutos_transcurridos' => $minutos_transcurridos,
        'horas_transcurridas' => $horas_transcurridas
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>VitalCarga - Gestión de Guías de Envío</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🚚</text></svg>">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#0d1117">
    <link rel="stylesheet" href="../listar_pedidos.css">

    <!-- Sistema de Notificaciones -->
    <link rel="stylesheet" href="../notifications/notifications.css">
    <link rel="stylesheet" href="../notifications/push_notifications.css">

    <style>
        /* Estilos específicos para VitalCarga */
        .header-title {
            color: #58a6ff;
            font-weight: 600;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .vitalcarga-info {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            border: 1px solid #3b82f6;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 15px;
            color: white;
            font-size: 0.9rem;
        }

        .cliente-info-completa {
            background: #21262d;
            border: 1px solid #30363d;
            border-radius: 6px;
            padding: 10px;
            margin: 5px 0;
            font-size: 0.85rem;
            line-height: 1.4;
        }

        .cliente-info-completa .nombre-cliente {
            font-weight: 600;
            color: #58a6ff;
            margin-bottom: 5px;
        }

        .cliente-info-completa .info-row {
            margin-bottom: 3px;
            color: #e6edf3;
        }

        .cliente-info-completa .info-row strong {
            color: #8b949e;
            font-weight: 500;
            display: inline-block;
            min-width: 80px;
        }

        .btn-cargar-guia {
            background: #28a745;
            border: 1px solid #28a745;
            border-radius: 4px;
            padding: 4px 8px;
            color: white;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s ease;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            margin-right: 4px;
            min-width: 70px;
            justify-content: center;
        }

        .btn-cargar-guia:hover {
            background: #218838;
            border-color: #1e7e34;
            transform: translateY(-1px);
        }

        .btn-whatsapp-transportista {
            background: #25d366;
            border: 1px solid #25d366;
            border-radius: 4px;
            padding: 4px 8px;
            color: white;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s ease;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            min-width: 70px;
            justify-content: center;
        }

        .btn-whatsapp-transportista:hover {
            background: #20b358;
            border-color: #1e7e34;
            transform: translateY(-1px);
        }

        .acciones-container {
            display: flex;
            flex-direction: row;
            gap: 4px;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
        }

        .tabla-vitalcarga {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            table-layout: fixed;
        }

        .tabla-vitalcarga th {
            background: #21262d;
            color: #e6edf3;
            padding: 10px 6px;
            text-align: center;
            font-weight: 600;
            border-bottom: 2px solid #30363d;
            font-size: 0.85rem;
            vertical-align: middle;
            line-height: 1.2;
        }

        .header-label {
            font-size: 0.7rem;
            font-weight: 500;
            color: #8b949e;
            display: block;
            margin-top: 2px;
        }

        .tabla-vitalcarga td {
            padding: 6px;
            border-bottom: 1px solid #30363d;
            vertical-align: middle;
            font-size: 0.85rem;
        }

        .tabla-vitalcarga tr:hover {
            background: rgba(88, 166, 255, 0.05);
        }

        .col-id { width: 60px; text-align: center; }
        .col-fecha { width: 90px; text-align: center; }
        .col-cliente { width: 180px; text-align: left; }
        .col-direccion { width: 220px; text-align: left; }
        .col-recaudo { width: 70px; text-align: center; }
        .col-tiempo { width: 90px; text-align: center; }
        .col-btn-guia,
        .col-btn-llamar,
        .col-btn-whatsapp,
        .col-btn-maps,
        .col-btn-estado,
        .col-btn-notas,
        .col-btn-programar,
        .col-btn-foto {
            width: 50px;
            text-align: center;
            padding: 4px;
        }

        .whatsapp-link {
            color: #25d366;
            text-decoration: none;
            font-size: 1.1rem;
            margin-right: 5px;
        }

        .whatsapp-link:hover {
            color: #1faa54;
        }

        .email-link {
            color: #58a6ff;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .email-link:hover {
            color: #79c0ff;
        }

        .info-cell {
            font-size: 0.85rem;
            line-height: 1.3;
        }

        .nombre-completo {
            font-weight: 600;
            color: #58a6ff;
            margin-bottom: 2px;
        }

        .telefono-whatsapp {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .correo-cell {
            word-break: break-all;
        }

        .direccion-cell {
            font-size: 0.8rem;
            line-height: 1.2;
        }

        .recaudo-cell {
            font-size: 0.8rem;
            font-weight: 600;
        }

        .recaudo-si {
            color: #f0ad4e;
        }

        .recaudo-no {
            color: #5cb85c;
        }

        /* Estilos para datos del cliente */
        .datos-cliente {
            font-size: 0.8rem;
            line-height: 1.3;
        }

        .nombre-cliente {
            font-weight: 600;
            color: #58a6ff;
            margin-bottom: 4px;
            font-size: 0.9rem;
        }

        .telefono-cliente {
            color: #e6edf3;
            margin-bottom: 2px;
            font-size: 0.8rem;
        }

        .email-cliente {
            color: #8b949e;
            font-size: 0.8rem;
            word-break: break-word;
        }

        .direccion-completa {
            color: #e6edf3;
            font-size: 0.8rem;
            line-height: 1.3;
            word-break: break-word;
            padding: 2px 0;
        }

        /* Estilos para botones de acción icono */
        .btn-accion-icono {
            width: 36px;
            height: 36px;
            border: 1px solid;
            border-radius: 6px;
            color: white;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s ease;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            -webkit-user-select: none;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
            margin: 0 auto;
        }

        .btn-accion-icono:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(0,0,0,0.2);
            filter: brightness(1.1);
        }

        .btn-accion-icono:active {
            transform: translateY(0);
            box-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }

        .btn-cargar-guia {
            background: #28a745;
            border-color: #28a745;
        }

        .btn-cargar-guia:hover {
            background: #218838;
            border-color: #1e7e34;
        }

        .btn-llamar {
            background: #007bff;
            border-color: #007bff;
        }

        .btn-llamar:hover {
            background: #0056b3;
            border-color: #004085;
        }

        .btn-whatsapp {
            background: #25d366;
            border-color: #25d366;
        }

        .btn-whatsapp:hover {
            background: #20b358;
            border-color: #1e7e34;
        }

        .btn-maps {
            background: #dc3545;
            border-color: #dc3545;
        }

        .btn-maps:hover {
            background: #c82333;
            border-color: #bd2130;
        }

        .btn-estado {
            background: #ffc107;
            border-color: #ffc107;
            color: #212529;
        }

        .btn-estado:hover {
            background: #e0a800;
            border-color: #d39e00;
        }

        .btn-programar {
            background: #6f42c1;
            border-color: #6f42c1;
        }

        .btn-programar:hover {
            background: #5a32a3;
            border-color: #4e2a8e;
        }

        .btn-notas {
            background: #17a2b8;
            border-color: #17a2b8;
        }

        .btn-notas:hover {
            background: #138496;
            border-color: #117a8b;
        }

        .btn-foto {
            background: #e83e8c;
            border-color: #e83e8c;
        }

        .btn-foto:hover {
            background: #e21e7b;
            border-color: #d91a72;
        }

        /* Estilos para tiempo transcurrido y semáforo */
        .tiempo-container {
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
        }

        .semaforo-urgencia {
            font-size: 1.2rem;
            cursor: pointer;
        }

        .tiempo-transcurrido {
            font-size: 0.8rem;
            font-weight: 600;
            color: #e6edf3;
        }

        .semaforo-urgencia.rojo {
            animation: pulso-rojo 2s infinite;
        }

        .semaforo-urgencia.amarillo {
            animation: pulso-amarillo 3s infinite;
        }

        @keyframes pulso-rojo {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        @keyframes pulso-amarillo {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        /* Prioridad visual para recaudo */
        .recaudo-priority {
            background: rgba(255, 107, 53, 0.1);
            border: 1px solid #ff6b35;
            border-radius: 4px;
            padding: 2px 6px;
            font-weight: 700;
            box-shadow: 0 0 8px rgba(255, 107, 53, 0.3);
        }

        /* Alertas visuales para filas */
        .fila-pedido.urgente {
            background: rgba(255, 23, 68, 0.05);
            border-left: 4px solid #ff1744;
        }

        .fila-pedido.recaudo-importante {
            background: rgba(255, 107, 53, 0.05);
            border-left: 4px solid #ff6b35;
        }

        .fila-pedido.antiguo {
            background: rgba(255, 193, 7, 0.05);
            border-left: 4px solid #ffc107;
        }

        @media (max-width: 1200px) {
            .tabla-vitalcarga {
                font-size: 0.75rem;
            }

            .col-cliente { width: 160px; }
            .col-direccion { width: 180px; }
            .col-recaudo { width: 60px; }
            .col-tiempo { width: 70px; }
            .col-btn-guia,
            .col-btn-llamar,
            .col-btn-whatsapp,
            .col-btn-maps,
            .col-btn-estado,
            .col-btn-notas,
            .col-btn-programar,
            .col-btn-foto { width: 45px; }

            .btn-accion-icono {
                width: 32px;
                height: 32px;
                font-size: 0.95rem;
            }
        }

        /* 📱 MOBILE APP OPTIMIZATION */
        @media (max-width: 768px) {
            body {
                padding-bottom: 80px;
                -webkit-touch-callout: none;
                -webkit-user-select: none;
                user-select: none;
                -webkit-tap-highlight-color: transparent;
                touch-action: manipulation;
            }

            /* Large app-like buttons */
            .btn-accion-icono {
                width: 42px !important;
                height: 42px !important;
                font-size: 1.2rem !important;
                border-radius: 50% !important;
                box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
                transition: all 0.2s ease !important;
            }

            .btn-accion-icono:active {
                transform: scale(0.95) !important;
                box-shadow: 0 2px 4px rgba(0,0,0,0.3) !important;
                /* Haptic feedback simulation */
                animation: pulse 0.1s ease-in-out !important;
            }

            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(0.95); }
                100% { transform: scale(1); }
            }

            /* Optimized table for mobile */
            .tabla-vitalcarga {
                font-size: 0.75rem;
                border-collapse: separate;
                border-spacing: 2px;
            }

            .col-cliente { width: 150px; }
            .col-direccion { width: 170px; }
            .col-recaudo { width: 50px; }
            .col-tiempo { width: 65px; }
            .col-btn-guia,
            .col-btn-llamar,
            .col-btn-whatsapp,
            .col-btn-maps,
            .col-btn-estado,
            .col-btn-notas,
            .col-btn-programar,
            .col-btn-foto { width: 50px; }

            /* Mobile app navigation */
            .mobile-nav {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: rgba(13, 17, 23, 0.95);
                backdrop-filter: blur(10px);
                border-top: 1px solid #30363d;
                padding: 10px;
                display: flex;
                justify-content: space-around;
                align-items: center;
                z-index: 1000;
                box-shadow: 0 -4px 20px rgba(0,0,0,0.3);
            }

            .mobile-nav-btn {
                display: flex;
                flex-direction: column;
                align-items: center;
                padding: 8px 12px;
                border-radius: 8px;
                background: transparent;
                border: none;
                color: #8b949e;
                text-decoration: none;
                transition: all 0.2s;
                min-width: 60px;
            }

            .mobile-nav-btn:hover,
            .mobile-nav-btn.active {
                background: rgba(88, 166, 255, 0.1);
                color: #58a6ff;
                transform: translateY(-2px);
            }

            .mobile-nav-btn .icon {
                font-size: 1.3rem;
                margin-bottom: 2px;
            }

            .mobile-nav-btn .label {
                font-size: 0.7rem;
                font-weight: 500;
            }

            /* Touch optimizations */
            .container-fluid {
                padding: 10px 5px;
            }

            .table-responsive {
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            }

            /* Mobile notifications */
            .mobile-notification {
                position: fixed;
                top: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: #238636;
                color: white;
                padding: 12px 20px;
                border-radius: 25px;
                font-size: 0.9rem;
                font-weight: 500;
                z-index: 9999;
                opacity: 0;
                transition: all 0.3s ease;
                box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            }

            .mobile-notification.show {
                opacity: 1;
                transform: translateX(-50%) translateY(10px);
            }

            /* Improved touch targets */
            td {
                padding: 8px 4px !important;
            }

            th {
                padding: 12px 6px !important;
                position: sticky;
                top: 0;
                background: #21262d !important;
                z-index: 10;
            }

            /* Mobile gestures */
            .tabla-vitalcarga tbody tr {
                transition: all 0.2s ease;
            }

            .tabla-vitalcarga tbody tr:active {
                background: rgba(88, 166, 255, 0.05);
                transform: scale(0.98);
            }

            /* Improved scrolling */
            .table-responsive {
                -webkit-overflow-scrolling: touch;
                scroll-behavior: smooth;
            }

            /* Status indicators with better mobile visibility */
            .semaforo-urgencia {
                width: 12px !important;
                height: 12px !important;
                margin: 0 auto;
                box-shadow: 0 0 8px rgba(0,0,0,0.3);
            }

            .tiempo-transcurrido {
                font-size: 0.65rem !important;
                font-weight: 600;
            }

            /* Mobile header optimizations */
            .header-label {
                font-size: 0.6rem !important;
                font-weight: 600;
                color: #8b949e;
            }

            .nombre-cliente-principal {
                font-size: 0.85rem;
            }

            .telefono-link {
                font-size: 0.75rem;
            }

            .direccion-completa {
                font-size: 0.75rem;
            }
        }

        /* Estilos específicos para móviles */
        @media (max-width: 480px) {
            .header-title {
                font-size: 1rem;
            }

            .vitalcarga-info {
                font-size: 0.8rem;
                padding: 8px;
            }

            .tabla-vitalcarga {
                font-size: 0.65rem;
            }

            .col-cliente { width: 120px; }
            .col-direccion { width: 140px; }
            .col-recaudo { width: 45px; }
            .col-tiempo { width: 50px; }
            .col-btn-guia,
            .col-btn-llamar,
            .col-btn-whatsapp,
            .col-btn-maps,
            .col-btn-estado,
            .col-btn-notas,
            .col-btn-programar,
            .col-btn-foto { width: 35px; }

            .btn-accion-icono {
                width: 28px;
                height: 28px;
                font-size: 0.75rem;
            }

            .btn-accion-icono {
                width: 26px;
                height: 26px;
                font-size: 0.7rem;
            }

            .datos-cliente {
                font-size: 0.7rem;
            }

            .direccion-completa {
                font-size: 0.7rem;
            }

            .tabla-vitalcarga th {
                font-size: 0.75rem;
                padding: 8px 4px;
            }

            .header-label {
                font-size: 0.6rem;
            }

            .tabla-vitalcarga td {
                padding: 4px;
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>
<div class="sticky-bar">
    <div class="header-ultra-compacto">
        <div class="fila-compacta">
            <div class="header-title">
                🚚 VitalCarga - Gestión de Guías de Envío
            </div>

            <div class="filtros-rapidos">
                <div class="filtros-fila-mobile">
                    <select name="filtro" class="select-compacto" onchange="aplicarFiltroRapido(this.value)">
                        <option value="todos" <?php echo ($filtro=='todos' ? 'selected' : ''); ?>>📦 Todos</option>
                        <option value="hoy" <?php echo ($filtro=='hoy' ? 'selected' : ''); ?>>📅 Hoy</option>
                        <option value="semana" <?php echo ($filtro=='semana' ? 'selected' : ''); ?>>📊 Semana</option>
                        <option value="mes" <?php echo ($filtro=='mes' ? 'selected' : ''); ?>>📈 Mes</option>
                        <option value="ultimos_30" <?php echo ($filtro=='ultimos_30' ? 'selected' : ''); ?>>📆 Últimos 30 días</option>
                        <option value="pago_confirmado" <?php echo ($filtro=='pago_confirmado' ? 'selected' : ''); ?>>✅ Pagados</option>
                        <option value="pendientes_atencion" <?php echo ($filtro=='pendientes_atencion' ? 'selected' : ''); ?>>⏳ Pendientes</option>
                    </select>
                </div>

                <div class="busqueda-fila-mobile">
                    <input type="text"
                           id="busquedaRapida"
                           name="buscar"
                           value="<?php echo h($buscar); ?>"
                           placeholder="🔍 Buscar por ID, nombre, email, teléfono, ciudad..."
                           class="input-compacto"
                           onkeyup="busquedaEnTiempoReal(this.value)"
                           autocomplete="off">

                    <?php if($buscar): ?>
                        <button type="button" class="btn-limpiar-busqueda" onclick="limpiarBusqueda()" title="Limpiar búsqueda">✕</button>
                    <?php endif; ?>
                </div>

                <div class="filtro-ciudad-mobile">
                    <select id="filtro-ciudad" class="select-compacto" onchange="aplicarFiltroCiudad()">
                        <option value="todas">🏙️ Todas las ciudades</option>
                        <?php foreach($ciudades_sin_guia as $ciudad): ?>
                            <option value="<?php echo htmlspecialchars($ciudad); ?>"
                                    <?php echo (isset($_GET['ciudad']) && $_GET['ciudad'] == $ciudad) ? 'selected' : ''; ?>>
                                📍 <?php echo htmlspecialchars($ciudad); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="vitalcarga-info">
        <strong>📋 Información:</strong> Esta página muestra únicamente los pedidos que aún no tienen guía de envío asignada.
        Total de pedidos sin guía: <strong><?php echo $total_pedidos; ?></strong>
    </div>

    <div class="contenedor-tabla">
        <table class="tabla-vitalcarga">
            <thead>
                <tr>
                    <th class="col-id">📦 ID</th>
                    <th class="col-fecha">📅 Fecha</th>
                    <th class="col-cliente">👤 Cliente</th>
                    <th class="col-direccion">📍 Dirección</th>
                    <th class="col-tiempo">⏱️ Tiempo</th>
                    <th class="col-recaudo">💰 Recaudo</th>
                    <th class="col-btn-guia" title="Cargar guía">📦<br><span class="header-label">Guía</span></th>
                    <th class="col-btn-llamar" title="Llamar cliente">📞<br><span class="header-label">Llamar</span></th>
                    <th class="col-btn-whatsapp" title="WhatsApp cliente">💬<br><span class="header-label">WhatsApp</span></th>
                    <th class="col-btn-maps" title="Ver en Maps">🗺️<br><span class="header-label">Maps</span></th>
                    <th class="col-btn-estado" title="Cambiar estado">🚚<br><span class="header-label">Estado</span></th>
                    <th class="col-btn-notas" title="Agregar notas">📝<br><span class="header-label">Notas</span></th>
                    <th class="col-btn-programar" title="Programar entrega">⏰<br><span class="header-label">Programar</span></th>
                    <th class="col-btn-foto" title="Subir foto">📸<br><span class="header-label">Foto</span></th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($pedidos) == 0): ?>
                    <tr>
                        <td colspan="14" class="tabla-vacia">
                            <div class="mensaje-vacio">
                                <div class="icono-vacio">✅</div>
                                <div class="titulo-vacio">¡Excelente!</div>
                                <div class="subtitulo-vacio">Todos los pedidos ya tienen guía de envío asignada</div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach($pedidos as $p): ?>
                        <?php
                        $tiempo_info = calculate_priority_and_time($p);
                        $clase_fila = 'fila-pedido';
                        if ($tiempo_info['prioridad'] === 'rojo') {
                            $clase_fila .= ' urgente';
                        } elseif ($tiempo_info['prioridad'] === 'amarillo') {
                            $clase_fila .= ' antiguo';
                        }
                        if ($p['recaudo'] == '1') {
                            $clase_fila .= ' recaudo-importante';
                        }
                        ?>
                        <tr class="<?php echo $clase_fila; ?>" data-id="<?php echo $p['id']; ?>">
                            <!-- ID del Pedido -->
                            <td class="col-id">
                                <a href="../ver_detalle_pedido.php?id=<?php echo $p['id']; ?>" class="enlace-pedido" target="_blank">
                                    #<?php echo $p['id']; ?>
                                </a>
                            </td>

                            <!-- Fecha del Pedido -->
                            <td class="col-fecha">
                                <?php $fecha_info = format_date($p['fecha']); ?>
                                <div class="info-fecha">
                                    <div class="fecha-principal"><?php echo $fecha_info['fecha_principal']; ?></div>
                                    <div class="hora-pedido"><?php echo $fecha_info['hora_pedido']; ?></div>
                                </div>
                            </td>

                            <!-- Datos del Cliente -->
                            <td class="col-cliente">
                                <?php $cliente_info = generate_full_customer_info($p); ?>
                                <div class="datos-cliente">
                                    <div class="nombre-cliente">
                                        <?php echo $cliente_info['nombre']; ?>
                                    </div>
                                    <div class="telefono-cliente">
                                        📞 <?php echo $cliente_info['telefono']; ?>
                                    </div>
                                    <div class="email-cliente">
                                        📧 <?php echo $cliente_info['correo']; ?>
                                    </div>
                                </div>
                            </td>

                            <!-- Dirección Completa -->
                            <td class="col-direccion">
                                <div class="direccion-completa">
                                    <?php echo $cliente_info['direccion']; ?>, <?php echo $cliente_info['barrio']; ?>, <?php echo $cliente_info['ciudad']; ?>
                                </div>
                            </td>

                            <!-- Tiempo Transcurrido -->
                            <td class="col-tiempo">
                                <?php $tiempo_info = calculate_priority_and_time($p); ?>
                                <div class="tiempo-container">
                                    <div class="semaforo-urgencia <?php echo $tiempo_info['prioridad']; ?>"
                                         title="Prioridad: <?php echo ucfirst($tiempo_info['prioridad']); ?>">
                                        <?php
                                        echo $tiempo_info['prioridad'] === 'rojo' ? '🔴' :
                                            ($tiempo_info['prioridad'] === 'amarillo' ? '🟡' : '🟢');
                                        ?>
                                    </div>
                                    <div class="tiempo-transcurrido">
                                        <?php echo $tiempo_info['tiempo_transcurrido']; ?>
                                    </div>
                                </div>
                            </td>

                            <!-- Recaudo -->
                            <td class="col-recaudo">
                                <div class="info-cell recaudo-cell" style="text-align: center;">
                                    <span class="<?php echo $p['recaudo'] == '1' ? 'recaudo-si recaudo-priority' : 'recaudo-no'; ?>"
                                          title="<?php echo $p['recaudo'] == '1' ? 'Con recaudo (pago contra entrega)' : 'Sin recaudo (pago ya realizado)'; ?>">
                                        💰 <?php echo $p['recaudo'] == '1' ? 'Sí' : 'No'; ?>
                                    </span>
                                </div>
                            </td>

                            <!-- Botón Cargar Guía -->
                            <td class="col-btn-guia">
                                <button
                                    class="btn-accion-icono btn-cargar-guia"
                                    onclick="abrirModalGuiaVital(<?php echo $p['id']; ?>)"
                                    title="Cargar guía de envío">
                                    📦
                                </button>
                            </td>

                            <!-- Botón Llamar -->
                            <td class="col-btn-llamar">
                                <button
                                    class="btn-accion-icono btn-llamar"
                                    onclick="llamarCliente('<?php echo $cliente_info['telefono']; ?>')"
                                    title="Llamar cliente">
                                    📞
                                </button>
                            </td>

                            <!-- Botón WhatsApp -->
                            <td class="col-btn-whatsapp">
                                <button
                                    class="btn-accion-icono btn-whatsapp"
                                    onclick="contactarClienteWhatsApp('<?php echo $cliente_info['telefono_whatsapp']; ?>', '<?php echo $cliente_info['nombre']; ?>', <?php echo $p['id']; ?>)"
                                    title="WhatsApp cliente">
                                    💬
                                </button>
                            </td>

                            <!-- Botón Maps -->
                            <td class="col-btn-maps">
                                <button
                                    class="btn-accion-icono btn-maps"
                                    onclick="abrirGoogleMaps('<?php echo $cliente_info['direccion_completa']; ?>')"
                                    title="Ver ubicación">
                                    🗺️
                                </button>
                            </td>

                            <!-- Botón Estado -->
                            <td class="col-btn-estado">
                                <button
                                    class="btn-accion-icono btn-estado"
                                    onclick="cambiarEstadoEntrega(<?php echo $p['id']; ?>)"
                                    title="Cambiar estado">
                                    🚚
                                </button>
                            </td>

                            <!-- Botón Notas -->
                            <td class="col-btn-notas">
                                <button
                                    class="btn-accion-icono btn-notas"
                                    onclick="agregarNotasTransportista(<?php echo $p['id']; ?>)"
                                    title="Agregar notas">
                                    📝
                                </button>
                            </td>

                            <!-- Botón Programar -->
                            <td class="col-btn-programar">
                                <button
                                    class="btn-accion-icono btn-programar"
                                    onclick="programarEntrega(<?php echo $p['id']; ?>)"
                                    title="Programar entrega">
                                    ⏰
                                </button>
                            </td>

                            <!-- Botón Foto -->
                            <td class="col-btn-foto">
                                <button
                                    class="btn-accion-icono btn-foto"
                                    onclick="subirFotoEntrega(<?php echo $p['id']; ?>)"
                                    title="Subir foto">
                                    📸
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    <?php if($total_paginas > 1): ?>
        <div class="paginacion-nueva">
            <div class="info-paginacion">
                Mostrando <?php echo count($pedidos); ?> de <?php echo $total_pedidos; ?> pedidos sin guía
            </div>
            <div class="controles-paginacion">
                <?php for($i = 1; $i <= $total_paginas; $i++): ?>
                    <a href="?filtro=<?php echo $filtro; ?>&buscar=<?php echo urlencode($buscar); ?>&metodo_pago=<?php echo urlencode($metodo_pago); ?>&ciudad=<?php echo urlencode($ciudad); ?>&fecha_desde=<?php echo urlencode($fecha_desde); ?>&fecha_hasta=<?php echo urlencode($fecha_hasta); ?>&page=<?php echo $i; ?>"
                       class="btn-pagina <?php echo $i == $page ? 'activa' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- MODAL SUBIR GUÍA VITAL -->
<div id="modal-guia-vital-bg" class="modal-detalle-bg" style="display:none;">
    <div class="modal-detalle" style="max-width:400px;text-align:center;">
        <button class="cerrar-modal" onclick="cerrarModalGuiaVital()">×</button>
        <div style="font-size:1.2rem;font-weight:600;margin-bottom:15px;color:#58a6ff;">
            🚚 Cargar Guía de Envío
        </div>
        <div id="info-pedido-vital" style="background:#21262d;padding:10px;border-radius:6px;margin-bottom:15px;text-align:left;">
            <!-- Información del pedido se cargará aquí -->
        </div>
        <form id="formGuiaVital" enctype="multipart/form-data" method="POST" action="../subir_guia.php" autocomplete="off">
            <input type="hidden" name="id_pedido" id="guia_vital_id_pedido">

            <div style="margin-bottom:15px;">
                <label style="display:block;margin-bottom:5px;font-weight:500;"> Foto de la Guía:</label>
                <input type="file" name="guia" id="guia_file_vital" accept="image/*,application/pdf" required style="width:100%;padding:8px;border:1px solid #30363d;border-radius:4px;background:#0d1117;color:#e6edf3;">
            </div>

            <div style="margin-bottom:15px;">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;color:#e6edf3;">
                    <input type="checkbox" id="marcarEnviadoVital" name="marcar_enviado" value="1" checked style="width:auto;margin:0;">
                    <span style="font-weight:500;">🚚 Marcar pedido como ENVIADO</span>
                </label>
                <small style="color:#8b949e;display:block;margin-top:5px;margin-left:25px;">
                    ✅ Recomendado: Marcar como enviado automáticamente al cargar la guía
                </small>
            </div>

            <button type="submit" class="btn-cargar-guia" style="width:100%;justify-content:center;">
                <span>📤</span>
                <span>Cargar Guía y Notificar</span>
            </button>
        </form>
        <div id="guia_vital_status" style="margin-top:15px;font-size:1rem;"></div>
    </div>
</div>

<!-- Incluir notificaciones -->
<div id="notification-container"></div>

<script>
// Variables globales
let currentPedidoId = null;
let isProcessing = false;

// Función para abrir WhatsApp
function abrirWhatsApp(telefono) {
    if (telefono && telefono.length > 0) {
        const url = `https://wa.me/${telefono}`;
        window.open(url, '_blank');
    }
}

// Función específica para contactar cliente desde transportista
function contactarClienteWhatsApp(telefono, nombreCliente, pedidoId) {
    if (telefono && telefono.length > 0) {
        // Mensaje preformateado para transportista
        const mensaje = `Hola ${nombreCliente}, soy de la transportadora encargada de entregar tu pedido #${pedidoId}. Te contacto para coordinar la entrega. ¿Podrías confirmarme tu disponibilidad?`;

        const mensajeCodificado = encodeURIComponent(mensaje);
        const url = `https://wa.me/${telefono}?text=${mensajeCodificado}`;

        window.open(url, '_blank');
    } else {
        alert('No se encontró número de teléfono para este cliente');
    }
}

// Función para llamar cliente directamente
function llamarCliente(telefono) {
    if (telefono && telefono.length > 0) {
        // Detectar si es móvil y usar tel: protocol
        const telefonoLimpio = telefono.replace(/[^0-9]/g, '');
        window.location.href = `tel:${telefonoLimpio}`;
    } else {
        alert('No se encontró número de teléfono para este cliente');
    }
}

// Función para abrir Google Maps
function abrirGoogleMaps(direccion) {
    if (direccion && direccion.length > 0) {
        const direccionCodificada = encodeURIComponent(direccion);
        const url = `https://www.google.com/maps/search/?api=1&query=${direccionCodificada}`;
        window.open(url, '_blank');
    } else {
        alert('No se encontró dirección para este cliente');
    }
}

// Función para cambiar estado de entrega
function cambiarEstadoEntrega(pedidoId) {
    const estados = [
        {valor: 'en_ruta', texto: '🚚 En Ruta'},
        {valor: 'entregado', texto: '✅ Entregado'},
        {valor: 'reintento', texto: '🔄 Reintento'},
        {valor: 'devuelto', texto: '🔙 Devuelto'}
    ];

    let opciones = estados.map(estado => `${estado.valor}: ${estado.texto}`).join('\n');

    const nuevoEstado = prompt(`Seleccione el nuevo estado para el pedido #${pedidoId}:\n\n${opciones}\n\nIngrese el valor (en_ruta, entregado, reintento, devuelto):`);

    if (nuevoEstado && estados.some(e => e.valor === nuevoEstado)) {
        // Aquí se implementaría la actualización del estado
        alert(`Estado cambiado a: ${estados.find(e => e.valor === nuevoEstado).texto}`);
        // TODO: Implementar actualización en base de datos
    } else if (nuevoEstado) {
        alert('Estado no válido. Intente nuevamente.');
    }
}

// Función para programar entrega
function programarEntrega(pedidoId) {
    const fechaActual = new Date();
    const fechaManana = new Date(fechaActual.getTime() + 24 * 60 * 60 * 1000);
    const fechaFormateada = fechaManana.toISOString().slice(0, 16);

    const nuevaFecha = prompt(`Programar entrega para el pedido #${pedidoId}:\n\nIngrese fecha y hora (formato: YYYY-MM-DD HH:MM):`, fechaFormateada.replace('T', ' '));

    if (nuevaFecha) {
        const motivo = prompt('Ingrese el motivo de la reprogramación (opcional):', '');

        // Aquí se implementaría la programación
        alert(`Entrega programada para: ${nuevaFecha}${motivo ? '\nMotivo: ' + motivo : ''}`);
        // TODO: Implementar programación en base de datos y notificaciones
    }
}

// Función para enviar WhatsApp de notificación automática
function enviarWhatsAppNotificacion(telefono, nombreCliente, pedidoId) {
    if (telefono && telefono.length > 0) {
        const mensaje = `Hola ${nombreCliente}, tu pedido #${pedidoId} ya está en camino. Nuestro transportista te contactará pronto para coordinar la entrega. ¡Gracias por tu compra!`;

        const mensajeCodificado = encodeURIComponent(mensaje);
        const url = `https://wa.me/${telefono}?text=${mensajeCodificado}`;

        window.open(url, '_blank');
    } else {
        alert('No se encontró número de teléfono para este cliente');
    }
}

// Función para agregar notas del transportista
function agregarNotasTransportista(pedidoId) {
    const notaActual = prompt('Ingrese las notas del transportista para el pedido #' + pedidoId + ':', '');

    if (notaActual !== null && notaActual.trim() !== '') {
        // Enviar nota al servidor
        fetch('../actualizar_notas_transportista.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `pedido_id=${pedidoId}&notas=${encodeURIComponent(notaActual)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Notas guardadas exitosamente');
            } else {
                alert('Error al guardar las notas: ' + (data.error || 'Error desconocido'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión al guardar las notas');
        });
    }
}

// Función para subir foto de entrega
function subirFotoEntrega(pedidoId) {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.capture = 'environment'; // Usar cámara trasera en móviles

    input.onchange = function(event) {
        const file = event.target.files[0];
        if (file) {
            const formData = new FormData();
            formData.append('foto_entrega', file);
            formData.append('pedido_id', pedidoId);

            // Mostrar indicador de carga
            const loadingMsg = document.createElement('div');
            loadingMsg.innerHTML = '📸 Subiendo foto...';
            loadingMsg.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#0d1117;color:#58a6ff;padding:20px;border-radius:8px;z-index:9999;border:1px solid #30363d;';
            document.body.appendChild(loadingMsg);

            fetch('../subir_foto_entrega.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.body.removeChild(loadingMsg);
                if (data.success) {
                    alert('✅ Foto de entrega subida exitosamente');
                } else {
                    alert('❌ Error al subir la foto: ' + (data.error || 'Error desconocido'));
                }
            })
            .catch(error => {
                document.body.removeChild(loadingMsg);
                console.error('Error:', error);
                alert('❌ Error de conexión al subir la foto');
            });
        }
    };

    input.click();
}

// Función para mostrar notificaciones push
function mostrarNotificacionPush(titulo, mensaje, tipo = 'info') {
    // Verificar si el navegador soporta notificaciones
    if ('Notification' in window) {
        // Pedir permiso si no lo tenemos
        if (Notification.permission === 'default') {
            Notification.requestPermission().then(permission => {
                if (permission === 'granted') {
                    mostrarNotificacion(titulo, mensaje, tipo);
                }
            });
        } else if (Notification.permission === 'granted') {
            mostrarNotificacion(titulo, mensaje, tipo);
        }
    }

    // Mostrar notificación visual en la interfaz
    const notificacion = document.createElement('div');
    notificacion.className = `notificacion-push ${tipo}`;
    notificacion.innerHTML = `
        <div class="notificacion-header">
            <strong>${titulo}</strong>
            <button onclick="this.parentElement.parentElement.remove()" style="background:none;border:none;color:#e6edf3;cursor:pointer;font-size:1.2rem;">&times;</button>
        </div>
        <div class="notificacion-body">${mensaje}</div>
    `;

    notificacion.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #21262d;
        border: 1px solid #30363d;
        border-radius: 8px;
        padding: 15px;
        max-width: 300px;
        z-index: 9999;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        animation: slideIn 0.3s ease-out;
    `;

    document.body.appendChild(notificacion);

    // Auto-remover después de 5 segundos
    setTimeout(() => {
        if (notificacion.parentElement) {
            notificacion.remove();
        }
    }, 5000);
}

function mostrarNotificacion(titulo, mensaje, tipo) {
    const notificacion = new Notification(titulo, {
        body: mensaje,
        icon: 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">🚚</text></svg>',
        tag: 'vitalcarga-' + Date.now()
    });

    notificacion.onclick = function() {
        window.focus();
        this.close();
    };
}

// Función para verificar nuevos pedidos cada 30 segundos
function verificarNuevosPedidos() {
    fetch('../verificar_nuevos_pedidos.php')
    .then(response => response.json())
    .then(data => {
        if (data.nuevos_pedidos && data.nuevos_pedidos > 0) {
            mostrarNotificacionPush(
                '📦 Nuevos Pedidos',
                `Hay ${data.nuevos_pedidos} nuevo${data.nuevos_pedidos > 1 ? 's' : ''} pedido${data.nuevos_pedidos > 1 ? 's' : ''} sin asignar`,
                'info'
            );
        }
    })
    .catch(error => console.error('Error verificando nuevos pedidos:', error));
}

// Función para aplicar filtro por ciudad
function aplicarFiltroCiudad() {
    const selectCiudad = document.getElementById('filtro-ciudad');
    if (selectCiudad) {
        const ciudad = selectCiudad.value;
        const currentUrl = new URL(window.location.href);

        if (ciudad && ciudad !== 'todas') {
            currentUrl.searchParams.set('ciudad', ciudad);
        } else {
            currentUrl.searchParams.delete('ciudad');
        }

        currentUrl.searchParams.set('page', '1');
        window.location.href = currentUrl.toString();
    }
}

// Función para enviar email automático cuando se carga guía
function enviarEmailGuiaCargada(pedidoId, numeroGuia, transportadora) {
    fetch('../enviar_email_guia.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `pedido_id=${pedidoId}&numero_guia=${encodeURIComponent(numeroGuia)}&transportadora=${encodeURIComponent(transportadora)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Email enviado exitosamente');
        } else {
            console.error('Error enviando email:', data.error);
        }
    })
    .catch(error => console.error('Error:', error));
}

// Función para aplicar filtro rápido
function aplicarFiltroRapido(filtro) {
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('filtro', filtro);
    currentUrl.searchParams.set('page', '1');
    window.location.href = currentUrl.toString();
}

// Función para búsqueda en tiempo real
function busquedaEnTiempoReal(valor) {
    clearTimeout(window.searchTimeout);
    window.searchTimeout = setTimeout(() => {
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('buscar', valor);
        currentUrl.searchParams.set('page', '1');
        window.location.href = currentUrl.toString();
    }, 500);
}

// Función para limpiar búsqueda
function limpiarBusqueda() {
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.delete('buscar');
    currentUrl.searchParams.set('page', '1');
    window.location.href = currentUrl.toString();
}

// Función para abrir modal de guía vital
function abrirModalGuiaVital(pedidoId) {
    currentPedidoId = pedidoId;
    document.getElementById('guia_vital_id_pedido').value = pedidoId;

    // Cargar información del pedido
    cargarInfoPedidoVital(pedidoId);

    // Mostrar modal
    document.getElementById('modal-guia-vital-bg').style.display = 'flex';

    // Limpiar formulario
    document.getElementById('formGuiaVital').reset();
    document.getElementById('guia_vital_id_pedido').value = pedidoId;
    document.getElementById('guia_vital_status').innerHTML = '';
}

// Función para cargar información del pedido
function cargarInfoPedidoVital(pedidoId) {
    const fila = document.querySelector(`tr[data-id="${pedidoId}"]`);
    if (fila) {
        const nombreCliente = fila.querySelector('.nombre-cliente').textContent;
        const telefonoCliente = fila.querySelector('.telefono-cliente').textContent;
        const direccionCompleta = fila.querySelector('.direccion-completa').textContent;

        const infoContainer = document.getElementById('info-pedido-vital');

        infoContainer.innerHTML = `
            <div style="font-size:0.9rem;color:#e6edf3;line-height:1.4;">
                <div style="margin-bottom: 6px;"><strong>🆔 Pedido:</strong> #${pedidoId}</div>
                <div style="margin-bottom: 6px;"><strong>👤 Cliente:</strong> ${nombreCliente}</div>
                <div style="margin-bottom: 6px;"><strong>� Teléfono:</strong> ${telefonoCliente}</div>
                <div style="margin-bottom: 6px;"><strong>📍 Dirección:</strong> ${direccionCompleta}</div>
            </div>
        `;
    } else {
        const infoContainer = document.getElementById('info-pedido-vital');
        infoContainer.innerHTML = `<div><strong>🆔 Pedido:</strong> #${pedidoId}</div>`;
    }
}

// Función para cerrar modal
function cerrarModalGuiaVital() {
    document.getElementById('modal-guia-vital-bg').style.display = 'none';
    currentPedidoId = null;
}

// Manejar envío del formulario
document.getElementById('formGuiaVital').addEventListener('submit', function(e) {
    e.preventDefault();

    if (isProcessing) return;

    isProcessing = true;
    const statusDiv = document.getElementById('guia_vital_status');
    statusDiv.innerHTML = '<span style="color:#58a6ff;">📤 Subiendo guía...</span>';

    const formData = new FormData(this);

    // Verificar que tenemos el archivo
    const archivoGuia = formData.get('guia');
    if (!archivoGuia || archivoGuia.size === 0) {
        statusDiv.innerHTML = '<span style="color:#f85149;">❌ Debe seleccionar un archivo de guía</span>';
        isProcessing = false;
        return;
    }

    console.log('Enviando guía para pedido:', formData.get('id_pedido'));
    console.log('Archivo seleccionado:', archivoGuia.name, 'Tamaño:', archivoGuia.size);

    fetch('../subir_guia.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Respuesta recibida. Status:', response.status);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return response.json();
    })
    .then(data => {
        console.log('Datos recibidos:', data);

        if (data.success) {
            statusDiv.innerHTML = '<span style="color:#238636;">✅ Guía subida exitosamente</span>';

            // Mostrar información adicional sobre emails
            let infoAdicional = '';
            if (data.email_cliente_enviado) {
                infoAdicional += '<br><span style="color:#58a6ff;">📧 Email enviado al cliente</span>';
            }
            if (data.email_ventas_enviado) {
                infoAdicional += '<br><span style="color:#58a6ff;">📋 Copia enviada a ventas</span>';
            }

            if (infoAdicional) {
                statusDiv.innerHTML += infoAdicional;
            }

            // Mostrar notificación de éxito
            mostrarNotificacionPush('✅ Guía Cargada', `Guía subida correctamente para pedido #${currentPedidoId}`, 'success');

            // Cerrar modal después de 3 segundos para dar tiempo a leer la info de emails
            setTimeout(() => {
                cerrarModalGuiaVital();
                // Recargar la página para actualizar la lista
                window.location.reload();
            }, 3000);

        } else {
            statusDiv.innerHTML = '<span style="color:#f85149;">❌ Error: ' + (data.error || 'Error desconocido') + '</span>';
        }
    })
    .catch(error => {
        console.error('Error completo:', error);

        if (error.name === 'TypeError' && error.message.includes('fetch')) {
            statusDiv.innerHTML = '<span style="color:#f85149;">❌ Error de red - servidor no responde</span>';
        } else if (error.name === 'TypeError' && error.message.includes('Failed to fetch')) {
            statusDiv.innerHTML = '<span style="color:#f85149;">❌ Error CORS o conexión bloqueada</span>';
        } else {
            statusDiv.innerHTML = '<span style="color:#f85149;">❌ Error: ' + error.message + '</span>';
        }
    })
    .finally(() => {
        isProcessing = false;
    });
});

// Cerrar modal al hacer clic fuera
document.getElementById('modal-guia-vital-bg').addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModalGuiaVital();
    }
});

// Cerrar modal con Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('modal-guia-vital-bg').style.display === 'flex') {
        cerrarModalGuiaVital();
    }
});

// Inicializar notificaciones
document.addEventListener('DOMContentLoaded', function() {
    // Cargar sistema de notificaciones si está disponible
    if (typeof initializeNotifications === 'function') {
        initializeNotifications();
    }

    // Solicitar permisos de notificación
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }

    // Verificar nuevos pedidos cada 30 segundos
    setInterval(verificarNuevosPedidos, 30000);

    console.log('VitalCarga inicializado - Pedidos sin guía:', <?php echo $total_pedidos; ?>);
});
</script>

<!-- Incluir scripts de notificaciones -->
<script src="../notifications/notifications.js"></script>

<!-- Mobile App Navigation (only visible on mobile) -->
<nav class="mobile-nav">
    <a href="vitalcarga.php" class="mobile-nav-btn active">
        <span class="icon">🚚</span>
        <span class="label">Guías</span>
    </a>
    <a href="dashboard_transportista.php" class="mobile-nav-btn">
        <span class="icon">📊</span>
        <span class="label">Dashboard</span>
    </a>
    <a href="progreso_entregas.php" class="mobile-nav-btn">
        <span class="icon">📈</span>
        <span class="label">Progreso</span>
    </a>
    <a href="chat_interno.php" class="mobile-nav-btn">
        <span class="icon">💬</span>
        <span class="label">Chat</span>
    </a>
</nav>

<!-- Mobile Notification Container -->
<div id="mobile-notification" class="mobile-notification"></div>

<script>
// 📱 MOBILE APP ENHANCEMENTS
document.addEventListener('DOMContentLoaded', function() {
    // Only apply mobile features on mobile devices
    if (window.innerWidth <= 768) {
        initializeMobileApp();
    }
});

function initializeMobileApp() {
    // Add haptic feedback simulation
    addHapticFeedback();

    // Add touch gestures
    addTouchGestures();

    // Add mobile notifications
    initializeMobileNotifications();

    // Optimize button interactions
    optimizeButtonInteractions();

    // Add swipe gestures
    addSwipeGestures();
}

function addHapticFeedback() {
    // Simulate haptic feedback with vibration API
    const buttons = document.querySelectorAll('.btn-accion-icono');
    buttons.forEach(button => {
        button.addEventListener('touchstart', function() {
            // Vibrate if supported
            if (navigator.vibrate) {
                navigator.vibrate(50); // Short vibration
            }
        });
    });
}

function addTouchGestures() {
    const table = document.querySelector('.table-responsive');
    let startX, startY;

    table.addEventListener('touchstart', function(e) {
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
    });

    table.addEventListener('touchmove', function(e) {
        if (!startX || !startY) return;

        let currentX = e.touches[0].clientX;
        let currentY = e.touches[0].clientY;

        let diffX = startX - currentX;
        let diffY = startY - currentY;

        // Horizontal swipe
        if (Math.abs(diffX) > Math.abs(diffY)) {
            if (Math.abs(diffX) > 50) {
                // Add visual feedback for horizontal scroll
                table.style.background = 'rgba(88, 166, 255, 0.02)';
                setTimeout(() => {
                    table.style.background = '';
                }, 200);
            }
        }
    });
}

function initializeMobileNotifications() {
    window.showMobileNotification = function(message, type = 'success') {
        const notification = document.getElementById('mobile-notification');
        notification.textContent = message;
        notification.className = `mobile-notification ${type}`;
        notification.classList.add('show');

        setTimeout(() => {
            notification.classList.remove('show');
        }, 3000);
    };

    // Override existing success/error messages to use mobile notifications
    const originalAlert = window.alert;
    window.alert = function(message) {
        if (window.innerWidth <= 768) {
            showMobileNotification(message, 'info');
        } else {
            originalAlert(message);
        }
    };
}

function optimizeButtonInteractions() {
    const buttons = document.querySelectorAll('.btn-accion-icono');

    buttons.forEach(button => {
        // Add press effect
        button.addEventListener('touchstart', function() {
            this.style.transform = 'scale(0.95)';
        });

        button.addEventListener('touchend', function() {
            setTimeout(() => {
                this.style.transform = '';
            }, 100);
        });

        // Prevent double-tap zoom
        button.addEventListener('touchend', function(e) {
            e.preventDefault();
        });
    });
}

function addSwipeGestures() {
    let startX, startY;

    document.addEventListener('touchstart', function(e) {
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
    });

    document.addEventListener('touchend', function(e) {
        if (!startX || !startY) return;

        let endX = e.changedTouches[0].clientX;
        let endY = e.changedTouches[0].clientY;

        let diffX = startX - endX;
        let diffY = startY - endY;

        // Only trigger on significant swipes
        if (Math.abs(diffX) > 100 && Math.abs(diffX) > Math.abs(diffY)) {
            if (diffX > 0) {
                // Swipe left - could navigate to next page
                console.log('Swipe left detected');
            } else {
                // Swipe right - could navigate to previous page
                console.log('Swipe right detected');
            }
        }

        startX = null;
        startY = null;
    });
}

// Add PWA-like features
if ('serviceWorker' in navigator && window.innerWidth <= 768) {
    // Add to home screen prompt
    let deferredPrompt;

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;

        // Show install banner after 5 seconds
        setTimeout(() => {
            if (deferredPrompt) {
                showMobileNotification('💫 Agregar VitalCarga a tu pantalla de inicio', 'info');

                // Add click handler to install
                setTimeout(() => {
                    if (deferredPrompt) {
                        deferredPrompt.prompt();
                        deferredPrompt.userChoice.then((choiceResult) => {
                            if (choiceResult.outcome === 'accepted') {
                                showMobileNotification('✅ ¡VitalCarga instalado!', 'success');
                            }
                            deferredPrompt = null;
                        });
                    }
                }, 5000);
            }
        }, 5000);
    });
}

// Handle mobile navigation
document.querySelectorAll('.mobile-nav-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        // Remove active class from all buttons
        document.querySelectorAll('.mobile-nav-btn').forEach(b => b.classList.remove('active'));
        // Add active class to clicked button
        this.classList.add('active');

        // Add haptic feedback
        if (navigator.vibrate) {
            navigator.vibrate(30);
        }
    });
});

// Optimize for mobile viewport
if (window.innerWidth <= 768) {
    // Add viewport meta tag if not present
    let viewport = document.querySelector('meta[name=viewport]');
    if (viewport) {
        viewport.setAttribute('content', 'width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover');
    }

    // Add apple-mobile-web-app meta tags for better iOS integration
    const head = document.head;

    const appleMeta = [
        { name: 'apple-mobile-web-app-capable', content: 'yes' },
        { name: 'apple-mobile-web-app-status-bar-style', content: 'black-translucent' },
        { name: 'apple-mobile-web-app-title', content: 'VitalCarga' },
        { name: 'theme-color', content: '#0d1117' }
    ];

    appleMeta.forEach(meta => {
        if (!document.querySelector(`meta[name="${meta.name}"]`)) {
            const metaTag = document.createElement('meta');
            metaTag.name = meta.name;
            metaTag.content = meta.content;
            head.appendChild(metaTag);
        }
    });
}
</script>

</body>
</html>
