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

// Consulta detallada
$consultaDetallada = $conn->query("
        SELECT 
            v.Fecha_venta, 
            v.Hora, 
            c.Nombre_categoria as Categoria, 
            p.Nombre as Producto, 
            (SELECT AVG(cal.Calificacion) FROM calificacion cal WHERE cal.Id_producto = p.Id_producto) as Calificacion,
            t.Precio_unitario as Precio,
            p.Cantidad as Existencia_actual,
            t.Cantidad as Unidades_vendidas,
            t.Subtotal as Total
        FROM ventas v
        JOIN productos p ON v.Id_producto = p.Id_producto
        JOIN categorias c ON p.Id_categoria = c.Id_categoria
        JOIN (
            SELECT Id_producto, MIN(Id_ticket) as Id_ticket
            FROM ticket_compra
            GROUP BY Id_producto
        ) tc ON tc.Id_producto = v.Id_producto
        JOIN ticket_compra t ON t.Id_ticket = tc.Id_ticket
        WHERE p.Id_usuario = $userId
        ORDER BY v.Fecha_venta DESC, v.Hora DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
// Consulta agrupada
$consultaAgrupada = $conn->query("SELECT 
                                    DATE_FORMAT(v.Fecha_venta, '%Y-%m') as MesAnio,
                                    c.Nombre_categoria as Categoria,
                                    COUNT(v.Id_producto) as Ventas,
                                    SUM(t.Subtotal) as Total
                                  FROM ventas v 
                                  JOIN productos p ON v.Id_producto = p.Id_producto 
                                  JOIN categorias c ON p.Id_categoria = c.Id_categoria
                                  JOIN ticket_compra t ON v.Id_producto = t.Id_producto 
                                  WHERE p.Id_usuario = $userId 
                                  GROUP BY MesAnio, c.Nombre_categoria
                                  ORDER BY MesAnio DESC, Ventas DESC")->fetchAll(PDO::FETCH_ASSOC);

// Calcular total general de productos vendidos y ganancias
$totalProductosVendidos = array_sum(array_column($consultaDetallada, 'Unidades_vendidas'));
$totalGanancias = array_sum(array_column($consultaDetallada, 'Total'));
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
    <style>
        .summary-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .summary-card:hover {
            transform: translateY(-5px);
        }
        .summary-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #eee;
            padding: 15px;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }
        .total-summary {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .product-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
        .btn-amazon {
            background-color: #FF9900;
            color: #000;
            font-weight: bold;
        }
        .btn-amazon:hover {
            background-color: #e68a00;
            color: #000;
        }
        .nav-tabs .nav-link.active {
            font-weight: bold;
            border-bottom: 3px solid #FF9900;
        }
        .rating {
            color: #FFA41C;
        }
        .table-responsive {
            max-height: 500px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'Navbar.php'; ?>
    
    <!-- Header del dashboard -->
    <div class="dashboard-header bg-light py-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-chart-line me-2"></i> Resumen de Ventas</h1>
                    <p class="mb-0">Bienvenido a tu panel de ventas</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="Inventario.php" class="btn btn-amazon me-2">
                        <i class="fas fa-boxes me-1"></i> Mis Productos
                    </a>
                    <a href="Nuevo_producto.php" class="btn btn-outline-secondary">
                        <i class="fas fa-plus me-1"></i> Nuevo Producto
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contenido principal -->
    <div class="container my-4">
        <!-- Totales generales -->
        <div class="total-summary">
            <div class="row">
                <div class="col-md-4">
                    <h4><i class="fas fa-cubes me-2"></i> Total de productos vendidos</h4>
                    <h2 class="text-primary"><?php echo $totalProductosVendidos; ?></h2>
                </div>
                <div class="col-md-4">
                    <h4><i class="fas fa-money-bill-wave me-2"></i> Ganancias totales</h4>
                    <h2 class="text-success">$<?php echo number_format($totalGanancias, 2); ?></h2>
                </div>
                
            </div>
        </div>
        
        <!-- Pestañas para cambiar entre vistas -->
        <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="detalle-tab" data-bs-toggle="tab" data-bs-target="#detalle" type="button" role="tab" aria-controls="detalle" aria-selected="true">
                    <i class="fas fa-list-ul me-2"></i> Vista Detallada
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="agrupada-tab" data-bs-toggle="tab" data-bs-target="#agrupada" type="button" role="tab" aria-controls="agrupada" aria-selected="false">
                    <i class="fas fa-chart-pie me-2"></i> Vista Agrupada
                </button>
            </li>
        </ul>
        
        <!-- Contenido de las pestañas -->
        <div class="tab-content" id="myTabContent">
            <!-- Pestaña de vista detallada -->
            <div class="tab-pane fade show active" id="detalle" role="tabpanel" aria-labelledby="detalle-tab">
                <div class="card mb-4">
                    <div class="summary-header">
                        <h5 class="mb-0"><i class="fas fa-list-ul me-2"></i> Ventas detalladas</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Fecha y Hora</th>
                                        <th>Categoría</th>
                                        <th>Producto</th>
                                        <th class="text-center">Calificación</th>
                                        <th class="text-end">Precio</th>
                                        <th class="text-center">Existencia</th>
                                        <th class="text-center">Unidades</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($consultaDetallada as $venta): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($venta['Fecha_venta'] . ' ' . $venta['Hora'])); ?></td>
                                        <td><?php echo htmlspecialchars($venta['Categoria']); ?></td>
                                        <td><?php echo htmlspecialchars($venta['Producto']); ?></td>
                                        <td class="text-center">
                                            <?php if ($venta['Calificacion']): ?>
                                                <span class="rating">
                                                    <?php 
                                                    $rating = round($venta['Calificacion'], 1);
                                                    $fullStars = floor($rating);
                                                    $halfStar = ($rating - $fullStars) >= 0.5;
                                                    $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
                                                    
                                                    for ($i = 0; $i < $fullStars; $i++) {
                                                        echo '<i class="fas fa-star"></i>';
                                                    }
                                                    if ($halfStar) {
                                                        echo '<i class="fas fa-star-half-alt"></i>';
                                                    }
                                                    for ($i = 0; $i < $emptyStars; $i++) {
                                                        echo '<i class="far fa-star"></i>';
                                                    }
                                                    ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">Sin calificar</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">$<?php echo number_format($venta['Precio'], 2); ?></td>
                                        <td class="text-center"><?php echo $venta['Existencia_actual']; ?></td>
                                        <td class="text-center"><?php echo $venta['Unidades_vendidas']; ?></td>
                                        <td class="text-end">$<?php echo number_format($venta['Total'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($consultaDetallada)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">
                                            <i class="fas fa-info-circle me-2"></i> No hay ventas registradas
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pestaña de vista agrupada -->
            <div class="tab-pane fade" id="agrupada" role="tabpanel" aria-labelledby="agrupada-tab">
                <div class="card mb-4">
                    <div class="summary-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i> Ventas agrupadas por mes y categoría</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Mes-Año</th>
                                        <th>Categoría</th>
                                        <th class="text-center">Ventas</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($consultaAgrupada as $venta): ?>
                                    <tr>
                                        <td><?php echo date('m/Y', strtotime($venta['MesAnio'] . '-01')); ?></td>
                                        <td><?php echo htmlspecialchars($venta['Categoria']); ?></td>
                                        <td class="text-center"><?php echo $venta['Ventas']; ?></td>
                                        <td class="text-end">$<?php echo number_format($venta['Total'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($consultaAgrupada)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">
                                            <i class="fas fa-info-circle me-2"></i> No hay ventas registradas
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
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