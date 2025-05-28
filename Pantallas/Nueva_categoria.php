<?php
// Iniciar sesión y verificar rol de vendedor
session_start();
require_once '../Config/database.php';

if (!isset($_SESSION['Id_usuario'])) {
    header('Location: Login.php');
    exit();
}

$db = Database::getInstance();
$conn = $db->getConnection();

$userId = $_SESSION['Id_usuario'];
// Procesar el formulario
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombreCategoria = trim($_POST['nombre_categoria']);
    if (empty($nombreCategoria)) {
        $error = 'El nombre de la categoría es requerido';
    } else {
        try {
            // Modificamos la consulta para incluir el campo autorizado con valor 0 (no autorizado por defecto)
            $sql = "INSERT INTO categorias (Nombre_categoria, Id_usuario, autorizado) VALUES (?, ?, 0)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$nombreCategoria, $_SESSION['Id_usuario']]);
            
            $success = 'Categoría creada exitosamente. Espera a que un administrador la apruebe.';
        } catch (PDOException $e) {
            $error = 'Error al crear la categoría: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Categoría - TuTiendaOnline</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background-color: #131921;
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        .btn-primary {
            background-color: #FF9900;
            border-color: #FF9900;
        }
        .btn-primary:hover {
            background-color: #FF8C00;
            border-color: #FF8C00;
        }
        .form-control:focus {
            border-color: #FF9900;
            box-shadow: 0 0 0 0.25rem rgba(255, 153, 0, 0.25);
        }
        .alert-info {
            background-color: #e7f5ff;
            border-color: #d0ebff;
            color: #1864ab;
        }
    </style>
</head>
<body>
    <?php include 'Navbar.php'; ?>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card">
                    <div class="card-header text-center">
                        <h4><i class="fas fa-plus-circle me-2"></i>Nueva Categoría</h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <div class="alert alert-info mb-4">
                            <i class="fas fa-info-circle me-2"></i>
                            Las nuevas categorías deben ser aprobadas por un administrador antes de ser visibles.
                        </div>
                        
                        <form method="POST" action="Nueva_categoria.php">
                            <div class="mb-4">
                                <label for="nombre_categoria" class="form-label fw-bold">Nombre de la categoría</label>
                                <input type="text" class="form-control" id="nombre_categoria" name="nombre_categoria" 
                                       placeholder="Ej. Electrónica, Ropa, Hogar" required>
                                <div class="form-text">El nombre debe ser descriptivo y único.</div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save me-2"></i>Guardar Categoría
                                </button>
                                <a href="mis_productos.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Cancelar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="mt-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Instrucciones</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">1. El nombre debe ser único y descriptivo.</li>
                                <li class="list-group-item">2. Las categorías deben ser aprobadas por un administrador.</li>
                                <li class="list-group-item">3. Solo podrás usar categorías aprobadas para tus productos.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>