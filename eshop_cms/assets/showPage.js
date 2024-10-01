var pageNumber = 0;
var max_articles_count_per_page_value;
var ids;
var pageCountText;
var previousPageButton;
var nextPageButton;

window.onload = function() {
    // 获取 DOM 元素
    pageCountText = document.getElementById("pageCount");
    previousPageButton = document.getElementById("previousPageButton");
    nextPageButton = document.getElementById("nextPageButton");

    // 确保元素存在并获取它们的值
    max_articles_count_per_page_value = parseInt(document.getElementById('MAX_ARTICLES_COUNT_PER_PAGE').getAttribute('max_articles_count_per_page_value'));
    ids = document.getElementById('ids').getAttribute('data-ids').split(',');

    // 初始化页面展示
    showPage();

    // 绑定按钮事件
    previousPageButton.onclick = previousPage;
    nextPageButton.onclick = nextPage;
};

// 在全局作用域中定义 nextPage 和 previousPage 函数
function nextPage() {
    pageNumber++;
    showPage();
}

function previousPage() {
    pageNumber--;
    showPage();
}

// showPage 函数逻辑
function showPage() {
    var lastPageNumber = Math.trunc((ids.length - 1) / max_articles_count_per_page_value);
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