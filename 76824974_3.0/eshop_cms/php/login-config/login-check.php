<?php
$_SESSION["admin"] = true;
//echo "login check";
header('Content-Type: application/json');
require_once "./constants.php";
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);
$username = $input["username"];
$password = $input["password"];
//$login_info = new Login_Info("Tony", "1509517798", "product_manager", 0);

$login_info = $userDB->check_login($username, $password);
error_log("before session starts");

if ($login_info === false) {
    $_SESSION["admin"] = false;
    http_response_code(400);
    echo json_encode(["error" => "invalid login or password"]);
    exit();
}

error_log(json_encode($_SESSION));
if (!isset($_SESSION["admin"]) || $_SESSION["admin"] !== true) {
    http_response_code(400);
    echo json_encode(["error" => "login failed"]);
    error_log("login failed");
    error_log(json_encode($_SESSION));

    include "./php/login-config/admin_login.php";
    
}

$lifeTime = 24 * 3600;
setcookie(session_name(), session_id(), time() + $lifeTime, "/");
error_log(json_encode(["username" => $login_info->username, "password"=> $login_info->password, "position" => $login_info->position, "isClient" => $login_info->is_client]));
echo json_encode(["username" => $login_info->username, "password"=> $login_info->password, "position" => $login_info->position, "isClient" => $login_info->is_client]);
