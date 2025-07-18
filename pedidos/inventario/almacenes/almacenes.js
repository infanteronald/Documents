/**
 * JavaScript para Módulo de Almacenes
 * Sistema de Inventario - Sequoia Speed
 */

// Objeto principal para el módulo de almacenes
const AlmacenesModule = {
    // Configuración
    config: {
        searchDebounce: 300,
        animationDuration: 300
    },

    // Inicialización
    init: function() {
        this.bindEvents();
        this.initializeComponents();
        console.log('Módulo de Almacenes inicializado');
    },

    // Vincular eventos
    bindEvents: function() {
        // Búsqueda con debounce
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    AlmacenesModule.handleSearch();
                }, AlmacenesModule.config.searchDebounce);
            });
        }

        // Filtros
        const filterSelects = document.querySelectorAll('.filter-select');
        filterSelects.forEach(select => {
            select.addEventListener('change', () => {
                AlmacenesModule.handleFilterChange();
            });
        });

        // Botones de acción
        this.bindActionButtons();

        // Formularios
        this.bindForms();

        // Modales
        this.bindModals();
    },

    // Inicializar componentes
    initializeComponents: function() {
        // Tooltips
        this.initializeTooltips();
        
        // Validación en tiempo real
        this.initializeValidation();
        
        // Estadísticas animadas
        this.animateStats();
    },

    // Manejar búsqueda
    handleSearch: function() {
        const searchForm = document.querySelector('.filters-form');
        if (searchForm) {
            searchForm.submit();
        }
    },

    // Manejar cambio de filtros
    handleFilterChange: function() {
        const filterForm = document.querySelector('.filters-form');
        if (filterForm) {
            filterForm.submit();
        }
    },

    // Vincular botones de acción
    bindActionButtons: function() {
        // Botones de eliminar
        const deleteButtons = document.querySelectorAll('.btn-delete, .btn-action.btn-delete');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const almacenId = this.getAttribute('data-id') || this.getAttribute('onclick')?.match(/\d+/)?.[0];
                const almacenNombre = this.getAttribute('data-nombre') || 'este almacén';
                
                if (almacenId) {
                    AlmacenesModule.confirmarEliminacion(almacenId, almacenNombre);
                }
            });
        });

        // Botones de editar
        const editButtons = document.querySelectorAll('.btn-edit, .btn-action.btn-edit');
        editButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                AlmacenesModule.handleEdit(this);
            });
        });

        // Botones de ver detalle
        const viewButtons = document.querySelectorAll('.btn-info, .btn-action.btn-info');
        viewButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                AlmacenesModule.handleView(this);
            });
        });
    },

    // Vincular formularios
    bindForms: function() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!AlmacenesModule.validateForm(this)) {
                    e.preventDefault();
                    return false;
                }
                AlmacenesModule.handleFormSubmit(this);
            });
        });
    },

    // Vincular modales
    bindModales: function() {
        const modal = document.getElementById('modalConfirmacion');
        if (modal) {
            // Cerrar modal al hacer clic fuera
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    AlmacenesModule.cerrarModal();
                }
            });

            // Cerrar modal con ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal.style.display === 'flex') {
                    AlmacenesModule.cerrarModal();
                }
            });
        }
    },

    // Validar formulario
    validateForm: function(form) {
        const formId = form.getAttribute('id');
        
        if (formId === 'formCrearAlmacen' || formId === 'formEditarAlmacen') {
            return this.validateAlmacenForm(form);
        }
        
        return true;
    },

    // Validar formulario de almacén
    validateAlmacenForm: function(form) {
        const errors = [];
        
        // Validar nombre
        const nombre = form.querySelector('#nombre');
        if (!nombre.value.trim()) {
            errors.push('El nombre del almacén es requerido');
            this.highlightError(nombre);
        } else {
            this.clearError(nombre);
        }
        
        // Validar ubicación
        const ubicacion = form.querySelector('#ubicacion');
        if (!ubicacion.value.trim()) {
            errors.push('La ubicación es requerida');
            this.highlightError(ubicacion);
        } else {
            this.clearError(ubicacion);
        }
        
        // Validar capacidad máxima
        const capacidad = form.querySelector('#capacidad_maxima');
        if (capacidad.value && (isNaN(capacidad.value) || parseInt(capacidad.value) < 0)) {
            errors.push('La capacidad máxima debe ser un número positivo');
            this.highlightError(capacidad);
        } else {
            this.clearError(capacidad);
        }
        
        if (errors.length > 0) {
            this.showValidationErrors(errors);
            return false;
        }
        
        return true;
    },

    // Resaltar campo con error
    highlightError: function(field) {
        field.style.borderColor = '#da3633';
        field.classList.add('error');
    },

    // Limpiar error de campo
    clearError: function(field) {
        field.style.borderColor = '';
        field.classList.remove('error');
    },

    // Mostrar errores de validación
    showValidationErrors: function(errors) {
        const errorMessage = errors.join('\n');
        this.showNotification(errorMessage, 'error');
    },

    // Manejar envío de formulario
    handleFormSubmit: function(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            
            const originalText = submitBtn.innerHTML;
            const loadingText = originalText.includes('Crear') ? '⏳ Creando...' : '⏳ Actualizando...';
            submitBtn.innerHTML = loadingText;
            
            // Restaurar botón después de 5 segundos (fallback)
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }, 5000);
        }
    },

    // Confirmar eliminación
    confirmarEliminacion: function(id, nombre) {
        const modal = document.getElementById('modalConfirmacion');
        if (!modal) return;
        
        const titulo = document.getElementById('modalTitulo');
        const mensaje = document.getElementById('modalMensaje');
        const btnConfirmar = document.getElementById('btnConfirmar');
        
        if (titulo) titulo.textContent = '🗑️ Confirmar Eliminación';
        if (mensaje) mensaje.textContent = `¿Estás seguro de que quieres eliminar el almacén "${nombre}"? Esta acción no se puede deshacer.`;
        
        if (btnConfirmar) {
            btnConfirmar.onclick = () => {
                this.eliminarAlmacen(id);
            };
        }
        
        modal.style.display = 'flex';
    },

    // Eliminar almacén
    eliminarAlmacen: function(id) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'procesar.php';
        
        const accionInput = document.createElement('input');
        accionInput.type = 'hidden';
        accionInput.name = 'accion';
        accionInput.value = 'eliminar';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;
        
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = this.getCSRFToken();
        
        form.appendChild(accionInput);
        form.appendChild(idInput);
        form.appendChild(csrfInput);
        document.body.appendChild(form);
        form.submit();
    },

    // Obtener token CSRF
    getCSRFToken: function() {
        const csrfInput = document.querySelector('input[name="csrf_token"]');
        return csrfInput ? csrfInput.value : '';
    },

    // Cerrar modal
    cerrarModal: function() {
        const modal = document.getElementById('modalConfirmacion');
        if (modal) {
            modal.style.display = 'none';
        }
    },

    // Manejar edición
    handleEdit: function(button) {
        // Agregar indicador de carga
        const originalText = button.innerHTML;
        button.innerHTML = '⏳';
        button.disabled = true;
        
        setTimeout(() => {
            button.innerHTML = originalText;
            button.disabled = false;
        }, 1000);
    },

    // Manejar vista
    handleView: function(button) {
        // Agregar indicador de carga
        const originalText = button.innerHTML;
        button.innerHTML = '⏳';
        button.disabled = true;
        
        setTimeout(() => {
            button.innerHTML = originalText;
            button.disabled = false;
        }, 1000);
    },

    // Inicializar tooltips
    initializeTooltips: function() {
        const elementsWithTooltip = document.querySelectorAll('[title]');
        elementsWithTooltip.forEach(element => {
            element.addEventListener('mouseenter', function() {
                AlmacenesModule.showTooltip(this);
            });
            element.addEventListener('mouseleave', function() {
                AlmacenesModule.hideTooltip();
            });
        });
    },

    // Mostrar tooltip
    showTooltip: function(element) {
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = element.getAttribute('title');
        
        tooltip.style.position = 'absolute';
        tooltip.style.background = 'rgba(0, 0, 0, 0.8)';
        tooltip.style.color = 'white';
        tooltip.style.padding = '4px 8px';
        tooltip.style.borderRadius = '4px';
        tooltip.style.fontSize = '12px';
        tooltip.style.zIndex = '1000';
        tooltip.style.pointerEvents = 'none';
        
        document.body.appendChild(tooltip);
        
        element.addEventListener('mousemove', function(e) {
            tooltip.style.left = (e.pageX + 10) + 'px';
            tooltip.style.top = (e.pageY - 30) + 'px';
        });
        
        element.tooltip = tooltip;
    },

    // Ocultar tooltip
    hideTooltip: function() {
        const tooltips = document.querySelectorAll('.tooltip');
        tooltips.forEach(tooltip => {
            tooltip.remove();
        });
    },

    // Inicializar validación en tiempo real
    initializeValidation: function() {
        const inputs = document.querySelectorAll('input[required], textarea[required]');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                AlmacenesModule.validateField(this);
            });
            
            input.addEventListener('input', function() {
                if (this.classList.contains('error')) {
                    AlmacenesModule.clearError(this);
                }
            });
        });
    },

    // Validar campo individual
    validateField: function(field) {
        const value = field.value.trim();
        
        if (field.hasAttribute('required') && !value) {
            this.highlightError(field);
            return false;
        }
        
        if (field.type === 'number' && value) {
            const num = parseFloat(value);
            if (isNaN(num) || num < 0) {
                this.highlightError(field);
                return false;
            }
        }
        
        this.clearError(field);
        return true;
    },

    // Animar estadísticas
    animateStats: function() {
        const statNumbers = document.querySelectorAll('.stat-number');
        statNumbers.forEach(stat => {
            const finalValue = parseInt(stat.textContent.replace(/\D/g, ''));
            if (finalValue > 0) {
                this.animateNumber(stat, finalValue);
            }
        });
    },

    // Animar número
    animateNumber: function(element, finalValue) {
        let currentValue = 0;
        const increment = Math.ceil(finalValue / 50);
        const timer = setInterval(() => {
            currentValue += increment;
            if (currentValue >= finalValue) {
                currentValue = finalValue;
                clearInterval(timer);
            }
            element.textContent = this.formatNumber(currentValue);
        }, 30);
    },

    // Formatear número
    formatNumber: function(num) {
        return num.toLocaleString();
    },

    // Mostrar notificación
    showNotification: function(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        
        const icon = type === 'error' ? '❌' : type === 'success' ? '✅' : 'ℹ️';
        notification.innerHTML = `
            <span class="notification-icon">${icon}</span>
            <span class="notification-message">${message}</span>
            <button class="notification-close" onclick="this.parentElement.remove()">×</button>
        `;
        
        // Estilos
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'error' ? '#da3633' : type === 'success' ? '#238636' : '#58a6ff'};
            color: white;
            padding: 12px 16px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 10000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            animation: slideInRight 0.3s ease;
        `;
        
        document.body.appendChild(notification);
        
        // Auto-eliminar después de 5 segundos
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    },

    // Resetear formulario
    resetForm: function(formId) {
        const form = document.getElementById(formId);
        if (form) {
            form.reset();
            
            // Limpiar errores
            const errorFields = form.querySelectorAll('.error');
            errorFields.forEach(field => {
                this.clearError(field);
            });
            
            // Enfocar primer campo
            const firstInput = form.querySelector('input[type="text"], textarea');
            if (firstInput) {
                firstInput.focus();
            }
        }
    },

    // Utilidades
    utils: {
        // Debounce function
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        // Validar email
        validateEmail: function(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        },

        // Formatear fecha
        formatDate: function(date) {
            return new Date(date).toLocaleDateString('es-ES', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    }
};

// Funciones globales para compatibilidad
function confirmarEliminacion(id, nombre) {
    AlmacenesModule.confirmarEliminacion(id, nombre);
}

function cerrarModal() {
    AlmacenesModule.cerrarModal();
}

function limpiarFormulario() {
    AlmacenesModule.resetForm('formCrearAlmacen');
}

function resetearFormulario() {
    AlmacenesModule.resetForm('formEditarAlmacen');
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    AlmacenesModule.init();
});

// Agregar estilos CSS para animaciones
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    .notification-close {
        background: none;
        border: none;
        color: white;
        font-size: 16px;
        cursor: pointer;
        padding: 0;
        margin-left: 8px;
    }
    
    .notification-close:hover {
        opacity: 0.8;
    }
    
    .error {
        border-color: #da3633 !important;
        box-shadow: 0 0 0 2px rgba(218, 54, 51, 0.2) !important;
    }
`;
document.head.appendChild(style);