# 🔧 Guía de Configuración para Desarrollo Local

## Descripción General

Esta guía explica cómo configurar el entorno de desarrollo local para trabajar con el sistema de pedidos de Sequoia Speed después de la implementación del sistema de seguridad.

## 📋 Requisitos Previos

- PHP 7.4 o superior
- MySQL o MariaDB local
- Servidor web (Apache/Nginx) o PHP built-in server
- Git

## 🚀 Pasos de Configuración

### 1. Clonar el Repositorio

```bash
git clone [URL_DEL_REPOSITORIO]
cd pedidos
```

### 2. Configurar Variables de Entorno

```bash
# Copiar el archivo de ejemplo
cp .env.example .env

# Editar el archivo con tus credenciales locales
nano .env  # o usa tu editor preferido
```

### 3. Configurar Base de Datos Local

Edita el archivo `.env` con tus credenciales locales:

```env
# Configuración de Base de Datos LOCAL
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=pedidos_local
DB_USERNAME=tu_usuario_local
DB_PASSWORD=tu_password_local
DB_CHARSET=utf8mb4
DB_TIMEZONE=America/Bogota

# Configuración de Aplicación para desarrollo
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000

# Configuración de Email (opcional para desarrollo)
MAIL_MAILER=log
MAIL_FROM_ADDRESS=test@localhost
MAIL_FROM_NAME="Sequoia Speed Local"

# Configuración de Notificaciones
NOTIFICATION_ENABLED=false
SSE_ENABLED=false
```

### 4. Crear Base de Datos Local

```sql
-- Conectar a MySQL como root o usuario con privilegios
mysql -u root -p

-- Crear base de datos
CREATE DATABASE pedidos_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Crear usuario (opcional)
CREATE USER 'pedidos_user'@'localhost' IDENTIFIED BY 'password_seguro';
GRANT ALL PRIVILEGES ON pedidos_local.* TO 'pedidos_user'@'localhost';
FLUSH PRIVILEGES;
```

### 5. Importar Estructura de Base de Datos

Solicita al equipo de desarrollo:
- Dump de la estructura de la base de datos (sin datos sensibles)
- O script SQL de creación de tablas

```bash
# Importar estructura
mysql -u tu_usuario -p pedidos_local < estructura_db.sql
```

### 6. Verificar Instalación

```bash
# Ejecutar script de verificación
php verify_security_implementation.php
```

Deberías ver algo como:
```
✅ Archivo .env encontrado
✅ EnvLoader.php encontrado
✅ config_secure.php encontrado
⚠️  No se pudo conectar a la base de datos (verifica credenciales locales)
```

### 7. Iniciar Servidor de Desarrollo

```bash
# Opción 1: PHP built-in server
php -S localhost:8000

# Opción 2: Si usas Laravel Valet, Laragon, XAMPP, etc.
# Configura el directorio del proyecto según tu herramienta
```

## 🛠️ Solución de Problemas Comunes

### Error: "No se pudo conectar a la base de datos"

1. Verifica que MySQL esté ejecutándose:
   ```bash
   # En macOS
   brew services list | grep mysql
   
   # En Linux
   systemctl status mysql
   
   # En Windows
   # Verifica en el panel de servicios
   ```

2. Verifica las credenciales en `.env`

3. Verifica que la base de datos exista:
   ```bash
   mysql -u tu_usuario -p -e "SHOW DATABASES;"
   ```

### Error: "Class 'EnvLoader' not found"

Asegúrate de que el archivo existe:
```bash
ls -la app/config/EnvLoader.php
```

### Error: "Permission denied"

Ajusta los permisos:
```bash
chmod 644 .env
chmod -R 755 app/
```

## 📝 Buenas Prácticas para Desarrollo

1. **Nunca uses credenciales de producción en local**
2. **Mantén `.env` fuera del control de versiones** (ya está en `.gitignore`)
3. **Usa `APP_DEBUG=true` solo en desarrollo**
4. **Desactiva notificaciones email en desarrollo** (`MAIL_MAILER=log`)

## 🔍 Comandos Útiles para Debugging

```bash
# Ver logs de PHP
tail -f /var/log/apache2/error.log  # Linux/Apache
tail -f /usr/local/var/log/php-fpm.log  # macOS/PHP-FPM

# Verificar conexión a base de datos
php -r "
require_once 'config_secure.php';
if (\$conn && !\$conn->connect_error) {
    echo '✅ Conexión exitosa\n';
} else {
    echo '❌ Error de conexión\n';
}
"

# Ver variables de entorno cargadas (sin mostrar valores sensibles)
php -r "
require_once 'app/config/EnvLoader.php';
EnvLoader::load();
echo 'Variables cargadas: ';
print_r(array_keys(\$_ENV));
"
```

## 🚀 Flujo de Trabajo Recomendado

1. **Desarrollo**: Trabaja en tu rama feature
   ```bash
   git checkout -b feature/nueva-funcionalidad
   ```

2. **Pruebas locales**: Verifica que todo funcione
   ```bash
   php verify_security_implementation.php
   ```

3. **Commit y Push**:
   ```bash
   git add .
   git commit -m "feat: descripción del cambio"
   git push origin feature/nueva-funcionalidad
   ```

4. **Deploy**: Usa el script de despliegue
   ```bash
   ./deploy_security_fix.sh
   ```

## 📚 Recursos Adicionales

- [SEGURIDAD_CORREGIDA.md](./SEGURIDAD_CORREGIDA.md) - Documentación completa de seguridad
- [.env.example](./.env.example) - Plantilla de variables de entorno
- [verify_security_implementation.php](./verify_security_implementation.php) - Script de verificación

## ⚠️ Importante

**NUNCA** subas archivos con credenciales reales al repositorio:
- `.env`
- `config.php` con credenciales hardcodeadas
- Dumps de base de datos con datos reales
- Archivos de logs

---

Si tienes problemas, contacta al equipo de desarrollo o revisa la documentación en `SEGURIDAD_CORREGIDA.md`.