<?php
require_once "constants.php";
require_once "db_config.php";
require_once "index.php";
?>
<!DOCTYPE html>
<html>
<head>
    <title>article edit</title>
    <link rel="stylesheet" type="text/css" href="<?php echo APP_DIRECTORY ?>style.css">
</head>
<body>
    <form action="#" enctype="multipart/form-data">

        <label for="username">Username: </label>
        <input type="text" id="username" name="username">
        <input type="password" id="password" name="password">
        <button type="button" id="loginButton" onclick="login_()">Login</button>
    </form>
    <script>
        var usernameElement =document.getElementById("username");
        var passwordElement =document.getElementById("password");
        async function login_() {
            console.log("funguje login button");
            var response = await fetch("<?php echo APP_DIRECTORY?>login-check",{
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
                //console.log(await response);
                //var responseText =await response.text();
                //console.log(responseText);
                var responseData = await response.json();
                console.log(responseData);
                var position = responseData.position;
                var isClient =responseData.isClient;
                console.log(isClient);
                window.location.href = "<?php echo APP_DIRECTORY?>products";
                /*
                if (isClient == true) {
                    window.location.href = "<?php echo APP_DIRECTORY?>products";
                }
                */
            } catch (error) {
                console.error("Error parsing JSON response:", error);
            }
        }
    </script>
</body>