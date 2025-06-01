<?php
session_start();
require_once '../Config/database.php';

// Obtener la instancia de la base de datos
$database = Database::getInstance();
$conn = $database->getConnection();

// Verificar si hay una búsqueda
if (!isset($_GET['q']) || empty($_GET['q'])) {
    header("Location: index.php");
    exit();
}

$searchTerm = '%' . $_GET['q'] . '%';
$currentUserId = isset($_SESSION['Id_usuario']) ? $_SESSION['Id_usuario'] : 0;

try {
    // Buscar productos
    $sqlProducts = "SELECT p.Id_producto, p.Nombre, p.Precio, u.Nombre_del_usuario as Vendedor 
                    FROM productos p
                    JOIN usuarios u ON p.Id_usuario = u.Id_usuario
                    WHERE p.Nombre LIKE ? AND p.autorizado = 1
                    LIMIT 10";
    $stmtProducts = $conn->prepare($sqlProducts);
    $stmtProducts->execute([$searchTerm]);
    $products = $stmtProducts->fetchAll(PDO::FETCH_ASSOC);

    // Buscar usuarios (excluyendo al usuario actual)
    $sqlUsers = "SELECT Id_usuario, Nombre_del_usuario, Avatar 
                 FROM usuarios 
                 WHERE (Nombre_del_usuario LIKE ? OR Nombre LIKE ? OR Apellido_paterno LIKE ? OR Apellido_materno LIKE ?)
                 AND Id_usuario != ?
                 LIMIT 10";
    $stmtUsers = $conn->prepare($sqlUsers);
    $stmtUsers->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $currentUserId]);
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Manejar errores de base de datos
    die("Error en la consulta: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultados de búsqueda</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .search-result-card {
            transition: transform 0.2s;
            margin-bottom: 15px;
        }
        .search-result-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .search-section {
            margin-bottom: 30px;
        }
        .search-header {
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'Navbar.php'; ?>
    
    <div class="container mt-4">
        <h2>Resultados de búsqueda para: "<?php echo htmlspecialchars($_GET['q']); ?>"</h2>
        
        <!-- Resultados de productos -->
        <div class="search-section">
            <div class="search-header d-flex justify-content-between align-items-center">
                <h4>Productos</h4>
                <span class="badge bg-primary"><?php echo count($products); ?> resultados</span>
            </div>
            
            <?php if (count($products) > 0): ?>
                <div class="row">
                    <?php foreach ($products as $product): ?>
                        <div class="col-md-4">
                            <div class="card search-result-card">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($product['Nombre']); ?></h5>
                                    <p class="card-text text-muted">Vendedor: <?php echo htmlspecialchars($product['Vendedor']); ?></p>
                                    <p class="card-text text-success">$<?php echo number_format($product['Precio'], 2); ?></p>
                                    <a href="detalle_producto.php?id=<?php echo $product['Id_producto']; ?>" class="btn btn-primary btn-sm">Ver producto</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No se encontraron productos.</div>
            <?php endif; ?>
        </div>
        
        <!-- Resultados de usuarios -->
        <div class="search-section">
            <div class="search-header d-flex justify-content-between align-items-center">
                <h4>Usuarios</h4>
                <span class="badge bg-primary"><?php echo count($users); ?> resultados</span>
            </div>
            
            <?php if (count($users) > 0): ?>
                <div class="row">
                    <?php foreach ($users as $user): ?>
                        <div class="col-md-4">
                            <div class="card search-result-card">
                                <div class="card-body d-flex align-items-center">
                                    <?php if ($user['Avatar']): ?>
                                        <img src="data:image/jpeg;base64,<?php echo base64_encode($user['Avatar']); ?>" class="rounded-circle me-3" width="50" height="50" alt="Avatar">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-secondary me-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                            <i class="fas fa-user text-white"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($user['Nombre_del_usuario']); ?></h5>
                                        <a href="Ver_perfil.php?id=<?php echo $user['Id_usuario']; ?>" class="btn btn-outline-primary btn-sm mt-2">Ver perfil</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No se encontraron usuarios.</div>
            <?php endif; ?>
        </div>
    </div>
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>