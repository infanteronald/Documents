<?php
require_once "conexion.php";
$pedido_id = isset($_GET['pedido']) ? intval($_GET['pedido']) : 0;
$detalles = [];
$monto = 0;
$descuento = 0;

// Procesamiento de pedido existente por ID
if ($pedido_id) {
  // Obtener el descuento desde pedidos_detal
  $stmt_desc = $conn->prepare("SELECT descuento FROM pedidos_detal WHERE id = ? LIMIT 1");
  $stmt_desc->bind_param("i", $pedido_id);
  $stmt_desc->execute();
  $stmt_desc->bind_result($descuento_db);
  if ($stmt_desc->fetch()) {
    $descuento = $descuento_db ?? 0;
  }
  $stmt_desc->close();
  
  // Obtener detalles del pedido
  $res = $conn->query("SELECT * FROM pedido_detalle WHERE pedido_id = $pedido_id");
  while ($row = $res->fetch_assoc()) {
    $detalles[] = $row;
    $monto += $row['precio'] * $row['cantidad'];
  }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Orden de Pedido - Sequoia Speed</title>

  <!-- CSS optimizados del sistema (combinados) -->
  <link rel="stylesheet" href="assets/combined/app.min.css">

  <!-- Scripts externos -->
  <script src="https://checkout.bold.co/library/boldPaymentButton.js"></script>

  <!-- JavaScript optimizados del sistema -->
  <script defer src="assets/combined/app.min.js"></script>
  <script defer src="assets/optimized/js/lazy-loader.min.js"></script>
  <style>
    /* Estilo Apple oscuro como VSCode con botones azules */
    :root {
      /* Colores VS Code Dark - Paleta Principal */
      --vscode-bg: #1e1e1e;
      --vscode-sidebar: #252526;
      --vscode-border: #3e3e42;
      --vscode-text: #cccccc;
      --vscode-text-muted: #999999;
      --vscode-text-light: #ffffff;

      /* Solo azul Apple como único color de acento */
      --apple-blue: #007aff;
      --apple-blue-hover: #0056d3;
      --apple-blue-active: #004494;

      /* Grises para estados neutros */
      --gray-light: rgba(204, 204, 204, 0.1);
      --gray-medium: rgba(204, 204, 204, 0.2);
      --gray-dark: rgba(204, 204, 204, 0.05);

      /* Espaciado */
      --space-xs: 4px;
      --space-sm: 8px;
      --space-md: 16px;
      --space-lg: 24px;
      --space-xl: 32px;

      /* Border radius */
      --radius-sm: 6px;
      --radius-md: 12px;
      --radius-lg: 16px;
    }

    /* Estilos generales */
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'SF Pro Display', 'Helvetica Neue', Arial, sans-serif;
      background: var(--vscode-bg);
      color: var(--vscode-text);
      margin: 0;
      padding: 0;
      min-height: 100vh;
    }

    .container {
      max-width: 600px;
      margin: 0 auto;
      padding: var(--space-lg);
      box-sizing: border-box;
    }

    /* Logo y título */
    .logo {
      display: block;
      height: 50px;
      width: auto;
      margin: 0 auto var(--space-sm);
      object-fit: contain;
    }

    h1 {
      text-align: center;
      font-size: 1.5rem;
      font-weight: 600;
      margin-bottom: var(--space-xl);
      color: var(--vscode-text-light);
    }

    /* Formulario */
    form {
      background: var(--vscode-sidebar);
      border-radius: var(--radius-md);
      padding: var(--space-lg);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
      border: 1px solid var(--vscode-border);
    }

    /* Campos del formulario */
    textarea,
    input[type="text"],
    input[type="number"],
    input[type="email"],
    input[type="tel"],
    input[type="file"],
    select {
      width: 100%;
      max-width: 100%;
      background: var(--vscode-bg);
      border: 1px solid var(--vscode-border);
      border-radius: var(--radius-sm);
      color: var(--vscode-text);
      margin-bottom: var(--space-md);
      font-size: 0.7rem !important;
      padding: 6px !important;
      font-family: inherit;
      box-sizing: border-box;
    }

    textarea:focus,
    input:focus,
    select:focus {
      outline: none;
      border-color: var(--apple-blue);
      box-shadow: 0 0 0 2px rgba(0, 122, 255, 0.2);
    }

    /* Botones */
    button {
      background: var(--apple-blue);
      color: white;
      border: none;
      padding: 8px 16px;
      border-radius: var(--radius-md);
      font-weight: 600;
      font-size: 0.7rem !important;
      cursor: pointer;
      transition: all 0.2s;
      width: 100%;
      margin-top: var(--space-sm);
    }

    button:hover {
      background: var(--apple-blue-hover);
      transform: translateY(-1px);
    }

    /* Tabla */
    .tabla-responsive {
      overflow-x: auto;
      margin-bottom: var(--space-md);
    }

    table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      background: var(--vscode-sidebar);
      border-radius: var(--radius-sm);
      overflow: hidden;
      font-size: 0.7rem;
    }

    th {
      text-align: left;
      padding: var(--space-sm);
      background: var(--gray-dark);
      color: var(--vscode-text-light);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    td {
      padding: var(--space-sm);
      border-top: 1px solid var(--vscode-border);
    }

    /* Whatsapp button */
    .btn-whatsapp {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: var(--space-sm);
      background: #25D366;
      color: white;
      text-decoration: none;
      padding: 8px var(--space-md);
      border-radius: var(--radius-md);
      margin-top: var(--space-lg);
      font-size: 0.7rem !important;
      font-weight: 600;
      transition: all 0.2s;
    }

    .btn-whatsapp:hover {
      background: #20bd5a;
      transform: translateY(-1px);
    }

    .wa-icon {
      width: 18px;
      height: 18px;
    }

    /* Info de pago */
    .info-pago {
      background: var(--gray-light);
      padding: var(--space-md);
      border-radius: var(--radius-sm);
      margin-bottom: var(--space-md);
      font-size: 0.68rem !important;
    }

    .label-archivo {
      display: block;
      margin-bottom: var(--space-xs);
      font-size: 0.7rem !important;
      color: var(--vscode-text-muted);
    }

    /* Error message */
    .error-message {
      color: #ff4d4d;
      text-align: center;
      margin: var(--space-md) 0;
    }

    /* Estilos para PSE Bold */
    .pse-bold-container {
      background: var(--gray-dark);
      border: 1px solid var(--apple-blue);
      border-radius: var(--radius-md);
      padding: var(--space-md);
      margin-top: var(--space-sm);
    }

    .pse-bold-container p {
      margin: 8px 0;
      font-size: 0.9rem;
    }

    #bold-payment-container {
      display: flex;
      justify-content: center;
      min-height: 60px;
      align-items: center;
      padding: var(--space-sm);
    }

    /* Estilos específicos para el botón Bold */
    #bold-payment-container [data-bold-button] {
      margin: 0 auto !important;
      display: block !important;
    }

    #bold-payment-container iframe {
      border: none !important;
      max-width: 100% !important;
      height: auto !important;
    }

    /* Loading state para Bold */
    .bold-loading {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: var(--space-md);
      color: var(--apple-blue);
      font-size: 0.9rem;
    }

    .bold-loading::before {
      content: '';
      width: 16px;
      height: 16px;
      border: 2px solid var(--apple-blue);
      border-top: 2px solid transparent;
      border-radius: 50%;
      margin-right: var(--space-sm);
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      0% {
        transform: rotate(0deg);
      }

      100% {
        transform: rotate(360deg);
      }
    }

    /* Media queries para dispositivos móviles */
    @media (max-width: 768px) {
      .container {
        max-width: 95%;
        padding: var(--space-md);
      }

      textarea,
      input,
      select,
      button {
        font-size: 16px !important;
        /* Evita zoom en iOS */
      }
    }

    @media (max-width: 480px) {
      .container {
        max-width: 98%;
        padding: var(--space-sm);
      }
    }
  </style>

  <!-- CSS y JS para UX mejorada -->
  <link rel="stylesheet" href="payment_ux_enhanced.css">
</head>

<body>
  <div class="container">
    <img src="logo.png" class="logo" alt="Sequoia Speed">
    <h1>Orden de Pedido Manual</h1>
    <form id="formPedido" method="POST" enctype="multipart/form-data" action="procesar_orden.php">
      <?php if ($pedido_id && $detalles): ?>
        <!-- Pedido guardado desde orden_pedido.php -->
        <div style="margin-bottom:8px; background:rgba(0, 122, 255, 0.1); padding:12px; border-radius:8px; border:1px solid var(--apple-blue);">
          <p style="margin:0 0 8px 0; font-weight:600; color:var(--apple-blue);">📦 Pedido #<?= $pedido_id ?> - Completa los datos de envío</p>
          <div class="tabla-responsive">
            <table style="margin:0 auto; font-size:0.7rem;">
              <thead>
                <tr>
                  <th>Producto</th>
                  <th style="width: 50px; text-align:center;">Cant</th>
                  <th>Precio</th>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($detalles as $item): ?>
                  <tr>
                    <td><?= htmlspecialchars($item['nombre']) ?> <?php if ($item['talla'] && $item['talla'] != 'N/A'): ?>(Talla: <?= htmlspecialchars($item['talla']) ?>)<?php endif; ?></td>
                    <td style="text-align:center;"><?= $item['cantidad'] ?></td>
                    <td style="text-align:right;">$<?= number_format($item['precio'], 0, ',', '.') ?></td>
                    <td style="text-align:right;">$<?= number_format($item['precio'] * $item['cantidad'], 0, ',', '.') ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if ($descuento > 0): ?>
                  <tr style="border-top: 2px solid var(--vscode-border);">
                    <td colspan="3" style="text-align:right; font-weight:600; color: var(--vscode-text-muted); padding-top:8px;">Subtotal:</td>
                    <td style="text-align:right; font-weight:600; padding-top:8px;">$<?= number_format($monto, 0, ',', '.') ?></td>
                  </tr>
                  <tr>
                    <td colspan="3" style="text-align:right; font-weight:600; color: #ff6b6b;">Descuento:</td>
                    <td style="text-align:right; font-weight:600; color: #ff6b6b;">-$<?= number_format($descuento, 0, ',', '.') ?></td>
                  </tr>
                  <tr style="border-top: 2px solid var(--apple-blue); background: var(--gray-dark);">
                    <td colspan="3" style="text-align:right; font-weight:700; color: var(--apple-blue); font-size:0.8rem;">TOTAL FINAL:</td>
                    <td style="text-align:right; font-weight:700; color: var(--apple-blue); font-size:0.8rem;">$<?= number_format($monto - $descuento, 0, ',', '.') ?></td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Campo oculto con el ID del pedido -->
        <input type="hidden" name="pedido_id" value="<?= $pedido_id ?>">
        
        <!-- Campo oculto con el descuento aplicado -->
        <input type="hidden" name="descuento" value="<?= $descuento ?>">

        <!-- Campo de monto sólo lectura -->
        <input
          type="text"
          name="monto"
          style="text-align:right;"
          value="$<?= number_format($monto - $descuento, 0, ',', '.') ?>"
          readonly
          required>
      <?php elseif ($pedido_id): ?>
        <div style="color:#ff4d4d;text-align:center;margin:20px 0;">
          No se encontraron detalles para este pedido.
        </div>
        <input type="hidden" name="pedido_id" value="<?= $pedido_id ?>">
        
        <!-- Campo oculto con el descuento aplicado -->
        <input type="hidden" name="descuento" value="<?= $descuento ?>">

        <!-- Campo de monto sólo lectura cuando pedido_id existe pero sin detalles -->
        <input
          type="text"
          name="monto"
          style="text-align:right;"
          value="$<?= number_format($monto - $descuento, 0, ',', '.') ?>"
          readonly
          required>
      <?php else: ?>
        <!-- Sin número de pedido, el usuario puede ingresar el monto manualmente -->
        <textarea name="pedido" rows="3" placeholder="Indique su pedido. Indicar producto, tallas y cantidades" required></textarea>
        <input
          type="number"
          name="monto"
          placeholder="Monto (ejemplo: 100000)"
          required>
      <?php endif; ?>

      <input type="text" name="nombre" placeholder="Nombre completo" required>
      <input type="text" name="direccion" placeholder="Dirección de envío" required>
      <input type="text" name="ciudad" placeholder="Ciudad" required>
      <input type="text" name="barrio" placeholder="Barrio" required>
      <input type="tel" name="telefono" placeholder="Teléfono" pattern="^[0-9]{7,15}$" required>
      <input type="email" name="correo" placeholder="Correo electrónico" required>
      <select name="metodo_pago" id="metodo_pago" required>
        <option value="">Método de pago</option>
        <option value="Nequi">Nequi</option>
        <option value="Transfiya">Transfiya</option>
        <option value="Bancolombia">Bancolombia</option>
        <option value="Provincial">Provincial</option>
        <option value="Addi">Addi</option>
        <option value="Sistecredito">Sistecredito</option>
        <option value="PSE Bold">PSE Bold</option>
        <option value="Botón Bancolombia">Botón Bancolombia</option>
        <option value="Tarjeta de Crédito o Débito">Tarjeta de Crédito o Débito</option>
        <option value="Datafono">Datafono</option>
        <option value="QR">QR</option>
        <option value="Efectivo_Bogota">Efectivo en Tienda Bogotá</option>
        <option value="Efectivo_Medellin">Efectivo en Tienda Medellín</option>
        <option value="Recaudo al Entregar">Recaudo al Entregar</option>
      </select>
      <div id="info_pago" class="info-pago"></div>
      <textarea name="comentario" rows="3" placeholder="Comentario adicional (opcional)"></textarea>
      <span class="label-archivo">Adjuntar comprobante de pago:</span>
      <input type="file" name="comprobante" accept="image/*,application/pdf">
      <button type="submit">Enviar pedido</button>
    </form>
    <a class="btn-whatsapp" href="https://wa.me/573142162979" target="_blank">
      <img src="https://upload.wikimedia.org/wikipedia/commons/6/6b/WhatsApp.svg" alt="WhatsApp" class="wa-icon"> Necesito Ayuda</a>
  </div>
  <script>
    document.getElementById('metodo_pago').addEventListener('change', function() {
      console.log('🔥 EVENT LISTENER: Método de pago cambiado a:', this.value);
      const value = this.value;
      let info = "";
      if (value === "Nequi" || value === "Transfiya") info = "<b>Nequi / Transfiya:</b> 3213260357";
      else if (value === "Bancolombia") info = "<b>Bancolombia:</b> Ahorros 03500000175 Ronald Infante";
      else if (value === "Provincial") info = "<b>Provincial:</b> Ahorros 0958004765 Ronald Infante";
      else if (value === "Addi") info = "<b>Addi:</b> Pago en cuotas flexibles - Registra tu pedido y completa el proceso con Addi";
      else if (value === "Sistecredito") info = "<b>Sistecredito:</b> Financiación inmediata - Completa tu compra con crédito rápido";
      else if (value === "PSE Bold") {
        console.log('🔥 Método Bold seleccionado: PSE Bold');
        info = `<div class="pse-bold-container">
        <b>PSE Bold - Pago Seguro:</b>
        <p style="color: var(--vscode-text-muted); margin: 8px 0;">Pague de manera segura sin salir de esta página</p>
        <div id="bold-payment-container" style="margin-top: 12px;"></div>
      </div>`;
      } else if (value === "Botón Bancolombia") {
        console.log('🔥 Método Bold seleccionado: Botón Bancolombia');
        info = `<div class="pse-bold-container">
        <b>Botón Bancolombia - Pago Seguro:</b>
        <p style="color: var(--vscode-text-muted); margin: 8px 0;">Pague directamente con su cuenta Bancolombia de forma segura</p>
        <div id="bold-payment-container" style="margin-top: 12px;"></div>
      </div>`;
      } else if (value === "Tarjeta de Crédito o Débito") {
        console.log('🔥 Método Bold seleccionado: Tarjeta de Crédito o Débito');
        info = `<div class="pse-bold-container">
        <b>Tarjeta de Crédito o Débito - Pago Seguro:</b>
        <p style="color: var(--vscode-text-muted); margin: 8px 0;">Pague con cualquier tarjeta de crédito o débito de forma segura</p>
        <div id="bold-payment-container" style="margin-top: 12px;"></div>
      </div>`;
      } else if (value === "Datafono") info = "<b>Datafono:</b> Pago con tarjeta en nuestro punto de venta físico - Se procesa al momento de la entrega o recogida";
      else if (value === "QR") info = "<b>Código QR:</b><br><img src='qr.jpg' alt='Código QR de pago' style='max-width: 200px; height: auto; margin-top: 8px; border-radius: 8px;'>";
      else if (value === "Efectivo_Bogota") info = "<b>Efectivo en Tienda Bogotá:</b> Pago presencial en nuestra tienda de Bogotá";
      else if (value === "Efectivo_Medellin") info = "<b>Efectivo en Tienda Medellín:</b> Pago presencial en nuestra tienda de Medellín";
      else if (value === "Recaudo al Entregar") info = "<b>Recaudo al Entregar:</b> No requiere pago anticipado";

      console.log('🔄 Actualizando info_pago con:', info.substring(0, 50) + '...');
      document.getElementById('info_pago').innerHTML = info;

      // Inicializar Bold PSE si es la opción seleccionada
      if (value === "PSE Bold" || value === "Botón Bancolombia" || value === "Tarjeta de Crédito o Débito") {
        console.log('⏰ Programando inicialización Bold con verificación de container...');

        // Función para verificar que el container existe antes de inicializar
        function initializeBoldWhenReady(maxAttempts = 10, attempt = 1) {
          console.log(`🔍 Intento ${attempt}/${maxAttempts}: Verificando container...`);

          const container = document.getElementById('bold-payment-container');

          if (container) {
            console.log('✅ Container encontrado, ejecutando initializeBoldPayment...');
            try {
              if (typeof initializeBoldPayment === 'function') {
                console.log('🎯 Intentando ejecutar initializeBoldPayment...');
                const result = initializeBoldPayment();
                console.log('✅ initializeBoldPayment ejecutada, resultado:', result);

                if (result === false) {
                  console.warn('⚠️ initializeBoldPayment retornó false, puede haber un problema');
                } else if (result === true) {
                  console.log('🎉 initializeBoldPayment ejecutada exitosamente');
                }
              } else {
                console.error('❌ initializeBoldPayment NO está definida como función');
              }
            } catch (error) {
              console.error('❌ ERROR al ejecutar initializeBoldPayment():', error);
              console.error('❌ Stack trace:', error.stack);
            }
          } else {
            console.log(`⏳ Container no encontrado en intento ${attempt}, reintentando...`);
            if (attempt < maxAttempts) {
              setTimeout(() => initializeBoldWhenReady(maxAttempts, attempt + 1), 100);
            } else {
              console.error('❌ Container no encontrado después de', maxAttempts, 'intentos');
            }
          }
        }

        // Iniciar verificación con un pequeño delay
        setTimeout(() => initializeBoldWhenReady(), 50);
      }
    });

    // Listener para actualizar monto dinámicamente en pedidos manuales
    document.addEventListener('DOMContentLoaded', function() {
      const montoField = document.querySelector('input[name="monto"]');
      if (montoField && !montoField.readOnly) {
        montoField.addEventListener('input', function() {
          // Si hay un método Bold seleccionado, reinicializar
          const metodoPago = document.getElementById('metodo_pago').value;
          if (metodoPago === "PSE Bold" || metodoPago === "Botón Bancolombia" || metodoPago === "Tarjeta de Crédito o Débito") {
            const container = document.getElementById('bold-payment-container');
            if (container) {
              container.innerHTML = '<div class="bold-loading">Actualizando monto...</div>';
              setTimeout(() => {
                console.log('🔄 Ejecutando initializeBoldPayment desde setTimeout...');
                const result = initializeBoldPayment();
                console.log('✅ Resultado de initializeBoldPayment en setTimeout:', result);

                if (result === false) {
                  console.error('❌ initializeBoldPayment falló en setTimeout');
                  if (container) {
                    container.innerHTML = '<div style="color: #ff6b6b; text-align: center; padding: 16px;">Error al actualizar el pago. Intente refrescar la página.</div>';
                  }
                } else if (result === undefined) {
                  console.warn('⚠️ initializeBoldPayment retornó undefined en setTimeout');
                } else {
                  console.log('🎉 initializeBoldPayment exitosa en setTimeout');
                }
              }, 500);
            }
          }
        });
      }
    });

    // Función para inicializar el pago Bold PSE con ventana separada
    function initializeBoldPayment() {
      // Log inmediato para confirmar que la función se ejecuta
      console.log('🚀 initializeBoldPayment() INICIADA');
      console.log('🕐 Timestamp:', new Date().toISOString());

      // Capturar errores globales durante la ejecución
      window.addEventListener('error', function(e) {
        console.error('❌ ERROR GLOBAL durante initializeBoldPayment:', e.message, 'en', e.filename, 'línea', e.lineno);
      });

      try {
        console.log('🔧 PASO 1: Intentando obtener container...');

        const container = document.getElementById('bold-payment-container');
        console.log('🔍 Container encontrado:', container);

        if (!container) {
          console.error('❌ Container Bold no encontrado - RETORNANDO FALSE');
          return false;
        }

        console.log('✅ Container Bold encontrado y verificado');

        // Mostrar información del pago
        console.log('🔧 PASO 2: Intentando mostrar loading...');
        container.innerHTML = '<div style="text-align: center; padding: 16px; color: #007aff;">Preparando pago seguro...</div>';
        console.log('✅ Loading mostrado exitosamente');

        // Generar ID único para la orden
        console.log('🔧 PASO 3: Generando ID de orden...');
        const orderId = 'SEQ-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        console.log('✅ ID orden generado:', orderId);

        // Obtener el monto del pedido con manejo seguro (ya con descuento aplicado)
        console.log('🔧 PASO 4: Obteniendo monto...');
        let monto = <?php echo json_encode(($monto - $descuento) > 0 ? ($monto - $descuento) : 0); ?>;
        console.log('✅ Monto desde PHP:', monto, 'Tipo:', typeof monto);

        // Si no hay monto del PHP, intentar obtenerlo del campo del formulario
        if (!monto || monto === 0) {
          console.log('🔧 PASO 5: Intentando obtener monto del formulario...');
          const montoField = document.querySelector('input[name="monto"]');
          if (montoField && montoField.value) {
            // Remover formato de moneda y convertir a número
            const rawValue = montoField.value.replace(/[^\d]/g, '');
            monto = parseInt(rawValue) || 0;
            console.log('✅ Monto desde formulario:', monto);
          } else {
            console.log('⚠️ No se encontró campo de monto en formulario');
          }
        }

        // Obtener el método de pago seleccionado
        console.log('🔧 PASO 6: Obteniendo método de pago...');
        const metodoPago = document.getElementById('metodo_pago').value;
        console.log('✅ Método de pago:', metodoPago);

        console.log('🔧 PASO 7: Preparando Bold con método:', metodoPago, 'monto:', monto);

        // Validar que hay un monto válido (permitir monto 0 para checkout abierto)
        if (monto === null || monto === undefined || (typeof monto === 'string' && monto.trim() === '')) {
          console.log('⚠️ Inicializando Bold con checkout abierto (sin monto específico)');
          monto = 0; // Monto abierto para que el cliente defina el valor
        }

        console.log('💰 Monto final para Bold:', monto);

        console.log('🔄 Iniciando proceso de preparación Bold...');
        // Obtener datos del cliente del formulario
        const customerData = {
          email: document.querySelector('input[name="correo"]')?.value || '',
          fullName: document.querySelector('input[name="nombre"]')?.value || '',
          phone: document.querySelector('input[name="telefono"]')?.value || '',
          dialCode: '+57'
        };
        console.log('👤 Datos del cliente:', customerData);

        // Datos de dirección de facturación
        const billingAddress = {
          address: document.querySelector('input[name="direccion"]')?.value || '',
          city: 'Bogotá',
          state: 'Cundinamarca',
          country: 'CO'
        };
        console.log('📍 Dirección de facturación:', billingAddress);

        // Crear URL para la ventana de pago
        const paymentParams = new URLSearchParams({
          order_id: orderId,
          amount: monto,
          method: metodoPago,
          customer_data: JSON.stringify(customerData),
          billing_address: JSON.stringify(billingAddress)
        });

        const paymentUrl = 'bold/bold_payment.php?' + paymentParams.toString();
        console.log('🔗 URL de pago generada:', paymentUrl);

        // Mostrar botón para abrir ventana de pago
        console.log('🎨 Creando botón de pago...');
        container.innerHTML = `
        <div style="text-align: center; padding: var(--space-md);">
          <button type="button" onclick="openPaymentWindow('${paymentUrl}', '${orderId}')"
                  style="background: var(--apple-blue); color: white; border: none;
                         padding: 12px 24px; border-radius: var(--radius-md);
                         font-weight: 600; font-size: 0.9rem; cursor: pointer;
                         transition: all 0.2s;">
            🔒 Abrir Pago Seguro
          </button>
          <p style="margin: 8px 0 0 0; font-size: 0.8rem; color: var(--vscode-text-muted);">
            Se abrirá una ventana segura para procesar tu pago
          </p>
        </div>
      `;

        console.log('✅ Botón de pago creado exitosamente');

        // Guardar información del pedido para uso posterior
        window.currentOrderData = {
          orderId: orderId,
          amount: monto,
          method: metodoPago,
          customer: customerData,
          billing: billingAddress
        };

        console.log('💾 Datos del pedido guardados:', window.currentOrderData);
        console.log('🎉 initializeBoldPayment() COMPLETADA EXITOSAMENTE');
        console.log('🕐 Timestamp final:', new Date().toISOString());

        return true; // Retornar éxito

      } catch (error) {
        console.error('❌ ERROR CAPTURADO en initializeBoldPayment:', error);
        console.error('❌ Mensaje:', error.message);
        console.error('❌ Stack trace:', error.stack);
        console.error('❌ Timestamp error:', new Date().toISOString());

        const container = document.getElementById('bold-payment-container');
        if (container) {
          container.innerHTML = '<div style="color: #ff6b6b; text-align: center; padding: 16px;">Error al inicializar el pago. Intente nuevamente.</div>';
        }
        return false; // Retornar error
      }
    }

    // Función para abrir ventana de pago
    function openPaymentWindow(paymentUrl, orderId) {
      console.log('Abriendo ventana de pago para orden:', orderId);

      // Configuración de la ventana popup
      const windowFeatures = 'width=600,height=700,resizable=yes,scrollbars=yes,status=yes,location=no,menubar=no,toolbar=no';

      // Abrir ventana de pago
      const paymentWindow = window.open(paymentUrl, 'boldPayment', windowFeatures);

      if (!paymentWindow) {
        showBoldError('No se pudo abrir la ventana de pago. Verifique que no esté bloqueando ventanas emergentes.');
        return;
      }

      // Mostrar estado en el contenedor principal
      const container = document.getElementById('bold-payment-container');
      container.innerHTML = `
      <div style="text-align: center; padding: var(--space-md); background: var(--gray-dark); border-radius: var(--radius-sm);">
        <div style="color: var(--apple-blue); font-weight: 600; margin-bottom: 8px;">
          🔒 Pago en Proceso
        </div>
        <div style="font-size: 0.85rem; color: var(--vscode-text-muted); margin-bottom: 12px;">
          Orden: ${orderId}
        </div>
        <div style="font-size: 0.8rem; color: var(--vscode-text-muted);">
          Complete su pago en la ventana que se abrió. <br>
          Esta página se actualizará automáticamente al finalizar.
        </div>
        <button type="button" onclick="focusPaymentWindow()"
                style="margin-top: 12px; background: var(--gray-medium); color: var(--vscode-text);
                       border: 1px solid var(--vscode-border); padding: 6px 12px;
                       border-radius: var(--radius-sm); font-size: 0.8rem; cursor: pointer;">
          🔍 Ver Ventana de Pago
        </button>
      </div>
    `;

      // Guardar referencia a la ventana
      window.currentPaymentWindow = paymentWindow;
      window.currentOrderId = orderId;

      // Monitorear el estado de la ventana
      monitorPaymentWindow(paymentWindow, orderId);
    }

    // Función para enfocar la ventana de pago
    function focusPaymentWindow() {
      if (window.currentPaymentWindow && !window.currentPaymentWindow.closed) {
        window.currentPaymentWindow.focus();
      } else {
        showBoldInfo('La ventana de pago ya se cerró. Esperando resultado...');
      }
    }

    // Función para monitorear la ventana de pago
    function monitorPaymentWindow(paymentWindow, orderId) {
      const checkInterval = setInterval(() => {
        if (paymentWindow.closed) {
          console.log('Ventana de pago cerrada para orden:', orderId);
          clearInterval(checkInterval);

          // Si no recibimos un mensaje de éxito, mostrar que se cerró
          setTimeout(() => {
            if (!window.paymentCompleted) {
              showBoldInfo('Ventana de pago cerrada. Si completaste el pago, recibirás una confirmación pronto.');
            }
          }, 1000);
        }
      }, 1000);

      // Timeout de seguridad (15 minutos)
      setTimeout(() => {
        clearInterval(checkInterval);
        if (!paymentWindow.closed) {
          console.log('Timeout de pago alcanzado');
        }
      }, 15 * 60 * 1000);
    }

    // Escuchar mensajes de la ventana de pago
    window.addEventListener('message', function(event) {
      // Verificar que el mensaje viene de nuestra ventana de pago
      if (event.data && event.data.type === 'bold_payment_update') {
        console.log('Mensaje recibido de ventana de pago:', event.data);

        const {
          status,
          orderId,
          data
        } = event.data;

        switch (status) {
          case 'payment_started':
            console.log('Pago iniciado para orden:', orderId);
            showBoldInfo('Pago iniciado correctamente. Complete el proceso en la ventana de pago.');
            break;

          case 'payment_success':
            console.log('Pago exitoso para orden:', orderId);
            window.paymentCompleted = true;
            showBoldSuccess('¡Pago completado exitosamente! 🎉<br>Orden: ' + orderId + '<br>Puede proceder a enviar el formulario.');

            // Habilitar el botón de envío del formulario
            const submitButton = document.querySelector('button[type="submit"]');
            if (submitButton) {
              submitButton.style.background = 'var(--apple-blue)';
              submitButton.style.opacity = '1';
              submitButton.disabled = false;
              submitButton.textContent = 'Enviar pedido ✓';
            }

            // Agregar campo oculto con la información del pago
            addPaymentDataToForm(orderId, 'completed', data);
            break;

          case 'payment_error':
            console.log('Error en pago para orden:', orderId, data);
            showBoldError('Error en el pago: ' + (data.error || 'Error desconocido') + '<br>Puede intentar nuevamente.');
            break;

          case 'payment_closed':
            console.log('Ventana de pago cerrada para orden:', orderId);
            if (!window.paymentCompleted) {
              showBoldInfo('Ventana de pago cerrada. Si completó el pago, recibirá confirmación por email.');
            }
            break;
        }
      }
    });

    // Función para agregar datos de pago al formulario
    function addPaymentDataToForm(orderId, status, paymentData = {}) {
      // Remover campos de pago previos
      const existingFields = document.querySelectorAll('input[name^="payment_"]');
      existingFields.forEach(field => field.remove());

      // Agregar nuevos campos de pago
      const form = document.getElementById('formPedido');

      const orderIdField = document.createElement('input');
      orderIdField.type = 'hidden';
      orderIdField.name = 'payment_order_id';
      orderIdField.value = orderId;
      form.appendChild(orderIdField);

      const statusField = document.createElement('input');
      statusField.type = 'hidden';
      statusField.name = 'payment_status';
      statusField.value = status;
      form.appendChild(statusField);

      const dataField = document.createElement('input');
      dataField.type = 'hidden';
      dataField.name = 'payment_data';
      dataField.value = JSON.stringify(paymentData);
      form.appendChild(dataField);

      console.log('Datos de pago agregados al formulario:', orderId, status);
    }

    // Funciones para mostrar mensajes
    function showBoldSuccess(message) {
      const container = document.getElementById('bold-payment-container');
      container.innerHTML = `<div style="color: var(--apple-blue); text-align: center; padding: var(--space-md); background: var(--gray-dark); border-radius: var(--radius-sm); border: 1px solid var(--apple-blue);">${message}</div>`;
    }

    function showBoldError(message) {
      const container = document.getElementById('bold-payment-container');
      container.innerHTML = `<div style="color: #ff6b6b; text-align: center; padding: var(--space-md); background: var(--gray-dark); border-radius: var(--radius-sm); border: 1px solid #ff6b6b;">${message}</div>`;
    }

    function showBoldInfo(message) {
      const container = document.getElementById('bold-payment-container');
      container.innerHTML = `<div style="color: var(--vscode-text-muted); text-align: center; padding: var(--space-md); background: var(--gray-dark); border-radius: var(--radius-sm);">${message}</div>`;
    }

    // Prevenir envío del formulario si hay un pago en proceso
    document.getElementById('formPedido').addEventListener('submit', function(event) {
      const metodoPago = document.getElementById('metodo_pago').value;

      // Si es un método de pago Bold y no se ha completado el pago
      if ((metodoPago === 'PSE Bold' || metodoPago === 'Botón Bancolombia' || metodoPago === 'Tarjeta de Crédito o Débito') &&
        !window.paymentCompleted) {

        // Verificar si hay una ventana de pago abierta
        if (window.currentPaymentWindow && !window.currentPaymentWindow.closed) {
          event.preventDefault();
          alert('Por favor, complete el pago en la ventana abierta antes de enviar el formulario.');
          window.currentPaymentWindow.focus();
          return false;
        }

        // Si no hay pago completado ni ventana abierta, mostrar mensaje
        const paymentOrderField = document.querySelector('input[name="payment_order_id"]');
        if (!paymentOrderField) {
          event.preventDefault();
          alert('Por favor, complete el proceso de pago antes de enviar el formulario.');
          return false;
        }
      }
    });
  </script>

  <!-- Sistema de archivos CSS/JS optimizados -->
  <!-- Sistema de pago Bold con UI en tiempo real -->
  <script src="bold/bold_realtime_payment_ui.js"></script>
  <script src="bold/bold_payment_enhanced_handler_v3.js"></script>

  <script>
    // Inicializar sistema Bold UI en tiempo real
    document.addEventListener("DOMContentLoaded", function() {
      console.log("🚀 Inicializando Bold Payment UI v2.0");

      // Verificar disponibilidad de sistemas
      if (typeof BoldRealtimePaymentUI !== "undefined" && typeof BoldPaymentEnhancedHandler !== "undefined") {
        console.log("✅ Sistema Bold UI en tiempo real cargado");

        // Inicializar la UI en tiempo real
        const boldUI = new BoldRealtimePaymentUI();

        // Inicializar el handler mejorado
        const boldHandler = new BoldPaymentEnhancedHandler(boldUI);

        // Configurar el formulario
        const form = document.getElementById("formPedido");
        if (form) {
          boldHandler.attachToForm(form);
          console.log("✅ Handler Bold conectado al formulario");
        }

        // Hacer disponible globalmente para legacy
        window.boldPaymentSystem = {
          ui: boldUI,
          handler: boldHandler,
          showBoldSuccess: (data) => boldUI.showFinalResult(data, "success"),
          showBoldError: (message) => boldUI.showFinalResult({
            error: message
          }, "error"),
          showBoldInfo: (message) => boldUI.updateProgress("Información: " + message, 50)
        };

      } else {
        console.warn("⚠️ Sistema Bold UI no disponible, usando fallback");
        // Funciones fallback para compatibilidad
        window.boldPaymentSystem = {
          showBoldSuccess: (data) => {
            console.log("Bold Success (fallback):", data);
            alert("Pago completado exitosamente");
          },
          showBoldError: (message) => {
            console.error("Bold Error (fallback):", message);
            alert("Error en el pago: " + message);
          },
          showBoldInfo: (message) => {
            console.log("Bold Info (fallback):", message);
          }
        };
      }
    });

    // Funciones globales para retrocompatibilidad
    function showBoldSuccess(data) {
      if (window.boldPaymentSystem) {
        window.boldPaymentSystem.showBoldSuccess(data);
      }
    }

    function showBoldError(message) {
      if (window.boldPaymentSystem) {
        window.boldPaymentSystem.showBoldError(message);
      }
    }

    function showBoldInfo(message) {
      if (window.boldPaymentSystem) {
        window.boldPaymentSystem.showBoldInfo(message);
      }
    }

    // Listener para mensajes de la ventana de pago Bold
    window.addEventListener('message', function(event) {
      console.log('Mensaje recibido desde ventana de pago:', event.data);

      if (event.data && event.data.type === 'bold_payment_result') {
        const result = event.data;

        if (result.status === 'success') {
          console.log('Pago Bold exitoso:', result);

          // Agregar el bold_order_id al formulario antes de enviarlo
          if (result.orderId) {
            let boldOrderInput = document.getElementById('bold_order_id_input');
            if (!boldOrderInput) {
              boldOrderInput = document.createElement('input');
              boldOrderInput.type = 'hidden';
              boldOrderInput.name = 'bold_order_id';
              boldOrderInput.id = 'bold_order_id_input';
              document.getElementById('formPedido').appendChild(boldOrderInput);
            }
            boldOrderInput.value = result.orderId;
            console.log('Bold Order ID agregado al formulario:', result.orderId);
          }

          // Mostrar éxito y proceder con el envío del formulario
          showBoldSuccess(result);
          window.paymentCompleted = true;

          // Auto-enviar el formulario después de 2 segundos
          setTimeout(() => {
            console.log('Auto-enviando formulario después de pago exitoso...');
            document.getElementById('formPedido').submit();
          }, 2000);

        } else if (result.status === 'error') {
          console.error('Error en pago Bold:', result);
          showBoldError(result.message || 'Error en el pago');
        }
      }
    });
  </script>

</body>

</html>
