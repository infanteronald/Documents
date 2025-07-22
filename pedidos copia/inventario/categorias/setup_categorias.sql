-- =====================================================
-- SETUP DE CATEGORÍAS DE PRODUCTOS
-- Sistema de Inventario - Sequoia Speed
-- =====================================================

-- Crear tabla de categorías
CREATE TABLE IF NOT EXISTS categorias_productos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT,
    icono VARCHAR(10) DEFAULT '🏷️',
    color VARCHAR(7) DEFAULT '#58a6ff',
    activa BOOLEAN DEFAULT TRUE,
    orden INT DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_nombre (nombre),
    INDEX idx_activa (activa),
    INDEX idx_orden (orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrar categorías existentes desde productos
INSERT INTO categorias_productos (nombre, descripcion, icono, orden)
SELECT DISTINCT 
    categoria as nombre,
    CONCAT('Categoría migrada automáticamente: ', categoria) as descripcion,
    '🏷️' as icono,
    ROW_NUMBER() OVER (ORDER BY categoria) * 10 as orden
FROM productos 
WHERE categoria IS NOT NULL 
  AND categoria != '' 
  AND categoria NOT IN (SELECT nombre FROM categorias_productos)
ORDER BY categoria;

-- Agregar columna categoria_id a productos (si no existe)
ALTER TABLE productos 
ADD COLUMN IF NOT EXISTS categoria_id INT NULL,
ADD INDEX IF NOT EXISTS idx_categoria_id (categoria_id);

-- Actualizar productos para usar categoria_id
UPDATE productos p
INNER JOIN categorias_productos cp ON p.categoria = cp.nombre
SET p.categoria_id = cp.id
WHERE p.categoria IS NOT NULL AND p.categoria != '';

-- Crear foreign key constraint
ALTER TABLE productos 
ADD CONSTRAINT IF NOT EXISTS fk_productos_categoria 
FOREIGN KEY (categoria_id) REFERENCES categorias_productos(id) 
ON UPDATE CASCADE ON DELETE SET NULL;

-- NOTA: Las categorías específicas se insertan desde las ya existentes en productos
-- Este archivo es reemplazado por migracion_categorias_especificas.sql
-- que se genera automáticamente leyendo las categorías reales del sistema

-- Crear vista para estadísticas de categorías
CREATE OR REPLACE VIEW vista_categorias_estadisticas AS
SELECT 
    cp.id,
    cp.nombre,
    cp.descripcion,
    cp.icono,
    cp.color,
    cp.activa,
    cp.orden,
    cp.fecha_creacion,
    cp.fecha_actualizacion,
    COALESCE(COUNT(p.id), 0) as total_productos,
    COALESCE(COUNT(CASE WHEN p.activo = 1 THEN 1 END), 0) as productos_activos,
    COALESCE(SUM(CASE WHEN p.activo = 1 AND ia.stock_actual > 0 THEN ia.stock_actual ELSE 0 END), 0) as stock_total,
    COALESCE(AVG(CASE WHEN p.activo = 1 THEN p.precio END), 0) as precio_promedio
FROM categorias_productos cp
LEFT JOIN productos p ON cp.id = p.categoria_id
LEFT JOIN inventario_almacen ia ON p.id = ia.producto_id
GROUP BY cp.id, cp.nombre, cp.descripcion, cp.icono, cp.color, cp.activa, cp.orden, cp.fecha_creacion, cp.fecha_actualizacion
ORDER BY cp.orden ASC, cp.nombre ASC;