<?php
require_once "./config/configuration.php";
require_once "./config/db_config.php";
require_once "constants.php";
require_once "model/product_DB_model.php";
require_once "model/product_model.php";
require_once "./model/login_info_model.php";
define("ACTIONS_WITH_ID", ["product", "product-edit", "product-save", "product-delete"]);
define("ACTIONS_WITH_FILENAME", ["language-edit", "language-delete"]);
error_log($config->DB_server);
$conn = new mysqli($config->DB_server, $config->DB_login, $config->DB_password, $config->DB);

//$conn = new mysqli($db_config["server"], $db_config["login"], $db_config["password"], $db_config["database"]);
if ($conn->connect_error) {
    print("Cannot connect to Databse!");
    exit();
}
$userDB = new Database("User", $conn, $config);
$database;

//echo $_GET["page"];
$page = parsePage($_GET["page"]);

function check_login($username, $password, $conn) {
    $stmt = $conn->prepare("SELECT * FROM User WHERE username = ? AND password = ?");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    if ($result->num_rows == 1) {
        [$username, $password, $position, $is_client] = $result->fetch_row();
        return new Login_Info($username, $password, $position, $is_client);
    }
    return false;
}


switch ($page["action"]) {
    case "login":
        require_once "./php/login-config/admin_login.php";
        break;
    case "login-check":
        require_once "./php/login-config/login-check.php";
        break;
    case "product":
        require_once "model/product_model.php";
        break;
    case "products": 
        $database = new Database("Product", $conn, $config);
        require_once "./php/product-config/products.php";
        break;
    case "product-edit":
        $database = new Database("Product", $conn, $config);
        require_once "./php/product-config/product-edit.php";
        break;
    case "product-save":
        $database = new Database("Product", $conn, $config);
        require_once "./php/product-config/saving.php";
        break;
    case "product-delete":
        $database = new Database("Product", $conn, $config);
        require_once "./php/product-config/deleting.php";
        break;
    case "product-create":
        $database = new Database("Product", $conn, $config);
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
        if ($action == "products" || $action == "product-edit") {
            $language = $partsOfPage[1];
        }
        
    }
    $result = ["action" => $action, "id" => $id, "language" => $language];
    error_log(json_encode($result));
    return $result;
}
