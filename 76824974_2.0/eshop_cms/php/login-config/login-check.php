<?php
//echo "login check";
header('Content-Type: application/json');
require_once "./constants.php";
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);
$username = $input["username"];
$password = $input["password"];
//$login_info = new Login_Info("Tony", "1509517798", "product_manager", 0);
try {
    $login_info = check_login($username, $password, $conn);
} catch (WrongFormatException $wfe) {
    http_response_code(400);
    echo json_encode(["error" => "invalid login or password"]);
    exit();
}
if ($login_info === false) {
    http_response_code(400);
    echo json_encode(["error" => "invalid login or password"]);
    exit();
}
/*
$currentDateTime = date('Y-m-d H:i:s');
$newContent = $currentDateTime . ": " . $login_info->username . " " . $login_info->position;
$filePath = "./logs/login_logs.txt";
$oldContent = file_get_contents($filePath);
$newContent .= $oldContent;
file_put_contents($filePath, $newContent);
*/
echo json_encode(["username" => $login_info->username, "password"=> $login_info->password, "position" => $login_info->position, "isClient" => $login_info->is_client]);
