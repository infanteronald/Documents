# Sistema de Gestión de Pedidos con Bold PSE - PRODUCCIÓN

**🎯 Estado: SISTEMA EN PRODUCCIÓN - 100% Migrado**

Sistema completo de gestión de pedidos integrado con Bold PSE mejorado para procesamiento de pagos.

## ✅ Estado Actual del Sistema

- **Webhook Bold PSE**: ✅ **100% MIGRADO** - `bold_webhook_enhanced.php` activo
- **Bold Dashboard**: ✅ Configurado para usar webhook mejorado
- **Sistema de Pagos**: ✅ Funcionando correctamente
- **SSH Remoto**: ✅ Configurado para VS Code

## 📁 Estructura de Producción

```
/ (PRODUCCIÓN - Solo archivos necesarios)
├── index.php                          # Página principal
├── bold_webhook_enhanced.php           # 🔥 WEBHOOK PRINCIPAL (ACTIVO)
├── dual_mode_config.php               # Configuración webhook (100%)
├── conexion.php                       # Configuración de BD
├── smtp_config.php                    # Configuración SMTP
├── bold_notification_system.php       # Sistema de notificaciones
├── bold_payment.php                   # Procesamiento de pagos
├── bold_confirmation.php              # Confirmaciones Bold
├── pedidos.css / script.js            # Estilos y JS principales
├── logs/                              # Logs del sistema
├── comprobantes/                      # Comprobantes generados
├── uploads/                           # Archivos subidos
├── guias/                             # Guías de envío
└── development/                       # 📂 ARCHIVOS DE DESARROLLO
    ├── testing/                       # Scripts de testing
    ├── monitoring/                    # Scripts de monitoreo
    ├── migration/                     # Archivos de migración
    ├── debugging/                     # Herramientas de debug
    └── documentation/                 # Documentación técnica
```

## 🔧 Archivos Críticos de Producción

**⚠️ NO MOVER NI MODIFICAR estos archivos:**

1. **`bold_webhook_enhanced.php`** - Webhook principal activo
2. **`dual_mode_config.php`** - Configuración al 100%
3. **`conexion.php`** - Conexión BD
4. **`smtp_config.php`** - Config SMTP
5. **`bold_notification_system.php`** - Notificaciones

## 🛠️ Desarrollo y Testing

Todos los archivos de desarrollo están organizados en `/development/`:

- **Testing**: Scripts de pruebas en `development/testing/`
- **Monitoreo**: Scripts de supervisión en `development/monitoring/`
- **Documentación**: Docs técnicas en `development/documentation/`

Ver `development/README.md` para más detalles.

## 🚀 Estado de Migración

✅ **MIGRACIÓN COMPLETADA AL 100%**
- Bold Dashboard → `bold_webhook_enhanced.php`
- Sistema procesando pagos correctamente
- No hay tráfico al distributor antiguo
- SSH configurado para pruebas remotas

## 📞 Configuración Remota

SSH configurado en VS Code para pruebas remotas:
```
Host nombre-servidor
    HostName IP-del-servidor
    User usuario
    Port 22
```

## ⚠️ Importante

**PRODUCCIÓN**: Usar solo archivos en la raíz del proyecto
**DESARROLLO**: Usar archivos en `/development/`

El sistema está 100% operativo con el webhook mejorado.

## 🎯 Resumen del Proyecto

El sistema Bold PSE mejorado para Sequoia Speed implementa tres optimizaciones principales:

1. **Manejo robusto de errores en webhooks** con retry logic inteligente
2. **Experiencia de usuario optimizada** durante el proceso de pago
3. **Sistema de notificaciones automatizado** con templates personalizados

## 🏗️ Arquitectura del Sistema

### Componentes Principales

#### 1. Sistema de Webhooks Mejorado
- **`bold_webhook_enhanced.php`**: Procesador principal con retry logic
- **`bold_webhook_monitor.php`**: Monitor en tiempo real
- **`bold_retry_processor.php`**: Procesador de webhooks fallidos

#### 2. Sistema de Notificaciones
- **`bold_notification_system.php`**: Manejo de emails y WhatsApp
- **Templates HTML**: Notificaciones personalizadas por tipo de evento

#### 3. Mejoras de UX
- **`payment_ux_enhanced.js`**: Lógica de experiencia de usuario
- **`payment_ux_enhanced.css`**: Estilos y animaciones

#### 4. Herramientas de Migración
- **`migration_gradual.php`**: Migración gradual del sistema
- **`setup_enhanced_webhooks.php`**: Configuración de base de datos
- **`setup_cron_jobs.php`**: Configuración de tareas automáticas

## 🗄️ Estructura de Base de Datos

### Tablas Nuevas

#### `bold_retry_queue`
Cola de webhooks fallidos para reprocesamiento.

```sql
CREATE TABLE bold_retry_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    webhook_data TEXT NOT NULL,
    error_message TEXT,
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 5,
    next_retry TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### `bold_webhook_logs`
Registro detallado de todos los webhooks procesados.

```sql
CREATE TABLE bold_webhook_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    webhook_data TEXT,
    processing_result TEXT,
    status ENUM('success', 'warning', 'error') DEFAULT 'success',
    processing_time_ms INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### `notification_logs`
Registro de notificaciones enviadas.

```sql
CREATE TABLE notification_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_email VARCHAR(255),
    notification_type ENUM('success', 'pending', 'failure', 'admin'),
    transaction_id VARCHAR(100),
    subject VARCHAR(255),
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    error_message TEXT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Campos Agregados a `pedidos_detal`

```sql
ALTER TABLE pedidos_detal ADD COLUMN retry_count INT DEFAULT 0;
ALTER TABLE pedidos_detal ADD COLUMN last_webhook_at TIMESTAMP NULL;
ALTER TABLE pedidos_detal ADD COLUMN bold_transaction_id VARCHAR(100) NULL;
ALTER TABLE pedidos_detal ADD COLUMN bold_response TEXT NULL;
```

## 🔧 Configuración e Instalación

### 1. Configuración Inicial

```bash
# 1. Ejecutar setup de base de datos
php setup_enhanced_webhooks.php

# 2. Configurar permisos de directorios
chmod 755 logs/
chmod 755 backups/

# 3. Verificar configuración SMTP en smtp_config.php
```

### 2. Configuración de Cron Jobs

```bash
# Abrir crontab
crontab -e

# Agregar las siguientes líneas:
# Procesador de retry cada 5 minutos
*/5 * * * * cd /path/to/pedidos && php bold_retry_processor.php --cron >> logs/cron_retry.log 2>&1

# Limpieza de logs diarios a las 2 AM
0 2 * * * cd /path/to/pedidos && php database_maintenance.php >> logs/cron_cleanup.log 2>&1

# Mantenimiento semanal domingos a las 3 AM
0 3 * * 0 cd /path/to/pedidos && php database_maintenance.php >> logs/cron_maintenance.log 2>&1
```

### 3. Migración Gradual

```bash
# Opción 1: Interfaz web
http://localhost/pedidos/migration_gradual.php

# Opción 2: Línea de comandos
php migration_gradual.php
```

## 🚀 Uso del Sistema

### Webhooks

#### Configuración en Bold Dashboard
1. **URL de Webhook**: `https://tudominio.com/pedidos/bold_webhook_enhanced.php`
2. **Método**: POST
3. **Eventos**: Todos los eventos de pago (SALE_APPROVED, SALE_REJECTED, etc.)

#### Eventos Soportados
- `SALE_APPROVED`: Pago aprobado
- `SALE_REJECTED`: Pago rechazado
- `SALE_PENDING`: Pago pendiente
- `VOID_APPROVED`: Anulación aprobada
- `REFUND_APPROVED`: Reembolso aprobado

### Monitoreo

#### Dashboard en Tiempo Real
```
http://localhost/pedidos/bold_webhook_monitor.php
```

Características:
- Estadísticas del día actual
- Cola de retry en tiempo real
- Logs recientes con filtros
- Auto-refresh cada 30 segundos
- Gráficos de los últimos 7 días

#### Procesador de Retry
```
http://localhost/pedidos/bold_retry_processor.php
```

Funcionalidades:
- Procesamiento manual de cola
- Estadísticas de retry
- Limpieza de elementos antiguos
- Monitoreo de performance

### Notificaciones

#### Tipos de Notificación

1. **Éxito de Pago**
   - Enviada al cliente
   - Incluye detalles del pedido y transacción
   - Instrucciones de seguimiento

2. **Pago Pendiente**
   - Notifica estado de procesamiento
   - Tiempo estimado de confirmación

3. **Pago Fallido**
   - Enviada al cliente y administrador
   - Opciones de reintento
   - Información de contacto

4. **Notificaciones Administrativas**
   - Resumen de transacciones
   - Alertas de errores
   - Estadísticas diarias

## 🔍 Troubleshooting

### Problemas Comunes

#### 1. Webhooks no se procesan
```bash
# Verificar logs
tail -f logs/bold_webhook.log
tail -f logs/bold_errors.log

# Revisar cola de retry
SELECT * FROM bold_retry_queue WHERE attempts < max_attempts;
```

#### 2. Notificaciones no se envían
```bash
# Verificar configuración SMTP
php -r "require 'smtp_config.php'; print_r(get_defined_constants());"

# Revisar logs de notificaciones
SELECT * FROM notification_logs WHERE status = 'failed';
```

#### 3. UX no funciona correctamente
```bash
# Verificar archivos JS/CSS
ls -la payment_ux_enhanced.*

# Revisar consola del navegador
# Verificar errores de JavaScript
```

### Logs y Debugging

#### Archivos de Log
- `logs/bold_webhook.log`: Logs normales de webhooks
- `logs/bold_errors.log`: Errores críticos
- `logs/migration.log`: Logs de migración
- `logs/dual_mode.log`: Logs del modo dual
- `logs/cron_*.log`: Logs de tareas automáticas

#### Niveles de Log
- **INFO**: Operaciones normales
- **WARNING**: Situaciones que requieren atención
- **ERROR**: Errores críticos que requieren intervención

## 📊 Métricas y Performance

### KPIs del Sistema

1. **Tasa de Éxito de Webhooks**: % de webhooks procesados exitosamente
2. **Tiempo de Procesamiento**: Tiempo promedio de procesamiento
3. **Tasa de Retry**: % de webhooks que requieren reprocesamiento
4. **Tiempo de Recuperación**: Tiempo promedio para procesar webhooks fallidos

### Consultas Útiles

```sql
-- Estadísticas del día
SELECT 
    status,
    COUNT(*) as count,
    AVG(processing_time_ms) as avg_time
FROM bold_webhook_logs 
WHERE DATE(created_at) = CURDATE()
GROUP BY status;

-- Webhooks fallidos por día
SELECT 
    DATE(created_at) as date,
    COUNT(*) as failed_count
FROM bold_retry_queue
GROUP BY DATE(created_at)
ORDER BY date DESC;

-- Performance de notificaciones
SELECT 
    notification_type,
    status,
    COUNT(*) as count
FROM notification_logs
WHERE DATE(sent_at) = CURDATE()
GROUP BY notification_type, status;
```

## 🔒 Seguridad

### Medidas Implementadas

1. **Validación de Entrada**
   - Sanitización de datos con `htmlspecialchars()` y `strip_tags()`
   - Validación de formato de order_id
   - Verificación de estructura de datos

2. **Logging de Seguridad**
   - Registro de IPs de origen
   - Tracking de intentos fallidos
   - Detección de patrones sospechosos

3. **Protección de Archivos**
   - Logs fuera del directorio web
   - Permisos restrictivos en archivos sensibles
   - Backup cifrado de datos críticos

### Recomendaciones de Seguridad

1. **Configurar HTTPS** para todas las comunicaciones
2. **Implementar autenticación** para paneles administrativos
3. **Monitorear logs** regularmente por actividad sospechosa
4. **Mantener backups** actualizados y seguros
5. **Actualizar dependencias** regularmente

## 🚀 Próximas Mejoras

### Roadmap de Desarrollo

#### Versión 2.0
- [ ] Dashboard administrativo completo
- [ ] Integración con múltiples proveedores de pago
- [ ] API REST para integraciones externas
- [ ] Sistema de alertas por Slack/Teams

#### Versión 2.1
- [ ] Machine Learning para detección de fraude
- [ ] Análisis predictivo de fallos
- [ ] Optimización automática de retry intervals
- [ ] Integración con sistemas de inventario

#### Versión 2.2
- [ ] Microservicios architecture
- [ ] Containerización con Docker
- [ ] Escalabilidad horizontal
- [ ] Procesamiento asíncrono con colas

## 📞 Soporte

### Contacto Técnico
- **Email**: soporte@sequoiaspeed.com
- **Documentación**: [Wiki interno]
- **Issues**: [Sistema de tickets]

### Recursos Adicionales
- **Bold API Documentation**: https://bold.co/docs
- **PHP Best Practices**: https://www.php-fig.org/
- **MySQL Performance**: https://dev.mysql.com/doc/

---

**Última actualización**: $(date)
**Versión del sistema**: 1.0.0
**Autor**: Sistema de Desarrollo Sequoia Speed
