document.addEventListener('DOMContentLoaded', () => {
    const applyButton = document.getElementById('apply-discount');
    const discountInput = document.getElementById('new-discount');
    const checkboxes = document.querySelectorAll('input[name="productIds[]"]');
    const selectAll = document.getElementById('select-all');

    if (selectAll) {
        selectAll.addEventListener('change', () => {
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
        });
    }

    applyButton?.addEventListener('click', () => {
        const discount = parseFloat(discountInput.value);
        if (isNaN(discount) || discount < 0 || discount > 100) {
            alert("Please enter a valid discount between 0 and 100");
            return;
        }

        const selected = Array.from(checkboxes).filter(cb => cb.checked).map(cb => cb.value);
        if (selected.length === 0) {
            alert("Please select at least one product");
            return;
        }

        const payload = {
            discounts: {}
        };
        selected.forEach(id => {
            payload.discounts[id] = discount;
        });

        fetch('/event-manager/update-discounts', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Update failed: ' + data.message);
            }
        })
        .catch(error => {
            console.error(error);
            alert('Server error');
        });
    });
});