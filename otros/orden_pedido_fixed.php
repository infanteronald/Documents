<?php
require_once "conexion.php";

// Obtener categorías
$categorias = [];
$sql_cat = "SELECT DISTINCT categoria FROM productos WHERE activo = 1 ORDER BY categoria ASC";
$res_cat = $conn->query($sql_cat);
while ($row = $res_cat->fetch_assoc()) {
    $categorias[] = $row['categoria'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de Pedido - Sequoia Speed</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="apple-ui.css">
    <style>
        body { 
            background: #1e1e1e; 
            font-family: 'SF Pro Display', 'Helvetica Neue', Arial, sans-serif; 
            margin: 0; 
            padding: 20px; 
            color: #e0e0e0; 
        }
        
        .container { 
            max-width: 900px; 
            margin: 0 auto; 
            background: #232323; 
            border-radius: 18px; 
            box-shadow: 0 8px 32px rgba(0,0,0,0.3); 
            padding: 32px; 
            border: 1px solid #333;
        }
        
        .header {
            display: flex;
            align-items: center;
            margin-bottom: 32px;
            padding-bottom: 20px;
            border-bottom: 1px solid #444;
        }
        
        .logo {
            height: 50px;
            width: auto;
            margin-right: 16px;
        }
        
        h1 {
            font-size: 2.2rem;
            font-weight: 600;
            margin: 0;
            color: #e0e0e0;
        }
        
        .form-section {
            margin-bottom: 32px;
        }
        
        .form-section h3 {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 16px;
            color: #e0e0e0;
        }
        
        .form-row {
            display: flex;
            gap: 16px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        select, input[type="text"], input[type="number"] {
            flex: 1;
            min-width: 200px;
            padding: 12px 16px;
            border: 1px solid #444;
            border-radius: 12px;
            font-size: 16px;
            background: #333;
            color: #e0e0e0;
            transition: all 0.2s ease;
        }
        
        select:focus, input:focus {
            outline: none;
            border-color: #007aff;
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }
        
        .productos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 24px;
        }
        
        .producto-card {
            background: #2a2a2a;
            border: 1px solid #444;
            border-radius: 16px;
            padding: 20px;
            transition: all 0.2s ease;
            color: #e0e0e0;
        }
        
        .producto-card:hover {
            border-color: #007aff;
            box-shadow: 0 4px 20px rgba(0, 122, 255, 0.15);
        }
        
        .producto-card h4 {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0 0 8px 0;
            color: #e0e0e0;
        }
        
        .producto-precio {
            font-size: 1.3rem;
            font-weight: 700;
            color: #007aff;
            margin-bottom: 16px;
        }
        
        .tallas-container {
            margin-bottom: 16px;
        }
        
        .tallas-container label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: #bbb;
        }
        
        .tallas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(50px, 1fr));
            gap: 8px;
        }
        
        .talla-btn {
            padding: 8px 12px;
            border: 1px solid #444;
            border-radius: 8px;
            background: #333;
            color: #e0e0e0;
            cursor: pointer;
            text-align: center;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .talla-btn:hover {
            border-color: #007aff;
            background: #1a3d5c;
        }
        
        .talla-btn.selected {
            background: #007aff;
            color: #fff;
            border-color: #007aff;
        }
        
        .cantidad-container {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .cantidad-container label {
            font-weight: 500;
            color: #bbb;
        }
        
        .cantidad-container input {
            width: 80px;
            text-align: center;
        }
        
        .btn {
            background: #007aff;
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-block;
            text-decoration: none;
        }
        
        .btn:hover {
            background: #0056b3;
            transform: translateY(-1px);
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid #007aff;
            color: #007aff;
        }
        
        .btn-outline:hover {
            background: #007aff;
            color: #fff;
        }
        
        .btn-danger {
            background: #ff3b30;
        }
        
        .btn-danger:hover {
            background: #d70015;
        }
        
        .carrito-section {
            background: #2a2a2a;
            border: 1px solid #444;
            border-radius: 16px;
            padding: 24px;
            margin-top: 32px;
        }
        
        .carrito-section h3 {
            font-size: 1.4rem;
            font-weight: 600;
            margin: 0 0 20px 0;
            color: #e0e0e0;
        }
        
        .carrito-item {
            background: #333;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .item-info h5 {
            margin: 0 0 4px 0;
            font-weight: 600;
            color: #e0e0e0;
        }
        
        .item-info p {
            margin: 0;
            font-size: 14px;
            color: #bbb;
        }
        
        .item-precio {
            font-weight: 700;
            color: #007aff;
            font-size: 1.1rem;
        }
        
        .total-section {
            border-top: 2px solid #444;
            padding-top: 20px;
            margin-top: 20px;
        }
        
        .total-precio {
            font-size: 1.8rem;
            font-weight: 700;
            color: #e0e0e0;
            text-align: right;
        }
        
        .personalizado-section {
            background: #3a3a2f;
            border: 1px solid #666633;
            border-radius: 16px;
            padding: 24px;
            margin-top: 24px;
        }
        
        .personalizado-section h4 {
            color: #ffcc00;
            margin: 0 0 16px 0;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .btn-finalizar {
            width: 100%;
            font-size: 1.2rem;
            padding: 16px;
            margin-top: 24px;
            background: #34c759;
        }
        
        .btn-finalizar:hover {
            background: #30a14e;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                padding: 20px;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
            
            h1 {
                font-size: 1.8rem;
            }
            
            .productos-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                flex-direction: column;
            }
            
            select, input[type="text"], input[type="number"] {
                min-width: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="logo.png" class="logo" alt="Sequoia Speed">
            <h1>Orden de Pedido</h1>
        </div>

        <!-- Sección de búsqueda de productos -->
        <div class="form-section">
            <h3>Buscar Productos</h3>
            <div class="form-row">
                <select id="categoria">
                    <option value="">Selecciona una categoría</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" id="busqueda" placeholder="Buscar por nombre de producto">
            </div>
        </div>

        <!-- Lista de productos -->
        <div id="productos-list" class="productos-grid">
            <p style="grid-column: 1 / -1; text-align: center; color: #86868b; font-style: italic;">
                Selecciona una categoría o busca un producto para comenzar
            </p>
        </div>

        <!-- Sección de producto personalizado -->
        <div class="personalizado-section">
            <h4>🎨 Agregar Producto Personalizado</h4>
            <div class="form-row">
                <input type="text" id="producto-personalizado" placeholder="Nombre del producto personalizado">
                <input type="number" id="precio-personalizado" placeholder="Precio" min="0" step="1000">
            </div>
            <div class="form-row">
                <input type="text" id="talla-personalizada" placeholder="Talla (opcional)">
                <input type="number" id="cantidad-personalizada" placeholder="Cantidad" min="1" value="1">
                <button class="btn" onclick="agregarPersonalizado()">Agregar al carrito</button>
            </div>
        </div>

        <!-- Carrito de compras -->
        <div class="carrito-section" id="carrito-section" style="display: none;">
            <h3>🛒 Carrito de Compras</h3>
            <div id="carrito-items"></div>
            <div class="total-section">
                <div class="total-precio">Total: $<span id="total-carrito">0</span></div>
                <button class="btn btn-finalizar" onclick="finalizarPedido()">
                    Continuar con datos de envío
                </button>
            </div>
        </div>
    </div>
<script>
let carrito = [];

document.getElementById('categoria').addEventListener('change', cargarProductos);
document.getElementById('busqueda').addEventListener('input', cargarProductos);

function cargarProductos() {
    const categoria = document.getElementById('categoria').value;
    const busqueda = document.getElementById('busqueda').value.trim();
    const productosList = document.getElementById('productos-list');

    if (!categoria && !busqueda) {
        productosList.innerHTML = '<p style="grid-column: 1 / -1; text-align: center; color: #86868b; font-style: italic;">Selecciona una categoría o busca un producto para comenzar</p>';
        return;
    }

    // Mostrar mensaje de carga
    productosList.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; color: #86868b; padding: 20px;">Cargando productos...</div>';

    // Llamada AJAX para obtener los productos
    fetch(`productos_por_categoria.php?cat=${encodeURIComponent(categoria)}&search=${encodeURIComponent(busqueda)}`)
        .then(response => response.json())
        .then(data => {
            if (!data.productos || data.productos.length === 0) {
                productosList.innerHTML = '<p style="grid-column: 1 / -1; text-align: center; color: #86868b;">No se encontraron productos.</p>';
                return;
            }

            mostrarProductos(data.productos);
        })
        .catch(error => {
            console.error('Error al cargar los productos:', error);
            productosList.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; color: #ff3b30; padding: 20px;">Error al cargar los productos. Intenta nuevamente.</div>';
        });
}

function mostrarProductos(productos) {
    const productosList = document.getElementById('productos-list');
    let html = '';

    productos.forEach(producto => {
        // Obtener tallas disponibles (asumiendo que las tallas están en el campo 'tallas' o generar tallas comunes)
        const tallasDisponibles = producto.tallas ? producto.tallas.split(',') : ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
        
        html += `
            <div class="producto-card">
                <h4>${producto.nombre}</h4>
                <div class="producto-precio">$${Number(producto.precio).toLocaleString()}</div>
                
                <div class="tallas-container">
                    <label>Selecciona talla:</label>
                    <div class="tallas-grid">
                        ${tallasDisponibles.map(talla => 
                            `<div class="talla-btn" onclick="seleccionarTalla(${producto.id}, '${talla.trim()}')" data-producto="${producto.id}" data-talla="${talla.trim()}">${talla.trim()}</div>`
                        ).join('')}
                    </div>
                </div>
                
                <div class="cantidad-container">
                    <label>Cantidad:</label>
                    <input type="number" min="1" max="100" value="1" id="cantidad_${producto.id}">
                </div>
                
                <button class="btn" onclick="agregarAlCarrito(${producto.id}, '${producto.nombre}', ${producto.precio})">
                    Agregar al carrito
                </button>
            </div>
        `;
    });

    productosList.innerHTML = html;
}

function seleccionarTalla(productoId, talla) {
    // Deseleccionar todas las tallas de este producto
    const tallasProducto = document.querySelectorAll(`[data-producto="${productoId}"]`);
    tallasProducto.forEach(tallaBtn => tallaBtn.classList.remove('selected'));
    
    // Seleccionar la talla clickeada
    const tallaSeleccionada = document.querySelector(`[data-producto="${productoId}"][data-talla="${talla}"]`);
    tallaSeleccionada.classList.add('selected');
}

function agregarAlCarrito(id, nombre, precio) {
    // Verificar que hay una talla seleccionada
    const tallaSeleccionada = document.querySelector(`[data-producto="${id}"].selected`);
    if (!tallaSeleccionada) {
        alert('Por favor selecciona una talla antes de agregar al carrito');
        return;
    }
    
    const talla = tallaSeleccionada.dataset.talla;
    const cantidad = parseInt(document.getElementById(`cantidad_${id}`).value);
    
    if (isNaN(cantidad) || cantidad < 1) {
        alert('Por favor ingresa una cantidad válida');
        return;
    }

    const itemKey = `${id}_${talla}`;
    const index = carrito.findIndex(item => item.key === itemKey);
    
    if (index >= 0) {
        carrito[index].cantidad += cantidad;
    } else {
        carrito.push({ 
            key: itemKey,
            id, 
            nombre, 
            precio, 
            cantidad, 
            talla 
        });
    }
    
    actualizarCarrito();
    
    // Limpiar selección
    document.querySelector(`[data-producto="${id}"].selected`).classList.remove('selected');
    document.getElementById(`cantidad_${id}`).value = 1;
}

function agregarPersonalizado() {
    const nombre = document.getElementById('producto-personalizado').value.trim();
    const precio = parseInt(document.getElementById('precio-personalizado').value);
    const talla = document.getElementById('talla-personalizada').value.trim();
    const cantidad = parseInt(document.getElementById('cantidad-personalizada').value);
    
    if (!nombre) {
        alert('Por favor ingresa el nombre del producto');
        return;
    }
    
    if (isNaN(precio) || precio < 0) {
        alert('Por favor ingresa un precio válido');
        return;
    }
    
    if (isNaN(cantidad) || cantidad < 1) {
        alert('Por favor ingresa una cantidad válida');
        return;
    }
    
    const itemKey = `personalizado_${Date.now()}`;
    carrito.push({
        key: itemKey,
        id: 'personalizado',
        nombre: nombre,
        precio: precio,
        cantidad: cantidad,
        talla: talla || 'N/A'
    });
    
    actualizarCarrito();
    
    // Limpiar formulario
    document.getElementById('producto-personalizado').value = '';
    document.getElementById('precio-personalizado').value = '';
    document.getElementById('talla-personalizada').value = '';
    document.getElementById('cantidad-personalizada').value = 1;
}

function actualizarCarrito() {
    const carritoSection = document.getElementById('carrito-section');
    const carritoItems = document.getElementById('carrito-items');
    const totalElement = document.getElementById('total-carrito');
    
    if (carrito.length === 0) {
        carritoSection.style.display = 'none';
        return;
    }
    
    carritoSection.style.display = 'block';
    
    let html = '';
    let total = 0;
    
    carrito.forEach((item, index) => {
        const subtotal = item.precio * item.cantidad;
        total += subtotal;
        
        html += `
            <div class="carrito-item">
                <div class="item-info">
                    <h5>${item.nombre}</h5>
                    <p>Talla: ${item.talla} | Cantidad: ${item.cantidad}</p>
                </div>
                <div class="item-precio">$${Number(subtotal).toLocaleString()}</div>
                <button class="btn btn-danger" onclick="eliminarDelCarrito(${index})">Eliminar</button>
            </div>
        `;
    });
    
    carritoItems.innerHTML = html;
    totalElement.textContent = Number(total).toLocaleString();
}

function eliminarDelCarrito(index) {
    carrito.splice(index, 1);
    actualizarCarrito();
}

function finalizarPedido() {
    if (carrito.length === 0) {
        alert('El carrito está vacío');
        return;
    }
    
    // Crear texto del pedido
    let textoPedido = 'PEDIDO PERSONALIZADO:\n\n';
    let total = 0;
    
    carrito.forEach(item => {
        const subtotal = item.precio * item.cantidad;
        total += subtotal;
        textoPedido += `• ${item.nombre}\n`;
        textoPedido += `  Talla: ${item.talla}\n`;
        textoPedido += `  Cantidad: ${item.cantidad}\n`;
        textoPedido += `  Precio: $${Number(item.precio).toLocaleString()}\n`;
        textoPedido += `  Subtotal: $${Number(subtotal).toLocaleString()}\n\n`;
    });
    
    textoPedido += `TOTAL: $${Number(total).toLocaleString()}`;
    
    // Redirigir a index.php con el pedido
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'index.php';
    
    const pedidoInput = document.createElement('input');
    pedidoInput.type = 'hidden';
    pedidoInput.name = 'pedido_prefilled';
    pedidoInput.value = textoPedido;
    
    const montoInput = document.createElement('input');
    montoInput.type = 'hidden';
    montoInput.name = 'monto_prefilled';
    montoInput.value = total;
    
    form.appendChild(pedidoInput);
    form.appendChild(montoInput);
    document.body.appendChild(form);
    form.submit();
}
</script>
</body>
</html>
