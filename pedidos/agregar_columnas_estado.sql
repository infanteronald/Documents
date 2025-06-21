-- Script para agregar nuevas columnas de estado a la tabla pedidos_detal
-- Estas columnas permitirán múltiples estados activos simultáneamente

-- Agregar las nuevas columnas de estado
ALTER TABLE `pedidos_detal`
ADD COLUMN `enviado` ENUM('0', '1') DEFAULT '0' COMMENT 'Estado: Pedido enviado',
ADD COLUMN `archivado` ENUM('0', '1') DEFAULT '0' COMMENT 'Estado: Pedido archivado',
ADD COLUMN `anulado` ENUM('0', '1') DEFAULT '0' COMMENT 'Estado: Pedido anulado',
ADD COLUMN `guia` ENUM('0', '1') DEFAULT '0' COMMENT 'Estado: Tiene guía de envío',
ADD COLUMN `comprobante` ENUM('0', '1') DEFAULT '0' COMMENT 'Estado: Tiene comprobante de pago';

-- Migrar datos existentes del campo 'estado' a las nuevas columnas
UPDATE `pedidos_detal`
SET
    `enviado` = '1'
WHERE
    `estado` = 'enviado';

UPDATE `pedidos_detal`
SET
    `anulado` = '1'
WHERE
    `estado` = 'anulado';

UPDATE `pedidos_detal`
SET
    `archivado` = '1'
WHERE
    `estado` = 'archivado';

-- Actualizar estados de guía y comprobante basados en campos existentes
UPDATE `pedidos_detal`
SET
    `guia` = '1'
WHERE
    `guia` IS NOT NULL
    AND `guia` != '';

UPDATE `pedidos_detal`
SET
    `comprobante` = '1'
WHERE
    `comprobante` IS NOT NULL
    AND `comprobante` != '';

-- Agregar índices para mejorar rendimiento en consultas
ALTER TABLE `pedidos_detal`
ADD INDEX `idx_enviado` (`enviado`),
ADD INDEX `idx_archivado` (`archivado`),
ADD INDEX `idx_anulado` (`anulado`),
ADD INDEX `idx_guia` (`guia`),
ADD INDEX `idx_comprobante` (`comprobante`);

-- Verificar los cambios
SELECT
    id,
    estado AS estado_anterior,
    enviado,
    archivado,
    anulado,
    guia,
    comprobante
FROM `pedidos_detal`
ORDER BY id DESC
LIMIT 10;
