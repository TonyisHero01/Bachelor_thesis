function edit(id) {
    const editRoute = document.getElementById(id).getAttribute('data-edit-route');
    
    if (editRoute) {
        window.location.href = editRoute;
    } else {
        console.error('Edit route not found for product ID ' + id);
    }
}

const idElement = document.getElementById("employeeId").getAttribute("employee-id-data");
const surnameElement = document.getElementById("surname");
const nameElement = document.getElementById("name");
const phoneNumberElement = document.getElementById("phoneNumber");
const emailElement = document.getElementById("email");
const roleCheckboxes = document.querySelectorAll("input[name='roles[]']");

//https://blog.51cto.com/zhezhebie/5445075 - can't name function as save()
async function save_() {
    const selectedRoles = [];
    roleCheckboxes.forEach(function(checkbox) {
        if (checkbox.checked) {
            selectedRoles.push(checkbox.value);
        }
    });
    await fetch("/employee_save/" + idElement, {
        method: "POST",
        headers: {
            "content-type" : "application/json"
        },
        body: JSON.stringify({
            "surname" : surnameElement.value, 
            "name" : nameElement.value, 
            "phoneNumber" : phoneNumberElement.value, 
            "email" : emailElement.value,
            "roles": selectedRoles
        })
    });

    window.location.href = "/employee_list";
}
function backToEmployees() {
    const routeData = document.getElementById("routeData")
    window.location.href = routeData.getAttribute("data-employee_list-route");
}

