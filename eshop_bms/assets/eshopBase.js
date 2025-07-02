function backToHomePage() {
    window.location.href = '/homepage';
}

function handleSearch(event) {
    event.preventDefault();
    const query = document.getElementById('searchInput').value.trim();
    if (!query) return;

    document.getElementById('loadingSpinner').style.display = 'block';

    setTimeout(() => {
        window.location.href = '/search/results?query=' + encodeURIComponent(query);
    }, 2000);
}