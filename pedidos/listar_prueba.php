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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Pedidos - Sequoia Speed</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📦</text></svg>">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #111827;
            color: #f9fafb;
            line-height: 1.5;
            font-size: 14px;
        }

        .navbar {
            background-color: #1f2937;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid #374151;
        }

        .navbar .logo {
            font-size: 20px;
            font-weight: 700;
            color: #3b82f6;
        }

        .navbar .title {
            font-size: 16px;
            font-weight: 600;
            color: #e5e7eb;
        }

        .navbar .actions {
            display: flex;
            gap: 0.5rem;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1.5rem;
        }

        .header-section {
            background-color: #1f2937;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #374151;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background-color: #374151;
            border-radius: 0.5rem;
            padding: 1rem;
            text-align: center;
        }

        .stat-card .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: #3b82f6;
            margin-bottom: 0.25rem;
        }

        .stat-card .stat-label {
            font-size: 12px;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .filters-section {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .filter-group {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .filter-group label {
            font-size: 12px;
            color: #9ca3af;
            font-weight: 500;
        }

        .select-modern,
        .input-modern {
            background-color: #374151;
            border: 1px solid #4b5563;
            color: #f9fafb;
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 14px;
            transition: all 0.2s;
        }

        .select-modern:focus,
        .input-modern:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
        }

        .button {
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .button-primary {
            background-color: #3b82f6;
            color: #ffffff;
        }

        .button-primary:hover {
            background-color: #2563eb;
        }

        .button-secondary {
            background-color: transparent;
            border: 1px solid #4b5563;
            color: #d1d5db;
        }

        .button-secondary:hover {
            background-color: #374151;
            border-color: #6b7280;
        }

        .button-success {
            background-color: #10b981;
            color: #ffffff;
        }

        .button-success:hover {
            background-color: #059669;
        }

        .button-warning {
            background-color: #f59e0b;
            color: #ffffff;
        }

        .button-warning:hover {
            background-color: #d97706;
        }

        .button-danger {
            background-color: #ef4444;
            color: #ffffff;
        }

        .button-danger:hover {
            background-color: #dc2626;
        }

        .orders-grid {
            display: grid;
            gap: 1rem;
        }

        .order-card {
            background-color: #1f2937;
            border-radius: 0.75rem;
            padding: 1.5rem;
            border: 1px solid #374151;
            transition: all 0.2s;
            cursor: pointer;
        }

        .order-card:hover {
            border-color: #4b5563;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .order-header {
            display: flex;
            justify-content: between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .order-id {
            font-size: 18px;
            font-weight: 700;
            color: #3b82f6;
            margin-bottom: 0.25rem;
        }

        .order-date {
            font-size: 12px;
            color: #9ca3af;
        }

        .order-amount {
            font-size: 18px;
            font-weight: 600;
            color: #10b981;
            text-align: right;
        }

        .order-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .info-label {
            font-size: 11px;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 500;
        }

        .info-value {
            font-size: 14px;
            color: #e5e7eb;
            font-weight: 500;
        }

        .status-badges {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .badge {
            font-size: 11px;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .badge-paid {
            background-color: #065f46;
            color: #86efac;
        }

        .badge-unpaid {
            background-color: #7f1d1d;
            color: #fca5a5;
        }

        .badge-shipped {
            background-color: #1e40af;
            color: #93c5fd;
        }

        .badge-unshipped {
            background-color: #92400e;
            color: #fbbf24;
        }

        .badge-store {
            background-color: #7c2d12;
            color: #fdba74;
        }

        .badge-cancelled {
            background-color: #374151;
            color: #9ca3af;
        }

        .order-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .action-btn {
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 12px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .action-view {
            background-color: #374151;
            color: #d1d5db;
        }

        .action-view:hover {
            background-color: #4b5563;
        }

        .action-edit {
            background-color: #1d4ed8;
            color: #ffffff;
        }

        .action-edit:hover {
            background-color: #1e40af;
        }

        .action-whatsapp {
            background-color: #16a34a;
            color: #ffffff;
        }

        .action-whatsapp:hover {
            background-color: #15803d;
        }

        .order-details {
            display: none;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #374151;
        }

        .order-details.show {
            display: block;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .detail-section {
            background-color: #374151;
            border-radius: 0.5rem;
            padding: 1rem;
        }

        .detail-section h4 {
            font-size: 14px;
            font-weight: 600;
            color: #3b82f6;
            margin-bottom: 0.75rem;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            margin-top: 2rem;
        }

        .pagination a,
        .pagination span {
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
        }

        .pagination a {
            background-color: #374151;
            color: #d1d5db;
            border: 1px solid #4b5563;
        }

        .pagination a:hover {
            background-color: #4b5563;
        }

        .pagination .current {
            background-color: #3b82f6;
            color: #ffffff;
        }

        .search-section {
            margin-bottom: 1rem;
        }

        .search-input {
            width: 100%;
            max-width: 400px;
            background-color: #374151;
            border: 1px solid #4b5563;
            color: #f9fafb;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            font-size: 14px;
            transition: all 0.2s;
        }

        .search-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .order-info {
                grid-template-columns: 1fr;
            }
            
            .filters-section {
                flex-direction: column;
                align-items: stretch;
            }
            
            .order-actions {
                justify-content: center;
            }
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.75);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            display: none;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal {
            background-color: #1f2937;
            border-radius: 0.75rem;
            padding: 2rem;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
        }

        .modal h3 {
            font-size: 20px;
            font-weight: 700;
            color: #3b82f6;
            margin-bottom: 1rem;
        }

        .whatsapp-btn {
            background-color: #25d366;
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .whatsapp-btn:hover {
            background-color: #22c55e;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
    <div class="logo">📦 Sequoia Speed</div>
    <div class="title">Gestión de Pedidos</div>
    <div class="actions">
        <button class="button button-primary" onclick="window.open('orden_pedido.php', '_blank')">
            ➕ Nuevo Pedido
        </button>
        <button class="button button-secondary" onclick="location.reload()">
            🔄 Actualizar
        </button>
    </div>
</nav>

<div class="container">
    <!-- Header with Stats -->
    <div class="header-section">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($total_pedidos); ?></div>
                <div class="stat-label">Total Pedidos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($monto_total_real, 0, ',', '.'); ?></div>
                <div class="stat-label">Monto Total</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php 
                    $pendientes = 0;
                    foreach($pedidos as $p) {
                        if($p['pagado'] == '0') $pendientes++;
                    }
                    echo $pendientes;
                ?></div>
                <div class="stat-label">Pendientes Pago</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_pedidos - $pendientes; ?></div>
                <div class="stat-label">Pagados</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <div class="filter-group">
                <label>Filtro:</label>
                <select class="select-modern" onchange="aplicarFiltroRapido(this.value)">
                    <option value="todos" <?php echo ($filtro=='todos' ? 'selected' : ''); ?>>📦 Todos</option>
                    <option value="hoy" <?php echo ($filtro=='hoy' ? 'selected' : ''); ?>>📅 Hoy</option>
                    <option value="semana" <?php echo ($filtro=='semana' ? 'selected' : ''); ?>>📊 Semana</option>
                    <option value="mes" <?php echo ($filtro=='mes' ? 'selected' : ''); ?>>📈 Mes</option>
                    <option value="pendientes_atencion" <?php echo ($filtro=='pendientes_atencion' ? 'selected' : ''); ?>>⏳ Pendientes</option>
                    <option value="pago_confirmado" <?php echo ($filtro=='pago_confirmado' ? 'selected' : ''); ?>>✅ Pagados</option>
                    <option value="enviados" <?php echo ($filtro=='enviados' ? 'selected' : ''); ?>>🚚 Enviados</option>
                    <option value="anulados" <?php echo ($filtro=='anulados' ? 'selected' : ''); ?>>❌ Anulados</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Buscar:</label>
                <input type="text" 
                       class="input-modern search-input" 
                       placeholder="🔍 ID, nombre, email, teléfono..."
                       value="<?php echo htmlspecialchars($buscar); ?>"
                       onkeyup="busquedaEnTiempoReal(this.value)">
            </div>

            <button class="button button-secondary" onclick="exportarExcel()">
                📊 Excel
            </button>
            <button class="button button-secondary" onclick="exportarPDF()">
                📄 PDF
            </button>
        </div>
    </div>

    <!-- Orders Grid -->
    <div class="orders-grid">
        <?php if(empty($pedidos)): ?>
            <div class="order-card" style="text-align: center; padding: 3rem;">
                <div style="font-size: 48px; margin-bottom: 1rem;">📦</div>
                <h3>No hay pedidos</h3>
                <p style="color: #9ca3af; margin-bottom: 1.5rem;">No se encontraron pedidos con los filtros aplicados</p>
                <button class="button button-primary" onclick="window.open('orden_pedido.php', '_blank')">
                    ➕ Crear Primer Pedido
                </button>
            </div>
        <?php else: ?>
            <?php foreach($pedidos as $p): ?>
                <div class="order-card" onclick="toggleOrderDetails(<?php echo $p['id']; ?>)">
                    <div class="order-header" style="display: flex; justify-content: space-between; align-items: start;">
                        <div>
                            <div class="order-id">#<?php echo $p['id']; ?></div>
                            <div class="order-date"><?php echo date('d/m/Y H:i', strtotime($p['fecha'])); ?></div>
                        </div>
                        <div class="order-amount">$<?php echo number_format($p['monto'], 0, ',', '.'); ?></div>
                    </div>

                    <div class="order-info">
                        <div class="info-item">
                            <div class="info-label">Cliente</div>
                            <div class="info-value"><?php echo htmlspecialchars($p['nombre']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Teléfono</div>
                            <div class="info-value"><?php echo htmlspecialchars($p['telefono']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Ciudad</div>
                            <div class="info-value"><?php echo htmlspecialchars($p['ciudad']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Método de Pago</div>
                            <div class="info-value"><?php echo htmlspecialchars($p['metodo_pago']); ?></div>
                        </div>
                    </div>

                    <div class="status-badges">
                        <?php if($p['pagado'] == '1'): ?>
                            <span class="badge badge-paid">💳 Pagado</span>
                        <?php else: ?>
                            <span class="badge badge-unpaid">⏳ Pendiente</span>
                        <?php endif; ?>

                        <?php if($p['enviado'] == '1'): ?>
                            <span class="badge badge-shipped">🚚 Enviado</span>
                        <?php elseif($p['tienda'] == '1'): ?>
                            <span class="badge badge-store">🏪 Tienda</span>
                        <?php else: ?>
                            <span class="badge badge-unshipped">📦 Sin Enviar</span>
                        <?php endif; ?>

                        <?php if($p['anulado'] == '1'): ?>
                            <span class="badge badge-cancelled">❌ Anulado</span>
                        <?php endif; ?>
                    </div>

                    <div class="order-actions" onclick="event.stopPropagation()">
                        <a href="ver_detalle_pedido.php?id=<?php echo $p['id']; ?>" 
                           class="action-btn action-view" target="_blank">
                            👁️ Ver
                        </a>
                        <a href="https://wa.me/57<?php echo preg_replace('/[^0-9]/', '', $p['telefono']); ?>?text=Hola <?php echo urlencode($p['nombre']); ?>, te contactamos por tu pedido #<?php echo $p['id']; ?>" 
                           class="action-btn action-whatsapp" target="_blank">
                            📱 WhatsApp
                        </a>
                        <button class="action-btn action-edit" onclick="abrirDetallePopup(<?php echo $p['id']; ?>)">
                            ⚙️ Configurar
                        </button>
                    </div>

                    <!-- Expandable Details -->
                    <div class="order-details" id="details-<?php echo $p['id']; ?>">
                        <div class="details-grid">
                            <div class="detail-section">
                                <h4>📧 Información de Contacto</h4>
                                <div class="info-item">
                                    <div class="info-label">Email</div>
                                    <div class="info-value"><?php echo htmlspecialchars($p['correo']); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Dirección</div>
                                    <div class="info-value"><?php echo htmlspecialchars($p['direccion']); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Barrio</div>
                                    <div class="info-value"><?php echo htmlspecialchars($p['barrio']); ?></div>
                                </div>
                            </div>
                            
                            <div class="detail-section">
                                <h4>💰 Información de Pago</h4>
                                <div class="info-item">
                                    <div class="info-label">Subtotal</div>
                                    <div class="info-value">$<?php echo number_format($p['monto'] + $p['descuento'], 0, ',', '.'); ?></div>
                                </div>
                                <?php if($p['descuento'] > 0): ?>
                                <div class="info-item">
                                    <div class="info-label">Descuento</div>
                                    <div class="info-value" style="color: #ef4444;">-$<?php echo number_format($p['descuento'], 0, ',', '.'); ?></div>
                                </div>
                                <?php endif; ?>
                                <div class="info-item">
                                    <div class="info-label">Total</div>
                                    <div class="info-value" style="color: #10b981; font-weight: 700;">$<?php echo number_format($p['monto'], 0, ',', '.'); ?></div>
                                </div>
                            </div>

                            <div class="detail-section">
                                <h4>📋 Estado del Pedido</h4>
                                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                    <button class="button <?php echo $p['pagado'] == '1' ? 'button-success' : 'button-warning'; ?>"
                                            onclick="toggleEstadoPago(<?php echo $p['id']; ?>, <?php echo $p['pagado']; ?>, '<?php echo htmlspecialchars($p['comprobante']); ?>', '<?php echo $p['tiene_comprobante']; ?>', '<?php echo htmlspecialchars($p['metodo_pago']); ?>')">
                                        <?php echo $p['pagado'] == '1' ? '✅ Pagado' : '⏳ Pendiente'; ?>
                                    </button>
                                    
                                    <button class="button button-secondary"
                                            onclick="abrirModalComprobante(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['comprobante']); ?>', '<?php echo $p['tiene_comprobante']; ?>', '<?php echo htmlspecialchars($p['metodo_pago']); ?>')">
                                        📄 Comprobante
                                    </button>
                                    
                                    <button class="button button-secondary"
                                            onclick="abrirModalGuia(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['guia']); ?>', '<?php echo $p['tiene_guia']; ?>', '<?php echo $p['enviado']; ?>', '<?php echo $p['tienda']; ?>')">
                                        📦 Guía
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if($total_paginas > 1): ?>
        <div class="pagination">
            <?php if($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($params, ['page' => $page - 1])); ?>">⬅️ Anterior</a>
            <?php endif; ?>
            
            <span class="current">Página <?php echo $page; ?> de <?php echo $total_paginas; ?></span>
            
            <?php if($page < $total_paginas): ?>
                <a href="?<?php echo http_build_query(array_merge($params, ['page' => $page + 1])); ?>">Siguiente ➡️</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// JavaScript functions from original listar_pedidos.php
function aplicarFiltroRapido(filtro) {
    const url = new URL(window.location);
    url.searchParams.set('filtro', filtro);
    url.searchParams.delete('page'); // Reset to page 1
    window.location.href = url.toString();
}

function busquedaEnTiempoReal(termino) {
    clearTimeout(window.searchTimeout);
    window.searchTimeout = setTimeout(() => {
        const url = new URL(window.location);
        if (termino.trim()) {
            url.searchParams.set('buscar', termino);
        } else {
            url.searchParams.delete('buscar');
        }
        url.searchParams.delete('page'); // Reset to page 1
        window.location.href = url.toString();
    }, 500);
}

function toggleOrderDetails(orderId) {
    const details = document.getElementById(`details-${orderId}`);
    if (details.classList.contains('show')) {
        details.classList.remove('show');
    } else {
        // Close all other details first
        document.querySelectorAll('.order-details.show').forEach(el => {
            el.classList.remove('show');
        });
        details.classList.add('show');
    }
}

function exportarExcel() {
    const url = new URL('export_handler.php', window.location.origin + window.location.pathname.replace('listar_prueba.php', ''));
    url.searchParams.set('formato', 'excel');
    // Add current filters
    const currentUrl = new URL(window.location);
    for (const [key, value] of currentUrl.searchParams) {
        url.searchParams.set(key, value);
    }
    window.open(url.toString(), '_blank');
}

function exportarPDF() {
    const url = new URL('export_handler.php', window.location.origin + window.location.pathname.replace('listar_prueba.php', ''));
    url.searchParams.set('formato', 'pdf');
    // Add current filters
    const currentUrl = new URL(window.location);
    for (const [key, value] of currentUrl.searchParams) {
        url.searchParams.set(key, value);
    }
    window.open(url.toString(), '_blank');
}

// Placeholder functions for modal operations (would need full implementation)
function abrirDetallePopup(pedidoId) {
    alert('Función de configuración para pedido #' + pedidoId + '\n(Requiere implementación completa del modal)');
}

function toggleEstadoPago(pedidoId, estadoActual, comprobante, tieneComprobante, metodoPago) {
    alert('Función de cambio de estado de pago para pedido #' + pedidoId + '\n(Requiere implementación completa)');
}

function abrirModalComprobante(pedidoId, comprobante, tieneComprobante, metodoPago) {
    alert('Modal de comprobante para pedido #' + pedidoId + '\n(Requiere implementación completa)');
}

function abrirModalGuia(pedidoId, guia, tieneGuia, enviado, tienda) {
    alert('Modal de guía para pedido #' + pedidoId + '\n(Requiere implementación completa)');
}

// Auto-refresh every 5 minutes
setInterval(() => {
    location.reload();
}, 300000);
</script>

</body>
</html>