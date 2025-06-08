-- Script para agregar el campo talla a la tabla pedido_detalle
-- Ejecutar este script en la base de datos remota

ALTER TABLE pedido_detalle ADD COLUMN talla VARCHAR(50) DEFAULT 'N/A' AFTER cantidad;

-- Verificar la estructura actualizada
DESCRIBE pedido_detalle;
