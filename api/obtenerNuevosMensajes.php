<?php
require_once '../Config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['Id_usuario'])) {
    http_response_code(401);
    die(json_encode(['error' => 'No autenticado']));
}

$db = Database::getInstance();
$conn = $db->getConnection();
$userId = $_SESSION['Id_usuario'];

if (!isset($_GET['conversacion_id'])) {
    http_response_code(400);
    die(json_encode(['error' => 'ID de conversación requerido']));
}

$conversacionId = $_GET['conversacion_id'];
$ultimoMensaje = $_GET['ultimo_mensaje'] ?? 0;
$ultimaPropuesta = $_GET['ultima_propuesta'] ?? 0;

try {
    // Verificar acceso a la conversación
    $sql = "SELECT Id_conversacion FROM conversacion 
            WHERE Id_conversacion = ? AND (id_emisor = ? OR id_receptor = ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$conversacionId, $userId, $userId]);

    if ($stmt->rowCount() === 0) {
        http_response_code(403);
        die(json_encode(['error' => 'No tienes acceso a esta conversación']));
    }

    // Obtener nuevos mensajes
    $sql = "SELECT m.*, 
                   CASE 
                       WHEN m.Id_emisor = ? THEN 'Tú'
                       ELSE u.Nombre_del_usuario 
                   END AS Nombre_del_usuario,
                   u.Id_usuario,
                   DATE_FORMAT(m.Fecha, '%d/%m/%Y %H:%i') as Fecha
            FROM mensajes m
            JOIN usuarios u ON m.Id_emisor = u.Id_usuario
            WHERE m.Id_conversacion = ? AND m.Id_mensaje > ?
            ORDER BY m.Fecha ASC, m.Hora ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId, $conversacionId, $ultimoMensaje]);
    $mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener nuevas propuestas
    $sql = "SELECT p.*, pr.Nombre as nombre_producto 
            FROM propuestas_cotizacion p
            JOIN productos pr ON p.Id_producto = pr.Id_producto
            WHERE p.Id_conversacion = ? AND p.Id_propuesta > ? AND (p.Id_vendedor = ? OR p.Id_comprador = ?)
            ORDER BY p.Fecha_propuesta ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$conversacionId, $ultimaPropuesta, $userId, $userId]);
    $propuestas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode([
        'mensajes' => $mensajes,
        'propuestas' => $propuestas
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Error en la base de datos: ' . $e->getMessage()]));
}