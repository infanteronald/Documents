<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'conexion.php';
require_once 'filters.php';
require_once 'ui-helpers.php';

// Inicializar filtros usando la nueva clase
try {
    $filter = new PedidosFilter($conn);
    $filter_data = $filter->processFilters();
    
    // Extraer datos
    $pedidos = $filter_data['pedidos'];
    $total_pedidos = $filter_data['total_pedidos'];
    $monto_total_real = $filter_data['monto_total_real'];
    $total_paginas = $filter_data['total_paginas'];
    $metodos_pago = $filter_data['metodos_pago'];
    $ciudades = $filter_data['ciudades'];
    
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

// Las funciones de filtrado ahora están en filters.php

// Las funciones auxiliares están ahora en ui-helpers.php
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
                        <?php echo generate_filter_options($metodos_pago, $metodo_pago, 'Método de pago', '💳'); ?>
                    </select>

                    <select name="ciudad" class="select-avanzado" onchange="aplicarFiltros()">
                        <?php echo generate_filter_options($ciudades, $ciudad, 'Ciudad', '🏙️'); ?>
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
                        <th class="col-ver">👁️ Acciones</th>
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
                                    <?php $fecha_info = format_date($p['fecha']); ?>
                                    <div class="info-fecha">
                                        <div class="fecha-principal"><?php echo $fecha_info['fecha_principal']; ?></div>
                                        <div class="hora-pedido"><?php echo $fecha_info['hora_pedido']; ?></div>
                                    </div>
                                </td>

                                <!-- Cliente -->
                                <td class="col-cliente">
                                    <?php $cliente_info = generate_customer_info($p); ?>
                                    <div class="info-cliente">
                                        <div class="nombre-cliente"><?php echo $cliente_info['nombre']; ?></div>
                                        <div class="telefono-ciudad">
                                            <a href="#" onclick="abrirWhatsApp('<?php echo $cliente_info['telefono_whatsapp']; ?>'); return false;" class="whatsapp-link" title="Contactar por WhatsApp">
                                                📱
                                            </a>
                                            <?php echo $cliente_info['telefono_display'] . ' - ' . $cliente_info['ciudad']; ?>
                                        </div>
                                    </div>
                                </td>

                                <!-- Monto -->
                                <td class="col-monto">
                                    <span class="valor-monto">$<?php echo number_format($p['monto'], 0, ',', '.'); ?></span>
                                </td>

                                <!-- Acciones -->
                                <td class="col-ver">
                                    <div class="botones-acciones">
                                        <button class="btn-accion-tabla btn-ver-productos" onclick="toggleProductos(<?php echo $p['id']; ?>)" title="Ver productos del pedido">
                                            👁️
                                        </button>
                                        <button class="btn-accion-tabla btn-configurar" onclick="abrirDetallePopup(<?php echo $p['id']; ?>)" title="Configurar pedido">
                                            ⚙️
                                        </button>
                                    </div>
                                </td>

                                <!-- Status: Pagado -->
                                <td class="col-pagado" onclick="toggleEstadoPago(<?php echo $p['id']; ?>, <?php echo $p['pagado']; ?>, '<?php echo htmlspecialchars($p['comprobante']); ?>', '<?php echo $p['tiene_comprobante']; ?>', '<?php echo htmlspecialchars($p['metodo_pago']); ?>')" style="cursor: pointer;" title="<?php echo $p['pagado'] == '1' ? 'Click para marcar como NO pagado' : 'Click para subir comprobante'; ?>">
                                    <span class="badge-status <?php echo $p['pagado'] == '1' ? 'status-si' : 'status-no'; ?>">
                                        <?php echo $p['pagado'] == '1' ? '✅ Sí' : '⏳ No'; ?>
                                    </span>
                                </td>

                                <!-- Status: Enviado -->
                                <td class="col-enviado">
                                    <?php echo generate_status_badge($p['enviado'], 'enviado'); ?>
                                </td>

                                <!-- Status: Comprobante -->
                                <td class="col-comprobante" onclick="abrirModalComprobante(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['comprobante']); ?>', '<?php echo $p['tiene_comprobante']; ?>', '<?php echo htmlspecialchars($p['metodo_pago']); ?>')" style="cursor: pointer;" title="Click para ver/subir comprobante">
                                    <?php echo generate_status_badge($p['tiene_comprobante'], 'comprobante'); ?>
                                </td>

                                <!-- Status: Guía -->
                                <td class="col-guia" onclick="abrirModalGuia(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['guia']); ?>', '<?php echo $p['tiene_guia']; ?>', '<?php echo $p['enviado']; ?>')" style="cursor: pointer;" title="Click para ver/subir guía">
                                    <?php echo generate_status_badge($p['tiene_guia'], 'guia'); ?>
                                </td>

                                <!-- Status: Archivado -->
                                <td class="col-archivado">
                                    <?php echo generate_status_badge($p['archivado'], 'archivado'); ?>
                                </td>

                                <!-- Status: Anulado -->
                                <td class="col-anulado">
                                    <?php echo generate_status_badge($p['anulado'], 'anulado'); ?>
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

    try {
        const modal = document.createElement('div');
        modal.className = 'modal-detalle-bg';
        modal.setAttribute('data-pedido-id', pedidoId);

        let contenidoModal = '';

        // Si ya tiene comprobante, mostrar el comprobante
        if (tieneComprobante === '1' && comprobante && comprobante.trim() !== '') {
            // Caso especial: Efectivo confirmado
            if (comprobante === 'EFECTIVO_CONFIRMADO') {
                contenidoModal = '<div class="modal-detalle" style="max-width: 500px;">' +
                    '<button class="cerrar-modal" onclick="this.closest(\'.modal-detalle-bg\').remove()">×</button>' +
                    '<h3>💵 Pago en Efectivo Confirmado - Pedido #' + pedidoId + '</h3>' +
                    '<div style="text-align: center; padding: 20px;">' +
                    '<div style="background: var(--apple-green); color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;">' +
                    '<strong>✅ Pago en efectivo confirmado</strong>' +
                    '</div>' +
                    '<p style="margin-bottom: 20px; color: #8b949e;">Este pedido fue marcado como pagado en efectivo.</p>' +
                    '<div class="acciones-comprobante" style="display: flex; gap: 10px; justify-content: center;">' +
                    '<button onclick="desconfirmarEfectivo(' + pedidoId + ')" class="btn-danger">❌ Desconfirmar Efectivo</button>' +
                    '<button onclick="subirComprobanteAlternativo(' + pedidoId + ')" class="btn-secondary">📄 Cambiar a Comprobante</button>' +
                    '</div>' +
                    '</div>' +
                    '</div>';
            } else {
                // Comprobante normal (archivo)
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


        // Si hay formulario, configurar el submit
        const form = modal.querySelector('#formComprobante-' + pedidoId);
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                subirComprobanteForm(pedidoId, this);
            });
        }


    } catch (error) {
        console.error('Error al crear modal:', error);
        alert('Error al crear el modal de comprobante');
    }
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

// Función para abrir detalle en popup
function abrirDetallePopup(pedidoId) {
    const url = `ver_detalle_pedido.php?id=${pedidoId}`;

    // Calcular posición centrada
    const ancho = 900;
    const alto = 650;
    const left = (screen.width - ancho) / 2;
    const top = (screen.height - alto) / 2;

    const opciones = `width=${ancho},height=${alto},left=${left},top=${top},scrollbars=yes,resizable=yes,toolbar=no,menubar=no,location=no,status=no,directories=no,fullscreen=no`;

    window.open(url, `detalle_pedido_${pedidoId}`, opciones);
}

// Función para cambiar estado de pago
function toggleEstadoPago(pedidoId, estadoActual, comprobante, tieneComprobante, metodoPago) {

    if (estadoActual == 1) {
        // Si está pagado, preguntar si desea marcarlo como no pagado
        if (confirm('¿Estás seguro de que deseas marcar este pedido como NO PAGADO?')) {
            cambiarEstadoPago(pedidoId, 0);
        }
    } else {
        // Si no está pagado, abrir modal para subir comprobante
        abrirModalComprobante(pedidoId, comprobante, tieneComprobante, metodoPago);
    }
}

// Función para cambiar estado de pago en la base de datos
function cambiarEstadoPago(pedidoId, nuevoEstado) {
    const formData = new FormData();
    formData.append('id', pedidoId);
    formData.append('estado', nuevoEstado == 1 ? 'pago-confirmado' : 'pago-pendiente');

    fetch('actualizar_estado.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Actualizar el span en la tabla sin recargar la página
            const celda = document.querySelector(`td[onclick*="toggleEstadoPago(${pedidoId}"]`);
            const span = celda ? celda.querySelector('.badge-status') : null;
            if (span && nuevoEstado == 0) {
                span.className = 'badge-status status-no';
                span.innerHTML = '⏳ No';
                celda.title = 'Click para subir comprobante';
                // Actualizar el onclick para reflejar el nuevo estado
                const currentOnclick = celda.getAttribute('onclick');
                const match = currentOnclick.match(/toggleEstadoPago\((\d+),\s*(\d+),\s*'([^']*)',\s*'([^']*)',\s*'([^']*)'\)/);
                if (match) {
                    celda.setAttribute('onclick', `toggleEstadoPago(${pedidoId}, 0, '', '0', '${match[5]}')`);
                }
            }
            mostrarNotificacion('✅ Estado de pago actualizado correctamente', 'success');
        } else {
            mostrarNotificacion('❌ Error al actualizar el estado de pago: ' + (data.error || 'Error desconocido'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarNotificacion('❌ Error de conexión al actualizar el estado de pago', 'error');
    });
}

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
        feedback.style.animation = 'slideOutRight  0.3s ease';
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
    const statusDiv = modal.querySelector(`#comprobante-status-${pedidoId}`);
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
    abrirModalComprobante(pedidoId);
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

function desconfirmarEfectivo(pedidoId) {
    if (!confirm('¿Estás seguro de que quieres desconfirmar este pago en efectivo?')) {
        return;
    }

    fetch('actualizar_pago_efectivo.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            id_pedido: pedidoId,
            es_efectivo: 0 // Desmarcar como efectivo
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.querySelector(`[data-pedido-id="${pedidoId}"]`)?.remove();
            mostrarNotificacion('✅ Pago en efectivo desconfirmado exitosamente', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            mostrarNotificacion('❌ Error: ' + (data.message || 'No se pudo desconfirmar'), 'error');
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

// ===== FUNCIÓN PARA ABRIR MODAL DE GUÍAS =====
function abrirModalGuia(pedidoId, guia, tieneGuia, enviado) {

    try {
        const modal = document.createElement('div');
        modal.className = 'modal-detalle-bg';
        modal.setAttribute('data-pedido-id', pedidoId);

        let contenidoModal = '';

        if (tieneGuia == '1' && guia && guia.trim() !== '') {
            // Mostrar guía existente con opciones
            const esImagen = /\.(jpg|jpeg|png|gif|bmp|webp)$/i.test(guia);
            const esPdf = /\.pdf$/i.test(guia);

            contenidoModal = `
                <div class="modal-detalle" style="max-width: 500px;">
                    <button class="cerrar-modal" onclick="this.closest('.modal-detalle-bg').remove()">×</button>
                    <h3 style="margin-bottom: 20px; color: var(--vscode-text);">📦 Guía de Envío - Pedido #${pedidoId}</h3>

                    <div style="margin-bottom: 20px;">
                        <div style="background: var(--vscode-sidebar); padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                                <span style="color: var(--apple-green);">✅</span>
                                <strong>Guía adjunta:</strong> ${guia}
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="color: ${enviado == '1' ? 'var(--apple-green)' : 'var(--apple-orange)'};">
                                    ${enviado == '1' ? '🚚' : '📋'}
                                </span>
                                <span>Estado: ${enviado == '1' ? 'Enviado' : 'Preparando envío'}</span>
                            </div>
                        </div>

                        ${esImagen ? `
                            <div style="text-align: center; margin-bottom: 15px;">
                                <img src="guias/${guia}" alt="Guía" style="max-width: 100%; max-height: 300px; border-radius: 8px; border: 1px solid var(--vscode-border);">
                            </div>
                        ` : esPdf ? `
                            <div style="text-align: center; margin-bottom: 15px;">
                                <div style="padding: 20px; background: var(--vscode-sidebar); border-radius: 8px; border: 1px solid var(--vscode-border);">
                                    <div style="font-size: 48px; margin-bottom: 10px;">📄</div>
                                    <div>Archivo PDF adjunto</div>
                                </div>
                            </div>
                        ` : `
                            <div style="text-align: center; margin-bottom: 15px;">
                                <div style="padding: 20px; background: var(--vscode-sidebar); border-radius: 8px; border: 1px solid var(--vscode-border);">
                                    <div style="font-size: 48px; margin-bottom: 10px;">📁</div>
                                    <div>Archivo adjunto</div>
                                </div>
                            </div>
                        `}
                    </div>

                    <div style="display: flex; gap: 10px; justify-content: space-between;">
                        <button onclick="verGuia(${pedidoId})" class="btn-accion btn-ver" style="flex: 1;">
                            👁️ Ver/Descargar
                        </button>
                        <button onclick="reemplazarGuia(${pedidoId})" class="btn-accion btn-editar" style="flex: 1;">
                            🔄 Reemplazar
                        </button>
                        <button onclick="eliminarGuia(${pedidoId})" class="btn-accion btn-eliminar" style="flex: 1;">
                            🗑️ Eliminar
                        </button>
                    </div>
                </div>
            `;
        } else {
            // Formulario para subir nueva guía
            contenidoModal = `
                <div class="modal-detalle" style="max-width: 450px;">
                    <button class="cerrar-modal" onclick="this.closest('.modal-detalle-bg').remove()">×</button>
                    <h3 style="margin-bottom: 20px; color: var(--vscode-text);">📦 Subir Guía de Envío - Pedido #${pedidoId}</h3>

                    <form id="formSubirGuia" enctype="multipart/form-data" style="text-align: left;">
                        <input type="hidden" name="pedido_id" value="${pedidoId}">

                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                                📎 Seleccionar archivo de guía:
                            </label>
                            <input type="file" name="guia" accept="image/*,application/pdf" required
                                   style="width: 100%; padding: 10px; border: 1px solid var(--vscode-border); border-radius: 6px; background: var(--vscode-bg);">
                            <small style="color: var(--vscode-text-muted); display: block; margin-top: 5px;">
                                Formatos: JPG, PNG, PDF (máx. 10MB)
                            </small>
                        </div>

                        <div style="margin-bottom: 20px;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="marcar_enviado" value="true" id="marcarEnviado">
                                <span>🚚 Marcar pedido como enviado al subir guía</span>
                            </label>
                        </div>

                        <button type="submit" class="btn-accion btn-subir" style="width: 100%; padding: 12px;">
                            📤 Subir Guía y Notificar Cliente
                        </button>
                    </form>

                    <div id="statusGuia" style="margin-top: 15px; padding: 10px; border-radius: 6px; display: none;"></div>
                </div>
            `;
        }

        modal.innerHTML = contenidoModal;
        document.body.appendChild(modal);

        // Configurar el formulario si existe
        const form = document.getElementById('formSubirGuia');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                subirGuia(pedidoId);
            });
        }

        // Cerrar modal al hacer click en el fondo
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.remove();
            }
        });

    } catch (error) {
        console.error('Error creando modal guía:', error);
        alert('Error al abrir el modal de guía');
    }
}

// ===== FUNCIONES AUXILIARES PARA GUÍAS =====
function verGuia(pedidoId) {
    window.open(`ver_guia.php?id=${pedidoId}`, '_blank');
}

function reemplazarGuia(pedidoId) {
    // Cerrar modal actual y abrir modal de subida
    document.querySelector(`[data-pedido-id="${pedidoId}"]`)?.remove();

    setTimeout(() => {
        abrirModalGuia(pedidoId, '', '0', '0');
    }, 100);
}

function eliminarGuia(pedidoId) {
    if (!confirm('¿Estás seguro de que deseas eliminar la guía de envío?\n\nEsta acción no se puede deshacer.')) {
        return;
    }

    fetch('eliminar_guia.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            id_pedido: pedidoId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.querySelector(`[data-pedido-id="${pedidoId}"]`)?.remove();
            mostrarNotificacion('✅ Guía eliminada exitosamente', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            mostrarNotificacion('❌ Error: ' + (data.message || 'No se pudo eliminar la guía'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarNotificacion('❌ Error de conexión', 'error');
    });
}

// ===== FUNCIÓN PARA MOSTRAR/OCULTAR PRODUCTOS =====
function toggleProductos(pedidoId) {

    const filaProductos = document.querySelector(`#productos-${pedidoId}`);
    const boton = document.querySelector(`button[onclick="toggleProductos(${pedidoId})"]`);

    if (!filaProductos) {
        // La fila no existe, crearla y cargar productos
        const filaPedido = boton.closest('tr');
        const nuevaFila = document.createElement('tr');
        nuevaFila.id = `productos-${pedidoId}`;
        nuevaFila.className = 'fila-productos';
        nuevaFila.innerHTML = `
            <td colspan="12" class="productos-container">
                <div class="productos-loading">
                    <div class="spinner"></div>
                    <span>Cargando productos...</span>
                </div>
            </td>
        `;

        // Insertar después de la fila del pedido
        filaPedido.parentNode.insertBefore(nuevaFila, filaPedido.nextSibling);

        // Cambiar texto del botón
        boton.innerHTML = '👁️ Ocultar';
        boton.title = 'Ocultar productos del pedido';

        // Cargar productos via AJAX
        cargarProductosPedido(pedidoId);
    } else {
        // La fila existe, toggle visibilidad
        const esVisible = getComputedStyle(filaProductos).display !== 'none';

        if (esVisible) {
            // Ocultar
            filaProductos.style.display = 'none';
            boton.innerHTML = '👁️ Ver';
            boton.title = 'Ver productos del pedido';
        } else {
            // Mostrar
            filaProductos.style.display = 'table-row';
            boton.innerHTML = '👁️ Ocultar';
            boton.title = 'Ocultar productos del pedido';
        }
    }
}

function cargarProductosPedido(pedidoId) {
    const productosContainer = document.querySelector(`#productos-${pedidoId} .productos-container`);

    fetch(`get_productos_pedido.php?id=${pedidoId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.productos) {
                // Calcular totales
                const total = data.productos.reduce((sum, p) => sum + (p.cantidad * p.precio), 0);
                const cantidadTotal = data.productos.reduce((sum, p) => sum + parseInt(p.cantidad), 0);

                let html = `
                    <div class="carrito-moderno">
                        <div class="carrito-header-moderno">
                            <div class="carrito-info">
                                <div class="carrito-titulo-principal">
                                    <span class="carrito-icono">🛒</span>
                                    <span>Detalles del Pedido #${pedidoId}</span>
                                </div>
                                <div class="carrito-estadisticas">
                                    <div class="stat-item">
                                        <span class="stat-numero">${data.productos.length}</span>
                                        <span class="stat-label">productos</span>
                                    </div>
                                    <div class="stat-divider">•</div>
                                    <div class="stat-item">
                                        <span class="stat-numero">${cantidadTotal}</span>
                                        <span class="stat-label">unidades</span>
                                    </div>
                                </div>
                            </div>
                            <button class="btn-cerrar-moderno" onclick="toggleProductos(${pedidoId})" title="Cerrar detalles">
                                <span class="cerrar-icono">✕</span>
                            </button>
                        </div>

                        <div class="productos-grid">
                `;

                data.productos.forEach((producto, index) => {
                    const subtotal = producto.cantidad * producto.precio;
                    const animationDelay = index * 0.1;

                    html += `
                        <div class="producto-card" style="animation-delay: ${animationDelay}s">
                            <div class="producto-card-header">
                                <div class="producto-numero">#${index + 1}</div>
                                <div class="producto-badge">
                                    <span class="badge-cantidad">${producto.cantidad}x</span>
                                </div>
                            </div>

                            <div class="producto-card-body">
                                <h4 class="producto-nombre-moderno">${producto.nombre}</h4>

                                <div class="producto-precios">
                                    <div class="precio-unitario">
                                        <span class="precio-label">Precio unitario</span>
                                        <span class="precio-valor">$${Number(producto.precio).toLocaleString()}</span>
                                    </div>
                                    <div class="precio-total">
                                        <span class="precio-label">Subtotal</span>
                                        <span class="precio-valor precio-destacado">$${subtotal.toLocaleString()}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="producto-card-footer">
                                <div class="producto-indicador">
                                    <div class="indicador-barra" style="width: ${(subtotal / total) * 100}%"></div>
                                </div>
                                <div class="producto-porcentaje">
                                    ${((subtotal / total) * 100).toFixed(1)}% del total
                                </div>
                            </div>
                        </div>
                    `;
                });

                html += `
                        </div>

                        <div class="carrito-resumen-moderno">
                            <div class="resumen-gradient">
                                <div class="resumen-contenido">
                                    <div class="resumen-items">
                                        <div class="resumen-item">
                                            <span class="resumen-icono">📦</span>
                                            <span class="resumen-texto">Total productos: ${data.productos.length}</span>
                                        </div>
                                        <div class="resumen-item">
                                            <span class="resumen-icono">🔢</span>
                                            <span class="resumen-texto">Total unidades: ${cantidadTotal}</span>
                                        </div>
                                    </div>

                                    <div class="resumen-total">
                                        <div class="total-label">Total del Pedido</div>
                                        <div class="total-valor">$${total.toLocaleString()}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                productosContainer.innerHTML = html;
            } else {
                productosContainer.innerHTML = `
                    <div class="productos-error-moderno">
                        <div class="error-icono">😞</div>
                        <div class="error-mensaje">
                            <h4>No se pudieron cargar los productos</h4>
                            <p>${data.message || 'Error desconocido'}</p>
                        </div>
                        <button class="btn-cerrar-error" onclick="toggleProductos(${pedidoId})">
                            Cerrar
                        </button>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error cargando productos:', error);
            productosContainer.innerHTML = `
                <div class="productos-error-moderno">
                    <div class="error-icono">🚫</div>
                    <div class="error-mensaje">
                        <h4>Error de conexión</h4>
                        <p>No se pudo conectar con el servidor</p>
                    </div>
                    <button class="btn-cerrar-error" onclick="toggleProductos(${pedidoId})">
                        Cerrar
                    </button>
                </div>
            `;
        });
}

function subirGuia(pedidoId) {
    const form = document.getElementById('formSubirGuia');
    const formData = new FormData(form);
    const statusDiv = document.getElementById('statusGuia');

    // Mostrar estado de carga
    statusDiv.style.display = 'block';
    statusDiv.style.background = 'var(--apple-blue-light)';
    statusDiv.style.color = 'var(--apple-blue)';
    statusDiv.innerHTML = '⏳ Subiendo guía...';

    fetch('subir_guia.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            statusDiv.style.background = 'var(--apple-green-light)';
            statusDiv.style.color = 'var(--apple-green)';
            statusDiv.innerHTML = '✅ ' + data.message;

            setTimeout(() => {
                document.querySelector(`[data-pedido-id="${pedidoId}"]`)?.remove();
                location.reload();
            }, 2000);
        } else {
            statusDiv.style.background = 'var(--apple-red-light)';
            statusDiv.style.color = 'var(--apple-red)';
            statusDiv.innerHTML = '❌ Error: ' + (data.error || data.message || 'Error desconocido');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        statusDiv.style.background = 'var(--apple-red-light)';
        statusDiv.style.color = 'var(--apple-red)';
        statusDiv.innerHTML = '❌ Error de conexión';
    });
}

// Función para mostrar notificaciones
function mostrarNotificacion(mensaje, tipo = 'info') {
    // Crear elemento de notificación
    const notificacion = document.createElement('div');
    notificacion.className = `notificacion notificacion-${tipo}`;
    notificacion.textContent = mensaje;

    // Estilos inline para la notificación
    notificacion.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        border-radius: 8px;
        color: white;
        font-weight: 500;
        z-index: 10000;
        max-width: 400px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        transform: translateX(100%);
        transition: transform 0.3s ease;
        ${tipo === 'success' ? 'background: #34C759;' : ''}
        ${tipo === 'error' ? 'background: #FF3B30;' : ''}
        ${tipo === 'info' ? 'background: #007AFF;' : ''}
    `;

    document.body.appendChild(notificacion);

    // Animar entrada
    setTimeout(() => {
        notificacion.style.transform = 'translateX(0)';
    }, 100);

    // Remover después de 4 segundos
    setTimeout(() => {
        notificacion.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notificacion.parentNode) {
                notificacion.parentNode.removeChild(notificacion);
            }
        }, 300);
    }, 4000);
}

</script>

</body>
</html>
