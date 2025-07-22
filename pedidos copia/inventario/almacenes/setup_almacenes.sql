-- ========================================
-- SETUP PARA MÓDULO DE ALMACENES
-- Sistema de Inventario - Sequoia Speed
-- ========================================

-- Crear tabla de almacenes
CREATE TABLE IF NOT EXISTS almacenes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT,
    ubicacion VARCHAR(255),
    capacidad_maxima INT DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_nombre (nombre),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Poblar con almacenes existentes basados en la tabla productos
INSERT IGNORE INTO almacenes (nombre, descripcion, ubicacion, capacidad_maxima) 
SELECT DISTINCT 
    p.almacen as nombre,
    CONCAT('Almacén ', p.almacen) as descripcion,
    CASE 
        WHEN p.almacen LIKE '%Bogotá%' THEN 'Bogotá, Colombia'
        WHEN p.almacen LIKE '%Medellín%' THEN 'Medellín, Colombia'
        WHEN p.almacen LIKE '%Cali%' THEN 'Cali, Colombia'
        WHEN p.almacen LIKE '%Fábrica%' THEN 'Zona Industrial, Bogotá'
        WHEN p.almacen LIKE '%Bodega%' THEN 'Zona de Bodegas, Bogotá'
        ELSE 'Ubicación por definir'
    END as ubicacion,
    CASE 
        WHEN p.almacen LIKE '%Fábrica%' THEN 2000
        WHEN p.almacen LIKE '%Bodega%' THEN 1500
        WHEN p.almacen LIKE '%Tienda%' THEN 500
        ELSE 1000
    END as capacidad_maxima
FROM productos p 
WHERE p.almacen IS NOT NULL 
  AND p.almacen != '' 
  AND p.almacen != 'NULL'
ORDER BY p.almacen;

-- Agregar algunos almacenes adicionales comunes si no existen
INSERT IGNORE INTO almacenes (nombre, descripcion, ubicacion, capacidad_maxima) VALUES
('Almacén Central', 'Almacén principal de distribución', 'Bogotá, Colombia', 3000),
('Bodega Norte', 'Bodega de almacenamiento norte', 'Medellín, Colombia', 1500),
('Bodega Sur', 'Bodega de almacenamiento sur', 'Cali, Colombia', 1200),
('Almacén Temporal', 'Almacén para almacenamiento temporal', 'Bogotá, Colombia', 800);

-- Crear vista para reportes rápidos
CREATE OR REPLACE VIEW vista_almacenes_productos AS
SELECT 
    a.id,
    a.nombre as almacen,
    a.descripcion,
    a.ubicacion,
    a.capacidad_maxima,
    a.activo,
    COUNT(p.id) as total_productos,
    SUM(p.stock_actual) as stock_total,
    SUM(CASE WHEN p.stock_actual <= p.stock_minimo THEN 1 ELSE 0 END) as productos_criticos,
    SUM(CASE WHEN p.stock_actual = 0 THEN 1 ELSE 0 END) as productos_sin_stock,
    AVG(p.precio) as precio_promedio,
    MAX(p.fecha_actualizacion) as ultima_actualizacion
FROM almacenes a
LEFT JOIN productos p ON a.nombre = p.almacen AND p.activo = 1
GROUP BY a.id, a.nombre, a.descripcion, a.ubicacion, a.capacidad_maxima, a.activo;

-- Crear índices adicionales para mejor performance
CREATE INDEX IF NOT EXISTS idx_productos_almacen ON productos(almacen);
CREATE INDEX IF NOT EXISTS idx_productos_stock ON productos(stock_actual, stock_minimo);
CREATE INDEX IF NOT EXISTS idx_productos_activo ON productos(activo);

-- Verificar datos insertados
SELECT 
    'Almacenes creados' as info,
    COUNT(*) as cantidad 
FROM almacenes;

SELECT 
    'Productos por almacén' as info,
    almacen,
    COUNT(*) as productos
FROM productos 
WHERE almacen IS NOT NULL 
GROUP BY almacen 
ORDER BY productos DESC;