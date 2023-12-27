const languageElement = document.getElementById("language");
const usernameElement = document.getElementById("username");
const passwordElement = document.getElementById("password");
const productNameElement = document.getElementById("product_name");
//const SKU_Element = document.getElementById("SKU");
const addTimeElement = document.getElementById("add_time");
const kategoryElement = document.getElementById("kategory");
const descriptionElement = document.getElementById("description");
const numberInStockElement = document.getElementById("number_in_stock");
const imageElement = document.getElementById("image");
const widthElement = document.getElementById("width");
const heightElement = document.getElementById("height");
const lengthElement = document.getElementById("length");
const weightElement = document.getElementById("weight");
const materialElement = document.getElementById("material");
const colorElement = document.getElementById("color");
const priceElement = document.getElementById("price");
const editElement = document.getElementById("edit");
const deleteElement = document.getElementById("delete");
const createProductElement = document.getElementById("create_product");
const createElement = document.getElementById("create");
const cancelElement = document.getElementById("cancel");
const nextElement = document.getElementById("next");
const previousElement = document.getElementById("previous");
const saveElement = document.getElementById("save");
const backToProductListElement = document.getElementById("back_to_product_list");
const loginElement = document.getElementById("login");
const productListElement = document.getElementById("product_list");
const APP_DIRECTORY_Element = document.getElementById("APP_DIRECTORY");

async function save_() {
    await fetch(APP_DIRECTORY_Element.getAttribute('data-app-directory')+'language-save/', {
        method: "POST",
        headers: {
            "content-type" : "application/json"
        },
        body: JSON.stringify({
            "fileName": languageElement.value+".json",
            "login": {
                "username": usernameElement.value,
                "password": passwordElement.value
            },
            "product_manage": {
                "product_property": {
                    "product_name": productNameElement.value,
                    "add_time": addTimeElement.value,
                    "kategory" : kategoryElement.value,
                    "description" : descriptionElement.value,
                    "number_in_stock" : numberInStockElement.value,
                    "image" :imageElement.value,
                    "width" : widthElement.value,
                    "height" : heightElement.value,
                    "length" : lengthElement.value,
                    "weight" : weightElement.value,
                    "material" : materialElement.value,
                    "color" : colorElement.value,
                    "price" : priceElement.value
                }
            },
            "function": {
                "edit": editElement.value,
                "delete": deleteElement.value,
                "create_product": createProductElement.value,
                "create": createElement.value,
                "cancel": cancelElement.value,
                "next": nextElement.value,
                "previous": previousElement.value,
                "save": saveElement.value,
                "back_to_product_list": backToProductListElement.value,
                "login": loginElement.value
            },
            "title": {
                "product_list": productListElement.value
            }
        })
    });
    window.location.href = APP_DIRECTORY_Element.getAttribute('data-app-directory')+"translation";
}

function backToLanguageList() {
    window.location.href = APP_DIRECTORY_Element.getAttribute('data-app-directory')+"translation";
}