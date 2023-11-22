const nameElement = document.getElementById("name");
const kategoryElement = document.getElementById("kategory");
const descriptionElement = document.getElementById("description");
const numberInStockElement = document.getElementById("number_in_stock");
const imageURLElement = document.getElementById("image_url");
const addTimeElement = document.getElementById("add_time");
const widthElement = document.getElementById("width");
const heightElement = document.getElementById("height");
const lengthElement = document.getElementById("length");
const weightElement = document.getElementById("weight");
const materialElement = document.getElementById("material");
const colorElement = document.getElementById("color");
const priceElement = document.getElementById("price");
const imagePath =  document.getElementById("image_path");

const APP_DIRECTORY_Element = document.getElementById("APP_DIRECTORY");
const PAGE_ID_Element = document.getElementById("PAGE_ID");

const handleImageUpload = event => {
const files = event.target.files
const formData = new FormData();

formData.append('myFile', files[0]);
formData.append("name",document.getElementById("name").value)
    console.log('form data: ',formData)
fetch('./../images.php', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => {
    console.log("data---"+data.filePath)

    data.name = "1.jpg";
    let path = data.filePath;
    console.log('path',path)
    document.getElementById("image").src = path;
    document.getElementById("image_path").value = path;
    data.lastModified = new Date();
})
.catch(error => {
    console.error(error)
})
}

document.querySelector('#image_url').addEventListener('change', event => {
handleImageUpload(event)
});

function download(downfile) {
    browser.downloads.download({
        url: URL.createObjectURL(downfile),
        filename: "test/1.jpg",
        saveAs: false,
    })
}
function downloadBlob(blob, name = 'file.txt') {
    if (
        window.navigator && 
        window.navigator.msSaveOrOpenBlob
    ) return window.navigator.msSaveOrOpenBlob(blob);

    const data = window.URL.createObjectURL(blob);

    const link = document.createElement('a');
    link.href = data;
    link.download = name;

    link.dispatchEvent(
        new MouseEvent('click', { 
        bubbles: true, 
        cancelable: true, 
        view: window 
        })
    );

    setTimeout(() => {
        window.URL.revokeObjectURL(data);
        link.remove();
    }, 100);
}

//https://blog.51cto.com/zhezhebie/5445075 - can't name function as save()
async function save_() {
    await fetch(APP_DIRECTORY_Element.getAttribute('data-app-directory')+'product-save/'+PAGE_ID_Element.getAttribute('data-page-id'), {
        method: "POST",
        headers: {
            "content-type" : "application/json"
        },
        body: JSON.stringify({
            "name" : nameElement.value, 
            "kategory" : kategoryElement.value,
            "description" : descriptionElement.value,
            "number_in_stock" : numberInStockElement.value,
            "image_url" :imagePath.value ,
            'add_time' :addTimeElement.value+'',
            "width" : widthElement.value,
            "height" : heightElement.value,
            "length" : lengthElement.value,
            "weight" : weightElement.value,
            "material" : materialElement.value,
            "color" : colorElement.value,
            "price" : priceElement.value
        })
    });
    window.location.href = APP_DIRECTORY_Element.getAttribute('data-app-directory')+"products";
                                    // "/~76824974/eshop_cms/products"
}
function backToProducts() {
    window.location.href = APP_DIRECTORY_Element.getAttribute('data-app-directory')+"products";
}