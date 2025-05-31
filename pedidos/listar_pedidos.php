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
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="stylesheet" href="styles.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
    /* Estilo Apple oscuro como VSCode con botones azules */
    body {
      font-family: var(--font-system);
      background: var(--vscode-bg);
      color: var(--vscode-text);
    }
    
    .sticky-bar {
      position: sticky;
      top: 0;
      z-index: 100;
      background: var(--vscode-sidebar);
      border-bottom: 1px solid var(--vscode-border);
      padding: var(--space-lg) 0 var(--space-md) 0;
      text-align: center;
      box-shadow: var(--shadow-light);
    }
    
    h1 {
      font-size: 1.5rem;
      font-weight: 600;
      margin-bottom: var(--space-md);
      color: var(--vscode-text-light);
    }
    
    .panel-container {
      max-width: 1200px;
      margin: var(--space-xl) auto;
      padding: 0 var(--space-lg);
    }
    
    .tabla-pedidos {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      background: var(--vscode-sidebar);
      border-radius: var(--radius-md);
      overflow: hidden;
      box-shadow: var(--shadow-light);
      margin-bottom: var(--space-xl);
    }
    
    .tabla-pedidos th {
      text-align: left;
      padding: var(--space-md);
      background: var(--gray-dark);
      color: var(--vscode-text-light);
      font-weight: 600;
      font-size: 0.9rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .tabla-pedidos td {
      padding: var(--space-md);
      border-top: 1px solid var(--vscode-border);
      font-size: 0.95rem;
    }
    
    .btn-neon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: var(--apple-blue);
      color: white;
      border: none;
      padding: 8px var(--space-lg);
      border-radius: var(--radius-md);
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
      text-decoration: none;
      font-size: 0.9rem;
    }
    
    .btn-neon:hover {
      background: var(--apple-blue-hover);
      transform: translateY(-1px);
      box-shadow: var(--shadow-medium);
    }
    
    .btn-glass {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: var(--gray-light);
      color: var(--vscode-text);
      border: 1px solid var(--vscode-border);
      padding: 8px var(--space-lg);
      border-radius: var(--radius-md);
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
      margin-left: var(--space-sm);
      font-size: 0.9rem;
    }
    
    .btn-glass:hover {
      background: var(--gray-medium);
    }
    
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
    .paginacion {
      display: flex;
      justify-content: center;
      gap: var(--space-sm);
      margin-top: var(--space-lg);
    }
    
    .paginacion a {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 36px;
      height: 36px;
      border-radius: var(--radius-md);
      background: var(--gray-light);
      color: var(--vscode-text);
      text-decoration: none;
      font-weight: 600;
      transition: all 0.2s;
    }
    
    .paginacion a:hover {
      background: var(--gray-medium);
    }
    
    .paginacion a.active {
      background: var(--apple-blue);
      color: white;
    }
    
    .estado-pill {
      display: inline-block;
      padding: 4px var(--space-md);
      border-radius: 1rem;
      font-size: 0.85rem;
      font-weight: 600;
      text-transform: capitalize;
      background: var(--gray-light);
    }
    
    .estado-pill.sin_enviar {
      background: var(--gray-medium);
    }
    
    .estado-pill.enviado {
      background: var(--apple-blue);
      color: white;
    }
    
    .estado-pill.anulado, .estado-pill.archivado {
      background: var(--gray-light);
      color: var(--vscode-text-muted);
    }
    
    .modal-detalle-bg {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0,0,0,0.75);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1000;
      backdrop-filter: blur(5px);
    }
    
    .modal-detalle {
      background: var(--vscode-sidebar);
      border-radius: var(--radius-md);
      padding: var(--space-xl);
      width: 90%;
      max-width: 650px;
      max-height: 90vh;
      overflow-y: auto;
      box-shadow: var(--shadow-heavy);
      position: relative;
    }
    
    .cerrar-modal {
      position: absolute;
      top: var(--space-md);
      right: var(--space-md);
      background: transparent;
      border: none;
      color: var(--vscode-text);
      font-size: 1.5rem;
      cursor: pointer;
    }
    
    .search-inp {
      padding: 8px var(--space-md);
      border: 1px solid var(--vscode-border);
      background: var(--vscode-bg);
      color: var(--vscode-text);
      border-radius: var(--radius-sm);
      font-family: var(--font-system);
      font-size: 0.95rem;
    }
    
    select {
      padding: 8px var(--space-md);
      border: 1px solid var(--vscode-border);
      background: var(--vscode-bg);
      color: var(--vscode-text);
      border-radius: var(--radius-sm);
      font-family: var(--font-system);
      font-size: 0.95rem;
      min-width: 120px;
    }
    
    /* Estilo responsive para móviles */
    @media (max-width: 768px) {
      .tabla-pedidos th {
        display: none;
      }
      
      .tabla-pedidos td {
        display: flex;
        padding: var(--space-sm) var(--space-md);
        text-align: right;
        position: relative;
      }
      
      .tabla-pedidos td::before {
        content: attr(data-label);
        position: absolute;
        left: var(--space-md);
        font-weight: 600;
        text-align: left;
      }
      
      .tabla-pedidos tr {
        margin-bottom: var(--space-md);
        display: block;
        border-bottom: 1px solid var(--vscode-border);
      }
      
      .filtros {
        flex-direction: column;
        align-items: center;
      }
    }
    </style>
</head>
<body>
<div class="sticky-bar">
    <h1>Gestión de Pedidos</h1>
    <div class="filtros">
        <form method="get" id="formFiltros" style="display:flex;gap:var(--space-md);flex-wrap:wrap;justify-content:center;">
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
        <a href="exportar_excel.php?filtro=<?php echo urlencode($filtro);?>&buscar=<?php echo urlencode($buscar);?>" class="btn-neon" style="margin-top:var(--space-md);">Exportar Excel</a>
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
                    <img src="https://cdn-icons-png.flaticon.com/512/1419/1419525.png" alt="WhatsApp" width="22" height="22" style="vertical-align:middle;" />
                  </a>
                </td>
                <td data-label="Monto"><b>$<?php echo number_format($p['monto'],0,',','.');?></b></td>
                <td data-label="Estado"><?php echo estado_pill($p['estado']);?></td>
                <td data-label="Acciones">
                    <?php if($p['estado']=='sin_enviar'): ?>
                        <button class="btn-neon" onclick="abrirModalGuia(<?php echo $p['id'];?>,'<?php echo htmlspecialchars($p['correo']);?>')">Marcar Enviado</button>
                        <button class="btn-glass" onclick="cambiarEstado(<?php echo $p['id'];?>,'anulado');return false;">Anular</button>
                    <?php elseif($p['estado']=='enviado'): ?>
                        <?php if($p['guia']): ?>
                          <a href="guias/<?php echo $p['guia'];?>" class="btn-neon" style="margin-left:8px;padding:7px 13px;" target="_blank" title="Seguimiento de Envío" aria-label="Seguimiento de Envío">
                            <img src="https://cdn-icons-png.flaticon.com/512/664/664468.png" alt="Seguimiento de Envío" width="33" height="27" style="vertical-align:middle;filter:grayscale(1) brightness(1.2);" />
                          </a>
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