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

                <div id="error-message" class="alert alert-danger d-none"></div>
                <div id="success-message" class="alert alert-success d-none"></div>

                <form id="loginForm">
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

    <!-- JavaScript para consumir la API -->
    <script>
    document.getElementById('loginForm').addEventListener('submit', async function (e) {
        e.preventDefault(); // Evita envío clásico del formulario

        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value.trim();
        const errorDiv = document.getElementById('error-message');

        // Limpiar errores anteriores
        errorDiv.classList.add('d-none');
        errorDiv.textContent = '';

        try {
            const response = await fetch('../api/Login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ username, password })
            });

            const result = await response.json();

            if (result.success) {
    const successDiv = document.getElementById('success-message');
    successDiv.textContent = "Inicio de sesión exitoso. Redirigiendo...";
    successDiv.classList.remove('d-none');

    // Opcional: deshabilita el botón para evitar doble envío
    document.querySelector('button[type="submit"]').disabled = true;

    // Esperar 2 segundos antes de redirigir
    setTimeout(() => {
        switch (result.data.Id_rol) {
            case "1":
                window.location.href = "Pagina_principal.php";
                break;
            case "2":
                window.location.href = "ResumenVentas.php";
                break;
            case "3":
                window.location.href = "Admin_panel.php";
                break;
            default:
                window.location.href = "Pagina_principal.php";
                    }
                }, 8000); // 2 segundos
            }else {
                errorDiv.textContent = result.message || "Error desconocido";
                errorDiv.classList.remove('d-none');
            }
        } catch (error) {
            errorDiv.textContent = "Error al conectar con el servidor.";
            errorDiv.classList.remove('d-none');
            console.error(error);
        }
    });
    </script>
</body>
</html>
