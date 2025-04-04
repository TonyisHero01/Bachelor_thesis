document.addEventListener('DOMContentLoaded', function() {
    const filterToggle = document.querySelector('.filter-toggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.filter-overlay');
    const closeFilter = document.querySelector('.close-filter');
    const resetButton = document.querySelector('.reset');
    const applyButton = document.querySelector('.apply');

    // 打开过滤器
    filterToggle.addEventListener('click', function() {
        sidebar.classList.add('active');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    });

    // 关闭过滤器
    function closeFilters() {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    // 添加关闭事件监听器
    closeFilter.addEventListener('click', function(e) {
        e.preventDefault();
        closeFilters();
    });

    overlay.addEventListener('click', function(e) {
        e.preventDefault();
        closeFilters();
    });

    // 重置过滤器
    resetButton.addEventListener('click', function() {
        const checkboxes = document.querySelectorAll('.filter-option input[type="checkbox"]');
        checkboxes.forEach(checkbox => checkbox.checked = false);
    });

    // 应用过滤器
    applyButton.addEventListener('click', function () {
        const selectedColors = Array.from(document.querySelectorAll('input[name="color"]:checked')).map(cb => cb.value);
        const selectedSizes = Array.from(document.querySelectorAll('input[name="size"]:checked')).map(cb => cb.value);

        // ✅ 从页面获取选中的价格范围（你已有的 price slider 显示元素）
        const minPrice = parseFloat(document.getElementById('price-selected-min').textContent) || 0;
        const maxPrice = parseFloat(document.getElementById('price-selected-max').textContent) || Infinity;

        const products = document.querySelectorAll('.product-item');

        products.forEach(product => {
            const productColor = product.dataset.color;
            const productSize = product.dataset.size;
            const discountedPrice = parseFloat(product.dataset.discountedPrice);

            const colorMatch = selectedColors.length === 0 || selectedColors.includes(productColor);
            const sizeMatch = selectedSizes.length === 0 || selectedSizes.includes(productSize);
            const priceMatch = discountedPrice >= minPrice && discountedPrice <= maxPrice;

            const shouldShow = colorMatch && sizeMatch && priceMatch;

            product.style.display = shouldShow ? '' : 'none';
        });

        closeFilters();
    });
});