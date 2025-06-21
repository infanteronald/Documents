-- Script para reorganizar IDs de pedidos
-- Fecha: 21 de junio de 2025
-- CUIDADO: Este script modifica foreign keys

-- Deshabilitar verificación de foreign keys temporalmente
SET FOREIGN_KEY_CHECKS = 0;

UPDATE pedido_detalle SET id = 1 WHERE id = 133;

-- Cambiar pedido_id 119 → 2
UPDATE pedido_detalle SET id = 2 WHERE id = 134;

-- Paso 4: Resetear AUTO_INCREMENT para que el próximo pedido sea 3
ALTER TABLE pedido_detalle AUTO_INCREMENT = 3;

-- Habilitar verificación de foreign keys
SET FOREIGN_KEY_CHECKS = 1;

-- Verificar resultados
SELECT 'DESPUÉS DE LOS CAMBIOS - pedidos_detal:' as info;

SELECT id, pedido, nombre, monto
FROM pedidos_detal
WHERE
    id IN (1, 2)
ORDER BY id;

SELECT 'DESPUÉS DE LOS CAMBIOS - pedido_detalle:' as info;

SELECT id, pedido_id, nombre, precio
FROM pedido_detalle
WHERE
    pedido_id IN (1, 2)
ORDER BY pedido_id;

-- Verificar AUTO_INCREMENT
SELECT 'AUTO_INCREMENT actual:' as info;

SELECT AUTO_INCREMENT
FROM information_schema.TABLES
WHERE
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pedidos_detal';

-- Mostrar advertencia
SELECT 'IMPORTANTE: El próximo pedido tendrá ID = 3' as advertencia;
