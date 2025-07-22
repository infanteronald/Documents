-- ========================================
-- SISTEMA DE GESTIÓN DE PRODUCTOS
-- Actualizar tabla productos existente con campos necesarios
-- ========================================

-- Crear tabla de almacenes/bodegas
CREATE TABLE IF NOT EXISTS almacenes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    direccion TEXT,
    telefono VARCHAR(20),
    encargado VARCHAR(100),
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insertar almacenes predefinidos
INSERT INTO almacenes (codigo, nombre, direccion, encargado) VALUES 
('FABRICA', 'Fábrica', 'Dirección de la fábrica', 'Supervisor de producción'),
('TIENDA_BOG', 'Tienda Bogotá', 'Dirección tienda Bogotá', 'Gerente Bogotá'),
('TIENDA_MED', 'Tienda Medellín', 'Dirección tienda Medellín', 'Gerente Medellín'),
('BODEGA_1', 'Bodega 1', 'Dirección bodega 1', 'Bodeguero 1'),
('BODEGA_2', 'Bodega 2', 'Dirección bodega 2', 'Bodeguero 2'),
('BODEGA_3', 'Bodega 3', 'Dirección bodega 3', 'Bodeguero 3')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- Crear tabla de inventario por almacén
CREATE TABLE IF NOT EXISTS inventario_almacen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT NOT NULL,
    almacen_id INT NOT NULL,
    stock_actual INT DEFAULT 0,
    stock_minimo INT DEFAULT 5,
    stock_maximo INT DEFAULT 100,
    ubicacion_fisica VARCHAR(100),
    fecha_ultima_entrada TIMESTAMP NULL,
    fecha_ultima_salida TIMESTAMP NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    FOREIGN KEY (almacen_id) REFERENCES almacenes(id),
    UNIQUE KEY unique_producto_almacen (producto_id, almacen_id)
);

-- Agregar campos que faltan para el sistema de inventario
ALTER TABLE productos 
ADD COLUMN IF NOT EXISTS stock_minimo INT NOT NULL DEFAULT 0 AFTER stock,
ADD COLUMN IF NOT EXISTS stock_maximo INT NOT NULL DEFAULT 100 AFTER stock_minimo,
ADD COLUMN IF NOT EXISTS almacen VARCHAR(100) DEFAULT 'Tienda Bogotá' AFTER stock_maximo,
ADD COLUMN IF NOT EXISTS fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER fecha_creacion;

-- Renombrar campo stock a stock_actual para mayor claridad
ALTER TABLE productos 
CHANGE COLUMN stock stock_actual INT DEFAULT 0;

-- Renombrar imagen_url a imagen para consistencia
ALTER TABLE productos 
CHANGE COLUMN imagen_url imagen VARCHAR(255) DEFAULT NULL;

-- Crear índices para mejorar rendimiento
CREATE INDEX IF NOT EXISTS idx_nombre ON productos (nombre);
CREATE INDEX IF NOT EXISTS idx_categoria ON productos (categoria);
CREATE INDEX IF NOT EXISTS idx_activo ON productos (activo);
CREATE INDEX IF NOT EXISTS idx_stock_minimo ON productos (stock_actual, stock_minimo);
CREATE INDEX IF NOT EXISTS idx_fecha_creacion ON productos (fecha_creacion);

-- Actualizar productos existentes con valores por defecto
UPDATE productos 
SET stock_minimo = 5 
WHERE stock_minimo = 0;

UPDATE productos 
SET stock_maximo = GREATEST(stock_actual * 2, 50) 
WHERE stock_maximo = 100;

UPDATE productos 
SET almacen = 'Tienda Bogotá' 
WHERE almacen IS NULL OR almacen = 'Principal';

-- Migrar datos existentes a inventario por almacén
INSERT INTO inventario_almacen (producto_id, almacen_id, stock_actual, stock_minimo, stock_maximo)
SELECT 
    p.id,
    a.id,
    CASE 
        WHEN p.almacen COLLATE utf8mb4_unicode_ci = a.nombre COLLATE utf8mb4_unicode_ci THEN p.stock_actual
        ELSE 0
    END,
    p.stock_minimo,
    p.stock_maximo
FROM productos p
CROSS JOIN almacenes a
WHERE a.activo = 1
ON DUPLICATE KEY UPDATE 
    stock_actual = VALUES(stock_actual),
    stock_minimo = VALUES(stock_minimo),
    stock_maximo = VALUES(stock_maximo);

-- Crear vista para productos con alertas de stock por almacén
CREATE VIEW vista_inventario_completo AS
SELECT 
    p.id,
    p.nombre,
    p.descripcion,
    p.categoria,
    p.precio,
    p.sku,
    p.imagen,
    p.activo,
    p.fecha_creacion,
    p.fecha_actualizacion,
    a.id as almacen_id,
    a.codigo as almacen_codigo,
    a.nombre as almacen_nombre,
    ia.stock_actual,
    ia.stock_minimo,
    ia.stock_maximo,
    ia.ubicacion_fisica,
    ia.fecha_ultima_entrada,
    ia.fecha_ultima_salida,
    CASE 
        WHEN ia.stock_actual <= ia.stock_minimo THEN 'bajo'
        WHEN ia.stock_actual <= (ia.stock_minimo + (ia.stock_maximo - ia.stock_minimo) * 0.3) THEN 'medio'
        ELSE 'alto'
    END as nivel_stock,
    CASE 
        WHEN ia.stock_actual <= ia.stock_minimo THEN '🔴'
        WHEN ia.stock_actual <= (ia.stock_minimo + (ia.stock_maximo - ia.stock_minimo) * 0.3) THEN '🟡'
        ELSE '🟢'
    END as icono_stock
FROM productos p
INNER JOIN inventario_almacen ia ON p.id = ia.producto_id
INNER JOIN almacenes a ON ia.almacen_id = a.id
WHERE a.activo = 1;

-- Crear tabla de movimientos de inventario
CREATE TABLE IF NOT EXISTS movimientos_inventario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT NOT NULL,
    almacen_id INT NOT NULL,
    tipo_movimiento ENUM('entrada', 'salida', 'ajuste', 'transferencia_salida', 'transferencia_entrada') NOT NULL,
    cantidad INT NOT NULL,
    cantidad_anterior INT NOT NULL,
    cantidad_nueva INT NOT NULL,
    costo_unitario DECIMAL(10,2) DEFAULT 0,
    motivo VARCHAR(255),
    documento_referencia VARCHAR(100),
    usuario_responsable VARCHAR(100),
    almacen_destino_id INT NULL, -- Para transferencias
    fecha_movimiento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    observaciones TEXT,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    FOREIGN KEY (almacen_id) REFERENCES almacenes(id),
    FOREIGN KEY (almacen_destino_id) REFERENCES almacenes(id)
);

-- Verificar si la tabla usuarios necesita campos adicionales
-- Si ya existe con estructura diferente, solo agregamos campos faltantes
ALTER TABLE usuarios 
ADD COLUMN IF NOT EXISTS activo TINYINT(1) DEFAULT 1,
ADD COLUMN IF NOT EXISTS fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Insertar usuario admin por defecto (sin campo rol)
INSERT INTO usuarios (nombre, email) VALUES 
('Administrador', 'admin@sequoia.com')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- Crear tabla de tipos de movimiento para referencia
CREATE TABLE IF NOT EXISTS tipos_movimiento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) UNIQUE NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    afecta_stock ENUM('suma', 'resta', 'neutro') NOT NULL,
    requiere_documento TINYINT(1) DEFAULT 0,
    activo TINYINT(1) DEFAULT 1
);

-- Insertar tipos de movimiento básicos
INSERT INTO tipos_movimiento (codigo, nombre, descripcion, afecta_stock, requiere_documento) VALUES
('ENTRADA_COMPRA', 'Entrada por Compra', 'Ingreso de productos por compra a proveedores', 'suma', 1),
('ENTRADA_DEVOLUCION', 'Entrada por Devolución', 'Ingreso de productos por devolución de clientes', 'suma', 1),
('SALIDA_VENTA', 'Salida por Venta', 'Salida de productos por venta a clientes', 'resta', 1),
('SALIDA_DESPERDICIO', 'Salida por Desperdicio', 'Salida de productos por daño o vencimiento', 'resta', 0),
('AJUSTE_POSITIVO', 'Ajuste Positivo', 'Ajuste de inventario - incremento', 'suma', 0),
('AJUSTE_NEGATIVO', 'Ajuste Negativo', 'Ajuste de inventario - decremento', 'resta', 0),
('TRANSFERENCIA', 'Transferencia entre Almacenes', 'Movimiento de productos entre almacenes', 'neutro', 0)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- Crear índices para mejorar rendimiento
CREATE INDEX IF NOT EXISTS idx_inventario_producto ON inventario_almacen (producto_id);
CREATE INDEX IF NOT EXISTS idx_inventario_almacen ON inventario_almacen (almacen_id);
CREATE INDEX IF NOT EXISTS idx_inventario_stock ON inventario_almacen (stock_actual, stock_minimo);
CREATE INDEX IF NOT EXISTS idx_almacenes_codigo ON almacenes (codigo);
CREATE INDEX IF NOT EXISTS idx_almacenes_activo ON almacenes (activo);

-- Índices para movimientos de inventario
CREATE INDEX IF NOT EXISTS idx_movimientos_producto ON movimientos_inventario (producto_id);
CREATE INDEX IF NOT EXISTS idx_movimientos_almacen ON movimientos_inventario (almacen_id);
CREATE INDEX IF NOT EXISTS idx_movimientos_fecha ON movimientos_inventario (fecha_movimiento);
CREATE INDEX IF NOT EXISTS idx_movimientos_tipo ON movimientos_inventario (tipo_movimiento);

-- Índices para usuarios
CREATE INDEX IF NOT EXISTS idx_usuarios_email ON usuarios (email);
CREATE INDEX IF NOT EXISTS idx_usuarios_activo ON usuarios (activo);

-- Crear tabla de alertas
CREATE TABLE IF NOT EXISTS alertas_inventario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_alerta ENUM('stock_bajo', 'stock_critico', 'stock_alto', 'sin_movimiento', 'vencimiento') NOT NULL,
    producto_id INT NOT NULL,
    almacen_id INT NOT NULL,
    mensaje TEXT NOT NULL,
    nivel_prioridad ENUM('baja', 'media', 'alta', 'critica') DEFAULT 'media',
    estado ENUM('pendiente', 'vista', 'resuelta', 'ignorada') DEFAULT 'pendiente',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_resolucion TIMESTAMP NULL,
    usuario_resolucion INT NULL,
    datos_adicionales JSON NULL,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    FOREIGN KEY (almacen_id) REFERENCES almacenes(id),
    FOREIGN KEY (usuario_resolucion) REFERENCES usuarios(id)
);

-- Crear tabla de configuración de alertas
CREATE TABLE IF NOT EXISTS configuracion_alertas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_alerta ENUM('stock_bajo', 'stock_critico', 'stock_alto', 'sin_movimiento', 'vencimiento') NOT NULL,
    habilitada TINYINT(1) DEFAULT 1,
    parametros JSON NOT NULL,
    email_habilitado TINYINT(1) DEFAULT 1,
    emails_destino TEXT,
    frecuencia_verificacion INT DEFAULT 60, -- minutos
    ultima_verificacion TIMESTAMP NULL,
    activa TINYINT(1) DEFAULT 1,
    UNIQUE KEY unique_tipo_alerta (tipo_alerta)
);

-- Insertar configuración por defecto de alertas
INSERT INTO configuracion_alertas (tipo_alerta, parametros, emails_destino) VALUES
('stock_bajo', '{"umbral_porcentaje": 20, "incluir_inactivos": false}', 'admin@sequoia.com'),
('stock_critico', '{"umbral_cantidad": 0, "incluir_inactivos": false}', 'admin@sequoia.com'),
('stock_alto', '{"umbral_porcentaje": 90, "incluir_inactivos": false}', 'admin@sequoia.com'),
('sin_movimiento', '{"dias_sin_movimiento": 30, "incluir_inactivos": false}', 'admin@sequoia.com'),
('vencimiento', '{"dias_antes_vencimiento": 7, "incluir_inactivos": false}', 'admin@sequoia.com')
ON DUPLICATE KEY UPDATE parametros = VALUES(parametros);

-- Crear tabla de historial de notificaciones
CREATE TABLE IF NOT EXISTS historial_notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alerta_id INT NOT NULL,
    tipo_notificacion ENUM('email', 'sms', 'push', 'sistema') NOT NULL,
    destinatario VARCHAR(255) NOT NULL,
    asunto VARCHAR(255),
    mensaje TEXT,
    estado ENUM('pendiente', 'enviada', 'fallida', 'rebotada') DEFAULT 'pendiente',
    fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_entrega TIMESTAMP NULL,
    error_mensaje TEXT,
    intentos INT DEFAULT 0,
    FOREIGN KEY (alerta_id) REFERENCES alertas_inventario(id) ON DELETE CASCADE
);

-- Crear tabla de suscripciones a alertas
CREATE TABLE IF NOT EXISTS suscripciones_alertas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo_alerta ENUM('stock_bajo', 'stock_critico', 'stock_alto', 'sin_movimiento', 'vencimiento') NOT NULL,
    almacen_id INT NULL, -- NULL = todos los almacenes
    categoria VARCHAR(100) NULL, -- NULL = todas las categorías
    email_habilitado TINYINT(1) DEFAULT 1,
    sms_habilitado TINYINT(1) DEFAULT 0,
    push_habilitado TINYINT(1) DEFAULT 1,
    activa TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (almacen_id) REFERENCES almacenes(id),
    UNIQUE KEY unique_suscripcion (usuario_id, tipo_alerta, almacen_id, categoria)
);

-- Insertar suscripción por defecto para admin
INSERT INTO suscripciones_alertas (usuario_id, tipo_alerta, almacen_id, categoria) VALUES
(1, 'stock_bajo', NULL, NULL),
(1, 'stock_critico', NULL, NULL)
ON DUPLICATE KEY UPDATE activa = 1;

-- Crear índices para alertas
CREATE INDEX IF NOT EXISTS idx_alertas_tipo ON alertas_inventario (tipo_alerta);
CREATE INDEX IF NOT EXISTS idx_alertas_producto ON alertas_inventario (producto_id);
CREATE INDEX IF NOT EXISTS idx_alertas_almacen ON alertas_inventario (almacen_id);
CREATE INDEX IF NOT EXISTS idx_alertas_estado ON alertas_inventario (estado);
CREATE INDEX IF NOT EXISTS idx_alertas_fecha ON alertas_inventario (fecha_creacion);
CREATE INDEX IF NOT EXISTS idx_alertas_prioridad ON alertas_inventario (nivel_prioridad);

-- Índices para historial de notificaciones
CREATE INDEX IF NOT EXISTS idx_notificaciones_alerta ON historial_notificaciones (alerta_id);
CREATE INDEX IF NOT EXISTS idx_notificaciones_tipo ON historial_notificaciones (tipo_notificacion);
CREATE INDEX IF NOT EXISTS idx_notificaciones_estado ON historial_notificaciones (estado);
CREATE INDEX IF NOT EXISTS idx_notificaciones_fecha ON historial_notificaciones (fecha_envio);

-- Índices para suscripciones
CREATE INDEX IF NOT EXISTS idx_suscripciones_usuario ON suscripciones_alertas (usuario_id);
CREATE INDEX IF NOT EXISTS idx_suscripciones_tipo ON suscripciones_alertas (tipo_alerta);
CREATE INDEX IF NOT EXISTS idx_suscripciones_almacen ON suscripciones_alertas (almacen_id);
CREATE INDEX IF NOT EXISTS idx_suscripciones_activa ON suscripciones_alertas (activa);