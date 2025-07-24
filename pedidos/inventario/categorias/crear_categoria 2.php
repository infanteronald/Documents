<?php
/**
 * Crear Nueva Categoría de Productos
 * Sistema de Inventario - Sequoia Speed
 */

// Iniciar sesión
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Requerir autenticación
require_once '../../accesos/auth_helper.php';
$current_user = auth_require('inventario', 'crear');

// Definir constante y conexión
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once '../../config_secure.php';

// Mensajes de sesión
$mensaje_error = $_SESSION['mensaje_error'] ?? '';
unset($_SESSION['mensaje_error']);

// Valores por defecto para el formulario
$categoria = [
    'nombre' => '',
    'descripcion' => '',
    'icono' => '🏷️',
    'color' => '#58a6ff',
    'activa' => 1,
    'orden' => 0
];

// Si hay datos de error, preservar valores
if (isset($_SESSION['form_data'])) {
    $categoria = array_merge($categoria, $_SESSION['form_data']);
    unset($_SESSION['form_data']);
}

// Obtener siguiente orden disponible
try {
    $query_orden = "SELECT COALESCE(MAX(orden), 0) + 10 as siguiente_orden FROM categorias_productos";
    $result_orden = $conn->query($query_orden);
    if ($result_orden) {
        $categoria['orden'] = $result_orden->fetch_assoc()['siguiente_orden'];
    }
} catch (Exception $e) {
    // Usar valor por defecto si hay error
}

// Iconos disponibles
$iconos_disponibles = [
    '🏷️', '📱', '👕', '🏠', '⚽', '📚', '🍎', '🔧', 
    '🧸', '💄', '🎮', '🏃', '🍽️', '🎵', '🚗', '🌿',
    '💊', '🎨', '🔨', '👶', '🐾', '💻', '📷', '⌚',
    '👓', '🎪', '🏖️', '🎯', '🔐', '🧹', '🍰', '📝'
];

// Colores disponibles
$colores_disponibles = [
    '#58a6ff', '#3498db', '#2ecc71', '#f39c12', 
    '#e74c3c', '#9b59b6', '#e67e22', '#1abc9c',
    '#34495e', '#e91e63', '#8bc34a', '#ff5722',
    '#607d8b', '#795548', '#ffc107', '#00bcd4'
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>➕ Nueva Categoría - Sequoia Speed</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>➕</text></svg>">
    <link rel="stylesheet" href="../productos.css">
    <link rel="stylesheet" href="categorias.css">
    <link rel="stylesheet" href="../../notifications/notifications.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">➕ Nueva Categoría</h1>
                    <div class="breadcrumb">
                        <a href="../../listar_pedidos.php">🏠 Inicio</a>
                        <span>/</span>
                        <a href="../productos.php">📦 Inventario</a>
                        <span>/</span>
                        <a href="index.php">🗂️ Categorías</a>
                        <span>/</span>
                        <span>➕ Nueva</span>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="index.php" class="btn btn-secondary">
                        ← Volver a Categorías
                    </a>
                </div>
            </div>
        </header>

        <!-- Mensajes -->
        <?php if (!empty($mensaje_error)): ?>
            <div class="mensaje mensaje-error">
                <div class="mensaje-contenido">
                    <span class="mensaje-icono">❌</span>
                    <span><?php echo htmlspecialchars($mensaje_error); ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="mensaje-cerrar">×</button>
            </div>
        <?php endif; ?>

        <!-- Formulario -->
        <div class="content-section">
            <form id="formCategoria" action="procesar_categoria.php" method="POST" class="form-container">
                <input type="hidden" name="accion" value="crear">
                
                <div class="form-grid">
                    <!-- Información básica -->
                    <div class="form-section">
                        <h3 class="section-title">📝 Información Básica</h3>
                        
                        <div class="form-group">
                            <label for="nombre" class="form-label">Nombre de la Categoría *</label>
                            <input type="text" 
                                   id="nombre" 
                                   name="nombre" 
                                   class="form-input" 
                                   value="<?php echo htmlspecialchars($categoria['nombre']); ?>"
                                   placeholder="Ej: Electrónicos, Ropa, Hogar..."
                                   required 
                                   maxlength="100">
                            <small class="form-help">Nombre único e identificativo de la categoría</small>
                        </div>

                        <div class="form-group">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea id="descripcion" 
                                      name="descripcion" 
                                      class="form-textarea" 
                                      rows="3"
                                      placeholder="Descripción detallada de la categoría..."><?php echo htmlspecialchars($categoria['descripcion']); ?></textarea>
                            <small class="form-help">Descripción opcional que ayude a identificar la categoría</small>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="orden" class="form-label">Orden de Visualización</label>
                                <input type="number" 
                                       id="orden" 
                                       name="orden" 
                                       class="form-input orden-input" 
                                       value="<?php echo $categoria['orden']; ?>"
                                       min="0" 
                                       max="9999"
                                       step="1">
                                <small class="form-help">Orden en que aparecerá la categoría (menor número = aparece primero)</small>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Estado</label>
                                <div class="radio-group">
                                    <label class="radio-label">
                                        <input type="radio" 
                                               name="activa" 
                                               value="1" 
                                               <?php echo $categoria['activa'] == 1 ? 'checked' : ''; ?>>
                                        <span class="radio-custom"></span>
                                        ✅ Activa
                                    </label>
                                    <label class="radio-label">
                                        <input type="radio" 
                                               name="activa" 
                                               value="0" 
                                               <?php echo $categoria['activa'] == 0 ? 'checked' : ''; ?>>
                                        <span class="radio-custom"></span>
                                        ❌ Inactiva
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Apariencia -->
                    <div class="form-section">
                        <h3 class="section-title">🎨 Apariencia</h3>
                        
                        <div class="form-group icon-group">
                            <label class="form-label">Icono de la Categoría</label>
                            <div class="current-selection">
                                <span class="current-icon" id="iconoActual"><?php echo htmlspecialchars($categoria['icono']); ?></span>
                                <span>Icono seleccionado</span>
                            </div>
                            <div class="icon-selector">
                                <?php foreach ($iconos_disponibles as $icono): ?>
                                    <div class="icon-option <?php echo $icono === $categoria['icono'] ? 'selected' : ''; ?>" 
                                         data-icon="<?php echo htmlspecialchars($icono); ?>">
                                        <?php echo $icono; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" id="icono" name="icono" value="<?php echo htmlspecialchars($categoria['icono']); ?>">
                        </div>

                        <div class="form-group color-group">
                            <label class="form-label">Color de la Categoría</label>
                            <div class="current-selection">
                                <div class="current-color" 
                                     id="colorActual" 
                                     style="background-color: <?php echo htmlspecialchars($categoria['color']); ?>"></div>
                                <span>Color seleccionado: <?php echo htmlspecialchars($categoria['color']); ?></span>
                            </div>
                            <div class="color-selector">
                                <?php foreach ($colores_disponibles as $color): ?>
                                    <div class="color-option <?php echo $color === $categoria['color'] ? 'selected' : ''; ?>" 
                                         style="background-color: <?php echo $color; ?>"
                                         data-color="<?php echo $color; ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" id="color" name="color" value="<?php echo htmlspecialchars($categoria['color']); ?>">
                        </div>

                        <!-- Vista previa -->
                        <div class="categoria-preview">
                            <div class="preview-icon" 
                                 id="previewIcon" 
                                 style="color: <?php echo htmlspecialchars($categoria['color']); ?>">
                                <?php echo htmlspecialchars($categoria['icono']); ?>
                            </div>
                            <div class="preview-info">
                                <h4 id="previewNombre"><?php echo !empty($categoria['nombre']) ? htmlspecialchars($categoria['nombre']) : 'Nombre de la categoría'; ?></h4>
                                <p id="previewDescripcion"><?php echo !empty($categoria['descripcion']) ? htmlspecialchars($categoria['descripcion']) : 'Descripción de la categoría'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones de acción -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        💾 Crear Categoría
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        ❌ Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Sistema de notificaciones -->
    <div id="notification-container"></div>

    <script src="../productos.js"></script>
    <script>
        // Selectores de icono
        document.querySelectorAll('.icon-option').forEach(option => {
            option.addEventListener('click', function() {
                // Remover selección anterior
                document.querySelectorAll('.icon-option').forEach(opt => opt.classList.remove('selected'));
                
                // Seleccionar nuevo icono
                this.classList.add('selected');
                const icono = this.dataset.icon;
                
                // Actualizar campos
                document.getElementById('icono').value = icono;
                document.getElementById('iconoActual').textContent = icono;
                document.getElementById('previewIcon').textContent = icono;
            });
        });

        // Selectores de color
        document.querySelectorAll('.color-option').forEach(option => {
            option.addEventListener('click', function() {
                // Remover selección anterior
                document.querySelectorAll('.color-option').forEach(opt => opt.classList.remove('selected'));
                
                // Seleccionar nuevo color
                this.classList.add('selected');
                const color = this.dataset.color;
                
                // Actualizar campos
                document.getElementById('color').value = color;
                document.getElementById('colorActual').style.backgroundColor = color;
                document.getElementById('previewIcon').style.color = color;
                
                // Actualizar texto del color actual
                document.querySelector('.current-selection span').textContent = `Color seleccionado: ${color}`;
            });
        });

        // Vista previa en tiempo real
        document.getElementById('nombre').addEventListener('input', function() {
            const nombre = this.value.trim();
            document.getElementById('previewNombre').textContent = nombre || 'Nombre de la categoría';
        });

        document.getElementById('descripcion').addEventListener('input', function() {
            const descripcion = this.value.trim();
            document.getElementById('previewDescripcion').textContent = descripcion || 'Descripción de la categoría';
        });

        // Validación del formulario
        document.getElementById('formCategoria').addEventListener('submit', function(e) {
            const nombre = document.getElementById('nombre').value.trim();
            
            if (!nombre) {
                e.preventDefault();
                alert('⚠️ El nombre de la categoría es obligatorio');
                document.getElementById('nombre').focus();
                return false;
            }

            if (nombre.length > 100) {
                e.preventDefault();
                alert('⚠️ El nombre no puede exceder los 100 caracteres');
                document.getElementById('nombre').focus();
                return false;
            }

            // Confirmar creación
            if (!confirm(`¿Crear la categoría "${nombre}"?`)) {
                e.preventDefault();
                return false;
            }
        });

        // Auto-focus en el primer campo
        document.getElementById('nombre').focus();
    </script>
</body>
</html>