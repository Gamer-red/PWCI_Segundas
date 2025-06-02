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

// Verificar si el usuario es vendedor
$stmtRol = $conn->prepare("SELECT Id_rol FROM usuarios WHERE Id_usuario = ?");
$stmtRol->execute([$userId]);
$usuario = $stmtRol->fetch(PDO::FETCH_ASSOC);
$esVendedor = ($usuario['Id_rol'] == 2); // Asumiendo que 2 es el ID para vendedores

// Obtener datos del JSON recibido
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validar y actualizar los datos
if ($data) {
    try {
        // Preparar la consulta base
        $sql = "UPDATE usuarios SET 
                Nombre = ?, 
                Apellido_paterno = ?, 
                Apellido_materno = ?, 
                Fecha_nacimiento = ?, 
                Nombre_del_usuario = ?, 
                Correo = ?, 
                Sexo = ?";
        
        // Parámetros base
        $params = [
            $data['nombre'],
            $data['apellidoPaterno'],
            $data['apellidoMaterno'],
            $data['fechaNacimiento'],
            $data['nombreUsuario'],
            $data['correo'],
            $data['sexo']
        ];
        
        // Agregar perfil_publico solo si no es vendedor
        if (!$esVendedor && isset($data['perfilPublico'])) {
            $sql .= ", perfil_publico = ?";
            $params[] = $data['perfilPublico'];
        }
        
        // Finalizar la consulta
        $sql .= " WHERE Id_usuario = ?";
        $params[] = $userId;
        
        // Ejecutar la consulta
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        echo json_encode(['success' => true, 'message' => 'Perfil actualizado correctamente']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar el perfil: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Datos no recibidos']);
}
?>