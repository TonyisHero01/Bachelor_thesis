<?php
if ($page["action"] == "products") {
    $html = str_replace(PRODUCT_LIST_TITLES[0], $decodedData["title"]["product_list"], $html);
    $sub_ELEMENT_IDS = array_slice(ELEMENT_IDS, 15, 8);
    for ($i=1; $i<count(PRODUCT_LIST_TITLES); $i++) {
        $element_id = $sub_ELEMENT_IDS[$i];
        error_log(PRODUCT_LIST_TITLES[$i]);
        error_log($decodedData["function"][$element_id]);
        $html = str_replace(PRODUCT_LIST_TITLES[$i], $decodedData["function"][$element_id], $html);
    }
}
if ($page["action"] == "product-edit") {
    $sub_ELEMENT_IDS = array_slice(ELEMENT_IDS, 3, 13);
    for ($i=0; $i<count(PRODUCT_PARAM_TITLES); $i++) {
        $element_id = $sub_ELEMENT_IDS[$i];
        $html = str_replace(PRODUCT_PARAM_TITLES[$i], $decodedData["product_manage"]["product_property"][$element_id], $html);
    }
    $sub_ELEMENT_IDS = array_slice(ELEMENT_IDS, 23, 2);

    for ($i=0; $i<count(BUTTON_CONTENT_TITLES_IN_PRODUCT_EDIT); $i++) {
        $element_id = $sub_ELEMENT_IDS[$i];
        $html = str_replace(BUTTON_CONTENT_TITLES_IN_PRODUCT_EDIT[$i], $decodedData["function"][$element_id], $html);
    }
}