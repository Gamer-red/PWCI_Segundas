<?php
session_start();
require_once '../Config/database.php';

// Obtener la instancia de la base de datos
$database = Database::getInstance();
$conn = $database->getConnection();

// Verificar si se proporcionó un ID de usuario
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$userId = $_GET['id'];
$currentUserId = isset($_SESSION['Id_usuario']) ? $_SESSION['Id_usuario'] : 0;
$isCurrentUser = ($userId == $currentUserId);

try {
    // Obtener información del usuario
    $sqlUser = "SELECT u.*, r.Nombre_rol as Rol 
                FROM usuarios u
                JOIN rol r ON u.Id_rol = r.Id_rol
                WHERE u.Id_usuario = ? AND (u.perfil_publico = 1 OR ? = 1)";
    $stmtUser = $conn->prepare($sqlUser);
    $stmtUser->execute([$userId, $isCurrentUser]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header("Location: index.php?error=perfil_no_encontrado");
        exit();
    }

    // Si el usuario es vendedor, obtener sus productos
    $products = [];
    if ($user['Id_rol'] == 2) { // Asumiendo que 2 es el ID para vendedores
        $sqlProducts = "SELECT p.Id_producto, p.Nombre, p.Precio, p.Cantidad 
                        FROM productos p
                        WHERE p.Id_usuario = ? AND p.autorizado = 1
                        ORDER BY p.Nombre";
        $stmtProducts = $conn->prepare($sqlProducts);
        $stmtProducts->execute([$userId]);
        $products = $stmtProducts->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    die("Error en la consulta: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil de <?php echo htmlspecialchars($user['Nombre_del_usuario']); ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-header {
            background-color: #f8f9fa;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        .avatar-container {
            width: 150px;
            height: 150px;
            margin: 0 auto;
            position: relative;
        }
        .avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            border: 5px solid white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .badge-role {
            font-size: 0.8rem;
            padding: 0.35em 0.65em;
        }
        .product-card {
            transition: transform 0.2s;
            margin-bottom: 20px;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .section-title {
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'Navbar.php'; ?>
    
    <div class="container mt-4">
        <!-- Encabezado del perfil -->
        <div class="profile-header text-center">
            <div class="avatar-container mb-3">
                <?php if ($user['Avatar']): ?>
                    <img src="data:image/jpeg;base64,<?php echo base64_encode($user['Avatar']); ?>" class="avatar-img" alt="Avatar">
                <?php else: ?>
                    <div class="avatar-img bg-secondary d-flex align-items-center justify-content-center">
                        <i class="fas fa-user text-white fa-4x"></i>
                    </div>
                <?php endif; ?>
            </div>
            
            <h2><?php echo htmlspecialchars($user['Nombre_del_usuario']); ?></h2>
            
            <span class="badge 
                <?php echo ($user['Id_rol'] == 3) ? 'bg-danger' : (($user['Id_rol'] == 2) ? 'bg-success' : 'bg-primary'); ?> 
                badge-role mb-3">
                <?php echo htmlspecialchars($user['Rol']); ?>
            </span>
            
            <?php if ($user['Nombre'] || $user['Apellido_paterno']): ?>
                <p class="lead"><?php echo htmlspecialchars($user['Nombre'] . ' ' . $user['Apellido_paterno']); ?></p>
            <?php endif; ?>
            
            <p class="text-muted">
                <i class="fas fa-calendar-alt me-2"></i>
                Miembro desde <?php echo date('d/m/Y', strtotime($user['Fecha_ingreso'])); ?>
            </p>
        </div>
        
        <!-- Sección de información básica -->
        <div class="mb-5">
            <h4 class="section-title">Información</h4>
            <div class="row">
                <div class="col-md-6">
                    <p><strong><i class="fas fa-envelope me-2"></i> Correo:</strong> 
                        <?php echo htmlspecialchars($user['Correo']); ?></p>
                </div>
                <?php if ($user['Fecha_nacimiento']): ?>
                    <div class="col-md-6">
                        <p><strong><i class="fas fa-birthday-cake me-2"></i> Fecha de nacimiento:</strong> 
                            <?php echo date('d/m/Y', strtotime($user['Fecha_nacimiento'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Sección de productos (solo para vendedores) -->
        <?php if ($user['Id_rol'] == 2 && count($products) > 0): ?>
            <div class="mb-5">
                <h4 class="section-title">Productos de este vendedor</h4>
                <div class="row">
                    <?php foreach ($products as $product): ?>
                        <div class="col-md-4">
                            <div class="card product-card">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($product['Nombre']); ?></h5>
                                    <p class="card-text text-success">$<?php echo number_format($product['Precio'], 2); ?></p>
                                    <p class="card-text text-muted">Disponibles: <?php echo $product['Cantidad']; ?></p>
                                    <a href="detalle_producto.php?id=<?php echo $product['Id_producto']; ?>" class="btn btn-primary btn-sm">Ver producto</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php elseif ($user['Id_rol'] == 2): ?>
            <div class="alert alert-info">Este vendedor no tiene productos publicados.</div>
        <?php endif; ?>
    </div>
    
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>