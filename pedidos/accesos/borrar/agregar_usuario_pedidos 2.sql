-- ============================================
-- AGREGAR COLUMNA USUARIO_ID A PEDIDOS_DETAL
-- Para rastrear quién crea cada pedido
-- ============================================

-- Agregar columna usuario_id a pedidos_detal
ALTER TABLE pedidos_detal 
ADD COLUMN usuario_id INT NULL AFTER metodo_pago,
ADD COLUMN fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP AFTER usuario_id,
ADD COLUMN fecha_modificacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER fecha_creacion;

-- Agregar índice para mejorar rendimiento
ALTER TABLE pedidos_detal 
ADD INDEX idx_usuario_id (usuario_id),
ADD INDEX idx_fecha_creacion (fecha_creacion);

-- Agregar llave foránea (después de que exista la tabla usuarios)
-- Nota: Esto se ejecutará después de la migración principal
-- ALTER TABLE pedidos_detal 
-- ADD CONSTRAINT fk_pedidos_usuario 
-- FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL;

-- Mostrar estructura actualizada
DESCRIBE pedidos_detal;

-- Verificar cambios
SELECT 'COLUMNA USUARIO_ID AGREGADA EXITOSAMENTE' as status;