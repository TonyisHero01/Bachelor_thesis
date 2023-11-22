const productIdsElement = document.getElementById("productIds");
let ids = JSON.parse(productIdsElement.getAttribute('data-product-ids'));

const NAME_MAX_LENGTH_Element = document.getElementById("NAME_MAX_LENGTH");
const CONTENT_MAX_LENGTH_Element = document.getElementById("CONTENT_MAX_LENGTH");
const APP_DIRECTORY_Element = document.getElementById("APP_DIRECTORY");
const MAX_ARTICLES_COUNT_PER_PAGE_Element = document.getElementById("MAX_ARTICLES_COUNT_PER_PAGE");

const constants = {
    NAME_MAX_LENGTH: NAME_MAX_LENGTH_Element.getAttribute('data-name-max-length'),
    CONTENT_MAX_LENGTH: CONTENT_MAX_LENGTH_Element.getAttribute('data-content-max-length'),
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
const popup = document.getElementById("myPopup");
const cancelButton = document.getElementById("cancelButton");
function openCreateForm() {
    popup.removeAttribute("style");
    cancelButton.removeAttribute("style");
}

function cancelCreateForm() {
    popup.setAttribute("style", "display: none;");
    cancelButton.setAttribute("style", "display: none;");
}

const nameElement = document.getElementById("name");
const numberInStockElement = document.getElementById("number_in_stock");
const priceElement = document.getElementById("price");

function enableCreateButton () {            
    const submitElement = document.getElementById("createButton");
    if (nameElement.value != "" && nameElement.value.length <= 32 & numberInStockElement != "" & priceElement != "") {
        submitElement.removeAttribute("disabled");
    }
    else {
        submitElement.setAttribute("disabled", "disabled");
    }
}
function addLeadingZero(number) {
    return number < 10 ? '0' + number : number;
}
async function create() {
    const currentDate = new Date();
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth() + 1;
    const day = currentDate.getDate();
    const hours = currentDate.getHours();
    const minutes = currentDate.getMinutes();
    const seconds = currentDate.getSeconds();
    const response = await fetch(constants.APP_DIRECTORY + "product-create/",{
        method: "POST",
        headers: {
        'content-type' : 'application/json'
        },
        body: JSON.stringify({
        "name" : nameElement.value,
        "number_in_stock" : numberInStockElement.value,
        "add_time" : addLeadingZero(hours) + ":" + addLeadingZero(minutes) + ":" + addLeadingZero(seconds) + " " + addLeadingZero(day) + "." + addLeadingZero(month) + "." + addLeadingZero(year),
        "price" : priceElement.value
        })
    });
    
    try {
        const responseData = await response.json();
        const id = responseData.id;
        console.log("ID:", id);
        window.location.href = constants.APP_DIRECTORY + "product-edit/" + id;
    } catch (error) {
        console.error("Error parsing JSON response:", error);
    }
}

function show(id) {
    window.location.href = constants.APP_DIRECTORY + "product/" + id;
}
function edit(id) {
    window.location.href = constants.APP_DIRECTORY + "product-edit/" + id;
}
function delete_(id) {
    const response = fetch(constants.APP_DIRECTORY + "product-delete/" + id, {
        method: "DELETE"
    });
    const row = document.getElementById(id);
    row.remove();
    ids.splice(ids.indexOf(id), 1);
    showPage();
}