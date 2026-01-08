/**
 * Redirects to the edit page for the given product ID.
 *
 * @param {string|number} productId - Product identifier.
 */
function edit(productId) {
    const editRoute = document
        .getElementById(productId)
        .getAttribute('data-edit-route');

    if (editRoute) {
        window.location.href = editRoute;
    } else {
        console.error(`Edit route not found for product ID ${productId}`);
    }
}

const popup = document.getElementById('myPopup');
const cancelButton = document.getElementById('cancelButton');
const cancelCategoryAddButton = document.getElementById('cancelCategoryAddButton');
const categoryPopup = document.getElementById('myCategoryPopup');
const cancelColorAddButton = document.getElementById('cancelColorAddButton');
const colorPopup = document.getElementById('myColorPopup');

/**
 * Opens the product create form popup.
 */
function openCreateForm() {
    popup.removeAttribute('style');
    cancelButton.removeAttribute('style');
}

/**
 * Opens the category add form popup.
 */
function openCategoryAddForm() {
    categoryPopup.removeAttribute('style');
    cancelCategoryAddButton.removeAttribute('style');
}

/**
 * Opens the color add form popup.
 */
function openColorAddForm() {
    colorPopup.removeAttribute('style');
    cancelColorAddButton.removeAttribute('style');
}

const nameElement = document.getElementById('name');
const skuElement = document.getElementById('sku');
const numberInStockElement = document.getElementById('number_in_stock');
const priceElement = document.getElementById('price');

const categoryNameElement = document.getElementById('categoryName');
const colorNameElement = document.getElementById('colorName');
const colorHexElement = document.getElementById('colorHex');

/**
 * Enables or disables the create product button based on form validity.
 */
function enableCreateButton() {
    const submitElement = document.getElementById('createButton');

    if (
        nameElement.value !== ''
        && nameElement.value.length <= 32
        & numberInStockElement !== ''
        & priceElement !== ''
    ) {
        submitElement.removeAttribute('disabled');
    } else {
        submitElement.setAttribute('disabled', 'disabled');
    }
}

/**
 * Enables or disables the add category button based on form validity.
 */
function enableCategoryAddButton() {
    const submitElement = document.getElementById('addCategoryButton');

    if (categoryNameElement.value !== '' && nameElement.value.length <= 32) {
        submitElement.removeAttribute('disabled');
    } else {
        submitElement.setAttribute('disabled', 'disabled');
    }
}

/**
 * Enables or disables the add color button based on form validity.
 */
function enableColorAddButton() {
    const submitElement = document.getElementById('addColorButton');

    if (colorNameElement.value !== '' && nameElement.value.length <= 32) {
        submitElement.removeAttribute('disabled');
    } else {
        submitElement.setAttribute('disabled', 'disabled');
    }
}

/**
 * Creates a new product and redirects to the edit page.
 *
 * @returns {Promise<void>}
 */
async function create() {
    const editRoute = document
        .getElementById('routeData')
        .getAttribute('data-create-route');

    const response = await fetch(editRoute, {
        method: 'POST',
        headers: {
            'content-type': 'application/json',
        },
        body: JSON.stringify({
            name: nameElement.value,
            sku: sku.value,
            number_in_stock: numberInStockElement.value,
            add_time: formatDateTime(),
            price: priceElement.value,
        }),
    });

    const result = await response.json();

    if (!result.success) {
        alert(result.message || 'Failed to create product.');
        return;
    }

    const id = result.id;
    window.location.href = `/bms/product_edit/${id}`;
}

/**
 * Creates a new category and redirects to the product list page.
 *
 * @returns {Promise<void>}
 */
async function createCategory() {
    const response = await fetch('/bms/save_category', {
        method: 'POST',
        headers: {
            'content-type': 'application/json',
        },
        body: JSON.stringify({
            name: categoryNameElement.value,
        }),
    });

    const id = (await response.json()).id;
    window.location.href = '/bms/product_list';
}

/**
 * Creates a new color and redirects to the product list page.
 *
 * @returns {Promise<void>}
 */
async function createColor() {
    const colorName = colorNameElement.value.trim();
    const colorHex = colorHexElement.value.trim();

    if (!colorName || !/^#[A-Fa-f0-9]{6}$/.test(colorHex)) {
        alert('Invalid color name or hex code!');
        return;
    }

    const response = await fetch('/bms/save_color', {
        method: 'POST',
        headers: {
            'content-type': 'application/json',
        },
        body: JSON.stringify({
            name: colorNameElement.value,
            hex: colorHex,
        }),
    });

    const id = (await response.json()).id;
    window.location.href = '/bms/product_list';
}

/**
 * Formats the current date/time as "YYYY-MM-DD HH:mm:ss".
 *
 * @returns {string} Formatted datetime string.
 */
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

/**
 * Opens the modify category popup.
 */
function openModifyCategoryForm() {
    document.getElementById('myModifyCategoryPopup').style.display = 'block';
}

/**
 * Enables or disables the modify category button based on input value.
 */
function enableModifyCategoryButton() {
    document.getElementById('modifyCategoryButton').disabled = document
        .getElementById('newCategoryName')
        .value
        .trim() === '';
}

/**
 * Sends a request to modify the selected category and reloads the page.
 */
function modifyCategory() {
    const categoryId = document.getElementById('modifyCategorySelect').value;
    const newCategoryName = document.getElementById('newCategoryName').value.trim();
    if (!newCategoryName) return;

    fetch('/bms/modify_category', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id: categoryId,
            new_name: newCategoryName,
        }),
    })
        .then((response) => response.json())
        .then((data) => {
            if (data.success) {
                alert('Category modified successfully!');
                location.reload();
            } else {
                alert(`Error: ${data.message}`);
            }
        })
        .catch((error) => console.error('Error:', error));
}

/**
 * Opens the modify color popup and pre-fills the hex value.
 */
function openModifyColorForm() {
    document.getElementById('myModifyColorPopup').style.display = 'block';

    const selectElement = document.getElementById('modifyColorSelect');
    const colorHex = selectElement.options[selectElement.selectedIndex]
        .getAttribute('data-hex');

    document.getElementById('newColorHex').value = colorHex;
}

/**
 * Enables or disables the modify color button based on input value.
 */
function enableModifyColorButton() {
    const newColorName = document.getElementById('newColorName').value.trim();
    document.getElementById('modifyColorButton').disabled = newColorName.length === 0;
}

document.getElementById('modifyColorSelect').addEventListener('change', function () {
    const selectedOption = this.options[this.selectedIndex];
    const colorHex = selectedOption.getAttribute('data-hex');
    document.getElementById('newColorHex').value = colorHex;
});

/**
 * Sends a request to modify the selected color and reloads the page.
 *
 * @returns {Promise<void>}
 */
async function modifyColor() {
    const colorId = document.getElementById('modifyColorSelect').value;
    const newColorName = document.getElementById('newColorName').value.trim();
    const newColorHex = document.getElementById('newColorHex').value;

    if (!colorId || !newColorName) {
        alert('Please select a color and enter a new name.');
        return;
    }

    const response = await fetch('/bms/modify_color', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            id: colorId,
            new_name: newColorName,
            new_hex: newColorHex,
        }),
    });

    const result = await response.json();

    if (result.status === 'success') {
        alert('Color modified successfully!');
        window.location.reload();
    } else {
        alert('Error modifying color.');
    }
}

/**
 * Opens the size add popup.
 */
function openSizeAddForm() {
    document.getElementById('mySizePopup').style.display = 'block';
}

/**
 * Enables or disables the add size button based on input value.
 */
function enableSizeAddButton() {
    document.getElementById('addSizeButton').disabled = document
        .getElementById('sizeName')
        .value
        .trim() === '';
}

/**
 * Sends a request to create a new size and reloads the page.
 */
function createSize() {
    const sizeName = document.getElementById('sizeName').value.trim();
    if (!sizeName) return;

    fetch('/bms/create_size', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: sizeName }),
    })
        .then((response) => response.json())
        .then((data) => {
            if (data.success) {
                alert('Size added successfully!');
                location.reload();
            } else {
                alert(`Error: ${data.message}`);
            }
        })
        .catch((error) => console.error('Error:', error));
}

/**
 * Opens the modify size popup.
 */
function openModifySizeForm() {
    document.getElementById('myModifySizePopup').style.display = 'block';
}

/**
 * Enables or disables the modify size button based on input value.
 */
function enableModifySizeButton() {
    document.getElementById('modifySizeButton').disabled = document
        .getElementById('newSizeName')
        .value
        .trim() === '';
}

/**
 * Sends a request to modify the selected size and reloads the page.
 */
function modifySize() {
    const sizeId = document.getElementById('modifySizeSelect').value;
    const newSizeName = document.getElementById('newSizeName').value.trim();
    if (!newSizeName) return;

    fetch(`/bms/modify_size/${sizeId}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: newSizeName }),
    })
        .then((response) => response.json())
        .then((data) => {
            if (data.success) {
                alert('Size modified successfully!');
                location.reload();
            } else {
                alert(`Error: ${data.message}`);
            }
        })
        .catch((error) => console.error('Error:', error));
}

/**
 * Cancels the create product popup.
 */
function cancelCreateProductForm() {
    document.getElementById('myPopup').style.display = 'none';
    document.getElementById('cancelButton').style.display = 'none';
}

/**
 * Cancels the create category popup.
 */
function cancelCreateCategoryForm() {
    document.getElementById('myCategoryPopup').style.display = 'none';
    document.getElementById('cancelCategoryAddButton').style.display = 'none';
}

/**
 * Cancels the create color popup.
 */
function cancelCreateColorForm() {
    document.getElementById('myColorPopup').style.display = 'none';
    document.getElementById('cancelColorAddButton').style.display = 'none';
}

/**
 * Cancels the modify category popup.
 */
function cancelModifyCategoryForm() {
    document.getElementById('myModifyCategoryPopup').style.display = 'none';
}

/**
 * Cancels the modify color popup.
 */
function cancelModifyColorForm() {
    document.getElementById('myModifyColorPopup').style.display = 'none';
}

/**
 * Cancels the create size popup.
 */
function cancelCreateSizeForm() {
    document.getElementById('mySizePopup').style.display = 'none';
}

/**
 * Cancels the modify size popup.
 */
function cancelModifySizeForm() {
    document.getElementById('myModifySizePopup').style.display = 'none';
}

/**
 * Applies product list filters for category, color, size, quantity and price.
 */
function applyFilters() {
    const categoryFilter = document.getElementById('filter-category').value.toLowerCase();
    const colorFilter = document.getElementById('filter-color').value.toLowerCase();
    const sizeFilter = document.getElementById('filter-size').value.toLowerCase();
    const quantityFilter = parseInt(document.getElementById('filter-quantity').value, 10);
    const priceFilter = parseFloat(document.getElementById('filter-price').value);

    const rows = document.querySelectorAll('tbody tr');

    rows.forEach((row) => {
        const name = row.children[0].textContent.toLowerCase();
        const category = row.children[1].textContent.toLowerCase();
        const color = row.children[2].textContent.toLowerCase();
        const size = row.children[3].textContent.toLowerCase();
        const quantity = parseInt(row.children[4].textContent, 10);
        const price = parseFloat(row.children[6].textContent);

        let show = true;

        if (categoryFilter && category !== categoryFilter) show = false;
        if (colorFilter && color !== colorFilter) show = false;
        if (sizeFilter && size !== sizeFilter) show = false;
        if (!Number.isNaN(quantityFilter) && quantity < quantityFilter) show = false;
        if (!Number.isNaN(priceFilter) && price > priceFilter) show = false;

        row.style.display = show ? '' : 'none';
    });
}

/**
 * Resets product list filters and restores default row visibility.
 */
function resetFilters() {
    const categorySelect = document.getElementById('filter-category');
    const colorSelect = document.getElementById('filter-color');
    const sizeSelect = document.getElementById('filter-size');
    const quantityInput = document.getElementById('filter-quantity');
    const priceInput = document.getElementById('filter-price');

    if (categorySelect) categorySelect.value = '';
    if (colorSelect) colorSelect.value = '';
    if (sizeSelect) sizeSelect.value = '';
    if (quantityInput) quantityInput.value = '';
    if (priceInput) priceInput.value = '';

    const rows = document.querySelectorAll('tbody tr');

    rows.forEach((row) => {
        row.style.display = '';

        if (row.dataset.hidden === 'true') {
            row.classList.add('hidden-product');
        } else {
            row.classList.remove('hidden-product');
        }
    });
}