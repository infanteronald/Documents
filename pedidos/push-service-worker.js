/**
 * Push Service Worker
 * Handles push notification events from the browser
 */

// Service Worker version for cache busting
const SW_VERSION = '1.0.0';

// Install event
self.addEventListener('install', (event) => {
    console.log('[SW] Installing service worker version:', SW_VERSION);
    self.skipWaiting();
});

// Activate event
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating service worker version:', SW_VERSION);
    event.waitUntil(
        clients.claim()
    );
});

// Push event handler
self.addEventListener('push', (event) => {
    console.log('[SW] Push event received');
    
    let notificationData = {};
    
    // Parse push data
    if (event.data) {
        try {
            notificationData = event.data.json();
        } catch (e) {
            notificationData = {
                title: 'Nueva Notificación',
                body: event.data.text() || 'Tienes una nueva notificación',
                icon: '/logo.png',
                badge: '/logo.png'
            };
        }
    } else {
        notificationData = {
            title: 'Nueva Notificación',
            body: 'Tienes una nueva notificación',
            icon: '/logo.png',
            badge: '/logo.png'
        };
    }
    
    // Default notification options
    const defaultOptions = {
        body: notificationData.body || notificationData.message || 'Nueva notificación',
        icon: notificationData.icon || '/logo.png',
        badge: notificationData.badge || '/logo.png',
        vibrate: [100, 50, 100],
        requireInteraction: true,
        actions: [],
        data: {
            timestamp: Date.now(),
            url: notificationData.url || '/',
            ...notificationData.data
        },
        tag: notificationData.tag || 'sequoia-notification'
    };
    
    // Add custom actions if provided
    if (notificationData.actions && Array.isArray(notificationData.actions)) {
        defaultOptions.actions = notificationData.actions.map(action => ({
            action: action.action || action.id || 'default',
            title: action.title || action.label || 'Abrir',
            icon: action.icon || '/logo.png'
        }));
    }
    
    // Show notification
    const promiseChain = self.registration.showNotification(
        notificationData.title || 'Sequoia Speed',
        defaultOptions
    );
    
    event.waitUntil(promiseChain);
});

// Notification click handler
self.addEventListener('notificationclick', (event) => {
    console.log('[SW] Notification click received');
    
    event.notification.close();
    
    let urlToOpen = '/';
    
    // Handle action clicks
    if (event.action) {
        console.log('[SW] Action clicked:', event.action);
        
        // Handle different actions
        switch (event.action) {
            case 'view':
            case 'open':
                urlToOpen = event.notification.data.url || '/';
                break;
            case 'dismiss':
                return; // Just close the notification
            default:
                urlToOpen = event.notification.data.url || '/';
        }
    } else {
        // Default click behavior
        urlToOpen = event.notification.data.url || '/';
    }
    
    // Open/focus the appropriate window
    const promiseChain = clients.matchAll({
        type: 'window',
        includeUncontrolled: true
    }).then((windowClients) => {
        // Check if there's already a window open with this URL
        const existingClient = windowClients.find(client => 
            client.url.includes(urlToOpen.replace(/^\//, ''))
        );
        
        if (existingClient) {
            // Focus existing window
            return existingClient.focus();
        } else {
            // Open new window
            return clients.openWindow(urlToOpen);
        }
    });
    
    event.waitUntil(promiseChain);
});

// Notification close handler
self.addEventListener('notificationclose', (event) => {
    console.log('[SW] Notification closed');
    
    // Track notification dismissal if needed
    if (event.notification.data && event.notification.data.trackClose) {
        fetch('/notifications/track_close.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                notificationId: event.notification.data.id,
                timestamp: Date.now()
            })
        }).catch(err => console.error('[SW] Error tracking close:', err));
    }
});

// Handle push subscription changes
self.addEventListener('pushsubscriptionchange', (event) => {
    console.log('[SW] Push subscription changed');
    
    // Resubscribe the user
    const promiseChain = self.registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(
            'BKkHNNyupL6icILj3eUek5Aq-fwrQ967-fhdzKTzG3uzhH8PlhUYGhjKRnvBVYIx9vmYKM-JObcOT3LfdXgBShY'
        )
    }).then((subscription) => {
        // Send new subscription to server
        return fetch('/notifications/update_subscription.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                subscription: subscription.toJSON(),
                action: 'update'
            })
        });
    });
    
    event.waitUntil(promiseChain);
});

// Utility function to convert VAPID key
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
        .replace(/\-/g, '+')
        .replace(/_/g, '/');
    
    const rawData = self.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    
    return outputArray;
}

// Handle sync events (for background sync)
self.addEventListener('sync', (event) => {
    console.log('[SW] Sync event received:', event.tag);
    
    if (event.tag === 'background-sync-notifications') {
        event.waitUntil(
            // Sync any pending notifications
            fetch('/notifications/sync_notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'sync',
                    timestamp: Date.now()
                })
            }).catch(err => console.error('[SW] Sync error:', err))
        );
    }
});

// Handle messages from the main thread
self.addEventListener('message', (event) => {
    console.log('[SW] Message received:', event.data);
    
    if (event.data.action === 'skipWaiting') {
        self.skipWaiting();
    }
});

console.log('[SW] Service worker loaded successfully');