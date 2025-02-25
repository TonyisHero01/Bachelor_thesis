function backToHomePage() {
    window.location.href = '/homepage';
}

document.addEventListener("DOMContentLoaded", function() {
    fetch('/cart/count')
        .then(response => response.json())
        .then(data => {
            updateCartCount(data.cartCount);
        })
        .catch(error => console.error('Error fetching cart count:', error));
});

// 更新购物车图标上的数量
function updateCartCount(count) {
    const cartCountElement = document.querySelector('[data-cart-count]');
    if (cartCountElement) {
        cartCountElement.textContent = count;
        cartCountElement.setAttribute('data-cart-count', count);
    }
}

document.addEventListener("DOMContentLoaded", function () {
    let searchButton = document.getElementById("search-button");
    let searchInput = document.getElementById("search-input");

    if (!searchButton || !searchInput) {
        console.error("Search input or button not found in DOM.");
        return;
    }

    searchButton.addEventListener("click", function () {
        let query = searchInput.value.trim();
        if (!query) {
            alert("Please enter a search term!");
            return;
        }

        fetch("/search", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({ query: query })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log("Search results:", data);
            if (data.results.length === 0) {
                alert("No products found.");
            } else {
                window.location.href = `/search/results?ids=${data.results.join(",")}`;
            }
        })
        .catch(error => {
            console.error("Error:", error);
            alert("An error occurred while searching. Please try again.");
        });
    });
});