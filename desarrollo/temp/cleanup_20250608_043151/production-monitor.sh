#!/bin/bash
# Sequoia Speed - Monitoreo de Producción
LOG_FILE="logs/production.log"
mkdir -p logs
echo "[$(date)] Verificación de producción iniciada" >> $LOG_FILE

# Verificar archivos críticos
if [ -f "index.php" ] && [ -f "migration-helper.php" ]; then
    echo "[$(date)] ✅ Archivos críticos: OK" >> $LOG_FILE
else
    echo "[$(date)] ❌ ALERTA: Archivos faltantes" >> $LOG_FILE
fi

echo "[$(date)] Verificación completada" >> $LOG_FILE
