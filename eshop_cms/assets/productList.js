function edit(productId) {
    // 构建编辑路由
    const editRoute = document.getElementById(productId).getAttribute('data-edit-route');
    
    if (editRoute) {
        // 跳转到编辑页面
        window.location.href = editRoute;
    } else {
        console.error('Edit route not found for product ID ' + productId);
    }
}

var popup = document.getElementById("myPopup");
var cancelButton = document.getElementById("cancelButton");
function openCreateForm() {
    popup.removeAttribute("style");
    cancelButton.removeAttribute("style");
}

function cancelCreateForm() {
    console.log("cancel");
    popup.setAttribute("style", "display: none;");
    cancelButton.setAttribute("style", "display: none;");
    //window.location.href = "<?php echo APP_DIRECTORY?>products;
}
var nameElement = document.getElementById("name");
var numberInStockElement = document.getElementById("number_in_stock");
var priceElement = document.getElementById("price");
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
    window.location.href = 'product_edit/' + id;
    
}

function formatDateTime() {
    var now = new Date();

    var hours = String(now.getHours()).padStart(2, '0');
    var minutes = String(now.getMinutes()).padStart(2, '0');
    var seconds = String(now.getSeconds()).padStart(2, '0');

    var day = String(now.getDate()).padStart(2, '0');
    var month = String(now.getMonth() + 1).padStart(2, '0'); // Months are zero-indexed
    var year = now.getFullYear();

    return hours + ':' + minutes + ':' + seconds + ' ' + day + '.' + month + '.' + year;
}