<?php
// Iniciar sesión
session_start();
require_once '../Config/database.php';

// Procesar el formulario si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Validar credenciales
    $stmt = $conn->prepare("SELECT u.*, r.Id_rol, r.Nombre_rol FROM usuarios u 
                           JOIN rol r ON u.Id_rol = r.Id_rol 
                           WHERE (u.Nombre_del_usuario = ? OR u.Correo = ?)");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && $password === $user['Contrasenia']) {
        // Credenciales válidas, iniciar sesión
        $_SESSION['Id_usuario'] = $user['Id_usuario'];
        $_SESSION['Nombre_del_usuario'] = $user['Nombre_del_usuario'];
        $_SESSION['Id_rol'] = $user['Id_rol']; // Almacenar el ID de rol en sesión
        $_SESSION['Nombre_rol'] = $user['Nombre_rol']; // Almacenar el nombre del rol en sesión
        
        // Determinar redirección según el rol
        switch ($user['Id_rol']) {
            case 1: // Comprador
                $redirect = 'Pagina_principal.php';
                break;
            case 2: // Vendedor
                $redirect = 'ResumenVentas.php';
                break;
            case 3: // Administrador
                $redirect = 'admin_panel.php';
                break;
            default:
                $redirect = 'Pagina_principal.php';
        }
        header('Location: ' . $redirect);
        exit();
    } else {
        $error = "Usuario o contraseña incorrectos";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión | TuTiendaOnline</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../CSS/Estilo_Login.css">
</head>
<body class="d-flex align-items-center min-vh-100">
    <div class="container login-container">
        <div class="logo">
            <h1 class="logo-text">TuTiendaOnline</h1>
        </div>
        <div class="card login-card">
            <div class="card-body p-4">
                <h2 class="h4 mb-4">Iniciar sesión</h2>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="Login.php">
                    <div class="mb-3">
                        <label for="username" class="form-label fw-bold">Nombre de usuario o correo electrónico</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label fw-bold">Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-amazon w-100 mb-3 py-2">Entrar</button>
                    <a href="RegistroUsuarios.php" class="btn btn-outline-secondary w-100 py-2">Registrate</a>
                </form>
            </div>
        </div>
    </div>
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>