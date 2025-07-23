-- Tabla para tokens "recordarme" de 30 días
-- Sequoia Speed - Sistema de Accesos

CREATE TABLE IF NOT EXISTS `remember_tokens` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `usuario_id` int(11) NOT NULL,
    `selector` varchar(24) NOT NULL,
    `token_hash` varchar(64) NOT NULL,
    `fecha_creacion` timestamp DEFAULT CURRENT_TIMESTAMP,
    `fecha_expiracion` timestamp NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_selector` (`selector`),
    KEY `idx_usuario_id` (`usuario_id`),
    KEY `idx_fecha_expiracion` (`fecha_expiracion`),
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear índice compuesto para mejorar rendimiento en búsquedas
CREATE INDEX `idx_selector_token_expiry` ON `remember_tokens` (`selector`, `token_hash`, `fecha_expiracion`);