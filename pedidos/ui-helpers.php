<?php
/**
 * Funciones auxiliares para la interfaz de usuario
 * Separa las funciones de presentación del archivo principal
 */

/**
 * Genera pills de estado para los pedidos
 */
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

/**
 * Formatea la lista de productos para mostrar
 */
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

/**
 * Genera badge de estado optimizado
 */
function generate_status_badge($value, $type) {
    $badges = [
        'pagado' => [
            '1' => ['class' => 'status-si', 'text' => '✅ Sí'],
            '0' => ['class' => 'status-no', 'text' => '⏳ No']
        ],
        'enviado' => [
            '1' => ['class' => 'status-si', 'text' => '✅ Sí'],
            '0' => ['class' => 'status-no', 'text' => '⏳ No']
        ],
        'comprobante' => [
            '1' => ['class' => 'status-si', 'text' => '✅ Sí'],
            '0' => ['class' => 'status-no', 'text' => '⏳ No']
        ],
        'guia' => [
            '1' => ['class' => 'status-si', 'text' => '✅ Sí'],
            '0' => ['class' => 'status-no', 'text' => '⏳ No']
        ],
        'archivado' => [
            '1' => ['class' => 'status-archivado', 'text' => '📁 Sí'],
            '0' => ['class' => 'status-activo', 'text' => '📂 No']
        ],
        'anulado' => [
            '1' => ['class' => 'status-anulado', 'text' => '❌ Sí'],
            '0' => ['class' => 'status-activo', 'text' => '✅ No']
        ],
        'tienda' => [
            '1' => ['class' => 'status-si', 'text' => '🏪 Sí'],
            '0' => ['class' => 'status-no', 'text' => '⏳ No']
        ]
    ];
    
    $badge = $badges[$type][$value] ?? ['class' => 'status-unknown', 'text' => '?'];
    return '<span class="badge-status ' . $badge['class'] . '">' . $badge['text'] . '</span>';
}

/**
 * Formatea fecha de manera consistente
 */
function format_date($fecha) {
    return [
        'fecha_principal' => date('d/m/Y', strtotime($fecha)),
        'hora_pedido' => date('H:i', strtotime($fecha))
    ];
}

/**
 * Limpia número de teléfono para WhatsApp
 */
function clean_phone_for_whatsapp($phone) {
    return preg_replace('/[^0-9]/', '', $phone);
}

/**
 * Genera el HTML para información del cliente
 */
function generate_customer_info($pedido) {
    $phone_clean = clean_phone_for_whatsapp($pedido['telefono']);
    
    return [
        'nombre' => htmlspecialchars($pedido['nombre']),
        'telefono_display' => htmlspecialchars($pedido['telefono']),
        'telefono_whatsapp' => $phone_clean,
        'ciudad' => htmlspecialchars($pedido['ciudad'])
    ];
}

/**
 * Genera opciones para filtros de forma optimizada
 */
function generate_filter_options($items, $selected_value, $placeholder, $icon = '') {
    $html = "<option value=\"\">$icon $placeholder</option>";
    foreach($items as $item) {
        $selected = ($selected_value == $item) ? 'selected' : '';
        $html .= "<option value=\"" . htmlspecialchars($item) . "\" $selected>";
        $html .= htmlspecialchars($item);
        $html .= "</option>";
    }
    return $html;
}

/**
 * Genera card móvil para un pedido
 */
function generate_mobile_card($pedido) {
    $fecha_info = format_date($pedido['fecha']);
    $cliente_info = generate_customer_info($pedido);
    
    $html = '<div class="mobile-card" data-id="' . $pedido['id'] . '">';
    
    // Header del card
    $html .= '<div class="mobile-card-header">';
    $html .= '<div class="mobile-card-id">#' . $pedido['id'] . '</div>';
    $html .= '<div class="mobile-card-date">' . $fecha_info['fecha_principal'] . ' ' . $fecha_info['hora_pedido'] . '</div>';
    $html .= '</div>';
    
    // Body del card
    $html .= '<div class="mobile-card-body">';
    
    // Cliente
    $html .= '<div class="mobile-cliente">';
    $html .= '<div class="mobile-cliente-info">';
    $html .= '<div class="mobile-cliente-nombre">' . $cliente_info['nombre'] . '</div>';
    $html .= '<div class="mobile-cliente-contacto">';
    $html .= '<span>' . $cliente_info['telefono_display'] . ' - ' . $cliente_info['ciudad'] . '</span>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<button class="mobile-whatsapp-btn" onclick="abrirWhatsApp(\'' . $cliente_info['telefono_whatsapp'] . '\')" title="WhatsApp">📱</button>';
    $html .= '</div>';
    
    // Monto
    $html .= '<div class="mobile-monto">';
    $html .= '<span class="mobile-monto-label">Total</span>';
    $html .= '<span class="mobile-monto-valor">$' . number_format($pedido['monto'], 0, ',', '.') . '</span>';
    $html .= '</div>';
    
    // Estados
    $html .= '<div class="mobile-estados">';
    $html .= generate_mobile_estado_item('💳', 'Pagado', $pedido['pagado'], 'toggleEstadoPago(' . $pedido['id'] . ', ' . $pedido['pagado'] . ', \'' . htmlspecialchars($pedido['comprobante']) . '\', \'' . $pedido['tiene_comprobante'] . '\', \'' . htmlspecialchars($pedido['metodo_pago']) . '\')');
    $html .= generate_mobile_estado_item('🚚', 'Enviado', $pedido['enviado']);
    $html .= generate_mobile_estado_item('📄', 'Comprobante', $pedido['tiene_comprobante'], 'abrirModalComprobante(' . $pedido['id'] . ', \'' . htmlspecialchars($pedido['comprobante']) . '\', \'' . $pedido['tiene_comprobante'] . '\', \'' . htmlspecialchars($pedido['metodo_pago']) . '\')');
    $html .= generate_mobile_estado_item('📦', 'Guía', $pedido['tiene_guia'], 'abrirModalGuia(' . $pedido['id'] . ', \'' . htmlspecialchars($pedido['guia']) . '\', \'' . $pedido['tiene_guia'] . '\', \'' . $pedido['enviado'] . '\')');
    $html .= '</div>';
    
    // Acciones
    $html .= '<div class="mobile-acciones">';
    $html .= '<button class="mobile-btn" onclick="toggleProductos(' . $pedido['id'] . ')">👁️ Ver Productos</button>';
    $html .= '<button class="mobile-btn primary" onclick="abrirDetallePopup(' . $pedido['id'] . ')">⚙️ Configurar</button>';
    $html .= '</div>';
    
    $html .= '</div>'; // mobile-card-body
    $html .= '</div>'; // mobile-card
    
    return $html;
}

/**
 * Genera item de estado para móvil
 */
function generate_mobile_estado_item($icon, $label, $value, $onclick = '') {
    $onclick_attr = $onclick ? 'onclick="' . $onclick . '"' : '';
    $class_value = $value == '1' ? 'si' : 'no';
    $text_value = $value == '1' ? 'Sí' : 'No';
    
    return '<div class="mobile-estado-item" ' . $onclick_attr . '>' .
           '<span class="mobile-estado-label">' . $icon . ' ' . $label . '</span>' .
           '<span class="mobile-estado-valor ' . $class_value . '">' . $text_value . '</span>' .
           '</div>';
}

/**
 * Genera mensaje vacío para móvil
 */
function generate_mobile_empty($message = 'No hay pedidos para este filtro') {
    return '<div class="mobile-empty">' .
           '<div class="mobile-empty-icon">📭</div>' .
           '<div class="mobile-empty-title">Sin resultados</div>' .
           '<div class="mobile-empty-subtitle">' . htmlspecialchars($message) . '</div>' .
           '</div>';
}
?>