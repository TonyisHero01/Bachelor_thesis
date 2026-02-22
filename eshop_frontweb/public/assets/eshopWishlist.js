/**
 * Initializes wishlist actions after the DOM is fully loaded.
 */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.remove-from-wishlist').forEach((button) => {
        button.addEventListener('click', function () {
            const productId = this.getAttribute('data-product-id');

            fetch(`/wishlist/remove/${productId}`, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then((response) => response.json())
                .then((data) => {
                    if (data.success) {
                        this.closest('tr').remove();
                    } else {
                        alert('Failed to remove');
                    }
                })
                .catch((error) => console.error('Error:', error));
        });
    });

    document.querySelectorAll('.add-to-cart').forEach((button) => {
        button.addEventListener('click', function () {
            const productId = this.getAttribute('data-product-id');
            const quantity = parseInt(
                document.getElementById(`wishlist-quantity-${productId}`).textContent,
                10,
            );

            fetch('/cart/add', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ productId, quantity }),
            })
                .then((response) => response.json())
                .then((data) => {
                    if (data.success) {
                        alert('Added to cart successfully!');
                        updateCartCount(data.cartCount);
                    } else {
                        alert(`Failed to add: ${data.message}`);
                    }
                })
                .catch((error) => console.error('Error:', error));
        });
    });
});

/**
 * Updates the displayed wishlist quantity for a product.
 *
 * @param {string|number} productId - Product identifier.
 * @param {number} change - Quantity change amount.
 */
function updateQuantity(productId, change) {
    const quantityElement = document.getElementById(
        `wishlist-quantity-${productId}`,
    );

    const currentQuantity = parseInt(quantityElement.textContent, 10);
    const newQuantity = currentQuantity + change;

    if (newQuantity < 1) return;

    quantityElement.textContent = newQuantity;
}

/**
 * Updates all cart count bubble elements.
 *
 * @param {number|string} count - Cart item count.
 */
function updateCartCount(count) {
    const cartCountElements = document.querySelectorAll('.cart-count-bubble');

    cartCountElements.forEach((el) => {
        el.textContent = parseInt(count, 10);
    });
}