<?php
session_start();
require_once '../Config/database.php';

if (!isset($_SESSION['Id_usuario'])) {
    header('Location: Login.php');
    exit();
}

$db = Database::getInstance();
$conn = $db->getConnection();
$userId = $_SESSION['Id_usuario'];

// Obtener la lista "Carrito" del usuario
$sqlCartList = "SELECT Id_lista FROM lista WHERE Id_usuario = ? AND Nombre_lista = 'Carrito' LIMIT 1";
$stmtCartList = $conn->prepare($sqlCartList);
$stmtCartList->execute([$userId]);
$cartList = $stmtCartList->fetch(PDO::FETCH_ASSOC);

$cartItems = [];
$total = 0;

if ($cartList) {
    // Obtener los productos del carrito con información completa, incluyendo si es cotización y el precio unitario
    $sqlCartItems = "SELECT pdl.*, p.Nombre, p.Cotizar, p.Cantidad as stock, 
                    COALESCE(pdl.precio_unitario, p.Precio) as Precio_final,
                    (SELECT Imagen FROM multimedia WHERE Id_producto = p.Id_producto LIMIT 1) as Imagen
                    FROM productos_de_lista pdl
                    JOIN productos p ON pdl.Id_producto = p.Id_producto
                    WHERE pdl.Id_lista = ?";
    $stmtCartItems = $conn->prepare($sqlCartItems);
    $stmtCartItems->execute([$cartList['Id_lista']]);
    $cartItems = $stmtCartItems->fetchAll(PDO::FETCH_ASSOC);

    // Calcular el total
    foreach ($cartItems as $item) {
        // Si es una cotización aceptada (tiene precio_unitario) o no es cotizable
        if (!is_null($item['precio_unitario']) || !$item['Cotizar']) {
            $precio = is_null($item['precio_unitario']) ? $item['Precio_final'] : $item['precio_unitario'];
            $total += $precio * $item['cantidad'];
        }
    }
}

// Procesar actualización de cantidades
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_quantity'])) {
        $productId = $_POST['product_id'];
        $newQuantity = (int)$_POST['quantity'];
        
        // Validar que la cantidad sea válida
        if ($newQuantity > 0) {
            $sqlUpdate = "UPDATE productos_de_lista SET cantidad = ? 
                          WHERE Id_lista = ? AND Id_producto = ?";
            $stmtUpdate = $conn->prepare($sqlUpdate);
            $stmtUpdate->execute([$newQuantity, $cartList['Id_lista'], $productId]);
            
            // Redirigir para evitar reenvío del formulario
            header('Location: Detalle_carrito.php');
            exit();
        }
    } elseif (isset($_POST['remove_item'])) {
        $productId = $_POST['product_id'];
        
        $sqlDelete = "DELETE FROM productos_de_lista 
                      WHERE Id_lista = ? AND Id_producto = ?";
        $stmtDelete = $conn->prepare($sqlDelete);
        $stmtDelete->execute([$cartList['Id_lista'], $productId]);
        
        // Redirigir para evitar reenvío del formulario
        header('Location: Detalle_carrito.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrito de Compras | TuTiendaOnline</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../CSS/Estilo_DetalleCarrito.css">
</head>
<body>
    <!-- Navbar -->
    <?php include 'Navbar.php'; ?>
    <div class="container py-5">
        <div class="row">
            <div class="col-md-8">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-shopping-cart me-2"></i>Mi Carrito</h2>
                    <span class="text-muted"><?php echo count($cartItems); ?> <?php echo count($cartItems) === 1 ? 'producto' : 'productos'; ?></span>
                </div>
                
                <?php if (empty($cartItems)): ?>
                    <div class="card empty-cart">
                        <div class="card-body text-center">
                            <i class="fas fa-shopping-cart fa-4x text-muted mb-4"></i>
                            <h4 class="mb-3">Tu carrito está vacío</h4>
                            <p class="text-muted mb-4">Agrega productos para comenzar a comprar</p>
                            <a href="Pagina_principal.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left me-2"></i>Continuar comprando
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 120px;">Producto</th>
                                            <th>Descripción</th>
                                            <th>Precio</th>
                                            <th>Cantidad</th>
                                            <th>Total</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cartItems as $item): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($item['Imagen']): ?>
                                                        <img src="data:image/jpeg;base64,<?php echo base64_encode($item['Imagen']); ?>" 
                                                             class="product-image img-thumbnail" alt="<?php echo htmlspecialchars($item['Nombre']); ?>">
                                                    <?php else: ?>
                                                        <img src="https://via.placeholder.com/100?text=Sin+imagen" 
                                                             class="product-image img-thumbnail" alt="Producto sin imagen">
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($item['Nombre']); ?></h6>
                                                    <?php if ($item['cantidad'] > $item['stock']): ?>
                                                        <div class="text-danger small mt-1">
                                                            <i class="fas fa-exclamation-circle"></i> Solo <?php echo $item['stock']; ?> disponibles
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    $<?php echo number_format($item['Precio_final'], 2); ?>
                                                </td>
                                                <td>
                                                    <form method="post" class="d-flex">
                                                        <input type="hidden" name="product_id" value="<?php echo $item['Id_producto']; ?>">
                                                        <?php if (!is_null($item['precio_unitario'])): ?>
                                                            <!-- Si tiene precio_unitario (propuesta aprobada), mostrar cantidad fija -->
                                                            <input type="number" name="quantity" value="<?php echo $item['cantidad']; ?>" 
                                                                class="form-control quantity-input" readonly>
                                                            <button type="button" class="btn btn-sm btn-outline-secondary ms-2" disabled>
                                                                <i class="fas fa-sync-alt"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <!-- Si no tiene precio_unitario, permitir cambiar cantidad -->
                                                            <input type="number" name="quantity" min="1" max="<?php echo $item['stock']; ?>" 
                                                                value="<?php echo $item['cantidad']; ?>" class="form-control quantity-input">
                                                            <button type="submit" name="update_quantity" class="btn btn-sm btn-outline-secondary ms-2">
                                                                <i class="fas fa-sync-alt"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </form>
                                                </td>
                                                <td>
                                                    $<?php echo number_format($item['Precio_final'] * $item['cantidad'], 2); ?>
                                                </td>
                                                <td class="text-end">
                                                    <form method="post" onsubmit="return confirm('¿Eliminar este producto del carrito?');">
                                                        <input type="hidden" name="product_id" value="<?php echo $item['Id_producto']; ?>">
                                                        <button type="submit" name="remove_item" class="btn btn-sm btn-outline-danger">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-3">
                        <a href="Pagina_principal.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Seguir comprando
                        </a>
                        <a href="Detalle_carrito.php" class="btn btn-outline-primary">
                            <i class="fas fa-sync-alt me-2"></i>Actualizar carrito
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($cartItems)): ?>
                <div class="col-md-4">
                    <div class="card summary-card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Resumen del pedido</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal:</span>
                                <span>$<?php echo number_format($total, 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Envío:</span>
                                <span class="text-success">Gratis</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between fw-bold fs-5">
                                <span>Total:</span>
                                <span>$<?php echo number_format($total, 2); ?></span>
                            </div>
                            
                            <div class="d-grid gap-2 mt-4">
                                <a href="Metodos_pago.php" class="btn btn-primary btn-lg">
                                    <i class="fas fa-credit-card me-2"></i> Proceder al pago
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>