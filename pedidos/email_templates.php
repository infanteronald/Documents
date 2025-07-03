<?php
/**
 * Plantillas de Email Profesionales - Estilo VSCode Dark + Apple
 * Sistema de emails con diseño moderno y responsivo
 */

class EmailTemplates {

    /**
     * Plantilla principal VSCode Dark + Apple
     */
    public static function getMainTemplate($title, $content, $footerText = '') {
        return '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
    <style>
        /* Reset y base */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Helvetica Neue", Arial, sans-serif;
            background: #0d1117 !important;
            color: #e6edf3 !important;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
        }

        /* Container principal */
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background: #161b22 !important;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.6);
            border: 1px solid #30363d;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #1f6feb, #0969da) !important;
            padding: 24px;
            text-align: center;
            border-bottom: 1px solid #30363d;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
        }

        .logo {
            width: 60px;
            height: 60px;
            object-fit: contain;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            padding: 8px;
        }

        .header-content {
            text-align: left;
        }

        .header h1 {
            color: #ffffff !important;
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .header .subtitle {
            color: rgba(255, 255, 255, 0.9) !important;
            font-size: 14px;
            margin-top: 4px;
            font-weight: 400;
        }

        /* Content */
        .content {
            padding: 32px 24px;
        }

        .section {
            margin-bottom: 32px;
        }

        .section h2 {
            color: #e6edf3 !important;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 16px;
            border-bottom: 2px solid #1f6feb;
            padding-bottom: 8px;
        }

        .section p {
            margin-bottom: 12px;
            color: #8b949e !important;
            font-size: 15px;
        }

        /* Info cards */
        .info-card {
            background: #21262d !important;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 16px;
        }

        .info-card h3 {
            color: #1f6feb !important;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
        }

        .info-card h3::before {
            content: "▸";
            margin-right: 8px;
            color: #1f6feb !important;
        }

        .info-row {
            display: flex;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }

        .info-label {
            color: #8b949e !important;
            font-weight: 500;
            min-width: 140px;
            font-size: 14px;
        }

        .info-value {
            color: #e6edf3 !important;
            font-weight: 600;
            flex: 1;
            font-size: 14px;
        }

        /* Table para productos */
        .products-table {
            width: 100%;
            border-collapse: collapse;
            background: #21262d !important;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #30363d;
        }

        .products-table th {
            background: #1f6feb !important;
            color: #ffffff !important;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .products-table td {
            padding: 12px;
            border-bottom: 1px solid #30363d;
            color: #e6edf3 !important;
            font-size: 14px;
        }

        .products-table tr:last-child td {
            border-bottom: none;
        }

        .products-table .price {
            color: #1f6feb !important;
            font-weight: 600;
            text-align: right;
        }

        /* Status badges */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-success {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
            border: 1px solid #28a745;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
            border: 1px solid #ffc107;
        }

        .status-error {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
            border: 1px solid #dc3545;
        }

        /* Buttons */
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #1f6feb !important;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            background: #0969da !important;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: transparent !important;
            border: 1px solid #30363d !important;
            color: #e6edf3 !important;
        }

        .btn-secondary:hover {
            background: #21262d !important;
            color: #e6edf3 !important;
        }

        /* Footer */
        .footer {
            background: #0d1117 !important;
            padding: 24px;
            text-align: center;
            border-top: 1px solid #30363d;
        }

        .footer p {
            color: #8b949e !important;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .footer .company-info {
            color: #e6edf3 !important;
            font-weight: 600;
            margin-bottom: 16px;
        }

        .social-links a {
            color: #1f6feb !important;
            text-decoration: none;
            margin: 0 8px;
            font-size: 14px;
        }

        /* Responsive */
        @media (max-width: 600px) {
            .email-container {
                margin: 0;
                border-radius: 0;
            }

            .header {
                padding: 24px 16px;
            }

            .content {
                padding: 24px 16px;
            }

            .info-row {
                flex-direction: column;
            }

            .info-label {
                min-width: auto;
                margin-bottom: 4px;
            }

            .products-table {
                font-size: 12px;
            }

            .products-table th,
            .products-table td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <img src="https://sequoiaspeed.com.co/pedidos/logo.png" alt="Sequoia Speed" class="logo" />
            <div class="header-content">
                <h1>' . htmlspecialchars($title) . '</h1>
                <div class="subtitle">Sistema de Pedidos Sequoia Speed</div>
            </div>
        </div>

        <div class="content">
            ' . $content . '
        </div>

        <div class="footer">
            <div class="company-info">Sequoia Speed</div>
            <p>Sistema automatizado de gestión de pedidos</p>
            <p>© ' . date('Y') . ' Sequoia Speed. Todos los derechos reservados.</p>
            ' . ($footerText ? '<p>' . htmlspecialchars($footerText) . '</p>' : '') . '
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Plantilla para nuevo pedido
     */
    public static function nuevoPedido($pedidoData) {
        $content = '
        <div class="section">
            <h2>📦 Nuevo Pedido Recibido</h2>
            <p>Se ha registrado un nuevo pedido en el sistema. A continuación los detalles:</p>
        </div>

        <div class="info-card">
            <h3>Información del Pedido</h3>
            <div class="info-row">
                <span class="info-label">Número de Pedido:</span>
                <span class="info-value">#' . htmlspecialchars($pedidoData['numero_pedido']) . '</span>
            </div>
            <div class="info-row">
                <span class="info-label">Fecha:</span>
                <span class="info-value">' . date('d/m/Y H:i:s') . '</span>
            </div>
            <div class="info-row">
                <span class="info-label">Estado:</span>
                <span class="info-value"><span class="status-badge status-pending">Pendiente</span></span>
            </div>
            <div class="info-row">
                <span class="info-label">Método de Pago:</span>
                <span class="info-value">' . htmlspecialchars($pedidoData['metodo_pago']) . '</span>
            </div>
            <div class="info-row">
                <span class="info-label">Monto Total:</span>
                <span class="info-value">$' . number_format($pedidoData['monto'], 0, ',', '.') . ' COP</span>
            </div>
        </div>

        <div class="info-card">
            <h3>Información del Cliente</h3>
            <div class="info-row">
                <span class="info-label">Nombre:</span>
                <span class="info-value">' . htmlspecialchars($pedidoData['nombre']) . '</span>
            </div>
            <div class="info-row">
                <span class="info-label">Email:</span>
                <span class="info-value">' . htmlspecialchars($pedidoData['correo']) . '</span>
            </div>
            <div class="info-row">
                <span class="info-label">Teléfono:</span>
                <span class="info-value">' . htmlspecialchars($pedidoData['telefono']) . '</span>
            </div>
            <div class="info-row">
                <span class="info-label">Dirección:</span>
                <span class="info-value">' . htmlspecialchars($pedidoData['direccion']) . '</span>
            </div>
            <div class="info-row">
                <span class="info-label">Ciudad:</span>
                <span class="info-value">' . htmlspecialchars($pedidoData['ciudad']) . '</span>
            </div>
            <div class="info-row">
                <span class="info-label">Barrio:</span>
                <span class="info-value">' . htmlspecialchars($pedidoData['barrio']) . '</span>
            </div>
        </div>';

        // Agregar detalles del pedido si existen
        if (isset($pedidoData['detalles']) && !empty($pedidoData['detalles'])) {
            $content .= '
            <div class="section">
                <h2>🛍️ Detalles del Pedido</h2>
                <table class="products-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio Unit.</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>';

            foreach ($pedidoData['detalles'] as $item) {
                $content .= '
                        <tr>
                            <td>' . htmlspecialchars($item['nombre']) . '</td>
                            <td>' . intval($item['cantidad']) . '</td>
                            <td class="price">$' . number_format($item['precio'], 0, ',', '.') . '</td>
                            <td class="price">$' . number_format($item['precio'] * $item['cantidad'], 0, ',', '.') . '</td>
                        </tr>';
            }

            // Agregar fila de total
            $content .= '
                        <tr style="border-top: 2px solid #30363d;">
                            <td colspan="3" style="text-align: right; font-weight: bold; color: #f0f6fc !important; padding: 12px 8px;">TOTAL:</td>
                            <td class="price" style="font-weight: bold; font-size: 16px; color: #58a6ff !important;">$' . number_format($pedidoData['monto'], 0, ',', '.') . ' COP</td>
                        </tr>';

            $content .= '
                    </tbody>
                </table>
            </div>';
        } else {
            $content .= '
            <div class="info-card">
                <h3>Descripción del Pedido</h3>
                <p style="color: #f0f6fc !important; font-size: 15px; line-height: 1.6;">' . nl2br(htmlspecialchars($pedidoData['pedido_texto'] ?? 'No especificado')) . '</p>
            </div>';
        }

        // Agregar información de pago Bold si existe
        if (isset($pedidoData['bold_order_id'])) {
            $content .= '
            <div class="info-card">
                <h3>💳 Información de Pago Bold</h3>
                <div class="info-row">
                    <span class="info-label">ID de Orden Bold:</span>
                    <span class="info-value">' . htmlspecialchars($pedidoData['bold_order_id']) . '</span>
                </div>';

            if (isset($pedidoData['bold_transaction_id'])) {
                $content .= '
                <div class="info-row">
                    <span class="info-label">ID de Transacción:</span>
                    <span class="info-value">' . htmlspecialchars($pedidoData['bold_transaction_id']) . '</span>
                </div>';
            }

            $content .= '
                <div class="info-row">
                    <span class="info-label">Estado del Pago:</span>
                    <span class="info-value">';

            $estado_pago = $pedidoData['estado_pago'] ?? 'pendiente';
            switch ($estado_pago) {
                case 'pagado':
                    $content .= '<span class="status-badge status-success">Pagado</span>';
                    break;
                case 'fallido':
                    $content .= '<span class="status-badge status-error">Fallido</span>';
                    break;
                default:
                    $content .= '<span class="status-badge status-pending">Pendiente</span>';
            }

            $content .= '</span>
                </div>
            </div>';
        }

        if (isset($pedidoData['comentario']) && !empty($pedidoData['comentario'])) {
            $content .= '
            <div class="info-card">
                <h3>📝 Comentarios Adicionales</h3>
                <p style="color: #f0f6fc !important; font-size: 15px; line-height: 1.6;">' . nl2br(htmlspecialchars($pedidoData['comentario'])) . '</p>
            </div>';
        }

        $content .= '
        <div class="section" style="text-align: center; margin-top: 32px;">
            <a href="https://sequoiaspeed.com.co/pedidos/ver_detalle_pedido_cliente.php?id=' . $pedidoData['numero_pedido'] . '" class="btn">
                Ver Mi Pedido
            </a>
        </div>';

        return self::getMainTemplate(
            'Nuevo Pedido #' . $pedidoData['numero_pedido'],
            $content,
            'Este email fue generado automáticamente por el sistema de pedidos.'
        );
    }

    /**
     * Plantilla para confirmación de pago
     */
    public static function confirmacionPago($pedidoData) {
        $content = '
        <div class="section">
            <h2>✅ Pago Confirmado</h2>
            <p>¡Excelente! Se ha confirmado el pago de su pedido. Procederemos con el envío.</p>
        </div>

        <div class="info-card">
            <h3>Información del Pago</h3>
            <div class="info-row">
                <span class="info-label">Pedido:</span>
                <span class="info-value">#' . htmlspecialchars($pedidoData['numero_pedido']) . '</span>
            </div>
            <div class="info-row">
                <span class="info-label">Monto Pagado:</span>
                <span class="info-value">$' . number_format($pedidoData['monto'], 0, ',', '.') . ' COP</span>
            </div>
            <div class="info-row">
                <span class="info-label">Método:</span>
                <span class="info-value">' . htmlspecialchars($pedidoData['metodo_pago']) . '</span>
            </div>
            <div class="info-row">
                <span class="info-label">Estado:</span>
                <span class="info-value"><span class="status-badge status-success">Pagado</span></span>
            </div>
        </div>';

        if (isset($pedidoData['bold_transaction_id'])) {
            $content .= '
            <div class="info-card">
                <h3>Comprobante de Transacción</h3>
                <div class="info-row">
                    <span class="info-label">ID Transacción:</span>
                    <span class="info-value">' . htmlspecialchars($pedidoData['bold_transaction_id']) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Fecha:</span>
                    <span class="info-value">' . date('d/m/Y H:i:s') . '</span>
                </div>
            </div>';
        }

        return self::getMainTemplate(
            'Pago Confirmado - Pedido #' . $pedidoData['numero_pedido'],
            $content,
            'Gracias por su confianza en Sequoia Speed.'
        );
    }

    /**
     * Plantilla de email para el equipo cuando un cliente agrega un comentario
     */
    public static function generarEmailComentarioEquipo($data) {
        $content = '
        <div class="section">
            <h2>💬 Nuevo Comentario de Cliente</h2>
            <p style="color: #f0f6fc !important; font-size: 16px; margin-bottom: 24px;">
                El cliente <strong>' . htmlspecialchars($data['nombre_cliente']) . '</strong> ha agregado un comentario al pedido <strong>#' . $data['numero_pedido'] . '</strong>
            </p>
        </div>

        <div class="section">
            <h3>📝 Comentario del Cliente</h3>
            <div style="background: #21262d !important; border: 1px solid #30363d; border-radius: 8px; padding: 20px; margin: 16px 0;">
                <p style="color: #e6edf3 !important; font-size: 15px; line-height: 1.6; margin: 0; font-style: italic;">
                    "' . nl2br(htmlspecialchars($data['comentario'])) . '"
                </p>
            </div>
            <p style="color: #8b949e !important; font-size: 14px; margin-top: 12px;">
                📅 Fecha: ' . $data['timestamp'] . '<br>
                👤 Cliente: ' . htmlspecialchars($data['nombre_cliente']) . '<br>
                📧 Email: ' . htmlspecialchars($data['correo_cliente']) . '
            </p>
        </div>

        <div class="section" style="text-align: center; margin-top: 32px;">
            <a href="https://sequoiaspeed.com.co/pedidos/ver_detalle_pedido.php?id=' . $data['numero_pedido'] . '" class="btn">
                Ver Pedido Completo
            </a>
        </div>';

        return self::getMainTemplate(
            '💬 Nuevo Comentario - Pedido #' . $data['numero_pedido'],
            $content,
            'Notificación automática del sistema de comentarios.'
        );
    }

    /**
     * Plantilla de email de confirmación para el cliente
     */
    public static function generarEmailComentarioCliente($data) {
        $content = '
        <div class="section">
            <h2>✅ Comentario Recibido</h2>
            <p style="color: #f0f6fc !important; font-size: 16px; margin-bottom: 24px;">
                Hola <strong>' . htmlspecialchars($data['nombre_cliente']) . '</strong>,<br><br>
                Hemos recibido tu comentario sobre el pedido <strong>#' . $data['numero_pedido'] . '</strong>.
                Nuestro equipo lo revisará y te responderemos a la brevedad.
            </p>
        </div>

        <div class="section">
            <h3>💬 Tu Comentario</h3>
            <div style="background: #21262d !important; border: 1px solid #30363d; border-radius: 8px; padding: 20px; margin: 16px 0;">
                <p style="color: #e6edf3 !important; font-size: 15px; line-height: 1.6; margin: 0;">
                    ' . nl2br(htmlspecialchars($data['comentario'])) . '
                </p>
            </div>
            <p style="color: #8b949e !important; font-size: 14px; margin-top: 12px;">
                📅 Fecha: ' . $data['timestamp'] . '
            </p>
        </div>

        <div class="section">
            <h3>📞 ¿Necesitas Ayuda Inmediata?</h3>
            <p style="color: #f0f6fc !important; font-size: 15px; line-height: 1.6;">
                Si tu consulta es urgente, puedes contactarnos directamente:
            </p>
            <ul style="color: #f0f6fc !important; font-size: 15px; line-height: 1.8; margin: 12px 0; padding-left: 20px;">
                <li>📧 Email: ventas@sequoiaspeed.com.co</li>
                <li>📱 WhatsApp: +57 300 123 4567</li>
                <li>⏰ Horario: Lunes a Viernes 8:00 AM - 6:00 PM</li>
            </ul>
        </div>

        <div class="section" style="text-align: center; margin-top: 32px;">
            <a href="https://sequoiaspeed.com.co/pedidos/ver_detalle_pedido_cliente.php?id=' . $data['numero_pedido'] . '" class="btn">
                Ver Mi Pedido
            </a>
        </div>';

        return self::getMainTemplate(
            '✅ Comentario Recibido - Pedido #' . $data['numero_pedido'],
            $content,
            'Gracias por contactarnos. Te responderemos pronto.'
        );
    }

    /**
     * Plantilla de email para el equipo cuando se cambia el estado de un pedido
     */
    public static function generarEmailCambioEstado($data) {
        $content = '
        <div class="section">
            <h2>🔄 Estado de Pedido Actualizado</h2>
            <p style="color: #f0f6fc !important; font-size: 16px; margin-bottom: 24px;">
                El pedido <strong>#' . $data['numero_pedido'] . '</strong> ha cambiado su estado a <strong>' . htmlspecialchars($data['nuevo_estado']) . '</strong>
            </p>
        </div>

        <div class="section">
            <h3>📋 Información del Pedido</h3>
            <div style="background: #21262d !important; border: 1px solid #30363d; border-radius: 8px; padding: 20px; margin: 16px 0;">
                <p style="color: #e6edf3 !important; font-size: 15px; line-height: 1.6; margin: 0;">
                    <strong>Cliente:</strong> ' . htmlspecialchars($data['nombre_cliente']) . '<br>
                    <strong>Email:</strong> ' . htmlspecialchars($data['correo_cliente']) . '<br>
                    <strong>Teléfono:</strong> ' . htmlspecialchars($data['telefono_cliente']) . '<br>
                    <strong>Ciudad:</strong> ' . htmlspecialchars($data['ciudad_cliente']) . '<br>
                    <strong>Barrio:</strong> ' . htmlspecialchars($data['barrio_cliente']) . '<br>
                    <strong>Monto:</strong> $' . number_format($data['monto'], 0, ',', '.') . '<br>
                    <strong>Nuevo Estado:</strong> <span style="color: #3fb950 !important; font-weight: bold;">' . htmlspecialchars($data['nuevo_estado']) . '</span>
                </p>
            </div>
            <p style="color: #8b949e !important; font-size: 14px; margin-top: 12px;">
                📅 Actualizado: ' . $data['timestamp'] . '
            </p>
        </div>

        <div class="section" style="text-align: center; margin-top: 32px;">
            <a href="https://sequoiaspeed.com.co/pedidos/ver_detalle_pedido.php?id=' . $data['numero_pedido'] . '" class="btn">
                Ver Pedido Completo
            </a>
        </div>';

        return self::getMainTemplate(
            $data['nuevo_estado'] . ' - Pedido #' . $data['numero_pedido'],
            $content,
            'Notificación automática del sistema de pedidos.'
        );
    }

    /**
     * Plantilla de email para el cliente cuando se cambia el estado de su pedido
     */
    public static function generarEmailCambioEstadoCliente($data) {
        $content = '
        <div class="section">
            <h2>📦 Tu Pedido ha sido Actualizado</h2>
            <p style="color: #f0f6fc !important; font-size: 16px; margin-bottom: 24px;">
                Hola <strong>' . htmlspecialchars($data['nombre_cliente']) . '</strong>,<br><br>
                Te informamos que tu pedido <strong>#' . $data['numero_pedido'] . '</strong> ha sido actualizado.
            </p>
        </div>

        <div class="section">
            <h3>🔄 Nuevo Estado</h3>
            <div style="background: #21262d !important; border: 1px solid #30363d; border-radius: 8px; padding: 20px; margin: 16px 0; text-align: center;">
                <p style="color: #3fb950 !important; font-size: 24px; font-weight: bold; margin: 0;">
                    ' . htmlspecialchars($data['nuevo_estado']) . '
                </p>
            </div>
            <p style="color: #8b949e !important; font-size: 14px; margin-top: 12px;">
                📅 Actualizado: ' . $data['timestamp'] . '
            </p>
        </div>

        <div class="section">
            <h3>📞 ¿Tienes Preguntas?</h3>
            <p style="color: #f0f6fc !important; font-size: 15px; line-height: 1.6;">
                Si tienes alguna pregunta sobre tu pedido, no dudes en contactarnos:
            </p>
            <ul style="color: #f0f6fc !important; font-size: 15px; line-height: 1.8; margin: 12px 0; padding-left: 20px;">
                <li>📧 Email: ventas@sequoiaspeed.com.co</li>
                <li>📱 WhatsApp: +57 300 123 4567</li>
                <li>⏰ Horario: Lunes a Viernes 8:00 AM - 6:00 PM</li>
            </ul>
        </div>

        <div class="section" style="text-align: center; margin-top: 32px;">
            <a href="https://sequoiaspeed.com.co/pedidos/ver_detalle_pedido_cliente.php?id=' . $data['numero_pedido'] . '" class="btn">
                Ver Mi Pedido
            </a>
        </div>';

        return self::getMainTemplate(
            $data['nuevo_estado'] . ' - Pedido #' . $data['numero_pedido'] . ' - Sequoia Speed',
            $content,
            'Tu pedido ha sido actualizado. ¡Gracias por confiar en nosotros!'
        );
    }

    /**
     * Email de actualización del pedido
     */
    public static function emailActualizacionPedido($pedido_id, $nombre_cliente, $pedido) {
        $content = '
        <div class="hero">
            <h1>📢 Actualización de tu Pedido</h1>
            <p class="hero-subtitle">Hola ' . htmlspecialchars($nombre_cliente) . ', tenemos novedades sobre tu pedido</p>
        </div>

        <div class="section">
            <h3>📦 Información del Pedido</h3>
            <div class="order-info">
                <div class="order-item">
                    <span class="label">Número de Pedido:</span>
                    <span class="value">#' . $pedido_id . '</span>
                </div>
                <div class="order-item">
                    <span class="label">Estado Actual:</span>
                    <span class="value status-processing">En Procesamiento</span>
                </div>
                <div class="order-item">
                    <span class="label">Fecha:</span>
                    <span class="value">' . date('d/m/Y', strtotime($pedido['fecha'] ?? 'now')) . '</span>
                </div>
            </div>
        </div>

        <div class="section">
            <h3>📋 Detalles de la Actualización</h3>
            <p style="color: #f0f6fc !important; font-size: 15px; line-height: 1.6;">
                Te contactamos para informarte sobre el estado actual de tu pedido.
                Nuestro equipo está trabajando en el procesamiento de tu pedido y te mantendremos
                informado sobre cualquier novedad importante.
            </p>
        </div>

        <div class="section">
            <h3>📞 ¿Tienes Preguntas?</h3>
            <p style="color: #f0f6fc !important; font-size: 15px; line-height: 1.6;">
                Si tienes alguna pregunta sobre tu pedido, no dudes en contactarnos.
            </p>
        </div>';

        return self::getMainTemplate(
            'Actualización de tu pedido #' . $pedido_id . ' - Sequoia Speed',
            $content,
            '¡Gracias por confiar en Sequoia Speed!'
        );
    }

    /**
     * Email de solicitud de seguimiento
     */
    public static function emailSolicitudSeguimiento($pedido_id, $nombre_cliente, $pedido) {
        $content = '
        <div class="hero">
            <h1>🔍 Solicitud de Seguimiento</h1>
            <p class="hero-subtitle">Hola ' . htmlspecialchars($nombre_cliente) . ', nos gustaría conocer tu experiencia</p>
        </div>

        <div class="section">
            <h3>📦 Sobre tu Pedido #' . $pedido_id . '</h3>
            <p style="color: #f0f6fc !important; font-size: 15px; line-height: 1.6;">
                Nos gustaría conocer tu experiencia con este pedido:
            </p>
            <ul style="color: #f0f6fc !important; font-size: 15px; line-height: 1.8; margin: 12px 0; padding-left: 20px;">
                <li>¿Has recibido tu pedido?</li>
                <li>¿Todo llegó en perfecto estado?</li>
                <li>¿Hay algo que podamos mejorar?</li>
            </ul>
        </div>

        <div class="section">
            <h3>💌 Tu Opinión Importa</h3>
            <p style="color: #f0f6fc !important; font-size: 15px; line-height: 1.6;">
                Tu opinión es muy importante para nosotros y nos ayuda a mejorar nuestro servicio.
                Por favor responde este email con cualquier comentario o sugerencia.
            </p>
        </div>';

        return self::getMainTemplate(
            'Solicitud de seguimiento - Pedido #' . $pedido_id,
            $content,
            '¡Gracias por elegirnos!'
        );
    }

    /**
     * Email de confirmación de entrega
     */
    public static function emailConfirmacionEntrega($pedido_id, $nombre_cliente, $pedido) {
        $content = '
        <div class="hero">
            <h1>✅ Entrega Confirmada</h1>
            <p class="hero-subtitle">Hola ' . htmlspecialchars($nombre_cliente) . ', tu pedido ha sido entregado</p>
        </div>

        <div class="section">
            <h3>📦 Pedido Entregado</h3>
            <div class="order-info">
                <div class="order-item">
                    <span class="label">Número de Pedido:</span>
                    <span class="value">#' . $pedido_id . '</span>
                </div>
                <div class="order-item">
                    <span class="label">Estado:</span>
                    <span class="value status-delivered">✅ Entregado</span>
                </div>
                <div class="order-item">
                    <span class="label">Fecha de Entrega:</span>
                    <span class="value">' . date('d/m/Y H:i') . '</span>
                </div>
            </div>
        </div>

        <div class="section">
            <h3>🎉 ¡Gracias por tu Compra!</h3>
            <p style="color: #f0f6fc !important; font-size: 15px; line-height: 1.6;">
                Nos complace informarte que tu pedido ha sido marcado como entregado.
                Esperamos que todo haya llegado en perfectas condiciones y que estés satisfecho con tu compra.
            </p>
            <p style="color: #f0f6fc !important; font-size: 15px; line-height: 1.6;">
                Si tienes algún inconveniente o pregunta sobre tu pedido, por favor contáctanos inmediatamente.
            </p>
        </div>';

        return self::getMainTemplate(
            'Confirmación de entrega - Pedido #' . $pedido_id,
            $content,
            '¡Gracias por confiar en Sequoia Speed!'
        );
    }

    /**
     * Email de entrega con guía
     */
    public static function emailEntregaConGuia($pedido_id, $nombre_cliente, $pedido) {
        $content = '
        <div class="hero">
            <h1>📦 Tu Pedido Está en Camino</h1>
            <p class="hero-subtitle">Hola ' . htmlspecialchars($nombre_cliente) . ', tu pedido ya fue enviado</p>
        </div>

        <div class="section">
            <h3>🚚 Información de Envío</h3>
            <div class="order-info">
                <div class="order-item">
                    <span class="label">Número de Pedido:</span>
                    <span class="value">#' . $pedido_id . '</span>
                </div>
                <div class="order-item">
                    <span class="label">Estado:</span>
                    <span class="value status-shipping">🚚 En Tránsito</span>
                </div>
                <div class="order-item">
                    <span class="label">Fecha de Envío:</span>
                    <span class="value">' . date('d/m/Y H:i') . '</span>
                </div>
            </div>
        </div>

        <div class="section">
            <h3>📄 Guía de Envío Adjunta</h3>
            <p style="color: #f0f6fc !important; font-size: 15px; line-height: 1.6;">
                Te enviamos adjunta la guía de envío para que puedas hacer seguimiento de tu pedido.
                Con esta guía podrás rastrear el estado de tu envío directamente con la transportadora.
            </p>
            <div style="background: #21262d; border: 1px solid #30363d; border-radius: 8px; padding: 16px; margin: 16px 0;">
                <p style="color: #58a6ff !important; font-weight: 600; margin: 0;">
                    📎 Archivo adjunto: guia_envio_pedido_' . $pedido_id . '.pdf
                </p>
            </div>
        </div>

        <div class="section">
            <h3>📱 Seguimiento del Envío</h3>
            <p style="color: #f0f6fc !important; font-size: 15px; line-height: 1.6;">
                Utiliza la guía adjunta para hacer seguimiento en tiempo real de tu envío.
                Si tienes alguna pregunta sobre tu envío, no dudes en contactarnos.
            </p>
        </div>';

        return self::getMainTemplate(
            '📦 Guía de envío - Pedido #' . $pedido_id . ' - Sequoia Speed',
            $content,
            '¡Gracias por confiar en Sequoia Speed!'
        );
    }
}
?>
