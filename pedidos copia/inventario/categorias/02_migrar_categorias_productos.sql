-- =====================================================
-- MIGRAR CATEGORÍAS DE PRODUCTOS A CATEGORIAS_PRODUCTOS
-- =====================================================

-- Primero limpiar las categorías por defecto que no corresponden
DELETE FROM categorias_productos 
WHERE nombre IN ('Repuestos', 'Accesorios', 'Filtros', 'Aceites', 'Neumáticos');

-- Insertar categorías específicas con iconos apropiados
INSERT IGNORE INTO categorias_productos (nombre, descripcion, icono, color, orden, activa) VALUES
('guantes', 'Guantes de protección y seguridad industrial', '🧤', '#ff6b6b', 10, 1),
('botas', 'Botas de seguridad y protección laboral', '🥾', '#4ecdc4', 20, 1),
('cascos', 'Cascos de protección industrial', '⛑️', '#45b7d1', 30, 1),
('chalecos', 'Chalecos de seguridad y alta visibilidad', '🦺', '#f9ca24', 40, 1),
('gafas', 'Gafas de protección y seguridad', '🥽', '#6c5ce7', 50, 1),
('mascaras', 'Máscaras y respiradores de protección', '😷', '#a55eea', 60, 1),
('overoles', 'Overoles y ropa de trabajo', '👔', '#26de81', 70, 1),
('arneses', 'Arneses y equipos de altura', '🔗', '#fd79a8', 80, 1),
('herramientas', 'Herramientas y equipos de trabajo', '🔧', '#fdcb6e', 90, 1),
('equipos', 'Equipos y maquinaria industrial', '⚙️', '#74b9ff', 100, 1);

-- Migrar categorías únicas desde productos que no están en la lista anterior
INSERT IGNORE INTO categorias_productos (nombre, descripcion, icono, color, orden, activa)
SELECT DISTINCT 
    categoria as nombre,
    CONCAT('Categoría migrada: ', categoria) as descripcion,
    '🏷️' as icono,
    '#58a6ff' as color,
    (ROW_NUMBER() OVER (ORDER BY categoria) + 10) * 10 as orden,
    1 as activa
FROM productos 
WHERE categoria IS NOT NULL 
  AND categoria != '' 
  AND categoria != 'null'
  AND categoria NOT IN (
    SELECT nombre FROM categorias_productos
  )
ORDER BY categoria;

-- Verificar las categorías migradas
SELECT * FROM categorias_productos ORDER BY orden, nombre;