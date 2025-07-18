# 🔐 Sistema de Accesos - Sequoia Speed

## Descripción

Sistema completo de autenticación y autorización para Sequoia Speed, que proporciona control granular de acceso basado en roles y permisos (RBAC) para todos los módulos del sistema.

## 🚀 Características Principales

### ✅ **Autenticación Segura**
- Login con hash bcrypt
- Sesiones seguras con tokens
- Protección CSRF
- Timeout de sesión configurable
- Limpieza automática de sesiones expiradas

### ✅ **Autorización Granular**
- 6 roles jerárquicos predefinidos
- Permisos CRUD por módulo
- Control granular de acceso
- Middleware de autorización

### ✅ **Auditoría Completa**
- Registro de todas las acciones
- Información detallada de sesiones
- Análisis de user agent
- Actividades relacionadas
- Exportación de auditoría

### ✅ **Interfaz Moderna**
- Tema oscuro consistente con VS Code
- Responsive design
- Notificaciones en tiempo real
- Filtros avanzados
- Paginación eficiente

## 📋 Estructura del Sistema

### **Roles Jerárquicos**
1. **👑 Super Admin** - Acceso total al sistema
2. **👨‍💼 Admin** - Acceso completo excepto configuración crítica
3. **👔 Gerente** - Acceso a ventas, inventario y reportes
4. **👨‍🔧 Supervisor** - Operaciones diarias y reportes básicos
5. **🛒 Vendedor** - Ventas y consulta de inventario
6. **🔍 Consultor** - Solo consulta de información básica

### **Módulos del Sistema**
- **🛒 Ventas** - Gestión de pedidos y ventas
- **📦 Inventario** - Control de productos y stock
- **👥 Usuarios** - Administración de usuarios y accesos
- **📊 Reportes** - Generación de reportes y estadísticas
- **⚙️ Configuración** - Configuración del sistema

### **Permisos CRUD**
- **👁️ Leer** - Permite ver y consultar información
- **➕ Crear** - Permite crear nuevos registros
- **✏️ Actualizar** - Permite modificar registros existentes
- **🗑️ Eliminar** - Permite eliminar registros

## 🗄️ Estructura de Base de Datos

```sql
-- Tablas principales
usuarios              -- Información de usuarios
roles                 -- Definición de roles
modulos               -- Módulos del sistema
permisos              -- Permisos disponibles
usuario_roles         -- Asignación de roles a usuarios
rol_permisos          -- Asignación de permisos a roles

-- Auditoría y sesiones
auditoria_accesos     -- Registro de todas las acciones
sesiones              -- Control de sesiones activas
```

## 📁 Archivos del Sistema

### **Modelos**
- `models/User.php` - Gestión de usuarios
- `models/Role.php` - Gestión de roles
- `models/Permission.php` - Gestión de permisos
- `models/Module.php` - Gestión de módulos

### **Middleware**
- `middleware/AuthMiddleware.php` - Autenticación y autorización

### **Páginas Principales**
- `login.php` - Sistema de login
- `logout.php` - Cierre de sesión
- `dashboard.php` - Panel principal
- `usuarios.php` - Gestión de usuarios
- `roles.php` - Gestión de roles
- `permisos.php` - Gestión de permisos
- `auditoria.php` - Auditoría del sistema

### **Helper de Integración**
- `auth_helper.php` - Funciones helper para integración
- `integration_example.php` - Ejemplos de integración

## 🔧 Instalación

### 1. **Configurar Base de Datos**
```bash
# Ejecutar el script de configuración
mysql -u usuario -p database_name < setup_accesos.sql
```

### 2. **Configurar Constantes**
```php
// Agregar en config_secure.php
define('SEQUOIA_SPEED_SYSTEM', true);
```

### 3. **Usuario Inicial**
- **Email:** admin@sequoiaspeed.com
- **Contraseña:** password (cambiar después del primer login)

## 📖 Guía de Integración

### **Paso 1: Incluir el Helper**
```php
require_once 'accesos/auth_helper.php';
```

### **Paso 2: Proteger Páginas**
```php
// Requerir autenticación y permisos
$current_user = auth_require('ventas', 'leer');
```

### **Paso 3: Verificar Permisos**
```php
// Mostrar botones según permisos
if (auth_can('ventas', 'crear')) {
    echo '<button>➕ Crear Pedido</button>';
}
```

### **Paso 4: Registrar Actividades**
```php
// Registrar acciones en auditoría
auth_log('create', 'ventas', 'Pedido creado: #123');
```

### **Ejemplo Completo de Integración**
```php
<?php
require_once 'accesos/auth_helper.php';

// Proteger página
$user = auth_require('ventas', 'leer');

// Registrar acceso
auth_log('read', 'ventas', 'Acceso a lista de pedidos');

// Verificar CSRF en formularios
if ($_POST) {
    if (!auth_verify_csrf($_POST['csrf_token'])) {
        die('Token CSRF inválido');
    }
    
    if (!auth_can('ventas', 'crear')) {
        die('Sin permisos para crear');
    }
    
    // Procesar formulario...
    auth_log('create', 'ventas', 'Pedido creado');
}

$csrf_token = auth_csrf();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Lista de Pedidos</title>
</head>
<body>
    <!-- Menú de navegación -->
    <?php echo auth_nav_menu('ventas'); ?>
    
    <!-- Información del usuario -->
    <?php echo auth_user_info(); ?>
    
    <!-- Botones según permisos -->
    <?php if (auth_can('ventas', 'crear')): ?>
        <button onclick="crearPedido()">➕ Nuevo Pedido</button>
    <?php endif; ?>
    
    <!-- Formulario con CSRF -->
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <!-- Campos del formulario -->
    </form>
</body>
</html>
```

## 🔧 Funciones Helper Disponibles

### **Autenticación**
```php
auth_check()                    // Verificar si está autenticado
auth_user()                     // Obtener usuario actual
auth_require($module, $perm)    // Requerir autenticación y permisos
auth_logout()                   // Cerrar sesión
```

### **Autorización**
```php
auth_can($module, $permission)  // Verificar permisos
auth_is_admin()                 // Verificar si es admin
auth_csrf()                     // Generar token CSRF
auth_verify_csrf($token)        // Verificar token CSRF
```

### **Auditoría**
```php
auth_log($action, $module, $description)  // Registrar actividad
```

### **Interfaz**
```php
auth_nav_menu($current_module)             // Generar menú
auth_user_info()                           // Info del usuario
auth_action_buttons($module, $id)          // Botones de acción
```

## 🛡️ Seguridad

### **Implementado**
- ✅ Hash bcrypt para contraseñas
- ✅ Protección CSRF
- ✅ Validación de sesiones
- ✅ Timeout de sesión
- ✅ Auditoría completa
- ✅ Validación de permisos
- ✅ Limpieza automática

### **Recomendaciones**
- Cambiar contraseña del admin inicial
- Configurar timeout de sesión apropiado
- Revisar auditoría regularmente
- Mantener roles y permisos actualizados
- Usar HTTPS en producción

## 📊 Auditoría y Monitoreo

### **Información Registrada**
- Usuario que realizó la acción
- Tipo de acción (crear, leer, actualizar, eliminar)
- Módulo afectado
- Descripción detallada
- Fecha y hora exacta
- Dirección IP
- Información del navegador

### **Análisis Disponible**
- Actividades por usuario
- Acciones por módulo
- Intentos de login fallidos
- Sesiones activas
- Actividades relacionadas
- Exportación a CSV

## 🎨 Personalización

### **Temas y Estilos**
El sistema utiliza variables CSS para fácil personalización:
```css
:root {
    --bg-primary: #0d1117;
    --text-primary: #e6edf3;
    --color-primary: #58a6ff;
    /* ... más variables */
}
```

### **Agregar Nuevos Módulos**
```sql
-- Agregar nuevo módulo
INSERT INTO modulos (nombre, descripcion) VALUES ('nuevo_modulo', 'Descripción');

-- Los permisos se crean automáticamente
```

### **Personalizar Roles**
```php
// En models/Role.php, modificar getRoleHierarchy()
```

## 🚀 Próximas Características

- [ ] Autenticación de dos factores (2FA)
- [ ] Integración con LDAP/Active Directory
- [ ] API REST para aplicaciones móviles
- [ ] Dashboard de métricas avanzadas
- [ ] Notificaciones push
- [ ] Backup automático de auditoría

## 📞 Soporte

Para soporte técnico o consultas:
- **Email:** admin@sequoiaspeed.com
- **Sistema:** Módulo de accesos > Auditoría

## 📄 Licencia

Sistema propietario de Sequoia Speed. Todos los derechos reservados.

---

**Desarrollado con ❤️ para Sequoia Speed**