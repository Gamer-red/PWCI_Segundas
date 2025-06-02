<?php
// Iniciar sesión y verificar rol de Superadmin
session_start();
require_once '../Config/database.php';

// Verificar si el usuario está logueado y es Superadmin
if (!isset($_SESSION['Id_usuario']) || $_SESSION['Id_rol'] != 4) {
    header('Location: Login.php');
    exit();
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Procesar el formulario
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombreUsuario = trim($_POST['nombre_usuario']);
    $correo = trim($_POST['correo']);
    $contrasenia = trim($_POST['contrasenia']);
    $confirmarContrasenia = trim($_POST['confirmar_contrasenia']);

    // Validaciones básicas
    if (empty($nombreUsuario) || empty($correo) || empty($contrasenia)) {
        $error = 'Todos los campos marcados como obligatorios son requeridos';
    } elseif ($contrasenia !== $confirmarContrasenia) {
        $error = 'Las contraseñas no coinciden';
    } elseif (strlen($contrasenia) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres';
    } else {
        try {
            // Verificar si el correo ya existe
            $sql = "SELECT Id_usuario FROM usuarios WHERE Correo = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$correo]);
            
            if ($stmt->rowCount() > 0) {
                $error = 'El correo electrónico ya está registrado';
            } else {
                // Verificar si el nombre de usuario ya existe
                $sql = "SELECT Id_usuario FROM usuarios WHERE Nombre_del_usuario = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$nombreUsuario]);
                
                if ($stmt->rowCount() > 0) {
                    $error = 'El nombre de usuario ya está en uso';
                } else {
                    // Hash de la contraseña
                    $contraseniaHash = $contrasenia; // Guardar como texto plano (INSEGURO)
                    $sql = "INSERT INTO usuarios (Id_rol, Correo, Nombre_del_usuario, Contrasenia, Fecha_ingreso) 
                            VALUES (3, ?, ?, ?, NOW())";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([
                        $correo,
                        $nombreUsuario,
                        $contraseniaHash
                    ]);
                    
                    $success = 'Administrador registrado exitosamente';
                }
            }
        } catch (PDOException $e) {
            $error = 'Error al registrar el administrador: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Administrador - Panel Superadmin</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background-color: #343a40;
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        .btn-primary {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        .btn-primary:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }
        .form-control:focus {
            border-color: #6c757d;
            box-shadow: 0 0 0 0.25rem rgba(108, 117, 125, 0.25);
        }
        .required-field::after {
            content: " *";
            color: red;
        }
    </style>
</head>
<body>
    <?php include 'Navbar.php'; ?>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card">
                    <div class="card-header text-center">
                        <h4><i class="fas fa-user-shield me-2"></i>Registrar Nuevo Administrador</h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="SuperAdmin.php" onsubmit="return validarFormulario();">
                                <div class="mb-3">
                                    <label for="nombre_usuario" class="form-label fw-bold required-field">Nombre de Usuario</label>
                                    <input type="text" class="form-control" id="nombre_usuario" name="nombre_usuario" 
                                        placeholder="Nombre de usuario para el administrador" 
                                        pattern="[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+" title="Solo letras y espacios" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="correo" class="form-label fw-bold required-field">Correo Electrónico</label>
                                    <input type="email" class="form-control" id="correo" name="correo" 
                                        placeholder="correo@ejemplo.com" required>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <label for="contrasenia" class="form-label fw-bold required-field">Contraseña</label>
                                        <input type="password" class="form-control" id="contrasenia" name="contrasenia" 
                                            placeholder="Mínimo 8 caracteres" minlength="8" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="confirmar_contrasenia" class="form-label fw-bold required-field">Confirmar Contraseña</label>
                                        <input type="password" class="form-control" id="confirmar_contrasenia" name="confirmar_contrasenia" 
                                            placeholder="Repite la contraseña" minlength="8" required>
                                    </div>
                                </div>
                          

                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-user-plus me-2"></i>Registrar Administrador
                                </button>
                                <a href="panel_superadmin.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Volver al Panel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="mt-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Información Importante</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">1. Solo usuarios con rol Superadmin pueden registrar nuevos administradores.</li>
                                <li class="list-group-item">2. Los administradores tendrán acceso al panel de administración.</li>
                                <li class="list-group-item">3. Asegúrate de proporcionar un correo electrónico válido.</li>
                                <li class="list-group-item">4. La contraseña debe tener al menos 8 caracteres.</li>
                                <li class="list-group-item">5. El nombre de usuario debe ser único.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function validarFormulario() {
    const contrasenia = document.getElementById('contrasenia').value;
    const confirmar = document.getElementById('confirmar_contrasenia').value;

    if (contrasenia.length < 8 || confirmar.length < 8) {
        alert("La contraseña debe tener al menos 8 caracteres.");
        return false;
    }

    if (contrasenia !== confirmar) {
        alert("Las contraseñas no coinciden.");
        return false;
    }

    return true;
}
</script>
</body>
</html>