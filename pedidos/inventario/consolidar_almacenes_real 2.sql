-- ========================================
-- SCRIPT DE CONSOLIDACIÓN DE SISTEMA DE ALMACENES (ESTRUCTURA REAL)
-- Migración específica para la estructura actual de almacenes
-- ========================================

-- PASO 1: CREAR BACKUP DE SEGURIDAD
CREATE TABLE IF NOT EXISTS backup_productos_almacen AS 
SELECT id, nombre, almacen, stock_actual, stock_minimo, stock_maximo 
FROM productos WHERE 1=1;

CREATE TABLE IF NOT EXISTS backup_almacenes_original AS 
SELECT * FROM almacenes WHERE 1=1;

-- PASO 2: CREAR TABLA ALMACENES CONSOLIDADA CON CAMPOS ADICIONALES
CREATE TABLE IF NOT EXISTS almacenes_consolidado (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT DEFAULT NULL,
    direccion TEXT DEFAULT NULL,
    ubicacion VARCHAR(255) DEFAULT NULL,
    telefono VARCHAR(20) DEFAULT NULL,
    capacidad_maxima INT DEFAULT 0,
    encargado VARCHAR(100) DEFAULT NULL,
    icono VARCHAR(10) DEFAULT '🏪',
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- PASO 3: MIGRAR DATOS DE ALMACENES EXISTENTES
-- Usar solo las columnas que existen en la tabla actual
INSERT INTO almacenes_consolidado (codigo, nombre, descripcion, direccion, telefono, encargado, icono, activo, fecha_creacion, fecha_actualizacion)
SELECT 
    codigo,
    nombre,
    CONCAT('Almacén ', nombre, ' - Migrado del sistema anterior') as descripcion,
    direccion,
    telefono,
    encargado,
    CASE 
        WHEN codigo = 'FABRICA' THEN '🏭'
        WHEN codigo = 'TIENDA_BOG' THEN '🏬'
        WHEN codigo = 'TIENDA_MED' THEN '🏪'
        WHEN codigo LIKE 'BODEGA_%' THEN '📦'
        WHEN codigo LIKE '%BOG%' THEN '🏬'
        WHEN codigo LIKE '%MED%' THEN '🏪'
        WHEN codigo LIKE '%FABRIC%' THEN '🏭'
        ELSE '🏪'
    END as icono,
    activo,
    fecha_creacion,
    fecha_actualizacion
FROM almacenes
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    direccion = VALUES(direccion),
    telefono = VALUES(telefono),
    encargado = VALUES(encargado),
    icono = VALUES(icono);

-- PASO 4: AGREGAR ALMACENES DESDE PRODUCTOS QUE NO EXISTEN
-- Identificar almacenes únicos en productos que no están en la tabla almacenes
INSERT IGNORE INTO almacenes_consolidado (codigo, nombre, descripcion, icono, activo)
SELECT DISTINCT
    CONCAT('ALM_', UPPER(REPLACE(REPLACE(REPLACE(p.almacen, ' ', '_'), 'ñ', 'N'), 'á', 'a'))) as codigo,
    p.almacen as nombre,
    CONCAT('Almacén ', p.almacen, ' - Migrado automáticamente desde productos') as descripcion,
    CASE 
        WHEN LOWER(p.almacen) LIKE '%fábrica%' OR LOWER(p.almacen) LIKE '%fabrica%' THEN '🏭'
        WHEN LOWER(p.almacen) LIKE '%tienda%' OR LOWER(p.almacen) LIKE '%bogotá%' OR LOWER(p.almacen) LIKE '%bogota%' THEN '🏬'
        WHEN LOWER(p.almacen) LIKE '%medellín%' OR LOWER(p.almacen) LIKE '%medellin%' THEN '🏪'
        WHEN LOWER(p.almacen) LIKE '%bodega%' THEN '📦'
        WHEN LOWER(p.almacen) LIKE '%principal%' THEN '🏪'
        WHEN LOWER(p.almacen) LIKE '%centro%' THEN '🏪'
        ELSE '🏪'
    END as icono,
    1 as activo
FROM productos p
WHERE p.almacen IS NOT NULL 
  AND p.almacen != ''
  AND NOT EXISTS (
    SELECT 1 FROM almacenes_consolidado ac 
    WHERE ac.nombre COLLATE utf8mb4_unicode_ci = p.almacen COLLATE utf8mb4_unicode_ci
  );

-- PASO 5: CREAR TABLA INVENTARIO_ALMACEN
CREATE TABLE IF NOT EXISTS inventario_almacen_new (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT NOT NULL,
    almacen_id INT NOT NULL,
    stock_actual INT DEFAULT 0,
    stock_minimo INT DEFAULT 5,
    stock_maximo INT DEFAULT 100,
    ubicacion_fisica VARCHAR(100) DEFAULT NULL,
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
INSERT IGNORE INTO inventario_almacen_new (producto_id, almacen_id, stock_actual, stock_minimo, stock_maximo)
SELECT 
    p.id as producto_id,
    ac.id as almacen_id,
    COALESCE(p.stock_actual, 0) as stock_actual,
    COALESCE(p.stock_minimo, 5) as stock_minimo,
    COALESCE(p.stock_maximo, 100) as stock_maximo
FROM productos p
JOIN almacenes_consolidado ac ON p.almacen COLLATE utf8mb4_unicode_ci = ac.nombre COLLATE utf8mb4_unicode_ci
WHERE p.almacen IS NOT NULL AND p.almacen != '';

-- PASO 7: CREAR TABLA DE MOVIMIENTOS
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
    almacen_destino_id INT NULL,
    fecha_movimiento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    observaciones TEXT,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    FOREIGN KEY (almacen_id) REFERENCES almacenes_consolidado(id),
    FOREIGN KEY (almacen_destino_id) REFERENCES almacenes_consolidado(id)
);

-- PASO 8: CREAR VISTA PARA COMPATIBILIDAD
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
    a.descripcion as almacen_descripcion,
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

-- PASO 9: CREAR ÍNDICES PARA RENDIMIENTO
CREATE INDEX IF NOT EXISTS idx_almacenes_consolidado_codigo ON almacenes_consolidado (codigo);
CREATE INDEX IF NOT EXISTS idx_almacenes_consolidado_nombre ON almacenes_consolidado (nombre);
CREATE INDEX IF NOT EXISTS idx_almacenes_consolidado_activo ON almacenes_consolidado (activo);

CREATE INDEX IF NOT EXISTS idx_inventario_new_producto ON inventario_almacen_new (producto_id);
CREATE INDEX IF NOT EXISTS idx_inventario_new_almacen ON inventario_almacen_new (almacen_id);
CREATE INDEX IF NOT EXISTS idx_inventario_new_stock ON inventario_almacen_new (stock_actual, stock_minimo);

CREATE INDEX IF NOT EXISTS idx_movimientos_new_producto ON movimientos_inventario_new (producto_id);
CREATE INDEX IF NOT EXISTS idx_movimientos_new_almacen ON movimientos_inventario_new (almacen_id);
CREATE INDEX IF NOT EXISTS idx_movimientos_new_fecha ON movimientos_inventario_new (fecha_movimiento);

-- PASO 10: VALIDACIONES Y ESTADÍSTICAS
SELECT 
    'VALIDACIÓN DE MIGRACIÓN' as reporte,
    (SELECT COUNT(*) FROM almacenes) as almacenes_originales,
    (SELECT COUNT(*) FROM almacenes_consolidado) as almacenes_consolidados,
    (SELECT COUNT(*) FROM productos WHERE activo = 1) as productos_activos,
    (SELECT COUNT(DISTINCT producto_id) FROM inventario_almacen_new) as productos_migrados,
    (SELECT COUNT(*) FROM inventario_almacen_new) as registros_inventario,
    (SELECT COUNT(DISTINCT almacen) FROM productos WHERE almacen IS NOT NULL) as almacenes_en_productos;

-- PASO 11: MOSTRAR ALMACENES MIGRADOS
SELECT 
    'ALMACENES MIGRADOS' as info,
    id,
    codigo,
    nombre,
    descripcion,
    icono,
    activo,
    (SELECT COUNT(*) FROM inventario_almacen_new ia WHERE ia.almacen_id = almacenes_consolidado.id) as productos_asignados
FROM almacenes_consolidado
ORDER BY nombre;

-- PASO 12: MOSTRAR PRODUCTOS SIN ALMACÉN
SELECT 
    'PRODUCTOS SIN ALMACÉN' as info,
    COUNT(*) as total,
    GROUP_CONCAT(DISTINCT almacen) as almacenes_no_migrados
FROM productos p
WHERE p.almacen IS NOT NULL 
  AND p.almacen != ''
  AND NOT EXISTS (
    SELECT 1 FROM inventario_almacen_new ia 
    WHERE ia.producto_id = p.id
  );

-- MENSAJE FINAL
SELECT 'MIGRACIÓN COMPLETADA - REVISAR ESTADÍSTICAS ANTES DE CONTINUAR' as mensaje;

-- SCRIPT DE ROLLBACK (COMENTADO - SOLO PARA EMERGENCIAS)
/*
-- ROLLBACK EN CASO DE EMERGENCIA
DROP TABLE IF EXISTS almacenes_consolidado;
DROP TABLE IF EXISTS inventario_almacen_new;
DROP TABLE IF EXISTS movimientos_inventario_new;
DROP VIEW IF EXISTS vista_productos_almacen;

-- Restaurar desde backup si es necesario
*/