/**
 * Initializes wishlist state after the DOM is fully loaded.
 */
document.addEventListener('DOMContentLoaded', function () {
    const productId = document
        .getElementById('product_id')
        .getAttribute('product-id');

    const wishlistIcon = document.querySelector('.wishlist-icon');

    fetch(`/wishlist/check/${productId}`)
        .then((response) => response.json())
        .then((data) => {
            if (data.inWishlist) {
                wishlistIcon.classList.add('wishlist-active');
            } else {
                wishlistIcon.classList.remove('wishlist-active');
            }
        })
        .catch((error) => console.error('Error checking wishlist:', error));
});

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
 * Toggles wishlist status for the given product.
 *
 * @param {HTMLElement} button - Wishlist button element.
 * @returns {Promise<void>}
 */
async function toggleWishlist(button) {
    const productId = document
        .getElementById('product_id')
        .getAttribute('product-id');

    const icon = button.querySelector('.wishlist-icon');

    try {
        const response = await fetch('/add_to_wishlist', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: productId }),
        });

        const data = await response.json();

        if (data.status === 'success') {
            if (data.wishlist.includes(parseInt(productId, 10))) {
                icon.classList.add('wishlist-active');
            } else {
                icon.classList.remove('wishlist-active');
            }
        } else {
            alert(`Failed to update wishlist: ${data.message}`);
        }
    } catch (error) {
        console.error('Error updating wishlist:', error);
    }
}

/**
 * Shows an alert when the user is not logged in.
 */
function toggleWishlistAlert() {
    alert('You are not logged in. Please log in to add items to your wishlist.');
}

/**
 * Adds the current product to the cart with the selected quantity.
 */
function addToCart() {
    const productId = document
        .getElementById('product_id')
        .getAttribute('product-id');

    const quantityValue = parseInt(
        document.getElementById('quantity-value').innerText,
        10,
    );

    fetch('/cart/add', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ productId, quantity: quantityValue }),
    })
        .then((response) => response.json())
        .then((data) => {
            if (data.success) {
                alert('Product added to cart successfully!');
                updateCartCount(data.cartCount);
            } else {
                alert(`Failed to add product to cart: ${data.message}`);
            }
        })
        .catch((error) => console.error('Error:', error));
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