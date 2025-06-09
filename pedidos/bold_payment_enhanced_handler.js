// Bold Payment Enhanced Handler - Captura mejorada de resultados
class BoldPaymentEnhancedHandler {
    constructor() {
        this.initialize();
    }

    initialize() {
        console.log("🚀 Inicializando Bold Payment Enhanced Handler");
        
        // Escuchar mensajes de la ventana de pago
        window.addEventListener("message", (event) => {
            this.handlePaymentMessage(event);
        });
        
        // Interceptar el cierre de ventanas de pago
        this.interceptPaymentWindowClose();
    }

    handlePaymentMessage(event) {
        if (event.data && event.data.type === "bold_payment_update") {
            console.log("📨 Mensaje de pago recibido:", event.data);
            
            const { status, orderId, data } = event.data;
            
            switch (status) {
                case "payment_success":
                    this.handlePaymentSuccess(orderId, data);
                    break;
                case "payment_error":
                    this.handlePaymentError(orderId, data);
                    break;
                case "payment_closed":
                    this.handlePaymentClosed(orderId, data);
                    break;
                default:
                    console.log("📋 Estado de pago:", status, orderId);
            }
        }
    }

    async handlePaymentSuccess(orderId, data) {
        console.log("✅ Pago exitoso:", orderId, data);
        
        try {
            // Procesar el resultado del pago
            const result = await this.processPaymentResult({
                order_id: orderId,
                status: "approved",
                transaction_id: data.transaction_id || data.id,
                amount: data.amount || 0,
                payment_method: "PSE Bold",
                ...data
            });
            
            if (result.success) {
                this.showPaymentSuccess(result);
                this.enableFormSubmission();
                window.paymentCompleted = true;
            } else {
                this.showPaymentError(result.error);
            }
            
        } catch (error) {
            console.error("❌ Error procesando resultado exitoso:", error);
            this.showPaymentError("Error al procesar el resultado del pago");
        }
    }

    async handlePaymentError(orderId, data) {
        console.log("❌ Error en pago:", orderId, data);
        
        try {
            // Procesar el resultado del error
            const result = await this.processPaymentResult({
                order_id: orderId,
                status: "failed",
                error: data.error,
                ...data
            });
            
            this.showPaymentError(data.error || "Error en el procesamiento del pago");
            
        } catch (error) {
            console.error("❌ Error procesando resultado de error:", error);
        }
    }

    async handlePaymentClosed(orderId, data) {
        console.log("🔒 Ventana de pago cerrada:", orderId);
        
        if (!window.paymentCompleted) {
            // Verificar el estado del pago en el servidor
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
        const response = await fetch("bold_payment_result_simple.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data)
        });
        
        return await response.json();
    }

    async checkPaymentStatus(orderId) {
        const response = await fetch(`bold_payment_result_simple.php?order_id=${orderId}`);
        return await response.json();
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
                    ${result.amount ? `<div><strong>💰 Monto:</strong> $${result.amount.toLocaleString()} COP</div>` : ""}
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
                    <div><strong>📦 Orden:</strong> ${orderId}</div>
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
                    <div><strong>�� Orden:</strong> ${orderId}</div>
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
        const originalOpen = window.open;
        
        window.open = function(...args) {
            const newWindow = originalOpen.apply(this, args);
            
            if (newWindow && args[1] === "boldPayment") {
                console.log("🔗 Interceptando ventana de pago Bold");
                
                const checkClosed = setInterval(() => {
                    if (newWindow.closed) {
                        console.log("🔒 Ventana de pago cerrada detectada");
                        clearInterval(checkClosed);
                        
                        // Simular evento de cierre
                        window.postMessage({
                            type: "bold_payment_update",
                            status: "payment_closed",
                            orderId: window.currentOrderData?.orderId || "unknown"
                        }, "*");
                    }
                }, 1000);
            }
            
            return newWindow;
        };
    }
}

// Inicializar el handler mejorado cuando se carga la página
document.addEventListener("DOMContentLoaded", function() {
    window.boldPaymentHandler = new BoldPaymentEnhancedHandler();
    console.log("✅ Bold Payment Enhanced Handler inicializado");
});
