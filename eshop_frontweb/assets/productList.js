function edit(productId) {
    // 构建编辑路由
    const editRoute = document.getElementById(productId).getAttribute('data-edit-route');
    console.log('bms'+editRoute);
    if (editRoute) {
        // 跳转到编辑页面
        window.location.href = editRoute;
    } else {
        console.error('Edit route not found for product ID ' + productId);
    }
}

var popup = document.getElementById("myPopup");
var cancelButton = document.getElementById("cancelButton");
var cancelCategoryAddButton = document.getElementById("cancelCategoryAddButton");
var categoryPopup = document.getElementById("myCategoryPopup");
function openCreateForm() {
    popup.removeAttribute("style");
    cancelButton.removeAttribute("style");
}

function openCategoryAddForm() {
    categoryPopup.removeAttribute("style");
    cancelCategoryAddButton.removeAttribute("style");
}

function cancelCreateForm() {
    console.log("cancel");
    popup.setAttribute("style", "display: none;");
    categoryPopup.setAttribute("style", "display: none;");
    cancelButton.setAttribute("style", "display: none;");
    //window.location.href = "<?php echo APP_DIRECTORY?>products;
}
var nameElement = document.getElementById("name");
var numberInStockElement = document.getElementById("number_in_stock");
var priceElement = document.getElementById("price");

var categoryNameElement = document.getElementById("categoryName");
function enableCreateButton () {
    console.log("called func");
    
    var submitElement = document.getElementById("createButton");
    if (nameElement.value != "" && nameElement.value.length <= 32 & numberInStockElement != "" & priceElement != "") {
        submitElement.removeAttribute("disabled");
    }
    else {
        submitElement.setAttribute("disabled", "disabled");
    }
}
function enableCategoryAddButton() {
    console.log("called func");
    
    var submitElement = document.getElementById("addCategoryButton");
    if (categoryNameElement.value != "" && nameElement.value.length <= 32) {
        submitElement.removeAttribute("disabled");
    }
    else {
        submitElement.setAttribute("disabled", "disabled");
    }
}
async function create() {
    console.log("funguje create");
    var editRoute = document.getElementById('routeData').getAttribute("data-create-route")
    //var productListRoute = document.getElementById('routeData').getAttribute("data-edit-route")
    var response = await fetch(editRoute,{
        method: "POST",
        headers: {
            'content-type' : 'application/json'
        },
        body: JSON.stringify({
            "name" : nameElement.value,
            "number_in_stock" :numberInStockElement.value,
            "add_time" : formatDateTime(),
            "price" :priceElement.value
        })
    });
    //console.log(await response.text());
    
    var id = (await response.json())["id"];
    console.log(id);
    window.location.href = '/bms/product_edit/' + id;
    
}

async function createCategory() {
    console.log("funguje create");
    //var productListRoute = document.getElementById('routeData').getAttribute("data-edit-route")
    var response = await fetch('/bms/save_category',{
        method: "POST",
        headers: {
            'content-type' : 'application/json'
        },
        body: JSON.stringify({
            "name" : categoryNameElement.value,
        })
    });
    //console.log(await response.text());
    
    var id = (await response.json())["id"];
    console.log(id);
    window.location.href = '/bms/product_list';
    
}

function formatDateTime() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0'); // 月份从 0 开始，需要加 1
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');

    // 返回格式为 YYYY-MM-DD HH:MM:SS
    return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
}