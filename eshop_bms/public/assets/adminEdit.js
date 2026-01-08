const idElement = document
    .getElementById('adminId')
    .getAttribute('admin-id-data');

const surnameElement = document.getElementById('surname');
const nameElement = document.getElementById('name');
const phoneNumberElement = document.getElementById('phoneNumber');
const emailElement = document.getElementById('email');
const roleCheckboxes = document.querySelectorAll("input[name='roles[]']");

/**
 * Redirects to the edit page for the given admin ID.
 *
 * @param {string} id - The admin element ID.
 */
function edit(id) {
    const editRoute = document
        .getElementById(id)
        .getAttribute('data-edit-route');

    if (editRoute) {
        window.location.href = editRoute;
    } else {
        console.error(`Edit route not found for product ID ${id}`);
    }
}

/**
 * Saves admin data and redirects to the admin list page.
 *
 * @returns {Promise<void>}
 */
async function save_() {
    const selectedRoles = [];

    roleCheckboxes.forEach((checkbox) => {
        if (checkbox.checked) {
            selectedRoles.push(checkbox.value);
        }
    });

    await fetch(`/admin_save/${idElement}`, {
        method: 'POST',
        headers: {
            'content-type': 'application/json',
        },
        body: JSON.stringify({
            surname: surnameElement.value,
            name: nameElement.value,
            phoneNumber: phoneNumberElement.value,
            email: emailElement.value,
            roles: selectedRoles,
        }),
    });

    window.location.href = '/admin_list';
}

/**
 * Redirects back to the admin list page.
 */
function backToAdmins() {
    const routeData = document.getElementById('routeData');

    window.location.href = routeData.getAttribute(
        'data-admin_list-route',
    );
}