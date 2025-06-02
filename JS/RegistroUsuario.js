// Selección de rol
        function selectRole(role) {
            document.getElementById(role).checked = true;
            document.getElementById('compradorOption').classList.remove('selected');
            document.getElementById('vendedorOption').classList.remove('selected');
            document.getElementById(role + 'Option').classList.add('selected');
        }
        
        // Vista previa del avatar
        document.getElementById('avatar').addEventListener('change', function(e) {
            if (e.target.files && e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    document.getElementById('avatarPreview').src = event.target.result;
                };
                reader.readAsDataURL(e.target.files[0]);
            }
        });
        
        // Validación del formulario antes de enviar
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Las contraseñas no coinciden');
                return false;
            }
            
            if (password.length < 8) {
                e.preventDefault();
                alert('La contraseña debe tener al menos 8 caracteres');
                return false;
            }
            
            return true;
        });
          document.addEventListener("DOMContentLoaded", function() {
        const form = document.querySelector("form");
        const password = document.getElementById("password");
        const confirmPassword = document.getElementById("confirmPassword");

        form.addEventListener("submit", function(event) {
            if (password.value !== confirmPassword.value) {
                event.preventDefault();
                alert("Las contraseñas no coinciden.");
            }
        });
    });