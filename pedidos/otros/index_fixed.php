<?php
require_once "conexion.php";
$pedido_id = isset($_GET['pedido']) ? intval($_GET['pedido']) : 0;
$detalles = [];
$monto = 0;

// Procesamiento de pedido existente por ID
if ($pedido_id) {
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
  <script src="https://checkout.bold.co/library/boldPaymentButton.js"></script>
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
      padding: 20px;
      min-height: 100vh;
    }

    .container {
      max-width: 600px;
      margin: 0 auto;
      padding: var(--space-lg);
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
    textarea, input[type="text"], input[type="number"], input[type="email"], input[type="tel"], input[type="file"], select {
      width: 100%;
      background: var(--vscode-bg);
      border: 1px solid var(--vscode-border);
      border-radius: var(--radius-sm);
      color: var(--vscode-text);
      margin-bottom: var(--space-md);
      font-size: 0.9rem;
      padding: 12px;
      font-family: inherit;
      box-sizing: border-box;
    }

    textarea:focus, input:focus, select:focus {
      outline: none;
      border-color: var(--apple-blue);
      box-shadow: 0 0 0 2px rgba(0, 122, 255, 0.2);
    }

    /* Botones */
    button {
      background: var(--apple-blue);
      color: white;
      border: none;
      padding: 12px 24px;
      border-radius: var(--radius-md);
      font-weight: 600;
      font-size: 0.9rem;
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
      font-size: 0.8rem;
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
      padding: 12px var(--space-md);
      border-radius: var(--radius-md);
      margin-top: var(--space-lg);
      font-size: 0.9rem;
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
      font-size: 0.85rem;
    }

    .label-archivo {
      display: block;
      margin-bottom: var(--space-xs);
      font-size: 0.9rem;
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
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
  </style>
</head>
<body>
<div class="container">
    <img src="logo.png" class="logo" alt="Sequoia Speed">
    <h1>Orden de Pedido Manual</h1>
  <form id="formPedido" method="POST" enctype="multipart/form-data" action="procesar_orden.php">
    <?php if ($pedido_id && $detalles): ?>
      <!-- Pedido guardado desde orden_pedido.php -->
      <div style="margin-bottom:16px; background:rgba(0, 122, 255, 0.1); padding:16px; border-radius:8px; border:1px solid var(--apple-blue);">
        <p style="margin:0 0 12px 0; font-weight:600; color:var(--apple-blue);">📦 Pedido #<?= $pedido_id ?> - Completa los datos de envío</p>
        <div class="tabla-responsive">
          <table style="margin:0 auto;">
            <thead>
              <tr>
                <th>Producto</th>
                <th style="width: 60px; text-align:center;">Cant</th>
                <th style="text-align:right;">Precio</th>
                <th style="text-align:right;">Total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($detalles as $item): ?>
              <tr>
                <td><?= htmlspecialchars($item['nombre']) ?> <?php if($item['talla'] && $item['talla'] != 'N/A'): ?>(Talla: <?= htmlspecialchars($item['talla']) ?>)<?php endif; ?></td>
                <td style="text-align:center;"><?= $item['cantidad'] ?></td>
                <td style="text-align:right;">$<?= number_format($item['precio'], 0, ',', '.') ?></td>
                <td style="text-align:right;">$<?= number_format($item['precio'] * $item['cantidad'], 0, ',', '.') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      
      <!-- Campo oculto con el ID del pedido -->
      <input type="hidden" name="pedido_id" value="<?= $pedido_id ?>">

      <!-- Campo de monto sólo lectura -->
      <input
        type="text"
        name="monto"
        style="text-align:right;"
        value="$<?= number_format($monto, 0, ',', '.') ?>"
        readonly
        required>
        
    <?php elseif ($pedido_id): ?>
      <div style="color:#ff4d4d;text-align:center;margin:20px 0;">
        No se encontraron detalles para este pedido.
      </div>
      <input type="hidden" name="pedido_id" value="<?= $pedido_id ?>">

      <!-- Campo de monto sólo lectura cuando pedido_id existe pero sin detalles -->
      <input
        type="text"
        name="monto"
        style="text-align:right;"
        value="$<?= number_format($monto, 0, ',', '.') ?>"
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
      <option value="PSE Bold">PSE Bold</option>
      <option value="Botón Bancolombia">Botón Bancolombia</option>
      <option value="Tarjeta de Crédito o Débito">Tarjeta de Crédito o Débito</option>
      <option value="QR">QR</option>
      <option value="Efectivo">Efectivo</option>
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
    const value = this.value;
    let info = "";
    if(value === "Nequi" || value === "Transfiya") info = "<b>Nequi / Transfiya:</b> 3213260357";
    else if(value === "Bancolombia") info = "<b>Bancolombia:</b> Ahorros 03500000175 Ronald Infante";
    else if(value === "Provincial") info = "<b>Provincial:</b> Ahorros 0958004765 Ronald Infante";
    else if(value === "PSE Bold") {
      info = `<div class="pse-bold-container">
        <b>PSE Bold - Pago Seguro:</b>
        <p style="color: var(--vscode-text-muted); margin: 8px 0;">Pague de manera segura sin salir de esta página</p>
        <div id="bold-payment-container" style="margin-top: 12px;"></div>
      </div>`;
    }
    else if(value === "Botón Bancolombia") {
      info = `<div class="pse-bold-container">
        <b>Botón Bancolombia - Pago Seguro:</b>
        <p style="color: var(--vscode-text-muted); margin: 8px 0;">Pague directamente con su cuenta Bancolombia de forma segura</p>
        <div id="bold-payment-container" style="margin-top: 12px;"></div>
      </div>`;
    }
    else if(value === "Tarjeta de Crédito o Débito") {
      info = `<div class="pse-bold-container">
        <b>Tarjeta de Crédito o Débito - Pago Seguro:</b>
        <p style="color: var(--vscode-text-muted); margin: 8px 0;">Pague con cualquier tarjeta de crédito o débito de forma segura</p>
        <div id="bold-payment-container" style="margin-top: 12px;"></div>
      </div>`;
    }
    else if(value === "QR") info = "<b>Código QR:</b><br><img src='qr.jpg' alt='Código QR de pago' style='max-width: 200px; height: auto; margin-top: 8px; border-radius: 8px;'>";
    else if(value === "Efectivo") info = "<b>Efectivo:</b> En tienda o recaudo al recibir";
    else if(value === "Recaudo al Entregar") info = "<b>Recaudo al Entregar:</b> No requiere pago anticipado";
    
    document.getElementById('info_pago').innerHTML = info;
    
    // Inicializar Bold PSE si es la opción seleccionada
    if(value === "PSE Bold" || value === "Botón Bancolombia" || value === "Tarjeta de Crédito o Débito") {
      initializeBoldPayment();
    }
  });

  // Función para inicializar el pago Bold PSE con ventana separada
  async function initializeBoldPayment() {
    const container = document.getElementById('bold-payment-container');
    if (!container) {
      console.error('Container Bold no encontrado');
      return;
    }

    // Mostrar información del pago
    container.innerHTML = '<div class="bold-loading">Preparando pago seguro...</div>';

    // Generar ID único para la orden
    const orderId = 'SEQ-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    
    // Obtener el monto del pedido si existe
    const monto = <?php echo $monto > 0 ? $monto : 0; ?>;
    
    // Obtener el método de pago seleccionado
    const metodoPago = document.getElementById('metodo_pago').value;
    
    console.log('Preparando Bold con método:', metodoPago, 'monto:', monto);
    
    try {
      // Obtener datos del cliente del formulario
      const customerData = {
        email: document.querySelector('input[name="correo"]')?.value || '',
        fullName: document.querySelector('input[name="nombre"]')?.value || '',
        phone: document.querySelector('input[name="telefono"]')?.value || '',
        dialCode: '+57'
      };

      // Datos de dirección de facturación
      const billingAddress = {
        address: document.querySelector('input[name="direccion"]')?.value || '',
        city: 'Bogotá',
        state: 'Cundinamarca',
        country: 'CO'
      };

      // Crear URL para la ventana de pago
      const paymentParams = new URLSearchParams({
        order_id: orderId,
        amount: monto,
        method: metodoPago,
        customer_data: JSON.stringify(customerData),
        billing_address: JSON.stringify(billingAddress)
      });

      const paymentUrl = 'bold_payment.php?' + paymentParams.toString();
      
      // Mostrar botón para abrir ventana de pago
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

      // Guardar información del pedido para uso posterior
      window.currentOrderData = {
        orderId: orderId,
        amount: monto,
        method: metodoPago,
        customer: customerData,
        billing: billingAddress
      };

    } catch (error) {
      console.error('Error al preparar Bold:', error);
      showBoldError('Error al preparar el checkout: ' + error.message);
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
      
      const { status, orderId, data } = event.data;
      
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
    if ((metodoPago === 'PSE Bold' || metodoPago === 'Botón Bancolombia' || metodoPago === 'Tarjeta de Crédito o Débito') 
        && !window.paymentCompleted) {
      
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
</body>
</html>
