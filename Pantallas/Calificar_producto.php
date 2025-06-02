<?php
session_start();
require_once '../Config/database.php';

if (!isset($_SESSION['Id_usuario'])) {
    header('Location: Login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: Mis_compras.php');
    exit();
}

$userId = $_SESSION['Id_usuario'];
$productId = $_POST['product_id'];
$compraId = $_POST['compra_id'];
$rating = $_POST['rating'];
$comment = trim($_POST['comment']);

$db = Database::getInstance();
$conn = $db->getConnection();

// Validar que el producto pertenece a una compra del usuario
$sqlCheck = "SELECT COUNT(*) FROM ticket_compra t
             JOIN compras c ON t.Id_compra = c.Id_compra
             WHERE t.Id_producto = ? AND c.id_usuario = ? AND t.Id_compra = ?";
$stmtCheck = $conn->prepare($sqlCheck);
$stmtCheck->execute([$productId, $userId, $compraId]);
$isValid = $stmtCheck->fetchColumn() > 0;

if ($isValid && $rating >= 1 && $rating <= 5 && strlen($comment) >= 10) {
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

header("Location: Mis_compras.php");
exit();
?>