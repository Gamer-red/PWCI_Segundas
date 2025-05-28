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

// Obtener categorías disponibles
$categorias = $conn->query("SELECT * FROM categorias WHERE Id_usuario = $userId")->fetchAll(PDO::FETCH_ASSOC);

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        // Insertar el producto
        $stmt = $conn->prepare("INSERT INTO productos (Id_categoria, Id_usuario, Nombre, Cotizar, Precio, Cantidad, autorizado) 
                               VALUES (?, ?, ?, ?, ?, ?, 0)");
        $stmt->execute([
            $_POST['categoria'],
            $userId,
            $_POST['nombre'],
            isset($_POST['cotizar']) ? 1 : 0,
            $_POST['precio'],
            $_POST['cantidad']
        ]);
        
        $productoId = $conn->lastInsertId();
        
        // Procesar imagen principal
        if (!empty($_FILES['imagen_principal']['tmp_name'])) {
            $imagenPrincipalData = file_get_contents($_FILES['imagen_principal']['tmp_name']);
            
            $stmt = $conn->prepare("INSERT INTO multimedia (Id_producto, Imagen) VALUES (?, ?)");
            $stmt->execute([$productoId, $imagenPrincipalData]);
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
    <style>
        .dashboard-header {
            background-color: #232f3e;
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 2rem;
        }
        
        .btn-amazon {
            background-color: #FF9900;
            color: #131921;
            border-color: #FCD200;
        }
        
        .btn-amazon:hover {
            background-color: #F7CA00;
            border-color: #F2C200;
        }
        
        .product-form-section {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        
        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 5px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 15px;
        }
        
        .upload-area:hover {
            border-color: #FF9900;
            background-color: #f8f9fa;
        }
        
        .preview-container {
            margin-top: 15px;
        }
        
        .preview-image {
            max-width: 100%;
            max-height: 200px;
            border-radius: 5px;
            margin-top: 10px;
        }
        
        .preview-video {
            max-width: 100%;
            max-height: 200px;
            border-radius: 5px;
            margin-top: 10px;
        }
        
        .file-info {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .remove-media {
            color: #dc3545;
            cursor: pointer;
            font-size: 0.9rem;
            margin-top: 5px;
            display: inline-block;
        }
        .form-control:disabled {
            background-color: #e9ecef;
            opacity: 1;
        }
    </style>
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
        
        <form action="Nuevo_producto.php" method="POST" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-8">
                    <!-- Información básica del producto -->
                    <div class="product-form-section">
                        <h4 class="mb-4"><i class="fas fa-info-circle me-2"></i> Información básica</h4>
                        
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre del producto <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="categoria" class="form-label">Categoría <span class="text-danger">*</span></label>
                                <select class="form-select" id="categoria" name="categoria" required>
                                    <option value="">Selecciona una categoría</option>
                                    <?php foreach ($categorias as $categoria): ?>
                                        <option value="<?php echo $categoria['Id_categoria']; ?>">
                                            <?php echo htmlspecialchars($categoria['Nombre_categoria']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="cantidad" class="form-label">Cantidad en stock <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="cantidad" name="cantidad" min="0" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="precio" class="form-label">Precio <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="precio" name="precio" step="0.01" min="0" required>
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
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="4"></textarea>
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
                    
                    <!-- Imágenes adicionales -->
                    <div class="product-form-section">
                        <h4 class="mb-4"><i class="fas fa-images me-2"></i> Imágenes adicionales</h4>
                        
                        <div class="upload-area" id="imagenesAdicionalesUpload">
                            <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                            <p>Arrastra o haz clic para subir imágenes adicionales</p>
                            <small class="text-muted">(Máximo 5 imágenes adicionales)</small>
                            <input type="file" id="imagenes_adicionales" name="imagenes_adicionales[]" accept="image/*" multiple style="display: none;">
                        </div>
                        
                        <div class="preview-container" id="imagenesAdicionalesPreview">
                            <p class="text-muted">No hay imágenes adicionales seleccionadas</p>
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
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Manejar la subida de la imagen principal
        document.getElementById('imagenPrincipalUpload').addEventListener('click', function() {
            document.getElementById('imagen_principal').click();
        });
        
        document.getElementById('imagen_principal').addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    document.getElementById('imagenPrincipalPreviewImg').src = e.target.result;
                    document.getElementById('imagenPrincipalInfo').textContent = 
                        `${file.name} (${(file.size / 1024).toFixed(2)} KB)`;
                    
                    document.getElementById('imagenPrincipalUpload').style.display = 'none';
                    document.getElementById('imagenPrincipalPreview').style.display = 'block';
                }
                
                reader.readAsDataURL(file);
            }
        });
        
        // Manejar la subida del video
        document.getElementById('videoUpload').addEventListener('click', function() {
            document.getElementById('video').click();
        });
        
        document.getElementById('video').addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const videoPreview = document.getElementById('videoPreviewElement');
                const videoUrl = URL.createObjectURL(file);
                
                videoPreview.src = videoUrl;
                document.getElementById('videoInfo').textContent = 
                    `${file.name} (${(file.size / (1024 * 1024)).toFixed(2)} MB)`;
                
                document.getElementById('videoUpload').style.display = 'none';
                document.getElementById('videoPreview').style.display = 'block';
            }
        });
        
        // Manejar la subida de imágenes adicionales
        document.getElementById('imagenesAdicionalesUpload').addEventListener('click', function() {
            document.getElementById('imagenes_adicionales').click();
        });
        
        document.getElementById('imagenes_adicionales').addEventListener('change', function(e) {
            const previewContainer = document.getElementById('imagenesAdicionalesPreview');
            
            if (this.files && this.files.length > 0) {
                previewContainer.innerHTML = '';
                
                for (let i = 0; i < this.files.length; i++) {
                    const file = this.files[i];
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const previewItem = document.createElement('div');
                        previewItem.className = 'mb-3';
                        
                        previewItem.innerHTML = `
                            <img src="${e.target.result}" class="preview-image">
                            <div class="file-info">${file.name} (${(file.size / 1024).toFixed(2)} KB)</div>
                        `;
                        
                        previewContainer.appendChild(previewItem);
                    }
                    
                    reader.readAsDataURL(file);
                }
            } else {
                previewContainer.innerHTML = '<p class="text-muted">No hay imágenes adicionales seleccionadas</p>';
            }
        });
        // Función para eliminar medios
        function removeMedia(type) {
            if (type === 'imagen_principal') {
                document.getElementById('imagen_principal').value = '';
                document.getElementById('imagenPrincipalUpload').style.display = 'block';
                document.getElementById('imagenPrincipalPreview').style.display = 'none';
            } else if (type === 'video') {
                document.getElementById('video').value = '';
                document.getElementById('videoPreviewElement').src = '';
                document.getElementById('videoUpload').style.display = 'block';
                document.getElementById('videoPreview').style.display = 'none';
            }
        }
        // Validación del formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            const imagenPrincipal = document.getElementById('imagen_principal').files.length;
            
            if (imagenPrincipal === 0) {
                e.preventDefault();
                alert('Debes subir al menos la imagen principal del producto');
                return false;
            }
            return true;
        });
        document.getElementById('cotizar').addEventListener('change', function() {
    const precioInput = document.getElementById('precio');
    
    if (this.checked) {
        precioInput.disabled = true;
        precioInput.required = false;
        precioInput.value = ''; // Opcional: limpiar el valor
    } else {
        precioInput.disabled = false;
        precioInput.required = true;
    }
});

// También verificar al cargar la página por si ya está marcado
        document.addEventListener('DOMContentLoaded', function() {
            const cotizarCheckbox = document.getElementById('cotizar');
            const precioInput = document.getElementById('precio');
            
            if (cotizarCheckbox.checked) {
                precioInput.disabled = true;
                precioInput.required = false;
            }
        });
    </script>
</body>
</html>