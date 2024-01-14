<?php
require_once "constants.php";
require_once "./config/db_config.php";
require_once "index.php";



try {
    require_once "./view/header.php";
    require_once "./view/translation_template.php";
    require_once "./view/footer.html";
} catch (Exception $e){
    echo "An error occurred: " . $e->getMessage();
}
