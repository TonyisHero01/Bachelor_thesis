document.querySelector('.image_upload_input').addEventListener('change', previewImages);
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
// 单独处理 LOGO 的上传
function handleLogoUpload(event) {
    const file = event.target.files[0];
    const formData = new FormData();
    formData.append('logo', file); // 使用 FormData 上传文件本身

    fetch('/logo_save', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // 上传成功后返回的文件名
        if (data.filePath) {
            document.getElementById('logo_preview').src = `/images/${data.filePath}`;
            console.log("上传的文件路径:", data.filePath);
        }
    })
    .catch(error => console.error('上传错误:', error));
}
const handleImageUpload = event => {
    const files = event.target.files;
    const formData = new FormData();

    // 上传多个图片
    for (let i = 0; i < files.length; i++) {
        formData.append('images[]', files[i]);
        console.log(files[i]); // 打印每个文件
    }
    
    fetch('/image_save', {
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

function deleteImage(imageName) {
    imageName = imageName.replace("images/", "");
    console.log(`/delete_cimage/${imageName}`);
    fetch(`/delete_cimage/${imageName}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'Success') {
            const imageSection = document.querySelector(`[data-image-url="${imageName}"]`);
            if (imageSection) {
                imageSection.remove();
            }
        } else {
            console.error("Failed to delete image: ", data.message);
        }
    })
    .catch(error => console.error('Error:', error));
}
document.querySelector('.image_upload_input').addEventListener('change', handleImageUpload);

async function save_() {
    var eshopNameElement = document.getElementById("eshop_name");
    var addressElement = document.getElementById("address");
    var telElement = document.getElementById("tel");
    var emailElement = document.getElementById("email");
    var aboutElement = document.getElementById("about");
    var howToOrderElement = document.getElementById("how_to_order");
    var conditionsElement = document.getElementById("conditions");
    var privacyElement = document.getElementById("privacy");
    var shippingElement = document.getElementById("shipping");
    var paymentElement = document.getElementById("payment");
    var refundElement = document.getElementById("refund");
    var colorElement = document.getElementById("color");
    var logoUrlElement = document.getElementById("logo_url");
    var carouselUrlsElement = document.getElementById("carousel_urls");
    var companyName = document.getElementById("company_name");
    var cin = document.getElementById("cin");
    const imagePaths = Array.from(document.querySelectorAll('.image_path')).map(input => input.value);

    const currencies = [];
    const codes = document.getElementsByName("currencyCode[]");
    const values = document.getElementsByName("currencyValue[]");
    const defaultCurrencyIndex = document.querySelector('input[name="isDefaultCurrency"]:checked');

    for (let i = 0; i < codes.length; i++) {
        currencies.push({
            name: codes[i].value.trim(),
            value: parseFloat(values[i].value),
            isDefault: defaultCurrencyIndex && defaultCurrencyIndex.value == i
        });
    }
    
    // 构建请求数据对象
    let requestData = {
        "eshopName": eshopNameElement.value,
        "address": addressElement.value,
        "tel": telElement.value,
        "email": emailElement.value,
        "about": aboutElement.value,
        "image_urls": imagePaths,
        "howToOrder": howToOrderElement.value,
        "conditions": conditionsElement.value,
        "privacy": privacyElement.value,
        "shipping": shippingElement.value,
        "payment": paymentElement.value,
        "refund": refundElement.value,
        "color": colorElement.value,
        "companyName": companyName.value,
        "cin": cin.value,
        currencies: currencies // 添加货币数据
    };

    // 如果有新 logo 上传，则添加到请求数据中
    if (logoUrlElement.value) {
        requestData["logo_url"] = logoUrlElement.value.replace("C:\\fakepath\\", "");
    }

    await fetch("/eshop_save", {
        method: "POST",
        headers: {
            "content-type": "application/json"
        },
        body: JSON.stringify(requestData)
    })
    .then(response => {
        if (response.ok) {
            return response.json().then(data => {
                alert("Edit Shop Info Successful!");
            });
        } else {
            alert("保存失败，请稍后重试。");
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert("请求过程中出现错误，请检查网络或稍后重试。");
    });
}
function addCurrency() {
    const container = document.getElementById('currency-container');
    const index = container.children.length; // 计算当前货币项数量

    // 创建新的货币项
    const currencyPair = document.createElement('div');
    currencyPair.className = 'currency-pair';
    currencyPair.innerHTML = `
        <input type="text" name="currencyCode[]" placeholder="Currency Code (e.g., USD)" required>
        <input type="number" name="currencyValue[]" placeholder="Value" step="0.01" required>
        <input type="radio" name="isDefaultCurrency" value="${index}"> Default
        <button type="button" class="delete-currency" onclick="deleteCurrency(this)">Delete</button>
    `;

    container.appendChild(currencyPair);
}

function deleteCurrency(button) {
    const currencyPair = button.parentElement;
    currencyPair.remove();
}