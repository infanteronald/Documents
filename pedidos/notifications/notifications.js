/**
 * Sistema de Notificaciones en Tiempo Real
 * @author Claude Assistant
 * @version 1.0.0
 */

class NotificationSystem {
    constructor() {
        this.container = null;
        this.bell = null;
        this.dropdown = null;
        this.eventSource = null;
        this.notifications = [];
        this.unreadCount = 0;
        this.isDropdownOpen = false;
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
        // Crear contenedor de toasts si no existe
        if (!document.querySelector('.notifications-container')) {
            this.container = document.createElement('div');
            this.container.className = 'notifications-container';
            document.body.appendChild(this.container);
        } else {
            this.container = document.querySelector('.notifications-container');
        }

        // Crear campanita
        this.createNotificationBell();

        // Iniciar conexión SSE
        this.connectSSE();
        
        // Cargar preferencias
        this.loadPreferences();
        
        // Cargar notificaciones no leídas
        this.loadUnreadNotifications();
    }

    /**
     * Crear campanita de notificaciones
     */
    createNotificationBell() {
        this.bell = document.createElement('div');
        this.bell.className = 'notification-bell';
        this.bell.innerHTML = `
            <div class="notification-bell-icon" onclick="notificationSystem.toggleDropdown()">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/>
                </svg>
                <div class="notification-bell-badge" style="display: none;">0</div>
            </div>
            <div class="notification-dropdown">
                <div class="notification-dropdown-header">
                    <h3 class="notification-dropdown-title">Notificaciones</h3>
                    <div class="notification-dropdown-actions">
                        <button class="notification-dropdown-btn" onclick="notificationSystem.clearAllNotifications()">
                            Limpiar
                        </button>
                    </div>
                </div>
                <div class="notification-dropdown-list" id="notification-dropdown-list">
                    <div class="notification-dropdown-empty">
                        <div class="notification-dropdown-empty-icon">🔔</div>
                        <div class="notification-dropdown-empty-title">Sin notificaciones</div>
                        <div class="notification-dropdown-empty-subtitle">No tienes notificaciones nuevas</div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(this.bell);
        this.dropdown = this.bell.querySelector('.notification-dropdown');
        
        // Cerrar dropdown al hacer click fuera
        document.addEventListener('click', (e) => {
            if (!this.bell.contains(e.target) && this.isDropdownOpen) {
                this.closeDropdown();
            }
        });
    }

    /**
     * Toggle del dropdown
     */
    toggleDropdown() {
        if (this.isDropdownOpen) {
            this.closeDropdown();
        } else {
            this.openDropdown();
        }
    }

    /**
     * Abrir dropdown
     */
    openDropdown() {
        this.isDropdownOpen = true;
        this.dropdown.classList.add('show');
        this.bell.querySelector('.notification-bell-icon').classList.add('active');
        this.loadDropdownNotifications();
    }

    /**
     * Cerrar dropdown
     */
    closeDropdown() {
        this.isDropdownOpen = false;
        this.dropdown.classList.remove('show');
        this.bell.querySelector('.notification-bell-icon').classList.remove('active');
    }

    /**
     * Cargar notificaciones para el dropdown
     */
    async loadDropdownNotifications() {
        try {
            const response = await fetch('notifications/notifications.php?action=get_all&limit=20');
            const data = await response.json();
            
            if (data.success && data.data.notifications) {
                this.renderDropdownNotifications(data.data.notifications);
            } else {
                console.log('No notifications found or error:', data);
            }
        } catch (error) {
            console.error('Error loading dropdown notifications:', error);
        }
    }

    /**
     * Renderizar notificaciones en el dropdown
     */
    renderDropdownNotifications(notifications) {
        const list = document.getElementById('notification-dropdown-list');
        
        console.log('Renderizando notificaciones:', notifications);
        
        if (!notifications || notifications.length === 0) {
            list.innerHTML = `
                <div class="notification-dropdown-empty">
                    <div class="notification-dropdown-empty-icon">🔔</div>
                    <div class="notification-dropdown-empty-title">Sin notificaciones</div>
                    <div class="notification-dropdown-empty-subtitle">No tienes notificaciones nuevas</div>
                </div>
            `;
            return;
        }

        list.innerHTML = notifications.map(notification => {
            const isUnread = !notification.read_at;
            const timeAgo = this.getTimeAgo(notification.created_at);
            const icon = this.getIcon(notification.type);
            
            return `
                <div class="notification-dropdown-item ${isUnread ? 'unread' : ''}" 
                     onclick="notificationSystem.handleDropdownItemClick('${notification.id}', ${JSON.stringify(notification.data || {}).replace(/"/g, '&quot;')})">
                    <div class="notification-dropdown-icon ${notification.type}">
                        ${icon}
                    </div>
                    <div class="notification-dropdown-content">
                        <div class="notification-dropdown-item-title">${this.escapeHtml(notification.title)}</div>
                        <div class="notification-dropdown-item-message">${this.escapeHtml(notification.message)}</div>
                        <div class="notification-dropdown-item-time">${timeAgo}</div>
                    </div>
                </div>
            `;
        }).join('');
    }

    /**
     * Manejar click en item del dropdown
     */
    handleDropdownItemClick(notificationId, data) {
        // Marcar como leída
        this.markAsRead(notificationId);
        
        // Si tiene acción, ejecutarla
        if (data && data.actions && data.actions.length > 0) {
            const action = data.actions[0];
            if (action.url) {
                if (action.target === '_blank') {
                    window.open(action.url, '_blank');
                } else {
                    window.location.href = action.url;
                }
            }
        }
        
        // Cerrar dropdown
        this.closeDropdown();
    }

    /**
     * Obtener tiempo relativo
     */
    getTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);
        
        if (diffInSeconds < 60) {
            return 'Hace un momento';
        } else if (diffInSeconds < 3600) {
            const minutes = Math.floor(diffInSeconds / 60);
            return `Hace ${minutes} min`;
        } else if (diffInSeconds < 86400) {
            const hours = Math.floor(diffInSeconds / 3600);
            return `Hace ${hours}h`;
        } else {
            const days = Math.floor(diffInSeconds / 86400);
            return `Hace ${days}d`;
        }
    }

    /**
     * Marcar todas como leídas
     */
    async markAllAsRead() {
        try {
            await fetch('notifications/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'mark_all_read'
                })
            });
            
            // Actualizar contador
            this.updateBadgeCount(0);
            
            // Recargar dropdown
            this.loadDropdownNotifications();
            
        } catch (error) {
            console.error('Error marking all as read:', error);
        }
    }

    /**
     * Limpiar todas las notificaciones (eliminar del listado)
     */
    async clearAllNotifications() {
        try {
            await fetch('notifications/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'delete_all'
                })
            });
            
            // Actualizar contador a 0
            this.updateBadgeCount(0);
            
            // Mostrar listado vacío inmediatamente
            this.renderDropdownNotifications([]);
            
        } catch (error) {
            console.error('Error clearing all notifications:', error);
        }
    }

    /**
     * Actualizar contador del badge
     */
    updateBadgeCount(count) {
        const badge = this.bell.querySelector('.notification-bell-badge');
        this.unreadCount = count;
        
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'block';
        } else {
            badge.style.display = 'none';
        }
    }

    /**
     * Conectar con Server-Sent Events
     */
    connectSSE() {
        // PAUSADO TEMPORALMENTE PARA TROUBLESHOOTING
        console.log('SSE connections paused for troubleshooting');
        return;
        
        if (this.eventSource) {
            this.eventSource.close();
        }

        this.eventSource = new EventSource('notifications/notifications_sse.php');
        
        this.eventSource.onmessage = (event) => {
            const data = JSON.parse(event.data);
            if (data.type === 'notification') {
                this.showNotification(data.notification);
                // Incrementar contador del badge
                this.updateBadgeCount(this.unreadCount + 1);
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
            const response = await fetch('notifications/notifications.php?action=get_preferences');
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
            const response = await fetch('notifications/notifications.php?action=get_unread');
            const data = await response.json();
            if (data.success && data.data.notifications) {
                // Actualizar contador del badge
                this.updateBadgeCount(data.data.notifications.length);
                
                // Mostrar toasts solo para las más recientes (máximo 3) cuando cargamos por primera vez
                if (!this.bell.classList.contains('initialized')) {
                    const recentNotifications = data.data.notifications.slice(0, 3);
                    recentNotifications.forEach(notification => {
                        this.showNotification(notification, false);
                    });
                    this.bell.classList.add('initialized');
                }
            } else {
                // Si no hay notificaciones, asegurar que el badge esté oculto
                this.updateBadgeCount(0);
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
            this.updateBadgeCount(0);
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
            const response = await fetch('notifications/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'mark_read',
                    notification_id: id
                })
            });
            
            if (response.ok) {
                // Decrementar contador del badge si la notificación estaba no leída
                if (this.unreadCount > 0) {
                    this.updateBadgeCount(this.unreadCount - 1);
                }
            }
        } catch (error) {
            console.error('Error marking as read:', error);
        }
    }

    /**
     * Marcar notificación como mostrada
     */
    async markAsDisplayed(id) {
        try {
            await fetch('notifications/notifications.php', {
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
        fetch('notifications/notifications.php', {
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
    // SISTEMA DE NOTIFICACIONES COMPLETAMENTE PAUSADO PARA TROUBLESHOOTING
    console.log('Notification system completely disabled for troubleshooting 503 error');
    return;
    
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