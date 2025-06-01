function edit(productId) {
    const editRoute = document.getElementById(productId).getAttribute('data-edit-route');
    if (editRoute) {
        window.location.href = editRoute;
    } else {
        console.error('Edit route not found for product ID ' + productId);
    }
}

const popup = document.getElementById("myPopup");
const cancelButton = document.getElementById("cancelButton");
const cancelCategoryAddButton = document.getElementById("cancelCategoryAddButton");
const categoryPopup = document.getElementById("myCategoryPopup");
const cancelColorAddButton = document.getElementById("cancelColorAddButton");
const colorPopup = document.getElementById("myColorPopup");
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
    popup.setAttribute("style", "display: none;");
    categoryPopup.setAttribute("style", "display: none;");
    cancelButton.setAttribute("style", "display: none;");
}
const nameElement = document.getElementById("name");
const skuElement = document.getElementById("sku");
const numberInStockElement = document.getElementById("number_in_stock");
const priceElement = document.getElementById("price");

const categoryNameElement = document.getElementById("categoryName");
const colorNameElement = document.getElementById("colorName");
const colorHexElement = document.getElementById('colorHex');
function enableCreateButton () {
    var submitElement = document.getElementById("createButton");
    if (nameElement.value != "" && nameElement.value.length <= 32 & numberInStockElement != "" & priceElement != "") {
        submitElement.removeAttribute("disabled");
    }
    else {
        submitElement.setAttribute("disabled", "disabled");
    }
}
function enableCategoryAddButton() {
    var submitElement = document.getElementById("addCategoryButton");
    if (categoryNameElement.value != "" && nameElement.value.length <= 32) {
        submitElement.removeAttribute("disabled");
    }
    else {
        submitElement.setAttribute("disabled", "disabled");
    }
}

function enableColorAddButton() {
    var submitElement = document.getElementById("addColorButton");
    if (colorNameElement.value != "" && nameElement.value.length <= 32) {
        submitElement.removeAttribute("disabled");
    }
    else {
        submitElement.setAttribute("disabled", "disabled");
    }
}
async function create() {
    const editRoute = document.getElementById('routeData').getAttribute("data-create-route");

    const response = await fetch(editRoute, {
        method: "POST",
        headers: {
            'content-type': 'application/json'
        },
        body: JSON.stringify({
            "name": nameElement.value,
            "sku": sku.value,
            "number_in_stock": numberInStockElement.value,
            "add_time": formatDateTime(),
            "price": priceElement.value
        })
    });

    const result = await response.json();

    if (!result.success) {
        alert(result.message || "Failed to create product.");
        return;
    }

    const id = result.id;
    window.location.href = '/bms/product_edit/' + id;
}

async function createCategory() {
    let response = await fetch('/bms/save_category',{
        method: "POST",
        headers: {
            'content-type' : 'application/json'
        },
        body: JSON.stringify({
            "name" : categoryNameElement.value,
        })
    });
    let id = (await response.json())["id"];
    window.location.href = '/bms/product_list';
}

async function createColor() {
    const colorName = colorNameElement.value.trim();
    const colorHex = colorHexElement.value.trim();
    if (!colorName || !/^#[A-Fa-f0-9]{6}$/.test(colorHex)) {
        alert("Invalid color name or hex code!");
        return;
    }
    let response = await fetch('/bms/save_color',{
        method: "POST",
        headers: {
            'content-type' : 'application/json'
        },
        body: JSON.stringify({
            "name" : colorNameElement.value,
            "hex": colorHex 
        })
    });
    let id = (await response.json())["id"];
    window.location.href = '/bms/product_list';
    
}

function formatDateTime() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
}

function openModifyCategoryForm() {
    document.getElementById("myModifyCategoryPopup").style.display = "block";
}

function cancelModifyCategoryForm() {
    document.getElementById("myModifyCategoryPopup").style.display = "none";
}

function enableModifyCategoryButton() {
    document.getElementById("modifyCategoryButton").disabled = document.getElementById("newCategoryName").value.trim() === "";
}

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

document.getElementById("modifyColorSelect").addEventListener("change", function() {
    const selectedOption = this.options[this.selectedIndex];
    const colorHex = selectedOption.getAttribute("data-hex");
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

function openSizeAddForm() {
    document.getElementById("mySizePopup").style.display = "block";
}

function cancelCreateForm() {
    document.getElementById("mySizePopup").style.display = "none";
    document.getElementById("myModifySizePopup").style.display = "none";
}

function enableSizeAddButton() {
    document.getElementById("addSizeButton").disabled = document.getElementById("sizeName").value.trim() === "";
}

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
            location.reload();
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(error => console.error("Error:", error));
}

function openModifySizeForm() {
    document.getElementById("myModifySizePopup").style.display = "block";
}

function cancelModifySizeForm() {
    document.getElementById("myModifySizePopup").style.display = "none";
}

function enableModifySizeButton() {
    document.getElementById("modifySizeButton").disabled = document.getElementById("newSizeName").value.trim() === "";
}

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
            location.reload();
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(error => console.error("Error:", error));
}