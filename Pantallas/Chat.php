<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['Id_usuario'])) {
    header("Location: login.php");
    exit();
}

require_once '../Config/database.php';
$db = Database::getInstance();
$conn = $db->getConnection();

$userId = $_SESSION['Id_usuario'];
// Verificar si el usuario es vendedor (tiene productos con Cotizar = 1)
$esVendedor = false;
$productosCotizables = [];

try {
    $sql = "SELECT COUNT(*) FROM productos WHERE Id_usuario = ? AND Cotizar = 1 AND autorizado = 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId]);
    $esVendedor = ($stmt->fetchColumn() > 0);
    
    if ($esVendedor) {
    $sql = "SELECT Id_producto, Nombre, Precio, Cantidad as stock 
            FROM productos 
            WHERE Id_usuario = ? AND Cotizar = 1 AND autorizado = 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId]);
    $productosCotizables = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
} catch (PDOException $e) {
    $error = "Error al verificar productos: " . $e->getMessage();
}
// Enviar nuevo mensaje
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mensaje'])) {
    $conversacionId = $_POST['conversacion_id'];
    $mensaje = trim($_POST['mensaje']);

    if (!empty($mensaje) && !empty($conversacionId)) {
        try {
            $sql = "INSERT INTO mensajes (Mensaje, Fecha, Hora, Id_conversacion, Id_emisor) 
                    VALUES (?, NOW(), CURRENT_TIME(), ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$mensaje, $conversacionId, $userId]);
        } catch (PDOException $e) {
            $error = "Error al enviar el mensaje: " . $e->getMessage();
        }
    }
}
// Enviar nueva propuesta de cotización
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_propuesta'])) {
    $conversacionId = $_POST['conversacion_id'];
    $productoId = $_POST['producto_id'];
    $cantidad = $_POST['cantidad'];
    $precio = $_POST['precio'];
    
    try {
        // Obtener ID del comprador (el otro participante de la conversación)
        $sql = "SELECT IF(id_emisor = ?, id_receptor, id_emisor) as id_comprador 
                FROM conversacion WHERE Id_conversacion = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$userId, $conversacionId]);
        $comprador = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($comprador) {
            // Insertar propuesta
            $sql = "INSERT INTO propuestas_cotizacion 
                    (Id_conversacion, Id_producto, Id_vendedor, Id_comprador, Cantidad, Precio_propuesto) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$conversacionId, $productoId, $userId, $comprador['id_comprador'], $cantidad, $precio]);
            
            // Crear mensaje automático
            $sqlProducto = "SELECT Nombre FROM productos WHERE Id_producto = ?";
            $stmtProducto = $conn->prepare($sqlProducto);
            $stmtProducto->execute([$productoId]);
            $producto = $stmtProducto->fetch(PDO::FETCH_ASSOC);
            
            $mensaje = "He enviado una cotización para {$producto['Nombre']} - {$cantidad} unidades a \$" . number_format($precio, 2) . " cada una";
            
            $sqlMensaje = "INSERT INTO mensajes (Mensaje, Fecha, Hora, Id_conversacion, Id_emisor) 
                          VALUES (?, NOW(), CURRENT_TIME(), ?, ?)";
            $stmtMensaje = $conn->prepare($sqlMensaje);
            $stmtMensaje->execute([$mensaje, $conversacionId, $userId]);
            
            header("Location: Chat.php?conversacion_id=$conversacionId");
            exit();
        }
    } catch (PDOException $e) {
        $error = "Error al enviar la cotización: " . $e->getMessage();
    }
}

// Aceptar o rechazar propuesta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['aceptar_propuesta']) || isset($_POST['rechazar_propuesta']))) {
    $propuestaId = $_POST['propuesta_id'];
    $conversacionId = $_POST['conversacion_id'];
    $accion = isset($_POST['aceptar_propuesta']) ? 'aceptada' : 'rechazada';
    
    try {
        // Actualizar estado de la propuesta
        $sql = "UPDATE propuestas_cotizacion SET Estado = ? 
                WHERE Id_propuesta = ? AND Id_comprador = ? AND Estado = 'pendiente'";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$accion, $propuestaId, $userId]);
        
        if ($accion === 'aceptada' && $stmt->rowCount() > 0) {
            // Obtener datos de la propuesta
            $sql = "SELECT p.*, pr.Nombre as nombre_producto 
                    FROM propuestas_cotizacion p
                    JOIN productos pr ON p.Id_producto = pr.Id_producto
                    WHERE p.Id_propuesta = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$propuestaId]);
            $propuesta = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($propuesta) {
                // 1. Obtener o crear el carrito (lista)
                $sqlCarrito = "SELECT Id_lista FROM lista 
                              WHERE Id_usuario = ? AND Nombre_lista = 'Carrito' LIMIT 1";
                $stmtCarrito = $conn->prepare($sqlCarrito);
                $stmtCarrito->execute([$userId]);
                $carrito = $stmtCarrito->fetch(PDO::FETCH_ASSOC);
                
                if (!$carrito) {
                    $sqlCrear = "INSERT INTO lista (Id_usuario, Nombre_lista, Descripcion_lista) 
                                VALUES (?, 'Carrito', 'Productos en mi carrito de compras')";
                    $stmtCrear = $conn->prepare($sqlCrear);
                    $stmtCrear->execute([$userId]);
                    $idLista = $conn->lastInsertId();
                } else {
                    $idLista = $carrito['Id_lista'];
                }
                
                // 2. Agregar al carrito (productos_de_lista)
                $sqlInsert = "INSERT INTO productos_de_lista (
                                Id_lista, Id_producto, id_usuario, fecha_actualizacion, 
                                hora_actualizacion, cantidad, precio_unitario, es_cotizacion, id_propuesta
                             ) VALUES (?, ?, ?, CURDATE(), CURTIME(), ?, ?, 1, ?)";
                $stmtInsert = $conn->prepare($sqlInsert);
                $stmtInsert->execute([
                    $idLista,
                    $propuesta['Id_producto'],
                    $userId,
                    $propuesta['Cantidad'],
                    $propuesta['Precio_propuesto'],
                    $propuestaId
                ]);
            }
        }
        
        // Crear mensaje de respuesta
        $mensaje = $accion === 'aceptada' 
            ? "He aceptado tu cotización" 
            : "He rechazado tu cotización";
        
        $sqlMensaje = "INSERT INTO mensajes (Mensaje, Fecha, Hora, Id_conversacion, Id_emisor) 
                      VALUES (?, NOW(), CURRENT_TIME(), ?, ?)";
        $stmtMensaje = $conn->prepare($sqlMensaje);
        $stmtMensaje->execute([$mensaje, $conversacionId, $userId]);
        
        header("Location: Chat.php?conversacion_id=$conversacionId");
        exit();
    } catch (PDOException $e) {
        $error = "Error al procesar la cotización: " . $e->getMessage();
    }
}

// Obtener conversaciones
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

// Obtener mensajes y propuestas
$mensajes = [];
$propuestas = [];
$conversacionActual = null;

if (isset($_GET['conversacion_id']) || isset($_POST['conversacion_id'])) {
    $conversacionId = $_GET['conversacion_id'] ?? $_POST['conversacion_id'];
    $conversacionActual = $conversacionId;

    try {
        // Verificar acceso a la conversación
        $sql = "SELECT Id_conversacion FROM conversacion 
                WHERE Id_conversacion = ? AND (id_emisor = ? OR id_receptor = ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$conversacionId, $userId, $userId]);

        if ($stmt->rowCount() > 0) {
            // Obtener mensajes
            $sql = "SELECT m.*, 
                           CASE 
                               WHEN m.Id_emisor = ? THEN 'Tú'
                               ELSE u.Nombre_del_usuario 
                           END AS Nombre_del_usuario,
                           u.Id_usuario
                    FROM mensajes m
                    JOIN usuarios u ON m.Id_emisor = u.Id_usuario
                    WHERE m.Id_conversacion = ?
                    ORDER BY m.Fecha ASC, m.Hora ASC";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$userId, $conversacionId]);
            $mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Obtener propuestas de cotización
            $sql = "SELECT p.*, pr.Nombre as nombre_producto 
                    FROM propuestas_cotizacion p
                    JOIN productos pr ON p.Id_producto = pr.Id_producto
                    WHERE p.Id_conversacion = ? AND (p.Id_vendedor = ? OR p.Id_comprador = ?)
                    ORDER BY p.Fecha_propuesta DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$conversacionId, $userId, $userId]);
            $propuestas = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../CSS/Estilo_Chat.css">
    <style>
        .btn-toggle-form {
            margin: 5px;
        }
        .form-container {
            display: none;
            padding: 15px;
            border-top: 1px solid #ddd;
            background-color: #f8f9fa;
        }
        .form-container.active {
            display: block;
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
                        <div class="conversation-item p-3 <?php echo ($conv['Id_conversacion'] == $conversacionActual) ? 'active' : ''; ?>" 
                             onclick="window.location.href='Chat.php?conversacion_id=<?php echo $conv['Id_conversacion']; ?>'">
                            <div class="fw-bold"><?php echo htmlspecialchars($conv['nombre_contacto']); ?></div>
                         
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
                    <div class="chat-header p-3 bg-light border-bottom">
                        <i class="fas fa-user me-2"></i> <?php echo htmlspecialchars($nombreContacto); ?>
                    </div>

                    <div class="messages-container" id="messages-container">
                        <?php if (empty($mensajes) && empty($propuestas)): ?>
                            <div class="text-center text-muted mt-4">No hay mensajes en esta conversación</div>
                        <?php else: ?>
                            <?php 
                            // Combinar mensajes y eventos de propuestas ordenados por fecha
                            $eventos = [];
                            
                            foreach ($mensajes as $msg) {
                                $eventos[] = [
                                    'tipo' => 'mensaje',
                                    'fecha' => strtotime($msg['Fecha'] . ' ' . $msg['Hora']),
                                    'data' => $msg
                                ];
                            }
                            
                            foreach ($propuestas as $prop) {
                                $eventos[] = [
                                    'tipo' => 'propuesta',
                                    'fecha' => strtotime($prop['Fecha_propuesta']),
                                    'data' => $prop
                                ];
                            }
                            
                            // Ordenar eventos por fecha
                            usort($eventos, function($a, $b) {
                                return $a['fecha'] - $b['fecha'];
                            });
                            
                            foreach ($eventos as $evento): 
                                if ($evento['tipo'] === 'mensaje'):
                                    $msg = $evento['data']; ?>
                                   <div class="message <?php echo ($msg['Id_usuario'] == $userId) ? 'sent' : 'received'; ?>" data-id="<?php echo $msg['Id_mensaje']; ?>">
                                        <div class="message-info small text-muted">
                                            <?php echo htmlspecialchars($msg['Nombre_del_usuario']); ?> - 
                                            <?php echo date('d/m/Y H:i', strtotime($msg['Fecha'] . ' ' . $msg['Hora'])); ?>
                                        </div>
                                        <div><?php echo htmlspecialchars($msg['Mensaje']); ?></div>
                                    </div>
                                <?php else: 
                                    $prop = $evento['data']; ?>
                                    <div class="propuesta-card card mb-3 <?php echo $prop['Estado']; ?>" data-id="propuesta-<?php echo $prop['Id_propuesta']; ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h6 class="card-title mb-0">
                                                    <i class="fas fa-file-invoice-dollar me-2"></i>
                                                    Cotización: <?php echo htmlspecialchars($prop['nombre_producto']); ?>
                                                </h6>
                                                <span class="badge bg-<?php 
                                                    echo $prop['Estado'] === 'aceptada' ? 'success' : 
                                                         ($prop['Estado'] === 'rechazada' ? 'danger' : 'warning'); ?>">
                                                    <?php echo ucfirst($prop['Estado']); ?>
                                                </span>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <small class="text-muted">Cantidad:</small>
                                                    <div><?php echo $prop['Cantidad']; ?></div>
                                                </div>
                                                <div class="col-md-4">
                                                    <small class="text-muted">Precio unitario:</small>
                                                    <div>$<?php echo number_format($prop['Precio_propuesto'], 2); ?></div>
                                                </div>
                                                <div class="col-md-4">
                                                    <small class="text-muted">Total:</small>
                                                    <div>$<?php echo number_format($prop['Cantidad'] * $prop['Precio_propuesto'], 2); ?></div>
                                                </div>
                                            </div>
                                            
                                            <?php if ($prop['Estado'] === 'pendiente' && $prop['Id_comprador'] == $userId): ?>
                                                <form method="POST" class="mt-3">
                                                    <input type="hidden" name="propuesta_id" value="<?php echo $prop['Id_propuesta']; ?>">
                                                    <input type="hidden" name="conversacion_id" value="<?php echo $conversacionActual; ?>">
                                                    <button type="submit" name="aceptar_propuesta" class="btn btn-sm btn-success me-2">
                                                        <i class="fas fa-check"></i> Aceptar
                                                    </button>
                                                    <button type="submit" name="rechazar_propuesta" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-times"></i> Rechazar
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="button-container p-3 border-top bg-light d-flex justify-content-center">
                        <button id="btn-mensaje" class="btn btn-primary btn-toggle-form me-2">
                            <i class="fas fa-comment"></i> Enviar mensaje
                        </button>
                        
                        <?php if ($esVendedor && !empty($productosCotizables)): ?>
                            <button id="btn-cotizacion" class="btn btn-success btn-toggle-form">
                                <i class="fas fa-file-invoice-dollar"></i> Enviar cotización
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- Formulario de mensaje -->
                    <div id="form-mensaje" class="form-container">
                        <form method="POST">
                            <input type="hidden" name="conversacion_id" value="<?php echo htmlspecialchars($conversacionActual); ?>">
                            <div class="input-group">
                                <input type="text" name="mensaje" class="form-control" placeholder="Escribe un mensaje..." required>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Enviar
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Formulario de cotización (solo para vendedores) -->
                    <?php if ($esVendedor && !empty($productosCotizables)): ?>
                        <div id="form-cotizacion" class="form-container">
                            <form method="POST">
                                <input type="hidden" name="conversacion_id" value="<?php echo htmlspecialchars($conversacionActual); ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label">Producto cotizable</label>
                                    <select name="producto_id" class="form-select" required>
                                        <option value="">Seleccione un producto</option>
                                        <?php foreach ($productosCotizables as $producto): ?>
                                            <option value="<?php echo $producto['Id_producto']; ?>" 
                                                    data-precio="<?php echo $producto['Precio']; ?>"
                                                    data-stock="<?php echo $producto['stock']; ?>">
                                                <?php echo htmlspecialchars($producto['Nombre']); ?> 
                                                (Precio base: $<?php echo number_format($producto['Precio'], 2); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Cantidad</label>
                                    <input type="number" name="cantidad" min="1" class="form-control" 
                                        data-stock="<?php echo $producto['stock']; ?>" 
                                        required>
                                    <small class="text-muted">Disponible: <span class="stock-display">0</span></small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Precio unitario propuesto</label>
                                    <input type="number" name="precio" min="0.01" step="0.01" class="form-control" required>
                                    <small class="text-muted">Precio base: $<span id="precio-base">0.00</span></small>
                                </div>
                                
                                <button type="submit" name="enviar_propuesta" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Enviar cotización
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../JS/Chat.js"></script>
    <script>
        function cargarNuevosMensajes() {
            if (!<?php echo isset($conversacionActual) ? 'true' : 'false'; ?>) return;
            
            const ultimoMensaje = document.querySelector('.message:last-child');
            const ultimaPropuesta = document.querySelector('.propuesta-card:last-child');
            
            let ultimoIdMensaje = ultimoMensaje ? ultimoMensaje.getAttribute('data-id') : 0;
            let ultimoIdPropuesta = ultimaPropuesta ? ultimaPropuesta.getAttribute('data-id').replace('propuesta-', '') : 0;
            
            fetch(`../api/obtenerNuevosMensajes.php?conversacion_id=<?php echo $conversacionActual; ?>&ultimo_mensaje=${ultimoIdMensaje}&ultima_propuesta=${ultimoIdPropuesta}`)
                .then(response => response.json())
                .then(data => {
                    if (data.mensajes.length > 0 || data.propuestas.length > 0) {
                        // Agregar nuevos mensajes/propuestas al DOM
                        agregarNuevosMensajes(data.mensajes, data.propuestas);
                        
                        // Desplazarse al final
                        const container = document.getElementById('messages-container');
                        container.scrollTop = container.scrollHeight;
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // Función para agregar nuevos mensajes al DOM
        function agregarNuevosMensajes(mensajes, propuestas) {
    const container = document.getElementById('messages-container');
    
    mensajes.forEach(msg => {
        // Verificar si el mensaje ya existe para no duplicar
        if (!document.querySelector(`.message[data-id="${msg.Id_mensaje}"]`)) {
            const esMio = msg.Id_emisor == <?php echo $userId; ?>;
            const html = `
                <div class="message ${esMio ? 'sent' : 'received'}" data-id="${msg.Id_mensaje}">
                    <div class="message-info small text-muted">
                        ${msg.Nombre_del_usuario} - ${msg.Fecha}
                    </div>
                    <div>${msg.Mensaje}</div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
        }
    });
    
    propuestas.forEach(prop => {
        // Verificar si la propuesta ya existe para no duplicar
        if (!document.querySelector(`.propuesta-card[data-id="propuesta-${prop.Id_propuesta}"]`)) {
            const esComprador = <?php echo $userId; ?> == prop.Id_comprador;
            const botonesAccion = esComprador && prop.Estado === 'pendiente' ? `
                <form method="POST" class="mt-3">
                    <input type="hidden" name="propuesta_id" value="${prop.Id_propuesta}">
                    <input type="hidden" name="conversacion_id" value="<?php echo $conversacionActual; ?>">
                    <button type="submit" name="aceptar_propuesta" class="btn btn-sm btn-success me-2">
                        <i class="fas fa-check"></i> Aceptar
                    </button>
                    <button type="submit" name="rechazar_propuesta" class="btn btn-sm btn-danger">
                        <i class="fas fa-times"></i> Rechazar
                    </button>
                </form>
            ` : '';
            
            const badgeColor = prop.Estado === 'aceptada' ? 'success' : 
                             (prop.Estado === 'rechazada' ? 'danger' : 'warning');
            
            const html = `
                <div class="propuesta-card card mb-3 ${prop.Estado}" data-id="propuesta-${prop.Id_propuesta}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-file-invoice-dollar me-2"></i>
                                Cotización: ${prop.nombre_producto}
                            </h6>
                            <span class="badge bg-${badgeColor}">
                                ${prop.Estado.charAt(0).toUpperCase() + prop.Estado.slice(1)}
                            </span>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <small class="text-muted">Cantidad:</small>
                                <div>${prop.Cantidad}</div>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Precio unitario:</small>
                                <div>$${parseFloat(prop.Precio_propuesto).toFixed(2)}</div>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Total:</small>
                                <div>$${(prop.Cantidad * prop.Precio_propuesto).toFixed(2)}</div>
                            </div>
                        </div>
                        
                        ${botonesAccion}
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
        }
    });
}
        // Actualizar cada 5 segundos
        setInterval(cargarNuevosMensajes, 5000);
    </script>
</body>
</html>