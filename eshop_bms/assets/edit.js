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
    var sizeElement = document.getElementById("sizeOptions");
    var size = sizeElement.options[sizeElement.selectedIndex].value;
    var widthElement = document.getElementById("width");
    var heightElement = document.getElementById("height");
    var lengthElement = document.getElementById("length");
    var weightElement = document.getElementById("weight");
    var materialElement = document.getElementById("material");
    var colorElement = document.getElementById("colorOptions");
    var priceElement = document.getElementById("price");
    var discountElement = document.getElementById("discount");
    var hideBox = document.getElementById('hideBox');
    var hide = hideBox.checked ? 1 : 0;
    var category = categoryElement.options[categoryElement.selectedIndex].text;
    var attributes = {};
    const keys = document.getElementsByName("attributeKey[]");
    const values = document.getElementsByName("attributeValue[]");
    var taxRate = document.getElementById("tax_rate");

    for (let i = 0; i < keys.length; i++) {
        const key = keys[i].value.trim();
        const value = values[i].value.trim();
        if (key && value) {
            attributes[key] = value;
        }
    }
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
            "size": size,
            "width" : widthElement.value,
            "height" : heightElement.value,
            "length" : lengthElement.value,
            "weight" : weightElement.value,
            "material" : materialElement.value,
            "color" : colorElement.value,
            "price" : priceElement.value,
            "hidden": hide,
            "discount": discountElement.value,
            "edit_time": formatDateTime(),
            "attributes": attributes,
            "tax_rate": taxRate.value
        })
    });

    window.location.href = "/bms/product_list";
}

function backToProducts() {
    var routeData = document.getElementById("routeData")
    window.location.href = routeData.getAttribute("data-product_list-route");
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

function switchVersion(selectedUrl) {
    if (selectedUrl) {
        window.location.href = selectedUrl; // 根据选中的 URL 重新加载页面
    } else {
        console.error("Selected URL is empty");
    }
}

// 添加一组新的键值对输入框
function addAttribute() {
    const container = document.getElementById('attributes-container');

    // 创建新的键值对输入框
    const attributePair = document.createElement('div');
    attributePair.className = 'attribute-pair';
    attributePair.innerHTML = `
        <input type="text" name="attributeKey[]" placeholder="Key" required>
        <input type="text" name="attributeValue[]" placeholder="Value" required>
        <button type="button" class="delete-attribute" onclick="deleteAttribute(this)">Delete</button>
    `;

    container.appendChild(attributePair);
}

// 删除当前键值对输入框
function deleteAttribute(button) {
    const attributePair = button.parentElement;
    attributePair.remove();
}