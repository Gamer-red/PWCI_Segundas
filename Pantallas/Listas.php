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

// Obtener todas las listas del usuario (excepto el carrito)
$sqlListas = "SELECT l.*, COUNT(pdl.Id_producto) as total_productos 
              FROM lista l
              LEFT JOIN productos_de_lista pdl ON l.Id_lista = pdl.Id_lista
              WHERE l.Id_usuario = ? AND l.Nombre_lista != 'Carrito'
              GROUP BY l.Id_lista
              ORDER BY l.Nombre_lista";
$stmtListas = $conn->prepare($sqlListas);
$stmtListas->execute([$userId]);
$listas = $stmtListas->fetchAll(PDO::FETCH_ASSOC);

// Procesar acciones (eliminar lista o producto de lista)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'delete_list' && isset($_POST['list_id'])) {
                // Eliminar lista y sus productos
                $conn->beginTransaction();
                
                // Primero eliminar los productos de la lista
                $sqlDeleteProducts = "DELETE FROM productos_de_lista WHERE Id_lista = ?";
                $stmtDeleteProducts = $conn->prepare($sqlDeleteProducts);
                $stmtDeleteProducts->execute([$_POST['list_id']]);
                
                // Luego eliminar la lista
                $sqlDeleteList = "DELETE FROM lista WHERE Id_lista = ? AND Id_usuario = ?";
                $stmtDeleteList = $conn->prepare($sqlDeleteList);
                $stmtDeleteList->execute([$_POST['list_id'], $userId]);
                
                $conn->commit();
                $_SESSION['success_message'] = "Lista eliminada correctamente";
                
            } elseif ($_POST['action'] === 'remove_product' && isset($_POST['list_id']) && isset($_POST['product_id'])) {
                // Eliminar producto de una lista
                $sqlRemoveProduct = "DELETE FROM productos_de_lista 
                                    WHERE Id_lista = ? AND Id_producto = ?";
                $stmtRemoveProduct = $conn->prepare($sqlRemoveProduct);
                $stmtRemoveProduct->execute([$_POST['list_id'], $_POST['product_id']]);
                
                $_SESSION['success_message'] = "Producto eliminado de la lista";
            } elseif ($_POST['action'] === 'update_list' && isset($_POST['list_id'])) {
                // Actualizar información de la lista
                $nombre = htmlspecialchars(trim($_POST['nombre']));
                $descripcion = htmlspecialchars(trim($_POST['descripcion']));
                
                if (empty($nombre)) {
                    throw new Exception("El nombre de la lista es requerido");
                }
                
                $sqlUpdateList = "UPDATE lista 
                                 SET Nombre_lista = ?, Descripcion_lista = ?
                                 WHERE Id_lista = ? AND Id_usuario = ?";
                $stmtUpdateList = $conn->prepare($sqlUpdateList);
                $stmtUpdateList->execute([$nombre, $descripcion, $_POST['list_id'], $userId]);
                
                $_SESSION['success_message'] = "Lista actualizada correctamente";
            }
            
            // Recargar la página para evitar reenvío del formulario
            header("Location: Listas.php");
            exit();
            
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $_SESSION['error_message'] = $e->getMessage();
            header("Location: Listas.php");
            exit();
        }
    }
}

// Obtener productos de una lista específica si se solicita
$listaDetalle = null;
$productosLista = [];
if (isset($_GET['view'])) {
    $listaId = $_GET['view'];
    
    // Verificar que la lista pertenece al usuario
    $sqlCheckList = "SELECT * FROM lista WHERE Id_lista = ? AND Id_usuario = ?";
    $stmtCheckList = $conn->prepare($sqlCheckList);
    $stmtCheckList->execute([$listaId, $userId]);
    $listaDetalle = $stmtCheckList->fetch(PDO::FETCH_ASSOC);
    
    if ($listaDetalle) {
        // Obtener productos de la lista
        $sqlProductosLista = "SELECT p.*, pdl.cantidad, pdl.id_productos_de_lista, 
                             u.Nombre_del_usuario as vendedor
                             FROM productos p
                             JOIN productos_de_lista pdl ON p.Id_producto = pdl.Id_producto
                             JOIN usuarios u ON p.Id_usuario = u.Id_usuario
                             WHERE pdl.Id_lista = ? AND p.autorizado = 1
                             ORDER BY pdl.fecha_actualizacion DESC, pdl.hora_actualizacion DESC";
        $stmtProductosLista = $conn->prepare($sqlProductosLista);
        $stmtProductosLista->execute([$listaId]);
        $productosLista = $stmtProductosLista->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Listas | TuTiendaOnline</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .list-card {
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
        }
        .list-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .product-img {
            max-height: 100px;
            object-fit: contain;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        .list-actions {
            position: absolute;
            top: 10px;
            right: 10px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'Navbar.php'; ?>
    
    <div class="container py-4">
        <!-- Mensajes de éxito/error -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Mis Listas</h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newListModal">
                <i class="fas fa-plus"></i> Crear Nueva Lista
            </button>
        </div>
        
        <?php if (empty($listas) && !isset($_GET['view'])): ?>
            <div class="card empty-state">
                <div class="card-body">
                    <i class="fas fa-list fa-4x mb-3"></i>
                    <h3>No tienes listas creadas</h3>
                    <p>Comienza creando tu primera lista para organizar tus productos favoritos.</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newListModal">
                        <i class="fas fa-plus"></i> Crear Lista
                    </button>
                </div>
            </div>
        <?php elseif (isset($_GET['view']) && $listaDetalle): ?>
            <!-- Vista detallada de una lista específica -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-0"><?php echo htmlspecialchars($listaDetalle['Nombre_lista']); ?></h2>
                        <?php if ($listaDetalle['Descripcion_lista']): ?>
                            <p class="mb-0 text-muted"><?php echo htmlspecialchars($listaDetalle['Descripcion_lista']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editListModal" 
                                onclick="setEditListData(<?php echo $listaDetalle['Id_lista']; ?>, '<?php echo addslashes($listaDetalle['Nombre_lista']); ?>', '<?php echo addslashes($listaDetalle['Descripcion_lista']); ?>')">
                            <i class="fas fa-edit"></i> Editar
                        </button>
                        <a href="Listas.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php if (empty($productosLista)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                            <h4>Esta lista está vacía</h4>
                            <p class="text-muted">Agrega productos desde la página de un producto.</p>
                            <a href="Pagina_principal.php" class="btn btn-primary">
                                <i class="fas fa-shopping-bag"></i> Explorar Productos
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Vendedor</th>
                                        <th>Precio</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($productosLista as $producto): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div>
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($producto['Nombre']); ?></h6>
                                                        <small class="text-muted"><?php echo htmlspecialchars($producto['Cotizar'] ? 'Precio a cotizar' : '$' . number_format($producto['Precio'], 2)); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($producto['vendedor']); ?></td>
                                            <td>
                                                <?php if ($producto['Cotizar']): ?>
                                                    <span class="text-muted">Cotizar</span>
                                                <?php else: ?>
                                                    $<?php echo number_format($producto['Precio'], 2); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <a href="detalle_producto.php?id=<?php echo $producto['Id_producto']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="Ver producto">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <button class="btn btn-sm btn-outline-danger" 
                                                            onclick="removeProduct(<?php echo $listaDetalle['Id_lista']; ?>, <?php echo $producto['Id_producto']; ?>)" 
                                                            title="Eliminar de la lista">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <?php if ($listaDetalle['Nombre_lista'] !== 'Lista de deseos'): ?>
                                                       
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Vista de todas las listas -->
            <div class="row">
                <?php foreach ($listas as $lista): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card list-card">
                            <div class="card-body">
                                <div class="list-actions">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                                                data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item" href="Listas.php?view=<?php echo $lista['Id_lista']; ?>">
                                                    <i class="fas fa-eye me-2"></i>Ver
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#editListModal"
                                                   onclick="setEditListData(<?php echo $lista['Id_lista']; ?>, '<?php echo addslashes($lista['Nombre_lista']); ?>', '<?php echo addslashes($lista['Descripcion_lista']); ?>')">
                                                    <i class="fas fa-edit me-2"></i>Editar
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <button class="dropdown-item text-danger" 
                                                        onclick="confirmDelete(<?php echo $lista['Id_lista']; ?>, '<?php echo addslashes($lista['Nombre_lista']); ?>')">
                                                    <i class="fas fa-trash me-2"></i>Eliminar
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="d-flex flex-column h-100">
                                    <h3 class="card-title"><?php echo htmlspecialchars($lista['Nombre_lista']); ?></h3>
                                    
                                    <?php if ($lista['Descripcion_lista']): ?>
                                        <p class="card-text text-muted"><?php echo htmlspecialchars($lista['Descripcion_lista']); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="mt-auto">
                                        <p class="mb-2">
                                            <i class="fas fa-box-open me-2"></i>
                                            <?php echo $lista['total_productos']; ?> producto<?php echo $lista['total_productos'] != 1 ? 's' : ''; ?>
                                        </p>
                                        <a href="Listas.php?view=<?php echo $lista['Id_lista']; ?>" class="btn btn-primary w-100">
                                            <i class="fas fa-eye me-2"></i>Ver Lista
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal para crear nueva lista -->
    <div class="modal fade" id="newListModal" tabindex="-1" aria-labelledby="newListModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="createListForm" method="POST" action="Listas.php">
                    <input type="hidden" name="action" value="create_list">
                    <div class="modal-header">
                        <h5 class="modal-title" id="newListModalLabel">Crear Nueva Lista</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="listName" class="form-label">Nombre de la lista</label>
                            <input type="text" class="form-control" id="listName" name="nombre" required>
                        </div>
                        <div class="mb-3">
                            <label for="listDescription" class="form-label">Descripción (opcional)</label>
                            <textarea class="form-control" id="listDescription" name="descripcion" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Crear Lista</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal para editar lista -->
    <div class="modal fade" id="editListModal" tabindex="-1" aria-labelledby="editListModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="editListForm" method="POST" action="Listas.php">
                    <input type="hidden" name="action" value="update_list">
                    <input type="hidden" id="editListId" name="list_id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editListModalLabel">Editar Lista</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="editListName" class="form-label">Nombre de la lista</label>
                            <input type="text" class="form-control" id="editListName" name="nombre" required>
                        </div>
                        <div class="mb-3">
                            <label for="editListDescription" class="form-label">Descripción (opcional)</label>
                            <textarea class="form-control" id="editListDescription" name="descripcion" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal de confirmación para eliminar lista -->
    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="Listas.php">
                    <input type="hidden" name="action" value="delete_list">
                    <input type="hidden" id="deleteListId" name="list_id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="confirmDeleteModalLabel">Confirmar Eliminación</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>¿Estás seguro de que deseas eliminar la lista "<span id="deleteListName"></span>"?</p>
                        <p class="text-danger">Esta acción no se puede deshacer y se eliminarán todos los productos de la lista.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Eliminar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para confirmar eliminación de lista
        function confirmDelete(listId, listName) {
            document.getElementById('deleteListId').value = listId;
            document.getElementById('deleteListName').textContent = listName;
            const modal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
            modal.show();
        }
        
        // Función para configurar datos de edición de lista
        function setEditListData(listId, name, description) {
            document.getElementById('editListId').value = listId;
            document.getElementById('editListName').value = name;
            document.getElementById('editListDescription').value = description || '';
            
            const modal = new bootstrap.Modal(document.getElementById('editListModal'));
            modal.show();
        }
        
        // Función para eliminar producto de lista
        function removeProduct(listId, productId) {
            if (confirm('¿Estás seguro de que deseas eliminar este producto de la lista?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'Listas.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'remove_product';
                form.appendChild(actionInput);
                
                const listInput = document.createElement('input');
                listInput.type = 'hidden';
                listInput.name = 'list_id';
                listInput.value = listId;
                form.appendChild(listInput);
                
                const productInput = document.createElement('input');
                productInput.type = 'hidden';
                productInput.name = 'product_id';
                productInput.value = productId;
                form.appendChild(productInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Función para actualizar cantidad de producto en lista
        function updateQuantity(itemId, quantity) {
            fetch('update_list_item.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    item_id: itemId,
                    quantity: quantity
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert('Error al actualizar la cantidad: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión');
            });
        }
        
        // Función para mover producto a lista de deseos
        function moveToWishlist(productId) {
            fetch('Wishlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'add',
                    productId: productId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Producto movido a tu lista de deseos');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión');
            });
        }
    </script>
</body>
</html>