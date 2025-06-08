<?php
require_once "conexion.php";
$pedido_id = isset($_GET['pedido']) ? intval($_GET['pedido']) : 0;
$detalles = [];
$monto = 0;

if ($pedido_id) {
    // Obtener detalles del pedido
    $res = $conn->query("SELECT * FROM pedido_detalle WHERE pedido_id = $pedido_id");
    while ($row = $res->fetch_assoc()) {
        $detalles[] = $row;
        $monto += $row['precio'] * $row['cantidad'];
    }
    // Depuración:
    echo '<pre>'; print_r($detalles); echo '</pre>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Orden de Pedido - Sequoia Speed</title>
  <link rel="stylesheet" href="apple-ui.css">
</head>
<body>
<div class="container">
  <div class="logo-bg">
    <img src="logo.png" class="logo" alt="Sequoia Speed">
  </div>
  <h1>Orden de Pedido1</h1>
  <form id="formPedido" method="POST" enctype="multipart/form-data" action="procesar_orden.php">
    <?php if ($pedido_id && $detalles): ?>
      <div style="margin-bottom:18px;">
        <h3 style="margin:0 0 10px 0;">Detalle del pedido</h3>
        <table>
          <thead>
            <tr>
              <th>Producto</th>
              <th>Cantidad</th>
              <th>Precio</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($detalles as $item): ?>
            <tr>
              <td><?= htmlspecialchars($item['nombre']) ?></td>
              <td style="text-align:center;"><?= $item['cantidad'] ?></td>
              <td style="text-align:right;">$<?= number_format($item['precio'], 0, ',', '.') ?></td>
              <td style="text-align:right;">$<?= number_format($item['precio'] * $item['cantidad'], 0, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div style="text-align:right;margin-top:8px;font-weight:700;">
          Total: $<?= number_format($monto, 0, ',', '.') ?>
        </div>
      </div>
      <input type="hidden" name="pedido_id" value="<?= $pedido_id ?>">
    <?php elseif ($pedido_id): ?>
      <div style="color:#ff4d4d;text-align:center;margin:20px 0;">
        No se encontraron detalles para este pedido.
      </div>
    <?php else: ?>
      <textarea name="pedido" rows="3" placeholder="Indique su pedido. Indicar producto, tallas y cantidades" required></textarea>
    <?php endif; ?>
    <input type="number" step="10" name="monto" placeholder="Monto a pagar" required>
    <input type="text" name="nombre" placeholder="Nombre completo" required>
    <input type="text" name="direccion" placeholder="Dirección de envío" required>
    <input type="tel" name="telefono" placeholder="Teléfono" pattern="^[0-9]{7,15}$" required>
    <input type="email" name="correo" placeholder="Correo electrónico" required>
    <input type="text" name="persona_recibe" placeholder="Persona que recibe" required>
    <input type="text" name="horarios" placeholder="Horario de recepción (ej: Lun a Vie 9am-6pm)" required>
    <select name="metodo_pago" id="metodo_pago" required>
      <option value="">Método de pago</option>
      <option value="Nequi">Nequi</option>
      <option value="Transfiya">Transfiya</option>
      <option value="Bancolombia">Bancolombia</option>
      <option value="Provincial">Provincial</option>
      <option value="PSE">PSE</option>
      <option value="Contra entrega">Contra entrega</option>
    </select>
    <div id="info_pago" class="info-pago"></div>
    <span class="label-archivo">Adjuntar comprobante de pago:</span>
    <input type="file" name="comprobante" accept="image/*,application/pdf">
    <button type="submit">Enviar pedido</button>
  </form>
  <a class="btn-whatsapp" href="https://wa.me/573142162979" target="_blank">
    <img src="https://upload.wikimedia.org/wikipedia/commons/6/6b/WhatsApp.svg" alt="WhatsApp" class="wa-icon"> Necesito Ayuda</a>
</div>
<script>
  document.getElementById('metodo_pago').addEventListener('change', function() {
    const value = this.value;
    let info = "";
    if(value === "Nequi" || value === "Transfiya") info = "<b>Nequi / Transfiya:</b> 3213260357";
    else if(value === "Bancolombia") info = "<b>Bancolombia:</b> Ahorros 03500000175 Ronald Infante";
    else if(value === "Provincial") info = "<b>Provincial:</b> Ahorros 0958004765 Ronald Infante";
    else if(value === "PSE") info = "<b>PSE:</b> Solicitar link de pago a su asesor";
    else if(value === "Contra entrega") info = "<b>Contra entrega:</b> No requiere pago anticipado";
    document.getElementById('info_pago').innerHTML = info;
  });
</script>
</body>
</html>