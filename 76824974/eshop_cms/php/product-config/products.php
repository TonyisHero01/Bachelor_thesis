<?php
require_once "constants.php";
require_once "config/db_config.php";
require_once "index.php";

function generateProductRow($product) {
    ob_start();?>
    <tr id="<?php echo $product->id?>">
        <td class="product-name"><?php echo $product->name?></td>
        <td class="number-in-stock"><?php echo $product->number_in_stock . "ks"?></td>
        <td class="add-time"><?php echo $product->add_time?></td>
        <td class="price"><?php echo $product->price?></td>
        <td class="button"><span class="edit-button" type="button" onclick="edit(<?php echo $product->id?>)">{{EDIT_BUTTON_TITLE}}</span></td>
        <td class="button"><span class="delete-button" type="button" onclick="delete_(<?php echo $product->id?>)">{{DELETE_BUTTON_TITLE}}</span></td>
    </tr>
    <?php
    return ob_get_clean();
}

try {
    $products = $database->get_all_product_based_info();
    $product_ids = [];
    $product_list_html = '';

    $filePath = "locale/" . $page["language"] . ".json";

    $jsonData = file_get_contents($filePath);

    $decodedData = json_decode($jsonData, true);

    foreach ($products as $product) {
        $product_ids[] = intval($product->id);
        $product_list_html .= generateProductRow($product);
    }

    $html = file_get_contents('html/products_template.html');

    

    $html = str_replace('{{LANGUAGE}}', $page["language"], $html);
    $html = str_replace('{{PRODUCT_LIST}}', $product_list_html, $html);
    $html = str_replace('{{PRODUCT_IDS}}', json_encode($product_ids), $html);
    $html = str_replace('{{NAME_MAX_LENGTH}}', NAME_MAX_LENGTH, $html);
    $html = str_replace('{{CONTENT_MAX_LENGTH}}', CONTENT_MAX_LENGTH, $html);
    $html = str_replace('{{APP_DIRECTORY}}', APP_DIRECTORY, $html);
    $html = str_replace('{{MAX_ARTICLES_COUNT_PER_PAGE}}', MAX_ARTICLES_COUNT_PER_PAGE, $html);
    require_once "./php/locale-config/setting-language.php";

    echo $html;
} catch (Exception $e) {
    error_log("An error occurred: " . $e->getMessage());
}
?>