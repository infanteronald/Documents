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
                <span class="info-label">Persona que recibe:</span>
                <span class="info-value">' . htmlspecialchars($pedidoData['persona_recibe']) . '</span>
            </div>
            <div class="info-row">
                <span class="info-label">Horarios:</span>
                <span class="info-value">' . htmlspecialchars($pedidoData['horarios']) . '</span>
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
            <a href="https://sequoiaspeed.com.co/pedidos/ver_detalle_pedido.php?id=' . $pedidoData['numero_pedido'] . '" class="btn">
                Ver Detalles Completos
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
}
?>
