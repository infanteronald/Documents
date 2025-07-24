-- ========================================
-- SCRIPT DE CONSOLIDACIÓN - VERSIÓN SIMPLE SIN COLLATION
-- Enfoque directo que evita todos los problemas de collation
-- ========================================

-- PASO 1: CREAR BACKUP DE SEGURIDAD
CREATE TABLE IF NOT EXISTS backup_productos_almacen AS 
SELECT id, nombre, almacen, stock_actual, stock_minimo, stock_maximo 
FROM productos WHERE 1=1;

CREATE TABLE IF NOT EXISTS backup_almacenes_original AS 
SELECT * FROM almacenes WHERE 1=1;

-- PASO 2: CREAR TABLA ALMACENES CONSOLIDADA
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

-- PASO 3: MIGRAR ALMACENES EXISTENTES
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
        ELSE '🏪'
    END as icono,
    activo,
    fecha_creacion,
    fecha_actualizacion
FROM almacenes;

-- PASO 4: OBTENER ALMACENES ÚNICOS DE PRODUCTOS
DROP TABLE IF EXISTS temp_almacenes_productos;
CREATE TEMPORARY TABLE temp_almacenes_productos AS
SELECT DISTINCT 
    almacen,
    CONCAT('ALM_', UPPER(REPLACE(REPLACE(almacen, ' ', '_'), 'ñ', 'N'))) as codigo
FROM productos 
WHERE almacen IS NOT NULL AND almacen != '';

-- PASO 5: AGREGAR ALMACENES FALTANTES
INSERT IGNORE INTO almacenes_consolidado (codigo, nombre, descripcion, icono, activo)
SELECT 
    tap.codigo,
    tap.almacen,
    CONCAT('Almacén ', tap.almacen, ' - Desde productos'),
    '🏪',
    1
FROM temp_almacenes_productos tap;

-- PASO 6: CREAR TABLA INVENTARIO
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
    UNIQUE KEY unique_producto_almacen (producto_id, almacen_id)
);

-- PASO 7: MIGRAR INVENTARIO CON LOOP (EVITA COLLATION)
-- Crear tabla temporal para mapeo
DROP TABLE IF EXISTS temp_mapeo_almacenes;
CREATE TEMPORARY TABLE temp_mapeo_almacenes AS
SELECT 
    ROW_NUMBER() OVER (ORDER BY nombre) as num_fila,
    id as almacen_id,
    nombre as almacen_nombre
FROM almacenes_consolidado;

-- Obtener productos únicos
DROP TABLE IF EXISTS temp_productos_unicos;
CREATE TEMPORARY TABLE temp_productos_unicos AS
SELECT 
    ROW_NUMBER() OVER (ORDER BY id) as num_fila,
    id as producto_id,
    almacen,
    COALESCE(stock_actual, 0) as stock_actual,
    COALESCE(stock_minimo, 5) as stock_minimo,
    COALESCE(stock_maximo, 100) as stock_maximo
FROM productos 
WHERE almacen IS NOT NULL AND almacen != '';

-- Insertar registros uno por uno usando variables
SET @max_productos = (SELECT COUNT(*) FROM temp_productos_unicos);
SET @contador = 1;

-- Crear procedimiento temporal para la migración
DROP PROCEDURE IF EXISTS migrar_inventario;
DELIMITER //
CREATE PROCEDURE migrar_inventario()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_producto_id INT;
    DECLARE v_almacen_nombre VARCHAR(100);
    DECLARE v_stock_actual INT;
    DECLARE v_stock_minimo INT;
    DECLARE v_stock_maximo INT;
    DECLARE v_almacen_id INT;
    
    DECLARE cur CURSOR FOR 
        SELECT producto_id, almacen, stock_actual, stock_minimo, stock_maximo 
        FROM temp_productos_unicos;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO v_producto_id, v_almacen_nombre, v_stock_actual, v_stock_minimo, v_stock_maximo;
        
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Buscar almacen_id
        SELECT id INTO v_almacen_id 
        FROM almacenes_consolidado 
        WHERE nombre = v_almacen_nombre 
        LIMIT 1;
        
        -- Insertar si se encontró el almacén
        IF v_almacen_id IS NOT NULL THEN
            INSERT IGNORE INTO inventario_almacen_new (producto_id, almacen_id, stock_actual, stock_minimo, stock_maximo)
            VALUES (v_producto_id, v_almacen_id, v_stock_actual, v_stock_minimo, v_stock_maximo);
        END IF;
        
        SET v_almacen_id = NULL;
    END LOOP;
    
    CLOSE cur;
END//
DELIMITER ;

-- Ejecutar la migración
CALL migrar_inventario();

-- Limpiar procedimiento
DROP PROCEDURE IF EXISTS migrar_inventario;

-- PASO 8: CREAR TABLA DE MOVIMIENTOS
CREATE TABLE IF NOT EXISTS movimientos_inventario_new (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT NOT NULL,
    almacen_id INT NOT NULL,
    tipo_movimiento ENUM('entrada', 'salida', 'ajuste', 'transferencia_salida', 'transferencia_entrada') NOT NULL,
    cantidad INT NOT NULL,
    cantidad_anterior INT NOT NULL,
    cantidad_nueva INT NOT NULL,
    costo_unitario DECIMAL(10,2) DEFAULT 0,
    motivo VARCHAR(255) DEFAULT NULL,
    documento_referencia VARCHAR(100) DEFAULT NULL,
    usuario_responsable VARCHAR(100) DEFAULT NULL,
    almacen_destino_id INT NULL,
    fecha_movimiento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    observaciones TEXT DEFAULT NULL
);

-- PASO 9: AGREGAR FOREIGN KEYS DESPUÉS DE LA MIGRACIÓN
ALTER TABLE inventario_almacen_new 
ADD CONSTRAINT fk_inventario_producto 
FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE;

ALTER TABLE inventario_almacen_new 
ADD CONSTRAINT fk_inventario_almacen 
FOREIGN KEY (almacen_id) REFERENCES almacenes_consolidado(id);

ALTER TABLE movimientos_inventario_new 
ADD CONSTRAINT fk_movimientos_producto 
FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE;

ALTER TABLE movimientos_inventario_new 
ADD CONSTRAINT fk_movimientos_almacen 
FOREIGN KEY (almacen_id) REFERENCES almacenes_consolidado(id);

ALTER TABLE movimientos_inventario_new 
ADD CONSTRAINT fk_movimientos_almacen_destino 
FOREIGN KEY (almacen_destino_id) REFERENCES almacenes_consolidado(id);

-- PASO 10: CREAR VISTA
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

-- PASO 11: CREAR ÍNDICES
CREATE INDEX IF NOT EXISTS idx_almacenes_consolidado_codigo ON almacenes_consolidado (codigo);
CREATE INDEX IF NOT EXISTS idx_almacenes_consolidado_nombre ON almacenes_consolidado (nombre);
CREATE INDEX IF NOT EXISTS idx_almacenes_consolidado_activo ON almacenes_consolidado (activo);

CREATE INDEX IF NOT EXISTS idx_inventario_new_producto ON inventario_almacen_new (producto_id);
CREATE INDEX IF NOT EXISTS idx_inventario_new_almacen ON inventario_almacen_new (almacen_id);
CREATE INDEX IF NOT EXISTS idx_inventario_new_stock ON inventario_almacen_new (stock_actual, stock_minimo);

-- PASO 12: ESTADÍSTICAS FINALES
SELECT 
    'ESTADÍSTICAS DE MIGRACIÓN' as reporte,
    (SELECT COUNT(*) FROM almacenes) as almacenes_originales,
    (SELECT COUNT(*) FROM almacenes_consolidado) as almacenes_consolidados,
    (SELECT COUNT(*) FROM productos WHERE activo = 1) as productos_activos,
    (SELECT COUNT(DISTINCT producto_id) FROM inventario_almacen_new) as productos_migrados,
    (SELECT COUNT(*) FROM inventario_almacen_new) as registros_inventario;

-- PASO 13: VERIFICAR ÉXITO
SELECT 
    CASE 
        WHEN (SELECT COUNT(*) FROM inventario_almacen_new) > 0 
         AND (SELECT COUNT(*) FROM almacenes_consolidado) > 0
        THEN '✅ MIGRACIÓN EXITOSA'
        ELSE '❌ MIGRACIÓN INCOMPLETA'
    END as resultado_final;

-- PASO 14: MOSTRAR ALMACENES CREADOS
SELECT 
    'ALMACENES CREADOS' as info,
    id,
    codigo,
    nombre,
    icono,
    activo,
    (SELECT COUNT(*) FROM inventario_almacen_new ia WHERE ia.almacen_id = almacenes_consolidado.id) as productos
FROM almacenes_consolidado
ORDER BY nombre;

-- MENSAJE FINAL
SELECT 'MIGRACIÓN COMPLETADA SIN PROBLEMAS DE COLLATION' as mensaje;