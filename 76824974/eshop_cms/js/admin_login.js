const usernameElement =document.getElementById("username");
const passwordElement =document.getElementById("password");

const APP_DIRECTORY_Element = document.getElementById("APP_DIRECTORY");

async function login_() {
    console.log("funguje login button");
    const response = await fetch(APP_DIRECTORY_Element.getAttribute('data-app-directory')+"login-check",{
        method: "POST",
        headers: {
        'content-type' : 'application/json'
        },
        body: JSON.stringify({
        "username" : usernameElement.value,
        "password" : passwordElement.value
        })
    });
    console.log("funguje response");
    try {
        const responseData = await response.json();
        console.log(responseData);
        const position = responseData.position;
        const isClient =responseData.isClient;
        console.log(isClient);
        window.location.href = APP_DIRECTORY_Element.getAttribute('data-app-directory')+"products";
    } catch (error) {
        console.error("Error parsing JSON response:", error);
    }
}