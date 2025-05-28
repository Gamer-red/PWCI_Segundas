<?php
// Iniciar sesión (si no está iniciada)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../Config/database.php';

// Determinar el rol del usuario si está logueado
$esVendedor = false;
$esAdmin = false;
$esComprador = false;

if (isset($_SESSION['Id_usuario'])) {
    $userId = $_SESSION['Id_usuario'];
    $sql = "SELECT r.Id_rol FROM usuarios u 
            JOIN rol r ON u.Id_rol = r.Id_rol 
            WHERE u.Id_usuario = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $idRol = $result['Id_rol'];
        $esComprador = ($idRol == 1); // Asumiendo que 1 es el ID para compradores
        $esVendedor = ($idRol == 2); // Asumiendo que 2 es el ID para vendedores
        $esAdmin = ($idRol == 3); // Asumiendo que 3 es el ID para administradores
    }
}
$conn = null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .navbar-custom {
            background-color: #131921;
            padding: 0.5rem 1rem;
        }
        
        .navbar-custom .navbar-brand {
            color: #FF9900;
            font-weight: bold;
            font-size: 1.5rem;
        }
        
        .navbar-custom .nav-link {
            color: white;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .navbar-custom .nav-link:hover {
            background-color: #3b4148;
            color: #FFD814;
        }
        
        .navbar-custom .dropdown-menu {
            background-color: #131921;
            border: 1px solid #3b4148;
        }
        
        .navbar-custom .dropdown-item {
            color: white;
        }
        
        .navbar-custom .dropdown-item:hover {
            background-color: #232f3e;
            color: #FFD814;
        }
        
        .search-box {
            width: 100%;
            max-width: 600px;
        }
        
        .search-btn {
            background-color: #FFD814;
            color: #131921;
            border-color: #FCD200;
        }
        
        .search-btn:hover {
            background-color: #F7CA00;
            border-color: #F2C200;
        }
        
        .cart-count {
            background-color: #FF9900;
            color: white;
            border-radius: 50%;
            padding: 0 5px;
            font-size: 0.7rem;
            position: relative;
            top: -10px;
            left: -5px;
        }
        
        .user-greeting {
            color: #FFD814;
            font-size: 0.8rem;
            margin-right: 10px;
        }
        
        .admin-badge {
            background-color: #dc3545;
            color: white;
            font-size: 0.6rem;
            border-radius: 3px;
            padding: 2px 5px;
            margin-left: 5px;
            vertical-align: middle;
        }
        
        .vendedor-badge {
            background-color: #28a745;
            color: white;
            font-size: 0.6rem;
            border-radius: 3px;
            padding: 2px 5px;
            margin-left: 5px;
            vertical-align: middle;
        }
        
        @media (max-width: 992px) {
            .navbar-custom .nav-item {
                margin-bottom: 5px;
            }
            
            .search-box {
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container-fluid">
            <!-- Logo -->
            <a class="navbar-brand" href="Pagina_principal.php">
                <i class="fas fa-shopping-bag me-2"></i>TuTiendaOnline
            </a>
            
            <!-- Botón para móviles -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Contenido del navbar -->
            <div class="collapse navbar-collapse" id="navbarContent">
                <!-- Barra de búsqueda -->
               <form class="d-flex search-box mx-lg-3 my-2 my-lg-0" action="busqueda.php" method="get">
                    <input class="form-control me-2" type="search" name="q" placeholder="Buscar productos o usuarios..." aria-label="Search" required>
                    <button class="btn search-btn" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
                
                <!-- Menú de navegación -->
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <?php if(isset($_SESSION['Id_usuario'])): ?>
                        <!-- Saludo al usuario -->
                        <li class="nav-item d-none d-lg-block">
                            <span class="user-greeting">
                                Hola, <?php echo htmlspecialchars($_SESSION['Nombre_del_usuario'] ?? 'Usuario'); ?>
                                <?php if($esAdmin): ?>
                                    <span class="admin-badge">ADMIN</span>
                                <?php elseif($esVendedor): ?>
                                    <span class="vendedor-badge">VENDEDOR</span>
                                <?php endif; ?>
                            </span>
                        </li>
                        <?php if($esAdmin): ?>
                            <!-- Menú para ADMINISTRADORES -->
                            <li class="nav-item">
                                <a class="nav-link" href="admin_panel.php">
                                    <i class="fas fa-user-shield me-1"></i> Panel Admin
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php if($esVendedor): ?>
                            <!-- Menú para VENDEDORES (rol 2) -->
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="vendedorDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-store me-1"></i> Mi Tienda
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="vendedorDropdown">
                                    <li><a class="dropdown-item" href="Perfil_usuario.php"><i class="fas fa-user me-2"></i>Perfil</a></li>
                                    <li><a class="dropdown-item" href="mis_productos.php"><i class="fas fa-boxes me-2"></i>Mis Productos</a></li>
                                    <li><a class="dropdown-item" href="ventas.php"><i class="fas fa-chart-line me-2"></i>Ventas</a></li>
                                    <li><a class="dropdown-item" href="Inventario.php"><i class="fas fa-warehouse me-2"></i>Inventario</a></li>
                                    <li><a class="dropdown-item" href="Nueva_categoria.php"><i class="fas fa-plus-circle me-2"></i>Nueva Categoría</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="Chat.php"><i class="fas fa-comments me-2"></i>Chat</a></li>
                                </ul>
                            </li>
                        <?php elseif($esComprador): ?>
                            <!-- Menú para COMPRADORES (rol 1) -->
                            <li class="nav-item">
                                <a class="nav-link" href="compras.php">
                                    <i class="fas fa-shopping-bag me-1"></i> Mis Compras
                                </a>
                            </li>
                            
                            <li class="nav-item">
                                <a class="nav-link" href="Listas.php">
                                    <i class="fas fa-list me-1"></i> Mis Listas
                                </a>
                            </li>

                            <li class="nav-item">
                               <a class="nav-link" href="Detalle_carrito.php">
                                    <i class="fas fa-shopping-cart"></i> Carrito
                                    <?php if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0): ?>
                                        <span id="cart-count" class="badge bg-danger"><?php echo count($_SESSION['cart']); ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <!-- Elementos comunes a todos los usuarios -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-cog me-1"></i> Cuenta
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="Perfil_usuario.php"><i class="fas fa-user me-2"></i>Perfil</a></li>
                                <li><a class="dropdown-item" href="configuracion.php"><i class="fas fa-cog me-2"></i>Configuración</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <!-- Menú para usuarios NO autenticados -->
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">
                                <i class="fas fa-sign-in-alt me-1"></i> Iniciar sesión
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="registro.php">
                                <i class="fas fa-user-plus me-1"></i> Registrarse
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
</body>
</html>