function updateCart(cartItemId, newQuantity) {
    if (newQuantity < 1) return;

    fetch('/cart/update/' + cartItemId, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ quantity: newQuantity })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCartCount(data.cartCount);
            location.reload(); // 刷新购物车页面
        } else {
            alert('Error updating cart: ' + data.message);
        }
    })
    .catch(error => console.error('Error:', error));
}