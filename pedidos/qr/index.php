<?php
/**
 * Sistema QR - Dashboard Principal
 * Sequoia Speed - Módulo QR
 */

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once dirname(__DIR__) . '/config_secure.php';
require_once dirname(__DIR__) . '/accesos/middleware/AuthMiddleware.php';
require_once 'models/QRManager.php';
require_once 'csrf_helper.php';
require_once 'xss_helper.php';
require_once 'security_headers.php';
require_once 'error_handler.php';

// Configurar manejo de errores
setupErrorHandler();

// Establecer headers de seguridad
setSecurityHeaders(true);

// Verificar permisos
$auth = new AuthMiddleware($conn);
$current_user = $auth->requirePermission('qr', 'leer');

// Inicializar QR Manager
$qr_manager = new QRManager($conn);

// Obtener estadísticas básicas
$stats = $qr_manager->getQRStats();

// Verificar si se especificó un producto para generar QR
$producto_id = isset($_GET['producto_id']) ? intval($_GET['producto_id']) : 0;
$producto_info = null;

if ($producto_id > 0) {
    // Obtener información del producto
    $producto_query = "SELECT p.id, p.nombre, p.descripcion, p.sku, p.precio, 
                              c.nombre as categoria_nombre, p.imagen
                       FROM productos p 
                       LEFT JOIN categorias_productos c ON p.categoria_id = c.id 
                       WHERE p.id = ? AND p.activo = '1'";
    $stmt = $conn->prepare($producto_query);
    $stmt->bind_param("i", $producto_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $producto_info = $result->fetch_assoc();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📱 Sistema QR - Sequoia Speed</title>
    <?php echo csrfMetaTag(); ?>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📱</text></svg>">
    <link rel="stylesheet" href="../inventario/productos.css">
    <link rel="stylesheet" href="../notifications/notifications.css">
    <link rel="stylesheet" href="assets/css/qr.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">📱 Sistema QR</h1>
                    <div class="breadcrumb">
                        <a href="../listar_pedidos.php">🏠 Inicio</a>
                        <span>/</span>
                        <a href="../inventario/productos.php">📦 Inventario</a>
                        <span>/</span>
                        <span>📱 QR</span>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="print_manager.php" class="btn btn-outline">
                        🖨️ Ver/Imprimir QR
                    </a>
                    <a href="scanner.php" class="btn btn-primary">
                        📸 Escanear QR
                    </a>
                    <a href="generator.php" class="btn btn-secondary">
                        ➕ Generar QR
                    </a>
                </div>
            </div>
        </header>

        <!-- Estadísticas QR -->
        <div class="stats-section">
            <div class="stat-card">
                <div class="stat-icon">📱</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo safe_number($stats['total_qr_codes'] ?? 0); ?></div>
                    <div class="stat-label">Códigos QR</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📸</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo safe_number($stats['total_scans'] ?? 0); ?></div>
                    <div class="stat-label">Escaneos</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo safe_number($stats['active_users'] ?? 0); ?></div>
                    <div class="stat-label">Usuarios Activos</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stats['scans_today'] ?? 0); ?></div>
                    <div class="stat-label">Escaneos Hoy</div>
                </div>
            </div>
        </div>

        <!-- Acceso Rápido -->
        <div class="quick-access-section">
            <h2>🚀 Acceso Rápido</h2>
            <div class="quick-access-grid">
                <a href="scanner.php" class="quick-access-card">
                    <div class="card-icon">📸</div>
                    <div class="card-title">Escanear QR</div>
                    <div class="card-description">Escanear códigos QR con la cámara</div>
                </a>
                
                <a href="generator.php" class="quick-access-card">
                    <div class="card-icon">➕</div>
                    <div class="card-title">Generar QR</div>
                    <div class="card-description">Crear códigos QR para productos</div>
                </a>
                
                <a href="reports.php" class="quick-access-card">
                    <div class="card-icon">📊</div>
                    <div class="card-title">Analytics</div>
                    <div class="card-description">Estadísticas y reportes QR</div>
                </a>
                
                <a href="workflows.php" class="quick-access-card">
                    <div class="card-icon">📋</div>
                    <div class="card-title">Workflows</div>
                    <div class="card-description">Configurar flujos de trabajo</div>
                </a>
                
                <a href="print_manager.php" class="quick-access-card">
                    <div class="card-icon">🖨️</div>
                    <div class="card-title">Imprimir QR</div>
                    <div class="card-description">Ver e imprimir códigos QR generados</div>
                </a>
                
                <a href="alerts.php" class="quick-access-card">
                    <div class="card-icon">⚠️</div>
                    <div class="card-title">Alertas</div>
                    <div class="card-description">Sistema de notificaciones</div>
                </a>
                
                <a href="../inventario/productos.php" class="quick-access-card">
                    <div class="card-icon">📦</div>
                    <div class="card-title">Inventario</div>
                    <div class="card-description">Gestión de productos</div>
                </a>
            </div>
        </div>
        
        <!-- Integración con Sistema Existente -->
        <div class="integration-section">
            <h2>🔗 Integración Sistema Inventario</h2>
            <div class="integration-grid">
                <div class="integration-card">
                    <div class="integration-icon">📦</div>
                    <div class="integration-content">
                        <h3>Productos con QR</h3>
                        <p>Productos que tienen códigos QR asignados</p>
                        <div class="integration-stats">
                            <span id="productsWithQR">Cargando...</span>
                        </div>
                        <a href="../inventario/productos.php?filter=with_qr" class="btn btn-outline">Ver Productos</a>
                    </div>
                </div>
                
                <div class="integration-card">
                    <div class="integration-icon">📈</div>
                    <div class="integration-content">
                        <h3>Movimientos QR vs Manual</h3>
                        <p>Comparación de movimientos generados</p>
                        <div class="integration-stats">
                            <span id="qrMovements">Cargando...</span>
                        </div>
                        <a href="reports.php?type=inventory_impact" class="btn btn-outline">Ver Reporte</a>
                    </div>
                </div>
                
                <div class="integration-card">
                    <div class="integration-icon">⚙️</div>
                    <div class="integration-content">
                        <h3>Configuración RBAC</h3>
                        <p>Permisos integrados con sistema existente</p>
                        <div class="integration-stats">
                            <span id="qrPermissions">Configurado</span>
                        </div>
                        <a href="../accesos/usuarios.php" class="btn btn-outline">Gestionar Usuarios</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actividad Reciente -->
        <div class="recent-activity-section">
            <h2>📈 Actividad Reciente</h2>
            <div class="activity-list" id="recent-activity">
                <div class="activity-item">
                    <div class="activity-icon">📱</div>
                    <div class="activity-content">
                        <div class="activity-title">Sistema QR inicializado</div>
                        <div class="activity-time">Ahora</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Generate QR Modal -->
    <div id="generateModal" class="qr-modal-overlay" style="display: none;">
        <div class="qr-modal-container">
            <div class="qr-modal-header">
                <h3 class="qr-modal-title">🔗 Generar Código QR</h3>
                <button type="button" class="qr-modal-close" onclick="closeGenerateModal()">×</button>
            </div>
            <div class="qr-modal-body">
                <form id="generateForm">
                    <div class="qr-form-group">
                        <label class="qr-form-label">🏪 Almacén:</label>
                        <select id="warehouseSelect" class="qr-select" onchange="loadProductsForWarehouse()">
                            <option value="">Seleccionar almacén...</option>
                        </select>
                    </div>
                    <div class="qr-form-group">
                        <label class="qr-form-label">📦 Producto:</label>
                        <select id="productSelect" class="qr-select" disabled>
                            <option value="">Primero selecciona un almacén...</option>
                        </select>
                    </div>
                    <div class="qr-form-group">
                        <label class="qr-form-label">🔢 Cantidad de códigos QR:</label>
                        <input type="number" id="quantityInput" class="qr-form-input" value="1" min="1" max="100" 
                               placeholder="¿Cuántos códigos generar?">
                        <small style="color: var(--text-secondary); font-size: 12px; margin-top: var(--space-xs); display: block;">
                            ℹ️ Puedes generar múltiples códigos QR del mismo producto
                        </small>
                    </div>
                    <div class="qr-modal-actions">
                        <button type="button" class="btn-qr-secondary" onclick="closeGenerateModal()">
                            ❌ Cancelar
                        </button>
                        <button type="submit" class="btn-qr-primary">
                            ✨ Generar QR
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Dashboard functionality
        document.addEventListener('DOMContentLoaded', function() {
            loadIntegrationData();
            loadRecentActivity();
            
            // Auto-show modal if producto_id is provided
            <?php if ($producto_info): ?>
                setTimeout(() => {
                    showGenerateModalForProduct(<?php echo json_encode($producto_info); ?>);
                }, 500);
            <?php endif; ?>
        });

        async function loadIntegrationData() {
            try {
                // Load products with QR
                const qrResponse = await fetch('/qr/api/query.php?type=qr_stats');
                if (qrResponse.ok) {
                    const qrData = await qrResponse.json();
                    document.getElementById('productsWithQR').textContent = 
                        qrData.success ? (qrData.data?.total_qr_codes || 0) + ' productos' : 'Error';
                }

                // Load QR vs Manual movements
                const movResponse = await fetch('/qr/api/reports.php?type=inventory_impact');
                if (movResponse.ok) {
                    const movData = await movResponse.json();
                    if (movData.success && movData.data.source_comparison) {
                        const qrMovs = movData.data.source_comparison.find(s => s.source === 'QR');
                        document.getElementById('qrMovements').textContent = 
                            qrMovs ? qrMovs.movement_count + ' movimientos QR' : '0 movimientos QR';
                    }
                }
            } catch (error) {
                console.error('Error loading integration data:', error);
            }
        }

        async function loadRecentActivity() {
            try {
                const response = await fetch('/qr/api/query.php?type=recent_activity&limit=5');
                const result = await response.json();
                
                if (result.success && result.data.recent_activity.length > 0) {
                    const activityList = document.getElementById('recent-activity');
                    activityList.innerHTML = '';
                    
                    result.data.recent_activity.forEach(activity => {
                        const timeAgo = getTimeAgo(activity.scanned_at);
                        const item = document.createElement('div');
                        item.className = 'activity-item';
                        item.innerHTML = `
                            <div class="activity-icon">📱</div>
                            <div class="activity-content">
                                <div class="activity-title">${activity.action_performed} - ${activity.producto_name || 'Sin producto'}</div>
                                <div class="activity-time">${activity.user_name} • ${timeAgo}</div>
                            </div>
                        `;
                        activityList.appendChild(item);
                    });
                }
            } catch (error) {
                console.error('Error loading recent activity:', error);
            }
        }

        async function showGenerateModal() {
            const modal = document.getElementById('generateModal');
            modal.style.display = 'flex'; // Cambiado a flex para mejor centrado
            
            // Cargar almacenes primero
            await loadWarehouses();
        }

        async function loadWarehouses() {
            try {
                console.log('Cargando almacenes...');
                
                // Usar la API real de almacenes
                const response = await fetch('api/almacenes.php');
                
                const warehouseSelect = document.getElementById('warehouseSelect');
                warehouseSelect.innerHTML = '<option value="">Seleccionar almacén...</option>';
                
                if (response.ok) {
                    const data = await response.json();
                    console.log('Respuesta almacenes:', data);
                    
                    if (data.success && data.data && data.data.length > 0) {
                        data.data.forEach(warehouse => {
                            const option = document.createElement('option');
                            option.value = warehouse.id;
                            option.textContent = `${warehouse.codigo} - ${warehouse.nombre}`;
                            warehouseSelect.appendChild(option);
                        });
                        console.log(`Cargados ${data.data.length} almacenes reales`);
                    } else {
                        console.log('No se encontraron almacenes activos');
                        warehouseSelect.innerHTML = '<option value="">No hay almacenes disponibles</option>';
                    }
                } else {
                    console.error('Error en respuesta de almacenes:', response.status);
                    warehouseSelect.innerHTML = '<option value="">Error al cargar almacenes</option>';
                }
                
            } catch (error) {
                console.error('Error loading warehouses:', error);
                const warehouseSelect = document.getElementById('warehouseSelect');
                warehouseSelect.innerHTML = '<option value="">Error de conexión</option>';
                
                if (typeof mostrarNotificacion === 'function') {
                    mostrarNotificacion('Error al cargar almacenes', 'error');
                }
            }
        }

        async function loadProductsForWarehouse() {
            const warehouseId = document.getElementById('warehouseSelect').value;
            const productSelect = document.getElementById('productSelect');
            
            if (!warehouseId) {
                productSelect.innerHTML = '<option value="">Primero selecciona un almacén...</option>';
                productSelect.disabled = true;
                return;
            }

            try {
                console.log(`Cargando productos para almacén: ${warehouseId}`);
                productSelect.innerHTML = '<option value="">Cargando productos...</option>';
                productSelect.disabled = true;

                // Usar la API real de productos con el almacén seleccionado
                const response = await fetch(`api/productos.php?almacen_id=${warehouseId}&limit=200`);
                
                if (response.ok) {
                    const data = await response.json();
                    console.log('Respuesta productos:', data);
                    
                    productSelect.innerHTML = '<option value="">Seleccionar producto...</option>';
                    
                    if (data.success && data.data && data.data.length > 0) {
                        data.data.forEach(product => {
                            const option = document.createElement('option');
                            option.value = product.id;
                            
                            // Mostrar SKU, nombre y stock si está disponible
                            let displayText = `${product.sku || 'Sin SKU'} - ${product.nombre}`;
                            if (product.stock_actual !== undefined) {
                                displayText += ` (Stock: ${product.stock_actual})`;
                            }
                            
                            option.textContent = displayText;
                            productSelect.appendChild(option);
                        });
                        productSelect.disabled = false;
                        console.log(`Cargados ${data.data.length} productos reales`);
                    } else {
                        console.log('No se encontraron productos para este almacén');
                        productSelect.innerHTML = '<option value="">No hay productos en este almacén</option>';
                        productSelect.disabled = true;
                    }
                } else {
                    console.error('Error en respuesta de productos:', response.status);
                    productSelect.innerHTML = '<option value="">Error al cargar productos</option>';
                    productSelect.disabled = true;
                }
                
            } catch (error) {
                console.error('Error loading products:', error);
                productSelect.innerHTML = '<option value="">Error de conexión</option>';
                productSelect.disabled = true;
                
                if (typeof mostrarNotificacion === 'function') {
                    mostrarNotificacion('Error al cargar productos', 'error');
                }
            }
        }

        async function showGenerateModalForProduct(productInfo) {
            // Update modal title to show specific product
            document.querySelector('#generateModal h3').innerHTML = 
                `🔗 Generar Código QR para: <strong>${productInfo.nombre}</strong>`;
            
            const modal = document.getElementById('generateModal');
            modal.style.display = 'block';
            
            // Pre-populate product select
            const productSelect = document.getElementById('productSelect');
            productSelect.innerHTML = `<option value="${productInfo.id}" selected>${productInfo.sku || ''} - ${productInfo.nombre}</option>`;
            productSelect.disabled = true; // Disable since product is already selected
            
            // Load warehouses
            try {
                const warehousesRes = await fetch('../inventario/api/almacenes.php');
                if (warehousesRes.ok) {
                    const warehouses = await warehousesRes.json();
                    const warehouseSelect = document.getElementById('warehouseSelect');
                    warehouseSelect.innerHTML = '<option value="">Seleccionar almacén...</option>';
                    
                    if (warehouses.success && warehouses.data) {
                        warehouses.data.forEach(warehouse => {
                            const option = document.createElement('option');
                            option.value = warehouse.id;
                            option.textContent = `${warehouse.codigo} - ${warehouse.nombre}`;
                            warehouseSelect.appendChild(option);
                        });
                    }
                }
            } catch (error) {
                console.error('Error loading warehouses:', error);
            }
        }

        function closeGenerateModal() {
            document.getElementById('generateModal').style.display = 'none';
            
            // Reset modal to default state
            document.querySelector('#generateModal h3').innerHTML = '🔗 Generar Código QR';
            document.getElementById('productSelect').disabled = false;
        }

        document.getElementById('generateForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const productId = document.getElementById('productSelect').value;
            const warehouseId = document.getElementById('warehouseSelect').value;
            const quantity = parseInt(document.getElementById('quantityInput').value) || 1;
            
            if (!productId || !warehouseId) {
                if (typeof mostrarNotificacion === 'function') {
                    mostrarNotificacion('Por favor seleccione almacén y producto', 'warning');
                } else {
                    alert('Por favor seleccione almacén y producto');
                }
                return;
            }
            
            if (quantity < 1 || quantity > 100) {
                if (typeof mostrarNotificacion === 'function') {
                    mostrarNotificacion('La cantidad debe estar entre 1 y 100', 'warning');  
                } else {
                    alert('La cantidad debe estar entre 1 y 100');
                }
                return;
            }
            
            // Obtener token CSRF
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            
            // Mostrar indicador de carga
            const submitBtn = document.querySelector('#generateForm button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '⏳ Generando...';
            submitBtn.disabled = true;
            
            try {
                // Generar múltiples códigos QR si se solicita
                const results = [];
                
                for (let i = 1; i <= quantity; i++) {
                    submitBtn.innerHTML = `⏳ Generando ${i}/${quantity}...`;
                    
                    const response = await fetch('api/generate.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            producto_id: productId,
                            almacen_id: warehouseId,
                            quantity_index: i,
                            total_quantity: quantity,
                            include_qr_url: true
                    })
                });
                
                    const result = await response.json();
                    
                    if (result.success) {
                        results.push(result);
                    } else {
                        console.error(`Error generando QR ${i}:`, result.error);
                        results.push({ success: false, error: result.error || 'Error desconocido' });
                    }
                    
                    // Pequeña pausa entre generaciones para no sobrecargar el servidor
                    if (i < quantity) {
                        await new Promise(resolve => setTimeout(resolve, 500));
                    }
                }
                
                // Procesar resultados
                const successful = results.filter(r => r.success);
                const failed = results.filter(r => !r.success);
                
                let message = '';
                if (successful.length > 0) {
                    if (quantity === 1) {
                        message = `¡Código QR generado exitosamente!\nCódigo: ${successful[0].qr_content}`;
                    } else {
                        message = `¡${successful.length} código(s) QR generado(s) exitosamente!`;
                        if (failed.length > 0) {
                            message += `\n⚠️ ${failed.length} código(s) fallaron.`;
                        }
                    }
                    
                    if (typeof mostrarNotificacion === 'function') {
                        mostrarNotificacion(message, 'success');
                    } else {
                        alert(message);
                    }
                    
                    closeGenerateModal();
                    
                    // Refresh stats
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    const errorMsg = failed.length > 0 ? failed[0].error : 'Error desconocido';
                    if (typeof mostrarNotificacion === 'function') {
                        mostrarNotificacion(`Error generando códigos QR: ${errorMsg}`, 'error');
                    } else {
                        alert('Error: ' + errorMsg);
                    }
                }
                
            } catch (error) {
                console.error('Error generating QR:', error);
                if (typeof mostrarNotificacion === 'function') {
                    mostrarNotificacion('Error de conexión al generar códigos QR', 'error');
                } else {
                    alert('Error de conexión');
                }
            } finally {
                // Restaurar botón
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });

        // Close modal when clicking outside
        document.getElementById('generateModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeGenerateModal();
            }
        });

        function getTimeAgo(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const seconds = Math.floor((now - date) / 1000);
            
            if (seconds < 60) return 'hace ' + seconds + ' segundos';
            const minutes = Math.floor(seconds / 60);
            if (minutes < 60) return 'hace ' + minutes + ' minutos';
            const hours = Math.floor(minutes / 60);
            if (hours < 24) return 'hace ' + hours + ' horas';
            const days = Math.floor(hours / 24);
            return 'hace ' + days + ' días';
        }
    </script>

    <!-- Sistema de notificaciones -->
    <div id="notification-container"></div>
    
    <!-- JavaScript del sistema de notificaciones -->
    <script src="../inventario/productos.js"></script>
</body>
</html>