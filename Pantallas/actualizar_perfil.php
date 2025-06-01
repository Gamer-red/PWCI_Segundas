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

// Obtener datos del JSON recibido
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validar y actualizar los datos
if ($data) {
    try {
        $stmt = $conn->prepare("UPDATE usuarios SET 
            Nombre = ?, 
            Apellido_paterno = ?, 
            Apellido_materno = ?, 
            Fecha_nacimiento = ?, 
            Nombre_del_usuario = ?, 
            Correo = ?, 
            Sexo = ?, 
            perfil_publico = ? 
            WHERE Id_usuario = ?");

        $stmt->execute([
            $data['nombre'],
            $data['apellidoPaterno'],
            $data['apellidoMaterno'],
            $data['fechaNacimiento'],
            $data['nombreUsuario'],
            $data['correo'],
            $data['sexo'],
            $data['perfilPublico'],
            $userId
        ]);

        echo json_encode(['success' => true, 'message' => 'Perfil actualizado correctamente']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar el perfil: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Datos no recibidos']);
}
?>