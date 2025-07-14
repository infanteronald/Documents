<?php
require_once "conexion.php";

// Activar manejo de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

$orden_id = isset($_GET['orden']) ? intval($_GET['orden']) : 0;
$orden = null;
$detalles = [];

if ($orden_id) {
    // Obtener datos de la orden desde pedidos_detal
    $sql = "SELECT id, pedido, monto, descuento, nombre, direccion, telefono, ciudad, barrio, correo, metodo_pago, datos_pago, comprobante, nota_interna, fecha, estado, fecha_estado, guia, numero_guia, url_imagen_guia, comentario FROM pedidos_detal WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Error preparando consulta: " . $conn->error);
    }

    $stmt->bind_param("i", $orden_id);

    if (!$stmt->execute()) {
        die("Error ejecutando consulta: " . $stmt->error);
    }

    // Bind result variables - TODOS los campos en el orden correcto
    $stmt->bind_result($id, $pedido, $monto, $descuento, $nombre, $direccion, $telefono, $ciudad, $barrio, $correo, $metodo_pago, $datos_pago, $comprobante, $nota_interna, $fecha, $estado, $fecha_estado, $guia, $numero_guia, $url_imagen_guia, $comentario);

    if ($stmt->fetch()) {
        $orden = array(
            'id' => $id,
            'pedido' => $pedido,
            'monto' => $monto,
            'descuento' => $descuento,
            'nombre' => $nombre,
            'direccion' => $direccion,
            'telefono' => $telefono,
            'ciudad' => $ciudad,
            'barrio' => $barrio,
            'correo' => $correo,
            'metodo_pago' => $metodo_pago,
            'datos_pago' => $datos_pago,
            'comprobante' => $comprobante,
            'nota_interna' => $nota_interna,
            'fecha' => $fecha,
            'estado' => $estado,
            'fecha_estado' => $fecha_estado,
            'guia' => $guia,
            'numero_guia' => $numero_guia,
            'url_imagen_guia' => $url_imagen_guia,
            'comentario' => $comentario
        );
    }

    $stmt->close();
      // Obtener detalles individuales si existen - USAR SOLO LAS COLUMNAS QUE NECESITAS
    $total_calculado = 0;
    $sql_det = "SELECT nombre, precio, cantidad FROM pedido_detalle WHERE pedido_id = ?";
    $stmt_det = $conn->prepare($sql_det);
    if ($stmt_det) {
        $stmt_det->bind_param("i", $orden_id);
        if ($stmt_det->execute()) {
            // Solo 3 variables para 3 columnas
            $stmt_det->bind_result($det_nombre, $det_precio, $det_cantidad);

            while ($stmt_det->fetch()) {
                $detalles[] = [
                    'nombre' => $det_nombre,
                    'precio' => $det_precio,
                    'cantidad' => $det_cantidad
                ];
                // Calcular el total sumando cada producto
                $total_calculado += ($det_precio * $det_cantidad);
            }
        } else {
            echo "<!-- DEBUG: Error en consulta detalles: " . $stmt_det->error . " -->";
        }
        $stmt_det->close();
    }

    // Si tenemos detalles individuales, usar el total calculado menos descuento, sino usar el monto de la tabla principal
    if ($total_calculado > 0) {
        // Usar el total calculado y aplicar descuento
        $monto_final = $total_calculado - ($orden['descuento'] ?? 0);
    } else {
        // Usar el monto de la tabla principal (que ya incluye descuento aplicado)
        $monto_final = $orden['monto'];
    }
}

if (!$orden) {
    echo "Orden no encontrada - ID: " . $orden_id;
    exit;
}

// Debug: Mostrar los datos obtenidos
echo "<!-- DEBUG ORDEN: ";
print_r($orden);
echo " -->";

echo "<!-- DEBUG DETALLES: ";
print_r($detalles);
echo " -->";

echo "<!-- DEBUG MONTOS: Total calculado: $total_calculado, Monto original: " . ($orden['monto'] ?? 0) . ", Monto final: $monto_final -->";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Comprobante de Pedido</title>
    <style>
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }

        body {
            font-family: 'Courier New', monospace;
            margin: 0;
            padding: 10px;
            background: white;
            color: black;
        }

        .comprobante {
            width: 80mm;
            max-width: 300px;
            margin: 0 auto;
            border: 2px solid black;
            padding: 10px;
            background: white;
        }

        .header {
            text-align: center;
            border-bottom: 1px solid black;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }

        .logo {
            width: 60px;
            height: auto;
            margin-bottom: 5px;
        }

        .empresa {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 2px;
        }

        .titulo {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .numero-orden {
            font-size: 10px;
            margin-bottom: 2px;
        }

        .fecha {
            font-size: 9px;
        }

        .seccion {
            margin-bottom: 8px;
            font-size: 9px;
        }

        .seccion-titulo {
            font-weight: bold;
            border-bottom: 1px solid black;
            margin-bottom: 3px;
            font-size: 10px;
        }

        .detalle-tabla {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
            margin-bottom: 5px;
        }

        .detalle-tabla th,
        .detalle-tabla td {
            border: none;
            padding: 1px 2px;
            text-align: left;
        }

        .detalle-tabla th {
            border-bottom: 1px solid black;
            font-weight: bold;
        }

        .cantidad {
            text-align: center !important;
            width: 15%;
        }

        .precio {
            text-align: right !important;
            width: 25%;
        }

        .total-final {
            border-top: 1px solid black;
            border-bottom: 2px solid black;
            padding: 3px 0;
            text-align: right;
            font-weight: bold;
            font-size: 10px;
            margin-top: 5px;
        }

        .footer {
            border-top: 1px solid black;
            padding-top: 5px;
            margin-top: 8px;
            text-align: center;
            font-size: 8px;
        }

        .botones {
            text-align: center;
            margin: 20px 0;
        }

        .btn {
            background: black;
            color: white;
            border: none;
            padding: 8px 16px;
            margin: 0 5px;
            cursor: pointer;
            font-size: 12px;
            border-radius: 4px;
        }

        .btn:hover {
            background: #333;
        }

        .btn-pdf {
            background: #007bff;
        }

        .btn-pdf:hover {
            background: #0056b3;
        }

        .btn-email {
            background: #28a745;
        }

        .btn-email:hover {
            background: #1e7e34;
        }
    </style>
</head>
<body>
    <div class="comprobante">
        <div class="header">
            <img src="logo.png" class="logo" alt="Logo">
            <div class="empresa">SEQUOIA SPEED</div>
            <div class="titulo">COMPROBANTE DE PEDIDO</div>
            <div class="numero-orden">Orden #<?= str_pad($orden_id, 6, '0', STR_PAD_LEFT) ?></div>
            <div class="fecha"><?= date('d/m/Y H:i', strtotime($orden['fecha'])) ?></div>
        </div>

        <div class="seccion">
            <div class="seccion-titulo">DATOS DEL CLIENTE</div>
            <div><strong>Nombre:</strong> <?= htmlspecialchars($orden['nombre'] ?? 'N/A') ?></div>
            <div><strong>Teléfono:</strong> <?= htmlspecialchars($orden['telefono'] ?? 'N/A') ?></div>
            <div><strong>Email:</strong> <?= htmlspecialchars($orden['correo'] ?? 'N/A') ?></div>
        </div>

        <div class="seccion">
            <div class="seccion-titulo">DIRECCIÓN DE ENVÍO</div>            <div><?= htmlspecialchars($orden['direccion'] ?? 'N/A') ?></div>
            <div><strong>Ciudad:</strong> <?= htmlspecialchars($orden['ciudad'] ?? 'N/A') ?></div>
            <div><strong>Barrio:</strong> <?= htmlspecialchars($orden['barrio'] ?? 'N/A') ?></div>
        </div>

        <?php if ($detalles): ?>
        <div class="seccion">
            <div class="seccion-titulo">DETALLE DEL PEDIDO</div>
            <table class="detalle-tabla">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th class="cantidad">Cant</th>
                        <th class="precio">Precio</th>
                        <th class="precio">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalles as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['nombre']) ?></td>
                        <td class="cantidad"><?= $item['cantidad'] ?></td>
                        <td class="precio">$<?= number_format($item['precio'], 0, ',', '.') ?></td>
                        <td class="precio">$<?= number_format($item['precio'] * $item['cantidad'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php elseif (!empty($orden['pedido'])): ?>
        <div class="seccion">
            <div class="seccion-titulo">PEDIDO</div>
            <div><?= nl2br(htmlspecialchars($orden['pedido'])) ?></div>
        </div>
        <?php endif; ?>
          <div class="seccion">
            <div class="seccion-titulo">PAGO</div>
            <div><strong>Método:</strong> <?= htmlspecialchars($orden['metodo_pago'] ?? 'N/A') ?></div>
            
            <?php if ($orden['descuento'] > 0): ?>
                <?php 
                // Calcular subtotal sin descuento
                $subtotal_sin_descuento = ($total_calculado > 0) ? $total_calculado : $orden['monto'] + $orden['descuento'];
                ?>
                <div style="margin-top: 8px; font-size: 9px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                        <span>Subtotal:</span>
                        <span>$<?= number_format($subtotal_sin_descuento, 0, ',', '.') ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px; color: #666;">
                        <span>Descuento:</span>
                        <span>-$<?= number_format($orden['descuento'], 0, ',', '.') ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="total-final">TOTAL: $<?= number_format($monto_final, 0, ',', '.') ?></div>
        </div>

        <?php if (!empty($orden['comentario'])): ?>
        <div class="seccion">
            <div class="seccion-titulo">COMENTARIOS</div>
            <div><?= nl2br(htmlspecialchars($orden['comentario'])) ?></div>
        </div>
        <?php endif; ?>

        <div class="footer">
            <div>¡Gracias por su compra!</div>
            <div>WhatsApp: 3142162979</div>
        </div>
    </div>

    <div class="botones no-print">
        <button class="btn" onclick="window.print()">Imprimir</button>
    </div>

    <script>
        function descargarPDF() {
            window.open('generar_pdf.php?orden=<?= $orden_id ?>&accion=descargar', '_blank');
        }

        function enviarPDF() {
            if (confirm('¿Enviar el comprobante PDF al email del cliente?')) {
                window.open('generar_pdf.php?orden=<?= $orden_id ?>&accion=enviar', '_blank');
            }
        }
    </script>
</body>
</html>
