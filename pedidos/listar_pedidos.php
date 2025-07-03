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
$result = $conn->query("SELECT SQL_CALC_FOUND_ROWS id, nombre, telefono, ciudad, barrio, correo, monto, estado, fecha, direccion, metodo_pago, datos_pago, comprobante, guia, nota_interna, enviado, archivado, anulado, tiene_guia, tiene_comprobante, pagado FROM pedidos_detal WHERE $where ORDER BY fecha DESC LIMIT $limite OFFSET $offset");

$pedidos = [];
while ($row = $result->fetch_assoc()) {
    $pedidos[] = $row;
}

$total_result = $conn->query("SELECT FOUND_ROWS() as total");
$total_pedidos = $total_result->fetch_assoc()['total'];
$total_paginas = ceil($total_pedidos / $limite);

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
    <style>
    /* Variables CSS para tema VSCode Dark con Apple - MEJORADO */
    :root {
      /* Fuentes Apple System con mejores fallbacks */
      --font-system: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      --font-mono: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;

      /* Colores VSCode Dark Theme Refinados */
      --vscode-bg: #0d1117;
      --vscode-bg-secondary: #010409;
      --vscode-sidebar: #161b22;
      --vscode-sidebar-hover: #1c2128;
      --vscode-text: #e6edf3;
      --vscode-text-light: #f0f6fc;
      --vscode-text-muted: #8b949e;
      --vscode-text-subtle: #656d76;
      --vscode-border: #30363d;
      --vscode-border-subtle: #21262d;

      /* Colores Apple Accent */
      --apple-blue: #007AFF;
      --apple-blue-hover: #0056CC;
      --apple-blue-light: rgba(0, 122, 255, 0.1);
      --apple-green: #34C759;
      --apple-orange: #FF9500;
      --apple-red: #FF3B30;
      --apple-purple: #AF52DE;
      --apple-teal: #5AC8FA;

      /* Grises Refinados */
      --gray-50: #fafbfc;
      --gray-100: #f6f8fa;
      --gray-200: #eaeef2;
      --gray-300: #d0d7de;
      --gray-400: #8c959f;
      --gray-500: #656d76;
      --gray-600: #424a53;
      --gray-700: #32383f;
      --gray-800: #24292f;
      --gray-900: #1c2128;

      /* Espaciado con escala perfecta (1.25) */
      --space-3xs: 2px;
      --space-2xs: 4px;
      --space-xs: 6px;
      --space-sm: 8px;
      --space-md: 12px;
      --space-lg: 16px;
      --space-xl: 20px;
      --space-2xl: 24px;
      --space-3xl: 32px;
      --space-4xl: 40px;
      --space-5xl: 48px;

      /* Bordes con golden ratio */
      --radius-xs: 3px;
      --radius-sm: 4px;
      --radius-md: 6px;
      --radius-lg: 8px;
      --radius-xl: 12px;
      --radius-2xl: 16px;
      --radius-full: 9999px;

      /* Sombras Apple-style */
      --shadow-xs: 0 1px 2px rgba(0, 0, 0, 0.04);
      --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.12), 0 1px 2px rgba(0, 0, 0, 0.08);
      --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.07), 0 2px 4px rgba(0, 0, 0, 0.06);
      --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1), 0 4px 6px rgba(0, 0, 0, 0.05);
      --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.15), 0 10px 10px rgba(0, 0, 0, 0.04);
      --shadow-glow: 0 0 0 1px rgba(0, 122, 255, 0.3), 0 0 20px rgba(0, 122, 255, 0.2);

      /* Transiciones naturales */
      --transition-fast: 0.15s cubic-bezier(0.4, 0, 0.2, 1);
      --transition-base: 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      --transition-slow: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      --transition-bounce: 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);

      /* Z-index scale */
      --z-dropdown: 1000;
      --z-sticky: 1020;
      --z-fixed: 1030;
      --z-modal: 1040;
      --z-popover: 1050;
      --z-tooltip: 1060;
    }

    /* Reset y base mejorado */
    *, *::before, *::after {
      box-sizing: border-box;
    }

    /* Estilo Apple oscuro premium con mejores transiciones */
    body {
      font-family: var(--font-system);
      background: var(--vscode-bg);
      color: var(--vscode-text);
      margin: 0;
      padding: 0;
      line-height: 1.6;
      font-feature-settings: 'kern', 'liga', 'clig', 'calt';
      text-rendering: optimizeLegibility;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
      overflow-x: hidden;
    }

    /* Sticky header con glass morphism */
    .sticky-bar {
      position: sticky;
      top: 0;
      z-index: var(--z-sticky);
      background: rgba(22, 27, 34, 0.85);
      backdrop-filter: saturate(180%) blur(20px);
      border-bottom: 1px solid var(--vscode-border);
      padding: var(--space-2xl) 0 var(--space-lg) 0;
      text-align: center;
      box-shadow: var(--shadow-sm);
      transition: all var(--transition-base);
    }

    .sticky-bar::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 1px;
      background: linear-gradient(90deg, transparent, var(--vscode-border), transparent);
      opacity: 0.5;
    }

    h1 {
      font-size: clamp(1.25rem, 4vw, 1.75rem);
      font-weight: 700;
      margin-bottom: var(--space-lg);
      color: var(--vscode-text-light);
      letter-spacing: -0.02em;
      /* Eliminado gradiente azul - solo texto plano gris */
    }

    /* Contenedor principal con mejor espaciado */
    .panel-container {
      max-width: 1400px;
      margin: 0 auto;
      padding: var(--space-3xl) var(--space-2xl);
      min-height: 100vh;    }



    /* Botones premium con micro-interacciones */
    .btn-neon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, var(--apple-blue), var(--apple-blue-hover));
      color: white;
      border: none;
      padding: var(--space-sm) var(--space-xl);
      border-radius: var(--radius-lg);
      font-weight: 600;
      cursor: pointer;
      transition: all var(--transition-base);
      text-decoration: none;
      font-size: 0.875rem;
      letter-spacing: 0.01em;
      position: relative;
      overflow: hidden;
      box-shadow: var(--shadow-sm);
    }

    .btn-neon::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: left var(--transition-slow);
    }

    .btn-neon:hover {
      background: linear-gradient(135deg, var(--apple-blue-hover), #0043A6);
      transform: translateY(-1px) scale(1.02);
      box-shadow: var(--shadow-glow);
    }

    .btn-neon:hover::before {
      left: 100%;
    }

    .btn-neon:active {
      transform: translateY(0) scale(0.98);
    }

    .btn-glass {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: rgba(255, 255, 255, 0.05);
      backdrop-filter: blur(10px);
      color: var(--vscode-text);
      border: 1px solid var(--vscode-border);
      padding: var(--space-sm) var(--space-xl);
      border-radius: var(--radius-lg);
      font-weight: 600;
      cursor: pointer;
      transition: all var(--transition-base);
      margin-left: var(--space-sm);
      font-size: 0.875rem;
      position: relative;
      overflow: hidden;
    }

    .btn-glass::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), transparent);
      opacity: 0;
      transition: opacity var(--transition-base);
    }

    .btn-glass:hover {
      background: rgba(255, 255, 255, 0.1);
      border-color: var(--vscode-border);
      color: var(--vscode-text-light);
      transform: translateY(-1px);
      box-shadow: var(--shadow-md);
    }

    .btn-glass:hover::before {
      opacity: 1;
    }

    .whatsapp-icon {
      width: 17px;
      height: 17px;
      margin-left: 6px;
      vertical-align: middle;
      position: relative;
      top: 2px;
      transition: filter 0.12s;
      filter: grayscale(0.18) brightness(1.02);
    }
    .tabla-pedidos a.whatsapp-link:hover .whatsapp-icon {
      filter: drop-shadow(0 0 2px #25D366) brightness(1.2);
    }
    /* Paginación elegante */
    .paginacion {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: var(--space-xs);
      margin-top: var(--space-4xl);
      padding: var(--space-2xl) 0;
    }

    .paginacion a {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 40px;
      height: 40px;
      border-radius: var(--radius-lg);
      background: rgba(255, 255, 255, 0.05);
      backdrop-filter: blur(10px);
      color: var(--vscode-text);
      text-decoration: none;
      font-weight: 600;
      font-size: 0.875rem;
      transition: all var(--transition-base);
      border: 1px solid transparent;
      position: relative;
      overflow: hidden;
    }

    .paginacion a::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: var(--apple-blue);
      opacity: 0;
      transition: opacity var(--transition-base);
    }

    .paginacion a:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
      border-color: var(--vscode-border);
      color: var(--vscode-text-light);
    }

    .paginacion a.active {
      background: var(--apple-blue);
      color: white;
      box-shadow: 0 2px 8px rgba(0, 122, 255, 0.3);
    }

    .paginacion a.active::before {
      opacity: 1;
    }

    /* Pills de estado con gradientes */
    .estado-pill {
      display: inline-flex;
      align-items: center;
      padding: var(--space-xs) var(--space-md);
      border-radius: var(--radius-full);
      font-size: 0.8125rem;
      font-weight: 600;
      text-transform: capitalize;
      background: var(--gray-800);
      border: 1px solid var(--vscode-border);
      position: relative;
      overflow: hidden;
      margin: var(--space-3xs);
    }

    .estado-pill::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), transparent);
      opacity: 0;
      transition: opacity var(--transition-base);
    }

    .estado-pill:hover::before {
      opacity: 1;
    }

    .estado-pill.sin_enviar {
      background: var(--gray-700);
      color: var(--vscode-text-muted);
      border: 1px solid var(--vscode-border);
    }

    .estado-pill.enviado {
      background: var(--gray-600);
      color: var(--vscode-text-light);
      border: 1px solid var(--vscode-border);
    }

    .estado-pill.anulado {
      background: var(--gray-800);
      color: var(--vscode-text-subtle);
      border: 1px solid var(--vscode-border);
      text-decoration: line-through;
      opacity: 0.7;
    }

    .estado-pill.archivado {
      background: var(--gray-700);
      color: var(--vscode-text-subtle);
      border: 1px solid var(--vscode-border);
      opacity: 0.8;
    }

    .estado-pill.guia {
      background: var(--gray-600);
      color: var(--vscode-text-light);
      font-size: 0.75rem;
      border: 1px solid var(--vscode-border);
    }

    .estado-pill.comprobante {
      background: var(--gray-600);
      color: var(--vscode-text-light);
      font-size: 0.75rem;
      border: 1px solid var(--vscode-border);
    }

    .estado-pill.pago-pendiente {
      background: var(--gray-700);
      color: var(--vscode-text-muted);
      font-weight: 600;
      border: 1px solid var(--vscode-border);
      opacity: 0.9;
    }

    .estado-pill.pago-confirmado {
      background: var(--gray-500);
      color: var(--vscode-text-light);
      font-weight: 600;
      border: 1px solid var(--vscode-border);
    }



    /* Modal premium con glass morphism */
    .modal-detalle-bg {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.8);
      backdrop-filter: saturate(180%) blur(20px);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: var(--z-modal);
      opacity: 0;
      animation: fadeIn var(--transition-base) forwards;
    }

    @keyframes fadeIn {
      to { opacity: 1; }
    }

    .modal-detalle {
      background: rgba(22, 27, 34, 0.95);
      backdrop-filter: saturate(180%) blur(20px);
      border: 1px solid var(--vscode-border);
      border-radius: var(--radius-2xl);
      box-shadow: var(--shadow-xl);
      max-width: 90vw;
      max-height: 90vh;
      overflow: auto;
      padding: var(--space-3xl);
      position: relative;
      transform: scale(0.9);
      animation: modalSlideIn var(--transition-slow) forwards;
    }

    @keyframes modalSlideIn {
      to {
        transform: scale(1);
      }
    }

    .cerrar-modal {
      position: absolute;
      top: var(--space-lg);
      right: var(--space-lg);
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border: 1px solid var(--vscode-border);
      border-radius: var(--radius-full);
      width: 32px;
      height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--vscode-text);
      font-size: 1.25rem;
      cursor: pointer;
      transition: all var(--transition-base);
    }

    .cerrar-modal:hover {
      background: var(--apple-red);
      color: white;
      transform: scale(1.1);
    }

    /* Inputs premium */
    .search-inp, select {
      padding: var(--space-md) var(--space-lg);
      border: 1px solid var(--vscode-border);
      background: rgba(255, 255, 255, 0.05);
      backdrop-filter: blur(10px);
      color: var(--vscode-text);
      border-radius: var(--radius-lg);
      font-family: var(--font-system);
      font-size: 0.9375rem;
      transition: all var(--transition-base);
      outline: none;
      position: relative;
    }

    /* Inputs de fecha más compactos */
    input[type="date"] {
      padding: var(--space-sm) var(--space-md);
      border: 1px solid var(--vscode-border);
      background: rgba(255, 255, 255, 0.05);
      backdrop-filter: blur(10px);
      color: var(--vscode-text);
      border-radius: var(--radius-lg);
      font-family: var(--font-system);
      font-size: 0.875rem;
      transition: all var(--transition-base);
      outline: none;
      min-width: 0; /* Permite que se encoja */
    }

    .search-inp:focus, select:focus, input[type="date"]:focus {
      border-color: var(--vscode-border);
      box-shadow: var(--shadow-md);
      background: rgba(255, 255, 255, 0.08);
    }

    .search-inp::placeholder {
      color: var(--vscode-text-subtle);
      font-style: italic;
    }

    select {
      min-width: 140px;
      cursor: pointer;
      appearance: none;
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
      background-position: right var(--space-md) center;
      background-repeat: no-repeat;
      background-size: 16px;
      padding-right: var(--space-4xl);
    }

    /* ===== ESTILOS PARA FUNCIONALIDADES NUEVAS ===== */

    /* Panel de filtros con glass morphism */
    .filtros-avanzados {
      background: rgba(22, 27, 34, 0.8);
      backdrop-filter: saturate(180%) blur(20px);
      padding: var(--space-3xl);
      border-radius: var(--radius-2xl);
      margin-bottom: var(--space-3xl);
      border: 1px solid var(--vscode-border);
      position: relative;
      overflow: hidden;
    }

    .filtros-avanzados::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 2px;
      background: var(--vscode-border);
      opacity: 0.6;
    }

    .filtros-avanzados::after {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: radial-gradient(circle at 50% 0%, rgba(255, 255, 255, 0.03), transparent 50%);
      pointer-events: none;
    }

    .filtro-grupo {
      display: flex;
      flex-direction: column;
      gap: var(--space-sm);
      min-width: 160px;
      position: relative;
      z-index: 1;
    }

    /* Campos de fecha más compactos */
    .filtro-grupo.fecha-compacta {
      min-width: 96px; /* 40% reducción de 160px */
      flex-shrink: 0;
    }

    /* Contenedor de fechas personalizadas */
    #fechasPersonalizadas {
      display: flex;
      flex-wrap: wrap;
      transition: all var(--transition-base);
    }

    #fechasPersonalizadas.show {
      display: flex !important;
      animation: slideInFade var(--transition-base) ease-out;
    }

    @keyframes slideInFade {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .filtro-grupo label {
      font-size: 0.8125rem;
      font-weight: 600;
      color: var(--vscode-text-muted);
      letter-spacing: 0.02em;
      text-transform: uppercase;
    }

    .herramientas-panel {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: var(--space-lg);
      gap: var(--space-md);
      flex-wrap: wrap;
    }

    .acciones-masivas, .herramientas-export {
      display: flex;
      gap: var(--space-sm);
      flex-wrap: wrap;
    }

    .btn-herramienta, .btn-export {
      padding: var(--space-sm) var(--space-md);
      background: var(--gray-dark);
      color: var(--vscode-text);
      border: 1px solid var(--vscode-border);
      border-radius: var(--radius-sm);
      font-size: 0.85rem;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      transition: all 0.2s;
    }

    .btn-herramienta:hover, .btn-export:hover {
      background: var(--gray-600);
      color: var(--vscode-text-light);
      transform: translateY(-1px);
    }

    /* Dropdown de acciones masivas */
    .dropdown-masivo {
      position: relative;
      display: inline-block;
    }

    .dropdown-toggle {
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .dropdown-arrow {
      font-size: 0.7rem;
      transition: transform 0.2s;
    }

    .dropdown-toggle.active .dropdown-arrow {
      transform: rotate(180deg);
    }

    .dropdown-menu-masivo {
      position: absolute;
      top: 100%;
      left: 0;
      background: var(--gray-dark);
      border: 1px solid var(--vscode-border);
      border-radius: var(--radius-sm);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
      z-index: 1000;
      min-width: 200px;
      margin-top: 5px;
    }

    .dropdown-item {
      display: block;
      width: 100%;
      padding: var(--space-sm) var(--space-md);
      background: transparent;
      color: var(--vscode-text);
      border: none;
      text-align: left;
      cursor: pointer;
      font-size: 0.85rem;
      transition: background-color 0.2s;
    }

    .dropdown-item:hover {
      background: var(--gray-600);
      color: var(--vscode-text-light);
    }

    .dropdown-item:first-child {
      border-radius: var(--radius-sm) var(--radius-sm) 0 0;
    }

    .dropdown-item:last-child {
      border-radius: 0 0 var(--radius-sm) var(--radius-sm);
    }



    /* Dashboard de estadísticas premium */
    .stats-dashboard {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: var(--space-2xl);
      margin-bottom: var(--space-4xl);
    }

    .stat-card {
      background: rgba(22, 27, 34, 0.8);
      backdrop-filter: saturate(180%) blur(20px);
      padding: var(--space-3xl);
      border-radius: var(--radius-2xl);
      border: 1px solid var(--vscode-border);
      text-align: center;
      transition: all var(--transition-slow);
      position: relative;
      overflow: hidden;
      cursor: pointer;
    }

    .stat-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(135deg,
        rgba(255, 255, 255, 0.03),
        rgba(255, 255, 255, 0.05),
        rgba(255, 255, 255, 0.02),
        rgba(255, 255, 255, 0.04));
      opacity: 0;
      transition: opacity var(--transition-base);
    }

    .stat-card:hover {
      transform: translateY(-8px) scale(1.02);
      box-shadow: var(--shadow-xl);
      border-color: var(--vscode-border);
    }

    .stat-card:hover::before {
      opacity: 1;
    }

    .stat-number {
      font-size: clamp(2rem, 5vw, 2.5rem);
      font-weight: 800;
      color: var(--vscode-text-light);
      margin-bottom: var(--space-sm);
      font-feature-settings: 'tnum';
      letter-spacing: -0.02em;
      position: relative;
      z-index: 1;
    }

    .stat-label {
      font-size: 0.9375rem;
      color: var(--vscode-text-muted);
      font-weight: 500;
      letter-spacing: 0.01em;
      position: relative;
      z-index: 1;
    }

    /* Estilos mejorados para la tabla */
    .pedido-numero {
      display: flex;
      align-items: center;
      gap: var(--space-sm);
    }



    /* ===== ESTILOS DE CONTENIDO DE CELDAS ===== */

    /* Información del pedido */
    .pedido-numero {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: var(--space-xs);
      flex-wrap: wrap;
    }

    .pedido-numero a {
      display: inline-block;
      min-width: 0;
    }

    /* Información del cliente */
    .cliente-info {
      display: flex;
      flex-direction: column;
      gap: 2px;
      min-width: 0;
      overflow: hidden;
    }

    .cliente-nombre {
      font-weight: 600;
      color: var(--vscode-text-light);
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .cliente-email {
      font-size: 0.85rem;
      color: var(--vscode-text-muted);
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    /* Información de monto */
    .monto-info {
      text-align: center;
    }

    .monto-valor {
      font-weight: 600;
      color: var(--vscode-text-light);
      font-size: 1rem;
    }

    /* Método de pago */
    .metodo-pago {
      display: flex;
      align-items: center;
      gap: var(--space-xs);
      min-width: 0;
      overflow: hidden;
    }

    .metodo-nombre {
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      flex: 1;
    }

    .tiene-comprobante {
      color: var(--vscode-text-light);
      flex-shrink: 0;
    }

    /* Estados */
    .estado-container {
      display: flex;
      flex-wrap: wrap;
      gap: var(--space-3xs);
      align-items: center;
      min-width: 0;
    }

    /* Información de fecha */
    .fecha-info {
      text-align: center;
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .fecha-principal {
      font-weight: 600;
      color: var(--vscode-text-light);
      font-size: 0.9rem;
    }

    .fecha-hora {
      font-size: 0.8rem;
      color: var(--vscode-text-muted);
    }

    /* Contenedor de acciones */
    .acciones-container {
      display: flex;
      align-items: center;
      justify-content: center;
      min-width: 0;
    }

    .btn-accion {
      background: var(--gray-dark);
      border: 1px solid var(--vscode-border);
      color: var(--vscode-text);
      padding: 6px 8px;
      border-radius: var(--radius-sm);
      cursor: pointer;
      font-size: 0.9rem;
      transition: all 0.2s;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin: 2px; /* Espaciado entre botones */
    }

    .btn-accion:hover {
      transform: translateY(-1px);
      box-shadow: var(--shadow-light);
    }

    .btn-accion.pago:hover {
      background: #238636;
      color: white;
    }

    .btn-accion.pago-confirmado {
      background: #238636;
      color: white;
    }

    .btn-accion.comprobante:hover {
      background: #fb8500;
      color: white;
    }

    .btn-accion.comprobante-ok {
      background: #fb8500;
      color: white;
    }

    .btn-accion.envio:hover {
      background: var(--apple-blue);
      color: white;
    }

    .btn-accion.guia-ok {
      background: var(--apple-blue);
      color: white;
    }

    /* Dropdown de acciones */
    .dropdown-acciones {
      position: relative;
    }

    .dropdown-menu {
      position: absolute;
      top: 100%;
      right: 0;
      background: var(--vscode-sidebar);
      border: 1px solid var(--vscode-border);
      border-radius: var(--radius-md);
      box-shadow: var(--shadow-medium);
      z-index: 1000;
      min-width: 160px;
      display: none;
      padding: var(--space-sm);
    }

    .dropdown-menu button {
      display: block;
      width: 100%;
      padding: var(--space-sm);
      background: transparent;
      border: none;
      color: var(--vscode-text);
      text-align: left;
      cursor: pointer;
      border-radius: var(--radius-sm);
      font-size: 0.9rem;
      transition: all 0.2s;
    }

    .dropdown-menu button:hover {
      background: var(--gray-light);
    }



    /* Estados simplificados - Principalmente grises */
    .estado-pill.pago-confirmado {
      background: var(--gray-500);
      color: var(--vscode-text-light);
      border: 1px solid var(--vscode-border);
    }

    .estado-pill.pago-pendiente {
      background: var(--gray-700);
      color: var(--vscode-text-muted);
      border: 1px solid var(--vscode-border);
    }

    .estado-pill.enviado {
      background: var(--gray-600);
      color: var(--vscode-text-light);
      border: 1px solid var(--vscode-border);
    }

    .estado-pill.guia {
      background: var(--gray-600);
      color: var(--vscode-text-light);
      border: 1px solid var(--vscode-border);
    }

    .estado-pill.comprobante {
      background: var(--gray-600);
      color: var(--vscode-text-light);
      border: 1px solid var(--vscode-border);
    }

    /* Filas de tabla con hover mejorado */
    .pedido-row {
      transition: all var(--transition-base);
      position: relative;
    }

    .pedido-row::before {
      content: '';
      position: absolute;
      left: -5px; /* Completamente fuera del contenido */
      top: 0;
      bottom: 0;
      width: 3px;
      background: var(--vscode-border);
      opacity: 0;
      transition: opacity var(--transition-base);
      z-index: 1; /* Asegurar que esté por encima */
      pointer-events: none; /* No interferir con clicks */
    }

    .pedido-row:hover {
      background: rgba(255, 255, 255, 0.05) !important;
      transform: translateX(4px);
    }

    .pedido-row:hover::before {
      opacity: 1;
    }

    .pedido-row.selected {
      background: rgba(255, 255, 255, 0.08) !important;
      border-left: 3px solid var(--vscode-border);
      box-shadow: inset 3px 0 0 var(--vscode-border);
    }

    /* Responsive mejorado */
    @media (max-width: 1200px) {
      .stats-dashboard {
        grid-template-columns: repeat(2, 1fr);
      }

      .panel-container {
        padding: var(--space-2xl) var(--space-lg);
      }
    }

    @media (max-width: 768px) {
      .filtros-avanzados form {
        flex-direction: column;
      }

      .filtro-grupo {
        min-width: 100%;
      }

      .filtro-grupo.fecha-compacta {
        min-width: 100%; /* En móvil ocupan todo el ancho */
      }

      .stats-dashboard {
        grid-template-columns: 1fr;
        gap: var(--space-lg);
      }

      .tabla-pedidos {
        font-size: 0.875rem;
      }

      .btn-neon, .btn-glass {
        padding: var(--space-xs) var(--space-md);
        font-size: 0.8125rem;
      }

      .sticky-bar {
        padding: var(--space-lg) 0;
      }

      /* Dropdown acciones móvil */
      .btn-accion-principal {
        padding: 6px 12px;
        font-size: 0.8rem;
      }

      .dropdown-menu-fila {
        min-width: 200px;
        right: -10px; /* Ajuste para móvil */
      }

      .dropdown-item-fila {
        padding: 10px 14px;
        font-size: 0.85rem;
      }

      h1 {
        font-size: 1.25rem;
      }
    }

    /* Animaciones y transiciones avanzadas */
    @keyframes slideInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes fadeInScale {
      from {
        opacity: 0;
        transform: scale(0.95);
      }
      to {
        opacity: 1;
        transform: scale(1);
      }
    }

    .panel-container > * {
      animation: slideInUp var(--transition-slow) ease-out;
    }

    .stat-card {
      animation: fadeInScale var(--transition-slow) ease-out;
    }

    /* Scrollbar personalizado */
    ::-webkit-scrollbar {
      width: 8px;
      height: 8px;
    }

    ::-webkit-scrollbar-track {
      background: var(--vscode-bg);
    }

    ::-webkit-scrollbar-thumb {
      background: var(--gray-600);
      border-radius: var(--radius-full);
    }

    ::-webkit-scrollbar-thumb:hover {
      background: var(--vscode-text-muted);
    }

    /* RESPONSIVE DE TABLA */
    @media (max-width: 1200px) {
      .tabla-pedidos {
        overflow-x: auto;
        white-space: nowrap;
      }

      .tabla-pedidos th,
      .tabla-pedidos td {
        min-width: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
    }

    @media (max-width: 768px) {
      .tabla-pedidos,
      .tabla-pedidos thead,
      .tabla-pedidos tbody,
      .tabla-pedidos th,
      .tabla-pedidos td,
      .tabla-pedidos tr {
        display: block;
      }

      .tabla-pedidos thead tr {
        position: absolute;
        top: -9999px;
        left: -9999px;
      }

      .tabla-pedidos tr {
        border: 1px solid var(--vscode-border);
        margin-bottom: var(--space-md);
        border-radius: var(--radius-lg);
        background: var(--vscode-sidebar);
        padding: var(--space-md);
      }

      .tabla-pedidos td {
        border: none;
        padding: var(--space-sm) 0;
        position: relative;
        padding-left: 50%;
        text-align: right;
        width: 100%;
      }

      .tabla-pedidos td:before {
        content: attr(data-label) ": ";
        position: absolute;
        left: 6px;
        width: 45%;
        padding-right: 10px;
        white-space: nowrap;
        color: var(--vscode-text-muted);
        font-weight: 600;
        text-align: left;
      }
    }

    /* ============================================ */
    /* ESTILOS PARA NUEVA TABLA RECONSTRUIDA       */
    /* ============================================ */

    .nueva-tabla-container {
      width: 100%;
      background: var(--vscode-sidebar);
      border-radius: 12px;
      overflow-x: auto; /* Solo scroll horizontal */
      overflow-y: visible; /* Permitir overflow vertical para dropdowns */
      margin: 20px 0;
      border: 1px solid var(--vscode-border);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    .nueva-tabla-pedidos {
      width: 100%;
      table-layout: fixed;
      border-collapse: collapse;
      font-family: var(--font-system);
    }

    .nueva-tabla-pedidos thead {
      background: var(--vscode-bg-secondary);
      border-bottom: 2px solid var(--vscode-border);
    }

    .nueva-tabla-pedidos th {
      padding: 16px 12px;
      font-weight: 600;
      font-size: 14px;
      text-align: left;
      color: var(--vscode-text-light);
      border-right: 1px solid var(--vscode-border-subtle);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .nueva-tabla-pedidos th:last-child {
      border-right: none;
    }

    /* Anchos específicos para cada columna */
    .col-id { width: 80px; }
    .col-cliente { width: 200px; }
    .col-monto { width: 100px; }
    .col-pagado { width: 90px; }
    .col-enviado { width: 90px; }
    .col-comprobante { width: 110px; }
    .col-guia { width: 90px; }
    .col-archivado { width: 100px; }
    .col-anulado { width: 90px; }
    .col-acciones { width: 120px; }

    .nueva-tabla-pedidos tbody tr {
      border-bottom: 1px solid var(--vscode-border-subtle);
      transition: background-color 0.2s ease;
    }

    .nueva-tabla-pedidos tbody tr:hover {
      background: var(--vscode-sidebar-hover);
    }

    .nueva-tabla-pedidos td {
      padding: 14px 12px;
      vertical-align: middle;
      border-right: 1px solid var(--vscode-border-subtle);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    /* Excepción para la columna de acciones - permitir overflow para dropdown */
    .nueva-tabla-pedidos td.col-acciones {
      overflow: visible;
      position: relative;
    }

    .nueva-tabla-pedidos td:last-child {
      border-right: none;
    }

    /* Estilos para ID del pedido */
    .enlace-pedido {
      color: var(--apple-blue);
      text-decoration: none;
      font-weight: 600;
      font-size: 16px;
      transition: color 0.2s ease;
    }

    .enlace-pedido:hover {
      color: var(--apple-blue-hover);
      text-decoration: underline;
    }

    /* Estilos para información del cliente */
    .info-cliente {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .nombre-cliente {
      color: var(--vscode-text-light);
      font-weight: 600;
      font-size: 14px;
    }

    .email-cliente {
      color: var(--vscode-text-muted);
      font-size: 12px;
    }

    /* Estilos para monto */
    .valor-monto {
      color: var(--apple-green);
      font-weight: 600;
      font-size: 14px;
      font-family: var(--font-mono);
    }

    /* Badges de status */
    .badge-status {
      display: inline-block;
      padding: 4px 8px;
      border-radius: 6px;
      font-size: 11px;
      font-weight: 600;
      text-align: center;
      min-width: 45px;
      transition: all 0.2s ease;
    }

    .status-si {
      background: rgba(52, 199, 89, 0.15);
      color: var(--apple-green);
      border: 1px solid rgba(52, 199, 89, 0.3);
    }

    .status-no {
      background: rgba(255, 149, 0, 0.15);
      color: var(--apple-orange);
      border: 1px solid rgba(255, 149, 0, 0.3);
    }

    .status-archivado {
      background: rgba(139, 148, 158, 0.15);
      color: var(--vscode-text-muted);
      border: 1px solid rgba(139, 148, 158, 0.3);
    }

    .status-anulado {
      background: rgba(255, 59, 48, 0.15);
      color: var(--apple-red);
      border: 1px solid rgba(255, 59, 48, 0.3);
    }

    .status-activo {
      background: rgba(52, 199, 89, 0.15);
      color: var(--apple-green);
      border: 1px solid rgba(52, 199, 89, 0.3);
    }

    /* Dropdown de acciones */
    .dropdown-acciones {
      position: relative;
      display: inline-block;
      z-index: 1000; /* Z-index base alto para todos los dropdowns */
    }

    .dropdown-acciones.activo {
      z-index: 2147483647 !important; /* Z-index máximo para el dropdown activo */
    }

    .btn-acciones-nueva {
      background: var(--apple-blue);
      color: white;
      border: none;
      padding: 8px 12px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 4px;
      transition: background 0.2s ease;
      width: 100%;
      justify-content: center;
      position: relative; /* Asegurar contexto para el dropdown */
    }

    .btn-acciones-nueva:hover {
      background: var(--apple-blue-hover);
    }

    .flecha-dropdown {
      font-size: 10px;
      transition: transform 0.2s ease;
    }

    .btn-acciones-nueva.activo .flecha-dropdown {
      transform: rotate(180deg);
    }

    .menu-acciones-nueva {
      position: absolute;
      top: 100%;
      right: 0;
      background: var(--vscode-sidebar);
      border: 1px solid var(--vscode-border);
      border-radius: 8px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
      z-index: 2147483647 !important; /* Máximo z-index posible en CSS */
      min-width: 200px;
      padding: 8px 0;
      display: none;
      animation: slideDown 0.2s ease;
      /* Asegurar que se muestre sobre cualquier elemento */
      transform: translateZ(0); /* Crear nuevo contexto de apilamiento */
      will-change: transform; /* Optimización para animaciones */
      /* Asegurar que no se salga de la pantalla */
      max-width: 250px;
      white-space: nowrap;
      /* Forzar contexto de apilamiento */
      isolation: isolate;
    }

    /* Ajuste para menús cerca del borde derecho */
    @media (max-width: 768px) {
      .menu-acciones-nueva {
        right: auto;
        left: 0;
        transform: translateX(-50%);
        z-index: 2147483647 !important; /* Forzar máximo z-index en móviles */
      }
    }

    .menu-acciones-nueva.mostrar {
      display: block;
      z-index: 2147483646 !important; /* Z-index muy alto para menús mostrados */
    }

    .menu-acciones-nueva.activo {
      z-index: 2147483647 !important; /* Z-index máximo para el menú activo actual */
    }

    .item-accion {
      display: block;
      width: 100%;
      padding: 10px 16px;
      background: none;
      border: none;
      color: var(--vscode-text);
      font-size: 13px;
      text-align: left;
      cursor: pointer;
      transition: background 0.2s ease;
      text-decoration: none;
    }

    .item-accion:hover {
      background: var(--vscode-sidebar-hover);
      color: var(--vscode-text-light);
    }

    .item-accion.peligro {
      color: var(--apple-red);
    }

    .item-accion.peligro:hover {
      background: rgba(255, 59, 48, 0.1);
    }

    .separador-menu {
      height: 1px;
      background: var(--vscode-border-subtle);
      margin: 8px 0;
    }

    /* Mensaje de tabla vacía */
    .tabla-vacia {
      text-align: center;
      padding: 60px 20px;
      background: var(--vscode-bg-secondary);
    }

    .mensaje-vacio {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 12px;
    }

    .icono-vacio {
      font-size: 48px;
      opacity: 0.6;
    }

    .titulo-vacio {
      font-size: 18px;
      font-weight: 600;
      color: var(--vscode-text-light);
    }

    .subtitulo-vacio {
      font-size: 14px;
      color: var(--vscode-text-muted);
    }

    /* Paginación nueva */
    .paginacion-nueva {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 20px;
      padding: 16px 20px;
      background: var(--vscode-sidebar);
      border-radius: 8px;
      border: 1px solid var(--vscode-border);
    }

    .info-paginacion {
      color: var(--vscode-text-muted);
      font-size: 14px;
    }

    .controles-paginacion {
      display: flex;
      gap: 8px;
    }

    .btn-pagina {
      padding: 8px 12px;
      background: var(--vscode-bg);
      color: var(--vscode-text);
      text-decoration: none;
      border-radius: 6px;
      font-size: 14px;
      font-weight: 500;
      transition: all 0.2s ease;
      border: 1px solid var(--vscode-border);
      min-width: 40px;
      text-align: center;
    }

    .btn-pagina:hover {
      background: var(--vscode-sidebar-hover);
      color: var(--vscode-text-light);
    }

    .btn-pagina.activa {
      background: var(--apple-blue);
      color: white;
      border-color: var(--apple-blue);
    }

    /* Animaciones */
    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Responsividad */
    @media (max-width: 1200px) {
      .nueva-tabla-container {
        overflow-x: auto;
      }

      .nueva-tabla-pedidos {
        min-width: 1000px;
      }
    }

    @media (max-width: 768px) {
      .nueva-tabla-pedidos th,
      .nueva-tabla-pedidos td {
        padding: 10px 8px;
        font-size: 12px;
      }

      .paginacion-nueva {
        flex-direction: column;
        gap: 12px;
        text-align: center;
      }
    }

    </style>
</head>
<body>
<div class="sticky-bar">
    <h1>📦 Gestión de Pedidos Avanzada</h1>

    <!-- Panel de Filtros Expandido -->
    <div class="filtros-avanzados">
        <form method="get" id="formFiltros" style="display:flex;gap:var(--space-md);flex-wrap:wrap;justify-content:center;margin-bottom:var(--space-lg);">

            <!-- Filtros básicos -->
            <div class="filtro-grupo">
                <label>📅 Período:</label>
                <select name="filtro" id="filtroPeriodo" onchange="manejarCambioPeriodo();">
                    <option value="hoy" <?php if($filtro=='hoy') echo "selected";?>>Hoy</option>
                    <option value="semana" <?php if($filtro=='semana') echo "selected";?>>Esta Semana</option>
                    <option value="quincena" <?php if($filtro=='quincena') echo "selected";?>>Últimos 15 días</option>
                    <option value="mes" <?php if($filtro=='mes') echo "selected";?>>Este Mes</option>
                    <option value="personalizado" <?php if(!empty($fecha_desde) || !empty($fecha_hasta)) echo "selected";?>>📅 Personalizado</option>
                    <option value="todos" <?php if($filtro=='todos') echo "selected";?>>Todos</option>
                </select>
            </div>

            <!-- Filtros de estado -->
            <div class="filtro-grupo">
                <label>💰 Estado Pago:</label>
                <select name="filtro" onchange="aplicarFiltros()">
                    <option value="pago_pendiente" <?php if($filtro=='pago_pendiente') echo "selected";?>>⏳ Pago Pendiente</option>
                    <option value="pago_confirmado" <?php if($filtro=='pago_confirmado') echo "selected";?>>💰 Pago Confirmado</option>
                    <option value="sin_comprobante" <?php if($filtro=='sin_comprobante') echo "selected";?>>📄 Sin Comprobante</option>
                    <option value="con_comprobante" <?php if($filtro=='con_comprobante') echo "selected";?>>📋 Con Comprobante</option>
                    <option value="enviados" <?php if($filtro=='enviados') echo "selected";?>>🚚 Enviados</option>
                    <option value="con_guia" <?php if($filtro=='con_guia') echo "selected";?>>📦 Con Guía</option>
                    <option value="anulados" <?php if($filtro=='anulados') echo "selected";?>>❌ Anulados</option>
                    <option value="archivados" <?php if($filtro=='archivados') echo "selected";?>>📁 Archivados</option>
                </select>
            </div>

            <!-- Método de pago -->
            <div class="filtro-grupo">
                <label>💳 Método Pago:</label>
                <select name="metodo_pago" onchange="aplicarFiltros()">
                    <option value="">Todos los métodos</option>
                    <?php foreach($metodos_pago as $metodo): ?>
                        <option value="<?php echo htmlspecialchars($metodo);?>" <?php if($metodo_pago==$metodo) echo "selected";?>>
                            <?php echo htmlspecialchars($metodo);?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Ciudad -->
            <div class="filtro-grupo">
                <label>🏙️ Ciudad:</label>
                <select name="ciudad" onchange="aplicarFiltros()">
                    <option value="">Todas las ciudades</option>
                    <?php foreach($ciudades as $ciudad_opt): ?>
                        <option value="<?php echo htmlspecialchars($ciudad_opt);?>" <?php if($ciudad==$ciudad_opt) echo "selected";?>>
                            <?php echo htmlspecialchars($ciudad_opt);?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Búsqueda -->
            <div class="filtro-grupo" style="flex-grow: 1; max-width: 300px;">
                <label>🔍 Buscar:</label>
                <input class="search-inp" type="text" name="buscar" value="<?php echo htmlspecialchars($buscar);?>"
                       placeholder="Nombre, teléfono, correo, dirección o #ID"
                       oninput="if(this.value.length==0) aplicarFiltros();" />
            </div>

            <!-- Rango de fechas (oculto por defecto) -->
            <div id="fechasPersonalizadas" style="display: none; gap: var(--space-md);">
                <div class="filtro-grupo fecha-compacta">
                    <label>📅 Desde:</label>
                    <input type="date" name="fecha_desde" value="<?php echo htmlspecialchars($fecha_desde);?>" onchange="aplicarFiltrosPersonalizados()">
                </div>

                <div class="filtro-grupo fecha-compacta">
                    <label>📅 Hasta:</label>
                    <input type="date" name="fecha_hasta" value="<?php echo htmlspecialchars($fecha_hasta);?>" onchange="aplicarFiltrosPersonalizados()">
                </div>
            </div>

            <button type="submit" style="display:none;">Buscar</button>
        </form>

        <!-- Acciones masivas y herramientas -->
        <div class="herramientas-panel">
            <div class="acciones-masivas">
                <button onclick="seleccionarTodos()" class="btn-herramienta">✅ Seleccionar Todos</button>
                <button onclick="desseleccionarTodos()" class="btn-herramienta">❌ Deseleccionar</button>

                <!-- Dropdown de acciones masivas -->
                <div class="dropdown-masivo">
                    <button onclick="toggleDropdownMasivo()" class="btn-herramienta dropdown-toggle" id="btnAccionesMasivas">
                        ⚡ Acciones <span class="dropdown-arrow">▼</span>
                    </button>
                    <div class="dropdown-menu-masivo" id="dropdownAccionesMasivas" style="display: none;">
                        <button onclick="accionMasiva('confirmar_pago')" class="dropdown-item">💰 Confirmar Pago</button>
                        <button onclick="accionMasiva('marcar_enviado')" class="dropdown-item">🚚 Marcar Enviado</button>
                        <button onclick="accionMasiva('archivar')" class="dropdown-item">📁 Archivar</button>
                        <button onclick="imprimirSeleccionados()" class="dropdown-item">�️ Imprimir Selección</button>
                    </div>
                </div>
            </div>

            <div class="herramientas-export">
                <a href="exportar_excel.php?filtro=<?php echo urlencode($filtro);?>&buscar=<?php echo urlencode($buscar);?>&metodo_pago=<?php echo urlencode($metodo_pago);?>&ciudad=<?php echo urlencode($ciudad);?>&fecha_desde=<?php echo urlencode($fecha_desde);?>&fecha_hasta=<?php echo urlencode($fecha_hasta);?>" class="btn-export">📊 Exportar Excel</a>
            </div>
        </div>
    </div>
</div>
<div class="panel-container">
    <!-- Dashboard de estadísticas rápidas -->
    <div class="stats-dashboard">
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($total_pedidos); ?></div>
            <div class="stat-label">📦 Total Pedidos</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php
                $total_monto = array_sum(array_column($pedidos, 'monto'));
                echo '$' . number_format($total_monto, 0, ',', '.');
            ?></div>
            <div class="stat-label">💰 Valor Total</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php
                $pendientes = count(array_filter($pedidos, function($p) { return $p['pagado'] == '0'; }));
                echo $pendientes;
            ?></div>
            <div class="stat-label">⏳ Pago Pendiente</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php
                $confirmados = count(array_filter($pedidos, function($p) { return $p['pagado'] == '1'; }));
                echo $confirmados;
            ?></div>
            <div class="stat-label">✅ Pagos Confirmados</div>
        </div>
    </div>



        <!-- ================================== -->
        <!-- NUEVA TABLA RECONSTRUIDA          -->
        <!-- ================================== -->
        <div class="nueva-tabla-container">
            <table class="nueva-tabla-pedidos">
                <thead>
                    <tr>
                        <th class="col-id">📦 ID</th>
                        <th class="col-cliente">👤 Cliente</th>
                        <th class="col-monto">💰 Monto</th>
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
                            <td colspan="10" class="tabla-vacia">
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
                                    <div class="dropdown-acciones">
                                        <button class="btn-acciones-nueva" onclick="toggleDropdownNueva(<?php echo $p['id']; ?>)">
                                            ⚡ Acciones <span class="flecha-dropdown">▼</span>
                                        </button>
                                        <div class="menu-acciones-nueva" id="menu-nueva-<?php echo $p['id']; ?>">
                                            <!-- Acciones de pago -->
                                            <?php if($p['pagado'] == '0'): ?>
                                                <button onclick="cambiarEstadoPago(<?php echo $p['id']; ?>, 1)" class="item-accion">
                                                    💰 Confirmar Pago
                                                </button>
                                            <?php else: ?>
                                                <button onclick="cambiarEstadoPago(<?php echo $p['id']; ?>, 0)" class="item-accion">
                                                    💰 Marcar Pendiente
                                                </button>
                                            <?php endif; ?>

                                            <!-- Acciones de comprobante -->
                                            <?php if($p['tiene_comprobante'] == '0'): ?>
                                                <button onclick="subirComprobante(<?php echo $p['id']; ?>)" class="item-accion">
                                                    📄 Subir Comprobante
                                                </button>
                                            <?php else: ?>
                                                <button onclick="verComprobante(<?php echo $p['id']; ?>)" class="item-accion">
                                                    📄 Ver Comprobante
                                                </button>
                                            <?php endif; ?>

                                            <!-- Acciones de envío/guía -->
                                            <?php if($p['enviado'] == '0'): ?>
                                                <button onclick="abrirModalGuia(<?php echo $p['id']; ?>,'<?php echo htmlspecialchars($p['correo']); ?>')" class="item-accion">
                                                    🚚 Marcar Enviado
                                                </button>
                                            <?php else: ?>
                                                <?php if($p['guia']): ?>
                                                    <a href="guias/<?php echo $p['guia']; ?>" class="item-accion" target="_blank">
                                                        📦 Ver Guía
                                                    </a>
                                                <?php else: ?>
                                                    <button onclick="abrirModalGuia(<?php echo $p['id']; ?>,'<?php echo htmlspecialchars($p['correo']); ?>')" class="item-accion">
                                                        📦 Subir Guía
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <div class="separador-menu"></div>

                                            <!-- Acciones adicionales -->
                                            <button onclick="editarCliente(<?php echo $p['id']; ?>)" class="item-accion">
                                                ✏️ Editar Cliente
                                            </button>
                                            <button onclick="imprimirPedido(<?php echo $p['id']; ?>)" class="item-accion">
                                                🖨️ Imprimir Pedido
                                            </button>
                                            <button onclick="duplicarPedido(<?php echo $p['id']; ?>)" class="item-accion">
                                                � Duplicar Pedido
                                            </button>

                                            <div class="separador-menu"></div>

                                            <!-- Acciones de estado -->
                                            <?php if($p['anulado'] == '0'): ?>
                                                <button onclick="anularPedido(<?php echo $p['id']; ?>)" class="item-accion peligro">
                                                    ❌ Anular Pedido
                                                </button>
                                            <?php else: ?>
                                                <button onclick="restaurarPedido(<?php echo $p['id']; ?>)" class="item-accion">
                                                    🔄 Restaurar Pedido
                                                </button>
                                            <?php endif; ?>

                                            <?php if($p['archivado'] == '0'): ?>
                                                <button onclick="archivarPedido(<?php echo $p['id']; ?>)" class="item-accion">
                                                    📁 Archivar Pedido
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
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







// ===== FUNCIONES DE EDICIÓN =====
function editarCliente(pedidoId) {
    // Crear modal dinámico para editar cliente
    fetch(`editar_cliente.php?id=${pedidoId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarModalEditarCliente(pedidoId, data.cliente);
            } else {
                alert('❌ Error al cargar datos del cliente: ' + (data.error || 'Error desconocido'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('❌ Error de conexión');
        });
}

function mostrarModalEditarCliente(pedidoId, cliente) {
    const modal = document.createElement('div');
    modal.className = 'modal-detalle-bg';
    modal.innerHTML = `
        <div class="modal-detalle" style="max-width:500px;">
            <button class="cerrar-modal" onclick="this.closest('.modal-detalle-bg').remove()">×</button>
            <h3>✏️ Editar Cliente - Pedido #${pedidoId}</h3>
            <form id="formEditarCliente-${pedidoId}" style="display: flex; flex-direction: column; gap: 15px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; color: var(--vscode-text-muted);">Nombre:</label>
                    <input type="text" name="nombre" value="${cliente.nombre || ''}" required
                           style="width: 100%; padding: 8px; border: 1px solid var(--vscode-border); background: var(--vscode-bg); color: var(--vscode-text); border-radius: 4px;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; color: var(--vscode-text-muted);">Teléfono:</label>
                    <input type="text" name="telefono" value="${cliente.telefono || ''}" required
                           style="width: 100%; padding: 8px; border: 1px solid var(--vscode-border); background: var(--vscode-bg); color: var(--vscode-text); border-radius: 4px;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; color: var(--vscode-text-muted);">Correo:</label>
                    <input type="email" name="correo" value="${cliente.correo || ''}"
                           style="width: 100%; padding: 8px; border: 1px solid var(--vscode-border); background: var(--vscode-bg); color: var(--vscode-text); border-radius: 4px;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; color: var(--vscode-text-muted);">Dirección:</label>
                    <textarea name="direccion" rows="2"
                              style="width: 100%; padding: 8px; border: 1px solid var(--vscode-border); background: var(--vscode-bg); color: var(--vscode-text); border-radius: 4px;">${cliente.direccion || ''}</textarea>
                </div>
                <div style="display: flex; gap: 10px;">
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 5px; color: var(--vscode-text-muted);">Ciudad:</label>
                        <input type="text" name="ciudad" value="${cliente.ciudad || ''}"
                               style="width: 100%; padding: 8px; border: 1px solid var(--vscode-border); background: var(--vscode-bg); color: var(--vscode-text); border-radius: 4px;">
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 5px; color: var(--vscode-text-muted);">Barrio:</label>
                        <input type="text" name="barrio" value="${cliente.barrio || ''}"
                               style="width: 100%; padding: 8px; border: 1px solid var(--vscode-border); background: var(--vscode-bg); color: var(--vscode-text); border-radius: 4px;">
                    </div>
                </div>
                <button type="submit" class="btn-neon" style="margin-top: 15px;">
                    💾 Guardar Cambios
                </button>
            </form>
            <div id="editar-status-${pedidoId}" style="margin-top:15px;"></div>
        </div>
    `;

    document.body.appendChild(modal);
    modal.style.display = 'flex';

    // Configurar manejador de envío
    const form = modal.querySelector(`#formEditarCliente-${pedidoId}`);
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        procesarEdicionCliente(pedidoId, this, modal);
    });
}

function procesarEdicionCliente(pedidoId, form, modal) {
    const formData = new FormData(form);
    formData.append('id', pedidoId);

    const statusDiv = modal.querySelector(`#editar-status-${pedidoId}`);
    const submitBtn = form.querySelector('button[type="submit"]');

    // Mostrar estado de carga
    submitBtn.disabled = true;
    submitBtn.textContent = '⏳ Guardando...';
    statusDiv.innerHTML = '<span style="color: var(--vscode-text-muted);">Guardando cambios...</span>';

    fetch('editar_cliente.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            statusDiv.innerHTML = '<span style="color: #238636;">✅ Cliente actualizado correctamente</span>';
            setTimeout(() => {
                modal.remove();
                location.reload();
            }, 2000);
        } else {
            statusDiv.innerHTML = '<span style="color: #da3633;">❌ ' + (data.error || 'Error al actualizar') + '</span>';
            submitBtn.disabled = false;
            submitBtn.textContent = '💾 Guardar Cambios';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        statusDiv.innerHTML = '<span style="color: #da3633;">❌ Error de conexión</span>';
        submitBtn.disabled = false;
        submitBtn.textContent = '💾 Guardar Cambios';
    });
}

function imprimirPedido(pedidoId) {
    // Usar el endpoint específico de impresión
    window.open(`imprimir_pedido.php?id=${pedidoId}`, '_blank');
}

function duplicarPedido(pedidoId) {
    if (!confirm('¿Crear un nuevo pedido basado en este?')) {
        return;
    }

    // Mostrar indicador de carga
    const button = event.target;
    const originalText = button.textContent;
    button.textContent = '⏳ Duplicando...';
    button.disabled = true;

    fetch('duplicar_pedido.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${pedidoId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`✅ Pedido duplicado correctamente. Nuevo pedido #${data.nuevo_id}`);
            // Opcional: redireccionar al nuevo pedido
            window.open(`ver_detalle_pedido.php?id=${data.nuevo_id}`, '_blank');
        } else {
            alert('❌ Error al duplicar: ' + (data.error || 'Error desconocido'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error de conexión');
    })
    .finally(() => {
        button.textContent = originalText;
        button.disabled = false;
    });
}

function imprimirSeleccionados() {
    if (pedidosSeleccionados.length === 0) {
        alert('Selecciona al menos un pedido para imprimir');
        return;
    }

    const url = `imprimir_masivo.php?ids=${pedidosSeleccionados.join(',')}`;
    window.open(url, '_blank');
}

// ===== FUNCIONES LEGACY MEJORADAS =====
function cambiarEstado(pedidoId, nuevoEstado) {
    const mensajes = {
        'archivado': 'archivar'
    };

    if (!confirm(`¿Estás seguro de ${mensajes[nuevoEstado] || nuevoEstado} el pedido #${pedidoId}?`)) {
        return;
    }

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
            location.reload();
        } else {
            alert('❌ Error: ' + (data.error || 'No se pudo actualizar'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error de conexión');
    });
}

function anularPedido(pedidoId) {
    if (!confirm(`¿Estás seguro de anular el pedido #${pedidoId}?`)) {
        return;
    }

    // Mostrar indicador de carga
    const button = event.target;
    const originalText = button.textContent;
    button.textContent = '⏳ Anulando...';
    button.disabled = true;

    fetch('anular_pedido.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id_pedido=${pedidoId}` // Corregido para enviar id_pedido
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response ok:', response.ok);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.text();
    })
    .then(text => {
        console.log('Raw response:', text);
        try {
            const data = JSON.parse(text);
            if (data.success) {
                mostrarFeedbackAccion('✅ Pedido anulado correctamente', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                mostrarFeedbackAccion('❌ Error al anular: ' + (data.message || 'Error desconocido'), 'error');
            }
        } catch (parseError) {
            console.error('JSON Parse Error:', parseError);
            console.error('Raw text:', text);
            mostrarFeedbackAccion('❌ Error: Respuesta del servidor inválida', 'error');
        }
    })
    .catch(error => {
        console.error('Fetch Error:', error);
        mostrarFeedbackAccion('❌ Error de conexión: ' + error.message, 'error');
    })
    .finally(() => {
        button.textContent = originalText;
        button.disabled = false;
    });
}

function restaurarPedido(pedidoId) {
    if (!confirm(`¿Estás seguro de restaurar el pedido #${pedidoId}?`)) {
        return;
    }

    // Mostrar indicador de carga
    const button = event.target;
    const originalText = button.textContent;
    button.textContent = '⏳ Restaurando...';
    button.disabled = true;

    fetch('restaurar_pedido.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id_pedido=${pedidoId}` // Corregido para enviar id_pedido
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarFeedbackAccion('✅ Pedido restaurado correctamente', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            mostrarFeedbackAccion('❌ Error al restaurar: ' + (data.error || 'Error desconocido'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarFeedbackAccion('❌ Error de conexión', 'error');
    })
    .finally(() => {
        button.textContent = originalText;
        button.disabled = false;
    });
}

function archivarPedido(pedidoId) {
    if (!confirm(`¿Estás seguro de archivar el pedido #${pedidoId}?`)) {
        return;
    }

    // Mostrar indicador de carga
    const button = event.target;
    const originalText = button.textContent;
    button.textContent = '⏳ Archivando...';
    button.disabled = true;

    fetch('archivar_pedido.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id_pedido=${pedidoId}` // Corregido para enviar id_pedido
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarFeedbackAccion('✅ Pedido archivado correctamente', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            mostrarFeedbackAccion('❌ Error al archivar: ' + (data.error || 'Error desconocido'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarFeedbackAccion('❌ Error de conexión', 'error');
    })
    .finally(() => {
        button.textContent = originalText;
        button.disabled = false;
    });
}

/* ===== FUNCIÓN DE PRUEBA PARA ENDPOINTS AUXILIARES ===== */
function probarEndpointsAuxiliares() {
    console.log('🔧 Probando endpoints auxiliares...');

    const endpoints = [
        'get_productos_pedido.php',
        'editar_cliente.php',
        'duplicar_pedido.php',
        'anular_pedido.php',
        'imprimir_pedido.php',
        'archivar_pedido.php',
        'restaurar_pedido.php'
    ];

    endpoints.forEach(endpoint => {
        fetch(endpoint + '?test=1')
            .then(response => {
                if (response.ok) {
                    console.log(`✅ ${endpoint} - Disponible`);
                } else {
                    console.log(`⚠️ ${endpoint} - Error ${response.status}`);
                }
            })
            .catch(error => {
                console.log(`❌ ${endpoint} - No disponible`);
            });
    });
}

// ===== FUNCIÓN DE INICIALIZACIÓN DE FECHAS PERSONALIZADAS =====
function initializeFechasPersonalizadas() {
    window.isInitializing = true; // Flag para evitar limpiar campos durante inicialización

    const filtroPeriodo = document.getElementById('filtroPeriodo');
    const fechasContainer = document.getElementById('fechasPersonalizadas');
    const fechaDesde = document.querySelector('input[name="fecha_desde"]');
    const fechaHasta = document.querySelector('input[name="fecha_hasta"]');

    if (!filtroPeriodo || !fechasContainer) {
        window.isInitializing = false;
        return;
    }

    // Si hay fechas definidas en PHP, mostrar el contenedor y seleccionar "personalizado"
    if ((fechaDesde && fechaDesde.value) || (fechaHasta && fechaHasta.value)) {
        filtroPeriodo.value = 'personalizado';
        fechasContainer.style.display = 'flex';
        fechasContainer.classList.add('show');
    } else {
        // Verificar si ya está seleccionado "personalizado" para mostrar los campos
        if (filtroPeriodo.value === 'personalizado') {
            fechasContainer.style.display = 'flex';
            fechasContainer.classList.add('show');
        } else {
            // Asegurar que estén ocultos si no es personalizado
            fechasContainer.style.display = 'none';
            fechasContainer.classList.remove('show');
        }
    }

    setTimeout(() => {
        window.isInitializing = false; // Limpiar flag después de inicialización
    }, 100);
}

// Ejecutar prueba automáticamente si se incluye ?debug=1 en la URL
if (window.location.search.includes('debug=1')) {
    document.addEventListener('DOMContentLoaded', probarEndpointsAuxiliares);
}

// Inicializar fechas personalizadas al cargar la página
document.addEventListener('DOMContentLoaded', initializeFechasPersonalizadas);



// ============================================
// FUNCIONES PARA NUEVA TABLA RECONSTRUIDA
// ============================================

// Variable para controlar dropdown activo
let dropdownActivoNueva = null;

// Función para toggle del dropdown de acciones
function toggleDropdownNueva(pedidoId) {
    const menu = document.getElementById(`menu-nueva-${pedidoId}`);
    const boton = menu.previousElementSibling;
    const contenedor = menu.parentElement; // El div.dropdown-acciones

    // Cerrar dropdown anterior si existe
    if (dropdownActivoNueva && dropdownActivoNueva !== menu) {
        dropdownActivoNueva.classList.remove('mostrar');
        dropdownActivoNueva.classList.remove('activo');
        dropdownActivoNueva.previousElementSibling.classList.remove('activo');
        dropdownActivoNueva.parentElement.classList.remove('activo'); // Quitar clase del contenedor anterior
    }

    // Toggle del dropdown actual
    const estaAbierto = menu.classList.contains('mostrar');

    if (estaAbierto) {
        menu.classList.remove('mostrar');
        menu.classList.remove('activo');
        boton.classList.remove('activo');
        contenedor.classList.remove('activo'); // Quitar clase del contenedor
        dropdownActivoNueva = null;
    } else {
        menu.classList.add('mostrar');
        menu.classList.add('activo'); // Agregar clase activo para z-index máximo
        boton.classList.add('activo');
        contenedor.classList.add('activo'); // Agregar clase al contenedor para z-index máximo
        dropdownActivoNueva = menu;
    }
}

// Cerrar dropdowns al hacer click fuera
document.addEventListener('click', function(event) {
    if (dropdownActivoNueva && !event.target.closest('.dropdown-acciones')) {
        dropdownActivoNueva.classList.remove('mostrar');
        dropdownActivoNueva.classList.remove('activo');
        dropdownActivoNueva.previousElementSibling.classList.remove('activo');
        dropdownActivoNueva.parentElement.classList.remove('activo'); // Quitar clase del contenedor
        dropdownActivoNueva = null;
    }
});

// Cerrar dropdowns con Escape
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && dropdownActivoNueva) {
        dropdownActivoNueva.classList.remove('mostrar');
        dropdownActivoNueva.classList.remove('activo');
        dropdownActivoNueva.previousElementSibling.classList.remove('activo');
        dropdownActivoNueva.parentElement.classList.remove('activo'); // Quitar clase del contenedor
        dropdownActivoNueva = null;
    }
});

// ============================================
// FUNCIONES DE MODAL DE GUÍA FALTANTES
// ============================================

// Función para abrir el modal de guía
function abrirModalGuia(pedidoId, correoCliente) {
    document.getElementById('guia_id_pedido').value = pedidoId;
    document.getElementById('modal-guia-bg').style.display = 'flex';
    document.getElementById('guia_status').textContent = '';

    // Reset del formulario
    document.getElementById('formGuia').reset();
    document.getElementById('guia_id_pedido').value = pedidoId;

    console.log(`Modal de guía abierto para pedido #${pedidoId}, cliente: ${correoCliente}`);
}

// Función para cerrar el modal de guía
function cerrarModalGuia() {
    document.getElementById('modal-guia-bg').style.display = 'none';
    document.getElementById('formGuia').reset();
    document.getElementById('guia_status').textContent = '';
}

// Función para manejar la subida de guía
document.getElementById('formGuia').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const statusDiv = document.getElementById('guia_status');
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;

    // Mostrar indicador de carga
    submitBtn.textContent = '⏳ Subiendo...';
    submitBtn.disabled = true;
    statusDiv.textContent = 'Subiendo archivo...';
    statusDiv.style.color = '#007AFF';

    fetch('subir_guia.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            statusDiv.textContent = '✅ Guía subida correctamente';
            statusDiv.style.color = '#34C759';
            mostrarFeedbackAccion('✅ Guía subida y cliente notificado', 'success');
            setTimeout(() => {
                cerrarModalGuia();
                location.reload();
            }, 2000);
        } else {
            statusDiv.textContent = '❌ Error: ' + (data.error || 'Error desconocido');
            statusDiv.style.color = '#FF3B30';
            mostrarFeedbackAccion('❌ Error al subir guía', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        statusDiv.textContent = '❌ Error de conexión';
        statusDiv.style.color = '#FF3B30';
        mostrarFeedbackAccion('❌ Error de conexión', 'error');
    })
    .finally(() => {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
});

// Función para imprimir pedido
function imprimirPedido(pedidoId) {
    // Abrir ventana de impresión
    const url = `imprimir_pedido.php?id=${pedidoId}`;
    window.open(url, '_blank', 'width=800,height=600,scrollbars=yes');
    mostrarFeedbackAccion(`📄 Abriendo vista de impresión para pedido #${pedidoId}`, 'info');
}

// Función placeholder para editar cliente (si no existe implementación específica)
function editarCliente(pedidoId) {
    // Por ahora solo mostrar el modal de detalle que permite ver/editar información
    verDetallePedido(pedidoId);
    mostrarFeedbackAccion(`✏️ Abriendo detalles del pedido #${pedidoId}`, 'info');
}

// Función para duplicar pedido
function duplicarPedido(pedidoId) {
    if (!confirm(`¿Estás seguro de duplicar el pedido #${pedidoId}?`)) {
        return;
    }

    const button = event.target;
    const originalText = button.textContent;
    button.textContent = '⏳ Duplicando...';
    button.disabled = true;

    fetch('duplicar_pedido.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${pedidoId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarFeedbackAccion(`✅ Pedido duplicado. Nuevo ID: #${data.nuevo_id}`, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            mostrarFeedbackAccion('❌ Error al duplicar: ' + (data.error || 'Error desconocido'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarFeedbackAccion('❌ Error de conexión', 'error');
    })
    .finally(() => {
        button.textContent = originalText;
        button.disabled = false;
    });
}

// Feedback visual para acciones
function mostrarFeedbackAccion(mensaje, tipo = 'info') {
    // Crear elemento de feedback
    const feedback = document.createElement('div');
    feedback.className = `feedback-accion feedback-${tipo}`;
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

console.log('✅ Nueva tabla inicializada correctamente');
console.log('✅ Sistema de dropdowns configurado');
console.log('✅ Eventos de teclado y click configurados');
</script>

</body>
</html>
