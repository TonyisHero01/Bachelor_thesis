const languageIdsElement = document.getElementById("languageIds");

let ids = JSON.parse(languageIdsElement.getAttribute('data-language-ids'));

const APP_DIRECTORY_Element = document.getElementById("APP_DIRECTORY");
const MAX_ARTICLES_COUNT_PER_PAGE_Element = document.getElementById("MAX_ARTICLES_COUNT_PER_PAGE");

const constants = {
    APP_DIRECTORY: APP_DIRECTORY_Element.getAttribute('data-app-directory'),
    MAX_ARTICLES_COUNT_PER_PAGE: MAX_ARTICLES_COUNT_PER_PAGE_Element.getAttribute('data-articles-count-per-page')
};

let pageNumber = 0;
const pageCountText = document.getElementById("pageCount");

for (let i=0; i<ids.length; i++) {
    ids[i] = ids[i] + ",";
}

const previousPageButton = document.getElementById("previousPageButton");
const nextPageButton = document.getElementById("nextPageButton");

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
    let lastPageNumber = Math.trunc((ids.length-1) / constants.MAX_ARTICLES_COUNT_PER_PAGE);
    pageCountText.textContent = "Page Count " + (lastPageNumber+1);
    if (pageNumber == lastPageNumber+1) {
        pageNumber--;
    }
    const start = constants.MAX_ARTICLES_COUNT_PER_PAGE * pageNumber;
    const end = start + constants.MAX_ARTICLES_COUNT_PER_PAGE;
    for (let i = 0; i < ids.length; i++) {
        const tableRow = document.getElementById(ids[i].slice(0, -1));
        if (i >= start && i < end) {
            tableRow.style.removeProperty("display");
        }
        else {
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

function edit(id) {
    window.location.href = constants.APP_DIRECTORY + "language-edit/" + id;
}
function delete_(id) {
    fetch(constants.APP_DIRECTORY + "language-delete/" + id, {
        method: "DELETE"
    });
    const row = document.getElementById(id);
    row.remove();
    id = id + ",";
    ids.splice(ids.indexOf(id), 1);
    languageIdsElement.setAttribute('data-language-ids', ids);
    showPage();
}

function createNewLanguage(id) {
    window.location.href = constants.APP_DIRECTORY + "backend-content-translation/";
}