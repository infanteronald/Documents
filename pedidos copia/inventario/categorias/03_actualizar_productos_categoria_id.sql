-- =====================================================
-- ACTUALIZAR PRODUCTOS CON categoria_id
-- =====================================================

-- Agregar columna categoria_id si no existe
ALTER TABLE productos 
ADD COLUMN IF NOT EXISTS categoria_id INT NULL;

-- Agregar índice para categoria_id
ALTER TABLE productos 
ADD INDEX IF NOT EXISTS idx_categoria_id (categoria_id);

-- Actualizar productos para usar categoria_id basado en el nombre de categoria
UPDATE productos p
INNER JOIN categorias_productos cp ON p.categoria = cp.nombre
SET p.categoria_id = cp.id
WHERE p.categoria IS NOT NULL 
  AND p.categoria != '' 
  AND p.categoria != 'null'
  AND p.categoria_id IS NULL;

-- Verificar la actualización
SELECT 
    cp.nombre as categoria_nombre,
    cp.icono,
    COUNT(p.id) as total_productos
FROM categorias_productos cp
LEFT JOIN productos p ON cp.id = p.categoria_id
GROUP BY cp.id, cp.nombre, cp.icono
ORDER BY cp.orden, cp.nombre;

-- Crear foreign key constraint
ALTER TABLE productos 
ADD CONSTRAINT IF NOT EXISTS fk_productos_categoria 
FOREIGN KEY (categoria_id) REFERENCES categorias_productos(id) 
ON UPDATE CASCADE ON DELETE SET NULL;

-- Ver productos sin categoría asignada
SELECT COUNT(*) as productos_sin_categoria
FROM productos 
WHERE categoria_id IS NULL;

-- Ver algunos ejemplos de productos con categoría asignada
SELECT 
    p.nombre as producto,
    p.categoria as categoria_original,
    cp.nombre as categoria_nueva,
    cp.icono
FROM productos p
INNER JOIN categorias_productos cp ON p.categoria_id = cp.id
LIMIT 20;