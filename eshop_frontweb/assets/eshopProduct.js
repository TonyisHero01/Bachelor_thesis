document.addEventListener("DOMContentLoaded", function () {
    const productId = document.getElementById("product_id").getAttribute("product-id");
    const wishlistIcon = document.querySelector(".wishlist-icon");

    fetch(`/wishlist/check/${productId}`)
        .then(response => response.json())
        .then(data => {
            if (data.inWishlist) {
                wishlistIcon.classList.add("wishlist-active");
            } else {
                wishlistIcon.classList.remove("wishlist-active");
            }
        })
        .catch(error => console.error("Error checking wishlist:", error));
});

let quantity = 1;

function increaseQuantity() {
    quantity++;
    document.getElementById("quantity-value").innerText = quantity;
}

function decreaseQuantity() {
    if (quantity > 1) {
        quantity--;
        document.getElementById("quantity-value").innerText = quantity;
    }
}

async function toggleWishlist(button) {
    
    const productId = document.getElementById("product_id").getAttribute("product-id");
    const icon = button.querySelector(".wishlist-icon");

    try {
        let response = await fetch('/add_to_wishlist', {
            method: "POST",
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ "product_id": productId })
        });

        let data = await response.json();
        if (data.status === "success") {
            if (data.wishlist.includes(parseInt(productId))) {
                icon.classList.add("wishlist-active");
            } else {
                icon.classList.remove("wishlist-active");
            }
        } else {
            alert("Failed to update wishlist: " + data.message);
        }
    } catch (error) {
        console.error("Error updating wishlist:", error);
    }
}

function toggleWishlistAlert() {
    alert('You are not logged in. Please log in to add items to your wishlist.');
}

function addToCart() {
    const productId = document.getElementById('product_id').getAttribute('product-id');
    const quantity = parseInt(document.getElementById('quantity-value').innerText);

    fetch('/cart/add', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ productId, quantity })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Product added to cart successfully!');
            updateCartCount(data.cartCount);
        } else {
            alert('Failed to add product to cart: ' + data.message);
        }
    })
    .catch(error => console.error('Error:', error));
}

function updateCartCount(count) {
    const cartCountElements = document.querySelectorAll('.cart-count-bubble');
    cartCountElements.forEach(el => {
        el.textContent = parseInt(count);
    });
}