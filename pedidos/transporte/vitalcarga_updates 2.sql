-- ================================================
-- SCRIPT SQL PARA NUEVAS FUNCIONALIDADES VITALCARGA
-- ================================================
-- Ejecutar estos scripts para habilitar todas las funcionalidades del sistema de transporte

-- 1. Agregar columnas para estados de entrega
ALTER TABLE pedidos_detal ADD COLUMN IF NOT EXISTS estado_entrega VARCHAR(20) DEFAULT 'pendiente';
ALTER TABLE pedidos_detal ADD COLUMN IF NOT EXISTS fecha_estado_entrega TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE pedidos_detal ADD COLUMN IF NOT EXISTS notas_transportista TEXT;
ALTER TABLE pedidos_detal ADD COLUMN IF NOT EXISTS foto_entrega VARCHAR(255);

-- 2. Agregar columnas para programación de entregas
ALTER TABLE pedidos_detal ADD COLUMN IF NOT EXISTS fecha_programada TIMESTAMP NULL;
ALTER TABLE pedidos_detal ADD COLUMN IF NOT EXISTS motivo_reprogramacion TEXT;
ALTER TABLE pedidos_detal ADD COLUMN IF NOT EXISTS notificado_reprogramacion BOOLEAN DEFAULT FALSE;

-- 3. Agregar columnas para seguimiento de tiempo
ALTER TABLE pedidos_detal ADD COLUMN IF NOT EXISTS tiempo_transcurrido_minutos INT;
ALTER TABLE pedidos_detal ADD COLUMN IF NOT EXISTS prioridad_urgencia ENUM('verde', 'amarillo', 'rojo') DEFAULT 'verde';

-- 4. Crear tabla para historial de estados de entrega
CREATE TABLE IF NOT EXISTS historial_estados_entrega (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    estado_anterior VARCHAR(20),
    estado_nuevo VARCHAR(20) NOT NULL,
    fecha_cambio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    transportista_id INT,
    notas TEXT,
    FOREIGN KEY (pedido_id) REFERENCES pedidos_detal(id) ON DELETE CASCADE,
    INDEX idx_pedido_id (pedido_id),
    INDEX idx_fecha_cambio (fecha_cambio)
);

-- 5. Crear tabla para transportistas
CREATE TABLE IF NOT EXISTS transportistas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    telefono VARCHAR(20),
    email VARCHAR(100),
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    zona_asignada VARCHAR(100),
    vehiculo_tipo VARCHAR(50),
    UNIQUE KEY unique_telefono (telefono),
    INDEX idx_activo (activo)
);

-- 6. Crear tabla para asignación de pedidos a transportistas
CREATE TABLE IF NOT EXISTS pedidos_transportistas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    transportista_id INT NOT NULL,
    fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado_asignacion ENUM('asignado', 'en_ruta', 'entregado', 'cancelado') DEFAULT 'asignado',
    FOREIGN KEY (pedido_id) REFERENCES pedidos_detal(id) ON DELETE CASCADE,
    FOREIGN KEY (transportista_id) REFERENCES transportistas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_pedido_activo (pedido_id, estado_asignacion),
    INDEX idx_transportista (transportista_id),
    INDEX idx_estado (estado_asignacion)
);

-- 7. Crear tabla para chat interno
CREATE TABLE IF NOT EXISTS chat_interno (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    transportista_id INT,
    usuario_admin VARCHAR(100),
    mensaje TEXT NOT NULL,
    fecha_mensaje TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    leido BOOLEAN DEFAULT FALSE,
    tipo_mensaje ENUM('texto', 'imagen', 'ubicacion') DEFAULT 'texto',
    archivo_adjunto VARCHAR(255),
    FOREIGN KEY (pedido_id) REFERENCES pedidos_detal(id) ON DELETE CASCADE,
    FOREIGN KEY (transportista_id) REFERENCES transportistas(id) ON DELETE CASCADE,
    INDEX idx_pedido (pedido_id),
    INDEX idx_fecha (fecha_mensaje),
    INDEX idx_leido (leido)
);

-- 8. Crear tabla para notificaciones push
CREATE TABLE IF NOT EXISTS notificaciones_push (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transportista_id INT,
    pedido_id INT,
    titulo VARCHAR(100) NOT NULL,
    mensaje TEXT NOT NULL,
    tipo VARCHAR(50) DEFAULT 'nuevo_pedido',
    enviado BOOLEAN DEFAULT FALSE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_envio TIMESTAMP NULL,
    token_push VARCHAR(255),
    FOREIGN KEY (transportista_id) REFERENCES transportistas(id) ON DELETE CASCADE,
    FOREIGN KEY (pedido_id) REFERENCES pedidos_detal(id) ON DELETE CASCADE,
    INDEX idx_transportista (transportista_id),
    INDEX idx_enviado (enviado),
    INDEX idx_fecha_creacion (fecha_creacion)
);

-- 9. Crear tabla para estadísticas del transportista
CREATE TABLE IF NOT EXISTS estadisticas_transportista (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transportista_id INT NOT NULL,
    fecha DATE NOT NULL,
    pedidos_asignados INT DEFAULT 0,
    pedidos_entregados INT DEFAULT 0,
    pedidos_reintento INT DEFAULT 0,
    pedidos_devueltos INT DEFAULT 0,
    tiempo_promedio_entrega INT DEFAULT 0,
    distancia_recorrida DECIMAL(10,2) DEFAULT 0.00,
    FOREIGN KEY (transportista_id) REFERENCES transportistas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_transportista_fecha (transportista_id, fecha),
    INDEX idx_fecha (fecha)
);

-- 10. Crear tabla para configuración de zonas
CREATE TABLE IF NOT EXISTS zonas_entrega (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    ciudad VARCHAR(100) NOT NULL,
    barrios TEXT,
    transportista_id INT,
    tiempo_estimado_entrega INT DEFAULT 60,
    costo_base DECIMAL(10,2) DEFAULT 0.00,
    activa BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (transportista_id) REFERENCES transportistas(id) ON DELETE SET NULL,
    INDEX idx_ciudad (ciudad),
    INDEX idx_activa (activa)
);

-- 11. Insertar datos iniciales para transportistas de ejemplo
INSERT INTO transportistas (nombre, telefono, email, zona_asignada, vehiculo_tipo) VALUES
('Juan Pérez', '3101234567', 'juan.perez@vitalcarga.com', 'Norte', 'Moto'),
('María González', '3207654321', 'maria.gonzalez@vitalcarga.com', 'Sur', 'Moto'),
('Carlos Rodríguez', '3151112222', 'carlos.rodriguez@vitalcarga.com', 'Centro', 'Carro'),
('Ana Martínez', '3009998888', 'ana.martinez@vitalcarga.com', 'Occidente', 'Moto')
ON DUPLICATE KEY UPDATE telefono = VALUES(telefono);

-- 12. Insertar zonas de entrega iniciales
INSERT INTO zonas_entrega (nombre, ciudad, barrios, tiempo_estimado_entrega) VALUES
('Zona Norte', 'Medellín', 'Aranjuez,Manrique,Campo Valdés', 45),
('Zona Sur', 'Medellín', 'Envigado,Sabaneta,Itagüí', 60),
('Zona Centro', 'Medellín', 'Centro,La Candelaria,Prado', 30),
('Zona Occidente', 'Medellín', 'Robledo,Pajarito,San Cristóbal', 50)
ON DUPLICATE KEY UPDATE tiempo_estimado_entrega = VALUES(tiempo_estimado_entrega);

-- 13. Crear índices para optimización
CREATE INDEX IF NOT EXISTS idx_pedidos_estado_entrega ON pedidos_detal(estado_entrega);
CREATE INDEX IF NOT EXISTS idx_pedidos_fecha_programada ON pedidos_detal(fecha_programada);
CREATE INDEX IF NOT EXISTS idx_pedidos_prioridad ON pedidos_detal(prioridad_urgencia);
CREATE INDEX IF NOT EXISTS idx_pedidos_tiempo_transcurrido ON pedidos_detal(tiempo_transcurrido_minutos);
CREATE INDEX IF NOT EXISTS idx_pedidos_barrio ON pedidos_detal(barrio);
CREATE INDEX IF NOT EXISTS idx_pedidos_recaudo ON pedidos_detal(recaudo);

-- 14. Crear vista para estadísticas rápidas
CREATE OR REPLACE VIEW vista_estadisticas_transportista AS
SELECT 
    t.id as transportista_id,
    t.nombre as transportista_nombre,
    COUNT(pt.pedido_id) as total_pedidos,
    SUM(CASE WHEN pt.estado_asignacion = 'entregado' THEN 1 ELSE 0 END) as pedidos_entregados,
    SUM(CASE WHEN pt.estado_asignacion = 'en_ruta' THEN 1 ELSE 0 END) as pedidos_en_ruta,
    SUM(CASE WHEN p.estado_entrega = 'reintento' THEN 1 ELSE 0 END) as pedidos_reintento,
    SUM(CASE WHEN p.estado_entrega = 'devuelto' THEN 1 ELSE 0 END) as pedidos_devueltos,
    AVG(p.tiempo_transcurrido_minutos) as tiempo_promedio,
    DATE(pt.fecha_asignacion) as fecha
FROM transportistas t
LEFT JOIN pedidos_transportistas pt ON t.id = pt.transportista_id
LEFT JOIN pedidos_detal p ON pt.pedido_id = p.id
WHERE t.activo = TRUE
GROUP BY t.id, DATE(pt.fecha_asignacion);

-- 15. Crear procedimiento para actualizar prioridades automáticamente
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS ActualizarPrioridadesPedidos()
BEGIN
    -- Actualizar tiempo transcurrido
    UPDATE pedidos_detal 
    SET tiempo_transcurrido_minutos = TIMESTAMPDIFF(MINUTE, fecha, NOW())
    WHERE tiene_guia = '0';
    
    -- Actualizar prioridades basadas en tiempo transcurrido
    UPDATE pedidos_detal 
    SET prioridad_urgencia = CASE 
        WHEN tiempo_transcurrido_minutos > 2880 THEN 'rojo'    -- Más de 2 días
        WHEN tiempo_transcurrido_minutos > 1440 THEN 'amarillo' -- Más de 1 día
        ELSE 'verde'
    END
    WHERE tiene_guia = '0';
END //
DELIMITER ;

-- 16. Crear evento para ejecutar actualización automática cada hora
-- SET GLOBAL event_scheduler = ON;
-- CREATE EVENT IF NOT EXISTS actualizar_prioridades
-- ON SCHEDULE EVERY 1 HOUR
-- DO CALL ActualizarPrioridadesPedidos();

-- 17. Crear función para calcular tiempo estimado de entrega
DELIMITER //
CREATE FUNCTION IF NOT EXISTS CalcularTiempoEstimadoEntrega(ciudad_destino VARCHAR(100), barrio_destino VARCHAR(100))
RETURNS INT
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE tiempo_estimado INT DEFAULT 60;
    
    SELECT ze.tiempo_estimado_entrega INTO tiempo_estimado
    FROM zonas_entrega ze
    WHERE ze.ciudad = ciudad_destino 
    AND (ze.barrios LIKE CONCAT('%', barrio_destino, '%') OR ze.barrios IS NULL)
    AND ze.activa = TRUE
    LIMIT 1;
    
    RETURN COALESCE(tiempo_estimado, 60);
END //
DELIMITER ;

-- 18. Crear trigger para actualizar automáticamente el tiempo estimado
DELIMITER //
CREATE TRIGGER IF NOT EXISTS actualizar_tiempo_estimado
BEFORE INSERT ON pedidos_detal
FOR EACH ROW
BEGIN
    IF NEW.ciudad IS NOT NULL AND NEW.barrio IS NOT NULL THEN
        SET NEW.tiempo_transcurrido_minutos = CalcularTiempoEstimadoEntrega(NEW.ciudad, NEW.barrio);
    END IF;
END //
DELIMITER ;

-- ================================================
-- SCRIPT COMPLETADO
-- ================================================
-- Todas las funcionalidades del sistema VitalCarga han sido creadas
-- Para activar los eventos automáticos, ejecutar:
-- SET GLOBAL event_scheduler = ON;
-- 
-- Funcionalidades habilitadas:
-- ✅ Estados de entrega (En ruta, Entregado, Reintento, Devuelto)
-- ✅ Programación de entregas
-- ✅ Seguimiento de tiempo transcurrido
-- ✅ Sistema de prioridades (semáforo)
-- ✅ Historial de cambios de estado
-- ✅ Gestión de transportistas
-- ✅ Chat interno
-- ✅ Notificaciones push
-- ✅ Estadísticas por transportista
-- ✅ Zonas de entrega
-- ✅ Optimización con índices
-- ✅ Procedimientos automáticos