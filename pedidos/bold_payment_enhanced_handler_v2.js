// filepath: /Users/ronaldinfante/Documents/pedidos/bold_payment_enhanced_handler_v2.js
// Bold Payment Enhanced Handler V2 - Con UI en tiempo real integrada
class BoldPaymentEnhancedHandler {
    constructor() {
        this.paymentWindow = null;
        this.paymentStartTime = null;
        this.currentOrderId = null;
        this.initialize();
    }

    initialize() {
        console.log("🚀 Inicializando Bold Payment Enhanced Handler V2");
        
        // Escuchar mensajes de la ventana de pago
        window.addEventListener("message", (event) => {
            this.handlePaymentMessage(event);
        });
        
        // Interceptar el cierre de ventanas de pago
        this.interceptPaymentWindowClose();
        
        // Interceptar la apertura de ventanas de pago Bold
        this.interceptBoldPaymentStart();
    }

    interceptBoldPaymentStart() {
        // Interceptar cuando se abre la ventana de pago Bold
        const originalOpen = window.open;
        window.open = (...args) => {
            const url = args[0];
            
            if (url && (url.includes("bold") || url.includes("payment") || args[1] === "boldPayment")) {
                console.log("🎯 Interceptando apertura de pago Bold:", url);
                
                // Extraer order ID del formulario o URL
                const orderId = this.extractOrderId();
                const amount = this.extractAmount();
                
                this.currentOrderId = orderId;
                this.paymentStartTime = new Date();
                
                // Iniciar UI en tiempo real
                if (window.boldRealtimeUI) {
                    window.boldRealtimeUI.startPaymentProcess({
                        orderId: orderId,
                        amount: amount,
                        url: url
                    });
                    
                    // Simular progreso después de abrir ventana
                    setTimeout(() => {
                        document.dispatchEvent(new CustomEvent("boldPaymentProgress", {
                            detail: { step: 2, status: "Ventana de pago abierta" }
                        }));
                    }, 2000);
                } else {
                    // Fallback si no hay UI
                    console.warn("⚠️ boldRealtimeUI no disponible, usando fallback");
                    this.showLegacyPaymentInfo(orderId, amount);
                }
                
                const paymentWindow = originalOpen.apply(this, args);
                this.paymentWindow = paymentWindow;
                
                return paymentWindow;
            }
            
            return originalOpen.apply(this, args);
        };
    }

    extractOrderId() {
        // Intentar extraer order ID de varios lugares
        const orderInput = document.querySelector("input[name=\"order_id\"], input[name=\"orderId\"], input[name=\"pedido_id\"]");
        if (orderInput && orderInput.value) return orderInput.value;
        
        // Buscar en elementos con ID o clase que contengan "order" o "pedido"
        const orderElements = document.querySelectorAll("[id*=\"order\"], [class*=\"order\"], [id*=\"pedido\"], [class*=\"pedido\"]");
        for (let el of orderElements) {
            if (el.textContent && el.textContent.trim()) {
                const match = el.textContent.match(/\d+/);
                if (match) return "ORDER_" + match[0];
            }
        }
        
        // Generar un ID único si no se encuentra
        return "ORDER_" + Date.now();
    }

    extractAmount() {
        // Intentar extraer monto de varios lugares
        const amountInput = document.querySelector("input[name=\"amount\"], input[name=\"total\"], input[name=\"precio\"], input[name=\"monto\"]");
        if (amountInput && amountInput.value) return parseFloat(amountInput.value);
        
        // Buscar en spans o divs que puedan contener el total
        const totalElements = document.querySelectorAll("[class*=\"total\"], [id*=\"total\"], [class*=\"precio\"], [id*=\"precio\"], [class*=\"monto\"], [id*=\"monto\"]");
        for (let el of totalElements) {
            const text = el.textContent.replace(/[^\d]/g, "");
            if (text && parseInt(text) > 0) {
                return parseInt(text);
            }
        }
        
        return 0;
    }

    handlePaymentMessage(event) {
        if (!event.data || !event.data.type) return;
        
        // Manejar mensajes de Bold
        if (event.data.type === "bold_payment_update" || event.data.type.startsWith("bold_")) {
            console.log("📨 Mensaje de pago recibido:", event.data);
            
            const { status, orderId, data } = event.data;
            
            // Actualizar UI en tiempo real
            if (window.boldRealtimeUI) {
                let step = 2;
                if (status === "payment_success" || status === "approved") step = 4;
                else if (status === "processing") step = 3;
                
                document.dispatchEvent(new CustomEvent("boldPaymentProgress", {
                    detail: { 
                        step: step, 
                        status: this.getStatusText(status),
                        orderId: orderId || this.currentOrderId,
                        ...data
                    }
                }));
            }
            
            switch (status) {
                case "payment_success":
                case "approved":
                    this.handlePaymentSuccess(orderId, data);
                    break;
                case "payment_error":
                case "failed":
                case "error":
                    this.handlePaymentError(orderId, data);
                    break;
                case "payment_closed":
                    this.handlePaymentClosed(orderId, data);
                    break;
                case "processing":
                    this.handlePaymentProcessing(orderId, data);
                    break;
                default:
                    console.log("📋 Estado de pago:", status, orderId);
            }
        }
    }

    getStatusText(status) {
        const statusMap = {
            "payment_success": "Pago exitoso",
            "approved": "Pago aprobado",
            "payment_error": "Error en el pago",
            "failed": "Pago fallido",
            "error": "Error procesando",
            "processing": "Procesando pago...",
            "payment_closed": "Verificando estado..."
        };
        return statusMap[status] || `Estado: ${status}`;
    }

    async handlePaymentSuccess(orderId, data) {
        console.log("✅ Pago exitoso:", orderId, data);
        
        // Notificar a UI en tiempo real
        if (window.boldRealtimeUI) {
            document.dispatchEvent(new CustomEvent("boldPaymentComplete", {
                detail: {
                    status: "approved",
                    orderId: orderId || this.currentOrderId,
                    transactionId: data.transaction_id || data.id || data.reference,
                    amount: data.amount || this.extractAmount(),
                    paymentMethod: "PSE Bold",
                    reference: data.reference,
                    ...data
                }
            }));
        }
        
        try {
            // Procesar el resultado del pago
            const result = await this.processPaymentResult({
                order_id: orderId || this.currentOrderId,
                status: "approved",
                transaction_id: data.transaction_id || data.id || data.reference,
                amount: data.amount || this.extractAmount(),
                payment_method: "PSE Bold",
                ...data
            });
            
            if (result.success) {
                this.enableFormSubmission();
                window.paymentCompleted = true;
                
                // Mostrar en contenedor legacy si no hay UI moderna
                if (!window.boldRealtimeUI) {
                    this.showPaymentSuccess(result);
                }
            } else {
                console.error("Error en processPaymentResult:", result.error);
                if (!window.boldRealtimeUI) {
                    this.showPaymentError(result.error);
                }
            }
            
        } catch (error) {
            console.error("❌ Error procesando resultado exitoso:", error);
            if (!window.boldRealtimeUI) {
                this.showPaymentError("Error al procesar el resultado del pago");
            }
        }
    }

    async handlePaymentError(orderId, data) {
        console.log("❌ Error en pago:", orderId, data);
        
        // Notificar error a UI en tiempo real
        if (window.boldRealtimeUI) {
            document.dispatchEvent(new CustomEvent("boldPaymentComplete", {
                detail: {
                    status: "error",
                    orderId: orderId || this.currentOrderId,
                    error: data.error || data.message || "Error en el pago",
                    ...data
                }
            }));
        }
        
        try {
            // Procesar el resultado del error
            const result = await this.processPaymentResult({
                order_id: orderId || this.currentOrderId,
                status: "failed",
                error: data.error || data.message,
                ...data
            });
            
            if (!window.boldRealtimeUI) {
                this.showPaymentError(data.error || data.message || "Error en el procesamiento del pago");
            }
            
        } catch (error) {
            console.error("❌ Error procesando resultado de error:", error);
        }
    }

    handlePaymentProcessing(orderId, data) {
        console.log("🔄 Pago en proceso:", orderId, data);
        
        if (window.boldRealtimeUI) {
            document.dispatchEvent(new CustomEvent("boldPaymentProgress", {
                detail: {
                    step: 3,
                    status: "Procesando transacción...",
                    orderId: orderId || this.currentOrderId,
                    ...data
                }
            }));
        }
    }

    handlePaymentClosed(orderId, data) {
        console.log("🔒 Ventana de pago cerrada");
        
        // Si hay UI en tiempo real, ella maneja la verificación
        if (window.boldRealtimeUI) {
            // La UI maneja automáticamente la verificación del estado
            return;
        }
        
        // Fallback al comportamiento anterior
        this.checkPaymentStatusLegacy(orderId || this.currentOrderId);
    }

    async checkPaymentStatusLegacy(orderId) {
        console.log("🔍 Verificación legacy del estado del pago:", orderId);
        
        if (!window.paymentCompleted) {
            try {
                const result = await this.checkPaymentStatus(orderId);
                
                if (result.success && result.payment_info) {
                    const paymentInfo = result.payment_info;
                    
                    if (paymentInfo.payment_status === "pagado") {
                        this.showPaymentSuccess({
                            order_id: orderId,
                            status: "approved",
                            payment_status: "pagado",
                            transaction_id: paymentInfo.transaction_id,
                            amount: paymentInfo.amount,
                            updated_at: paymentInfo.updated_at
                        });
                        this.enableFormSubmission();
                        window.paymentCompleted = true;
                    } else if (paymentInfo.payment_status === "fallido") {
                        this.showPaymentError("El pago fue rechazado o falló");
                    } else {
                        this.showPaymentPending(orderId, paymentInfo);
                    }
                } else {
                    this.showPaymentUnknown(orderId);
                }
                
            } catch (error) {
                console.error("❌ Error verificando estado del pago:", error);
                this.showPaymentUnknown(orderId);
            }
        }
    }

    async processPaymentResult(data) {
        try {
            const response = await fetch("bold_payment_result_simple.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify(data)
            });
            
            return await response.json();
        } catch (error) {
            console.error("Error en processPaymentResult:", error);
            return { success: false, error: error.message };
        }
    }

    async checkPaymentStatus(orderId) {
        console.log("🔍 Verificando estado del pago:", orderId);
        
        try {
            const response = await fetch("bold_status_check.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({
                    order_id: orderId,
                    action: "check_payment_status"
                })
            });

            const result = await response.json();
            console.log("📊 Resultado verificación:", result);
            
            return result;
            
        } catch (error) {
            console.error("❌ Error verificando estado:", error);
            return { success: false, error: error.message };
        }
    }

    showLegacyPaymentInfo(orderId, amount) {
        const container = document.getElementById("bold-payment-container");
        if (!container) return;
        
        container.innerHTML = `
            <div style="
                background: linear-gradient(135deg, #cce5ff 0%, #b3d9ff 100%);
                border: 2px solid #007bff;
                border-radius: 12px;
                padding: 20px;
                text-align: center;
                color: #004085;
                margin: 16px 0;
                box-shadow: 0 4px 8px rgba(0, 123, 255, 0.2);
            ">
                <div style="font-size: 48px; margin-bottom: 12px;">💳</div>
                <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">
                    Procesando Pago Bold
                </div>
                <div style="font-size: 14px; margin-bottom: 16px;">
                    Ventana de pago abierta. Complete el proceso en la ventana emergente.
                </div>
                
                <div style="
                    background: rgba(255, 255, 255, 0.6);
                    border-radius: 8px;
                    padding: 12px;
                    margin: 12px 0;
                    text-align: left;
                    font-size: 13px;
                ">
                    <div><strong>📦 Orden:</strong> ${orderId}</div>
                    ${amount > 0 ? `<div><strong>💰 Monto:</strong> $${amount.toLocaleString("es-CO")}</div>` : ""}
                    <div><strong>⏳ Estado:</strong> Esperando confirmación</div>
                </div>
            </div>
        `;
    }

    showPaymentSuccess(result) {
        const container = document.getElementById("bold-payment-container");
        if (!container) return;
        
        const timestamp = result.updated_at || result.timestamp || new Date().toLocaleString();
        
        container.innerHTML = `
            <div style="
                background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
                border: 2px solid #28a745;
                border-radius: 12px;
                padding: 20px;
                text-align: center;
                color: #155724;
                margin: 16px 0;
                box-shadow: 0 4px 8px rgba(40, 167, 69, 0.2);
            ">
                <div style="font-size: 48px; margin-bottom: 12px;">✅</div>
                <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">
                    ¡Pago Completado Exitosamente!
                </div>
                <div style="font-size: 14px; opacity: 0.8; margin-bottom: 16px;">
                    Su pago ha sido procesado y confirmado
                </div>
                
                <div style="
                    background: rgba(255, 255, 255, 0.6);
                    border-radius: 8px;
                    padding: 12px;
                    margin: 12px 0;
                    text-align: left;
                    font-size: 13px;
                ">
                    <div><strong>📦 Orden:</strong> ${result.order_id}</div>
                    ${result.transaction_id ? `<div><strong>🔖 Transacción:</strong> ${result.transaction_id}</div>` : ""}
                    ${result.amount ? `<div><strong>💰 Monto:</strong> $${Number(result.amount).toLocaleString("es-CO")} COP</div>` : ""}
                    <div><strong>📅 Fecha:</strong> ${timestamp}</div>
                    <div><strong>✅ Estado:</strong> ${result.payment_status || "Pagado"}</div>
                </div>
                
                <div style="
                    background: rgba(0, 123, 255, 0.1);
                    border-radius: 6px;
                    padding: 8px;
                    font-size: 12px;
                    margin-top: 12px;
                ">
                    🎉 Ahora puede enviar su pedido usando el botón de abajo
                </div>
            </div>
        `;
    }

    showPaymentError(error) {
        const container = document.getElementById("bold-payment-container");
        if (!container) return;
        
        container.innerHTML = `
            <div style="
                background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
                border: 2px solid #dc3545;
                border-radius: 12px;
                padding: 20px;
                text-align: center;
                color: #721c24;
                margin: 16px 0;
                box-shadow: 0 4px 8px rgba(220, 53, 69, 0.2);
            ">
                <div style="font-size: 48px; margin-bottom: 12px;">❌</div>
                <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">
                    Error en el Pago
                </div>
                <div style="font-size: 14px; margin-bottom: 16px;">
                    ${error}
                </div>
                <button type="button" onclick="location.reload()" style="
                    background: #dc3545;
                    color: white;
                    border: none;
                    padding: 8px 16px;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 14px;
                ">
                    🔄 Intentar Nuevamente
                </button>
            </div>
        `;
    }

    showPaymentPending(orderId, paymentInfo) {
        const container = document.getElementById("bold-payment-container");
        if (!container) return;
        
        container.innerHTML = `
            <div style="
                background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
                border: 2px solid #ffc107;
                border-radius: 12px;
                padding: 20px;
                text-align: center;
                color: #856404;
                margin: 16px 0;
                box-shadow: 0 4px 8px rgba(255, 193, 7, 0.2);
            ">
                <div style="font-size: 48px; margin-bottom: 12px;">⏳</div>
                <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">
                    Pago en Proceso
                </div>
                <div style="font-size: 14px; margin-bottom: 16px;">
                    Su pago está siendo verificado. Los pagos PSE pueden tomar algunos minutos.
                </div>
                
                <div style="
                    background: rgba(255, 255, 255, 0.6);
                    border-radius: 8px;
                    padding: 12px;
                    margin: 12px 0;
                    text-align: left;
                    font-size: 13px;
                ">
                    <div><strong>�� Orden:</strong> ${orderId}</div>
                    ${paymentInfo.transaction_id ? `<div><strong>🔖 Transacción:</strong> ${paymentInfo.transaction_id}</div>` : ""}
                    <div><strong>⏱️ Estado:</strong> Procesando</div>
                </div>
            </div>
        `;
    }

    showPaymentUnknown(orderId) {
        const container = document.getElementById("bold-payment-container");
        if (!container) return;
        
        container.innerHTML = `
            <div style="
                background: linear-gradient(135deg, #e2e3e5 0%, #d6d8db 100%);
                border: 2px solid #6c757d;
                border-radius: 12px;
                padding: 20px;
                text-align: center;
                color: #495057;
                margin: 16px 0;
                box-shadow: 0 4px 8px rgba(108, 117, 125, 0.2);
            ">
                <div style="font-size: 48px; margin-bottom: 12px;">❓</div>
                <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">
                    Estado del Pago No Confirmado
                </div>
                <div style="font-size: 14px; margin-bottom: 16px;">
                    No pudimos verificar el estado de su pago. Si completó el proceso, recibirá confirmación por email.
                </div>
                
                <div style="
                    background: rgba(255, 255, 255, 0.6);
                    border-radius: 8px;
                    padding: 12px;
                    margin: 12px 0;
                    text-align: left;
                    font-size: 13px;
                ">
                    <div><strong>📦 Orden:</strong> ${orderId}</div>
                    <div><strong>⚠️ Recomendación:</strong> Contacte a soporte si no recibe confirmación</div>
                </div>
            </div>
        `;
    }

    enableFormSubmission() {
        const submitButton = document.querySelector("button[type=\"submit\"]");
        if (submitButton) {
            submitButton.style.background = "#28a745";
            submitButton.style.opacity = "1";
            submitButton.disabled = false;
            submitButton.textContent = "Enviar pedido ✓";
        }
    }

    interceptPaymentWindowClose() {
        // Este método ya no es necesario con la nueva implementación
        // Se mantiene para compatibilidad
    }

    // Método público para iniciar el pago manualmente
    static startPayment(orderId, amount) {
        if (window.boldRealtimeUI) {
            return window.boldRealtimeUI.startPaymentProcess({
                orderId: orderId,
                amount: amount,
                status: "started"
            });
        } else {
            console.warn("⚠️ boldRealtimeUI no disponible");
            return null;
        }
    }
}

// Inicializar el handler mejorado cuando se carga la página
document.addEventListener("DOMContentLoaded", function() {
    window.boldPaymentHandler = new BoldPaymentEnhancedHandler();
    console.log("✅ Bold Payment Enhanced Handler V2 inicializado");
});
