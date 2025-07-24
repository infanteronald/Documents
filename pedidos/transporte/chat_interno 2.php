<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config_secure.php';
require_once '../ui-helpers.php';
require_once '../php82_helpers.php';

// Obtener mensajes de chat para un pedido específico
function obtenerMensajesChat($conn, $pedido_id) {
    $query = "SELECT 
        c.id,
        c.mensaje,
        c.fecha_mensaje,
        c.transportista_id,
        c.usuario_admin,
        c.tipo_mensaje,
        c.archivo_adjunto,
        c.leido,
        t.nombre as transportista_nombre
        FROM chat_interno c
        LEFT JOIN transportistas t ON c.transportista_id = t.id
        WHERE c.pedido_id = ?
        ORDER BY c.fecha_mensaje ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $pedido_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $mensajes = [];
    while ($row = $result->fetch_assoc()) {
        $mensajes[] = $row;
    }
    
    return $mensajes;
}

// Enviar mensaje de chat
function enviarMensajeChat($conn, $pedido_id, $mensaje, $transportista_id = null, $usuario_admin = null) {
    $query = "INSERT INTO chat_interno (pedido_id, mensaje, transportista_id, usuario_admin, fecha_mensaje) VALUES (?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('isis', $pedido_id, $mensaje, $transportista_id, $usuario_admin);
    
    return $stmt->execute();
}

// Marcar mensajes como leídos
function marcarMensajesLeidos($conn, $pedido_id) {
    $query = "UPDATE chat_interno SET leido = TRUE WHERE pedido_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $pedido_id);
    
    return $stmt->execute();
}

// Procesar peticiones AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $pedido_id = intval($_POST['pedido_id'] ?? 0);
    
    switch ($action) {
        case 'enviar_mensaje':
            $mensaje = trim($_POST['mensaje'] ?? '');
            $transportista_id = intval($_POST['transportista_id'] ?? 0);
            $usuario_admin = trim($_POST['usuario_admin'] ?? '');
            
            if ($pedido_id > 0 && !empty($mensaje)) {
                if (enviarMensajeChat($conn, $pedido_id, $mensaje, $transportista_id ?: null, $usuario_admin ?: null)) {
                    echo json_encode(['success' => true, 'message' => 'Mensaje enviado']);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Error al enviar mensaje']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
            }
            break;
            
        case 'obtener_mensajes':
            if ($pedido_id > 0) {
                $mensajes = obtenerMensajesChat($conn, $pedido_id);
                echo json_encode(['success' => true, 'mensajes' => $mensajes]);
            } else {
                echo json_encode(['success' => false, 'error' => 'ID de pedido inválido']);
            }
            break;
            
        case 'marcar_leidos':
            if ($pedido_id > 0) {
                marcarMensajesLeidos($conn, $pedido_id);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'ID de pedido inválido']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
    
    exit;
}

// Obtener pedido específico si se proporciona ID
$pedido_id = intval($_GET['pedido_id'] ?? 0);
$pedido = null;
$mensajes = [];

if ($pedido_id > 0) {
    $stmt = $conn->prepare("SELECT id, nombre, telefono, ciudad, direccion, barrio FROM pedidos_detal WHERE id = ?");
    $stmt->bind_param('i', $pedido_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $pedido = $result->fetch_assoc();
    
    if ($pedido) {
        $mensajes = obtenerMensajesChat($conn, $pedido_id);
    }
}

// Obtener pedidos recientes para el selector
$stmt = $conn->prepare("SELECT id, nombre, ciudad FROM pedidos_detal WHERE tiene_guia = '0' ORDER BY fecha DESC LIMIT 20");
$stmt->execute();
$result = $stmt->get_result();
$pedidos_recientes = [];
while ($row = $result->fetch_assoc()) {
    $pedidos_recientes[] = $row;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Chat Interno - VitalCarga</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>💬</text></svg>">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <link rel="stylesheet" href="../listar_pedidos.css">
    
    <style>
        .chat-container {
            display: flex;
            height: 100vh;
            background: #0d1117;
        }
        
        .chat-sidebar {
            width: 300px;
            background: #21262d;
            border-right: 1px solid #30363d;
            display: flex;
            flex-direction: column;
        }
        
        .chat-header {
            padding: 20px;
            border-bottom: 1px solid #30363d;
            background: #1e3a8a;
            color: white;
        }
        
        .chat-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .chat-subtitle {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .pedidos-lista {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }
        
        .pedido-item {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: background 0.2s;
            border: 1px solid #30363d;
        }
        
        .pedido-item:hover {
            background: #30363d;
        }
        
        .pedido-item.active {
            background: #1e3a8a;
            border-color: #3b82f6;
        }
        
        .pedido-numero {
            font-weight: 600;
            color: #58a6ff;
            margin-bottom: 4px;
        }
        
        .pedido-cliente {
            font-size: 0.9rem;
            color: #e6edf3;
            margin-bottom: 2px;
        }
        
        .pedido-ciudad {
            font-size: 0.8rem;
            color: #8b949e;
        }
        
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .chat-header-main {
            padding: 20px;
            background: #21262d;
            border-bottom: 1px solid #30363d;
        }
        
        .chat-pedido-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .chat-pedido-avatar {
            width: 40px;
            height: 40px;
            background: #58a6ff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
        }
        
        .chat-pedido-details h3 {
            margin: 0;
            color: #e6edf3;
            font-size: 1.1rem;
        }
        
        .chat-pedido-details p {
            margin: 0;
            color: #8b949e;
            font-size: 0.9rem;
        }
        
        .chat-mensajes {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #0d1117;
        }
        
        .mensaje {
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .mensaje.admin {
            flex-direction: row-reverse;
        }
        
        .mensaje-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            color: white;
            flex-shrink: 0;
        }
        
        .mensaje-avatar.transportista {
            background: #28a745;
        }
        
        .mensaje-avatar.admin {
            background: #dc3545;
        }
        
        .mensaje-content {
            max-width: 70%;
            background: #21262d;
            padding: 12px 16px;
            border-radius: 18px;
            border: 1px solid #30363d;
        }
        
        .mensaje.admin .mensaje-content {
            background: #1e3a8a;
            border-color: #3b82f6;
        }
        
        .mensaje-texto {
            color: #e6edf3;
            line-height: 1.4;
            margin-bottom: 4px;
        }
        
        .mensaje-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.7rem;
            color: #8b949e;
        }
        
        .mensaje-tiempo {
            opacity: 0.8;
        }
        
        .mensaje-autor {
            font-weight: 500;
        }
        
        .chat-input {
            padding: 20px;
            background: #21262d;
            border-top: 1px solid #30363d;
        }
        
        .chat-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .chat-textarea {
            flex: 1;
            min-height: 40px;
            max-height: 120px;
            padding: 10px 15px;
            border: 1px solid #30363d;
            border-radius: 20px;
            background: #0d1117;
            color: #e6edf3;
            resize: none;
            font-family: inherit;
            line-height: 1.4;
        }
        
        .chat-textarea:focus {
            outline: none;
            border-color: #58a6ff;
        }
        
        .chat-send-btn {
            background: #3b82f6;
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: background 0.2s;
        }
        
        .chat-send-btn:hover {
            background: #2563eb;
        }
        
        .chat-send-btn:disabled {
            background: #6b7280;
            cursor: not-allowed;
        }
        
        .chat-vacio {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #8b949e;
            text-align: center;
        }
        
        .chat-vacio-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        
        .chat-vacio h3 {
            margin: 0 0 10px 0;
            color: #e6edf3;
        }
        
        .chat-vacio p {
            margin: 0;
            max-width: 300px;
        }
        
        .chat-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-chat {
            background: #238636;
            color: white;
            border: 1px solid #238636;
            border-radius: 6px;
            padding: 8px 16px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .btn-chat:hover {
            background: #2ea043;
            transform: translateY(-1px);
        }
        
        @media (max-width: 768px) {
            .chat-container {
                flex-direction: column;
            }
            
            .chat-sidebar {
                width: 100%;
                height: 200px;
                border-right: none;
                border-bottom: 1px solid #30363d;
            }
            
            .pedidos-lista {
                display: flex;
                gap: 10px;
                overflow-x: auto;
                padding: 10px;
            }
            
            .pedido-item {
                min-width: 200px;
                margin-bottom: 0;
            }
            
            .chat-main {
                height: calc(100vh - 200px);
            }
        }
    </style>
</head>
<body>
<div class="chat-container">
    <!-- Sidebar con lista de pedidos -->
    <div class="chat-sidebar">
        <div class="chat-header">
            <div class="chat-title">💬 Chat Interno</div>
            <div class="chat-subtitle">Comunicación transportista-oficina</div>
        </div>
        
        <div class="pedidos-lista">
            <?php foreach ($pedidos_recientes as $p): ?>
                <div class="pedido-item <?php echo $p['id'] == $pedido_id ? 'active' : ''; ?>" 
                     onclick="seleccionarPedido(<?php echo $p['id']; ?>)">
                    <div class="pedido-numero">Pedido #<?php echo $p['id']; ?></div>
                    <div class="pedido-cliente"><?php echo htmlspecialchars($p['nombre']); ?></div>
                    <div class="pedido-ciudad"><?php echo htmlspecialchars($p['ciudad']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Área principal del chat -->
    <div class="chat-main">
        <?php if ($pedido): ?>
            <!-- Header del chat -->
            <div class="chat-header-main">
                <div class="chat-pedido-info">
                    <div class="chat-pedido-avatar">📦</div>
                    <div class="chat-pedido-details">
                        <h3>Pedido #<?php echo $pedido['id']; ?> - <?php echo htmlspecialchars($pedido['nombre']); ?></h3>
                        <p><?php echo htmlspecialchars($pedido['direccion'] . ', ' . $pedido['barrio'] . ', ' . $pedido['ciudad']); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Mensajes -->
            <div class="chat-mensajes" id="chat-mensajes">
                <?php foreach ($mensajes as $msg): ?>
                    <div class="mensaje <?php echo $msg['usuario_admin'] ? 'admin' : 'transportista'; ?>">
                        <div class="mensaje-avatar <?php echo $msg['usuario_admin'] ? 'admin' : 'transportista'; ?>">
                            <?php echo $msg['usuario_admin'] ? '🏢' : '🚚'; ?>
                        </div>
                        <div class="mensaje-content">
                            <div class="mensaje-texto">
                                <?php echo htmlspecialchars($msg['mensaje']); ?>
                            </div>
                            <div class="mensaje-info">
                                <span class="mensaje-autor">
                                    <?php echo $msg['usuario_admin'] ?: ($msg['transportista_nombre'] ?: 'Transportista'); ?>
                                </span>
                                <span class="mensaje-tiempo">
                                    <?php echo date('H:i', strtotime($msg['fecha_mensaje'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Input para enviar mensajes -->
            <div class="chat-input">
                <form class="chat-form" onsubmit="enviarMensaje(event)">
                    <textarea class="chat-textarea" 
                              id="mensaje-input"
                              placeholder="Escribe tu mensaje..."
                              rows="1"
                              onkeypress="handleKeyPress(event)"
                              oninput="autoResize(this)"></textarea>
                    <button type="submit" class="chat-send-btn" id="send-btn">
                        ➤
                    </button>
                </form>
            </div>
        <?php else: ?>
            <!-- Estado vacío -->
            <div class="chat-vacio">
                <div class="chat-vacio-icon">💬</div>
                <h3>Selecciona un pedido</h3>
                <p>Elige un pedido de la lista para iniciar o continuar la conversación</p>
                <div class="chat-actions">
                    <a href="vitalcarga.php" class="btn-chat">🚚 Ir a Gestión de Guías</a>
                    <a href="dashboard_transportista.php" class="btn-chat">📊 Dashboard</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
let currentPedidoId = <?php echo $pedido_id ?: 0; ?>;
let chatInterval;

// Función para seleccionar un pedido
function seleccionarPedido(pedidoId) {
    window.location.href = `chat_interno.php?pedido_id=${pedidoId}`;
}

// Función para enviar mensaje
function enviarMensaje(event) {
    event.preventDefault();
    
    const mensajeInput = document.getElementById('mensaje-input');
    const mensaje = mensajeInput.value.trim();
    
    if (!mensaje || !currentPedidoId) return;
    
    const sendBtn = document.getElementById('send-btn');
    sendBtn.disabled = true;
    
    fetch('chat_interno.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=enviar_mensaje&pedido_id=${currentPedidoId}&mensaje=${encodeURIComponent(mensaje)}&transportista_id=1`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mensajeInput.value = '';
            mensajeInput.style.height = 'auto';
            cargarMensajes();
        } else {
            alert('Error al enviar mensaje: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error de conexión');
    })
    .finally(() => {
        sendBtn.disabled = false;
        mensajeInput.focus();
    });
}

// Función para cargar mensajes
function cargarMensajes() {
    if (!currentPedidoId) return;
    
    fetch('chat_interno.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=obtener_mensajes&pedido_id=${currentPedidoId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarMensajes(data.mensajes);
        }
    })
    .catch(error => console.error('Error cargando mensajes:', error));
}

// Función para mostrar mensajes
function mostrarMensajes(mensajes) {
    const chatMensajes = document.getElementById('chat-mensajes');
    if (!chatMensajes) return;
    
    chatMensajes.innerHTML = '';
    
    mensajes.forEach(msg => {
        const mensajeDiv = document.createElement('div');
        mensajeDiv.className = `mensaje ${msg.usuario_admin ? 'admin' : 'transportista'}`;
        
        const fecha = new Date(msg.fecha_mensaje);
        const tiempo = fecha.toLocaleTimeString('es-ES', {hour: '2-digit', minute:'2-digit'});
        const autor = msg.usuario_admin || msg.transportista_nombre || 'Transportista';
        
        mensajeDiv.innerHTML = `
            <div class="mensaje-avatar ${msg.usuario_admin ? 'admin' : 'transportista'}">
                ${msg.usuario_admin ? '🏢' : '🚚'}
            </div>
            <div class="mensaje-content">
                <div class="mensaje-texto">
                    ${msg.mensaje}
                </div>
                <div class="mensaje-info">
                    <span class="mensaje-autor">${autor}</span>
                    <span class="mensaje-tiempo">${tiempo}</span>
                </div>
            </div>
        `;
        
        chatMensajes.appendChild(mensajeDiv);
    });
    
    // Scroll al final
    chatMensajes.scrollTop = chatMensajes.scrollHeight;
}

// Función para manejar Enter en el textarea
function handleKeyPress(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        enviarMensaje(event);
    }
}

// Función para auto-redimensionar textarea
function autoResize(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
}

// Inicializar cuando se carga la página
document.addEventListener('DOMContentLoaded', function() {
    if (currentPedidoId) {
        // Cargar mensajes cada 5 segundos
        chatInterval = setInterval(cargarMensajes, 5000);
        
        // Marcar mensajes como leídos
        fetch('chat_interno.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=marcar_leidos&pedido_id=${currentPedidoId}`
        });
    }
    
    // Focus en el input
    const mensajeInput = document.getElementById('mensaje-input');
    if (mensajeInput) {
        mensajeInput.focus();
    }
});

// Limpiar interval cuando se cierra la página
window.addEventListener('beforeunload', function() {
    if (chatInterval) {
        clearInterval(chatInterval);
    }
});
</script>
</body>
</html>