/**
 * Bold PSE - Experiencia de Usuario Mejorada
 * Optimizaciones para el proceso de pago
 */

class PaymentUXEnhancer {
  constructor() {
    this.paymentState = {
      initialized: false,
      inProgress: false,
      completed: false,
      orderId: null,
      amount: 0,
      method: null
    };
    
    this.ui = {
      container: null,
      form: null,
      submitButton: null,
      progressSteps: null
    };
    
    this.timeouts = new Map();
    this.intervals = new Map();
    
    this.init();
  }

  init() {
    console.log('🎨 Inicializando mejorador de UX de pagos...');
    
    // Encontrar elementos principales
    this.ui.container = document.getElementById('bold-payment-container');
    this.ui.form = document.getElementById('formPedido');
    this.ui.submitButton = this.ui.form?.querySelector('button[type="submit"]');
    
    // Configurar eventos
    this.setupEventListeners();
    
    // Mejorar interfaz existente
    this.enhancePaymentInterface();
    
    // Configurar validación en tiempo real
    this.setupRealTimeValidation();
    
    console.log('✅ Mejorador de UX de pagos inicializado');
  }

  setupEventListeners() {
    // Listener para cambios en método de pago
    const metodoPagoSelect = document.getElementById('metodo_pago');
    if (metodoPagoSelect) {
      metodoPagoSelect.addEventListener('change', (e) => {
        this.onPaymentMethodChange(e.target.value);
      });
    }

    // Listener para mensajes de la ventana de pago
    window.addEventListener('message', (event) => {
      this.handlePaymentMessage(event);
    });

    // Listener para envío del formulario
    if (this.ui.form) {
      this.ui.form.addEventListener('submit', (e) => {
        this.onFormSubmit(e);
      });
    }

    // Listeners para validación en tiempo real de campos
    const campos = ['nombre', 'correo', 'telefono', 'direccion'];
    campos.forEach(campo => {
      const input = document.querySelector(`input[name="${campo}"]`);
      if (input) {
        input.addEventListener('input', () => this.validateField(campo, input.value));
        input.addEventListener('blur', () => this.validateField(campo, input.value, true));
      }
    });
  }

  onPaymentMethodChange(method) {
    this.paymentState.method = method;
    
    // Resetear estado si cambia el método
    if (this.paymentState.inProgress) {
      this.resetPaymentState();
    }

    // Mostrar información específica del método
    this.showPaymentMethodInfo(method);
  }

  showPaymentMethodInfo(method) {
    const infoContainer = document.getElementById('info_pago');
    if (!infoContainer) return;

    // Agregar animación de transición
    infoContainer.style.opacity = '0';
    infoContainer.style.transform = 'translateY(-10px)';
    
    setTimeout(() => {
      // El contenido se actualiza desde el listener original
      infoContainer.style.transition = 'all 0.3s ease';
      infoContainer.style.opacity = '1';
      infoContainer.style.transform = 'translateY(0)';
    }, 150);
  }

  enhancePaymentInterface() {
    if (!this.ui.container) return;

    // Agregar indicador de progreso
    this.createProgressIndicator();
    
    // Mejorar botón de envío
    this.enhanceSubmitButton();
    
    // Agregar indicadores de validación en tiempo real
    this.addValidationIndicators();
  }

  createProgressIndicator() {
    const progressHTML = `
      <div id="payment-progress" class="payment-progress" style="display: none;">
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

    // Insertar al inicio del formulario
    if (this.ui.form) {
      this.ui.form.insertAdjacentHTML('afterbegin', progressHTML);
      this.ui.progressSteps = document.getElementById('payment-progress');
    }
  }

  updateProgress(step) {
    if (!this.ui.progressSteps) return;

    const steps = this.ui.progressSteps.querySelectorAll('.step');
    const progressFill = this.ui.progressSteps.querySelector('.progress-fill');
    
    steps.forEach((stepEl, index) => {
      if (index < step) {
        stepEl.classList.add('completed');
        stepEl.classList.remove('active');
      } else if (index === step - 1) {
        stepEl.classList.add('active');
        stepEl.classList.remove('completed');
      } else {
        stepEl.classList.remove('active', 'completed');
      }
    });

    // Actualizar barra de progreso
    const percentage = (step / 3) * 100;
    progressFill.style.width = `${percentage}%`;
  }

  enhanceSubmitButton() {
    if (!this.ui.submitButton) return;

    // Agregar estado original
    this.ui.submitButton.dataset.originalText = this.ui.submitButton.textContent;
    
    // Mejorar estilos
    this.ui.submitButton.style.position = 'relative';
    this.ui.submitButton.style.overflow = 'hidden';
  }

  updateSubmitButton(state, text = null, disabled = null) {
    if (!this.ui.submitButton) return;

    const button = this.ui.submitButton;
    const originalText = button.dataset.originalText || 'Enviar pedido';

    switch (state) {
      case 'loading':
        button.innerHTML = `
          <span class="button-spinner"></span>
          ${text || 'Procesando...'}
        `;
        button.disabled = true;
        button.style.background = 'var(--gray-medium)';
        break;
        
      case 'success':
        button.innerHTML = '✅ ' + (text || 'Pago completado');
        button.disabled = false;
        button.style.background = 'var(--apple-blue)';
        break;
        
      case 'error':
        button.innerHTML = '❌ ' + (text || 'Error - Reintentar');
        button.disabled = false;
        button.style.background = '#ff6b6b';
        break;
        
      case 'waiting':
        button.innerHTML = '⏳ ' + (text || 'Esperando pago...');
        button.disabled = true;
        button.style.background = 'var(--gray-medium)';
        break;
        
      default:
        button.innerHTML = originalText;
        button.disabled = disabled || false;
        button.style.background = 'var(--apple-blue)';
    }
  }

  addValidationIndicators() {
    const campos = ['nombre', 'correo', 'telefono', 'direccion'];
    
    campos.forEach(campo => {
      const input = document.querySelector(`input[name="${campo}"]`);
      if (input) {
        // Crear contenedor de validación
        const wrapper = document.createElement('div');
        wrapper.className = 'input-wrapper';
        wrapper.style.position = 'relative';
        
        // Envolver el input
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        
        // Agregar indicador de validación
        const indicator = document.createElement('div');
        indicator.className = 'validation-indicator';
        indicator.style.cssText = `
          position: absolute;
          right: 8px;
          top: 50%;
          transform: translateY(-50%);
          opacity: 0;
          transition: all 0.3s ease;
        `;
        wrapper.appendChild(indicator);
        
        input.dataset.indicator = campo;
      }
    });
  }

  validateField(fieldName, value, showFeedback = false) {
    const input = document.querySelector(`input[name="${fieldName}"]`);
    const wrapper = input?.parentElement;
    const indicator = wrapper?.querySelector('.validation-indicator');
    
    if (!input || !indicator) return false;

    let isValid = false;
    let message = '';

    switch (fieldName) {
      case 'nombre':
        isValid = value.trim().length >= 3;
        message = isValid ? '✅' : (showFeedback ? '❌ Mínimo 3 caracteres' : '');
        break;
        
      case 'correo':
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        isValid = emailRegex.test(value);
        message = isValid ? '✅' : (showFeedback ? '❌ Email inválido' : '');
        break;
        
      case 'telefono':
        const phoneRegex = /^[0-9]{7,15}$/;
        isValid = phoneRegex.test(value.replace(/\s/g, ''));
        message = isValid ? '✅' : (showFeedback ? '❌ Solo números, 7-15 dígitos' : '');
        break;
        
      case 'direccion':
        isValid = value.trim().length >= 10;
        message = isValid ? '✅' : (showFeedback ? '❌ Dirección muy corta' : '');
        break;
    }

    // Actualizar indicador
    indicator.innerHTML = message.startsWith('✅') ? '✅' : (message.startsWith('❌') ? '❌' : '');
    indicator.style.opacity = message ? '1' : '0';
    indicator.style.color = isValid ? 'var(--apple-blue)' : '#ff6b6b';
    
    // Actualizar borde del input
    input.style.borderColor = value ? (isValid ? 'var(--apple-blue)' : '#ff6b6b') : 'var(--vscode-border)';
    
    // Mostrar mensaje de feedback si es necesario
    if (showFeedback && message.startsWith('❌')) {
      this.showFieldError(fieldName, message.substring(2));
    }

    return isValid;
  }

  showFieldError(fieldName, message) {
    const input = document.querySelector(`input[name="${fieldName}"]`);
    if (!input) return;

    // Remover error previo
    const existingError = input.parentElement.querySelector('.field-error');
    if (existingError) existingError.remove();

    // Crear nuevo mensaje de error
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
    
    // Animar entrada
    setTimeout(() => errorDiv.style.opacity = '1', 10);
    
    // Auto-remover después de 3 segundos
    setTimeout(() => {
      errorDiv.style.opacity = '0';
      setTimeout(() => errorDiv.remove(), 300);
    }, 3000);
  }

  setupRealTimeValidation() {
    // Validar formulario completo cuando cambian los campos
    const requiredFields = ['nombre', 'correo', 'telefono', 'direccion', 'metodo_pago'];
    
    requiredFields.forEach(fieldName => {
      const input = document.querySelector(`[name="${fieldName}"]`);
      if (input) {
        input.addEventListener('input', () => {
          this.validateForm();
        });
      }
    });
  }

  validateForm() {
    const nombre = document.querySelector('input[name="nombre"]')?.value || '';
    const correo = document.querySelector('input[name="correo"]')?.value || '';
    const telefono = document.querySelector('input[name="telefono"]')?.value || '';
    const direccion = document.querySelector('input[name="direccion"]')?.value || '';
    const metodoPago = document.querySelector('select[name="metodo_pago"]')?.value || '';

    const validations = {
      nombre: this.validateField('nombre', nombre),
      correo: this.validateField('correo', correo),
      telefono: this.validateField('telefono', telefono),
      direccion: this.validateField('direccion', direccion),
      metodoPago: metodoPago !== ''
    };

    const allValid = Object.values(validations).every(v => v);
    
    // Actualizar botón de envío
    if (this.ui.submitButton && !this.paymentState.inProgress) {
      this.ui.submitButton.disabled = !allValid;
      this.ui.submitButton.style.opacity = allValid ? '1' : '0.6';
    }

    return allValid;
  }

  initializeBoldPaymentEnhanced() {
    // Intentar obtener el container si no está disponible
    if (!this.ui.container) {
      console.log('🔍 Container no inicializado, intentando obtenerlo...');
      this.ui.container = document.getElementById('bold-payment-container');
    }
    
    if (!this.ui.container) {
      console.error('❌ Container bold-payment-container no encontrado en UX mejorada');
      // Intentar buscar containers alternativos
      const alternativeContainers = [
        document.querySelector('.pse-bold-container'),
        document.getElementById('info_pago'),
        document.querySelector('[id*="bold"]'),
        document.querySelector('[class*="bold"]')
      ];
      
      for (let i = 0; i < alternativeContainers.length; i++) {
        if (alternativeContainers[i]) {
          console.log(`⚠️ Usando container alternativo: ${alternativeContainers[i].tagName}${alternativeContainers[i].id ? '#' + alternativeContainers[i].id : ''}${alternativeContainers[i].className ? '.' + alternativeContainers[i].className : ''}`);
          this.ui.container = alternativeContainers[i];
          break;
        }
      }
      
      if (!this.ui.container) {
        console.error('❌ No se pudo encontrar ningún container válido');
        return false;
      }
    }

    console.log('🚀 Inicializando pago Bold con UX mejorada...');
    console.log('📍 Container encontrado:', this.ui.container.id || this.ui.container.className || 'sin id/clase');
    
    try {
      // Mostrar progreso
      if (this.ui.progressSteps) {
        this.ui.progressSteps.style.display = 'block';
        this.updateProgress(2);
      }

      // Actualizar estado
      this.paymentState.initialized = true;
      this.paymentState.inProgress = true;
      this.paymentState.orderId = 'SEQ-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);

      // Mostrar loading mejorado
      this.showEnhancedLoading();

      // Configurar datos del pago
      setupPaymentData();
      
      console.log('✅ UX mejorada inicializada correctamente');
      return true;
    } catch (error) {
      console.error('❌ Error en UX mejorada:', error);
      return false;
    }
  }

  showEnhancedLoading() {
    if (!this.ui.container) return;

    this.ui.container.innerHTML = `
      <div class="enhanced-loading">
        <div class="loading-animation">
          <div class="pulse-ring"></div>
          <div class="pulse-ring delay-1"></div>
          <div class="pulse-ring delay-2"></div>
          <div class="loading-icon">🔒</div>
        </div>
        <div class="loading-text">
          <div class="loading-title">Preparando pago seguro</div>
          <div class="loading-subtitle">Conectando con Bold PSE...</div>
        </div>
        <div class="loading-progress">
          <div class="loading-bar">
            <div class="loading-fill"></div>
          </div>
          <div class="loading-percentage">0%</div>
        </div>
      </div>
    `;

    // Animar progreso de carga
    this.animateLoadingProgress();
  }

  animateLoadingProgress() {
    const fill = this.ui.container.querySelector('.loading-fill');
    const percentage = this.ui.container.querySelector('.loading-percentage');
    const subtitle = this.ui.container.querySelector('.loading-subtitle');
    
    if (!fill || !percentage || !subtitle) return;

    let progress = 0;
    const steps = [
      { progress: 25, text: 'Validando datos...' },
      { progress: 50, text: 'Generando hash de seguridad...' },
      { progress: 75, text: 'Conectando con Bold...' },
      { progress: 100, text: 'Listo para pagar' }
    ];

    let currentStep = 0;
    
    const updateProgress = () => {
      if (currentStep < steps.length) {
        const step = steps[currentStep];
        progress = step.progress;
        
        fill.style.width = progress + '%';
        percentage.textContent = progress + '%';
        subtitle.textContent = step.text;
        
        currentStep++;
        
        if (currentStep < steps.length) {
          setTimeout(updateProgress, 800 + Math.random() * 400);
        } else {
          // Completado - mostrar botón de pago
          setTimeout(() => this.showPaymentButton(), 500);
        }
      }
    };

    updateProgress();
  }

  showPaymentButton() {
    if (!this.ui.container) return;

    const orderId = this.paymentState.orderId;
    const monto = window.currentOrderData?.amount || 0;

    this.ui.container.innerHTML = `
      <div class="payment-ready">
        <div class="payment-info">
          <div class="payment-icon">🎯</div>
          <div class="payment-details">
            <div class="payment-title">Pago Listo</div>
            <div class="payment-order">Orden: ${orderId}</div>
            ${monto > 0 ? `<div class="payment-amount">$${monto.toLocaleString()} COP</div>` : ''}
          </div>
        </div>
        <button type="button" class="enhanced-payment-btn" onclick="paymentUX.openPaymentWindow()">
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

    // Actualizar botón de envío
    this.updateSubmitButton('waiting', 'Complete el pago primero');
  }

  openPaymentWindow() {
    console.log('🚀 Abriendo ventana de pago...');
    
    // Lógica para abrir ventana (usar la función existente)
    if (window.openPaymentWindow && window.currentOrderData) {
      const { orderId, amount, method, customer, billing } = window.currentOrderData;
      
      const paymentParams = new URLSearchParams({
        order_id: orderId,
        amount: amount,
        method: method,
        customer_data: JSON.stringify(customer),
        billing_address: JSON.stringify(billing)
      });

      const paymentUrl = 'bold_payment.php?' + paymentParams.toString();
      window.openPaymentWindow(paymentUrl, orderId);
      
      // Mostrar estado de pago en proceso
      this.showPaymentInProgress(orderId);
    }
  }

  showPaymentInProgress(orderId) {
    if (!this.ui.container) return;

    this.ui.container.innerHTML = `
      <div class="payment-in-progress">
        <div class="progress-animation">
          <div class="payment-spinner"></div>
          <div class="progress-icon">💳</div>
        </div>
        <div class="progress-info">
          <div class="progress-title">Pago en Proceso</div>
          <div class="progress-order">Orden: ${orderId}</div>
          <div class="progress-subtitle">Complete su pago en la ventana que se abrió</div>
        </div>
        <div class="progress-actions">
          <button type="button" class="focus-window-btn" onclick="paymentUX.focusPaymentWindow()">
            🔍 Ver Ventana de Pago
          </button>
        </div>
        <div class="progress-tips">
          <div class="tip">💡 No cierre esta página hasta completar el pago</div>
          <div class="tip">⏱️ El proceso puede tomar unos minutos</div>
        </div>
      </div>
    `;

    // Actualizar progreso
    this.updateProgress(3);
  }

  focusPaymentWindow() {
    if (window.focusPaymentWindow) {
      window.focusPaymentWindow();
    }
  }

  handlePaymentMessage(event) {
    if (!event.data || event.data.type !== 'bold_payment_update') return;

    const { status, orderId, data } = event.data;
    console.log('📨 Mensaje de pago recibido:', status, orderId);

    switch (status) {
      case 'payment_started':
        this.onPaymentStarted(orderId);
        break;
        
      case 'payment_success':
        this.onPaymentSuccess(orderId, data);
        break;
        
      case 'payment_error':
        this.onPaymentError(orderId, data);
        break;
        
      case 'payment_closed':
        this.onPaymentClosed(orderId);
        break;
    }
  }

  onPaymentStarted(orderId) {
    console.log('✅ Pago iniciado:', orderId);
    this.showSuccessMessage('Pago iniciado correctamente', 'Complete el proceso en la ventana de pago');
  }

  onPaymentSuccess(orderId, data) {
    console.log('🎉 Pago exitoso:', orderId);
    
    this.paymentState.completed = true;
    this.paymentState.inProgress = false;
    
    // Mostrar éxito con animación
    this.showPaymentSuccess(orderId);
    
    // Actualizar botón de envío
    this.updateSubmitButton('success', 'Enviar pedido ✓');
    
    // Completar progreso
    this.updateProgress(3);
    
    // Agregar datos al formulario
    if (window.addPaymentDataToForm) {
      window.addPaymentDataToForm(orderId, 'completed', data);
    }
  }

  onPaymentError(orderId, data) {
    console.log('❌ Error en pago:', orderId, data);
    
    this.paymentState.inProgress = false;
    
    // Mostrar error con opción de reintentar
    this.showPaymentError(data.error || 'Error desconocido');
    
    // Actualizar botón de envío
    this.updateSubmitButton('error', 'Error - Complete el pago');
  }

  onPaymentClosed(orderId) {
    console.log('🔒 Ventana de pago cerrada:', orderId);
    
    if (!this.paymentState.completed) {
      this.showInfoMessage('Ventana de pago cerrada', 'Si completó el pago, recibirá confirmación pronto');
      this.updateSubmitButton('default');
    }
  }

  showPaymentSuccess(orderId) {
    if (!this.ui.container) return;

    this.ui.container.innerHTML = `
      <div class="payment-success">
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

    // Animación de celebración
    this.triggerSuccessAnimation();
  }

  triggerSuccessAnimation() {
    // Crear confetti effect
    const confettiCount = 50;
    const container = document.body;
    
    for (let i = 0; i < confettiCount; i++) {
      setTimeout(() => {
        this.createConfetti(container);
      }, i * 50);
    }
  }

  createConfetti(container) {
    const confetti = document.createElement('div');
    confetti.style.cssText = `
      position: fixed;
      width: 10px;
      height: 10px;
      background: ${['#007aff', '#30d158', '#ff9f0a', '#ff453a'][Math.floor(Math.random() * 4)]};
      left: ${Math.random() * 100}vw;
      top: -10px;
      z-index: 10000;
      border-radius: 2px;
      pointer-events: none;
    `;
    
    container.appendChild(confetti);
    
    // Animar caída
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
      <div class="payment-error">
        <div class="error-icon">❌</div>
        <div class="error-info">
          <div class="error-title">Error en el Pago</div>
          <div class="error-message">${message}</div>
        </div>
        <div class="error-actions">
          <button type="button" class="retry-btn" onclick="paymentUX.retryPayment()">
            <span class="btn-icon">🔄</span>
            <span class="btn-text">Intentar Nuevamente</span>
          </button>
        </div>
      </div>
    `;
  }

  showSuccessMessage(title, subtitle) {
    this.showTemporaryMessage('success', title, subtitle, 3000);
  }

  showInfoMessage(title, subtitle) {
    this.showTemporaryMessage('info', title, subtitle, 5000);
  }

  showTemporaryMessage(type, title, subtitle, duration) {
    const messageId = 'temp-message-' + Date.now();
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
    
    // Animar entrada
    setTimeout(() => messageDiv.style.transform = 'translateX(0)', 10);
    
    // Auto-remover
    setTimeout(() => {
      messageDiv.style.transform = 'translateX(100%)';
      setTimeout(() => messageDiv.remove(), 300);
    }, duration);
  }

  retryPayment() {
    console.log('🔄 Reintentando pago...');
    this.resetPaymentState();
    this.initializeBoldPaymentEnhanced();
  }

  resetPaymentState() {
    this.paymentState.initialized = false;
    this.paymentState.inProgress = false;
    this.paymentState.completed = false;
    
    // Limpiar timeouts e intervals
    this.timeouts.forEach(timeout => clearTimeout(timeout));
    this.intervals.forEach(interval => clearInterval(interval));
    this.timeouts.clear();
    this.intervals.clear();
    
    // Resetear progreso
    this.updateProgress(1);
    
    // Resetear botón de envío
    this.updateSubmitButton('default');
  }

  onFormSubmit(event) {
    const metodoPago = document.getElementById('metodo_pago')?.value;
    const isBoldMethod = ['PSE Bold', 'Botón Bancolombia', 'Tarjeta de Crédito o Débito'].includes(metodoPago);
    
    if (isBoldMethod && !this.paymentState.completed) {
      event.preventDefault();
      
      if (this.paymentState.inProgress) {
        this.showInfoMessage('Pago en proceso', 'Complete el pago antes de enviar el formulario');
        if (window.currentPaymentWindow && !window.currentPaymentWindow.closed) {
          window.currentPaymentWindow.focus();
        }
      } else {
        this.showInfoMessage('Pago requerido', 'Debe completar el pago antes de enviar el formulario');
      }
      
      return false;
    }
    
    // Envío exitoso
    this.updateSubmitButton('loading', 'Enviando pedido...');
    return true;
  }
}

function setupPaymentData() {
  console.log('📋 Configurando datos del pago...');
  
  try {
    // Obtener datos del formulario
    const customerData = {
      email: document.querySelector('input[name="correo"]')?.value || '',
      fullName: document.querySelector('input[name="nombre"]')?.value || '',
      phone: document.querySelector('input[name="telefono"]')?.value || '',
      dialCode: '+57'
    };

    const billingAddress = {
      address: document.querySelector('input[name="direccion"]')?.value || '',
      city: 'Bogotá',
      state: 'Cundinamarca',
      country: 'CO'
    };

    // Obtener monto
    let monto = 0;
    const montoField = document.querySelector('input[name="monto"]');
    if (montoField && montoField.value) {
      const rawValue = montoField.value.replace(/[^\d]/g, '');
      monto = parseInt(rawValue) || 0;
    }

    // Obtener método de pago
    const metodoPago = document.getElementById('metodo_pago')?.value || 'PSE Bold';

    // Guardar datos globalmente para uso posterior
    window.currentOrderData = {
      orderId: this.paymentState.orderId,
      amount: monto,
      method: metodoPago,
      customer: customerData,
      billing: billingAddress
    };

    console.log('✅ Datos de pago configurados:', {
      orderId: this.paymentState.orderId,
      amount: monto,
      customer: customerData.fullName,
      email: customerData.email
    });

    return true;
  } catch (error) {
    console.error('❌ Error configurando datos del pago:', error);
    return false;
  }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
  // Crear instancia global
  window.paymentUX = new PaymentUXEnhancer();
  
  // Sobrescribir función original si existe
  if (typeof initializeBoldPayment === 'function') {
    window.originalInitializeBoldPayment = window.initializeBoldPayment;
    window.initializeBoldPayment = function() {
      console.log('🔄 Función sobrescrita llamada, ejecutando UX mejorada...');
      const result = window.paymentUX.initializeBoldPaymentEnhanced();
      console.log('✅ Resultado de UX mejorada:', result);
      // Si la función UX no retorna nada, intentar la función original
      if (result === undefined && window.originalInitializeBoldPayment) {
        console.log('🔄 UX retornó undefined, ejecutando función original...');
        return window.originalInitializeBoldPayment();
      }
      return result !== undefined ? result : true; // Retornar true por defecto si no hay error
    };
  }
});
