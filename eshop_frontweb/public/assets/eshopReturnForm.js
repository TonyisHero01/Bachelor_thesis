/**
 * Submits a return request for the given order.
 *
 * @param {string|number} orderId - Order identifier.
 */
function submitReturnRequest(orderId) {
    const selectedItems = [];

    document
        .querySelectorAll("input[name='selectedItems']:checked")
        .forEach((item) => {
            const quantityInput = document.querySelector(
                `input[name='quantity-${item.id.split('-')[1]}']`,
            );
            const skuWithQuantity = `${item.value} x${quantityInput.value}`;
            selectedItems.push(skuWithQuantity);
        });

    if (selectedItems.length === 0) {
        alert('⚠️ Please select at least one item to return.');
        return;
    }

    const reasonSelect = document.getElementById('returnReason');

    if (reasonSelect.value === '') {
        alert('⚠️ Please select a return reason.');
        return;
    }

    const data = {
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        name: document.getElementById('name').value,
        reason: reasonSelect.value,
        message: document.getElementById('returnMessage').value,
        items: selectedItems,
    };

    fetch(`/order/${orderId}/submit-return`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
    })
        .then((response) => response.json())
        .then((res) => {
            if (res.success) {
                alert('✅ Return request submitted!');
                window.location.href = '/customer/orders';
            } else {
                alert(`❌ Error: ${res.message}`);
            }
        })
        .catch((error) => console.error('Error:', error));
}