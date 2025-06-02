<?php
session_start();
require_once '../Config/database.php';

// Verificar si el usuario es administrador
if (!isset($_SESSION['Id_usuario']) || $_SESSION['Id_rol'] != 3) { // Asumiendo que Id_rol 3 es Administrador
    header('Location: login.php');
    exit();
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Procesar acciones de aprobación/rechazo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['type'])) {
        $id = $_POST['id'];
        $action = $_POST['action'];
        
        if ($_POST['type'] === 'category') {
            $stmt = $conn->prepare("UPDATE categorias SET autorizado = ? WHERE Id_categoria = ?");
            $value = ($action === 'approve') ? 1 : -1; // Cambiado a -1 para rechazo
            $stmt->execute([$value, $id]);
        } elseif ($_POST['type'] === 'product') {
            $stmt = $conn->prepare("UPDATE productos SET autorizado = ? WHERE Id_producto = ?");
            $value = ($action === 'approve') ? 1 : -1; // Cambiado a -1 para rechazo
            $stmt->execute([$value, $id]);
        }
        
        header("Location: admin_panel.php");
        exit();
    }
}
// Obtener categorías pendientes de aprobación
$stmt = $conn->query("SELECT c.*, u.Nombre_del_usuario 
                     FROM categorias c 
                     JOIN usuarios u ON c.Id_usuario = u.Id_usuario 
                     WHERE c.autorizado = 0");
$pendingCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener productos pendientes de aprobación
$stmt = $conn->query("SELECT p.*, u.Nombre_del_usuario, cat.Nombre_categoria 
                     FROM productos p 
                     JOIN usuarios u ON p.Id_usuario = u.Id_usuario 
                     JOIN categorias cat ON p.Id_categoria = cat.Id_categoria 
                     WHERE p.autorizado = 0");
$pendingProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración | TuTiendaOnline</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .admin-container {
            margin-top: 20px;
        }
        .card {
            margin-bottom: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background-color: #343a40;
            color: white;
            font-weight: bold;
        }
        .btn-approve {
            background-color: #28a745;
            color: white;
        }
        .btn-reject {
            background-color: #dc3545;
            color: white;
        }
        .nav-tabs .nav-link.active {
            font-weight: bold;
            border-bottom: 3px solid #343a40;
        }
        .table-responsive {
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <?php include 'Navbar.php'; ?>
    
    <div class="container admin-container">
        <h2 class="mb-4"><i class="fas fa-user-shield me-2"></i>Panel de Administración</h2>
        
        <ul class="nav nav-tabs mb-4" id="adminTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories" type="button" role="tab">
                    <i class="fas fa-tags me-1"></i>Categorías Pendientes (<?php echo count($pendingCategories); ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button" role="tab">
                    <i class="fas fa-boxes me-1"></i>Productos Pendientes (<?php echo count($pendingProducts); ?>)
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="adminTabsContent">
            <!-- Pestaña de Categorías -->
            <div class="tab-pane fade show active" id="categories" role="tabpanel">
                <?php if (empty($pendingCategories)): ?>
                    <div class="alert alert-info">No hay categorías pendientes de aprobación.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                 
                                    <th>Nombre</th>
                                    <th>Creada por</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingCategories as $category): ?>
                                    <tr>
                                        
                                        <td><?php echo htmlspecialchars($category['Nombre_categoria']); ?></td>
                                        <td><?php echo htmlspecialchars($category['Nombre_del_usuario']); ?></td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="id" value="<?php echo $category['Id_categoria']; ?>">
                                                <input type="hidden" name="type" value="category">
                                                <button type="submit" name="action" value="approve" class="btn btn-sm btn-approve me-1">
                                                    <i class="fas fa-check"></i> Aprobar
                                                </button>
                                                <button type="submit" name="action" value="reject" class="btn btn-sm btn-reject">
                                                    <i class="fas fa-times"></i> Rechazar
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Pestaña de Productos -->
            <div class="tab-pane fade" id="products" role="tabpanel">
                <?php if (empty($pendingProducts)): ?>
                    <div class="alert alert-info">No hay productos pendientes de aprobación.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Categoría</th>
                                    <th>Vendedor</th>
                                    <th>Precio</th>
                                    <th>Stock</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingProducts as $product): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['Id_producto']); ?></td>
                                        <td><?php echo htmlspecialchars($product['Nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($product['Nombre_categoria']); ?></td>
                                        <td><?php echo htmlspecialchars($product['Nombre_del_usuario']); ?></td>
                                        <td>$<?php echo number_format($product['Precio'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($product['Cantidad']); ?></td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="id" value="<?php echo $product['Id_producto']; ?>">
                                                <input type="hidden" name="type" value="product">
                                                <button type="submit" name="action" value="approve" class="btn btn-sm btn-approve me-1">
                                                    <i class="fas fa-check"></i> Aprobar
                                                </button>
                                                <button type="submit" name="action" value="reject" class="btn btn-sm btn-reject">
                                                    <i class="fas fa-times"></i> Rechazar
                                                </button>
                                            </form>
                                           
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>