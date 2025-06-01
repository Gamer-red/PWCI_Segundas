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
    $sqlCartItems = "SELECT pdl.*, p.Nombre, p.Cotizar, p.Cantidad as stock, 
                    COALESCE(pdl.precio_unitario, p.Precio) as Precio_final
                    FROM productos_de_lista pdl
                    JOIN productos p ON pdl.Id_producto = p.Id_producto
                    WHERE pdl.Id_lista = ? AND (p.Cotizar = 0 OR pdl.precio_unitario IS NOT NULL)";
    $stmtCartItems = $conn->prepare($sqlCartItems);
    $stmtCartItems->execute([$cartList['Id_lista']]);
    $cartItems = $stmtCartItems->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cartItems as $item) {
        $total += $item['Precio_final'] * $item['cantidad'];
    }
}

// Procesar pago con tarjeta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    if ($_POST['paymentMethod'] === 'creditcard') {
        if (empty($_POST['cardNumber']) || empty($_POST['cardName']) || empty($_POST['cardExpiry']) || empty($_POST['cardCvv'])) {
            $_SESSION['payment_error'] = "Por favor complete todos los campos de la tarjeta";
            header('Location: Metodos_pago.php');
            exit();
        }

        $transactionId = 'CARD-' . uniqid();
        $payerId = 'CARD-' . substr($_POST['cardNumber'], -4);
        $date = date('Y-m-d');
        $time = date('H:i:s');
        $method = "Tarjeta de crédito";

        try {
            $conn->beginTransaction();

            $sqlInsertCompra = "INSERT INTO compras (id_usuario, Fecha_compta, Hora_compra, Metodo_pago, Paypal_order_id, Paypal_payer_id) 
                              VALUES (?, ?, ?, ?, ?, ?)";
            $stmtCompra = $conn->prepare($sqlInsertCompra);
            $stmtCompra->execute([$userId, $date, $time, $method, $transactionId, $payerId]);
            $compraId = $conn->lastInsertId();

            $sqlInsertTicket = "INSERT INTO ticket_compra (Id_compra, Id_producto, Nombre, Cantidad, Precio_unitario)
                               SELECT ?, pdl.Id_producto, p.Nombre, pdl.cantidad, COALESCE(pdl.precio_unitario, p.Precio)
                               FROM productos_de_lista pdl
                               JOIN productos p ON pdl.Id_producto = p.Id_producto
                               WHERE pdl.Id_lista = ? AND (p.Cotizar = 0 OR pdl.precio_unitario IS NOT NULL)";
            $stmtTicket = $conn->prepare($sqlInsertTicket);
            $stmtTicket->execute([$compraId, $cartList['Id_lista']]);

            $sqlUpdateStock = "UPDATE productos p
                              JOIN productos_de_lista pdl ON p.Id_producto = pdl.Id_producto
                              SET p.Cantidad = p.Cantidad - pdl.cantidad
                              WHERE pdl.Id_lista = ?";
            $stmtUpdateStock = $conn->prepare($sqlUpdateStock);
            $stmtUpdateStock->execute([$cartList['Id_lista']]);

            $sqlVaciarCarrito = "DELETE FROM productos_de_lista WHERE Id_lista = ?";
            $stmtVaciar = $conn->prepare($sqlVaciarCarrito);
            $stmtVaciar->execute([$cartList['Id_lista']]);

            $conn->commit();

            header("Location: Ticket.php?token=$transactionId&PayerID=$payerId");
            exit();
        } catch (PDOException $e) {
            $conn->rollBack();
            $_SESSION['payment_error'] = "Error al procesar el pago: " . $e->getMessage();
            header('Location: Metodos_pago.php');
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Métodos de Pago | TuTiendaOnline</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../CSS/Estilo_DetalleCarrito.css">
</head>
<body>
    <?php include 'Navbar.php'; ?>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-credit-card me-2"></i>Métodos de Pago</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($cartItems)): ?>
                            <div class="alert alert-warning">No hay productos en el carrito.</div>
                            <a href="Detalle_carrito.php" class="btn btn-outline-primary">Volver al carrito</a>
                        <?php else: ?>
                            <?php if (isset($_SESSION['payment_error'])): ?>
                                <div class="alert alert-danger">
                                    <?= $_SESSION['payment_error']; unset($_SESSION['payment_error']); ?>
                                </div>
                            <?php endif; ?>
                            <div class="mb-4">
                                <h5>Resumen</h5>
                                <ul class="list-group mb-3">
                                    <?php foreach ($cartItems as $item): ?>
                                        <li class="list-group-item d-flex justify-content-between">
                                            <div>
                                                <strong><?= htmlspecialchars($item['Nombre']); ?></strong><br>
                                                <small>Cantidad: <?= $item['cantidad']; ?></small>
                                            </div>
                                            <span>$<?= number_format($item['Precio_final'] * $item['cantidad'], 2); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <div class="d-flex justify-content-between fw-bold">
                                    <span>Total:</span>
                                    <span>$<?= number_format($total, 2); ?></span>
                                </div>
                            </div>
                            <form method="post">
                                <h5 class="mb-3">Seleccione un método de pago</h5>

                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="paymentMethod" id="creditCardMethod" value="creditcard" checked>
                                    <label class="form-check-label" for="creditCardMethod">Tarjeta de crédito</label>
                                </div>

                                <div id="creditCardForm" class="mb-3">
                                    <input type="text" name="cardNumber" class="form-control mb-2" placeholder="Número de tarjeta" required>
                                    <input type="text" name="cardName" class="form-control mb-2" placeholder="Nombre en tarjeta" required>
                                    <div class="row">
                                        <div class="col">
                                            <input type="text" name="cardExpiry" class="form-control mb-2" placeholder="MM/AA" required>
                                        </div>
                                        <div class="col">
                                            <input type="text" name="cardCvv" class="form-control mb-2" placeholder="CVV" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="paymentMethod" id="paypalMethod" value="paypal">
                                    <label class="form-check-label" for="paypalMethod">PayPal</label>
                                </div>

                                <div id="paypal-button-container" style="display:none;"></div>

                                <div class="d-grid gap-2 mt-3">
                                    <button type="submit" name="process_payment" id="proceed-button" class="btn btn-primary btn-lg">
                                        Confirmar pago
                                    </button>
                                    <a href="Detalle_carrito.php" class="btn btn-outline-secondary btn-lg">Volver al carrito</a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        const total = <?= number_format($total, 2, '.', '') ?>;
    </script>
    <script src="https://www.paypal.com/sdk/js?client-id=ASa6CdpEqZsGXj6bkiTvi4_uaKofdqbeJ81QWooGnwIxHF9z34xHD2T10TtdHAcutUvU2mtZb5NhBLsD&currency=USD&components=buttons"></script>

    <script src="../JS/Detalle_carrito.js"></script>
</body>
</html>
