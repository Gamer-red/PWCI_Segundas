<?php
session_start();
require_once '../Config/database.php';

if (!isset($_SESSION['Id_usuario'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Usuario no autenticado']);
    exit();
}

$db = Database::getInstance();
$conn = $db->getConnection();
$userId = $_SESSION['Id_usuario'];

$sqlCartList = "SELECT Id_lista FROM lista WHERE Id_usuario = ? AND Nombre_lista = 'Carrito' LIMIT 1";
$stmtCartList = $conn->prepare($sqlCartList);
$stmtCartList->execute([$userId]);
$cartList = $stmtCartList->fetch(PDO::FETCH_ASSOC);

// Obtener los items del carrito para registrar las ventas
$sqlCartItems = "SELECT pdl.Id_producto 
                 FROM productos_de_lista pdl
                 JOIN productos p ON pdl.Id_producto = p.Id_producto
                 WHERE pdl.Id_lista = ? AND (p.Cotizar = 0 OR pdl.precio_unitario IS NOT NULL)";
$stmtCartItems = $conn->prepare($sqlCartItems);
$stmtCartItems->execute([$cartList['Id_lista']]);
$cartItems = $stmtCartItems->fetchAll(PDO::FETCH_ASSOC);

$transactionId = $_POST['paypal_order_id'] ?? null;
$payerId = $_POST['paypal_payer_id'] ?? null;

if (!$transactionId || !$payerId || !$cartList) {
    echo json_encode(['status' => 'error', 'message' => 'Datos inválidos']);
    exit();
}

try {
    $conn->beginTransaction();

    $date = date('Y-m-d');
    $time = date('H:i:s');
    $method = "PayPal";

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

    // Registrar cada producto vendido en la tabla ventas
    foreach ($cartItems as $item) {
        $sqlInsertVenta = "INSERT INTO ventas (Fecha_venta, Hora, Id_producto, Id_Usuario) 
                          VALUES (?, ?, ?, ?)";
        $stmtVenta = $conn->prepare($sqlInsertVenta);
        $stmtVenta->execute([$date, $time, $item['Id_producto'], $userId]);
    }

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

    echo json_encode(['status' => 'success', 'redirect' => "Ticket.php?token=$transactionId&PayerID=$payerId"]);
    exit();

} catch (PDOException $e) {
    $conn->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Error al procesar el pago: ' . $e->getMessage()]);
    exit();
}