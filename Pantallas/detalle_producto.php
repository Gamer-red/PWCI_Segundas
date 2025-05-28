<?php
session_start();
require_once '../Config/database.php';

if (!isset($_SESSION['Id_usuario'])) {
    header('Location: Login.php');
    exit();
}
// Verificar si se recibió un ID de producto
if (!isset($_GET['id']))
 {
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

// Si no existe el producto o no está autorizado, redirigir
if (!$producto) {
    header('Location: Pagina_principal.php');
    exit();
}

// Obtener multimedia del producto (imágenes y videos)
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
$sqlComentarios = "SELECT c.*, u.Nombre_del_usuario, u.Avatar 
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
    $sqlListas = "SELECT Id_lista, Nombre_lista FROM lista WHERE Id_usuario = ?";
    $stmtListas = $conn->prepare($sqlListas);
    $stmtListas->execute([$_SESSION['Id_usuario']]);
    $listasUsuario = $stmtListas->fetchAll(PDO::FETCH_ASSOC);
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
            <div class="col-md-6">
                <div class="mb-3">
                    <?php if (!empty($imagenes)): ?>
                        <img id="mainImage" src="data:image/jpeg;base64,<?php echo base64_encode($imagenes[0]['Imagen']); ?>" 
                             class="product-image-main" alt="<?php echo htmlspecialchars($producto['Nombre']); ?>">
                    <?php else: ?>
                        <img id="mainImage" src="https://via.placeholder.com/500x500?text=Sin+imagen" 
                             class="product-image-main" alt="Producto sin imagen">
                    <?php endif; ?>
                </div>
                
                <div class="thumbnail-container d-flex flex-wrap">
                    <?php foreach ($imagenes as $index => $media): ?>
                        <img src="data:image/jpeg;base64,<?php echo base64_encode($media['Imagen']); ?>" 
                             class="product-thumbnail" 
                             onclick="document.getElementById('mainImage').src = this.src"
                             alt="Miniatura <?php echo $index + 1; ?>">
                    <?php endforeach; ?>
                </div>
                
                <!-- Sección de video -->
                <?php if (!empty($videos)): ?>
                    <div class="video-container">
                        <h4>Video del producto</h4>
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
            
            <!-- Información del producto -->
            <div class="col-md-6">
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
                        <button class="btn btn-primary btn-lg me-md-2">
                            <i class="fas fa-comment-dollar"></i> Solicitar cotización
                        </button>
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
                                        <li><a class="dropdown-item" href="#" onclick="addToList(<?php echo $producto['Id_producto']; ?>, <?php echo $lista['Id_lista']; ?>)"><?php echo htmlspecialchars($lista['Nombre_lista']); ?></a></li>
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
                                        <label for="listDescription" class="form-label">Descripción (opcional)</label>
                                        <textarea class="form-control" id="listDescription" rows="3"></textarea>
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
                            $descripcion = "Descripción detallada no disponible. Contacta al vendedor para más información.";
                            echo htmlspecialchars($descripcion); 
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
   <script>
   // Función para agregar al carrito
function addToCart(productId) {
    <?php if (isset($_SESSION['Id_usuario'])): ?>
        fetch('Cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                productId: productId, 
                quantity: 1
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la red');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Actualizar contador del carrito
                const cartCount = document.getElementById('cart-count');
                if (cartCount) {
                    cartCount.textContent = data.cartCount;
                }
                alert(data.message || 'Producto añadido al carrito');
            } else {
                alert(data.message || 'Error al añadir al carrito');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión: ' + error.message);
        });
    <?php else: ?>
        alert('Debes iniciar sesión para añadir productos al carrito');
        window.location.href = 'Login.php';
    <?php endif; ?>
    }
    // Función para agregar a lista de deseos
   function addToWishlist(productId) {
    <?php if (isset($_SESSION['Id_usuario'])): ?>
        const wishlistBtn = document.getElementById('wishlist-btn');
        wishlistBtn.disabled = true;
        wishlistBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
        
        fetch('Wishlist.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'add',
                productId: productId
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la red');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Cambiar el botón para mostrar que ya está en la lista
                wishlistBtn.innerHTML = '<i class="fas fa-check"></i> En tu lista';
                wishlistBtn.classList.remove('btn-outline-danger');
                wishlistBtn.classList.add('btn-success');
                wishlistBtn.disabled = true;
                
                // Mostrar mensaje de éxito
                alert(data.message || 'Producto añadido a tu lista de deseos');
            } else {
                alert(data.message || 'Error al añadir a lista de deseos');
                wishlistBtn.innerHTML = '<i class="fas fa-heart"></i> Lista de deseos';
                wishlistBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión: ' + error.message);
            wishlistBtn.innerHTML = '<i class="fas fa-heart"></i> Lista de deseos';
            wishlistBtn.disabled = false;
        });
    <?php else: ?>
        alert('Debes iniciar sesión para añadir productos a tu lista de deseos');
        window.location.href = 'Login.php';
    <?php endif; ?>
}
    // Función para cambiar el video principal
    function changeVideo(videoData) {
        const videoPlayer = document.getElementById('productVideo');
        videoPlayer.src = 'data:video/mp4;base64,' + videoData;
        videoPlayer.load();
        videoPlayer.play();
    }

    // Función para crear nueva lista
    function createNewList() {
        const listName = document.getElementById('listName').value;
        const description = document.getElementById('listDescription').value;
        const productId = document.getElementById('productId').value;

        if (!listName) {
            alert('El nombre de la lista es requerido');
            return;
        }
        fetch('Create_list.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                listName: listName,
                description: description,
                productId: productId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Lista creada y producto añadido');
                // Cerrar el modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('newListModal'));
                modal.hide();
                // Recargar la página para mostrar la nueva lista en el dropdown
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al crear la lista');
        });
    }
    // Función para agregar a una lista existente
    function addToList(productId, listId) {
        fetch('add_to_list.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                productId: productId,
                listId: listId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Producto añadido a la lista');
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al añadir a la lista');
        });
    }
</script>
</body>
</html>