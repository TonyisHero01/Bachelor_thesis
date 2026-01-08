/**
 * Initializes order confirmation and cancellation button behaviors
 * after the DOM is fully loaded.
 */
document.addEventListener('DOMContentLoaded', function () {
    const confirmBtn = document.getElementById('confirmOrderBtn');

    if (confirmBtn) {
        /**
         * Handles order confirmation button click.
         */
        confirmBtn.addEventListener('click', function () {
        });
    }

    const cancelBtn = document.getElementById('cancelOrderBtn');

    if (cancelBtn) {
        const apiUrl = cancelBtn.getAttribute('data-cancel-api');

        /**
         * Handles order cancellation after user confirmation.
         */
        cancelBtn.addEventListener('click', function () {
            if (!confirm('Are you sure you want to cancel this order?')) {
                return;
            }

            fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
            })
                .then((response) => response.json())
                .then((data) => {
                    if (data.success) {
                        alert('Order canceled!');
                        window.location.href = cancelBtn.getAttribute('data-cart-url')
                            || '/{{ _locale }}/cart';
                    } else {
                        alert(`Error: ${data.message}`);
                    }
                })
                .catch((error) => console.error('Cancel Order Error:', error));
        });
    }
});

/**
 * Redirects to the return request form for the given order.
 *
 * @param {string|number} orderId - Order identifier.
 */
function goToReturnForm(orderId) {
    window.location.href = `/order/${orderId}/return-form`;
}