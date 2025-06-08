# Organización de Pruebas - Sistema de Pedidos

## Estructura de Directorios

### `/tests/unit/`
Pruebas unitarias que verifican el funcionamiento de funciones y métodos individuales.
- **`debug/`** - Archivos de debug y diagnóstico de errores específicos
- **`migration/`** - Pruebas de migración de datos y estructura de BD
- Pruebas de conexiones a base de datos
- Validación de funciones de procesamiento
- Pruebas de utilidades y helpers

### `/tests/integration/`
Pruebas de integración que verifican la interacción entre diferentes componentes.
- **`bold/`** - Integración completa con sistema de pagos Bold
- **`email/`** - Pruebas de sistema de correo y adjuntos
- **`database/`** - Pruebas de integración con base de datos
- Flujos completos de procesamiento entre sistemas

### `/tests/functional/`
Pruebas funcionales que verifican el comportamiento desde la perspectiva del usuario.
- **`flows/`** - Flujos completos de pedidos y verificaciones
- **`ui/`** - Pruebas de interfaz de usuario y experiencia
- Validación de webhooks y notificaciones
- Pruebas de casos de uso completos

### `/tests/fixtures/`
Datos de prueba reutilizables.
- **`logs/`** - Archivos de log para análisis de pruebas
- Datos de ejemplo para pedidos, clientes y productos
- Configuraciones de prueba y webhooks
- Mocks y stubs

### `/tests/development/`
Herramientas y archivos de desarrollo.
- **`scripts/`** - Scripts de mantenimiento y fixes
- **`monitoring/`** - Herramientas de monitoreo y seguimiento
- **`docs/`** - Documentación de desarrollo y respaldos
- **`legacy/`** - Código obsoleto mantenido por referencia
- **`backups/`** - Respaldos de versiones anteriores

## Convenciones de Nomenclatura

- **Archivos PHP**: `test_[funcionalidad].php`
- **Archivos HTML**: `test_[funcionalidad].html`
- **Archivos de configuración**: `config_test_[tipo].php`

## Ejecución de Pruebas

Para ejecutar las pruebas:
```bash
# Todas las pruebas
php tests/run_all_tests.php

# Pruebas específicas
php tests/unit/test_conexion.php
php tests/integration/test_bold_integration.php
```

## Notas Importantes

- Las pruebas no deben afectar datos de producción
- Usar datos de prueba ficticios
- Mantener las pruebas actualizadas con los cambios del código
