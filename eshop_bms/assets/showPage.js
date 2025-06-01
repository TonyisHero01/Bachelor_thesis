let pageNumber = 0;
let max_articles_count_per_page_value;
let ids;
let pageCountText;
let previousPageButton;
let nextPageButton;

window.onload = function() {
    pageCountText = document.getElementById("pageCount");
    previousPageButton = document.getElementById("previousPageButton");
    nextPageButton = document.getElementById("nextPageButton");

    max_articles_count_per_page_value = parseInt(document.getElementById('MAX_ARTICLES_COUNT_PER_PAGE').getAttribute('max_articles_count_per_page_value'));
    ids = document.getElementById('ids').getAttribute('data-ids').split(',');
    showPage();

    previousPageButton.onclick = previousPage;
    nextPageButton.onclick = nextPage;
};

function nextPage() {
    pageNumber++;
    showPage();
}

function previousPage() {
    pageNumber--;
    showPage();
}

function showPage() {
    let lastPageNumber = Math.trunc((ids.length - 1) / max_articles_count_per_page_value);
    pageCountText.textContent = "Page Count " + (lastPageNumber + 1);

    if (pageNumber > lastPageNumber) {
        pageNumber--;
    }

    let start = max_articles_count_per_page_value * pageNumber;
    let end = start + max_articles_count_per_page_value;

    for (let i = 0; i < ids.length - 1; i++) {
        let tableRow = document.getElementById(ids[i]);
        if (i >= start && i < end) {
            tableRow.style.removeProperty("display");
        } else {
            tableRow.style.display = "none";
        }
    }

    previousPageButton.style.visibility = (pageNumber == 0) ? "hidden" : "visible";
    nextPageButton.style.visibility = (pageNumber == lastPageNumber) ? "hidden" : "visible";
}