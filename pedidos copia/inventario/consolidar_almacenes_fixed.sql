-- ========================================
-- SCRIPT DE CONSOLIDACIÓN DE SISTEMA DE ALMACENES (CORREGIDO)
-- Migración de sistema híbrido a sistema unificado con FK
-- ========================================

-- PASO 1: CREAR BACKUP DE SEGURIDAD
CREATE TABLE IF NOT EXISTS backup_productos_almacen AS 
SELECT id, nombre, stock_actual, stock_minimo, stock_maximo 
FROM productos WHERE 1=1;

-- Verificar si existe tabla almacenes y hacer backup
CREATE TABLE IF NOT EXISTS backup_almacenes_old AS 
SELECT * FROM almacenes WHERE 1=1;

-- PASO 2: CREAR TABLA ALMACENES CONSOLIDADA
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

-- PASO 3: VERIFICAR ESTRUCTURA DE TABLA ALMACENES EXISTENTE
-- Obtener información de las columnas que realmente existen
SET @sql = NULL;
SELECT 
    GROUP_CONCAT(COLUMN_NAME) INTO @existing_columns
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'almacenes' 
  AND TABLE_SCHEMA = DATABASE();

-- PASO 4: MIGRAR DATOS ADAPTÁNDOSE A LA ESTRUCTURA EXISTENTE
-- Versión 1: Si la tabla almacenes tiene la estructura completa
INSERT IGNORE INTO almacenes_consolidado (codigo, nombre, descripcion, direccion, telefono, capacidad_maxima, encargado, icono, activo)
SELECT 
    COALESCE(
        CASE WHEN @existing_columns LIKE '%codigo%' THEN codigo ELSE NULL END,
        CONCAT('ALM_', id)
    ) as codigo,
    nombre,
    CASE 
        WHEN @existing_columns LIKE '%descripcion%' THEN descripcion
        ELSE CONCAT('Almacén ', nombre)
    END as descripcion,
    CASE 
        WHEN @existing_columns LIKE '%direccion%' THEN direccion
        WHEN @existing_columns LIKE '%ubicacion%' THEN ubicacion
        ELSE 'Dirección no especificada'
    END as direccion,
    CASE 
        WHEN @existing_columns LIKE '%telefono%' THEN telefono
        ELSE NULL
    END as telefono,
    CASE 
        WHEN @existing_columns LIKE '%capacidad_maxima%' THEN COALESCE(capacidad_maxima, 0)
        ELSE 0
    END as capacidad_maxima,
    CASE 
        WHEN @existing_columns LIKE '%encargado%' THEN encargado
        ELSE 'No asignado'
    END as encargado,
    CASE 
        WHEN @existing_columns LIKE '%codigo%' THEN
            CASE 
                WHEN codigo = 'FABRICA' THEN '🏭'
                WHEN codigo = 'TIENDA_BOG' THEN '🏬'
                WHEN codigo = 'TIENDA_MED' THEN '🏪'
                WHEN codigo LIKE 'BODEGA_%' THEN '📦'
                ELSE '🏪'
            END
        ELSE '🏪'
    END as icono,
    CASE 
        WHEN @existing_columns LIKE '%activo%' THEN activo
        ELSE 1
    END as activo
FROM almacenes
WHERE nombre IS NOT NULL;

-- PASO 5: AGREGAR ALMACENES DESDE PRODUCTOS SI NO EXISTEN
-- Crear almacenes para cualquier nombre de almacén que aparezca en productos
INSERT IGNORE INTO almacenes_consolidado (codigo, nombre, descripcion, icono, activo)
SELECT DISTINCT
    CONCAT('ALM_', UPPER(REPLACE(REPLACE(p.almacen, ' ', '_'), 'ñ', 'n'))) as codigo,
    p.almacen as nombre,
    CONCAT('Almacén ', p.almacen, ' - Migrado automáticamente desde productos') as descripcion,
    CASE 
        WHEN p.almacen LIKE '%fábrica%' OR p.almacen LIKE '%fabrica%' THEN '🏭'
        WHEN p.almacen LIKE '%tienda%' OR p.almacen LIKE '%bogotá%' OR p.almacen LIKE '%bogota%' THEN '🏬'
        WHEN p.almacen LIKE '%medellín%' OR p.almacen LIKE '%medellin%' THEN '🏪'
        WHEN p.almacen LIKE '%bodega%' THEN '📦'
        WHEN p.almacen LIKE '%principal%' THEN '🏪'
        ELSE '🏪'
    END as icono,
    1 as activo
FROM productos p
WHERE p.almacen IS NOT NULL 
  AND p.almacen != ''
  AND NOT EXISTS (
    SELECT 1 FROM almacenes_consolidado ac 
    WHERE ac.nombre = p.almacen
  );

-- PASO 6: CREAR TABLA INVENTARIO_ALMACEN SI NO EXISTE
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

-- PASO 7: MIGRAR DATOS DE PRODUCTOS A INVENTARIO_ALMACEN
-- Migrar todos los productos existentes al nuevo sistema
INSERT IGNORE INTO inventario_almacen_new (producto_id, almacen_id, stock_actual, stock_minimo, stock_maximo)
SELECT 
    p.id,
    ac.id as almacen_id,
    COALESCE(p.stock_actual, 0) as stock_actual,
    COALESCE(p.stock_minimo, 5) as stock_minimo,
    COALESCE(p.stock_maximo, 100) as stock_maximo
FROM productos p
JOIN almacenes_consolidado ac ON p.almacen = ac.nombre
WHERE p.almacen IS NOT NULL AND p.almacen != '';

-- PASO 8: CREAR TABLA DE MOVIMIENTOS SI NO EXISTE
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

-- PASO 9: VALIDACIONES ANTES DE CONTINUAR
-- Verificar que todos los productos tienen su almacén migrado
SELECT 
    'VALIDACIÓN PRODUCTOS' as reporte,
    COUNT(*) as total_productos,
    COUNT(CASE WHEN p.almacen IS NOT NULL THEN 1 END) as productos_con_almacen,
    COUNT(ia.producto_id) as productos_migrados,
    COUNT(*) - COUNT(ia.producto_id) as productos_faltantes
FROM productos p
LEFT JOIN inventario_almacen_new ia ON p.id = ia.producto_id
WHERE p.activo = 1;

-- PASO 10: CREAR VISTA PARA COMPATIBILIDAD
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

-- PASO 11: CREAR ÍNDICES PARA RENDIMIENTO
CREATE INDEX IF NOT EXISTS idx_almacenes_consolidado_codigo ON almacenes_consolidado (codigo);
CREATE INDEX IF NOT EXISTS idx_almacenes_consolidado_nombre ON almacenes_consolidado (nombre);
CREATE INDEX IF NOT EXISTS idx_almacenes_consolidado_activo ON almacenes_consolidado (activo);

CREATE INDEX IF NOT EXISTS idx_inventario_new_producto ON inventario_almacen_new (producto_id);
CREATE INDEX IF NOT EXISTS idx_inventario_new_almacen ON inventario_almacen_new (almacen_id);
CREATE INDEX IF NOT EXISTS idx_inventario_new_stock ON inventario_almacen_new (stock_actual, stock_minimo);

CREATE INDEX IF NOT EXISTS idx_movimientos_new_producto ON movimientos_inventario_new (producto_id);
CREATE INDEX IF NOT EXISTS idx_movimientos_new_almacen ON movimientos_inventario_new (almacen_id);
CREATE INDEX IF NOT EXISTS idx_movimientos_new_fecha ON movimientos_inventario_new (fecha_movimiento);

-- PASO 12: ESTADÍSTICAS DE MIGRACIÓN
SELECT 
    'ESTADÍSTICAS DE MIGRACIÓN' as reporte,
    (SELECT COUNT(*) FROM almacenes_consolidado) as almacenes_consolidados,
    (SELECT COUNT(*) FROM inventario_almacen_new) as registros_inventario,
    (SELECT COUNT(DISTINCT producto_id) FROM inventario_almacen_new) as productos_migrados,
    (SELECT COUNT(*) FROM productos WHERE activo = 1) as productos_activos_total,
    (SELECT COUNT(DISTINCT almacen) FROM productos WHERE almacen IS NOT NULL) as almacenes_originales_productos;

-- PASO 13: MOSTRAR ESTRUCTURA DE TABLA ORIGINAL PARA DEPURACIÓN
SELECT 
    'ESTRUCTURA TABLA ALMACENES' as info,
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'almacenes' 
  AND TABLE_SCHEMA = DATABASE()
ORDER BY ORDINAL_POSITION;

-- PASO 14: MOSTRAR ALMACENES MIGRADOS
SELECT 
    'ALMACENES MIGRADOS' as info,
    id,
    codigo,
    nombre,
    descripcion,
    icono,
    activo
FROM almacenes_consolidado
ORDER BY nombre;

-- MENSAJE FINAL
SELECT 'MIGRACIÓN COMPLETADA - REVISAR ESTADÍSTICAS ANTES DE CONTINUAR' as mensaje;

-- PASO 15: SCRIPT DE ROLLBACK (COMENTADO - SOLO PARA EMERGENCIAS)
/*
-- ROLLBACK EN CASO DE EMERGENCIA
DROP TABLE IF EXISTS almacenes_consolidado;
DROP TABLE IF EXISTS inventario_almacen_new;
DROP TABLE IF EXISTS movimientos_inventario_new;
DROP VIEW IF EXISTS vista_productos_almacen;

-- Restaurar desde backup si es necesario
-- INSERT INTO productos (id, nombre, almacen, stock_actual, stock_minimo, stock_maximo)
-- SELECT id, nombre, almacen, stock_actual, stock_minimo, stock_maximo
-- FROM backup_productos_almacen
-- ON DUPLICATE KEY UPDATE
--     almacen = VALUES(almacen),
--     stock_actual = VALUES(stock_actual);
*/