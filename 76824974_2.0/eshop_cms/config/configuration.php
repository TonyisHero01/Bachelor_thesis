<?php
require_once "./model/configuration_model.php";
$DB_server = 'localhost';
$DB_login = 'TonyWang';
$DB_password = 'T1509517798w';
$DB = 'tony_76824974';
$NAME_MAX_LENGTH = 32;
$CONTENT_MAX_LENGTH = 1024;
$APP_DIRECTORY = "/76824974_2.0/eshop_cms/";
$MAX_ARTICLES_COUNT_PER_PAGE = 10;
$HTML_TRANSLATION_ELEMENTS_TO_CHANGE = ["{{LANGUAGE}}", "{{USERNAME}}", "{{PASSWORD}}", "{{PRODUCT_NAME}}", "{{ADD_TIME}}", "{{KATEGORY}}", "{{DESCRIPTION}}", "{{NUMBER_IN_STOCK}}", "{{IMAGE}}", "{{WIDTH}}", "{{HEIGHT}}", "{{LENGTH}}", "{{WEIGHT}}", "{{MATERIAL}}", "{{COLOR}}", "{{PRICE}}", "{{EDIT}}", "{{DELETE}}", "{{CREATE_PRODUCT}}", "{{CREATE}}", "{{CANCEL}}", "{{NEXT}}", "{{PREVIOUS}}", "{{SAVE}}", "{{BACK_TO_PRODUCT_LIST}}", "{{LOGIN}}", "{{PRODUCT_LIST}}"];
$ELEMENT_PARAMS = ["language", "username", "password", "product_name", "add_time", "kategory", "description", "number_in_stock", "image", "width", "height", "length", "weight", "material", "color", "price", "edit", "delete", "create_product", "create", "cancel", "next", "previous", "save", "back_to_product_list", "login", "product_list"];
$PRODUCT_PARAM_TITLES = ["{{NAME_TITLE}}", "{{ADD_TIME_TITLE}}", "{{KATEGORY_TITLE}}", "{{DESCRIPTION_TITLE}}", "{{NUMBER_IN_STOCK_TITLE}}", "{{IMAGE_TITLE}}", "{{WIDTH_TITLE}}", "{{HEIGHT_TITLE}}", "{{LENGTH_TITLE}}", "{{WEIGHT_TITLE}}", "{{MATERIAL_TITLE}}", "{{COLOR_TITLE}}", "{{PRICE_TITLE}}"];
$BUTTON_CONTENT_TITLES_IN_PRODUCT_EDIT = ["{{SAVE_BUTTON_TITLE}}", "{{BACK_TO_PRODUCTS_TITLE}}"];
$PRODUCT_LIST_TITLES = ["{{PRODUCT_LIST_TITLE}}", "{{EDIT_BUTTON_TITLE}}", "{{DELETE_BUTTON_TITLE}}", "{{CREATE_PRODUCT_BUTTON_TITLE}}", "{{CREATE_BUTTON_TITLE}}", "{{CANCEL_BUTTON_TITLE}}", "{{NEXT_BUTTON_TITLE}}", "{{PREVIOUS_BUTTON_TITLE}}"];
$PRODUCT_MANAGER = "product_manager";
$config = new Configuration(
    $DB_server, $DB_login, $DB_password, $DB, $NAME_MAX_LENGTH, 
    $CONTENT_MAX_LENGTH, $APP_DIRECTORY, $MAX_ARTICLES_COUNT_PER_PAGE,
    $HTML_TRANSLATION_ELEMENTS_TO_CHANGE, $ELEMENT_PARAMS, $PRODUCT_PARAM_TITLES,
    $BUTTON_CONTENT_TITLES_IN_PRODUCT_EDIT, $PRODUCT_LIST_TITLES, $PRODUCT_MANAGER
);