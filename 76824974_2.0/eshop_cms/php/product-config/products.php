<?php
require_once "constants.php";
require_once "config/db_config.php";
require_once "index.php";

try {
    
    
    $product_list_html = '';

    $filePath = "locale/" . $page["language"] . ".json";

    $jsonData = file_get_contents($filePath);

    $decodedData = json_decode($jsonData, true);
    require_once "./view/header.php";
    require_once "./view/products_template.php";
    require_once "./view/footer.html";
    
    //require_once "./php/locale-config/setting-language.php";

    //include($html);
    //echo $html;

} catch (Exception $e) {
    error_log("An error occurred: " . $e->getMessage());
}
?>