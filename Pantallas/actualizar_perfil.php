<?php
session_start();
require_once '../Config/database.php';

if (!isset($_SESSION['Id_usuario'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['Id_usuario'];

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
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
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}