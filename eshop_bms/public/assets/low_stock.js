(function () {
    const selectAll = document.getElementById('select_all');
    const checkboxes = document.querySelectorAll('.product-checkbox');
    const selectedCountEl = document.getElementById('selected-count');
    const submitBtn = document.getElementById('batch_submit');

    if (!checkboxes.length) {
        return;
    }

    /**
     * Updates the selected items count display and submit button state.
     */
    function updateCount() {
        const count = Array.from(checkboxes)
            .filter((cb) => cb.checked)
            .length;

        if (selectedCountEl) {
            selectedCountEl.textContent = `${count} selected`;
        }

        if (submitBtn) {
            submitBtn.disabled = count === 0;
        }
    }

    if (selectAll) {
        /**
         * Handles select-all checkbox state change.
         */
        selectAll.addEventListener('change', function () {
            checkboxes.forEach((cb) => {
                cb.checked = selectAll.checked;
            });
            updateCount();
        });
    }

    checkboxes.forEach((cb) => {
        /**
         * Handles individual checkbox state change.
         */
        cb.addEventListener('change', function () {
            if (!this.checked && selectAll && selectAll.checked) {
                selectAll.checked = false;
            }
            updateCount();
        });
    });

    updateCount();
}());