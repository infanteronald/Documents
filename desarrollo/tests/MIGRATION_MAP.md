# 📁 Mapa de Ubicación de Archivos - Migración desde /otros/

## Archivos Organizados por Categoría

### 🔗 Integración con Bold (`/tests/integration/bold/`)
- `bold_*.php` - Configuraciones y pruebas de Bold
- `test_bold_*.php` - Pruebas específicas de Bold
- `test_bold_*.html` - Interfaces de prueba de Bold
- `bold_webhook_simulator.html` - Simulador de webhooks
- `simple_webhook_test.php` - Pruebas básicas de webhook
- `direct_webhook_test.php` - Pruebas directas de webhook
- `remote_webhook_test.php` - Pruebas remotas de webhook
- `diagnostico_simple_bold.php` - Diagnósticos de Bold

### 📧 Integración de Email (`/tests/integration/email/`)
- `test_email*.php` - Pruebas de sistema de correo
- `test_fix_adjuntos.php` - Correcciones de adjuntos
- `demo_adjuntos.php` - Demo de adjuntos

### 🗄️ Integración de Base de Datos (`/tests/integration/database/`)
- `verificar_bd.php` - Verificación de BD
- `test_db_estructura.html` - Pruebas de estructura
- `test_quick_db.php` - Pruebas rápidas de BD
- `test_productos_tabla.php` - Pruebas de tabla productos
- `investigar_productos_pk.php` - Investigación de claves primarias
- `verificar_conexion_remota.php` - Verificación de conexión remota

### 🐛 Debug y Diagnóstico (`/tests/unit/debug/`)
- `debug_*.php` - Archivos de debug específicos
- `diagnosticar_error.php` - Diagnóstico general de errores
- `pattern_debug.php` - Debug de patrones

### 🔄 Migración de Datos (`/tests/unit/migration/`)
- `migration_*.php` - Scripts de migración
- `setup_*.php` - Scripts de configuración
- `verificar_migracion_bold.php` - Verificación de migración Bold
- `agregar_*.php` - Scripts para agregar campos/datos
- `*.sql` - Scripts de base de datos

### 🎯 Flujos Funcionales (`/tests/functional/flows/`)
- `test_flujo_*.php` - Pruebas de flujos completos
- `test_flujo_*.html` - Interfaces de flujos
- `test_system_integration.php` - Integración de sistemas
- `verificar_pedido_*.php` - Verificación de pedidos
- `test_pedido_*.php` - Pruebas de pedidos específicos
- `test_orden_*.php` - Pruebas de órdenes
- `verificacion_final_orden_pedido.php` - Verificación final

### 🎨 Interfaz de Usuario (`/tests/functional/ui/`)
- `test_pedido*.html` - Pruebas de UI de pedidos
- `test_index_*.html` - Pruebas de página principal
- `test_simple.html` - Pruebas simples de UI
- `test_productos_personalizados.html` - UI de productos personalizados

### 🛠️ Scripts de Desarrollo (`/tests/development/scripts/`)
- `*.sh` - Scripts de shell para desarrollo
- `index_fixed*.php` - Versiones corregidas de archivos
- `orden_pedido_fixed.php` - Corrección de orden de pedido
- `crear_pedido_inicial.php` - Creación inicial de pedidos
- `limpiar_tabla_pedidos.php` - Limpieza de datos

### 📊 Monitoreo (`/tests/development/monitoring/`)
- `monitor_*.php` - Scripts de monitoreo
- `dual_mode_*.php` - Monitoreo de modo dual
- `check_*.php` - Verificaciones automáticas
- `remote_webhook_monitor.php` - Monitor de webhooks remotos
- `ssh_remote_verification.php` - Verificación SSH remota

### 📚 Documentación (`/tests/development/docs/`)
- `*.md` - Toda la documentación de desarrollo
- `backup_26-05-2025_07am.zip` - Respaldo del 26 de mayo

### 🗂️ Legacy y Respaldos (`/tests/development/legacy/` y `/tests/development/backups/`)
- `sequoiaspeed.com.co/` - Archivos del sitio legacy
- `development 2/` - Versión anterior de development
- `pedidos_old 2/` - Respaldo completo anterior

### 📊 Fixtures y Datos (`/tests/fixtures/`)
- `*.json` - Archivos de configuración
- `logs/` - Archivos de log históricos
  - `debug.log`
  - `diagnostic.log`
  - `error.log`
  - `error_log`

## Comandos de Navegación Rápida

```bash
# Ir a pruebas de Bold
cd tests/integration/bold/

# Ir a pruebas de email
cd tests/integration/email/

# Ir a pruebas de base de datos
cd tests/integration/database/

# Ir a archivos de debug
cd tests/unit/debug/

# Ir a scripts de migración
cd tests/unit/migration/

# Ir a pruebas de flujos
cd tests/functional/flows/

# Ir a pruebas de UI
cd tests/functional/ui/

# Ir a scripts de desarrollo
cd tests/development/scripts/

# Ir a herramientas de monitoreo
cd tests/development/monitoring/

# Ir a documentación
cd tests/development/docs/
```

## Estadísticas de Migración

- **Total de archivos movidos**: ~80+ archivos
- **Directorios organizados**: 3 directorios principales migrados
- **Categorías creadas**: 13 subcategorías específicas
- **Estado del directorio /otros/**: ✅ Completamente vacío y organizado

---
*Migración completada el 8 de junio de 2025*
