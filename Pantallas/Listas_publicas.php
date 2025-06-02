<?php
session_start();
require_once '../Config/database.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['Id_usuario'])) {
    header("Location: login.php");
    exit();
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Obtener listas públicas de otros usuarios, excluyendo ciertos nombres y las del usuario actual
$sql = "SELECT l.*, u.Nombre_del_usuario as creador 
        FROM lista l
        JOIN usuarios u ON l.Id_usuario = u.Id_usuario
        WHERE (
            (l.Id_usuario != :userId AND l.Nombre_lista NOT IN ('Carrito', 'Lista de deseos', 'Wishlist'))
            OR 
            (l.Id_usuario = :userId AND l.Nombre_lista NOT IN ('Carrito', 'Lista de deseos', 'Wishlist'))
        )
        ORDER BY l.Id_lista DESC";

$stmt = $conn->prepare($sql);
$stmt->bindParam(':userId', $_SESSION['Id_usuario']);
$stmt->execute();
$listas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listas Públicas | TuTiendaOnline</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .lista-card {
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
        }
        .lista-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .lista-img {
            height: 200px;
            object-fit: cover;
            width: 100%;
        }
        .card-body {
            display: flex;
            flex-direction: column;
        }
        .card-text {
            flex-grow: 1;
        }
        .creador-badge {
            background-color: #232F3E;
            color: white;
        }
        .empty-state {
            text-align: center;
            padding: 50px;
            color: #666;
        }
        .img-placeholder {
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            height: 200px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'Navbar.php'; ?>
    
    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-globe me-2"></i> Listas Públicas</h2>
        
        <div class="row">
            <?php if (count($listas) > 0): ?>
                <?php foreach ($listas as $lista): ?>
                    <div class="col-md-4 col-lg-3 mb-4">
                        <div class="card lista-card h-100">
                            <?php if (!empty($lista['Imagen_lista'])): ?>
                                <img src="data:image/jpeg;base64,<?php echo base64_encode($lista['Imagen_lista']); ?>" 
                                     class="lista-img" alt="<?php echo htmlspecialchars($lista['Nombre_lista']); ?>">
                            <?php else: ?>
                                <div class="img-placeholder">
                                    <i class="fas fa-list-alt fa-4x"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($lista['Nombre_lista']); ?></h5>
                                
                                <span class="badge creador-badge mb-2">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($lista['creador']); ?>
                                </span>
                                
                                <?php if (!empty($lista['Descripcion_lista'])): ?>
                                    <p class="card-text"><?php echo htmlspecialchars($lista['Descripcion_lista']); ?></p>
                                <?php else: ?>
                                    <p class="card-text text-muted">Sin descripción</p>
                                <?php endif; ?>
                                
                                <div class="mt-auto">
                                    <a href="ver_lista.php?id=<?php echo $lista['Id_lista']; ?>" class="btn btn-primary w-100">
                                        <i class="fas fa-eye"></i> Ver lista
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="empty-state">
                        <i class="fas fa-list fa-4x mb-3"></i>
                        <h3>No hay listas públicas disponibles</h3>
                        <p>Actualmente no hay listas públicas para mostrar.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>