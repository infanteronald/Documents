# 🧪 Guía de Uso - Sistema de Pruebas

## Estructura Completada

```
tests/
├── README.md                    # Documentación general
├── config_test.php             # Configuración para pruebas
├── run_tests.php               # Script principal para ejecutar pruebas
├── USAGE_GUIDE.md              # Esta guía
├── unit/                       # Pruebas unitarias
│   ├── test_container_fix.html
│   ├── test_function_fix.html
│   ├── test_live_debug.html
│   ├── test_error_corregido.html
│   ├── test_error_corregido_final.html
│   ├── test_error_final_corregido.html
│   └── debug_undefined_return.html
├── integration/                # Pruebas de integración
│   ├── test_bold_complete.php
│   ├── test_final_bold.php
│   └── test_flujo_completo_bold.html
├── functional/                 # Pruebas funcionales
│   ├── test_bold_function.html
│   ├── test_final_solution.html
│   ├── test_produccion_final.html
│   └── verificacion_final.html
└── fixtures/                   # Datos de prueba
    ├── sample_data.php         # Datos de muestra
    ├── webhook_receiver.php    # Receptor de webhooks de prueba
    ├── uploads/.gitkeep
    ├── comprobantes/.gitkeep
    └── logs/.gitkeep
```

## Comandos Principales

### Ejecutar todas las pruebas
```bash
php tests/run_tests.php
# o simplemente
php tests/run_tests.php all
```

### Ejecutar pruebas por tipo
```bash
# Pruebas unitarias únicamente
php tests/run_tests.php unit

# Pruebas de integración únicamente
php tests/run_tests.php integration

# Pruebas funcionales únicamente
php tests/run_tests.php functional
```

### Gestión del entorno
```bash
# Configurar entorno de pruebas (crear directorios, limpiar logs)
php tests/run_tests.php --setup

# Limpiar datos de prueba de la base de datos
php tests/run_tests.php --clean

# Mostrar ayuda
php tests/run_tests.php --help
```

## Tipos de Pruebas

### 🔧 Pruebas Unitarias (`/tests/unit/`)
- **Propósito**: Verificar funciones y métodos individuales
- **Archivos**: Principalmente correcciones de errores y funciones específicas
- **Ejecución**: Automática para archivos PHP, manual para HTML

### 🔗 Pruebas de Integración (`/tests/integration/`)
- **Propósito**: Verificar integración entre componentes (Bold, BD, etc.)
- **Archivos**: Pruebas completas de sistemas de pago y flujos
- **Ejecución**: Automática para archivos PHP, manual para HTML

### 🎯 Pruebas Funcionales (`/tests/functional/`)
- **Propósito**: Verificar comportamiento desde perspectiva del usuario
- **Archivos**: Pruebas de UI y flujos completos de usuario
- **Ejecución**: Principalmente manual (archivos HTML)

## Flujo de Trabajo Recomendado

### 1. Antes de hacer cambios
```bash
# Configurar entorno
php tests/run_tests.php --setup

# Ejecutar pruebas para establecer línea base
php tests/run_tests.php
```

### 2. Después de hacer cambios
```bash
# Ejecutar pruebas relevantes
php tests/run_tests.php unit      # Para cambios en funciones
php tests/run_tests.php integration # Para cambios en integraciones
php tests/run_tests.php functional  # Para cambios en UI

# O ejecutar todas
php tests/run_tests.php all
```

### 3. Limpieza periódica
```bash
# Limpiar datos de prueba acumulados
php tests/run_tests.php --clean
```

## Configuración de Entorno

### Base de Datos de Pruebas
- Crear BD separada: `pedidos_test`
- Configurar credenciales en `config_test.php`
- Los datos se marcan con `created_for_test = 1`

### Servicios Externos
- **Bold**: Usar credenciales de sandbox
- **SMTP**: Usar servicio como Mailtrap para testing
- **Webhooks**: Usar receptor local en `fixtures/webhook_receiver.php`

## Archivos de Configuración

### `config_test.php`
Contiene todas las configuraciones específicas para pruebas:
- Credenciales de BD de prueba
- URLs de sandbox para Bold
- Rutas de archivos temporales
- Configuración de email de prueba

### `fixtures/sample_data.php`
Datos de muestra reutilizables:
- Productos de ejemplo
- Clientes de prueba
- Pedidos de muestra
- Payloads de webhook

## Mejores Prácticas

1. **Separación de datos**: Nunca usar datos de producción en pruebas
2. **Limpieza**: Limpiar datos después de cada sesión de pruebas
3. **Aislamiento**: Cada prueba debe ser independiente
4. **Documentación**: Documentar nuevas pruebas en el README
5. **Versionado**: Mantener las pruebas sincronizadas con el código

## Resolución de Problemas

### Error de conexión a BD
```bash
# Verificar configuración
cat tests/config_test.php

# Crear BD de pruebas si no existe
mysql -u root -p -e "CREATE DATABASE pedidos_test;"
```

### Archivos no encontrados
```bash
# Reconfigurar entorno
php tests/run_tests.php --setup
```

### Logs de debugging
```bash
# Ver logs de webhooks
cat tests/fixtures/logs/webhook_test.log

# Ver logs generales
ls -la tests/fixtures/logs/
```
