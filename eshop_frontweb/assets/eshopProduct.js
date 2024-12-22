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
async function toggleWishlist(button) {
    const icon = button.querySelector('.wishlist-icon');
    icon.classList.toggle('active');
    var product_id_div = document.getElementById("product_id");
    var product_id = product_id_div.getAttribute("product-id");
    //var productListRoute = document.getElementById('routeData').getAttribute("data-edit-route")
    var response = await fetch('/add_to_wishlist',{
        method: "POST",
        headers: {
            'content-type' : 'application/json'
        },
        body: JSON.stringify({
            "product_id" : product_id,
        })
    });
}
function toggleWishlistAlert() {
    alert('You are not logged, please login!');
}