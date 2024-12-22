var idElement = document.getElementById("adminId").getAttribute("admin-id-data");
var surnameElement = document.getElementById("surname");
var nameElement = document.getElementById("name");
var usernameElement = document.getElementById("username");
var phoneNumberElement = document.getElementById("phoneNumber");
var emailElement = document.getElementById("email");
var roleCheckboxes = document.querySelectorAll("input[name='roles[]']");
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
    var routeData = document.getElementById("routeData")
    window.location.href = routeData.getAttribute("data-admin_list-route");
}

