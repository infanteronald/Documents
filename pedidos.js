function cambiarEstado(id, estado) {
    if(!confirm('¿Está seguro de cambiar el estado de este pedido a ' + estado + '?')) return;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'actualizar_estado.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function(){
        if(xhr.status==200) location.reload();
        else alert('Error al actualizar. Intenta de nuevo.');
    };
    xhr.send('id='+encodeURIComponent(id)+'&estado='+encodeURIComponent(estado));
}

// MODAL detalle
function cerrarModalDetalle() {
    var modal = document.getElementById('modal-detalle').querySelector('.modal-detalle');
    if (modal) modal.classList.remove('comprobante-modal');
    document.getElementById('modal-detalle').style.display = 'none';
    document.getElementById('modal-contenido').innerHTML = '';
}

document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.ver-detalle').forEach(function(link){
        link.addEventListener('click', function(e){
            e.preventDefault();
            cargarDetallePedido(this.getAttribute('data-id'));
        });
    });
    // cerrar modal con ESC
    document.addEventListener('keydown', function(e){
        if(e.key === 'Escape') cerrarModalDetalle();
    });
    document.getElementById('modal-detalle').addEventListener('click', function(e){
        if(e.target === this) cerrarModalDetalle();
    });
    var formGuia = document.getElementById('formGuia');
    if(formGuia){
        formGuia.onsubmit = function(e){
            e.preventDefault();
            var fileInput = document.getElementById('guia_file');
            if(!fileInput.files.length){
                document.getElementById('guia_status').innerHTML = 'Debes adjuntar la guía de envío.';
                return false;
            }
            var formData = new FormData(formGuia);
            document.getElementById('guia_status').innerHTML = 'Enviando...';
            fetch('subir_guia.php', {
                method: 'POST',
                body: formData
            }).then(res=>res.json()).then(data=>{
                if(data.success){
                    document.getElementById('guia_status').style.color = '#17b300';
                    document.getElementById('guia_status').innerHTML = '¡Guía enviada y cliente notificado!';
                    setTimeout(()=>{ window.location.reload(); },1300);
                } else {
                    document.getElementById('guia_status').style.color = '#e02b2b';
                    document.getElementById('guia_status').innerHTML = data.error || 'Error al enviar.';
                }
            }).catch(()=>{
                document.getElementById('guia_status').style.color = '#e02b2b';
                document.getElementById('guia_status').innerHTML = 'Error de red.';
            });
        };
    }
});

// Restaurar pedido anulado -> sin_enviar
function restaurarPedido(id){
    if(!confirm('¿Restaurar este pedido? El pedido pasará a estado "sin enviar".')) return;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'restaurar_pedido.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function(){
        if(xhr.status==200){
            var data = JSON.parse(xhr.responseText);
            if(data.success) window.location.reload();
            else alert(data.error || 'Error al restaurar.');
        }else alert('Error en el servidor');
    };
    xhr.send('id='+encodeURIComponent(id));
}

// Archivar pedido anulado
function archivarPedido(id){
    if(!confirm('¿Archivar este pedido? Solo podrás consultarlo en el historial.')) return;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'archivar_pedido.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function(){
        if(xhr.status==200){
            var data = JSON.parse(xhr.responseText);
            if(data.success) window.location.reload();
            else alert(data.error || 'Error al archivar.');
        }else alert('Error en el servidor');
    };
    xhr.send('id='+encodeURIComponent(id));
}

// Abre el modal para subir la guía
function abrirModalGuia(id, correo){
    document.getElementById('modal-guia-bg').style.display = 'flex';
    document.getElementById('guia_id_pedido').value = id;
    document.getElementById('guia_file').value = '';
    document.getElementById('guia_status').innerHTML = '';
}

// Cierra modal guía
function cerrarModalGuia(){
    document.getElementById('modal-guia-bg').style.display = 'none';
}

// Cargar detalle de pedido (AJAX)
function cargarDetallePedido(id){
    var modal = document.getElementById('modal-detalle');
    var contenido = document.getElementById('modal-contenido');
    contenido.innerHTML = '<div style="text-align:center;padding:25px;">Cargando...</div>';
    modal.style.display = 'flex';

    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'ver_detalle_pedido.php?id='+encodeURIComponent(id), true);
    xhr.onload = function(){
        if(xhr.status==200) contenido.innerHTML = xhr.responseText;
        else contenido.innerHTML = "<div style='color:#d32f2f;'>Error al cargar detalle.</div>";
    };
    xhr.send();
}