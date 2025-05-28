<?php
// Iniciar sesión y verificar rol de vendedor
session_start();
require_once '../Config/database.php';

if (!isset($_SESSION['Id_usuario'])) {
    header('Location: Login.php');
    exit();
}

// Obtener instancia de la base de datos y conexión
$db = Database::getInstance();
$conn = $db->getConnection();

$userId = $_SESSION['Id_usuario'];

// Verificar si el usuario es vendedor
$stmt = $conn->prepare("SELECT Id_rol FROM usuarios WHERE Id_usuario = ?");
$stmt->execute([$userId]);
$idRol = $stmt->fetchColumn();

if ($idRol != 2) { // 2 = Vendedor
    header('Location: Login.php');
    exit();
}

// Obtener productos del inventario
$query = "SELECT p.Id_producto, p.Nombre, p.Precio, p.Cantidad, p.autorizado, 
                 c.Nombre_categoria as Categoria
          FROM productos p
          LEFT JOIN categorias c ON p.Id_categoria = c.Id_categoria
          WHERE p.Id_usuario = ?
          ORDER BY p.Nombre";
$stmt = $conn->prepare($query);
$stmt->execute([$userId]);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario | TuTiendaOnline</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        .dashboard-header {
            background-color: #232f3e;
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 2rem;
        }
        
        .card-stat {
            border-radius: 10px;
            transition: all 0.3s;
            height: 100%;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .card-stat:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #FF9900;
        }
        
        .btn-amazon {
            background-color: #FF9900;
            color: #131921;
            border-color: #FCD200;
        }
        
        .btn-amazon:hover {
            background-color: #F7CA00;
            border-color: #F2C200;
        }
        
        .badge-approved {
            background-color: #28a745;
        }
        
        .badge-pending {
            background-color: #ffc107;
            color: #212529;
        }
        
        .badge-rejected {
            background-color: #dc3545;
        }
        
        .product-img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'Navbar.php'; ?>
    
    <!-- Header del dashboard -->
    <div class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-warehouse me-2"></i> Inventario</h1>
                    <p class="mb-0">Administra tus productos y stock</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="Nuevo_producto.php" class="btn btn-amazon me-2">
                        <i class="fas fa-plus me-1"></i> Nuevo Producto
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contenido principal -->
    <div class="container mb-5">
        <!-- Resumen rápido -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card card-stat h-100 text-center p-3">
                    <div class="stat-icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div class="stat-value"><?php echo count($productos); ?></div>
                    <div class="stat-label">Productos totales</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card card-stat h-100 text-center p-3">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo array_reduce($productos, function($carry, $item) { return $carry + ($item['autorizado'] == 1 ? 1 : 0); }, 0); ?></div>
                    <div class="stat-label">Productos aprobados</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card card-stat h-100 text-center p-3">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value"><?php echo array_reduce($productos, function($carry, $item) { return $carry + ($item['autorizado'] == 0 ? 1 : 0); }, 0); ?></div>
                    <div class="stat-label">Productos pendientes</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card card-stat h-100 text-center p-3">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-value"><?php echo array_reduce($productos, function($carry, $item) { return $carry + ($item['autorizado'] == -1 ? 1 : 0); }, 0); ?></div>
                    <div class="stat-label">Productos rechazados</div>
                </div>
            </div>
        </div>
        
        <!-- Tabla de productos -->
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i> Lista de productos</h5>
                <div class="d-flex">
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="inventarioTable" class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Imagen</th>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th class="text-end">Precio</th>
                                <th class="text-center">Stock</th>
                                <th>Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos as $producto): ?>
                            <tr>
                                <td>
                                    <img src="../Assets/img/product-placeholder.png" class="product-img" alt="<?php echo htmlspecialchars($producto['Nombre']); ?>">
                                </td>
                                <td><?php echo htmlspecialchars($producto['Nombre']); ?></td>
                                <td><?php echo htmlspecialchars($producto['Categoria'] ?? 'Sin categoría'); ?></td>
                                <td class="text-end">$<?php echo number_format($producto['Precio'], 2); ?></td>
                                <td class="text-center"><?php echo $producto['Cantidad']; ?></td>
                                <td>
                                    <?php if ($producto['autorizado'] == 1): ?>
                                        <span class="badge badge-approved rounded-pill p-2">Aprobado</span>
                                    <?php elseif ($producto['autorizado'] == -1): ?>
                                        <span class="badge badge-rejected rounded-pill p-2">Rechazado</span>
                                    <?php else: ?>
                                        <span class="badge badge-pending rounded-pill p-2">Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center action-buttons">
                                    <a href="editar_producto.php?id=<?php echo $producto['Id_producto']; ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-danger" title="Eliminar" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $producto['Id_producto']; ?>">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                            
                            <!-- Modal de confirmación para eliminar -->
                            <div class="modal fade" id="deleteModal<?php echo $producto['Id_producto']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Confirmar eliminación</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            ¿Estás seguro que deseas eliminar el producto "<?php echo htmlspecialchars($producto['Nombre']); ?>"?
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                            <a href="eliminar_producto.php?id=<?php echo $producto['Id_producto']; ?>" class="btn btn-danger">Eliminar</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white">
            </div>
        </div>
    </div>
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        // Inicializar DataTable
        $(document).ready(function() {
            $('#inventarioTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                },
                columnDefs: [
                    { orderable: false, targets: [0, 6] } // Deshabilitar ordenación para columnas de imagen y acciones
                ]
            });
            
            // Buscar al escribir en el input
            $('#searchInput').keyup(function(){
                $('#inventarioTable').DataTable().search($(this).val()).draw();
            });
        });
    </script>
</body>
</html>