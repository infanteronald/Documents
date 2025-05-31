# Configuración de Email para Sequoia Speed

## Problema: Emails no se envían

Si los emails no se están enviando, es porque la función `mail()` de PHP requiere configuración del servidor SMTP.

## Soluciones:

### 1. Para XAMPP/WAMP (Windows):
Editar el archivo `php.ini`:

```ini
[mail function]
SMTP = smtp.gmail.com
smtp_port = 587
sendmail_from = tu-email@gmail.com
sendmail_path = "\"C:\xampp\sendmail\sendmail.exe\" -t"
```

### 2. Para servidores de producción:
Configurar un servidor SMTP real o usar servicios como:
- Gmail SMTP
- SendGrid
- Mailgun
- Amazon SES

### 3. Prueba rápida:
Ejecutar el archivo `test_email.php` para verificar la configuración.

### 4. Logs:
Los errores de email se guardan en `error.log`

## Archivos relacionados:
- `procesar_orden.php` - Procesamiento principal y envío de emails
- `test_email.php` - Archivo de prueba básico
- `test_email_adjunto.php` - Archivo de prueba con adjuntos
- `error.log` - Log de errores

## Emails que se envían:
1. **Al administrador**: ventas@sequoiaspeed.com.co (notificación de nueva orden)
2. **Copias**: jorgejosecardozo@gmail.com
3. **Al cliente**: Confirmación del pedido con detalles completos (con copia oculta al equipo)

## Configuración de archivos adjuntos:
- **Email del administrador**: Recibe el comprobante como archivo adjunto real
- **Email del cliente**: No recibe adjuntos (email más limpio)
- **Formatos soportados**: Imágenes (JPG, PNG, GIF), PDFs, documentos
- **Tamaño máximo**: Depende de la configuración del servidor PHP

## Configuración de remitente:
- **From**: ventas@sequoiaspeed.com.co
- **Reply-To**: ventas@sequoiaspeed.com.co (para emails del cliente)
- **Reply-To**: email del cliente (para emails del admin)

## Características implementadas:
- ✅ Headers completos y seguros
- ✅ Codificación UTF-8
- ✅ Logging detallado de errores
- ✅ Función de envío mejorada con fallbacks
- ✅ Emails diferenciados para admin y cliente
- ✅ Información específica para pagos Bold PSE
