<?php
session_start();
require_once '../Config/database.php';

// Obtener la instancia de la base de datos
$database = Database::getInstance();
$conn = $database->getConnection();

// Verificar si se proporcionó un ID de lista
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$listId = $_GET['id'];
$currentUserId = isset($_SESSION['Id_usuario']) ? $_SESSION['Id_usuario'] : 0;

try {
    // Obtener información de la lista
    $sqlList = "SELECT l.*, u.Nombre_del_usuario, u.Id_usuario as list_owner_id
                FROM lista l
                JOIN usuarios u ON l.Id_usuario = u.Id_usuario
                WHERE l.Id_lista = ?";
    $stmtList = $conn->prepare($sqlList);
    $stmtList->execute([$listId]);
    $list = $stmtList->fetch(PDO::FETCH_ASSOC);

    if (!$list) {
        header("Location: index.php?error=lista_no_encontrada");
        exit();
    }

    // Verificar si la lista es pública (no es Carrito ni Lista de deseos)
    $isPublicList = !in_array($list['Nombre_lista'], ['Carrito', 'Lista de deseos']);
    $isListOwner = ($currentUserId == $list['list_owner_id']);
    $canViewList = ($isPublicList || $isListOwner);

    if (!$canViewList) {
        header("Location: index.php?error=acceso_denegado");
        exit();
    }

    // Obtener productos de la lista
    $sqlProducts = "SELECT p.Id_producto, p.Nombre, p.Precio, p.Cantidad, 
                           pl.cantidad as lista_cantidad
                    FROM productos p
                    JOIN productos_de_lista pl ON p.Id_producto = pl.Id_producto
                    WHERE pl.Id_lista = ? AND p.autorizado = 1
                    ORDER BY p.Nombre";
    $stmtProducts = $conn->prepare($sqlProducts);
    $stmtProducts->execute([$listId]);
    $products = $stmtProducts->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error en la consulta: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista: <?php echo htmlspecialchars($list['Nombre_lista']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .list-header {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .list-img-container {
            height: 200px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            border-radius: 10px;
        }
        .list-img {
            max-height: 100%;
            max-width: 100%;
            object-fit: contain;
        }
        .product-card {
            transition: transform 0.2s;
            height: 100%;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .product-img-container {
            height: 150px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
        }
        .product-img {
            max-height: 100%;
            max-width: 100%;
            object-fit: contain;
        }
    </style>
</head>
<body>
<?php include 'Navbar.php'; ?>

<div class="container mt-4">
    <!-- Encabezado de la lista -->
    <div class="list-header">
        <div class="row align-items-center">
            <div class="col-md-3">
                <div class="list-img-container mb-3 mb-md-0">
                    <?php if ($list['Imagen_lista']): ?>
                        <img src="data:image/jpeg;base64,<?php echo base64_encode($list['Imagen_lista']); ?>" class="list-img" alt="Imagen de lista">
                    <?php else: ?>
                        <i class="fas fa-list-alt fa-5x text-muted"></i>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-9">
                <h1><?php echo htmlspecialchars($list['Nombre_lista']); ?></h1>
                <p class="text-muted">Creada por <?php echo htmlspecialchars($list['Nombre_del_usuario']); ?></p>
                
                <?php if ($list['Descripcion_lista']): ?>
                    <p class="lead"><?php echo htmlspecialchars($list['Descripcion_lista']); ?></p>
                <?php endif; ?>
                
                <?php if ($isListOwner): ?>
                    <div class="mt-3">
                        <a href="editar_lista.php?id=<?php echo $list['Id_lista']; ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-edit"></i> Editar lista
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Productos en la lista -->
    <div class="mb-5">
        <h3 class="mb-4">Productos en esta lista</h3>
        
        <?php if (!empty($products)): ?>
            <div class="row">
                <?php foreach ($products as $product): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card product-card h-100">
                            <div class="product-img-container">
                                <?php 
                                ?>
                                <i class="fas fa-box-open fa-4x text-muted"></i>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($product['Nombre']); ?></h5>
                                <p class="card-text text-success">$<?php echo number_format($product['Precio'], 2); ?></p>
                                <div class="d-flex justify-content-between">
                                    <a href="detalle_producto.php?id=<?php echo $product['Id_producto']; ?>" class="btn btn-primary btn-sm">
                                        Ver producto
                                    </a>
                                    <?php if ($isListOwner): ?>
                                        <a href="eliminar_de_lista.php?list_id=<?php echo $list['Id_lista']; ?>&product_id=<?php echo $product['Id_producto']; ?>" class="btn btn-outline-danger btn-sm">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">Esta lista no contiene productos.</div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>