<?php
// Iniciar sesión y conectar a la base de datos
session_start();
require_once '../Config/database.php';
// Variables para mensajes de error/éxito
$error = '';
$success = '';
// Procesar el formulario si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Obtener datos del formulario
    $rol = $_POST['rol'];
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $nombre = trim($_POST['nombre']);
    $apellido_paterno = trim($_POST['apellido_paterno']);
    $apellido_materno = trim($_POST['apellido_materno']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];
    $fecha_nacimiento = $_POST['fecha_nacimiento'];
    $sexo = $_POST['sexo'] == 'masculino' ? 1 : 0;
    
    // Validaciones básicas
    if ($password !== $confirmPassword) {
        $error = "Las contraseñas no coinciden";
    } else {
        try {
            // Verificar si el usuario o email ya existen
            $stmt = $conn->prepare("SELECT Id_usuario FROM usuarios WHERE Nombre_del_usuario = ? OR Correo = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->rowCount() > 0) {
                $error = "El nombre de usuario o correo electrónico ya está en uso";
            } else {
                // Determinar el Id_rol según la selección
                $id_rol = ($rol == 'vendedor') ? 2 : 1; // Asumiendo que en la tabla rol: 1=comprador, 2=vendedor    
                // Procesar imagen de avatar (si se subió)
                $avatar = null;
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $avatar = file_get_contents($_FILES['avatar']['tmp_name']);
                }
                // Insertar nuevo usuario
                $stmt = $conn->prepare("INSERT INTO usuarios 
                    (Id_rol, Correo, Nombre_del_usuario, Nombre, Apellido_paterno, Apellido_materno, 
                    Contrasenia, Avatar, Fecha_nacimiento, Fecha_ingreso, Sexo) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)");
                
                $stmt->execute([
                    $id_rol, 
                    $email, 
                    $username, 
                    $nombre, 
                    $apellido_paterno, 
                    $apellido_materno, 
                    $password,
                    $avatar, 
                    $fecha_nacimiento, 
                    $sexo
                ]);
                
                // Obtener el ID del nuevo usuario
                $userId = $conn->lastInsertId();
                
                // Iniciar sesión automáticamente
                $_SESSION['Id_usuario'] = $userId;
                $_SESSION['Nombre_del_usuario'] = $username;
                $_SESSION['esVendedor'] = ($rol == 'vendedor');
                
                // Redirigir según el rol
                header('Location: ' . ($_SESSION['esVendedor'] ? 'ResumenVentas.php' : 'Pagina_principal.php'));
                exit();
            }
        } catch (PDOException $e) {
            $error = "Error al registrar el usuario: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro | TuTiendaOnline</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
     <link rel="stylesheet" href="../CSS/Estilo_RegistroUsuarios.css">
</head>
<body class="d-flex align-items-center min-vh-100 py-4">
    <div class="container register-container">
        <div class="logo">
            <h1 class="logo-text">TuTiendaOnline</h1>
            <h2 class="h4 text-center">Crear cuenta</h2>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="card register-card">
            <div class="card-body p-4">
                <form id="registerForm" method="POST" action="RegistroUsuarios.php" enctype="multipart/form-data">
                    <!-- Selector de Rol -->
                    <div class="role-selector mb-4">
                        <h5 class="fw-bold mb-3 required-field">¿Qué tipo de cuenta deseas crear?</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="role-option text-center <?php echo (isset($_POST['rol']) && $_POST['rol'] == 'comprador') ? 'selected' : ''; ?>" 
                                     onclick="selectRole('comprador')" id="compradorOption">
                                    <div class="role-icon">🛒</div>
                                    <h6>Comprador</h6>
                                    <p class="small text-muted">Comprar productos en la plataforma</p>
                                    <input type="radio" name="rol" id="comprador" value="comprador" required 
                                           <?php echo (isset($_POST['rol']) && $_POST['rol'] == 'comprador') ? 'checked' : ''; ?>>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="role-option text-center <?php echo (isset($_POST['rol']) && $_POST['rol'] == 'vendedor') ? 'selected' : ''; ?>" 
                                     onclick="selectRole('vendedor')" id="vendedorOption">
                                    <div class="role-icon">🏪</div>
                                    <h6>Vendedor</h6>
                                    <p class="small text-muted">Vender productos en la plataforma</p>
                                    <input type="radio" name="rol" id="vendedor" value="vendedor" 
                                           <?php echo (isset($_POST['rol'])) && $_POST['rol'] == 'vendedor' ? 'checked' : ''; ?>>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Campos del formulario -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label fw-bold required-field">Nombre de usuario</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label fw-bold required-field">Correo electrónico</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                         <div class="col-md-4 mb-3">
                                <label for="nombre" class="form-label fw-bold required-field">Nombre</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" 
                                    value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>" 
                                    required
                                    pattern="[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+"
                                    title="Solo letras y espacios">
                            </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="apellido_paterno" class="form-label fw-bold required-field">Apellido paterno</label>
                            <input type="text" class="form-control" id="apellido_paterno" name="apellido_paterno" 
                                   value="<?php echo isset($_POST['apellido_paterno']) ? htmlspecialchars($_POST['apellido_paterno']) : ''; ?>" required
                                    pattern="[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+"
                                    title="Solo letras y espacios">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="apellido_materno" class="form-label fw-bold required-field">Apellido materno</label>
                            <input type="text" class="form-control" id="apellido_materno" name="apellido_materno" 
                                   value="<?php echo isset($_POST['apellido_materno']) ? htmlspecialchars($_POST['apellido_materno']) : ''; ?>"required
                                    pattern="[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+"
                                    title="Solo letras y espacios">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label fw-bold required-field">Contraseña</label>
                            <input type="password" class="form-control" id="password" name="password" 
                                required minlength="8"
                                title="La contraseña debe tener al menos 8 caracteres">
                            <div class="form-text">Mínimo 8 caracteres</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="confirmPassword" class="form-label fw-bold required-field">Confirmar contraseña</label>
                            <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" 
                                required minlength="8"
                                title="La contraseña debe tener al menos 8 caracteres">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="fecha_nacimiento" class="form-label fw-bold required-field">Fecha de nacimiento</label>
                            <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" 
                                   value="<?php echo isset($_POST['fecha_nacimiento']) ? htmlspecialchars($_POST['fecha_nacimiento']) : ''; ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold required-field">Sexo</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="sexo" id="masculino" value="masculino" 
                                           <?php echo (!isset($_POST['sexo'])) || (isset($_POST['sexo']) && $_POST['sexo'] == 'masculino') ? 'checked' : ''; ?> required>
                                    <label class="form-check-label" for="masculino">Masculino</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="sexo" id="femenino" value="femenino" 
                                           <?php echo (isset($_POST['sexo']) && $_POST['sexo'] == 'femenino') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="femenino">Femenino</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="avatar" class="form-label fw-bold">Avatar</label>
                        <div class="d-flex align-items-center gap-4">
                            <img id="avatarPreview" src="https://cdn.pixabay.com/photo/2015/10/05/22/37/blank-profile-picture-973460_1280.png" alt="Preview" class="avatar-preview">
                            <input type="file" class="form-control" id="avatar" name="avatar" accept="image/*" required>
                        </div>
                    </div>                    
                    <button type="submit" class="btn btn-amazon w-100 py-2 mb-3">Crear cuenta</button>
                </form>
            </div>
        </div>
    </div>
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../JS/RegistroUsuario.js"></script>
</body>
</html>