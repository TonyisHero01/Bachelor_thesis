var idElement = document.getElementById("productId").getAttribute("product-id-data");
var nameElement = document.getElementById("name");
//var kategoryElement = document.getElementById("kategory");
var categoryElement = document.getElementById("categoryOptions")
var descriptionElement = document.getElementById("description");
var numberInStockElement = document.getElementById("number_in_stock");
var imageURLElement = document.getElementById("image_url");
var addTimeElement = document.getElementById("add_time");
var widthElement = document.getElementById("width");
var heightElement = document.getElementById("height");
var lengthElement = document.getElementById("length");
var weightElement = document.getElementById("weight");
var materialElement = document.getElementById("material");
var colorElement = document.getElementById("color");
var priceElement = document.getElementById("price");
var imagePath =  document.getElementById("image_path");
var date = new Date();
var hideBox = document.getElementById('hideBox');
var discountElement = document.getElementById("discount");
//var hide = 0;

const handleImageUpload = event => {
    const files = event.target.files
    const formData = new FormData();

    formData.append('myFile', files[0]);
    formData.append("name",document.getElementById("name").value)
        console.log('form data: ',formData)
    fetch('/image_save/', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        //console.log("data: " + data.data);
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
var imageUrl = document.querySelector('#image_url');
if (imageUrl != null) {
    imageUrl.addEventListener('change', event => {
        handleImageUpload(event)
        });
}


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

    // For other browsers:
    // Create a link pointing to the ObjectURL containing the blob.
    const data = window.URL.createObjectURL(blob);

    const link = document.createElement('a');
    link.href = data;
    link.download = name;

    // this is necessary as link.click() does not work on the latest firefox
    link.dispatchEvent(
        new MouseEvent('click', { 
        bubbles: true, 
        cancelable: true, 
        view: window 
        })
    );

    setTimeout(() => {
        // For Firefox it is necessary to delay revoking the ObjectURL
        window.URL.revokeObjectURL(data);
        link.remove();
    }, 100);
}


//https://blog.51cto.com/zhezhebie/5445075 - can't name function as save()
async function save_() {
    //console.log("addTimeElement,",addTimeElement.value)
    //var rote = document.getElementById('routeData').getAttribute("")
    var hide = hideBox.checked ? 1 : 0;
    var category = categoryElement.options[categoryElement.selectedIndex].text;

    await fetch("/product_save/" + idElement, {
        method: "POST",
        headers: {
            "content-type" : "application/json"
        },
        body: JSON.stringify({
            "name" : nameElement.value, 
            "category" : category,
            "description" : descriptionElement.value,
            "number_in_stock" : numberInStockElement.value,
            "image_url" :imagePath.value ,
            /*"add_time" : date.getHours() + ":" + date.getMinutes() + ":" + date.getSeconds()+" "+date.getDate()+'-'+(date.getMonth()+1)+'-'+date.getFullYear(),*/
            /*'add_time' :addTimeElement.value+'',*/
            "width" : widthElement.value,
            "height" : heightElement.value,
            "length" : lengthElement.value,
            "weight" : weightElement.value,
            "material" : materialElement.value,
            "color" : colorElement.value,
            "price" : priceElement.value,
            "hidden": hide,
            "discount": discountElement.value
        })
    });
    console.log("name: " + nameElement.value)
    console.log("kategory: " + category)
    console.log("description: " + descriptionElement.value)
    console.log("number_in_stock: " + numberInStockElement.value)
    console.log("image_url: " + imagePath.value)
    /*console.log('add_time: ' + addTimeElement.value+'')*/
    console.log("width: " + widthElement.value)
    console.log("height: " + heightElement.value)
    console.log("length: " + lengthElement.value)
    console.log("weight: " + weightElement.value)
    console.log("material: " + materialElement.value)
    console.log("color: " + colorElement.value)
    console.log("price: " + priceElement.value)

    window.location.href = "/product_list";
}
function backToProducts() {
    var routeData = document.getElementById("routeData")
    window.location.href = routeData.getAttribute("data-product_list-route");
}

