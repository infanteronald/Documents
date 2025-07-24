<?php
/**
 * Generador de Códigos QR - Página Principal
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
$current_user = $auth->requirePermission('qr', 'crear');

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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔗 Generador QR - Sequoia Speed</title>
    <?php echo csrfMetaTag(); ?>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔗</text></svg>">
    <link rel="stylesheet" href="../inventario/productos.css">
    <link rel="stylesheet" href="../notifications/notifications.css">
    <link rel="stylesheet" href="assets/css/qr.css">
    
    <style>
        .generator-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--space-lg);
        }
        
        .generator-form {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: var(--space-lg);
            margin-bottom: var(--space-lg);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 200px auto;
            gap: var(--space-md);
            align-items: end;
            margin-bottom: var(--space-md);
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-label {
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 500;
            margin-bottom: var(--space-xs);
        }
        
        .form-select, .form-input {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: var(--space-sm) var(--space-md);
            color: var(--text-primary);
            font-size: 14px;
        }
        
        .form-select:focus, .form-input:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 2px rgba(88, 166, 255, 0.1);
        }
        
        .generate-btn {
            background: var(--color-primary);
            color: var(--bg-primary);
            border: none;
            padding: var(--space-sm) var(--space-lg);
            border-radius: var(--border-radius);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition-fast);
            height: fit-content;
        }
        
        .generate-btn:hover {
            background: #4a9eff;
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }
        
        .generate-btn:disabled {
            background: var(--text-secondary);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .results-section {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: var(--space-lg);
            margin-bottom: var(--space-lg);
            display: none;
        }
        
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: var(--space-md);
            margin-top: var(--space-md);
        }
        
        .qr-result-card {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: var(--space-md);
            text-align: center;
        }
        
        .qr-code-image {
            background: white;
            border-radius: var(--border-radius);
            padding: var(--space-sm);
            margin: var(--space-sm) 0;
            display: inline-block;
        }
        
        .qr-info {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: var(--space-xs);
        }
        
        .progress-bar {
            background: var(--bg-tertiary);
            border-radius: var(--border-radius);
            height: 20px;
            overflow: hidden;
            margin: var(--space-md) 0;
            display: none;
        }
        
        .progress-fill {
            background: var(--color-primary);
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: 500;
        }
        
        .download-section {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: var(--space-lg);
            text-align: center;
            display: none;
        }
        
        .download-btn {
            background: var(--color-success);
            color: white;
            border: none;
            padding: var(--space-sm) var(--space-lg);
            border-radius: var(--border-radius);
            margin: 0 var(--space-xs);
            cursor: pointer;
            transition: all var(--transition-fast);
        }
        
        .download-btn:hover {
            background: #2d8659;
            transform: translateY(-1px);
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .results-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="generator-container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">🔗 Generador de Códigos QR</h1>
                    <div class="breadcrumb">
                        <a href="../listar_pedidos.php">🏠 Inicio</a>
                        <span>/</span>
                        <a href="index.php">📱 QR</a>
                        <span>/</span>
                        <span>🔗 Generar</span>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="index.php" class="btn btn-secondary">
                        ← Volver al Dashboard
                    </a>
                    <a href="scanner.php" class="btn btn-primary">
                        📸 Scanner QR
                    </a>
                </div>
            </div>
        </header>

        <!-- Formulario de Generación -->
        <section class="generator-form">
            <h2>📝 Configuración de Generación</h2>
            <form id="generatorForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">🏪 Almacén:</label>
                        <select id="almacenSelect" class="form-select" required>
                            <option value="">Seleccionar almacén...</option>
                            <?php foreach ($almacenes as $almacen): ?>
                                <option value="<?= $almacen['id'] ?>"><?= htmlspecialchars($almacen['codigo'] . ' - ' . $almacen['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">📦 Producto:</label>
                        <select id="productoSelect" class="form-select" required disabled>
                            <option value="">Primero selecciona un almacén...</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">🔢 Cantidad:</label>
                        <input type="number" id="cantidadInput" class="form-input" value="1" min="1" max="500" required>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="generate-btn">
                            ✨ Generar QR
                        </button>
                    </div>
                </div>
                
                <div class="form-group" style="max-width: 400px;">
                    <label class="form-label">📝 Notas (opcional):</label>
                    <input type="text" id="notasInput" class="form-input" placeholder="Información adicional para los códigos QR">
                </div>
            </form>
        </section>

        <!-- Barra de Progreso -->
        <div class="progress-bar" id="progressBar">
            <div class="progress-fill" id="progressFill">0%</div>
        </div>

        <!-- Resultados -->
        <section class="results-section" id="resultsSection">
            <h2>📋 Códigos QR Generados</h2>
            <div class="results-grid" id="resultsGrid">
                <!-- Los resultados se cargarán aquí -->
            </div>
        </section>

        <!-- Sección de Descarga -->
        <section class="download-section" id="downloadSection">
            <h3>💾 Descargar Códigos QR</h3>
            <p>Exporta los códigos QR generados en diferentes formatos</p>
            <div>
                <button class="download-btn" onclick="downloadPDF()">📄 Descargar PDF</button>
                <button class="download-btn" onclick="downloadZIP()">📦 Descargar ZIP</button>
                <button class="download-btn" onclick="printQRCodes()">🖨️ Imprimir</button>
            </div>
        </section>
    </div>

    <script>
        // Variables globales
        let generatedQRs = [];
        let currentGeneration = null;
        
        // Configuración del usuario
        const userConfig = {
            id: <?= $current_user['id'] ?>,
            nombre: "<?= htmlspecialchars($current_user['nombre']) ?>",
            usuario: "<?= htmlspecialchars($current_user['usuario']) ?>"
        };

        document.addEventListener('DOMContentLoaded', function() {
            setupEventListeners();
        });

        function setupEventListeners() {
            // Cambio de almacén
            document.getElementById('almacenSelect').addEventListener('change', loadProductsForWarehouse);
            
            // Envío del formulario
            document.getElementById('generatorForm').addEventListener('submit', handleFormSubmit);
        }

        async function loadProductsForWarehouse() {
            const almacenId = document.getElementById('almacenSelect').value;
            const productoSelect = document.getElementById('productoSelect');
            
            if (!almacenId) {
                productoSelect.innerHTML = '<option value="">Primero selecciona un almacén...</option>';
                productoSelect.disabled = true;
                return;
            }

            try {
                productoSelect.innerHTML = '<option value="">Cargando productos...</option>';
                productoSelect.disabled = true;

                const response = await fetch(`api/productos.php?almacen_id=${almacenId}&limit=500`);
                
                if (response.ok) {
                    const data = await response.json();
                    
                    productoSelect.innerHTML = '<option value="">Seleccionar producto...</option>';
                    
                    if (data.success && data.data && data.data.length > 0) {
                        data.data.forEach(product => {
                            const option = document.createElement('option');
                            option.value = product.id;
                            
                            let displayText = `${product.sku || 'Sin SKU'} - ${product.nombre}`;
                            if (product.stock_actual !== undefined) {
                                displayText += ` (Stock: ${product.stock_actual})`;
                            }
                            
                            option.textContent = displayText;
                            productoSelect.appendChild(option);
                        });
                        productoSelect.disabled = false;
                        console.log(`Cargados ${data.data.length} productos`);
                    } else {
                        productoSelect.innerHTML = '<option value="">No hay productos en este almacén</option>';
                    }
                } else {
                    productoSelect.innerHTML = '<option value="">Error al cargar productos</option>';
                }
                
            } catch (error) {
                console.error('Error loading products:', error);
                productoSelect.innerHTML = '<option value="">Error de conexión</option>';
                showNotification('Error al cargar productos', 'error');
            }
        }

        async function handleFormSubmit(e) {
            e.preventDefault();
            
            const almacenId = document.getElementById('almacenSelect').value;
            const productoId = document.getElementById('productoSelect').value;
            const cantidad = parseInt(document.getElementById('cantidadInput').value) || 1;
            const notas = document.getElementById('notasInput').value.trim();
            
            if (!almacenId || !productoId) {
                showNotification('Por favor seleccione almacén y producto', 'warning');
                return;
            }
            
            if (cantidad < 1 || cantidad > 500) {
                showNotification('La cantidad debe estar entre 1 y 500', 'warning');
                return;
            }
            
            await generateQRCodes(almacenId, productoId, cantidad, notas);
        }

        async function generateQRCodes(almacenId, productoId, cantidad, notas) {
            // Obtener token CSRF
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            
            // Limpiar resultados anteriores
            document.getElementById('resultsGrid').innerHTML = '';
            document.getElementById('downloadSection').style.display = 'none';
            
            // Mostrar progress bar
            showProgress(true);
            generatedQRs = [];
            
            // Deshabilitar formulario
            document.getElementById('generatorForm').style.pointerEvents = 'none';
            document.querySelector('.generate-btn').disabled = true;
            document.querySelector('.generate-btn').textContent = '⏳ Generando...';
            
            try {
                for (let i = 1; i <= cantidad; i++) {
                    updateProgress(i, cantidad, `Generando código ${i} de ${cantidad}...`);
                    
                    const response = await fetch('api/generate.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            producto_id: productoId,
                            almacen_id: almacenId,
                            quantity_index: i,
                            total_quantity: cantidad,
                            notes: notas,
                            include_qr_url: true
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        generatedQRs.push(result);
                        displayQRResult(result, i);
                    } else {
                        console.error(`Error generando QR ${i}:`, result.error);
                        showNotification(`Error en QR ${i}: ${result.error}`, 'error');
                    }
                    
                    // Pausa pequeña para no sobrecargar el servidor y garantizar códigos únicos
                    if (i < cantidad) {
                        await new Promise(resolve => setTimeout(resolve, 500));
                    }
                }
                
                // Mostrar resultados finales
                const successful = generatedQRs.length;
                showNotification(`¡${successful} código(s) QR generado(s) exitosamente!`, 'success');
                
                // Mostrar sección de descarga si hay resultados
                if (successful > 0) {
                    document.getElementById('downloadSection').style.display = 'block';
                }
                
            } catch (error) {
                console.error('Error generating QR codes:', error);
                showNotification('Error de conexión al generar códigos QR', 'error');
            } finally {
                // Rehabilitar formulario
                showProgress(false);
                document.getElementById('generatorForm').style.pointerEvents = 'auto';
                document.querySelector('.generate-btn').disabled = false;
                document.querySelector('.generate-btn').textContent = '✨ Generar QR';
            }
        }

        function showProgress(show) {
            document.getElementById('progressBar').style.display = show ? 'block' : 'none';
            if (!show) {
                updateProgress(0, 100, '');
            }
        }

        function updateProgress(current, total, message) {
            const percentage = Math.round((current / total) * 100);
            const progressFill = document.getElementById('progressFill');
            progressFill.style.width = percentage + '%';
            progressFill.textContent = message || `${percentage}%`;
        }

        function displayQRResult(result, index) {
            const resultsSection = document.getElementById('resultsSection');
            const resultsGrid = document.getElementById('resultsGrid');
            
            // Mostrar sección de resultados
            resultsSection.style.display = 'block';
            
            // Crear tarjeta de resultado
            const card = document.createElement('div');
            card.className = 'qr-result-card';
            card.innerHTML = `
                <h4>QR #${index}</h4>
                <div class="qr-code-image">
                    <img src="api/qr-image.php?content=${encodeURIComponent(result.qr_content)}&size=150" 
                         alt="QR Code ${result.qr_content}" 
                         style="max-width: 150px; height: auto; border: 1px solid #ddd;"
                         onload="this.style.opacity='1'" 
                         onerror="this.src='data:image/svg+xml,<svg xmlns=\\'http://www.w3.org/2000/svg\\' width=\\'150\\' height=\\'150\\'><rect width=\\'150\\' height=\\'150\\' fill=\\'%23f0f0f0\\'/><text x=\\'75\\' y=\\'75\\' text-anchor=\\'middle\\' dy=\\'.3em\\' font-family=\\'Arial\\' font-size=\\'12\\' fill=\\'%23666\\'>Error QR</text></svg>'"
                         style="opacity: 0.8; transition: opacity 0.3s;">
                </div>
                <div class="qr-info">
                    <strong>Código:</strong> <code style="background: var(--bg-tertiary); padding: 2px 4px; border-radius: 3px; font-size: 11px;">${result.qr_content}</code><br>
                    <strong>Producto:</strong> ${result.producto.nombre}<br>
                    <strong>Almacén:</strong> ${result.almacen.nombre}<br>
                    ${result.qr_uuid ? `<strong>UUID:</strong> <small>${result.qr_uuid}</small>` : ''}
                </div>
            `;
            
            resultsGrid.appendChild(card);
        }

        function downloadPDF() {
            showNotification('Generando PDF...', 'info');
            // Implementar descarga PDF
        }

        function downloadZIP() {
            showNotification('Generando ZIP...', 'info');
            // Implementar descarga ZIP
        }

        function printQRCodes() {
            window.print();
        }

        function showNotification(message, type = 'info') {
            // Usar el sistema de notificaciones existente o alert como fallback
            if (typeof mostrarNotificacion === 'function') {
                mostrarNotificacion(message, type);
            } else {
                alert(message);
            }
        }
    </script>

    <!-- Sistema de notificaciones -->
    <div id="notification-container"></div>
    <script src="../inventario/productos.js"></script>
</body>
</html>