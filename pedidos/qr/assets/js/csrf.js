/**
 * CSRF Protection Handler
 * Sequoia Speed QR System
 */

// Obtener token CSRF del meta tag
function getCSRFToken() {
    const token = document.querySelector('meta[name="csrf-token"]');
    return token ? token.getAttribute('content') : '';
}

// Configurar CSRF token para todas las requests AJAX
function setupCSRFProtection() {
    const token = getCSRFToken();
    
    if (!token) {
        console.warn('CSRF token not found in meta tag');
        return;
    }
    
    // Para fetch API
    const originalFetch = window.fetch;
    window.fetch = function(url, options) {
        options = options || {};
        
        // Solo agregar CSRF token para requests POST, PATCH, DELETE, PUT
        const method = options.method ? options.method.toUpperCase() : 'GET';
        const needsCSRF = ['POST', 'PATCH', 'DELETE', 'PUT'].includes(method);
        
        if (needsCSRF) {
            // Si es FormData, agregar como campo
            if (options.body instanceof FormData) {
                options.body.append('csrf_token', token);
            }
            // Si es JSON, agregar al headers o al body según el caso
            else if (options.headers && 
                     (options.headers['Content-Type'] === 'application/json' ||
                      options.headers['content-type'] === 'application/json')) {
                // Para JSON, usar header personalizado
                options.headers['X-CSRF-TOKEN'] = token;
            }
            // Para otros tipos de contenido, usar header
            else {
                options.headers = options.headers || {};
                options.headers['X-CSRF-TOKEN'] = token;
            }
        }
        
        return originalFetch.call(this, url, options);
    };
    
    // Para XMLHttpRequest
    const originalOpen = XMLHttpRequest.prototype.open;
    const originalSend = XMLHttpRequest.prototype.send;
    
    XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
        this._method = method.toUpperCase();
        this._url = url;
        return originalOpen.call(this, method, url, async, user, password);
    };
    
    XMLHttpRequest.prototype.send = function(data) {
        const needsCSRF = ['POST', 'PATCH', 'DELETE', 'PUT'].includes(this._method);
        
        if (needsCSRF) {
            // Si es FormData, agregar campo
            if (data instanceof FormData) {
                data.append('csrf_token', token);
            }
            // Si no, usar header
            else {
                this.setRequestHeader('X-CSRF-TOKEN', token);
            }
        }
        
        return originalSend.call(this, data);
    };
}

// Agregar token CSRF a formularios existentes
function addCSRFToForms() {
    const token = getCSRFToken();
    if (!token) return;
    
    // Encontrar todos los formularios que necesitan CSRF
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        const method = form.method ? form.method.toUpperCase() : 'GET';
        const needsCSRF = ['POST', 'PATCH', 'DELETE', 'PUT'].includes(method);
        
        if (needsCSRF) {
            // Verificar si ya tiene token CSRF
            const existingToken = form.querySelector('input[name="csrf_token"]');
            
            if (!existingToken) {
                const tokenInput = document.createElement('input');
                tokenInput.type = 'hidden';
                tokenInput.name = 'csrf_token';
                tokenInput.value = token;
                form.appendChild(tokenInput);
            }
        }
    });
}

// Función para refrescar token CSRF (útil para SPAs)
async function refreshCSRFToken() {
    try {
        const response = await fetch('/qr/api/csrf-token.php', {
            method: 'GET',
            credentials: 'same-origin'
        });
        
        if (response.ok) {
            const data = await response.json();
            if (data.success && data.token) {
                // Actualizar meta tag
                const metaTag = document.querySelector('meta[name="csrf-token"]');
                if (metaTag) {
                    metaTag.setAttribute('content', data.token);
                }
                
                // Actualizar formularios
                addCSRFToForms();
                
                return data.token;
            }
        }
    } catch (error) {
        console.error('Error refreshing CSRF token:', error);
    }
    
    return null;
}

// Manejar errores 403 (CSRF token inválido)
function handleCSRFError(response) {
    if (response.status === 403) {
        console.warn('CSRF token may be invalid, attempting to refresh...');
        return refreshCSRFToken().then(newToken => {
            if (newToken) {
                console.log('CSRF token refreshed successfully');
                return true; // Indicar que se puede reintentar
            }
            return false;
        });
    }
    return Promise.resolve(false);
}

// Inicializar protección CSRF cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    setupCSRFProtection();
    addCSRFToForms();
    
    // Volver a agregar tokens cuando se agreguen nuevos formularios dinámicamente
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        if (node.tagName === 'FORM') {
                            addCSRFToForms();
                        } else if (node.querySelector && node.querySelector('form')) {
                            addCSRFToForms();
                        }
                    }
                });
            }
        });
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
});

// Exportar funciones para uso global
window.CSRFProtection = {
    getToken: getCSRFToken,
    refreshToken: refreshCSRFToken,
    handleError: handleCSRFError,
    addToForms: addCSRFToForms
};