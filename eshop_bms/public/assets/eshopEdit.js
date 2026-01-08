document
    .querySelector('.image_upload_input')
    .addEventListener('change', previewImages);

/**
 * Previews selected image files inside the image container.
 *
 * @param {Event} event - File input change event.
 */
function previewImages(event) {
    const files = event.target.files;
    const previewContainer = document.getElementById('image_container');

    for (let i = 0; i < files.length; i += 1) {
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
        reader.onload = (function (aImg) {
            return function (e) {
                aImg.src = e.target.result;
            };
        }(img));

        reader.readAsDataURL(file);
    }
}

/**
 * Uploads a logo file and updates the logo preview.
 *
 * @param {Event} event - File input change event.
 */
function handleLogoUpload(event) {
    const file = event.target.files[0];
    const formData = new FormData();
    formData.append('logo', file);

    fetch('/logo_save', {
        method: 'POST',
        body: formData,
    })
        .then((response) => response.json())
        .then((data) => {
            if (data.filePath) {
                document.getElementById(
                    'logo_preview',
                ).src = `/images/${data.filePath}`;
            }
        })
        .catch((error) => console.error('上传错误:', error));
}

/**
 * Uploads selected image files to the server.
 *
 * @param {Event} event - File input change event.
 */
const handleImageUpload = (event) => {
    const files = event.target.files;
    const formData = new FormData();

    for (let i = 0; i < files.length; i += 1) {
        formData.append('images[]', files[i]);
    }

    fetch('/image_save', {
        method: 'POST',
        body: formData,
    })
        .then((response) => response.text())
        .then((text) => {
            try {
                JSON.parse(text);
            } catch (e) {
                console.error('Invalid JSON response');
            }
        })
        .catch((error) => {
            console.error('Error uploading images:', error);
        });
};

/**
 * Deletes an image by name and removes it from the DOM.
 *
 * @param {string} imageName - Image file name.
 */
function deleteImage(imageName) {
    imageName = imageName.replace('images/', '');

    fetch(`/delete_cimage/${imageName}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
    })
        .then((response) => response.json())
        .then((data) => {
            if (data.status === 'Success') {
                const imageSection = document.querySelector(
                    `[data-image-url="${imageName}"]`,
                );
                if (imageSection) {
                    imageSection.remove();
                }
            } else {
                console.error('Failed to delete image: ', data.message);
            }
        })
        .catch((error) => console.error('Error:', error));
}

document
    .querySelector('.image_upload_input')
    .addEventListener('change', handleImageUpload);

/**
 * Saves e-shop settings and currency configuration to the server.
 *
 * @returns {Promise<void>}
 */
async function save_() {
    try {
        const csrfEl = document.getElementById('csrf_token_eshop_save');
        const csrfToken = csrfEl ? String(csrfEl.value || '').trim() : '';

        if (!csrfToken) {
            alert('Missing CSRF token.');
            return;
        }

        const eshopNameElement = document.getElementById('eshop_name');
        const addressElement = document.getElementById('address');
        const telElement = document.getElementById('tel');
        const emailElement = document.getElementById('email');
        const aboutElement = document.getElementById('about');
        const howToOrderElement = document.getElementById('how_to_order');
        const conditionsElement = document.getElementById('conditions');
        const privacyElement = document.getElementById('privacy');
        const shippingElement = document.getElementById('shipping');
        const paymentElement = document.getElementById('payment');
        const refundElement = document.getElementById('refund');
        const logoUrlElement = document.getElementById('logo_url');
        const companyNameElement = document.getElementById('company_name');
        const cinElement = document.getElementById('cin');
        const hidePricesElement = document.getElementById('hide_prices');

        const imagePaths = Array.from(
            document.querySelectorAll('.image_path'),
        )
            .map((el) => String(el.value || '').trim())
            .filter((v) => v !== '');

        const currencies = [];
        const rows = document.querySelectorAll(
            '#currency-container .currency-pair',
        );
        const codes = document.getElementsByName('currencyCode[]');
        const values = document.getElementsByName('currencyValue[]');

        const checked = document.querySelector(
            'input[name="isDefaultCurrency"]:checked',
        );
        const checkedValue = checked ? String(checked.value) : null;

        for (let i = 0; i < rows.length; i += 1) {
            const row = rows[i];

            const idAttr = row.getAttribute('data-currency-id');
            const currencyId =
                idAttr && String(idAttr).trim() !== ''
                    ? parseInt(idAttr, 10)
                    : null;

            const code = (codes[i]?.value || '').trim().toUpperCase();
            const rawVal = (values[i]?.value || '').trim();

            if (!code) {
                alert('Currency code is required.');
                return;
            }

            const value = Number(rawVal);
            if (!Number.isFinite(value) || value <= 0) {
                alert(`Invalid currency value for ${code}.`);
                return;
            }

            const isDefault = checkedValue !== null
                && (
                    (currencyId !== null
                        && checkedValue === String(currencyId))
                    || (currencyId === null
                        && checkedValue === `new-${i}`)
                );

            currencies.push({
                id: currencyId,
                name: code,
                value,
                isDefault,
            });
        }

        const requestData = {
            eshopName: eshopNameElement?.value ?? '',
            address: addressElement?.value ?? '',
            tel: telElement?.value ?? '',
            email: emailElement?.value ?? '',
            about: aboutElement?.value ?? '',
            image_urls: imagePaths,
            howToOrder: howToOrderElement?.value ?? '',
            conditions: conditionsElement?.value ?? '',
            privacy: privacyElement?.value ?? '',
            shipping: shippingElement?.value ?? '',
            payment: paymentElement?.value ?? '',
            refund: refundElement?.value ?? '',
            companyName: companyNameElement?.value ?? '',
            cin: cinElement?.value ?? '',
            hidePrices: !!hidePricesElement?.checked,
            currencies,
        };

        if (logoUrlElement && logoUrlElement.value) {
            requestData.logo_url = logoUrlElement.value.replace(
                'C:\\fakepath\\',
                '',
            );
        }

        const response = await fetch('/eshop_save', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
            body: JSON.stringify(requestData),
        });

        const responseText = await response.text();

        if (!response.ok) {
            alert(responseText || 'Failed to save shop settings.');
            return;
        }

        alert('Shop settings saved successfully.');
    } catch (error) {
        console.error(error);
        alert('An unexpected error occurred. Please try again.');
    }
}

/**
 * Adds a new currency input pair.
 */
function addCurrency() {
    const container = document.getElementById('currency-container');
    const index = container.querySelectorAll('.currency-pair').length;

    const currencyPair = document.createElement('div');
    currencyPair.className = 'currency-pair';
    currencyPair.setAttribute('data-currency-id', '');

    currencyPair.innerHTML = `
        <input type="text" name="currencyCode[]" placeholder="Currency Code (e.g., USD)" required>
        <input type="number" name="currencyValue[]" placeholder="Value" step="0.01" required>
        <input type="radio" name="isDefaultCurrency" value="new-${index}"> Default
        <button type="button" class="delete-currency" onclick="deleteCurrency(this)">Delete</button>
    `;

    container.appendChild(currencyPair);
}

/**
 * Deletes a currency input pair.
 *
 * @param {HTMLButtonElement} button - Delete button element.
 */
function deleteCurrency(button) {
    const currencyPair = button.parentElement;
    currencyPair.remove();
}