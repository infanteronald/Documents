<?php
require_once "conexion.php";

// Activar manejo de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

$orden_id = isset($_GET['orden']) ? intval($_GET['orden']) : 0;
$accion = isset($_GET['accion']) ? $_GET['accion'] : 'descargar';

if (!$orden_id) {
    die("ID de orden no válido");
}

// Obtener datos igual que antes...
// [código de obtención de datos igual al anterior]

// Generar HTML para PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .seccion { margin-bottom: 15px; }
        .seccion-titulo { font-weight: bold; border-bottom: 1px solid #000; margin-bottom: 5px; }
        .total { text-align: right; font-weight: bold; border: 2px solid #000; padding: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #000; padding: 5px; text-align: left; }
        th { background-color: #f0f0f0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>SEQUOIA SPEED</h1>
        <h2>COMPROBANTE DE PEDIDO</h2>
        <p>Orden #' . str_pad($orden_id, 6, '0', STR_PAD_LEFT) . '</p>
        <p>Fecha: ' . date('d/m/Y H:i', strtotime($orden['fecha'])) . '</p>
    </div>
    
    <div class="seccion">
        <div class="seccion-titulo">DATOS DEL CLIENTE</div>
        <p><strong>Nombre:</strong> ' . htmlspecialchars($orden['nombre']) . '</p>
        <p><strong>Teléfono:</strong> ' . htmlspecialchars($orden['telefono']) . '</p>
        <p><strong>Email:</strong> ' . htmlspecialchars($orden['correo']) . '</p>
    </div>
    
    <div class="seccion">
        <div class="seccion-titulo">DIRECCIÓN DE ENVÍO</div>
        <p>' . htmlspecialchars($orden['direccion']) . '</p>
        <p><strong>Recibe:</strong> ' . htmlspecialchars($orden['persona_recibe']) . '</p>
        <p><strong>Horario:</strong> ' . htmlspecialchars($orden['horarios']) . '</p>
    </div>';

if (!empty($detalles)) {
    $html .= '
    <div class="seccion">
        <div class="seccion-titulo">DETALLE DEL PEDIDO</div>
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Precio</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($detalles as $item) {
        $subtotal = $item['precio'] * $item['cantidad'];
        $html .= '
                <tr>
                    <td>' . htmlspecialchars($item['nombre']) . '</td>
                    <td>' . $item['cantidad'] . '</td>
                    <td>$' . number_format($item['precio'], 0, ',', '.') . '</td>
                    <td>$' . number_format($subtotal, 0, ',', '.') . '</td>
                </tr>';
    }
    
    $html .= '
            </tbody>
        </table>
    </div>';
}

$html .= '
    <div class="seccion">
        <div class="seccion-titulo">PAGO</div>
        <p><strong>Método:</strong> ' . htmlspecialchars($orden['metodo_pago']) . '</p>
        <div class="total">TOTAL: $' . number_format($orden['monto'], 0, ',', '.') . '</div>
    </div>
    
    <div style="text-align: center; margin-top: 30px; border-top: 1px solid #000; padding-top: 10px;">
        <p>¡Gracias por su compra!</p>
        <p>WhatsApp: 3142162979</p>
    </div>
</body>
</html>';

if ($accion === 'enviar') {
    // Enviar por email (HTML)
    $to = $orden['correo'];
    $subject = "Comprobante de pedido #" . str_pad($orden_id, 6, '0', STR_PAD_LEFT);
    
    $headers = "From: noreply@sequoiaspeed.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    if (mail($to, $subject, $html, $headers)) {
        echo "<script>alert('Comprobante enviado correctamente a: " . $orden['correo'] . "'); window.close();</script>";
    } else {
        echo "<script>alert('Error al enviar el comprobante'); window.close();</script>";
    }
    
} else {
    // Mostrar HTML para imprimir/guardar como PDF desde el navegador
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
    echo '<script>window.print();</script>';
}
?>