 document.addEventListener('DOMContentLoaded', function () {
            const container = document.getElementById('messages-container');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
            
            // Mostrar precio base cuando seleccionan producto
            const selectProducto = document.querySelector('select[name="producto_id"]');
            if (selectProducto) {
                selectProducto.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const precioBase = selectedOption.getAttribute('data-precio');
                    document.getElementById('precio-base').textContent = parseFloat(precioBase).toFixed(2);
                    
                    // Sugerir el precio base como valor inicial
                    document.querySelector('input[name="precio"]').value = parseFloat(precioBase).toFixed(2);
                });
            }
            
            // Toggle entre formularios
            const btnMensaje = document.getElementById('btn-mensaje');
            const btnCotizacion = document.getElementById('btn-cotizacion');
            const formMensaje = document.getElementById('form-mensaje');
            const formCotizacion = document.getElementById('form-cotizacion');
            
            if (btnMensaje && formMensaje) {
                btnMensaje.addEventListener('click', function() {
                    formMensaje.classList.toggle('active');
                    if (formCotizacion) formCotizacion.classList.remove('active');
                });
            }
            
            if (btnCotizacion && formCotizacion) {
                btnCotizacion.addEventListener('click', function() {
                    formCotizacion.classList.toggle('active');
                    formMensaje.classList.remove('active');
                });
            }
        });