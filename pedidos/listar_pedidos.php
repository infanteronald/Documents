<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'conexion.php';

// Filtros expandidos
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'todos';
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

// Filtros adicionales
if($buscar){
    $buscarSql = $conn->real_escape_string($buscar);
    $where .= " AND (nombre LIKE '%$buscarSql%' OR telefono LIKE '%$buscarSql%' OR id = '$buscarSql' OR correo LIKE '%$buscarSql%' OR direccion LIKE '%$buscarSql%')";
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
    ORDER BY p.fecha DESC
    LIMIT $limite OFFSET $offset
");

if (!$result) {
    die("Error en la consulta: " . $conn->error);
}

$pedidos = [];
while ($row = $result->fetch_assoc()) {
    $pedidos[] = $row;
}

$total_result = $conn->query("SELECT FOUND_ROWS() as total");
$total_pedidos = $total_result->fetch_assoc()['total'];
$total_paginas = ceil($total_pedidos / $limite);

// Calcular monto total real sumando desde pedido_detalle
$monto_total_result = $conn->query("
    SELECT COALESCE(SUM(pd.cantidad * pd.precio), 0) as monto_total
    FROM pedidos_detal p
    LEFT JOIN pedido_detalle pd ON p.id = pd.pedido_id
    WHERE $where
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
                    <option value="pago_pendiente" <?php echo ($filtro=='pago_pendiente' ? 'selected' : ''); ?>>⏳ Pendientes</option>
                    <option value="pago_confirmado" <?php echo ($filtro=='pago_confirmado' ? 'selected' : ''); ?>>✅ Pagados</option>
                    <option value="enviados" <?php echo ($filtro=='enviados' ? 'selected' : ''); ?>>🚚 Enviados</option>
                    <option value="anulados" <?php echo ($filtro=='anulados' ? 'selected' : ''); ?>>❌ Anulados</option>
                </select>

                <input type="text"
                       id="busquedaRapida"
                       value="<?php echo htmlspecialchars($buscar); ?>"
                       placeholder="🔍 Buscar..."
                       class="input-compacto"
                       onkeyup="busquedaEnTiempoReal(this.value)">

                <button class="btn-filtros-avanzados" onclick="toggleFiltrosAvanzados()" title="Más filtros">⚙️</button>
            </div>

            <!-- Estadísticas en línea -->
            <div class="stats-inline">
                <span class="stat-inline">📦 <?php echo number_format($total_pedidos); ?></span>
                <span class="stat-inline">💰 $<?php echo number_format($monto_total_real, 0, ',', '.'); ?></span>
                <span class="stat-inline">⏳ <?php echo count(array_filter($pedidos, function($p) { return $p['pagado'] == '0'; })); ?></span>
                <span class="stat-inline">✅ <?php echo count(array_filter($pedidos, function($p) { return $p['pagado'] == '1'; })); ?></span>
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
                                        <div class="email-cliente"><?php echo htmlspecialchars($p['correo']); ?></div>
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
                                <td class="col-comprobante">
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

// ===== FUNCIONES DE GESTIÓN DE ARCHIVOS =====
function subirComprobante(pedidoId) {
    // Crear modal dinámico para subir comprobante
    const modal = crearModalComprobante(pedidoId);
    document.body.appendChild(modal);
    modal.style.display = 'flex';
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
// FUNCIONES PARA MOSTRAR PRODUCTOS DEL PEDIDO
// ============================================

// Función para toggle de productos del pedido
function toggleProductos(pedidoId) {
    console.log(`🚀 toggleProductos llamado con ID: ${pedidoId}`);
    const filaProductos = document.getElementById(`productos-${pedidoId}`);
    console.log(`🔍 Fila productos existente:`, filaProductos);

    if (filaProductos) {
        console.log(`♻️ Fila existe, verificando contenido...`);

        // Verificar si la fila tiene contenido de loading o error
        const tieneLoading = filaProductos.querySelector('.carrito-loading');
        const tieneError = filaProductos.querySelector('.carrito-error');

        console.log(`🔍 Tiene loading:`, !!tieneLoading);
        console.log(`🔍 Tiene error:`, !!tieneError);

        if (tieneLoading || tieneError) {
            console.log(`🔄 Fila tiene loading/error, recargando productos...`);
            // Si tiene loading o error, recargar
            filaProductos.remove();
            cargarProductosPedido(pedidoId);
            return;
        }

        // Usar clases CSS para controlar visibilidad
        const isVisible = !filaProductos.classList.contains('oculta');
        console.log(`🔍 DEBUG - isVisible: ${isVisible}`);

        if (isVisible) {
            filaProductos.classList.add('oculta');
            console.log(`🙈 Ocultando fila productos (añadiendo clase 'oculta')`);
        } else {
            filaProductos.classList.remove('oculta');
            console.log(`👁️ Mostrando fila productos (removiendo clase 'oculta')`);
        }
    } else {
        console.log(`🆕 Fila no existe, cargando productos...`);
        // Si no existe, cargar productos y crear la fila
        cargarProductosPedido(pedidoId);
    }
}

// Función para cargar productos del pedido
function cargarProductosPedido(pedidoId) {
    console.log(`📦 cargarProductosPedido iniciado para ID: ${pedidoId}`);
    const filaActual = document.querySelector(`tr[data-id="${pedidoId}"]`);
    console.log(`🔍 Fila actual encontrada:`, filaActual);

    if (!filaActual) {
        console.error(`❌ No se encontró la fila del pedido: ${pedidoId}`);
        return;
    }

    // Crear fila de productos
    console.log(`🏗️ Creando fila de productos para pedido ${pedidoId}`);
    const filaProductos = document.createElement('tr');
    filaProductos.id = `productos-${pedidoId}`;
    filaProductos.className = 'fila-productos';
    console.log(`✅ Fila creada con ID: ${filaProductos.id}`);

    // Crear celda que abarca todas las columnas
    const columnas = filaActual.children.length;
    console.log(`📊 Número de columnas: ${columnas}`);
    filaProductos.innerHTML = `
        <td colspan="${columnas}" class="productos-container">
            <div class="carrito-container">
                <div class="carrito-header">
                    <div class="carrito-titulo">
                        <span class="carrito-icono">🛒</span>
                        Cargando carrito - Pedido #${pedidoId}
                    </div>
                </div>
                <div class="carrito-loading">
                    <div class="carrito-loading-spinner">⏳</div>
                    <div>Cargando productos del carrito...</div>
                    <div style="font-size: 0.8rem; opacity: 0.7;">
                        Obteniendo contenido del pedido...
                    </div>
                </div>
            </div>
        </td>
    `;

    // Insertar la fila después de la fila actual
    console.log(`📥 Insertando fila después de la fila actual`);
    filaActual.insertAdjacentElement('afterend', filaProductos);

    // Mostrar la fila inmediatamente (el usuario hizo clic para verla)
    // NO usar clase 'oculta' para que se muestre
    console.log(`✅ Fila insertada correctamente y mostrada (sin clase 'oculta')`);

    // Timeout de 10 segundos
    const timeoutId = setTimeout(() => {
        mostrarErrorProductos(pedidoId, 'Tiempo de espera agotado. Verifica la conexión.');
    }, 10000);

    // Cargar productos via AJAX - CARRITO COMPLETO DEL CLIENTE
    console.log('🔍 Cargando carrito completo para pedido:', pedidoId);
    console.log('🌐 URL del endpoint:', `get_productos_pedido.php?id=${pedidoId}`);

    fetch(`get_productos_pedido.php?id=${pedidoId}`)
        .then(response => {
            console.log('📡 Respuesta recibida:', response);
            console.log('📊 Status:', response.status, 'OK:', response.ok);
            console.log('📊 Headers:', response.headers);
            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status} - ${response.statusText}`);
            }
            return response.text(); // Primero como texto para debugging
        })
        .then(text => {
            clearTimeout(timeoutId);
            console.log('📝 Texto crudo recibido (primeros 200 chars):', text.substring(0, 200));
            console.log('📝 Longitud del texto:', text.length);

            if (!text || text.trim() === '') {
                throw new Error('Respuesta vacía del servidor');
            }

            try {
                const data = JSON.parse(text);
                console.log('📦 Datos JSON parseados exitosamente:', data);
                if (data.success) {
                    if (data.productos && data.productos.length > 0) {
                        console.log('✅ Productos encontrados:', data.productos.length);
                        console.log('🛍️ Primer producto:', data.productos[0]);
                        mostrarProductos(pedidoId, data.productos);
                    } else {
                        console.log('📭 No se encontraron productos para este pedido');
                        mostrarErrorProductos(pedidoId, 'Este pedido no tiene productos detallados');
                    }
                } else {
                    console.log('❌ Error en respuesta:', data.error);
                    mostrarErrorProductos(pedidoId, data.error || 'No se encontraron productos');
                }
            } catch (parseError) {
                console.error('🚨 Error parseando JSON:', parseError);
                console.log('📄 Texto completo que causó el error:', text);
                mostrarErrorProductos(pedidoId, 'Error en la respuesta del servidor: respuesta no es JSON válido');
            }
        })
        .catch(error => {
            clearTimeout(timeoutId);
            console.error('🚨 Error completo al cargar productos:', error);
            console.error('🚨 Stack trace:', error.stack);
            mostrarErrorProductos(pedidoId, 'Error al cargar productos: ' + error.message);
        });
}

// Función para mostrar productos en la fila expandida
function mostrarProductos(pedidoId, productos) {
    console.log(`🛍️ mostrarProductos llamado para pedido ${pedidoId} con ${productos.length} productos`);
    const filaProductos = document.getElementById(`productos-${pedidoId}`);
    console.log(`🔍 Fila productos encontrada:`, filaProductos);

    if (!filaProductos) {
        console.error(`❌ No se encontró la fila productos-${pedidoId}`);
        return;
    }

    if (!productos || productos.length === 0) {
        // Mostrar carrito vacío
        const columnas = filaProductos.querySelector('td').getAttribute('colspan');
        filaProductos.innerHTML = `
            <td colspan="${columnas}" class="productos-container">
                <div class="carrito-container">
                    <div class="carrito-header">
                        <div class="carrito-titulo">
                            <span class="carrito-icono">🛒</span>
                            Carrito de Compra - Pedido #${pedidoId}
                        </div>
                        <button class="btn-cerrar-productos" onclick="toggleProductos(${pedidoId})" title="Cerrar">×</button>
                    </div>
                    <div class="carrito-vacio">
                        <div class="carrito-vacio-icono">🛍️</div>
                        <div>Este pedido no tiene productos detallados</div>
                    </div>
                </div>
            </td>
        `;
        return;
    }

    // Calcular totales
    let totalGeneral = 0;
    let totalItems = 0;

    // Generar HTML del carrito
    let carritoHTML = '';

    productos.forEach((producto, index) => {
        const precio = parseFloat(producto.precio) || 0;
        const cantidad = parseInt(producto.cantidad) || 0;
        const subtotal = precio * cantidad;
        totalGeneral += subtotal;
        totalItems += cantidad;

        carritoHTML += `
            <div class="carrito-producto">
                <div class="producto-info-carrito">
                    <div class="producto-nombre-carrito">${producto.nombre || 'Producto sin nombre'}</div>
                    <div class="producto-variante">
                        ${producto.talla ? `<span class="producto-talla-carrito">${producto.talla}</span>` : '<span class="producto-talla-carrito">Sin talla</span>'}
                    </div>
                </div>
                <div class="producto-cantidad-carrito">
                    ${cantidad}x
                </div>
                <div class="producto-precio-carrito">
                    $${precio.toLocaleString()}
                </div>
                <div class="producto-subtotal-carrito">
                    $${subtotal.toLocaleString()}
                </div>
            </div>
        `;
    });

    // HTML completo del carrito
    const carritoCompleto = `
        <div class="carrito-container">
            <div class="carrito-header">
                <div class="carrito-titulo">
                    <span class="carrito-icono">🛒</span>
                    Carrito de Compra - Pedido #${pedidoId}
                </div>
                <div class="carrito-resumen">
                    <span>${productos.length} productos</span>
                    <span>${totalItems} artículos</span>
                </div>
                <button class="btn-cerrar-productos" onclick="toggleProductos(${pedidoId})" title="Cerrar">×</button>
            </div>

            <div class="carrito-productos">
                ${carritoHTML}
            </div>

            <div class="carrito-total">
                <div class="carrito-total-row">
                    <span class="carrito-total-label">Subtotal (${totalItems} artículos):</span>
                    <span class="carrito-total-valor">$${totalGeneral.toLocaleString()}</span>
                </div>
                <div class="carrito-total-row">
                    <span class="carrito-total-label">Productos diferentes:</span>
                    <span class="carrito-total-valor">${productos.length}</span>
                </div>
                <div class="carrito-total-row">
                    <span class="carrito-total-label">TOTAL DEL PEDIDO:</span>
                    <span class="carrito-total-valor carrito-total-final">$${totalGeneral.toLocaleString()}</span>
                </div>
            </div>
        </div>
    `;

    const columnas = filaProductos.querySelector('td').getAttribute('colspan');
    filaProductos.innerHTML = `
        <td colspan="${columnas}" class="productos-container">
            ${carritoCompleto}
        </td>
    `;

    console.log(`✅ Carrito mostrado: ${productos.length} productos, ${totalItems} items, total: $${totalGeneral.toLocaleString()}`);
}

// Función para mostrar error al cargar productos
function mostrarErrorProductos(pedidoId, mensaje) {
    console.log(`❌ mostrarErrorProductos: ${mensaje}`);
    const filaProductos = document.getElementById(`productos-${pedidoId}`);
    if (!filaProductos) return;

    const columnas = filaProductos.querySelector('td').getAttribute('colspan');
    filaProductos.innerHTML = `
        <td colspan="${columnas}" class="productos-container">
            <div class="carrito-container">
                <div class="carrito-header">
                    <div class="carrito-titulo">
                        <span class="carrito-icono">🛒</span>
                        Error al cargar carrito - Pedido #${pedidoId}
                    </div>
                    <button class="btn-cerrar-productos" onclick="toggleProductos(${pedidoId})" title="Cerrar">×</button>
                </div>
                <div class="carrito-error">
                    <div style="margin-bottom: 10px;">❌ Error</div>
                    <div>${mensaje}</div>
                </div>
            </div>
        </td>
    `;
}
</script>

</body>
</html>
