-- Script de migración: Agregar nuevos campos de estado
-- Fecha: 21 de junio de 2025
-- Propósito: Implementar sistema de estados múltiples para pedidos

-- Agregar las nuevas columnas de estado a la tabla pedidos_detal
ALTER TABLE `pedidos_detal`
ADD COLUMN `Enviado` ENUM('0', '1') DEFAULT '0' COMMENT 'Estado de envío del pedido',
ADD COLUMN `Archivado` ENUM('0', '1') DEFAULT '0' COMMENT 'Pedido archivado',
ADD COLUMN `Anulado` ENUM('0', '1') DEFAULT '0' COMMENT 'Pedido anulado/cancelado',
ADD COLUMN `Guia` ENUM('0', '1') DEFAULT '0' COMMENT 'Guía de envío subida',
ADD COLUMN `Comprobante` ENUM('0', '1') DEFAULT '0' COMMENT 'Comprobante de pago subido';

-- Verificar que las columnas se agregaron correctamente
DESCRIBE `pedidos_detal`;

-- Migrar datos existentes del campo 'estado' a los nuevos campos
-- Actualizar según el estado actual
UPDATE `pedidos_detal`
SET
    `Enviado` = '1'
WHERE
    `estado` = 'enviado';

UPDATE `pedidos_detal`
SET
    `Archivado` = '1'
WHERE
    `estado` = 'archivado';

UPDATE `pedidos_detal`
SET
    `Anulado` = '1'
WHERE
    `estado` = 'anulado';

-- Actualizar campo Guia basado en si existe guía
UPDATE `pedidos_detal`
SET
    `Guia` = '1'
WHERE
    `guia` IS NOT NULL
    AND `guia` != '';

-- Actualizar campo Comprobante basado en si existe comprobante
UPDATE `pedidos_detal`
SET
    `Comprobante` = '1'
WHERE
    `comprobante` IS NOT NULL
    AND `comprobante` != '';

-- Verificar los datos después de la migración
SELECT
    id,
    estado,
    Enviado,
    Archivado,
    Anulado,
    Guia,
    Comprobante,
    guia,
    comprobante
FROM `pedidos_detal`
ORDER BY id DESC
LIMIT 10;
