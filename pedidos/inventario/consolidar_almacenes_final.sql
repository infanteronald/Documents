-- ========================================
-- SCRIPT DE CONSOLIDACIÓN - SOLUCIÓN DEFINITIVA COLLATION
-- Maneja diferencias de collation entre tablas existentes
-- ========================================

-- PASO 1: CREAR BACKUP DE SEGURIDAD
CREATE TABLE IF NOT EXISTS backup_productos_almacen AS 
SELECT id, nombre, almacen, stock_actual, stock_minimo, stock_maximo 
FROM productos WHERE 1=1;

CREATE TABLE IF NOT EXISTS backup_almacenes_original AS 
SELECT * FROM almacenes WHERE 1=1;

-- PASO 2: VERIFICAR COLLATIONS EXISTENTES
SELECT 
    'COLLATIONS EXISTENTES' as info,
    TABLE_NAME,
    COLUMN_NAME,
    COLLATION_NAME
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME IN ('productos', 'almacenes')
  AND COLUMN_NAME IN ('almacen', 'nombre')
ORDER BY TABLE_NAME, COLUMN_NAME;

-- PASO 3: CREAR TABLA ALMACENES CONSOLIDADA CON COLLATION COMPATIBLE
-- Usar la misma collation que productos para evitar conflictos
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- PASO 4: MIGRAR DATOS DE ALMACENES EXISTENTES
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

-- PASO 5: CREAR TABLA TEMPORAL PARA ALMACENES ÚNICOS DE PRODUCTOS
CREATE TEMPORARY TABLE temp_almacenes_productos AS
SELECT DISTINCT 
    p.almacen,
    CONCAT('ALM_', UPPER(REPLACE(REPLACE(REPLACE(p.almacen, ' ', '_'), 'ñ', 'N'), 'á', 'a'))) as codigo,
    CASE 
        WHEN LOWER(p.almacen) LIKE '%fábrica%' OR LOWER(p.almacen) LIKE '%fabrica%' THEN '🏭'
        WHEN LOWER(p.almacen) LIKE '%tienda%' OR LOWER(p.almacen) LIKE '%bogotá%' OR LOWER(p.almacen) LIKE '%bogota%' THEN '🏬'
        WHEN LOWER(p.almacen) LIKE '%medellín%' OR LOWER(p.almacen) LIKE '%medellin%' THEN '🏪'
        WHEN LOWER(p.almacen) LIKE '%bodega%' THEN '📦'
        WHEN LOWER(p.almacen) LIKE '%principal%' THEN '🏪'
        WHEN LOWER(p.almacen) LIKE '%centro%' THEN '🏪'
        ELSE '🏪'
    END as icono
FROM productos p
WHERE p.almacen IS NOT NULL 
  AND p.almacen != '';

-- PASO 6: AGREGAR ALMACENES DESDE PRODUCTOS QUE NO EXISTEN
INSERT IGNORE INTO almacenes_consolidado (codigo, nombre, descripcion, icono, activo)
SELECT 
    tap.codigo,
    tap.almacen as nombre,
    CONCAT('Almacén ', tap.almacen, ' - Migrado automáticamente desde productos') as descripcion,
    tap.icono,
    1 as activo
FROM temp_almacenes_productos tap
WHERE tap.almacen NOT IN (
    SELECT nombre FROM almacenes_consolidado
);

-- PASO 7: CREAR TABLA INVENTARIO_ALMACEN CON COLLATION COMPATIBLE
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- PASO 8: CREAR TABLA TEMPORAL PARA MIGRACIÓN DE INVENTARIO
-- Primero crear la tabla temporal vacía
CREATE TEMPORARY TABLE temp_inventario_migracion (
    producto_id INT,
    almacen_id INT,
    stock_actual INT,
    stock_minimo INT,
    stock_maximo INT
);

-- Insertar datos usando subconsulta para evitar problemas de collation
INSERT INTO temp_inventario_migracion (producto_id, almacen_id, stock_actual, stock_minimo, stock_maximo)
SELECT 
    p.id as producto_id,
    (
        SELECT ac.id 
        FROM almacenes_consolidado ac 
        WHERE BINARY ac.nombre = BINARY p.almacen 
        LIMIT 1
    ) as almacen_id,
    COALESCE(p.stock_actual, 0) as stock_actual,
    COALESCE(p.stock_minimo, 5) as stock_minimo,
    COALESCE(p.stock_maximo, 100) as stock_maximo
FROM productos p
WHERE p.almacen IS NOT NULL 
  AND p.almacen != ''
  AND EXISTS (
    SELECT 1 FROM almacenes_consolidado ac 
    WHERE BINARY ac.nombre = BINARY p.almacen
  );

-- PASO 9: MIGRAR DATOS DE INVENTARIO
INSERT IGNORE INTO inventario_almacen_new (producto_id, almacen_id, stock_actual, stock_minimo, stock_maximo)
SELECT 
    producto_id,
    almacen_id,
    stock_actual,
    stock_minimo,
    stock_maximo
FROM temp_inventario_migracion;

-- PASO 10: CREAR TABLA DE MOVIMIENTOS
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
    observaciones TEXT DEFAULT NULL,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    FOREIGN KEY (almacen_id) REFERENCES almacenes_consolidado(id),
    FOREIGN KEY (almacen_destino_id) REFERENCES almacenes_consolidado(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- PASO 11: CREAR VISTA PARA COMPATIBILIDAD
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

-- PASO 12: CREAR ÍNDICES PARA RENDIMIENTO
CREATE INDEX IF NOT EXISTS idx_almacenes_consolidado_codigo ON almacenes_consolidado (codigo);
CREATE INDEX IF NOT EXISTS idx_almacenes_consolidado_nombre ON almacenes_consolidado (nombre);
CREATE INDEX IF NOT EXISTS idx_almacenes_consolidado_activo ON almacenes_consolidado (activo);

CREATE INDEX IF NOT EXISTS idx_inventario_new_producto ON inventario_almacen_new (producto_id);
CREATE INDEX IF NOT EXISTS idx_inventario_new_almacen ON inventario_almacen_new (almacen_id);
CREATE INDEX IF NOT EXISTS idx_inventario_new_stock ON inventario_almacen_new (stock_actual, stock_minimo);

CREATE INDEX IF NOT EXISTS idx_movimientos_new_producto ON movimientos_inventario_new (producto_id);
CREATE INDEX IF NOT EXISTS idx_movimientos_new_almacen ON movimientos_inventario_new (almacen_id);
CREATE INDEX IF NOT EXISTS idx_movimientos_new_fecha ON movimientos_inventario_new (fecha_movimiento);

-- PASO 13: VALIDACIONES Y ESTADÍSTICAS
SELECT 
    'VALIDACIÓN DE MIGRACIÓN' as reporte,
    (SELECT COUNT(*) FROM almacenes) as almacenes_originales,
    (SELECT COUNT(*) FROM almacenes_consolidado) as almacenes_consolidados,
    (SELECT COUNT(*) FROM productos WHERE activo = 1) as productos_activos,
    (SELECT COUNT(DISTINCT producto_id) FROM inventario_almacen_new) as productos_migrados,
    (SELECT COUNT(*) FROM inventario_almacen_new) as registros_inventario,
    (SELECT COUNT(DISTINCT almacen) FROM productos WHERE almacen IS NOT NULL) as almacenes_en_productos;

-- PASO 14: MOSTRAR ALMACENES MIGRADOS
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

-- PASO 15: VERIFICAR PRODUCTOS NO MIGRADOS
SELECT 
    'PRODUCTOS NO MIGRADOS' as info,
    COUNT(*) as total_no_migrados,
    GROUP_CONCAT(DISTINCT p.almacen) as almacenes_problema
FROM productos p
LEFT JOIN inventario_almacen_new ia ON p.id = ia.producto_id
WHERE p.almacen IS NOT NULL 
  AND p.almacen != ''
  AND ia.producto_id IS NULL;

-- PASO 16: MOSTRAR COLLATIONS FINALES
SELECT 
    'COLLATIONS FINALES' as info,
    TABLE_NAME,
    COLUMN_NAME,
    COLLATION_NAME
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME IN ('productos', 'almacenes_consolidado', 'inventario_almacen_new')
  AND COLUMN_NAME IN ('almacen', 'nombre')
ORDER BY TABLE_NAME, COLUMN_NAME;

-- PASO 17: VERIFICAR ÉXITO DE LA MIGRACIÓN
SELECT 
    CASE 
        WHEN (SELECT COUNT(*) FROM inventario_almacen_new) > 0 
         AND (SELECT COUNT(*) FROM almacenes_consolidado) > 0
        THEN '✅ MIGRACIÓN EXITOSA'
        ELSE '❌ MIGRACIÓN INCOMPLETA'
    END as resultado_final,
    (SELECT COUNT(*) FROM almacenes_consolidado) as almacenes_creados,
    (SELECT COUNT(*) FROM inventario_almacen_new) as registros_inventario_creados;

-- MENSAJE FINAL
SELECT 'MIGRACIÓN COMPLETADA - TODOS LOS PROBLEMAS DE COLLATION RESUELTOS' as mensaje;

-- SCRIPT DE ROLLBACK (COMENTADO - SOLO PARA EMERGENCIAS)
/*
-- ROLLBACK EN CASO DE EMERGENCIA
DROP TABLE IF EXISTS almacenes_consolidado;
DROP TABLE IF EXISTS inventario_almacen_new;
DROP TABLE IF EXISTS movimientos_inventario_new;
DROP VIEW IF EXISTS vista_productos_almacen;
*/