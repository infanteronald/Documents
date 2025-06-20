<?php
/**
 * Ver Detalle de Pedido - Versión Ultra Compatible
 * Sequoia Speed - Máxima compatibilidad con servidores antiguos
 */

// Configuración robusta de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '256M');

// Incluir conexión a base de datos
include 'conexion.php';

// Función para escape HTML seguro
function h($txt) {
    return htmlspecialchars($txt ?? '', ENT_QUOTES, 'UTF-8');
}

// Función para obtener campo de array de manera segura
function getField($array, $field, $default = 'No disponible') {
    // Mapeo de campos alternativos comunes
    $fieldMappings = [
        'nombre_cliente' => ['nombre_cliente', 'nombre', 'cliente_nombre', 'client_name'],
        'email_cliente' => ['email_cliente', 'email', 'cliente_email', 'client_email'],
        'telefono_cliente' => ['telefono_cliente', 'telefono', 'cliente_telefono', 'phone'],
        'fecha_pedido' => ['fecha_pedido', 'fecha', 'created_at', 'date_created'],
        'direccion_entrega' => ['direccion_entrega', 'direccion', 'address', 'delivery_address'],
        'metodo_pago' => ['metodo_pago', 'pago', 'payment_method', 'tipo_pago'],
        'notas' => ['notas', 'observaciones', 'comments', 'notes']
    ];

    // Intentar con el campo directo primero
    if (isset($array[$field]) && !empty($array[$field])) {
        return $array[$field];
    }

    // Si hay mapeos alternativos, intentarlos
    if (isset($fieldMappings[$field])) {
        foreach ($fieldMappings[$field] as $altField) {
            if (isset($array[$altField]) && !empty($array[$altField])) {
                return $array[$altField];
            }
        }
    }

    return $default;
}

// Variables principales
$pedido_encontrado = false;
$id = null;
$p = null;
$productos = [];
$total_productos = 0;
$error_message = '';

// Manejo dual: POST y GET para máxima compatibilidad
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pedido_id'])) {
    $id = intval($_POST['pedido_id']);
} elseif (isset($_GET['id'])) {
    $id = intval($_GET['id']);
}

if ($id && $id > 0) {
    try {
        // Obtener datos del pedido usando consulta directa para compatibilidad
        $id_safe = mysqli_real_escape_string($conn, $id);
        $query = "SELECT * FROM pedidos_detal WHERE id = $id_safe LIMIT 1";
        $result = mysqli_query($conn, $query);

        if ($result && mysqli_num_rows($result) > 0) {
            $pedido_encontrado = true;
            $p = mysqli_fetch_assoc($result);

            // Debug: Mostrar campos disponibles (comentar en producción)
            // echo "<pre>DEBUG - Campos disponibles: " . print_r(array_keys($p), true) . "</pre>";

            // Obtener productos del pedido
            $detalle_query = "SELECT nombre, precio, cantidad, talla FROM pedido_detalle WHERE pedido_id = $id_safe";
            $result_detalle = mysqli_query($conn, $detalle_query);

            if ($result_detalle) {
                while ($item = mysqli_fetch_assoc($result_detalle)) {
                    $productos[] = $item;
                    $total_productos += floatval($item['precio']) * intval($item['cantidad']);
                }
            }
        } else {
            $error_message = "Pedido #$id no encontrado en el sistema.";
        }
    } catch (Exception $e) {
        $error_message = "Error al consultar el pedido: " . $e->getMessage();
    }
} else {
    $error_message = "ID de pedido no válido o no proporcionado.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pedido_encontrado ? 'Pedido #' . h($p['id']) : 'Error'; ?> - Sequoia Speed</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #0d1117 0%, #161b22 100%);
            color: #e6edf3;
            line-height: 1.6;
            min-height: 100vh;
            padding: 20px;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
            text-size-adjust: 100%;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #21262d;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #1f6feb 0%, #0969da 100%);
            padding: 20px 30px;
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }

        .logo {
            max-width: 60px;
            max-height: 60px;
            width: auto;
            height: auto;
            display: block;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 1;
            object-fit: contain;
            flex-shrink: 0;
        }

        .header-content {
            position: relative;
            z-index: 1;
        }

        .header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: white;
            margin-bottom: 4px;
            position: relative;
            z-index: 1;
        }

        .header .subtitle {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            position: relative;
            z-index: 1;
        }

        .content {
            padding: 40px;
        }

        .error {
            background: linear-gradient(135deg, #da3633 0%, #f85149 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
            font-weight: 600;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-card {
            background: #30363d;
            border: 1px solid #3d444d;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .info-card:hover {
            border-color: #1f6feb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(31, 111, 235, 0.15);
        }

        .info-card h3 {
            color: #1f6feb;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .info-card p {
            font-size: 1.1rem;
            font-weight: 500;
            margin: 4px 0;
        }

        .productos-section {
            margin-top: 30px;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #e6edf3;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title::before {
            content: '📦';
            font-size: 1.2rem;
        }

        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 8px;
            margin-bottom: 0;
        }

        .productos-table {
            width: 100%;
            border-collapse: collapse;
            background: #30363d;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .productos-table th {
            background: linear-gradient(135deg, #1f6feb 0%, #0969da 100%);
            color: white;
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .productos-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #3d444d;
            vertical-align: middle;
        }

        .productos-table tbody tr:hover {
            background: #3d444d;
        }

        .productos-table tbody tr:last-child td {
            border-bottom: none;
        }

        .precio {
            font-weight: 600;
            color: #238636;
        }

        .total-section {
            margin-top: 30px;
            text-align: right;
        }

        .total-card {
            display: inline-block;
            background: linear-gradient(135deg, #1f6feb 0%, #0969da 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(35, 134, 54, 0.3);
        }

        .total-card h3 {
            font-size: 1.1rem;
            margin-bottom: 5px;
            opacity: 0.9;
        }

        .total-card .amount {
            font-size: 2rem;
            font-weight: 700;
        }

        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status.pendiente {
            background: linear-gradient(135deg, #fb8500 0%, #ffb700 100%);
            color: white;
        }

        .status.confirmado {
            background: linear-gradient(135deg, #1f6feb 0%, #0969da 100%);
            color: white;
        }

        .status.cancelado {
            background: linear-gradient(135deg, #da3633 0%, #f85149 100%);
            color: white;
        }

        .form-section {
            background: #30363d;
            border: 1px solid #3d444d;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            margin: 20px 0;
        }

        .form-section h2 {
            color: #1f6feb;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #e6edf3;
        }

        .form-group input {
            width: 200px;
            padding: 12px;
            border: 1px solid #3d444d;
            border-radius: 6px;
            background: #21262d;
            color: #e6edf3;
            font-size: 1rem;
            text-align: center;
        }

        .form-group input:focus {
            outline: none;
            border-color: #1f6feb;
            box-shadow: 0 0 0 2px rgba(31, 111, 235, 0.3);
        }

        .btn {
            background: linear-gradient(135deg, #1f6feb 0%, #0969da 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(31, 111, 235, 0.4);
        }

        .print-section {
            text-align: center;
        }

        .print-card {
            background: #30363d;
            border: 1px solid #3d444d;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            margin: 20px 0;
        }

        .print-card h2 {
            color: #1f6feb;
            margin-bottom: 15px;
            font-size: 1.4rem;
        }

        .btn-print {
            background: linear-gradient(135deg, #1f6feb 0%, #0969da 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 0 0 2px rgba(31, 111, 235, 0.3);
        }

        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(35, 134, 54, 0.4);
        }

        .print-icon {
            font-size: 1.2rem;
        }

        /* Estilos de impresión para tamaño carta */
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            @page {
                size: letter; /* Tamaño carta: 8.5 x 11 pulgadas */
                margin: 0.5in; /* Márgenes más pequeños */
            }

            body {
                background: white !important;
                color: #333 !important;
                font-size: 10pt; /* Texto más pequeño */
                line-height: 1.2; /* Espaciado más compacto */
            }

            .container {
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                border: none !important;
            }

            .header {
                background: #1f6feb !important;
                color: white !important;
                padding: 8pt 12pt !important; /* Reducido significativamente */
                margin-bottom: 8pt !important;
                border-radius: 0 !important;
                display: flex !important;
                align-items: center !important;
                gap: 10pt !important;
            }

            .logo {
                max-width: 40pt !important; /* Logo más pequeño */
                max-height: 40pt !important;
                width: auto !important;
                height: auto !important;
                margin: 0 !important;
                box-shadow: none !important;
                object-fit: contain !important;
                flex-shrink: 0 !important;
            }

            .header h1 {
                font-size: 14pt !important; /* Título más pequeño */
                color: white !important;
                margin: 0 !important;
            }

            .header .subtitle {
                font-size: 10pt !important;
                color: white !important;
                margin: 0 !important;
            }

            .content {
                padding: 0 !important;
            }

            .info-grid {
                display: grid !important;
                grid-template-columns: 1fr 1fr 1fr !important; /* 3 columnas para ahorrar espacio */
                gap: 6pt !important;
                margin-bottom: 8pt !important;
            }

            .info-card {
                background: #f8f9fa !important;
                border: 0.5pt solid #dee2e6 !important;
                border-radius: 2pt !important;
                padding: 6pt !important; /* Padding reducido */
                margin-bottom: 4pt !important;
                page-break-inside: avoid;
            }

            .info-card h3 {
                color: #1f6feb !important;
                font-size: 8pt !important; /* Títulos más pequeños */
                margin-bottom: 2pt !important;
                font-weight: bold !important;
            }

            .info-card p {
                font-size: 9pt !important;
                color: #333 !important;
                margin: 0 !important;
            }

            .productos-table {
                background: white !important;
                border: 0.5pt solid #333 !important;
                border-collapse: collapse !important;
                width: 100% !important;
                margin: 8pt 0 !important; /* Márgenes reducidos */
                page-break-inside: avoid;
                font-size: 9pt !important; /* Tabla más pequeña */
            }

            .productos-table th {
                background: #1f6feb !important;
                color: white !important;
                padding: 4pt 6pt !important; /* Padding muy reducido */
                border: 0.5pt solid #333 !important;
                font-size: 8pt !important; /* Texto del header más pequeño */
                font-weight: bold !important;
            }

            .productos-table td {
                padding: 3pt 6pt !important; /* Padding muy reducido */
                border: 0.5pt solid #333 !important;
                font-size: 9pt !important;
                color: #333 !important;
                vertical-align: middle !important;
            }

            .section-title {
                font-size: 11pt !important; /* Título de sección más pequeño */
                margin-bottom: 6pt !important;
                color: #333 !important;
            }

            .total-section {
                margin-top: 8pt !important; /* Menos espacio */
                text-align: right !important;
            }

            .total-card {
                background: #238636 !important;
                color: white !important;
                padding: 8pt 12pt !important; /* Padding reducido */
                border-radius: 3pt !important;
                text-align: center !important;
                margin: 8pt 0 !important;
                display: inline-block !important;
            }

            .total-card h3 {
                font-size: 10pt !important; /* Título más pequeño */
                color: white !important;
                margin-bottom: 2pt !important;
            }

            .total-card .amount {
                font-size: 14pt !important; /* Cantidad más pequeña */
                color: white !important;
                font-weight: bold !important;
            }

            .total-card .amount {
                font-size: 16pt !important;
                color: white !important;
            }

            .status {
                padding: 3pt 8pt !important;
                border-radius: 12pt !important;
                font-size: 9pt !important;
                color: white !important;
            }

            .print-section,
            .print-card,
            .btn-print {
                display: none !important;
            }

            .error,
            .form-section {
                display: none !important;
            }
        }

        /* Media queries para diferentes tamaños de pantalla */

        /* Tablets y pantallas medianas */
        @media (max-width: 1024px) {
            .container {
                margin: 10px;
                border-radius: 8px;
            }

            .info-grid {
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 15px;
            }

            .productos-table th,
            .productos-table td {
                padding: 12px 8px;
                font-size: 0.9rem;
            }
        }

        /* Móviles grandes */
        @media (max-width: 768px) {
            body {
                padding: 5px;
                font-size: 16px; /* Base font size for better scaling */
                line-height: 1.5; /* Mejor legibilidad */
            }

            .container {
                margin: 0;
                border-radius: 8px;
                max-width: 100%;
                box-shadow: none;
                overflow: hidden; /* Evitar desbordamientos */
            }

            .header {
                padding: 20px 15px;
                flex-direction: row;
                justify-content: flex-start;
                align-items: center;
                text-align: left;
                gap: 15px;
                border-radius: 8px 8px 0 0;
                min-height: 70px; /* Altura mínima para touch targets */
            }

            .header h1 {
                font-size: 1.4rem;
                line-height: 1.2;
                margin: 0;
                flex: 1; /* Ocupa el espacio disponible */
            }

            .header .subtitle {
                font-size: 0.85rem;
                opacity: 0.9;
                margin: 2px 0 0 0;
            }

            .logo {
                max-width: 50px;
                max-height: 50px;
                border-radius: 4px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
                flex-shrink: 0; /* No se contraiga */
            }

            .content {
                padding: 20px 15px;
            }

            .info-grid {
                grid-template-columns: 1fr;
                gap: 15px;
                margin-bottom: 25px;
            }

            .info-card {
                padding: 18px;
                border-radius: 8px;
                border: 1px solid #3d444d;
                background: #30363d;
            }

            .info-card h3 {
                font-size: 0.8rem;
                margin-bottom: 8px;
                color: #1f6feb;
                font-weight: 600;
            }

            .info-card p {
                font-size: 1.1rem;
                line-height: 1.4;
                color: #e6edf3;
                margin: 4px 0;
                word-break: break-word;
            }

            .section-title {
                font-size: 1.3rem;
                margin-bottom: 18px;
                text-align: left;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .section-title::before {
                font-size: 1.1rem;
            }

            .productos-section {
                margin-top: 25px;
            }

            /* Mejora del scroll horizontal para tablas */
            .table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                margin: 0 -15px 20px -15px;
                padding: 0 15px;
                border-radius: 8px;
                background: #30363d;
            }

            .productos-table {
                min-width: 650px; /* Aumentado para mejor legibilidad */
                font-size: 0.9rem;
                background: #30363d;
            }

            .productos-table th {
                padding: 12px 8px;
                font-size: 0.8rem;
                letter-spacing: 0.3px;
                background: linear-gradient(135deg, #1f6feb 0%, #0969da 100%);
                position: sticky;
                top: 0;
                z-index: 10;
            }

            .productos-table td {
                padding: 12px 8px;
                font-size: 0.85rem;
                white-space: nowrap;
                border-bottom: 1px solid #3d444d;
            }

            /* Cards móviles para productos (alternativa) */
            .productos-mobile {
                display: none; /* Mantenemos la tabla por ahora */
            }

            .total-section {
                margin-top: 25px;
                text-align: center;
                padding: 0 5px;
            }

            .total-card {
                padding: 20px 25px;
                margin: 20px auto;
                max-width: 300px;
                border-radius: 10px;
                box-shadow: 0 4px 15px rgba(31, 111, 235, 0.3);
            }

            .total-card h3 {
                font-size: 1rem;
                margin-bottom: 8px;
            }

            .total-card .amount {
                font-size: 1.8rem;
                font-weight: 700;
            }

            /* Botones mejorados para móvil */
            .btn, .btn-print {
                padding: 15px 25px;
                font-size: 1rem;
                border-radius: 8px;
                min-height: 44px; /* Touch target size */
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
            }

            .form-section {
                padding: 25px 20px;
                margin: 20px 0;
            }

            .form-group input {
                width: 100%;
                max-width: 250px;
                padding: 15px;
                font-size: 1rem;
                border-radius: 8px;
            }

            .print-card {
                padding: 25px 20px;
                margin: 20px 0;
            }
        }

        /* Móviles pequeños */
        @media (max-width: 480px) {
            body {
                padding: 2px;
                font-size: 15px;
            }

            .container {
                border-radius: 6px;
            }

            .header {
                padding: 15px 12px;
                flex-direction: column;
                text-align: center;
                gap: 12px;
            }

            .header h1 {
                font-size: 1.2rem;
            }

            .header .subtitle {
                font-size: 0.8rem;
            }

            .logo {
                max-width: 45px;
                max-height: 45px;
            }

            .content {
                padding: 15px 12px;
            }

            .info-card {
                padding: 15px;
            }

            .info-card h3 {
                font-size: 0.75rem;
            }

            .info-card p {
                font-size: 1rem;
            }

            .section-title {
                font-size: 1.1rem;
                margin-bottom: 15px;
            }

            .table-container {
                margin: 0 -12px 15px -12px;
                padding: 0 12px;
            }

            .productos-table {
                min-width: 600px;
                font-size: 0.8rem;
            }

            .productos-table th {
                padding: 10px 6px;
                font-size: 0.7rem;
            }

            .productos-table td {
                padding: 10px 6px;
                font-size: 0.75rem;
            }

            .total-card {
                padding: 18px 20px;
                max-width: 280px;
            }

            .total-card .amount {
                font-size: 1.6rem;
            }

            .btn, .btn-print {
                padding: 12px 20px;
                font-size: 0.9rem;
            }

            .total-card h3 {
                font-size: 1rem;
                margin-bottom: 4px;
            }

            .total-card .amount {
                font-size: 1.6rem;
            }

            .print-section {
                margin-top: 30px;
            }

            .print-card {
                padding: 20px 15px;
                margin: 15px 0;
            }

            .print-card h2 {
                font-size: 1.2rem;
                margin-bottom: 12px;
            }

            .print-card p {
                font-size: 0.9rem;
                margin-bottom: 15px;
            }

            .btn-print {
                padding: 12px 24px;
                font-size: 1rem;
                width: 100%;
                max-width: 280px;
            }

            .status {
                font-size: 0.75rem;
                padding: 4px 8px;
            }

            .form-section {
                padding: 20px 15px;
                margin: 15px 0;
            }

            .form-section h2 {
                font-size: 1.2rem;
                margin-bottom: 15px;
            }

            .form-group input {
                width: 100%;
                max-width: 280px;
                font-size: 1rem;
                padding: 14px;
            }

            .error {
                padding: 15px;
                margin: 15px 0;
                font-size: 0.9rem;
                border-radius: 6px;
            }
        }

        /* Estilos específicos para pantallas muy pequeñas */
        @media (max-width: 480px) {
            body {
                padding: 5px;
            }

            .header {
                padding: 12px 15px;
            }

            .header h1 {
                font-size: 1.4rem;
            }

            .content {
                padding: 12px;
            }

            .info-card {
                padding: 12px;
            }

            .info-card h3 {
                font-size: 0.75rem;
            }

            .info-card p {
                font-size: 0.9rem;
            }

            .section-title {
                font-size: 1.1rem;
            }

            .productos-table {
                min-width: 500px;
            }

            .productos-table th,
            .productos-table td {
                padding: 6px 4px;
                font-size: 0.75rem;
            }

            .total-card {
                padding: 12px 15px;
            }

            .total-card .amount {
                font-size: 1.4rem;
            }

            .print-card {
                padding: 15px 12px;
            }

            .btn-print {
                padding: 10px 20px;
                font-size: 0.9rem;
            }
        }

        /* Móviles muy pequeños - Vista alternativa de productos en cards */
        @media (max-width: 380px) {
            .table-container {
                display: none; /* Ocultar tabla en pantallas muy pequeñas */
            }

            .productos-mobile {
                display: block !important;
            }

            .producto-card {
                background: #30363d;
                border: 1px solid #3d444d;
                border-radius: 8px;
                padding: 15px;
                margin-bottom: 12px;
                transition: all 0.3s ease;
            }

            .producto-card:hover {
                border-color: #1f6feb;
                transform: translateY(-1px);
                box-shadow: 0 3px 10px rgba(31, 111, 235, 0.15);
            }

            .producto-card .producto-nombre {
                color: #e6edf3;
                font-weight: 600;
                font-size: 1rem;
                margin-bottom: 8px;
                display: block;
            }

            .producto-card .producto-info {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
                font-size: 0.85rem;
                margin-bottom: 10px;
            }

            .producto-card .producto-info span {
                color: #8b949e;
            }

            .producto-card .producto-info strong {
                color: #e6edf3;
            }

            .producto-card .producto-subtotal {
                text-align: right;
                font-size: 1.1rem;
                font-weight: 700;
                color: #238636;
                border-top: 1px solid #3d444d;
                padding-top: 8px;
                margin-top: 8px;
            }
        }

        /* Mejoras adicionales para accesibilidad móvil */
        .info-card .status {
            display: inline-block;
            margin-top: 5px;
        }

        /* Smooth scrolling para navegación */
        html {
            scroll-behavior: smooth;
        }

        /* Mejoras de contraste para texto */
        .info-card p {
            color: #e6edf3;
            line-height: 1.4;
        }

        /* Botones más accesibles en móvil */
        @media (max-width: 768px) {
            .btn, .btn-print {
                min-width: 120px;
                position: relative;
                overflow: hidden;
            }

            .btn::before, .btn-print::before {
                content: '';
                position: absolute;
                top: 50%;
                left: 50%;
                width: 0;
                height: 0;
                background: rgba(255, 255, 255, 0.2);
                border-radius: 50%;
                transform: translate(-50%, -50%);
                transition: width 0.3s, height 0.3s;
            }

            .btn:active::before, .btn-print:active::before {
                width: 300px;
                height: 300px;
            }

            /* Mejorar la tabla en móvil */
            .table-container {
                position: relative;
            }

            .table-container::after {
                content: '👉 Desliza para ver más';
                position: absolute;
                bottom: -25px;
                right: 0;
                font-size: 0.7rem;
                color: #8b949e;
                font-style: italic;
            }
        }

        /* Mejoras para experiencia táctil */
        @media (max-width: 768px) {
            /* Prevenir zoom accidental en inputs */
            input, select, textarea {
                font-size: 16px !important;
            }

            /* Mejorar área de toque para elementos interactivos */
            .info-card:hover {
                transform: none; /* Remover hover effects en móvil */
            }

            .producto-card:hover {
                transform: none;
            }

            /* Evitar overflow horizontal */
            body, html {
                overflow-x: hidden;
            }

            /* Mejorar contraste para lectores */
            .info-card h3 {
                color: #58a6ff; /* Azul más claro para mejor contraste */
            }

            /* Ajustar espaciado para dedos */
            .info-grid {
                gap: 16px; /* Espaciado ligeramente mayor */
            }

            /* Optimizar el scroll de la tabla */
            .table-container {
                scrollbar-width: thin;
                scrollbar-color: #1f6feb #21262d;
            }
        }

        /* Focus states mejorados para navegación por teclado */
        .btn:focus, .btn-print:focus {
            outline: 2px solid #58a6ff;
            outline-offset: 2px;
        }

        .info-card:focus-within {
            border-color: #58a6ff;
            box-shadow: 0 0 0 2px rgba(88, 166, 255, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="logo.png" alt="Sequoia Speed" class="logo" onerror="this.style.display='none'">
            <div class="header-content">
                <h1>Sequoia Speed</h1>
                <p class="subtitle">Detalles del Pedido</p>
            </div>
        </div>

        <div class="content">
            <?php if (!$pedido_encontrado): ?>
                <?php if (empty($id)): ?>
                    <div class="form-section">
                        <h2>Consultar Pedido</h2>
                        <p style="margin-bottom: 20px; opacity: 0.8;">Ingresa el número de pedido para ver los detalles:</p>

                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="pedido_id">Número de Pedido:</label>
                                <input type="number" id="pedido_id" name="pedido_id" min="1" placeholder="Ej: 117" required>
                            </div>
                            <button type="submit" class="btn">Ver Pedido</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="error">
                        <strong>❌ Error:</strong> <?php echo h($error_message); ?>
                    </div>

                    <div class="form-section">
                        <h2>Intentar con otro pedido</h2>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="pedido_id">Número de Pedido:</label>
                                <input type="number" id="pedido_id" name="pedido_id" min="1" placeholder="Ej: 117" required>
                            </div>
                            <button type="submit" class="btn">Ver Pedido</button>
                        </form>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="info-grid">
                    <div class="info-card">
                        <h3>Número de Pedido</h3>
                        <p><strong>#<?php echo h($p['id']); ?></strong></p>
                    </div>

                    <div class="info-card">
                        <h3>Cliente</h3>
                        <p><?php echo h(getField($p, 'nombre_cliente', 'Sin nombre')); ?></p>
                        <p style="font-size: 0.9rem; opacity: 0.8;"><?php echo h(getField($p, 'email_cliente', 'Sin email')); ?></p>
                    </div>

                    <div class="info-card">
                        <h3>Teléfono</h3>
                        <p><?php echo h(getField($p, 'telefono_cliente', 'Sin teléfono')); ?></p>
                    </div>

                    <div class="info-card">
                        <h3>Estado</h3>
                        <p><span class="status <?php echo strtolower(h(getField($p, 'estado', 'pendiente'))); ?>"><?php echo h(getField($p, 'estado', 'Pendiente')); ?></span></p>
                    </div>

                    <div class="info-card">
                        <h3>Fecha</h3>
                        <p><?php
                            $fecha = getField($p, 'fecha_pedido', '');
                            if ($fecha && $fecha != 'No disponible') {
                                echo date('d/m/Y H:i', strtotime($fecha));
                            } else {
                                echo 'Fecha no disponible';
                            }
                        ?></p>
                    </div>

                    <div class="info-card">
                        <h3>Método de Pago</h3>
                        <p><?php echo h(getField($p, 'metodo_pago', 'No especificado')); ?></p>
                    </div>
                </div>

                <?php if (!empty(getField($p, 'direccion_entrega', ''))): ?>
                <div class="info-card" style="margin-bottom: 30px;">
                    <h3>🚚 Dirección de Entrega</h3>
                    <p><?php echo h(getField($p, 'direccion_entrega')); ?></p>
                </div>
                <?php endif; ?>

                <?php if (!empty($productos)): ?>
                <div class="productos-section">
                    <h2 class="section-title">Productos Solicitados</h2>

                    <!-- Vista de tabla para pantallas normales -->
                    <div class="table-container">
                        <table class="productos-table">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Talla</th>
                                    <th>Cantidad</th>
                                    <th>Precio Unit.</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productos as $producto): ?>
                                <tr>
                                    <td><strong><?php echo h($producto['nombre']); ?></strong></td>
                                    <td><?php echo h($producto['talla'] ?? 'N/A'); ?></td>
                                    <td><?php echo intval($producto['cantidad']); ?></td>
                                    <td class="precio">$<?php echo number_format(floatval($producto['precio']), 0, ',', '.'); ?></td>
                                    <td class="precio"><strong>$<?php echo number_format(floatval($producto['precio']) * intval($producto['cantidad']), 0, ',', '.'); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Vista de cards para móviles muy pequeños -->
                    <div class="productos-mobile" style="display: none;">
                        <?php foreach ($productos as $producto): ?>
                        <div class="producto-card">
                            <div class="producto-nombre"><?php echo h($producto['nombre']); ?></div>
                            <div class="producto-info">
                                <div><span>Talla:</span> <strong><?php echo h($producto['talla'] ?? 'N/A'); ?></strong></div>
                                <div><span>Cantidad:</span> <strong><?php echo intval($producto['cantidad']); ?></strong></div>
                                <div><span>Precio Unit.:</span> <strong>$<?php echo number_format(floatval($producto['precio']), 0, ',', '.'); ?></strong></div>
                                <div><span>Subtotal:</span> <strong class="precio">$<?php echo number_format(floatval($producto['precio']) * intval($producto['cantidad']), 0, ',', '.'); ?></strong></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="total-section">
                        <div class="total-card">
                            <h3>Total del Pedido</h3>
                            <div class="amount">$<?php echo number_format($total_productos, 0, ',', '.'); ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty(getField($p, 'notas', ''))): ?>
                <div class="info-card" style="margin-top: 30px;">
                    <h3>📝 Notas Adicionales</h3>
                    <p><?php echo nl2br(h(getField($p, 'notas'))); ?></p>
                </div>
                <?php endif; ?>

                <div class="print-section" style="margin-top: 40px;">
                    <div class="print-card">
                        <button onclick="imprimirPedido()" class="btn-print">
                            <span class="print-icon">🖨️</span>
                            Imprimir Pedido
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function imprimirPedido() {
            // Configurar la impresión
            const originalTitle = document.title;
            document.title = 'Pedido #<?php echo h($p['id'] ?? ''); ?> - Sequoia Speed';

            // Imprimir
            window.print();

            // Restaurar título original
            setTimeout(() => {
                document.title = originalTitle;
            }, 1000);
        }

        // Atajos de teclado para impresión
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                imprimirPedido();
            }
        });
    </script>
</body>
</html>
