/**
 * Initializes discount batch update behavior after DOM is fully loaded.
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('dp-form');
    const applyButton = document.getElementById('dp-apply');
    const discountInput = document.getElementById('dp-new-discount');
    const selectAll = document.getElementById('dp-select-all');

    if (!form || !applyButton || !discountInput) {
        return;
    }

    const checkboxes = form.querySelectorAll(
        'input[name="productIds[]"]',
    );

    const postUrl = form.dataset.postUrl
        || '/event-manager/update-discounts';
    const csrfToken = form.dataset.csrf || '';

    if (selectAll) {
        /**
         * Handles select-all checkbox state change.
         */
        selectAll.addEventListener('change', () => {
            checkboxes.forEach((cb) => {
                cb.checked = selectAll.checked;
            });
        });
    }

    /**
     * Applies the entered discount to selected products.
     */
    applyButton.addEventListener('click', () => {
        const discount = Number.parseFloat(discountInput.value);

        if (Number.isNaN(discount) || discount < 0 || discount > 100) {
            alert('Please enter a valid discount between 0 and 100');
            return;
        }

        const selectedIds = Array.from(checkboxes)
            .filter((cb) => cb.checked)
            .map((cb) => cb.value)
            .filter((v) => /^\d+$/.test(v));

        if (selectedIds.length === 0) {
            alert('Please select at least one product');
            return;
        }

        if (!csrfToken) {
            alert('Missing CSRF token. Please refresh the page and try again.');
            return;
        }

        const payload = { _token: csrfToken, discounts: {} };

        selectedIds.forEach((id) => {
            payload.discounts[id] = discount;
        });

        fetch(postUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        })
            .then((response) => response.json().catch(() => null))
            .then((data) => {
                if (data && data.success) {
                    alert(data.message || 'Updated successfully');
                    location.reload();
                    return;
                }

                alert(
                    `Update failed: ${
                        (data && data.message)
                            ? data.message
                            : 'Unknown error'
                    }`,
                );
            })
            .catch((error) => {
                console.error(error);
                alert('Server error');
            });
    });
});