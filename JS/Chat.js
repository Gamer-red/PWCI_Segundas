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
 document.addEventListener('DOMContentLoaded', function() {
            // Actualizar stock disponible cuando se selecciona un producto
            const productoSelect = document.querySelector('select[name="producto_id"]');
            const cantidadInput = document.querySelector('input[name="cantidad"]');
            const stockDisplay = document.querySelector('.stock-display');
            
            if (productoSelect && cantidadInput && stockDisplay) {
                productoSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const stock = selectedOption.getAttribute('data-stock');
                    stockDisplay.textContent = stock;
                    cantidadInput.setAttribute('max', stock);
                    cantidadInput.value = 1; // Resetear cantidad al cambiar producto
                });
                
                // Validar cantidad al enviar el formulario
                const cotizacionForm = document.getElementById('form-cotizacion').querySelector('form');
                if (cotizacionForm) {
                    cotizacionForm.addEventListener('submit', function(e) {
                        const cantidad = parseInt(cantidadInput.value);
                        const stock = parseInt(cantidadInput.getAttribute('max'));
                        
                        if (cantidad > stock) {
                            e.preventDefault();
                            alert('La cantidad no puede ser mayor al stock disponible (' + stock + ')');
                            cantidadInput.focus();
                        }
                    });
                }
            }
            
            // Mostrar el stock inicial si ya hay un producto seleccionado
            if (productoSelect.value) {
                const event = new Event('change');
                productoSelect.dispatchEvent(event);
            }
});
