-- Script para limpiar la base de datos y resetear auto increments para producción
-- Ejecutar este script con cuidado, ya que eliminará todos los datos

-- Deshabilitar verificación de claves foráneas temporalmente
SET FOREIGN_KEY_CHECKS = 0;

-- Limpiar tablas principales
TRUNCATE TABLE `pedidos_detal`;

TRUNCATE TABLE `pedido_detalle`;

-- Limpiar tablas de logs de Bold
TRUNCATE TABLE `bold_logs`;

TRUNCATE TABLE `bold_webhook_logs`;

TRUNCATE TABLE `bold_webhook_stats`;

TRUNCATE TABLE `notification_logs`;

-- Limpiar otras tablas que puedan existir
TRUNCATE TABLE `bold_retry_queue`;

-- Resetear AUTO_INCREMENT a 1 para todas las tablas
ALTER TABLE `pedidos_detal` AUTO_INCREMENT = 1;

ALTER TABLE `pedido_detalle` AUTO_INCREMENT = 1;

ALTER TABLE `productos` AUTO_INCREMENT = 1;

ALTER TABLE `bold_logs` AUTO_INCREMENT = 1;

ALTER TABLE `bold_webhook_logs` AUTO_INCREMENT = 1;

ALTER TABLE `bold_webhook_stats` AUTO_INCREMENT = 1;

ALTER TABLE `notification_logs` AUTO_INCREMENT = 1;

-- Intentar resetear otras tablas si existen
ALTER TABLE `bold_retry_queue` AUTO_INCREMENT = 1;

-- Habilitar verificación de claves foráneas
SET FOREIGN_KEY_CHECKS = 1;

-- Mensaje de confirmación
SELECT 'Base de datos limpiada y AUTO_INCREMENT reseteado correctamente' AS status;
