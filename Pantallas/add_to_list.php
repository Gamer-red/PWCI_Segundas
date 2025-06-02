<?php
session_start();
require_once '../Config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['Id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión para añadir productos a listas']);
    exit();
}

$db = Database::getInstance();
$conn = $db->getConnection();

$data = json_decode(file_get_contents('php://input'), true);
$productId = $data['productId'] ?? null;
$listId = $data['listId'] ?? null;

// Validar datos de entrada
if (!$productId || !$listId) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

try {
    // Verificar que la lista pertenezca al usuario
    $sqlCheckList = "SELECT * FROM lista WHERE Id_lista = ? AND Id_usuario = ?";
    $stmtCheckList = $conn->prepare($sqlCheckList);
    $stmtCheckList->execute([$listId, $_SESSION['Id_usuario']]);
    
    if ($stmtCheckList->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Lista no encontrada o no tienes permiso']);
        exit();
    }

    // Verificar si el producto existe y está autorizado
    $sqlProduct = "SELECT * FROM productos WHERE Id_producto = ? AND autorizado = 1";
    $stmtProduct = $conn->prepare($sqlProduct);
    $stmtProduct->execute([$productId]);

    if ($stmtProduct->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado o no autorizado']);
        exit();
    }

    // Verificar si el producto ya está en la lista
    $sqlCheck = "SELECT * FROM productos_de_lista WHERE Id_lista = ? AND Id_producto = ?";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->execute([$listId, $productId]);

    if ($stmtCheck->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'El producto ya está en esta lista']);
        exit();
    }

    // Añadir producto a la lista
    $sqlAdd = "INSERT INTO productos_de_lista 
               (Id_lista, Id_producto, id_usuario, fecha_actualizacion, hora_actualizacion) 
               VALUES (?, ?, ?, CURDATE(), CURTIME())";
    $stmtAdd = $conn->prepare($sqlAdd);
    $stmtAdd->execute([$listId, $productId, $_SESSION['Id_usuario']]);

    echo json_encode(['success' => true, 'message' => 'Producto añadido a la lista correctamente']);
    
} catch (PDOException $e) {
    error_log("Error al agregar producto a lista: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en el servidor']);
}
?>