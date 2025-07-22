// filepath: /Users/ronaldinfante/Documents/pedidos/bold_realtime_payment_ui.js
// Sistema de visualización en tiempo real para pagos Bold
class BoldRealtimePaymentUI {
    constructor() {
        this.currentStep = 0;
        this.paymentData = {};
        this.isPaymentInProgress = false;
        this.initialize();
    }

    initialize() {
        console.log("🎨 Inicializando Bold Realtime Payment UI");
        this.createPaymentModal();
        this.setupEventListeners();
    }

    createPaymentModal() {
        // Crear el modal principal si no existe
        if (document.getElementById("bold-payment-modal")) return;

        const modalHTML = `
            <div id="bold-payment-modal" class="bold-modal" style="display: none;">
                <div class="bold-modal-overlay"></div>
                <div class="bold-modal-content">
                    <div class="bold-modal-header">
                        <h3>💳 Procesando Pago Bold</h3>
                        <button class="bold-close-btn" onclick="this.closest(\".bold-modal\").style.display=\"none\"">&times;</button>
                    </div>
                    <div class="bold-modal-body">
                        <!-- Barra de progreso -->
                        <div class="bold-progress-container">
                            <div class="bold-progress-bar">
                                <div class="bold-progress-fill" id="bold-progress-fill"></div>
                            </div>
                            <div class="bold-progress-text" id="bold-progress-text">Iniciando pago...</div>
                        </div>

                        <!-- Pasos del proceso -->
                        <div class="bold-steps-container" id="bold-steps-container">
                            <div class="bold-step" data-step="1">
                                <div class="bold-step-icon">🔄</div>
                                <div class="bold-step-content">
                                    <h4>Iniciando Pago</h4>
                                    <p>Conectando con Bold...</p>
                                </div>
                                <div class="bold-step-status">⏳</div>
                            </div>
                            <div class="bold-step" data-step="2">
                                <div class="bold-step-icon">🏦</div>
                                <div class="bold-step-content">
                                    <h4>Validando Datos</h4>
                                    <p>Verificando información bancaria...</p>
                                </div>
                                <div class="bold-step-status">⏳</div>
                            </div>
                            <div class="bold-step" data-step="3">
                                <div class="bold-step-icon">💰</div>
                                <div class="bold-step-content">
                                    <h4>Procesando Transacción</h4>
                                    <p>Realizando el pago...</p>
                                </div>
                                <div class="bold-step-status">⏳</div>
                            </div>
                            <div class="bold-step" data-step="4">
                                <div class="bold-step-icon">✅</div>
                                <div class="bold-step-content">
                                    <h4>Confirmando Pago</h4>
                                    <p>Validando resultado...</p>
                                </div>
                                <div class="bold-step-status">⏳</div>
                            </div>
                        </div>

                        <!-- Información en tiempo real -->
                        <div class="bold-realtime-info" id="bold-realtime-info">
                            <div class="bold-info-item">
                                <span class="bold-info-label">Estado:</span>
                                <span class="bold-info-value" id="bold-status">Iniciando...</span>
                            </div>
                            <div class="bold-info-item" id="bold-order-info" style="display: none;">
                                <span class="bold-info-label">Pedido:</span>
                                <span class="bold-info-value" id="bold-order-id">-</span>
                            </div>
                            <div class="bold-info-item" id="bold-amount-info" style="display: none;">
                                <span class="bold-info-label">Monto:</span>
                                <span class="bold-info-value" id="bold-amount">-</span>
                            </div>
                        </div>

                        <!-- Resultado final -->
                        <div class="bold-result-container" id="bold-result-container" style="display: none;">
                            <div class="bold-result-header">
                                <div class="bold-result-icon" id="bold-result-icon">✅</div>
                                <h3 id="bold-result-title">¡Pago Exitoso!</h3>
                            </div>
                            <div class="bold-result-details" id="bold-result-details">
                                <!-- Se llenará dinámicamente -->
                            </div>
                            <div class="bold-result-actions">
                                <button class="bold-btn bold-btn-primary" onclick="window.location.reload()">
                                    🔄 Nuevo Pedido
                                </button>
                                <button class="bold-btn bold-btn-secondary" onclick="this.closest(\".bold-modal\").style.display=\"none\"">
                                    ✅ Cerrar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML("beforeend", modalHTML);
        this.addStyles();
    }

    addStyles() {
        if (document.getElementById("bold-realtime-styles")) return;

        const styles = `
            <style id="bold-realtime-styles">
                .bold-modal {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    z-index: 10000;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
                }

                .bold-modal-overlay {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.7);
                    backdrop-filter: blur(4px);
                }

                .bold-modal-content {
                    position: relative;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    background: #ffffff;
                    border-radius: 12px;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                    max-width: 600px;
                    width: 90%;
                    max-height: 90vh;
                    overflow-y: auto;
                }

                .bold-modal-header {
                    padding: 20px 24px;
                    border-bottom: 1px solid #e5e7eb;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    border-radius: 12px 12px 0 0;
                }

                .bold-modal-header h3 {
                    margin: 0;
                    font-size: 1.25rem;
                    font-weight: 600;
                }

                .bold-close-btn {
                    background: none;
                    border: none;
                    color: white;
                    font-size: 24px;
                    cursor: pointer;
                    padding: 0;
                    width: 30px;
                    height: 30px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 50%;
                    transition: background-color 0.2s;
                }

                .bold-close-btn:hover {
                    background-color: rgba(255, 255, 255, 0.2);
                }

                .bold-modal-body {
                    padding: 24px;
                }

                .bold-progress-container {
                    margin-bottom: 32px;
                }

                .bold-progress-bar {
                    width: 100%;
                    height: 8px;
                    background: #e5e7eb;
                    border-radius: 4px;
                    overflow: hidden;
                    margin-bottom: 12px;
                }

                .bold-progress-fill {
                    height: 100%;
                    background: linear-gradient(90deg, #10b981 0%, #059669 100%);
                    border-radius: 4px;
                    transition: width 0.5s ease;
                    width: 0%;
                }

                .bold-progress-text {
                    text-align: center;
                    color: #6b7280;
                    font-size: 0.875rem;
                    font-weight: 500;
                }

                .bold-steps-container {
                    margin-bottom: 24px;
                }

                .bold-step {
                    display: flex;
                    align-items: center;
                    padding: 16px;
                    margin-bottom: 12px;
                    background: #f9fafb;
                    border-radius: 8px;
                    border-left: 4px solid #e5e7eb;
                    transition: all 0.3s ease;
                }

                .bold-step.active {
                    background: #ecfdf5;
                    border-left-color: #10b981;
                    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.1);
                }

                .bold-step.completed {
                    background: #f0f9ff;
                    border-left-color: #0ea5e9;
                }

                .bold-step-icon {
                    font-size: 1.5rem;
                    margin-right: 16px;
                    width: 40px;
                    text-align: center;
                }

                .bold-step-content {
                    flex: 1;
                }

                .bold-step-content h4 {
                    margin: 0 0 4px 0;
                    font-size: 0.875rem;
                    font-weight: 600;
                    color: #111827;
                }

                .bold-step-content p {
                    margin: 0;
                    font-size: 0.75rem;
                    color: #6b7280;
                }

                .bold-step-status {
                    font-size: 1.25rem;
                }

                .bold-realtime-info {
                    background: #f8fafc;
                    border-radius: 8px;
                    padding: 16px;
                    margin-bottom: 24px;
                }

                .bold-info-item {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 8px;
                }

                .bold-info-item:last-child {
                    margin-bottom: 0;
                }

                .bold-info-label {
                    font-weight: 500;
                    color: #374151;
                    font-size: 0.875rem;
                }

                .bold-info-value {
                    color: #111827;
                    font-weight: 600;
                    font-size: 0.875rem;
                }

                .bold-result-container {
                    text-align: center;
                    padding: 24px;
                    background: #f8fafc;
                    border-radius: 12px;
                    margin-top: 24px;
                }

                .bold-result-header {
                    margin-bottom: 24px;
                }

                .bold-result-icon {
                    font-size: 4rem;
                    margin-bottom: 16px;
                }

                .bold-result-title {
                    margin: 0 0 16px 0;
                    color: #111827;
                    font-size: 1.5rem;
                    font-weight: 600;
                }

                .bold-result-details {
                    background: white;
                    border-radius: 8px;
                    padding: 20px;
                    margin-bottom: 24px;
                    text-align: left;
                }

                .bold-result-row {
                    display: flex;
                    justify-content: space-between;
                    padding: 8px 0;
                    border-bottom: 1px solid #e5e7eb;
                }

                .bold-result-row:last-child {
                    border-bottom: none;
                }

                .bold-result-label {
                    font-weight: 500;
                    color: #6b7280;
                }

                .bold-result-value {
                    font-weight: 600;
                    color: #111827;
                }

                .bold-result-actions {
                    display: flex;
                    gap: 12px;
                    justify-content: center;
                }

                .bold-btn {
                    padding: 12px 24px;
                    border-radius: 8px;
                    font-weight: 600;
                    font-size: 0.875rem;
                    cursor: pointer;
                    border: none;
                    transition: all 0.2s;
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                }

                .bold-btn-primary {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                }

                .bold-btn-primary:hover {
                    transform: translateY(-1px);
                    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
                }

                .bold-btn-secondary {
                    background: #6b7280;
                    color: white;
                }

                .bold-btn-secondary:hover {
                    background: #4b5563;
                    transform: translateY(-1px);
                }

                @keyframes pulse {
                    0%, 100% { opacity: 1; }
                    50% { opacity: 0.5; }
                }

                .bold-step.active .bold-step-status {
                    animation: pulse 1.5s infinite;
                }
            </style>
        `;

        document.head.insertAdjacentHTML("beforeend", styles);
    }

    setupEventListeners() {
        // Escuchar mensajes de pago
        window.addEventListener("message", (event) => {
            this.handlePaymentMessage(event);
        });

        // Escuchar eventos personalizados
        document.addEventListener("boldPaymentStart", (e) => {
            this.startPaymentProcess(e.detail);
        });

        document.addEventListener("boldPaymentProgress", (e) => {
            this.updateProgress(e.detail);
        });

        document.addEventListener("boldPaymentComplete", (e) => {
            this.completePayment(e.detail);
        });
    }

    handlePaymentMessage(event) {
        if (!event.data || !event.data.type || !event.data.type.startsWith("bold_")) return;

        console.log("📨 Mensaje Bold recibido:", event.data);

        switch (event.data.type) {
            case "bold_payment_started":
                this.startPaymentProcess(event.data);
                break;
            case "bold_payment_progress":
                this.updateProgress(event.data);
                break;
            case "bold_payment_success":
                this.completePayment(event.data);
                break;
            case "bold_payment_error":
                this.showError(event.data);
                break;
            case "bold_payment_closed":
                this.handlePaymentClosed(event.data);
                break;
        }
    }

    startPaymentProcess(data) {
        console.log("🚀 Iniciando proceso de pago:", data);
        
        this.isPaymentInProgress = true;
        this.paymentData = data;
        this.currentStep = 1;

        // Mostrar modal
        document.getElementById("bold-payment-modal").style.display = "block";

        // Actualizar información inicial
        this.updateRealtimeInfo({
            status: "Iniciando pago...",
            orderId: data.orderId || data.order_id,
            amount: data.amount
        });

        // Activar primer paso
        this.activateStep(1);
        this.updateProgress({ step: 1, progress: 25 });

        // Simular progreso automático inicial
        setTimeout(() => this.progressToStep(2), 1000);
    }

    progressToStep(step) {
        if (!this.isPaymentInProgress) return;

        this.currentStep = step;
        this.activateStep(step);

        const progressPercent = (step / 4) * 100;
        this.updateProgressBar(progressPercent);

        const stepTexts = {
            1: "Iniciando pago...",
            2: "Validando datos bancarios...",
            3: "Procesando transacción...",
            4: "Confirmando pago..."
        };

        document.getElementById("bold-progress-text").textContent = stepTexts[step] || "Procesando...";
        
        // Actualizar estado en tiempo real
        this.updateRealtimeInfo({
            status: stepTexts[step] || "Procesando..."
        });
    }

    activateStep(stepNumber) {
        // Marcar pasos anteriores como completados
        for (let i = 1; i < stepNumber; i++) {
            const stepEl = document.querySelector(`[data-step="${i}"]`);
            if (stepEl) {
                stepEl.classList.remove("active");
                stepEl.classList.add("completed");
                stepEl.querySelector(".bold-step-status").textContent = "✅";
            }
        }

        // Activar paso actual
        const currentStepEl = document.querySelector(`[data-step="${stepNumber}"]`);
        if (currentStepEl) {
            currentStepEl.classList.add("active");
            currentStepEl.querySelector(".bold-step-status").textContent = "🔄";
        }
    }

    updateProgress(data) {
        console.log("📊 Actualizando progreso:", data);

        if (data.step) {
            this.progressToStep(data.step);
        }

        if (data.progress) {
            this.updateProgressBar(data.progress);
        }

        if (data.status) {
            this.updateRealtimeInfo({ status: data.status });
        }
    }

    updateProgressBar(percent) {
        const progressFill = document.getElementById("bold-progress-fill");
        if (progressFill) {
            progressFill.style.width = `${Math.min(100, Math.max(0, percent))}%`;
        }
    }

    updateRealtimeInfo(info) {
        if (info.status) {
            const statusEl = document.getElementById("bold-status");
            if (statusEl) statusEl.textContent = info.status;
        }

        if (info.orderId) {
            document.getElementById("bold-order-info").style.display = "flex";
            document.getElementById("bold-order-id").textContent = info.orderId;
        }

        if (info.amount) {
            document.getElementById("bold-amount-info").style.display = "flex";
            document.getElementById("bold-amount").textContent = `$${Number(info.amount).toLocaleString("es-CO")}`;
        }
    }

    completePayment(data) {
        console.log("✅ Completando pago:", data);

        this.isPaymentInProgress = false;
        this.progressToStep(4);
        this.updateProgressBar(100);

        // Marcar último paso como completado
        setTimeout(() => {
            const lastStep = document.querySelector(`[data-step="4"]`);
            if (lastStep) {
                lastStep.classList.remove("active");
                lastStep.classList.add("completed");
                lastStep.querySelector(".bold-step-status").textContent = "✅";
            }

            // Mostrar resultado final
            this.showFinalResult(data);
        }, 1000);
    }

    showFinalResult(data) {
        // Ocultar pasos y mostrar resultado
        document.getElementById("bold-steps-container").style.display = "none";
        document.getElementById("bold-progress-container").style.display = "none";
        document.getElementById("bold-realtime-info").style.display = "none";
        document.getElementById("bold-result-container").style.display = "block";

        // Configurar resultado
        const isSuccess = data.status === "approved" || data.status === "success";
        
        document.getElementById("bold-result-icon").textContent = isSuccess ? "✅" : "❌";
        document.getElementById("bold-result-title").textContent = isSuccess ? "¡Pago Exitoso!" : "Error en el Pago";

        // Generar detalles del resultado
        const detailsHTML = this.generateResultDetails(data);
        document.getElementById("bold-result-details").innerHTML = detailsHTML;
    }

    generateResultDetails(data) {
        const now = new Date();
        const dateStr = now.toLocaleDateString("es-CO");
        const timeStr = now.toLocaleTimeString("es-CO");

        return `
            <div class="bold-result-row">
                <span class="bold-result-label">📅 Fecha:</span>
                <span class="bold-result-value">${dateStr}</span>
            </div>
            <div class="bold-result-row">
                <span class="bold-result-label">🕐 Hora:</span>
                <span class="bold-result-value">${timeStr}</span>
            </div>
            <div class="bold-result-row">
                <span class="bold-result-label">🧾 Número de Pedido:</span>
                <span class="bold-result-value">${data.orderId || data.order_id || "N/A"}</span>
            </div>
            <div class="bold-result-row">
                <span class="bold-result-label">🔢 Código de Pago:</span>
                <span class="bold-result-value">${data.transactionId || data.transaction_id || "N/A"}</span>
            </div>
            <div class="bold-result-row">
                <span class="bold-result-label">💰 Monto:</span>
                <span class="bold-result-value">$${Number(data.amount || 0).toLocaleString("es-CO")}</span>
            </div>
            <div class="bold-result-row">
                <span class="bold-result-label">🏦 Método de Pago:</span>
                <span class="bold-result-value">${data.paymentMethod || "PSE Bold"}</span>
            </div>
            <div class="bold-result-row">
                <span class="bold-result-label">📋 Estado:</span>
                <span class="bold-result-value">${data.status === "approved" ? "Aprobado" : data.status || "Procesado"}</span>
            </div>
            ${data.reference ? `
            <div class="bold-result-row">
                <span class="bold-result-label">📄 Referencia:</span>
                <span class="bold-result-value">${data.reference}</span>
            </div>
            ` : ""}
        `;
    }

    showError(data) {
        console.log("❌ Error en pago:", data);
        
        this.isPaymentInProgress = false;
        this.updateRealtimeInfo({ status: "Error en el pago" });
        
        // Mostrar resultado de error
        setTimeout(() => {
            this.showFinalResult({
                ...data,
                status: "error"
            });
        }, 1000);
    }

    handlePaymentClosed(data) {
        console.log("🔒 Ventana de pago cerrada:", data);
        
        if (!this.isPaymentInProgress) return;

        // En lugar del mensaje genérico, consultar estado real
        this.updateRealtimeInfo({ status: "Verificando estado del pago..." });
        this.checkPaymentStatus(data.orderId || data.order_id);
    }

    async checkPaymentStatus(orderId) {
        try {
            const response = await fetch("bold_status_check.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ order_id: orderId })
            });

            const result = await response.json();
            
            if (result.status === "approved") {
                this.completePayment(result.data);
            } else if (result.status === "pending") {
                this.updateRealtimeInfo({ status: "Pago pendiente, verificando..." });
                // Reintentar en 3 segundos
                setTimeout(() => this.checkPaymentStatus(orderId), 3000);
            } else {
                this.showError({ status: "cancelled", orderId });
            }
        } catch (error) {
            console.error("Error verificando estado:", error);
            this.updateRealtimeInfo({ status: "Error verificando estado del pago" });
        }
    }

    // Método público para iniciar el pago desde el formulario
    static startPayment(orderId, amount) {
        const ui = new BoldRealtimePaymentUI();
        ui.startPaymentProcess({
            orderId: orderId,
            amount: amount,
            status: "started"
        });
        return ui;
    }
}

// Inicializar automáticamente
document.addEventListener("DOMContentLoaded", () => {
    window.boldRealtimeUI = new BoldRealtimePaymentUI();
});
