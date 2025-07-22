-- ========================================
-- SCRIPT DE CONSOLIDACIÓN DE SISTEMA DE ALMACENES
-- Migración de sistema híbrido a sistema unificado con FK
-- ========================================

-- PASO 1: CREAR BACKUP DE SEGURIDAD
CREATE TABLE IF NOT EXISTS backup_productos_almacen AS 
SELECT id, nombre, almacen, stock_actual, stock_minimo, stock_maximo 
FROM productos WHERE 1=1;

CREATE TABLE IF NOT EXISTS backup_almacenes_old AS 
SELECT * FROM almacenes WHERE 1=1;

-- PASO 2: CONSOLIDAR TABLA ALMACENES
-- Crear tabla temporal con estructura unificada
CREATE TABLE IF NOT EXISTS almacenes_consolidado (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT,
    direccion TEXT,
    ubicacion VARCHAR(255), -- Ubicación específica dentro de la dirección
    telefono VARCHAR(20),
    capacidad_maxima INT DEFAULT 0,
    encargado VARCHAR(100),
    icono VARCHAR(10) DEFAULT '🏪',
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- PASO 3: MIGRAR DATOS DE ALMACENES EXISTENTES
-- Insertar almacenes consolidando ambas fuentes
INSERT INTO almacenes_consolidado (codigo, nombre, descripcion, direccion, telefono, capacidad_maxima, encargado, icono, activo)
SELECT 
    COALESCE(codigo, CONCAT('ALM_', id)) as codigo,
    nombre,
    COALESCE(descripcion, CONCAT('Almacén ', nombre)) as descripcion,
    COALESCE(direccion, ubicacion) as direccion,
    telefono,
    COALESCE(capacidad_maxima, 0) as capacidad_maxima,
    encargado,
    CASE 
        WHEN codigo = 'FABRICA' THEN '🏭'
        WHEN codigo = 'TIENDA_BOG' THEN '🏬'
        WHEN codigo = 'TIENDA_MED' THEN '🏪'
        WHEN codigo LIKE 'BODEGA_%' THEN '📦'
        ELSE '🏪'
    END as icono,
    activo
FROM almacenes
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    direccion = VALUES(direccion),
    capacidad_maxima = VALUES(capacidad_maxima),
    icono = VALUES(icono);

-- PASO 4: AGREGAR ALMACENES FALTANTES DESDE PRODUCTOS
-- Identificar almacenes únicos en productos que no están en la tabla almacenes
INSERT INTO almacenes_consolidado (codigo, nombre, descripcion, icono, activo)
SELECT DISTINCT
    CONCAT('ALM_', UPPER(REPLACE(p.almacen, ' ', '_'))) as codigo,
    p.almacen as nombre,
    CONCAT('Almacén ', p.almacen, ' - Migrado automáticamente') as descripcion,
    '🏪' as icono,
    1 as activo
FROM productos p
LEFT JOIN almacenes_consolidado a ON p.almacen = a.nombre
WHERE a.id IS NULL AND p.almacen IS NOT NULL AND p.almacen != ''
ON DUPLICATE KEY UPDATE
    descripcion = CONCAT('Almacén ', VALUES(nombre), ' - Migrado automáticamente');

-- PASO 5: CREAR TABLA INVENTARIO_ALMACEN SI NO EXISTE
CREATE TABLE IF NOT EXISTS inventario_almacen_new (
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
    FOREIGN KEY (almacen_id) REFERENCES almacenes_consolidado(id),
    UNIQUE KEY unique_producto_almacen (producto_id, almacen_id)
);

-- PASO 6: MIGRAR DATOS DE PRODUCTOS A INVENTARIO_ALMACEN
-- Migrar todos los productos existentes al nuevo sistema
INSERT INTO inventario_almacen_new (producto_id, almacen_id, stock_actual, stock_minimo, stock_maximo)
SELECT 
    p.id,
    a.id,
    COALESCE(p.stock_actual, 0),
    COALESCE(p.stock_minimo, 5),
    COALESCE(p.stock_maximo, 100)
FROM productos p
JOIN almacenes_consolidado a ON p.almacen = a.nombre
WHERE p.almacen IS NOT NULL AND p.almacen != ''
ON DUPLICATE KEY UPDATE
    stock_actual = VALUES(stock_actual),
    stock_minimo = VALUES(stock_minimo),
    stock_maximo = VALUES(stock_maximo);

-- PASO 7: CREAR TABLA DE MOVIMIENTOS SI NO EXISTE
CREATE TABLE IF NOT EXISTS movimientos_inventario_new (
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
    FOREIGN KEY (almacen_id) REFERENCES almacenes_consolidado(id),
    FOREIGN KEY (almacen_destino_id) REFERENCES almacenes_consolidado(id)
);

-- PASO 8: VALIDACIONES ANTES DE ELIMINAR CAMPO VARCHAR
-- Verificar que todos los productos tienen su almacén migrado
SELECT 
    COUNT(*) as total_productos,
    COUNT(CASE WHEN p.almacen IS NOT NULL THEN 1 END) as productos_con_almacen,
    COUNT(ia.producto_id) as productos_migrados,
    COUNT(*) - COUNT(ia.producto_id) as productos_faltantes
FROM productos p
LEFT JOIN inventario_almacen_new ia ON p.id = ia.producto_id
WHERE p.activo = 1;

-- PASO 9: CREAR VISTA PARA COMPATIBILIDAD
CREATE OR REPLACE VIEW vista_productos_almacen AS
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
    a.icono as almacen_icono,
    ia.stock_actual,
    ia.stock_minimo,
    ia.stock_maximo,
    ia.ubicacion_fisica,
    ia.fecha_ultima_entrada,
    ia.fecha_ultima_salida,
    CASE 
        WHEN ia.stock_actual = 0 THEN 'sin_stock'
        WHEN ia.stock_actual <= ia.stock_minimo THEN 'critico'
        WHEN ia.stock_actual <= (ia.stock_minimo * 1.5) THEN 'bajo'
        ELSE 'ok'
    END as estado_stock,
    CASE 
        WHEN ia.stock_actual = 0 THEN '🔴'
        WHEN ia.stock_actual <= ia.stock_minimo THEN '🔴'
        WHEN ia.stock_actual <= (ia.stock_minimo * 1.5) THEN '🟡'
        ELSE '🟢'
    END as icono_stock
FROM productos p
INNER JOIN inventario_almacen_new ia ON p.id = ia.producto_id
INNER JOIN almacenes_consolidado a ON ia.almacen_id = a.id
WHERE a.activo = 1;

-- PASO 10: CREAR ÍNDICES PARA RENDIMIENTO
CREATE INDEX IF NOT EXISTS idx_almacenes_codigo ON almacenes_consolidado (codigo);
CREATE INDEX IF NOT EXISTS idx_almacenes_nombre ON almacenes_consolidado (nombre);
CREATE INDEX IF NOT EXISTS idx_almacenes_activo ON almacenes_consolidado (activo);

CREATE INDEX IF NOT EXISTS idx_inventario_producto ON inventario_almacen_new (producto_id);
CREATE INDEX IF NOT EXISTS idx_inventario_almacen ON inventario_almacen_new (almacen_id);
CREATE INDEX IF NOT EXISTS idx_inventario_stock ON inventario_almacen_new (stock_actual, stock_minimo);

CREATE INDEX IF NOT EXISTS idx_movimientos_producto ON movimientos_inventario_new (producto_id);
CREATE INDEX IF NOT EXISTS idx_movimientos_almacen ON movimientos_inventario_new (almacen_id);
CREATE INDEX IF NOT EXISTS idx_movimientos_fecha ON movimientos_inventario_new (fecha_movimiento);

-- PASO 11: ESTADÍSTICAS DE MIGRACIÓN
SELECT 
    'ESTADÍSTICAS DE MIGRACIÓN' as reporte,
    (SELECT COUNT(*) FROM almacenes_consolidado) as almacenes_consolidados,
    (SELECT COUNT(*) FROM inventario_almacen_new) as registros_inventario,
    (SELECT COUNT(DISTINCT producto_id) FROM inventario_almacen_new) as productos_migrados,
    (SELECT COUNT(*) FROM productos WHERE activo = 1) as productos_activos_total;

-- PASO 12: SCRIPT DE ROLLBACK (COMENTADO - SOLO PARA EMERGENCIAS)
/*
-- ROLLBACK EN CASO DE EMERGENCIA
DROP TABLE IF EXISTS almacenes_consolidado;
DROP TABLE IF EXISTS inventario_almacen_new;
DROP TABLE IF EXISTS movimientos_inventario_new;
DROP VIEW IF EXISTS vista_productos_almacen;

-- Restaurar desde backup
INSERT INTO productos (id, nombre, almacen, stock_actual, stock_minimo, stock_maximo)
SELECT id, nombre, almacen, stock_actual, stock_minimo, stock_maximo
FROM backup_productos_almacen
ON DUPLICATE KEY UPDATE
    almacen = VALUES(almacen),
    stock_actual = VALUES(stock_actual);
*/

-- MENSAJE FINAL
SELECT 'MIGRACIÓN COMPLETADA - REVISAR ESTADÍSTICAS ANTES DE CONTINUAR' as mensaje;