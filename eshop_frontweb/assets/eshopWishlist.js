document.addEventListener("DOMContentLoaded", function () {
    // **Remove product from wishlist**
    document.querySelectorAll(".remove-from-wishlist").forEach(button => {
        button.addEventListener("click", function () {
            let productId = this.getAttribute("data-product-id");
            fetch(`/wishlist/remove/${productId}`, {
                method: "POST",
                headers: { "X-Requested-With": "XMLHttpRequest" }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.closest("tr").remove();
                } else {
                    alert("Failed to remove");
                }
            })
            .catch(error => console.error("Error:", error));
        });
    });

    // **Add to cart and update cart count**
    document.querySelectorAll(".add-to-cart").forEach(button => {
        button.addEventListener("click", function () {
            let productId = this.getAttribute("data-product-id");
            let quantity = parseInt(document.getElementById("wishlist-quantity-" + productId).textContent);

            fetch("/cart/add", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ productId: productId, quantity: quantity })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert("Added to cart successfully!");
                    updateCartCount(data.cartCount); // ✅ Update cart icon count
                } else {
                    alert("Failed to add: " + data.message);
                }
            })
            .catch(error => console.error("Error:", error));
        });
    });
});

// **Update quantity**
function updateQuantity(productId, change) {
    let quantityElement = document.getElementById("wishlist-quantity-" + productId);
    let currentQuantity = parseInt(quantityElement.textContent);
    let newQuantity = currentQuantity + change;

    if (newQuantity < 1) return; // Don't allow less than 1
    
    quantityElement.textContent = newQuantity;
}

// **Update cart count**
function updateCartCount(count) {
    const cartCountElements = document.querySelectorAll('.cart-count-bubble');
    cartCountElements.forEach(el => {
        el.textContent = parseInt(count);
    });
}