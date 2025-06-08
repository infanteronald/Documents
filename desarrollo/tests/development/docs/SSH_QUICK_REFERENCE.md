# 🚀 SSH Quick Reference - Sequoia Speed

## **Uso Recomendado (Sin warnings):**

### **Comando Principal:**
```bash
ssh sequoia "comando"
```

### **Ejemplos Comunes:**

#### **Verificar sistema Bold:**
```bash
ssh sequoia "cd ./sequoiaspeed.com.co/pedidos/ && php bold_system_status.php"
```

#### **Probar hash generator:**
```bash
ssh sequoia "cd ./sequoiaspeed.com.co/pedidos/ && curl -s -X POST https://sequoiaspeed.com.co/pedidos/bold_hash.php -H 'Content-Type: application/json' -d '{\"amount\":50000,\"currency\":\"COP\",\"order_id\":\"TEST_001\",\"description\":\"Prueba\"}'"
```

#### **Ver logs recientes:**
```bash
ssh sequoia "cd ./sequoiaspeed.com.co/pedidos/logs && ls -la | tail -5"
```

#### **Probar webhook:**
```bash
ssh sequoia "cd ./sequoiaspeed.com.co/pedidos/ && curl -s -X POST https://sequoiaspeed.com.co/pedidos/bold_webhook_test.php -H 'Content-Type: application/json' -d '{\"event\":\"transaction.approved\",\"data\":{\"transaction\":{\"id\":\"test\",\"status\":\"APPROVED\",\"amount\":{\"total\":50000,\"currency\":\"COP\"},\"payment_method\":{\"type\":\"PSE\"},\"order_id\":\"TEST_001\"}}}'"
```

## **Configuración SSH (ya aplicada):**
- Alias: `sequoia`
- Sin warnings de locale
- Timeout configurado
- Conexión optimizada

## **Directorio de trabajo remoto:**
```
~/sequoiaspeed.com.co/pedidos/
```

---
*Archivo creado: $(date)*
*Estado del sistema: ✅ FUNCIONAL*
