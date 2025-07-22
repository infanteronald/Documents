<?php
/**
 * Formulario para Registrar Movimiento de Inventario
 * Sequoia Speed - Módulo de Inventario
 */

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once '../config_secure.php';
require_once '../notifications/notification_helpers.php';
require_once '../php82_helpers.php';

// Obtener almacén seleccionado
$almacen_seleccionado = isset($_GET['almacen']) ? trim($_GET['almacen']) : 'TIENDA_BOG';

// Obtener información del almacén seleccionado
$query_almacen = "SELECT * FROM almacenes WHERE codigo = ? AND activo = 1 LIMIT 1";
$stmt_almacen = $conn->prepare($query_almacen);
$stmt_almacen->bind_param('s', $almacen_seleccionado);
$stmt_almacen->execute();
$almacen_actual = $stmt_almacen->get_result()->fetch_assoc();

// Si no se encuentra el almacén, usar Tienda Bogotá por defecto
if (!$almacen_actual) {
    $almacen_seleccionado = 'TIENDA_BOG';
    $stmt_almacen->bind_param('s', $almacen_seleccionado);
    $stmt_almacen->execute();
    $almacen_actual = $stmt_almacen->get_result()->fetch_assoc();
}

// Obtener productos del almacén actual
$query_productos = "SELECT 
    p.id,
    p.nombre,
    p.sku,
    p.precio,
    ia.stock_actual,
    ia.stock_minimo,
    ia.stock_maximo
FROM productos p
INNER JOIN inventario_almacen ia ON p.id = ia.producto_id
WHERE ia.almacen_id = ? AND p.activo = 1
ORDER BY p.nombre";

$stmt_productos = $conn->prepare($query_productos);
$stmt_productos->bind_param('i', $almacen_actual['id']);
$stmt_productos->execute();
$productos = $stmt_productos->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener otros almacenes para transferencias
$query_otros_almacenes = "SELECT * FROM almacenes WHERE activo = 1 AND id != ? ORDER BY nombre";
$stmt_otros_almacenes = $conn->prepare($query_otros_almacenes);
$stmt_otros_almacenes->bind_param('i', $almacen_actual['id']);
$stmt_otros_almacenes->execute();
$otros_almacenes = $stmt_otros_almacenes->get_result()->fetch_all(MYSQLI_ASSOC);

// Valores por defecto del formulario
$valores_defecto = [
    'tipo_movimiento' => 'entrada',
    'producto_id' => '',
    'cantidad' => '',
    'costo_unitario' => '',
    'motivo' => '',
    'documento_referencia' => '',
    'usuario_responsable' => 'Administrador',
    'almacen_destino_id' => '',
    'observaciones' => ''
];

// Si hay datos en la sesión (errores de validación), mantenerlos
if (isset($_SESSION['form_data'])) {
    $valores_defecto = array_merge($valores_defecto, $_SESSION['form_data']);
    unset($_SESSION['form_data']);
}

// Mensajes de error
$errores = $_SESSION['errores'] ?? [];
unset($_SESSION['errores']);

// Mensaje de éxito
$mensaje_exito = $_SESSION['mensaje_exito'] ?? '';
unset($_SESSION['mensaje_exito']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📝 Registrar Movimiento - Sequoia Speed</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📝</text></svg>">
    <link rel="stylesheet" href="productos.css">
    <link rel="stylesheet" href="../notifications/notifications.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">📝 Registrar Movimiento</h1>
                    <div class="breadcrumb">
                        <a href="../listar_pedidos.php">🏠 Inicio</a>
                        <span>/</span>
                        <a href="productos.php">📦 Inventario</a>
                        <span>/</span>
                        <a href="movimientos.php?almacen=<?php echo $almacen_seleccionado; ?>">📊 Movimientos</a>
                        <span>/</span>
                        <span>📝 Registrar</span>
                        <span>/</span>
                        <span class="almacen-actual">🏪 <?php echo htmlspecialchars($almacen_actual['nombre']); ?></span>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="movimientos.php?almacen=<?php echo $almacen_seleccionado; ?>" class="btn btn-secondary">
                        ← Volver a Movimientos
                    </a>
                </div>
            </div>
        </header>

        <!-- Mensajes -->
        <?php if (!empty($mensaje_exito)): ?>
            <div class="mensaje mensaje-exito">
                <div class="mensaje-contenido">
                    <span class="mensaje-icono">✅</span>
                    <span><?php echo htmlspecialchars($mensaje_exito); ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="mensaje-cerrar">×</button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errores)): ?>
            <div class="mensaje mensaje-error">
                <div class="mensaje-contenido">
                    <span class="mensaje-icono">❌</span>
                    <div>
                        <strong>Error al registrar el movimiento:</strong>
                        <ul>
                            <?php foreach ($errores as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <button onclick="this.parentElement.remove()" class="mensaje-cerrar">×</button>
            </div>
        <?php endif; ?>

        <!-- Formulario -->
        <div class="form-section">
            <div class="form-container">
                <form action="procesar_movimiento.php" method="POST" class="producto-form" id="formRegistrarMovimiento">
                    <input type="hidden" name="almacen_id" value="<?php echo $almacen_actual['id']; ?>">
                    <input type="hidden" name="almacen_codigo" value="<?php echo $almacen_seleccionado; ?>">
                    
                    <div class="form-grid">
                        <!-- Tipo de Movimiento -->
                        <div class="form-group-section">
                            <h3 class="section-title">🔄 Tipo de Movimiento</h3>
                            
                            <div class="form-group">
                                <label for="tipo_movimiento" class="form-label">
                                    📋 Tipo de Movimiento <span class="required">*</span>
                                </label>
                                <select id="tipo_movimiento" 
                                        name="tipo_movimiento" 
                                        class="form-select" 
                                        required 
                                        onchange="mostrarCamposSegunTipo()">
                                    <option value="">Seleccionar tipo...</option>
                                    <option value="entrada" <?php echo $valores_defecto['tipo_movimiento'] === 'entrada' ? 'selected' : ''; ?>>
                                        📥 Entrada de Productos
                                    </option>
                                    <option value="salida" <?php echo $valores_defecto['tipo_movimiento'] === 'salida' ? 'selected' : ''; ?>>
                                        📤 Salida de Productos
                                    </option>
                                    <option value="ajuste" <?php echo $valores_defecto['tipo_movimiento'] === 'ajuste' ? 'selected' : ''; ?>>
                                        ⚖️ Ajuste de Inventario
                                    </option>
                                    <option value="transferencia" <?php echo $valores_defecto['tipo_movimiento'] === 'transferencia' ? 'selected' : ''; ?>>
                                        🔄 Transferencia entre Almacenes
                                    </option>
                                </select>
                            </div>

                            <!-- Campo de almacén destino (solo para transferencias) -->
                            <div class="form-group" id="campo_almacen_destino" style="display: none;">
                                <label for="almacen_destino_id" class="form-label">
                                    🏪 Almacén Destino <span class="required">*</span>
                                </label>
                                <select id="almacen_destino_id" name="almacen_destino_id" class="form-select">
                                    <option value="">Seleccionar almacén destino...</option>
                                    <?php foreach ($otros_almacenes as $almacen): ?>
                                        <option value="<?php echo $almacen['id']; ?>" 
                                                <?php echo $valores_defecto['almacen_destino_id'] == $almacen['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($almacen['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Producto y Cantidad -->
                        <div class="form-group-section">
                            <h3 class="section-title">📦 Producto y Cantidad</h3>
                            
                            <div class="form-group">
                                <label for="producto_id" class="form-label">
                                    📦 Producto <span class="required">*</span>
                                </label>
                                <select id="producto_id" 
                                        name="producto_id" 
                                        class="form-select" 
                                        required 
                                        onchange="mostrarInfoProducto()">
                                    <option value="">Seleccionar producto...</option>
                                    <?php foreach ($productos as $producto): ?>
                                        <option value="<?php echo $producto['id']; ?>" 
                                                data-stock="<?php echo $producto['stock_actual']; ?>"
                                                data-precio="<?php echo $producto['precio']; ?>"
                                                data-sku="<?php echo htmlspecialchars($producto['sku']); ?>"
                                                <?php echo $valores_defecto['producto_id'] == $producto['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($producto['nombre']); ?>
                                            <?php if (!empty($producto['sku'])): ?>
                                                - SKU: <?php echo htmlspecialchars($producto['sku']); ?>
                                            <?php endif; ?>
                                            (Stock: <?php echo $producto['stock_actual']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Información del producto seleccionado -->
                            <div class="producto-info-display" id="productoInfo" style="display: none;">
                                <div class="info-grid">
                                    <div class="info-item">
                                        <strong>Stock Actual:</strong>
                                        <span id="stockActual">-</span>
                                    </div>
                                    <div class="info-item">
                                        <strong>Precio:</strong>
                                        <span id="precioProducto">-</span>
                                    </div>
                                    <div class="info-item">
                                        <strong>SKU:</strong>
                                        <span id="skuProducto">-</span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="cantidad" class="form-label">
                                        📊 Cantidad <span class="required">*</span>
                                    </label>
                                    <input type="number" 
                                           id="cantidad" 
                                           name="cantidad" 
                                           value="<?php echo htmlspecialchars($valores_defecto['cantidad']); ?>"
                                           class="form-input" 
                                           required 
                                           min="1"
                                           placeholder="Cantidad a mover"
                                           onchange="calcularNuevoStock()">
                                </div>
                                
                                <div class="form-group">
                                    <label for="costo_unitario" class="form-label">
                                        💰 Costo Unitario
                                    </label>
                                    <div class="input-with-prefix">
                                        <span class="input-prefix">$</span>
                                        <input type="number" 
                                               id="costo_unitario" 
                                               name="costo_unitario" 
                                               value="<?php echo htmlspecialchars($valores_defecto['costo_unitario']); ?>"
                                               class="form-input" 
                                               min="0" 
                                               step="0.01"
                                               placeholder="0.00">
                                    </div>
                                </div>
                            </div>

                            <!-- Preview del cambio de stock -->
                            <div class="stock-preview" id="stockPreview" style="display: none;">
                                <div class="preview-content">
                                    <span class="preview-label">Stock después del movimiento:</span>
                                    <span class="preview-antes" id="stockAntes">-</span>
                                    <span class="preview-flecha">→</span>
                                    <span class="preview-despues" id="stockDespues">-</span>
                                </div>
                            </div>
                        </div>

                        <!-- Información del Movimiento -->
                        <div class="form-group-section">
                            <h3 class="section-title">📝 Información del Movimiento</h3>
                            
                            <div class="form-group">
                                <label for="motivo" class="form-label">
                                    📝 Motivo <span class="required">*</span>
                                </label>
                                <input type="text" 
                                       id="motivo" 
                                       name="motivo" 
                                       value="<?php echo htmlspecialchars($valores_defecto['motivo']); ?>"
                                       class="form-input" 
                                       required 
                                       maxlength="255"
                                       placeholder="Ej: Compra a proveedor, Venta a cliente, Ajuste por conteo físico">
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="documento_referencia" class="form-label">
                                        📋 Documento de Referencia
                                    </label>
                                    <input type="text" 
                                           id="documento_referencia" 
                                           name="documento_referencia" 
                                           value="<?php echo htmlspecialchars($valores_defecto['documento_referencia']); ?>"
                                           class="form-input" 
                                           maxlength="100"
                                           placeholder="Ej: Factura #123, Orden #456">
                                </div>
                                
                                <div class="form-group">
                                    <label for="usuario_responsable" class="form-label">
                                        👤 Usuario Responsable <span class="required">*</span>
                                    </label>
                                    <input type="text" 
                                           id="usuario_responsable" 
                                           name="usuario_responsable" 
                                           value="<?php echo htmlspecialchars($valores_defecto['usuario_responsable']); ?>"
                                           class="form-input" 
                                           required 
                                           maxlength="100"
                                           placeholder="Nombre del responsable">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="observaciones" class="form-label">
                                    📝 Observaciones
                                </label>
                                <textarea id="observaciones" 
                                          name="observaciones" 
                                          class="form-textarea" 
                                          rows="3"
                                          placeholder="Observaciones adicionales (opcional)..."><?php echo htmlspecialchars($valores_defecto['observaciones']); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Botones de acción -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-large">
                            ✅ Registrar Movimiento
                        </button>
                        
                        <button type="button" class="btn btn-secondary btn-large" onclick="limpiarFormulario()">
                            🗑️ Limpiar
                        </button>
                        
                        <a href="movimientos.php?almacen=<?php echo $almacen_seleccionado; ?>" class="btn btn-secondary btn-large">
                            ← Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Sistema de notificaciones -->
    <div id="notification-container"></div>

    <script src="productos.js"></script>
    <script>
        // Datos de productos para JavaScript
        const productos = <?php echo json_encode($productos); ?>;
        
        // Mostrar/ocultar campos según tipo de movimiento
        function mostrarCamposSegunTipo() {
            const tipo = document.getElementById('tipo_movimiento').value;
            const campoAlmacenDestino = document.getElementById('campo_almacen_destino');
            const almacenDestinoSelect = document.getElementById('almacen_destino_id');
            
            if (tipo === 'transferencia') {
                campoAlmacenDestino.style.display = 'block';
                almacenDestinoSelect.required = true;
            } else {
                campoAlmacenDestino.style.display = 'none';
                almacenDestinoSelect.required = false;
                almacenDestinoSelect.value = '';
            }
            
            // Recalcular stock cuando cambie el tipo
            calcularNuevoStock();
        }
        
        // Mostrar información del producto seleccionado
        function mostrarInfoProducto() {
            const select = document.getElementById('producto_id');
            const productoInfo = document.getElementById('productoInfo');
            
            if (select.value) {
                const option = select.options[select.selectedIndex];
                const stock = option.getAttribute('data-stock');
                const precio = option.getAttribute('data-precio');
                const sku = option.getAttribute('data-sku');
                
                document.getElementById('stockActual').textContent = stock;
                document.getElementById('precioProducto').textContent = '$' + parseInt(precio).toLocaleString('es-CO');
                document.getElementById('skuProducto').textContent = sku || 'Sin SKU';
                
                // Sugerir precio como costo unitario si está vacío
                const costoInput = document.getElementById('costo_unitario');
                if (!costoInput.value) {
                    costoInput.value = precio;
                }
                
                productoInfo.style.display = 'block';
            } else {
                productoInfo.style.display = 'none';
            }
            
            calcularNuevoStock();
        }
        
        // Calcular y mostrar preview del nuevo stock
        function calcularNuevoStock() {
            const productoSelect = document.getElementById('producto_id');
            const cantidad = parseInt(document.getElementById('cantidad').value) || 0;
            const tipo = document.getElementById('tipo_movimiento').value;
            const stockPreview = document.getElementById('stockPreview');
            
            if (productoSelect.value && cantidad > 0 && tipo) {
                const option = productoSelect.options[productoSelect.selectedIndex];
                const stockActual = parseInt(option.getAttribute('data-stock'));
                
                let stockNuevo = stockActual;
                
                switch (tipo) {
                    case 'entrada':
                    case 'transferencia_entrada':
                        stockNuevo = stockActual + cantidad;
                        break;
                    case 'salida':
                    case 'transferencia_salida':
                        stockNuevo = stockActual - cantidad;
                        break;
                    case 'ajuste':
                        stockNuevo = cantidad; // Para ajustes, la cantidad es el stock final
                        break;
                    case 'transferencia':
                        stockNuevo = stockActual - cantidad; // En el almacén origen
                        break;
                }
                
                document.getElementById('stockAntes').textContent = stockActual;
                document.getElementById('stockDespues').textContent = stockNuevo;
                
                // Cambiar color según si es positivo o negativo
                const stockDespuesEl = document.getElementById('stockDespues');
                if (stockNuevo < 0) {
                    stockDespuesEl.style.color = 'var(--color-danger)';
                } else if (stockNuevo < stockActual) {
                    stockDespuesEl.style.color = 'var(--color-warning)';
                } else {
                    stockDespuesEl.style.color = 'var(--color-success)';
                }
                
                stockPreview.style.display = 'block';
            } else {
                stockPreview.style.display = 'none';
            }
        }
        
        // Limpiar formulario
        function limpiarFormulario() {
            if (confirm('¿Estás seguro de que quieres limpiar el formulario?')) {
                document.getElementById('formRegistrarMovimiento').reset();
                document.getElementById('productoInfo').style.display = 'none';
                document.getElementById('stockPreview').style.display = 'none';
                mostrarCamposSegunTipo();
            }
        }
        
        // Validación antes de enviar
        document.getElementById('formRegistrarMovimiento').addEventListener('submit', function(e) {
            const cantidad = parseInt(document.getElementById('cantidad').value) || 0;
            const tipo = document.getElementById('tipo_movimiento').value;
            const productoSelect = document.getElementById('producto_id');
            
            if (productoSelect.value && cantidad > 0) {
                const option = productoSelect.options[productoSelect.selectedIndex];
                const stockActual = parseInt(option.getAttribute('data-stock'));
                
                // Validar que no se intente sacar más stock del disponible
                if ((tipo === 'salida' || tipo === 'transferencia') && cantidad > stockActual) {
                    e.preventDefault();
                    alert('No se puede sacar más cantidad de la disponible en stock (' + stockActual + ')');
                    return false;
                }
            }
        });
        
        // Inicializar al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            mostrarCamposSegunTipo();
            mostrarInfoProducto();
        });
    </script>
</body>
</html>