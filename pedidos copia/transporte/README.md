# 🚚 VitalCarga - Sistema de Transporte

## 📋 Descripción
Sistema completo de gestión de entregas para transportistas, optimizado para dispositivos móviles con interfaz táctil.

## 🎯 Estructura de la Tabla Principal

### 📊 Columnas de la Tabla:

1. **📦 ID** - Identificador único del pedido
2. **📅 Fecha** - Fecha y hora de creación
3. **👤 Cliente** - Nombre, teléfono y email del cliente
4. **📍 Dirección** - Dirección completa de entrega
5. **💰 Recaudo** - Indicador de pago contra entrega
6. **⏱️ Tiempo** - Semáforo de urgencia y tiempo transcurrido
7. **📦 Guía** - Botón para cargar guía de envío
8. **📞 Llamar** - Botón para llamar al cliente
9. **💬 WhatsApp** - Botón para contactar por WhatsApp
10. **🗺️ Maps** - Botón para ver ubicación en Google Maps
11. **🚚 Estado** - Botón para cambiar estado de entrega
12. **📝 Notas** - Botón para agregar notas del transportista
13. **⏰ Programar** - Botón para programar entrega
14. **📸 Foto** - Botón para subir foto de entrega

## 🎨 Características de la Interfaz

### 🔘 Botones de Acción:
- **Diseño icónico**: Solo íconos para máxima eficiencia
- **Tamaño táctil**: 36x36px optimizado para dedos
- **Colores distintivos**: Cada acción tiene su color único
- **Efectos hover**: Animaciones suaves al pasar el mouse
- **Responsive**: Se adapta a diferentes tamaños de pantalla

### 📱 Optimización Móvil:
- **Interfaz táctil**: Botones grandes y espaciados
- **Scroll horizontal**: Tabla se adapta a pantallas pequeñas
- **Tamaños escalables**: Botones se reducen en móviles
- **Tipografía legible**: Texto optimizado para cada dispositivo

## 🎯 Funcionalidades por Botón

### 📦 Cargar Guía
- Abre modal para subir guía de envío
- Campos: número de guía, transportadora, foto
- Envía email automático al cliente
- Marca pedido como enviado

### 📞 Llamar
- Abre app de teléfono del dispositivo
- Protocolo `tel:` para llamada directa
- Funciona en móviles y dispositivos con telefonía

### 💬 WhatsApp
- Abre WhatsApp con mensaje preformateado
- Mensaje personalizado con datos del pedido
- Detecta WhatsApp Web o app móvil

### 🗺️ Maps
- Abre Google Maps con la dirección
- Busca ubicación exacta del cliente
- Funciona en navegador y app móvil

### 🚚 Estado
- Cambiar estado: En ruta, Entregado, Reintento, Devuelto
- Actualiza base de datos automáticamente
- Registra historial de cambios

### 📝 Notas
- Agregar comentarios del transportista
- Guardar observaciones de entrega
- Historial de notas por pedido

### ⏰ Programar
- Agendar fecha y hora de entrega
- Notificar cliente y oficina
- Manejar reprogramaciones

### 📸 Foto
- Subir foto de entrega desde cámara
- Almacenamiento en servidor
- Evidencia de entrega completada

## 🔧 Configuración

### Requisitos:
- PHP 7.4+
- MySQL 5.7+
- Extensiones PHP: mysqli, gd, fileinfo
- Navegador moderno con soporte HTML5

### Instalación:
1. Ejecutar script SQL: `vitalcarga_updates.sql`
2. Configurar permisos de carpeta `uploads/`
3. Verificar rutas relativas en archivos PHP
4. Probar funcionalidades en dispositivo móvil

## 📊 Semáforo de Urgencia

### 🟢 Verde (Normal):
- Pedidos de menos de 24 horas
- Sin urgencia especial
- Proceso normal de entrega

### 🟡 Amarillo (Atención):
- Pedidos entre 24-48 horas
- Requiere seguimiento
- Prioridad media

### 🔴 Rojo (Urgente):
- Pedidos de más de 48 horas
- Máxima prioridad
- Animación pulsante
- Requiere acción inmediata

## 📱 Responsive Design

### 💻 Desktop (1200px+):
- Tabla completa visible
- Botones 36x36px
- Todas las columnas mostradas

### 📱 Tablet (768px - 1200px):
- Botones 32x32px
- Columnas optimizadas
- Scroll horizontal si necesario

### 📱 Mobile (< 768px):
- Botones 28x28px
- Columnas comprimidas
- Texto más pequeño
- Interfaz táctil optimizada

## 🔔 Notificaciones

### Push Notifications:
- Nuevos pedidos asignados
- Cambios de estado
- Recordatorios de entrega

### Email Automático:
- Cliente notificado al cargar guía
- Información de transportadora
- Número de seguimiento

## 📈 Estadísticas

### Dashboard incluye:
- Entregas del día
- Rendimiento semanal/mensual
- Gráficos de tendencias
- Métricas por transportista

## 🔒 Seguridad

### Validaciones:
- Sanitización de datos
- Protección CSRF
- Validación de archivos
- Permisos de usuario

## 🚀 Rendimiento

### Optimizaciones:
- Queries optimizadas
- Índices de base de datos
- Carga asíncrona
- Caché de navegador

---

## 📞 Soporte

Para soporte técnico o consultas sobre el sistema, contactar al equipo de desarrollo.

**Versión:** 2.0  
**Última actualización:** Enero 2025  
**Compatibilidad:** Todos los navegadores modernos