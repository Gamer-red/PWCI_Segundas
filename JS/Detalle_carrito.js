paypal.Buttons({
    createOrder: function(data, actions) {
        return actions.order.create({
            purchase_units: [{
                amount: {
                    value: total.toFixed(2)
                }
            }]
        });
    },
    onApprove: function(data, actions) {
        return actions.order.capture().then(function(details) {
            fetch('../Pantallas/ProcesarPayPal.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'paypal_order_id': data.orderID,
                    'paypal_payer_id': details.payer.payer_id
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    window.location.href = data.redirect;
                } else {
                    alert(data.message || 'Error al procesar el pago');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Ocurrió un error al procesar el pago con PayPal');
            });
        });
    }
}).render('#paypal-button-container');

document.querySelectorAll('input[name="paymentMethod"]').forEach(radio => {
    radio.addEventListener('change', () => {
        const paypalContainer = document.getElementById('paypal-button-container');
        const creditCardForm = document.getElementById('creditCardForm');
        const proceedBtn = document.getElementById('proceed-button');

        if (radio.value === 'paypal') {
            paypalContainer.style.display = 'block';
            creditCardForm.style.display = 'none';
            proceedBtn.style.display = 'none';
        } else {
            paypalContainer.style.display = 'none';
            creditCardForm.style.display = 'block';
            proceedBtn.style.display = 'block';
        }
    });
});
