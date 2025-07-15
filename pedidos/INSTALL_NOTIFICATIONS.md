# 🔔 INSTALACIÓN DEL SISTEMA DE NOTIFICACIONES

## 📋 PASOS DE INSTALACIÓN

### 1. **Crear Tablas en Base de Datos**
```sql
-- Ejecutar el archivo SQL
mysql -u [usuario] -p [base_de_datos] < create_notifications_table.sql
```

### 2. **Verificar Estructura de Archivos**
```
pedidos/
├── notifications/
│   ├── notifications.css          ✅ Estilos modernos
│   ├── notifications.js           ✅ Cliente JavaScript
│   ├── notifications.php          ✅ API backend
│   ├── notifications_sse.php      ✅ Server-Sent Events
│   ├── notification_helpers.php   ✅ Funciones auxiliares
│   └── include_notifications.php  ✅ Include helper
├── create_notifications_table.sql ✅ Script de BD
└── INSTALL_NOTIFICATIONS.md       ✅ Este archivo
```

### 3. **Archivos Actualizados**
- ✅ `subir_comprobante.php` - Notificaciones en lugar de emails internos
- ✅ `subir_guia.php` - Notificaciones en lugar de emails internos  
- ✅ `listar_pedidos.php` - Sistema incluido
- 🔄 **Pendientes de actualizar:**
  - `procesar_orden.php` - Para nuevo pedido
  - `actualizar_estado.php` - Para cambios de estado
  - `agregar_comentario_cliente.php` - Para comentarios
  - Cualquier otro archivo que envíe emails internos

### 4. **Configuración del Servidor**
```apache
# .htaccess - Permitir SSE
<Files "notifications_sse.php">
    Header set Cache-Control "no-cache"
    Header set Connection "keep-alive"
    Header set Content-Type "text/event-stream"
</Files>
```

### 5. **Verificar Permisos**
```bash
chmod 644 notifications/*.css
chmod 644 notifications/*.js
chmod 755 notifications/*.php
```

## 🔧 CÓMO USAR

### En archivos PHP:
```php
// Incluir helpers
require_once 'notifications/notification_helpers.php';

// Crear notificaciones
notificarNuevoPedido($pedido_id, $nombre_cliente, $monto);
notificarPagoConfirmado($pedido_id, $monto, $metodo_pago);
notificarGuiaAdjuntada($pedido_id, $numero_guia);
notificarComprobanteSubido($pedido_id, 'pago');
notificarCambioEstado($pedido_id, 'pendiente', 'enviado');
notificarPedidoAnulado($pedido_id, $razon);
```

### En archivos HTML/JS:
```html
<!-- Incluir CSS y JS -->
<link rel="stylesheet" href="notifications/notifications.css">
<script src="notifications/notifications.js"></script>

<!-- Crear notificación desde JS -->
<script>
showNotification('success', 'Operación Exitosa', 'El pedido se guardó correctamente');
</script>
```

## ⚠️ IMPORTANTE

### ✅ EMAILS QUE SE MANTIENEN (a clientes):
- Confirmación de pedido
- Pago confirmado
- Guía de envío adjuntada
- Anulación de pedido
- Cualquier comunicación con el cliente

### ❌ EMAILS QUE SE REEMPLAZAN (internos):
- joshua@...
- jorge@...
- ventas@sequoiaspeed.com.co
- comercial@sequoiaspeed.com.co

## 🔍 ARCHIVOS PENDIENTES DE REVISAR

Buscar emails internos en estos archivos y reemplazar con notificaciones:

```bash
# Buscar emails internos
grep -r "joshua\|jorge\|ventas@" *.php

# Archivos que probablemente necesiten actualización:
# - procesar_orden.php (nuevo pedido)
# - actualizar_estado.php (cambios de estado)
# - bold/bold_payment_*.php (pagos)
```

## 🚀 VERIFICAR FUNCIONAMIENTO

1. **Abrir** `listar_pedidos.php`
2. **Verificar** que aparece el contenedor de notificaciones
3. **Subir** un comprobante o guía
4. **Ver** notificación aparecer en tiempo real
5. **Confirmar** que NO se envían emails internos
6. **Confirmar** que SÍ se envían emails a clientes

## 📱 CARACTERÍSTICAS

- ✅ Tiempo real con Server-Sent Events
- ✅ Notificaciones persistentes en BD
- ✅ Auto-dismiss configurable
- ✅ Sonidos opcionales
- ✅ Tema oscuro
- ✅ Responsive
- ✅ Acciones personalizadas
- ✅ Sin afectar emails a clientes