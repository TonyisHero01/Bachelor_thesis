/**
 * Initializes order cancellation and confirmation button behaviors
 * after the DOM is fully loaded.
 */
document.addEventListener('DOMContentLoaded', () => {
    const cancelBtn = document.getElementById('cancelOrderBtn');

    if (cancelBtn) {
        /**
         * Handles order cancellation after user confirmation.
         */
        cancelBtn.addEventListener('click', async () => {
            if (!confirm('Are you sure you want to cancel this order?')) {
                return;
            }

            const url = cancelBtn.getAttribute('data-cancel-api');

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                });

                const result = await response.json();

                if (result.success) {
                    alert('Order canceled.');
                    const backToCart = document.querySelector('.back-link');

                    if (backToCart) {
                        window.location.href = backToCart.getAttribute('href');
                    }
                } else {
                    alert(
                        `Error canceling order: ${
                            result.message || 'unknown'
                        }`,
                    );
                }
            } catch (err) {
                alert('Network error.');
            }
        });
    }

    const confirmBtn = document.getElementById('confirmOrderBtn');

    if (confirmBtn) {
        /**
         * Handles order confirmation button click.
         *
         * @param {Event} e - Click event.
         */
        confirmBtn.addEventListener('click', (e) => {
            console.log('Redirecting to mock payment...');
        });
    }
});