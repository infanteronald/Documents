<?php
/**
 * Gestor de Impresión de Códigos QR
 * Sequoia Speed - Sistema QR
 */

defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once dirname(__DIR__) . '/config_secure.php';
require_once dirname(__DIR__) . '/accesos/middleware/AuthMiddleware.php';
require_once 'models/QRManager.php';
require_once 'csrf_helper.php';
require_once 'xss_helper.php';
require_once 'security_headers.php';

// Establecer headers de seguridad
setSecurityHeaders(true);

// Verificar permisos
$auth = new AuthMiddleware($conn);
$current_user = $auth->requirePermission('qr', 'leer');

// Inicializar QR Manager
$qr_manager = new QRManager($conn);

// Parámetros de filtrado
$filter_entity = $_GET['entity_type'] ?? '';
$filter_active = $_GET['active'] ?? '1';
$search_term = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 12; // QR cards per page for better print layout

// Construir query para obtener QR codes
$where_conditions = [];
$params = [];
$types = '';

if ($filter_entity && $filter_entity !== 'all') {
    $where_conditions[] = "qr.entity_type = ?";
    $params[] = $filter_entity;
    $types .= 's';
}

if ($filter_active !== '') {
    $where_conditions[] = "qr.active = ?";
    $params[] = intval($filter_active);
    $types .= 'i';
}

if ($search_term) {
    $where_conditions[] = "(qr.qr_content LIKE ? OR qr.entity_id LIKE ? OR p.nombre LIKE ? OR p.sku LIKE ?)";
    $search_param = '%' . $search_term . '%';
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= 'ssss';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Query principal con información del producto
$query = "
    SELECT 
        qr.id,
        qr.qr_uuid,
        qr.qr_content,
        qr.entity_type,
        qr.entity_id,
        qr.error_correction_level,
        qr.size_pixels,
        qr.scan_count,
        qr.created_at,
        qr.last_scanned_at,
        qr.active,
        p.nombre as producto_nombre,
        p.sku as producto_sku,
        p.precio as producto_precio,
        c.nombre as categoria_nombre,
        a.nombre as almacen_nombre,
        u.usuario as created_by_name
    FROM qr_codes qr
    LEFT JOIN productos p ON qr.linked_product_id = p.id
    LEFT JOIN categorias_productos c ON p.categoria_id = c.id
    LEFT JOIN almacenes a ON qr.linked_almacen_id = a.id
    LEFT JOIN usuarios u ON qr.created_by = u.id
    $where_clause
    ORDER BY qr.created_at DESC
    LIMIT ? OFFSET ?
";

// Agregar parámetros de paginación
$params[] = $per_page;
$params[] = ($page - 1) * $per_page;
$types .= 'ii';

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$qr_codes = [];

while ($row = $result->fetch_assoc()) {
    $qr_codes[] = $row;
}

// Contar total para paginación
$count_query = str_replace(['SELECT qr.id,qr.qr_uuid,qr.qr_content,qr.entity_type,qr.entity_id,qr.error_correction_level,qr.size_pixels,qr.scan_count,qr.created_at,qr.last_scanned_at,qr.active,p.nombre as producto_nombre,p.sku as producto_sku,p.precio as producto_precio,c.nombre as categoria_nombre,a.nombre as almacen_nombre,u.usuario as created_by_name', 'LIMIT ? OFFSET ?'], ['SELECT COUNT(*) as total', ''], $query);

$count_params = array_slice($params, 0, -2); // Remover limit y offset
$count_types = substr($types, 0, -2);

$count_stmt = $conn->prepare($count_query);
if (!empty($count_params)) {
    $count_stmt->bind_param($count_types, ...$count_params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $per_page);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🖨️ Gestor de Impresión QR - Sequoia Speed</title>
    <?php echo csrfMetaTag(); ?>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🖨️</text></svg>">
    <link rel="stylesheet" href="../inventario/productos.css">
    <link rel="stylesheet" href="../notifications/notifications.css">
    <link rel="stylesheet" href="assets/css/qr.css">
    
    <style>
        .print-manager-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: var(--space-lg);
        }
        
        .print-controls {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: var(--space-lg);
            margin-bottom: var(--space-lg);
        }
        
        .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--space-md);
            margin-bottom: var(--space-md);
        }
        
        .qr-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: var(--space-md);
            margin-bottom: var(--space-lg);
        }
        
        .qr-print-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: var(--space-md);
            position: relative;
            transition: all var(--transition-fast);
        }
        
        .qr-print-card:hover {
            border-color: var(--color-primary);
            box-shadow: var(--shadow-md);
        }
        
        .qr-print-card.selected {
            border-color: var(--color-primary);
            background: rgba(88, 166, 255, 0.1);
        }
        
        .qr-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--space-md);
        }
        
        .qr-entity-badge {
            padding: var(--space-xs) var(--space-sm);
            border-radius: var(--border-radius);
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .entity-producto { background: rgba(88, 166, 255, 0.2); color: var(--color-primary); }
        .entity-ubicacion { background: rgba(35, 134, 54, 0.2); color: var(--color-success); }
        .entity-lote { background: rgba(240, 173, 78, 0.2); color: var(--color-warning); }
        .entity-pedido { background: rgba(218, 54, 51, 0.2); color: var(--color-danger); }
        .entity-almacen { background: rgba(139, 148, 158, 0.2); color: var(--text-secondary); }
        
        .qr-checkbox {
            width: 18px;
            height: 18px;
            accent-color: var(--color-primary);
        }
        
        .qr-image-container {
            text-align: center;
            margin-bottom: var(--space-md);
            background: white;
            padding: var(--space-md);
            border-radius: var(--border-radius);
        }
        
        .qr-image {
            max-width: 150px;
            height: auto;
            border-radius: 4px;
        }
        
        .qr-info {
            font-size: 13px;
        }
        
        .qr-content {
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 11px;
            color: var(--text-secondary);
            word-break: break-all;
            background: var(--bg-tertiary);
            padding: var(--space-xs);
            border-radius: 4px;
            margin-bottom: var(--space-sm);
        }
        
        .qr-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-xs);
            font-size: 12px;
        }
        
        .qr-detail-item {
            display: flex;
            justify-content: space-between;
        }
        
        .qr-detail-label {
            color: var(--text-secondary);
        }
        
        .qr-detail-value {
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .print-actions {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: var(--space-md);
            position: sticky;
            bottom: var(--space-md);
            display: flex;
            gap: var(--space-md);
            align-items: center;
            justify-content: space-between;
        }
        
        .selected-count {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .print-buttons {
            display: flex;
            gap: var(--space-sm);
        }
        
        /* Estilos de impresión */
        @media print {
            body { background: white !important; }
            .print-controls, .print-actions, .pagination { display: none !important; }
            .qr-grid { 
                grid-template-columns: repeat(3, 1fr);
                gap: 10mm;
                padding: 10mm;
            }
            .qr-print-card {
                background: white !important;
                border: 1px solid #000 !important;
                page-break-inside: avoid;
                margin-bottom: 5mm;
            }
            .qr-image-container {
                background: white !important;
            }
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: var(--space-sm);
            margin-top: var(--space-lg);
        }
        
        .page-btn {
            padding: var(--space-sm) var(--space-md);
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            text-decoration: none;
            border-radius: var(--border-radius);
            transition: all var(--transition-fast);
        }
        
        .page-btn:hover {
            background: var(--bg-hover);
            border-color: var(--color-primary);
        }
        
        .page-btn.active {
            background: var(--color-primary);
            border-color: var(--color-primary);
            color: white;
        }
        
        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="print-manager-container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">🖨️ Gestor de Impresión QR</h1>
                    <div class="breadcrumb">
                        <a href="../index.php">🏠 Inicio</a>
                        <span>/</span>
                        <a href="index.php">📱 Sistema QR</a>
                        <span>/</span>
                        <span>🖨️ Impresión</span>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="index.php" class="btn btn-secondary">
                        ← Volver al Dashboard
                    </a>
                    <a href="generator.php" class="btn btn-primary">
                        ➕ Generar Nuevo QR
                    </a>
                </div>
            </div>
        </header>

        <!-- Controles de Filtrado e Impresión -->
        <div class="print-controls">
            <h3>🔍 Filtros y Controles</h3>
            <form method="GET" id="filterForm">
                <div class="filters-row">
                    <div class="form-group">
                        <label for="entity_type">Tipo de Entidad</label>
                        <select name="entity_type" id="entity_type" class="form-control">
                            <option value="">Todos los tipos</option>
                            <option value="producto" <?php echo $filter_entity === 'producto' ? 'selected' : ''; ?>>📦 Productos</option>
                            <option value="ubicacion" <?php echo $filter_entity === 'ubicacion' ? 'selected' : ''; ?>>📍 Ubicaciones</option>
                            <option value="lote" <?php echo $filter_entity === 'lote' ? 'selected' : ''; ?>>📦 Lotes</option>
                            <option value="pedido" <?php echo $filter_entity === 'pedido' ? 'selected' : ''; ?>>🛒 Pedidos</option>
                            <option value="almacen" <?php echo $filter_entity === 'almacen' ? 'selected' : ''; ?>>🏪 Almacenes</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="active">Estado</label>
                        <select name="active" id="active" class="form-control">
                            <option value="1" <?php echo $filter_active === '1' ? 'selected' : ''; ?>>✅ Activos</option>
                            <option value="0" <?php echo $filter_active === '0' ? 'selected' : ''; ?>>❌ Inactivos</option>
                            <option value="" <?php echo $filter_active === '' ? 'selected' : ''; ?>>Todos</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="search">Buscar</label>
                        <input type="text" name="search" id="search" class="form-control" 
                               placeholder="Código QR, SKU, nombre..." 
                               value="<?php echo escape_html($search_term); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                    </div>
                </div>
            </form>
            
            <div class="bulk-actions">
                <button type="button" class="btn btn-outline" onclick="selectAll()">☑️ Seleccionar Todo</button>
                <button type="button" class="btn btn-outline" onclick="selectNone()">◻️ Deseleccionar Todo</button>
                <span class="selected-count">
                    Total: <?php echo $total_records; ?> QR códigos
                </span>
            </div>
        </div>

        <!-- Grid de Códigos QR -->
        <div class="qr-grid" id="qrGrid">
            <?php if (empty($qr_codes)): ?>
                <div class="empty-state" style="grid-column: 1 / -1; text-align: center; padding: var(--space-xl);">
                    <div style="font-size: 48px; margin-bottom: var(--space-md);">📱</div>
                    <h3>No se encontraron códigos QR</h3>
                    <p>No hay códigos QR que coincidan con los filtros seleccionados.</p>
                    <a href="generator.php" class="btn btn-primary">➕ Generar primer QR</a>
                </div>
            <?php else: ?>
                <?php foreach ($qr_codes as $qr): ?>
                    <div class="qr-print-card" data-qr-id="<?php echo $qr['id']; ?>">
                        <div class="qr-header">
                            <span class="qr-entity-badge entity-<?php echo $qr['entity_type']; ?>">
                                <?php 
                                $entity_icons = [
                                    'producto' => '📦',
                                    'ubicacion' => '📍',
                                    'lote' => '📦',
                                    'pedido' => '🛒',
                                    'almacen' => '🏪'
                                ];
                                echo $entity_icons[$qr['entity_type']] . ' ' . ucfirst($qr['entity_type']);
                                ?>
                            </span>
                            <input type="checkbox" class="qr-checkbox" data-qr-id="<?php echo $qr['id']; ?>">
                        </div>
                        
                        <div class="qr-image-container">
                            <img src="api/image.php?content=<?php echo urlencode($qr['qr_content']); ?>&size=150" 
                                 alt="QR Code" class="qr-image" loading="lazy">
                        </div>
                        
                        <div class="qr-info">
                            <div class="qr-content"><?php echo escape_html($qr['qr_content']); ?></div>
                            
                            <?php if ($qr['producto_nombre']): ?>
                                <div style="margin-bottom: var(--space-sm);">
                                    <strong><?php echo escape_html($qr['producto_nombre']); ?></strong>
                                    <?php if ($qr['producto_sku']): ?>
                                        <br><small>SKU: <?php echo escape_html($qr['producto_sku']); ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="qr-details">
                                <div class="qr-detail-item">
                                    <span class="qr-detail-label">ID:</span>
                                    <span class="qr-detail-value"><?php echo $qr['entity_id']; ?></span>
                                </div>
                                <div class="qr-detail-item">
                                    <span class="qr-detail-label">Escaneos:</span>
                                    <span class="qr-detail-value"><?php echo $qr['scan_count'] ?? 0; ?></span>
                                </div>
                                <div class="qr-detail-item">
                                    <span class="qr-detail-label">Creado:</span>
                                    <span class="qr-detail-value"><?php echo date('d/m/Y', strtotime($qr['created_at'])); ?></span>
                                </div>
                                <?php if ($qr['last_scanned_at']): ?>
                                <div class="qr-detail-item">
                                    <span class="qr-detail-label">Último escaneo:</span>
                                    <span class="qr-detail-value"><?php echo date('d/m/Y', strtotime($qr['last_scanned_at'])); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Paginación -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-btn">‹ Anterior</a>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                       class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-btn">Siguiente ›</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Acciones de Impresión -->
    <div class="print-actions">
        <div class="selected-count">
            <span id="selectedCount">0</span> códigos seleccionados
        </div>
        <div class="print-buttons">
            <button type="button" class="btn btn-secondary" onclick="printSelected()">
                🖨️ Imprimir Seleccionados
            </button>
            <button type="button" class="btn btn-primary" onclick="printAll()">
                🖨️ Imprimir Todo
            </button>
            <button type="button" class="btn btn-outline" onclick="downloadSelected()">
                📥 Descargar PDF
            </button>
        </div>
    </div>

    <script>
        // Gestión de selección de QR codes
        let selectedQRs = new Set();
        
        // Event listeners para checkboxes
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.qr-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const qrId = this.dataset.qrId;
                    const card = this.closest('.qr-print-card');
                    
                    if (this.checked) {
                        selectedQRs.add(qrId);
                        card.classList.add('selected');
                    } else {
                        selectedQRs.delete(qrId);
                        card.classList.remove('selected');
                    }
                    
                    updateSelectedCount();
                });
            });
        });
        
        function updateSelectedCount() {
            document.getElementById('selectedCount').textContent = selectedQRs.size;
        }
        
        function selectAll() {
            const checkboxes = document.querySelectorAll('.qr-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
                const qrId = checkbox.dataset.qrId;
                selectedQRs.add(qrId);
                checkbox.closest('.qr-print-card').classList.add('selected');
            });
            updateSelectedCount();
        }
        
        function selectNone() {
            const checkboxes = document.querySelectorAll('.qr-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
                const qrId = checkbox.dataset.qrId;
                selectedQRs.delete(qrId);
                checkbox.closest('.qr-print-card').classList.remove('selected');
            });
            updateSelectedCount();
        }
        
        function printSelected() {
            if (selectedQRs.size === 0) {
                alert('Por favor selecciona al menos un código QR para imprimir.');
                return;
            }
            
            // Ocultar cards no seleccionados
            const allCards = document.querySelectorAll('.qr-print-card');
            allCards.forEach(card => {
                const qrId = card.dataset.qrId;
                if (!selectedQRs.has(qrId)) {
                    card.style.display = 'none';
                }
            });
            
            window.print();
            
            // Restaurar visibilidad
            allCards.forEach(card => {
                card.style.display = '';
            });
        }
        
        function printAll() {
            window.print();
        }
        
        function downloadSelected() {
            if (selectedQRs.size === 0) {
                alert('Por favor selecciona al menos un código QR para descargar.');
                return;
            }
            
            // Crear formulario para enviar IDs via POST
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'api/pdf_export.php';
            form.target = '_blank';
            
            // Agregar CSRF token
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            form.appendChild(csrfInput);
            
            // Agregar IDs de QR seleccionados
            const qrIdsInput = document.createElement('input');
            qrIdsInput.type = 'hidden';
            qrIdsInput.name = 'qr_ids';
            qrIdsInput.value = Array.from(selectedQRs).join(',');
            form.appendChild(qrIdsInput);
            
            // Enviar formulario
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
        
        // Auto-submit form on filter change
        document.getElementById('entity_type').addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
        
        document.getElementById('active').addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    </script>
</body>
</html>