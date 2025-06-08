# 🚀 GUÍA COMPLETA DE OPTIMIZACIÓN VS CODE - SEQUOIA SPEED

## 📋 PROBLEMA IDENTIFICADO
VS Code se vuelve lento con el tiempo, especialmente en proyectos grandes como Sequoia Speed con:
- 328 archivos PHP
- Múltiples directorios de uploads/comprobantes
- Alto uso de CPU (74.2% detectado)

## ✅ SOLUCIONES IMPLEMENTADAS

### 1. CONFIGURACIÓN OPTIMIZADA AUTOMÁTICA
**Archivo:** `.vscode/settings.json`
```json
{
    // Excluye directorios pesados del file watcher
    "files.watcherExclude": {
        "**/uploads/**": true,
        "**/comprobantes/**": true,
        "**/guias/**": true,
        "**/desarrollo/temp/**": true
    },

    // Optimizaciones de interfaz
    "workbench.editor.enablePreview": false,
    "editor.minimap.enabled": false,
    "git.autoRefresh": false,

    // Autoguardado inteligente
    "files.autoSave": "onWindowChange"
}
```

### 2. COMANDOS DE OPTIMIZACIÓN CREADOS

#### `optimizvscode` - Limpieza automática
```bash
optimizvscode
```
**Funciones:**
- ✅ Limpia archivos temporales (.log, .tmp, .DS_Store)
- ✅ Borra caché de VS Code
- ✅ Aplica configuración optimizada
- ✅ Configura extensiones recomendadas

#### `monitorvscode` - Diagnóstico de rendimiento
```bash
monitorvscode
```
**Funciones:**
- 📊 Muestra uso de CPU y memoria
- 📊 Cuenta archivos abiertos
- ⚠️ Detecta problemas de rendimiento
- 💡 Sugiere soluciones automáticas

### 3. ARCHIVOS CREADOS
```
desarrollo/scripts/
├── optimize-vscode.sh     # Script principal de optimización
└── monitor-vscode.sh      # Monitor de rendimiento

.vscode/
├── settings.json          # Configuración optimizada
├── extensions.json        # Extensiones recomendadas
└── .gitignore            # Ignora configs personales
```

## 🔧 USO DIARIO RECOMENDADO

### ⚡ SOLUCIÓN INMEDIATA (cuando VS Code está lento)
1. **Ejecuta optimización:** `optimizvscode`
2. **Reinicia VS Code completamente**
3. **Usa "Developer: Reload Window"** (Cmd+Shift+P)

### 📅 MANTENIMIENTO SEMANAL
1. **Monitor de estado:** `monitorvscode`
2. **Limpieza preventiva:** `optimizvscode`
3. **Cierra pestañas innecesarias**

### 🎯 MEJORES PRÁCTICAS
- **Cierra archivos no utilizados** regularmente
- **Desactiva extensiones innecesarias**
- **Usa workspaces específicos** para diferentes partes del proyecto
- **Evita abrir directorios** uploads/comprobantes/guias

## 🚨 SEÑALES DE ALERTA
- ⚠️ CPU > 15% sostenido
- ⚠️ Memoria > 20%
- ⚠️ +1000 archivos abiertos
- ⚠️ Respuesta lenta al escribir

## 📊 RESULTADOS ESPERADOS

### ANTES DE OPTIMIZACIÓN
- 🐌 Alto uso de CPU (74.2%)
- 🐌 Lentitud en tareas básicas
- 🐌 Cuelgues frecuentes
- 🐌 Consumo excesivo de memoria

### DESPUÉS DE OPTIMIZACIÓN
- ⚡ Reducción del 50-70% en uso de CPU
- ⚡ Respuesta más rápida
- ⚡ Menor consumo de memoria
- ⚡ Estabilidad mejorada

## 🔗 COMANDOS RÁPIDOS

| Comando | Función | Cuándo usar |
|---------|---------|-------------|
| `optimizvscode` | Optimización completa | VS Code lento |
| `monitorvscode` | Diagnóstico | Revisión rutinaria |
| `borratemporales` | Limpia proyecto | Antes de optimizar |

## 💡 CONSEJOS ADICIONALES

### Para MacBook Air M4
- **Usa Activity Monitor** para verificar procesos
- **Cierra aplicaciones pesadas** mientras programas
- **Mantén macOS actualizado**
- **Considera usar múltiples workspaces** de VS Code

### Extensiones Recomendadas SOLO para PHP
- ✅ PHP Intelephense
- ✅ PHP Debug
- ✅ Tailwind CSS (si usas)
- ❌ Evita extensiones de otros lenguajes

---

**Creado:** 8 de junio de 2025
**Autor:** Sistema de Optimización Sequoia Speed
**Versión:** 1.0
