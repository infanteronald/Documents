# 🏪 Módulo de Gestión de Almacenes

## 📋 Descripción

Módulo CRUD completo para la gestión de almacenes en el sistema de inventario Sequoia Speed. Permite crear, leer, actualizar y eliminar almacenes, así como ver el stock detallado por ubicación.

## 🚀 Instalación

### 1. Ejecutar Script de Base de Datos

```sql
-- Ejecutar en phpMyAdmin o cliente MySQL
source setup_almacenes.sql;
```

### 2. Verificar Permisos

Asegurar que el usuario tenga los permisos necesarios:

```sql
-- Verificar permisos del usuario actual
SELECT * FROM permisos WHERE modulo = 'inventario';
```

### 3. Acceder al Módulo

- **URL Principal:** `/inventario/almacenes/`
- **Menú:** Inicio → Inventario → Gestión de Almacenes

## 🗂️ Estructura de Archivos

```
/inventario/almacenes/
├── README.md              # Este archivo
├── setup_almacenes.sql    # Script de instalación
├── index.php             # Listado principal
├── crear.php             # Formulario crear almacén
├── editar.php            # Formulario editar almacén
├── detalle.php           # Vista detallada del almacén
├── procesar.php          # Procesador de operaciones CRUD
├── almacenes.css         # Estilos específicos
└── almacenes.js          # JavaScript funcional
```

## 🎯 Funcionalidades

### ✅ CRUD Completo
- **CREATE:** Crear nuevos almacenes
- **READ:** Listar y ver detalles de almacenes
- **UPDATE:** Editar información de almacenes
- **DELETE:** Eliminar almacenes (solo si no tienen productos)

### 📊 Características Avanzadas
- **Búsqueda en tiempo real** por nombre, descripción o ubicación
- **Filtros** por estado (activo/inactivo)
- **Estadísticas** de productos y stock por almacén
- **Validaciones** de integridad de datos
- **Responsive design** para móviles
- **Integración completa** con sistema de autenticación

## 🔧 Configuración

### Variables de Entorno

El módulo utiliza las configuraciones existentes del sistema:

```php
// config_secure.php
$conn = mysqli_connect($host, $user, $password, $database);
```

### Permisos Requeridos

```php
// Permisos necesarios por acción
'inventario' => [
    'crear' => 'Crear nuevos almacenes',
    'leer' => 'Ver almacenes existentes', 
    'actualizar' => 'Modificar almacenes',
    'eliminar' => 'Eliminar almacenes'
]
```

## 📊 Modelo de Datos

### Tabla Principal: `almacenes`

```sql
CREATE TABLE almacenes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT,
    ubicacion VARCHAR(255),
    capacidad_maxima INT DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Vista: `vista_almacenes_productos`

```sql
-- Vista para consultas optimizadas
CREATE VIEW vista_almacenes_productos AS
SELECT 
    a.id, a.nombre as almacen, a.descripcion, a.ubicacion,
    COUNT(p.id) as total_productos,
    SUM(p.stock_actual) as stock_total,
    SUM(CASE WHEN p.stock_actual <= p.stock_minimo THEN 1 ELSE 0 END) as productos_criticos
FROM almacenes a
LEFT JOIN productos p ON a.nombre = p.almacen AND p.activo = 1
GROUP BY a.id;
```

## 🔗 Integración con Sistema Existente

### Relación con Productos

```sql
-- Los productos se relacionan por nombre (temporal)
SELECT p.*, a.id as almacen_id 
FROM productos p 
JOIN almacenes a ON p.almacen = a.nombre 
WHERE a.activo = 1;
```

### Migración Futura (Opcional)

```sql
-- Para migrar de VARCHAR a FK (cuando esté listo)
ALTER TABLE productos ADD COLUMN almacen_id INT;
UPDATE productos p 
JOIN almacenes a ON p.almacen = a.nombre 
SET p.almacen_id = a.id;
-- Luego eliminar el campo VARCHAR
```

## 🎨 Interfaz de Usuario

### Listado Principal
- **Estadísticas:** Almacenes activos, productos totales, stock crítico
- **Filtros:** Búsqueda por texto, filtro por estado
- **Acciones:** Ver, editar, eliminar por almacén

### Formularios
- **Validación:** En tiempo real con JavaScript
- **Campos:** Nombre*, descripción, ubicación*, capacidad, estado
- **Seguridad:** Tokens CSRF, sanitización de datos

### Vista Detallada
- **Información:** Datos completos del almacén
- **Productos:** Lista de productos en el almacén
- **Estadísticas:** Stock por estado, valor del inventario

## 🔒 Seguridad

### Autenticación
```php
// Verificar permisos antes de cada operación
$current_user = auth_require('inventario', 'crear');
```

### Validaciones
```php
// Validar datos de entrada
$errores = validarDatosComunes($_POST);
if (!empty($errores)) {
    // Manejar errores
}
```

### Auditoría
```php
// Registrar todas las operaciones
auth_log('create', 'almacenes', "Almacén creado: {$nombre}");
```

## 🐛 Resolución de Problemas

### Error: "Tabla almacenes no existe"
```sql
-- Ejecutar script de instalación
source setup_almacenes.sql;
```

### Error: "Permisos insuficientes"
```sql
-- Verificar permisos del usuario
SELECT * FROM rol_permisos WHERE usuario_id = [ID_USUARIO];
```

### Error: "No se pueden eliminar almacenes"
```sql
-- Verificar productos asociados
SELECT COUNT(*) FROM productos WHERE almacen = '[NOMBRE_ALMACEN]';
```

### CSS/JS no se cargan
```html
<!-- Verificar rutas en los archivos PHP -->
<link rel="stylesheet" href="almacenes.css">
<script src="almacenes.js"></script>
```

## 🔄 Mantenimiento

### Limpieza de Datos
```sql
-- Eliminar almacenes inactivos sin productos (opcional)
DELETE FROM almacenes 
WHERE activo = 0 
AND id NOT IN (
    SELECT DISTINCT a.id 
    FROM almacenes a 
    JOIN productos p ON a.nombre = p.almacen
);
```

### Optimización
```sql
-- Reindexar tablas periódicamente
ANALYZE TABLE almacenes;
ANALYZE TABLE productos;
```

## 📈 Métricas y Monitoreo

### Consultas Útiles

```sql
-- Estadísticas generales
SELECT 
    COUNT(*) as total_almacenes,
    SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as activos,
    AVG(capacidad_maxima) as capacidad_promedio
FROM almacenes;

-- Almacenes con más productos
SELECT 
    a.nombre,
    COUNT(p.id) as productos,
    SUM(p.stock_actual) as stock_total
FROM almacenes a
LEFT JOIN productos p ON a.nombre = p.almacen
GROUP BY a.id
ORDER BY productos DESC;

-- Productos críticos por almacén
SELECT 
    a.nombre as almacen,
    COUNT(p.id) as productos_criticos
FROM almacenes a
JOIN productos p ON a.nombre = p.almacen
WHERE p.stock_actual <= p.stock_minimo
GROUP BY a.id
ORDER BY productos_criticos DESC;
```

## 🚀 Próximas Mejoras

### Fase 2: Funcionalidades Avanzadas
- [ ] Transferencias entre almacenes
- [ ] Ubicaciones físicas (pasillo, estante, nivel)
- [ ] Códigos QR para almacenes
- [ ] Reportes avanzados en PDF/Excel

### Fase 3: Integración Completa
- [ ] Migración de VARCHAR a FK
- [ ] Historial de movimientos
- [ ] Alertas automáticas por email
- [ ] API REST para integraciones

## 💡 Consejos de Uso

1. **Nombres únicos:** Usar nombres descriptivos y únicos
2. **Ubicaciones precisas:** Incluir direcciones completas
3. **Capacidades realistas:** Definir capacidades máximas apropiadas
4. **Mantenimiento regular:** Revisar almacenes inactivos periódicamente
5. **Respaldos:** Hacer backup antes de operaciones masivas

## 📞 Soporte

Para soporte técnico o reportar errores:
- **Sistema:** Sequoia Speed v1.0
- **Módulo:** Gestión de Almacenes
- **Versión:** 1.0.0
- **Última actualización:** <?php echo date('Y-m-d'); ?>

---

**Desarrollado para Sequoia Speed** 🚀