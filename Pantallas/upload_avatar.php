<?php
session_start();
require_once '../Config/database.php';

if (!isset($_SESSION['Id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$db = Database::getInstance();
$conn = $db->getConnection();
$userId = $_SESSION['Id_usuario'];

if ($_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    $avatar = file_get_contents($_FILES['avatar']['tmp_name']);

    try {
        $stmt = $conn->prepare("UPDATE usuarios SET Avatar = ? WHERE Id_usuario = ?");
        $stmt->execute([$avatar, $userId]);

        // Generar URL base64 para la vista previa
        $avatarUrl = 'data:image/jpeg;base64,' . base64_encode($avatar);
        echo json_encode(['success' => true, 'avatarUrl' => $avatarUrl]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar el avatar: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Error al subir el archivo']);
}
?>