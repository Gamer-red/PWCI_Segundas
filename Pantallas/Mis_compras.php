<?php
session_start();
require_once '../Config/database.php';

if (!isset($_SESSION['Id_usuario'])) {
    header('Location: Login.php');
    exit();
}

$userId = $_SESSION['Id_usuario'];
$db = Database::getInstance();
$conn = $db->getConnection();

// Obtener todas las compras del usuario con más detalles
$sqlCompras = "SELECT c.Id_compra, c.Fecha_compta, c.Hora_compra, c.Metodo_pago, 
               SUM(t.Subtotal) AS Total
               FROM compras c
               JOIN ticket_compra t ON c.Id_compra = t.Id_compra
               WHERE c.id_usuario = ?
               GROUP BY c.Id_compra
               ORDER BY c.Fecha_compta DESC, c.Hora_compra DESC";
$stmtCompras = $conn->prepare($sqlCompras);
$stmtCompras->execute([$userId]);
$compras = $stmtCompras->fetchAll(PDO::FETCH_ASSOC);

// Obtener detalles de productos para cada compra con categoría y calificación
$comprasConDetalles = [];
foreach ($compras as $compra) {
    $sqlDetalles = "SELECT 
                    t.Id_producto, 
                    t.Nombre, 
                    t.Cantidad, 
                    t.Precio_unitario, 
                    t.Subtotal,
                    cat.Nombre_categoria AS Categoria,
                    (SELECT AVG(Calificacion) FROM calificacion WHERE Id_producto = t.Id_producto) AS Calificacion_promedio,
                    (SELECT Imagen FROM multimedia m WHERE m.Id_producto = t.Id_producto LIMIT 1) AS Imagen,
                    (SELECT COUNT(*) FROM calificacion WHERE Id_producto = t.Id_producto AND Id_usuario = ?) AS yaCalificado
                   FROM ticket_compra t
                   JOIN productos p ON t.Id_producto = p.Id_producto
                   JOIN categorias cat ON p.Id_categoria = cat.Id_categoria
                   WHERE t.Id_compra = ?";
    $stmtDetalles = $conn->prepare($sqlDetalles);
    $stmtDetalles->execute([$userId, $compra['Id_compra']]);
    $detalles = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);
    
    $compra['detalles'] = $detalles;
    $comprasConDetalles[] = $compra;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Compras | TuTiendaOnline</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .purchase-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .purchase-header {
            background-color: #f1f3f5;
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
        }
        .purchase-body {
            padding: 20px;
            background-color: white;
        }
        .product-img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 8px;
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .status-delivered {
            background-color: #d4edda;
            color: #155724;
        }
        .status-shipped {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-processing {
            background-color: #cce5ff;
            color: #004085;
        }
        .empty-state {
            text-align: center;
            padding: 50px 20px;
        }
        .empty-icon {
            font-size: 5rem;
            color: #adb5bd;
            margin-bottom: 20px;
        }
        .accordion-button:not(.collapsed) {
            background-color: rgba(0, 123, 255, 0.05);
            color: #000;
        }
        .accordion-button:focus {
            box-shadow: none;
            border-color: rgba(0, 0, 0, 0.125);
        }
        .method-icon {
            width: 30px;
            height: 30px;
            margin-right: 10px;
        }
        .star-rating {
            font-size: 1rem;
            color: #ffc107;
        }
        .star-rating .far {
            color: #ddd;
        }
        .already-reviewed {
            color: #28a745;
            font-weight: bold;
        }
        .category-badge {
            background-color: #6c757d;
            color: white;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'Navbar.php'; ?>
    
    <div class="container py-5">
        <div class="row mb-4">
            <div class="col">
                <h2><i class="fas fa-shopping-bag me-2"></i>Mis Compras</h2>
                <p class="text-muted">Historial de todas tus compras realizadas</p>
            </div>
        </div>
        
        <?php if (empty($comprasConDetalles)): ?>
            <div class="card empty-state">
                <div class="card-body">
                    <div class="empty-icon">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <h3 class="mb-3">No tienes compras registradas</h3>
                    <p class="text-muted mb-4">Cuando realices una compra, aparecerá en esta sección</p>
                    <a href="Pagina_principal.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left me-2"></i>Ir a comprar
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="accordion" id="comprasAccordion">
                <?php foreach ($comprasConDetalles as $index => $compra): ?>
                    <div class="card purchase-card mb-3">
                        <div class="purchase-header d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1">Orden #<?php echo $compra['Id_compra']; ?></h5>
                                <small class="text-muted">
                                    <?php echo date('d/m/Y', strtotime($compra['Fecha_compta'])); ?> 
                                    a las <?php echo date('H:i', strtotime($compra['Hora_compra'])); ?>
                                </small>
                            </div>
                            <div>
                                <span class="badge status-badge status-delivered">Entregado</span>
                            </div>
                        </div>
                        
                        <div class="purchase-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <h6><i class="fas fa-credit-card me-2"></i>Método de pago</h6>
                                    <div class="d-flex align-items-center mt-2">
                                        <?php if ($compra['Metodo_pago'] == 'PayPal'): ?>
                                            <img src="https://www.paypalobjects.com/webstatic/mktg/logo/pp_cc_mark_37x23.jpg" 
                                                 class="method-icon" alt="PayPal">
                                            <span>PayPal</span>
                                        <?php else: ?>
                                            <i class="far fa-credit-card method-icon"></i>
                                            <span>Tarjeta de crédito/débito</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="fas fa-dollar-sign me-2"></i>Total de la compra</h6>
                                    <h4 class="mt-2 text-success">$<?php echo number_format($compra['Total'], 2); ?></h4>
                                </div>
                            </div>
                            
                            <h6><i class="fas fa-boxes me-2"></i>Productos comprados</h6>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Categoría</th>
                                            <th>Calificación</th>
                                            <th>Precio unitario</th>
                                            <th>Cantidad</th>
                                            <th>Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($compra['detalles'] as $detalle): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if ($detalle['Imagen']): ?>
                                                        <img src="data:image/jpeg;base64,<?php echo base64_encode($detalle['Imagen']); ?>" 
                                                             class="product-img me-3" alt="<?php echo htmlspecialchars($detalle['Nombre']); ?>">
                                                    <?php else: ?>
                                                        <img src="https://via.placeholder.com/70?text=Sin+imagen" 
                                                             class="product-img me-3" alt="Producto sin imagen">
                                                    <?php endif; ?>
                                                    <span><?php echo htmlspecialchars($detalle['Nombre']); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge category-badge"><?php echo htmlspecialchars($detalle['Categoria']); ?></span>
                                            </td>
                                            <td>
                                                <div class="star-rating">
                                                    <?php
                                                    $rating = round($detalle['Calificacion_promedio'] ?? 0, 1);
                                                    $fullStars = floor($rating);
                                                    $halfStar = ($rating - $fullStars) >= 0.5;
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
                                                </div>
                                            </td>
                                            <td>$<?php echo number_format($detalle['Precio_unitario'], 2); ?></td>
                                            <td><?php echo $detalle['Cantidad']; ?></td>
                                            <td>$<?php echo number_format($detalle['Subtotal'], 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="d-flex justify-content-end mt-3">
                                <?php foreach ($compra['detalles'] as $detalle): ?>
                                    <?php if (!$detalle['yaCalificado']): ?>
                                        <button class="btn btn-outline-secondary me-2" data-bs-toggle="modal" data-bs-target="#reviewModal<?php echo $detalle['Id_producto']; ?>">
                                            <i class="fas fa-comment me-2"></i>Dejar reseña
                                        </button>
                                    <?php else: ?>
                                        <span class="text-success align-self-center"><i class="fas fa-check-circle me-2"></i>Ya calificaste este producto</span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Modales para calificar productos -->
                    <?php foreach ($compra['detalles'] as $detalle): ?>
                        <?php if (!$detalle['yaCalificado']): ?>
                            <div class="modal fade" id="reviewModal<?php echo $detalle['Id_producto']; ?>" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="reviewModalLabel">Calificar producto: <?php echo htmlspecialchars($detalle['Nombre']); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <form action="Calificar_Producto.php" method="post">
                                                <input type="hidden" name="product_id" value="<?php echo $detalle['Id_producto']; ?>">
                                                <input type="hidden" name="compra_id" value="<?php echo $compra['Id_compra']; ?>">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Calificación:</label>
                                                    <div class="star-rating">
                                                        <i class="far fa-star" data-rating="1"></i>
                                                        <i class="far fa-star" data-rating="2"></i>
                                                        <i class="far fa-star" data-rating="3"></i>
                                                        <i class="far fa-star" data-rating="4"></i>
                                                        <i class="far fa-star" data-rating="5"></i>
                                                        <input type="hidden" name="rating" id="rating-value-<?php echo $detalle['Id_producto']; ?>" required>
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="comment-<?php echo $detalle['Id_producto']; ?>" class="form-label">Comentario:</label>
                                                    <textarea class="form-control" id="comment-<?php echo $detalle['Id_producto']; ?>" name="comment" rows="3" required minlength="10"></textarea>
                                                    <small class="text-muted">Mínimo 10 caracteres</small>
                                                </div>
                                                
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-primary">Enviar calificación</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Script para manejar interacciones
        document.addEventListener('DOMContentLoaded', function() {
            // Configurar calificación por estrellas para cada modal
            document.querySelectorAll('.star-rating').forEach(ratingContainer => {
                const stars = ratingContainer.querySelectorAll('i');
                const ratingInput = ratingContainer.parentElement.querySelector('input[name="rating"]');
                
                stars.forEach(star => {
                    star.addEventListener('click', function() {
                        const rating = this.getAttribute('data-rating');
                        ratingInput.value = rating;
                        
                        // Actualizar visualización de estrellas
                        stars.forEach((s, index) => {
                            if (index < rating) {
                                s.classList.remove('far');
                                s.classList.add('fas');
                            } else {
                                s.classList.remove('fas');
                                s.classList.add('far');
                            }
                        });
                    });
                });
            });
        });
    </script>
</body>
</html>