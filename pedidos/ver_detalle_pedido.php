<?php
include 'conexion.php';
$id = intval($_GET['id']);
$r = $conn->query("SELECT * FROM pedidos_detal WHERE id=$id LIMIT 1");
if($r->num_rows==0){ echo "<b>Pedido no encontrado.</b>"; exit; }
$p = $r->fetch_assoc();
function h($txt){return htmlspecialchars($txt);}
?>
<div>
  <div class="detalle-label">Nro Pedido:</div>
  <div class="detalle-valor">#<?php echo $p['id'];?></div>
  <div class="detalle-label">Nombre:</div>
  <div class="detalle-valor"><?php echo h($p['nombre']);?></div>
  <div class="detalle-label">Teléfono:</div>
  <div class="detalle-valor"><?php echo h($p['telefono']);?>
    <a href="https://wa.me/57<?php echo preg_replace('/\D/','',$p['telefono']);?>?text=Hola%20<?php echo urlencode($p['nombre']);?>,%20tu%20pedido%20Nro%20<?php echo $p['id'];?>" target="_blank" style="margin-left:8px;text-decoration:none;font-weight:bold;">💬</a>
  </div>
  <div class="detalle-label">Correo:</div>
  <div class="detalle-valor"><?php echo h($p['correo']);?></div>
  <div class="detalle-label">Monto:</div>
  <div class="detalle-valor">$<?php echo number_format($p['monto'],0,',','.');?></div>
  <div class="detalle-label">Persona que recibe:</div>
  <div class="detalle-valor"><?php echo h($p['persona_recibe']);?></div>
  <div class="detalle-label">Dirección:</div>
  <div class="detalle-valor">
    <?php echo h($p['direccion']);?>
    <a href="#" onclick="verMapa('<?php echo addslashes($p['direccion']);?>');return false;" class="detalle-link" title="Ver en mapa">🗺️ Mapa</a>
  </div>
  <div class="detalle-label">Horarios de entrega:</div>
  <div class="detalle-valor"><?php echo h($p['horarios']);?></div>
  <div class="detalle-label">Método de pago:</div>
  <div class="detalle-valor"><?php echo h($p['metodo_pago'])." | ".h($p['datos_pago']);?></div>
  <div class="detalle-label">Estado:</div>
  <div class="detalle-valor"><?php echo ucfirst(str_replace('_',' ',$p['estado']));?></div>
  <div class="detalle-label">Fecha y hora:</div>
  <div class="detalle-valor"><?php echo $p['fecha'];?></div>
  <?php if($p['comprobante']): ?>
    <div class="detalle-label">Comprobante:</div>
    <div class="detalle-valor">
      <a href="#" onclick="verComprobante('<?php echo h($p['comprobante']);?>');return false;" class="detalle-link">Ver Comprobante</a>
      <a href="<?php echo h($p['comprobante']);?>" target="_blank" class="detalle-link" style="margin-left:13px;">Descargar</a>
    </div>
  <?php endif; ?>
  <div class="detalle-separador"></div>
  <div class="detalle-label">Nota interna:</div>
  <textarea id="nota-<?php echo $p['id'];?>" class="nota-area"><?php echo h($p['nota_interna']);?></textarea>
  <button class="nota-btn" id="btn-nota-<?php echo $p['id'];?>" onclick="guardarNota(<?php echo $p['id'];?>)">Guardar Nota</button>
</div>
<?php if(!empty($p['guia'])): ?>
  <div class="detalle-label">Guía de envío:</div>
  <div class="detalle-valor">
    <a href="guias/<?php echo htmlspecialchars($p['guia']); ?>" target="_blank" class="detalle-link">Ver Guía</a>
    <a href="guias/<?php echo htmlspecialchars($p['guia']); ?>" download class="detalle-link" style="margin-left:13px;">Descargar</a>
  </div>
<?php endif; ?>

<?php
// Mostrar detalles de productos si existen
$detalle_query = "SELECT nombre, precio, cantidad, talla FROM pedido_detalle WHERE pedido_id = ?";
$stmt_detalle = $conn->prepare($detalle_query);
$stmt_detalle->bind_param("i", $id);
$stmt_detalle->execute();
$result_detalle = $stmt_detalle->get_result();

if ($result_detalle->num_rows > 0):
?>
  <div class="detalle-separador"></div>
  <div class="detalle-label">Productos del pedido:</div>
  <div class="detalle-valor">
    <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
      <thead>
        <tr style="background: var(--vscode-sidebar);">
          <th style="border: 1px solid var(--vscode-border); padding: 8px; text-align: left;">Producto</th>
          <th style="border: 1px solid var(--vscode-border); padding: 8px; text-align: center; width: 60px;">Cant</th>
          <th style="border: 1px solid var(--vscode-border); padding: 8px; text-align: center; width: 60px;">Talla</th>
          <th style="border: 1px solid var(--vscode-border); padding: 8px; text-align: right; width: 80px;">Precio</th>
          <th style="border: 1px solid var(--vscode-border); padding: 8px; text-align: right; width: 80px;">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($item = $result_detalle->fetch_assoc()): ?>
        <tr>
          <td style="border: 1px solid var(--vscode-border); padding: 8px;"><?= htmlspecialchars($item['nombre']) ?></td>
          <td style="border: 1px solid var(--vscode-border); padding: 8px; text-align: center;"><?= $item['cantidad'] ?></td>
          <td style="border: 1px solid var(--vscode-border); padding: 8px; text-align: center;"><?= htmlspecialchars($item['talla'] ?? 'N/A') ?></td>
          <td style="border: 1px solid var(--vscode-border); padding: 8px; text-align: right;">$<?= number_format($item['precio'], 0, ',', '.') ?></td>
          <td style="border: 1px solid var(--vscode-border); padding: 8px; text-align: right;">$<?= number_format($item['precio'] * $item['cantidad'], 0, ',', '.') ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
<?php
endif;
$stmt_detalle->close();
?>
