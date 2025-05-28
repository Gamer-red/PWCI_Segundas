<?php
// Iniciar sesión y verificar rol de vendedor
session_start();
require_once '../Config/database.php';
// Obtener datos del vendedor
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
// Obtener estadísticas de ventas
$ventasHoy = $conn->query("SELECT COUNT(*) FROM ventas WHERE Id_Usuario = $userId AND Fecha_venta = CURDATE()")->fetchColumn();
$ventasSemana = $conn->query("SELECT COUNT(*) FROM ventas WHERE Id_Usuario = $userId AND Fecha_venta BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND CURDATE()")->fetchColumn();
$ventasMes = $conn->query("SELECT COUNT(*) FROM ventas WHERE Id_Usuario = $userId AND MONTH(Fecha_venta) = MONTH(CURDATE())")->fetchColumn();
$ingresosMes = $conn->query("SELECT SUM(t.Subtotal) FROM ticket_compra t JOIN ventas v ON t.Id_producto = v.Id_producto WHERE v.Id_Usuario = $userId AND MONTH(v.Fecha_venta) = MONTH(CURDATE())")->fetchColumn();
$ingresosMes = $ingresosMes ? $ingresosMes : 0;
// Obtener últimos productos vendidos
$productosVendidos = $conn->query("SELECT p.Nombre, COUNT(v.Id_producto) as cantidad, SUM(t.Subtotal) as total 
                                  FROM ventas v 
                                  JOIN productos p ON v.Id_producto = p.Id_producto 
                                  JOIN ticket_compra t ON v.Id_producto = t.Id_producto 
                                  WHERE p.Id_usuario = $userId 
                                  GROUP BY v.Id_producto 
                                  ORDER BY cantidad DESC 
                                  LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resumen de Ventas | TuTiendaOnline</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="../CSS/Estilo_ResumenVentas.css">
</head>
<body>
    <!-- Navbar -->
    <?php include 'Navbar.php'; ?>
    
    <!-- Header del dashboard -->
    <div class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-chart-line me-2"></i> Resumen de Ventas</h1>
                    <p class="mb-0">Bienvenido a tu panel de ventas</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="mis_productos.php" class="btn btn-amazon me-2">
                        <i class="fas fa-boxes me-1"></i> Mis Productos
                    </a>
                    <a href="nuevo_producto.php" class="btn btn-outline-light">
                        <i class="fas fa-plus me-1"></i> Nuevo Producto
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contenido principal -->
    <div class="container mb-5">
        <!-- Selector de período de tiempo -->
        <div class="time-period-selector mb-4 text-center">
            <div class="btn-group">
                <button class="btn btn-outline-secondary active">Hoy</button>
                <button class="btn btn-outline-secondary">Esta semana</button>
                <button class="btn btn-outline-secondary">Este mes</button>
                <button class="btn btn-outline-secondary">Este año</button>
                <button class="btn btn-outline-secondary">Personalizado</button>
            </div>
        </div>
        
        <!-- Estadísticas rápidas -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card card-stat h-100 text-center p-3">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="stat-value"><?php echo $ventasHoy; ?></div>
                    <div class="stat-label">Ventas hoy</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card card-stat h-100 text-center p-3">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-week"></i>
                    </div>
                    <div class="stat-value"><?php echo $ventasSemana; ?></div>
                    <div class="stat-label">Ventas esta semana</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card card-stat h-100 text-center p-3">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-value"><?php echo $ventasMes; ?></div>
                    <div class="stat-label">Ventas este mes</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card card-stat h-100 text-center p-3">
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-value">$<?php echo number_format($ingresosMes, 2); ?></div>
                    <div class="stat-label">Ingresos este mes</div>
                </div>
            </div>
        </div>
        
        <!-- Productos más vendidos -->
        <div class="card sales-table mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-star me-2"></i> Productos más vendidos</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th class="text-center">Unidades vendidas</th>
                                <th class="text-end">Ingresos totales</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productosVendidos as $producto): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($producto['Nombre']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-primary rounded-pill"><?php echo $producto['cantidad']; ?></span>
                                </td>
                                <td class="text-end">$<?php echo number_format($producto['total'], 2); ?></td>
                                <td class="text-center">
                                    <a href="#" class="btn btn-sm btn-outline-primary" title="Ver detalles">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="#" class="btn btn-sm btn-outline-secondary" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($productosVendidos)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    <i class="fas fa-info-circle me-2"></i> No hay productos vendidos aún
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Últimas ventas -->
        <div class="card sales-table">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-clock me-2"></i> Ventas recientes</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID Venta</th>
                                <th>Fecha</th>
                                <th>Producto</th>
                                <th class="text-end">Total</th>
                                <th class="text-center">Estado</th>
                            </tr>
                        </thead>
                        
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white text-center">
                <a href="ventas.php" class="btn btn-amazon">Ver todas las ventas</a>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Script para interactividad -->
    <script>
        // Cambiar período de tiempo
        document.querySelectorAll('.time-period-selector .btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelector('.time-period-selector .btn.active').classList.remove('active');
                this.classList.add('active');
                // Aquí iría la lógica para actualizar los datos según el período seleccionado
            });
        });
    </script>
</body>
</html>