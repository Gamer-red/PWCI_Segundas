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
    // Obtener información del usuario (sin filtrar por perfil_publico)
    $sqlUser = "SELECT u.*, r.Nombre_rol as Rol 
                FROM usuarios u
                JOIN rol r ON u.Id_rol = r.Id_rol
                WHERE u.Id_usuario = ?";
    $stmtUser = $conn->prepare($sqlUser);
    $stmtUser->execute([$userId]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header("Location: index.php?error=perfil_no_encontrado");
        exit();
    }

    $canViewFullProfile = ($user['perfil_publico'] == 1 || $isCurrentUser);

    // Obtener productos si es vendedor
    $products = [];
    if ($user['Id_rol'] == 2) {
        $sqlProducts = "SELECT p.Id_producto, p.Nombre, p.Precio, p.Cantidad 
                        FROM productos p
                        WHERE p.Id_usuario = ? AND p.autorizado = 1
                        ORDER BY p.Nombre";
        $stmtProducts = $conn->prepare($sqlProducts);
        $stmtProducts->execute([$userId]);
        $products = $stmtProducts->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener listas públicas del usuario (excluyendo Carrito y Lista de deseos)
    $publicLists = [];
    if ($canViewFullProfile) {
        $sqlLists = "SELECT Id_lista, Nombre_lista, Descripcion_lista, Imagen_lista 
                     FROM lista 
                     WHERE Id_usuario = ? 
                     AND Nombre_lista NOT IN ('Carrito', 'Lista de deseos')
                     ORDER BY Nombre_lista";
        $stmtLists = $conn->prepare($sqlLists);
        $stmtLists->execute([$userId]);
        $publicLists = $stmtLists->fetchAll(PDO::FETCH_ASSOC);
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../CSS/Estilo_Ver_Perfil.css">
    <style>
        .list-card {
            transition: transform 0.2s;
            height: 100%;
        }
        .list-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .list-img-container {
            height: 150px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
        }
        .list-img {
            max-height: 100%;
            max-width: 100%;
            object-fit: contain;
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

        <?php if ($canViewFullProfile): ?>
            <?php if ($user['Nombre'] || $user['Apellido_paterno']): ?>
                <p class="lead"><?php echo htmlspecialchars($user['Nombre'] . ' ' . $user['Apellido_paterno']); ?></p>
            <?php endif; ?>
            <p class="text-muted">
                <i class="fas fa-calendar-alt me-2"></i>
                Miembro desde <?php echo date('d/m/Y', strtotime($user['Fecha_ingreso'])); ?>
            </p>
        <?php else: ?>
            <p class="text-muted"><em>Este perfil es privado. Solo se muestra información pública.</em></p>
        <?php endif; ?>
    </div>

    <!-- Información adicional -->
    <?php if ($canViewFullProfile): ?>
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
    <?php endif; ?>

    <!-- Listas públicas del usuario -->
    <?php if ($canViewFullProfile && !empty($publicLists)): ?>
        <div class="mb-5">
            <h4 class="section-title">Listas públicas</h4>
            <div class="row">
                <?php foreach ($publicLists as $list): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card list-card">
                            <div class="list-img-container">
                                <?php if ($list['Imagen_lista']): ?>
                                    <img src="data:image/jpeg;base64,<?php echo base64_encode($list['Imagen_lista']); ?>" class="list-img" alt="Imagen de lista">
                                <?php else: ?>
                                    <i class="fas fa-list-alt fa-3x text-muted"></i>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($list['Nombre_lista']); ?></h5>
                                <?php if ($list['Descripcion_lista']): ?>
                                    <p class="card-text text-muted"><?php echo htmlspecialchars($list['Descripcion_lista']); ?></p>
                                <?php endif; ?>
                                <a href="ver_lista.php?id=<?php echo $list['Id_lista']; ?>" class="btn btn-outline-primary btn-sm">Ver lista</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php elseif ($canViewFullProfile): ?>
        <div class="alert alert-info">Este usuario no tiene listas públicas.</div>
    <?php endif; ?>

    <!-- Productos del vendedor -->
    <?php if ($user['Id_rol'] == 2): ?>
        <?php if ($canViewFullProfile && count($products) > 0): ?>
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
        <?php elseif ($canViewFullProfile): ?>
            <div class="alert alert-info">Este vendedor no tiene productos publicados.</div>
        <?php else: ?>
            <div class="alert alert-info">Este usuario es un vendedor, pero su perfil es privado.</div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>