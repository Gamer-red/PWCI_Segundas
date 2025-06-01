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

// Obtener todas las compras del usuario
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

// Obtener detalles de productos para cada compra
$comprasConDetalles = [];
foreach ($compras as $compra) {
    $sqlDetalles = "SELECT t.Nombre, t.Cantidad, t.Precio_unitario, t.Subtotal,
                   (SELECT Imagen FROM multimedia m 
                    JOIN productos p ON m.Id_producto = p.Id_producto 
                    WHERE p.Nombre = t.Nombre LIMIT 1) AS Imagen
                   FROM ticket_compra t
                   WHERE t.Id_compra = ?";
    $stmtDetalles = $conn->prepare($sqlDetalles);
    $stmtDetalles->execute([$compra['Id_compra']]);
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
                                            <th>Cantidad</th>
                                            <th>Precio unitario</th>
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
                                            <td><?php echo $detalle['Cantidad']; ?></td>
                                            <td>$<?php echo number_format($detalle['Precio_unitario'], 2); ?></td>
                                            <td>$<?php echo number_format($detalle['Subtotal'], 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="d-flex justify-content-end mt-3">
                                <button class="btn btn-outline-secondary">
                                    <i class="fas fa-comment me-2"></i>Dejar reseña y calificacion
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Script para manejar interacciones
        document.addEventListener('DOMContentLoaded', function() {
            // Puedes añadir aquí funcionalidades adicionales si necesitas
            console.log('Página de mis compras cargada');
        });
    </script>
</body>
</html>