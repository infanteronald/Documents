// Método de pago dinámico
const mp = document.getElementById('metodo_pago');
const infoPago = document.getElementById('info_pago');
mp.addEventListener('change', function() {
  let txt = '';
  switch(this.value) {
    case 'Nequi':
    case 'Transfiya':
      txt = 'Nequi / Transfiya: <b>3213260357</b>';
      break;
    case 'Bancolombia':
      txt = 'Bancolombia Ahorros: <b>03500000175</b> Ronald Infante';
      break;
    case 'Provincial':
      txt = 'Provincial Ahorros: <b>0958004765</b> Ronald Infante';
      break;
    case 'PSE':
      txt = 'Solicite su link de pago a su asesor.';
      break;
    case 'Contra entrega':
      txt = 'Pagará al recibir el pedido. No requiere pago anticipado.';
      break;
    default: txt = '';
  }
  infoPago.innerHTML = txt;
  infoPago.style.display = txt ? 'block' : 'none';
});

// Validación rápida extra
document.getElementById('formPedido').addEventListener('submit', function(e) {
  let correo = document.querySelector('input[name="correo"]').value;
  let tel = document.querySelector('input[name="telefono"]').value;
  let emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  let telRegex = /^\d{7,15}$/;
  let errores = [];
  if (!emailRegex.test(correo)) errores.push('Correo no válido.');
  if (!telRegex.test(tel)) errores.push('Teléfono inválido (solo números, 7 a 15 dígitos).');
  if (errores.length > 0) {
    e.preventDefault();
    alert(errores.join('\n'));
  }
});