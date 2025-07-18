/**
 * Sistema de Gestión de Productos - JavaScript
 * Funcionalidad para el módulo de inventario
 */

// Variables globales
let procesando = false;

// Inicialización cuando se carga la página
document.addEventListener('DOMContentLoaded', function() {
    inicializarEventos();
    inicializarBusqueda();
    inicializarNotificaciones();
    console.log('🚀 Sistema de productos inicializado');
});

// Inicializar eventos
function inicializarEventos() {
    // Eventos de los botones de acción
    document.querySelectorAll('.btn-accion').forEach(btn => {
        btn.addEventListener('click', function(e) {
            // Agregar efecto visual al hacer clic
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });
    });
    
    // Eventos del modal
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            cerrarModal();
        }
    });
    
    // Cerrar modal con Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            cerrarModal();
        }
    });
}

// Inicializar búsqueda en tiempo real
function inicializarBusqueda() {
    const searchInput = document.querySelector('.search-input');
    if (searchInput) {
        let searchTimeout;
        
        searchInput.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const searchTerm = e.target.value.trim();
                if (searchTerm.length >= 3 || searchTerm.length === 0) {
                    buscarProductos(searchTerm);
                }
            }, 300);
        });
    }
}

// Función de búsqueda
function buscarProductos(termino) {
    const url = new URL(window.location.href);
    url.searchParams.set('buscar', termino);
    url.searchParams.set('pagina', '1');
    
    // Mostrar indicador de carga
    mostrarIndicadorCarga();
    
    // Redirigir con los nuevos parámetros
    window.location.href = url.toString();
}

// Función para alternar estado activo/inactivo
function toggleActivo(id, estadoActual) {
    if (procesando) return;
    
    const nuevoEstado = estadoActual == '1' ? '0' : '1';
    const accion = nuevoEstado == '1' ? 'activar' : 'desactivar';
    
    if (!confirm(`¿Estás seguro de que quieres ${accion} este producto?`)) {
        return;
    }
    
    procesando = true;
    
    // Mostrar indicador de carga
    mostrarIndicadorCarga();
    
    fetch('eliminar_producto.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${id}&accion=toggle&estado=${nuevoEstado}`
    })
    .then(response => response.json())
    .then(data => {
        ocultarIndicadorCarga();
        
        if (data.success) {
            mostrarNotificacion(data.message, 'success');
            
            // Actualizar la fila en la tabla
            actualizarFilaProducto(id, nuevoEstado);
            
            // Actualizar estadísticas
            actualizarEstadisticas();
            
        } else {
            mostrarNotificacion(data.error || 'Error al actualizar el producto', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        ocultarIndicadorCarga();
        mostrarNotificacion('Error de conexión', 'error');
    })
    .finally(() => {
        procesando = false;
    });
}

// Función para ver detalles del producto
function verDetalles(id) {
    if (procesando) return;
    
    procesando = true;
    mostrarIndicadorCarga();
    
    fetch(`obtener_producto.php?id=${id}`)
    .then(response => response.json())
    .then(data => {
        ocultarIndicadorCarga();
        
        if (data.success) {
            mostrarModalDetalles(data.producto);
        } else {
            mostrarNotificacion(data.error || 'Error al cargar los detalles', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        ocultarIndicadorCarga();
        mostrarNotificacion('Error de conexión', 'error');
    })
    .finally(() => {
        procesando = false;
    });
}

// Mostrar modal con detalles del producto
function mostrarModalDetalles(producto) {
    const modal = document.getElementById('modalDetalles');
    const modalBody = document.getElementById('modalBody');
    
    // Formatear precio
    const precioFormateado = new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0
    }).format(producto.precio);
    
    // Determinar nivel de stock
    let nivelStock = 'alto';
    let iconoStock = '🟢';
    let colorStock = 'var(--stock-alto)';
    
    if (producto.stock_actual <= producto.stock_minimo) {
        nivelStock = 'bajo';
        iconoStock = '🔴';
        colorStock = 'var(--stock-bajo)';
    } else if (producto.stock_actual <= (producto.stock_minimo + (producto.stock_maximo - producto.stock_minimo) * 0.3)) {
        nivelStock = 'medio';
        iconoStock = '🟡';
        colorStock = 'var(--stock-medio)';
    }
    
    // Generar contenido del modal
    modalBody.innerHTML = `
        <div class="producto-detalle">
            <div class="detalle-imagen">
                ${producto.imagen ? 
                    `<img src="uploads/productos/${producto.imagen}" alt="${producto.nombre}" class="imagen-detalle">` :
                    '<div class="imagen-placeholder-grande">📦</div>'
                }
            </div>
            
            <div class="detalle-info">
                <h2 class="detalle-nombre">${producto.nombre}</h2>
                <p class="detalle-descripcion">${producto.descripcion || 'Sin descripción'}</p>
                
                <div class="detalle-grid">
                    <div class="detalle-item">
                        <strong>🏷️ Categoría:</strong>
                        <span class="badge-categoria">${producto.categoria}</span>
                    </div>
                    
                    <div class="detalle-item">
                        <strong>💰 Precio:</strong>
                        <span class="precio-grande">${precioFormateado}</span>
                    </div>
                    
                    <div class="detalle-item">
                        <strong>📊 Stock:</strong>
                        <span class="badge-stock stock-${nivelStock}" style="color: ${colorStock}; border-color: ${colorStock};">
                            ${iconoStock} ${producto.stock_actual}
                        </span>
                    </div>
                    
                    <div class="detalle-item">
                        <strong>📈 Stock Mínimo:</strong>
                        <span>${producto.stock_minimo}</span>
                    </div>
                    
                    <div class="detalle-item">
                        <strong>📊 Stock Máximo:</strong>
                        <span>${producto.stock_maximo}</span>
                    </div>
                    
                    <div class="detalle-item">
                        <strong>🏪 Almacén:</strong>
                        <span class="badge-almacen">${producto.almacen}</span>
                    </div>
                    
                    <div class="detalle-item">
                        <strong>📅 Fecha Creación:</strong>
                        <span>${formatearFecha(producto.fecha_creacion)}</span>
                    </div>
                    
                    <div class="detalle-item">
                        <strong>⚙️ Estado:</strong>
                        <span class="badge-estado ${producto.activo == '1' ? 'activo' : 'inactivo'}">
                            ${producto.activo == '1' ? '✅ Activo' : '❌ Inactivo'}
                        </span>
                    </div>
                    
                    ${producto.sku ? `
                        <div class="detalle-item">
                            <strong>🔖 SKU:</strong>
                            <span class="sku">${producto.sku}</span>
                        </div>
                    ` : ''}
                </div>
                
                <div class="detalle-acciones">
                    <a href="editar_producto.php?id=${producto.id}" class="btn btn-primary">
                        ✏️ Editar Producto
                    </a>
                    
                    <button onclick="toggleActivo(${producto.id}, ${producto.activo})" 
                            class="btn ${producto.activo == '1' ? 'btn-danger' : 'btn-success'}">
                        ${producto.activo == '1' ? '❌ Desactivar' : '✅ Activar'}
                    </button>
                </div>
            </div>
        </div>
    `;
    
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

// Cerrar modal
function cerrarModal() {
    const modal = document.getElementById('modalDetalles');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Actualizar fila del producto en la tabla
function actualizarFilaProducto(id, nuevoEstado) {
    const fila = document.querySelector(`tr[data-id="${id}"]`);
    if (!fila) return;
    
    // Actualizar clase de la fila
    if (nuevoEstado == '0') {
        fila.classList.add('inactive');
    } else {
        fila.classList.remove('inactive');
    }
    
    // Actualizar botón de acción
    const btnToggle = fila.querySelector('.btn-accion[onclick*="toggleActivo"]');
    if (btnToggle) {
        btnToggle.textContent = nuevoEstado == '1' ? '❌' : '✅';
        btnToggle.title = nuevoEstado == '1' ? 'Desactivar producto' : 'Activar producto';
        btnToggle.className = `btn-accion ${nuevoEstado == '1' ? 'btn-desactivar' : 'btn-activar'}`;
        btnToggle.setAttribute('onclick', `toggleActivo(${id}, ${nuevoEstado})`);
    }
    
    // Actualizar badge de estado en la información del producto
    const productoInfo = fila.querySelector('.producto-info');
    let badgeInactivo = productoInfo.querySelector('.badge-inactive');
    
    if (nuevoEstado == '0') {
        if (!badgeInactivo) {
            badgeInactivo = document.createElement('span');
            badgeInactivo.className = 'badge-inactive';
            badgeInactivo.textContent = '❌ Inactivo';
            productoInfo.appendChild(badgeInactivo);
        }
    } else {
        if (badgeInactivo) {
            badgeInactivo.remove();
        }
    }
}

// Actualizar estadísticas
function actualizarEstadisticas() {
    fetch('obtener_estadisticas.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Actualizar números en las tarjetas de estadísticas
            const stats = document.querySelectorAll('.stat-number');
            if (stats[0]) stats[0].textContent = data.estadisticas.total_productos;
            if (stats[1]) stats[1].textContent = data.estadisticas.stock_bajo;
            if (stats[2]) stats[2].textContent = data.estadisticas.activos;
        }
    })
    .catch(error => console.error('Error actualizando estadísticas:', error));
}

// Función para exportar a Excel
function exportarExcel() {
    if (procesando) return;
    
    procesando = true;
    mostrarIndicadorCarga();
    
    // Obtener parámetros de filtro actuales
    const params = new URLSearchParams(window.location.search);
    params.append('export', 'excel');
    
    // Crear enlace de descarga
    const link = document.createElement('a');
    link.href = `exportar_excel.php?${params.toString()}`;
    link.download = `productos_${new Date().toISOString().split('T')[0]}.xlsx`;
    
    // Hacer clic en el enlace
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    setTimeout(() => {
        ocultarIndicadorCarga();
        procesando = false;
        mostrarNotificacion('Excel exportado correctamente', 'success');
    }, 1000);
}

// Función para mostrar notificaciones
function mostrarNotificacion(mensaje, tipo = 'info') {
    const container = document.getElementById('notification-container') || document.body;
    
    const notificacion = document.createElement('div');
    notificacion.className = `notificacion ${tipo}`;
    
    const iconos = {
        success: '✅',
        error: '❌',
        warning: '⚠️',
        info: 'ℹ️'
    };
    
    notificacion.innerHTML = `
        <div class="notificacion-contenido">
            <span class="notificacion-icono">${iconos[tipo] || iconos.info}</span>
            <span class="notificacion-mensaje">${mensaje}</span>
        </div>
        <button class="notificacion-cerrar" onclick="this.parentElement.remove()">×</button>
    `;
    
    // Estilos inline para la notificación
    notificacion.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${tipo === 'success' ? '#238636' : tipo === 'error' ? '#da3633' : tipo === 'warning' ? '#f0ad4e' : '#0969da'};
        color: white;
        padding: 16px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 12px;
        max-width: 400px;
        animation: slideInRight 0.3s ease;
        font-size: 14px;
        font-weight: 500;
    `;
    
    container.appendChild(notificacion);
    
    // Auto-eliminar después de 5 segundos
    setTimeout(() => {
        if (notificacion.parentElement) {
            notificacion.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => {
                notificacion.remove();
            }, 300);
        }
    }, 5000);
}

// Función para mostrar indicador de carga
function mostrarIndicadorCarga() {
    let indicador = document.getElementById('indicador-carga');
    
    if (!indicador) {
        indicador = document.createElement('div');
        indicador.id = 'indicador-carga';
        indicador.innerHTML = `
            <div class="carga-contenido">
                <div class="carga-spinner"></div>
                <span>Cargando...</span>
            </div>
        `;
        
        indicador.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(13, 17, 23, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(4px);
        `;
        
        document.body.appendChild(indicador);
    }
    
    indicador.style.display = 'flex';
}

// Función para ocultar indicador de carga
function ocultarIndicadorCarga() {
    const indicador = document.getElementById('indicador-carga');
    if (indicador) {
        indicador.style.display = 'none';
    }
}

// Función para formatear fechas
function formatearFecha(fecha) {
    const date = new Date(fecha);
    return date.toLocaleDateString('es-CO', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Inicializar sistema de notificaciones
function inicializarNotificaciones() {
    // Agregar estilos CSS para notificaciones
    const styles = document.createElement('style');
    styles.textContent = `
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        .notificacion-contenido {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
        }
        
        .notificacion-cerrar {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 18px;
            padding: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background 0.2s;
        }
        
        .notificacion-cerrar:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .carga-contenido {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 32px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            color: var(--text-primary);
        }
        
        .carga-spinner {
            width: 32px;
            height: 32px;
            border: 3px solid var(--border-color);
            border-top: 3px solid var(--color-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .detalle-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
            margin: 24px 0;
        }
        
        .detalle-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .detalle-item strong {
            color: var(--text-secondary);
            font-size: 13px;
        }
        
        .imagen-detalle {
            width: 200px;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        
        .imagen-placeholder-grande {
            width: 200px;
            height: 200px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: var(--text-secondary);
        }
        
        .producto-detalle {
            display: flex;
            gap: 24px;
            align-items: flex-start;
        }
        
        .detalle-info {
            flex: 1;
        }
        
        .detalle-nombre {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .detalle-descripcion {
            color: var(--text-secondary);
            margin-bottom: 16px;
            line-height: 1.6;
        }
        
        .precio-grande {
            font-size: 20px;
            font-weight: 600;
            color: var(--color-primary);
        }
        
        .badge-estado.activo {
            background: rgba(35, 134, 54, 0.2);
            color: var(--color-success);
            border: 1px solid var(--color-success);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .badge-estado.inactivo {
            background: rgba(218, 54, 51, 0.2);
            color: var(--color-danger);
            border: 1px solid var(--color-danger);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .sku {
            font-family: monospace;
            background: var(--bg-tertiary);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .detalle-acciones {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid var(--border-color);
        }
        
        @media (max-width: 768px) {
            .producto-detalle {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .detalle-grid {
                grid-template-columns: 1fr;
            }
            
            .detalle-acciones {
                flex-direction: column;
                width: 100%;
            }
        }
    `;
    
    document.head.appendChild(styles);
}

// Función para manejar errores globales
window.addEventListener('error', function(e) {
    console.error('Error global:', e.error);
    mostrarNotificacion('Ha ocurrido un error inesperado', 'error');
});

// Función para manejar errores de red
window.addEventListener('unhandledrejection', function(e) {
    console.error('Error de promesa:', e.reason);
    mostrarNotificacion('Error de conexión', 'error');
});

console.log('✅ Sistema de productos JavaScript cargado correctamente');