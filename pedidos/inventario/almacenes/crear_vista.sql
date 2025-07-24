-- ========================================
-- CREAR VISTA FALTANTE PARA ALMACENES
-- Sistema de Inventario - Sequoia Speed
-- ========================================

-- Crear vista para reportes rápidos
CREATE OR REPLACE VIEW vista_almacenes_productos AS
SELECT 
    a.id,
    a.nombre as almacen,
    a.descripcion,
    a.ubicacion,
    a.capacidad_maxima,
    a.activo,
    COUNT(DISTINCT ia.producto_id) as total_productos,
    SUM(ia.stock_actual) as stock_total,
    SUM(CASE WHEN ia.stock_actual <= ia.stock_minimo THEN 1 ELSE 0 END) as productos_criticos,
    SUM(CASE WHEN ia.stock_actual = 0 THEN 1 ELSE 0 END) as productos_sin_stock,
    AVG(p.precio) as precio_promedio,
    MAX(ia.fecha_actualizacion) as ultima_actualizacion
FROM almacenes a
LEFT JOIN inventario_almacen ia ON a.id = ia.almacen_id
LEFT JOIN productos p ON ia.producto_id = p.id AND p.activo = 1
GROUP BY a.id, a.nombre, a.descripcion, a.ubicacion, a.capacidad_maxima, a.activo;

-- Verificar que la vista funciona
SELECT 'Vista creada exitosamente' as resultado;
SELECT COUNT(*) as total_registros FROM vista_almacenes_productos;