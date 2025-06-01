document.querySelector('.image_upload_input').addEventListener('change', previewImages);
const idElement = document.getElementById("productId").getAttribute("product-id-data");
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

const handleImageUpload = event => {
    const files = event.target.files;
    const formData = new FormData();

    for (let i = 0; i < files.length; i++) {
        formData.append('images[]', files[i]);
    }
    
    fetch('/image_save/' + idElement, {
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
    const nameElement = document.getElementById("name");
    const categoryElement = document.getElementById("categoryOptions");
    const descriptionElement = document.getElementById("description");
    const numberInStockElement = document.getElementById("number_in_stock");
    const sizeElement = document.getElementById("sizeOptions");
    const size = sizeElement.options[sizeElement.selectedIndex].value;
    const widthElement = document.getElementById("width");
    const heightElement = document.getElementById("height");
    const lengthElement = document.getElementById("length");
    const weightElement = document.getElementById("weight");
    const materialElement = document.getElementById("material");
    const colorElement = document.getElementById("colorOptions");
    const priceElement = document.getElementById("price");
    const discountElement = document.getElementById("discount");
    const hideBox = document.getElementById('hideBox');
    const hide = hideBox.checked ? 1 : 0;
    const category = categoryElement.value;
    const attributes = {};
    const keys = document.getElementsByName("attributeKey[]");
    const values = document.getElementsByName("attributeValue[]");
    const taxRate = document.getElementById("tax_rate");
    const noVersionUpdateBox = document.getElementById('noVersionUpdate');
    const noVersionUpdate = noVersionUpdateBox.checked;

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
            "category": parseInt(category) || null,
            "description" : descriptionElement.value,
            "number_in_stock" : numberInStockElement.value,
            "image_urls": imagePaths,
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
            "tax_rate": taxRate.value,
            "no_version_update": noVersionUpdate
        })
    });
    window.location.href = "/bms/product_list";
}

function backToProducts() {
    const routeData = document.getElementById("routeData")
    window.location.href = routeData.getAttribute("data-product_list-route");
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

function switchVersion(selectedUrl) {
    if (selectedUrl) {
        window.location.href = selectedUrl;
    } else {
        console.error("Selected URL is empty");
    }
}

function addAttribute() {
    const container = document.getElementById('attributes-container');
    const attributePair = document.createElement('div');
    attributePair.className = 'attribute-pair';
    attributePair.innerHTML = `
        <input type="text" name="attributeKey[]" placeholder="Key" required>
        <input type="text" name="attributeValue[]" placeholder="Value" required>
        <button type="button" class="delete-attribute" onclick="deleteAttribute(this)">Delete</button>
    `;

    container.appendChild(attributePair);
}

function deleteAttribute(button) {
    const attributePair = button.parentElement;
    attributePair.remove();
}