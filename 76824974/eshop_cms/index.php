<?php

require_once "db_config.php";
require_once "constants.php";
require_once "editing.php";
require_once "product.php";
define("ACTIONS_WITH_ID", ["product", "product-edit", "product-save", "product-delete", "product-create"]);

$conn = new mysqli($db_config["server"], $db_config["login"], $db_config["password"], $db_config["database"]);
if ($conn->connect_error) {
    print("Cannot connect to Databse!");
    exit();
}
$database = new Database("Product", $conn);
$userDB = new Database("User", $conn);
//echo var_dump($_GET);

$page = parsePage($_GET["page"]);
//echo "Hello";

//echo $page["action"];
switch ($page["action"]) {
    case "login":
        require_once "./admin_login.php";
        break;
    case "login-check":
        require_once "./login-check.php";
        break;
    case "product":
        //require_once "./product-page.php";
        require_once "./product.php";
        break;
    case "products": 
        require_once "./products.php";
        break;
    case "product-edit":
        require_once "./product-edit.php";
        break;
    case "product-save":
        require_once "./saving.php";
        break;
    case "product-delete": 
        require_once "./deleting.php";
        break;
    case "product-create":
        require_once "./creating.php";
        break;
    case "images":
        require_once "./images.php";
        break;
}

function parsePage($page) {
    $partsOfPage = explode("/", $page);
    $action = $partsOfPage[0];
    $result = [];
    if (in_array($action, ACTIONS_WITH_ID)) {
        $id = intval($partsOfPage[1] ?? null);
        if ($id <= 0) {
            $id = null;
        }
    }
    else {
        if ($action != "login") {
            $action = null;
        }
        $id = null;
    }
    $result = ["action" => $action, "id" => $id];
    return $result;
}

//echo "Hello";