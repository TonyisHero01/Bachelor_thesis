var nameElement = document.getElementById("name");
var surnameElement = document.getElementById("surname");
var usernameElement = document.getElementById("username");
var passwordElement = document.getElementById("password");
var phoneNumberElement = document.getElementById("phone_number");
var emailElement = document.getElementById("email");

var checkboxes = document.getElementsByName("position");



//https://blog.51cto.com/zhezhebie/5445075 - can't name function as save()
async function register_() {
    //console.log("addTimeElement,",addTimeElement.value)
    //var rote = document.getElementById('routeData').getAttribute("")
    /*
    var positions = new Array(checkboxes.length);
    for (var i=0; i < checkboxes.length; i++) {
        console.log(checkboxes[i].id)
        if (checkboxes[i].checked == true) {
            positions[i] = checkboxes[i].value;
        }
    }
        */
    var positions = [];

    for (var i = 0; i < checkboxes.length; i++) {
        console.log(checkboxes[i].id);
        if (checkboxes[i].checked) {
            positions.push(checkboxes[i].value);
        }
    }
    await fetch("/employee_save", {
        method: "POST",
        headers: {
            "content-type" : "application/json"
        },
        body: JSON.stringify({
            "surname" : surnameElement.value, 
            "name" : nameElement.value, 
            "username" : usernameElement.value, 
            "password" : passwordElement.value,
            "phone_number" : phoneNumberElement.value,
            "email" : emailElement.value,
            "positions" : positions.toString()
        })
    });
    console.log(positions.toString());
    //window.location.href = "/register_succesfull";
}
function backToProducts() {
    var routeData = document.getElementById("routeData")
    window.location.href = routeData.getAttribute("data-product_list-route");
}

