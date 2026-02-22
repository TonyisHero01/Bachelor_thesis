/**
 * Initializes mobile/desktop search behavior after the DOM is fully loaded.
 */
document.addEventListener('DOMContentLoaded', function () {
    const searchButton = document.querySelector('.header_right .search__button');
    const mobileSearchOverlay = document.getElementById('mobile-search-overlay');
    const closeSearchButton = document.querySelector('.close-search');
    const mobileSearchInput = document.querySelector('.mobile-search__input');
    const mobileSearchButton = document.querySelector('.mobile-search__button');
    const mobileSearchResults = document.getElementById('mobile-search-results');
    const desktopSearchInput = document.querySelector('.header_right .search__input');
    const isMobile = window.innerWidth <= 768;

    searchButton.addEventListener('click', function (e) {
        e.preventDefault();

        if (isMobile) {
            mobileSearchOverlay.style.display = 'block';
            mobileSearchInput.focus();
        } else {
            performSearch();
        }
    });

    closeSearchButton.addEventListener('click', function () {
        mobileSearchOverlay.style.display = 'none';
        mobileSearchInput.value = '';
        mobileSearchResults.innerHTML = '';
    });

    mobileSearchOverlay.addEventListener('click', function (e) {
        if (e.target === mobileSearchOverlay) {
            mobileSearchOverlay.style.display = 'none';
            mobileSearchInput.value = '';
            mobileSearchResults.innerHTML = '';
        }
    });

    /**
     * Performs a search request or redirects to search results page.
     */
    function performSearch() {
        const searchTerm = mobileSearchInput.value.trim();
        const spinner = document.getElementById('loadingSpinner');

        if (!isMobile) {
            const query = desktopSearchInput.value.trim();

            if (query) {
                spinner.style.display = 'block';

                requestAnimationFrame(() => {
                    setTimeout(() => {
                        window.location.href = `/search/results?query=${encodeURIComponent(query)}`;
                    }, 50);
                });
            }
        }

        if (!searchTerm) {
            mobileSearchResults.innerHTML = '<p class="no-results">Please enter your search keywords</p>';
            return;
        }

        spinner.style.display = 'block';

        fetch('{{ path("search") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ query: searchTerm }),
        })
            .then((response) => response.json())
            .then((data) => {
                spinner.style.display = 'none';
                mobileSearchResults.innerHTML = '';

                if (data.error) {
                    mobileSearchResults.innerHTML = `<p class="no-results">${data.error}</p>`;
                    return;
                }

                if (data.results && data.results.length > 0) {
                    window.location.href = '{{ path("search_results") }}';
                } else {
                    mobileSearchResults.innerHTML = '<p class="no-results">No related products found</p>';
                }
            })
            .catch((error) => {
                console.error('Error:', error);
                spinner.style.display = 'none';
                mobileSearchResults.innerHTML = '<p class="error">Search error, please try again later</p>';
            });
    }

    mobileSearchButton.addEventListener('click', performSearch);

    mobileSearchInput.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            performSearch();
        }
    });

    desktopSearchInput.addEventListener('keypress', function (e) {
        if (e.key === 'Enter' && !isMobile) {
            e.preventDefault();

            const query = this.value.trim();
            if (query) {
                window.location.href = `/search/results?query=${encodeURIComponent(query)}`;
            }
        }
    });

    window.addEventListener('resize', function () {
        const newIsMobile = window.innerWidth <= 768;

        if (newIsMobile !== isMobile) {
            if (!newIsMobile) {
                mobileSearchOverlay.style.display = 'none';
                mobileSearchInput.value = '';
                mobileSearchResults.innerHTML = '';
            }
        }
    });
});