# 🚀 INSTALACIÓN LAMP STACK COMPLETADA

## ✅ Componentes Instalados

### Apache HTTP Server
- **Versión**: 2.4.65 (Homebrew)
- **Ubicación**: /opt/homebrew/etc/httpd/
- **Document Root**: /opt/homebrew/var/www/
- **Puerto**: 8080 (configurado para funcionar sin sudo)

### PHP 8.3
- **Versión**: PHP 8.3.x
- **Ubicación**: /opt/homebrew/opt/php@8.3/
- **Configuración**: /opt/homebrew/etc/php/8.3/php.ini
- **Extensiones**: mysqli, pdo, pdo_mysql, json, mbstring, curl, gd, openssl

### MySQL
- **Versión**: MySQL 9.3.0 (Homebrew)
- **Socket**: /tmp/mysql.sock
- **Puerto**: 3306
- **Usuario**: root (sin contraseña por defecto)

## 🧪 Archivos de Prueba Creados

### 1. Script de Prueba Principal
**Archivo**: `lamp_test_simple.php`
**URL**: http://localhost:8000/lamp_test_simple.php

Características:
- ✅ Verifica PHP version y configuración
- ✅ Prueba conexión a MySQL
- ✅ Lista extensiones PHP instaladas
- ✅ Muestra información del servidor
- ✅ Ejecuta pruebas SQL básicas
- ✅ Interfaz moderna y responsive

### 2. Script de Inicio Automático
**Archivo**: `start_lamp.sh`

Funciones:
- Inicia MySQL si no está ejecutándose
- Inicia servidor PHP en puerto disponible
- Verifica servicios
- Muestra URLs de acceso

## 🛠️ Comandos Útiles

### Gestión de Servicios

#### MySQL
```bash
# Iniciar MySQL
brew services start mysql

# Detener MySQL  
brew services stop mysql

# Conectar a MySQL
mysql -u root

# Estado del servicio
brew services list | grep mysql
```

#### Apache (Opcional)
```bash
# Iniciar Apache
brew services start httpd

# Detener Apache
brew services stop httpd

# URL: http://localhost:8080
```

#### Servidor PHP de Desarrollo
```bash
# Iniciar servidor PHP
php -S localhost:8000

# Con documento root específico
php -S localhost:8000 -t /ruta/al/directorio

# Usar script automático
./start_lamp.sh
```

### Verificación de Estados
```bash
# Ver procesos MySQL
ps aux | grep mysql

# Ver puertos ocupados
lsof -i :3306  # MySQL
lsof -i :8000  # PHP Server
lsof -i :8080  # Apache

# Probar conexión MySQL
mysql -u root -e "SELECT VERSION();"
```

## 🌐 URLs de Acceso

| Servicio | URL | Descripción |
|----------|-----|-------------|
| **Test LAMP** | http://localhost:8000/lamp_test_simple.php | Script de verificación completo |
| **Apache** | http://localhost:8080 | Servidor web Apache (si está configurado) |
| **PHP Info** | http://localhost:8000/phpinfo.php | Información de PHP (crear manualmente) |

## 📁 Estructura de Directorios

### Homebrew LAMP Stack
```
/opt/homebrew/
├── etc/
│   ├── httpd/ (configuración Apache)
│   └── php/8.3/ (configuración PHP)
├── var/
│   ├── www/ (document root Apache)
│   └── mysql/ (datos MySQL)
└── opt/
    ├── httpd/ (binarios Apache)
    ├── php@8.3/ (binarios PHP)
    └── mysql/ (binarios MySQL) 
```

### Configuraciones Importantes
```
Apache Config: /opt/homebrew/etc/httpd/httpd.conf
PHP Config:    /opt/homebrew/etc/php/8.3/php.ini  
MySQL Config:  /opt/homebrew/etc/my.cnf
MySQL Data:    /opt/homebrew/var/mysql/
MySQL Socket:  /tmp/mysql.sock
```

## 🔧 Solución de Problemas

### MySQL no se conecta
```bash
# Verificar si está ejecutándose
ps aux | grep mysql

# Iniciar manualmente
/opt/homebrew/opt/mysql/bin/mysqld_safe --datadir=/opt/homebrew/var/mysql &

# Verificar logs
tail -f /opt/homebrew/var/mysql/*.err
```

### Apache no inicia
```bash
# Verificar configuración
/opt/homebrew/bin/httpd -t

# Ver logs de errores
tail -f /opt/homebrew/var/log/httpd/error_log
```

### PHP no funciona
```bash
# Verificar versión
/opt/homebrew/opt/php@8.3/bin/php --version

# Agregar al PATH (en ~/.zshrc)
export PATH="/opt/homebrew/opt/php@8.3/bin:$PATH"
```

### Puertos ocupados
```bash
# Cambiar puerto del servidor PHP
php -S localhost:8001

# Usar puerto alternativo para Apache
# Editar /opt/homebrew/etc/httpd/httpd.conf
# Cambiar "Listen 8080" por "Listen 8081"
```

## 🚀 Inicio Rápido

### Opción 1: Script Automático
```bash
cd /Users/ronaldinfante/Documents/pedidos
./start_lamp.sh
```

### Opción 2: Manual
```bash
# 1. Iniciar MySQL
brew services start mysql

# 2. Iniciar servidor PHP
php -S localhost:8000

# 3. Abrir navegador
open http://localhost:8000/lamp_test_simple.php
```

## ✨ Próximos Pasos

1. **Configurar Virtual Hosts** para múltiples sitios
2. **Instalar phpMyAdmin** para administrar MySQL
3. **Configurar SSL/HTTPS** para desarrollo seguro  
4. **Instalar Composer** para manejo de dependencias PHP
5. **Configurar Xdebug** para debugging avanzado

## 📞 Verificación de Instalación

Ejecutar el comando de prueba:
```bash
curl -s http://localhost:8000/lamp_test_simple.php | grep -i "conectado\|php"
```

Resultado esperado: Debe mostrar "✅ Conectado" y la versión de PHP.

---

**🎉 ¡Instalación LAMP completada exitosamente!**

**Fecha**: $(date '+%Y-%m-%d %H:%M:%S')  
**Sistema**: macOS con Homebrew  
**Stack**: Apache 2.4.65 + PHP 8.3.x + MySQL 9.3.0