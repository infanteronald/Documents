<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Orden de Pedido - Sequoia Speed</title>
  <link rel="stylesheet" href="apple-ui.css">
<style>
  body {
    background: #f5f5f7;
  }
  .container {
    max-width: 440px;
    margin: 42px auto 0 auto;
    background: #fff;
    border-radius: 28px;
    box-shadow: 0 6px 48px #cfcfd2a0;
    padding: 35px 18px 28px 18px;
    text-align: center;
    min-height: 70vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 11px;
    border: 1.5px solid #eaeaec;
  }
  .logo-bg {
    width: 118px;
    height: 118px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: -26px auto 10px auto;
    background: #f5f5f7;
    border-radius: 20px;
    box-shadow: 0 2px 18px #e0e0e0;
  }
  .logo {
    width: 92px;
    height: auto;
    border-radius: 10px;
    background: transparent;
    margin: 0;
    box-shadow: none;
    display: block;
  }
  h1 {
    font-size: 2.0rem;
    font-weight: 700;
    margin-bottom: 16px;
    color: #222;
    letter-spacing: .1px;
  }
  form {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 14px;
    margin-bottom: 8px;
    align-items: stretch;
    box-sizing: border-box;
  }
  textarea, input[type="text"], input[type="number"], input[type="email"], input[type="tel"], select {
    width: 100%;
    border: 1.3px solid #dadadc;
    border-radius: 14px;
    background: #f5f5f7;
    color: #222;
    padding: 13px 14px;
    font-size: 1.03rem;
    font-family: inherit;
    box-shadow: 0 1px 5px #e7e7eb35;
    outline: none;
    margin: 0;
    transition: border .15s, background .13s, box-shadow .14s;
    box-sizing: border-box;
    min-width: 0;
  }
  textarea:focus, input:focus, select:focus {
    border: 1.8px solid #2997ff;
    background: #f2f7fd;
    box-shadow: 0 2px 11px #d7d7e1;
  }
  select {
    appearance: none;
    -webkit-appearance: none;
  }
  .label-archivo {
    display: block;
    text-align: left;
    color: #888;
    font-size: .97rem;
    font-weight: 600;
    margin-bottom: 3px;
    margin-top: 3px;
    margin-left: 2px;
  }
  input[type="file"] {
    background: none;
    border: none;
    box-shadow: none;
    color: #444;
    font-size: 1rem;
    margin-bottom: 0;
    margin-top: 0;
  }
  button[type="submit"] {
    background: #2997ff;
    color: #fff;
    border: none;
    border-radius: 14px;
    padding: 14px 0;
    font-size: 1.12rem;
    font-weight: 700;
    margin-top: 8px;
    box-shadow: 0 2px 10px #aad1fc42;
    cursor: pointer;
    transition: background .13s, box-shadow .12s, transform .11s;
    letter-spacing: .2px;
    width: 100%;
  }
  button[type="submit"]:hover {
    background: #1a73e8;
    box-shadow: 0 4px 16px #85b7ec73;
    transform: scale(1.03);
  }
  .btn-whatsapp {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    margin-top: 13px;
    padding: 12px 18px;
    border-radius: 14px;
    background: #e5f7ea;
    color: #1a1a1a;
    font-weight: 700;
    font-size: 1.09rem;
    text-decoration: none;
    box-shadow: 0 1px 8px #e5e5e7;
    border: 1.4px solid #cbefdb;
    transition: background .12s, color .13s, border .13s;
  }
  .btn-whatsapp:hover {
    background: #d6ffe6;
    color: #075e54;
    border: 1.4px solid #25d366;
    transform: scale(1.03);
  }
  .wa-icon {
    width: 22px;
    height: 22px;
    margin-right: 3px;
  }
  .info-pago {
    color: #222;
    background: #f8f9fb;
    border-radius: 8px;
    padding: 8px 14px;
    margin-bottom: 2px;
    font-size: .97rem;
    text-align: left;
    min-height: 18px;
    border: 1px solid #e3e7ef;
  }
  @media (max-width: 600px) {
    .container {
      max-width: 99vw;
      padding: 10vw 3vw 7vw 3vw;
      margin-top: 4vw;
      border-radius: 18px;
    }
    .logo-bg {
      width: 70px;
      height: 70px;
      margin: -25px auto 7px auto;
    }
    .logo { width: 54px; }
  }
</style>
</head>
<body>
<div class="container">
  <div class="logo-bg">
  <img src="logo.png" class="logo" alt="Sequoia Speed">
</div>
  <h1>Orden de Pedido</h1>
  <form id="formPedido" method="POST" enctype="multipart/form-data" action="procesar_orden.php">
    <textarea name="pedido" rows="3" placeholder="Indique su pedido. Indicar producto, tallas y cantidades" required></textarea>
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
  // Actualiza info de pago estilo Apple
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