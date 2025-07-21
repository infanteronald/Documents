-- =====================================================
-- CONSULTAR CATEGORÍAS EXISTENTES EN PRODUCTOS
-- =====================================================

-- Ver todas las categorías únicas en la tabla productos
SELECT DISTINCT categoria, COUNT(*) as total_productos 
FROM productos 
WHERE categoria IS NOT NULL 
  AND categoria != '' 
  AND categoria != 'null'
GROUP BY categoria 
ORDER BY categoria;

-- Ver algunos ejemplos de productos por categoría
SELECT categoria, nombre, COUNT(*) OVER (PARTITION BY categoria) as productos_en_categoria
FROM productos 
WHERE categoria IS NOT NULL 
  AND categoria != '' 
  AND categoria != 'null'
ORDER BY categoria, nombre
LIMIT 50;