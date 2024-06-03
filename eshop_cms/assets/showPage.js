
var pageNumber = 0;
var pageCountText = document.getElementById("pageCount");

var max_articles_count_per_page_value = parseInt(document.getElementById('MAX_ARTICLES_COUNT_PER_PAGE').getAttribute('max_articles_count_per_page_value'))

var ids = document.getElementById('productIds').getAttribute('data-ids').split(',');


var previousPageButton = document.getElementById("previousPageButton");
var nextPageButton = document.getElementById("nextPageButton");
console.log(Math.trunc((ids.length-1) / max_articles_count_per_page_value));
showPage();
function nextPage() {
    pageNumber++;
    showPage();
}
function previousPage() {
    pageNumber--;
    showPage();
}

function showPage() {
    var lastPageNumber = Math.trunc((ids.length-1) / max_articles_count_per_page_value);
    console.log(ids)
    pageCountText.textContent = "Page Count " + (lastPageNumber+1);
    if (pageNumber == lastPageNumber+1) {
        pageNumber--;
    }
    let start = max_articles_count_per_page_value * pageNumber;
    let end = start + max_articles_count_per_page_value;
    for (let i = 0; i < ids.length-1; i++) {
        let tableRow = document.getElementById(ids[i]);
        if (i >= start && i < end) {
            //tableRow.removeAttribute("style");
            tableRow.style.removeProperty("display");
        }
        else {
            //tableRow.setAttribute("style", "display: none;");
            tableRow.style.display = "none";
        }
    }
    if (pageNumber == 0) {
        previousPageButton.style.visibility = "hidden";
    }
    else {
        previousPageButton.style.visibility = "visible";
    }
    if (pageNumber == lastPageNumber) {
        nextPageButton.style.visibility = "hidden";
    }
    else {
        nextPageButton.style.visibility = "visible";
    }
}