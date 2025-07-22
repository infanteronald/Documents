/**
 * Sequoia Speed - Gestión de Pedidos
 * Funcionalidades específicas para el manejo de pedidos
 */

class PedidoManager {
  constructor() {
    this.carrito = [];
    this.productos = [];
    this.cliente = {};
    this.init();
  }
  
  init() {
    this.setupEventListeners();
    this.loadProductos();
    console.log('✅ PedidoManager inicializado');
  }
  
  setupEventListeners() {
    // Eventos para categorías y búsqueda
    const categoriaSelect = document.getElementById('categoria');
    const busquedaInput = document.getElementById('busqueda');
    
    if (categoriaSelect) {
      categoriaSelect.addEventListener('change', () => this.cargarProductos());
    }
    
    if (busquedaInput) {
      busquedaInput.addEventListener('input', 
        window.SequoiaSpeed.utils.debounce(() => this.cargarProductos(), 300)
      );
    }
    
    // Eventos para productos personalizados
    this.setupProductosPersonalizados();
  }
  
  setupProductosPersonalizados() {
    const customNombre = document.getElementById('custom-nombre');
    if (customNombre) {
      customNombre.addEventListener('input', () => {
        this.actualizarTallasProductoPersonalizado();
      });
    }
  }
  
  async loadProductos() {
    try {
      const response = await fetch('/productos_por_categoria.php');
      const data = await response.json();
      this.productos = data.productos || [];
      console.log(`📦 ${this.productos.length} productos cargados`);
    } catch (error) {
      console.error('Error cargando productos:', error);
    }
  }
  
  async cargarProductos() {
    const categoria = document.getElementById('categoria')?.value || '';
    const busqueda = document.getElementById('busqueda')?.value || '';
    const productosContainer = document.getElementById('productos-container');
    
    if (!productosContainer) return;
    
    // Mostrar loading
    window.SequoiaSpeed.utils.showLoading(productosContainer, 'Cargando productos...');
    
    try {
      const formData = new FormData();
      formData.append('categoria', categoria);
      formData.append('busqueda', busqueda);
      
      const response = await fetch('/productos_por_categoria.php', {
        method: 'POST',
        body: formData
      });
      
      const data = await response.json();
      
      if (data.success) {
        this.renderProductos(data.productos, productosContainer);
      } else {
        productosContainer.innerHTML = `
          <div class="alert error">
            <div class="alert-content">
              Error al cargar productos: ${data.message || 'Error desconocido'}
            </div>
          </div>
        `;
      }
    } catch (error) {
      console.error('Error cargando productos:', error);
      productosContainer.innerHTML = `
        <div class="alert error">
          <div class="alert-content">
            Error de conexión al cargar productos
          </div>
        </div>
      `;
    }
  }
  
  renderProductos(productos, container) {
    if (!productos || productos.length === 0) {
      container.innerHTML = `
        <div class="alert info">
          <div class="alert-content">
            No se encontraron productos para los criterios seleccionados
          </div>
        </div>
      `;
      return;
    }
    
    const productosHTML = productos.map(producto => `
      <div class="producto-item card" data-id="${producto.id}">
        <div class="producto-info">
          <h3 class="producto-nombre">${producto.nombre}</h3>
          <p class="producto-precio">${window.SequoiaSpeed.utils.formatCurrency(producto.precio)}</p>
          <p class="producto-stock">Stock: ${producto.stock}</p>
        </div>
        <div class="producto-tallas">
          ${this.renderTallasProducto(producto)}
        </div>
        <div class="producto-actions">
          <button type="button" class="btn btn-outline" onclick="pedidoManager.agregarAlCarrito(${producto.id})">
            Agregar al carrito
          </button>
        </div>
      </div>
    `).join('');
    
    container.innerHTML = productosHTML;
    
    // Aplicar animaciones
    container.querySelectorAll('.producto-item').forEach((item, index) => {
      item.style.animationDelay = `${index * 0.1}s`;
      item.classList.add('fade-in');
    });
  }
  
  renderTallasProducto(producto) {
    if (!producto.tallas || producto.tallas.length === 0) {
      return '<p class="text-muted">Sin tallas específicas</p>';
    }
    
    return `
      <div class="tallas-grid">
        ${producto.tallas.map(talla => `
          <button type="button" class="talla-btn" data-talla="${talla.talla}" data-stock="${talla.stock}">
            ${talla.talla}
            <small>(${talla.stock})</small>
          </button>
        `).join('')}
      </div>
    `;
  }
  
  actualizarTallasProductoPersonalizado() {
    const nombreProducto = document.getElementById('custom-nombre')?.value?.toLowerCase() || '';
    const tallaSelect = document.getElementById('custom-talla');
    
    if (!tallaSelect) return;
    
    // Detectar si es una bota o calzado
    const esBotas = nombreProducto.includes('bota') || 
                   nombreProducto.includes('zapato') || 
                   nombreProducto.includes('calzado') || 
                   nombreProducto.includes('zapatilla');
    
    // Limpiar opciones existentes
    tallaSelect.innerHTML = '<option value="">Seleccionar talla</option>';
    
    let tallasDisponibles;
    if (esBotas) {
      // Tallas de calzado
      tallasDisponibles = ['35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45'];
    } else {
      // Tallas de ropa
      tallasDisponibles = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
    }
    
    tallasDisponibles.forEach(talla => {
      const option = document.createElement('option');
      option.value = talla;
      option.textContent = talla;
      tallaSelect.appendChild(option);
    });
    
    console.log(`👕 Tallas actualizadas para ${esBotas ? 'calzado' : 'ropa'}: ${tallasDisponibles.join(', ')}`);
  }
  
  agregarAlCarrito(productoId, talla = null, cantidad = 1) {
    const producto = this.productos.find(p => p.id === productoId);
    if (!producto) {
      this.mostrarError('Producto no encontrado');
      return;
    }
    
    // Verificar stock
    if (producto.stock < cantidad) {
      this.mostrarError('Stock insuficiente');
      return;
    }
    
    // Agregar al carrito
    const itemCarrito = {
      id: Date.now(), // ID único para el item del carrito
      productoId: productoId,
      nombre: producto.nombre,
      precio: producto.precio,
      talla: talla,
      cantidad: cantidad,
      subtotal: producto.precio * cantidad
    };
    
    this.carrito.push(itemCarrito);
    this.actualizarCarrito();
    this.mostrarExito(`${producto.nombre} agregado al carrito`);
    
    console.log('🛒 Producto agregado al carrito:', itemCarrito);
  }
  
  removerDelCarrito(itemId) {
    const index = this.carrito.findIndex(item => item.id === itemId);
    if (index !== -1) {
      const item = this.carrito[index];
      this.carrito.splice(index, 1);
      this.actualizarCarrito();
      this.mostrarExito(`${item.nombre} removido del carrito`);
    }
  }
  
  actualizarCantidadCarrito(itemId, nuevaCantidad) {
    const item = this.carrito.find(item => item.id === itemId);
    if (item && nuevaCantidad > 0) {
      item.cantidad = nuevaCantidad;
      item.subtotal = item.precio * nuevaCantidad;
      this.actualizarCarrito();
    }
  }
  
  actualizarCarrito() {
    const carritoContainer = document.getElementById('carrito-container');
    const totalElement = document.getElementById('total-pedido');
    
    if (!carritoContainer) return;
    
    if (this.carrito.length === 0) {
      carritoContainer.innerHTML = `
        <div class="alert info">
          <div class="alert-content">
            El carrito está vacío
          </div>
        </div>
      `;
      if (totalElement) totalElement.textContent = window.SequoiaSpeed.utils.formatCurrency(0);
      return;
    }
    
    const carritoHTML = `
      <div class="table-container">
        <table id="carrito-table">
          <thead>
            <tr>
              <th>Producto</th>
              <th>Talla</th>
              <th>Cantidad</th>
              <th>Precio</th>
              <th>Subtotal</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            ${this.carrito.map(item => `
              <tr data-item-id="${item.id}">
                <td>${item.nombre}</td>
                <td>${item.talla || 'N/A'}</td>
                <td>
                  <input type="number" 
                         value="${item.cantidad}" 
                         min="1" 
                         class="cantidad-input"
                         onchange="pedidoManager.actualizarCantidadCarrito(${item.id}, this.value)">
                </td>
                <td>${window.SequoiaSpeed.utils.formatCurrency(item.precio)}</td>
                <td>${window.SequoiaSpeed.utils.formatCurrency(item.subtotal)}</td>
                <td>
                  <button type="button" 
                          class="btn btn-sm btn-outline" 
                          onclick="pedidoManager.removerDelCarrito(${item.id})"
                          title="Eliminar">
                    🗑️
                  </button>
                </td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    `;
    
    carritoContainer.innerHTML = carritoHTML;
    
    // Actualizar total
    const total = this.carrito.reduce((sum, item) => sum + item.subtotal, 0);
    if (totalElement) {
      totalElement.textContent = window.SequoiaSpeed.utils.formatCurrency(total);
    }
    
    // Actualizar campo hidden para el formulario
    const carritoDataInput = document.getElementById('carrito-data');
    if (carritoDataInput) {
      carritoDataInput.value = JSON.stringify(this.carrito);
    }
    
    console.log(`🛒 Carrito actualizado: ${this.carrito.length} items, total: ${window.SequoiaSpeed.utils.formatCurrency(total)}`);
  }
  
  vaciarCarrito() {
    this.carrito = [];
    this.actualizarCarrito();
    this.mostrarExito('Carrito vaciado');
  }
  
  getTotal() {
    return this.carrito.reduce((sum, item) => sum + item.subtotal, 0);
  }
  
  getItemCount() {
    return this.carrito.reduce((sum, item) => sum + item.cantidad, 0);
  }
  
  mostrarExito(mensaje) {
    this.mostrarNotificacion(mensaje, 'success');
  }
  
  mostrarError(mensaje) {
    this.mostrarNotificacion(mensaje, 'error');
  }
  
  mostrarNotificacion(mensaje, tipo = 'info') {
    // Remover notificaciones existentes
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(n => n.remove());
    
    const notification = document.createElement('div');
    notification.className = `notification alert ${tipo}`;
    notification.innerHTML = `
      <div class="alert-content">
        ${mensaje}
      </div>
    `;
    
    // Posicionar en la esquina superior derecha
    notification.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 1000;
      max-width: 300px;
      animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remover después de 3 segundos
    setTimeout(() => {
      notification.style.animation = 'slideOut 0.3s ease';
      setTimeout(() => {
        if (notification.parentNode) {
          notification.remove();
        }
      }, 300);
    }, 3000);
  }
}

// Gestión de productos personalizados
class ProductoPersonalizadoManager {
  constructor() {
    this.productosPersonalizados = [];
    this.init();
  }
  
  init() {
    this.setupEventListeners();
    console.log('✅ ProductoPersonalizadoManager inicializado');
  }
  
  setupEventListeners() {
    const form = document.getElementById('form-producto-personalizado');
    if (form) {
      form.addEventListener('submit', (e) => this.handleSubmit(e));
    }
  }
  
  handleSubmit(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const producto = {
      id: Date.now(),
      nombre: formData.get('custom-nombre'),
      talla: formData.get('custom-talla'),
      cantidad: parseInt(formData.get('custom-cantidad')) || 1,
      precio: parseFloat(formData.get('custom-precio')) || 0,
      descripcion: formData.get('custom-descripcion') || '',
      personalizado: true
    };
    
    // Validaciones
    if (!producto.nombre || !producto.talla || producto.precio <= 0) {
      this.mostrarError('Por favor complete todos los campos requeridos');
      return;
    }
    
    this.productosPersonalizados.push(producto);
    this.agregarAlCarrito(producto);
    this.limpiarFormulario(event.target);
    
    console.log('✨ Producto personalizado creado:', producto);
  }
  
  agregarAlCarrito(producto) {
    if (window.pedidoManager) {
      const itemCarrito = {
        id: Date.now(),
        productoId: producto.id,
        nombre: `${producto.nombre} (Personalizado)`,
        precio: producto.precio,
        talla: producto.talla,
        cantidad: producto.cantidad,
        subtotal: producto.precio * producto.cantidad,
        personalizado: true,
        descripcion: producto.descripcion
      };
      
      window.pedidoManager.carrito.push(itemCarrito);
      window.pedidoManager.actualizarCarrito();
      window.pedidoManager.mostrarExito(`Producto personalizado "${producto.nombre}" agregado al carrito`);
    }
  }
  
  limpiarFormulario(form) {
    form.reset();
    const tallaSelect = form.querySelector('#custom-talla');
    if (tallaSelect) {
      tallaSelect.innerHTML = '<option value="">Seleccionar talla</option>';
    }
  }
  
  mostrarError(mensaje) {
    if (window.pedidoManager) {
      window.pedidoManager.mostrarError(mensaje);
    }
  }
}

// Inicialización cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
  // Inicializar gestores de pedidos
  window.pedidoManager = new PedidoManager();
  window.productoPersonalizadoManager = new ProductoPersonalizadoManager();
  
  console.log('✅ Gestores de pedidos inicializados');
});

// Agregar estilos para animaciones
const styles = `
  @keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
  }
  
  @keyframes slideOut {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
  }
  
  .notification {
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
  }
  
  .producto-item {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }
  
  .producto-item:hover {
    transform: translateY(-2px);
  }
  
  .talla-btn {
    transition: all 0.2s ease;
  }
  
  .talla-btn:hover {
    transform: scale(1.05);
  }
  
  .talla-btn.selected {
    background: var(--apple-blue, #007aff);
    color: white;
    border-color: var(--apple-blue, #007aff);
  }
`;

// Inyectar estilos
const styleSheet = document.createElement('style');
styleSheet.textContent = styles;
document.head.appendChild(styleSheet);
