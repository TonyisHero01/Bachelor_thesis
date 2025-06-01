const idElement = document.getElementById("adminId").getAttribute("admin-id-data");
const surnameElement = document.getElementById("surname");
const nameElement = document.getElementById("name");
const usernameElement = document.getElementById("username");
const phoneNumberElement = document.getElementById("phoneNumber");
const emailElement = document.getElementById("email");
const roleCheckboxes = document.querySelectorAll("input[name='roles[]']");
function edit(id) {
    const editRoute = document.getElementById(id).getAttribute('data-edit-route');
    
    if (editRoute) {
        window.location.href = editRoute;
    } else {
        console.error('Edit route not found for product ID ' + id);
    }
}
//https://blog.51cto.com/zhezhebie/5445075 - can't name function as save()
async function save_() {
    const selectedRoles = [];

    roleCheckboxes.forEach(function(checkbox) {
        if (checkbox.checked) {
            selectedRoles.push(checkbox.value);
        }
    });
    await fetch("/admin_save/" + idElement, {
        method: "POST",
        headers: {
            "content-type" : "application/json"
        },
        body: JSON.stringify({
            "surname" : surnameElement.value, 
            "name" : nameElement.value, 
            "username" : usernameElement.value, 
            "phoneNumber" : phoneNumberElement.value, 
            "email" : emailElement.value,
            "roles": selectedRoles
        })
    });

    window.location.href = "/admin_list";
}
function backToAdmins() {
    const routeData = document.getElementById("routeData");
    window.location.href = routeData.getAttribute("data-admin_list-route");
}

