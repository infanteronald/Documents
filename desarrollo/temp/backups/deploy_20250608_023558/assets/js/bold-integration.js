/**
 * Bold PSE Integration - Sistema de Pago Integrado
 * Sequoia Speed - Gestión profesional de pagos con Bold PSE
 * 
 * @version 2.0.0
 * @author Sequoia Speed Team
 */

class BoldPaymentIntegration {
  constructor() {
    this.config = {
      apiEndpoint: '/public/api/bold/',
      timeoutDuration: 300000, // 5 minutos
      retryAttempts: 3,
      debug: true
    };

    this.state = {
      initialized: false,
      inProgress: false,
      completed: false,
      orderId: null,
      amount: 0,
      method: null,
      customer: null,
      billing: null
    };
    
    this.ui = {
      container: null,
      form: null,
      submitButton: null,
      progressIndicator: null
    };
    
    this.timeouts = new Map();
    this.intervals = new Map();
    this.paymentWindow = null;
    
    this.init();
  }

  init() {
    this.log('🚀 Inicializando integración Bold PSE...');
    
    // Buscar elementos principales
    this.findUIElements();
    
    // Configurar eventos
    this.setupEventListeners();
    
    // Mejorar interfaz
    this.enhanceInterface();
    
    // Configurar validación
    this.setupValidation();
    
    this.log('✅ Integración Bold PSE inicializada');
  }

  findUIElements() {
    // Buscar container principal
    this.ui.container = this.findPaymentContainer();
    this.ui.form = document.getElementById('formPedido');
    this.ui.submitButton = this.ui.form?.querySelector('button[type="submit"]');
    
    if (!this.ui.container) {
      this.log('⚠️ Container de pago no encontrado, creando uno nuevo');
      this.createPaymentContainer();
    }
  }

  findPaymentContainer() {
    const selectors = [
      '#bold-payment-container',
      '.pse-bold-container',
      '#info_pago',
      '[id*="bold"]',
      '[class*="bold"]'
    ];
    
    for (const selector of selectors) {
      const element = document.querySelector(selector);
      if (element) {
        this.log(`📍 Container encontrado: ${selector}`);
        return element;
      }
    }
    
    return null;
  }

  createPaymentContainer() {
    if (!this.ui.form) return;
    
    const container = document.createElement('div');
    container.id = 'bold-payment-container';
    container.className = 'bold-payment-container';
    
    // Insertar después del método de pago
    const metodoPagoSelect = document.getElementById('metodo_pago');
    if (metodoPagoSelect && metodoPagoSelect.parentNode) {
      metodoPagoSelect.parentNode.insertAdjacentElement('afterend', container);
      this.ui.container = container;
    }
  }

  setupEventListeners() {
    // Cambios en método de pago
    const metodoPagoSelect = document.getElementById('metodo_pago');
    if (metodoPagoSelect) {
      metodoPagoSelect.addEventListener('change', (e) => {
        this.onPaymentMethodChange(e.target.value);
      });
    }

    // Mensajes de ventana de pago
    window.addEventListener('message', (event) => {
      this.handlePaymentMessage(event);
    });

    // Envío del formulario
    if (this.ui.form) {
      this.ui.form.addEventListener('submit', (e) => {
        this.onFormSubmit(e);
      });
    }

    // Validación de campos
    this.setupFieldValidation();

    // Eventos de ventana
    window.addEventListener('beforeunload', (e) => {
      if (this.state.inProgress && !this.state.completed) {
        e.preventDefault();
        e.returnValue = 'Tiene un pago en proceso. ¿Está seguro de salir?';
      }
    });
  }

  setupFieldValidation() {
    const fields = ['nombre', 'correo', 'telefono', 'direccion'];
    
    fields.forEach(fieldName => {
      const input = document.querySelector(`input[name="${fieldName}"]`);
      if (input) {
        input.addEventListener('input', () => {
          this.validateField(fieldName, input.value);
          this.updateFormValidation();
        });
        
        input.addEventListener('blur', () => {
          this.validateField(fieldName, input.value, true);
        });
      }
    });
  }

  enhanceInterface() {
    if (!this.ui.container) return;
    
    // Crear indicador de progreso
    this.createProgressIndicator();
    
    // Mejorar botón de envío
    this.enhanceSubmitButton();
    
    // Agregar indicadores de validación
    this.addValidationIndicators();
  }

  createProgressIndicator() {
    const progressHTML = `
      <div id="bold-progress" class="bold-payment-progress" style="display: none;">
        <div class="progress-steps">
          <div class="step active" data-step="1">
            <div class="step-icon">📝</div>
            <div class="step-label">Datos</div>
          </div>
          <div class="step" data-step="2">
            <div class="step-icon">💳</div>
            <div class="step-label">Pago</div>
          </div>
          <div class="step" data-step="3">
            <div class="step-icon">✅</div>
            <div class="step-label">Confirmación</div>
          </div>
        </div>
        <div class="progress-bar">
          <div class="progress-fill" style="width: 33%;"></div>
        </div>
      </div>
    `;

    if (this.ui.form) {
      this.ui.form.insertAdjacentHTML('afterbegin', progressHTML);
      this.ui.progressIndicator = document.getElementById('bold-progress');
    }
  }

  updateProgress(step) {
    if (!this.ui.progressIndicator) return;

    const steps = this.ui.progressIndicator.querySelectorAll('.step');
    const progressFill = this.ui.progressIndicator.querySelector('.progress-fill');
    
    steps.forEach((stepEl, index) => {
      stepEl.classList.remove('active', 'completed');
      if (index < step) {
        stepEl.classList.add('completed');
      } else if (index === step - 1) {
        stepEl.classList.add('active');
      }
    });

    const percentage = (step / 3) * 100;
    if (progressFill) {
      progressFill.style.width = `${percentage}%`;
    }
  }

  enhanceSubmitButton() {
    if (!this.ui.submitButton) return;

    this.ui.submitButton.dataset.originalText = this.ui.submitButton.textContent;
    this.ui.submitButton.style.position = 'relative';
    this.ui.submitButton.style.overflow = 'hidden';
  }

  updateSubmitButton(state, text = null, disabled = null) {
    if (!this.ui.submitButton) return;

    const button = this.ui.submitButton;
    const originalText = button.dataset.originalText || 'Enviar pedido';

    const states = {
      loading: {
        html: `<span class="button-spinner"></span>${text || 'Procesando...'}`,
        disabled: true,
        style: { background: 'var(--gray-medium)' }
      },
      success: {
        html: `✅ ${text || 'Pago completado'}`,
        disabled: false,
        style: { background: 'var(--apple-blue)' }
      },
      error: {
        html: `❌ ${text || 'Error - Reintentar'}`,
        disabled: false,
        style: { background: '#ff6b6b' }
      },
      waiting: {
        html: `⏳ ${text || 'Esperando pago...'}`,
        disabled: true,
        style: { background: 'var(--gray-medium)' }
      },
      default: {
        html: originalText,
        disabled: disabled || false,
        style: { background: 'var(--apple-blue)' }
      }
    };

    const stateConfig = states[state] || states.default;
    button.innerHTML = stateConfig.html;
    button.disabled = stateConfig.disabled;
    Object.assign(button.style, stateConfig.style);
  }

  addValidationIndicators() {
    const fields = ['nombre', 'correo', 'telefono', 'direccion'];
    
    fields.forEach(fieldName => {
      const input = document.querySelector(`input[name="${fieldName}"]`);
      if (input && !input.parentElement.querySelector('.validation-indicator')) {
        const wrapper = document.createElement('div');
        wrapper.className = 'input-wrapper';
        wrapper.style.position = 'relative';
        
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        
        const indicator = document.createElement('div');
        indicator.className = 'validation-indicator';
        indicator.style.cssText = `
          position: absolute;
          right: 8px;
          top: 50%;
          transform: translateY(-50%);
          opacity: 0;
          transition: all 0.3s ease;
          z-index: 10;
        `;
        wrapper.appendChild(indicator);
      }
    });
  }

  validateField(fieldName, value, showFeedback = false) {
    const validators = {
      nombre: {
        test: (val) => val.trim().length >= 3,
        message: 'Mínimo 3 caracteres'
      },
      correo: {
        test: (val) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val),
        message: 'Email inválido'
      },
      telefono: {
        test: (val) => /^[0-9]{7,15}$/.test(val.replace(/\s/g, '')),
        message: 'Solo números, 7-15 dígitos'
      },
      direccion: {
        test: (val) => val.trim().length >= 10,
        message: 'Dirección muy corta'
      }
    };

    const validator = validators[fieldName];
    if (!validator) return false;

    const isValid = validator.test(value);
    this.updateFieldIndicator(fieldName, isValid, showFeedback ? validator.message : '');
    
    return isValid;
  }

  updateFieldIndicator(fieldName, isValid, message = '') {
    const input = document.querySelector(`input[name="${fieldName}"]`);
    const wrapper = input?.parentElement;
    const indicator = wrapper?.querySelector('.validation-indicator');
    
    if (!input || !indicator) return;

    indicator.innerHTML = isValid ? '✅' : (message ? '❌' : '');
    indicator.style.opacity = (isValid || message) ? '1' : '0';
    indicator.style.color = isValid ? 'var(--apple-blue)' : '#ff6b6b';
    
    input.style.borderColor = input.value ? 
      (isValid ? 'var(--apple-blue)' : '#ff6b6b') : 
      'var(--vscode-border)';

    if (!isValid && message) {
      this.showFieldError(fieldName, message);
    }
  }

  showFieldError(fieldName, message) {
    const input = document.querySelector(`input[name="${fieldName}"]`);
    if (!input) return;

    const existingError = input.parentElement.querySelector('.field-error');
    if (existingError) existingError.remove();

    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.style.cssText = `
      color: #ff6b6b;
      font-size: 0.75rem;
      margin-top: 4px;
      opacity: 0;
      transition: opacity 0.3s ease;
    `;
    errorDiv.textContent = message;

    input.parentElement.appendChild(errorDiv);
    setTimeout(() => errorDiv.style.opacity = '1', 10);
    
    this.timeouts.set(`error-${fieldName}`, setTimeout(() => {
      errorDiv.style.opacity = '0';
      setTimeout(() => errorDiv.remove(), 300);
    }, 3000));
  }

  updateFormValidation() {
    const fields = ['nombre', 'correo', 'telefono', 'direccion', 'metodo_pago'];
    const validations = fields.map(field => {
      if (field === 'metodo_pago') {
        return document.querySelector(`[name="${field}"]`)?.value !== '';
      }
      const value = document.querySelector(`input[name="${field}"]`)?.value || '';
      return this.validateField(field, value);
    });

    const allValid = validations.every(v => v);
    
    if (this.ui.submitButton && !this.state.inProgress) {
      this.ui.submitButton.disabled = !allValid;
      this.ui.submitButton.style.opacity = allValid ? '1' : '0.6';
    }

    return allValid;
  }

  onPaymentMethodChange(method) {
    this.state.method = method;
    
    if (this.state.inProgress) {
      this.resetPaymentState();
    }

    this.showPaymentMethodInfo(method);
  }

  showPaymentMethodInfo(method) {
    const infoContainer = document.getElementById('info_pago');
    if (!infoContainer) return;

    infoContainer.style.opacity = '0';
    infoContainer.style.transform = 'translateY(-10px)';
    
    setTimeout(() => {
      infoContainer.style.transition = 'all 0.3s ease';
      infoContainer.style.opacity = '1';
      infoContainer.style.transform = 'translateY(0)';
    }, 150);
  }

  async initializePayment() {
    this.log('🚀 Inicializando pago Bold PSE...');
    
    try {
      if (!this.ui.container) {
        throw new Error('Container de pago no disponible');
      }

      if (this.ui.progressIndicator) {
        this.ui.progressIndicator.style.display = 'block';
        this.updateProgress(2);
      }

      this.state.initialized = true;
      this.state.inProgress = true;
      this.state.orderId = this.generateOrderId();

      this.showLoadingInterface();
      await this.preparePaymentData();
      
      this.log('✅ Pago inicializado correctamente');
      return true;
    } catch (error) {
      this.log('❌ Error inicializando pago:', error);
      this.showError('Error al inicializar el pago: ' + error.message);
      return false;
    }
  }

  generateOrderId() {
    const timestamp = Date.now();
    const random = Math.random().toString(36).substr(2, 9);
    return `SEQ-${timestamp}-${random}`;
  }

  showLoadingInterface() {
    if (!this.ui.container) return;

    this.ui.container.innerHTML = `
      <div class="bold-loading">
        <div class="loading-animation">
          <div class="pulse-ring"></div>
          <div class="pulse-ring delay-1"></div>
          <div class="pulse-ring delay-2"></div>
          <div class="loading-icon">🔒</div>
        </div>
        <div class="loading-content">
          <div class="loading-title">Preparando pago seguro</div>
          <div class="loading-subtitle">Conectando con Bold PSE...</div>
          <div class="loading-progress">
            <div class="loading-bar">
              <div class="loading-fill"></div>
            </div>
            <div class="loading-percentage">0%</div>
          </div>
        </div>
      </div>
    `;

    this.animateLoadingProgress();
  }

  animateLoadingProgress() {
    const fill = this.ui.container.querySelector('.loading-fill');
    const percentage = this.ui.container.querySelector('.loading-percentage');
    const subtitle = this.ui.container.querySelector('.loading-subtitle');
    
    if (!fill || !percentage || !subtitle) return;

    const steps = [
      { progress: 25, text: 'Validando datos...' },
      { progress: 50, text: 'Generando hash de seguridad...' },
      { progress: 75, text: 'Conectando con Bold...' },
      { progress: 100, text: 'Listo para pagar' }
    ];

    let currentStep = 0;
    
    const updateStep = () => {
      if (currentStep < steps.length) {
        const step = steps[currentStep];
        
        fill.style.width = step.progress + '%';
        percentage.textContent = step.progress + '%';
        subtitle.textContent = step.text;
        
        currentStep++;
        
        if (currentStep < steps.length) {
          this.timeouts.set('loading', setTimeout(updateStep, 800 + Math.random() * 400));
        } else {
          this.timeouts.set('ready', setTimeout(() => this.showPaymentReady(), 500));
        }
      }
    };

    updateStep();
  }

  async preparePaymentData() {
    this.log('📋 Preparando datos del pago...');
    
    // Obtener datos del formulario
    this.state.customer = {
      email: document.querySelector('input[name="correo"]')?.value || '',
      fullName: document.querySelector('input[name="nombre"]')?.value || '',
      phone: document.querySelector('input[name="telefono"]')?.value || '',
      dialCode: '+57'
    };

    this.state.billing = {
      address: document.querySelector('input[name="direccion"]')?.value || '',
      city: 'Bogotá',
      state: 'Cundinamarca',
      country: 'CO'
    };

    // Obtener monto
    const montoField = document.querySelector('input[name="monto"]');
    if (montoField && montoField.value) {
      const rawValue = montoField.value.replace(/[^\d]/g, '');
      this.state.amount = parseInt(rawValue) || 0;
    }

    this.log('✅ Datos preparados:', {
      orderId: this.state.orderId,
      amount: this.state.amount,
      customer: this.state.customer.fullName
    });
  }

  showPaymentReady() {
    if (!this.ui.container) return;

    this.ui.container.innerHTML = `
      <div class="bold-payment-ready">
        <div class="payment-info">
          <div class="payment-icon">🎯</div>
          <div class="payment-details">
            <div class="payment-title">Pago Listo</div>
            <div class="payment-order">Orden: ${this.state.orderId}</div>
            ${this.state.amount > 0 ? `<div class="payment-amount">$${this.state.amount.toLocaleString()} COP</div>` : ''}
          </div>
        </div>
        <button type="button" class="bold-payment-btn" onclick="boldPayment.openPaymentWindow()">
          <span class="btn-icon">🔒</span>
          <span class="btn-text">Abrir Pago Seguro</span>
          <span class="btn-arrow">→</span>
        </button>
        <div class="payment-security">
          <div class="security-badge">
            <span class="security-icon">🛡️</span>
            <span class="security-text">Pago 100% seguro con Bold PSE</span>
          </div>
        </div>
      </div>
    `;

    this.updateSubmitButton('waiting', 'Complete el pago primero');
  }

  async openPaymentWindow() {
    this.log('🚀 Abriendo ventana de pago...');
    
    try {
      const paymentData = {
        order_id: this.state.orderId,
        amount: this.state.amount,
        method: this.state.method,
        customer_data: JSON.stringify(this.state.customer),
        billing_address: JSON.stringify(this.state.billing)
      };

      const paymentParams = new URLSearchParams(paymentData);
      const paymentUrl = 'bold_payment.php?' + paymentParams.toString();
      
      // Abrir ventana de pago
      this.paymentWindow = window.open(
        paymentUrl,
        'boldPayment',
        'width=600,height=700,scrollbars=yes,resizable=yes,toolbar=no,menubar=no'
      );
      
      if (this.paymentWindow) {
        this.showPaymentInProgress();
        this.monitorPaymentWindow();
      } else {
        throw new Error('No se pudo abrir la ventana de pago. Verifique que no esté bloqueada por el navegador.');
      }
    } catch (error) {
      this.log('❌ Error abriendo ventana de pago:', error);
      this.showError(error.message);
    }
  }

  showPaymentInProgress() {
    if (!this.ui.container) return;

    this.ui.container.innerHTML = `
      <div class="bold-payment-progress">
        <div class="progress-animation">
          <div class="payment-spinner"></div>
          <div class="progress-icon">💳</div>
        </div>
        <div class="progress-info">
          <div class="progress-title">Pago en Proceso</div>
          <div class="progress-order">Orden: ${this.state.orderId}</div>
          <div class="progress-subtitle">Complete su pago en la ventana que se abrió</div>
        </div>
        <div class="progress-actions">
          <button type="button" class="focus-window-btn" onclick="boldPayment.focusPaymentWindow()">
            🔍 Ver Ventana de Pago
          </button>
        </div>
        <div class="progress-tips">
          <div class="tip">💡 No cierre esta página hasta completar el pago</div>
          <div class="tip">⏱️ El proceso puede tomar unos minutos</div>
        </div>
      </div>
    `;

    this.updateProgress(3);
  }

  monitorPaymentWindow() {
    if (!this.paymentWindow) return;

    const checkWindow = () => {
      if (this.paymentWindow.closed && !this.state.completed) {
        this.onPaymentClosed();
      } else if (!this.paymentWindow.closed) {
        this.timeouts.set('monitor', setTimeout(checkWindow, 1000));
      }
    };

    checkWindow();
  }

  focusPaymentWindow() {
    if (this.paymentWindow && !this.paymentWindow.closed) {
      this.paymentWindow.focus();
    } else {
      this.showMessage('info', 'Ventana cerrada', 'La ventana de pago se ha cerrado');
    }
  }

  handlePaymentMessage(event) {
    if (!event.data || event.data.type !== 'bold_payment_update') return;

    const { status, orderId, data } = event.data;
    this.log('📨 Mensaje de pago recibido:', status, orderId);

    const handlers = {
      payment_started: () => this.onPaymentStarted(orderId),
      payment_success: () => this.onPaymentSuccess(orderId, data),
      payment_error: () => this.onPaymentError(orderId, data),
      payment_closed: () => this.onPaymentClosed(orderId)
    };

    const handler = handlers[status];
    if (handler) handler();
  }

  onPaymentStarted(orderId) {
    this.log('✅ Pago iniciado:', orderId);
    this.showMessage('success', 'Pago iniciado', 'Complete el proceso en la ventana de pago');
  }

  onPaymentSuccess(orderId, data) {
    this.log('🎉 Pago exitoso:', orderId);
    
    this.state.completed = true;
    this.state.inProgress = false;
    
    this.showPaymentSuccess(orderId);
    this.updateSubmitButton('success', 'Enviar pedido ✓');
    this.updateProgress(3);
    
    // Agregar datos al formulario
    this.addPaymentDataToForm(orderId, 'completed', data);
  }

  onPaymentError(orderId, data) {
    this.log('❌ Error en pago:', orderId, data);
    
    this.state.inProgress = false;
    this.showPaymentError(data?.error || 'Error desconocido en el pago');
    this.updateSubmitButton('error', 'Error - Complete el pago');
  }

  onPaymentClosed(orderId) {
    this.log('🔒 Ventana de pago cerrada:', orderId);
    
    if (!this.state.completed) {
      this.showMessage('info', 'Ventana cerrada', 'Si completó el pago, recibirá confirmación pronto');
      this.updateSubmitButton('default');
    }
  }

  showPaymentSuccess(orderId) {
    if (!this.ui.container) return;

    this.ui.container.innerHTML = `
      <div class="bold-payment-success">
        <div class="success-animation">
          <div class="success-checkmark">
            <div class="check-icon">✅</div>
          </div>
        </div>
        <div class="success-info">
          <div class="success-title">¡Pago Completado!</div>
          <div class="success-order">Orden: ${orderId}</div>
          <div class="success-subtitle">Su pago ha sido procesado exitosamente</div>
        </div>
        <div class="success-actions">
          <div class="next-step">
            <span class="step-icon">📤</span>
            <span class="step-text">Ahora puede enviar su pedido</span>
          </div>
        </div>
      </div>
    `;

    this.triggerSuccessAnimation();
  }

  triggerSuccessAnimation() {
    const confettiCount = 50;
    
    for (let i = 0; i < confettiCount; i++) {
      this.timeouts.set(`confetti-${i}`, setTimeout(() => {
        this.createConfetti();
      }, i * 50));
    }
  }

  createConfetti() {
    const colors = ['#007aff', '#30d158', '#ff9f0a', '#ff453a'];
    const confetti = document.createElement('div');
    
    confetti.style.cssText = `
      position: fixed;
      width: 10px;
      height: 10px;
      background: ${colors[Math.floor(Math.random() * colors.length)]};
      left: ${Math.random() * 100}vw;
      top: -10px;
      z-index: 10000;
      border-radius: 2px;
      pointer-events: none;
    `;
    
    document.body.appendChild(confetti);
    
    confetti.animate([
      { transform: 'translateY(0) rotate(0deg)', opacity: 1 },
      { transform: `translateY(${window.innerHeight + 50}px) rotate(360deg)`, opacity: 0 }
    ], {
      duration: 3000,
      easing: 'cubic-bezier(0.25, 0.46, 0.45, 0.94)'
    }).onfinish = () => confetti.remove();
  }

  showPaymentError(message) {
    if (!this.ui.container) return;

    this.ui.container.innerHTML = `
      <div class="bold-payment-error">
        <div class="error-icon">❌</div>
        <div class="error-info">
          <div class="error-title">Error en el Pago</div>
          <div class="error-message">${message}</div>
        </div>
        <div class="error-actions">
          <button type="button" class="retry-btn" onclick="boldPayment.retryPayment()">
            <span class="btn-icon">🔄</span>
            <span class="btn-text">Intentar Nuevamente</span>
          </button>
        </div>
      </div>
    `;
  }

  showError(message) {
    this.showMessage('error', 'Error', message);
  }

  showMessage(type, title, subtitle, duration = 5000) {
    const messageId = 'bold-message-' + Date.now();
    const colors = {
      success: { bg: 'rgba(48, 209, 88, 0.1)', border: '#30d158', text: '#30d158' },
      info: { bg: 'rgba(0, 122, 255, 0.1)', border: '#007aff', text: '#007aff' },
      error: { bg: 'rgba(255, 69, 58, 0.1)', border: '#ff453a', text: '#ff453a' }
    };

    const color = colors[type] || colors.info;
    
    const messageDiv = document.createElement('div');
    messageDiv.id = messageId;
    messageDiv.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      background: ${color.bg};
      border: 1px solid ${color.border};
      color: ${color.text};
      padding: 16px;
      border-radius: 12px;
      max-width: 300px;
      z-index: 10000;
      transform: translateX(100%);
      transition: transform 0.3s ease;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    `;
    
    messageDiv.innerHTML = `
      <div style="font-weight: 600; margin-bottom: 4px;">${title}</div>
      <div style="font-size: 0.9rem; opacity: 0.8;">${subtitle}</div>
    `;
    
    document.body.appendChild(messageDiv);
    
    setTimeout(() => messageDiv.style.transform = 'translateX(0)', 10);
    
    this.timeouts.set(messageId, setTimeout(() => {
      messageDiv.style.transform = 'translateX(100%)';
      setTimeout(() => messageDiv.remove(), 300);
    }, duration));
  }

  addPaymentDataToForm(orderId, status, data = {}) {
    if (!this.ui.form) return;

    // Agregar campos ocultos con información del pago
    const fields = [
      { name: 'payment_order_id', value: orderId },
      { name: 'payment_status', value: status },
      { name: 'payment_method', value: this.state.method },
      { name: 'payment_data', value: JSON.stringify(data) }
    ];

    fields.forEach(field => {
      let input = this.ui.form.querySelector(`input[name="${field.name}"]`);
      if (!input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = field.name;
        this.ui.form.appendChild(input);
      }
      input.value = field.value;
    });
  }

  retryPayment() {
    this.log('🔄 Reintentando pago...');
    this.resetPaymentState();
    this.initializePayment();
  }

  resetPaymentState() {
    this.state.initialized = false;
    this.state.inProgress = false;
    this.state.completed = false;
    
    // Cerrar ventana de pago si existe
    if (this.paymentWindow && !this.paymentWindow.closed) {
      this.paymentWindow.close();
    }
    
    // Limpiar timeouts e intervals
    this.timeouts.forEach(timeout => clearTimeout(timeout));
    this.intervals.forEach(interval => clearInterval(interval));
    this.timeouts.clear();
    this.intervals.clear();
    
    this.updateProgress(1);
    this.updateSubmitButton('default');
  }

  onFormSubmit(event) {
    const metodoPago = document.getElementById('metodo_pago')?.value;
    const isBoldMethod = ['PSE Bold', 'Botón Bancolombia', 'Tarjeta de Crédito o Débito'].includes(metodoPago);
    
    if (isBoldMethod && !this.state.completed) {
      event.preventDefault();
      
      if (this.state.inProgress) {
        this.showMessage('info', 'Pago en proceso', 'Complete el pago antes de enviar el formulario');
        this.focusPaymentWindow();
      } else {
        this.showMessage('info', 'Pago requerido', 'Debe completar el pago antes de enviar el formulario');
      }
      
      return false;
    }
    
    this.updateSubmitButton('loading', 'Enviando pedido...');
    return true;
  }

  log(message, ...args) {
    if (this.config.debug) {
      console.log(`[Bold Integration] ${message}`, ...args);
    }
  }

  // API pública para compatibilidad
  static init() {
    if (!window.boldPayment) {
      window.boldPayment = new BoldPaymentIntegration();
    }
    return window.boldPayment;
  }

  static getInstance() {
    return window.boldPayment || BoldPaymentIntegration.init();
  }
}

// Inicialización automática
document.addEventListener('DOMContentLoaded', function() {
  const boldPayment = BoldPaymentIntegration.init();
  
  // Sobrescribir función legacy si existe
  if (typeof initializeBoldPayment === 'function') {
    window.originalInitializeBoldPayment = window.initializeBoldPayment;
    window.initializeBoldPayment = function() {
      console.log('🔄 Función legacy detectada, usando integración moderna...');
      return boldPayment.initializePayment();
    };
  }
  
  // Crear función global para compatibilidad
  window.initializeBoldPaymentEnhanced = function() {
    return boldPayment.initializePayment();
  };
});

// Exportar para uso como módulo
if (typeof module !== 'undefined' && module.exports) {
  module.exports = BoldPaymentIntegration;
}
