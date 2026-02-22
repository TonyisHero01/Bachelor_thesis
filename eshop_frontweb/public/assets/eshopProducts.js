document.addEventListener('DOMContentLoaded', function () {
    const filterToggle = document.getElementById('category-filter-toggle');
    const sidebar = document.getElementById('category-sidebar');
    const overlay = document.getElementById('category-filter-overlay');
    const closeFilter = document.getElementById('category-filter-close');
    const resetButton = document.getElementById('reset-filters');
    const applyButton = document.getElementById('apply-filters');

    const sortSelect = document.getElementById('sort-select');
    const grid = document.querySelector('.product-grid');

    /**
     * Returns the currently active currency code.
     *
     * @returns {string}
     */
    function getActiveCurrency() {
        const c = (window.ACTIVE_CURRENCY || 'EUR').toString().toUpperCase();
        return c || 'EUR';
    }

    /**
     * Returns the currency rate map.
     *
     * @returns {Object}
     */
    function getCurrenciesMap() {
        const map = window.CURRENCIES_MAP || {};
        return map && typeof map === 'object' ? map : {};
    }

    /**
     * Returns the exchange rate for the active currency.
     *
     * @returns {number}
     */
    function getRate() {
        const code = getActiveCurrency();
        const map = getCurrenciesMap();
        const raw = map[code];

        const rate = Number(raw);
        if (Number.isFinite(rate) && rate > 0) return rate;

        const eur = Number(map.EUR);
        if (Number.isFinite(eur) && eur > 0) return eur;

        return 1;
    }

    /**
     * Returns the product price converted to the active currency.
     *
     * @param {HTMLElement} item
     * @returns {number}
     */
    function getPriceActive(item) {
        const v = item.dataset.discountedPrice ?? item.dataset.price ?? '0';
        const base = parseFloat(v);
        const baseNum = Number.isFinite(base) ? base : 0;

        const priceActive = baseNum * getRate();
        return Number.isFinite(priceActive) ? priceActive : 0;
    }

    /**
     * Parses product creation timestamp.
     *
     * @param {HTMLElement} item
     * @returns {number}
     */
    function parseCreatedAt(item) {
        const s = item.dataset.createdAt;
        const t = s ? Date.parse(s) : 0;
        return Number.isFinite(t) ? t : 0;
    }

    if (sortSelect && grid) {
        Array.from(grid.querySelectorAll('.product-item')).forEach((el, idx) => {
            el.dataset.originalIndex = String(idx);
        });

        /**
         * Sorts products based on selected mode.
         *
         * @param {string} mode
         */
        const doSort = (mode) => {
            const items = Array.from(grid.querySelectorAll('.product-item'));

            if (mode === 'price_asc') {
                items.sort((a, b) => getPriceActive(a) - getPriceActive(b));
            } else if (mode === 'price_desc') {
                items.sort((a, b) => getPriceActive(b) - getPriceActive(a));
            } else if (mode === 'latest') {
                items.sort((a, b) => parseCreatedAt(b) - parseCreatedAt(a));
            } else {
                items.sort(
                    (a, b) => (parseInt(a.dataset.originalIndex, 10) || 0)
                        - (parseInt(b.dataset.originalIndex, 10) || 0),
                );
            }

            items.forEach((el) => grid.appendChild(el));
        };

        sortSelect.addEventListener('change', (e) => {
            doSort(e.target.value);
        });

        doSort(sortSelect.value);
    } else {
        console.warn('[Sort] missing elements', {
            sortSelect: !!sortSelect,
            grid: !!grid,
        });
    }

    if (
        !filterToggle
        || !sidebar
        || !overlay
        || !closeFilter
        || !resetButton
        || !applyButton
    ) {
        console.warn('[Filter] missing elements', {
            filterToggle: !!filterToggle,
            sidebar: !!sidebar,
            overlay: !!overlay,
            closeFilter: !!closeFilter,
            resetButton: !!resetButton,
            applyButton: !!applyButton,
        });
        return;
    }

    /**
     * Opens the filter sidebar.
     */
    function openFilters() {
        sidebar.classList.add('active');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    /**
     * Closes the filter sidebar.
     */
    function closeFilters() {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    filterToggle.addEventListener('click', function (e) {
        e.preventDefault();
        openFilters();
    });

    closeFilter.addEventListener('click', function (e) {
        e.preventDefault();
        closeFilters();
    });

    overlay.addEventListener('click', function (e) {
        e.preventDefault();
        closeFilters();
    });

    resetButton.addEventListener('click', function (e) {
        e.preventDefault();
        document
            .querySelectorAll('.filter-option input[type="checkbox"]')
            .forEach((cb) => {
                cb.checked = false;
            });
    });

    applyButton.addEventListener('click', function (e) {
        e.preventDefault();

        const selectedColors = Array.from(
            document.querySelectorAll('input[name="color"]:checked'),
        ).map((cb) => cb.value);

        const selectedSizes = Array.from(
            document.querySelectorAll('input[name="size"]:checked'),
        ).map((cb) => cb.value);

        const minEl = document.getElementById('price-selected-min');
        const maxEl = document.getElementById('price-selected-max');

        const minPrice = minEl ? parseFloat(minEl.textContent) || 0 : 0;
        const maxPrice = maxEl ? parseFloat(maxEl.textContent) || Infinity : Infinity;

        document.querySelectorAll('.product-item').forEach((product) => {
            const productColor = product.dataset.color || '';
            const productSize = product.dataset.size || '';
            const priceActive = getPriceActive(product);

            const colorMatch = selectedColors.length === 0
                || selectedColors.includes(productColor);
            const sizeMatch = selectedSizes.length === 0
                || selectedSizes.includes(productSize);
            const priceMatch = priceActive >= minPrice && priceActive <= maxPrice;

            product.style.display = (colorMatch && sizeMatch && priceMatch)
                ? ''
                : 'none';
        });

        closeFilters();
    });
});