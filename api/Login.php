<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Solo para pruebas. Restringe esto en producción.
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
require_once '../Config/database.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    $response['message'] = 'Método no permitido';
    echo json_encode($response);
    exit();
}

// Obtener y decodificar datos JSON
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['username']) || !isset($data['password'])) {
    http_response_code(400); // Bad Request
    $response['message'] = 'Faltan credenciales';
    echo json_encode($response);
    exit();
}

$username = trim($data['username']);
$password = trim($data['password']);

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT u.*, r.Id_rol, r.Nombre_rol FROM usuarios u 
                            JOIN rol r ON u.Id_rol = r.Id_rol 
                            WHERE (u.Nombre_del_usuario = ? OR u.Correo = ?)");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $password === $user['Contrasenia']) {
        $_SESSION['Id_usuario'] = $user['Id_usuario'];
        $_SESSION['Nombre_del_usuario'] = $user['Nombre_del_usuario'];
        $_SESSION['Id_rol'] = $user['Id_rol'];
        $_SESSION['Nombre_rol'] = $user['Nombre_rol'];

        $response['success'] = true;
        $response['message'] = 'Inicio de sesión exitoso';
        $response['data'] = [
            'Id_usuario' => $user['Id_usuario'],
            'Nombre_del_usuario' => $user['Nombre_del_usuario'],
            'Id_rol' => $user['Id_rol'],
            'Nombre_rol' => $user['Nombre_rol']
        ];
    } else {
        http_response_code(401); // Unauthorized
        $response['message'] = 'Usuario o contraseña incorrectos';
    }
} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    $response['message'] = 'Error en el servidor: ' . $e->getMessage();
}

echo json_encode($response);
