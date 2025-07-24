<?php
/**
 * Gestión de Workflows QR
 * Sequoia Speed - Sistema QR
 */

defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once dirname(__DIR__) . '/config_secure.php';
require_once dirname(__DIR__) . '/accesos/middleware/AuthMiddleware.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/xss_helper.php';
require_once __DIR__ . '/security_headers.php';

// Establecer headers de seguridad
setSecurityHeaders(true);

$auth = new AuthMiddleware($conn);
$current_user = $auth->requirePermission('qr', 'actualizar');

// Obtener workflows existentes
$workflows_query = "SELECT id, workflow_name, workflow_type, config_data, validation_rules, 
                           ui_config, required_permissions, active, created_at, updated_at
                   FROM qr_workflow_config ORDER BY workflow_type, workflow_name";
$workflows_result = $conn->query($workflows_query);
$workflows = [];
while ($row = $workflows_result->fetch_assoc()) {
    $row['config_data'] = json_decode($row['config_data'], true);
    $row['validation_rules'] = json_decode($row['validation_rules'], true);
    $row['ui_config'] = json_decode($row['ui_config'], true);
    $row['required_permissions'] = json_decode($row['required_permissions'], true);
    $workflows[] = $row;
}

// Tipos de workflow disponibles
$workflow_types = [
    'entrada' => 'Recepción de Productos',
    'salida' => 'Despacho de Productos', 
    'conteo' => 'Conteo de Inventario',
    'movimiento' => 'Movimiento Interno',
    'auditoria' => 'Auditoría de Inventario',
    'transferencia' => 'Transferencia entre Almacenes',
    'ajuste' => 'Ajuste de Inventario'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workflows QR - Sequoia Speed</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #2d3748;
            --secondary-color: #4a5568;
            --success-color: #38a169;
            --warning-color: #d69e2e;
            --error-color: #e53e3e;
            --info-color: #3182ce;
        }
        
        body {
            background-color: #f7fafc;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
        }
        
        .workflow-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            overflow: hidden;
            transition: transform 0.2s ease;
        }
        
        .workflow-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
        }
        
        .workflow-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .workflow-type-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
        }
        
        .workflow-type-entrada { background-color: #c6f6d5; color: #2f855a; }
        .workflow-type-salida { background-color: #fed7d7; color: #c53030; }
        .workflow-type-conteo { background-color: #bee3f8; color: #2b6cb0; }
        .workflow-type-movimiento { background-color: #e9d8fd; color: #6b46c1; }
        .workflow-type-auditoria { background-color: #fbb6ce; color: #b83280; }
        .workflow-type-transferencia { background-color: #fef5e7; color: #d69e2e; }
        .workflow-type-ajuste { background-color: #e2e8f0; color: #4a5568; }
        
        .workflow-body {
            padding: 20px;
        }
        
        .step-list {
            list-style: none;
            padding: 0;
        }
        
        .step-item {
            display: flex;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .step-item:last-child {
            border-bottom: none;
        }
        
        .step-number {
            background: var(--info-color);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8em;
            margin-right: 12px;
        }
        
        .step-required {
            background: var(--error-color);
        }
        
        .config-json {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 12px;
            font-family: 'Courier New', monospace;
            font-size: 0.85em;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .workflow-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-workflow {
            border-radius: 20px;
            padding: 6px 16px;
            font-size: 0.85em;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col">
                    <h1><i class="bi bi-diagram-3"></i> Workflows QR</h1>
                    <p class="mb-0">Configuración de flujos de trabajo para el sistema QR</p>
                </div>
                <div class="col-auto">
                    <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#newWorkflowModal">
                        <i class="bi bi-plus-circle"></i> Nuevo Workflow
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <!-- Workflows List -->
        <div class="row">
            <?php foreach ($workflows as $workflow): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="workflow-card">
                        <div class="workflow-header">
                            <div>
                                <h5 class="mb-1"><?= htmlspecialchars($workflow['workflow_name']) ?></h5>
                                <span class="workflow-type-badge workflow-type-<?= $workflow['workflow_type'] ?>">
                                    <?= htmlspecialchars($workflow_types[$workflow['workflow_type']] ?? $workflow['workflow_type']) ?>
                                </span>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-link text-muted" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="editWorkflow(<?= $workflow['id'] ?>)">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a></li>
                                    <li><a class="dropdown-item" href="#" onclick="duplicateWorkflow(<?= $workflow['id'] ?>)">
                                        <i class="bi bi-files"></i> Duplicar
                                    </a></li>
                                    <?php if ($workflow['active']): ?>
                                        <li><a class="dropdown-item text-warning" href="#" onclick="toggleWorkflow(<?= $workflow['id'] ?>, false)">
                                            <i class="bi bi-pause-circle"></i> Desactivar
                                        </a></li>
                                    <?php else: ?>
                                        <li><a class="dropdown-item text-success" href="#" onclick="toggleWorkflow(<?= $workflow['id'] ?>, true)">
                                            <i class="bi bi-play-circle"></i> Activar
                                        </a></li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteWorkflow(<?= $workflow['id'] ?>)">
                                        <i class="bi bi-trash"></i> Eliminar
                                    </a></li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="workflow-body">
                            <!-- Steps -->
                            <?php if (!empty($workflow['config_data']['steps'])): ?>
                                <h6>Pasos del flujo:</h6>
                                <ul class="step-list">
                                    <?php foreach ($workflow['config_data']['steps'] as $index => $step): ?>
                                        <li class="step-item">
                                            <span class="step-number <?= $step['required'] ? 'step-required' : '' ?>">
                                                <?= $index + 1 ?>
                                            </span>
                                            <span><?= htmlspecialchars($step['name']) ?></span>
                                            <?php if ($step['required']): ?>
                                                <span class="badge bg-danger ms-auto">Requerido</span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            
                            <!-- Configuration Summary -->
                            <div class="mt-3">
                                <small class="text-muted">
                                    <?php if ($workflow['config_data']['auto_create_movement'] ?? false): ?>
                                        <i class="bi bi-check-circle text-success"></i> Auto-crear movimiento
                                    <?php endif; ?>
                                    <?php if ($workflow['config_data']['auto_create_adjustment'] ?? false): ?>
                                        <i class="bi bi-check-circle text-success"></i> Auto-crear ajuste
                                    <?php endif; ?>
                                </small>
                            </div>
                            
                            <!-- Actions -->
                            <div class="workflow-actions mt-3">
                                <button class="btn btn-outline-primary btn-workflow" onclick="testWorkflow(<?= $workflow['id'] ?>)">
                                    <i class="bi bi-play"></i> Probar
                                </button>
                                <button class="btn btn-outline-info btn-workflow" onclick="viewWorkflowStats(<?= $workflow['id'] ?>)">
                                    <i class="bi bi-graph-up"></i> Stats
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- New Workflow Modal -->
    <div class="modal fade" id="newWorkflowModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Workflow QR</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="workflowForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="workflow_name" class="form-label">Nombre del Workflow</label>
                                    <input type="text" class="form-control" id="workflow_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="workflow_type" class="form-label">Tipo</label>
                                    <select class="form-select" id="workflow_type" required>
                                        <?php foreach ($workflow_types as $value => $label): ?>
                                            <option value="<?= $value ?>"><?= htmlspecialchars($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Pasos del Workflow</label>
                            <div id="workflow-steps">
                                <div class="step-input mb-2">
                                    <div class="input-group">
                                        <input type="text" class="form-control" placeholder="Nombre del paso" name="step_name[]">
                                        <div class="input-group-text">
                                            <input type="checkbox" name="step_required[]" title="Requerido">
                                        </div>
                                        <button type="button" class="btn btn-outline-danger" onclick="removeStep(this)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addStep()">
                                <i class="bi bi-plus"></i> Agregar Paso
                            </button>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="auto_create_movement">
                                        <label class="form-check-label" for="auto_create_movement">
                                            Auto-crear movimiento de inventario
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="auto_create_adjustment">
                                        <label class="form-check-label" for="auto_create_adjustment">
                                            Auto-crear ajuste de inventario
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Reglas de Validación</label>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="require_product_validation">
                                        <label class="form-check-label" for="require_product_validation">
                                            Validar producto
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="require_stock_validation">
                                        <label class="form-check-label" for="require_stock_validation">
                                            Validar stock
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="require_quantity">
                                        <label class="form-check-label" for="require_quantity">
                                            Cantidad requerida
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="saveWorkflow()">Guardar Workflow</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function addStep() {
            const container = document.getElementById('workflow-steps');
            const stepHtml = `
                <div class="step-input mb-2">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Nombre del paso" name="step_name[]">
                        <div class="input-group-text">
                            <input type="checkbox" name="step_required[]" title="Requerido">
                        </div>
                        <button type="button" class="btn btn-outline-danger" onclick="removeStep(this)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', stepHtml);
        }
        
        function removeStep(button) {
            button.closest('.step-input').remove();
        }
        
        async function saveWorkflow() {
            const form = document.getElementById('workflowForm');
            const formData = new FormData(form);
            
            // Collect steps
            const stepNames = formData.getAll('step_name[]');
            const stepRequired = formData.getAll('step_required[]');
            
            const steps = stepNames.map((name, index) => ({
                name: name,
                required: stepRequired.includes(index.toString())
            })).filter(step => step.name.trim() !== '');
            
            // Build workflow data
            const workflowData = {
                workflow_name: formData.get('workflow_name'),
                workflow_type: formData.get('workflow_type'),
                config_data: {
                    steps: steps,
                    auto_create_movement: document.getElementById('auto_create_movement').checked,
                    auto_create_adjustment: document.getElementById('auto_create_adjustment').checked
                },
                validation_rules: {
                    require_product_validation: document.getElementById('require_product_validation').checked,
                    require_stock_validation: document.getElementById('require_stock_validation').checked,
                    require_quantity: document.getElementById('require_quantity').checked
                }
            };
            
            try {
                const response = await fetch('/qr/api/workflows.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(workflowData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Workflow guardado exitosamente');
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                alert('Error de conexión: ' + error.message);
            }
        }
        
        async function editWorkflow(id) {
            // TODO: Implement edit functionality
            alert('Función de edición en desarrollo');
        }
        
        async function duplicateWorkflow(id) {
            // TODO: Implement duplicate functionality
            alert('Función de duplicación en desarrollo');
        }
        
        async function toggleWorkflow(id, active) {
            try {
                const response = await fetch('/qr/api/workflows.php', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id: id,
                        active: active
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                alert('Error de conexión: ' + error.message);
            }
        }
        
        async function deleteWorkflow(id) {
            if (!confirm('¿Está seguro de que desea eliminar este workflow?')) {
                return;
            }
            
            try {
                const response = await fetch('/qr/api/workflows.php', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id: id })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                alert('Error de conexión: ' + error.message);
            }
        }
        
        function testWorkflow(id) {
            // TODO: Implement test functionality
            alert('Función de prueba en desarrollo');
        }
        
        function viewWorkflowStats(id) {
            // TODO: Implement stats view
            alert('Función de estadísticas en desarrollo');
        }
    </script>
</body>
</html>