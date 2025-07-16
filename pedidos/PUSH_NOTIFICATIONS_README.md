# 🔔 Browser Push Notifications System

## Overview
This system extends the existing in-app notification system to include native browser push notifications. Users will receive notifications even when the browser tab is closed or the browser is not running.

## Features
- ✅ **Native Browser Push Notifications**
- ✅ **VAPID Authentication** for secure messaging
- ✅ **Service Worker** for background message handling
- ✅ **Permission Management** with user-friendly UI
- ✅ **Database Persistence** for subscriptions and logs
- ✅ **Automatic Integration** with existing notification system
- ✅ **Responsive Design** for mobile and desktop
- ✅ **Error Handling** and subscription management

## How It Works

### 1. **Service Worker Registration**
- Registers `push-service-worker.js` on page load
- Handles push events in the background
- Manages notification display and click events

### 2. **Push Subscription Flow**
1. User grants notification permissions
2. Browser creates a push subscription
3. Subscription is sent to the server
4. Server stores subscription in database
5. When events occur, server sends push to browser

### 3. **Notification Types**
- **new_order**: New order received
- **payment**: Payment confirmations
- **status_change**: Order status updates
- **shipment**: Shipping and delivery updates
- **error**: Error notifications
- **warning**: Warning messages

## File Structure

```
📁 Push Notifications System
├── 📄 push-service-worker.js          # Service worker for push handling
├── 📄 push_config.json                # VAPID keys configuration
├── 📄 composer.json                   # PHP dependencies
├── 📄 setup_push_notifications.php    # Setup script
├── 📄 test_push_notifications.php     # Testing script
├── 📄 create_push_subscriptions_table.sql # Database schema
├── 📁 notifications/
│   ├── 📄 notifications.js            # Enhanced notification system
│   ├── 📄 push_notifications.css      # Push notification styles
│   ├── 📄 push_subscription.php       # Subscription management API
│   ├── 📄 push_sender.php             # Push notification sender
│   └── 📄 notification_helpers.php    # Enhanced helpers
└── 📁 vendor/                         # Composer dependencies
```

## Database Tables

### push_subscriptions
Stores user push subscriptions
- `id`: Primary key
- `user_id`: User identifier
- `endpoint`: Push service endpoint
- `p256dh_key`: Client public key
- `auth_token`: Authentication token
- `is_active`: Subscription status

### push_notification_logs
Tracks sent notifications
- `id`: Primary key
- `subscription_id`: Related subscription
- `title`: Notification title
- `message`: Notification message
- `status`: Delivery status
- `sent_at`: Timestamp

### push_notification_settings
User preferences
- `user_id`: User identifier
- `push_enabled`: Master toggle
- `new_orders`: Order notifications
- `payment_confirmations`: Payment notifications
- `status_changes`: Status notifications
- `shipment_updates`: Shipping notifications
- `errors_and_warnings`: Error notifications

## Installation Steps

### 1. **Upload Files**
Upload all files to your server, maintaining the directory structure.

### 2. **Install Dependencies**
```bash
php composer.phar install
```

### 3. **Run Setup Script**
```bash
php setup_push_notifications.php
```

### 4. **Create Database Tables**
```bash
php setup_push_database.php
```

### 5. **Test the System**
```bash
php test_push_notifications.php
```
## Usage

### 1. **User Experience**
- Users visit any admin page
- System asks for notification permissions
- Users can manage settings via the bell dropdown
- Push notifications appear as native OS notifications

### 2. **Developer Integration**
Push notifications are automatically sent when using existing notification functions:

```php
// This will send both in-app AND push notifications
notificarNuevoPedido($pedido_id, $nombre_cliente, $monto);
notificarPagoConfirmado($pedido_id, $monto, $metodo_pago);
notificarCambioEstado($pedido_id, $estado_anterior, $estado_nuevo);
```

### 3. **Manual Push Notifications**
```php
// Send custom push notification
sendPushNotification(
    'Custom Title',
    'Custom message',
    ['custom_data' => 'value'],
    'admin',
    'info'
);
```

## Configuration

### VAPID Keys
Located in `push_config.json`:
```json
{
    "vapid": {
        "subject": "mailto:admin@sequoiaspeed.com",
        "publicKey": "BKkH...",
        "privateKey": "w3we..."
    }
}
```

### JavaScript Configuration
Update the VAPID public key in `notifications.js`:
```javascript
this.vapidPublicKey = 'YOUR_PUBLIC_KEY_HERE';
```

## Security Considerations

1. **VAPID Private Key**: Keep secure, never commit to version control
2. **HTTPS Required**: Push notifications only work over HTTPS
3. **Permission Management**: Users can revoke permissions at any time
4. **Data Encryption**: All push messages are encrypted
5. **Rate Limiting**: Implement rate limiting to prevent abuse

## Browser Support

- ✅ Chrome 50+
- ✅ Firefox 44+
- ✅ Safari 16+
- ✅ Edge 17+
- ✅ Mobile browsers (Chrome, Safari, Firefox)

## Troubleshooting

### Common Issues

1. **Service Worker Not Loading**
   - Check HTTPS requirement
   - Verify service worker path
   - Check console for errors

2. **Push Not Received**
   - Verify VAPID keys are correct
   - Check subscription is active
   - Verify browser permissions

3. **Database Errors**
   - Check database connection
   - Verify tables exist
   - Check user permissions

### Debug Mode
Enable debug logging in service worker:
```javascript
console.log('[SW] Debug message');
```

## Performance Considerations

1. **Batch Notifications**: System batches push notifications for efficiency
2. **Subscription Cleanup**: Inactive subscriptions are automatically removed
3. **Error Handling**: Failed pushes are logged and subscriptions are cleaned up
4. **Rate Limiting**: Built-in rate limiting prevents spam

## Testing

### Manual Testing
1. Visit admin page
2. Allow notifications
3. Run `test_push_notifications.php`
4. Check if notifications appear
5. Test with browser closed

### Automated Testing
The system includes comprehensive test scripts:
- `test_push_notifications.php`: Tests notification creation
- Push subscription validation
- Service worker registration testing

## Maintenance

### Regular Tasks
1. **Clean up old logs**: Run cleanup script monthly
2. **Update VAPID keys**: Rotate keys annually
3. **Monitor subscriptions**: Check for inactive subscriptions
4. **Update dependencies**: Keep libraries current

### Monitoring
- Track notification delivery rates
- Monitor subscription counts
- Check error logs regularly
- Monitor server performance

## Support

For issues or questions:
1. Check console errors
2. Review server logs
3. Verify HTTPS setup
4. Check browser permissions
5. Test service worker registration

---

🎉 **The push notification system is now fully implemented and ready for production use!**
