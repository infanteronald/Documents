<?php
/**
 * Detalles de Actividad (AJAX)
 * Sequoia Speed - Sistema de Accesos
 */

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once dirname(__DIR__) . '/config_secure.php';
require_once dirname(__DIR__) . '/php82_helpers.php';
require_once 'middleware/AuthMiddleware.php';

// Inicializar middleware y requerir permisos
$auth = new AuthMiddleware($conn);
$current_user = $auth->requirePermission('acc_usuarios', 'leer');

// Obtener ID de la actividad
$activity_id = intval($_GET['id'] ?? 0);

if ($activity_id <= 0) {
    echo '<div class="alert alert-error">ID de actividad inválido</div>';
    exit;
}

try {
    // Obtener información de la actividad
    $query = "SELECT 
        aa.*,
        u.nombre as usuario_nombre,
        u.email as usuario_email,
        u.activo as usuario_activo
    FROM acc_auditoria_accesos aa
    INNER JOIN acc_usuarios u ON aa.usuario_id = u.id
    WHERE aa.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $activity_id);
    $stmt->execute();
    $actividad = $stmt->get_result()->fetch_assoc();
    
    if (!$actividad) {
        echo '<div class="alert alert-error">Actividad no encontrada</div>';
        exit;
    }
    
    // Obtener actividades relacionadas del mismo usuario en un rango de tiempo
    $query_relacionadas = "SELECT 
        aa.*,
        u.nombre as usuario_nombre
    FROM acc_auditoria_accesos aa
    INNER JOIN acc_usuarios u ON aa.usuario_id = u.id
    WHERE aa.usuario_id = ? 
    AND aa.id != ?
    AND aa.fecha_accion BETWEEN DATE_SUB(?, INTERVAL 1 HOUR) AND DATE_ADD(?, INTERVAL 1 HOUR)
    ORDER BY aa.fecha_accion DESC
    LIMIT 10";
    
    $stmt = $conn->prepare($query_relacionadas);
    $stmt->bind_param('iiss', $actividad['usuario_id'], $activity_id, $actividad['fecha_accion'], $actividad['fecha_accion']);
    $stmt->execute();
    $actividades_relacionadas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Función para formatear fecha
    function formatear_fecha($fecha) {
        return date('d/m/Y H:i:s', strtotime($fecha));
    }
    
    // Función para formatear fecha relativa
    function tiempo_relativo($fecha) {
        $ahora = time();
        $tiempo = strtotime($fecha);
        $diferencia = $ahora - $tiempo;
        
        if ($diferencia < 60) return 'Hace ' . $diferencia . ' segundos';
        if ($diferencia < 3600) return 'Hace ' . round($diferencia / 60) . ' minutos';
        if ($diferencia < 86400) return 'Hace ' . round($diferencia / 3600) . ' horas';
        if ($diferencia < 2592000) return 'Hace ' . round($diferencia / 86400) . ' días';
        
        return formatear_fecha($fecha);
    }
    
    // Función para formatear acción
    function formatear_accion($accion) {
        $acciones = [
            'login' => ['🔐', 'Inicio de sesión', 'success'],
            'logout' => ['🚪', 'Cierre de sesión', 'info'],
            'login_failed' => ['❌', 'Login fallido', 'danger'],
            'create' => ['➕', 'Crear', 'success'],
            'read' => ['👁️', 'Consultar', 'info'],
            'update' => ['✏️', 'Actualizar', 'warning'],
            'delete' => ['🗑️', 'Eliminar', 'danger'],
            'export' => ['📄', 'Exportar', 'info'],
            'import' => ['📥', 'Importar', 'warning']
        ];
        
        return $acciones[$accion] ?? ['📝', ucfirst($accion), 'secondary'];
    }
    
    // Función para formatear módulo
    function formatear_modulo($modulo) {
        $modulos = [
            'acc_usuarios' => ['👥', 'Usuarios', 'primary'],
            'ventas' => ['🛒', 'Ventas', 'success'],
            'inventario' => ['📦', 'Inventario', 'info'],
            'reportes' => ['📊', 'Reportes', 'warning'],
            'configuracion' => ['⚙️', 'Configuración', 'secondary']
        ];
        
        return $modulos[$modulo] ?? ['📁', ucfirst($modulo), 'secondary'];
    }
    
    // Función para analizar user agent
    function analizar_user_agent($user_agent) {
        $info = [
            'navegador' => 'Desconocido',
            'sistema' => 'Desconocido',
            'dispositivo' => 'Desconocido',
            'es_movil' => false
        ];
        
        // Detectar navegador
        if (strpos($user_agent, 'Chrome') !== false) {
            $info['navegador'] = 'Chrome';
        } elseif (strpos($user_agent, 'Firefox') !== false) {
            $info['navegador'] = 'Firefox';
        } elseif (strpos($user_agent, 'Safari') !== false) {
            $info['navegador'] = 'Safari';
        } elseif (strpos($user_agent, 'Edge') !== false) {
            $info['navegador'] = 'Edge';
        }
        
        // Detectar sistema operativo
        if (strpos($user_agent, 'Windows') !== false) {
            $info['sistema'] = 'Windows';
        } elseif (strpos($user_agent, 'Mac') !== false) {
            $info['sistema'] = 'macOS';
        } elseif (strpos($user_agent, 'Linux') !== false) {
            $info['sistema'] = 'Linux';
        } elseif (strpos($user_agent, 'Android') !== false) {
            $info['sistema'] = 'Android';
            $info['es_movil'] = true;
        } elseif (strpos($user_agent, 'iOS') !== false) {
            $info['sistema'] = 'iOS';
            $info['es_movil'] = true;
        }
        
        // Detectar dispositivo
        if (strpos($user_agent, 'Mobile') !== false || strpos($user_agent, 'Android') !== false) {
            $info['dispositivo'] = 'Móvil';
            $info['es_movil'] = true;
        } elseif (strpos($user_agent, 'Tablet') !== false || strpos($user_agent, 'iPad') !== false) {
            $info['dispositivo'] = 'Tablet';
        } else {
            $info['dispositivo'] = 'Escritorio';
        }
        
        return $info;
    }
    
    $accion_info = formatear_accion($actividad['accion']);
    $modulo_info = formatear_modulo($actividad['modulo']);
    $user_agent_info = analizar_user_agent($actividad['user_agent']);
    
} catch (Exception $e) {
    echo '<div class="alert alert-error">Error al cargar los datos: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>

<div class="activity-details">
    <!-- Información principal -->
    <div class="activity-header" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--space-lg); margin-bottom: var(--space-lg);">
        <div style="display: flex; align-items: center; gap: var(--space-lg);">
            <div style="font-size: 48px;">
                <?php echo $accion_info[0]; ?>
            </div>
            <div style="flex: 1;">
                <h4 style="color: var(--text-primary); margin-bottom: var(--space-sm);">
                    <?php echo $accion_info[1]; ?> en <?php echo $modulo_info[1]; ?>
                </h4>
                <div style="display: flex; align-items: center; gap: var(--space-md); margin-bottom: var(--space-sm);">
                    <span class="badge-tipo badge-<?php echo $accion_info[2]; ?>">
                        <?php echo $accion_info[0]; ?> <?php echo $accion_info[1]; ?>
                    </span>
                    <span class="badge-categoria">
                        <?php echo $modulo_info[0]; ?> <?php echo $modulo_info[1]; ?>
                    </span>
                </div>
                <div style="font-size: 12px; color: var(--text-secondary);">
                    ID: <?php echo $actividad['id']; ?> | <?php echo tiempo_relativo($actividad['fecha_accion']); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Detalles de la actividad -->
    <div class="detail-section">
        <h4 style="color: var(--text-primary); margin-bottom: var(--space-md); display: flex; align-items: center; gap: var(--space-sm);">
            📋 Detalles de la Actividad
        </h4>
        <div class="info-grid">
            <div class="info-item">
                <strong>Fecha y Hora:</strong>
                <span><?php echo formatear_fecha($actividad['fecha_accion']); ?></span>
            </div>
            <div class="info-item">
                <strong>Usuario:</strong>
                <span><?php echo htmlspecialchars($actividad['usuario_nombre']); ?></span>
            </div>
            <div class="info-item">
                <strong>Email:</strong>
                <span><?php echo htmlspecialchars($actividad['usuario_email']); ?></span>
            </div>
            <div class="info-item">
                <strong>Acción:</strong>
                <span><?php echo $accion_info[1]; ?></span>
            </div>
            <div class="info-item">
                <strong>Módulo:</strong>
                <span><?php echo $modulo_info[1]; ?></span>
            </div>
            <div class="info-item">
                <strong>Dirección IP:</strong>
                <span style="font-family: monospace;"><?php echo htmlspecialchars($actividad['ip_address']); ?></span>
            </div>
        </div>
        
        <?php if (!empty($actividad['descripcion'])): ?>
            <div style="margin-top: var(--space-md); padding: var(--space-md); background: var(--bg-tertiary); border-radius: var(--border-radius); border-left: 4px solid var(--color-primary);">
                <strong style="color: var(--text-primary); display: block; margin-bottom: var(--space-sm);">Descripción:</strong>
                <p style="color: var(--text-secondary); margin: 0;">
                    <?php echo htmlspecialchars($actividad['descripcion']); ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Información del navegador -->
    <?php if (!empty($actividad['user_agent'])): ?>
        <div class="detail-section" style="margin-top: var(--space-lg);">
            <h4 style="color: var(--text-primary); margin-bottom: var(--space-md); display: flex; align-items: center; gap: var(--space-sm);">
                🌐 Información del Navegador
            </h4>
            <div class="browser-info" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--space-md);">
                <div class="info-grid">
                    <div class="info-item">
                        <strong>Navegador:</strong>
                        <span><?php echo $user_agent_info['navegador']; ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Sistema:</strong>
                        <span><?php echo $user_agent_info['sistema']; ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Dispositivo:</strong>
                        <span>
                            <?php echo $user_agent_info['dispositivo']; ?>
                            <?php if ($user_agent_info['es_movil']): ?>
                                <span class="badge-info" style="font-size: 10px; margin-left: var(--space-xs);">📱 Móvil</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <div style="margin-top: var(--space-md); padding-top: var(--space-md); border-top: 1px solid var(--border-color);">
                    <strong style="color: var(--text-primary); display: block; margin-bottom: var(--space-sm);">User Agent completo:</strong>
                    <div style="font-family: monospace; font-size: 11px; color: var(--text-secondary); word-break: break-all; background: var(--bg-primary); padding: var(--space-sm); border-radius: var(--border-radius);">
                        <?php echo htmlspecialchars($actividad['user_agent']); ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Información del usuario -->
    <div class="detail-section" style="margin-top: var(--space-lg);">
        <h4 style="color: var(--text-primary); margin-bottom: var(--space-md); display: flex; align-items: center; gap: var(--space-sm);">
            👤 Información del Usuario
        </h4>
        <div class="user-info" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--space-md);">
            <div class="info-grid">
                <div class="info-item">
                    <strong>ID Usuario:</strong>
                    <span><?php echo $actividad['usuario_id']; ?></span>
                </div>
                <div class="info-item">
                    <strong>Nombre:</strong>
                    <span><?php echo htmlspecialchars($actividad['usuario_nombre']); ?></span>
                </div>
                <div class="info-item">
                    <strong>Email:</strong>
                    <span><?php echo htmlspecialchars($actividad['usuario_email']); ?></span>
                </div>
                <div class="info-item">
                    <strong>Estado:</strong>
                    <span>
                        <?php if ($actividad['usuario_activo']): ?>
                            <span class="badge-stock stock-alto">✅ Activo</span>
                        <?php else: ?>
                            <span class="badge-stock stock-bajo">❌ Inactivo</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Actividades relacionadas -->
    <?php if (!empty($actividades_relacionadas)): ?>
        <div class="detail-section" style="margin-top: var(--space-lg);">
            <h4 style="color: var(--text-primary); margin-bottom: var(--space-md); display: flex; align-items: center; gap: var(--space-sm);">
                🔗 Actividades Relacionadas (±1 hora)
            </h4>
            <div class="related-activities" style="max-height: 300px; overflow-y: auto;">
                <?php foreach ($actividades_relacionadas as $relacionada): ?>
                    <?php $rel_accion = formatear_accion($relacionada['accion']); ?>
                    <div class="related-item" style="display: flex; justify-content: space-between; align-items: center; padding: var(--space-md); border-bottom: 1px solid var(--border-color); background: var(--bg-tertiary); margin-bottom: var(--space-sm); border-radius: var(--border-radius);">
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: var(--space-sm); margin-bottom: var(--space-xs);">
                                <span style="font-size: 16px;"><?php echo $rel_accion[0]; ?></span>
                                <span style="font-weight: 500; color: var(--text-primary);">
                                    <?php echo $rel_accion[1]; ?>
                                </span>
                                <span class="badge-categoria" style="font-size: 10px; padding: 2px 6px;">
                                    <?php echo htmlspecialchars($relacionada['modulo']); ?>
                                </span>
                            </div>
                            <div style="font-size: 12px; color: var(--text-secondary);">
                                <?php echo htmlspecialchars($relacionada['descripcion']); ?>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 12px; color: var(--text-primary); margin-bottom: var(--space-xs);">
                                <?php echo tiempo_relativo($relacionada['fecha_accion']); ?>
                            </div>
                            <div style="font-size: 10px; color: var(--text-muted); font-family: monospace;">
                                <?php echo htmlspecialchars($relacionada['ip_address']); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Acciones -->
    <div class="detail-actions" style="margin-top: var(--space-lg); padding-top: var(--space-lg); border-top: 2px solid var(--border-color); display: flex; gap: var(--space-md); justify-content: center;">
        <?php if ($auth->hasPermission('acc_usuarios', 'leer')): ?>
            <button onclick="verDetalleUsuario(<?php echo $actividad['usuario_id']; ?>)" class="btn btn-primary">
                👤 Ver Usuario
            </button>
        <?php endif; ?>
        
        <button onclick="filtrarPorUsuario(<?php echo $actividad['usuario_id']; ?>)" class="btn btn-info">
            🔍 Filtrar por Usuario
        </button>
        
        <button onclick="filtrarPorAccion('<?php echo $actividad['accion']; ?>')" class="btn btn-secondary">
            📝 Filtrar por Acción
        </button>
    </div>
</div>

<script>
// Función para ver detalles del usuario
function verDetalleUsuario(userId) {
    cerrarModal();
    setTimeout(() => {
        window.open(`usuarios.php?search=${userId}`, '_blank');
    }, 100);
}

// Función para filtrar por usuario
function filtrarPorUsuario(userId) {
    cerrarModal();
    setTimeout(() => {
        window.location.href = `auditoria.php?usuario_filter=${userId}`;
    }, 100);
}

// Función para filtrar por acción
function filtrarPorAccion(accion) {
    cerrarModal();
    setTimeout(() => {
        window.location.href = `auditoria.php?accion_filter=${accion}`;
    }, 100);
}

// Efectos hover para los elementos relacionados
document.querySelectorAll('.related-item').forEach(item => {
    item.addEventListener('mouseenter', function() {
        this.style.backgroundColor = 'var(--bg-hover)';
        this.style.transform = 'translateX(4px)';
    });
    
    item.addEventListener('mouseleave', function() {
        this.style.backgroundColor = 'var(--bg-tertiary)';
        this.style.transform = 'translateX(0)';
    });
});
</script>

<style>
.activity-details {
    padding: var(--space-md);
}

.detail-section {
    margin-bottom: var(--space-lg);
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--space-md);
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: var(--space-xs);
}

.info-item strong {
    font-size: 12px;
    color: var(--text-secondary);
}

.info-item span {
    font-size: 14px;
    color: var(--text-primary);
    font-weight: 500;
}

.related-item {
    transition: all var(--transition-fast);
}

.browser-info, .user-info {
    transition: all var(--transition-fast);
}

.browser-info:hover, .user-info:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-sm);
}
</style>