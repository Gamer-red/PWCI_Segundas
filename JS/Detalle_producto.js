// Función para agregar al carrito
function addToCart(productId) {
    fetch('Cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            productId: productId, 
            quantity: 1
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Error en la red');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Actualizar contador del carrito
            const cartCount = document.getElementById('cart-count');
            if (cartCount) {
                cartCount.textContent = data.cartCount;
            }
            alert(data.message || 'Producto añadido al carrito');
        } else {
            alert(data.message || 'Error al añadir al carrito');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error de conexión: ' + error.message);
    });
}

// Función para agregar a lista de deseos
function addToWishlist(productId) {
    const wishlistBtn = document.getElementById('wishlist-btn');
    wishlistBtn.disabled = true;
    wishlistBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
    
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
    .then(response => {
        if (!response.ok) {
            throw new Error('Error en la red');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Cambiar el botón para mostrar que ya está en la lista
            wishlistBtn.innerHTML = '<i class="fas fa-check"></i> En tu lista';
            wishlistBtn.classList.remove('btn-outline-danger');
            wishlistBtn.classList.add('btn-success');
            wishlistBtn.disabled = true;
            
            // Mostrar mensaje de éxito
            alert(data.message || 'Producto añadido a tu lista de deseos');
        } else {
            alert(data.message || 'Error al añadir a lista de deseos');
            wishlistBtn.innerHTML = '<i class="fas fa-heart"></i> Lista de deseos';
            wishlistBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error de conexión: ' + error.message);
        wishlistBtn.innerHTML = '<i class="fas fa-heart"></i> Lista de deseos';
        wishlistBtn.disabled = false;
    });
}

// Función para cambiar el video principal
function changeVideo(videoData) {
    const videoPlayer = document.getElementById('productVideo');
    videoPlayer.src = 'data:video/mp4;base64,' + videoData;
    videoPlayer.load();
    videoPlayer.play();
}

// Función para crear nueva lista
function createNewList() {
    const listName = document.getElementById('listName').value;
    const description = document.getElementById('listDescription').value;
    const productId = document.getElementById('productId').value;
    
    if (!listName) {
        alert('El nombre de la lista es requerido');
        return;
    }
    
    fetch('Create_list.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            listName: listName,
            description: description,
            productId: productId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Lista creada y producto añadido');
            // Cerrar el modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('newListModal'));
            modal.hide();
            // Recargar la página para mostrar la nueva lista en el dropdown
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al crear la lista');
    });
}

// Función para agregar a una lista existente
function addToList(productId, listId) {
    fetch('add_to_list.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            productId: productId,
            listId: listId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Producto añadido a la lista');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al añadir a la lista');
    });
}

// Configurar calificación por estrellas
document.addEventListener('DOMContentLoaded', function() {
    const stars = document.querySelectorAll('.star-rating i');
    
    stars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = this.getAttribute('data-rating');
            document.getElementById('ratingValue').value = rating;
            
            // Actualizar visualización de estrellas
            stars.forEach((s, index) => {
                if (index < rating) {
                    s.classList.remove('far');
                    s.classList.add('fas');
                } else {
                    s.classList.remove('fas');
                    s.classList.add('far');
                }
            });
        });
        
        // Mostrar estrellas vacías inicialmente
        star.classList.add('far');
    });
});
document.querySelector('form[method="post"]').addEventListener('submit', function(e) {
    if (!confirm('¿Deseas contactar al vendedor para cotizar este producto?')) {
        e.preventDefault();
    }
});