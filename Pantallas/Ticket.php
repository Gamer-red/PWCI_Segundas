<?php
session_start();
require_once '../Config/database.php';

if (!isset($_SESSION['Id_usuario'])) {
    header('Location: Login.php');
    exit();
}

if (!isset($_GET['token']) || !isset($_GET['PayerID'])) {
    header('Location: Mis_compras.php');
    exit();
}
$orderId = $_GET['token'];
$payerId = $_GET['PayerID'];
$userId = $_SESSION['Id_usuario'];

$db = Database::getInstance();
$conn = $db->getConnection();

// Obtener información de la compra
$sqlCompra = "SELECT * FROM compras 
              WHERE (Paypal_order_id = ? OR Paypal_order_id = ?) 
              AND id_usuario = ? 
              LIMIT 1";
$stmtCompra = $conn->prepare($sqlCompra);
$stmtCompra->execute([$orderId, "CARD-".$orderId, $userId]);
$compra = $stmtCompra->fetch(PDO::FETCH_ASSOC);
if (!$compra) {
    echo "No se encontró la compra especificada.";
    exit();
}

$compraId = $compra['Id_compra'];

// Determinar método de pago
$paymentInfo = '';
if (strpos($compra['Paypal_order_id'], 'CARD-') === 0) {
    $paymentInfo = "Tarjeta de crédito (Terminación: " . substr($compra['Paypal_payer_id'], -4) . ")";
} else {
    $paymentInfo = "PayPal (ID: " . htmlspecialchars(substr($payerId, 0, 8)) . "...)";
}

// Obtener productos del ticket
$sqlTicketItems = "SELECT * FROM ticket_compra WHERE Id_compra = ?";
$stmtTicketItems = $conn->prepare($sqlTicketItems);
$stmtTicketItems->execute([$compraId]);
$ticketItems = $stmtTicketItems->fetchAll(PDO::FETCH_ASSOC);

// Calcular total
$total = 0;
foreach ($ticketItems as $item) {
    $total += $item['Subtotal'];
}

// Procesar reseñas si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $productId = $_POST['product_id'];
    $rating = $_POST['rating'];
    $comment = trim($_POST['comment']);
    
    // Validar datos
    if (empty($productId) || empty($rating) || empty($comment)) {
        $_SESSION['review_error'] = "Todos los campos son requeridos";
        header("Location: Ticket.php?token=$orderId&PayerID=$payerId");
        exit();
    }
    
    // Verificar que el producto pertenece a esta compra
    $sqlCheckProduct = "SELECT COUNT(*) FROM ticket_compra 
                       WHERE Id_compra = ? AND Id_producto = ?";
    $stmtCheckProduct = $conn->prepare($sqlCheckProduct);
    $stmtCheckProduct->execute([$compraId, $productId]);
    $productExists = $stmtCheckProduct->fetchColumn() > 0;
    
    if ($productExists && $rating >= 1 && $rating <= 5 && strlen($comment) >= 10) {
        try {
            $conn->beginTransaction();
            
            // Insertar calificación
            $sqlRating = "INSERT INTO calificacion (Id_producto, Id_usuario, Calificacion) 
                         VALUES (?, ?, ?)";
            $stmtRating = $conn->prepare($sqlRating);
            $stmtRating->execute([$productId, $userId, $rating]);
            
            // Insertar comentario
            $sqlComment = "INSERT INTO comentarios (Id_producto, Contenido, Id_usuario, Fecha_Creacion)
                          VALUES (?, ?, ?, CURDATE())";
            $stmtComment = $conn->prepare($sqlComment);
            $stmtComment->execute([$productId, $comment, $userId]);
            
            $conn->commit();
            $_SESSION['review_success'] = "¡Gracias por tu calificación y comentario!";
        } catch (PDOException $e) {
            $conn->rollBack();
            $_SESSION['review_error'] = "Error al procesar tu reseña: " . $e->getMessage();
        }
    } else {
        $_SESSION['review_error'] = "Datos no válidos. La calificación debe ser entre 1-5 y el comentario tener al menos 10 caracteres.";
    }
    
    header("Location: Ticket.php?token=$orderId&PayerID=$payerId");
    exit();
}

// Verificar qué productos ya fueron calificados
$productosCalificar = [];
foreach ($ticketItems as $item) {
    $productId = $item['Id_producto'];
    
    $sqlCheckReview = "SELECT COUNT(*) FROM calificacion 
                      WHERE Id_producto = ? AND Id_usuario = ?";
    $stmtCheckReview = $conn->prepare($sqlCheckReview);
    $stmtCheckReview->execute([$productId, $userId]);
    $alreadyReviewed = $stmtCheckReview->fetchColumn() > 0;
    
    $productosCalificar[$productId] = [
        'Nombre' => $item['Nombre'],
        'alreadyReviewed' => $alreadyReviewed
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compra Exitosa | TuTiendaOnline</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .confirmation-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .confirmation-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .checkmark-circle {
            font-size: 5rem;
            margin-bottom: 1rem;
            animation: bounce 1s;
        }
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
            40% {transform: translateY(-30px);}
            60% {transform: translateY(-15px);}
        }
        .order-details {
            padding: 2rem;
            background-color: white;
        }
        .detail-item {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        .product-img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        .total-box {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }
        .btn-continue {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            padding: 10px 25px;
            font-weight: 600;
        }
        .btn-continue:hover {
            background: linear-gradient(135deg, #218838, #1aa179);
        }
        .rating-section {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 2rem;
        }
        .star-rating {
            font-size: 1.5rem;
            color: #ffc107;
            cursor: pointer;
        }
        .star-rating .far {
            color: #ddd;
        }
        .review-form {
            margin-top: 1rem;
        }
        .already-reviewed {
            color: #28a745;
            font-weight: bold;
        }
        textarea {
        resize: none;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'Navbar.php'; ?>
    
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card confirmation-card mb-4">
                    <div class="confirmation-header">
                        <div class="checkmark-circle">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h2 class="mb-3">¡Gracias por tu compra!</h2>
                        <p class="mb-0">Tu pedido #<?php echo $compraId; ?> ha sido procesado exitosamente</p>
                    </div>
                    
                    <div class="order-details">
                        <h4 class="mb-4"><i class="fas fa-receipt me-2"></i>Resumen de tu compra</h4>
                        
                        <div class="detail-item">
                            <h5><i class="fas fa-credit-card me-2"></i>Método de pago</h5>
                            <p class="text-muted"><?php echo $paymentInfo; ?></p>
                        </div>
                        
                        <div class="detail-item">
                            <h5><i class="fas fa-calendar-alt me-2"></i>Fecha de compra</h5>
                            <p class="text-muted"><?php echo date('d/m/Y', strtotime($compra['Fecha_compta'])); ?> a las <?php echo date('H:i', strtotime($compra['Hora_compra'])); ?></p>
                        </div>
                        
                        <h5 class="mt-4 mb-3"><i class="fas fa-box-open me-2"></i>Productos comprados</h5>
                        
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Cantidad</th>
                                        <th>Precio unitario</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ticketItems as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['Nombre']); ?></td>
                                        <td><?php echo $item['Cantidad']; ?></td>
                                        <td>$<?php echo number_format($item['Precio_unitario'], 2); ?></td>
                                        <td>$<?php echo number_format($item['Subtotal'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="total-box text-end">
                            <h4>Total: <span class="text-success">$<?php echo number_format($total, 2); ?></span></h4>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-star me-2"></i>Califica tus productos</h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['review_success'])): ?>
                            <div class="alert alert-success">
                                <?php echo $_SESSION['review_success']; unset($_SESSION['review_success']); ?>
                            </div>
                        <?php elseif (isset($_SESSION['review_error'])): ?>
                            <div class="alert alert-danger">
                                <?php echo $_SESSION['review_error']; unset($_SESSION['review_error']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php foreach ($productosCalificar as $productId => $product): ?>
                            <div class="rating-section mb-4">
                                <h6><?php echo htmlspecialchars($product['Nombre']); ?></h6>
                                
                                <?php if ($product['alreadyReviewed']): ?>
                                    <p class="already-reviewed">
                                        <i class="fas fa-check-circle me-2"></i>Ya calificaste este producto
                                    </p>
                                <?php else: ?>
                                    <form method="post" class="review-form">
                                        <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Calificación:</label>
                                            <div class="star-rating">
                                                <i class="far fa-star" data-rating="1"></i>
                                                <i class="far fa-star" data-rating="2"></i>
                                                <i class="far fa-star" data-rating="3"></i>
                                                <i class="far fa-star" data-rating="4"></i>
                                                <i class="far fa-star" data-rating="5"></i>
                                                <input type="hidden" name="rating" id="rating-value-<?php echo $productId; ?>" required>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="comment-<?php echo $productId; ?>" class="form-label">Comentario:</label>
                                            <textarea class="form-control" id="comment-<?php echo $productId; ?>" name="comment" rows="3" required minlength="10" style="resize: none;"></textarea>
                                            <small class="text-muted">Mínimo 10 caracteres</small>
                                        </div>
                                        
                                        <button type="submit" name="submit_review" class="btn btn-primary">
                                            <i class="fas fa-paper-plane me-2"></i>Enviar calificación
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="d-grid gap-2 mt-4">
                    <a href="Pagina_principal.php" class="btn btn-continue btn-lg">
                        <i class="fas fa-home me-2"></i>Volver al inicio
                    </a>
                    <a href="Mis_compras.php" class="btn btn-outline-secondary btn-lg">
                        <i class="fas fa-shopping-bag me-2"></i>Ver mis compras
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Animación para la página
        document.addEventListener('DOMContentLoaded', function() {
            // Efecto de aparición gradual
            const elements = document.querySelectorAll('.confirmation-header, .detail-item, .table, .total-box, .alert, .d-grid');
            elements.forEach((el, index) => {
                setTimeout(() => {
                    el.style.opacity = '1';
                }, 100 * index);
                el.style.opacity = '0';
                el.style.transition = 'opacity 0.5s ease';
            });
            
            // Configurar calificación por estrellas para cada producto
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