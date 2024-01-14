<?php
if (!isset($_SESSION) || !isset($_SESSION["admin"]) || $_SESSION["admin"] !== true) {
    error_log("user didnt log");
    //echo '<script>window.location.href = ' . APP_DIRECTORY . ' + /login;</script>';
    header("Location: " . APP_DIRECTORY . "login");
    exit();
} else {
    try {
        $product = $database->get_product_by_id($page["id"]);
    } catch (MissingIdException $mie) {
        http_response_code(404);
        exit();
    }
    $filePath = "locale/" . $page["language"] . ".json";
    
    $jsonData = file_get_contents($filePath);
    
    $decodedData = json_decode($jsonData, true);
    
    $html = file_get_contents("./html/product-edit_template.html");
    
    require_once "./php/locale-config/setting-language.php";
    
    $html = str_replace("{{APP_DIRECTORY}}",APP_DIRECTORY, $html);
    $html = str_replace("{{LANGUAGE}}", $page["language"], $html);
    $html = str_replace("{{PAGE_ID}}",$page["id"], $html);
    $html = str_replace("{{PRODUCT_ADD_TIME}}", $product->add_time, $html);
    $html = str_replace("{{NAME_MAX_LENGTH}}", NAME_MAX_LENGTH, $html);
    $html = str_replace("{{PRODUCT_NAME}}", $product->name, $html);
    $html = str_replace("{{PRODUCT_KATEGORY}}", $product->kategory, $html);
    $html = str_replace("{{PRODUCT_DESCRIPTION}}", $product->description, $html);
    $html = str_replace("{{PRODUCT_NUMBER_IN_STOCK}}", $product->number_in_stock, $html);
    $html = str_replace("{{IMAGE_URL}}", $product->image_url, $html);
    $html = str_replace("{{PRODUCT_WIDTH}}", $product->width, $html);
    $html = str_replace("{{PRODUCT_HEIGHT}}", $product->height, $html);
    $html = str_replace("{{PRODUCT_LENGTH}}", $product->length, $html);
    $html = str_replace("{{PRODUCT_WEIGHT}}", $product->weight, $html);
    $html = str_replace("{{PRODUCT_MATERIAL}}", $product->material, $html);
    $html = str_replace("{{PRODUCT_COLOR}}", $product->color, $html);
    $html = str_replace("{{PRODUCT_PRICE}}", $product->price, $html);
    echo $html;
}
?>