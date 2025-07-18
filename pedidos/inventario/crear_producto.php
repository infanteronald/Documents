<?php
/**
 * Formulario para Crear Nuevo Producto
 * Sequoia Speed - Módulo de Inventario
 * ACTUALIZADO: Integración con sistema unificado de almacenes
 */

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once '../config_secure.php';
require_once '../notifications/notification_helpers.php';
require_once '../php82_helpers.php';
require_once 'config_almacenes.php';

// Configurar conexión para AlmacenesConfig
AlmacenesConfig::setConnection($conn);

// Obtener categorías existentes para el selector
$categorias_query = "SELECT DISTINCT categoria FROM productos WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria";
$categorias_result = $conn->query($categorias_query);
$categorias = $categorias_result->fetch_all(MYSQLI_ASSOC);

// Obtener almacenes usando la nueva configuración
$almacenes = AlmacenesConfig::getAlmacenes();

// Obtener almacén preseleccionado desde URL
$almacen_preseleccionado = isset($_GET['almacen_id']) ? intval($_GET['almacen_id']) : null;
if ($almacen_preseleccionado) {
    $almacen_data = AlmacenesConfig::getAlmacenPorId($almacen_preseleccionado);
    if ($almacen_data) {
        $almacen_por_defecto = $almacen_preseleccionado;
    } else {
        $almacen_por_defecto = AlmacenesConfig::getAlmacenPorDefecto()['id'] ?? null;
    }
} else {
    $almacen_por_defecto = AlmacenesConfig::getAlmacenPorDefecto()['id'] ?? null;
}

// Valores por defecto
$valores_defecto = [
    'nombre' => '',
    'descripcion' => '',
    'categoria' => '',
    'precio' => '',
    'stock_actual' => '0',
    'stock_minimo' => '5',
    'stock_maximo' => '100',
    'almacen_id' => $almacen_por_defecto,
    'activo' => '1',
    'sku' => ''
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
    <title>➕ Crear Producto - Sequoia Speed</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>➕</text></svg>">
    <link rel="stylesheet" href="productos.css">
    <link rel="stylesheet" href="../notifications/notifications.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">➕ Crear Nuevo Producto</h1>
                    <div class="breadcrumb">
                        <a href="../listar_pedidos.php">🏠 Inicio</a>
                        <span>/</span>
                        <a href="productos.php">📦 Productos</a>
                        <span>/</span>
                        <span>➕ Crear</span>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="productos.php" class="btn btn-secondary">
                        ← Volver al Listado
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
                        <strong>Error al crear el producto:</strong>
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
                <form action="procesar_producto.php" method="POST" enctype="multipart/form-data" class="producto-form" id="formCrearProducto">
                    <input type="hidden" name="accion" value="crear">
                    
                    <div class="form-grid">
                        <!-- Información básica -->
                        <div class="form-group-section">
                            <h3 class="section-title">📋 Información Básica</h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="nombre" class="form-label">
                                        📦 Nombre del Producto <span class="required">*</span>
                                    </label>
                                    <input type="text" 
                                           id="nombre" 
                                           name="nombre" 
                                           value="<?php echo htmlspecialchars($valores_defecto['nombre']); ?>"
                                           class="form-input" 
                                           required 
                                           maxlength="255"
                                           placeholder="Ej: Camiseta deportiva Nike">
                                </div>
                                
                                <div class="form-group">
                                    <label for="sku" class="form-label">
                                        🔖 SKU (Código único)
                                    </label>
                                    <input type="text" 
                                           id="sku" 
                                           name="sku" 
                                           value="<?php echo htmlspecialchars($valores_defecto['sku']); ?>"
                                           class="form-input" 
                                           maxlength="40"
                                           placeholder="Ej: NIKE-001">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="descripcion" class="form-label">
                                    📝 Descripción
                                </label>
                                <textarea id="descripcion" 
                                          name="descripcion" 
                                          class="form-textarea" 
                                          rows="3"
                                          placeholder="Descripción detallada del producto..."><?php echo htmlspecialchars($valores_defecto['descripcion']); ?></textarea>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="categoria" class="form-label">
                                        🏷️ Categoría <span class="required">*</span>
                                    </label>
                                    <div class="input-with-suggestions">
                                        <input type="text" 
                                               id="categoria" 
                                               name="categoria" 
                                               value="<?php echo htmlspecialchars($valores_defecto['categoria']); ?>"
                                               class="form-input" 
                                               required 
                                               maxlength="50"
                                               placeholder="Ej: Electrónicos"
                                               list="categorias-list">
                                        <datalist id="categorias-list">
                                            <?php foreach ($categorias as $cat): ?>
                                                <option value="<?php echo htmlspecialchars($cat['categoria']); ?>">
                                            <?php endforeach; ?>
                                        </datalist>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="precio" class="form-label">
                                        💰 Precio <span class="required">*</span>
                                    </label>
                                    <div class="input-with-prefix">
                                        <span class="input-prefix">$</span>
                                        <input type="number" 
                                               id="precio" 
                                               name="precio" 
                                               value="<?php echo htmlspecialchars($valores_defecto['precio']); ?>"
                                               class="form-input" 
                                               required 
                                               min="0" 
                                               step="1"
                                               placeholder="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Inventario -->
                        <div class="form-group-section">
                            <h3 class="section-title">📊 Control de Inventario</h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="stock_actual" class="form-label">
                                        📦 Stock Actual <span class="required">*</span>
                                    </label>
                                    <input type="number" 
                                           id="stock_actual" 
                                           name="stock_actual" 
                                           value="<?php echo htmlspecialchars($valores_defecto['stock_actual']); ?>"
                                           class="form-input" 
                                           required 
                                           min="0"
                                           placeholder="0">
                                </div>
                                
                                <div class="form-group">
                                    <label for="stock_minimo" class="form-label">
                                        🔴 Stock Mínimo <span class="required">*</span>
                                    </label>
                                    <input type="number" 
                                           id="stock_minimo" 
                                           name="stock_minimo" 
                                           value="<?php echo htmlspecialchars($valores_defecto['stock_minimo']); ?>"
                                           class="form-input" 
                                           required 
                                           min="0"
                                           placeholder="5">
                                </div>
                                
                                <div class="form-group">
                                    <label for="stock_maximo" class="form-label">
                                        🟢 Stock Máximo <span class="required">*</span>
                                    </label>
                                    <input type="number" 
                                           id="stock_maximo" 
                                           name="stock_maximo" 
                                           value="<?php echo htmlspecialchars($valores_defecto['stock_maximo']); ?>"
                                           class="form-input" 
                                           required 
                                           min="1"
                                           placeholder="100">
                                </div>
                            </div>
                            
                            <div class="stock-indicator" id="stockIndicator">
                                <div class="stock-bar">
                                    <div class="stock-fill" id="stockFill"></div>
                                </div>
                                <div class="stock-labels">
                                    <span class="stock-label">Mínimo</span>
                                    <span class="stock-label">Actual</span>
                                    <span class="stock-label">Máximo</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Ubicación y Estado -->
                        <div class="form-group-section">
                            <h3 class="section-title">🏪 Ubicación y Estado</h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="almacen_id" class="form-label">
                                        🏪 Almacén <span class="required">*</span>
                                    </label>
                                    <select id="almacen_id" name="almacen_id" class="form-select" required>
                                        <option value="">Seleccionar almacén...</option>
                                        <?php foreach ($almacenes as $almacen): ?>
                                            <option value="<?php echo $almacen['id']; ?>" 
                                                    <?php echo ($valores_defecto['almacen_id'] == $almacen['id']) ? 'selected' : ''; ?>>
                                                <?php echo AlmacenesConfig::getIconoAlmacen($almacen) . ' ' . htmlspecialchars($almacen['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="activo" class="form-label">
                                        ⚙️ Estado del Producto
                                    </label>
                                    <select id="activo" name="activo" class="form-select">
                                        <option value="1" <?php echo $valores_defecto['activo'] == '1' ? 'selected' : ''; ?>>
                                            ✅ Activo
                                        </option>
                                        <option value="0" <?php echo $valores_defecto['activo'] == '0' ? 'selected' : ''; ?>>
                                            ❌ Inactivo
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Imagen -->
                        <div class="form-group-section">
                            <h3 class="section-title">📷 Imagen del Producto</h3>
                            
                            <div class="form-group">
                                <label for="imagen" class="form-label">
                                    📸 Imagen del Producto
                                </label>
                                <div class="file-upload-container">
                                    <input type="file" 
                                           id="imagen" 
                                           name="imagen" 
                                           class="form-file" 
                                           accept="image/*"
                                           onchange="previewImage(this)">
                                    <div class="file-upload-area" onclick="document.getElementById('imagen').click()">
                                        <div class="file-upload-content">
                                            <div class="file-upload-icon">📷</div>
                                            <div class="file-upload-text">
                                                <strong>Haz clic para seleccionar una imagen</strong>
                                                <br>
                                                <small>JPG, PNG o WebP (máx. 5MB)</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="image-preview" id="imagePreview" style="display: none;">
                                    <img id="previewImg" src="" alt="Vista previa">
                                    <button type="button" class="remove-image" onclick="removeImage()">×</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Botones de acción -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-large">
                            ✅ Crear Producto
                        </button>
                        
                        <button type="button" class="btn btn-secondary btn-large" onclick="limpiarFormulario()">
                            🗑️ Limpiar
                        </button>
                        
                        <a href="productos.php" class="btn btn-secondary btn-large">
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
        // Actualizar indicador de stock en tiempo real
        function actualizarIndicadorStock() {
            const stockActual = parseInt(document.getElementById('stock_actual').value) || 0;
            const stockMinimo = parseInt(document.getElementById('stock_minimo').value) || 0;
            const stockMaximo = parseInt(document.getElementById('stock_maximo').value) || 1;
            
            const porcentaje = stockMaximo > 0 ? (stockActual / stockMaximo) * 100 : 0;
            const stockFill = document.getElementById('stockFill');
            
            stockFill.style.width = Math.min(porcentaje, 100) + '%';
            
            // Cambiar color según el nivel
            if (stockActual <= stockMinimo) {
                stockFill.style.backgroundColor = 'var(--color-danger)';
            } else if (stockActual <= stockMinimo + (stockMaximo - stockMinimo) * 0.3) {
                stockFill.style.backgroundColor = 'var(--color-warning)';
            } else {
                stockFill.style.backgroundColor = 'var(--color-success)';
            }
        }
        
        // Preview de imagen
        function previewImage(input) {
            const file = input.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('imagePreview');
                    const previewImg = document.getElementById('previewImg');
                    
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                    
                    // Ocultar área de upload
                    document.querySelector('.file-upload-area').style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
        }
        
        // Remover imagen
        function removeImage() {
            document.getElementById('imagen').value = '';
            document.getElementById('imagePreview').style.display = 'none';
            document.querySelector('.file-upload-area').style.display = 'flex';
        }
        
        // Limpiar formulario
        function limpiarFormulario() {
            if (confirm('¿Estás seguro de que quieres limpiar el formulario?')) {
                document.getElementById('formCrearProducto').reset();
                removeImage();
                actualizarIndicadorStock();
            }
        }
        
        // Validación en tiempo real
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = ['stock_actual', 'stock_minimo', 'stock_maximo'];
            inputs.forEach(id => {
                document.getElementById(id).addEventListener('input', actualizarIndicadorStock);
            });
            
            // Actualizar indicador inicial
            actualizarIndicadorStock();
            
            // Validar que stock máximo sea mayor que mínimo
            document.getElementById('stock_maximo').addEventListener('input', function() {
                const minimo = parseInt(document.getElementById('stock_minimo').value) || 0;
                const maximo = parseInt(this.value) || 0;
                
                if (maximo <= minimo) {
                    this.setCustomValidity('El stock máximo debe ser mayor que el mínimo');
                } else {
                    this.setCustomValidity('');
                }
            });
        });
    </script>
</body>
</html>