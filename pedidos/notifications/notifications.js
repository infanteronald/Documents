/**
 * Sistema de Notificaciones en Tiempo Real
 * @author Claude Assistant
 * @version 1.0.0
 */

class NotificationSystem {
    constructor() {
        this.container = null;
        this.eventSource = null;
        this.notifications = [];
        this.preferences = {
            sound_enabled: true,
            auto_dismiss_seconds: 10,
            position: 'top-right'
        };
        this.audioContext = null;
        this.init();
    }

    /**
     * Inicializar el sistema
     */
    init() {
        // Crear contenedor si no existe
        if (!document.querySelector('.notifications-container')) {
            this.container = document.createElement('div');
            this.container.className = 'notifications-container';
            document.body.appendChild(this.container);
        } else {
            this.container = document.querySelector('.notifications-container');
        }

        // Iniciar conexión SSE
        this.connectSSE();
        
        // Cargar preferencias
        this.loadPreferences();
        
        // Cargar notificaciones no leídas
        this.loadUnreadNotifications();
    }

    /**
     * Conectar con Server-Sent Events
     */
    connectSSE() {
        if (this.eventSource) {
            this.eventSource.close();
        }

        this.eventSource = new EventSource('/pedidos/notifications/notifications_sse.php');
        
        this.eventSource.onmessage = (event) => {
            const data = JSON.parse(event.data);
            if (data.type === 'notification') {
                this.showNotification(data.notification);
            }
        };

        this.eventSource.onerror = (error) => {
            console.error('SSE Error:', error);
            // Reconectar después de 5 segundos
            setTimeout(() => this.connectSSE(), 5000);
        };
    }

    /**
     * Cargar preferencias del usuario
     */
    async loadPreferences() {
        try {
            const response = await fetch('/pedidos/notifications/notifications.php?action=get_preferences');
            const data = await response.json();
            if (data.success) {
                this.preferences = data.preferences;
                this.updateContainerPosition();
            }
        } catch (error) {
            console.error('Error loading preferences:', error);
        }
    }

    /**
     * Cargar notificaciones no leídas
     */
    async loadUnreadNotifications() {
        try {
            const response = await fetch('/pedidos/notifications/notifications.php?action=get_unread');
            const data = await response.json();
            if (data.success && data.notifications) {
                data.notifications.forEach(notification => {
                    this.showNotification(notification, false);
                });
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
        }
    }

    /**
     * Mostrar una notificación
     */
    showNotification(data, playSound = true) {
        const notification = document.createElement('div');
        notification.className = `notification-toast ${data.type}`;
        notification.dataset.id = data.id;
        
        const icon = this.getIcon(data.type);
        
        // Construir HTML
        notification.innerHTML = `
            <div class="notification-icon">${icon}</div>
            <div class="notification-content">
                <h4 class="notification-title">${this.escapeHtml(data.title)}</h4>
                <p class="notification-message">${this.escapeHtml(data.message)}</p>
                ${data.actions ? this.renderActions(data.actions) : ''}
            </div>
            <button class="notification-close" onclick="notificationSystem.closeNotification('${data.id}')">&times;</button>
            ${this.preferences.auto_dismiss_seconds > 0 ? `<div class="notification-progress" style="animation-duration: ${this.preferences.auto_dismiss_seconds}s"></div>` : ''}
        `;
        
        // Agregar al contenedor
        this.container.appendChild(notification);
        this.notifications.push(data.id);
        
        // Reproducir sonido
        if (playSound && this.preferences.sound_enabled) {
            this.playNotificationSound(data.type);
        }
        
        // Auto-dismiss si está configurado
        if (this.preferences.auto_dismiss_seconds > 0) {
            setTimeout(() => {
                this.closeNotification(data.id);
            }, this.preferences.auto_dismiss_seconds * 1000);
        }
        
        // Marcar como mostrada
        this.markAsDisplayed(data.id);
    }

    /**
     * Cerrar una notificación
     */
    closeNotification(id) {
        const notification = document.querySelector(`[data-id="${id}"]`);
        if (notification) {
            notification.classList.add('removing');
            setTimeout(() => {
                notification.remove();
                this.notifications = this.notifications.filter(nId => nId !== id);
            }, 300);
            
            // Marcar como leída
            this.markAsRead(id);
        }
    }

    /**
     * Marcar notificación como leída
     */
    async markAsRead(id) {
        try {
            await fetch('/pedidos/notifications/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'mark_read',
                    notification_id: id
                })
            });
        } catch (error) {
            console.error('Error marking as read:', error);
        }
    }

    /**
     * Marcar notificación como mostrada
     */
    async markAsDisplayed(id) {
        try {
            await fetch('/pedidos/notifications/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'mark_displayed',
                    notification_id: id
                })
            });
        } catch (error) {
            console.error('Error marking as displayed:', error);
        }
    }

    /**
     * Obtener icono según el tipo
     */
    getIcon(type) {
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };
        return icons[type] || icons.info;
    }

    /**
     * Renderizar acciones
     */
    renderActions(actions) {
        if (!actions || !Array.isArray(actions)) return '';
        
        return `
            <div class="notification-actions">
                ${actions.map(action => `
                    <a href="${action.url}" class="notification-action" ${action.target ? `target="${action.target}"` : ''}>
                        ${this.escapeHtml(action.label)}
                    </a>
                `).join('')}
            </div>
        `;
    }

    /**
     * Reproducir sonido de notificación
     */
    playNotificationSound(type) {
        if (!this.audioContext) {
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
        }
        
        const duration = 0.1;
        const frequency = type === 'error' ? 300 : 600;
        
        const oscillator = this.audioContext.createOscillator();
        const gainNode = this.audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(this.audioContext.destination);
        
        oscillator.frequency.value = frequency;
        oscillator.type = 'sine';
        
        gainNode.gain.setValueAtTime(0.3, this.audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, this.audioContext.currentTime + duration);
        
        oscillator.start(this.audioContext.currentTime);
        oscillator.stop(this.audioContext.currentTime + duration);
    }

    /**
     * Actualizar posición del contenedor
     */
    updateContainerPosition() {
        const positions = {
            'top-right': { top: '20px', right: '20px', bottom: 'auto', left: 'auto' },
            'top-left': { top: '20px', left: '20px', bottom: 'auto', right: 'auto' },
            'bottom-right': { bottom: '20px', right: '20px', top: 'auto', left: 'auto' },
            'bottom-left': { bottom: '20px', left: '20px', top: 'auto', right: 'auto' }
        };
        
        const pos = positions[this.preferences.position] || positions['top-right'];
        Object.assign(this.container.style, pos);
    }

    /**
     * Escapar HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Destruir el sistema
     */
    destroy() {
        if (this.eventSource) {
            this.eventSource.close();
        }
        if (this.container) {
            this.container.remove();
        }
    }
}

// Funciones globales para uso fácil
let notificationSystem = null;

/**
 * Mostrar notificación manual
 */
function showNotification(type, title, message, options = {}) {
    if (!notificationSystem) {
        console.error('Notification system not initialized');
        return;
    }
    
    const notification = {
        id: 'manual-' + Date.now(),
        type: type,
        title: title,
        message: message,
        actions: options.actions || null,
        data: options.data || null
    };
    
    notificationSystem.showNotification(notification);
    
    // Guardar en backend si se requiere
    if (options.persist !== false) {
        fetch('/pedidos/notifications/notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'create',
                notification: notification
            })
        });
    }
}

/**
 * Inicializar sistema al cargar la página
 */
document.addEventListener('DOMContentLoaded', function() {
    // No inicializar en páginas excluidas
    if (window.location.pathname.includes('ver_detalle_pedido_cliente.php')) {
        return;
    }
    
    // Crear instancia global
    notificationSystem = new NotificationSystem();
});

// Exportar para uso en otros scripts
window.NotificationSystem = NotificationSystem;
window.showNotification = showNotification;