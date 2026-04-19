/**
 * Redirects the user to the home page.
 */
function backToHomePage() {
    window.location.href = '/homepage';
}

/**
 * Handles the search form submission and redirects to the results page.
 *
 * @param {Event} event - Form submit event.
 */
function handleSearch(event) {
    event.preventDefault();

    const query = document.getElementById('searchInput').value.trim();
    if (!query) return;

    document.getElementById('loadingSpinner').style.display = 'block';

    setTimeout(() => {
        window.location.href = `/search/results?query=${encodeURIComponent(query)}`;
    }, 2000);
}