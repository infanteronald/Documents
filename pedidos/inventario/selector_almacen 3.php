<?php
/**
 * Componente Selector de Almacén Reutilizable
 * Sistema de Inventario - Sequoia Speed
 * 
 * Componente para generar selectores de almacén consistentes
 */

// Verificar que se está ejecutando en el contexto correcto
if (!defined('SEQUOIA_SPEED_SYSTEM')) {
    die('Acceso directo no permitido');
}

require_once 'config_almacenes.php';

/**
 * Renderizar selector de almacén como HTML
 */
function renderSelectorAlmacen($options = []) {
    // Opciones por defecto
    $defaults = [
        'name' => 'almacen_id',
        'id' => 'almacen_id',
        'class' => 'form-select',
        'required' => true,
        'selected' => null,
        'placeholder' => 'Seleccionar almacén...',
        'incluir_todos' => false,
        'activos_solo' => true,
        'show_icon' => true,
        'show_description' => false,
        'onchange' => null,
        'disabled' => false
    ];
    
    $options = array_merge($defaults, $options);
    
    // Obtener almacenes
    $almacenes = AlmacenesConfig::getAlmacenesPorPrioridad($options['activos_solo']);
    
    // Construir atributos
    $attributes = [];
    $attributes[] = 'name="' . htmlspecialchars($options['name']) . '"';
    $attributes[] = 'id="' . htmlspecialchars($options['id']) . '"';
    $attributes[] = 'class="' . htmlspecialchars($options['class']) . '"';
    
    if ($options['required']) {
        $attributes[] = 'required';
    }
    
    if ($options['disabled']) {
        $attributes[] = 'disabled';
    }
    
    if ($options['onchange']) {
        $attributes[] = 'onchange="' . htmlspecialchars($options['onchange']) . '"';
    }
    
    $attributes_str = implode(' ', $attributes);
    
    // Generar HTML
    $html = "<select {$attributes_str}>\n";
    
    // Opción por defecto
    if ($options['incluir_todos']) {
        $html .= '    <option value="">Todos los almacenes</option>' . "\n";
    } else {
        $html .= '    <option value="">' . htmlspecialchars($options['placeholder']) . '</option>' . "\n";
    }
    
    // Opciones de almacenes
    foreach ($almacenes as $almacen) {
        $selected = ($options['selected'] == $almacen['id']) ? 'selected' : '';
        $text = '';
        
        if ($options['show_icon']) {
            $text .= AlmacenesConfig::getIconoAlmacen($almacen) . ' ';
        }
        
        $text .= htmlspecialchars($almacen['nombre']);
        
        if ($options['show_description'] && !empty($almacen['descripcion'])) {
            $text .= ' - ' . htmlspecialchars($almacen['descripcion']);
        }
        
        $html .= '    <option value="' . $almacen['id'] . '" ' . $selected . '>' . $text . '</option>' . "\n";
    }
    
    $html .= "</select>\n";
    
    return $html;
}

/**
 * Renderizar selector de almacén con label
 */
function renderSelectorAlmacenConLabel($options = []) {
    // Opciones por defecto para label
    $label_defaults = [
        'label' => '🏪 Almacén',
        'required_indicator' => true,
        'help_text' => null,
        'label_class' => 'form-label'
    ];
    
    $options = array_merge($label_defaults, $options);
    
    $html = '';
    
    // Label
    $required_mark = ($options['required_indicator'] && ($options['required'] ?? true)) ? ' <span class="required">*</span>' : '';
    $html .= '<label for="' . htmlspecialchars($options['id'] ?? $options['name']) . '" class="' . htmlspecialchars($options['label_class']) . '">';
    $html .= htmlspecialchars($options['label']) . $required_mark;
    $html .= '</label>' . "\n";
    
    // Selector
    $html .= renderSelectorAlmacen($options);
    
    // Texto de ayuda
    if ($options['help_text']) {
        $html .= '<small class="form-help">' . htmlspecialchars($options['help_text']) . '</small>' . "\n";
    }
    
    return $html;
}

/**
 * Renderizar selector de almacén como radio buttons
 */
function renderSelectorAlmacenRadio($options = []) {
    // Opciones por defecto
    $defaults = [
        'name' => 'almacen_id',
        'class' => 'almacen-radio',
        'selected' => null,
        'activos_solo' => true,
        'show_icon' => true,
        'show_description' => true,
        'required' => true,
        'layout' => 'grid' // 'grid' o 'list'
    ];
    
    $options = array_merge($defaults, $options);
    
    // Obtener almacenes
    $almacenes = AlmacenesConfig::getAlmacenesPorPrioridad($options['activos_solo']);
    
    $html = '<div class="almacen-radio-group ' . ($options['layout'] === 'grid' ? 'grid-layout' : 'list-layout') . '">' . "\n";
    
    foreach ($almacenes as $almacen) {
        $checked = ($options['selected'] == $almacen['id']) ? 'checked' : '';
        $required = $options['required'] ? 'required' : '';
        
        $html .= '    <div class="almacen-radio-item">' . "\n";
        $html .= '        <input type="radio" ';
        $html .= 'name="' . htmlspecialchars($options['name']) . '" ';
        $html .= 'id="almacen_' . $almacen['id'] . '" ';
        $html .= 'value="' . $almacen['id'] . '" ';
        $html .= 'class="' . htmlspecialchars($options['class']) . '" ';
        $html .= $checked . ' ' . $required . '>' . "\n";
        
        $html .= '        <label for="almacen_' . $almacen['id'] . '" class="almacen-radio-label">' . "\n";
        
        if ($options['show_icon']) {
            $html .= '            <span class="almacen-icon">' . AlmacenesConfig::getIconoAlmacen($almacen) . '</span>' . "\n";
        }
        
        $html .= '            <span class="almacen-info">' . "\n";
        $html .= '                <strong>' . htmlspecialchars($almacen['nombre']) . '</strong>' . "\n";
        
        if ($options['show_description'] && !empty($almacen['descripcion'])) {
            $html .= '                <small>' . htmlspecialchars($almacen['descripcion']) . '</small>' . "\n";
        }
        
        $html .= '            </span>' . "\n";
        $html .= '        </label>' . "\n";
        $html .= '    </div>' . "\n";
    }
    
    $html .= '</div>' . "\n";
    
    return $html;
}

/**
 * Generar datos JSON para JavaScript
 */
function getSelectorAlmacenJSON($activos_solo = true) {
    $almacenes = AlmacenesConfig::getAlmacenes($activos_solo);
    $data = [];
    
    foreach ($almacenes as $almacen) {
        $data[] = [
            'id' => $almacen['id'],
            'codigo' => $almacen['codigo'],
            'nombre' => $almacen['nombre'],
            'descripcion' => $almacen['descripcion'] ?? '',
            'ubicacion' => $almacen['ubicacion'] ?? '',
            'icono' => AlmacenesConfig::getIconoAlmacen($almacen),
            'activo' => $almacen['activo']
        ];
    }
    
    return json_encode($data);
}

/**
 * Renderizar selector de almacén con búsqueda (select2 style)
 */
function renderSelectorAlmacenBusqueda($options = []) {
    // Opciones por defecto
    $defaults = [
        'name' => 'almacen_id',
        'id' => 'almacen_id',
        'class' => 'form-select almacen-select-search',
        'required' => true,
        'selected' => null,
        'placeholder' => 'Buscar almacén...',
        'activos_solo' => true,
        'show_icon' => true,
        'min_length' => 1
    ];
    
    $options = array_merge($defaults, $options);
    
    // Generar selector básico
    $html = renderSelectorAlmacen($options);
    
    // Agregar script de inicialización
    $html .= '<script>' . "\n";
    $html .= 'document.addEventListener("DOMContentLoaded", function() {' . "\n";
    $html .= '    const almacenesData = ' . getSelectorAlmacenJSON($options['activos_solo']) . ';' . "\n";
    $html .= '    initAlmacenSelector("' . $options['id'] . '", almacenesData);' . "\n";
    $html .= '});' . "\n";
    $html .= '</script>' . "\n";
    
    return $html;
}

/**
 * Validar selección de almacén
 */
function validarSeleccionAlmacen($almacen_id) {
    if (empty($almacen_id)) {
        return ['valido' => false, 'error' => 'Debe seleccionar un almacén'];
    }
    
    if (!is_numeric($almacen_id)) {
        return ['valido' => false, 'error' => 'ID de almacén inválido'];
    }
    
    $almacen = AlmacenesConfig::getAlmacenPorId($almacen_id);
    if (!$almacen) {
        return ['valido' => false, 'error' => 'El almacén seleccionado no existe'];
    }
    
    if (!$almacen['activo']) {
        return ['valido' => false, 'error' => 'El almacén seleccionado está inactivo'];
    }
    
    return ['valido' => true, 'almacen' => $almacen];
}

/**
 * Obtener estadísticas de almacén para selector
 */
function getEstadisticasAlmacenSelector($almacen_id) {
    $stats = AlmacenesConfig::getEstadisticasAlmacen($almacen_id);
    
    return [
        'productos' => $stats['total_productos'] ?? 0,
        'stock_total' => $stats['stock_total'] ?? 0,
        'stock_critico' => $stats['stock_critico'] ?? 0,
        'valor_inventario' => $stats['valor_inventario'] ?? 0
    ];
}

/**
 * Ejemplo de uso del componente
 */
function ejemploUsoSelector() {
    // Selector básico
    echo renderSelectorAlmacen([
        'name' => 'almacen_id',
        'selected' => 1,
        'required' => true
    ]);
    
    // Selector con label
    echo renderSelectorAlmacenConLabel([
        'name' => 'almacen_id',
        'label' => '🏪 Seleccionar Almacén',
        'help_text' => 'Seleccione el almacén donde se ubicará el producto',
        'selected' => 1
    ]);
    
    // Selector como radio buttons
    echo renderSelectorAlmacenRadio([
        'name' => 'almacen_id',
        'selected' => 1,
        'layout' => 'grid'
    ]);
    
    // Selector con búsqueda
    echo renderSelectorAlmacenBusqueda([
        'name' => 'almacen_id',
        'placeholder' => 'Buscar almacén...',
        'selected' => 1
    ]);
}

// CSS para los selectores
$css_selector_almacen = '
<style>
.almacen-radio-group {
    display: flex;
    gap: 15px;
    margin: 10px 0;
}

.almacen-radio-group.grid-layout {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
}

.almacen-radio-group.list-layout {
    flex-direction: column;
}

.almacen-radio-item {
    position: relative;
}

.almacen-radio-item input[type="radio"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.almacen-radio-label {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
    border: 2px solid #444;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    background: #2d2d2d;
    color: #e0e0e0;
}

.almacen-radio-label:hover {
    border-color: #58a6ff;
    background: #383838;
}

.almacen-radio-item input[type="radio"]:checked + .almacen-radio-label {
    border-color: #58a6ff;
    background: #1a4480;
    color: white;
}

.almacen-icon {
    font-size: 1.5rem;
}

.almacen-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.almacen-info strong {
    font-size: 0.9rem;
}

.almacen-info small {
    font-size: 0.8rem;
    opacity: 0.8;
}

.almacen-select-search {
    min-width: 200px;
}

.form-help {
    display: block;
    margin-top: 5px;
    font-size: 0.8rem;
    color: #a0a0a0;
}

.required {
    color: #da3633;
}
</style>';

// Función para incluir CSS
function incluirCSSSelector() {
    global $css_selector_almacen;
    echo $css_selector_almacen;
}
?>