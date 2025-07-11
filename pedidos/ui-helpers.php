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
?>