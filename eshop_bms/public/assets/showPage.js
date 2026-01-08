let pageNumber = 0;
let perPage = 10;
let ids = [];
let pageCountText;
let previousPageButton;
let nextPageButton;

document.addEventListener('DOMContentLoaded', () => {
    pageCountText = document.getElementById('pageCount');
    previousPageButton = document.getElementById('previousPageButton');
    nextPageButton = document.getElementById('nextPageButton');

    const maxEl = document.getElementById('MAX_ARTICLES_COUNT_PER_PAGE');
    if (maxEl) {
        const raw = maxEl.dataset.value ?? maxEl.getAttribute('data-value') ?? '';
        const parsed = parseInt(raw, 10);

        if (!Number.isNaN(parsed) && parsed > 0) {
            perPage = parsed;
        }
    }

    const idsEl = document.getElementById('ids');
    if (!idsEl) {
        console.warn('No #ids element found, pagination disabled.');
        return;
    }

    const rawIds = idsEl.dataset.ids
        || idsEl.getAttribute('data-ids')
        || '';

    ids = rawIds
        .split(',')
        .map((s) => s.trim())
        .filter((s) => s.length > 0);

    if (ids.length === 0) {
        pageCountText.textContent = 'Page 0 / 0';
        previousPageButton.style.visibility = 'hidden';
        nextPageButton.style.visibility = 'hidden';
        return;
    }

    previousPageButton.addEventListener('click', () => changePage(-1));
    nextPageButton.addEventListener('click', () => changePage(1));

    renderPage();
});

/**
 * Changes the current page by the given delta and re-renders the page.
 *
 * @param {number} delta - Page offset (e.g. -1 or 1).
 */
function changePage(delta) {
    pageNumber += delta;
    renderPage();
}

/**
 * Renders the current page, updates pagination controls
 * and toggles row visibility based on page size.
 */
function renderPage() {
    const lastPageNumber = Math.max(
        0,
        Math.ceil(ids.length / perPage) - 1,
    );

    if (pageNumber < 0) pageNumber = 0;
    if (pageNumber > lastPageNumber) pageNumber = lastPageNumber;

    pageCountText.textContent = `Page ${pageNumber + 1} / ${lastPageNumber + 1}`;

    const start = perPage * pageNumber;
    const end = start + perPage;

    ids.forEach((id, index) => {
        const row = document.getElementById(id);
        if (!row) return;

        if (index >= start && index < end) {
            row.style.removeProperty('display');
        } else {
            row.style.display = 'none';
        }
    });

    previousPageButton.style.visibility =
        pageNumber === 0 ? 'hidden' : 'visible';
    nextPageButton.style.visibility =
        pageNumber === lastPageNumber ? 'hidden' : 'visible';
}