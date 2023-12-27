<?php
require_once "./config/db_config.php";
require_once "constants.php";
require_once "php/product-config/editing.php";
require_once "php/product-config/product.php";
require_once "./php/login-config/login_info.php";
define("ACTIONS_WITH_ID", ["product", "product-edit", "product-save", "product-delete", "product-create"]);
define("ACTIONS_WITH_FILENAME", ["language-edit", "language-delete"]);
$conn = new mysqli($db_config["server"], $db_config["login"], $db_config["password"], $db_config["database"]);
if ($conn->connect_error) {
    print("Cannot connect to Databse!");
    exit();
}
$database = new Database("Product", $conn);
$userDB = new Database("User", $conn);
//echo $_GET["page"];
$page = parsePage($_GET["page"]);

switch ($page["action"]) {
    case "login":
        require_once "./php/login-config/admin_login.php";
        break;
    case "login-check":
        require_once "./php/login-config/login-check.php";
        break;
    case "product":
        require_once "./php/product-config/product.php";
        break;
    case "products": 
        require_once "./php/product-config/products.php";
        break;
    case "product-edit":
        require_once "./php/product-config/product-edit.php";
        break;
    case "product-save":
        require_once "./php/product-config/saving.php";
        break;
    case "product-delete": 
        require_once "./php/product-config/deleting.php";
        break;
    case "product-create":
        require_once "./php/product-config/creating.php";
        break;
    case "images":
        require_once "./php/product-config/images.php";
        break;
    case "translation":
        require_once "./php/locale-config/translation.php";
        break;
    case "backend-content-translation":
        require_once "./php/locale-config/language-edit.php";
        break;
    case "language-save":
        require_once "./php/locale-config/language-save.php";
        break;
    case "language-edit":
        require_once "./php/locale-config/language-edit.php";
        break;
    case "language-delete":
        require_once "./php/locale-config/language-delete.php";
        break;
}

function parsePage($page) {
    //echo $page;
    $partsOfPage = explode("/", $page);
    //echo var_dump($partsOfPage);
    $action = $partsOfPage[0];
    error_log("page: ".$page);
    $result = [];
    $id = null;
    $language = null;
    if (in_array($action, ACTIONS_WITH_ID)) {
        $id = intval($partsOfPage[1]); 
        if ($id <= 0) {
            $id = null;
        }
        $language = $partsOfPage[2];
    }
    else if (in_array($action, ACTIONS_WITH_FILENAME)) {
        $id = $partsOfPage[1];
        //$language = $partsOfPage[2];
    }
    else {
        if ($action != "login" && $action != "products" && $action != "product-create" && $action != "login-check" && $action != "translation" && $action != "backend-content-translation" && $action != "language-save") {
            $action = null;
        }
        $id = null;
        if ($action != "login" && $action != "login-check" && $action != "translation" && $action != "backend-content-translation") {
            $language = $partsOfPage[1];
        }
        
    }

    /*
    if (isset($partsOfPage[1])) {
        if (is_numeric($partsOfPage[1])) {
            if (in_array($action, ACTIONS_WITH_ID)) {
                $id = intval($partsOfPage[1]);
                if ($id <= 0) {
                    $id = null;
                }
            }
        }
        else {
            if (in_array($action, ACTIONS_WITH_FILENAME)) {
                $id = $partsOfPage[1];
            }
        }
    }
    else {
        if ($action != "login" && $action != "products" && $action != "product-create" && $action != "login-check" && $action != "translation" && $action != "backend-content-translation" && $action != "language-save") {
            $action = null;
        }
        $id = null;
    }
    */
    $result = ["action" => $action, "id" => $id, "language" => $language];
    error_log(json_encode($result));
    return $result;
}
