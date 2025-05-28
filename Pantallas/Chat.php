<?php
// Iniciar sesión (si no está iniciada)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar si el usuario está logueado
if (!isset($_SESSION['Id_usuario'])) {
    header("Location: login.php");
    exit();
}

require_once '../Config/database.php';
$db = Database::getInstance();
$conn = $db->getConnection();

// Obtener el ID del usuario actual
$userId = $_SESSION['Id_usuario'];

// Procesar envío de nuevo mensaje
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mensaje'])) {
    $conversacionId = $_POST['conversacion_id'];
    $mensaje = trim($_POST['mensaje']);
    
    if (!empty($mensaje) && !empty($conversacionId)) {
        try {
            $sql = "INSERT INTO mensajes (Mensaje, Fecha, Hora, Id_conversacion) 
                    VALUES (?, NOW(), CURRENT_TIME(), ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$mensaje, $conversacionId]);
        } catch (PDOException $e) {
            $error = "Error al enviar el mensaje: " . $e->getMessage();
        }
    }
}

// Obtener conversaciones del usuario
$conversaciones = [];
try {
    $sql = "SELECT c.Id_conversacion, 
                   CASE 
                       WHEN c.id_emisor = ? THEN u2.Nombre_del_usuario
                       ELSE u1.Nombre_del_usuario
                   END AS nombre_contacto,
                   CASE 
                       WHEN c.id_emisor = ? THEN c.id_receptor
                       ELSE c.id_emisor
                   END AS id_contacto
            FROM conversacion c
            JOIN usuarios u1 ON c.id_emisor = u1.Id_usuario
            JOIN usuarios u2 ON c.id_receptor = u2.Id_usuario
            WHERE c.id_emisor = ? OR c.id_receptor = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId, $userId, $userId, $userId]);
    $conversaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al cargar las conversaciones: " . $e->getMessage();
}

// Obtener mensajes de la conversación seleccionada
$mensajes = [];
$conversacionActual = null;
if (isset($_GET['conversacion_id']) || isset($_POST['conversacion_id'])) {
    $conversacionId = $_GET['conversacion_id'] ?? $_POST['conversacion_id'];
    $conversacionActual = $conversacionId;
    
    try {
        // Verificar que el usuario pertenece a esta conversación
        $sql = "SELECT Id_conversacion FROM conversacion 
                WHERE Id_conversacion = ? AND (id_emisor = ? OR id_receptor = ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$conversacionId, $userId, $userId]);
        
        if ($stmt->rowCount() > 0) {
            // Obtener mensajes
            $sql = "SELECT m.*, u.Nombre_del_usuario, u.Id_usuario 
                    FROM mensajes m
                    JOIN conversacion c ON m.Id_conversacion = c.Id_conversacion
                    JOIN usuarios u ON (m.Id_conversacion = c.Id_conversacion AND 
                                       (u.Id_usuario = c.id_emisor OR u.Id_usuario = c.id_receptor))
                    WHERE m.Id_conversacion = ? AND u.Id_usuario != ?
                    ORDER BY m.Fecha ASC, m.Hora ASC";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$conversacionId, $userId]);
            $mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $error = "No tienes acceso a esta conversación";
        }
    } catch (PDOException $e) {
        $error = "Error al cargar los mensajes: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat | TuTiendaOnline</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .chat-container {
            display: flex;
            height: calc(100vh - 120px);
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 20px;
        }
        
        .conversation-list {
            width: 300px;
            border-right: 1px solid #ddd;
            overflow-y: auto;
            background-color: #f8f9fa;
        }
        
        .conversation-item {
            padding: 15px;
            border-bottom: 1px solid #ddd;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .conversation-item:hover {
            background-color: #e9ecef;
        }
        
        .conversation-item.active {
            background-color: #d1e7ff;
        }
        
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .chat-header {
            padding: 15px;
            border-bottom: 1px solid #ddd;
            background-color: #f8f9fa;
            font-weight: bold;
        }
        
        .messages-container {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
            background-color: #fff;
        }
        
        .message {
            margin-bottom: 15px;
            max-width: 70%;
        }
        
        .message.received {
            align-self: flex-start;
            background-color: #f1f1f1;
            border-radius: 0 15px 15px 15px;
            padding: 10px 15px;
        }
        
        .message.sent {
            align-self: flex-end;
            background-color: #007bff;
            color: white;
            border-radius: 15px 0 15px 15px;
            padding: 10px 15px;
        }
        
        .message-info {
            font-size: 0.8rem;
            margin-bottom: 5px;
            color: #6c757d;
        }
        
        .message.sent .message-info {
            color: rgba(255, 255, 255, 0.7);
        }
        
        .message-form {
            padding: 15px;
            border-top: 1px solid #ddd;
            background-color: #f8f9fa;
        }
        
        .no-conversation {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <?php include 'Navbar.php'; ?>
    
    <div class="container">
        <h2 class="my-4"><i class="fas fa-comments me-2"></i> Chat</h2>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="chat-container">
            <div class="conversation-list">
                <?php if (empty($conversaciones)): ?>
                    <div class="p-3 text-center text-muted">No tienes conversaciones</div>
                <?php else: ?>
                    <?php foreach ($conversaciones as $conv): ?>
                        <div class="conversation-item <?php echo ($conv['Id_conversacion'] == $conversacionActual) ? 'active' : ''; ?>" 
                             onclick="window.location.href='Chat.php?conversacion_id=<?php echo $conv['Id_conversacion']; ?>'">
                            <div class="fw-bold"><?php echo htmlspecialchars($conv['nombre_contacto']); ?></div>
                            <div class="small text-muted">ID: <?php echo htmlspecialchars($conv['id_contacto']); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="chat-area">
                <?php if (empty($conversacionActual)): ?>
                    <div class="no-conversation">
                        <div class="text-center">
                            <i class="fas fa-comment-slash fa-3x mb-3"></i>
                            <h4>Selecciona una conversación</h4>
                            <p class="text-muted">O inicia una nueva conversación desde el perfil de un vendedor</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php 
                    $nombreContacto = '';
                    foreach ($conversaciones as $conv) {
                        if ($conv['Id_conversacion'] == $conversacionActual) {
                            $nombreContacto = $conv['nombre_contacto'];
                            break;
                        }
                    }
                    ?>
                    <div class="chat-header">
                        <i class="fas fa-user me-2"></i> <?php echo htmlspecialchars($nombreContacto); ?>
                    </div>
                    
                    <div class="messages-container" id="messages-container">
                        <?php if (empty($mensajes)): ?>
                            <div class="text-center text-muted mt-4">No hay mensajes en esta conversación</div>
                        <?php else: ?>
                            <?php foreach ($mensajes as $msg): ?>
                                <div class="message <?php echo ($msg['Id_usuario'] == $userId) ? 'sent' : 'received'; ?>">
                                    <div class="message-info">
                                        <?php echo htmlspecialchars($msg['Nombre_del_usuario']); ?> - 
                                        <?php echo date('d/m/Y H:i', strtotime($msg['Fecha'] . ' ' . $msg['Hora'])); ?>
                                    </div>
                                    <div><?php echo htmlspecialchars($msg['Mensaje']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST" class="message-form">
                        <input type="hidden" name="conversacion_id" value="<?php echo htmlspecialchars($conversacionActual); ?>">
                        <div class="input-group">
                            <input type="text" name="mensaje" class="form-control" placeholder="Escribe un mensaje..." required>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Enviar
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-scroll al final de los mensajes
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('messages-container');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        });
    </script>
</body>
</html>