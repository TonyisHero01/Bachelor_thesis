document.querySelector('.image_upload_input').addEventListener('change', previewImages);
var idElement = document.getElementById("productId").getAttribute("product-id-data");
function previewImages(event) {
    const files = event.target.files;
    const previewContainer = document.getElementById('image_container');

    // 预览每一张图片
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        
        // 确保文件是图片
        if (!file.type.startsWith('image/')) {
            continue;
        }

        const img = document.createElement('img');
        img.file = file;
        img.width = 304;
        img.height = 228;

        // 显示空的 img 元素，之后通过 FileReader 来设置图片内容
        previewContainer.appendChild(img);

        const reader = new FileReader();
        reader.onload = (function(aImg) {
            return function(e) {
                aImg.src = e.target.result;
            };
        })(img);

        // 读取图片文件作为 Data URL
        reader.readAsDataURL(file);
    }
}

const handleImageUpload = event => {
    const files = event.target.files;
    const formData = new FormData();

    // 上传多个图片
    for (let i = 0; i < files.length; i++) {
        formData.append('images[]', files[i]);
        console.log(files[i]); // 打印每个文件
    }
    
    fetch('/image_save/' + idElement, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())  // 先获取原始响应
    .then(text => {
        console.log("Server response:", text); // 打印服务器响应内容
        try {
            const data = JSON.parse(text); // 转换为JSON，防止非JSON报错
            console.log("Uploaded file paths:", data.filePaths);
            
        } catch (e) {
            console.error("Invalid JSON response");
        }
    })
    .catch(error => {
        console.error('Error uploading images:', error);
    });
};

function deleteImage(imageUrl) {
    fetch(`/delete_image/` + idElement, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ imageUrl: imageUrl })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // 成功后在页面中移除图片元素
            const imageSection = document.querySelector(`[data-image-url="${imageUrl}"]`);
            if (imageSection) {
                imageSection.remove();
            } else {
                console.warn("Image section not found for URL:", imageUrl);
            }
        } else {
            console.error("Failed to delete image.");
        }
    })
    .catch(error => console.error('Error:', error));
}

document.querySelector('.image_upload_input').addEventListener('change', handleImageUpload);

async function save_() {
    
    var nameElement = document.getElementById("name");
    var categoryElement = document.getElementById("categoryOptions");
    var descriptionElement = document.getElementById("description");
    var numberInStockElement = document.getElementById("number_in_stock");
    var widthElement = document.getElementById("width");
    var heightElement = document.getElementById("height");
    var lengthElement = document.getElementById("length");
    var weightElement = document.getElementById("weight");
    var materialElement = document.getElementById("material");
    var colorElement = document.getElementById("color");
    var priceElement = document.getElementById("price");
    var discountElement = document.getElementById("discount");
    var hideBox = document.getElementById('hideBox');
    var hide = hideBox.checked ? 1 : 0;
    var category = categoryElement.options[categoryElement.selectedIndex].text;

    const imagePaths = Array.from(document.querySelectorAll('.image_path')).map(input => input.value);
    await fetch("/bms/product_save/" + idElement, {
        method: "POST",
        headers: {
            "content-type" : "application/json"
        },
        body: JSON.stringify({
            "name" : nameElement.value, 
            "category" : category,
            "description" : descriptionElement.value,
            "number_in_stock" : numberInStockElement.value,
            "image_urls": imagePaths,  // 上传的所有图片路径
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

    window.location.href = "/bms/product_list";
}

function backToProducts() {
    var routeData = document.getElementById("routeData")
    window.location.href = routeData.getAttribute("data-product_list-route");
}