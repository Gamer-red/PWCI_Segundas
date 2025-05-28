<?php
// Iniciar sesión y verificar autenticación
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
// Obtener datos del usuario
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE Id_usuario = ?");
$stmt->execute([$userId]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

// Verificar si el usuario es vendedor
$esVendedor = ($usuario['Id_rol'] == 2);

// Obtener estadísticas si es vendedor
if ($esVendedor) {
    $totalProductos = $conn->query("SELECT COUNT(*) FROM productos WHERE Id_usuario = $userId")->fetchColumn();
    $totalVentas = $conn->query("SELECT COUNT(*) FROM ventas WHERE Id_Usuario = $userId")->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil de Usuario | TuTiendaOnline</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-header {
            background-color: #232f3e;
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .avatar-container {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto;
        }
        
        .avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid white;
        }
        
        .avatar-upload {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: #FF9900;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        .stat-card {
            border-radius: 10px;
            transition: all 0.3s;
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #FF9900;
        }
        
        .nav-pills .nav-link.active {
            background-color: #FF9900;
        }
        
        .nav-pills .nav-link {
            color: #232f3e;
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
        
        .profile-section {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'Navbar.php'; ?>
    
    <!-- Encabezado del perfil -->
    <div class="profile-header">
        <div class="container text-center">
            <div class="avatar-container mb-3">
                <?php if ($usuario['Avatar']): ?>
                    <img src="data:image/jpeg;base64,<?php echo base64_encode($usuario['Avatar']); ?>" class="avatar-img" alt="Avatar">
                <?php else: ?>
                    <div class="avatar-img bg-light d-flex align-items-center justify-content-center">
                        <i class="fas fa-user fa-4x text-secondary"></i>
                    </div>
                <?php endif; ?>
                <label for="avatarInput" class="avatar-upload">
                    <i class="fas fa-camera text-white"></i>
                    <input type="file" id="avatarInput" style="display: none;" accept="image/*">
                </label>
            </div>
            <h1><?php echo htmlspecialchars($usuario['Nombre'] . ' ' . $usuario['Apellido_paterno']); ?></h1>
            <p class="lead">@<?php echo htmlspecialchars($usuario['Nombre_del_usuario']); ?></p>
            <p><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($usuario['Correo']); ?></p>
        </div>
    </div>
    
    <!-- Contenido principal -->
    
        
        <div class="row">
            <div class="col-md-4">
                <!-- Menú de navegación -->
                <div class="profile-section">
                    <ul class="nav nav-pills flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="#informacion" data-bs-toggle="pill">Información personal</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#seguridad" data-bs-toggle="pill">Seguridad</a>
                        </li>
                    </ul>
                </div>
                
                <!-- Acciones rápidas -->
                <div class="profile-section">
                    <h5 class="mb-3"><i class="fas fa-bolt me-2"></i>Acciones rápidas</h5>
                    <div class="d-grid gap-2">
                        <?php if ($esVendedor): ?>
                            <a href="ResumenVentas.php" class="btn btn-amazon">
                                <i class="fas fa-chart-pie me-2"></i>Ver resumen de ventas
                            </a>
                            <a href="mis_productos.php" class="btn btn-outline-secondary">
                                <i class="fas fa-boxes me-2"></i>Mis productos
                            </a>
                        <?php else: ?>
                            <a href="compras.php" class="btn btn-amazon">
                                <i class="fas fa-shopping-bag me-2"></i>Mis compras
                            </a>
                            <a href="listas.php" class="btn btn-outline-secondary">
                                <i class="fas fa-list me-2"></i>Mis listas
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <!-- Contenido de las pestañas -->
                <div class="tab-content">
                    <!-- Información personal -->
                    <div class="tab-pane fade show active profile-section" id="informacion">
                        <h4 class="mb-4"><i class="fas fa-user me-2"></i>Información personal</h4>
                        <form>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="nombre" class="form-label">Nombre</label>
                                    <input type="text" class="form-control" id="nombre" value="<?php echo htmlspecialchars($usuario['Nombre']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="apellidoPaterno" class="form-label">Apellido paterno</label>
                                    <input type="text" class="form-control" id="apellidoPaterno" value="<?php echo htmlspecialchars($usuario['Apellido_paterno']); ?>">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="apellidoMaterno" class="form-label">Apellido materno</label>
                                    <input type="text" class="form-control" id="apellidoMaterno" value="<?php echo htmlspecialchars($usuario['Apellido_materno']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="fechaNacimiento" class="form-label">Fecha de nacimiento</label>
                                    <input type="date" class="form-control" id="fechaNacimiento" value="<?php echo htmlspecialchars($usuario['Fecha_nacimiento']); ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="nombreUsuario" class="form-label">Nombre de usuario</label>
                                <div class="input-group">
                                    <span class="input-group-text">@</span>
                                    <input type="text" class="form-control" id="nombreUsuario" value="<?php echo htmlspecialchars($usuario['Nombre_del_usuario']); ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="correo" class="form-label">Correo electrónico</label>
                                <input type="email" class="form-control" id="correo" value="<?php echo htmlspecialchars($usuario['Correo']); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Sexo</label>
                                <div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="sexo" id="masculino" value="1" <?php echo ($usuario['Sexo'] == 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="masculino">Masculino</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="sexo" id="femenino" value="0" <?php echo ($usuario['Sexo'] == 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="femenino">Femenino</label>
                                    </div>
                                </div>
                    </div>
        <!-- Nueva sección para visibilidad del perfil -->
        <div class="mb-3">
            <label class="form-label">Visibilidad del perfil</label>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="perfilPublico" name="perfilPublico" 
                       <?php echo ($usuario['perfil_publico'] == 1) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="perfilPublico">Perfil público</label>
            </div>
            <small class="text-muted">Cuando está activado, otros usuarios pueden ver tu perfil.</small>
        </div>
              <button type="submit" class="btn btn-amazon">Guardar cambios</button>
            </form>
                        </div>
                    <!-- Seguridad -->
                    <div class="tab-pane fade profile-section" id="seguridad">
                        <h4 class="mb-4"><i class="fas fa-lock me-2"></i>Seguridad</h4>
                        <form>
                            <div class="mb-3">
                                <label for="contraseniaActual" class="form-label">Contraseña actual</label>
                                <input type="password" class="form-control" id="contraseniaActual">
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="nuevaContrasenia" class="form-label">Nueva contraseña</label>
                                    <input type="password" class="form-control" id="nuevaContrasenia">
                                </div>
                                <div class="col-md-6">
                                    <label for="confirmarContrasenia" class="form-label">Confirmar nueva contraseña</label>
                                    <input type="password" class="form-control" id="confirmarContrasenia">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-amazon">Cambiar contraseña</button>
                        </form>    
                        <hr class="my-4">           
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Manejar la subida del avatar
        document.getElementById('avatarInput').addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    document.querySelector('.avatar-img').src = e.target.result;
                    // Aquí podrías agregar código para subir la imagen al servidor
                }
                
                reader.readAsDataURL(this.files[0]);
            }
        });
        // Manejar el envío del formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Recoger los datos del formulario
            const formData = {
                nombre: document.getElementById('nombre').value,
                apellidoPaterno: document.getElementById('apellidoPaterno').value,
                apellidoMaterno: document.getElementById('apellidoMaterno').value,
                fechaNacimiento: document.getElementById('fechaNacimiento').value,
                nombreUsuario: document.getElementById('nombreUsuario').value,
                correo: document.getElementById('correo').value,
                sexo: document.querySelector('input[name="sexo"]:checked').value,
                perfilPublico: document.getElementById('perfilPublico').checked ? 1 : 0
            };
            
            // Enviar los datos al servidor (usando Fetch API)
            fetch('actualizar_perfil.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Perfil actualizado correctamente');
                    // Recargar la página para ver los cambios
                    location.reload();
                } else {
                    alert('Error al actualizar el perfil: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Ocurrió un error al actualizar el perfil');
            });
        });
    </script>
</body>
</html>