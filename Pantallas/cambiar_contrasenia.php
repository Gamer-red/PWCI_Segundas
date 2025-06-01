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

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if ($data) {
    // Verificar la contraseña actual
    $stmt = $conn->prepare("SELECT Contrasenia FROM usuarios WHERE Id_usuario = ?");
    $stmt->execute([$userId]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (password_verify($data['contraseniaActual'], $usuario['Contrasenia'])) {
        // Actualizar la contraseña
        $nuevaContraseniaHash = password_hash($data['nuevaContrasenia'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE usuarios SET Contrasenia = ? WHERE Id_usuario = ?");
        $stmt->execute([$nuevaContraseniaHash, $userId]);

        echo json_encode(['success' => true, 'message' => 'Contraseña actualizada correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'La contraseña actual es incorrecta']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Datos no recibidos']);
}
?>