<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'conexion.php';

// Filtros
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'hoy';
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limite = 20;
$offset = ($page - 1) * $limite;

// Filtro avanzado que excluye archivados en todos menos "archivados"
switch($filtro) {
    case 'hoy':
        $where = "DATE(fecha) = CURDATE() AND estado!='archivado'";
        break;
    case 'semana':
        $where = "YEARWEEK(fecha,1) = YEARWEEK(CURDATE(),1) AND estado!='archivado'";
        break;
    case 'quincena':
        $where = "fecha >= CURDATE() - INTERVAL 15 DAY AND estado!='archivado'";
        break;
    case 'mes':
        $where = "MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) AND estado!='archivado'";
        break;
    case 'archivados':
        $where = "estado='archivado'";
        break;
    default:
        $where = "estado!='archivado'";
}
if($buscar){
    $buscarSql = $conn->real_escape_string($buscar);
    $where .= " AND (nombre LIKE '%$buscarSql%' OR telefono LIKE '%$buscarSql%' OR id = '$buscarSql' OR correo LIKE '%$buscarSql%')";
}
$result = $conn->query("SELECT SQL_CALC_FOUND_ROWS id, nombre, telefono, correo, monto, estado, fecha, persona_recibe, direccion, horarios, metodo_pago, datos_pago, comprobante, guia, nota_interna FROM pedidos_detal WHERE $where ORDER BY fecha DESC LIMIT $limite OFFSET $offset");
$pedidos = [];
while ($row = $result->fetch_assoc()) {
    $pedidos[] = $row;
}
$total_result = $conn->query("SELECT FOUND_ROWS() as total");
$total_pedidos = $total_result->fetch_assoc()['total'];
$total_paginas = ceil($total_pedidos / $limite);

function estado_pill($estado) {
    $txt = ucfirst(str_replace('_',' ',$estado));
    if($estado=='archivado') $txt = 'Archivado';
    return '<span class="estado-pill '.$estado.'">'.$txt.'</span>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Pedidos</title>
    <link rel="stylesheet" href="apple-ui.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
    .whatsapp-icon {
      width: 17px;
      height: 17px;
      margin-left: 6px;
      vertical-align: middle;
      position: relative;
      top: 2px;
      transition: filter 0.12s;
      filter: grayscale(0.18) brightness(1.02);
    }
    .tabla-pedidos a.whatsapp-link:hover .whatsapp-icon {
      filter: drop-shadow(0 0 2px #25D366) brightness(1.2);
    }
    </style>
</head>
<body>
<div class="sticky-bar">
    <h1>Gestión de Pedidos</h1>
    <div class="filtros">
        <form method="get" id="formFiltros" style="display:flex;gap:14px;flex-wrap:wrap;">
            <select name="filtro" onchange="document.getElementById('formFiltros').submit()">
                <option value="hoy" <?php if($filtro=='hoy') echo "selected";?>>Hoy</option>
                <option value="semana" <?php if($filtro=='semana') echo "selected";?>>Semana</option>
                <option value="quincena" <?php if($filtro=='quincena') echo "selected";?>>Últimos 15 días</option>
                <option value="mes" <?php if($filtro=='mes') echo "selected";?>>Mes</option>
                <option value="archivados" <?php if($filtro=='archivados') echo "selected";?>>Archivados</option>
                <option value="todos" <?php if($filtro=='todos') echo "selected";?>>Todos</option>
            </select>
            <input class="search-inp" type="text" name="buscar" value="<?php echo htmlspecialchars($buscar);?>" placeholder="Buscar nombre, teléfono, correo o #ID" oninput="if(this.value.length==0) this.form.submit();" />
            <button type="submit" style="display:none;">Buscar</button>
        </form>
        <a href="exportar_excel.php?filtro=<?php echo urlencode($filtro);?>&buscar=<?php echo urlencode($buscar);?>" class="btn-neon" style="margin-left:14px;">Exportar Excel</a>
    </div>
</div>
<div class="panel-container">
    <table class="tabla-pedidos">
        <thead>
            <tr>
                <th>Nro Pedido</th>
                <th>Nombre</th>
                <th>Teléfono</th>
                <th>Monto</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php if(count($pedidos)==0): ?>
            <tr><td colspan="6" style="text-align:center; color:#888;">No hay pedidos para este filtro.</td></tr>
        <?php else: foreach($pedidos as $p): ?>
            <tr data-id="<?php echo $p['id'];?>">
                <td data-label="Nro Pedido">
                    <a href="#" class="ver-detalle" data-id="<?php echo $p['id'];?>" style="text-decoration:underline;color:#2997ff;font-weight:600;"><?php echo "#".$p['id'];?></a>
                </td>
                <td data-label="Nombre"><?php echo htmlspecialchars($p['nombre']);?></td>
                <td data-label="Teléfono">
                  <?php echo htmlspecialchars($p['telefono']);?>
                  <a href="https://wa.me/57<?php echo preg_replace('/\D/','',$p['telefono']);?>?text=Hola%20<?php echo urlencode($p['nombre']);?>,%20tu%20pedido%20Nro%20<?php echo $p['id'];?>" target="_blank" title="WhatsApp" class="whatsapp-link">
                    <svg class="whatsapp-icon" viewBox="0 0 32 32" fill="none">
                      <path d="M16 2.67C8.55 2.67 2.17 9.05 2.17 16.5c0 2.93.91 5.76 2.62 8.13L2 30l5.56-2.55c2.28 1.25 4.87 1.93 7.61 1.93 7.45 0 13.83-6.38 13.83-13.83S23.45 2.67 16 2.67zm0 24.94c-2.48 0-4.92-.7-7.01-2.02l-.5-.31-3.3 1.51.7-3.46-.21-.34C4.3 18.6 3.67 17.59 3.67 16.5c0-6.8 5.53-12.33 12.33-12.33s12.33 5.53 12.33 12.33-5.53 12.33-12.33 12.33zm7.41-8.07c-.1-.16-.38-.26-.8-.45-.44-.2-2.6-1.28-3.01-1.43-.4-.15-.7-.23-1 .23-.3.47-1.15 1.44-1.41 1.74-.25.29-.5.32-.93.13-.44-.18-1.86-.69-3.53-2.2-1.3-1.17-2.18-2.62-2.43-3.05-.26-.44-.03-.69.17-.92.17-.18.39-.48.59-.72.2-.23.25-.38.37-.62.13-.23.07-.46-.03-.64-.1-.18-.86-2.1-1.18-2.87-.32-.77-.63-.67-.86-.68-.23-.01-.49-.01-.76-.01-.26 0-.68.1-1.03.45-.36.36-1.37 1.33-1.37 3.23s1.4 3.75 1.59 4.01c.19.26 2.74 4.36 6.65 5.96.93.37 1.66.59 2.22.75.93.25 1.78.22 2.45.13.75-.1 2.3-.94 2.62-1.84.33-.91.33-1.69.23-1.84z" fill="#25D366"/>
                    </svg>
                  </a>
                </td>
                <td data-label="Monto"><b>$<?php echo number_format($p['monto'],0,',','.');?></b></td>
                <td data-label="Estado"><?php echo estado_pill($p['estado']);?></td>
                <td data-label="Acciones">
                    <?php if($p['estado']=='sin_enviar'): ?>
                        <button class="btn-neon" onclick="abrirModalGuia(<?php echo $p['id'];?>,'<?php echo htmlspecialchars($p['correo']);?>')">Marcar Enviado</button>
                        <button class="btn-glass" onclick="cambiarEstado(<?php echo $p['id'];?>,'anulado');return false;">Anular</button>
                    <?php elseif($p['estado']=='enviado'): ?>
                        <span class="estado-pill enviado">Enviado</span>
                        <?php if($p['guia']): ?>
                          <a href="guias/<?php echo $p['guia'];?>" class="btn-neon" style="margin-left:8px;" target="_blank">Ver Guía</a>
                        <?php endif; ?>
                    <?php elseif($p['estado']=='anulado'): ?>
                        <button class="btn-neon" onclick="restaurarPedido(<?php echo $p['id'];?>);return false;">Restaurar</button>
                        <button class="btn-glass" onclick="archivarPedido(<?php echo $p['id'];?>);return false;">Archivar</button>
                    <?php elseif($p['estado']=='archivado'): ?>
                        <span class="estado-pill anulado">Archivado</span>
                    <?php endif;?>
                </td>
            </tr>
        <?php endforeach; endif;?>
        </tbody>
    </table>
    <div class="paginacion">
        <?php for($i=1;$i<=$total_paginas;$i++): ?>
            <a class="<?php if($i==$page) echo 'active';?>" href="?filtro=<?php echo $filtro;?>&buscar=<?php echo urlencode($buscar);?>&page=<?php echo $i;?>"><?php echo $i;?></a>
        <?php endfor;?>
    </div>
</div>

<!-- MODAL DETALLE -->
<div id="modal-detalle" class="modal-detalle-bg" style="display:none;">
  <div class="modal-detalle">
    <button class="cerrar-modal" onclick="cerrarModalDetalle()">×</button>
    <div id="modal-contenido"></div>
  </div>
</div>

<!-- MODAL SUBIR GUÍA -->
<div id="modal-guia-bg" class="modal-detalle-bg" style="display:none;">
  <div class="modal-detalle" style="max-width:370px;text-align:center;">
    <button class="cerrar-modal" onclick="cerrarModalGuia()">×</button>
    <form id="formGuia" enctype="multipart/form-data" method="POST" autocomplete="off">
      <input type="hidden" name="id_pedido" id="guia_id_pedido">
      <div style="font-size:1.07rem;font-weight:600;margin-bottom:10px;">
        Adjuntar foto de la guía de envío
      </div>
      <input type="file" name="guia" id="guia_file" accept="image/*,application/pdf" required style="margin-bottom:13px;">
      <button type="submit" class="btn-neon" style="width:100%;">Enviar guía y notificar cliente</button>
    </form>
    <div id="guia_status" style="margin-top:12px;font-size:1rem;color:#e02b2b;"></div>
  </div>
</div>

<script src="pedidos.js"></script>
</body>
</html>