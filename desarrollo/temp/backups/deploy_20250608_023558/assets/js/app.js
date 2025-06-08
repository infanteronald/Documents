/**
 * Sequoia Speed - JavaScript Principal
 * Migrado desde script.js con mejoras
 */

// Configuración global
window.SequoiaSpeed = {
  initialized: false,
  config: {
    apiUrl: '/api',
    debug: false
  },
  
  // Cache para elementos DOM
  elements: {},
  
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
    
    // Validaciones
    validateEmail: function(email) {
      const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return regex.test(email);
    },
    
    validatePhone: function(phone) {
      const regex = /^\d{7,15}$/;
      return regex.test(phone);
    },
    
    // Formateo
    formatCurrency: function(amount) {
      return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0
      }).format(amount);
    },
    
    // Loading states
    showLoading: function(element, message = 'Cargando...') {
      if (typeof element === 'string') {
        element = document.getElementById(element);
      }
      if (!element) return;
      
      element.innerHTML = `
        <div class="loading">
          <div class="spinner"></div>
          ${message}
        </div>
      `;
    },
    
    hideLoading: function(element) {
      if (typeof element === 'string') {
        element = document.getElementById(element);
      }
      if (!element) return;
      
      const loading = element.querySelector('.loading');
      if (loading) {
        loading.remove();
      }
    }
  }
};

// Gestión de métodos de pago
class PaymentMethodManager {
  constructor() {
    this.metodoPago = null;
    this.infoPago = null;
    this.init();
  }
  
  init() {
    this.metodoPago = document.getElementById('metodo_pago');
    this.infoPago = document.getElementById('info_pago');
    
    if (this.metodoPago && this.infoPago) {
      this.metodoPago.addEventListener('change', (e) => this.handleMethodChange(e));
      console.log('✅ PaymentMethodManager inicializado');
    }
  }
  
  handleMethodChange(event) {
    const method = event.target.value;
    let content = '';
    
    const paymentMethods = {
      'Nequi': 'Nequi / Transfiya: <b>3213260357</b>',
      'Transfiya': 'Nequi / Transfiya: <b>3213260357</b>',
      'Bancolombia': 'Bancolombia Ahorros: <b>03500000175</b> Ronald Infante',
      'Provincial': 'Provincial Ahorros: <b>0958004765</b> Ronald Infante',
      'PSE': 'Solicite su link de pago a su asesor.',
      'Contra entrega': 'Pagará al recibir el pedido. No requiere pago anticipado.'
    };
    
    content = paymentMethods[method] || '';
    
    this.infoPago.innerHTML = content;
    this.infoPago.style.display = content ? 'block' : 'none';
    
    // Agregar clases para estilos
    this.infoPago.className = 'payment-info';
    if (content) {
      this.infoPago.classList.add('active');
      
      // Animación de entrada
      this.infoPago.style.animation = 'slideDown 0.3s ease';
    }
    
    // Trigger evento personalizado
    document.dispatchEvent(new CustomEvent('paymentMethodChanged', {
      detail: { method, content }
    }));
    
    console.log(`💳 Método de pago cambiado: ${method}`);
  }
  
  getCurrentMethod() {
    return this.metodoPago ? this.metodoPago.value : null;
  }
  
  setMethod(method) {
    if (this.metodoPago) {
      this.metodoPago.value = method;
      this.handleMethodChange({ target: { value: method } });
    }
  }
}

// Validación de formularios
class FormValidator {
  constructor(formSelector) {
    this.form = typeof formSelector === 'string' 
      ? document.querySelector(formSelector) 
      : formSelector;
    
    this.rules = {};
    this.errors = {};
    
    if (this.form) {
      this.init();
    }
  }
  
  init() {
    this.form.addEventListener('submit', (e) => this.handleSubmit(e));
    
    // Validación en tiempo real
    const inputs = this.form.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
      input.addEventListener('blur', (e) => this.validateField(e.target));
      input.addEventListener('input', (e) => {
        // Remover error al escribir
        this.clearFieldError(e.target);
      });
    });
    
    console.log('✅ FormValidator inicializado');
  }
  
  addRule(fieldName, validator, message) {
    if (!this.rules[fieldName]) {
      this.rules[fieldName] = [];
    }
    this.rules[fieldName].push({ validator, message });
  }
  
  validateField(field) {
    const fieldName = field.name;
    const value = field.value.trim();
    const rules = this.rules[fieldName] || [];
    
    // Limpiar errores previos
    this.clearFieldError(field);
    
    // Aplicar reglas
    for (const rule of rules) {
      if (!rule.validator(value, field)) {
        this.showFieldError(field, rule.message);
        return false;
      }
    }
    
    this.showFieldSuccess(field);
    return true;
  }
  
  validateForm() {
    let isValid = true;
    const inputs = this.form.querySelectorAll('input, select, textarea');
    
    inputs.forEach(input => {
      if (!this.validateField(input)) {
        isValid = false;
      }
    });
    
    return isValid;
  }
  
  handleSubmit(event) {
    // Validación específica del formulario de pedidos
    const correo = this.form.querySelector('input[name="correo"]');
    const telefono = this.form.querySelector('input[name="telefono"]');
    
    let errores = [];
    
    if (correo && !window.SequoiaSpeed.utils.validateEmail(correo.value)) {
      errores.push('Correo electrónico no válido.');
      this.showFieldError(correo, 'Correo electrónico no válido');
    }
    
    if (telefono && !window.SequoiaSpeed.utils.validatePhone(telefono.value)) {
      errores.push('Teléfono inválido (solo números, 7 a 15 dígitos).');
      this.showFieldError(telefono, 'Teléfono inválido (solo números, 7 a 15 dígitos)');
    }
    
    if (errores.length > 0) {
      event.preventDefault();
      this.showFormErrors(errores);
      return false;
    }
    
    // Validación general
    if (!this.validateForm()) {
      event.preventDefault();
      return false;
    }
    
    console.log('✅ Formulario validado correctamente');
    return true;
  }
  
  showFieldError(field, message) {
    field.classList.add('error');
    
    let errorElement = field.parentNode.querySelector('.field-validation');
    if (!errorElement) {
      errorElement = document.createElement('div');
      errorElement.className = 'field-validation error';
      field.parentNode.appendChild(errorElement);
    }
    
    errorElement.textContent = message;
    errorElement.className = 'field-validation error';
  }
  
  showFieldSuccess(field) {
    field.classList.remove('error');
    field.classList.add('success');
    
    let errorElement = field.parentNode.querySelector('.field-validation');
    if (errorElement) {
      errorElement.textContent = '✓ Válido';
      errorElement.className = 'field-validation success';
    }
  }
  
  clearFieldError(field) {
    field.classList.remove('error', 'success');
    
    const errorElement = field.parentNode.querySelector('.field-validation');
    if (errorElement) {
      errorElement.textContent = '';
      errorElement.className = 'field-validation';
    }
  }
  
  showFormErrors(errors) {
    const existingAlert = this.form.querySelector('.form-errors');
    if (existingAlert) {
      existingAlert.remove();
    }
    
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert error form-errors';
    alertDiv.innerHTML = `
      <div class="alert-icon">⚠️</div>
      <div class="alert-content">
        <div class="alert-title">Errores en el formulario:</div>
        <ul class="alert-message">
          ${errors.map(error => `<li>${error}</li>`).join('')}
        </ul>
      </div>
    `;
    
    this.form.insertBefore(alertDiv, this.form.firstChild);
    
    // Auto-remove después de 5 segundos
    setTimeout(() => {
      if (alertDiv.parentNode) {
        alertDiv.remove();
      }
    }, 5000);
  }
}

// Gestión de UI mejorada
class UIEnhancer {
  constructor() {
    this.init();
  }
  
  init() {
    this.enhanceForms();
    this.enhanceButtons();
    this.setupAnimations();
    this.setupTooltips();
    console.log('✅ UIEnhancer inicializado');
  }
  
  enhanceForms() {
    // Mejorar campos de input
    const inputs = document.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
      input.addEventListener('focus', (e) => {
        e.target.parentNode.classList.add('focused');
      });
      
      input.addEventListener('blur', (e) => {
        e.target.parentNode.classList.remove('focused');
        if (e.target.value) {
          e.target.parentNode.classList.add('has-value');
        } else {
          e.target.parentNode.classList.remove('has-value');
        }
      });
    });
  }
  
  enhanceButtons() {
    // Mejorar botones con efectos ripple
    const buttons = document.querySelectorAll('button');
    buttons.forEach(button => {
      button.addEventListener('click', (e) => {
        const ripple = document.createElement('span');
        ripple.className = 'ripple-effect';
        
        const rect = button.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = e.clientX - rect.left - size / 2;
        const y = e.clientY - rect.top - size / 2;
        
        ripple.style.cssText = `
          width: ${size}px;
          height: ${size}px;
          left: ${x}px;
          top: ${y}px;
        `;
        
        button.appendChild(ripple);
        
        setTimeout(() => {
          ripple.remove();
        }, 600);
      });
    });
  }
  
  setupAnimations() {
    // Configurar observer para animaciones de entrada
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('animate-in');
        }
      });
    }, { threshold: 0.1 });
    
    // Observar elementos con clase animate-on-scroll
    document.querySelectorAll('.animate-on-scroll').forEach(el => {
      observer.observe(el);
    });
  }
  
  setupTooltips() {
    // Inicializar tooltips
    document.querySelectorAll('[data-tooltip]').forEach(element => {
      element.addEventListener('mouseenter', (e) => {
        this.showTooltip(e.target);
      });
      
      element.addEventListener('mouseleave', (e) => {
        this.hideTooltip(e.target);
      });
    });
  }
  
  showTooltip(element) {
    const tooltip = element.getAttribute('data-tooltip');
    if (!tooltip) return;
    
    const tooltipElement = document.createElement('div');
    tooltipElement.className = 'tooltip-popup';
    tooltipElement.textContent = tooltip;
    
    document.body.appendChild(tooltipElement);
    
    const rect = element.getBoundingClientRect();
    tooltipElement.style.cssText = `
      position: fixed;
      top: ${rect.top - tooltipElement.offsetHeight - 10}px;
      left: ${rect.left + rect.width / 2 - tooltipElement.offsetWidth / 2}px;
      z-index: 1000;
    `;
    
    element._tooltip = tooltipElement;
  }
  
  hideTooltip(element) {
    if (element._tooltip) {
      element._tooltip.remove();
      delete element._tooltip;
    }
  }
}

// Inicialización cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
  console.log('🚀 Inicializando Sequoia Speed App...');
  
  // Inicializar componentes
  window.SequoiaSpeed.paymentManager = new PaymentMethodManager();
  window.SequoiaSpeed.formValidator = new FormValidator('#formPedido');
  window.SequoiaSpeed.uiEnhancer = new UIEnhancer();
  
  // Marcar como inicializado
  window.SequoiaSpeed.initialized = true;
  
  console.log('✅ Sequoia Speed App inicializado completamente');
  
  // Trigger evento de inicialización
  document.dispatchEvent(new CustomEvent('sequoiaSpeedReady'));
});

// Exportar para uso global
window.PaymentMethodManager = PaymentMethodManager;
window.FormValidator = FormValidator;
window.UIEnhancer = UIEnhancer;
