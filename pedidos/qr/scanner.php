<?php
/**
 * Scanner QR - Interfaz Principal
 * Sequoia Speed - Sistema QR
 */

defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once dirname(__DIR__) . '/config_secure.php';
require_once dirname(__DIR__) . '/accesos/middleware/AuthMiddleware.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/xss_helper.php';
require_once __DIR__ . '/security_headers.php';

// Establecer headers de seguridad
setSecurityHeaders(true);

$auth = new AuthMiddleware($conn);
$current_user = $auth->requirePermission('qr', 'leer');

// Obtener almacenes disponibles
$almacenes_query = "SELECT id, nombre, codigo FROM almacenes WHERE activo = 1 ORDER BY nombre";
$almacenes_result = $conn->query($almacenes_query);
$almacenes = [];
while ($row = $almacenes_result->fetch_assoc()) {
    // Escapar datos para prevenir XSS
    $row['nombre'] = escape_html($row['nombre']);
    $row['codigo'] = escape_html($row['codigo']);
    $almacenes[] = $row;
}

// Obtener workflows disponibles
$workflows_query = "SELECT id, workflow_name, workflow_type, config_data 
                   FROM qr_workflow_config WHERE active = 1 ORDER BY workflow_type, workflow_name";
$workflows_result = $conn->query($workflows_query);
$workflows = [];
while ($row = $workflows_result->fetch_assoc()) {
    $row['config_data'] = json_decode($row['config_data'], true);
    // Escapar datos para prevenir XSS
    $row['workflow_name'] = escape_html($row['workflow_name']);
    $row['workflow_type'] = escape_html($row['workflow_type']);
    $workflows[] = $row;
}

// Obtener permisos del usuario usando el sistema acc_
$user_permissions_query = "SELECT p.tipo_permiso
                          FROM usuarios u
                          JOIN acc_usuario_roles ur ON u.id = ur.usuario_id
                          JOIN acc_rol_permisos rp ON ur.rol_id = rp.rol_id
                          JOIN acc_permisos p ON rp.permiso_id = p.id
                          JOIN acc_modulos m ON p.modulo_id = m.id
                          WHERE u.id = ? AND m.nombre = 'qr' AND ur.activo = 1 AND rp.activo = 1";
$stmt = $conn->prepare($user_permissions_query);
$stmt->bind_param('i', $current_user['id']);
$stmt->execute();
$permissions_result = $stmt->get_result();
$user_permissions = [];
while ($row = $permissions_result->fetch_assoc()) {
    $user_permissions[] = $row['tipo_permiso'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📸 Scanner QR - Sequoia Speed</title>
    <?php echo csrfMetaTag(); ?>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📸</text></svg>">
    
    <!-- Usar el mismo CSS que inventario/productos.php -->
    <link rel="stylesheet" href="../inventario/productos.css">
    <link rel="stylesheet" href="../notifications/notifications.css">
    
    <!-- Scanner JS Library -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    
    <style>
        /* Estilos específicos para el scanner QR que complementan productos.css */
        
        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: var(--space-md);
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }
        
        .stats-section {
            margin-bottom: var(--space-lg);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--space-md);
        }
        
        #qr-reader {
            border-radius: var(--border-radius);
            overflow: hidden;
            margin-bottom: var(--space-lg);
            box-shadow: var(--shadow-md);
            background: var(--bg-secondary);
            width: 100%;
        }
        
        .scanner-section {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: var(--space-xl);
            margin-bottom: var(--space-lg);
        }
        
        .scanner-controls {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--space-md);
            margin-bottom: var(--space-lg);
        }
        
        .manual-input {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: var(--space-md);
            margin-bottom: var(--space-lg);
            display: none;
        }
        
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: var(--space-sm);
            margin-bottom: var(--space-lg);
        }
        
        .action-btn {
            padding: var(--space-sm) var(--space-md);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all var(--transition-fast);
            text-align: center;
            font-weight: 500;
            font-size: 13px;
            color: var(--text-primary);
        }
        
        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }
        
        .action-btn.entrada {
            background: var(--stock-alto);
            border-color: var(--stock-alto);
            color: white;
        }
        
        .action-btn.salida {
            background: var(--stock-bajo);
            border-color: var(--stock-bajo);
            color: white;
        }
        
        .action-btn.conteo {
            background: var(--color-primary);
            border-color: var(--color-primary);
            color: white;
        }
        
        .action-btn.consulta {
            background: var(--stock-medio);
            border-color: var(--stock-medio);
            color: white;
        }
        
        .loading {
            text-align: center;
            padding: var(--space-xl);
            display: none;
            color: var(--text-secondary);
        }
        
        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--border-color);
            border-top-color: var(--color-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto var(--space-md);
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .scan-result {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: var(--space-md);
            margin-bottom: var(--space-md);
            border-left: 4px solid;
        }
        
        .scan-result.success {
            border-left-color: var(--stock-alto);
        }
        
        .scan-result.error {
            border-left-color: var(--stock-bajo);
        }
        
        .scan-result.info {
            border-left-color: var(--color-primary);
        }
        
        .result-title {
            font-weight: 600;
            margin-bottom: var(--space-xs);
            color: var(--text-primary);
        }
        
        .result-details {
            color: var(--text-secondary);
            font-size: 13px;
        }
        
        /* Estilos para los selectores del scanner usando la estética de productos.css */
        .scanner-select, .form-control {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: var(--border-radius);
            padding: var(--space-sm) var(--space-md);
            font-size: 13px;
            width: 100%;
        }
        
        .scanner-select:focus, .form-control:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(88, 166, 255, 0.1);
        }
        
        .scanner-input {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: var(--border-radius);
            padding: var(--space-sm) var(--space-md);
            font-size: 13px;
            width: 100%;
        }
        
        .scanner-input:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(88, 166, 255, 0.1);
        }
        
        /* Botón específico para scanner */
        .btn-scanner {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            border: none;
            padding: var(--space-sm) var(--space-md);
            border-radius: var(--border-radius);
            font-size: 13px;
            font-weight: 500;
            transition: all var(--transition-fast);
            cursor: pointer;
        }
        
        .btn-scanner:hover {
            background: linear-gradient(135deg, #16a34a, #15803d);
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }
        
        .form-group {
            margin-bottom: var(--space-md);
        }
        
        .form-group label {
            display: block;
            margin-bottom: var(--space-xs);
            font-weight: 500;
            color: var(--text-primary);
            font-size: 13px;
        }
        
        .input-group {
            display: flex;
            gap: var(--space-xs);
        }
        
        .input-group .scanner-input {
            flex: 1;
        }
        
        @media (max-width: 768px) {
            .action-buttons {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .scanner-controls {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">📸 Scanner QR</h1>
                    <div class="breadcrumb">
                        <a href="../listar_pedidos.php">🏠 Inicio</a>
                        <span>/</span>
                        <a href="../inventario/productos.php">📦 Inventario</a>
                        <span>/</span>
                        <a href="index.php">📱 QR</a>
                        <span>/</span>
                        <span>📸 Scanner</span>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="index.php" class="btn btn-secondary">
                        ⬅️ Volver a QR
                    </a>
                    <a href="generator.php" class="btn btn-primary">
                        ➕ Generar QR
                    </a>
                </div>
            </div>
        </header>

        <!-- Controls Section -->
        <div class="scanner-section">
            <h2 class="section-title">⚙️ Configuración</h2>
            <div class="scanner-controls">
                <div class="form-group">
                    <label for="almacen-select" class="form-label">📦 Almacén:</label>
                    <select id="almacen-select" class="scanner-select">
                        <option value="">Seleccionar almacén</option>
                        <?php foreach ($almacenes as $almacen): ?>
                            <option value="<?= $almacen['id'] ?>"><?= htmlspecialchars($almacen['codigo'] . ' - ' . $almacen['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="workflow-select" class="form-label">📋 Flujo de trabajo:</label>
                    <select id="workflow-select" class="scanner-select">
                        <option value="">Manual</option>
                        <?php foreach ($workflows as $workflow): ?>
                            <option value="<?= $workflow['id'] ?>" data-type="<?= $workflow['workflow_type'] ?>">
                                <?= htmlspecialchars($workflow['workflow_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">📱 Modo de Escaneo:</label>
                    <div class="input-group">
                        <button id="toggle-camera" class="btn-scanner">
                            📸 Iniciar Cámara
                        </button>
                        <button id="toggle-manual" class="btn btn-outline">
                            ⌨️ Manual
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scanner Area -->
        <div class="scanner-section">
            <h2 class="section-title">📸 Área de Escaneo</h2>
            <div id="qr-reader"></div>
            
            <!-- Manual Input -->
            <div id="manual-input" class="manual-input">
                <div class="form-group">
                    <label class="form-label">⌨️ Entrada Manual:</label>
                    <div class="input-group">
                        <input type="text" id="manual-qr-input" class="scanner-input" placeholder="Ingrese código QR manualmente">
                        <button id="manual-scan-btn" class="btn-scanner">
                            🔍 Procesar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="scanner-section">
            <h2 class="section-title">🎯 Acciones Disponibles</h2>
            <div class="action-buttons" id="action-buttons">
                <?php if (in_array('leer', $user_permissions)): ?>
                    <button class="action-btn consulta" data-action="consulta">
                        ℹ️ Consultar
                    </button>
                <?php endif; ?>
                
                <?php if (in_array('crear', $user_permissions) || in_array('actualizar', $user_permissions)): ?>
                    <button class="action-btn entrada" data-action="entrada">
                        ⬇️ Entrada
                    </button>
                    <button class="action-btn salida" data-action="salida">
                        ⬆️ Salida
                    </button>
                    <button class="action-btn conteo" data-action="conteo">
                        📊 Conteo
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Loading -->
        <div id="loading" class="loading">
            <div class="loading-spinner"></div>
            <p>Procesando código QR...</p>
        </div>
        
        <!-- Results -->
        <div id="scan-results"></div>

        <!-- Session Stats -->
        <div class="stats-section" id="session-stats" style="display: none;">
            <h2 class="section-title">📊 Estadísticas de Sesión</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📱</div>
                    <div class="stat-content">
                        <div class="stat-number" id="stat-scans">0</div>
                        <div class="stat-label">Escaneos</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">⬇️</div>
                    <div class="stat-content">
                        <div class="stat-number" id="stat-entries">0</div>
                        <div class="stat-label">Entradas</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">⬆️</div>
                    <div class="stat-content">
                        <div class="stat-number" id="stat-exits">0</div>
                        <div class="stat-label">Salidas</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-content">
                        <div class="stat-number" id="stat-counts">0</div>
                        <div class="stat-label">Conteos</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Scanner Script -->
    <script>
        // Variables globales
        let html5QrCode = null;
        let isScanning = false;
        let currentQRCode = '';
        let sessionStats = {
            scans: 0,
            entries: 0,
            exits: 0,
            counts: 0
        };
        
        // Configuración del usuario
        const userConfig = {
            id: <?= $current_user['id'] ?>,
            nombre: "<?= htmlspecialchars($current_user['nombre']) ?>",
            usuario: "<?= htmlspecialchars($current_user['usuario']) ?>",
            permissions: <?= json_encode($user_permissions) ?>
        };
        
        // Workflows disponibles
        const workflows = <?= json_encode($workflows) ?>;
        
        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            initializeScanner();
            setupEventListeners();
        });
        
        function initializeScanner() {
            html5QrCode = new Html5Qrcode("qr-reader");
        }
        
        function setupEventListeners() {
            // Toggle camera
            document.getElementById('toggle-camera').addEventListener('click', toggleCamera);
            
            // Toggle manual input
            document.getElementById('toggle-manual').addEventListener('click', toggleManualInput);
            
            // Manual scan
            document.getElementById('manual-scan-btn').addEventListener('click', processManualInput);
            
            // Enter key for manual input
            document.getElementById('manual-qr-input').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    processManualInput();
                }
            });
            
            // Action buttons
            document.querySelectorAll('.action-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const action = this.dataset.action;
                    if (currentQRCode) {
                        processQRCode(currentQRCode, action);
                    } else {
                        showMessage('Primero escanee un código QR', 'warning');
                    }
                });
            });
            
            // Workflow selector
            document.getElementById('workflow-select').addEventListener('change', handleWorkflowChange);
        }
        
        async function toggleCamera() {
            const button = document.getElementById('toggle-camera');
            
            if (!isScanning) {
                try {
                    button.innerHTML = '⏳ Iniciando...';
                    button.disabled = true;
                    
                    await html5QrCode.start(
                        { facingMode: "environment" },
                        {
                            fps: 10,
                            qrbox: { width: 250, height: 250 }
                        },
                        onScanSuccess,
                        onScanFailure
                    );
                    
                    isScanning = true;
                    button.innerHTML = '📹 Detener Cámara';
                    button.className = 'btn btn-outline';
                    
                    // Hide manual input
                    document.getElementById('manual-input').style.display = 'none';
                    
                } catch (err) {
                    console.error('Error starting camera:', err);
                    showMessage('Error al iniciar la cámara: ' + err.message, 'error');
                    button.innerHTML = '📸 Iniciar Cámara';
                } finally {
                    button.disabled = false;
                }
            } else {
                try {
                    await html5QrCode.stop();
                    isScanning = false;
                    button.innerHTML = '📸 Iniciar Cámara';
                    button.className = 'btn-scanner';
                } catch (err) {
                    console.error('Error stopping camera:', err);
                }
            }
        }
        
        function toggleManualInput() {
            const manualDiv = document.getElementById('manual-input');
            const isVisible = manualDiv.style.display !== 'none';
            
            manualDiv.style.display = isVisible ? 'none' : 'block';
            
            if (!isVisible) {
                document.getElementById('manual-qr-input').focus();
            }
        }
        
        function processManualInput() {
            const input = document.getElementById('manual-qr-input');
            const qrCode = input.value.trim();
            
            if (qrCode) {
                onScanSuccess(qrCode);
                input.value = '';
            } else {
                showMessage('Ingrese un código QR válido', 'warning');
            }
        }
        
        function onScanSuccess(decodedText, decodedResult) {
            if (decodedText !== currentQRCode) {
                currentQRCode = decodedText;
                showQRInfo(decodedText);
                
                // Auto-process if workflow is selected
                const workflowSelect = document.getElementById('workflow-select');
                if (workflowSelect.value) {
                    const selectedWorkflow = workflows.find(w => w.id == workflowSelect.value);
                    if (selectedWorkflow) {
                        autoProcessWithWorkflow(decodedText, selectedWorkflow);
                    }
                }
            }
        }
        
        function onScanFailure(error) {
            // Ignore scan failures - they're too frequent
        }
        
        async function showQRInfo(qrCode) {
            try {
                showLoading(true);
                
                const response = await fetch('api/scan.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        qr_content: qrCode,
                        action: 'consulta',
                        scan_method: 'camera_web'
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    displayQRInfo(result);
                } else {
                    showMessage('Error: ' + result.error, 'error');
                }
                
            } catch (error) {
                console.error('Error getting QR info:', error);
                showMessage('Error de conexión', 'error');
            } finally {
                showLoading(false);
            }
        }
        
        function displayQRInfo(result) {
            const { qr_data, contextual_data, suggestions } = result;
            
            let html = `
                <div class="scan-result scan-success">
                    <h5><i class="bi bi-qr-code"></i> Código QR Detectado</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Código:</strong> ${qr_data.qr_content}</p>
                            <p><strong>Tipo:</strong> ${qr_data.entity_type}</p>
                            <p><strong>Escaneos:</strong> ${qr_data.scan_count}</p>
                        </div>
                        <div class="col-md-6">
                            ${contextual_data.producto_nombre ? `<p><strong>Producto:</strong> ${contextual_data.producto_nombre}</p>` : ''}
                            ${contextual_data.stock_actual !== undefined ? `<p><strong>Stock:</strong> ${contextual_data.stock_actual}</p>` : ''}
                            ${contextual_data.ubicacion_fisica ? `<p><strong>Ubicación:</strong> ${contextual_data.ubicacion_fisica}</p>` : ''}
                        </div>
                    </div>
                    
                    ${suggestions && suggestions.length > 0 ? `
                        <div class="mt-3">
                            <strong>Acciones sugeridas:</strong>
                            <div class="d-flex gap-2 mt-2 flex-wrap">
                                ${suggestions.map(s => `
                                    <button class="btn btn-sm btn-outline-primary" onclick="processQRCode('${qr_data.qr_content}', '${s.action}')">
                                        <i class="bi bi-${s.icon}"></i> ${s.label}
                                    </button>
                                `).join('')}
                            </div>
                        </div>
                    ` : ''}
                </div>
            `;
            
            document.getElementById('scan-results').innerHTML = html;
            document.getElementById('session-stats').style.display = 'block';
        }
        
        async function processQRCode(qrCode, action) {
            // Validate required fields based on action
            if ((action === 'entrada' || action === 'salida' || action === 'conteo') && !document.getElementById('almacen-select').value) {
                showMessage('Seleccione un almacén antes de continuar', 'warning');
                return;
            }
            
            let quantity = 1;
            if (action === 'entrada' || action === 'salida' || action === 'conteo') {
                quantity = prompt(`Ingrese la cantidad para ${action}:`, '1');
                if (!quantity || isNaN(quantity) || parseInt(quantity) <= 0) {
                    showMessage('Cantidad inválida', 'warning');
                    return;
                }
                quantity = parseInt(quantity);
            }
            
            try {
                showLoading(true);
                
                const requestData = {
                    qr_content: qrCode,
                    action: action,
                    scan_method: 'camera_web',
                    quantity: quantity,
                    location: document.getElementById('almacen-select').selectedOptions[0]?.text || '',
                    workflow_type: document.getElementById('workflow-select').value || null
                };
                
                const response = await fetch('api/scan.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(requestData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showProcessResult(result);
                    updateSessionStats(action);
                } else {
                    showMessage('Error: ' + result.error, 'error');
                }
                
            } catch (error) {
                console.error('Error processing QR:', error);
                showMessage('Error de conexión', 'error');
            } finally {
                showLoading(false);
            }
        }
        
        function showProcessResult(result) {
            let html = `
                <div class="scan-result scan-success">
                    <h5><i class="bi bi-check-circle"></i> ${result.message}</h5>
                    <p><strong>Acción:</strong> ${result.action_performed}</p>
                    <p><strong>Tiempo de procesamiento:</strong> ${result.processing_time_ms}ms</p>
                    
                    ${result.movement ? `
                        <div class="mt-2">
                            <strong>Movimiento creado:</strong>
                            <p>Tipo: ${result.movement.tipo_movimiento} | Cantidad: ${result.movement.cantidad}</p>
                            <p>Stock anterior: ${result.movement.cantidad_anterior} → Nuevo: ${result.movement.cantidad_nueva}</p>
                        </div>
                    ` : ''}
                    
                    ${result.stock_info ? `
                        <div class="mt-2">
                            <strong>Stock actual:</strong> ${result.stock_info.stock_actual}
                        </div>
                    ` : ''}
                </div>
            `;
            
            document.getElementById('scan-results').innerHTML = html;
        }
        
        function updateSessionStats(action) {
            sessionStats.scans++;
            
            switch(action) {
                case 'entrada': sessionStats.entries++; break;
                case 'salida': sessionStats.exits++; break;
                case 'conteo': sessionStats.counts++; break;
            }
            
            document.getElementById('stat-scans').textContent = sessionStats.scans;
            document.getElementById('stat-entries').textContent = sessionStats.entries;
            document.getElementById('stat-exits').textContent = sessionStats.exits;
            document.getElementById('stat-counts').textContent = sessionStats.counts;
        }
        
        function handleWorkflowChange() {
            const select = document.getElementById('workflow-select');
            const selectedWorkflow = workflows.find(w => w.id == select.value);
            
            if (selectedWorkflow) {
                showMessage(`Workflow seleccionado: ${selectedWorkflow.workflow_name}`, 'info');
            }
        }
        
        function autoProcessWithWorkflow(qrCode, workflow) {
            // Auto-process based on workflow configuration
            const config = workflow.config_data;
            
            if (config.auto_create_movement || config.auto_create_adjustment) {
                const defaultAction = workflow.workflow_type;
                processQRCode(qrCode, defaultAction);
            }
        }
        
        function showMessage(message, type = 'info') {
            const alertClass = {
                'success': 'alert-success',
                'error': 'alert-danger',
                'warning': 'alert-warning',
                'info': 'alert-info'
            }[type] || 'alert-info';
            
            const html = `
                <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            document.getElementById('scan-results').innerHTML = html;
        }
        
        function showLoading(show) {
            document.getElementById('loading').style.display = show ? 'block' : 'none';
        }
    </script>
</body>
</html>