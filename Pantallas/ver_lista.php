<?php
session_start();
require_once '../Config/database.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['Id_usuario'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: Listas_publicas.php");
    exit();
}

$listaId = $_GET['id'];
$db = Database::getInstance();
$conn = $db->getConnection();

// Obtener información de la lista
$sqlLista = "SELECT l.*, u.Nombre_del_usuario as creador 
             FROM lista l
             JOIN usuarios u ON l.Id_usuario = u.Id_usuario
             WHERE l.Id_lista = ?";
$stmtLista = $conn->prepare($sqlLista);
$stmtLista->execute([$listaId]);
$lista = $stmtLista->fetch(PDO::FETCH_ASSOC);

if (!$lista) {
    header("Location: Listas_publicas.php");
    exit();
}

// Obtener productos de la lista
$sqlProductos = "SELECT pdl.*, p.Nombre, p.Precio, p.Cotizar, 
                (SELECT m.Imagen FROM multimedia m WHERE m.Id_producto = p.Id_producto LIMIT 1) as Imagen
                FROM productos_de_lista pdl
                JOIN productos p ON pdl.Id_producto = p.Id_producto
                WHERE pdl.Id_lista = ? AND p.autorizado = 1";
$stmtProductos = $conn->prepare($sqlProductos);
$stmtProductos->execute([$listaId]);
$productos = $stmtProductos->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($lista['Nombre_lista']); ?> | TuTiendaOnline</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .product-img {
            height: 150px;
            object-fit: contain;
        }
        .lista-header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .img-placeholder {
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            height: 200px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'Navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="lista-header">
            <div class="row">
                <div class="col-md-2">
                    <?php if (!empty($lista['Imagen_lista'])): ?>
                        <img src="data:image/jpeg;base64,<?php echo base64_encode($lista['Imagen_lista']); ?>" 
                             class="img-fluid rounded" alt="<?php echo htmlspecialchars($lista['Nombre_lista']); ?>">
                    <?php else: ?>
                        <div class="img-placeholder">
                            <i class="fas fa-list-alt fa-4x"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-10">
                    <h1><?php echo htmlspecialchars($lista['Nombre_lista']); ?></h1>
                    <p class="text-muted">Creada por: <?php echo htmlspecialchars($lista['creador']); ?></p>
                    <?php if (!empty($lista['Descripcion_lista'])): ?>
                        <p><?php echo htmlspecialchars($lista['Descripcion_lista']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <h3 class="mb-3">Productos en esta lista</h3>
        
        <div class="row">
            <?php if (count($productos) > 0): ?>
                <?php foreach ($productos as $producto): ?>
                    <div class="col-md-3 mb-4">
                        <div class="card h-100">
                            <?php if (!empty($producto['Imagen'])): ?>
                                <img src="data:image/jpeg;base64,<?php echo base64_encode($producto['Imagen']); ?>" 
                                     class="card-img-top product-img" alt="<?php echo htmlspecialchars($producto['Nombre']); ?>">
                            <?php else: ?>
                                <img src="https://via.placeholder.com/200x200?text=Sin+imagen" 
                                     class="card-img-top product-img" alt="Producto sin imagen">
                            <?php endif; ?>
                            
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($producto['Nombre']); ?></h5>
                                
                                <?php if ($producto['Cotizar']): ?>
                                    <p class="text-muted">Precio a cotizar</p>
                                <?php else: ?>
                                    <p class="price">$<?php echo number_format($producto['Precio'], 2); ?></p>
                                <?php endif; ?>
                                
                                
                            </div>
                            
                            <div class="card-footer bg-white">
                                <a href="detalle_producto.php?id=<?php echo $producto['Id_producto']; ?>" class="btn btn-sm btn-outline-primary w-100">
                                    Ver producto
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        Esta lista no contiene productos o los productos no están disponibles actualmente.
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="mt-4">
            <a href="Listas_publicas.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Volver a listas públicas
            </a>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>