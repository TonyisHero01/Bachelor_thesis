document.getElementById("confirmOrderBtn").addEventListener("click", function() {
    window.location.href = "{{ path('order_delivery_options', {'id': order.getId()}) }}";
});

document.getElementById("cancelOrderBtn").addEventListener("click", function() {
    fetch("{{ path('cancel_order', {'id': order.getId()}) }}", {
        method: "POST",
        headers: { "Content-Type": "application/json" }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert("Order canceled!");
            window.location.href = "{{ path('customer_cart') }}";
        } else {
            alert("Error canceling order: " + data.message);
        }
    })
    .catch(error => console.error("Error:", error));
});