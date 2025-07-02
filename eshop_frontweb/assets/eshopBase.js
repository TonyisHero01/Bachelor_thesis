function backToHomePage() {
    window.location.href = '/homepage';
}

function updateCartCount(count) {
    const cartCountElement = document.querySelector('[data-cart-count]');
    if (cartCountElement) {
        cartCountElement.textContent = count;
        cartCountElement.setAttribute('data-cart-count', count);
    }
}

function getRGB(color) {
    let r, g, b;
    if (color.startsWith("#")) {
        r = parseInt(color.substr(1, 2), 16);
        g = parseInt(color.substr(3, 2), 16);
        b = parseInt(color.substr(5, 2), 16);
    } else if (color.startsWith("rgb")) {
        const rgbValues = color.match(/\d+/g);
        if (rgbValues) {
            r = parseInt(rgbValues[0]);
            g = parseInt(rgbValues[1]);
            b = parseInt(rgbValues[2]);
        }
    } else {
        return { r: 255, g: 255, b: 255 };
    }
    return { r, g, b };
}

function applyFilters() {
    let queryParams = new URLSearchParams();
    document.querySelectorAll('.color-options input:checked').forEach(input => {
        queryParams.append("color", input.value);
    });
    document.querySelectorAll('.size-options input:checked').forEach(input => {
        queryParams.append("size", input.value);
    });
    if (typeof priceSlider !== "undefined") {
        const priceValues = priceSlider.get();
        queryParams.append("price_min", priceValues[0]);
        queryParams.append("price_max", priceValues[1]);
    }
    const newUrl = window.location.pathname + "?" + queryParams.toString();
    window.location.href = newUrl;
}

document.addEventListener("DOMContentLoaded", function() {
    const menuToggle = document.querySelector('.menu-toggle');
    const mobileCategories = document.querySelector('.mobile-categories');
    const overlay = document.querySelector('.overlay');
    const closeCategories = document.querySelector('.close-categories');
    const body = document.body;

    function toggleCategories() {
        if (mobileCategories && overlay) {
            mobileCategories.classList.toggle('active');
            overlay.classList.toggle('active');
            body.style.overflow = mobileCategories.classList.contains('active') ? 'hidden' : '';
            if (menuToggle) {
                menuToggle.style.display = mobileCategories.classList.contains('active') ? 'none' : 'block';
            }
        }
    }

    if (menuToggle) {
        menuToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleCategories();
        });
    }

    if (closeCategories) {
        closeCategories.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleCategories();
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleCategories();
        });
    }

    fetch('/cart/count')
        .then(response => response.json())
        .then(data => {
            updateCartCount(data.cartCount);
        })
        .catch(error => console.error('Error fetching cart count:', error));

    const searchButton = document.querySelector('.header_right .search__button');
    const mobileSearchOverlay = document.getElementById('mobile-search-overlay');
    const closeSearchButton = document.querySelector('.close-search');
    const mobileSearchInput = document.querySelector('.mobile-search__input');
    const mobileSearchButton = document.querySelector('.mobile-search__button');
    const mobileSearchResults = document.getElementById('mobile-search-results');
    const desktopSearchInput = document.querySelector('.header_right .search__input');

    searchButton.addEventListener('click', function(e) {
        e.preventDefault();
        if (window.innerWidth <= 768) {
            mobileSearchOverlay.style.display = 'block';
            mobileSearchInput.focus();
        } else {
            const query = desktopSearchInput.value.trim();
            if (query) {
                window.location.href = `/search/results?query=${encodeURIComponent(query)}`;
            } else {
                desktopSearchInput.focus();
            }
        }
    });

    closeSearchButton.addEventListener('click', function() {
        mobileSearchOverlay.style.display = 'none';
        mobileSearchInput.value = '';
        mobileSearchResults.innerHTML = '';
    });

    mobileSearchOverlay.addEventListener('click', function(e) {
        if (e.target === mobileSearchOverlay) {
            mobileSearchOverlay.style.display = 'none';
            mobileSearchInput.value = '';
            mobileSearchResults.innerHTML = '';
        }
    });

    function performSearch() {
        const searchTerm = mobileSearchInput.value.trim();
        if (!searchTerm) {
            mobileSearchResults.innerHTML = '<p class="no-results">Please enter your search keywords</p>';
            return;
        }

        fetch('/search', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ query: searchTerm })
        })
        .then(response => response.json())
        .then(data => {
            mobileSearchResults.innerHTML = '';
            if (data.results && data.results.length > 0) {
                window.location.href = `/search/results?query=${encodeURIComponent(searchTerm)}`;
            } else {
                mobileSearchResults.innerHTML = '<p class="no-results">No related products found</p>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mobileSearchResults.innerHTML = '<p class="error">Search error, please try again later</p>';
        });
    }

    mobileSearchButton.addEventListener('click', performSearch);

    mobileSearchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            performSearch();
        }
    });

    desktopSearchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && window.innerWidth > 768) {
            e.preventDefault();
            const query = this.value.trim();
            if (query) {
                window.location.href = `/search/results?query=${encodeURIComponent(query)}`;
            }
        }
    });

    document.querySelectorAll(".checkmark").forEach((checkmark) => {
        const bgColor = window.getComputedStyle(checkmark).backgroundColor;
        if (bgColor) {
            const rgb = getRGB(bgColor);
            const brightness = (rgb.r * 0.299 + rgb.g * 0.587 + rgb.b * 0.114);
            if (brightness > 186) {
                checkmark.classList.add("light");
            } else {
                checkmark.classList.add("dark");
            }
        }
    });

    const priceSelectedMin = document.getElementById("price-selected-min");
    const priceSelectedMax = document.getElementById("price-selected-max");
    const priceSlider = document.getElementById("price-slider");

    if (priceSlider && priceSelectedMin && priceSelectedMax) {
        const productPrices = Array.from(document.querySelectorAll(".product-item"))
            .map(p => parseFloat(p.dataset.price))
            .filter(p => !isNaN(p));
        const minPrice = 0;
        const maxPrice = Math.max(...productPrices, 1000);
    
        noUiSlider.create(priceSlider, {
            start: [minPrice, maxPrice],
            connect: true,
            range: {
                'min': minPrice,
                'max': maxPrice
            },
            step: 1,
            tooltips: false,
            format: {
                to: value => Math.round(value),
                from: value => Number(value)
            }
        });
    
        priceSlider.noUiSlider.on("update", function(values) {
            priceSelectedMin.textContent = Math.round(values[0]);
            priceSelectedMax.textContent = Math.round(values[1]);
        });
    }

    const resetFilters = document.getElementById("reset-filters");
    const applyFiltersBtn = document.getElementById("apply-filters");

    if (resetFilters) {
        resetFilters.addEventListener("click", function() {
            document.querySelectorAll('.filter-section input[type="checkbox"]').forEach((checkbox) => {
                checkbox.checked = false;
            });

            if (typeof priceSlider !== "undefined") {
                priceSlider.noUiSlider.set([0, 1000]);
            }

            document.getElementById("price-selected-min").textContent = "0";
            document.getElementById("price-selected-max").textContent = "1000";

            document.querySelectorAll(".filter-button").forEach((button) => {
                button.classList.remove("active");
            });

            applyFilters();
        });
    }

    if (applyFiltersBtn) {
        applyFiltersBtn.addEventListener("click", function() {
    const selectedColors = Array.from(document.querySelectorAll('input[name="color"]:checked')).map(el => el.value);
    const selectedSizes = Array.from(document.querySelectorAll('input[name="size"]:checked')).map(el => el.value);
    const minPrice = parseFloat(document.getElementById("price-selected-min").textContent);
    const maxPrice = parseFloat(document.getElementById("price-selected-max").textContent);
    
    document.querySelectorAll(".product-item").forEach(product => {
        const productColor = String(product.getAttribute("data-color"));
        const productSize = product.getAttribute("data-size");
        const productPrice = parseFloat(product.getAttribute("data-price"));

        let matchesColor = selectedColors.length === 0 || selectedColors.includes(productColor);
        let matchesSize = selectedSizes.length === 0 || selectedSizes.includes(productSize);
        let matchesPrice = productPrice >= minPrice && productPrice <= maxPrice;

                product.style.display = (matchesColor && matchesSize && matchesPrice) ? "block" : "none";
            });
        });
    }
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            if (mobileCategories && overlay) {
                mobileCategories.classList.remove('active');
                overlay.classList.remove('active');
                body.style.overflow = '';
            }
        }
    });
});

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.footer-links a.static-link').forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            const label = link.innerText.trim();
            const url = new URL(link.href, window.location.origin);
            url.searchParams.set('title', label);
            window.location.href = url.toString();
        });
    });
});