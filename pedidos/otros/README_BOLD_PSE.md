# 🚀 Integración Bold PSE - Sequoia Speed

## 📋 Resumen de la Implementación

Se ha implementado una integración completa de **Bold PSE** con checkout embebido para el sistema de pedidos de Sequoia Speed, aplicando el tema Apple dark similar a VSCode con botones azules.

## ✅ Archivos Implementados

### 🔧 Archivos Principales
- **`index.php`** - Formulario principal con integración Bold PSE
- **`bold_hash.php`** - Generador seguro de hash de integridad
- **`bold_webhook.php`** - Manejador de webhooks de Bold
- **`procesar_orden.php`** - Procesamiento de órdenes con soporte Bold
- **`bold_confirmation.php`** - Página de confirmación de pagos

### 📊 Base de Datos
- **`setup_bold_db.php`** - Script de configuración de BD
- **`bold_config_example.php`** - Ejemplo de configuración

## 🎯 Funcionalidades Implementadas

### ✨ Checkout Embebido
- ✅ Iframe integrado sin salir del sitio
- ✅ Diseño responsivo Apple dark theme
- ✅ Carga dinámica del botón Bold
- ✅ Manejo de estados de carga

### 🔒 Seguridad
- ✅ Hash SHA256 generado en servidor
- ✅ Validación de webhooks
- ✅ Protección de llaves secretas
- ✅ Validación de datos de entrada

### 📱 UX/UI Mejorado
- ✅ Loading states con spinners
- ✅ Mensajes de éxito/error
- ✅ Interfaz consistente con tema VSCode
- ✅ Diseño mobile-first responsive

### 🔄 Flujo Completo
- ✅ Generación de ID único por transacción
- ✅ Precarga de datos del cliente
- ✅ Redirección post-pago
- ✅ Confirmación por webhook
- ✅ Notificaciones por email

## 🛠️ Configuración Requerida

### 1. 🔑 Obtener Llaves de Bold

1. Registrarse en [Bold.co](https://bold.co/)
2. Ir a **Panel de Control → Integraciones → Llaves de API**
3. Copiar:
   - **Llave de Identidad** (pública): `pk_live_...`
   - **Llave Secreta** (privada): `sk_live_...`

### 2. ⚙️ Configurar Archivos

#### Editar `bold_hash.php`:
```php
// Reemplazar estas líneas:
const BOLD_API_KEY = 'TU_LLAVE_DE_IDENTIDAD';
const BOLD_SECRET_KEY = 'TU_LLAVE_SECRETA';

// Con tus llaves reales:
const BOLD_API_KEY = 'pk_live_tu_llave_publica_aqui';
const BOLD_SECRET_KEY = 'sk_live_tu_llave_secreta_aqui';
```

### 3. 🗄️ Configurar Base de Datos

1. Ejecutar: `https://tudominio.com/setup_bold_db.php`
2. Verificar que se crearon las tablas:
   - `pedidos` (con campos Bold)
   - `bold_logs`
   - Campos adicionales en `pedidos_detal`

### 4. 🌐 Configurar Webhooks en Bold

1. En el panel de Bold, ir a **Webhooks**
2. Agregar URL: `https://tudominio.com/bold_webhook.php`
3. Seleccionar eventos:
   - `payment.success`
   - `payment.failed` 
   - `payment.pending`

### 5. 🔧 URLs de Redirección

Configurar en Bold:
- **URL de éxito**: `https://tudominio.com/bold_confirmation.php`
- **URL de error**: `https://tudominio.com/bold_confirmation.php`

## 🚦 Testing

### Ambiente de Pruebas
Bold proporciona llaves de test para desarrollo:
- Usar llaves que empiecen con `pk_test_` y `sk_test_`
- Documentación: [Bold Testing](https://developers.bold.co/pagos-en-linea/boton-de-pagos/ambiente-pruebas)

### Flujo de Prueba
1. Seleccionar "PSE Bold" como método de pago
2. Completar formulario
3. Verificar carga del checkout embebido
4. Simular pago en ambiente de pruebas
5. Verificar webhook y confirmación

## 📊 Monitoreo

### Logs Disponibles
- **Error log PHP**: Errores del servidor
- **Tabla `bold_logs`**: Eventos de webhooks
- **Emails**: Notificaciones de nuevas órdenes

### Estados de Pago
- **`pendiente`**: Pago iniciado, esperando confirmación
- **`pagado`**: Pago confirmado exitosamente
- **`fallido`**: Pago rechazado o fallido
- **`cancelado`**: Pago cancelado por usuario

## 🎨 Tema Visual

### Colores Aplicados
```css
--vscode-bg: #1e1e1e;        /* Fondo principal */
--vscode-sidebar: #252526;    /* Contenedores */
--vscode-border: #3e3e42;     /* Bordes */
--vscode-text: #cccccc;       /* Texto principal */
--apple-blue: #007aff;        /* Botones y acentos */
```

### Componentes Estilizados
- ✅ Formularios con tema dark
- ✅ Botones azul Apple
- ✅ Loading states animados
- ✅ Mensajes de estado
- ✅ Responsive design

## 🔍 Troubleshooting

### Problemas Comunes

1. **Botón Bold no aparece**
   - Verificar llaves de API en `bold_hash.php`
   - Revisar consola del navegador para errores
   - Confirmar que el script de Bold se carga

2. **Hash de integridad inválido**
   - Verificar que la llave secreta es correcta
   - Confirmar orden de concatenación: `{id}{amount}{currency}{secret}`
   - Revisar que el monto no tenga decimales

3. **Webhook no funciona**
   - Verificar URL del webhook en panel Bold
   - Confirmar que el servidor acepta POST requests
   - Revisar logs en `bold_logs` table

### Debug Mode
Habilitar en `bold_hash.php`:
```php
// Para debugging temporal
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## 📱 Contacto y Soporte

- **Email Ventas**: ventas@sequoiaspeed.com.co
- **WhatsApp**: +57 314 216 2979
- **Soporte Bold**: [developers.bold.co](https://developers.bold.co/)

## 🔄 Próximas Mejoras

- [ ] Dashboard de administración para gestionar pagos
- [ ] Reportes de transacciones Bold
- [ ] Integración con sistema de inventario
- [ ] Notificaciones push en tiempo real
- [ ] Múltiples métodos de pago Bold (tarjetas, Nequi, etc.)

---

## 📄 Documentación Bold

- [Documentación Oficial](https://developers.bold.co/pagos-en-linea/boton-de-pagos/integracion-manual)
- [Embedded Checkout](https://developers.bold.co/pagos-en-linea/boton-de-pagos/integracion-manual/integracion-manual#7-embedded-checkout)
- [Webhook Events](https://developers.bold.co/webhook)

---

**🎉 Implementación completada por GitHub Copilot**
*Fecha: 25 de mayo de 2025*
