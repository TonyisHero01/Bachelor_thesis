let quantity = 1;

/**
 * Increases the product quantity by one and updates the UI.
 */
function increaseQuantity() {
    quantity += 1;
    document.getElementById('quantity-value').innerText = quantity;
}

/**
 * Decreases the product quantity by one (minimum 1) and updates the UI.
 */
function decreaseQuantity() {
    if (quantity > 1) {
        quantity -= 1;
        document.getElementById('quantity-value').innerText = quantity;
    }
}

/**
 * Toggles the wishlist state for the given button.
 *
 * @param {HTMLElement} button - Wishlist button element.
 */
function toggleWishlist(button) {
    const icon = button.querySelector('.wishlist-icon');
    icon.classList.toggle('active');
}