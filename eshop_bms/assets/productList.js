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
var cancelColorAddButton = document.getElementById("cancelColorAddButton");
var colorPopup = document.getElementById("myColorPopup");
function openCreateForm() {
    popup.removeAttribute("style");
    cancelButton.removeAttribute("style");
}

function openCategoryAddForm() {
    categoryPopup.removeAttribute("style");
    cancelCategoryAddButton.removeAttribute("style");
}

function openColorAddForm() {
    colorPopup.removeAttribute("style");
    cancelColorAddButton.removeAttribute("style");
}

function cancelCreateForm() {
    console.log("cancel");
    popup.setAttribute("style", "display: none;");
    categoryPopup.setAttribute("style", "display: none;");
    cancelButton.setAttribute("style", "display: none;");
    //window.location.href = "<?php echo APP_DIRECTORY?>products;
}
var nameElement = document.getElementById("name");
var skuElement = document.getElementById("sku");
var numberInStockElement = document.getElementById("number_in_stock");
var priceElement = document.getElementById("price");

var categoryNameElement = document.getElementById("categoryName");
var colorNameElement = document.getElementById("colorName");
const colorHexElement = document.getElementById('colorHex');
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

function enableColorAddButton() {
    console.log("called func");
    
    var submitElement = document.getElementById("addColorButton");
    if (colorNameElement.value != "" && nameElement.value.length <= 32) {
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
            "sku" : sku.value,
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

async function createColor() {
    console.log("funguje create");
    const colorName = colorNameElement.value.trim();
    const colorHex = colorHexElement.value.trim();

    // 检查输入是否为空
    if (!colorName || !/^#[A-Fa-f0-9]{6}$/.test(colorHex)) {
        alert("Invalid color name or hex code!");
        return;
    }
    //var productListRoute = document.getElementById('routeData').getAttribute("data-edit-route")
    var response = await fetch('/bms/save_color',{
        method: "POST",
        headers: {
            'content-type' : 'application/json'
        },
        body: JSON.stringify({
            "name" : colorNameElement.value,
            "hex": colorHex 
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

function openModifyCategoryForm() {
    document.getElementById("myModifyCategoryPopup").style.display = "block";
}

// 🔹 关闭 "Modify Category" 窗口
function cancelModifyCategoryForm() {
    document.getElementById("myModifyCategoryPopup").style.display = "none";
}

// 🔹 启用 "Modify Category" 按钮
function enableModifyCategoryButton() {
    document.getElementById("modifyCategoryButton").disabled = document.getElementById("newCategoryName").value.trim() === "";
}

// 🔹 发送请求修改 Category
function modifyCategory() {
    const categoryId = document.getElementById("modifyCategorySelect").value;
    const newCategoryName = document.getElementById("newCategoryName").value.trim();
    if (!newCategoryName) return;

    fetch("/bms/modify_category", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            id: categoryId,
            new_name: newCategoryName
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert("Category modified successfully!");
            location.reload();
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(error => console.error("Error:", error));
}

function openModifyColorForm() {
    document.getElementById("myModifyColorPopup").style.display = "block";

    // 设置默认颜色
    let selectElement = document.getElementById("modifyColorSelect");
    let colorHex = selectElement.options[selectElement.selectedIndex].getAttribute("data-hex");
    document.getElementById("newColorHex").value = colorHex;
}

function cancelModifyColorForm() {
    document.getElementById("myModifyColorPopup").style.display = "none";
}

function enableModifyColorButton() {
    let newColorName = document.getElementById("newColorName").value.trim();
    document.getElementById("modifyColorButton").disabled = newColorName.length === 0;
}

// 当选择颜色变化时，更新颜色输入框
document.getElementById("modifyColorSelect").addEventListener("change", function() {
    let selectedOption = this.options[this.selectedIndex];
    let colorHex = selectedOption.getAttribute("data-hex");
    document.getElementById("newColorHex").value = colorHex;
});

async function modifyColor() {
    let colorId = document.getElementById("modifyColorSelect").value;
    let newColorName = document.getElementById("newColorName").value.trim();
    let newColorHex = document.getElementById("newColorHex").value;

    if (!colorId || !newColorName) {
        alert("Please select a color and enter a new name.");
        return;
    }

    let response = await fetch('/bms/modify_color', {
        method: "POST",
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            "id": colorId,
            "new_name": newColorName,
            "new_hex": newColorHex
        })
    });

    let result = await response.json();
    if (result.status === "success") {
        alert("Color modified successfully!");
        window.location.reload();
    } else {
        alert("Error modifying color.");
    }
}

// 🔹 打开 "Add Size" 窗口
function openSizeAddForm() {
    document.getElementById("mySizePopup").style.display = "block";
}

// 🔹 关闭 "Add Size" 窗口
function cancelCreateForm() {
    document.getElementById("mySizePopup").style.display = "none";
    document.getElementById("myModifySizePopup").style.display = "none";
}

// 🔹 启用 "Add Size" 按钮
function enableSizeAddButton() {
    document.getElementById("addSizeButton").disabled = document.getElementById("sizeName").value.trim() === "";
}

// 🔹 发送请求创建新 Size
function createSize() {
    const sizeName = document.getElementById("sizeName").value.trim();
    if (!sizeName) return;

    fetch("/bms/create_size", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name: sizeName })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert("Size added successfully!");
            location.reload(); // 重新加载页面
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(error => console.error("Error:", error));
}

// 🔹 打开 "Modify Size" 窗口
function openModifySizeForm() {
    document.getElementById("myModifySizePopup").style.display = "block";
}

// 🔹 关闭 "Modify Size" 窗口
function cancelModifySizeForm() {
    document.getElementById("myModifySizePopup").style.display = "none";
}

// 🔹 启用 "Modify Size" 按钮
function enableModifySizeButton() {
    document.getElementById("modifySizeButton").disabled = document.getElementById("newSizeName").value.trim() === "";
}

// 🔹 发送请求修改 Size
function modifySize() {
    const sizeId = document.getElementById("modifySizeSelect").value;
    const newSizeName = document.getElementById("newSizeName").value.trim();
    if (!newSizeName) return;

    fetch(`/bms/modify_size/${sizeId}`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name: newSizeName })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert("Size modified successfully!");
            location.reload(); // 重新加载页面
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(error => console.error("Error:", error));
}