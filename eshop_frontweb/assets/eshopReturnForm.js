function submitReturnRequest(orderId) {
    let selectedItems = [];
    document.querySelectorAll("input[name='selectedItems']:checked").forEach(item => {
        let quantityInput = document.querySelector(`input[name='quantity-${item.id.split('-')[1]}']`);
        let skuWithQuantity = `${item.value} x${quantityInput.value}`;
        selectedItems.push(skuWithQuantity);
    });

    if (selectedItems.length === 0) {
        alert("⚠️ Please select at least one item to return.");
        return;
    }

    let reasonSelect = document.getElementById("returnReason");
    if (reasonSelect.value === "") {
        alert("⚠️ Please select a return reason.");
        return;
    }

    let data = {
        email: document.getElementById("email").value,
        phone: document.getElementById("phone").value,
        name: document.getElementById("name").value,
        reason: reasonSelect.value,
        message: document.getElementById("returnMessage").value,
        items: selectedItems
    };

    fetch(`/order/${orderId}/submit-return`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert("✅ Return request submitted!");
            window.location.href = "/customer/orders";
        } else {
            alert("❌ Error: " + data.message);
        }
    })
    .catch(error => console.error("Error:", error));
}