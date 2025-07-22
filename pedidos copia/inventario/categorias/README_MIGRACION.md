# 📋 Migración de Categorías - Instrucciones

## 🎯 Objetivo
Migrar las categorías existentes de la tabla `productos` (campo varchar) al nuevo sistema de categorías estructurado.

## 📂 Scripts Incluidos

### 1️⃣ `01_consultar_categorias_existentes.sql`
**Propósito:** Ver qué categorías existen actualmente
```sql
-- Ejecutar para ver todas las categorías únicas
-- Muestra cuántos productos tiene cada categoría
```

### 2️⃣ `02_migrar_categorias_productos.sql`
**Propósito:** Crear las nuevas categorías en `categorias_productos`
- Limpia categorías de prueba
- Inserta categorías específicas (guantes, botas, etc.) con iconos apropiados
- Migra cualquier categoría adicional que encuentre

### 3️⃣ `03_actualizar_productos_categoria_id.sql`
**Propósito:** Conectar productos con las nuevas categorías
- Agrega columna `categoria_id` a productos
- Actualiza todos los productos para usar el nuevo sistema
- Crea foreign key constraint
- Verifica la migración

## 🚀 Cómo Ejecutar

### Opción 1: phpMyAdmin / Administrador BD
1. Ejecutar scripts en orden (01, 02, 03)
2. Revisar resultados de cada uno antes de continuar

### Opción 2: Línea de comandos MySQL
```bash
mysql -u tu_usuario -p tu_base_datos < 01_consultar_categorias_existentes.sql
mysql -u tu_usuario -p tu_base_datos < 02_migrar_categorias_productos.sql
mysql -u tu_usuario -p tu_base_datos < 03_actualizar_productos_categoria_id.sql
```

## ✅ Verificación
Después de ejecutar todos los scripts:
- Las categorías aparecerán en: `https://sequoiaspeed.com.co/pedidos/inventario/categorias/`
- Cada categoría mostrará el número correcto de productos
- Los productos mantendrán su categoría original pero ahora usarán el nuevo sistema

## 🔧 Categorías con Iconos Específicos
- 🧤 guantes
- 🥾 botas  
- ⛑️ cascos
- 🦺 chalecos
- 🥽 gafas
- 😷 mascaras
- 👔 overoles
- 🔗 arneses
- 🔧 herramientas
- ⚙️ equipos

Cualquier otra categoría usará 🏷️ por defecto.