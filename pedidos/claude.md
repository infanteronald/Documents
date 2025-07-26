# CLAUDE.md

Este archivo proporciona instrucciones específicas para Claude Code cuando trabaja con el código en este repositorio.

## Resumen del Proyecto

Este repositorio contiene el sistema Sequoia Speed de gestión de pedidos, una plataforma PHP e-commerce para manejar pedidos, inventario, envíos y pagos. El desarrollo principal está en `/Documents/pedidos/` con varias versiones de respaldo en `/Desktop/`.

## Comandos de Desarrollo Comunes

### Configuración Inicial
```bash
# Instalar dependencias PHP (requiere PHP 8.0+)
cd Documents/pedidos
composer install

# Crear directorios necesarios
mkdir -p logs uploads/photos storage cache inventario/uploads/temp inventario/uploads/products reportes/exports

# Establecer permisos apropiados
chmod -R 755 logs uploads storage cache inventario/uploads reportes/exports
```

### Ejecutar la Aplicación

**IMPORTANTE**: Debido a conflictos con .htaccess en el servidor de desarrollo PHP, usa estas URLs específicas:

```bash
# Iniciar servidor de desarrollo (recomendado)
./start_dev_server.sh

# O manualmente:
cd Documents/pedidos
php -S localhost:8000

# URLs para desarrollo:
# - Principal: http://localhost:8000/index.php (NO uses http://localhost:8000/)
# - Login: http://localhost:8000/accesos/login.php
```

**Problema conocido**: `http://localhost:8000/` (raíz) causa errores debido al archivo .htaccess que contiene directivas de Apache no compatibles con el servidor de desarrollo PHP. Siempre accede directamente a `/index.php`.

### Operaciones de Base de Datos
```bash
# Conectar a base de datos MySQL
mysql -u [user] -p [database]

# Ejecutar migraciones (desde directorio del proyecto)
mysql -u [user] -p [database] < accesos/setup_accesos.sql
mysql -u [user] -p [database] < inventario/setup_productos.sql
```

### Operaciones Git
```bash
# Ver estado actual
git status

# Preparar y confirmar cambios
git add .
git commit -m "feat: descripción"

# Ver commits recientes
git log --oneline -5
```

## Arquitectura de Alto Nivel

### Ubicaciones del Proyecto
- **Desarrollo Principal**: `/Users/ronaldinfante/Documents/pedidos/` - Desarrollo principal con CLAUDE.md
- **Versiones de Respaldo**: `/Users/ronaldinfante/Desktop/pedidos_*` - Varios respaldos fechados
- **Ambiente de Pruebas**: `/Users/ronaldinfante/Desktop/pedidos copia/` - Copia de pruebas

### Tecnologías Principales
- **Backend**: PHP 8.0+ con gestión de dependencias Composer
- **Base de Datos**: MySQL/MariaDB
- **Frontend**: JavaScript vanilla, tema oscuro responsivo
- **Tiempo Real**: Server-Sent Events (SSE), notificaciones Web Push
- **Librerías**: minishlink/web-push (notificaciones), endroid/qr-code (generación QR)

### Módulos Clave
- **Autenticación** (`/accesos/`): Control de acceso basado en roles con 6 roles jerárquicos
- **Inventario** (`/inventario/`): Gestión de productos, almacenes y categorías
- **Transporte** (`/transporte/`): Integración VitalCarga para entregas
- **Pagos** (`/bold/`): Integración con pasarela de pagos Bold PSE
- **Reportes** (`/reportes/`): Analíticas con exportación Excel/PDF
- **Notificaciones** (`/notifications/`): Notificaciones push y SSE

### Características de Seguridad
- Protección CSRF en todos los formularios
- Autenticación basada en sesiones
- Jerarquía de roles: super_admin > admin > supervisor > vendedor > bodeguero > transportista
- Registro de auditoría comprensivo
- Validación de entrada y declaraciones preparadas

### Notas de Desarrollo
- Idioma principal: Español
- Moneda: Peso colombiano (COP)
- Zona horaria: America/Bogota (UTC-5)
- Tema UI: Tema oscuro estilo VS Code
- Diseño responsivo mobile-first

## Tareas Comunes

### Probar Integración de Pagos
```bash
# Probar pasarela de pagos Bold
php bold/bold_diagnostico.php

# Monitorear logs de pagos
tail -f logs/bold_*.log
```

### Gestionar Inventario
```bash
# Ejecutar verificación de alertas de inventario
php inventario/verificar_alertas.php

# Procesar tareas diarias
php tareas_diarias.php
```

### Depurar SSE/Notificaciones
```bash
# Monitorear procesos SSE
php notifications/monitor_processes.php

# Limpiar procesos obsoletos
php notifications/cleanup_processes.php
```

## Variables de Ambiente Importantes
Crear archivo `.env` en la raíz del proyecto:
```
DB_HOST=localhost
DB_NAME=tu_base_de_datos
DB_USER=tu_usuario
DB_PASS=tu_contraseña
SMTP_HOST=smtp.gmail.com
SMTP_USER=tu_email
SMTP_PASS=tu_contraseña
VAPID_PUBLIC_KEY=tu_clave
VAPID_PRIVATE_KEY=tu_clave
```

## Solución de Problemas

### Problema: Error 500 al acceder a localhost:8000/
**Causa**: Conflicto del archivo .htaccess con el servidor de desarrollo PHP
**Solución**: 
1. Usa `http://localhost:8000/index.php` en lugar de `http://localhost:8000/`
2. O usa el script `./start_dev_server.sh` que maneja esto automáticamente

### Problema: Error de timezone MySQL
**Causa**: Formato de zona horaria incorrecto
**Solución**: El sistema usa `-05:00` en lugar de `America/Bogota` para compatibilidad con MySQL

### Problema: Permisos de super admin no funcionan
**Causa**: Vista `acc_vista_permisos_usuario` corrupta
**Solución**: Ejecutar `fix_view_final.sql` para recrear la vista

## Credenciales de Desarrollo
- Usuario super admin: `infanteronald`
- La contraseña debe configurarse en la base de datos

## Recordatorios Importantes
- Haz lo que se solicita; nada más, nada menos
- NUNCA crear archivos a menos que sean absolutamente necesarios
- SIEMPRE preferir editar un archivo existente a crear uno nuevo
- NUNCA crear proactivamente archivos de documentación (*.md) o README. Solo crear archivos de documentación si el Usuario lo solicita explícitamente