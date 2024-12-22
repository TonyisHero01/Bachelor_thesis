function edit(id) {
    // 构建编辑路由
    const editRoute = document.getElementById(id).getAttribute('data-edit-route');
    
    if (editRoute) {
        // 跳转到编辑页面
        window.location.href = editRoute;
    } else {
        console.error('Edit route not found for product ID ' + productId);
    }
}

var idElement = document.getElementById("employeeId").getAttribute("employee-id-data");
var surnameElement = document.getElementById("surname");
var nameElement = document.getElementById("name");
var usernameElement = document.getElementById("username");
var phoneNumberElement = document.getElementById("phoneNumber");
var emailElement = document.getElementById("email");
var roleCheckboxes = document.querySelectorAll("input[name='roles[]']");

//https://blog.51cto.com/zhezhebie/5445075 - can't name function as save()
async function save_() {
    //console.log("addTimeElement,",addTimeElement.value)
    //var rote = document.getElementById('routeData').getAttribute("")
    var selectedRoles = [];

    // 遍历所有的复选框，检查是否被选中
    roleCheckboxes.forEach(function(checkbox) {
        if (checkbox.checked) {
            selectedRoles.push(checkbox.value);  // 如果被选中，添加到数组中
        }
    });
    console.log(idElement);
    await fetch("/employee_save/" + idElement, {
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

    window.location.href = "/employee_list";
}
function backToEmployees() {
    var routeData = document.getElementById("routeData")
    window.location.href = routeData.getAttribute("data-employee_list-route");
}

