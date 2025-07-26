# 📋 PLAN DE IMPLEMENTACIÓN: INVENTARIO POR TALLA Y ALMACÉN

**Proyecto**: Sequoia Speed - Sistema de Inventario por Tallas  
**Fecha**: 25 de Julio 2025  
**Duración Estimada**: 2-3 semanas  

---

## 🎯 **FASE 1: PREPARACIÓN Y ANÁLISIS** (1-2 días)

### **1.1 Respaldo y Preparación**
- [ ] **Crear respaldo completo de la base de datos**
  ```bash
  mysqldump -h 127.0.0.1 -u motodota_facturacion -p'Blink.182...' motodota_factura_electronica > backup_antes_tallas_$(date +%Y%m%d_%H%M%S).sql
  ```

- [ ] **Crear rama Git para el desarrollo**
  ```bash
  git checkout -b feature/inventario-por-tallas
  git push -u origin feature/inventario-por-tallas
  ```

- [ ] **Verificar estado actual del sistema**
  - Confirmar que el sistema funciona correctamente
  - Documentar datos críticos existentes
  - Verificar integridad de tablas principales

### **1.2 Análisis de Datos Existentes**
- [ ] **Analizar tallas en pedidos actuales**
  ```sql
  SELECT talla, COUNT(*) as cantidad 
  FROM pedido_detalle 
  WHERE talla IS NOT NULL 
  GROUP BY talla 
  ORDER BY cantidad DESC;
  ```

- [ ] **Identificar productos más vendidos por talla**
- [ ] **Mapear categorías que requieren tallas específicas**
- [ ] **Definir estándares de tallas por categoría**

---

## 🔧 **FASE 2: ESTRUCTURA DE BASE DE DATOS** (2-3 días)

### **2.1 Crear Nuevas Tablas**
- [ ] **Tabla `tallas_sistema`** - Tallas disponibles globalmente
  ```sql
  CREATE TABLE tallas_sistema (
      id INT AUTO_INCREMENT PRIMARY KEY,
      codigo VARCHAR(15) NOT NULL UNIQUE,
      nombre VARCHAR(60) NOT NULL,
      tipo ENUM('ropa', 'calzado', 'numerica', 'unica') DEFAULT 'ropa',
      orden_visualizacion INT DEFAULT 0,
      activa TINYINT(1) DEFAULT 1,
      fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  );
  ```

- [ ] **Tabla `producto_tallas`** - Tallas por producto específico
  ```sql
  CREATE TABLE producto_tallas (
      id INT AUTO_INCREMENT PRIMARY KEY,
      producto_id INT NOT NULL,
      talla_id INT NOT NULL,
      activa TINYINT(1) DEFAULT 1,
      precio_diferencial DECIMAL(10,2) DEFAULT 0.00,
      fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
      FOREIGN KEY (talla_id) REFERENCES tallas_sistema(id),
      UNIQUE KEY unique_producto_talla (producto_id, talla_id)
  );
  ```

### **2.2 Modificar Tablas Existentes**
- [ ] **Modificar `inventario_almacen`**
  ```sql
  ALTER TABLE inventario_almacen 
  ADD COLUMN talla_id INT NULL AFTER almacen_id,
  ADD FOREIGN KEY fk_inventario_talla (talla_id) REFERENCES tallas_sistema(id),
  DROP INDEX unique_producto_almacen,
  ADD UNIQUE KEY unique_producto_almacen_talla (producto_id, almacen_id, talla_id);
  ```

- [ ] **Modificar `movimientos_inventario`**
  ```sql
  ALTER TABLE movimientos_inventario 
  ADD COLUMN talla_id INT NULL AFTER almacen_id,
  ADD FOREIGN KEY fk_movimiento_talla (talla_id) REFERENCES tallas_sistema(id);
  ```

### **2.3 Migración de Datos**
- [ ] **Insertar tallas del sistema desde pedidos históricos**
- [ ] **Crear talla "ÚNICA" para productos sin variantes**
- [ ] **Migrar inventario existente con talla por defecto**
- [ ] **Distribuir stock actual entre tallas basado en historial**

---

## 💻 **FASE 3: BACKEND - LÓGICA DE NEGOCIO** (3-4 días)

### **3.1 Clases y Servicios Core**
- [ ] **Crear `TallaManager.php`**
  - Gestión CRUD de tallas por producto
  - Validaciones de negocio
  - Integración con inventario

- [ ] **Crear `InventarioTallaService.php`**
  - Control de stock por talla y almacén
  - Validaciones de disponibilidad
  - Transacciones de stock

- [ ] **Actualizar `StockManager.php`**
  - Verificación de stock por talla
  - Reserva y liberación de stock
  - Control de concurrencia

### **3.2 APIs y Endpoints**
- [ ] **`/inventario/api/tallas_producto.php`**
  - GET: Obtener tallas disponibles de un producto
  - POST: Crear nueva talla para producto
  - PUT: Actualizar configuración de talla
  - DELETE: Desactivar talla

- [ ] **`/inventario/api/stock_talla.php`**
  - GET: Consultar stock por producto/talla/almacén
  - POST: Actualizar stock de talla específica
  - PUT: Transferir stock entre tallas

### **3.3 Modificación de Archivos Existentes**
- [ ] **`inventario/productos.php`** - Vista con gestión de tallas
- [ ] **`inventario/crear_producto.php`** - Formulario con tallas
- [ ] **`inventario/editar_producto.php`** - Edición de tallas existentes
- [ ] **`inventario/procesar_producto.php`** - Lógica de backend
- [ ] **`inventario/movimientos.php`** - Movimientos por talla

---

## 🎨 **FASE 4: FRONTEND - INTERFACES DE USUARIO** (2-3 días)

### **4.1 Componentes JavaScript**
- [ ] **`TallaSelector.js`**
  - Componente reutilizable para selección de tallas
  - Carga dinámica según producto/categoría
  - Validación de disponibilidad

- [ ] **`StockManagerTallas.js`**
  - Gestión visual de stock por talla
  - Alertas de stock bajo por talla
  - Bulk operations

### **4.2 Interfaces Principales**
- [ ] **Dashboard de Productos**
  - Vista expandible por tallas
  - Filtros por talla y disponibilidad
  - Indicadores visuales de stock

- [ ] **Formularios de Productos**
  - Gestión intuitiva de tallas
  - Previsualización de stock
  - Validaciones en tiempo real

- [ ] **Gestión de Movimientos**
  - Selección de talla en movimientos
  - Historial por talla específica
  - Reportes por talla

### **4.3 Mobile Optimization**
- [ ] **Adaptar interfaces móviles**
- [ ] **Touch-friendly controls**
- [ ] **Responsive design para tallas**

---

## 🛒 **FASE 5: INTEGRACIÓN CON SISTEMA DE PEDIDOS** (2-3 días)

### **5.1 Modificar Flujo de Pedidos**
- [ ] **`orden_pedido.php`**
  - Selección dinámica de tallas
  - Validación de stock por talla
  - Preview de disponibilidad

- [ ] **`guardar_pedido.php`**
  - Verificación de stock con bloqueos
  - Descuento automático por talla
  - Transacciones robustas

### **5.2 Validaciones y Controles**
- [ ] **Middleware de validación de stock**
- [ ] **Sistema de reservas temporales**
- [ ] **Rollback automático en fallos**

### **5.3 Reportes y Analytics**
- [ ] **Reportes de ventas por talla**
- [ ] **Análisis de rotación por talla**
- [ ] **Predicción de demanda por talla**

---

## 🔍 **FASE 6: TESTING Y OPTIMIZACIÓN** (1-2 días)

### **6.1 Testing Funcional**
- [ ] **Pruebas de CRUD de productos con tallas**
- [ ] **Pruebas de flujo completo de pedidos**
- [ ] **Pruebas de concurrencia en stock**
- [ ] **Pruebas de migración de datos**

### **6.2 Testing de Performance**
- [ ] **Optimización de consultas SQL**
- [ ] **Índices de base de datos**
- [ ] **Cache de tallas frecuentes**
- [ ] **Lazy loading de componentes**

### **6.3 Testing de UX**
- [ ] **Usabilidad en móviles**
- [ ] **Flujos de usuario intuitivos**
- [ ] **Tiempos de respuesta**
- [ ] **Manejo de errores**

---

## 🚀 **FASE 7: DESPLIEGUE Y MIGRACIÓN** (1 día)

### **7.1 Preparación para Producción**
- [ ] **Script de migración final**
- [ ] **Procedimientos de rollback**
- [ ] **Documentación de cambios**
- [ ] **Capacitación de usuarios**

### **7.2 Despliegue Gradual**
- [ ] **Migración en horario de menor uso**
- [ ] **Monitoreo en tiempo real**
- [ ] **Validación post-migración**
- [ ] **Comunicación a stakeholders**

### **7.3 Post-Despliegue**
- [ ] **Monitoreo de errores 24h**
- [ ] **Ajustes basados en feedback**
- [ ] **Optimizaciones adicionales**
- [ ] **Documentación final**

---

## ⚡ **CONSIDERACIONES TÉCNICAS IMPORTANTES**

### **Concurrencia y Performance**
- Uso de `FOR UPDATE` en verificaciones de stock
- Índices compuestos para consultas frecuentes
- Cache de tallas por categoría
- Lazy loading en interfaces

### **Integridad de Datos**
- Transacciones ACID para operaciones críticas
- Validaciones a nivel de base de datos
- Auditoría completa de cambios
- Respaldos automáticos

### **Experiencia de Usuario**
- Transiciones suaves entre estados
- Feedback inmediato en acciones
- Manejo graceful de errores
- Responsive design completo

### **Escalabilidad**
- Estructura preparada para nuevos tipos de talla
- APIs RESTful para futuras integraciones
- Separación clara de responsabilidades
- Documentación completa

---

## 📊 **MÉTRICAS DE ÉXITO**

- ✅ **Funcionalidad**: 100% de productos migrados exitosamente
- ✅ **Performance**: Tiempo de respuesta < 2 segundos
- ✅ **Integridad**: 0% pérdida de datos en migración
- ✅ **Usabilidad**: Flujo de pedidos funcional en < 30 segundos
- ✅ **Estabilidad**: 0 errores críticos en primera semana

---

## 🚨 **RIESGOS Y MITIGACIONES**

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|--------------|---------|------------|
| Pérdida de datos | Baja | Alto | Respaldos múltiples + testing |
| Performance degradada | Media | Medio | Optimización de consultas |
| Resistencia del usuario | Media | Bajo | Capacitación + UX intuitivo |
| Bugs en producción | Media | Alto | Testing exhaustivo + rollback plan |

---

**Responsable**: Claude AI + Ronald Infante  
**Revisión**: Cada fase requiere aprobación antes de continuar  
**Contacto**: Para dudas o cambios, consultar antes de proceder