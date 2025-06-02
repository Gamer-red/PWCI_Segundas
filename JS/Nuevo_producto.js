
        document.getElementById('imagenPrincipalUpload').addEventListener('click', function() {
            document.getElementById('imagen_principal').click();
        });
        
        document.getElementById('imagen_principal').addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    document.getElementById('imagenPrincipalPreviewImg').src = e.target.result;
                    document.getElementById('imagenPrincipalInfo').textContent = 
                        `${file.name} (${(file.size / 1024).toFixed(2)} KB)`;
                    
                    document.getElementById('imagenPrincipalUpload').style.display = 'none';
                    document.getElementById('imagenPrincipalPreview').style.display = 'block';
                }
                
                reader.readAsDataURL(file);
            }
        });
                // Imagen 2
        document.getElementById('imagen2Upload').addEventListener('click', function () {
            document.getElementById('imagen_2').click();
        });

        document.getElementById('imagen_2').addEventListener('change', function () {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const reader = new FileReader();

                reader.onload = function (e) {
                    document.getElementById('imagen2PreviewImg').src = e.target.result;
                    document.getElementById('imagen2Info').textContent =
                        `${file.name} (${(file.size / 1024).toFixed(2)} KB)`;

                    document.getElementById('imagen2Upload').style.display = 'none';
                    document.getElementById('imagen2Preview').style.display = 'block';
                };

                reader.readAsDataURL(file);
            }
        });

        // Imagen 3
        document.getElementById('imagen3Upload').addEventListener('click', function () {
            document.getElementById('imagen_3').click();
        });

        document.getElementById('imagen_3').addEventListener('change', function () {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const reader = new FileReader();

                reader.onload = function (e) {
                    document.getElementById('imagen3PreviewImg').src = e.target.result;
                    document.getElementById('imagen3Info').textContent =
                        `${file.name} (${(file.size / 1024).toFixed(2)} KB)`;

                    document.getElementById('imagen3Upload').style.display = 'none';
                    document.getElementById('imagen3Preview').style.display = 'block';
                };

                reader.readAsDataURL(file);
            }
        });
                
        // Manejar la subida del video
        document.getElementById('videoUpload').addEventListener('click', function() {
            document.getElementById('video').click();
        });
        
        document.getElementById('video').addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const videoPreview = document.getElementById('videoPreviewElement');
                const videoUrl = URL.createObjectURL(file);
                
                videoPreview.src = videoUrl;
                document.getElementById('videoInfo').textContent = 
                    `${file.name} (${(file.size / (1024 * 1024)).toFixed(2)} MB)`;
                
                document.getElementById('videoUpload').style.display = 'none';
                document.getElementById('videoPreview').style.display = 'block';
            }
        });
        
        // Manejar la subida de imágenes adicionales
        document.getElementById('imagenesAdicionalesUpload').addEventListener('click', function() {
            document.getElementById('imagenes_adicionales').click();
        });
        
        document.getElementById('imagenes_adicionales').addEventListener('change', function(e) {
            const previewContainer = document.getElementById('imagenesAdicionalesPreview');
            
            if (this.files && this.files.length > 0) {
                previewContainer.innerHTML = '';
                
                for (let i = 0; i < this.files.length; i++) {
                    const file = this.files[i];
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const previewItem = document.createElement('div');
                        previewItem.className = 'mb-3';
                        
                        previewItem.innerHTML = `
                            <img src="${e.target.result}" class="preview-image">
                            <div class="file-info">${file.name} (${(file.size / 1024).toFixed(2)} KB)</div>
                        `;
                        
                        previewContainer.appendChild(previewItem);
                    }
                    
                    reader.readAsDataURL(file);
                }
            } else {
                previewContainer.innerHTML = '<p class="text-muted">No hay imágenes adicionales seleccionadas</p>';
            }
        });
        // Función para eliminar medios
        function removeMedia(type) {
    if (type === 'imagen_principal') {
        document.getElementById('imagen_principal').value = '';
        document.getElementById('imagenPrincipalUpload').style.display = 'block';
        document.getElementById('imagenPrincipalPreview').style.display = 'none';
    } else if (type === 'video') {
        document.getElementById('video').value = '';
        document.getElementById('videoPreviewElement').src = '';
        document.getElementById('videoUpload').style.display = 'block';
        document.getElementById('videoPreview').style.display = 'none';
    } else if (type === 'imagen_2') {
        document.getElementById('imagen_2').value = '';
        document.getElementById('imagen2Upload').style.display = 'block';
        document.getElementById('imagen2Preview').style.display = 'none';
    } else if (type === 'imagen_3') {
        document.getElementById('imagen_3').value = '';
        document.getElementById('imagen3Upload').style.display = 'block';
        document.getElementById('imagen3Preview').style.display = 'none';
    }
}
        // Validación del formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            const imagenPrincipal = document.getElementById('imagen_principal').files.length;
            
            if (imagenPrincipal === 0) {
                e.preventDefault();
                alert('Debes subir al menos la imagen principal del producto');
                return false;
            }
            return true;
        });

        // Recargar la página cuando se cierra el modal de categoría (para actualizar las opciones)
        document.getElementById('nuevaCategoriaModal').addEventListener('hidden.bs.modal', function () {
            location.reload();
        });