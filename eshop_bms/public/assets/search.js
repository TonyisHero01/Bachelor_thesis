const searchInputElement = document.getElementById('searchInput');

async function search_() {
  const spinner = document.getElementById('loadingSpinner');
  const localeEl = document.getElementById('current-locale');
  const locale = (localeEl && localeEl.value) ? localeEl.value : 'en';

  try {
    if (!searchInputElement) {
      throw new Error('searchInput element not found');
    }

    const query = (searchInputElement.value || '').trim();
    if (!query) {
      alert('Please enter a search query.');
      return;
    }

    if (spinner) spinner.style.display = 'block';

    const url = `/bms/search?_locale=${encodeURIComponent(locale)}`;

    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ query }),
    });

    const text = await response.text();
    let data = null;
    try {
      data = text ? JSON.parse(text) : null;
    } catch (_) {
      // ignore JSON parse error
    }

    if (!response.ok) {
      console.error('Search failed response:', {
        status: response.status,
        statusText: response.statusText,
        body: text,
      });
      alert(data?.error || `Search failed (${response.status}). See console for details.`);
      return;
    }

    if (!data || !Array.isArray(data.results)) {
      console.error('Unexpected search response:', text);
      alert('Search failed: unexpected response format.');
      return;
    }

    window.location.href = `/bms/results?_locale=${encodeURIComponent(locale)}`;
  } catch (error) {
    console.error('Search failed:', error);
    alert('Search failed. Please try again.');
  } finally {
    if (spinner) spinner.style.display = 'none';
  }
}