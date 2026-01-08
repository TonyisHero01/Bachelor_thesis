const searchInputElement = document.getElementById('searchInput');

/**
 * Sends a search request to the server and redirects to the results page.
 *
 * @returns {Promise<void>}
 */
async function search_() {
    const spinner = document.getElementById('loadingSpinner');
    const locale = document.getElementById('current-locale').value;

    try {
        spinner.style.display = 'block';

        const response = await fetch('/bms/search', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                query: searchInputElement.value,
            }),
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const jsonResponse = await response.json();
        const results = jsonResponse.results;

        window.location.href = `/bms/results?_locale=${locale}`;
    } catch (error) {
        console.error('Search failed:', error);
        alert('Search failed. Please try again.');
    } finally {
        spinner.style.display = 'none';
    }
}