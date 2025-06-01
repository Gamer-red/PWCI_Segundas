<?php
session_start();
require_once '../Config/database.php';
// Obtener productos aprobados
$db = Database::getInstance();
$conn = $db->getConnection();

// Consulta modificada para obtener productos con sus calificaciones
$sql = "SELECT p.*, 
        (SELECT m.Imagen FROM multimedia m WHERE m.Id_producto = p.Id_producto LIMIT 1) as Imagen,
        (SELECT AVG(c.Calificacion) FROM calificacion c WHERE c.Id_producto = p.Id_producto) as promedio_rating,
        (SELECT COUNT(c.Id_calificacion) FROM calificacion c WHERE c.Id_producto = p.Id_producto) as total_ratings
        FROM productos p 
        WHERE p.autorizado = 1
        ORDER BY p.Id_producto DESC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener categorías para el filtro
$sqlCategorias = "SELECT * FROM categorias WHERE autorizado = 1";
$stmtCategorias = $conn->prepare($sqlCategorias);
$stmtCategorias->execute();
$categorias = $stmtCategorias->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos | TuTiendaOnline</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .product-card {
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .product-img {
            height: 200px;
            object-fit: contain;
            padding: 10px;
        }
        .price {
            font-weight: bold;
            color: #B12704;
            font-size: 1.2rem;
        }
        .card-body {
            display: flex;
            flex-direction: column;
        }
        .card-text {
            flex-grow: 1;
        }
        .category-badge {
            background-color: #232F3E;
            color: white;
        }
        .search-container {
            background-color: #f7f7f7;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .empty-state {
            text-align: center;
            padding: 50px;
            color: #666;
        }
        .rating {
            color: #FFA41C;
            margin-bottom: 10px;
        }
        .rating-count {
            font-size: 0.8rem;
            color: #555;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'Navbar.php'; ?>
    
    <div class="container mt-4">
        <!-- Filtros y búsqueda -->
        <div class="search-container">
            <div class="row">
                <div class="col-md-4">
                    <select class="form-select" id="categoryFilter">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?php echo $categoria['Id_categoria']; ?>"
                                <?php echo (isset($_GET['category'])) && $_GET['category'] == $categoria['Id_categoria'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($categoria['Nombre_categoria']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Listado de productos -->
        <div class="row">
            <?php if (count($productos) > 0): ?>
                <?php foreach ($productos as $producto): 
                    $promedio = round($producto['promedio_rating'] ?? 0, 1);
                    $totalRatings = $producto['total_ratings'] ?? 0;
                ?>
                    <div class="col-md-4 col-lg-3 mb-4">
                        <div class="card product-card h-100">
                            <?php if (!empty($producto['Imagen'])): ?>
                                <img src="data:image/jpeg;base64,<?php echo base64_encode($producto['Imagen']); ?>" 
                                     class="card-img-top product-img" alt="<?php echo htmlspecialchars($producto['Nombre']); ?>">
                            <?php else: ?>
                                <img src="https://via.placeholder.com/200x200?text=Sin+imagen" 
                                     class="card-img-top product-img" alt="Producto sin imagen">
                            <?php endif; ?>
                            
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($producto['Nombre']); ?></h5>
                                
                                <!-- Mostrar categoría -->
                                <?php 
                                $categoriaNombre = '';
                                foreach ($categorias as $cat) {
                                    if ($cat['Id_categoria'] == $producto['Id_categoria']) {
                                        $categoriaNombre = $cat['Nombre_categoria'];
                                        break;
                                    }
                                }
                                ?>
                                <span class="badge category-badge mb-2"><?php echo htmlspecialchars($categoriaNombre); ?></span>
                                
                                <!-- Rating -->
                                <div class="rating mb-2">
                                    <?php
                                    $fullStars = floor($promedio);
                                    $halfStar = ($promedio - $fullStars) >= 0.5;
                                    $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
                                    
                                    for ($i = 0; $i < $fullStars; $i++) {
                                        echo '<i class="fas fa-star"></i>';
                                    }
                                    if ($halfStar) {
                                        echo '<i class="fas fa-star-half-alt"></i>';
                                    }
                                    for ($i = 0; $i < $emptyStars; $i++) {
                                        echo '<i class="far fa-star"></i>';
                                    }
                                    ?>
                                    <span class="rating-count">(<?php echo $totalRatings; ?>)</span>
                                </div>
                                
                                <p class="card-text">
                                    <?php 
                                    $descripcion = "Descripción del producto no disponible";
                                    echo htmlspecialchars($descripcion); 
                                    ?>
                                </p>
                                
                                <div class="mt-auto">
                                    <?php if ($producto['Cotizar']): ?>
                                        <p class="price">Precio a cotizar</p>
                                        <a href="detalle_producto.php?id=<?php echo $producto['Id_producto']; ?>" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-comment-dollar"></i> Cotizar
                                        </a>
                                    <?php else: ?>
                                        <p class="price">$<?php echo number_format($producto['Precio'], 2); ?></p>
                                        <div class="d-grid gap-2">
                                            <a href="detalle_producto.php?id=<?php echo $producto['Id_producto']; ?>" class="btn btn-primary">
                                                <i class="fas fa-eye"></i> Ver detalles
                                            </a>
                                            <a href="carrito.php?add=<?php echo $producto['Id_producto']; ?>" class="btn btn-warning">
                                                <i class="fas fa-cart-plus"></i> Añadir al carrito
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="empty-state">
                        <i class="fas fa-box-open fa-4x mb-3"></i>
                        <h3>No hay productos disponibles</h3>
                        <p>Actualmente no hay productos aprobados para mostrar.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Filtro por categoría
        document.getElementById('categoryFilter').addEventListener('change', function() {
            const categoryId = this.value;
            const url = new URL(window.location.href);
            
            if (categoryId) {
                url.searchParams.set('category', categoryId);
            } else {
                url.searchParams.delete('category');
            }
            
            window.location.href = url.toString();
        });
    </script>
</body>
</html>