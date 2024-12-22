let quantity = 1;

function increaseQuantity() {
    quantity++;
    document.getElementById("quantity-value").innerText = quantity;
}

function decreaseQuantity() {
    if (quantity > 1) { // 避免数量小于 1
        quantity--;
        document.getElementById("quantity-value").innerText = quantity;
    }
}
function toggleWishlist(button) {
    const icon = button.querySelector('.wishlist-icon');
    icon.classList.toggle('active');
}