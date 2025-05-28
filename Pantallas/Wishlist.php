<?php
session_start();
require_once '../Config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['Id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión para usar la lista de deseos']);
    exit();
}

$db = Database::getInstance();
$conn = $db->getConnection();
$userId = $_SESSION['Id_usuario'];

// Manejar solicitudes POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['action']) || !isset($data['productId'])) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
        exit();
    }
    
    $action = $data['action'];
    $productId = $data['productId'];
    
    // Verificar si el producto existe y está autorizado
    $sqlProduct = "SELECT * FROM productos WHERE Id_producto = ? AND autorizado = 1";
    $stmtProduct = $conn->prepare($sqlProduct);
    $stmtProduct->execute([$productId]);
    $product = $stmtProduct->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado o no disponible']);
        exit();
    }
    
    // Buscar o crear la lista de deseos
    $sqlLista = "SELECT Id_lista FROM lista 
                 WHERE Id_usuario = ? AND Nombre_lista = 'Lista de deseos'";
    $stmtLista = $conn->prepare($sqlLista);
    $stmtLista->execute([$userId]);
    $lista = $stmtLista->fetch(PDO::FETCH_ASSOC);
    
    if (!$lista) {
        // Crear la lista de deseos si no existe
        $sqlCreate = "INSERT INTO lista (Id_usuario, Nombre_lista, Descripcion_lista) 
                      VALUES (?, 'Lista de deseos', 'Mis productos favoritos')";
        $stmtCreate = $conn->prepare($sqlCreate);
        $stmtCreate->execute([$userId]);
        $listaId = $conn->lastInsertId();
    } else {
        $listaId = $lista['Id_lista'];
    }
    
    // Verificar si el producto ya está en la lista
    $sqlCheck = "SELECT id_productos_de_lista FROM productos_de_lista 
                 WHERE Id_lista = ? AND Id_producto = ?";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->execute([$listaId, $productId]);
    $existingItem = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if ($action === 'add') {
        if ($existingItem) {
            echo json_encode(['success' => false, 'message' => 'El producto ya está en tu lista de deseos']);
            exit();
        }
        
        // Añadir producto a la lista
        $sqlAdd = "INSERT INTO productos_de_lista 
                   (Id_lista, Id_producto, id_usuario, fecha_actualizacion, hora_actualizacion, cantidad) 
                   VALUES (?, ?, ?, CURDATE(), CURTIME(), 1)";
        $stmtAdd = $conn->prepare($sqlAdd);
        
        if ($stmtAdd->execute([$listaId, $productId, $userId])) {
            echo json_encode(['success' => true, 'message' => 'Producto añadido a tu lista de deseos']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al añadir a lista de deseos']);
        }
    } elseif ($action === 'remove') {
        if (!$existingItem) {
            echo json_encode(['success' => false, 'message' => 'El producto no está en tu lista de deseos']);
            exit();
        }
        
        // Eliminar producto de la lista
        $sqlRemove = "DELETE FROM productos_de_lista 
                      WHERE id_productos_de_lista = ?";
        $stmtRemove = $conn->prepare($sqlRemove);
        
        if ($stmtRemove->execute([$existingItem['id_productos_de_lista']])) {
            echo json_encode(['success' => true, 'message' => 'Producto eliminado de tu lista de deseos']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al eliminar de lista de deseos']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Método no permitido']);
?>