   function addToCart(productId) {
            fetch('carrito.php?add=' + productId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Producto añadido al carrito');
                        if (document.querySelector('.cart-count')) {
                            document.querySelector('.cart-count').textContent = data.cartCount;
                        }
                    } else {
                        alert('Error al añadir al carrito: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al añadir al carrito');
                });
        }
        function addToList(productId, listId) {
            fetch('add_to_list.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `productId=${productId}&listId=${listId}`
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

        function createNewList() {
            const listName = document.getElementById('listName').value;
            const listDescription = document.getElementById('listDescription').value;
            const productId = document.getElementById('productId').value;

            if (!listName) {
                alert('Por favor ingresa un nombre para la lista');
                return;
            }

            fetch('create_list.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `listName=${encodeURIComponent(listName)}&listDescription=${encodeURIComponent(listDescription)}&productId=${productId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Lista creada y producto añadido');
                    location.reload(); // Recargar para mostrar la nueva lista
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al crear la lista');
            });
        }

        // Función para cambiar el video principal
        function changeVideo(videoData) {
            const videoPlayer = document.getElementById('productVideo');
            videoPlayer.src = 'data:video/mp4;base64,' + videoData;
            videoPlayer.load();
            videoPlayer.play();
        }