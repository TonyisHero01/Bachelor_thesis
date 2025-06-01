document.querySelector('.image_upload_input').addEventListener('change', previewImages);
function previewImages(event) {
    const files = event.target.files;
    const previewContainer = document.getElementById('image_container');
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        if (!file.type.startsWith('image/')) {
            continue;
        }

        const img = document.createElement('img');
        img.file = file;
        img.width = 304;
        img.height = 228;

        previewContainer.appendChild(img);

        const reader = new FileReader();
        reader.onload = (function(aImg) {
            return function(e) {
                aImg.src = e.target.result;
            };
        })(img);

        reader.readAsDataURL(file);
    }
}

function handleLogoUpload(event) {
    const file = event.target.files[0];
    const formData = new FormData();
    formData.append('logo', file);

    fetch('/logo_save', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.filePath) {
            document.getElementById('logo_preview').src = `/images/${data.filePath}`;
        }
    })
    .catch(error => console.error('上传错误:', error));
}
const handleImageUpload = event => {
    const files = event.target.files;
    const formData = new FormData();
    for (let i = 0; i < files.length; i++) {
        formData.append('images[]', files[i]);
    }
    
    fetch('/image_save', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        try {
            const data = JSON.parse(text);
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
    const eshopNameElement = document.getElementById("eshop_name");
    const addressElement = document.getElementById("address");
    const telElement = document.getElementById("tel");
    const emailElement = document.getElementById("email");
    const aboutElement = document.getElementById("about");
    const howToOrderElement = document.getElementById("how_to_order");
    const conditionsElement = document.getElementById("conditions");
    const privacyElement = document.getElementById("privacy");
    const shippingElement = document.getElementById("shipping");
    const paymentElement = document.getElementById("payment");
    const refundElement = document.getElementById("refund");
    const colorElement = document.getElementById("color");
    const logoUrlElement = document.getElementById("logo_url");
    const companyName = document.getElementById("company_name");
    const cin = document.getElementById("cin");
    const hidePricesElement = document.getElementById("hide_prices");

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
        "hidePrices": hidePricesElement.checked,
        "currencies": currencies
    };

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
    const index = container.children.length;
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