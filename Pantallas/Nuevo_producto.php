<?php
// Iniciar sesión y verificar rol de vendedor
session_start();
require_once '../Config/database.php';

if (!isset($_SESSION['Id_usuario'])) {
    header('Location: Login.php');
    exit();
}

// Obtener instancia de la base de datos y conexión
$db = Database::getInstance();
$conn = $db->getConnection();

$userId = $_SESSION['Id_usuario'];

// Verificar si el usuario es vendedor
$stmt = $conn->prepare("SELECT Id_rol FROM usuarios WHERE Id_usuario = ?");
$stmt->execute([$userId]);
$idRol = $stmt->fetchColumn();

if ($idRol != 2) { // 2 = Vendedor
    header('Location: Login.php');
    exit();
}

// Procesar creación de nueva categoría si se envió el formulario del modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nueva_categoria'])) {
    $nombreCategoria = trim($_POST['nombre_categoria']);
    if (empty($nombreCategoria)) {
        $error_message = 'El nombre de la categoría es requerido';
    } else {
        try {
            $sql = "INSERT INTO categorias (Nombre_categoria, Id_usuario, autorizado) VALUES (?, ?, 0)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$nombreCategoria, $userId]);
            
            $_SESSION['success_message'] = 'Categoría creada exitosamente. Espera a que un administrador la apruebe.';
            header('Location: Nuevo_producto.php');
            exit();
        } catch (PDOException $e) {
            $error_message = 'Error al crear la categoría: ' . $e->getMessage();
        }
    }
}

// Obtener categorías disponibles
$categorias = $conn->query("SELECT * FROM categorias WHERE autorizado = 1")->fetchAll(PDO::FETCH_ASSOC);

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre'])) {
    try {
        $conn->beginTransaction();
        
        // Insertar el producto
       $stmt = $conn->prepare("INSERT INTO productos (Id_categoria, Id_usuario, Nombre, Cotizar, Precio, Cantidad, autorizado, descripcion) 
                       VALUES (?, ?, ?, ?, ?, ?, 0, ?)");
       $stmt->execute([
        $_POST['categoria'],
        $userId,
        $_POST['nombre'],
        isset($_POST['cotizar']) ? 1 : 0,
        $_POST['precio'],
        $_POST['cantidad'],
        $_POST['descripcion'] ?? '' // Usamos el operador de fusión null para evitar errores si no se envía
        ]);
        
        $productoId = $conn->lastInsertId();
        
        // Procesar imagen principal
        if (!empty($_FILES['imagen_principal']['tmp_name'])) {
            $imagenPrincipalData = file_get_contents($_FILES['imagen_principal']['tmp_name']);
            
            $stmt = $conn->prepare("INSERT INTO multimedia (Id_producto, Imagen) VALUES (?, ?)");
            $stmt->execute([$productoId, $imagenPrincipalData]);
        }

        // Imagen 2
        if (!empty($_FILES['imagen_2']['tmp_name'])) {
            $imagen2Data = file_get_contents($_FILES['imagen_2']['tmp_name']);
            $stmt = $conn->prepare("INSERT INTO multimedia (Id_producto, Imagen) VALUES (?, ?)");
            $stmt->execute([$productoId, $imagen2Data]);
        }

        // Imagen 3
        if (!empty($_FILES['imagen_3']['tmp_name'])) {
            $imagen3Data = file_get_contents($_FILES['imagen_3']['tmp_name']);
            $stmt = $conn->prepare("INSERT INTO multimedia (Id_producto, Imagen) VALUES (?, ?)");
            $stmt->execute([$productoId, $imagen3Data]);
        }
        
        // Procesar video
        if (!empty($_FILES['video']['tmp_name'])) {
            $videoData = file_get_contents($_FILES['video']['tmp_name']);
            
            $stmt = $conn->prepare("INSERT INTO multimedia (Id_producto, Video) VALUES (?, ?)");
            $stmt->execute([$productoId, $videoData]);
        }
        
        // Procesar imágenes adicionales
        if (!empty($_FILES['imagenes_adicionales']['name'][0])) {
            foreach ($_FILES['imagenes_adicionales']['tmp_name'] as $key => $tmpName) {
                if ($tmpName) {
                    $imageData = file_get_contents($tmpName);
                    
                    $stmt = $conn->prepare("INSERT INTO multimedia (Id_producto, Imagen) VALUES (?, ?)");
                    $stmt->execute([$productoId, $imageData]);
                }
            }
        }
        
        $conn->commit();
        $_SESSION['success_message'] = "Producto creado exitosamente. Está pendiente de aprobación.";
        header('Location: Inventario.php');
        exit();
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = "Error al crear el producto: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Producto | TuTiendaOnline</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../CSS/Estilo_NuevoProducto.css">
</head>
<body>
    <!-- Navbar -->
    <?php include 'Navbar.php'; ?>
    <!-- Header del dashboard -->
    <div class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-plus-circle me-2"></i> Nuevo Producto</h1>
                    <p class="mb-0">Agrega un nuevo producto a tu inventario</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="ResumenVentas.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-1"></i> Atras
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- Contenido principal -->
    <div class="container mb-5">
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success_message']); ?></div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <form action="Nuevo_producto.php" method="POST" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-8">
                    <!-- Información básica del producto -->
                    <div class="product-form-section">
                        <h4 class="mb-4"><i class="fas fa-info-circle me-2"></i> Información básica</h4>
                        
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre del producto <span class="text-danger">*</span></label>
                          <input type="text" class="form-control" id="nombre" name="nombre" required pattern="[A-Za-z\s]+" title="Solo se permiten letras y espacios">
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="categoria" class="form-label">Categoría <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <select class="form-select" id="categoria" name="categoria" required>
                                        <option value="">Selecciona una categoría</option>
                                        <?php foreach ($categorias as $categoria): ?>
                                            <option value="<?php echo $categoria['Id_categoria']; ?>">
                                                <?php echo htmlspecialchars($categoria['Nombre_categoria']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-add-category" data-bs-toggle="modal" data-bs-target="#nuevaCategoriaModal">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="cantidad" class="form-label">Cantidad en stock <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="cantidad" name="cantidad" min="1" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="precio" class="form-label">Precio <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="precio" name="precio" step="0.01" min="0" >
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Opciones</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="cotizar" name="cotizar">
                                    <label class="form-check-label" for="cotizar">
                                        Permitir cotización (precio negociable)
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción detallada</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="4" required></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <!-- Imagen principal del producto -->
                    <div class="product-form-section">
                        <h4 class="mb-4"><i class="fas fa-image me-2"></i> Imagen principal</h4>
                        
                        <div class="upload-area" id="imagenPrincipalUpload">
                            <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                            <p>Arrastra o haz clic para subir la imagen principal</p>
                            <small class="text-muted">(Recomendado: 800x800px, formato JPG o PNG)</small>
                            <input type="file" id="imagen_principal" name="imagen_principal" accept="image/*" style="display: none;" required>
                        </div>
                        
                        <div class="preview-container" id="imagenPrincipalPreview" style="display: none;">
                            <img id="imagenPrincipalPreviewImg" class="preview-image">
                            <div class="file-info" id="imagenPrincipalInfo"></div>
                            <span class="remove-media" onclick="removeMedia('imagen_principal')">
                                <i class="fas fa-trash-alt me-1"></i> Eliminar imagen
                            </span>
                        </div>
                    </div>
                    
                    <!-- Video del producto -->
                    <div class="product-form-section">
                        <h4 class="mb-4"><i class="fas fa-video me-2"></i> Video del producto</h4>
                        
                        <div class="upload-area" id="videoUpload">
                            <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                            <p>Arrastra o haz clic para subir un video</p>
                            <small class="text-muted">(Recomendado: formato MP4, máximo 50MB)</small>
                            <input type="file" id="video" name="video" accept="video/*" style="display: none;">
                        </div>
                        
                        <div class="preview-container" id="videoPreview" style="display: none;">
                            <video id="videoPreviewElement" class="preview-video" controls>
                                Tu navegador no soporta la reproducción de videos.
                            </video>
                            <div class="file-info" id="videoInfo"></div>
                            <span class="remove-media" onclick="removeMedia('video')">
                                <i class="fas fa-trash-alt me-1"></i> Eliminar video
                            </span>
                        </div>
                    </div>
                    
                    <!-- Imagen 2 -->
                    <div class="product-form-section">
                        <h4 class="mb-4"><i class="fas fa-image me-2"></i> Imagen 2</h4>

                        <div class="upload-area" id="imagen2Upload">
                            <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                            <p>Haz clic para subir la Imagen 2</p>
                            <input type="file" id="imagen_2" name="imagen_2" accept="image/*" style="display: none;">
                        </div>

                        <div class="preview-container" id="imagen2Preview" style="display: none;">
                            <img id="imagen2PreviewImg" class="preview-image">
                            <div class="file-info" id="imagen2Info"></div>
                            <span class="remove-media" onclick="removeMedia('imagen_2')">
                                <i class="fas fa-trash-alt me-1"></i> Eliminar imagen
                            </span>
                        </div>
                    </div>

                    <!-- Imagen 3 -->
                    <div class="product-form-section">
                        <h4 class="mb-4"><i class="fas fa-image me-2"></i> Imagen 3</h4>

                        <div class="upload-area" id="imagen3Upload">
                            <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                            <p>Haz clic para subir la Imagen 3</p>
                            <input type="file" id="imagen_3" name="imagen_3" accept="image/*" style="display: none;">
                        </div>

                        <div class="preview-container" id="imagen3Preview" style="display: none;">
                            <img id="imagen3PreviewImg" class="preview-image">
                            <div class="file-info" id="imagen3Info"></div>
                            <span class="remove-media" onclick="removeMedia('imagen_3')">
                                <i class="fas fa-trash-alt me-1"></i> Eliminar imagen
                            </span>
                        </div>
                    </div>
                    
                    <!-- Publicación -->
                    <div class="product-form-section">
                        <h4 class="mb-4"><i class="fas fa-paper-plane me-2"></i> Publicación</h4>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Todos los productos nuevos requieren aprobación antes de ser publicados.
                        </div>
                        
                        <button type="submit" class="btn btn-amazon w-100">
                            <i class="fas fa-save me-2"></i> Guardar y enviar para aprobación
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Modal para nueva categoría -->
    <div class="modal fade" id="nuevaCategoriaModal" tabindex="-1" aria-labelledby="nuevaCategoriaModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="nuevaCategoriaModalLabel"><i class="fas fa-plus-circle me-2"></i>Nueva Categoría</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="Nuevo_producto.php">
                    <div class="modal-body">
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Las nuevas categorías deben ser aprobadas por un administrador antes de ser visibles.
                        </div>
                        
                        <div class="mb-3">
                            <label for="nombre_categoria" class="form-label fw-bold">Nombre de la categoría</label>
                           <input type="text" class="form-control" id="nombre_categoria" name="nombre_categoria"
                                placeholder="Ej. Electrónica, Ropa, Hogar" required
                                pattern="[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+" 
                                title="Solo se permiten letras y espacios"
                                onkeypress="return /[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/.test(event.key)">
                            <div class="form-text">El nombre debe ser descriptivo y único.</div>
                        </div>
                        <input type="hidden" name="nueva_categoria" value="1">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Guardar Categoría
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../JS/Nuevo_producto.js"></script>
    <script>document.addEventListener('DOMContentLoaded', function () {
    const cotizarCheckbox = document.getElementById('cotizar');
    const precioInput = document.getElementById('precio');

    // Función para actualizar el estado del campo de precio
    function actualizarEstadoPrecio() {
        if (cotizarCheckbox.checked) {
            precioInput.disabled = true;
            precioInput.required = false;
            precioInput.value = ''; // Opcional: borrar el valor si está cotizando
        } else {
            precioInput.disabled = false;
            precioInput.required = true;
        }
    }

    // Verificar al cargar la página
    actualizarEstadoPrecio();

    // Escuchar cambios en el checkbox
    cotizarCheckbox.addEventListener('change', actualizarEstadoPrecio);
});</script>
</body>
</html>