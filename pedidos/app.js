// JavaScript para el sistema de pedidos Sequoia Speed

// Funciones de validación
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    const required = form.querySelectorAll('[required]');
    let isValid = true;
    
    required.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('error');
            isValid = false;
        } else {
            field.classList.remove('error');
        }
    });
    
    return isValid;
}

// Funciones de API
async function createPedido(data) {
    try {
        const response = await fetch('api/pedidos/create.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        return await response.json();
    } catch (error) {
        console.error('Error creating pedido:', error);
        return { success: false, error: error.message };
    }
}

// Funciones de UI
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    console.log('Sequoia Speed system initialized');
    
    // Configurar formularios
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this.id)) {
                e.preventDefault();
                showNotification('Por favor complete todos los campos requeridos', 'error');
            }
        });
    });
});
