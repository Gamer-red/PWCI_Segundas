<?php
session_start();
require_once '../Config/database.php';

if (!isset($_SESSION['Id_usuario'])) {
    header('Location: Login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: Pagina_principal.php');
    exit();
}
$productoId = $_GET['id'];
$db = Database::getInstance();
$conn = $db->getConnection();

// Obtener información del producto
$sqlProducto = "SELECT p.*, u.Nombre_del_usuario as vendedor 
                FROM productos p 
                JOIN usuarios u ON p.Id_usuario = u.Id_usuario 
                WHERE p.Id_producto = ? AND p.autorizado = 1";
$stmtProducto = $conn->prepare($sqlProducto);
$stmtProducto->execute([$productoId]);
$producto = $stmtProducto->fetch(PDO::FETCH_ASSOC);

if (!$producto) {
    header('Location: Pagina_principal.php');
    exit();
}

// Obtener multimedia del producto
$sqlMultimedia = "SELECT Imagen, Video FROM multimedia WHERE Id_producto = ?";
$stmtMultimedia = $conn->prepare($sqlMultimedia);
$stmtMultimedia->execute([$productoId]);
$multimedia = $stmtMultimedia->fetchAll(PDO::FETCH_ASSOC);

// Separar imágenes y videos
$imagenes = [];
$videos = [];
foreach ($multimedia as $media) {
    if ($media['Imagen'] !== null) {
        $imagenes[] = $media;
    }
    if ($media['Video'] !== null) {
        $videos[] = $media;
    }
}

// Obtener categoría del producto
$sqlCategoria = "SELECT Nombre_categoria FROM categorias WHERE Id_categoria = ?";
$stmtCategoria = $conn->prepare($sqlCategoria);
$stmtCategoria->execute([$producto['Id_categoria']]);
$categoria = $stmtCategoria->fetch(PDO::FETCH_ASSOC);

// Obtener comentarios y calificaciones
$sqlComentarios = "SELECT c.*, u.Nombre_del_usuario, u.Avatar, 
                  (SELECT cal.Calificacion FROM calificacion cal 
                   WHERE cal.Id_producto = c.Id_producto AND cal.Id_usuario = c.Id_usuario) as Calificacion
                  FROM comentarios c 
                  JOIN usuarios u ON c.Id_usuario = u.Id_usuario 
                  WHERE c.Id_producto = ? 
                  ORDER BY c.Fecha_Creacion DESC";
$stmtComentarios = $conn->prepare($sqlComentarios);
$stmtComentarios->execute([$productoId]);
$comentarios = $stmtComentarios->fetchAll(PDO::FETCH_ASSOC);

// Calcular promedio de calificaciones
$sqlRating = "SELECT AVG(Calificacion) as promedio, COUNT(*) as total 
              FROM calificacion 
              WHERE Id_producto = ?";
$stmtRating = $conn->prepare($sqlRating);
$stmtRating->execute([$productoId]);
$rating = $stmtRating->fetch(PDO::FETCH_ASSOC);
$promedioRating = round($rating['promedio'] ?? 0, 1);
$totalRatings = $rating['total'] ?? 0;

// Obtener listas del usuario si está logueado
$listasUsuario = []; 
if (isset($_SESSION['Id_usuario'])) {
    $sqlListas = "SELECT l.Id_lista, l.Nombre_lista, u.Nombre_del_usuario as creador 
                  FROM lista l
                  JOIN usuarios u ON l.Id_usuario = u.Id_usuario
                  WHERE l.Nombre_lista NOT IN ('Carrito', 'Lista de deseos')";
    $stmtListas = $conn->prepare($sqlListas);
    $stmtListas->execute();
    $listasUsuario = $stmtListas->fetchAll(PDO::FETCH_ASSOC);
}

// Verificar si el usuario ya calificó/comentó este producto
$usuarioYaComento = false;
if (isset($_SESSION['Id_usuario'])) {
    $sqlCheckReview = "SELECT COUNT(*) FROM comentarios 
                      WHERE Id_producto = ? AND Id_usuario = ?";
    $stmtCheckReview = $conn->prepare($sqlCheckReview);
    $stmtCheckReview->execute([$productoId, $_SESSION['Id_usuario']]);
    $usuarioYaComento = $stmtCheckReview->fetchColumn() > 0;
}
$usuarioHaComprado = false;
if (isset($_SESSION['Id_usuario'])) {
    $sqlCheckCompra = "SELECT COUNT(*) FROM compras c
                       JOIN ticket_compra t ON c.Id_compra = t.Id_compra
                       WHERE c.id_usuario = ? AND t.Id_producto = ?";
    $stmtCheckCompra = $conn->prepare($sqlCheckCompra);
    $stmtCheckCompra->execute([$_SESSION['Id_usuario'], $productoId]);
    $usuarioHaComprado = $stmtCheckCompra->fetchColumn() > 0;
}

// Procesar solicitud de cotización si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['solicitar_cotizacion'])) {
    $productoId = $_POST['producto_id'];
    $vendedorId = $producto['Id_usuario']; // ID del vendedor
    
    // Verificar si ya existe una conversación con este vendedor
$sqlCheckConversacion = "SELECT Id_conversacion FROM conversacion 
                         WHERE (id_emisor = ? AND id_receptor = ?) 
                            OR (id_emisor = ? AND id_receptor = ?)";
$stmtCheckConversacion = $conn->prepare($sqlCheckConversacion);
$stmtCheckConversacion->execute([$_SESSION['Id_usuario'], $vendedorId, $vendedorId, $_SESSION['Id_usuario']]);
$conversacionExistente = $stmtCheckConversacion->fetch(PDO::FETCH_ASSOC);
    
    if ($conversacionExistente) {
        // Usar conversación existente
        $conversacionId = $conversacionExistente['Id_conversacion'];
    } else {
        // Crear nueva conversación
        $sqlNuevaConversacion = "INSERT INTO conversacion (id_emisor, id_receptor) VALUES (?, ?)";
        $stmtNuevaConversacion = $conn->prepare($sqlNuevaConversacion);
        $stmtNuevaConversacion->execute([$_SESSION['Id_usuario'], $vendedorId]);
        $conversacionId = $conn->lastInsertId();
    }
    
    // Crear mensaje inicial
    $mensajeInicial = "Hola, estoy interesado en cotizar el producto: " . $producto['Nombre'];
    
    $sqlInsertMensaje = "INSERT INTO mensajes (Mensaje, Fecha, Hora, Id_conversacion, Id_emisor) 
                         VALUES (?, NOW(), CURRENT_TIME(), ?, ?)";
    $stmtInsertMensaje = $conn->prepare($sqlInsertMensaje);
    $stmtInsertMensaje->execute([$mensajeInicial, $conversacionId, $_SESSION['Id_usuario']]);

    // Redirigir al chat
    header("Location: Chat.php?conversacion_id=" . $conversacionId);
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($producto['Nombre']); ?> | TuTiendaOnline</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- CSS personalizado -->
    <link rel="stylesheet" href="../CSS/Estilo_DetalleProducto.css">
</head>
<body>
    <!-- Navbar -->
    <?php include 'Navbar.php'; ?>
    
    <div class="container mt-4 mb-5">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="Pagina_principal.php">Inicio</a></li>
                <li class="breadcrumb-item"><a href="Pagina_principal.php?category=<?php echo $producto['Id_categoria']; ?>">
                    <?php echo htmlspecialchars($categoria['Nombre_categoria'] ?? 'Categoría'); ?>
                </a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($producto['Nombre']); ?></li>
            </ol>
        </nav>
        
        <div class="row">
            <!-- Galería de imágenes y videos -->
            <div class="col-lg-6">
                <div class="sticky-top" style="top: 20px;">
                    <?php if (!empty($imagenes)): ?>
                        <img id="mainImage" src="data:image/jpeg;base64,<?php echo base64_encode($imagenes[0]['Imagen']); ?>" 
                             class="product-image-main" alt="<?php echo htmlspecialchars($producto['Nombre']); ?>">
                    <?php else: ?>
                        <img id="mainImage" src="https://via.placeholder.com/500x500?text=Sin+imagen" 
                             class="product-image-main" alt="Producto sin imagen">
                    <?php endif; ?>
                    
                    <div class="thumbnail-container">
                        <?php foreach ($imagenes as $index => $media): ?>
                            <img src="data:image/jpeg;base64,<?php echo base64_encode($media['Imagen']); ?>" 
                                 class="product-thumbnail" 
                                 onclick="document.getElementById('mainImage').src = this.src"
                                 alt="Miniatura <?php echo $index + 1; ?>">
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (!empty($videos)): ?>
                        <div class="video-container">
                            <h4><i class="fas fa-video"></i> Video del producto</h4>
                            <video id="productVideo" class="video-player" controls>
                                <source src="data:video/mp4;base64,<?php echo base64_encode($videos[0]['Video']); ?>" type="video/mp4">
                                Tu navegador no soporta el elemento de video.
                            </video>
                            
                            <?php if (count($videos) > 1): ?>
                                <div class="d-flex flex-wrap mt-2">
                                    <?php foreach ($videos as $index => $video): ?>
                                        <video class="video-thumbnail" onclick="changeVideo('<?php echo base64_encode($video['Video']); ?>')">
                                            <source src="data:video/mp4;base64,<?php echo base64_encode($video['Video']); ?>" type="video/mp4">
                                        </video>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Información del producto -->
            <div class="col-lg-6">
                <h1 class="mb-3"><?php echo htmlspecialchars($producto['Nombre']); ?></h1>
                
                <div class="d-flex align-items-center mb-3">
                    <div class="rating-stars">
                        <?php
                        $fullStars = floor($promedioRating);
                        $halfStar = ($promedioRating - $fullStars) >= 0.5;
                        $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
                        
                        for ($i = 0; $i < $fullStars; $i++) {
                            echo '<i class="fas fa-star"></i>';
                        }
                        if ($halfStar) {
                            echo '<i class="fas fa-star-half-alt"></i>';
                        }
                        for ($i = 0; $i < $emptyStars; $i++) {
                            echo '<i class="far fa-star"></i>';
                        }
                        ?>
                    </div>
                    <span class="ms-2"><?php echo $promedioRating; ?> (<?php echo $totalRatings; ?> valoraciones)</span>
                </div>
                
                <div class="mb-4">
                    <span class="badge bg-success"><?php echo htmlspecialchars($categoria['Nombre_categoria'] ?? ''); ?></span>
                    <?php if ($producto['Cantidad'] > 0): ?>
                        <span class="badge bg-primary ms-2">Disponible</span>
                    <?php else: ?>
                        <span class="badge bg-secondary ms-2">Agotado</span>
                    <?php endif; ?>
                </div>
                
                <?php if ($producto['Cotizar']): ?>
                    <h3 class="price mb-4">Precio a cotizar</h3>
                <?php else: ?>
                    <h3 class="price mb-4">$<?php echo number_format($producto['Precio'], 2); ?></h3>
                <?php endif; ?>
                
                <div class="seller-info mb-4">
                    <h5><i class="fas fa-store"></i> Vendedor: <?php echo htmlspecialchars($producto['vendedor']); ?></h5>
                    <p class="mb-2"><i class="fas fa-box-open"></i> Unidades disponibles: <?php echo $producto['Cantidad']; ?></p>
                    <p class="mb-0"><i class="fas fa-calendar-alt"></i> Publicado el: <?php echo date('d/m/Y', strtotime($producto['Fecha_ingreso'] ?? 'now')); ?></p>
                </div>
                
                <div class="d-grid gap-2 d-md-flex mb-4">
                   <?php if ($producto['Cotizar']): ?>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="producto_id" value="<?php echo $producto['Id_producto']; ?>">
                            <button type="submit" name="solicitar_cotizacion" class="btn btn-primary btn-lg me-md-2">
                                <i class="fas fa-comment-dollar"></i> Solicitar cotización
                            </button>
                        </form>
                    <?php else: ?>
                       <button class="btn btn-warning btn-lg me-md-2" onclick="addToCart(<?php echo $producto['Id_producto']; ?>)">
                            <i class="fas fa-cart-plus"></i> Añadir al carrito
                        </button>
                    <?php endif; ?>
                    
                    <!-- Botón de Lista de deseos -->
                    <button class="btn btn-outline-danger btn-lg me-md-2" id="wishlist-btn" 
                             onclick="addToWishlist(<?php echo $producto['Id_producto']; ?>)">
                        <i class="fas fa-heart"></i> Lista de deseos
                    </button>
                    
                    <!-- Botón desplegable para agregar a lista -->
                    <?php if (isset($_SESSION['Id_usuario'])): ?>
                        <div class="dropdown">
                            <button class="btn btn-outline-primary btn-lg dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-list"></i> Agregar a lista
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                <?php if (!empty($listasUsuario)): ?>
                                    <?php foreach ($listasUsuario as $lista): ?>
                                        <li><a class="dropdown-item" href="#" onclick="addToList(<?php echo $producto['Id_producto']; ?>, <?php echo $lista['Id_lista']; ?>)">
                                            <?php echo htmlspecialchars($lista['Nombre_lista']); ?> 
                                            <small class="text-muted">(de <?php echo htmlspecialchars($lista['creador']); ?>)</small>
                                        </a></li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li><a class="dropdown-item" href="#" onclick="alert('No tienes listas creadas. Por favor, crea una lista primero.');">No tienes listas</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#newListModal"><i class="fas fa-plus"></i> Crear nueva lista</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <button class="btn btn-outline-primary btn-lg" onclick="alert('Debes iniciar sesión para usar esta función.');">
                            <i class="fas fa-list"></i> Agregar a lista
                        </button>
                    <?php endif; ?>
                </div>
                
                <!-- Modal para crear nueva lista -->
                <div class="modal fade" id="newListModal" tabindex="-1" aria-labelledby="newListModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="newListModalLabel">Crear nueva lista</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="newListForm">
                                    <div class="mb-3">
                                        <label for="listName" class="form-label">Nombre de la lista</label>
                                        <input type="text" class="form-control" id="listName" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="listDescription" class="form-label">Descripción</label>
                                        <textarea class="form-control" id="listDescription" rows="3" required></textarea>
                                    </div>
                                    <input type="hidden" id="productId" value="<?php echo $producto['Id_producto']; ?>">
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="button" class="btn btn-primary" onclick="createNewList()">Crear lista</button>
                            </div>
                        </div>
                    </div>
                </div>
                
               <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle feature-icon"></i>Descripción</h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">
                            <?php 
                            $descripcion = !empty($producto['descripcion']) ? $producto['descripcion'] : 
                                        "Descripción detallada no disponible. Contacta al vendedor para más información.";
                            echo nl2br(htmlspecialchars($descripcion)); 
                            ?>
                        </p>
                    </div>
                </div>        <!-- Aviso para usuarios que compraron pero no comentaron -->
                <?php if ($usuarioHaComprado && !$usuarioYaComento): ?>
                    <div class="alert alert-info">
                        ¡Has comprado este producto! ¿Quieres dejar un comentario o calificación?
                    </div>
                <?php endif; ?>
                <!-- Sección de comentarios y valoraciones -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-comments feature-icon"></i>Comentarios y valoraciones</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($totalRatings > 0): ?>
                            <div class="mb-4">
                                <h6>Promedio de valoraciones: <?php echo $promedioRating; ?> / 5</h6>
                                <div class="progress mb-2" style="height: 20px;">
                                    <div class="progress-bar bg-warning" role="progressbar" 
                                         style="width: <?php echo ($promedioRating / 5) * 100; ?>%" 
                                         aria-valuenow="<?php echo $promedioRating; ?>" 
                                         aria-valuemin="0" aria-valuemax="5"></div>
                                </div>
                                <small>Basado en <?php echo $totalRatings; ?> valoraciones</small>
                            </div>
                            
                            <?php foreach ($comentarios as $comentario): ?>
                                <div class="comment-box">
                                    <div class="d-flex align-items-center mb-2">
                                        <?php if (!empty($comentario['Avatar'])): ?>
                                            <img src="data:image/jpeg;base64,<?php echo base64_encode($comentario['Avatar']); ?>" 
                                                 class="comment-avatar me-3" alt="<?php echo htmlspecialchars($comentario['Nombre_del_usuario']); ?>">
                                        <?php else: ?>
                                            <div class="comment-avatar bg-secondary text-white d-flex align-items-center justify-content-center me-3">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($comentario['Nombre_del_usuario']); ?></h6>
                                            <?php if (!empty($comentario['Calificacion'])): ?>
                                                <div class="comment-rating">
                                                    <?php
                                                    $rating = round($comentario['Calificacion']);
                                                    for ($i = 1; $i <= 5; $i++) {
                                                        if ($i <= $rating) {
                                                            echo '<i class="fas fa-star"></i>';
                                                        } else {
                                                            echo '<i class="far fa-star"></i>';
                                                        }
                                                    }
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <p class="mb-1"><?php echo htmlspecialchars($comentario['Contenido']); ?></p>
                                    <small class="text-muted"><?php echo date('d/m/Y', strtotime($comentario['Fecha_Creacion'])); ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">Aún no hay comentarios para este producto.</p>
                        <?php endif; ?>
                    </div>
                </div> 
              
            </div>
        </div>
    </div>
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>// Verificar sesión directamente en JavaScript
    const isUserLoggedIn = <?php echo isset($_SESSION['Id_usuario']) ? 'true' : 'false'; ?>;
    </script>
    <script src="../JS/Detalle_producto.js"></script>
</body>
</html>