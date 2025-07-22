/**
 * Bold Payment Enhanced Handler V3 - Sistema Avanzado de Pagos
 * Versión completamente corregida con todas las fixes aplicadas
 * Integración completa con UI en tiempo real y manejo robusto de errores
 * Autor: Sistema Bold Enhanced
 * Fecha: 2025-06-09
 */

class BoldPaymentEnhancedHandler {
    constructor() {
        this.isInitialized = false;
        this.originalOpen = null;
        this.paymentWindow = null;
        this.checkInterval = null;
        this.statusCheckInterval = null;
        this.timeoutId = null;
        this.isProcessing = false;
        this.maxRetries = 3;
        this.retryCount = 0;
        this.config = {
            checkInterval: 2000,
            timeout: 300000, // 5 minutos
            retryDelay: 3000,
            statusCheckDelay: 1000
        };

        // Bindings
        this.handleFormSubmit = this.handleFormSubmit.bind(this);
        this.checkPaymentStatus = this.checkPaymentStatus.bind(this);
        this.handleWindowClosed = this.handleWindowClosed.bind(this);

        console.log("[Bold Handler V3] Constructor inicializado");
    }

    /**
     * Inicialización del sistema
     */
    init() {
        if (this.isInitialized) {
            console.log("[Bold Handler V3] Ya está inicializado");
            return;
        }

        try {
            this.setupWindowInterception();
            this.attachToForm();
            this.isInitialized = true;
            console.log("[Bold Handler V3] Inicialización exitosa");
        } catch (error) {
            console.error("[Bold Handler V3] Error en inicialización:", error);
            this.showError("Error al inicializar el sistema de pagos", error);
        }
    }

    /**
     * Conectar al formulario de pago
     */
    attachToForm() {
        const form = document.querySelector('form[action*="procesar_orden"], form#payment-form, form.payment-form');
        if (form) {
            // Remover listeners previos
            form.removeEventListener('submit', this.handleFormSubmit);
            // Agregar nuevo listener
            form.addEventListener('submit', this.handleFormSubmit);
            console.log("[Bold Handler V3] Formulario conectado:", form);
        } else {
            console.warn("[Bold Handler V3] No se encontró formulario de pago");
        }
    }

    /**
     * Configurar interceptación de ventanas
     */
    setupWindowInterception() {
        if (!this.originalOpen) {
            this.originalOpen = window.open;

            window.open = (...args) => {
                const url = args[0];
                console.log("[Bold Handler V3] Interceptando window.open:", url);

                // Verificar si es una URL de Bold
                if (this.isBoldUrl(url)) {
                    console.log("[Bold Handler V3] URL de Bold detectada");
                    this.startPaymentProcess(url, args);
                    return null; // Prevenir la apertura de ventana
                }

                // Para URLs no-Bold, usar comportamiento original
                return this.originalOpen.call(window, ...args);
            };

            console.log("[Bold Handler V3] Interceptación de ventanas configurada");
        }
    }

    /**
     * Verificar si una URL es de Bold
     */
    isBoldUrl(url) {
        if (!url) return false;
        const boldDomains = [
            'checkout.bold.co',
            'api.bold.co',
            'sandbox-checkout.bold.co',
            'bold.com'
        ];

        try {
            const urlObj = new URL(url);
            return boldDomains.some(domain => urlObj.hostname.includes(domain));
        } catch (e) {
            return typeof url === 'string' && boldDomains.some(domain => url.includes(domain));
        }
    }

    /**
     * Manejar envío del formulario
     */
    handleFormSubmit(event) {
        console.log("[Bold Handler V3] Formulario enviado");

        if (this.isProcessing) {
            console.log("[Bold Handler V3] Ya hay un pago en proceso");
            event.preventDefault();
            return false;
        }

        // Validar campos requeridos
        const form = event.target;
        const requiredFields = form.querySelectorAll('[required]');
        let hasErrors = false;

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('error');
                hasErrors = true;
            } else {
                field.classList.remove('error');
            }
        });

        if (hasErrors) {
            event.preventDefault();
            this.showError("Por favor completa todos los campos requeridos");
            return false;
        }

        // Mostrar UI de proceso
        this.showInfo("Preparando pago...");
    }

    /**
     * Iniciar proceso de pago
     */
    async startPaymentProcess(url, windowArgs) {
        if (this.isProcessing) {
            console.log("[Bold Handler V3] Pago ya en proceso");
            return;
        }

        this.isProcessing = true;
        this.retryCount = 0;

        try {
            console.log("[Bold Handler V3] Iniciando proceso de pago");

            // Mostrar UI de progreso
            this.showProgress("Conectando con Bold...", 10);

            // Simular delay de conexión
            await this.delay(1000);

            // Abrir ventana de pago real
            this.openPaymentWindow(url, windowArgs);

        } catch (error) {
            console.error("[Bold Handler V3] Error iniciando pago:", error);
            this.handlePaymentError(error);
        }
    }

    /**
     * Abrir ventana de pago
     */
    openPaymentWindow(url, windowArgs) {
        try {
            console.log("[Bold Handler V3] Abriendo ventana de pago");

            // Configurar ventana
            const windowFeatures = windowArgs[2] || 'width=800,height=600,scrollbars=yes,resizable=yes';

            // Abrir ventana usando método original
            this.paymentWindow = this.originalOpen.call(window, url, '_blank', windowFeatures);

            if (!this.paymentWindow) {
                throw new Error("Ventana bloqueada por el navegador");
            }

            // Actualizar progreso
            this.showProgress("Ventana de pago abierta", 30);

            // Iniciar monitoreo
            this.startWindowMonitoring();
            this.startStatusChecking();
            this.startTimeout();

        } catch (error) {
            console.error("[Bold Handler V3] Error abriendo ventana:", error);
            this.handlePaymentError(error);
        }
    }

    /**
     * Monitorear ventana de pago
     */
    startWindowMonitoring() {
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
        }

        this.checkInterval = setInterval(() => {
            if (this.paymentWindow && this.paymentWindow.closed) {
                console.log("[Bold Handler V3] Ventana cerrada detectada");
                this.handleWindowClosed();
            }
        }, this.config.checkInterval);

        console.log("[Bold Handler V3] Monitoreo de ventana iniciado");
    }

    /**
     * Iniciar verificación de estado
     */
    startStatusChecking() {
        if (this.statusCheckInterval) {
            clearInterval(this.statusCheckInterval);
        }

        this.statusCheckInterval = setInterval(async () => {
            await this.checkPaymentStatus();
        }, this.config.statusCheckDelay);

        console.log("[Bold Handler V3] Verificación de estado iniciada");
    }

    /**
     * Configurar timeout
     */
    startTimeout() {
        if (this.timeoutId) {
            clearTimeout(this.timeoutId);
        }

        this.timeoutId = setTimeout(() => {
            console.log("[Bold Handler V3] Timeout alcanzado");
            this.handleTimeout();
        }, this.config.timeout);
    }

    /**
     * Verificar estado del pago
     */
    async checkPaymentStatus() {
        try {
            const orderNumber = this.getOrderNumber();
            if (!orderNumber) {
                console.log("[Bold Handler V3] No hay número de orden para verificar");
                return;
            }

            console.log("[Bold Handler V3] Verificando estado del pago:", orderNumber);

            const response = await fetch('bold_status_check.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ order_number: orderNumber })
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();
            console.log("[Bold Handler V3] Respuesta del estado:", result);

            if (result.success && result.payment_completed) {
                this.handlePaymentSuccess(result);
            } else if (result.payment_failed) {
                this.handlePaymentFailure(result);
            } else {
                // Actualizar progreso
                const progress = Math.min(90, 30 + (Date.now() - this.startTime) / 1000);
                this.showProgress("Verificando estado del pago...", progress);
            }

        } catch (error) {
            console.error("[Bold Handler V3] Error verificando estado:", error);
            // No mostrar error aquí, seguir intentando
        }
    }

    /**
     * Manejar ventana cerrada
     */
    handleWindowClosed() {
        console.log("[Bold Handler V3] Procesando cierre de ventana");

        // Dar tiempo para verificación final
        setTimeout(async () => {
            await this.checkPaymentStatus();

            // Si no se completó el pago, mostrar mensaje
            if (this.isProcessing) {
                this.showInfo("Verificando estado del pago...");

                // Intentar verificar por 10 segundos más
                let attempts = 0;
                const finalCheck = setInterval(async () => {
                    attempts++;
                    await this.checkPaymentStatus();

                    if (!this.isProcessing || attempts >= 5) {
                        clearInterval(finalCheck);
                        if (this.isProcessing) {
                            this.handlePaymentCancelled();
                        }
                    }
                }, 2000);
            }
        }, 3000);
    }

    /**
     * Manejar timeout
     */
    handleTimeout() {
        console.log("[Bold Handler V3] Manejando timeout");
        this.cleanup();
        this.showError("El tiempo de espera ha expirado. Por favor intenta nuevamente.");
    }

    /**
     * Manejar éxito del pago
     */
    async handlePaymentSuccess(result) {
        console.log("[Bold Handler V3] Pago exitoso:", result);

        try {
            // Log del éxito
            await this.logActivity(
                result.order_id || this.getOrderNumber(),
                'payment_success',
                `Pago completado exitosamente. Monto: ${result.amount}`,
                'success'
            );

            // Guardar datos en BD
            if (result.order_id) {
                await this.savePaymentData({
                    order_id: result.order_id,
                    transaction_id: result.transaction_id,
                    amount: result.amount,
                    status: 'completed',
                    payment_method: result.payment_method || 'Bold',
                    bold_response: result
                });
            }

            // Limpiar y mostrar éxito
            this.cleanup();
            this.showSuccess("¡Pago completado exitosamente!", result);

        } catch (error) {
            console.error("[Bold Handler V3] Error procesando éxito:", error);
            // Aún así mostrar éxito al usuario, pero log el error
            this.cleanup();
            this.showSuccess("¡Pago completado exitosamente!", result);
        }
    }

    /**
     * FIX 3: Método mejorado para manejar fallo con logging
     */
    async handlePaymentFailure(result) {
        console.log("[Bold Handler V3] Pago fallido:", result);

        try {
            // Log del fallo
            await this.logActivity(
                result.order_id || this.getOrderNumber(),
                'payment_failure',
                `Pago fallido. Error: ${result.error}`,
                'error'
            );

            // Limpiar y mostrar error
            this.cleanup();
            this.showError("El pago no pudo ser procesado", result.error);

        } catch (error) {
            console.error("[Bold Handler V3] Error procesando fallo:", error);
            this.cleanup();
            this.showError("El pago no pudo ser procesado", result.error);
        }
    }

    /**
     * Limpiar recursos
     */
    cleanup() {
        console.log("[Bold Handler V3] Limpiando recursos");

        this.isProcessing = false;

        if (this.checkInterval) {
            clearInterval(this.checkInterval);
            this.checkInterval = null;
        }

        if (this.statusCheckInterval) {
            clearInterval(this.statusCheckInterval);
            this.statusCheckInterval = null;
        }

        if (this.timeoutId) {
            clearTimeout(this.timeoutId);
            this.timeoutId = null;
        }

        if (this.paymentWindow && !this.paymentWindow.closed) {
            this.paymentWindow.close();
        }
        this.paymentWindow = null;
    }

    /**
     * Obtener número de orden
     */
    getOrderNumber() {
        // Intentar obtener de diferentes fuentes
        const orderInput = document.querySelector('input[name="order_number"], input[name="numero_orden"]');
        if (orderInput && orderInput.value) {
            return orderInput.value;
        }

        // Intentar obtener de datos del formulario
        const form = document.querySelector('form[action*="procesar_orden"]');
        if (form) {
            const formData = new FormData(form);
            return formData.get('order_number') || formData.get('numero_orden');
        }

        // Intentar obtener de localStorage
        return localStorage.getItem('bold_order_number') || null;
    }

    /**
     * Utilidades de UI
     */
    showProgress(message, percentage) {
        if (typeof window.boldUI !== 'undefined' && window.boldUI.showProgress) {
            window.boldUI.showProgress(message, percentage);
        } else {
            console.log(`[Bold Progress] ${message} (${percentage}%)`);
        }
    }

    showSuccess(message, data) {
        if (typeof window.boldUI !== 'undefined' && window.boldUI.showSuccess) {
            window.boldUI.showSuccess(message, data);
        } else if (typeof showBoldSuccess === 'function') {
            showBoldSuccess(message, data);
        } else {
            alert(`Éxito: ${message}`);
            console.log("[Bold Success]", data);
        }
    }

    showError(message, error) {
        if (typeof window.boldUI !== 'undefined' && window.boldUI.showError) {
            window.boldUI.showError(message, error);
        } else if (typeof showBoldError === 'function') {
            showBoldError(message, error);
        } else {
            alert(`Error: ${message}`);
            console.error("[Bold Error]", error);
        }
    }

    showInfo(message) {
        if (typeof window.boldUI !== 'undefined' && window.boldUI.showInfo) {
            window.boldUI.showInfo(message);
        } else if (typeof showBoldInfo === 'function') {
            showBoldInfo(message);
        } else {
            console.log(`[Bold Info] ${message}`);
        }
    }

    /**
     * Utilidad de delay
     */
    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Reiniciar el handler
     */
    reset() {
        console.log("[Bold Handler V3] Reiniciando handler");
        this.cleanup();
        this.retryCount = 0;
        this.isInitialized = false;
    }

    /**
     * Obtener estado actual
     */
    getStatus() {
        return {
            isInitialized: this.isInitialized,
            isProcessing: this.isProcessing,
            retryCount: this.retryCount,
            hasPaymentWindow: !!this.paymentWindow
        };
    }

    // 🎯 AGREGAR AQUÍ LOS MÉTODOS FALTANTES (ANTES DE ESTA LÍNEA):
    // } ← Cierre de la clase

    /**
     * FIX 1: Método de logging unificado
     */
    async logActivity(orderId, activityType, details, status = 'info') {
        try {
            const response = await fetch('bold_log_endpoint.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    order_id: orderId,
                    activity_type: activityType,
                    details: details,
                    status: status,
                    timestamp: new Date().toISOString()
                })
            });

            if (!response.ok) {
                console.warn("[Bold Handler V3] Error enviando log:", response.statusText);
            }
        } catch (error) {
            console.warn("[Bold Handler V3] Error en logging:", error);
        }
    }

    /**
     * FIX 2: Callback para guardar datos de pago
     */
    async savePaymentData(paymentData) {
        try {
            console.log("[Bold Handler V3] Guardando datos de pago:", paymentData);

            const response = await fetch('bold_payment_callback.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    order_id: paymentData.order_id,
                    transaction_id: paymentData.transaction_id,
                    amount: paymentData.amount,
                    status: paymentData.status,
                    payment_method: paymentData.payment_method,
                    bold_response: JSON.stringify(paymentData),
                    timestamp: new Date().toISOString()
                })
            });

            const result = await response.json();

            if (result.success) {
                console.log("[Bold Handler V3] Datos guardados exitosamente");
                await this.logActivity(
                    paymentData.order_id,
                    'payment_data_saved',
                    'Datos de pago guardados en base de datos',
                    'success'
                );
            } else {
                throw new Error(result.message || 'Error guardando datos');
            }

            return result;
        } catch (error) {
            console.error("[Bold Handler V3] Error guardando datos:", error);
            await this.logActivity(
                paymentData.order_id || 'unknown',
                'payment_save_error',
                `Error guardando datos: ${error.message}`,
                'error'
            );
            throw error;
        }
    }
}

// Inicialización automática cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function () {
    console.log("[Bold Handler V3] DOM listo, inicializando...");

    // Crear instancia global
    window.boldHandler = new BoldPaymentEnhancedHandler();

    // Inicializar después de un breve delay para asegurar que otros scripts estén cargados
    setTimeout(() => {
        window.boldHandler.init();
        console.log("[Bold Handler V3] Sistema inicializado completamente");
    }, 500);
});

// Exportar para uso en módulos
if (typeof module !== 'undefined' && module.exports) {
    module.exports = BoldPaymentEnhancedHandler;
}

console.log("[Bold Handler V3] Script cargado correctamente");
