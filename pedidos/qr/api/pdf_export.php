<?php
/**
 * API - Exportación PDF de Códigos QR
 * Sequoia Speed - Sistema QR
 */

defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once dirname(dirname(__DIR__)) . '/config_secure.php';
require_once dirname(dirname(__DIR__)) . '/accesos/middleware/AuthMiddleware.php';

try {
    // Verificar autenticación y permisos
    $auth = new AuthMiddleware($conn);
    $current_user = $auth->requirePermission('qr', 'leer');
    
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido', 405);
    }
    
    // Verificar CSRF token
    if (!isset($_POST['csrf_token']) || !$auth->verifyCSRF($_POST['csrf_token'])) {
        throw new Exception('Token CSRF inválido', 403);
    }
    
    // Obtener IDs de QR a exportar
    $qr_ids = $_POST['qr_ids'] ?? '';
    if (empty($qr_ids)) {
        throw new Exception('No se especificaron códigos QR para exportar', 400);
    }
    
    // Convertir IDs a array y validar
    $qr_ids_array = explode(',', $qr_ids);
    $qr_ids_array = array_map('intval', $qr_ids_array);
    $qr_ids_array = array_filter($qr_ids_array, function($id) { return $id > 0; });
    
    if (empty($qr_ids_array)) {
        throw new Exception('IDs de QR inválidos', 400);
    }
    
    // Limitar cantidad para evitar sobrecarga
    if (count($qr_ids_array) > 50) {
        throw new Exception('Máximo 50 códigos QR por exportación', 400);
    }
    
    // Obtener datos de los QR
    $placeholders = str_repeat('?,', count($qr_ids_array) - 1) . '?';
    $query = "
        SELECT 
            qr.id,
            qr.qr_uuid,
            qr.qr_content,
            qr.entity_type,
            qr.entity_id,
            qr.created_at,
            p.nombre as producto_nombre,
            p.sku as producto_sku,
            p.precio as producto_precio,
            c.nombre as categoria_nombre,
            a.nombre as almacen_nombre
        FROM qr_codes qr
        LEFT JOIN productos p ON qr.linked_product_id = p.id
        LEFT JOIN categorias_productos c ON p.categoria_id = c.id
        LEFT JOIN almacenes a ON qr.linked_almacen_id = a.id
        WHERE qr.id IN ($placeholders) AND qr.active = 1
        ORDER BY qr.created_at DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param(str_repeat('i', count($qr_ids_array)), ...$qr_ids_array);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $qr_codes = [];
    while ($row = $result->fetch_assoc()) {
        $qr_codes[] = $row;
    }
    
    if (empty($qr_codes)) {
        throw new Exception('No se encontraron códigos QR válidos', 404);
    }
    
    // Generar HTML para PDF
    $html = generateQRPDF($qr_codes);
    
    // Headers para descarga
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="codigos_qr_' . date('Y-m-d_H-i-s') . '.html"');
    
    echo $html;
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function generateQRPDF($qr_codes) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Códigos QR - Sequoia Speed</title>
        <style>
            body {
                margin: 0;
                padding: 20px;
                font-family: Arial, sans-serif;
                background: white;
            }
            
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #333;
                padding-bottom: 20px;
            }
            
            .header h1 {
                margin: 0;
                color: #333;
                font-size: 24px;
            }
            
            .header p {
                margin: 5px 0 0 0;
                color: #666;
                font-size: 14px;
            }
            
            .qr-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 20px;
                margin-bottom: 20px;
            }
            
            .qr-card {
                border: 1px solid #333;
                padding: 15px;
                text-align: center;
                page-break-inside: avoid;
                background: white;
            }
            
            .qr-image {
                max-width: 120px;
                height: 120px;
                margin: 0 auto 10px auto;
                display: block;
            }
            
            .qr-content {
                font-family: 'Courier New', monospace;
                font-size: 9px;
                word-break: break-all;
                margin-bottom: 8px;
                padding: 5px;
                background: #f5f5f5;
                border: 1px solid #ddd;
            }
            
            .qr-info {
                font-size: 11px;
                text-align: left;
            }
            
            .qr-info strong {
                display: block;
                margin-bottom: 3px;
                font-size: 12px;
            }
            
            .qr-info .detail {
                margin-bottom: 2px;
                color: #555;
            }
            
            .entity-badge {
                display: inline-block;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 9px;
                font-weight: bold;
                text-transform: uppercase;
                margin-bottom: 5px;
            }
            
            .entity-producto { background: #e3f2fd; color: #1976d2; }
            .entity-ubicacion { background: #e8f5e8; color: #388e3c; }
            .entity-lote { background: #fff3e0; color: #f57c00; }
            .entity-pedido { background: #ffebee; color: #d32f2f; }
            .entity-almacen { background: #f3e5f5; color: #7b1fa2; }
            
            @media print {
                body { margin: 0; }
                .qr-grid { gap: 15px; }
                .qr-card { margin-bottom: 10px; }
            }
            
            .footer {
                position: fixed;
                bottom: 20px;
                left: 20px;
                right: 20px;
                text-align: center;
                font-size: 10px;
                color: #666;
                border-top: 1px solid #ddd;
                padding-top: 10px;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>📱 Códigos QR - Sequoia Speed</h1>
            <p>Generado el: <?php echo date('d/m/Y H:i:s'); ?> | Total: <?php echo count($qr_codes); ?> códigos</p>
        </div>
        
        <div class="qr-grid">
            <?php foreach ($qr_codes as $qr): ?>
                <div class="qr-card">
                    <div class="entity-badge entity-<?php echo $qr['entity_type']; ?>">
                        <?php 
                        $entity_icons = [
                            'producto' => '📦',
                            'ubicacion' => '📍',
                            'lote' => '📦',
                            'pedido' => '🛒',
                            'almacen' => '🏪'
                        ];
                        echo $entity_icons[$qr['entity_type']] . ' ' . ucfirst($qr['entity_type']);
                        ?>
                    </div>
                    
                    <div class="qr-image">
                        <img src="data:image/png;base64,<?php echo base64_encode(file_get_contents('https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=' . urlencode($qr['qr_content']))); ?>" 
                             alt="QR Code" style="width: 120px; height: 120px;">
                    </div>
                    
                    <div class="qr-content"><?php echo htmlspecialchars($qr['qr_content']); ?></div>
                    
                    <div class="qr-info">
                        <?php if ($qr['producto_nombre']): ?>
                            <strong><?php echo htmlspecialchars($qr['producto_nombre']); ?></strong>
                            <?php if ($qr['producto_sku']): ?>
                                <div class="detail">SKU: <?php echo htmlspecialchars($qr['producto_sku']); ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <div class="detail">ID: <?php echo htmlspecialchars($qr['entity_id']); ?></div>
                        <div class="detail">Creado: <?php echo date('d/m/Y', strtotime($qr['created_at'])); ?></div>
                        
                        <?php if ($qr['categoria_nombre']): ?>
                            <div class="detail">Categoría: <?php echo htmlspecialchars($qr['categoria_nombre']); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($qr['almacen_nombre']): ?>
                            <div class="detail">Almacén: <?php echo htmlspecialchars($qr['almacen_nombre']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="footer">
            Sistema Sequoia Speed - Gestión de Códigos QR | <?php echo count($qr_codes); ?> códigos exportados
        </div>
        
        <script>
            // Auto-print cuando se abre el documento
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 1000);
            };
        </script>
    </body>
    </html>
    <?php
    return ob_get_clean();
}
?>