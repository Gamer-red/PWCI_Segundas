<?php
session_start();
require_once '../Config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['Id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión para crear listas']);
    exit();
}
$db = Database::getInstance();
$conn = $db->getConnection();

$data = json_decode(file_get_contents('php://input'), true);
$listName = $data['listName'];
$description = $data['description'] ?? '';
$productId = $data['productId'];

// Crear la nueva lista
$sqlCreate = "INSERT INTO lista (Id_usuario, Nombre_lista, Descripcion_lista) 
              VALUES (?, ?, ?)";
$stmtCreate = $conn->prepare($sqlCreate);
$stmtCreate->execute([$_SESSION['Id_usuario'], $listName, $description]);
$listaId = $conn->lastInsertId();

// Añadir el producto a la lista si se proporcionó un ID de producto
if ($productId) {
    // Verificar si el producto existe
    $sqlProduct = "SELECT * FROM productos WHERE Id_producto = ? AND autorizado = 1";
    $stmtProduct = $conn->prepare($sqlProduct);
    $stmtProduct->execute([$productId]);
    
    if ($stmtProduct->rowCount() > 0) {
        $sqlAdd = "INSERT INTO productos_de_lista 
                   (Id_lista, Id_producto, id_usuario, fecha_actualizacion, hora_actualizacion) 
                   VALUES (?, ?, ?, CURDATE(), CURTIME())";
        $stmtAdd = $conn->prepare($sqlAdd);
        $stmtAdd->execute([$listaId, $productId, $_SESSION['Id_usuario']]);
    }
}
echo json_encode(['success' => true, 'message' => 'Lista creada exitosamente']);
?>