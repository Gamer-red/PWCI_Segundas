<?php
session_start();
require_once '../Config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['Id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión para añadir productos al carrito']);
    exit();
}

$db = Database::getInstance();
$conn = $db->getConnection();
$userId = $_SESSION['Id_usuario'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $productId = $data['productId'];
    $quantity = $data['quantity'] ?? 1;

    // Verificar si el producto existe y está disponible
    $sql = "SELECT * FROM productos WHERE Id_producto = ? AND autorizado = 1 AND Cantidad > 0";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Producto no disponible']);
        exit();
    }

    // 1. Obtener o crear la lista "Carrito" del usuario
    $sqlGetCartList = "SELECT Id_lista FROM lista WHERE Id_usuario = ? AND Nombre_lista = 'Carrito' LIMIT 1";
    $stmtGetCartList = $conn->prepare($sqlGetCartList);
    $stmtGetCartList->execute([$userId]);
    $cartList = $stmtGetCartList->fetch(PDO::FETCH_ASSOC);

    if (!$cartList) {
        // Crear la lista Carrito si no existe
        $sqlCreateList = "INSERT INTO lista (Id_usuario, Nombre_lista, Descripcion_lista) VALUES (?, 'Carrito', 'Productos en mi carrito de compras')";
        $stmtCreateList = $conn->prepare($sqlCreateList);
        $stmtCreateList->execute([$userId]);
        $listId = $conn->lastInsertId();
    } else {
        $listId = $cartList['Id_lista'];
    }

    // 2. Verificar si el producto ya está en la lista Carrito
    $sqlCheckProduct = "SELECT * FROM productos_de_lista WHERE Id_lista = ? AND Id_producto = ?";
    $stmtCheckProduct = $conn->prepare($sqlCheckProduct);
    $stmtCheckProduct->execute([$listId, $productId]);
    $existingProduct = $stmtCheckProduct->fetch(PDO::FETCH_ASSOC);

    if ($existingProduct) {
        // Actualizar cantidad si el producto ya está en el carrito
        $newQuantity = $existingProduct['cantidad'] + $quantity;
        $sqlUpdate = "UPDATE productos_de_lista SET cantidad = ?, fecha_actualizacion = NOW() WHERE id_productos_de_lista = ?";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->execute([$newQuantity, $existingProduct['id_productos_de_lista']]);
    } else {
        // Insertar nuevo producto en la lista Carrito
        $sqlInsert = "INSERT INTO productos_de_lista (Id_lista, Id_producto, id_usuario, cantidad) VALUES (?, ?, ?, ?)";
        $stmtInsert = $conn->prepare($sqlInsert);
        $stmtInsert->execute([$listId, $productId, $userId, $quantity]);
    }

    // 3. Obtener el conteo actual de productos en el carrito
    $sqlCount = "SELECT SUM(cantidad) as cartCount FROM productos_de_lista WHERE Id_lista = ?";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->execute([$listId]);
    $countResult = $stmtCount->fetch(PDO::FETCH_ASSOC);
    $cartCount = $countResult['cartCount'] ?? 0;

    // Actualizar el carrito en la sesión para mantener consistencia
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $found = false;
    foreach ($_SESSION['cart'] as &$item) {
        if ($item['productId'] == $productId) {
            $item['quantity'] += $quantity;
            $found = true;
            break;
        }
    }

    if (!$found) {
        $_SESSION['cart'][] = [
            'productId' => $productId,
            'quantity' => $quantity,
            'price' => $product['Precio'],
            'name' => $product['Nombre'],
            'cotizar' => $product['Cotizar']
        ];
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Producto añadido al carrito', 
        'cartCount' => $cartCount
    ]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Método no permitido']);
?>