<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'conexion.php';

// Filtros expandidos
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'semana';
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$metodo_pago = isset($_GET['metodo_pago']) ? $_GET['metodo_pago'] : '';
$ciudad = isset($_GET['ciudad']) ? $_GET['ciudad'] : '';
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limite = 20;
$offset = ($page - 1) * $limite;

// Filtro avanzado usando los nuevos campos de estado booleanos
switch($filtro) {
    case 'hoy':
        $where = "DATE(fecha) = CURDATE() AND archivado = '0' AND anulado = '0'";
        break;
    case 'semana':
        $where = "YEARWEEK(fecha,1) = YEARWEEK(CURDATE(),1) AND archivado = '0' AND anulado = '0'";
        break;
    case 'quincena':
        $where = "fecha >= CURDATE() - INTERVAL 15 DAY AND archivado = '0' AND anulado = '0'";
        break;
    case 'mes':
        $where = "MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) AND archivado = '0' AND anulado = '0'";
        break;
    case 'ultimos_30':
        $where = "fecha >= CURDATE() - INTERVAL 30 DAY AND archivado = '0' AND anulado = '0'";
        break;
    case 'ultimos_60':
        $where = "fecha >= CURDATE() - INTERVAL 60 DAY AND archivado = '0' AND anulado = '0'";
        break;
    case 'ultimos_90':
        $where = "fecha >= CURDATE() - INTERVAL 90 DAY AND archivado = '0' AND anulado = '0'";
        break;
    case 'archivados':
        $where = "archivado = '1'";
        break;
    case 'anulados':
        $where = "anulado = '1'";
        break;
    case 'enviados':
        $where = "enviado = '1' AND archivado = '0' AND anulado = '0'";
        break;
    case 'pago_pendiente':
        $where = "pagado = '0' AND archivado = '0' AND anulado = '0'";
        break;
    case 'pago_confirmado':
        $where = "pagado = '1' AND archivado = '0' AND anulado = '0'";
        break;
    case 'con_comprobante':
        $where = "tiene_comprobante = '1' AND archivado = '0' AND anulado = '0'";
        break;
    case 'sin_comprobante':
        $where = "tiene_comprobante = '0' AND pagado = '0' AND archivado = '0' AND anulado = '0'";
        break;
    case 'con_guia':
        $where = "tiene_guia = '1' AND archivado = '0' AND anulado = '0'";
        break;
    case 'personalizado':
        $where = "archivado = '0' AND anulado = '0'"; // Para personalizado, excluir archivados y anulados
        break;
    case 'todos':
        $where = "archivado = '0' AND anulado = '0'"; // Por defecto, excluir archivados y anulados
        break;
    default:
        $where = "archivado = '0' AND anulado = '0'"; // Por defecto, no mostrar archivados ni anulados
}

// ===== BUSCADOR INTELIGENTE MEJORADO =====
if($buscar && trim($buscar) !== ''){
    $buscarOriginal = trim($buscar);
    $buscarSql = $conn->real_escape_string($buscarOriginal);

    // Si es un número puro, priorizar búsqueda por ID
    if(is_numeric($buscarSql) && strlen($buscarSql) <= 8) {
        $where .= " AND (
            p.id = '$buscarSql' OR
            p.telefono LIKE '%$buscarSql%' OR
            p.nombre LIKE '%$buscarSql%' OR
            p.correo LIKE '%$buscarSql%'
        )";
    } else {
        // Dividir términos de búsqueda
        $buscarTerminos = array_filter(explode(' ', $buscarSql), function($termino) {
            return strlen(trim($termino)) >= 2;
        });

        if(!empty($buscarTerminos)) {
            $condicionesBusqueda = [];

            foreach($buscarTerminos as $termino) {
                $termino = trim($termino);
                $termino = $conn->real_escape_string($termino);

                // Condiciones de búsqueda amplias para encontrar cualquier coincidencia
                $condicionesTermino = [
                    // Datos principales del cliente
                    "p.nombre LIKE '%$termino%'",
                    "p.correo LIKE '%$termino%'",
                    "p.telefono LIKE '%$termino%'",

                    // Ubicación
                    "p.ciudad LIKE '%$termino%'",
                    "p.barrio LIKE '%$termino%'",
                    "p.direccion LIKE '%$termino%'",

                    // Información de pago
                    "p.metodo_pago LIKE '%$termino%'",
                    "p.datos_pago LIKE '%$termino%'",

                    // Estados y notas
                    "p.estado LIKE '%$termino%'",
                    "p.nota_interna LIKE '%$termino%'"
                ];

                // Búsqueda por ID si es numérico
                if(is_numeric($termino)) {
                    $condicionesTermino[] = "p.id = '$termino'";
                }

                // Búsqueda por fecha si tiene formato de fecha
                if(preg_match('/\d{4}-\d{2}-\d{2}/', $termino)) {
                    $condicionesTermino[] = "DATE(p.fecha) = '$termino'";
                    $condicionesTermino[] = "DATE_FORMAT(p.fecha, '%Y-%m-%d') LIKE '%$termino%'";
                }
                if(preg_match('/\d{2}\/\d{2}\/\d{4}/', $termino)) {
                    $condicionesTermino[] = "DATE_FORMAT(p.fecha, '%d/%m/%Y') LIKE '%$termino%'";
                }

                // Búsqueda por año si es un año válido
                if(preg_match('/^20\d{2}$/', $termino)) {
                    $condicionesTermino[] = "YEAR(p.fecha) = '$termino'";
                }

                // Búsqueda por mes si coincide con nombres de meses
                $meses = [
                    'enero' => '01', 'febrero' => '02', 'marzo' => '03', 'abril' => '04',
                    'mayo' => '05', 'junio' => '06', 'julio' => '07', 'agosto' => '08',
                    'septiembre' => '09', 'octubre' => '10', 'noviembre' => '11', 'diciembre' => '12'
                ];
                $terminoLower = strtolower($termino);
                if(isset($meses[$terminoLower])) {
                    $numeroMes = $meses[$terminoLower];
                    $condicionesTermino[] = "MONTH(p.fecha) = '$numeroMes'";
                }

                // Crear condición OR para este término
                $condicionesBusqueda[] = "(" . implode(" OR ", $condicionesTermino) . ")";
            }

            // Todos los términos deben encontrarse (AND entre términos)
            if(!empty($condicionesBusqueda)) {
                $where .= " AND (" . implode(" AND ", $condicionesBusqueda) . ")";
            }
        }
    }
}
if($metodo_pago){
    $metodoPagoSql = $conn->real_escape_string($metodo_pago);
    $where .= " AND metodo_pago = '$metodoPagoSql'";
}
if($ciudad){
    $ciudadSql = $conn->real_escape_string($ciudad);
    $where .= " AND ciudad LIKE '%$ciudadSql%'";
}
if($fecha_desde){
    $where .= " AND DATE(fecha) >= '" . $conn->real_escape_string($fecha_desde) . "'";
}
if($fecha_hasta){
    $where .= " AND DATE(fecha) <= '" . $conn->real_escape_string($fecha_hasta) . "'";
}

// ===== BÚSQUEDA POR MONTO (SI APLICA) =====
$montoFiltro = '';
if($buscar && is_numeric($buscar) && strlen($buscar) >= 4) {
    $montoNumerico = intval($buscar);
    // Crear un filtro HAVING para búsqueda por monto
    $margenMonto = max(1000, $montoNumerico * 0.1); // 10% de margen o mínimo 1000
    $montoFiltro = " HAVING monto BETWEEN " . ($montoNumerico - $margenMonto) . " AND " . ($montoNumerico + $margenMonto);
}
$result = $conn->query("
    SELECT SQL_CALC_FOUND_ROWS
        p.id, p.nombre, p.telefono, p.ciudad, p.barrio, p.correo, p.estado, p.fecha, p.direccion,
        p.metodo_pago, p.datos_pago, p.comprobante, p.guia, p.nota_interna, p.enviado, p.archivado,
        p.anulado, p.tiene_guia, p.tiene_comprobante, p.pagado,
        COALESCE(SUM(pd.cantidad * pd.precio), 0) as monto
    FROM pedidos_detal p
    LEFT JOIN pedido_detalle pd ON p.id = pd.pedido_id
    WHERE $where
    GROUP BY p.id
    $montoFiltro
    ORDER BY p.fecha DESC
    LIMIT $limite OFFSET $offset
");

if (!$result) {
    die("Error en la consulta: " . $conn->error);
}

// DEBUG: Mostrar información de la consulta si hay búsqueda activa (comentar en producción)
/*
if($buscar) {
    echo "<!-- DEBUG BÚSQUEDA INTELIGENTE -->";
    echo "<!-- Término: " . htmlspecialchars($buscar) . " -->";
    echo "<!-- WHERE: " . htmlspecialchars($where) . " -->";
    if($montoFiltro) {
        echo "<!-- HAVING: " . htmlspecialchars($montoFiltro) . " -->";
    }
    echo "<!-- Total encontrados: " . $total_pedidos . " -->";
}
*/

$pedidos = [];
while ($row = $result->fetch_assoc()) {
    $pedidos[] = $row;
}

$total_result = $conn->query("SELECT FOUND_ROWS() as total");
$total_pedidos = $total_result->fetch_assoc()['total'];
$total_paginas = ceil($total_pedidos / $limite);

// Calcular monto total real sumando desde pedido_detalle
$monto_total_result = $conn->query("
    SELECT COALESCE(SUM(monto_temp), 0) as monto_total
    FROM (
        SELECT COALESCE(SUM(pd.cantidad * pd.precio), 0) as monto_temp
        FROM pedidos_detal p
        LEFT JOIN pedido_detalle pd ON p.id = pd.pedido_id
        WHERE $where
        GROUP BY p.id
        $montoFiltro
    ) as subquery
");
$monto_total_row = $monto_total_result->fetch_assoc();
$monto_total_real = $monto_total_row['monto_total'];

// PRODUCTOS ELIMINADOS DE CARGA INICIAL - Ahora se cargan bajo demanda via AJAX
// Los productos se obtienen individualmente a través de get_productos_pedido.php

// Obtener listas para filtros
$metodos_pago = [];
$ciudades = [];

$metodos_result = $conn->query("SELECT DISTINCT metodo_pago FROM pedidos_detal WHERE metodo_pago IS NOT NULL AND metodo_pago != '' ORDER BY metodo_pago");
while ($row = $metodos_result->fetch_assoc()) {
    $metodos_pago[] = $row['metodo_pago'];
}

$ciudades_result = $conn->query("SELECT DISTINCT ciudad FROM pedidos_detal WHERE ciudad IS NOT NULL AND ciudad != '' ORDER BY ciudad");
while ($row = $ciudades_result->fetch_assoc()) {
    $ciudades[] = $row['ciudad'];
}

function estado_pill($pedido) {
    $estados = [];

    // Verificar estado de pago primero
    if ($pedido['pagado'] == '1') {
        $estados[] = '<span class="estado-pill pago-confirmado">💰 Pago Confirmado</span>';
    } else {
        $estados[] = '<span class="estado-pill pago-pendiente">⏳ Pago Pendiente</span>';
    }

    // Verificar otros estados
    if ($pedido['anulado'] == '1') {
        $estados = ['<span class="estado-pill anulado">❌ Anulado</span>']; // Reemplazar todo si está anulado
    } else {
        if ($pedido['archivado'] == '1') {
            $estados[] = '<span class="estado-pill archivado">📁 Archivado</span>';
        }
        if ($pedido['enviado'] == '1') {
            $estados[] = '<span class="estado-pill enviado">🚚 Enviado</span>';
        }
        if ($pedido['tiene_guia'] == '1') {
            $estados[] = '<span class="estado-pill guia">📋 Con Guía</span>';
        }
        if ($pedido['tiene_comprobante'] == '1') {
            $estados[] = '<span class="estado-pill comprobante">📄 Con Comprobante</span>';
        }
    }

    return implode(' ', $estados);
}

function formatear_productos($productos) {
    if (empty($productos)) return 'Sin productos detallados';

    $html = '<div class="productos-mini">';
    $total = 0;
    foreach ($productos as $producto) {
        $subtotal = $producto['precio'] * $producto['cantidad'];
        $total += $subtotal;
        $talla = !empty($producto['talla']) ? " ({$producto['talla']})" : "";
        $html .= '<div class="producto-item">';
        $html .= '<span class="producto-nombre">' . htmlspecialchars($producto['nombre']) . $talla . '</span>';
        $html .= '<span class="producto-cantidad">x' . $producto['cantidad'] . '</span>';
        $html .= '<span class="producto-precio">$' . number_format($producto['precio'], 0, ',', '.') . '</span>';
        $html .= '</div>';
    }
    $html .= '<div class="productos-total">Total: $' . number_format($total, 0, ',', '.') . '</div>';
    $html .= '</div>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Pedidos</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📦</text></svg>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="listar_pedidos.css">
</head>
<body>
<div class="sticky-bar">

    <!-- � HEADER ULTRA-COMPACTO ESTILO APPLE/VS CODE -->
    <div class="header-ultra-compacto">
        <!-- Fila única con todos los elementos -->
        <div class="fila-compacta">
            <!-- Filtros rápidos -->
            <div class="filtros-rapidos">
                <select name="filtro" class="select-compacto" onchange="aplicarFiltroRapido(this.value)">
                    <option value="todos" <?php echo ($filtro=='todos' ? 'selected' : ''); ?>>📦 Todos</option>
                    <option value="hoy" <?php echo ($filtro=='hoy' ? 'selected' : ''); ?>>📅 Hoy</option>
                    <option value="semana" <?php echo ($filtro=='semana' ? 'selected' : ''); ?>>📊 Semana</option>
                    <option value="mes" <?php echo ($filtro=='mes' ? 'selected' : ''); ?>>📈 Mes</option>
                    <option value="ultimos_30" <?php echo ($filtro=='ultimos_30' ? 'selected' : ''); ?>>📆 Últimos 30 días</option>
                    <option value="ultimos_60" <?php echo ($filtro=='ultimos_60' ? 'selected' : ''); ?>>📅 Últimos 60 días</option>
                    <option value="ultimos_90" <?php echo ($filtro=='ultimos_90' ? 'selected' : ''); ?>>📊 Últimos 90 días</option>
                    <option value="pago_pendiente" <?php echo ($filtro=='pago_pendiente' ? 'selected' : ''); ?>>⏳ Pendientes</option>
                    <option value="pago_confirmado" <?php echo ($filtro=='pago_confirmado' ? 'selected' : ''); ?>>✅ Pagados</option>
                    <option value="enviados" <?php echo ($filtro=='enviados' ? 'selected' : ''); ?>>🚚 Enviados</option>
                    <option value="anulados" <?php echo ($filtro=='anulados' ? 'selected' : ''); ?>>❌ Anulados</option>
                </select>

                <input type="text"
                       id="busquedaRapida"
                       name="buscar"
                       value="<?php echo htmlspecialchars($buscar); ?>"
                       placeholder="🔍 Buscar por ID, nombre, email, teléfono, ciudad, monto, fecha, año, mes..."
                       class="input-compacto"
                       onkeyup="busquedaEnTiempoReal(this.value)"
                       onfocus="mostrarEjemplosBusqueda()"
                       autocomplete="off">

                <?php if($buscar): ?>
                    <button type="button" class="btn-limpiar-busqueda" onclick="limpiarBusqueda()" title="Limpiar búsqueda">✕</button>
                <?php endif; ?>

                <button class="btn-filtros-avanzados" onclick="toggleFiltrosAvanzados()" title="Más filtros">⚙️</button>
            </div>

            <!-- Estadísticas en línea -->
            <div class="stats-inline">
                <span class="stat-inline">📦 <?php echo number_format($total_pedidos); ?></span>
                <span class="stat-inline">💰 $<?php echo number_format($monto_total_real, 0, ',', '.'); ?></span>
                <span class="stat-inline">⏳ <?php echo count(array_filter($pedidos, function($p) { return $p['pagado'] == '0'; })); ?></span>
                <span class="stat-inline">✅ <?php echo count(array_filter($pedidos, function($p) { return $p['pagado'] == '1'; })); ?></span>
                <?php if($buscar): ?>
                    <span class="stat-inline" style="background: var(--apple-green); color: white;">
                        🔍 Filtrando: "<?php echo htmlspecialchars($buscar); ?>"
                        <?php if(is_numeric($buscar) && strlen($buscar) >= 4): ?>
                            (Búsqueda por monto)
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>

            <!-- Acciones rápidas -->
            <div class="acciones-compactas">
                <button onclick="location.reload()" class="btn-compacto" title="Actualizar">🔄</button>
                <button onclick="exportarExcel()" class="btn-compacto" title="Exportar">📊</button>
                <button onclick="window.print()" class="btn-compacto" title="Imprimir">🖨️</button>
            </div>
        </div>

        <!-- Panel de filtros avanzados (oculto por defecto) -->
        <div class="filtros-avanzados-panel" id="filtrosAvanzados" style="display: none;">
            <form method="get" class="filtros-avanzados-form">
                <div class="filtros-row">
                    <select name="metodo_pago" class="select-avanzado" onchange="aplicarFiltros()">
                        <option value="">💳 Método de pago</option>
                        <?php foreach($metodos_pago as $metodo): ?>
                            <option value="<?php echo htmlspecialchars($metodo); ?>" <?php echo ($metodo_pago==$metodo ? 'selected' : ''); ?>>
                                <?php echo htmlspecialchars($metodo); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="ciudad" class="select-avanzado" onchange="aplicarFiltros()">
                        <option value="">🏙️ Ciudad</option>
                        <?php foreach($ciudades as $ciudad_opt): ?>
                            <option value="<?php echo htmlspecialchars($ciudad_opt); ?>" <?php echo ($ciudad==$ciudad_opt ? 'selected' : ''); ?>>
                                <?php echo htmlspecialchars($ciudad_opt); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <input type="date" name="fecha_desde" value="<?php echo htmlspecialchars($fecha_desde); ?>" class="input-avanzado" placeholder="Desde" onchange="aplicarFiltros()">
                    <input type="date" name="fecha_hasta" value="<?php echo htmlspecialchars($fecha_hasta); ?>" class="input-avanzado" placeholder="Hasta" onchange="aplicarFiltros()">

                    <button type="button" onclick="limpiarTodosFiltros()" class="btn-limpiar">🗑️ Limpiar</button>
                </div>
            </form>
        </div>

        <!-- Indicadores de filtros activos (si los hay) -->
        <?php if ($buscar || $metodo_pago || $ciudad || $fecha_desde || $fecha_hasta): ?>
        <div class="filtros-activos-compactos">
            <?php if ($buscar): ?>
                <span class="filtro-activo-mini">🔍 "<?php echo htmlspecialchars(substr($buscar, 0, 20)); ?><?php echo strlen($buscar) > 20 ? '...' : ''; ?>" <button onclick="removerFiltro('buscar')">×</button></span>
            <?php endif; ?>
            <?php if ($metodo_pago): ?>
                <span class="filtro-activo-mini">💳 <?php echo htmlspecialchars($metodo_pago); ?> <button onclick="removerFiltro('metodo_pago')">×</button></span>
            <?php endif; ?>
            <?php if ($ciudad): ?>
                <span class="filtro-activo-mini">🏙️ <?php echo htmlspecialchars($ciudad); ?> <button onclick="removerFiltro('ciudad')">×</button></span>
            <?php endif; ?>
            <?php if ($fecha_desde || $fecha_hasta): ?>
                <span class="filtro-activo-mini">📅
                    <?php echo $fecha_desde ? date('d/m', strtotime($fecha_desde)) : '...'; ?> -
                    <?php echo $fecha_hasta ? date('d/m', strtotime($fecha_hasta)) : '...'; ?>
                    <button onclick="removerFiltro('fechas')">×</button>
                </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ================================== -->
        <!-- NUEVA TABLA RECONSTRUIDA          -->
        <!-- ================================== -->
        <div class="nueva-tabla-container">
            <table class="nueva-tabla-pedidos">
                <thead>
                    <tr>
                        <th class="col-id">📦 ID</th>
                        <th class="col-fecha">📅 Fecha</th>
                        <th class="col-cliente">👤 Cliente</th>
                        <th class="col-monto">💰 Monto</th>
                        <th class="col-ver">�️ Ver</th>
                        <th class="col-pagado">💳 Pagado</th>
                        <th class="col-enviado">🚚 Enviado</th>
                        <th class="col-comprobante">📄 Comprobante</th>
                        <th class="col-guia">📦 Guía</th>
                        <th class="col-archivado">📁 Archivado</th>
                        <th class="col-anulado">❌ Anulado</th>
                        <th class="col-acciones">⚡ Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($pedidos) == 0): ?>
                        <tr>
                            <td colspan="12" class="tabla-vacia">
                                <div class="mensaje-vacio">
                                    <div class="icono-vacio">📭</div>
                                    <div class="titulo-vacio">No hay pedidos para este filtro</div>
                                    <div class="subtitulo-vacio">Intenta cambiar los filtros o agregar un nuevo pedido</div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($pedidos as $p): ?>
                            <tr class="fila-pedido" data-id="<?php echo $p['id']; ?>">
                                <!-- ID del Pedido -->
                                <td class="col-id">
                                    <a href="#" class="enlace-pedido ver-detalle" data-id="<?php echo $p['id']; ?>">
                                        #<?php echo $p['id']; ?>
                                    </a>
                                </td>

                                <!-- Fecha del Pedido -->
                                <td class="col-fecha">
                                    <div class="info-fecha">
                                        <div class="fecha-principal"><?php echo date('d/m/Y', strtotime($p['fecha'])); ?></div>
                                        <div class="hora-pedido"><?php echo date('H:i', strtotime($p['fecha'])); ?></div>
                                    </div>
                                </td>

                                <!-- Cliente -->
                                <td class="col-cliente">
                                    <div class="info-cliente">
                                        <div class="nombre-cliente"><?php echo htmlspecialchars($p['nombre']); ?></div>
                                        <div class="telefono-ciudad">
                                            <a href="#" onclick="abrirWhatsApp('<?php echo preg_replace('/[^0-9]/', '', $p['telefono']); ?>'); return false;" class="whatsapp-link" title="Contactar por WhatsApp">
                                                📱
                                            </a>
                                            <?php echo htmlspecialchars($p['telefono']) . ' - ' . htmlspecialchars($p['ciudad']); ?>
                                        </div>
                                    </div>
                                </td>

                                <!-- Monto -->
                                <td class="col-monto">
                                    <span class="valor-monto">$<?php echo number_format($p['monto'], 0, ',', '.'); ?></span>
                                </td>

                                <!-- Ver Productos -->
                                <td class="col-ver">
                                    <button class="btn-ver-productos" onclick="toggleProductos(<?php echo $p['id']; ?>)" title="Ver productos del pedido">
                                        👁️
                                    </button>
                                </td>

                                <!-- Status: Pagado -->
                                <td class="col-pagado">
                                    <span class="badge-status <?php echo $p['pagado'] == '1' ? 'status-si' : 'status-no'; ?>">
                                        <?php echo $p['pagado'] == '1' ? '✅ Sí' : '⏳ No'; ?>
                                    </span>
                                </td>

                                <!-- Status: Enviado -->
                                <td class="col-enviado">
                                    <span class="badge-status <?php echo $p['enviado'] == '1' ? 'status-si' : 'status-no'; ?>">
                                        <?php echo $p['enviado'] == '1' ? '✅ Sí' : '⏳ No'; ?>
                                    </span>
                                </td>

                                <!-- Status: Comprobante -->
                                <td class="col-comprobante" onclick="abrirModalComprobante(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['comprobante']); ?>', '<?php echo $p['tiene_comprobante']; ?>', '<?php echo htmlspecialchars($p['metodo_pago']); ?>')" style="cursor: pointer;" title="Click para ver/subir comprobante">
                                    <span class="badge-status <?php echo $p['tiene_comprobante'] == '1' ? 'status-si' : 'status-no'; ?>">
                                        <?php echo $p['tiene_comprobante'] == '1' ? '✅ Sí' : '⏳ No'; ?>
                                    </span>
                                </td>

                                <!-- Status: Guía -->
                                <td class="col-guia">
                                    <span class="badge-status <?php echo $p['tiene_guia'] == '1' ? 'status-si' : 'status-no'; ?>">
                                        <?php echo $p['tiene_guia'] == '1' ? '✅ Sí' : '⏳ No'; ?>
                                    </span>
                                </td>

                                <!-- Status: Archivado -->
                                <td class="col-archivado">
                                    <span class="badge-status <?php echo $p['archivado'] == '1' ? 'status-archivado' : 'status-activo'; ?>">
                                        <?php echo $p['archivado'] == '1' ? '📁 Sí' : '📂 No'; ?>
                                    </span>
                                </td>

                                <!-- Status: Anulado -->
                                <td class="col-anulado">
                                    <span class="badge-status <?php echo $p['anulado'] == '1' ? 'status-anulado' : 'status-activo'; ?>">
                                        <?php echo $p['anulado'] == '1' ? '❌ Sí' : '✅ No'; ?>
                                    </span>
                                </td>

                                <!-- Acciones -->
                                <td class="col-acciones">
                                    <div class="dropdown-acciones" style="position: relative;">
                                        <button class="btn-acciones" onclick="toggleAcciones(<?php echo $p['id']; ?>)">⚡</button>
                                        <div class="menu-acciones" id="menu-<?php echo $p['id']; ?>">
                                            <a href="#" class="accion-item ver-detalle" data-id="<?php echo $p['id']; ?>">👁️ Ver Detalle</a>
                                            <a href="comprobante.php?id=<?php echo $p['id']; ?>" class="accion-item" target="_blank">📄 Comprobante</a>

                                            <?php if ($p['anulado'] == '0'): ?>
                                                <?php if ($p['pagado'] == '0'): ?>
                                                    <a href="#" class="accion-item" onclick="confirmarPago(<?php echo $p['id']; ?>)">💰 Confirmar Pago</a>
                                                <?php endif; ?>
                                                <?php if ($p['enviado'] == '0'): ?>
                                                    <a href="#" class="accion-item" onclick="marcarEnviado(<?php echo $p['id']; ?>)">🚚 Marcar Enviado</a>
                                                <?php endif; ?>
                                                <?php if ($p['archivado'] == '0'): ?>
                                                    <a href="#" class="accion-item" onclick="archivarPedido(<?php echo $p['id']; ?>)">📁 Archivar</a>
                                                <?php else: ?>
                                                    <a href="#" class="accion-item" onclick="restaurarPedido(<?php echo $p['id']; ?>)">🔄 Restaurar</a>
                                                <?php endif; ?>
                                                <a href="#" class="accion-item anular" onclick="anularPedido(<?php echo $p['id']; ?>)">❌ Anular</a>
                                            <?php else: ?>
                                                <a href="#" class="accion-item" onclick="restaurarPedido(<?php echo $p['id']; ?>)">🔄 Restaurar</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <!-- Nota: Las filas de productos se crean dinámicamente con JavaScript al hacer clic en el botón ojo -->
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación Nueva -->
        <?php if($total_paginas > 1): ?>
            <div class="paginacion-nueva">
                <div class="info-paginacion">
                    Mostrando <?php echo count($pedidos); ?> de <?php echo $total_pedidos; ?> pedidos
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

<!-- MODAL DETALLE -->
<div id="modal-detalle" class="modal-detalle-bg" style="display:none;">
  <div class="modal-detalle">
    <button class="cerrar-modal" onclick="cerrarModalDetalle()">×</button>
    <div id="modal-contenido"></div>
  </div>
</div>

<!-- MODAL SUBIR GUÍA -->
<div id="modal-guia-bg" class="modal-detalle-bg" style="display:none;">
  <div class="modal-detalle" style="max-width:370px;text-align:center;">
    <button class="cerrar-modal" onclick="cerrarModalGuia()">×</button>
    <form id="formGuia" enctype="multipart/form-data" method="POST" autocomplete="off">
      <input type="hidden" name="id_pedido" id="guia_id_pedido">
      <div style="font-size:1.07rem;font-weight:600;margin-bottom:10px;">
        Adjuntar foto de la guía de envío
      </div>
      <input type="file" name="guia" id="guia_file" accept="image/*,application/pdf" required style="margin-bottom:13px;">
      <button type="submit" class="btn-neon" style="width:100%;">Enviar guía y notificar cliente</button>
    </form>
    <div id="guia_status" style="margin-top:12px;font-size:1rem;color:#e02b2b;"></div>
  </div>
</div>

<script>
// ===== SISTEMA AVANZADO DE GESTIÓN DE PEDIDOS =====

// Variables globales
let pedidosSeleccionados = [];

// ===== FUNCIONES DE FILTROS =====
function aplicarFiltros() {
    document.getElementById('formFiltros').submit();
}

// ===== FUNCIÓN PARA MANEJAR CAMBIO DE PERÍODO =====
function manejarCambioPeriodo() {
    const filtroPeriodo = document.getElementById('filtroPeriodo');

    if (filtroPeriodo.value === 'personalizado') {
        // Solo mostrar los campos, NO enviar el formulario todavía
        toggleFechasPersonalizadas();
    } else {
        // Para otros filtros, ocultar fechas y enviar formulario
        toggleFechasPersonalizadas();
        aplicarFiltros();
    }
}

// ===== FUNCIÓN PARA APLICAR FILTROS PERSONALIZADOS =====
function aplicarFiltrosPersonalizados() {
    // Verificar que el período esté en "personalizado" antes de enviar
    const filtroPeriodo = document.getElementById('filtroPeriodo');
    if (filtroPeriodo.value === 'personalizado') {
        aplicarFiltros();
    }
}

// ===== FUNCIÓN PARA FILTROS RÁPIDOS =====
function aplicarFiltroRapido(filtroSeleccionado) {
    console.log('🔍 Aplicando filtro rápido:', filtroSeleccionado);

    // Construir URL con el filtro seleccionado manteniendo otros parámetros
    const buscarActual = document.getElementById('busquedaRapida').value;
    const params = new URLSearchParams();

    // Agregar filtro seleccionado
    params.append('filtro', filtroSeleccionado);

    // Mantener búsqueda actual si existe
    if (buscarActual.trim()) {
        params.append('buscar', buscarActual.trim());
    }

    // Redirigir con los nuevos parámetros
    window.location.href = window.location.pathname + '?' + params.toString();
}

// ===== FUNCIÓN PARA BÚSQUEDA EN TIEMPO REAL MEJORADA =====
let busquedaTimeout;
let ultimaBusqueda = '';

function busquedaEnTiempoReal(termino) {
    const inputBusqueda = document.getElementById('busquedaRapida');

    // Evitar búsquedas duplicadas
    if (termino === ultimaBusqueda) return;

    // Limpiar timeout anterior
    clearTimeout(busquedaTimeout);

    // Indicador visual mientras escribe
    if (termino.trim().length > 0) {
        inputBusqueda.style.borderColor = 'var(--apple-blue)';
        inputBusqueda.style.boxShadow = '0 0 0 2px rgba(0, 122, 255, 0.2)';
        inputBusqueda.style.backgroundColor = '#f8f9ff';
    } else {
        inputBusqueda.style.borderColor = '';
        inputBusqueda.style.boxShadow = '';
        inputBusqueda.style.backgroundColor = '';
    }

    // Para términos muy cortos, limpiar la búsqueda
    if (termino.trim().length === 0) {
        const params = new URLSearchParams(window.location.search);
        params.delete('buscar');
        const filtroActual = params.get('filtro') || 'semana';
        params.set('filtro', filtroActual);
        window.location.href = window.location.pathname + '?' + params.toString();
        return;
    }

    // No buscar si es muy corto
    if (termino.trim().length < 2) {
        return;
    }

    // Delay dinámico: búsquedas más rápidas para términos largos
    const delayTime = termino.trim().length >= 4 ? 400 : 800;

    busquedaTimeout = setTimeout(() => {
        ultimaBusqueda = termino;
        console.log('🔍 Ejecutando búsqueda inteligente:', termino);

        // Indicador de búsqueda activa
        inputBusqueda.style.borderColor = 'var(--apple-green)';
        inputBusqueda.style.boxShadow = '0 0 0 2px rgba(52, 199, 89, 0.2)';
        inputBusqueda.style.backgroundColor = '#f8fff8';

        // Construir parámetros de búsqueda
        const params = new URLSearchParams(window.location.search);
        const filtroActual = params.get('filtro') || 'semana';

        const newParams = new URLSearchParams();
        newParams.append('filtro', filtroActual);
        newParams.append('buscar', termino.trim());

        // Mantener otros filtros activos
        ['metodo_pago', 'ciudad', 'fecha_desde', 'fecha_hasta'].forEach(param => {
            const valor = params.get(param);
            if (valor && valor.trim() !== '') {
                newParams.append(param, valor);
            }
        });

        // Mostrar feedback antes de redirigir
        setTimeout(() => {
            window.location.href = window.location.pathname + '?' + newParams.toString();
        }, 100);

    }, delayTime);
}

// ===== FUNCIÓN PARA LIMPIAR BÚSQUEDA =====
function limpiarBusqueda() {
    const params = new URLSearchParams(window.location.search);
    params.delete('buscar');
    const filtroActual = params.get('filtro') || 'semana';
    params.set('filtro', filtroActual);
    window.location.href = window.location.pathname + '?' + params.toString();
}

// ===== FUNCIÓN PARA MOSTRAR EJEMPLOS DE BÚSQUEDA =====
let ejemplosTimeout;
function mostrarEjemplosBusqueda() {
    const input = document.getElementById('busquedaRapida');
    if (input.value.trim() !== '') return; // Solo si está vacío

    clearTimeout(ejemplosTimeout);

    const ejemplos = [
        'Juan Pérez',
        'juan@email.com',
        '3001234567',
        'Bogotá',
        'transferencia',
        '50000',
        '2024-12-01',
        'diciembre',
        '2024'
    ];

    let indiceEjemplo = 0;

    ejemplosTimeout = setTimeout(() => {
        const intervalo = setInterval(() => {
            if (document.activeElement !== input) {
                clearInterval(intervalo);
                input.placeholder = "🔍 Buscar por ID, nombre, email, teléfono, ciudad, monto, fecha, año, mes...";
                return;
            }

            input.placeholder = `🔍 Ejemplo: ${ejemplos[indiceEjemplo]}`;
            indiceEjemplo = (indiceEjemplo + 1) % ejemplos.length;

            if (input.value.trim() !== '') {
                clearInterval(intervalo);
                input.placeholder = "🔍 Buscar por ID, nombre, email, teléfono, ciudad, monto, fecha, año, mes...";
            }
        }, 1500);
    }, 500);
}

// ===== FUNCIÓN PARA MOSTRAR/OCULTAR FECHAS PERSONALIZADAS =====
function toggleFechasPersonalizadas() {
    const filtroPeriodo = document.getElementById('filtroPeriodo');
    const fechasContainer = document.getElementById('fechasPersonalizadas');

    if (!filtroPeriodo || !fechasContainer) return;

    if (filtroPeriodo.value === 'personalizado') {
        // Mostrar campos de fecha
        fechasContainer.style.display = 'flex';
        fechasContainer.classList.add('show');

        // Focus en el primer campo de fecha después de la animación
        setTimeout(() => {
            const primerInput = fechasContainer.querySelector('input[type="date"]');
            if (primerInput && !primerInput.value) {
                primerInput.focus();
            }
        }, 200);
    } else {
        // Ocultar campos de fecha
        fechasContainer.style.display = 'none';
        fechasContainer.classList.remove('show');

        // Limpiar campos de fecha solo si no es la inicialización
        if (!window.isInitializing) {
            const inputDesde = document.querySelector('input[name="fecha_desde"]');
            const inputHasta = document.querySelector('input[name="fecha_hasta"]');
            if (inputDesde) inputDesde.value = '';
            if (inputHasta) inputHasta.value = '';
        }
    }
}

// ===== FUNCIONES DE SELECCIÓN MASIVA =====
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.pedido-checkbox');

    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
        updateRowSelection(checkbox);
    });

    updateSelectionCount();
}

function updateRowSelection(checkbox) {
    const row = checkbox.closest('tr');
    if (checkbox.checked) {
        row.classList.add('selected');
    } else {
        row.classList.remove('selected');
    }
}

function updateSelectionCount() {
    const checkboxes = document.querySelectorAll('.pedido-checkbox:checked');
    pedidosSeleccionados = Array.from(checkboxes).map(cb => cb.value);

    // Actualizar UI de conteo
    const countElement = document.getElementById('selection-count');
    if (countElement) {
        countElement.textContent = pedidosSeleccionados.length;
    }

    // Habilitar/deshabilitar botones de acciones masivas
    const botonesAccionMasiva = document.querySelectorAll('.btn-herramienta');
    botonesAccionMasiva.forEach(btn => {
        btn.disabled = pedidosSeleccionados.length === 0;
    });
}

function seleccionarTodos() {
    document.getElementById('selectAll').checked = true;
    toggleSelectAll();
}

function desseleccionarTodos() {
    document.getElementById('selectAll').checked = false;
    toggleSelectAll();
}

// ===== FUNCIÓN PARA MANEJAR DROPDOWN DE ACCIONES MASIVAS =====
function toggleDropdownMasivo() {
    const dropdown = document.getElementById('dropdownAccionesMasivas');
    const toggle = document.getElementById('btnAccionesMasivas');

    if (dropdown.style.display === 'none' || dropdown.style.display === '') {
        dropdown.style.display = 'block';
        toggle.classList.add('active');
    } else {
        dropdown.style.display = 'none';
        toggle.classList.remove('active');
    }
}

// Cerrar dropdown al hacer clic fuera
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('dropdownAccionesMasivas');
    const toggle = document.getElementById('btnAccionesMasivas');

    if (dropdown && !dropdown.contains(event.target) && !toggle.contains(event.target)) {
        dropdown.style.display = 'none';
        toggle.classList.remove('active');
    }
});



// ===== FUNCIONES DE ACCIONES MASIVAS =====
function accionMasiva(accion) {
    if (pedidosSeleccionados.length === 0) {
        alert('Selecciona al menos un pedido para realizar esta acción');
        return;
    }

    const mensajes = {
        'confirmar_pago': 'confirmar el pago de',
        'marcar_enviado': 'marcar como enviado',
        'archivar': 'archivar'
    };

    const mensaje = mensajes[accion] || accion;

    if (!confirm('¿Estás seguro de ' + mensaje + ' ' + pedidosSeleccionados.length + ' pedido(s)?')) {
        return;
    }

    // Mostrar indicador de carga
    mostrarCargaMasiva(true);

    const promesas = pedidosSeleccionados.map(pedidoId => {
        const formData = new FormData();
        formData.append('id', pedidoId);

        switch(accion) {
            case 'confirmar_pago':
                formData.append('pagado', '1');
                return fetch('actualizar_estado.php', { method: 'POST', body: formData });
            case 'marcar_enviado':
                formData.append('enviado', '1');
                return fetch('actualizar_estado.php', { method: 'POST', body: formData });
            case 'archivar':
                formData.append('archivado', '1');
                return fetch('actualizar_estado.php', { method: 'POST', body: formData });
        }
    });

    Promise.all(promesas)
        .then(responses => Promise.all(responses.map(r => r.json())))
        .then(resultados => {
            mostrarCargaMasiva(false);
            const exitosos = resultados.filter(r => r.success).length;
            const fallidos = resultados.length - exitosos;

            if (fallidos === 0) {
                alert('✅ Acción completada en ' + exitosos + ' pedido(s)');
                location.reload();
            } else {
                alert('⚠️ ' + exitosos + ' exitosos, ' + fallidos + ' fallidos. Revisa los pedidos.');
                location.reload();
            }
        })
        .catch(error => {
            mostrarCargaMasiva(false);
            console.error('Error en acción masiva:', error);
            alert('❌ Error al procesar la acción masiva');
        });
}

function mostrarCargaMasiva(mostrar) {
    const botones = document.querySelectorAll('.btn-herramienta');
    botones.forEach(btn => {
        btn.disabled = mostrar;
        if (mostrar) {
            btn.textContent = '⏳ Procesando...';
        }
    });
}

// ===== FUNCIONES DE ESTADO DE PEDIDOS =====
function cambiarEstadoPago(pedidoId, nuevoEstado) {
    const accion = nuevoEstado == 1 ? 'confirmar el pago' : 'marcar como pago pendiente';

    if (!confirm('¿Estás seguro de ' + accion + ' del pedido #' + pedidoId + '?')) {
        return;
    }

    const formData = new FormData();
    formData.append('id', pedidoId);
    formData.append('pagado', nuevoEstado);

    // Mostrar indicador de carga
    const button = event.target;
    const originalHTML = button.innerHTML;
    button.innerHTML = '⏳';
    button.disabled = true;

    fetch('actualizar_estado.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarFeedbackAccion(`✅ Estado de pago ${nuevoEstado == 1 ? 'confirmado' : 'marcado como pendiente'}`, 'success');
            // Actualizar la UI sin recargar
            actualizarEstadoPagoUI(pedidoId, nuevoEstado);
        } else {
            mostrarFeedbackAccion('❌ Error: ' + (data.error || 'No se pudo actualizar el estado'), 'error');
            button.innerHTML = originalHTML;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarFeedbackAccion('❌ Error de conexión', 'error');
        button.innerHTML = originalHTML;
        button.disabled = false;
    });
}

function actualizarEstadoPagoUI(pedidoId, nuevoEstado) {
    // Actualizar botón de pago
    const row = document.querySelector('tr[data-id="' + pedidoId + '"]');
    const btnPago = row.querySelector('.btn-accion.pago, .btn-accion.pago-confirmado');

    if (nuevoEstado == 1) {
        btnPago.className = 'btn-accion pago-confirmado';
        btnPago.title = 'Marcar como Pendiente';
        btnPago.innerHTML = '💰✅';
        btnPago.onclick = () => cambiarEstadoPago(pedidoId, 0);
    } else {
        btnPago.className = 'btn-accion pago';
        btnPago.title = 'Confirmar Pago';
        btnPago.innerHTML = '💰';
        btnPago.onclick = () => cambiarEstadoPago(pedidoId, 1);
    }

    // Actualizar pill de estado
    const estadoContainer = row.querySelector('.estado-container');
    // Aquí puedes actualizar el estado pill si es necesario

    btnPago.disabled = false;
}

// ===== FUNCIONES DE GESTIÓN DE COMPROBANTES =====
function abrirModalComprobante(pedidoId, comprobante, tieneComprobante, metodoPago) {
    console.log('Abriendo modal comprobante:', { pedidoId, comprobante, tieneComprobante, metodoPago });

    try {
        const modal = document.createElement('div');
        modal.className = 'modal-detalle-bg';
        modal.setAttribute('data-pedido-id', pedidoId);

        let contenidoModal = '';

        // Si ya tiene comprobante, mostrar el comprobante
        if (tieneComprobante === '1' && comprobante && comprobante.trim() !== '') {
            const esImagen = /\.(jpg|jpeg|png|gif|webp)$/i.test(comprobante);
            const esPDF = /\.pdf$/i.test(comprobante);

            contenidoModal = '<div class="modal-detalle" style="max-width: 600px;">' +
                '<button class="cerrar-modal" onclick="this.closest(\'.modal-detalle-bg\').remove()">×</button>' +
                '<h3>📄 Comprobante de Pago - Pedido #' + pedidoId + '</h3>' +
                '<div class="comprobante-viewer">';

            if (esImagen) {
                contenidoModal += '<img src="comprobantes/' + comprobante + '" alt="Comprobante" style="max-width: 100%; max-height: 400px; border-radius: 8px; border: 1px solid #30363d;">';
            } else if (esPDF) {
                contenidoModal += '<iframe src="comprobantes/' + comprobante + '" style="width: 100%; height: 400px; border: 1px solid #30363d; border-radius: 8px;"></iframe>';
            } else {
                contenidoModal += '<div style="padding: 20px; text-align: center; border: 1px solid #30363d; border-radius: 8px;">' +
                    '<p>📄 Archivo: ' + comprobante + '</p>' +
                    '<a href="comprobantes/' + comprobante + '" target="_blank" class="btn-neon">Abrir Archivo</a>' +
                    '</div>';
            }

            contenidoModal += '</div>' +
                '<div class="acciones-comprobante" style="margin-top: 20px; display: flex; gap: 10px; justify-content: center;">' +
                '<button onclick="reemplazarComprobante(' + pedidoId + ')" class="btn-warning">🔄 Reemplazar</button>' +
                '<button onclick="eliminarComprobante(' + pedidoId + ')" class="btn-danger">🗑️ Eliminar</button>' +
                '<a href="comprobantes/' + comprobante + '" download class="btn-secondary">⬇️ Descargar</a>' +
                '</div>' +
                '</div>';
        }
        // Si el método de pago es efectivo, mostrar opción de marcar como efectivo
        else if (metodoPago && metodoPago.toLowerCase().includes('efectivo')) {
            contenidoModal = '<div class="modal-detalle" style="max-width: 450px;">' +
                '<button class="cerrar-modal" onclick="this.closest(\'.modal-detalle-bg\').remove()">×</button>' +
                '<h3>💵 Pago en Efectivo - Pedido #' + pedidoId + '</h3>' +
                '<div style="text-align: center; padding: 20px;">' +
                '<p style="margin-bottom: 20px;">Este pedido se pagó en <strong>efectivo</strong>.</p>' +
                '<p style="margin-bottom: 30px; color: #8b949e;">Los pagos en efectivo no requieren comprobante.</p>' +
                '<label style="display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 30px; font-size: 1.1rem;">' +
                '<input type="checkbox" id="checkEfectivo-' + pedidoId + '" onchange="marcarComoEfectivo(' + pedidoId + ', this.checked)" style="transform: scale(1.5);">' +
                '<span>✅ Confirmar pago en efectivo recibido</span>' +
                '</label>' +
                '<p style="margin-bottom: 20px; color: #FF9500;">¿Hubo cambio de método de pago?</p>' +
                '<button onclick="subirComprobanteAlternativo(' + pedidoId + ')" class="btn-secondary">📄 Subir Comprobante</button>' +
                '</div>' +
                '</div>';
        }
        // Si no tiene comprobante, mostrar opción para subir
        else {
            contenidoModal = '<div class="modal-detalle" style="max-width: 450px;">' +
                '<button class="cerrar-modal" onclick="this.closest(\'.modal-detalle-bg\').remove()">×</button>' +
                '<h3>📄 Subir Comprobante de Pago</h3>' +
                '<p style="text-align: center;">Pedido #' + pedidoId + '</p>' +
                '<form id="formComprobante-' + pedidoId + '" enctype="multipart/form-data" style="padding: 20px;">' +
                '<input type="hidden" name="id_pedido" value="' + pedidoId + '">' +
                '<div style="margin-bottom: 20px;">' +
                '<label style="display: block; margin-bottom: 8px; font-weight: 600;">Seleccionar archivo:</label>' +
                '<input type="file" name="comprobante" accept="image/*,application/pdf" required style="width: 100%; padding: 10px; border: 1px solid #30363d; border-radius: 6px; background: #161b22;">' +
                '<small style="color: #8b949e; display: block; margin-top: 5px;">Formatos aceptados: JPG, PNG, PDF (máx. 5MB)</small>' +
                '</div>' +
                '<button type="submit" class="btn-neon" style="width: 100%; margin-bottom: 15px;">📤 Subir Comprobante</button>' +
                '</form>' +
                '<div style="text-align: center; border-top: 1px solid #30363d; padding-top: 15px;">' +
                '<p style="margin-bottom: 15px; color: #8b949e;">¿Es pago en efectivo?</p>' +
                '<button onclick="marcarComoEfectivo(' + pedidoId + ', true)" class="btn-secondary">💵 Marcar como Efectivo</button>' +
                '</div>' +
                '</div>';
        }        modal.innerHTML = contenidoModal;

        // Debugging: verificar que el modal tiene contenido
        console.log('Contenido del modal generado:', contenidoModal.length > 0 ? 'OK' : 'VACÍO');
        console.log('Modal HTML:', modal.outerHTML.substring(0, 200) + '...');

        // Asegurar que no haya otros modales abiertos
        const modalesExistentes = document.querySelectorAll('.modal-detalle-bg');
        modalesExistentes.forEach(m => m.remove());

        // Añadir al DOM
        document.body.appendChild(modal);

        // Forzar el display
        modal.style.display = 'flex';
        modal.style.position = 'fixed';
        modal.style.top = '0';
        modal.style.left = '0';
        modal.style.width = '100%';
        modal.style.height = '100%';
        modal.style.zIndex = '10000';
        modal.style.background = 'rgba(0, 0, 0, 0.7)';

        // Verificar que se añadió al DOM
        console.log('Modal en DOM:', document.body.contains(modal));
        console.log('Estilos aplicados:', modal.style.display, modal.style.zIndex);

        // Si hay formulario, configurar el submit
        const form = modal.querySelector('#formComprobante-' + pedidoId);
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                subirComprobanteForm(pedidoId, this);
            });
        }

        console.log('Modal creado y mostrado exitosamente');

    } catch (error) {
        console.error('Error al crear modal:', error);
        // Método alternativo si falla el principal
        crearModalSimple(pedidoId, comprobante, tieneComprobante, metodoPago);
    }
}

// Método alternativo simplificado para crear modal
function crearModalSimple(pedidoId, comprobante, tieneComprobante, metodoPago) {
    console.log('Creando modal simple como respaldo...');

    // Crear overlay
    const overlay = document.createElement('div');
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
    `;

    // Crear contenido del modal
    const modalContent = document.createElement('div');
    modalContent.style.cssText = `
        background: #0d1117;
        border: 1px solid #30363d;
        border-radius: 8px;
        padding: 20px;
        max-width: 500px;
        width: 90%;
        position: relative;
        color: #e6edf3;
    `;

    // Botón cerrar
    const closeBtn = document.createElement('button');
    closeBtn.innerHTML = '×';
    closeBtn.style.cssText = `
        position: absolute;
        top: 10px;
        right: 15px;
        background: #ff3b30;
        border: none;
        border-radius: 50%;
        width: 30px;
        height: 30px;
        color: white;
        cursor: pointer;
        font-size: 18px;
    `;
    closeBtn.onclick = function() {
        overlay.remove();
    };

    // Contenido según estado
    if (tieneComprobante === '1' && comprobante) {
        modalContent.innerHTML = `
            <h3>📄 Comprobante - Pedido #${pedidoId}</h3>
            <img src="comprobantes/${comprobante}" alt="Comprobante" style="max-width: 100%; margin: 20px 0;">
            <div style="text-align: center; margin-top: 20px;">
                <button onclick="eliminarComprobante(${pedidoId})" style="background: #ff3b30; color: white; border: none; padding: 10px 20px; margin: 5px; border-radius: 5px; cursor: pointer;">🗑️ Eliminar</button>
                <a href="comprobantes/${comprobante}" download style="background: #007aff; color: white; padding: 10px 20px; margin: 5px; border-radius: 5px; text-decoration: none; display: inline-block;">⬇️ Descargar</a>
            </div>
        `;
    } else {
        modalContent.innerHTML = `
            <h3>📄 Subir Comprobante - Pedido #${pedidoId}</h3>
            <form id="formSimple-${pedidoId}" enctype="multipart/form-data">
                <input type="hidden" name="id_pedido" value="${pedidoId}">
                <input type="file" name="comprobante" accept="image/*,application/pdf" required style="width: 100%; margin: 20px 0; padding: 10px;">
                <button type="submit" style="background: #34c759; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; width: 100%;">📤 Subir</button>
            </form>
            <button onclick="marcarComoEfectivo(${pedidoId}, true)" style="background: #ff9500; color: white; border: none; padding: 10px 20px; margin-top: 10px; border-radius: 5px; cursor: pointer; width: 100%;">💵 Marcar como Efectivo</button>
        `;
    }

    modalContent.appendChild(closeBtn);
    overlay.appendChild(modalContent);
    document.body.appendChild(overlay);

    // Configurar formulario si existe
    const form = document.getElementById(`formSimple-${pedidoId}`);
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            subirComprobanteForm(pedidoId, this);
        });
    }

    console.log('Modal simple creado exitosamente');
}

function crearModalComprobante(pedidoId) {
    const modal = document.createElement('div');
    modal.className = 'modal-detalle-bg';
    modal.innerHTML = `
        <div class="modal-detalle" style="max-width:400px;text-align:center;">
            <button class="cerrar-modal" onclick="this.closest('.modal-detalle-bg').remove()">×</button>
            <h3>📄 Subir Comprobante de Pago</h3>
            <p>Pedido #${pedidoId}</p>
            <form id="formComprobante-${pedidoId}" enctype="multipart/form-data">
                <input type="hidden" name="id_pedido" value="${pedidoId}">
                <input type="file" name="comprobante" accept="image/*,application/pdf" required
                       style="margin-bottom:15px; width: 100%;">
                <button type="submit" class="btn-neon" style="width:100%;">
                    📤 Subir Comprobante
                </button>
            </form>
            <div id="comprobante-status-${pedidoId}" style="margin-top:15px;"></div>
        </div>
    `;

    // Agregar manejador de envío
    const form = modal.querySelector(`#formComprobante-${pedidoId}`);
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        procesarSubidaComprobante(pedidoId, this, modal);
    });

    return modal;
}

function procesarSubidaComprobante(pedidoId, form, modal) {
    const formData = new FormData(form);
    const statusDiv = modal.querySelector(`#comprobante-status-${pedidoId}`);
    const submitBtn = form.querySelector('button[type="submit"]');

    // Mostrar estado de carga
    submitBtn.disabled = true;
    submitBtn.textContent = '⏳ Subiendo...';
    statusDiv.innerHTML = '<span style="color: var(--vscode-text-muted);">Subiendo comprobante...</span>';

    fetch('subir_comprobante_modern.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            statusDiv.innerHTML = '<span style="color: #238636;">✅ Comprobante subido correctamente</span>';
            setTimeout(() => {
                modal.remove();
                location.reload(); // O actualizar la UI específica
            }, 2000);
        } else {
            statusDiv.innerHTML = '<span style="color: #da3633;">❌ ' + (data.error || 'Error al subir') + '</span>';
            submitBtn.disabled = false;
            submitBtn.textContent = '📤 Subir Comprobante';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        statusDiv.innerHTML = '<span style="color: #da3633;">❌ Error de conexión</span>';
        submitBtn.disabled = false;
        submitBtn.textContent = '📤 Subir Comprobante';
    });
}

function verComprobante(pedidoId) {
    // Abrir modal para ver comprobante
    fetch(`get_comprobante.php?id=${pedidoId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.comprobante) {
                window.open(data.comprobante, '_blank');
            } else {
                alert('No se encontró el comprobante');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al obtener el comprobante');
        });
}

// ============================================
// FUNCIONES PARA MODAL DE DETALLE
// ============================================

// Función para abrir modal de detalle
function abrirModalDetalle(pedidoId) {
    const modal = document.getElementById('modal-detalle');
    const contenido = document.getElementById('modal-contenido');

    if (!modal || !contenido) {
        console.error('No se encontraron los elementos del modal');
        return;
    }

    // Mostrar modal con loading
    contenido.innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <div style="font-size: 2rem; margin-bottom: 1rem;">⏳</div>
            <div>Cargando detalles del pedido...</div>
        </div>
    `;
    modal.style.display = 'block';

    // Cargar contenido del pedido
    fetch(`ver_detalle_pedido.php?id=${pedidoId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status}`);
            }
            return response.text();
        })
        .then(html => {
            contenido.innerHTML = html;
        })
        .catch(error => {
            console.error('Error al cargar detalle:', error);
            contenido.innerHTML = `
                <div style="text-align: center; padding: 40px; color: #ff3b30;">
                    <div style="font-size: 2rem; margin-bottom: 1rem;">❌</div>
                    <div><strong>Error al cargar el pedido</strong></div>
                    <div style="margin-top: 0.5rem; opacity: 0.7;">${error.message}</div>
                    <button onclick="cerrarModalDetalle()" style="margin-top: 1rem; padding: 8px 16px; background: #007AFF; color: white; border: none; border-radius: 4px; cursor: pointer;">Cerrar</button>
                </div>
            `;
        });
}

// Función para cerrar modal de detalle
function cerrarModalDetalle() {
    const modal = document.getElementById('modal-detalle');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Event listeners para los enlaces de "Ver Detalle"
document.addEventListener('click', function(event) {
    if (event.target.classList.contains('ver-detalle') || event.target.closest('.ver-detalle')) {
        event.preventDefault();
        const elemento = event.target.classList.contains('ver-detalle') ? event.target : event.target.closest('.ver-detalle');
        const pedidoId = elemento.getAttribute('data-id');
        if (pedidoId) {
            abrirModalDetalle(pedidoId);
        }
    }
});

// Cerrar modal al hacer click en el fondo
document.addEventListener('click', function(event) {
    if (event.target.id === 'modal-detalle') {
        cerrarModalDetalle();
    }
});

// Cerrar modal con tecla Escape
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        cerrarModalDetalle();
    }
});

// ============================================
// FUNCIONES PARA MENÚ DE ACCIONES
// ============================================

// Función para toggle del dropdown de acciones
function toggleAcciones(pedidoId) {
    const menu = document.getElementById(`menu-${pedidoId}`);
    if (!menu) {
        console.error(`No se encontró el menú con ID: menu-${pedidoId}`);
        return;
    }

    // Cerrar otros menús abiertos
    document.querySelectorAll('.menu-acciones').forEach(otroMenu => {
        if (otroMenu !== menu) {
            otroMenu.classList.remove('mostrar');
        }
    });

    // Toggle del menú actual
    menu.classList.toggle('mostrar');
}

// Cerrar menús de acciones al hacer click fuera
document.addEventListener('click', function(event) {
    if (!event.target.closest('.dropdown-acciones')) {
        document.querySelectorAll('.menu-acciones').forEach(menu => {
            menu.classList.remove('mostrar');
        });
    }
});

// Cerrar menús con tecla Escape
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        document.querySelectorAll('.menu-acciones').forEach(menu => {
            menu.classList.remove('mostrar');
        });
    }
});

// ============================================
// FUNCIONES DE ACCIONES DE PEDIDOS
// ============================================

// Función para confirmar pago
function confirmarPago(pedidoId) {
    if (confirm('¿Confirmar el pago de este pedido?')) {
        // Aquí irá la lógica para confirmar pago
        console.log('Confirmando pago del pedido:', pedidoId);
        mostrarFeedback('Pago confirmado correctamente', 'success');
    }
}

// Función para marcar como enviado
function marcarEnviado(pedidoId) {
    if (confirm('¿Marcar este pedido como enviado?')) {
        // Aquí irá la lógica para marcar como enviado
        console.log('Marcando como enviado el pedido:', pedidoId);
        mostrarFeedback('Pedido marcado como enviado', 'success');
    }
}

// Función para archivar pedido
function archivarPedido(pedidoId) {
    if (confirm('¿Archivar este pedido?')) {
        // Aquí irá la lógica para archivar
        console.log('Archivando pedido:', pedidoId);
        mostrarFeedback('Pedido archivado correctamente', 'success');
    }
}

// Función para restaurar pedido
function restaurarPedido(pedidoId) {
    if (confirm('¿Restaurar este pedido?')) {
        // Aquí irá la lógica para restaurar
        console.log('Restaurando pedido:', pedidoId);
        mostrarFeedback('Pedido restaurado correctamente', 'success');
    }
}

// Función para anular pedido
function anularPedido(pedidoId) {
    if (confirm('¿ANULAR este pedido? Esta acción no se puede deshacer.')) {
        // Aquí irá la lógica para anular
        console.log('Anulando pedido:', pedidoId);
        mostrarFeedback('Pedido anulado', 'error');
    }
}

// Función para mostrar feedback
function mostrarFeedback(mensaje, tipo = 'info') {
    const feedback = document.createElement('div');
    feedback.textContent = mensaje;
    feedback.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        background: ${tipo === 'success' ? '#34C759' : tipo === 'error' ? '#FF3B30' : '#007AFF'};
        color: white;
        border-radius: 8px;
        font-weight: 600;
        z-index: 9999;
        animation: slideInRight 0.3s ease;
    `;

    document.body.appendChild(feedback);

    // Remover después de 3 segundos
    setTimeout(() => {
        feedback.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => feedback.remove(), 300);
    }, 3000);
}

// Agregar estilos para animaciones de feedback
if (!document.querySelector('#feedback-styles')) {
    const style = document.createElement('style');
    style.id = 'feedback-styles';
    style.textContent = `
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
}

// ============================================
// FUNCIONES AUXILIARES PARA COMPROBANTES
// ============================================
function subirComprobanteForm(pedidoId, form) {
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');

    // Deshabilitar botón y mostrar cargando
    const textoOriginal = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '⏳ Subiendo...';

    fetch('subir_comprobante.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Cerrar modal
            form.closest('.modal-detalle-bg').remove();

            // Actualizar la página o la fila específica
            mostrarNotificacion('✅ Comprobante subido exitosamente', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            mostrarNotificacion('❌ Error: ' + (data.message || 'No se pudo subir el comprobante'), 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = textoOriginal;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarNotificacion('❌ Error de conexión al subir comprobante', 'error');
        submitBtn.disabled = false;
        submitBtn.innerHTML = textoOriginal;
    });
}

function marcarComoEfectivo(pedidoId, esEfectivo) {
    const datos = {
        id_pedido: pedidoId,
        es_efectivo: esEfectivo ? 1 : 0
    };

    fetch('actualizar_pago_efectivo.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(datos)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Cerrar modal
            document.querySelector(`[data-pedido-id="${pedidoId}"]`)?.remove();

            mostrarNotificacion('✅ Pago en efectivo ' + (esEfectivo ? 'confirmado' : 'desmarcado'), 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            mostrarNotificacion('❌ Error: ' + (data.message || 'No se pudo actualizar'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarNotificacion('❌ Error de conexión', 'error');
    });
}

function reemplazarComprobante(pedidoId) {
    // Cerrar modal actual y abrir modal de subida
    document.querySelector(`[data-pedido-id="${pedidoId}"]`)?.remove();

    setTimeout(() => {
        abrirModalComprobante(pedidoId, '', '0', 'transferencia');
    }, 100);
}

function eliminarComprobante(pedidoId) {
    if (!confirm('¿Estás seguro de que quieres eliminar este comprobante?')) {
        return;
    }

    fetch('eliminar_comprobante.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id_pedido: pedidoId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.querySelector(`[data-pedido-id="${pedidoId}"]`)?.remove();
            mostrarNotificacion('✅ Comprobante eliminado exitosamente', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            mostrarNotificacion('❌ Error: ' + (data.message || 'No se pudo eliminar'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarNotificacion('❌ Error de conexión', 'error');
    });
}

function subirComprobanteAlternativo(pedidoId) {
    // Cerrar modal actual y abrir modal de subida
    document.querySelector(`[data-pedido-id="${pedidoId}"]`)?.remove();

    setTimeout(() => {
        abrirModalComprobante(pedidoId, '', '0', 'transferencia');
    }, 100);
}

// Función para mostrar notificaciones
function mostrarNotificacion(mensaje, tipo = 'info') {
    const notificacion = document.createElement('div');
    notificacion.className = `notificacion notificacion-${tipo}`;
    notificacion.innerHTML = mensaje;
    notificacion.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        padding: 15px 20px;
        border-radius: 8px;
        color: white;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
        max-width: 400px;
    `;

    // Colores según tipo
    switch(tipo) {
        case 'success':
            notificacion.style.background = 'var(--apple-green)';
            break;
        case 'error':
            notificacion.style.background = 'var(--apple-red)';
            break;
        case 'warning':
            notificacion.style.background = 'var(--apple-orange)';
            break;
        default:
            notificacion.style.background = 'var(--apple-blue)';
    }

    document.body.appendChild(notificacion);

    // Animar entrada
    setTimeout(() => {
        notificacion.style.opacity = '1';
        notificacion.style.transform = 'translateX(0)';
    }, 100);

    // Animar salida y eliminar
    setTimeout(() => {
        notificacion.style.opacity = '0';
        notificacion.style.transform = 'translateX(100%)';
        setTimeout(() => notificacion.remove(), 300);
    }, 4000);
}
</script>

</body>
</html>
